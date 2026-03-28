<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class AgentTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'list_agents',
            description: 'Список агентов Битрикса с фильтром',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'filter' => ['type' => 'object',  'description' => 'Фильтр (MODULE_ID, ACTIVE, NAME и др.)'],
                    'order'  => ['type' => 'object',  'description' => 'Сортировка, по умолчанию {"ID":"DESC"}'],
                    'limit'  => ['type' => 'integer', 'description' => 'Лимит (по умолчанию 20)'],
                ],
                'properties' => [],
            ],
            handler: [$self, 'listAgents']
        );

        $server->addTool(
            name: 'get_agent',
            description: 'Получить агент по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID агента'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'getAgent']
        );

        $server->addTool(
            name: 'add_agent',
            description: 'Создать агент. Поле name — строка вызова PHP-функции, напр. "MyAgent();". Функция должна возвращать своё имя для перезапуска или пустую строку для однократного запуска.',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'name'      => ['type' => 'string',  'description' => 'PHP-строка вызова, напр. "\\MyModule\\Agent::run();"'],
                    'module_id' => ['type' => 'string',  'description' => 'ID модуля'],
                    'interval'  => ['type' => 'integer', 'description' => 'Интервал в секундах (по умолчанию 86400)'],
                    'active'    => ['type' => 'boolean', 'description' => 'Активен (по умолчанию true)'],
                    'is_period' => ['type' => 'boolean', 'description' => 'Считать интервал от NEXT_EXEC, а не от фактического запуска'],
                    'sort'      => ['type' => 'integer', 'description' => 'Сортировка (по умолчанию 100)'],
                    'next_exec' => ['type' => 'string',  'description' => 'Время первого запуска (DD.MM.YYYY HH:MM:SS), по умолчанию сейчас'],
                ],
                'required' => ['name'],
            ],
            handler: [$self, 'addAgent'],
            mutating: true
        );

        $server->addTool(
            name: 'update_agent',
            description: 'Обновить агент по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'        => ['type' => 'integer', 'description' => 'ID агента'],
                    'name'      => ['type' => 'string',  'description' => 'PHP-строка вызова'],
                    'interval'  => ['type' => 'integer', 'description' => 'Интервал в секундах'],
                    'active'    => ['type' => 'boolean', 'description' => 'Активен'],
                    'is_period' => ['type' => 'boolean', 'description' => 'IS_PERIOD'],
                    'sort'      => ['type' => 'integer', 'description' => 'Сортировка'],
                    'next_exec' => ['type' => 'string',  'description' => 'Следующий запуск (DD.MM.YYYY HH:MM:SS)'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'updateAgent'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_agent',
            description: 'Удалить агент по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID агента'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'deleteAgent'],
            mutating: true
        );

        $server->addTool(
            name: 'run_agent',
            description: 'Запланировать немедленный запуск агента (устанавливает NEXT_EXEC = сейчас). Агент выполнится при следующем хите или cron-цикле.',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID агента'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'runAgent'],
            mutating: true
        );
    }

    public function listAgents(array $args): array
    {
        $order  = $args['order']  ?? ['ID' => 'DESC'];
        $filter = $args['filter'] ?? [];
        $limit  = (int) ($args['limit'] ?? 20);

        $rs     = \CAgent::GetList($order, $filter);
        $result = [];
        $count  = 0;
        while ($row = $rs->Fetch()) {
            $result[] = $row;
            if (++$count >= $limit) {
                break;
            }
        }

        return $result;
    }

    public function getAgent(array $args): array
    {
        $rs  = \CAgent::GetById((int) $args['id']);
        $row = $rs->Fetch();

        if (!$row) {
            throw new \RuntimeException("Агент с ID {$args['id']} не найден");
        }

        return $row;
    }

    public function addAgent(array $args): array
    {
        $fields = [
            'MODULE_ID'      => $args['module_id'] ?? '',
            'NAME'           => $args['name'],
            'ACTIVE'         => ($args['active'] ?? true) ? 'Y' : 'N',
            'AGENT_INTERVAL' => (int) ($args['interval'] ?? 86400),
            'IS_PERIOD'      => ($args['is_period'] ?? false) ? 'Y' : 'N',
            'SORT'           => (int) ($args['sort'] ?? 100),
        ];

        if (!empty($args['next_exec'])) {
            $fields['NEXT_EXEC'] = $args['next_exec'];
        }

        $id = \CAgent::Add($fields);

        if (!$id) {
            throw new \RuntimeException("Не удалось создать агент");
        }

        return ['success' => true, 'id' => (int) $id];
    }

    public function updateAgent(array $args): array
    {
        $fields = [];

        if (isset($args['name']))      $fields['NAME']           = $args['name'];
        if (isset($args['interval']))  $fields['AGENT_INTERVAL'] = (int) $args['interval'];
        if (isset($args['active']))    $fields['ACTIVE']         = $args['active'] ? 'Y' : 'N';
        if (isset($args['is_period'])) $fields['IS_PERIOD']      = $args['is_period'] ? 'Y' : 'N';
        if (isset($args['sort']))      $fields['SORT']           = (int) $args['sort'];
        if (isset($args['next_exec'])) $fields['NEXT_EXEC']      = $args['next_exec'];

        $result = \CAgent::Update((int) $args['id'], $fields);

        return ['success' => (bool) $result];
    }

    public function deleteAgent(array $args): array
    {
        $result = \CAgent::Delete((int) $args['id']);

        return ['success' => (bool) $result];
    }

    public function runAgent(array $args): array
    {
        $result = \CAgent::Update((int) $args['id'], [
            'NEXT_EXEC'  => date('d.m.Y H:i:s', time()),
            'DATE_CHECK' => '',
            'ACTIVE'     => 'Y',
        ]);

        return ['success' => (bool) $result, 'note' => 'Агент запустится при следующем хите или cron-цикле.'];
    }
}
