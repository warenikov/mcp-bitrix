<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class OptionTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'get_option',
            description: 'Получить значение настройки модуля Битрикса',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'module_id' => ['type' => 'string', 'description' => 'ID модуля (например, "main", "iblock")'],
                    'name'      => ['type' => 'string', 'description' => 'Имя настройки'],
                    'default'   => ['type' => 'string', 'description' => 'Значение по умолчанию, если настройка не найдена'],
                    'site_id'   => ['type' => 'string', 'description' => 'ID сайта (необязательно)'],
                ],
                'required' => ['module_id', 'name'],
            ],
            handler: [$self, 'getOption']
        );

        $server->addTool(
            name: 'set_option',
            description: 'Установить значение настройки модуля Битрикса',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'module_id' => ['type' => 'string', 'description' => 'ID модуля'],
                    'name'      => ['type' => 'string', 'description' => 'Имя настройки'],
                    'value'     => ['type' => 'string', 'description' => 'Новое значение'],
                    'site_id'   => ['type' => 'string', 'description' => 'ID сайта (необязательно)'],
                ],
                'required' => ['module_id', 'name', 'value'],
            ],
            handler: [$self, 'setOption'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_option',
            description: 'Удалить настройку модуля Битрикса',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'module_id' => ['type' => 'string', 'description' => 'ID модуля'],
                    'name'      => ['type' => 'string', 'description' => 'Имя настройки'],
                    'site_id'   => ['type' => 'string', 'description' => 'ID сайта (необязательно)'],
                ],
                'required' => ['module_id', 'name'],
            ],
            handler: [$self, 'deleteOption'],
            mutating: true
        );

        $server->addTool(
            name: 'list_options',
            description: 'Список настроек модуля Битрикса',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'module_id' => ['type' => 'string', 'description' => 'ID модуля'],
                    'site_id'   => ['type' => 'string', 'description' => 'ID сайта (необязательно)'],
                ],
                'required' => ['module_id'],
            ],
            handler: [$self, 'listOptions']
        );
    }

    public function getOption(array $args): array
    {
        $moduleId = $args['module_id'];
        $name     = $args['name'];
        $default  = $args['default'] ?? '';
        $siteId   = $args['site_id'] ?? '';

        $value = \Bitrix\Main\Config\Option::get($moduleId, $name, $default, $siteId);

        return ['module_id' => $moduleId, 'name' => $name, 'value' => $value];
    }

    public function setOption(array $args): array
    {
        $moduleId = $args['module_id'];
        $name     = $args['name'];
        $value    = $args['value'];
        $siteId   = $args['site_id'] ?? '';

        \Bitrix\Main\Config\Option::set($moduleId, $name, $value, $siteId);

        return ['success' => true, 'module_id' => $moduleId, 'name' => $name, 'value' => $value];
    }

    public function deleteOption(array $args): array
    {
        $moduleId = $args['module_id'];
        $name     = $args['name'];
        $siteId   = $args['site_id'] ?? null;

        if ($siteId !== null) {
            \Bitrix\Main\Config\Option::delete($moduleId, ['name' => $name, 'site_id' => $siteId]);
        } else {
            \Bitrix\Main\Config\Option::delete($moduleId, ['name' => $name]);
        }

        return ['success' => true];
    }

    public function listOptions(array $args): array
    {
        $moduleId = $args['module_id'];
        $siteId   = $args['site_id'] ?? '';

        $raw    = \Bitrix\Main\Config\Option::getForModule($moduleId, $siteId);
        $result = [];
        foreach ($raw as $name => $value) {
            $result[] = ['NAME' => $name, 'VALUE' => $value];
        }

        return $result;
    }
}
