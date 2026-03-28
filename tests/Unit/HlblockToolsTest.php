<?php

declare(strict_types=1);

// =============================================================================
// Stubs глобального неймспейса
// =============================================================================

namespace {

    class FakeOldResult
    {
        private array $rows;
        private int   $pos = 0;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function GetNext(): array|false
        {
            return $this->rows[$this->pos++] ?? false;
        }
    }

    class FakeException
    {
        public function __construct(private string $message) {}
        public function GetString(): string { return $this->message; }
    }

    class FakeApplication
    {
        private static ?FakeException $exception = null;

        public static function setException(?FakeException $e): void
        {
            self::$exception = $e;
        }

        public function GetException(): ?FakeException
        {
            return self::$exception;
        }

        public static function reset(): void
        {
            self::$exception = null;
        }
    }

    // Инициализируем $GLOBALS['APPLICATION'] один раз
    $GLOBALS['APPLICATION'] = new FakeApplication();

    class CUserTypeEntity
    {
        public static array      $addCalls     = [];
        public static array      $updateCalls  = [];
        public static array      $deleteCalls  = [];
        public static array      $getListCalls = [];
        public static array      $getListRows  = [];
        public static int|false  $addReturn    = 1;
        public static bool       $updateReturn = true;
        public static bool       $deleteReturn = true;
        public string $LAST_ERROR = '';

        public function Add(array $fields): int|false
        {
            self::$addCalls[] = $fields;
            return self::$addReturn;
        }

        public function Update(int $id, array $fields): bool
        {
            self::$updateCalls[] = ['id' => $id, 'fields' => $fields];
            return self::$updateReturn;
        }

        public function Delete(int $id): bool
        {
            self::$deleteCalls[] = $id;
            return self::$deleteReturn;
        }

        public function GetList(array $order, array $filter): FakeOldResult
        {
            self::$getListCalls[] = ['order' => $order, 'filter' => $filter];
            return new FakeOldResult(self::$getListRows);
        }

        public static function reset(): void
        {
            self::$addCalls     = [];
            self::$updateCalls  = [];
            self::$deleteCalls  = [];
            self::$getListCalls = [];
            self::$getListRows  = [];
            self::$addReturn    = 1;
            self::$updateReturn = true;
            self::$deleteReturn = true;
        }
    }
}

// =============================================================================
// Stubs Bitrix\Main
// =============================================================================

namespace Bitrix\Main {

    class Loader
    {
        public static function includeModule(string $module): bool
        {
            return true;
        }
    }

    class FakeQueryResult
    {
        private array $rows;
        private int   $pos = 0;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function fetch(): array|false
        {
            return $this->rows[$this->pos++] ?? false;
        }
    }

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
}

// =============================================================================
// Stubs Bitrix\Highloadblock
// =============================================================================

namespace Bitrix\Highloadblock {

    use Bitrix\Main\FakeQueryResult;
    use Bitrix\Main\OrmResult;

    class FakeEntity
    {
        public function __construct(private string $dataClass) {}

        public function getDataClass(): string
        {
            return $this->dataClass;
        }
    }

    class HighloadBlockTable
    {
        public static array  $addCalls    = [];
        public static array  $updateCalls = [];
        public static array  $deleteCalls = [];
        public static array  $rows        = [];
        public static bool   $addSuccess  = true;
        public static int    $addId       = 42;
        public static string $dataClass   = '';

        public static function getList(array $params = []): FakeQueryResult
        {
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

        public static function compileEntity(array $hlblock): FakeEntity
        {
            return new FakeEntity(self::$dataClass);
        }

        public static function reset(): void
        {
            self::$addCalls    = [];
            self::$updateCalls = [];
            self::$deleteCalls = [];
            self::$rows        = [];
            self::$addSuccess  = true;
            self::$addId       = 42;
            self::$dataClass   = '';
        }
    }
}

// =============================================================================
// Fake DataManager для тестов элементов
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use Bitrix\Main\FakeQueryResult;
    use Bitrix\Main\OrmResult;

    class FakeHlDataManager
    {
        public static array $addCalls      = [];
        public static array $updateCalls   = [];
        public static array $deleteCalls   = [];
        public static array $getListParams = [];
        public static array $rows          = [];
        public static bool  $addSuccess    = true;
        public static int   $addId         = 99;

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
            self::$addId         = 99;
        }
    }

// =============================================================================
// Тесты
// =============================================================================

    use Bitrix\Highloadblock\HighloadBlockTable;
    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\HlblockTools;

    class HlblockToolsTest extends TestCase
    {
        private HlblockTools $tools;

        protected function setUp(): void
        {
            $this->tools = new HlblockTools();

            \CUserTypeEntity::reset();
            \FakeApplication::reset();
            HighloadBlockTable::reset();
            FakeHlDataManager::reset();
        }

        // ─── HL-блоки ────────────────────────────────────────────────────────

        public function testListHlblocksReturnsAllRows(): void
        {
            HighloadBlockTable::$rows = [
                ['ID' => 1, 'NAME' => 'Products', 'TABLE_NAME' => 'b_hl_products'],
                ['ID' => 2, 'NAME' => 'Colors',   'TABLE_NAME' => 'b_hl_colors'],
            ];

            $result = $this->tools->listHlblocks([]);

            $this->assertCount(2, $result);
            $this->assertEquals('Products', $result[0]['NAME']);
        }

        public function testGetHlblockReturnsRow(): void
        {
            HighloadBlockTable::$rows = [['ID' => 5, 'NAME' => 'Tags', 'TABLE_NAME' => 'b_hl_tags']];

            $result = $this->tools->getHlblock(['id' => 5]);

            $this->assertEquals(5,      $result['ID']);
            $this->assertEquals('Tags', $result['NAME']);
        }

        public function testGetHlblockThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найден');

            $this->tools->getHlblock(['id' => 999]);
        }

        public function testCreateHlblockPassesNameAndTableName(): void
        {
            $this->tools->createHlblock(['name' => 'Products', 'table_name' => 'b_hl_products']);

            $this->assertCount(1, HighloadBlockTable::$addCalls);
            $fields = HighloadBlockTable::$addCalls[0];
            $this->assertEquals('Products',      $fields['NAME']);
            $this->assertEquals('b_hl_products', $fields['TABLE_NAME']);
        }

        public function testCreateHlblockReturnsId(): void
        {
            HighloadBlockTable::$addId = 7;

            $result = $this->tools->createHlblock(['name' => 'Test', 'table_name' => 'b_hl_test']);

            $this->assertTrue($result['success']);
            $this->assertEquals(7, $result['id']);
        }

        public function testCreateHlblockReturnsErrorsOnFailure(): void
        {
            HighloadBlockTable::$addSuccess = false;

            $result = $this->tools->createHlblock(['name' => 'Test', 'table_name' => 'b_hl_test']);

            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('errors', $result);
        }

        public function testUpdateHlblockPassesOnlyProvidedFields(): void
        {
            $this->tools->updateHlblock(['id' => 3, 'name' => 'NewName']);

            $call = HighloadBlockTable::$updateCalls[0];
            $this->assertEquals(3,         $call['id']);
            $this->assertEquals('NewName', $call['fields']['NAME']);
            $this->assertArrayNotHasKey('TABLE_NAME', $call['fields']);
        }

        public function testDeleteHlblockCallsDeleteWithId(): void
        {
            $this->tools->deleteHlblock(['id' => 10]);

            $this->assertContains(10, HighloadBlockTable::$deleteCalls);
        }

        // ─── Поля ─────────────────────────────────────────────────────────────

        public function testListFieldsCallsGetListWithEntityId(): void
        {
            $this->tools->listFields(['hlblock_id' => 3]);

            $this->assertCount(1, \CUserTypeEntity::$getListCalls);
            $this->assertEquals('HLBLOCK_3', \CUserTypeEntity::$getListCalls[0]['filter']['ENTITY_ID']);
        }

        public function testListFieldsPassesLangToFilter(): void
        {
            $this->tools->listFields(['hlblock_id' => 3]);
            $this->assertEquals('ru', \CUserTypeEntity::$getListCalls[0]['filter']['LANG']);
        }

        public function testListFieldsUsesCustomLang(): void
        {
            $this->tools->listFields(['hlblock_id' => 3, 'lang' => 'en']);
            $this->assertEquals('en', \CUserTypeEntity::$getListCalls[0]['filter']['LANG']);
        }

        public function testListFieldsReturnsRows(): void
        {
            \CUserTypeEntity::$getListRows = [
                ['ID' => 1, 'FIELD_NAME' => 'UF_NAME'],
                ['ID' => 2, 'FIELD_NAME' => 'UF_SORT'],
            ];

            $result = $this->tools->listFields(['hlblock_id' => 3]);

            $this->assertCount(2, $result);
            $this->assertEquals('UF_NAME', $result[0]['FIELD_NAME']);
        }

        public function testAddFieldBuildsEntityId(): void
        {
            $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_NAME',
                'type'       => 'string',
            ]);

            $fields = \CUserTypeEntity::$addCalls[0];
            $this->assertEquals('HLBLOCK_5', $fields['ENTITY_ID']);
        }

        public function testAddFieldPassesFieldNameAndType(): void
        {
            $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_PRICE',
                'type'       => 'double',
            ]);

            $fields = \CUserTypeEntity::$addCalls[0];
            $this->assertEquals('UF_PRICE', $fields['FIELD_NAME']);
            $this->assertEquals('double',   $fields['USER_TYPE_ID']);
        }

        public function testAddFieldConvertsBooleanFlagsToYN(): void
        {
            $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_TAGS',
                'type'       => 'string',
                'multiple'   => true,
                'mandatory'  => false,
            ]);

            $fields = \CUserTypeEntity::$addCalls[0];
            $this->assertEquals('Y', $fields['MULTIPLE']);
            $this->assertEquals('N', $fields['MANDATORY']);
        }

        public function testAddFieldDefaultsMultipleAndMandatoryToN(): void
        {
            $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_CODE',
                'type'       => 'string',
            ]);

            $fields = \CUserTypeEntity::$addCalls[0];
            $this->assertEquals('N', $fields['MULTIPLE']);
            $this->assertEquals('N', $fields['MANDATORY']);
        }

        public function testAddFieldDefaultsSortTo100(): void
        {
            $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_CODE',
                'type'       => 'string',
            ]);

            $this->assertEquals(100, \CUserTypeEntity::$addCalls[0]['SORT']);
        }

        public function testAddFieldSetsLabelInRuAndEn(): void
        {
            $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_NAME',
                'type'       => 'string',
                'label'      => 'Название',
            ]);

            $fields = \CUserTypeEntity::$addCalls[0];
            $this->assertEquals('Название', $fields['EDIT_FORM_LABEL']['ru']);
            $this->assertEquals('Название', $fields['EDIT_FORM_LABEL']['en']);
        }

        public function testAddFieldReturnsFieldId(): void
        {
            \CUserTypeEntity::$addReturn = 15;

            $result = $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_NAME',
                'type'       => 'string',
            ]);

            $this->assertTrue($result['success']);
            $this->assertEquals(15, $result['field_id']);
        }

        public function testAddFieldReturnsApplicationExceptionOnFailure(): void
        {
            \CUserTypeEntity::$addReturn = false;
            \FakeApplication::setException(new \FakeException('Field already exists'));

            $result = $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_NAME',
                'type'       => 'string',
            ]);

            $this->assertFalse($result['success']);
            $this->assertEquals('Field already exists', $result['error']);
        }

        public function testAddFieldReturnsFallbackMessageWhenNoException(): void
        {
            \CUserTypeEntity::$addReturn = false;

            $result = $this->tools->addField([
                'hlblock_id' => 5,
                'field_name' => 'UF_NAME',
                'type'       => 'string',
            ]);

            $this->assertFalse($result['success']);
            $this->assertStringContainsString('duplicate', $result['error']);
        }

        public function testUpdateFieldFetchesFieldByEntityIdAndName(): void
        {
            \CUserTypeEntity::$getListRows = [['ID' => 7, 'FIELD_NAME' => 'UF_NAME']];

            $this->tools->updateField([
                'hlblock_id' => 3,
                'field_name' => 'UF_NAME',
                'label'      => 'Новое название',
            ]);

            $filter = \CUserTypeEntity::$getListCalls[0]['filter'];
            $this->assertEquals('HLBLOCK_3', $filter['ENTITY_ID']);
            $this->assertEquals('UF_NAME',   $filter['FIELD_NAME']);
        }

        public function testUpdateFieldPassesLabelToUpdate(): void
        {
            \CUserTypeEntity::$getListRows = [['ID' => 7, 'FIELD_NAME' => 'UF_NAME']];

            $this->tools->updateField([
                'hlblock_id' => 3,
                'field_name' => 'UF_NAME',
                'label'      => 'Новое название',
            ]);

            $call = \CUserTypeEntity::$updateCalls[0];
            $this->assertEquals(7,                $call['id']);
            $this->assertEquals('Новое название', $call['fields']['EDIT_FORM_LABEL']['ru']);
        }

        public function testUpdateFieldThrowsWhenFieldNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найдено');

            $this->tools->updateField(['hlblock_id' => 3, 'field_name' => 'UF_MISSING']);
        }

        public function testDeleteFieldFetchesFieldThenCallsDelete(): void
        {
            \CUserTypeEntity::$getListRows = [['ID' => 12, 'FIELD_NAME' => 'UF_CODE']];

            $this->tools->deleteField(['hlblock_id' => 3, 'field_name' => 'UF_CODE']);

            $this->assertContains(12, \CUserTypeEntity::$deleteCalls);
        }

        public function testDeleteFieldThrowsWhenFieldNotFound(): void
        {
            $this->expectException(\RuntimeException::class);

            $this->tools->deleteField(['hlblock_id' => 3, 'field_name' => 'UF_MISSING']);
        }

        // ─── Элементы ─────────────────────────────────────────────────────────

        private function prepareDataManager(): void
        {
            HighloadBlockTable::$rows      = [['ID' => 1, 'NAME' => 'Test', 'TABLE_NAME' => 'b_test']];
            HighloadBlockTable::$dataClass = FakeHlDataManager::class;
        }

        public function testListElementsPassesFilterAndPagination(): void
        {
            $this->prepareDataManager();

            $this->tools->listElements([
                'hlblock_id' => 1,
                'filter'     => ['UF_ACTIVE' => true],
                'limit'      => 10,
                'offset'     => 5,
            ]);

            $params = FakeHlDataManager::$getListParams;
            $this->assertEquals(['UF_ACTIVE' => true], $params['filter']);
            $this->assertEquals(10, $params['limit']);
            $this->assertEquals(5,  $params['offset']);
        }

        public function testListElementsDefaultLimitIs20(): void
        {
            $this->prepareDataManager();

            $this->tools->listElements(['hlblock_id' => 1]);

            $this->assertEquals(20, FakeHlDataManager::$getListParams['limit']);
        }

        public function testGetElementReturnsRow(): void
        {
            $this->prepareDataManager();
            FakeHlDataManager::$rows = [['ID' => 5, 'UF_NAME' => 'Bar']];

            $result = $this->tools->getElement(['hlblock_id' => 1, 'id' => 5]);

            $this->assertEquals(5,     $result['ID']);
            $this->assertEquals('Bar', $result['UF_NAME']);
        }

        public function testGetElementThrowsWhenNotFound(): void
        {
            $this->prepareDataManager();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найден');

            $this->tools->getElement(['hlblock_id' => 1, 'id' => 999]);
        }

        public function testAddElementPassesFieldsToDataManager(): void
        {
            $this->prepareDataManager();

            $this->tools->addElement([
                'hlblock_id' => 1,
                'fields'     => ['UF_NAME' => 'Widget', 'UF_SORT' => 100],
            ]);

            $this->assertCount(1, FakeHlDataManager::$addCalls);
            $this->assertEquals('Widget', FakeHlDataManager::$addCalls[0]['UF_NAME']);
            $this->assertEquals(100,      FakeHlDataManager::$addCalls[0]['UF_SORT']);
        }

        public function testAddElementReturnsId(): void
        {
            $this->prepareDataManager();
            FakeHlDataManager::$addId = 55;

            $result = $this->tools->addElement([
                'hlblock_id' => 1,
                'fields'     => ['UF_NAME' => 'Test'],
            ]);

            $this->assertTrue($result['success']);
            $this->assertEquals(55, $result['id']);
        }

        public function testUpdateElementPassesIdAndFields(): void
        {
            $this->prepareDataManager();

            $this->tools->updateElement([
                'hlblock_id' => 1,
                'id'         => 7,
                'fields'     => ['UF_NAME' => 'Updated'],
            ]);

            $call = FakeHlDataManager::$updateCalls[0];
            $this->assertEquals(7,         $call['id']);
            $this->assertEquals('Updated', $call['fields']['UF_NAME']);
        }

        public function testDeleteElementCallsDeleteWithId(): void
        {
            $this->prepareDataManager();

            $this->tools->deleteElement(['hlblock_id' => 1, 'id' => 8]);

            $this->assertContains(8, FakeHlDataManager::$deleteCalls);
        }

        public function testGetDataClassThrowsWhenHlblockNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найден');

            $this->tools->listElements(['hlblock_id' => 999]);
        }
    }
}
