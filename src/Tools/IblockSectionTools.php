<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class IblockSectionTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'list_iblock_sections',
            description: 'Получить список секций инфоблока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'iblock_id'        => ['type' => 'integer', 'description' => 'ID инфоблока'],
                    'parent_section_id'=> ['type' => 'integer', 'description' => 'ID родительской секции (0 — корневые)'],
                ],
                'required'   => ['iblock_id'],
            ],
            handler: [$self, 'listSections']
        );

        $server->addTool(
            name: 'add_iblock_section',
            description: 'Добавить секцию в инфоблок',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'iblock_id'         => ['type' => 'integer', 'description' => 'ID инфоблока'],
                    'name'              => ['type' => 'string',  'description' => 'Название секции'],
                    'code'              => ['type' => 'string',  'description' => 'Символьный код'],
                    'parent_section_id' => ['type' => 'integer', 'description' => 'ID родительской секции'],
                    'sort'              => ['type' => 'integer', 'description' => 'Сортировка'],
                    'description'       => ['type' => 'string',  'description' => 'Описание'],
                    'active'            => ['type' => 'boolean', 'description' => 'Активна'],
                ],
                'required'   => ['iblock_id', 'name'],
            ],
            handler: [$self, 'addSection'],
            mutating: true
        );

        $server->addTool(
            name: 'update_iblock_section',
            description: 'Обновить секцию инфоблока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer', 'description' => 'ID секции'],
                    'iblock_id'   => ['type' => 'integer', 'description' => 'ID инфоблока'],
                    'name'        => ['type' => 'string',  'description' => 'Название'],
                    'code'        => ['type' => 'string',  'description' => 'Символьный код'],
                    'sort'        => ['type' => 'integer', 'description' => 'Сортировка'],
                    'description' => ['type' => 'string',  'description' => 'Описание'],
                    'active'      => ['type' => 'boolean', 'description' => 'Активна'],
                ],
                'required'   => ['id', 'iblock_id'],
            ],
            handler: [$self, 'updateSection'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_iblock_section',
            description: 'Удалить секцию инфоблока по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'        => ['type' => 'integer', 'description' => 'ID секции'],
                    'iblock_id' => ['type' => 'integer', 'description' => 'ID инфоблока'],
                ],
                'required'   => ['id', 'iblock_id'],
            ],
            handler: [$self, 'deleteSection'],
            mutating: true
        );
    }

    public function listSections(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $filter = ['IBLOCK_ID' => (int) $args['iblock_id'], 'ACTIVE' => 'Y'];
        if (isset($args['parent_section_id'])) {
            $filter['SECTION_ID'] = (int) $args['parent_section_id'] ?: false;
        }

        $result = [];
        $rs = \CIBlockSection::GetList(['SORT' => 'ASC'], $filter);
        while ($row = $rs->Fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function addSection(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $fields = [
            'IBLOCK_ID'        => (int) $args['iblock_id'],
            'NAME'             => $args['name'],
            'CODE'             => $args['code'] ?? '',
            'IBLOCK_SECTION_ID'=> $args['parent_section_id'] ?? false,
            'SORT'             => $args['sort'] ?? 500,
            'DESCRIPTION'      => $args['description'] ?? '',
            'ACTIVE'           => ($args['active'] ?? true) ? 'Y' : 'N',
        ];

        $section = new \CIBlockSection();
        $id = $section->Add($fields);

        if ($id) {
            return ['success' => true, 'id' => (int) $id];
        }

        return ['success' => false, 'error' => $section->LAST_ERROR];
    }

    public function updateSection(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $id = (int) $args['id'];
        $fields = ['IBLOCK_ID' => (int) $args['iblock_id']];

        if (isset($args['name']))        $fields['NAME']        = $args['name'];
        if (isset($args['code']))        $fields['CODE']        = $args['code'];
        if (isset($args['sort']))        $fields['SORT']        = $args['sort'];
        if (isset($args['description'])) $fields['DESCRIPTION'] = $args['description'];
        if (isset($args['active']))      $fields['ACTIVE']      = $args['active'] ? 'Y' : 'N';

        $section = new \CIBlockSection();
        $result = $section->Update($id, $fields);

        if ($result) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => $section->LAST_ERROR];
    }

    public function deleteSection(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $result = \CIBlockSection::Delete((int) $args['id']);

        return ['success' => (bool) $result];
    }
}
