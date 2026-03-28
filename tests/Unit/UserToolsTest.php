<?php

declare(strict_types=1);

// =============================================================================
// Stubs глобального неймспейса
// =============================================================================

namespace {

    // FakeOldResult и FakeApplication уже объявлены в HlblockToolsTest.php,
    // поэтому объявляем только те классы, которых там нет.

    class CUser
    {
        public static array      $addCalls    = [];
        public static array      $updateCalls = [];
        public static array      $deleteCalls = [];
        public static array      $getListCalls = [];
        public static array      $rows         = [];
        public static int|false  $addReturn    = 1;
        public static bool       $updateReturn = true;
        public static array      $userGroups   = [];
        public static array      $setGroupCalls = [];
        public string $LAST_ERROR = '';

        public function Add(array $fields): int|false
        {
            self::$addCalls[] = $fields;
            return self::$addReturn;
        }

        public function Update(int $id, array $fields): bool
        {
            self::$updateCalls[] = ['id' => $id, 'fields' => $fields];
            return self::$updateReturn;
        }

        public static function Delete(int $id): bool
        {
            self::$deleteCalls[] = $id;
            return true;
        }

        public static function GetByID(int $id): FakeOldResult
        {
            $found = array_values(array_filter(self::$rows, fn($r) => $r['ID'] === $id));
            return new FakeOldResult($found);
        }

        public static function GetList(string $sort, string $order, array $filter, array $nav): FakeOldResult
        {
            self::$getListCalls[] = ['sort' => $sort, 'order' => $order, 'filter' => $filter, 'nav' => $nav];
            return new FakeOldResult(self::$rows);
        }

        public static function GetUserGroup(int $userId): array
        {
            return self::$userGroups[$userId] ?? [];
        }

        public static function SetUserGroup(int $userId, array $groups): void
        {
            self::$setGroupCalls[] = ['user_id' => $userId, 'groups' => $groups];
            self::$userGroups[$userId] = $groups;
        }

        public static function reset(): void
        {
            self::$addCalls      = [];
            self::$updateCalls   = [];
            self::$deleteCalls   = [];
            self::$getListCalls  = [];
            self::$rows          = [];
            self::$addReturn     = 1;
            self::$updateReturn  = true;
            self::$userGroups    = [];
            self::$setGroupCalls = [];
        }
    }

    class CGroup
    {
        public static array     $addCalls    = [];
        public static array     $updateCalls = [];
        public static array     $deleteCalls = [];
        public static array     $rows        = [];
        public static int|false $addReturn   = 10;
        public static bool      $updateReturn = true;
        public string $LAST_ERROR = '';

        public function Add(array $fields): int|false
        {
            self::$addCalls[] = $fields;
            return self::$addReturn;
        }

        public function Update(int $id, array $fields): bool
        {
            self::$updateCalls[] = ['id' => $id, 'fields' => $fields];
            return self::$updateReturn;
        }

        public static function Delete(int $id): bool
        {
            self::$deleteCalls[] = $id;
            return true;
        }

        public static function GetByID(int $id): FakeOldResult
        {
            $found = array_values(array_filter(self::$rows, fn($r) => $r['ID'] === $id));
            return new FakeOldResult($found);
        }

        public static function GetList(string $sort, string $order, array $filter = []): FakeOldResult
        {
            return new FakeOldResult(self::$rows);
        }

        public static function reset(): void
        {
            self::$addCalls     = [];
            self::$updateCalls  = [];
            self::$deleteCalls  = [];
            self::$rows         = [];
            self::$addReturn    = 10;
            self::$updateReturn = true;
        }
    }
}

// =============================================================================
// Тесты
// =============================================================================

namespace Warenikov\McpBitrix\Tests\Unit {

    use PHPUnit\Framework\TestCase;
    use Warenikov\McpBitrix\Tools\UserTools;

    class UserToolsTest extends TestCase
    {
        private UserTools $tools;

        protected function setUp(): void
        {
            $this->tools = new UserTools();
            \CUser::reset();
            \CGroup::reset();
            \FakeApplication::reset();
        }

        // ─── Пользователи ────────────────────────────────────────────────────

        public function testListUsersPassesFilterAndPagination(): void
        {
            $this->tools->listUsers([
                'filter' => ['ACTIVE' => 'Y'],
                'limit'  => 10,
                'offset' => 20,
            ]);

            $call = \CUser::$getListCalls[0];
            $this->assertEquals(['ACTIVE' => 'Y'], $call['filter']);
            $this->assertEquals(10, $call['nav']['nPageSize']);
            $this->assertEquals(3,  $call['nav']['iNumPage']); // offset 20 / limit 10 + 1
        }

        public function testListUsersDefaultLimitIs20(): void
        {
            $this->tools->listUsers([]);
            $this->assertEquals(20, \CUser::$getListCalls[0]['nav']['nPageSize']);
        }

        public function testListUsersDefaultSortIsId(): void
        {
            $this->tools->listUsers([]);
            $this->assertEquals('ID', \CUser::$getListCalls[0]['sort']);
        }

        public function testListUsersOrderNormalization(): void
        {
            $this->tools->listUsers(['order' => 'DESC']);
            $this->assertEquals('desc', \CUser::$getListCalls[0]['order']);
        }

        public function testGetUserReturnsRow(): void
        {
            \CUser::$rows = [['ID' => 5, 'LOGIN' => 'john', 'EMAIL' => 'j@example.com']];

            $result = $this->tools->getUser(['id' => 5]);

            $this->assertEquals(5,     $result['ID']);
            $this->assertEquals('john', $result['LOGIN']);
        }

        public function testGetUserThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найден');

            $this->tools->getUser(['id' => 999]);
        }

        public function testGetUserIncludesGroupsWhenRequested(): void
        {
            \CUser::$rows       = [['ID' => 3, 'LOGIN' => 'test']];
            \CUser::$userGroups = [3 => [1, 2]];
            \CGroup::$rows      = [
                ['ID' => 1, 'NAME' => 'Admins'],
                ['ID' => 2, 'NAME' => 'Editors'],
            ];

            $result = $this->tools->getUser(['id' => 3, 'with_groups' => true]);

            $this->assertArrayHasKey('GROUPS', $result);
            $this->assertEquals([1, 2], $result['GROUPS']);
        }

        public function testCreateUserPassesRequiredFields(): void
        {
            $this->tools->createUser([
                'login'    => 'newuser',
                'password' => 'secret123',
                'email'    => 'new@example.com',
            ]);

            $fields = \CUser::$addCalls[0];
            $this->assertEquals('newuser',       $fields['LOGIN']);
            $this->assertEquals('secret123',     $fields['PASSWORD']);
            $this->assertEquals('secret123',     $fields['CONFIRM_PASSWORD']);
            $this->assertEquals('new@example.com', $fields['EMAIL']);
        }

        public function testCreateUserDefaultsActiveToY(): void
        {
            $this->tools->createUser([
                'login'    => 'u',
                'password' => 'p',
                'email'    => 'e@e.com',
            ]);

            $this->assertEquals('Y', \CUser::$addCalls[0]['ACTIVE']);
        }

        public function testCreateUserConvertsActiveBooleanToYN(): void
        {
            $this->tools->createUser([
                'login'    => 'u',
                'password' => 'p',
                'email'    => 'e@e.com',
                'active'   => false,
            ]);

            $this->assertEquals('N', \CUser::$addCalls[0]['ACTIVE']);
        }

        public function testCreateUserPassesGroupIds(): void
        {
            $this->tools->createUser([
                'login'     => 'u',
                'password'  => 'p',
                'email'     => 'e@e.com',
                'group_ids' => [1, 3, 5],
            ]);

            $this->assertEquals([1, 3, 5], \CUser::$addCalls[0]['GROUP_ID']);
        }

        public function testCreateUserPassesExtraFields(): void
        {
            $this->tools->createUser([
                'login'    => 'u',
                'password' => 'p',
                'email'    => 'e@e.com',
                'fields'   => ['PERSONAL_PHONE' => '+79001234567'],
            ]);

            $this->assertEquals('+79001234567', \CUser::$addCalls[0]['PERSONAL_PHONE']);
        }

        public function testCreateUserReturnsId(): void
        {
            \CUser::$addReturn = 42;

            $result = $this->tools->createUser([
                'login'    => 'u',
                'password' => 'p',
                'email'    => 'e@e.com',
            ]);

            $this->assertTrue($result['success']);
            $this->assertEquals(42, $result['id']);
        }

        public function testCreateUserReturnsApplicationErrorOnFailure(): void
        {
            \CUser::$addReturn = false;
            \FakeApplication::setException(new \FakeException('Login already exists'));

            $result = $this->tools->createUser([
                'login'    => 'u',
                'password' => 'p',
                'email'    => 'e@e.com',
            ]);

            $this->assertFalse($result['success']);
            $this->assertEquals('Login already exists', $result['error']);
        }

        public function testUpdateUserPassesOnlyProvidedFields(): void
        {
            $this->tools->updateUser(['id' => 7, 'name' => 'Ivan', 'active' => false]);

            $call = \CUser::$updateCalls[0];
            $this->assertEquals(7,      $call['id']);
            $this->assertEquals('Ivan', $call['fields']['NAME']);
            $this->assertEquals('N',    $call['fields']['ACTIVE']);
            $this->assertArrayNotHasKey('EMAIL', $call['fields']);
        }

        public function testUpdateUserSetsConfirmPasswordWhenPasswordGiven(): void
        {
            $this->tools->updateUser(['id' => 7, 'password' => 'newpass']);

            $fields = \CUser::$updateCalls[0]['fields'];
            $this->assertEquals('newpass', $fields['PASSWORD']);
            $this->assertEquals('newpass', $fields['CONFIRM_PASSWORD']);
        }

        public function testUpdateUserConvertsGroupIdsToInt(): void
        {
            $this->tools->updateUser(['id' => 7, 'group_ids' => ['1', '2']]);

            $this->assertEquals([1, 2], \CUser::$updateCalls[0]['fields']['GROUP_ID']);
        }

        public function testDeleteUserCallsDeleteWithId(): void
        {
            $this->tools->deleteUser(['id' => 9]);
            $this->assertContains(9, \CUser::$deleteCalls);
        }

        // ─── Группы ──────────────────────────────────────────────────────────

        public function testListGroupsReturnsRows(): void
        {
            \CGroup::$rows = [
                ['ID' => 1, 'NAME' => 'Admins'],
                ['ID' => 2, 'NAME' => 'Editors'],
            ];

            $result = $this->tools->listGroups([]);
            $this->assertCount(2, $result);
        }

        public function testGetGroupReturnsRow(): void
        {
            \CGroup::$rows = [['ID' => 3, 'NAME' => 'Authors']];

            $result = $this->tools->getGroup(['id' => 3]);
            $this->assertEquals('Authors', $result['NAME']);
        }

        public function testGetGroupThrowsWhenNotFound(): void
        {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('не найдена');

            $this->tools->getGroup(['id' => 999]);
        }

        public function testCreateGroupPassesNameAndDefaults(): void
        {
            $this->tools->createGroup(['name' => 'Moderators']);

            $fields = \CGroup::$addCalls[0];
            $this->assertEquals('Moderators', $fields['NAME']);
            $this->assertEquals('Y',          $fields['ACTIVE']);
            $this->assertEquals(100,          $fields['SORT']);
        }

        public function testCreateGroupConvertsActiveBooleanToYN(): void
        {
            $this->tools->createGroup(['name' => 'G', 'active' => false]);
            $this->assertEquals('N', \CGroup::$addCalls[0]['ACTIVE']);
        }

        public function testCreateGroupReturnsId(): void
        {
            \CGroup::$addReturn = 7;
            $result = $this->tools->createGroup(['name' => 'Test']);

            $this->assertTrue($result['success']);
            $this->assertEquals(7, $result['id']);
        }

        public function testUpdateGroupPassesOnlyProvidedFields(): void
        {
            $this->tools->updateGroup(['id' => 4, 'name' => 'NewName']);

            $call = \CGroup::$updateCalls[0];
            $this->assertEquals(4,         $call['id']);
            $this->assertEquals('NewName', $call['fields']['NAME']);
            $this->assertArrayNotHasKey('SORT', $call['fields']);
        }

        public function testDeleteGroupCallsDeleteWithId(): void
        {
            $this->tools->deleteGroup(['id' => 5]);
            $this->assertContains(5, \CGroup::$deleteCalls);
        }

        // ─── Членство ────────────────────────────────────────────────────────

        public function testGetUserGroupsReturnsGroupDetails(): void
        {
            \CUser::$userGroups = [1 => [2, 3]];
            \CGroup::$rows      = [
                ['ID' => 2, 'NAME' => 'Editors'],
                ['ID' => 3, 'NAME' => 'Authors'],
            ];

            $result = $this->tools->getUserGroups(['user_id' => 1]);

            $this->assertCount(2, $result);
            $this->assertEquals('Editors', $result[0]['NAME']);
        }

        public function testGetUserGroupsReturnsEmptyForUserWithNoGroups(): void
        {
            $result = $this->tools->getUserGroups(['user_id' => 99]);
            $this->assertEquals([], $result);
        }

        public function testSetUserGroupsCallsSetUserGroupWithCorrectArgs(): void
        {
            $this->tools->setUserGroups(['user_id' => 5, 'group_ids' => [1, 2, 3]]);

            $call = \CUser::$setGroupCalls[0];
            $this->assertEquals(5,         $call['user_id']);
            $this->assertEquals([1, 2, 3], $call['groups']);
        }

        public function testSetUserGroupsConvertsIdsToInt(): void
        {
            $this->tools->setUserGroups(['user_id' => 5, 'group_ids' => ['1', '2']]);

            $this->assertEquals([1, 2], \CUser::$setGroupCalls[0]['groups']);
        }
    }
}
