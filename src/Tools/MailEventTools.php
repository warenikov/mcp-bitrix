<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class MailEventTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        // ── Типы событий ──────────────────────────────────────────────────────

        $server->addTool(
            name: 'list_mail_event_types',
            description: 'Список типов почтовых событий Битрикса',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'module_id' => ['type' => 'string', 'description' => 'Фильтр по ID модуля (необязательно)'],
                    'lang'      => ['type' => 'string', 'description' => 'Код языка, по умолчанию ru'],
                ],
                'properties' => [],
            ],
            handler: [$self, 'listMailEventTypes']
        );

        $server->addTool(
            name: 'get_mail_event_type',
            description: 'Получить тип почтового события по символьному коду',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'event_name' => ['type' => 'string', 'description' => 'Символьный код события (EVENT_NAME)'],
                    'lang'       => ['type' => 'string', 'description' => 'Код языка, по умолчанию ru'],
                ],
                'required' => ['event_name'],
            ],
            handler: [$self, 'getMailEventType']
        );

        // ── Шаблоны ───────────────────────────────────────────────────────────

        $server->addTool(
            name: 'list_mail_templates',
            description: 'Список шаблонов почтовых событий',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'filter' => [
                        'type'        => 'object',
                        'description' => 'Фильтр: EVENT_NAME, ACTIVE, SITE_ID и др.',
                    ],
                    'limit'  => ['type' => 'integer', 'description' => 'Лимит (по умолчанию 20)'],
                ],
                'properties' => [],
            ],
            handler: [$self, 'listMailTemplates']
        );

        $server->addTool(
            name: 'get_mail_template',
            description: 'Получить шаблон почтового события по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID шаблона'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'getMailTemplate']
        );

        $server->addTool(
            name: 'add_mail_template',
            description: 'Создать шаблон почтового события',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'event_name' => ['type' => 'string',  'description' => 'Символьный код события (EVENT_NAME)'],
                    'subject'    => ['type' => 'string',  'description' => 'Тема письма (может содержать макросы #FIELD#)'],
                    'body'       => ['type' => 'string',  'description' => 'Тело письма (HTML или текст)'],
                    'to'         => ['type' => 'string',  'description' => 'Поле TO — адрес получателя или макрос'],
                    'body_type'  => ['type' => 'string',  'description' => 'Тип: html или text (по умолчанию html)'],
                    'from'       => ['type' => 'string',  'description' => 'Поле FROM (по умолчанию #DEFAULT_EMAIL_FROM#)'],
                    'active'     => ['type' => 'boolean', 'description' => 'Активен (по умолчанию true)'],
                    'site_id'    => ['type' => 'array',   'description' => 'Массив ID сайтов (по умолчанию ["s1"])'],
                ],
                'required' => ['event_name', 'subject', 'body', 'to'],
            ],
            handler: [$self, 'addMailTemplate'],
            mutating: true
        );

        $server->addTool(
            name: 'update_mail_template',
            description: 'Обновить шаблон почтового события',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'        => ['type' => 'integer', 'description' => 'ID шаблона'],
                    'subject'   => ['type' => 'string',  'description' => 'Тема письма'],
                    'body'      => ['type' => 'string',  'description' => 'Тело письма'],
                    'body_type' => ['type' => 'string',  'description' => 'Тип: html или text'],
                    'from'      => ['type' => 'string',  'description' => 'Поле FROM'],
                    'to'        => ['type' => 'string',  'description' => 'Поле TO'],
                    'active'    => ['type' => 'boolean', 'description' => 'Активен'],
                    'site_id'   => ['type' => 'array',   'description' => 'Массив ID сайтов'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'updateMailTemplate'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_mail_template',
            description: 'Удалить шаблон почтового события',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID шаблона'],
                ],
                'required' => ['id'],
            ],
            handler: [$self, 'deleteMailTemplate'],
            mutating: true
        );
    }

    // ── Типы событий ──────────────────────────────────────────────────────────

    public function listMailEventTypes(array $args): array
    {
        $lang     = $args['lang'] ?? 'ru';
        $moduleId = $args['module_id'] ?? false;

        $filter = ['LID' => $lang];
        if ($moduleId) {
            $filter['MODULE_ID'] = $moduleId;
        }

        $rs     = \CEventType::GetList(['MODULE_ID' => 'ASC'], $filter);
        $result = [];
        while ($row = $rs->Fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function getMailEventType(array $args): array
    {
        $lang      = $args['lang'] ?? 'ru';
        $eventName = $args['event_name'];

        $rs = \CEventType::GetList(['MODULE_ID' => 'ASC'], ['LID' => $lang]);
        while ($row = $rs->Fetch()) {
            if ($row['EVENT_NAME'] === $eventName) {
                return $row;
            }
        }

        throw new \RuntimeException("Тип события «{$eventName}» не найден");
    }

    // ── Шаблоны ───────────────────────────────────────────────────────────────

    public function listMailTemplates(array $args): array
    {
        $filter = $args['filter'] ?? [];
        $limit  = (int) ($args['limit'] ?? 20);

        $rs     = \CEventMessage::GetList(['ID' => 'DESC'], $filter);
        $result = [];
        $count  = 0;
        while ($row = $rs->Fetch()) {
            $result[] = $this->normalizeRow($row);
            if (++$count >= $limit) {
                break;
            }
        }

        return $result;
    }

    public function getMailTemplate(array $args): array
    {
        $rs  = \CEventMessage::GetById((int) $args['id']);
        $row = $rs->Fetch();

        if (!$row) {
            throw new \RuntimeException("Шаблон с ID {$args['id']} не найден");
        }

        return $this->normalizeRow($row);
    }

    public function addMailTemplate(array $args): array
    {
        $fields = [
            'EVENT_NAME' => $args['event_name'],
            'LID'        => $args['site_id'] ?? ['s1'],
            'ACTIVE'     => ($args['active'] ?? true) ? 'Y' : 'N',
            'EMAIL_FROM' => $args['from'] ?? '#DEFAULT_EMAIL_FROM#',
            'EMAIL_TO'   => $args['to'],
            'SUBJECT'    => $args['subject'],
            'MESSAGE'    => $args['body'],
            'BODY_TYPE'  => strtolower($args['body_type'] ?? 'html') === 'text' ? 'text' : 'html',
        ];

        $message = new \CEventMessage();
        $id      = $message->Add($fields);

        if (!$id) {
            $error = $message->LAST_ERROR ?? 'Неизвестная ошибка';
            throw new \RuntimeException("Не удалось создать шаблон: {$error}");
        }

        return ['success' => true, 'id' => (int) $id];
    }

    public function updateMailTemplate(array $args): array
    {
        $fields = [];

        if (isset($args['active']))    $fields['ACTIVE']     = $args['active'] ? 'Y' : 'N';
        if (isset($args['from']))      $fields['EMAIL_FROM'] = $args['from'];
        if (isset($args['to']))        $fields['EMAIL_TO']   = $args['to'];
        if (isset($args['subject']))   $fields['SUBJECT']    = $args['subject'];
        if (isset($args['body']))      $fields['MESSAGE']    = $args['body'];
        if (isset($args['site_id']))   $fields['LID']        = $args['site_id'];
        if (isset($args['body_type'])) $fields['BODY_TYPE']  = strtolower($args['body_type']) === 'text' ? 'text' : 'html';

        $message = new \CEventMessage();
        $result  = $message->Update((int) $args['id'], $fields);

        return ['success' => (bool) $result];
    }

    public function deleteMailTemplate(array $args): array
    {
        $result = \CEventMessage::Delete((int) $args['id']);

        return ['success' => (bool) $result];
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \Bitrix\Main\Type\DateTime || $value instanceof \Bitrix\Main\Type\Date) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        return $row;
    }
}
