<?php

declare(strict_types=1);

namespace Warenikov\McpBitrix\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Warenikov\McpBitrix\BitrixBootstrap;
use Warenikov\McpBitrix\Tools\AgentTools;
use Warenikov\McpBitrix\Tools\CacheTools;
use Warenikov\McpBitrix\Tools\OptionTools;
use Warenikov\McpBitrix\Tools\EventLogTools;
use Warenikov\McpBitrix\Tools\MailEventTools;

/**
 * Smoke-тесты системных инструментов на реальном Bitrix.
 * Запускать через: composer test-integration
 *
 * Требования:
 *   - Реальный Bitrix смонтирован в /var/www/html (или BITRIX_DOCUMENT_ROOT)
 *   - Доступ к БД
 */
class SystemToolsSmokeTest extends TestCase
{
    private const AGENT_NAME      = '\Warenikov\McpBitrix\Tests\Integration\SystemToolsSmokeTest::smokeAgentStub();';
    private const OPTION_MODULE   = 'mcp_bitrix_test';
    private const OPTION_NAME     = 'smoke_test_value';
    private const EVENT_TYPE_CODE = 'MCP_SMOKE_TEST';
    private const LOG_AUDIT_TYPE  = 'MCP_SMOKE_TEST';

    public static function setUpBeforeClass(): void
    {
        BitrixBootstrap::init();
    }

    // ── AgentTools ────────────────────────────────────────────────────────────

    public function testAgentLifecycle(): void
    {
        $tools = new AgentTools();

        // Создаём
        $add = $tools->addAgent([
            'name'      => self::AGENT_NAME,
            'module_id' => '',
            'interval'  => 3600,
            'active'    => false,
        ]);
        $this->assertTrue($add['success']);
        $id = $add['id'];
        $this->assertGreaterThan(0, $id);

        // Получаем по ID
        $agent = $tools->getAgent(['id' => $id]);
        $this->assertEquals(self::AGENT_NAME, $agent['NAME']);
        $this->assertEquals('N', $agent['ACTIVE']);

        // Обновляем
        $upd = $tools->updateAgent(['id' => $id, 'active' => true, 'interval' => 7200]);
        $this->assertTrue($upd['success']);

        $agent = $tools->getAgent(['id' => $id]);
        $this->assertEquals('Y', $agent['ACTIVE']);
        $this->assertEquals('7200', $agent['AGENT_INTERVAL']);

        // Запускаем (устанавливает NEXT_EXEC)
        $run = $tools->runAgent(['id' => $id]);
        $this->assertTrue($run['success']);

        // Список содержит наш агент
        $list = $tools->listAgents(['filter' => ['NAME' => self::AGENT_NAME]]);
        $ids  = array_column($list, 'ID');
        $this->assertContains((string) $id, $ids);

        // Удаляем
        $del = $tools->deleteAgent(['id' => $id]);
        $this->assertTrue($del['success']);

        // После удаления не найти
        $this->expectException(\RuntimeException::class);
        $tools->getAgent(['id' => $id]);
    }

    // ── CacheTools ────────────────────────────────────────────────────────────

    public function testClearCacheFullDoesNotThrow(): void
    {
        $tools  = new CacheTools();
        $result = $tools->clearCache([]);

        $this->assertTrue($result['success']);
        $this->assertEquals('all', $result['cleared']);
    }

    public function testClearCacheByTagDoesNotThrow(): void
    {
        $tools  = new CacheTools();
        $result = $tools->clearCache(['tag' => 'iblock_id_1']);

        $this->assertTrue($result['success']);
        $this->assertEquals('tag', $result['cleared']);
    }

    public function testClearMenuCacheReturnsDeletedCount(): void
    {
        $tools  = new CacheTools();
        $result = $tools->clearMenuCache([]);

        $this->assertTrue($result['success']);
        $this->assertIsInt($result['deleted_files']);
        $this->assertGreaterThanOrEqual(0, $result['deleted_files']);
    }

    // ── OptionTools ───────────────────────────────────────────────────────────

    public function testOptionLifecycle(): void
    {
        $tools = new OptionTools();

        // Устанавливаем
        $set = $tools->setOption([
            'module_id' => self::OPTION_MODULE,
            'name'      => self::OPTION_NAME,
            'value'     => 'hello_smoke',
        ]);
        $this->assertTrue($set['success']);

        // Читаем
        $get = $tools->getOption([
            'module_id' => self::OPTION_MODULE,
            'name'      => self::OPTION_NAME,
        ]);
        $this->assertEquals('hello_smoke', $get['value']);

        // Список содержит нашу опцию
        $list  = $tools->listOptions(['module_id' => self::OPTION_MODULE]);
        $names = array_column($list, 'NAME');
        $this->assertContains(self::OPTION_NAME, $names);

        // Удаляем
        $del = $tools->deleteOption([
            'module_id' => self::OPTION_MODULE,
            'name'      => self::OPTION_NAME,
        ]);
        $this->assertTrue($del['success']);

        // После удаления — дефолт
        $after = $tools->getOption([
            'module_id' => self::OPTION_MODULE,
            'name'      => self::OPTION_NAME,
            'default'   => '__missing__',
        ]);
        $this->assertEquals('__missing__', $after['value']);
    }

    // ── EventLogTools ─────────────────────────────────────────────────────────

    public function testEventLogAdd(): void
    {
        $tools  = new EventLogTools();
        $result = $tools->addEventLog([
            'severity'      => 'INFO',
            'audit_type_id' => self::LOG_AUDIT_TYPE,
            'module_id'     => 'mcp_bitrix',
            'item_id'       => '0',
            'description'   => 'Integration smoke test',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testEventLogListAndGet(): void
    {
        $tools = new EventLogTools();

        $tools->addEventLog([
            'audit_type_id' => self::LOG_AUDIT_TYPE,
            'description'   => 'Smoke list test',
        ]);

        $list = $tools->listEventLog([
            'filter' => ['AUDIT_TYPE_ID' => self::LOG_AUDIT_TYPE],
            'limit'  => 1,
        ]);

        $this->assertNotEmpty($list);

        $id  = (int) $list[0]['ID'];
        $row = $tools->getEventLog(['id' => $id]);
        $this->assertEquals((string) $id, $row['ID']);
    }

    public function testEventLogGetThrowsForMissingId(): void
    {
        $tools = new EventLogTools();
        $this->expectException(\RuntimeException::class);
        $tools->getEventLog(['id' => PHP_INT_MAX]);
    }

    public function testClearEventLog(): void
    {
        $tools  = new EventLogTools();
        $result = $tools->clearEventLog(['older_than_days' => 9999]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('cleared_before', $result);
    }

    // ── MailEventTools ────────────────────────────────────────────────────────

    public function testMailEventTypeList(): void
    {
        $tools = new MailEventTools();
        $list  = $tools->listMailEventTypes([]);

        // В любом Битриксе есть хотя бы один тип события
        $this->assertNotEmpty($list);
        $this->assertArrayHasKey('EVENT_NAME', $list[0]);
    }

    public function testMailEventTypeGetByName(): void
    {
        $tools = new MailEventTools();

        // Берём первый существующий тип и ищем его по имени
        $list      = $tools->listMailEventTypes([]);
        $eventName = $list[0]['EVENT_NAME'];

        $row = $tools->getMailEventType(['event_name' => $eventName]);
        $this->assertEquals($eventName, $row['EVENT_NAME']);
    }

    public function testMailEventTypeGetThrowsForMissing(): void
    {
        $tools = new MailEventTools();
        $this->expectException(\RuntimeException::class);
        $tools->getMailEventType(['event_name' => 'MCP_NONEXISTENT_XYZXYZ']);
    }

    public function testMailTemplateLifecycle(): void
    {
        $tools = new MailEventTools();

        // Находим первый существующий тип события
        $eventTypes = $tools->listMailEventTypes([]);
        $this->assertNotEmpty($eventTypes, 'Нет ни одного типа почтового события в системе');
        $eventName = $eventTypes[0]['EVENT_NAME'];

        // Создаём шаблон
        $add = $tools->addMailTemplate([
            'event_name' => $eventName,
            'subject'    => 'Smoke test subject',
            'body'       => '<p>Smoke test body</p>',
            'to'         => '#DEFAULT_EMAIL_FROM#',
            'active'     => false,
        ]);
        $this->assertTrue($add['success']);
        $id = $add['id'];
        $this->assertGreaterThan(0, $id);

        // Получаем по ID — TIMESTAMP_X должен быть строкой, не {}
        $tpl = $tools->getMailTemplate(['id' => $id]);
        $this->assertEquals((string) $id, $tpl['ID']);
        if (isset($tpl['TIMESTAMP_X'])) {
            $this->assertIsString($tpl['TIMESTAMP_X'], 'TIMESTAMP_X должен быть строкой, не объектом');
        }

        // Список содержит наш шаблон
        $list = $tools->listMailTemplates(['filter' => ['EVENT_NAME' => $eventName], 'limit' => 100]);
        $ids  = array_column($list, 'ID');
        $this->assertContains((string) $id, $ids);

        // Обновляем
        $upd = $tools->updateMailTemplate([
            'id'      => $id,
            'subject' => 'Updated subject',
            'active'  => true,
        ]);
        $this->assertTrue($upd['success']);

        $tpl = $tools->getMailTemplate(['id' => $id]);
        $this->assertEquals('Updated subject', $tpl['SUBJECT']);
        $this->assertEquals('Y', $tpl['ACTIVE']);

        // Удаляем
        $del = $tools->deleteMailTemplate(['id' => $id]);
        $this->assertTrue($del['success']);

        // После удаления не найти
        $this->expectException(\RuntimeException::class);
        $tools->getMailTemplate(['id' => $id]);
    }

    // ── Заглушка агента ───────────────────────────────────────────────────────

    public static function smokeAgentStub(): string
    {
        // Возвращаем пустую строку — однократный запуск
        return '';
    }
}
