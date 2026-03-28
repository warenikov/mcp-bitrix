<?php

namespace Warenikov\McpBitrix\Tools;

use Bitrix\Main\Application;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DateField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Warenikov\McpBitrix\Server;

class OrmTools
{
    private const REGISTRY_TABLE = 'b_mcp_orm_registry';

    private const FIELD_TYPES = [
        'integer'  => IntegerField::class,
        'string'   => StringField::class,
        'text'     => TextField::class,
        'float'    => FloatField::class,
        'boolean'  => BooleanField::class,
        'date'     => DateField::class,
        'datetime' => DatetimeField::class,
    ];

    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'create_orm_entity',
            description: 'Создать кастомную ORM-сущность и таблицу в БД. Поля описываются массивом с ключами: name, type (integer/string/text/float/boolean/date/datetime), primary, required, size',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string', 'description' => 'Имя сущности (латиница, напр. ProductLog). Используется как имя класса.'],
                    'table_name'  => ['type' => 'string', 'description' => 'Имя таблицы в БД (напр. b_product_log)'],
                    'fields'      => [
                        'type'        => 'array',
                        'description' => 'Описание полей. Каждое поле: {name, type, primary?, required?, size?, autocomplete?}. Поле ID (integer, primary, autocomplete) добавляется автоматически если не указано.',
                    ],
                ],
                'required' => ['entity_name', 'table_name', 'fields'],
            ],
            handler: [$self, 'createEntity'],
            mutating: true
        );

        $server->addTool(
            name: 'list_orm_entities',
            description: 'Список всех зарегистрированных ORM-сущностей',
            inputSchema: [
                'type'       => 'object',
                'properties' => [],
            ],
            handler: [$self, 'listEntities']
        );

        $server->addTool(
            name: 'get_orm_entity',
            description: 'Получить описание ORM-сущности по имени',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string', 'description' => 'Имя сущности'],
                ],
                'required' => ['entity_name'],
            ],
            handler: [$self, 'getEntity']
        );

        $server->addTool(
            name: 'drop_orm_entity',
            description: 'Удалить ORM-сущность и её таблицу из БД',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string', 'description' => 'Имя сущности'],
                ],
                'required' => ['entity_name'],
            ],
            handler: [$self, 'dropEntity'],
            mutating: true
        );

        $server->addTool(
            name: 'orm_list',
            description: 'Получить список записей ORM-сущности с фильтром и постраничкой',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string',  'description' => 'Имя сущности'],
                    'filter'      => ['type' => 'object',  'description' => 'Фильтр по полям'],
                    'select'      => ['type' => 'array',   'description' => 'Список полей (по умолчанию все)'],
                    'order'       => ['type' => 'object',  'description' => 'Сортировка, напр. {"ID": "DESC"}'],
                    'limit'       => ['type' => 'integer', 'description' => 'Лимит (по умолчанию 20)'],
                    'offset'      => ['type' => 'integer', 'description' => 'Смещение'],
                ],
                'required' => ['entity_name'],
            ],
            handler: [$self, 'listRows']
        );

        $server->addTool(
            name: 'orm_get',
            description: 'Получить запись ORM-сущности по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string',  'description' => 'Имя сущности'],
                    'id'          => ['type' => 'integer', 'description' => 'ID записи'],
                ],
                'required' => ['entity_name', 'id'],
            ],
            handler: [$self, 'getRow']
        );

        $server->addTool(
            name: 'orm_add',
            description: 'Добавить запись в ORM-сущность',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string', 'description' => 'Имя сущности'],
                    'fields'      => ['type' => 'object', 'description' => 'Значения полей'],
                ],
                'required' => ['entity_name', 'fields'],
            ],
            handler: [$self, 'addRow'],
            mutating: true
        );

        $server->addTool(
            name: 'orm_update',
            description: 'Обновить запись ORM-сущности по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string',  'description' => 'Имя сущности'],
                    'id'          => ['type' => 'integer', 'description' => 'ID записи'],
                    'fields'      => ['type' => 'object',  'description' => 'Значения полей для обновления'],
                ],
                'required' => ['entity_name', 'id', 'fields'],
            ],
            handler: [$self, 'updateRow'],
            mutating: true
        );

        $server->addTool(
            name: 'orm_delete',
            description: 'Удалить запись ORM-сущности по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string',  'description' => 'Имя сущности'],
                    'id'          => ['type' => 'integer', 'description' => 'ID записи'],
                ],
                'required' => ['entity_name', 'id'],
            ],
            handler: [$self, 'deleteRow'],
            mutating: true
        );
    }

    // -------------------------------------------------------------------------
    // Управление сущностями
    // -------------------------------------------------------------------------

    public function createEntity(array $args): array
    {
        $this->ensureRegistry();

        $entityName = $args['entity_name'];
        $tableName  = $args['table_name'];
        $fields     = $args['fields'];

        $conn = Application::getConnection();

        if ($conn->isTableExists($tableName)) {
            throw new \RuntimeException("Таблица {$tableName} уже существует в БД");
        }

        if ($this->findInRegistry($entityName)) {
            throw new \RuntimeException("Сущность {$entityName} уже зарегистрирована");
        }

        // Добавляем ID если не задан
        $hasId = !empty(array_filter($fields, fn($f) => strtoupper($f['name']) === 'ID'));
        if (!$hasId) {
            array_unshift($fields, [
                'name'         => 'ID',
                'type'         => 'integer',
                'primary'      => true,
                'autocomplete' => true,
            ]);
        }

        // Компилируем entity и создаём таблицу
        $ormFields = $this->buildOrmFields($fields);
        $entity    = Entity::compileEntity($entityName, $ormFields, ['table_name' => $tableName]);
        $entity->createDbTable();

        // Сохраняем в реестре
        $helper = $conn->getSqlHelper();
        $conn->query(
            "INSERT INTO " . self::REGISTRY_TABLE . " (ENTITY_NAME, TABLE_NAME, FIELDS) VALUES ('" .
            $helper->forSql($entityName) . "', '" .
            $helper->forSql($tableName) . "', '" .
            $helper->forSql(json_encode($fields, JSON_UNESCAPED_UNICODE)) . "')"
        );

        return ['success' => true, 'entity_name' => $entityName, 'table_name' => $tableName];
    }

    public function listEntities(array $args): array
    {
        $this->ensureRegistry();

        $result = [];
        $rs = Application::getConnection()->query("SELECT * FROM " . self::REGISTRY_TABLE . " ORDER BY ID ASC");
        while ($row = $rs->fetch()) {
            $row['FIELDS'] = json_decode($row['FIELDS'], true);
            $result[] = $row;
        }

        return $result;
    }

    public function getEntity(array $args): array
    {
        $this->ensureRegistry();

        $row = $this->findInRegistry($args['entity_name']);
        if (!$row) {
            throw new \RuntimeException("Сущность {$args['entity_name']} не найдена");
        }

        $row['FIELDS'] = json_decode($row['FIELDS'], true);
        return $row;
    }

    public function dropEntity(array $args): array
    {
        $this->ensureRegistry();

        $row = $this->findInRegistry($args['entity_name']);
        if (!$row) {
            throw new \RuntimeException("Сущность {$args['entity_name']} не найдена");
        }

        $conn = Application::getConnection();
        $conn->query("DROP TABLE IF EXISTS " . $conn->getSqlHelper()->forSql($row['TABLE_NAME']));
        $conn->query(
            "DELETE FROM " . self::REGISTRY_TABLE . " WHERE ENTITY_NAME = '" .
            $conn->getSqlHelper()->forSql($args['entity_name']) . "'"
        );

        return ['success' => true];
    }

    // -------------------------------------------------------------------------
    // CRUD записей
    // -------------------------------------------------------------------------

    public function listRows(array $args): array
    {
        $dataClass = $this->compileFromRegistry($args['entity_name']);

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

    public function getRow(array $args): array
    {
        $dataClass = $this->compileFromRegistry($args['entity_name']);
        $row       = $dataClass::getById((int) $args['id'])->fetch();

        if (!$row) {
            throw new \RuntimeException("Запись с ID {$args['id']} не найдена в {$args['entity_name']}");
        }

        return $row;
    }

    public function addRow(array $args): array
    {
        [$dataClass, $fieldDefs] = $this->resolveEntity($args['entity_name']);
        $result = $dataClass::add($this->normalizeValues($args['fields'], $fieldDefs));

        if ($result->isSuccess()) {
            return ['success' => true, 'id' => $result->getId()];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    public function updateRow(array $args): array
    {
        [$dataClass, $fieldDefs] = $this->resolveEntity($args['entity_name']);
        $result = $dataClass::update((int) $args['id'], $this->normalizeValues($args['fields'], $fieldDefs));

        if ($result->isSuccess()) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    public function deleteRow(array $args): array
    {
        $dataClass = $this->compileFromRegistry($args['entity_name']);
        $result    = $dataClass::delete((int) $args['id']);

        if ($result->isSuccess()) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => $result->getErrorMessages()];
    }

    // -------------------------------------------------------------------------
    // Вспомогательные методы
    // -------------------------------------------------------------------------

    private function ensureRegistry(): void
    {
        $conn = Application::getConnection();

        if (!$conn->isTableExists(self::REGISTRY_TABLE)) {
            $conn->query("
                CREATE TABLE " . self::REGISTRY_TABLE . " (
                    ID         INT NOT NULL AUTO_INCREMENT,
                    ENTITY_NAME VARCHAR(100) NOT NULL,
                    TABLE_NAME  VARCHAR(100) NOT NULL,
                    FIELDS      TEXT NOT NULL,
                    PRIMARY KEY (ID),
                    UNIQUE KEY ux_entity_name (ENTITY_NAME)
                )
            ");
        }
    }

    private function findInRegistry(string $entityName): array|false
    {
        $conn   = Application::getConnection();
        $helper = $conn->getSqlHelper();
        $rs     = $conn->query(
            "SELECT * FROM " . self::REGISTRY_TABLE . " WHERE ENTITY_NAME = '" . $helper->forSql($entityName) . "' LIMIT 1"
        );

        return $rs->fetch() ?: false;
    }

    private function compileFromRegistry(string $entityName): string
    {
        return $this->resolveEntity($entityName)[0];
    }

    /**
     * @return array{0: string, 1: array}  [dataClass, fieldDefs]
     */
    private function resolveEntity(string $entityName): array
    {
        $row = $this->findInRegistry($entityName);

        if (!$row) {
            throw new \RuntimeException("Сущность {$entityName} не найдена в реестре");
        }

        $fieldDefs = json_decode($row['FIELDS'], true);
        $entity    = Entity::compileEntity($entityName, $this->buildOrmFields($fieldDefs), [
            'table_name' => $row['TABLE_NAME'],
        ]);

        return [$entity->getDataClass(), $fieldDefs];
    }

    private function normalizeValues(array $values, array $fieldDefs): array
    {
        $types = [];
        foreach ($fieldDefs as $def) {
            $types[strtoupper($def['name'])] = strtolower($def['type'] ?? 'string');
        }

        $result = [];
        foreach ($values as $key => $value) {
            $type = $types[strtoupper($key)] ?? null;

            if ($value === null || $type === null) {
                $result[$key] = $value;
                continue;
            }

            if ($type === 'datetime') {
                $result[$key] = $value instanceof \Bitrix\Main\Type\DateTime
                    ? $value
                    : \Bitrix\Main\Type\DateTime::createFromPhp(new \DateTime((string) $value));
            } elseif ($type === 'date') {
                $result[$key] = $value instanceof \Bitrix\Main\Type\Date
                    ? $value
                    : \Bitrix\Main\Type\Date::createFromPhp(new \DateTime((string) $value));
            } elseif ($type === 'boolean') {
                $result[$key] = ($value === true || $value === 1 || strtoupper((string) $value) === 'Y') ? 'Y' : 'N';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function buildOrmFields(array $fields): array
    {
        $ormFields = [];

        foreach ($fields as $fieldDef) {
            $name = $fieldDef['name'];
            $type = strtolower($fieldDef['type'] ?? 'string');

            if (!isset(self::FIELD_TYPES[$type])) {
                throw new \RuntimeException("Неизвестный тип поля: {$type}. Доступны: " . implode(', ', array_keys(self::FIELD_TYPES)));
            }

            $class = self::FIELD_TYPES[$type];
            $params = [];

            if (!empty($fieldDef['primary']))      $params['primary']      = true;
            if (!empty($fieldDef['autocomplete']))  $params['autocomplete'] = true;
            if (!empty($fieldDef['required']))      $params['required']     = true;
            if (!empty($fieldDef['size']))          $params['size']         = (int) $fieldDef['size'];

            $ormFields[$name] = new $class($name, $params);
        }

        return $ormFields;
    }
}
