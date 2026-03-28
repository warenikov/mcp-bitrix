<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class IblockElementTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'get_iblock_elements',
            description: 'Получить список элементов инфоблока с фильтрацией и постраничкой',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'iblock_id'  => ['type' => 'integer', 'description' => 'ID инфоблока'],
                    'filter'     => ['type' => 'object',  'description' => 'Фильтр (ключи: ACTIVE, SECTION_ID, NAME, CODE и т.д.)'],
                    'select'     => ['type' => 'array',   'description' => 'Список полей для выборки (по умолчанию все)'],
                    'limit'      => ['type' => 'integer', 'description' => 'Лимит (по умолчанию 20)'],
                    'offset'     => ['type' => 'integer', 'description' => 'Смещение'],
                    'with_props' => ['type' => 'boolean', 'description' => 'Включить значения свойств'],
                ],
                'required'   => ['iblock_id'],
            ],
            handler: [$self, 'getElements']
        );

        $server->addTool(
            name: 'get_iblock_element',
            description: 'Получить элемент инфоблока по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer', 'description' => 'ID элемента'],
                    'with_props' => ['type' => 'boolean', 'description' => 'Включить значения свойств'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'getElement']
        );

        $server->addTool(
            name: 'add_iblock_element',
            description: 'Добавить элемент в инфоблок',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'iblock_id'  => ['type' => 'integer', 'description' => 'ID инфоблока'],
                    'name'       => ['type' => 'string',  'description' => 'Название элемента'],
                    'code'       => ['type' => 'string',  'description' => 'Символьный код'],
                    'section_id' => ['type' => 'integer', 'description' => 'ID секции'],
                    'active'     => ['type' => 'boolean', 'description' => 'Активен'],
                    'sort'       => ['type' => 'integer', 'description' => 'Сортировка'],
                    'preview_text' => ['type' => 'string', 'description' => 'Анонс'],
                    'detail_text'  => ['type' => 'string', 'description' => 'Детальный текст'],
                    'properties' => ['type' => 'object',  'description' => 'Значения свойств (ключ — CODE свойства)'],
                ],
                'required'   => ['iblock_id', 'name'],
            ],
            handler: [$self, 'addElement'],
            mutating: true
        );

        $server->addTool(
            name: 'update_iblock_element',
            description: 'Обновить элемент инфоблока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'           => ['type' => 'integer', 'description' => 'ID элемента'],
                    'name'         => ['type' => 'string',  'description' => 'Название'],
                    'code'         => ['type' => 'string',  'description' => 'Символьный код'],
                    'section_id'   => ['type' => 'integer', 'description' => 'ID секции'],
                    'active'       => ['type' => 'boolean', 'description' => 'Активен'],
                    'sort'         => ['type' => 'integer', 'description' => 'Сортировка'],
                    'preview_text' => ['type' => 'string',  'description' => 'Анонс'],
                    'detail_text'  => ['type' => 'string',  'description' => 'Детальный текст'],
                    'properties'   => ['type' => 'object',  'description' => 'Значения свойств'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'updateElement'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_iblock_element',
            description: 'Удалить элемент инфоблока по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID элемента'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'deleteElement'],
            mutating: true
        );
    }

    public function getElements(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $filter = array_merge(
            ['IBLOCK_ID' => (int) $args['iblock_id'], 'ACTIVE' => 'Y'],
            $args['filter'] ?? []
        );

        $select = $args['select'] ?? ['ID', 'NAME', 'CODE', 'SORT', 'ACTIVE', 'SECTION_ID', 'DATE_CREATE', 'TIMESTAMP_X'];
        $limit  = $args['limit'] ?? 20;
        $offset = $args['offset'] ?? 0;

        $result = [];
        $rs = \CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            $filter,
            false,
            ['nPageSize' => $limit, 'iNumPage' => (int) floor($offset / $limit) + 1],
            $select
        );

        while ($row = $rs->GetNext()) {
            if (!empty($args['with_props'])) {
                $props = [];
                $propRs = \CIBlockElement::GetProperty((int) $args['iblock_id'], $row['ID']);
                while ($prop = $propRs->Fetch()) {
                    $props[$prop['CODE']] = $prop['VALUE'];
                }
                $row['PROPERTIES'] = $props;
            }
            $result[] = $row;
        }

        return $result;
    }

    public function getElement(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $rs = \CIBlockElement::GetByID((int) $args['id']);
        $row = $rs->GetNextElement();

        if (!$row) {
            throw new \RuntimeException("Элемент с ID {$args['id']} не найден");
        }

        $fields = $row->GetFields();

        if (!empty($args['with_props'])) {
            $props = $row->GetProperties();
            $fields['PROPERTIES'] = $props;
        }

        return $fields;
    }

    public function addElement(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $fields = [
            'IBLOCK_ID'    => (int) $args['iblock_id'],
            'NAME'         => $args['name'],
            'CODE'         => $args['code'] ?? '',
            'IBLOCK_SECTION_ID' => $args['section_id'] ?? false,
            'ACTIVE'       => ($args['active'] ?? true) ? 'Y' : 'N',
            'SORT'         => $args['sort'] ?? 500,
            'PREVIEW_TEXT' => $args['preview_text'] ?? '',
            'DETAIL_TEXT'  => $args['detail_text'] ?? '',
        ];

        if (!empty($args['properties'])) {
            $fields['PROPERTY_VALUES'] = $args['properties'];
        }

        $el = new \CIBlockElement();
        $id = $el->Add($fields);

        if ($id) {
            return ['success' => true, 'id' => (int) $id];
        }

        return ['success' => false, 'error' => $el->LAST_ERROR];
    }

    public function updateElement(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $id = (int) $args['id'];
        $fields = [];

        if (isset($args['name']))         $fields['NAME']              = $args['name'];
        if (isset($args['code']))         $fields['CODE']              = $args['code'];
        if (isset($args['section_id']))   $fields['IBLOCK_SECTION_ID'] = $args['section_id'];
        if (isset($args['active']))       $fields['ACTIVE']            = $args['active'] ? 'Y' : 'N';
        if (isset($args['sort']))         $fields['SORT']              = $args['sort'];
        if (isset($args['preview_text'])) $fields['PREVIEW_TEXT']      = $args['preview_text'];
        if (isset($args['detail_text']))  $fields['DETAIL_TEXT']       = $args['detail_text'];

        $el = new \CIBlockElement();
        $result = $el->Update($id, $fields);

        if (!$result) {
            return ['success' => false, 'error' => $el->LAST_ERROR];
        }

        // Обновляем свойства отдельно
        if (!empty($args['properties'])) {
            // Нужен iblock_id — получаем из элемента
            $rs = \CIBlockElement::GetByID($id);
            if ($row = $rs->Fetch()) {
                \CIBlockElement::SetPropertyValuesEx($id, $row['IBLOCK_ID'], $args['properties']);
            }
        }

        return ['success' => true];
    }

    public function deleteElement(array $args): array
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $result = \CIBlockElement::Delete((int) $args['id']);

        return ['success' => (bool) $result];
    }
}
