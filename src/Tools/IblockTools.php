<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class IblockTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'list_iblocks',
            description: 'Получить список инфоблоков',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'description' => 'Фильтр по типу инфоблока (опционально)'],
                ],
            ],
            handler: [$self, 'listIblocks']
        );

        $server->addTool(
            name: 'get_iblock',
            description: 'Получить инфоблок по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID инфоблока'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'getIblock']
        );

        $server->addTool(
            name: 'create_iblock',
            description: 'Создать новый инфоблок',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'name'        => ['type' => 'string',  'description' => 'Название инфоблока'],
                    'code'        => ['type' => 'string',  'description' => 'Символьный код (латиница)'],
                    'type'        => ['type' => 'string',  'description' => 'Тип инфоблока (ID типа)'],
                    'description' => ['type' => 'string',  'description' => 'Описание (опционально)'],
                    'sort'        => ['type' => 'integer', 'description' => 'Сортировка (по умолчанию 500)'],
                    'active'      => ['type' => 'boolean', 'description' => 'Активен (по умолчанию true)'],
                ],
                'required'   => ['name', 'code', 'type'],
            ],
            handler: [$self, 'createIblock']
        );

        $server->addTool(
            name: 'update_iblock',
            description: 'Обновить поля инфоблока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer', 'description' => 'ID инфоблока'],
                    'name'        => ['type' => 'string',  'description' => 'Название'],
                    'code'        => ['type' => 'string',  'description' => 'Символьный код'],
                    'description' => ['type' => 'string',  'description' => 'Описание'],
                    'sort'        => ['type' => 'integer', 'description' => 'Сортировка'],
                    'active'      => ['type' => 'boolean', 'description' => 'Активен'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'updateIblock']
        );

        $server->addTool(
            name: 'delete_iblock',
            description: 'Удалить инфоблок по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID инфоблока'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'deleteIblock']
        );
    }

    public function listIblocks(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $filter = ['ACTIVE' => 'Y'];
        if (!empty($args['type'])) {
            $filter['TYPE'] = $args['type'];
        }

        $result = [];
        $rs = \CIBlock::GetList(['SORT' => 'ASC'], $filter);
        while ($row = $rs->Fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function getIblock(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $rs = \CIBlock::GetByID((int) $args['id']);
        $row = $rs->Fetch();

        if (!$row) {
            throw new \RuntimeException("Инфоблок с ID {$args['id']} не найден");
        }

        return $row;
    }

    public function createIblock(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $fields = [
            'ACTIVE'         => ($args['active'] ?? true) ? 'Y' : 'N',
            'NAME'           => $args['name'],
            'CODE'           => $args['code'],
            'IBLOCK_TYPE_ID' => $args['type'],
            'DESCRIPTION'    => $args['description'] ?? '',
            'SORT'           => $args['sort'] ?? 500,
            'LIST_PAGE_URL'  => '',
            'DETAIL_PAGE_URL'=> '',
        ];

        $ib = new \CIBlock();
        $id = $ib->Add($fields);

        if ($id) {
            return ['success' => true, 'id' => (int) $id];
        }

        return ['success' => false, 'error' => $ib->LAST_ERROR];
    }

    public function updateIblock(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $id = (int) $args['id'];
        $fields = [];

        if (isset($args['name']))        $fields['NAME']        = $args['name'];
        if (isset($args['code']))        $fields['CODE']        = $args['code'];
        if (isset($args['description'])) $fields['DESCRIPTION'] = $args['description'];
        if (isset($args['sort']))        $fields['SORT']        = $args['sort'];
        if (isset($args['active']))      $fields['ACTIVE']      = $args['active'] ? 'Y' : 'N';

        $ib = new \CIBlock();
        $result = $ib->Update($id, $fields);

        if ($result) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => $ib->LAST_ERROR];
    }

    public function deleteIblock(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $result = \CIBlock::Delete((int) $args['id']);

        return ['success' => (bool) $result];
    }
}
