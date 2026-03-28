<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class EventLogTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'list_event_log',
            description: 'Список записей системного журнала событий Битрикса',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'filter' => [
                        'type'        => 'object',
                        'description' => 'Фильтр: SEVERITY (DEBUG/INFO/WARNING/ERROR/SECURITY), MODULE_ID, AUDIT_TYPE_ID, USER_ID, SITE_ID, DATE (дата в формате DD.MM.YYYY)',
                    ],
                    'order'  => ['type' => 'object',  'description' => 'Сортировка, по умолчанию {"ID":"DESC"}'],
                    'limit'  => ['type' => 'integer', 'description' => 'Лимит (по умолчанию 20)'],
                ],
                'properties' => [],
            ],
            handler: [$self, 'listEventLog']
        );

        $server->addTool(
            name: 'get_event_log',
            description: 'Получить запись журнала событий по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID записи'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'getEventLog']
        );

        $server->addTool(
            name: 'add_event_log',
            description: 'Добавить запись в системный журнал событий',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'severity'       => ['type' => 'string',  'description' => 'Уровень: DEBUG, INFO, WARNING, ERROR, SECURITY (по умолчанию INFO)'],
                    'audit_type_id'  => ['type' => 'string',  'description' => 'Тип аудита (например, "MCP_ACTION")'],
                    'module_id'      => ['type' => 'string',  'description' => 'ID модуля'],
                    'item_id'        => ['type' => 'string',  'description' => 'ID объекта'],
                    'description'    => ['type' => 'string',  'description' => 'Описание события'],
                ],
                'required' => ['audit_type_id', 'description'],
            ],
            handler: [$self, 'addEventLog'],
            mutating: true
        );

        $server->addTool(
            name: 'clear_event_log',
            description: 'Очистить системный журнал событий (удалить записи старше указанного количества дней)',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'older_than_days' => ['type' => 'integer', 'description' => 'Удалить записи старше N дней (по умолчанию 30)'],
                ],
                'properties' => [],
            ],
            handler: [$self, 'clearEventLog'],
            mutating: true
        );
    }

    public function listEventLog(array $args): array
    {
        $order  = $args['order']  ?? ['ID' => 'DESC'];
        $filter = $args['filter'] ?? [];
        $limit  = (int) ($args['limit'] ?? 20);

        $rs     = \CEventLog::GetList($order, $filter);
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

    public function getEventLog(array $args): array
    {
        $rs  = \CEventLog::GetById((int) $args['id']);
        $row = $rs->Fetch();

        if (!$row) {
            throw new \RuntimeException("Запись журнала с ID {$args['id']} не найдена");
        }

        return $row;
    }

    public function addEventLog(array $args): array
    {
        $severity  = strtoupper($args['severity'] ?? 'INFO');
        $validSeverities = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'SECURITY'];
        if (!in_array($severity, $validSeverities, true)) {
            $severity = 'INFO';
        }

        \CEventLog::Add([
            'SEVERITY'      => $severity,
            'AUDIT_TYPE_ID' => $args['audit_type_id'],
            'MODULE_ID'     => $args['module_id'] ?? 'mcp_bitrix',
            'ITEM_ID'       => $args['item_id'] ?? '',
            'DESCRIPTION'   => $args['description'],
        ]);

        return ['success' => true];
    }

    public function clearEventLog(array $args): array
    {
        $days    = (int) ($args['older_than_days'] ?? 30);
        $cutoff  = date('d.m.Y', strtotime("-{$days} days"));

        \CEventLog::ClearByDate($cutoff);

        return ['success' => true, 'cleared_before' => $cutoff];
    }
}
