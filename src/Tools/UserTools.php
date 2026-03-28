<?php

namespace Warenikov\McpBitrix\Tools;

use Bitrix\Main\Loader;
use Warenikov\McpBitrix\Server;

class UserTools
{
    public static function register(Server $server): void
    {
        $self = new self();

        // --- Пользователи ---

        $server->addTool(
            name: 'list_users',
            description: 'Получить список пользователей с фильтрацией и постраничкой',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'filter' => ['type' => 'object',  'description' => 'Фильтр: ID, LOGIN, EMAIL, NAME, LAST_NAME, ACTIVE (Y/N), GROUP_ID, GROUPS_ID (массив)'],
                    'select' => ['type' => 'array',   'description' => 'Поля для выборки (по умолчанию: ID, LOGIN, EMAIL, NAME, LAST_NAME, ACTIVE)'],
                    'sort'   => ['type' => 'string',  'description' => 'Поле сортировки (по умолчанию ID)'],
                    'order'  => ['type' => 'string',  'description' => 'Направление: ASC или DESC'],
                    'limit'  => ['type' => 'integer', 'description' => 'Лимит (по умолчанию 20)'],
                    'offset' => ['type' => 'integer', 'description' => 'Смещение'],
                ],
                'required'   => [],
            ],
            handler: [$self, 'listUsers']
        );

        $server->addTool(
            name: 'get_user',
            description: 'Получить пользователя по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'           => ['type' => 'integer', 'description' => 'ID пользователя'],
                    'with_groups'  => ['type' => 'boolean', 'description' => 'Включить список групп'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'getUser']
        );

        $server->addTool(
            name: 'create_user',
            description: 'Создать нового пользователя',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'login'       => ['type' => 'string',  'description' => 'Логин'],
                    'password'    => ['type' => 'string',  'description' => 'Пароль'],
                    'email'       => ['type' => 'string',  'description' => 'Email'],
                    'name'        => ['type' => 'string',  'description' => 'Имя'],
                    'last_name'   => ['type' => 'string',  'description' => 'Фамилия'],
                    'second_name' => ['type' => 'string',  'description' => 'Отчество'],
                    'active'      => ['type' => 'boolean', 'description' => 'Активен (по умолчанию true)'],
                    'group_ids'   => ['type' => 'array',   'description' => 'Массив ID групп'],
                    'fields'      => ['type' => 'object',  'description' => 'Дополнительные поля (UF_*, PERSONAL_PHONE и т.д.)'],
                ],
                'required'   => ['login', 'password', 'email'],
            ],
            handler: [$self, 'createUser'],
            mutating: true
        );

        $server->addTool(
            name: 'update_user',
            description: 'Обновить пользователя',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer', 'description' => 'ID пользователя'],
                    'login'       => ['type' => 'string',  'description' => 'Логин'],
                    'password'    => ['type' => 'string',  'description' => 'Новый пароль'],
                    'email'       => ['type' => 'string',  'description' => 'Email'],
                    'name'        => ['type' => 'string',  'description' => 'Имя'],
                    'last_name'   => ['type' => 'string',  'description' => 'Фамилия'],
                    'second_name' => ['type' => 'string',  'description' => 'Отчество'],
                    'active'      => ['type' => 'boolean', 'description' => 'Активен'],
                    'group_ids'   => ['type' => 'array',   'description' => 'Новый список групп (полная замена)'],
                    'fields'      => ['type' => 'object',  'description' => 'Дополнительные поля (UF_*, PERSONAL_PHONE и т.д.)'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'updateUser'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_user',
            description: 'Удалить пользователя по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID пользователя'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'deleteUser'],
            mutating: true
        );

        // --- Группы ---

        $server->addTool(
            name: 'list_groups',
            description: 'Получить список групп пользователей',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean', 'description' => 'Только активные'],
                ],
                'required'   => [],
            ],
            handler: [$self, 'listGroups']
        );

        $server->addTool(
            name: 'get_group',
            description: 'Получить группу по ID',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID группы'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'getGroup']
        );

        $server->addTool(
            name: 'create_group',
            description: 'Создать группу пользователей',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'name'        => ['type' => 'string',  'description' => 'Название группы'],
                    'description' => ['type' => 'string',  'description' => 'Описание'],
                    'active'      => ['type' => 'boolean', 'description' => 'Активна (по умолчанию true)'],
                    'sort'        => ['type' => 'integer', 'description' => 'Сортировка (по умолчанию 100)'],
                ],
                'required'   => ['name'],
            ],
            handler: [$self, 'createGroup'],
            mutating: true
        );

        $server->addTool(
            name: 'update_group',
            description: 'Обновить группу пользователей',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer', 'description' => 'ID группы'],
                    'name'        => ['type' => 'string',  'description' => 'Название'],
                    'description' => ['type' => 'string',  'description' => 'Описание'],
                    'active'      => ['type' => 'boolean', 'description' => 'Активна'],
                    'sort'        => ['type' => 'integer', 'description' => 'Сортировка'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'updateGroup'],
            mutating: true
        );

        $server->addTool(
            name: 'delete_group',
            description: 'Удалить группу пользователей',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID группы'],
                ],
                'required'   => ['id'],
            ],
            handler: [$self, 'deleteGroup'],
            mutating: true
        );

        // --- Членство ---

        $server->addTool(
            name: 'get_user_groups',
            description: 'Получить список групп пользователя',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'user_id' => ['type' => 'integer', 'description' => 'ID пользователя'],
                ],
                'required'   => ['user_id'],
            ],
            handler: [$self, 'getUserGroups']
        );

        $server->addTool(
            name: 'set_user_groups',
            description: 'Заменить группы пользователя (полная замена списка)',
            inputSchema: [
                'type'       => 'object',
                'properties' => [
                    'user_id'   => ['type' => 'integer', 'description' => 'ID пользователя'],
                    'group_ids' => ['type' => 'array',   'description' => 'Новый список ID групп'],
                ],
                'required'   => ['user_id', 'group_ids'],
            ],
            handler: [$self, 'setUserGroups'],
            mutating: true
        );
    }

    // -------------------------------------------------------------------------
    // Пользователи
    // -------------------------------------------------------------------------

    public function listUsers(array $args): array
    {
        $filter = $args['filter'] ?? [];
        $select = $args['select'] ?? ['ID', 'LOGIN', 'EMAIL', 'NAME', 'LAST_NAME', 'ACTIVE'];
        $limit  = $args['limit']  ?? 20;
        $offset = $args['offset'] ?? 0;
        $sort   = $args['sort']   ?? 'ID';
        $order  = strtoupper($args['order'] ?? 'ASC') === 'DESC' ? 'desc' : 'asc';

        $result = [];
        $rs = \CUser::GetList(
            $sort,
            $order,
            $filter,
            ['FIELDS' => $select, 'nPageSize' => $limit, 'iNumPage' => (int) floor($offset / $limit) + 1]
        );

        while ($row = $rs->Fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function getUser(array $args): array
    {
        $rs  = \CUser::GetByID((int) $args['id']);
        $row = $rs->Fetch();

        if (!$row) {
            throw new \RuntimeException("Пользователь с ID {$args['id']} не найден");
        }

        if (!empty($args['with_groups'])) {
            $row['GROUPS'] = \CUser::GetUserGroup((int) $args['id']);
        }

        return $row;
    }

    public function createUser(array $args): array
    {
        $fields = array_merge($args['fields'] ?? [], [
            'LOGIN'            => $args['login'],
            'PASSWORD'         => $args['password'],
            'CONFIRM_PASSWORD' => $args['password'],
            'EMAIL'            => $args['email'],
            'NAME'             => $args['name']        ?? '',
            'LAST_NAME'        => $args['last_name']   ?? '',
            'SECOND_NAME'      => $args['second_name'] ?? '',
            'ACTIVE'           => ($args['active'] ?? true) ? 'Y' : 'N',
        ]);

        if (!empty($args['group_ids'])) {
            $fields['GROUP_ID'] = array_map('intval', $args['group_ids']);
        }

        $user = new \CUser();
        $id   = $user->Add($fields);

        if ($id) {
            return ['success' => true, 'id' => (int) $id];
        }

        $exception = $GLOBALS['APPLICATION']->GetException();
        $error = $exception ? $exception->GetString() : $user->LAST_ERROR;
        return ['success' => false, 'error' => $error];
    }

    public function updateUser(array $args): array
    {
        $id     = (int) $args['id'];
        $fields = $args['fields'] ?? [];

        if (isset($args['login']))       $fields['LOGIN']       = $args['login'];
        if (isset($args['email']))       $fields['EMAIL']       = $args['email'];
        if (isset($args['name']))        $fields['NAME']        = $args['name'];
        if (isset($args['last_name']))   $fields['LAST_NAME']   = $args['last_name'];
        if (isset($args['second_name'])) $fields['SECOND_NAME'] = $args['second_name'];
        if (isset($args['active']))      $fields['ACTIVE']      = $args['active'] ? 'Y' : 'N';
        if (isset($args['password'])) {
            $fields['PASSWORD']         = $args['password'];
            $fields['CONFIRM_PASSWORD'] = $args['password'];
        }
        if (isset($args['group_ids'])) {
            $fields['GROUP_ID'] = array_map('intval', $args['group_ids']);
        }

        $user   = new \CUser();
        $result = $user->Update($id, $fields);

        if ($result) {
            return ['success' => true];
        }

        $exception = $GLOBALS['APPLICATION']->GetException();
        $error = $exception ? $exception->GetString() : $user->LAST_ERROR;
        return ['success' => false, 'error' => $error];
    }

    public function deleteUser(array $args): array
    {
        $result = \CUser::Delete((int) $args['id']);
        return ['success' => (bool) $result];
    }

    // -------------------------------------------------------------------------
    // Группы
    // -------------------------------------------------------------------------

    public function listGroups(array $args): array
    {
        $filter = [];
        if (isset($args['active'])) {
            $filter['ACTIVE'] = $args['active'] ? 'Y' : 'N';
        }

        $result = [];
        $rs = \CGroup::GetList('SORT', 'asc', $filter);
        while ($row = $rs->Fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function getGroup(array $args): array
    {
        $rs  = \CGroup::GetByID((int) $args['id']);
        $row = $rs->Fetch();

        if (!$row) {
            throw new \RuntimeException("Группа с ID {$args['id']} не найдена");
        }

        return $row;
    }

    public function createGroup(array $args): array
    {
        $fields = [
            'NAME'        => $args['name'],
            'DESCRIPTION' => $args['description'] ?? '',
            'ACTIVE'      => ($args['active'] ?? true) ? 'Y' : 'N',
            'SORT'        => $args['sort'] ?? 100,
        ];

        $group = new \CGroup();
        $id    = $group->Add($fields);

        if ($id) {
            return ['success' => true, 'id' => (int) $id];
        }

        return ['success' => false, 'error' => $group->LAST_ERROR];
    }

    public function updateGroup(array $args): array
    {
        $id     = (int) $args['id'];
        $fields = [];

        if (isset($args['name']))        $fields['NAME']        = $args['name'];
        if (isset($args['description'])) $fields['DESCRIPTION'] = $args['description'];
        if (isset($args['active']))      $fields['ACTIVE']      = $args['active'] ? 'Y' : 'N';
        if (isset($args['sort']))        $fields['SORT']        = $args['sort'];

        $group  = new \CGroup();
        $result = $group->Update($id, $fields);

        if ($result) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => $group->LAST_ERROR];
    }

    public function deleteGroup(array $args): array
    {
        $result = \CGroup::Delete((int) $args['id']);
        return ['success' => (bool) $result];
    }

    // -------------------------------------------------------------------------
    // Членство
    // -------------------------------------------------------------------------

    public function getUserGroups(array $args): array
    {
        $groupIds = \CUser::GetUserGroup((int) $args['user_id']);

        if (empty($groupIds)) {
            return [];
        }

        $result = [];
        $rs = \CGroup::GetList('SORT', 'asc', ['ID' => implode('|', $groupIds)]);
        while ($row = $rs->Fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function setUserGroups(array $args): array
    {
        $userId   = (int) $args['user_id'];
        $groupIds = array_map('intval', $args['group_ids']);

        \CUser::SetUserGroup($userId, $groupIds);

        return ['success' => true];
    }
}
