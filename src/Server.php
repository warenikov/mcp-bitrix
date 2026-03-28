<?php

namespace Warenikov\McpBitrix;

use Warenikov\McpBitrix\Tools\IblockTypeTools;
use Warenikov\McpBitrix\Tools\IblockTools;
use Warenikov\McpBitrix\Tools\IblockPropertyTools;
use Warenikov\McpBitrix\Tools\IblockSectionTools;
use Warenikov\McpBitrix\Tools\IblockElementTools;
use Warenikov\McpBitrix\Tools\HlblockTools;

class Server
{
    private const VERSION = '1.0.0';
    private const PROTOCOL_VERSION = '2024-11-05';

    /** @var array<string, array{definition: array, handler: callable, mutating: bool}> */
    private array $tools = [];

    private bool $readonly;

    public function __construct()
    {
        $this->readonly = filter_var(getenv('BITRIX_READONLY'), FILTER_VALIDATE_BOOLEAN);
        $this->bootstrap();
        $this->registerTools();
    }

    protected function bootstrap(): void
    {
        BitrixBootstrap::init();
    }

    protected function registerTools(): void
    {
        IblockTypeTools::register($this);
        IblockTools::register($this);
        IblockPropertyTools::register($this);
        IblockSectionTools::register($this);
        IblockElementTools::register($this);
        HlblockTools::register($this);
    }

    public function addTool(
        string $name,
        string $description,
        array $inputSchema,
        callable $handler,
        bool $mutating = false
    ): void {
        $fullDescription = $mutating && $this->readonly
            ? '[READONLY] ' . $description
            : $description;

        $this->tools[$name] = [
            'definition' => [
                'name'        => $name,
                'description' => $fullDescription,
                'inputSchema' => $this->normalizeSchema($inputSchema),
            ],
            'handler'  => $handler,
            'mutating' => $mutating,
        ];
    }

    public function run(): void
    {
        $this->runOnStreams(STDIN, STDOUT);
    }

    public function runOnStreams(mixed $input, mixed $output): void
    {
        while (($line = fgets($input)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $request = json_decode($line, true);
            if ($request === null) {
                continue;
            }

            $response = $this->dispatch($request);
            if ($response !== null) {
                fwrite($output, json_encode($response, JSON_UNESCAPED_UNICODE) . "\n");
                fflush($output);
            }
        }
    }

    private function dispatch(array $request): ?array
    {
        $method = $request['method'] ?? '';
        $id     = $request['id'] ?? null;

        // Уведомления не требуют ответа
        if ($id === null && str_starts_with($method, 'notifications/')) {
            return null;
        }

        try {
            return match ($method) {
                'initialize'    => $this->handleInitialize($id),
                'tools/list'    => $this->handleToolsList($id),
                'tools/call'    => $this->handleToolCall($request, $id),
                'ping'          => $this->okResponse($id, []),
                default         => $this->errorResponse($id, -32601, "Method not found: {$method}"),
            };
        } catch (\Throwable $e) {
            return $this->errorResponse($id, -32603, $e->getMessage());
        }
    }

    private function handleInitialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities'    => ['tools' => ['listChanged' => false]],
                'serverInfo'      => ['name' => 'mcp-bitrix', 'version' => self::VERSION],
            ],
        ];
    }

    private function handleToolsList(mixed $id): array
    {
        $tools = array_map(fn($t) => $t['definition'], array_values($this->tools));

        return $this->okResponse($id, ['tools' => $tools]);
    }

    private function handleToolCall(array $request, mixed $id): array
    {
        $name      = $request['params']['name'] ?? '';
        $arguments = $request['params']['arguments'] ?? [];

        if (!isset($this->tools[$name])) {
            return $this->errorResponse($id, -32602, "Unknown tool: {$name}");
        }

        if ($this->readonly && $this->tools[$name]['mutating']) {
            return $this->okResponse($id, [
                'content' => [['type' => 'text', 'text' => "Сервер запущен в режиме readonly. Операция «{$name}» запрещена."]],
                'isError'  => true,
            ]);
        }

        // Перехватываем любой вывод из Битрикса во время вызова инструмента
        ob_start();
        try {
            $result = ($this->tools[$name]['handler'])($arguments);
            ob_end_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->okResponse($id, [
                'content' => [['type' => 'text', 'text' => 'Ошибка: ' . $e->getMessage()]],
                'isError'  => true,
            ]);
        }

        $text = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $this->okResponse($id, [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError'  => false,
        ]);
    }

    private function normalizeSchema(array $schema): array
    {
        if (isset($schema['properties']) && is_array($schema['properties']) && empty($schema['properties'])) {
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }

    private function okResponse(mixed $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
