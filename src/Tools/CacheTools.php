<?php

namespace Warenikov\McpBitrix\Tools;

use Warenikov\McpBitrix\Server;

class CacheTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        $server->addTool(
            name: 'clear_cache',
            description: 'Очистить кэш Битрикса. Без параметров — полная очистка managed cache. С параметром tag — очистка по тегу.',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'tag' => ['type' => 'string', 'description' => 'Тег кэша для точечной очистки (необязательно)'],
                ],
            ],
            handler: [$self, 'clearCache'],
            mutating: true
        );

        $server->addTool(
            name: 'clear_menu_cache',
            description: 'Очистить кэш меню Битрикса (файловый кэш .menu_ex.php)',
            inputSchema: [
                'type'       => 'object',
                'properties' => [],
            ],
            handler: [$self, 'clearMenuCache'],
            mutating: true
        );
    }

    public function clearCache(array $args): array
    {
        $tag = $args['tag'] ?? null;

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        if ($tag !== null && $tag !== '') {
            $taggedCache = new \Bitrix\Main\Data\TaggedCache();
            $taggedCache->clearByTag($tag);

            return ['success' => true, 'cleared' => 'tag', 'tag' => $tag];
        }

        $managedCache = new \Bitrix\Main\Data\ManagedCache();
        $managedCache->cleanAll();

        return ['success' => true, 'cleared' => 'all'];
    }

    public function clearMenuCache(array $args): array
    {
        $documentRoot = \Bitrix\Main\Application::getDocumentRoot();
        $deleted = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($documentRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === '.menu_ex.php') {
                unlink($file->getRealPath());
                $deleted++;
            }
        }

        return ['success' => true, 'deleted_files' => $deleted];
    }
}
