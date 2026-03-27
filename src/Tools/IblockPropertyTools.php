<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class IblockPropertyTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'list_iblock_properties',
            description: 'Получить список свойств инфоблока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'iblock_id' => ['type' => 'integer', 'description' => 'ID инфоблока'],
                ],
                'required'   => ['iblock_id'],
            ],
            handler: [$self, 'listProperties']
        );

        $server->addTool(
            name: 'add_iblock_property',
            description: 'Добавить свойство к инфоблоку',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'iblock_id'     => ['type' => 'integer', 'description' => 'ID инфоблока'],
                    'name'          => ['type' => 'string',  'description' => 'Название свойства'],
                    'code'          => ['type' => 'string',  'description' => 'Символьный код (латиница)'],
                    'property_type' => [
                        'type'        => 'string',
                        'description' => 'Тип: S (строка), N (число), L (список), F (файл), G (секция), E (привязка к элементу)',
                        'enum'        => ['S', 'N', 'L', 'F', 'G', 'E'],
                    ],
                    'multiple'      => ['type' => 'boolean', 'description' => 'Множественное значение'],
                    'required'      => ['type' => 'boolean', 'description' => 'Обязательное'],
                    'sort'          => ['type' => 'integer', 'description' => 'Сортировка'],
                    'default_value' => ['type' => 'string',  'description' => 'Значение по умолчанию'],
                ],
                'required'   => ['iblock_id', 'name', 'code', 'property_type'],
            ],
            handler: [$self, 'addProperty'],
            mutating: true
        );

        $server->addTool(
            name: 'update_iblock_property',
            description: 'Обновить свойство инфоблока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'            => ['type' => 'integer', 'description' => 'ID свойства'],
                    'name'          => ['type' => 'string',  'description' => 'Название'],
                    'code'          => ['type' => 'string',  'description' => 'Символьный код'],
                    'sort'          => ['type' => 'integer', 'description' => 'Сортировка'],
                    'multiple'      => ['type' => 'boolean', 'description' => 'Множественное'],
                    'required'      => ['type' => 'boolean', 'description' => 'Обязательное'],
                    'default_value' => ['type' => 'string',  'description' => 'Значение по умолчанию'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'updateProperty'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_iblock_property',
            description: 'Удалить свойство инфоблока по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID свойства'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'deleteProperty'],
            mutating: true
        );
    }

    public function listProperties(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $result = [];
        $rs = \CIBlockProperty::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => (int) $args['iblock_id'], 'ACTIVE' => 'Y']
        );
        while ($row = $rs->Fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function addProperty(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $fields = [
            'IBLOCK_ID'     => (int) $args['iblock_id'],
            'NAME'          => $args['name'],
            'CODE'          => $args['code'],
            'PROPERTY_TYPE' => $args['property_type'],
            'MULTIPLE'      => ($args['multiple'] ?? false) ? 'Y' : 'N',
            'IS_REQUIRED'   => ($args['required'] ?? false) ? 'Y' : 'N',
            'SORT'          => $args['sort'] ?? 500,
            'DEFAULT_VALUE' => $args['default_value'] ?? '',
            'ACTIVE'        => 'Y',
        ];

        $prop = new \CIBlockProperty();
        $id = $prop->Add($fields);

        if ($id) {
            return ['success' => true, 'id' => (int) $id];
        }

        return ['success' => false, 'error' => $prop->LAST_ERROR];
    }

    public function updateProperty(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $id = (int) $args['id'];
        $fields = [];

        if (isset($args['name']))          $fields['NAME']          = $args['name'];
        if (isset($args['code']))          $fields['CODE']          = $args['code'];
        if (isset($args['sort']))          $fields['SORT']          = $args['sort'];
        if (isset($args['multiple']))      $fields['MULTIPLE']      = $args['multiple'] ? 'Y' : 'N';
        if (isset($args['required']))      $fields['IS_REQUIRED']   = $args['required'] ? 'Y' : 'N';
        if (isset($args['default_value'])) $fields['DEFAULT_VALUE'] = $args['default_value'];

        $prop = new \CIBlockProperty();
        $result = $prop->Update($id, $fields);

        if ($result) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => $prop->LAST_ERROR];
    }

    public function deleteProperty(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $result = \CIBlockProperty::Delete((int) $args['id']);

        return ['success' => (bool) $result];
    }
}
