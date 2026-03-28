<?php

declare(strict_types=1);

// =============================================================================
// Stubs Bitrix\Main\ORM\Fields
// =============================================================================

namespace Bitrix\Main\ORM\Fields {

    class ScalarField
    {
        public function __construct(public string $name, public array $params = []) {}
        public function setEntity($e): void {}
        public function postInitialize(): void {}
        public function getName(): string { return $this->name; }
    }

    class IntegerField  extends ScalarField {}
    class StringField   extends ScalarField {}
    class TextField     extends ScalarField {}
    class FloatField    extends ScalarField {}
    class BooleanField  extends ScalarField {}
    class DateField     extends ScalarField {}
    class DatetimeField extends ScalarField {}
}

// =============================================================================
// Stubs Bitrix\Main\ORM
// =============================================================================

namespace Bitrix\Main\ORM {

    use Bitrix\Main\ORM\Fields\ScalarField;
    use Bitrix\Main\DB\OrmResult;

    class FakeEntity
    {
        public static array  $compileCalls = [];
        public static string $dataClass    = '';

        public function getDataClass(): string
        {
            return self::$dataClass;
        }

        public function createDbTable(): void
        {
            // no-op in tests
        }

        public static function reset(): void
        {
            self::$compileCalls = [];
            self::$dataClass    = '';
        }
    }

    class Entity
    {
        public static function compileEntity(string $name, array $fields = [], array $params = []): FakeEntity
        {
            FakeEntity::$compileCalls[] = ['name' => $name, 'fields' => $fields, 'params' => $params];
            return new FakeEntity();
        }
    }
}

// =============================================================================
// Stubs Bitrix\Main\DB
// =============================================================================

namespace Bitrix\Main\DB {

    class OrmResult
    {
        public function __construct(
            private bool  $success,
            private int   $id     = 0,
            private array $errors = []
        ) {}

        public function isSuccess(): bool         { return $this->success; }
        public function getId(): int              { return $this->id; }
        public function getErrorMessages(): array { return $this->errors; }
    }

    class FakeQueryResult
    {
        private array $rows;
        private int   $pos = 0;

        public function __construct(array $rows) { $this->rows = $rows; }

        public function fetch(): array|false
        {
            return $this->rows[$this->pos++] ?? false;
        }
    }

    class SqlHelper
    {
        public function forSql(string $v): string { return addslashes($v); }
    }

    class FakeConnection
    {
        public static array  $queries       = [];
        public static array  $queryRows     = [];
        public static bool   $tableExists   = false;
        /** @var array<string, bool> per-table overrides */
        public static array  $tableExistsMap = [];

        private SqlHelper $helper;

        public function __construct() { $this->helper = new SqlHelper(); }

        public function isTableExists(string $table): bool
        {
            return self::$tableExistsMap[$table] ?? self::$tableExists;
        }

        public function query(string $sql, array $params = []): FakeQueryResult
        {
            self::$queries[] = ['sql' => $sql, 'params' => $params];
            return new FakeQueryResult(self::$queryRows);
        }

        public function getSqlHelper(): SqlHelper
        {
            return $this->helper;
        }

        public static function reset(): void
        {
            self::$queries        = [];
            self::$queryRows      = [];
            self::$tableExists    = false;
            self::$tableExistsMap = [];
        }
    }
}

// =============================================================================
// Stubs Bitrix\Main
// =============================================================================

namespace Bitrix\Main {

    use Bitrix\Main\DB\FakeConnection;

    class Application
    {
        private static FakeConnection $conn;

        public static function getConnection(): FakeConnection
        {
            if (!isset(self::$conn)) {
                self::$conn = new FakeConnection();
            }
            return self::$conn;
        }

        public static function resetConnection(): void
        {
            self::$conn = new FakeConnection();
            FakeConnection::reset();
        }
    }
}

// =============================================================================
// Fake DataManager для CRUD-тестов
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use Bitrix\Main\DB\FakeQueryResult;
    use Bitrix\Main\DB\OrmResult;

    class FakeOrmDataManager
    {
        public static array $addCalls      = [];
        public static array $updateCalls   = [];
        public static array $deleteCalls   = [];
        public static array $getListParams = [];
        public static array $rows          = [];
        public static bool  $addSuccess    = true;
        public static int   $addId         = 1;

        public static function getList(array $params): FakeQueryResult
        {
            self::$getListParams = $params;
            return new FakeQueryResult(self::$rows);
        }

        public static function getById(int $id): FakeQueryResult
        {
            $found = array_values(array_filter(self::$rows, fn($r) => $r['ID'] === $id));
            return new FakeQueryResult($found);
        }

        public static function add(array $fields): OrmResult
        {
            self::$addCalls[] = $fields;
            return new OrmResult(self::$addSuccess, self::$addId);
        }

        public static function update(int $id, array $fields): OrmResult
        {
            self::$updateCalls[] = ['id' => $id, 'fields' => $fields];
            return new OrmResult(true);
        }

        public static function delete(int $id): OrmResult
        {
            self::$deleteCalls[] = $id;
            return new OrmResult(true);
        }

        public static function reset(): void
        {
            self::$addCalls      = [];
            self::$updateCalls   = [];
            self::$deleteCalls   = [];
            self::$getListParams = [];
            self::$rows          = [];
            self::$addSuccess    = true;
            self::$addId         = 1;
        }
    }

// =============================================================================
// Тесты
// =============================================================================

    use Bitrix\Main\Application;
    use Bitrix\Main\DB\FakeConnection;
    use Bitrix\Main\ORM\FakeEntity;
    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\OrmTools;

    class OrmToolsTest extends TestCase
    {
        private OrmTools $tools;

        protected function setUp(): void
        {
            $this->tools = new OrmTools();
            Application::resetConnection();
            FakeEntity::reset();
            FakeOrmDataManager::reset();
        }

        // ─── Реестр ──────────────────────────────────────────────────────────

        public function testCreateEntityCreatesRegistryTableIfNotExists(): void
        {
            FakeConnection::$tableExists = false;

            $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [['name' => 'NAME', 'type' => 'string']],
            ]);

            $sqls = array_column(FakeConnection::$queries, 'sql');
            $createRegistry = array_filter($sqls, fn($s) => str_contains($s, 'b_mcp_orm_registry') && str_contains($s, 'CREATE TABLE'));
            $this->assertNotEmpty($createRegistry);
        }

        public function testCreateEntityDoesNotRecreateRegistryIfExists(): void
        {
            FakeConnection::$tableExistsMap = ['b_mcp_orm_registry' => true];

            $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [['name' => 'NAME', 'type' => 'string']],
            ]);

            $sqls = array_column(FakeConnection::$queries, 'sql');
            $createRegistry = array_filter($sqls, fn($s) => str_contains($s, 'b_mcp_orm_registry') && str_contains($s, 'CREATE TABLE'));
            $this->assertEmpty($createRegistry);
        }

        public function testCreateEntityThrowsWhenTableExists(): void
        {
            FakeConnection::$tableExists = true;

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('уже существует');

            $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [],
            ]);
        }

        public function testCreateEntityCallsCompileEntityWithCorrectParams(): void
        {
            FakeConnection::$tableExists = false;

            $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [['name' => 'NAME', 'type' => 'string']],
            ]);

            $this->assertCount(1, FakeEntity::$compileCalls);
            $call = FakeEntity::$compileCalls[0];
            $this->assertEquals('ProductLog',    $call['name']);
            $this->assertEquals('b_product_log', $call['params']['table_name']);
        }

        public function testCreateEntityAutoAddsIdFieldIfMissing(): void
        {
            FakeConnection::$tableExists = false;

            $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [['name' => 'NAME', 'type' => 'string']],
            ]);

            $fields = FakeEntity::$compileCalls[0]['fields'];
            $this->assertArrayHasKey('ID', $fields);
        }

        public function testCreateEntityDoesNotDuplicateIdIfAlreadyDefined(): void
        {
            FakeConnection::$tableExists = false;

            $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [
                    ['name' => 'ID',   'type' => 'integer', 'primary' => true, 'autocomplete' => true],
                    ['name' => 'NAME', 'type' => 'string'],
                ],
            ]);

            $fields = FakeEntity::$compileCalls[0]['fields'];
            $idFields = array_filter(array_keys($fields), fn($k) => $k === 'ID');
            $this->assertCount(1, $idFields);
        }

        public function testCreateEntitySavesJsonFieldsToRegistry(): void
        {
            FakeConnection::$tableExists = false;

            $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [['name' => 'NAME', 'type' => 'string']],
            ]);

            $sqls = array_column(FakeConnection::$queries, 'sql');
            $insertSql = array_values(array_filter($sqls, fn($s) => str_contains($s, 'INSERT INTO') && str_contains($s, 'b_mcp_orm_registry')));
            $this->assertNotEmpty($insertSql);
        }

        public function testCreateEntityReturnsSuccessWithNames(): void
        {
            FakeConnection::$tableExists = false;

            $result = $this->tools->createEntity([
                'entity_name' => 'ProductLog',
                'table_name'  => 'b_product_log',
                'fields'      => [['name' => 'NAME', 'type' => 'string']],
            ]);

            $this->assertTrue($result['success']);
            $this->assertEquals('ProductLog',    $result['entity_name']);
            $this->assertEquals('b_product_log', $result['table_name']);
        }

        public function testBuildOrmFieldsMapsAllTypes(): void
        {
            FakeConnection::$tableExists = false;

            $this->tools->createEntity([
                'entity_name' => 'TypeTest',
                'table_name'  => 'b_type_test',
                'fields'      => [
                    ['name' => 'F1', 'type' => 'integer'],
                    ['name' => 'F2', 'type' => 'string'],
                    ['name' => 'F3', 'type' => 'text'],
                    ['name' => 'F4', 'type' => 'float'],
                    ['name' => 'F5', 'type' => 'boolean'],
                    ['name' => 'F6', 'type' => 'date'],
                    ['name' => 'F7', 'type' => 'datetime'],
                ],
            ]);

            $fields = FakeEntity::$compileCalls[0]['fields'];
            $this->assertInstanceOf(\Bitrix\Main\ORM\Fields\IntegerField::class,  $fields['F1']);
            $this->assertInstanceOf(\Bitrix\Main\ORM\Fields\StringField::class,   $fields['F2']);
            $this->assertInstanceOf(\Bitrix\Main\ORM\Fields\TextField::class,     $fields['F3']);
            $this->assertInstanceOf(\Bitrix\Main\ORM\Fields\FloatField::class,    $fields['F4']);
            $this->assertInstanceOf(\Bitrix\Main\ORM\Fields\BooleanField::class,  $fields['F5']);
            $this->assertInstanceOf(\Bitrix\Main\ORM\Fields\DateField::class,     $fields['F6']);
            $this->assertInstanceOf(\Bitrix\Main\ORM\Fields\DatetimeField::class, $fields['F7']);
        }

        public function testBuildOrmFieldsThrowsOnUnknownType(): void
        {
            FakeConnection::$tableExists = false;

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Неизвестный тип поля');

            $this->tools->createEntity([
                'entity_name' => 'Bad',
                'table_name'  => 'b_bad',
                'fields'      => [['name' => 'F', 'type' => 'unknown_type']],
            ]);
        }

        public function testGetEntityThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найдена');

            $this->tools->getEntity(['entity_name' => 'Missing']);
        }

        public function testDropEntityExecutesDropTable(): void
        {
            FakeConnection::$queryRows = [['ID' => 1, 'ENTITY_NAME' => 'Logs', 'TABLE_NAME' => 'b_logs', 'FIELDS' => '[]']];

            $this->tools->dropEntity(['entity_name' => 'Logs']);

            $sqls = array_column(FakeConnection::$queries, 'sql');
            $dropSql = array_filter($sqls, fn($s) => str_contains($s, 'DROP TABLE'));
            $this->assertNotEmpty($dropSql);
        }

        public function testDropEntityDeletesFromRegistry(): void
        {
            FakeConnection::$queryRows = [['ID' => 1, 'ENTITY_NAME' => 'Logs', 'TABLE_NAME' => 'b_logs', 'FIELDS' => '[]']];

            $this->tools->dropEntity(['entity_name' => 'Logs']);

            $sqls = array_column(FakeConnection::$queries, 'sql');
            $deleteSql = array_filter($sqls, fn($s) => str_contains($s, 'DELETE FROM') && str_contains($s, 'b_mcp_orm_registry'));
            $this->assertNotEmpty($deleteSql);
        }

        public function testDropEntityThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);

            $this->tools->dropEntity(['entity_name' => 'Missing']);
        }

        // ─── CRUD записей ─────────────────────────────────────────────────────

        private function prepareForCrud(): void
        {
            FakeConnection::$queryRows = [[
                'ID'          => 1,
                'ENTITY_NAME' => 'Products',
                'TABLE_NAME'  => 'b_products',
                'FIELDS'      => json_encode([
                    ['name' => 'ID',   'type' => 'integer', 'primary' => true, 'autocomplete' => true],
                    ['name' => 'NAME', 'type' => 'string'],
                ]),
            ]];
            FakeEntity::$dataClass = FakeOrmDataManager::class;
        }

        public function testOrmListPassesFilterAndPagination(): void
        {
            $this->prepareForCrud();

            $this->tools->listRows([
                'entity_name' => 'Products',
                'filter'      => ['NAME' => 'Test'],
                'limit'       => 5,
                'offset'      => 10,
            ]);

            $params = FakeOrmDataManager::$getListParams;
            $this->assertEquals(['NAME' => 'Test'], $params['filter']);
            $this->assertEquals(5,  $params['limit']);
            $this->assertEquals(10, $params['offset']);
        }

        public function testOrmListDefaultLimitIs20(): void
        {
            $this->prepareForCrud();
            $this->tools->listRows(['entity_name' => 'Products']);
            $this->assertEquals(20, FakeOrmDataManager::$getListParams['limit']);
        }

        public function testOrmGetReturnsRow(): void
        {
            $this->prepareForCrud();
            FakeOrmDataManager::$rows = [['ID' => 3, 'NAME' => 'Widget']];

            $result = $this->tools->getRow(['entity_name' => 'Products', 'id' => 3]);

            $this->assertEquals(3,        $result['ID']);
            $this->assertEquals('Widget', $result['NAME']);
        }

        public function testOrmGetThrowsWhenNotFound(): void
        {
            $this->prepareForCrud();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найдена');

            $this->tools->getRow(['entity_name' => 'Products', 'id' => 999]);
        }

        public function testOrmAddPassesFields(): void
        {
            $this->prepareForCrud();

            $this->tools->addRow([
                'entity_name' => 'Products',
                'fields'      => ['NAME' => 'NewProduct'],
            ]);

            $this->assertEquals(['NAME' => 'NewProduct'], FakeOrmDataManager::$addCalls[0]);
        }

        public function testOrmAddReturnsId(): void
        {
            $this->prepareForCrud();
            FakeOrmDataManager::$addId = 42;

            $result = $this->tools->addRow([
                'entity_name' => 'Products',
                'fields'      => ['NAME' => 'Test'],
            ]);

            $this->assertTrue($result['success']);
            $this->assertEquals(42, $result['id']);
        }

        public function testOrmUpdatePassesIdAndFields(): void
        {
            $this->prepareForCrud();

            $this->tools->updateRow([
                'entity_name' => 'Products',
                'id'          => 7,
                'fields'      => ['NAME' => 'Updated'],
            ]);

            $call = FakeOrmDataManager::$updateCalls[0];
            $this->assertEquals(7,         $call['id']);
            $this->assertEquals('Updated', $call['fields']['NAME']);
        }

        public function testOrmDeleteCallsDeleteWithId(): void
        {
            $this->prepareForCrud();

            $this->tools->deleteRow(['entity_name' => 'Products', 'id' => 5]);

            $this->assertContains(5, FakeOrmDataManager::$deleteCalls);
        }

        public function testOrmCrudThrowsWhenEntityNotInRegistry(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найдена в реестре');

            $this->tools->listRows(['entity_name' => 'Unknown']);
        }
    }
}
