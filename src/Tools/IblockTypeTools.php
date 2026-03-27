<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class IblockTypeTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'list_iblock_types',
            description: 'Получить список типов инфоблоков',
            inputSchema: [
                'type'       => 'object',
                'properties' => [],
            ],
            handler: [$self, 'listIblockTypes']
        );

        $server->addTool(
            name: 'create_iblock_type',
            description: 'Создать новый тип инфоблоков',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'   => ['type' => 'string', 'description' => 'Символьный код типа (латиница, цифры, подчёркивание)'],
                    'name' => ['type' => 'string', 'description' => 'Название типа на русском'],
                    'sort' => ['type' => 'integer', 'description' => 'Сортировка (по умолчанию 100)'],
                ],
                'required'   => ['id', 'name'],
            ],
            handler: [$self, 'createIblockType'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_iblock_type',
            description: 'Удалить тип инфоблоков по символьному коду',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'Символьный код типа'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'deleteIblockType'],
            mutating: true
        );
    }

    public function listIblockTypes(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $result = [];
        $rs = \CIBlockType::GetList(['SORT' => 'ASC'], []);
        while ($row = $rs->Fetch()) {
            // Добавляем языковые названия
            $langRs = \CIBlockType::GetByIDLang($row['ID']);
            $row['LANG'] = [];
            while ($lang = $langRs->Fetch()) {
                $row['LANG'][$lang['LID']] = $lang['NAME'];
            }
            $result[] = $row;
        }

        return $result;
    }

    public function createIblockType(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $fields = [
            'ID'       => $args['id'],
            'SECTIONS' => 'Y',
            'SORT'     => $args['sort'] ?? 100,
            'LANG'     => [
                'ru' => ['NAME' => $args['name']],
                'en' => ['NAME' => $args['name']],
            ],
        ];

        $type = new \CIBlockType();
        $result = $type->Add($fields);

        if ($result) {
            return ['success' => true, 'id' => $args['id']];
        }

        return ['success' => false, 'error' => $type->LAST_ERROR];
    }

    public function deleteIblockType(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $result = \CIBlockType::Delete($args['id']);

        return ['success' => (bool) $result];
    }
}
