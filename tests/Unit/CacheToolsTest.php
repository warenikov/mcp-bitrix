<?php

declare(strict_types=1);

// =============================================================================
// Stubs
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
// Tests
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\CacheTools;

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
}
