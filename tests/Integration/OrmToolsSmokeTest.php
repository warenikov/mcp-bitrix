<?php

declare(strict_types=1);

namespace Warenikov\McpBitrix\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Warenikov\McpBitrix\BitrixBootstrap;
use Warenikov\McpBitrix\Tools\OrmTools;

/**
 * Smoke-тесты OrmTools на реальном Bitrix.
 * Запускать через: composer test-integration
 *
 * Требования:
 *   - Реальный Bitrix смонтирован в /var/www/html (или BITRIX_DOCUMENT_ROOT)
 *   - Доступ к БД
 *
 * Сущность создаётся один раз для всего набора тестов (ограничение PHP:
 * eval()-классы нельзя переобъявить в том же процессе).
 */
class OrmToolsSmokeTest extends TestCase
{
    private const ENTITY_NAME = 'McpSmokeTest';
    private const TABLE_NAME  = 'b_mcp_smoke_test';

    private static OrmTools $tools;

    public static function setUpBeforeClass(): void
    {
        BitrixBootstrap::init();
        self::$tools = new OrmTools();

        // Чистим остатки предыдущего запуска
        self::dropEntitySilently();

        self::$tools->createEntity([
            'entity_name' => self::ENTITY_NAME,
            'table_name'  => self::TABLE_NAME,
            'fields'      => [
                ['name' => 'TITLE',      'type' => 'string',   'size' => 255],
                ['name' => 'AMOUNT',     'type' => 'float'],
                ['name' => 'ACTIVE',     'type' => 'boolean'],
                ['name' => 'CREATED_AT', 'type' => 'datetime'],
            ],
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropEntitySilently();
    }

    protected function setUp(): void
    {
        // Удаляем все строки между тестами
        $rows = self::$tools->listRows(['entity_name' => self::ENTITY_NAME, 'limit' => 1000]);
        foreach ($rows as $row) {
            self::$tools->deleteRow(['entity_name' => self::ENTITY_NAME, 'id' => (int) $row['ID']]);
        }
    }

    // ── Сущности ─────────────────────────────────────────────────────────────

    public function testEntityAppearsInList(): void
    {
        $list  = self::$tools->listEntities([]);
        $names = array_column($list, 'ENTITY_NAME');

        $this->assertContains(self::ENTITY_NAME, $names);
    }

    public function testGetEntityReturnsCorrectMeta(): void
    {
        $entity = self::$tools->getEntity(['entity_name' => self::ENTITY_NAME]);

        $this->assertEquals(self::ENTITY_NAME, $entity['ENTITY_NAME']);
        $this->assertEquals(self::TABLE_NAME,  $entity['TABLE_NAME']);
        $this->assertIsArray($entity['FIELDS']);
    }

    public function testCreateEntityThrowsOnDuplicate(): void
    {
        $this->expectException(\RuntimeException::class);

        self::$tools->createEntity([
            'entity_name' => self::ENTITY_NAME,
            'table_name'  => self::TABLE_NAME,
            'fields'      => [],
        ]);
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function testAddRowReturnsId(): void
    {
        $result = self::$tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'Item A', 'AMOUNT' => 9.99, 'ACTIVE' => true],
        ]);

        $this->assertTrue($result['success']);
        $this->assertIsInt($result['id']);
        $this->assertGreaterThan(0, $result['id']);
    }

    public function testGetRowById(): void
    {
        $id = self::$tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'Find me'],
        ])['id'];

        $row = self::$tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
        $this->assertEquals('Find me', $row['TITLE']);
    }

    public function testGetRowThrowsWhenNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        self::$tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => 999999]);
    }

    public function testBooleanFieldStoredAsYN(): void
    {
        $id = self::$tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'bool test', 'ACTIVE' => true],
        ])['id'];

        $row = self::$tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
        $this->assertEquals('Y', $row['ACTIVE']);
    }

    public function testDatetimeFieldAcceptsString(): void
    {
        $result = self::$tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'datetime test', 'CREATED_AT' => '2026-01-15 10:30:00'],
        ]);

        $this->assertTrue($result['success']);
    }

    public function testListRowsReturnsAll(): void
    {
        self::$tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'A']]);
        self::$tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'B']]);
        self::$tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'C']]);

        $rows = self::$tools->listRows(['entity_name' => self::ENTITY_NAME]);
        $this->assertCount(3, $rows);
    }

    public function testListRowsWithFilter(): void
    {
        self::$tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'Active',   'ACTIVE' => true]]);
        self::$tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'Inactive', 'ACTIVE' => false]]);

        $rows = self::$tools->listRows([
            'entity_name' => self::ENTITY_NAME,
            'filter'      => ['=ACTIVE' => 'Y'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertEquals('Active', $rows[0]['TITLE']);
    }

    public function testListRowsLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            self::$tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => "Item $i"]]);
        }

        $rows = self::$tools->listRows(['entity_name' => self::ENTITY_NAME, 'limit' => 3]);
        $this->assertCount(3, $rows);
    }

    public function testUpdateRow(): void
    {
        $id = self::$tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'Old', 'ACTIVE' => false],
        ])['id'];

        $result = self::$tools->updateRow([
            'entity_name' => self::ENTITY_NAME,
            'id'          => $id,
            'fields'      => ['TITLE' => 'New', 'ACTIVE' => true],
        ]);

        $this->assertTrue($result['success']);

        $row = self::$tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
        $this->assertEquals('New', $row['TITLE']);
        $this->assertEquals('Y',   $row['ACTIVE']);
    }

    public function testDeleteRow(): void
    {
        $id = self::$tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'To delete'],
        ])['id'];

        $result = self::$tools->deleteRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
        $this->assertTrue($result['success']);

        $this->expectException(\RuntimeException::class);
        self::$tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
    }

    // ── Вспомогательные ──────────────────────────────────────────────────────

    private static function dropEntitySilently(): void
    {
        try {
            self::$tools->dropEntity(['entity_name' => self::ENTITY_NAME]);
        } catch (\Throwable) {
            // Сущность не существует — ок
        }
    }
}
