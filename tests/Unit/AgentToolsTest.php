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

    if (!class_exists('CAgent')) {
        class CAgent
        {
            public static array  $addCalls    = [];
            public static array  $updateCalls = [];
            public static array  $deleteCalls = [];
            public static array  $listRows    = [];
            public static int    $nextId      = 1;
            public static bool   $opResult    = true;

            public static function GetList(array $order, array $filter): \FakeDbResult
            {
                return new \FakeDbResult(self::$listRows);
            }

            public static function GetById(int $id): \FakeDbResult
            {
                $rows = array_values(array_filter(self::$listRows, fn($r) => (int) $r['ID'] === $id));
                return new \FakeDbResult($rows);
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
                self::$listRows    = [];
                self::$nextId      = 1;
                self::$opResult    = true;
            }
        }
    }
}

// =============================================================================
// Tests
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\AgentTools;

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
            \CAgent::$listRows = [['ID' => '42', 'NAME' => 'MyAgent();']];

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
}
