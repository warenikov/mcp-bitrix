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
}

// =============================================================================
// Tests
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\MailEventTools;

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

        public function testGetMailEventTypeSkipsNonMatchingRows(): void
        {
            \CEventType::$listRows = [
                ['EVENT_NAME' => 'OTHER_EVENT'],
                ['EVENT_NAME' => 'TARGET_EVENT'],
            ];
            $result = $this->tools->getMailEventType(['event_name' => 'TARGET_EVENT']);
            $this->assertEquals('TARGET_EVENT', $result['EVENT_NAME']);
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

        public function testGetMailTemplateNormalizesDatetime(): void
        {
            $dt = new class extends \Bitrix\Main\Type\DateTime {};
            \CEventMessage::$getByIdRow = ['ID' => '1', 'TIMESTAMP_X' => $dt];

            $result = $this->tools->getMailTemplate(['id' => 1]);
            $this->assertIsString($result['TIMESTAMP_X']);
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
                'to'         => 'admin@example.com',
                'body_type'  => 'text',
                'from'       => 'noreply@example.com',
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
            $this->tools->addMailTemplate(['event_name' => 'X', 'subject' => 'S', 'body' => 'B', 'to' => 'a@b.com']);
            $this->assertEquals('html', \CEventMessage::$addCalls[0]['BODY_TYPE']);
        }

        public function testAddMailTemplateThrowsOnFailure(): void
        {
            \CEventMessage::$opResult = false;
            $this->expectException(\RuntimeException::class);
            $this->tools->addMailTemplate(['event_name' => 'X', 'subject' => 'S', 'body' => 'B', 'to' => 'a@b.com']);
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
