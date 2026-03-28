<?php

declare(strict_types=1);

// =============================================================================
// Stubs
// =============================================================================

namespace Bitrix\Main\Config {

    if (!class_exists(\Bitrix\Main\Config\Option::class)) {
        class Option
        {
            public static array $store      = [];
            public static array $setCalls   = [];
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
                        $result[substr($key, strlen($prefix))] = $value;
                    }
                }
                return $result;
            }

            public static function reset(): void
            {
                self::$store        = [];
                self::$setCalls     = [];
                self::$deleteCalls  = [];
            }
        }
    }
}

// =============================================================================
// Tests
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\OptionTools;

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

        public function testListOptionsReturnsNameValueFormat(): void
        {
            \Bitrix\Main\Config\Option::$store = ['mymod.KEY' => 'val'];

            $result = $this->tools->listOptions(['module_id' => 'mymod']);
            $this->assertEquals('KEY', $result[0]['NAME']);
            $this->assertEquals('val', $result[0]['VALUE']);
        }
    }
}
