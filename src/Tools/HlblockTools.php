<?php

namespace Warenikov\McpBitrix\Tools;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Warenikov\McpBitrix\Server;

class HlblockTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        // --- HL-блоки ---

        $server->addTool(
            name: 'list_hlblocks',
            description: 'Получить список всех Highload-блоков',
            inputSchema: [
                'type'       => 'object',
                'properties' => [],
            ],
            handler: [$self, 'listHlblocks']
        );

        $server->addTool(
            name: 'get_hlblock',
            description: 'Получить Highload-блок по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'getHlblock']
        );

        $server->addTool(
            name: 'create_hlblock',
            description: 'Создать новый Highload-блок (автоматически создаёт таблицу в БД)',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'name'       => ['type' => 'string', 'description' => 'Имя сущности (латиница, без пробелов, напр. Products)'],
                    'table_name' => ['type' => 'string', 'description' => 'Имя таблицы в БД (напр. b_hl_products)'],
                ],
                'required'   => ['name', 'table_name'],
            ],
            handler: [$self, 'createHlblock'],
            mutating: true
        );

        $server->addTool(
            name: 'update_hlblock',
            description: 'Обновить Highload-блок',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'name'       => ['type' => 'string',  'description' => 'Новое имя сущности'],
                    'table_name' => ['type' => 'string',  'description' => 'Новое имя таблицы'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'updateHlblock'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_hlblock',
            description: 'Удалить Highload-блок (таблица в БД не удаляется)',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'deleteHlblock'],
            mutating: true
        );

        // --- Поля ---

        $server->addTool(
            name: 'list_hlblock_fields',
            description: 'Получить список полей Highload-блока включая лейблы',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'lang'       => ['type' => 'string',  'description' => 'Язык лейблов (по умолчанию ru)'],
                ],
                'required'   => ['hlblock_id'],
            ],
            handler: [$self, 'listFields']
        );

        $server->addTool(
            name: 'add_hlblock_field',
            description: 'Добавить поле к Highload-блоку. Типы: string, text, integer, double, boolean, datetime, date, file, enumeration',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id'   => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'field_name'   => ['type' => 'string',  'description' => 'Код поля (должен начинаться с UF_, напр. UF_NAME)'],
                    'type'         => ['type' => 'string',  'description' => 'Тип поля: string, text, integer, double, boolean, datetime, date, file, enumeration'],
                    'label'        => ['type' => 'string',  'description' => 'Название поля (для ru и en)'],
                    'sort'         => ['type' => 'integer', 'description' => 'Сортировка (по умолчанию 100)'],
                    'multiple'     => ['type' => 'boolean', 'description' => 'Множественное поле'],
                    'mandatory'    => ['type' => 'boolean', 'description' => 'Обязательное поле'],
                ],
                'required'   => ['hlblock_id', 'field_name', 'type'],
            ],
            handler: [$self, 'addField'],
            mutating: true
        );

        $server->addTool(
            name: 'update_hlblock_field',
            description: 'Обновить поле Highload-блока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id'  => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'field_name'  => ['type' => 'string',  'description' => 'Код поля (UF_*)'],
                    'label'       => ['type' => 'string',  'description' => 'Новое название'],
                    'sort'        => ['type' => 'integer', 'description' => 'Сортировка'],
                    'mandatory'   => ['type' => 'boolean', 'description' => 'Обязательное поле'],
                ],
                'required'   => ['hlblock_id', 'field_name'],
            ],
            handler: [$self, 'updateField'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_hlblock_field',
            description: 'Удалить поле Highload-блока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'field_name' => ['type' => 'string',  'description' => 'Код поля (UF_*)'],
                ],
                'required'   => ['hlblock_id', 'field_name'],
            ],
            handler: [$self, 'deleteField'],
            mutating: true
        );

        // --- Элементы ---

        $server->addTool(
            name: 'list_hlblock_elements',
            description: 'Получить список элементов Highload-блока с фильтрацией и постраничкой',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'filter'     => ['type' => 'object',  'description' => 'Фильтр по полям (напр. {"UF_ACTIVE": true})'],
                    'select'     => ['type' => 'array',   'description' => 'Список полей для выборки (по умолчанию все)'],
                    'order'      => ['type' => 'object',  'description' => 'Сортировка (напр. {"UF_SORT": "ASC"})'],
                    'limit'      => ['type' => 'integer', 'description' => 'Лимит (по умолчанию 20)'],
                    'offset'     => ['type' => 'integer', 'description' => 'Смещение'],
                ],
                'required'   => ['hlblock_id'],
            ],
            handler: [$self, 'listElements']
        );

        $server->addTool(
            name: 'get_hlblock_element',
            description: 'Получить элемент Highload-блока по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'id'         => ['type' => 'integer', 'description' => 'ID элемента'],
                ],
                'required'   => ['hlblock_id', 'id'],
            ],
            handler: [$self, 'getElement']
        );

        $server->addTool(
            name: 'add_hlblock_element',
            description: 'Добавить элемент в Highload-блок',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'fields'     => ['type' => 'object',  'description' => 'Значения полей (напр. {"UF_NAME": "Foo", "UF_SORT": 100})'],
                ],
                'required'   => ['hlblock_id', 'fields'],
            ],
            handler: [$self, 'addElement'],
            mutating: true
        );

        $server->addTool(
            name: 'update_hlblock_element',
            description: 'Обновить элемент Highload-блока',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'id'         => ['type' => 'integer', 'description' => 'ID элемента'],
                    'fields'     => ['type' => 'object',  'description' => 'Значения полей для обновления'],
                ],
                'required'   => ['hlblock_id', 'id', 'fields'],
            ],
            handler: [$self, 'updateElement'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_hlblock_element',
            description: 'Удалить элемент Highload-блока по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'hlblock_id' => ['type' => 'integer', 'description' => 'ID Highload-блока'],
                    'id'         => ['type' => 'integer', 'description' => 'ID элемента'],
                ],
                'required'   => ['hlblock_id', 'id'],
            ],
            handler: [$self, 'deleteElement'],
            mutating: true
        );
    }

    // -------------------------------------------------------------------------
    // HL-блоки
    // -------------------------------------------------------------------------

    public function listHlblocks(array $args): array
    {
        Loader::includeModule('highloadblock');

        $result = [];
        $rs = HighloadBlockTable::getList(['order' => ['ID' => 'ASC']]);
        while ($row = $rs->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function getHlblock(array $args): array
    {
        Loader::includeModule('highloadblock');

        $row = HighloadBlockTable::getById((int) $args['id'])->fetch();

        if (!$row) {
            throw new \RuntimeException("Highload-блок с ID {$args['id']} не найден");
        }

        return $row;
    }

    public function createHlblock(array $args): array
    {
        Loader::includeModule('highloadblock');

        $result = HighloadBlockTable::add([
            'NAME'       => $args['name'],
            'TABLE_NAME' => $args['table_name'],
        ]);

        if ($result->isSuccess()) {
            return ['success' => true, 'id' => $result->getId()];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    public function updateHlblock(array $args): array
    {
        Loader::includeModule('highloadblock');

        $fields = [];
        if (isset($args['name']))       $fields['NAME']       = $args['name'];
        if (isset($args['table_name'])) $fields['TABLE_NAME'] = $args['table_name'];

        $result = HighloadBlockTable::update((int) $args['id'], $fields);

        if ($result->isSuccess()) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    public function deleteHlblock(array $args): array
    {
        Loader::includeModule('highloadblock');

        $result = HighloadBlockTable::delete((int) $args['id']);

        if ($result->isSuccess()) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    // -------------------------------------------------------------------------
    // Поля
    // -------------------------------------------------------------------------

    public function listFields(array $args): array
    {
        Loader::includeModule('highloadblock');

        $entityId = 'HLBLOCK_' . (int) $args['hlblock_id'];

        $result = [];
        $uft = new \CUserTypeEntity();
        $rs  = $uft->GetList(
            ['SORT' => 'ASC'],
            ['ENTITY_ID' => $entityId, 'LANG' => $args['lang'] ?? 'ru']
        );
        while ($row = $rs->GetNext()) {
            $result[] = $row;
        }

        return $result;
    }

    public function addField(array $args): array
    {
        Loader::includeModule('highloadblock');

        $hlblockId = (int) $args['hlblock_id'];
        $entityId  = 'HLBLOCK_' . $hlblockId;
        $label     = $args['label'] ?? $args['field_name'];

        $uft    = new \CUserTypeEntity();
        $fieldId = $uft->Add([
            'ENTITY_ID'         => $entityId,
            'FIELD_NAME'        => $args['field_name'],
            'USER_TYPE_ID'      => $args['type'],
            'SORT'              => $args['sort'] ?? 100,
            'MULTIPLE'          => ($args['multiple'] ?? false) ? 'Y' : 'N',
            'MANDATORY'         => ($args['mandatory'] ?? false) ? 'Y' : 'N',
            'SHOW_FILTER'       => 'Y',
            'SHOW_IN_LIST'      => 'Y',
            'EDIT_IN_LIST'      => 'Y',
            'IS_SEARCHABLE'     => 'N',
            'EDIT_FORM_LABEL'   => ['ru' => $label, 'en' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label, 'en' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label, 'en' => $label],
        ]);

        if (!$fieldId) {
            $exception = $GLOBALS['APPLICATION']->GetException();
            $error = $exception ? $exception->GetString() : ($uft->LAST_ERROR ?: 'Unknown error (possibly duplicate field name)');
            return ['success' => false, 'error' => $error];
        }

        return ['success' => true, 'field_id' => (int) $fieldId];
    }

    public function updateField(array $args): array
    {
        Loader::includeModule('highloadblock');

        $entityId = 'HLBLOCK_' . (int) $args['hlblock_id'];

        $uft      = new \CUserTypeEntity();
        $fieldRow = $uft->GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $args['field_name']])->GetNext();

        if (!$fieldRow) {
            throw new \RuntimeException("Поле {$args['field_name']} не найдено в блоке {$args['hlblock_id']}");
        }

        $fields = [];
        if (isset($args['sort']))     $fields['SORT']      = $args['sort'];
        if (isset($args['mandatory'])) $fields['MANDATORY'] = $args['mandatory'] ? 'Y' : 'N';
        if (isset($args['label'])) {
            $label = $args['label'];
            $fields['EDIT_FORM_LABEL']   = ['ru' => $label, 'en' => $label];
            $fields['LIST_COLUMN_LABEL'] = ['ru' => $label, 'en' => $label];
            $fields['LIST_FILTER_LABEL'] = ['ru' => $label, 'en' => $label];
        }

        $result = $uft->Update((int) $fieldRow['ID'], $fields);

        if (!$result) {
            return ['success' => false, 'error' => $uft->LAST_ERROR];
        }

        return ['success' => true];
    }

    public function deleteField(array $args): array
    {
        Loader::includeModule('highloadblock');

        $entityId = 'HLBLOCK_' . (int) $args['hlblock_id'];

        $uft      = new \CUserTypeEntity();
        $fieldRow = $uft->GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $args['field_name']])->GetNext();

        if (!$fieldRow) {
            throw new \RuntimeException("Поле {$args['field_name']} не найдено в блоке {$args['hlblock_id']}");
        }

        $result = $uft->Delete((int) $fieldRow['ID']);

        if (!$result) {
            return ['success' => false, 'error' => $uft->LAST_ERROR];
        }

        return ['success' => true];
    }

    // -------------------------------------------------------------------------
    // Элементы
    // -------------------------------------------------------------------------

    private function getDataClass(int $hlblockId): string
    {
        $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();

        if (!$hlblock) {
            throw new \RuntimeException("Highload-блок с ID {$hlblockId} не найден");
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }

    public function listElements(array $args): array
    {
        Loader::includeModule('highloadblock');

        $dataClass = $this->getDataClass((int) $args['hlblock_id']);

        $query = [
            'select' => $args['select'] ?? ['*'],
            'order'  => $args['order']  ?? ['ID' => 'ASC'],
            'limit'  => $args['limit']  ?? 20,
            'offset' => $args['offset'] ?? 0,
        ];

        if (!empty($args['filter'])) {
            $query['filter'] = $args['filter'];
        }

        $result = [];
        $rs = $dataClass::getList($query);
        while ($row = $rs->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function getElement(array $args): array
    {
        Loader::includeModule('highloadblock');

        $dataClass = $this->getDataClass((int) $args['hlblock_id']);
        $row       = $dataClass::getById((int) $args['id'])->fetch();

        if (!$row) {
            throw new \RuntimeException("Элемент с ID {$args['id']} не найден");
        }

        return $row;
    }

    public function addElement(array $args): array
    {
        Loader::includeModule('highloadblock');

        $dataClass = $this->getDataClass((int) $args['hlblock_id']);
        $result    = $dataClass::add($args['fields']);

        if ($result->isSuccess()) {
            return ['success' => true, 'id' => $result->getId()];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    public function updateElement(array $args): array
    {
        Loader::includeModule('highloadblock');

        $dataClass = $this->getDataClass((int) $args['hlblock_id']);
        $result    = $dataClass::update((int) $args['id'], $args['fields']);

        if ($result->isSuccess()) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    public function deleteElement(array $args): array
    {
        Loader::includeModule('highloadblock');

        $dataClass = $this->getDataClass((int) $args['hlblock_id']);
        $result    = $dataClass::delete((int) $args['id']);

        if ($result->isSuccess()) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }
}
