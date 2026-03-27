<?php

namespace Warenikov\McpBitrix\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Warenikov\McpBitrix\Server;

/**
 * Тестовый стаб: пропускает инициализацию Битрикса и регистрирует
 * только тестовые инструменты без реальных зависимостей.
 */
class TestServer extends Server
{
    protected function bootstrap(): void
    {
        // Битрикс не нужен в unit-тестах
    }

    protected function registerTools(): void
    {
        $this->addTool(
            name: 'read_tool',
            description: 'Read-only tool',
            inputSchema: ['type' => 'object', 'properties' => []],
            handler: fn(array $args) => ['result' => 'ok'],
            mutating: false
        );

        $this->addTool(
            name: 'write_tool',
            description: 'Mutating tool',
            inputSchema: ['type' => 'object', 'properties' => []],
            handler: fn(array $args) => ['result' => 'written'],
            mutating: true
        );

        $this->addTool(
            name: 'echo_tool',
            description: 'Returns args back',
            inputSchema: ['type' => 'object', 'properties' => ['value' => ['type' => 'string']]],
            handler: fn(array $args) => ['echo' => $args['value'] ?? null],
            mutating: false
        );
    }

    /** Тестовый хелпер: отправить одно сообщение и получить ответ */
    public function send(array $message): ?array
    {
        $input = fopen('php://memory', 'r+');
        fwrite($input, json_encode($message) . "\n");
        rewind($input);

        $output = fopen('php://memory', 'r+');

        $this->runOnStreams($input, $output);

        rewind($output);
        $line = fgets($output);

        fclose($input);
        fclose($output);

        return $line ? json_decode(trim($line), true) : null;
    }
}

class ServerTest extends TestCase
{
    private TestServer $server;

    protected function setUp(): void
    {
        putenv('BITRIX_READONLY=');
        $this->server = new TestServer();
    }

    // --- initialize ---

    public function testInitializeReturnsProtocolVersion(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2024-11-05', 'capabilities' => []],
        ]);

        $this->assertEquals('2024-11-05', $response['result']['protocolVersion']);
        $this->assertEquals('mcp-bitrix', $response['result']['serverInfo']['name']);
    }

    public function testInitializeResponseHasToolsCapability(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2024-11-05', 'capabilities' => []],
        ]);

        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
    }

    // --- tools/list ---

    public function testToolsListReturnsAllRegisteredTools(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
            'params'  => [],
        ]);

        $names = array_column($response['result']['tools'], 'name');
        $this->assertContains('read_tool', $names);
        $this->assertContains('write_tool', $names);
        $this->assertContains('echo_tool', $names);
        $this->assertCount(3, $names);
    }

    public function testToolsListIncludesInputSchema(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
            'params'  => [],
        ]);

        foreach ($response['result']['tools'] as $tool) {
            $this->assertArrayHasKey('inputSchema', $tool);
        }
    }

    // --- tools/call ---

    public function testToolCallReturnsResult(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/call',
            'params'  => ['name' => 'read_tool', 'arguments' => []],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertStringContainsString('ok', $response['result']['content'][0]['text']);
    }

    public function testToolCallPassesArguments(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/call',
            'params'  => ['name' => 'echo_tool', 'arguments' => ['value' => 'hello']],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertStringContainsString('hello', $response['result']['content'][0]['text']);
    }

    public function testToolCallUnknownToolReturnsError(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/call',
            'params'  => ['name' => 'nonexistent_tool', 'arguments' => []],
        ]);

        $this->assertEquals(-32602, $response['error']['code']);
    }

    public function testToolCallExceptionReturnsIsError(): void
    {
        // Регистрируем инструмент который кидает исключение
        $this->server->addTool(
            name: 'broken_tool',
            description: 'Always throws',
            inputSchema: ['type' => 'object', 'properties' => []],
            handler: function (array $args): never {
                throw new \RuntimeException('Something went wrong');
            }
        );

        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/call',
            'params'  => ['name' => 'broken_tool', 'arguments' => []],
        ]);

        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('Something went wrong', $response['result']['content'][0]['text']);
    }

    // --- readonly ---

    public function testReadonlyBlocksMutatingTool(): void
    {
        putenv('BITRIX_READONLY=true');
        $server = new TestServer();

        $response = $server->send([
            'jsonrpc' => '2.0',
            'id'      => 4,
            'method'  => 'tools/call',
            'params'  => ['name' => 'write_tool', 'arguments' => []],
        ]);

        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('readonly', $response['result']['content'][0]['text']);
    }

    public function testReadonlyAllowsReadTool(): void
    {
        putenv('BITRIX_READONLY=true');
        $server = new TestServer();

        $response = $server->send([
            'jsonrpc' => '2.0',
            'id'      => 4,
            'method'  => 'tools/call',
            'params'  => ['name' => 'read_tool', 'arguments' => []],
        ]);

        $this->assertFalse($response['result']['isError']);
    }

    public function testReadonlyPrefixesWriteToolDescription(): void
    {
        putenv('BITRIX_READONLY=true');
        $server = new TestServer();

        $response = $server->send([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
            'params'  => [],
        ]);

        $tools = array_column($response['result']['tools'], 'description', 'name');
        $this->assertStringStartsWith('[READONLY]', $tools['write_tool']);
        $this->assertStringNotContainsString('[READONLY]', $tools['read_tool']);
    }

    // --- unknown method ---

    public function testUnknownMethodReturnsMethodNotFound(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 5,
            'method'  => 'unknown/method',
            'params'  => [],
        ]);

        $this->assertEquals(-32601, $response['error']['code']);
    }

    // --- notifications ---

    public function testNotificationHasNoResponse(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'method'  => 'notifications/initialized',
            'params'  => [],
        ]);

        $this->assertNull($response);
    }

    // --- ping ---

    public function testPingReturnsOk(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 6,
            'method'  => 'ping',
            'params'  => [],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayNotHasKey('error', $response);
    }

    // --- JSON-RPC формат ---

    public function testResponseAlwaysHasJsonrpcField(): void
    {
        $response = $this->server->send([
            'jsonrpc' => '2.0',
            'id'      => 7,
            'method'  => 'ping',
            'params'  => [],
        ]);

        $this->assertEquals('2.0', $response['jsonrpc']);
    }

    public function testResponsePreservesRequestId(): void
    {
        foreach ([1, 'abc', 42] as $id) {
            $response = $this->server->send([
                'jsonrpc' => '2.0',
                'id'      => $id,
                'method'  => 'ping',
                'params'  => [],
            ]);

            $this->assertEquals($id, $response['id']);
        }
    }
}
