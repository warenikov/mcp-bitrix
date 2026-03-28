<?php

declare(strict_types=1);

// =============================================================================
// Stubs
// =============================================================================

namespace {
    if (!class_exists('FakeDbResult')) {
        class FakeDbResult
        {
            private array $rows;
            private int   $pos = 0;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function Fetch(): array|false { return $this->rows[$this->pos++] ?? false; }
        }
    }

    if (!class_exists('CEventLog')) {
        class CEventLog
        {
            public static array $addCalls = [];
            public static array $listRows = [];

            public static function GetList(array $order, array $filter): \FakeDbResult
            {
                if (isset($filter['ID'])) {
                    $id   = (int) $filter['ID'];
                    $rows = array_values(array_filter(self::$listRows, fn($r) => (int) $r['ID'] === $id));
                    return new \FakeDbResult($rows);
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
}

// =============================================================================
// Tests
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\EventLogTools;

    class EventLogToolsTest extends TestCase
    {
        private EventLogTools $tools;

        protected function setUp(): void
        {
            $this->tools = new EventLogTools();
            \CEventLog::reset();
            \Bitrix\Main\Application::resetConnection();
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

        public function testGetEventLogReturnsRow(): void
        {
            \CEventLog::$listRows = [['ID' => '5', 'SEVERITY' => 'WARNING']];
            $result = $this->tools->getEventLog(['id' => 5]);
            $this->assertEquals('5', $result['ID']);
        }

        public function testGetEventLogThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->tools->getEventLog(['id' => 999]);
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
            $result = $this->tools->clearEventLog(['older_than_days' => 7]);
            $this->assertTrue($result['success']);

            $queries = \Bitrix\Main\DB\FakeConnection::$queries;
            $this->assertCount(1, $queries);
            $this->assertStringContainsString('DELETE FROM b_event_log', $queries[0]['sql']);
        }

        public function testClearEventLogDefaultIs30Days(): void
        {
            $this->tools->clearEventLog([]);

            $sql      = \Bitrix\Main\DB\FakeConnection::$queries[0]['sql'];
            $expected = date('Y-m-d', strtotime('-30 days'));
            $this->assertStringContainsString($expected, $sql);
        }
    }
}
