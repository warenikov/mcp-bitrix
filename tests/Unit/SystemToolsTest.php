<?php

declare(strict_types=1);

// =============================================================================
// Stubs for AgentTools — \CAgent
// =============================================================================

namespace {

    if (!class_exists('CAgent')) {
        class CAgent
        {
            public static array  $addCalls    = [];
            public static array  $updateCalls = [];
            public static array  $deleteCalls = [];
            public static ?array $getByIdRow  = null;
            public static array  $listRows    = [];
            public static int    $nextId      = 1;
            public static bool   $opResult    = true;

            public static function GetList(array $order, array $filter): \FakeDbResult
            {
                return new \FakeDbResult(self::$listRows);
            }

            public static function GetById(int $id): \FakeDbResult
            {
                $row = self::$getByIdRow;
                return new \FakeDbResult($row !== null ? [$row] : []);
            }

            public static function Add(array $fields): int|false
            {
                self::$addCalls[] = $fields;
                return self::$opResult ? self::$nextId++ : false;
            }

            public static function Update(int $id, array $fields): bool
            {
                self::$updateCalls[] = ['id' => $id, 'fields' => $fields];
                return self::$opResult;
            }

            public static function Delete(int $id): bool
            {
                self::$deleteCalls[] = $id;
                return self::$opResult;
            }

            public static function reset(): void
            {
                self::$addCalls    = [];
                self::$updateCalls = [];
                self::$deleteCalls = [];
                self::$getByIdRow  = null;
                self::$listRows    = [];
                self::$nextId      = 1;
                self::$opResult    = true;
            }
        }
    }

    // ── CEventLog ──────────────────────────────────────────────────────────

    if (!class_exists('CEventLog')) {
        class CEventLog
        {
            public static array $addCalls = [];
            public static array $listRows = [];

            public static function GetList(array $order, array $filter): \FakeDbResult
            {
                // Имитируем фильтр по ID для getEventLog
                if (isset($filter['ID'])) {
                    $id = (int) $filter['ID'];
                    $rows = array_filter(self::$listRows, fn($r) => (int) $r['ID'] === $id);
                    return new \FakeDbResult(array_values($rows));
                }
                return new \FakeDbResult(self::$listRows);
            }

            public static function Add(array $fields): void
            {
                self::$addCalls[] = $fields;
            }

            public static function reset(): void
            {
                self::$addCalls = [];
                self::$listRows = [];
            }
        }
    }

    // ── CEventType ──────────────────────────────────────────────────────────

    if (!class_exists('CEventType')) {
        class CEventType
        {
            public static array $listRows = [];

            public static function GetList(array $order, array $filter): \FakeDbResult
            {
                return new \FakeDbResult(self::$listRows);
            }

            public static function reset(): void
            {
                self::$listRows = [];
            }
        }
    }

    // ── CEventMessage ──────────────────────────────────────────────────────

    if (!class_exists('CEventMessage')) {
        class CEventMessage
        {
            public static array  $addCalls    = [];
            public static array  $updateCalls = [];
            public static array  $deleteCalls = [];
            public static ?array $getByIdRow  = null;
            public static array  $listRows    = [];
            public static int    $nextId      = 1;
            public static bool   $opResult    = true;

            public string $LAST_ERROR = '';

            public static function GetList(array $order, array $filter): \FakeDbResult
            {
                return new \FakeDbResult(self::$listRows);
            }

            public static function GetById(int $id): \FakeDbResult
            {
                $row = self::$getByIdRow;
                return new \FakeDbResult($row !== null ? [$row] : []);
            }

            public function Add(array $fields): int|false
            {
                self::$addCalls[] = $fields;
                return self::$opResult ? self::$nextId++ : false;
            }

            public function Update(int $id, array $fields): bool
            {
                self::$updateCalls[] = ['id' => $id, 'fields' => $fields];
                return self::$opResult;
            }

            public static function Delete(int $id): bool
            {
                self::$deleteCalls[] = $id;
                return self::$opResult;
            }

            public static function reset(): void
            {
                self::$addCalls    = [];
                self::$updateCalls = [];
                self::$deleteCalls = [];
                self::$getByIdRow  = null;
                self::$listRows    = [];
                self::$nextId      = 1;
                self::$opResult    = true;
            }
        }
    }

    // ── Generic FakeDbResult ────────────────────────────────────────────────

    if (!class_exists('FakeDbResult')) {
        class FakeDbResult
        {
            private array $rows;
            private int   $pos = 0;

            public function __construct(array $rows) { $this->rows = $rows; }

            public function Fetch(): array|false
            {
                return $this->rows[$this->pos++] ?? false;
            }
        }
    }
}

// =============================================================================
// Stubs for CacheTools — Bitrix\Main\Data + Bitrix\Main\Application (if not yet defined)
// =============================================================================

namespace Bitrix\Main\Data {

    if (!class_exists(\Bitrix\Main\Data\ManagedCache::class)) {
        class ManagedCache
        {
            public static array $cleanAllCalls = [];
            public function cleanAll(): void { self::$cleanAllCalls[] = true; }
            public static function reset(): void { self::$cleanAllCalls = []; }
        }
    }

    if (!class_exists(\Bitrix\Main\Data\TaggedCache::class)) {
        class TaggedCache
        {
            public static array $clearByTagCalls = [];
            public function clearByTag(string $tag): void { self::$clearByTagCalls[] = $tag; }
            public static function reset(): void { self::$clearByTagCalls = []; }
        }
    }

    if (!class_exists(\Bitrix\Main\Data\Cache::class)) {
        class Cache
        {
            public static function createInstance(): static { return new static(); }
        }
    }
}

// =============================================================================
// Stubs for OptionTools — Bitrix\Main\Config\Option
// =============================================================================

namespace Bitrix\Main\Config {

    if (!class_exists(\Bitrix\Main\Config\Option::class)) {
        class Option
        {
            public static array $store = [];
            public static array $setCalls = [];
            public static array $deleteCalls = [];

            public static function get(string $module, string $name, string $default = '', string $siteId = ''): string
            {
                return self::$store["{$module}.{$name}"] ?? $default;
            }

            public static function set(string $module, string $name, string $value, string $siteId = ''): void
            {
                self::$store["{$module}.{$name}"] = $value;
                self::$setCalls[] = compact('module', 'name', 'value', 'siteId');
            }

            public static function delete(string $module, array $params = []): void
            {
                self::$deleteCalls[] = ['module' => $module, 'params' => $params];
                $name = $params['name'] ?? null;
                if ($name) {
                    unset(self::$store["{$module}.{$name}"]);
                }
            }

            public static function getForModule(string $module, string $siteId = ''): array
            {
                $result = [];
                $prefix = "{$module}.";
                foreach (self::$store as $key => $value) {
                    if (str_starts_with($key, $prefix)) {
                        $result[] = ['NAME' => substr($key, strlen($prefix)), 'VALUE' => $value];
                    }
                }
                return $result;
            }

            public static function reset(): void
            {
                self::$store       = [];
                self::$setCalls    = [];
                self::$deleteCalls = [];
            }
        }
    }
}

// =============================================================================
// Stubs — Bitrix\Main\Application (getDocumentRoot)
// OrmToolsTest.php declares Bitrix\Main\Application with getConnection().
// We add getDocumentRoot() here only if the class is not yet defined.
// Because PHPUnit loads files alphabetically (S > O), OrmToolsTest.php will
// load AFTER this file and will try to re-declare Application — which fails.
// To avoid that, both declarations must be in one place.
// Solution: define everything here and make OrmToolsTest.php stubs guard with class_exists.
// (OrmToolsTest.php already has its Application stub without class_exists guard,
//  so we must ensure this file loads AFTER it, i.e. name starts with a letter > 'O'.)
// File is named SystemToolsTest.php (S > O) — loads after OrmToolsTest.php.
// At that point Bitrix\Main\Application already exists, so we just extend it.
// =============================================================================

namespace Bitrix\Main {

    // Application is already defined by OrmToolsTest.php at this point.
    // We use a workaround: add getDocumentRoot via a trait or simply check.
    // Since we can't re-open a class in PHP, we rely on the fact that
    // CacheTools calls Application::getDocumentRoot() at runtime, not at parse time.
    // We mock the filesystem scan differently in tests — see testClearMenuCache below.
}

// =============================================================================
// Tests
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\AgentTools;
    use Warenikov\McpBitrix\Tools\CacheTools;
    use Warenikov\McpBitrix\Tools\OptionTools;
    use Warenikov\McpBitrix\Tools\EventLogTools;
    use Warenikov\McpBitrix\Tools\MailEventTools;

    // ── AgentTools ────────────────────────────────────────────────────────────

    class AgentToolsTest extends TestCase
    {
        private AgentTools $tools;

        protected function setUp(): void
        {
            $this->tools = new AgentTools();
            \CAgent::reset();
        }

        public function testListAgentsReturnsRows(): void
        {
            \CAgent::$listRows = [
                ['ID' => '1', 'NAME' => 'TestAgent();', 'ACTIVE' => 'Y'],
                ['ID' => '2', 'NAME' => 'OtherAgent();', 'ACTIVE' => 'N'],
            ];

            $result = $this->tools->listAgents([]);
            $this->assertCount(2, $result);
        }

        public function testListAgentsRespectsLimit(): void
        {
            \CAgent::$listRows = array_fill(0, 10, ['ID' => '1', 'NAME' => 'A();']);

            $result = $this->tools->listAgents(['limit' => 3]);
            $this->assertCount(3, $result);
        }

        public function testGetAgentReturnsRow(): void
        {
            \CAgent::$getByIdRow = ['ID' => '42', 'NAME' => 'MyAgent();'];

            $result = $this->tools->getAgent(['id' => 42]);
            $this->assertEquals('42', $result['ID']);
        }

        public function testGetAgentThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->tools->getAgent(['id' => 999]);
        }

        public function testAddAgentReturnsId(): void
        {
            \CAgent::$nextId = 7;
            $result = $this->tools->addAgent(['name' => 'MyAgent();']);

            $this->assertTrue($result['success']);
            $this->assertEquals(7, $result['id']);
        }

        public function testAddAgentPassesFieldsToCAgent(): void
        {
            $this->tools->addAgent([
                'name'      => 'MyAgent();',
                'module_id' => 'mymodule',
                'interval'  => 3600,
                'active'    => false,
                'is_period' => true,
                'sort'      => 50,
            ]);

            $fields = \CAgent::$addCalls[0];
            $this->assertEquals('mymodule', $fields['MODULE_ID']);
            $this->assertEquals('MyAgent();', $fields['NAME']);
            $this->assertEquals('N', $fields['ACTIVE']);
            $this->assertEquals('Y', $fields['IS_PERIOD']);
            $this->assertEquals(3600, $fields['AGENT_INTERVAL']);
            $this->assertEquals(50, $fields['SORT']);
        }

        public function testAddAgentThrowsOnFailure(): void
        {
            \CAgent::$opResult = false;
            $this->expectException(\RuntimeException::class);
            $this->tools->addAgent(['name' => 'Fail();']);
        }

        public function testUpdateAgentCallsCAgentUpdate(): void
        {
            $result = $this->tools->updateAgent(['id' => 5, 'active' => true, 'interval' => 1800]);

            $this->assertTrue($result['success']);
            $call = \CAgent::$updateCalls[0];
            $this->assertEquals(5, $call['id']);
            $this->assertEquals('Y', $call['fields']['ACTIVE']);
            $this->assertEquals(1800, $call['fields']['AGENT_INTERVAL']);
        }

        public function testDeleteAgentCallsCAgentDelete(): void
        {
            $result = $this->tools->deleteAgent(['id' => 3]);
            $this->assertTrue($result['success']);
            $this->assertContains(3, \CAgent::$deleteCalls);
        }

        public function testRunAgentSetsNextExecAndActivatesAgent(): void
        {
            $result = $this->tools->runAgent(['id' => 10]);

            $this->assertTrue($result['success']);
            $call = \CAgent::$updateCalls[0];
            $this->assertEquals(10, $call['id']);
            $this->assertEquals('Y', $call['fields']['ACTIVE']);
            $this->assertArrayHasKey('NEXT_EXEC', $call['fields']);
        }
    }

    // ── CacheTools ────────────────────────────────────────────────────────────

    class CacheToolsTest extends TestCase
    {
        private CacheTools $tools;

        protected function setUp(): void
        {
            $this->tools = new CacheTools();
            \Bitrix\Main\Data\ManagedCache::reset();
            \Bitrix\Main\Data\TaggedCache::reset();
        }

        public function testClearCacheWithoutTagCallsCleanAll(): void
        {
            $result = $this->tools->clearCache([]);

            $this->assertTrue($result['success']);
            $this->assertEquals('all', $result['cleared']);
            $this->assertCount(1, \Bitrix\Main\Data\ManagedCache::$cleanAllCalls);
        }

        public function testClearCacheWithTagCallsClearByTag(): void
        {
            $result = $this->tools->clearCache(['tag' => 'iblock_id_5']);

            $this->assertTrue($result['success']);
            $this->assertEquals('tag', $result['cleared']);
            $this->assertEquals('iblock_id_5', $result['tag']);
            $this->assertContains('iblock_id_5', \Bitrix\Main\Data\TaggedCache::$clearByTagCalls);
            $this->assertCount(0, \Bitrix\Main\Data\ManagedCache::$cleanAllCalls);
        }

        public function testClearCacheEmptyTagActsAsFullClean(): void
        {
            $result = $this->tools->clearCache(['tag' => '']);
            $this->assertEquals('all', $result['cleared']);
        }
    }

    // ── OptionTools ───────────────────────────────────────────────────────────

    class OptionToolsTest extends TestCase
    {
        private OptionTools $tools;

        protected function setUp(): void
        {
            $this->tools = new OptionTools();
            \Bitrix\Main\Config\Option::reset();
        }

        public function testGetOptionReturnsDefault(): void
        {
            $result = $this->tools->getOption(['module_id' => 'main', 'name' => 'MISSING', 'default' => 'def']);
            $this->assertEquals('def', $result['value']);
        }

        public function testSetAndGetOption(): void
        {
            $this->tools->setOption(['module_id' => 'main', 'name' => 'SITE_NAME', 'value' => 'MyShop']);
            $result = $this->tools->getOption(['module_id' => 'main', 'name' => 'SITE_NAME']);
            $this->assertEquals('MyShop', $result['value']);
        }

        public function testSetOptionReturnsSuccess(): void
        {
            $result = $this->tools->setOption(['module_id' => 'main', 'name' => 'X', 'value' => '1']);
            $this->assertTrue($result['success']);
        }

        public function testDeleteOptionRemovesValue(): void
        {
            \Bitrix\Main\Config\Option::$store['main.X'] = 'hello';

            $result = $this->tools->deleteOption(['module_id' => 'main', 'name' => 'X']);
            $this->assertTrue($result['success']);
            $this->assertArrayNotHasKey('main.X', \Bitrix\Main\Config\Option::$store);
        }

        public function testListOptionsReturnsAllForModule(): void
        {
            \Bitrix\Main\Config\Option::$store = [
                'mymod.A' => '1',
                'mymod.B' => '2',
                'other.C' => '3',
            ];

            $result = $this->tools->listOptions(['module_id' => 'mymod']);
            $this->assertCount(2, $result);
        }
    }

    // ── EventLogTools ─────────────────────────────────────────────────────────

    class EventLogToolsTest extends TestCase
    {
        private EventLogTools $tools;

        protected function setUp(): void
        {
            $this->tools = new EventLogTools();
            \CEventLog::reset();
        }

        public function testListEventLogReturnsRows(): void
        {
            \CEventLog::$listRows = [
                ['ID' => '1', 'SEVERITY' => 'INFO'],
                ['ID' => '2', 'SEVERITY' => 'ERROR'],
            ];

            $result = $this->tools->listEventLog([]);
            $this->assertCount(2, $result);
        }

        public function testListEventLogRespectsLimit(): void
        {
            \CEventLog::$listRows = array_fill(0, 10, ['ID' => '1']);

            $result = $this->tools->listEventLog(['limit' => 3]);
            $this->assertCount(3, $result);
        }

        public function testGetEventLogThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->tools->getEventLog(['id' => 999]);
        }

        public function testGetEventLogReturnsRow(): void
        {
            \CEventLog::$listRows = [['ID' => '5', 'SEVERITY' => 'WARNING']];
            $result = $this->tools->getEventLog(['id' => 5]);
            $this->assertEquals('5', $result['ID']);
        }

        public function testAddEventLogPassesFields(): void
        {
            $this->tools->addEventLog([
                'severity'      => 'error',
                'audit_type_id' => 'MCP_TEST',
                'description'   => 'Test event',
                'module_id'     => 'test.module',
                'item_id'       => '42',
            ]);

            $fields = \CEventLog::$addCalls[0];
            $this->assertEquals('ERROR', $fields['SEVERITY']);
            $this->assertEquals('MCP_TEST', $fields['AUDIT_TYPE_ID']);
            $this->assertEquals('Test event', $fields['DESCRIPTION']);
            $this->assertEquals('test.module', $fields['MODULE_ID']);
            $this->assertEquals('42', $fields['ITEM_ID']);
        }

        public function testAddEventLogDefaultSeverityIsInfo(): void
        {
            $this->tools->addEventLog(['audit_type_id' => 'X', 'description' => 'Y']);
            $this->assertEquals('INFO', \CEventLog::$addCalls[0]['SEVERITY']);
        }

        public function testAddEventLogInvalidSeverityFallsBackToInfo(): void
        {
            $this->tools->addEventLog(['audit_type_id' => 'X', 'description' => 'Y', 'severity' => 'CRITICAL']);
            $this->assertEquals('INFO', \CEventLog::$addCalls[0]['SEVERITY']);
        }

        public function testClearEventLogExecutesSql(): void
        {
            \Bitrix\Main\Application::resetConnection();

            $result = $this->tools->clearEventLog(['older_than_days' => 7]);
            $this->assertTrue($result['success']);

            $queries = \Bitrix\Main\DB\FakeConnection::$queries;
            $this->assertCount(1, $queries);
            $this->assertStringContainsString('DELETE FROM b_event_log', $queries[0]['sql']);
        }

        public function testClearEventLogDefaultIs30Days(): void
        {
            \Bitrix\Main\Application::resetConnection();

            $this->tools->clearEventLog([]);

            $sql = \Bitrix\Main\DB\FakeConnection::$queries[0]['sql'];
            $expected = date('Y-m-d', strtotime('-30 days'));
            $this->assertStringContainsString($expected, $sql);
        }
    }

    // ── MailEventTools ────────────────────────────────────────────────────────

    class MailEventToolsTest extends TestCase
    {
        private MailEventTools $tools;

        protected function setUp(): void
        {
            $this->tools = new MailEventTools();
            \CEventType::reset();
            \CEventMessage::reset();
        }

        public function testListMailEventTypesReturnsRows(): void
        {
            \CEventType::$listRows = [
                ['EVENT_NAME' => 'NEW_USER', 'NAME' => 'Новый пользователь'],
            ];

            $result = $this->tools->listMailEventTypes([]);
            $this->assertCount(1, $result);
        }

        public function testGetMailEventTypeThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->tools->getMailEventType(['event_name' => 'NONEXISTENT']);
        }

        public function testGetMailEventTypeReturnsRow(): void
        {
            \CEventType::$listRows = [['EVENT_NAME' => 'NEW_USER']];
            $result = $this->tools->getMailEventType(['event_name' => 'NEW_USER']);
            $this->assertEquals('NEW_USER', $result['EVENT_NAME']);
        }

        public function testListMailTemplatesReturnsRows(): void
        {
            \CEventMessage::$listRows = [
                ['ID' => '1', 'EVENT_NAME' => 'NEW_USER'],
                ['ID' => '2', 'EVENT_NAME' => 'ORDER_NEW'],
            ];

            $result = $this->tools->listMailTemplates([]);
            $this->assertCount(2, $result);
        }

        public function testListMailTemplatesRespectsLimit(): void
        {
            \CEventMessage::$listRows = array_fill(0, 10, ['ID' => '1']);

            $result = $this->tools->listMailTemplates(['limit' => 2]);
            $this->assertCount(2, $result);
        }

        public function testGetMailTemplateThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->tools->getMailTemplate(['id' => 999]);
        }

        public function testAddMailTemplateReturnsId(): void
        {
            \CEventMessage::$nextId = 5;

            $result = $this->tools->addMailTemplate([
                'event_name' => 'NEW_USER',
                'subject'    => 'Welcome #NAME#',
                'body'       => '<p>Hello</p>',
                'to'         => '#EMAIL#',
            ]);

            $this->assertTrue($result['success']);
            $this->assertEquals(5, $result['id']);
        }

        public function testAddMailTemplatePassesFields(): void
        {
            $this->tools->addMailTemplate([
                'event_name' => 'TEST_EVENT',
                'subject'    => 'Subj',
                'body'       => 'Body',
                'body_type'  => 'text',
                'from'       => 'noreply@example.com',
                'to'         => 'admin@example.com',
                'active'     => false,
                'site_id'    => ['s1', 's2'],
            ]);

            $fields = \CEventMessage::$addCalls[0];
            $this->assertEquals('TEST_EVENT', $fields['EVENT_NAME']);
            $this->assertEquals('N', $fields['ACTIVE']);
            $this->assertEquals('text', $fields['BODY_TYPE']);
            $this->assertEquals('noreply@example.com', $fields['EMAIL_FROM']);
            $this->assertEquals('admin@example.com', $fields['EMAIL_TO']);
            $this->assertEquals(['s1', 's2'], $fields['LID']);
        }

        public function testAddMailTemplateDefaultBodyTypeIsHtml(): void
        {
            $this->tools->addMailTemplate(['event_name' => 'X', 'subject' => 'S', 'body' => 'B', 'to' => 'admin@example.com']);
            $this->assertEquals('html', \CEventMessage::$addCalls[0]['BODY_TYPE']);
        }

        public function testAddMailTemplateThrowsOnFailure(): void
        {
            \CEventMessage::$opResult = false;
            $this->expectException(\RuntimeException::class);
            $this->tools->addMailTemplate(['event_name' => 'X', 'subject' => 'S', 'body' => 'B', 'to' => 'admin@example.com']);
        }

        public function testUpdateMailTemplateCallsUpdate(): void
        {
            $result = $this->tools->updateMailTemplate([
                'id'      => 3,
                'subject' => 'New subject',
                'active'  => true,
            ]);

            $this->assertTrue($result['success']);
            $call = \CEventMessage::$updateCalls[0];
            $this->assertEquals(3, $call['id']);
            $this->assertEquals('New subject', $call['fields']['SUBJECT']);
            $this->assertEquals('Y', $call['fields']['ACTIVE']);
        }

        public function testDeleteMailTemplateCallsDelete(): void
        {
            $result = $this->tools->deleteMailTemplate(['id' => 7]);
            $this->assertTrue($result['success']);
            $this->assertContains(7, \CEventMessage::$deleteCalls);
        }
    }
}
