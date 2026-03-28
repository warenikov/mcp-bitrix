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

    /** @var array<string, string> кэш entityName → dataClass FQCN */
    private array $compiledClasses = [];

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

        $server->addTool(
            name: 'write_orm_class_file',
            description: 'Сгенерировать PHP-класс DataManager для ORM-сущности и записать его в файловую систему сайта. Вызывать ТОЛЬКО по явному запросу разработчика — не запускать автоматически после create_orm_entity. После записи файл нужно подключить в кодовую базу через автолоадер Битрикса.',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'entity_name' => ['type' => 'string', 'description' => 'Имя сущности (должна быть создана через create_orm_entity)'],
                    'path'        => ['type' => 'string', 'description' => 'Папка для записи файла (по умолчанию /var/www/html/local/lib/Orm)'],
                    'namespace'   => ['type' => 'string', 'description' => 'PHP-namespace класса (опционально, напр. App\\Orm)'],
                ],
                'required' => ['entity_name'],
            ],
            handler: [$self, 'writeOrmClassFile'],
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

        // PHP не позволяет переобъявить класс в том же процессе.
        // Если класс уже скомпилирован (сущность была создана и удалена в этой же сессии),
        // создать её заново можно только после перезапуска MCP-сервера.
        if (class_exists($entityName . 'Table', false)) {
            throw new \RuntimeException(
                "Класс {$entityName}Table уже объявлен в текущей сессии PHP. " .
                "Пересоздание сущности с тем же именем требует перезапуска MCP-сервера."
            );
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
        $this->compiledClasses[$entityName] = $entity->getDataClass();
        $entity->createDbTable();

        // Сохраняем в реестре
        $helper = $conn->getSqlHelper();
        $conn->query(
            "INSERT INTO " . self::REGISTRY_TABLE . " (ENTITY_NAME, TABLE_NAME, FIELDS) VALUES ('" .
            $helper->forSql($entityName) . "', '" .
            $helper->forSql($tableName) . "', '" .
            $helper->forSql(json_encode($fields, JSON_UNESCAPED_UNICODE)) . "')"
        );

        return [
            'success'     => true,
            'entity_name' => $entityName,
            'table_name'  => $tableName,
            'hint'        => "Сущность создана и доступна через MCP. Чтобы использовать её в PHP-коде сайта, вызовите write_orm_class_file.",
        ];
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

        unset($this->compiledClasses[$args['entity_name']]);

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
            $result[] = $this->normalizeRow($row);
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

        return $this->normalizeRow($row);
    }

    public function addRow(array $args): array
    {
        [$dataClass, $fieldDefs] = $this->resolveEntity($args['entity_name']);
        $result = $dataClass::add($this->normalizeValues($args['fields'], $fieldDefs));

        if ($result->isSuccess()) {
            return ['success' => true, 'id' => (int) $result->getId()];
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

    // -------------------------------------------------------------------------
    // Генерация PHP-класса
    // -------------------------------------------------------------------------

    public function writeOrmClassFile(array $args): array
    {
        $this->ensureRegistry();

        $row = $this->findInRegistry($args['entity_name']);
        if (!$row) {
            throw new \RuntimeException("Сущность {$args['entity_name']} не найдена");
        }

        $entityName = $row['ENTITY_NAME'];
        $tableName  = $row['TABLE_NAME'];
        $fieldDefs  = json_decode($row['FIELDS'], true);
        $namespace  = $args['namespace'] ?? '';
        $dir        = rtrim($args['path'] ?? '/var/www/html/local/lib/Orm', '/');
        $className  = $entityName . 'Table';
        $filePath   = $dir . '/' . $className . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, $this->generateClassCode($className, $tableName, $fieldDefs, $namespace));

        return [
            'success'    => true,
            'file'       => $filePath,
            'class_name' => $className,
            'note'       => "Зарегистрируйте класс в автолоадере Битрикса (/local/.settings.php) или подключите через require_once.",
        ];
    }

    private function generateClassCode(string $className, string $tableName, array $fieldDefs, string $namespace): string
    {
        $fieldTypeMap = [
            'integer'  => ['class' => 'IntegerField',  'use' => 'Bitrix\\Main\\ORM\\Fields\\IntegerField'],
            'string'   => ['class' => 'StringField',   'use' => 'Bitrix\\Main\\ORM\\Fields\\StringField'],
            'text'     => ['class' => 'TextField',     'use' => 'Bitrix\\Main\\ORM\\Fields\\TextField'],
            'float'    => ['class' => 'FloatField',    'use' => 'Bitrix\\Main\\ORM\\Fields\\FloatField'],
            'boolean'  => ['class' => 'BooleanField',  'use' => 'Bitrix\\Main\\ORM\\Fields\\BooleanField'],
            'date'     => ['class' => 'DateField',     'use' => 'Bitrix\\Main\\ORM\\Fields\\DateField'],
            'datetime' => ['class' => 'DatetimeField', 'use' => 'Bitrix\\Main\\ORM\\Fields\\DatetimeField'],
        ];

        $usedTypes = array_unique(array_map(fn($f) => strtolower($f['type'] ?? 'string'), $fieldDefs));
        $uses      = ['use Bitrix\\Main\\ORM\\Data\\DataManager;'];
        foreach ($usedTypes as $type) {
            if (isset($fieldTypeMap[$type])) {
                $uses[] = 'use ' . $fieldTypeMap[$type]['use'] . ';';
            }
        }
        sort($uses);

        $fieldLines = [];
        foreach ($fieldDefs as $def) {
            $type   = strtolower($def['type'] ?? 'string');
            $class  = $fieldTypeMap[$type]['class'] ?? 'StringField';
            $params = [];
            if (!empty($def['primary']))      $params[] = "'primary' => true";
            if (!empty($def['autocomplete'])) $params[] = "'autocomplete' => true";
            if (!empty($def['required']))     $params[] = "'required' => true";
            if (!empty($def['size']))         $params[] = "'size' => " . (int) $def['size'];
            if ($type === 'boolean')          $params[] = "'values' => ['N', 'Y']";

            $paramsStr  = empty($params) ? '' : ', [' . implode(', ', $params) . ']';
            $fieldLines[] = "            new {$class}('{$def['name']}'{$paramsStr}),";
        }

        $nsLine     = $namespace ? "namespace {$namespace};\n\n" : '';
        $usesCode   = implode("\n", $uses);
        $fieldsCode = implode("\n", $fieldLines);
        $date       = date('Y-m-d');

        return "<?php\n\n{$nsLine}{$usesCode}\n\n"
            . "/**\n"
            . " * Сгенерировано mcp-bitrix {$date}\n"
            . " * Таблица в БД: {$tableName}\n"
            . " *\n"
            . " * Автолоадер (/local/.settings.php):\n"
            . " *   'autoload' => ['value' => ['classes' => ['{$className}' => '/local/lib/Orm/{$className}.php']]]\n"
            . " */\n"
            . "class {$className} extends DataManager\n"
            . "{\n"
            . "    public static function getTableName(): string\n"
            . "    {\n"
            . "        return '{$tableName}';\n"
            . "    }\n\n"
            . "    public static function getMap(): array\n"
            . "    {\n"
            . "        return [\n"
            . $fieldsCode . "\n"
            . "        ];\n"
            . "    }\n"
            . "}\n";
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \Bitrix\Main\Type\DateTime) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            } elseif ($value instanceof \Bitrix\Main\Type\Date) {
                $row[$key] = $value->format('Y-m-d');
            }
        }
        return $row;
    }

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

        // Используем кэш, чтобы не вызывать compileEntity повторно в том же процессе.
        // Повторный eval() вызывает PHP fatal "Cannot declare class, already in use".
        if (!isset($this->compiledClasses[$entityName])) {
            $entity = Entity::compileEntity($entityName, $this->buildOrmFields($fieldDefs), [
                'table_name' => $row['TABLE_NAME'],
            ]);
            $this->compiledClasses[$entityName] = $entity->getDataClass();
        }

        return [$this->compiledClasses[$entityName], $fieldDefs];
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
                // Явно конвертируем в 'Y'/'N' — динамически компилируемые сущности
                // не вызывают convertValueToDb(), и MySQL получает '1'/'0' вместо 'Y'/'N'
                if (is_string($value)) {
                    $result[$key] = (strtoupper($value) === 'Y' || $value === '1' || $value === 'true') ? 'Y' : 'N';
                } else {
                    $result[$key] = $value ? 'Y' : 'N';
                }
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

            $params = [];

            if (!empty($fieldDef['primary']))      $params['primary']      = true;
            if (!empty($fieldDef['autocomplete']))  $params['autocomplete'] = true;
            if (!empty($fieldDef['required']))      $params['required']     = true;
            if (!empty($fieldDef['size']))          $params['size']         = (int) $fieldDef['size'];

            // BooleanField в динамически компилируемых сущностях не вызывает
            // convertValueToDb() — используем StringField(size=1) со значениями 'Y'/'N',
            // которые normalizeValues подставляет явно перед сохранением.
            if ($type === 'boolean') {
                $params['size'] = 1;
                $ormFields[$name] = new StringField($name, $params);
            } else {
                $ormFields[$name] = new (self::FIELD_TYPES[$type])($name, $params);
            }
        }

        return $ormFields;
    }
}
