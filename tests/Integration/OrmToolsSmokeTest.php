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
 */
class OrmToolsSmokeTest extends TestCase
{
    private const ENTITY_NAME = 'McpSmokeTest';
    private const TABLE_NAME  = 'b_mcp_smoke_test';

    private OrmTools $tools;

    public static function setUpBeforeClass(): void
    {
        BitrixBootstrap::init();
    }

    protected function setUp(): void
    {
        $this->tools = new OrmTools();
        $this->cleanupTestEntity();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestEntity();
    }

    // ── Сущности ─────────────────────────────────────────────────────────────

    public function testCreateEntity(): void
    {
        $result = $this->tools->createEntity([
            'entity_name' => self::ENTITY_NAME,
            'table_name'  => self::TABLE_NAME,
            'fields'      => [
                ['name' => 'TITLE',      'type' => 'string',   'size' => 255],
                ['name' => 'AMOUNT',     'type' => 'float'],
                ['name' => 'ACTIVE',     'type' => 'boolean'],
                ['name' => 'CREATED_AT', 'type' => 'datetime'],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(self::ENTITY_NAME, $result['entity_name']);
        $this->assertEquals(self::TABLE_NAME,  $result['table_name']);
    }

    public function testCreateEntityThrowsIfAlreadyExists(): void
    {
        $this->createTestEntity();

        $this->expectException(\RuntimeException::class);
        $this->tools->createEntity([
            'entity_name' => self::ENTITY_NAME,
            'table_name'  => self::TABLE_NAME,
            'fields'      => [],
        ]);
    }

    public function testListEntitiesContainsCreated(): void
    {
        $this->createTestEntity();

        $list = $this->tools->listEntities([]);
        $names = array_column($list, 'ENTITY_NAME');

        $this->assertContains(self::ENTITY_NAME, $names);
    }

    public function testGetEntity(): void
    {
        $this->createTestEntity();

        $entity = $this->tools->getEntity(['entity_name' => self::ENTITY_NAME]);

        $this->assertEquals(self::ENTITY_NAME, $entity['ENTITY_NAME']);
        $this->assertEquals(self::TABLE_NAME,  $entity['TABLE_NAME']);
        $this->assertIsArray($entity['FIELDS']);
    }

    public function testDropEntity(): void
    {
        $this->createTestEntity();

        $result = $this->tools->dropEntity(['entity_name' => self::ENTITY_NAME]);
        $this->assertTrue($result['success']);

        // После удаления сущность не должна находиться
        $this->expectException(\RuntimeException::class);
        $this->tools->getEntity(['entity_name' => self::ENTITY_NAME]);
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function testAddAndGetRow(): void
    {
        $this->createTestEntity();

        $addResult = $this->tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => [
                'TITLE'      => 'Test item',
                'AMOUNT'     => 99.99,
                'ACTIVE'     => true,
                'CREATED_AT' => '2026-01-01 12:00:00',
            ],
        ]);

        $this->assertTrue($addResult['success']);
        $this->assertIsInt($addResult['id']);

        $row = $this->tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => $addResult['id']]);
        $this->assertEquals('Test item', $row['TITLE']);
        $this->assertEquals('Y',         $row['ACTIVE']);
    }

    public function testListRows(): void
    {
        $this->createTestEntity();

        $this->tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'A', 'ACTIVE' => true]]);
        $this->tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'B', 'ACTIVE' => false]]);

        $rows = $this->tools->listRows(['entity_name' => self::ENTITY_NAME]);
        $this->assertCount(2, $rows);
    }

    public function testListRowsWithFilter(): void
    {
        $this->createTestEntity();

        $this->tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'Active',   'ACTIVE' => true]]);
        $this->tools->addRow(['entity_name' => self::ENTITY_NAME, 'fields' => ['TITLE' => 'Inactive', 'ACTIVE' => false]]);

        $rows = $this->tools->listRows([
            'entity_name' => self::ENTITY_NAME,
            'filter'      => ['=ACTIVE' => 'Y'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertEquals('Active', $rows[0]['TITLE']);
    }

    public function testUpdateRow(): void
    {
        $this->createTestEntity();

        $id = $this->tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'Old title'],
        ])['id'];

        $updateResult = $this->tools->updateRow([
            'entity_name' => self::ENTITY_NAME,
            'id'          => $id,
            'fields'      => ['TITLE' => 'New title'],
        ]);

        $this->assertTrue($updateResult['success']);

        $row = $this->tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
        $this->assertEquals('New title', $row['TITLE']);
    }

    public function testDeleteRow(): void
    {
        $this->createTestEntity();

        $id = $this->tools->addRow([
            'entity_name' => self::ENTITY_NAME,
            'fields'      => ['TITLE' => 'To delete'],
        ])['id'];

        $deleteResult = $this->tools->deleteRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
        $this->assertTrue($deleteResult['success']);

        $this->expectException(\RuntimeException::class);
        $this->tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => $id]);
    }

    public function testGetRowThrowsWhenNotFound(): void
    {
        $this->createTestEntity();

        $this->expectException(\RuntimeException::class);
        $this->tools->getRow(['entity_name' => self::ENTITY_NAME, 'id' => 999999]);
    }

    // ── Вспомогательные ──────────────────────────────────────────────────────

    private function createTestEntity(): void
    {
        $this->tools->createEntity([
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

    private function cleanupTestEntity(): void
    {
        try {
            $this->tools->dropEntity(['entity_name' => self::ENTITY_NAME]);
        } catch (\Throwable) {
            // Сущность не существует — ок
        }
    }
}
