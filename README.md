# mcp-bitrix

[![Tests](https://github.com/warenikov/mcp-bitrix/actions/workflows/tests.yml/badge.svg)](https://github.com/warenikov/mcp-bitrix/actions/workflows/tests.yml)
[![Docker](https://github.com/warenikov/mcp-bitrix/actions/workflows/docker.yml/badge.svg)](https://github.com/warenikov/mcp-bitrix/actions/workflows/docker.yml)

MCP-сервер для управления Битриксом через Claude. Позволяет создавать инфоблоки, управлять элементами, работать с пользователями и многое другое — прямо из чата.

## Требования

- Битрикс CMS (любая версия с поддержкой D7)
- Docker
- [Claude Code](https://claude.ai/code)

PHP на хосте **не нужен** — сервер запускается в собственном Docker-контейнере.

## Установка

### Быстрый старт — скрипт установки

Запустите в корне вашего Битрикс-проекта:

```bash
curl -sL https://raw.githubusercontent.com/warenikov/mcp-bitrix/main/install.sh | bash
```

Скрипт автоматически:
- скачает Docker-образ `ghcr.io/warenikov/mcp-bitrix`
- определит Docker-сеть проекта
- создаст `.mcp.json` и `.claude/settings.json` в текущей папке

После этого **перезапустите Claude Code** — сервер подключится автоматически.

---

### Ручная установка

Создайте `.mcp.json` в корне проекта:

```json
{
  "mcpServers": {
    "bitrix": {
      "command": "docker",
      "args": ["run", "--rm", "-i", "-v", "/путь/к/проекту:/var/www/html", "ghcr.io/warenikov/mcp-bitrix:latest"]
    }
  }
}
```

Замените `/путь/к/проекту` на абсолютный путь к папке с вашим Битриксом (где лежит `bitrix/`).

Если Битрикс работает в Docker — добавьте `--network` с именем сети вашего проекта:

```json
"args": ["run", "--rm", "-i", "--network", "myproject_default", "-v", "/путь/к/проекту:/var/www/html", "ghcr.io/warenikov/mcp-bitrix:latest"]
```

Создайте `.claude/settings.json`:

```json
{
  "enabledMcpjsonServers": ["bitrix"]
}
```

Перезапустите Claude Code.

---

## Настройка

### Режим только чтения

Чтобы запретить любые изменения данных, добавьте в `.mcp.json`:

```json
{
  "mcpServers": {
    "bitrix": {
      "command": "docker",
      "args": ["run", "--rm", "-i", "-v", "/путь/к/проекту:/var/www/html", "ghcr.io/warenikov/mcp-bitrix:latest"],
      "env": {
        "BITRIX_READONLY": "true"
      }
    }
  }
}
```

В этом режиме все операции создания, обновления и удаления заблокированы.

### Нестандартный путь к сайту внутри контейнера

Если Битрикс смонтирован не в `/var/www/html`:

```json
"args": ["run", "--rm", "-i", "-e", "BITRIX_DOCUMENT_ROOT=/var/www/mysite", "-v", "/путь/к/проекту:/var/www/mysite", "ghcr.io/warenikov/mcp-bitrix:latest"]
```

---

## Реализованные инструменты

### Типы инфоблоков
| Инструмент | Описание |
|---|---|
| `list_iblock_types` | Список всех типов |
| `create_iblock_type` | Создать тип |
| `delete_iblock_type` | Удалить тип |

### Инфоблоки
| Инструмент | Описание |
|---|---|
| `list_iblocks` | Список (фильтр по типу) |
| `get_iblock` | Получить по ID |
| `create_iblock` | Создать |
| `update_iblock` | Обновить |
| `delete_iblock` | Удалить |

### Свойства
| Инструмент | Описание |
|---|---|
| `list_iblock_properties` | Список свойств |
| `add_iblock_property` | Добавить (типы: S/N/L/F/G/E) |
| `update_iblock_property` | Обновить |
| `delete_iblock_property` | Удалить |

### Секции
| Инструмент | Описание |
|---|---|
| `list_iblock_sections` | Список секций |
| `add_iblock_section` | Добавить |
| `update_iblock_section` | Обновить |
| `delete_iblock_section` | Удалить |

### Элементы
| Инструмент | Описание |
|---|---|
| `get_iblock_elements` | Выборка с фильтром и постраничкой |
| `get_iblock_element` | Получить по ID (со свойствами) |
| `add_iblock_element` | Добавить |
| `update_iblock_element` | Обновить (поля + свойства) |
| `delete_iblock_element` | Удалить |

### Highload-блоки
| Инструмент | Описание |
|---|---|
| `list_hlblocks` | Список всех HL-блоков |
| `get_hlblock` | Получить по ID |
| `create_hlblock` | Создать (автоматически создаёт таблицу в БД) |
| `update_hlblock` | Обновить |
| `delete_hlblock` | Удалить |
| `list_hlblock_fields` | Список полей с лейблами (параметр `lang`, по умолчанию ru) |
| `add_hlblock_field` | Добавить поле (типы: string, text, integer, double, boolean, datetime, date, file, enumeration) |
| `update_hlblock_field` | Обновить поле |
| `delete_hlblock_field` | Удалить поле |
| `list_hlblock_elements` | Список элементов с фильтром и постраничкой |
| `get_hlblock_element` | Получить элемент по ID |
| `add_hlblock_element` | Добавить элемент |
| `update_hlblock_element` | Обновить элемент |
| `delete_hlblock_element` | Удалить элемент |

### Пользователи и группы
| Инструмент | Описание |
|---|---|
| `list_users` | Список пользователей с фильтром и постраничкой |
| `get_user` | Получить пользователя по ID (опционально с группами) |
| `create_user` | Создать пользователя |
| `update_user` | Обновить пользователя |
| `delete_user` | Удалить пользователя |
| `list_groups` | Список групп |
| `get_group` | Получить группу по ID |
| `create_group` | Создать группу |
| `update_group` | Обновить группу |
| `delete_group` | Удалить группу |
| `get_user_groups` | Получить группы пользователя |
| `set_user_groups` | Задать группы пользователя (полная замена) |

### Кастомные ORM-сущности

Инструменты для создания произвольных таблиц в БД через Битрикс D7 ORM и работы с ними прямо из Claude.

> **Как это работает.** Сервер хранит схемы сущностей в служебной таблице `b_mcp_orm_registry` (создаётся автоматически при первом обращении). При каждом CRUD-запросе сущность компилируется из сохранённой схемы через `Entity::compileEntity()`. Таблица в БД создаётся один раз при `create_orm_entity` и живёт независимо.

**Управление сущностями**

| Инструмент | Описание |
|---|---|
| `create_orm_entity` | Создать сущность и таблицу в БД |
| `list_orm_entities` | Список всех зарегистрированных сущностей |
| `get_orm_entity` | Описание сущности по имени |
| `drop_orm_entity` | Удалить сущность и её таблицу из БД |

Поддерживаемые типы полей: `integer`, `string`, `text`, `float`, `boolean`, `date`, `datetime`.

Поле `ID` (integer, primary, autocomplete) добавляется автоматически, если не задано явно.

**CRUD записей**

| Инструмент | Описание |
|---|---|
| `orm_list` | Список записей с фильтром, сортировкой и постраничкой |
| `orm_get` | Получить запись по ID |
| `orm_add` | Добавить запись |
| `orm_update` | Обновить запись по ID |
| `orm_delete` | Удалить запись по ID |

**Генерация PHP-класса**

| Инструмент | Описание |
|---|---|
| `write_orm_class_file` | Записать PHP DataManager-класс в файловую систему сайта |

По умолчанию файл создаётся в `/local/lib/Orm/{EntityName}Table.php`.

Сценарий использования:
1. Claude создаёт сущность через `create_orm_entity` и проверяет структуру через CRUD
2. Когда структура устраивает — вызывает `write_orm_class_file`
3. Разработчик регистрирует класс в автолоадере Битрикса

Пример регистрации в `/local/.settings.php`:
```php
'autoload' => [
    'value' => [
        'classes' => [
            'ProductLogTable' => '/local/lib/Orm/ProductLogTable.php',
        ],
    ],
],
```

После этого класс доступен в коде сайта как обычный DataManager:
```php
$rows = ProductLogTable::getList(['filter' => ['ACTIVE' => 'Y']])->fetchAll();
```

### Агенты
| Инструмент | Описание |
|---|---|
| `list_agents` | Список агентов с фильтром |
| `get_agent` | Получить агент по ID |
| `add_agent` | Создать агент |
| `update_agent` | Обновить агент |
| `delete_agent` | Удалить агент |
| `run_agent` | Запланировать немедленный запуск (устанавливает NEXT_EXEC = сейчас) |

### Кэш
| Инструмент | Описание |
|---|---|
| `clear_cache` | Очистить managed cache (полностью или по тегу) |
| `clear_menu_cache` | Удалить файлы кэша меню `.menu_ex.php` |

### Настройки модулей
| Инструмент | Описание |
|---|---|
| `get_option` | Получить значение настройки модуля |
| `set_option` | Установить значение настройки модуля |
| `delete_option` | Удалить настройку |
| `list_options` | Список всех настроек модуля |

### Журнал событий
| Инструмент | Описание |
|---|---|
| `list_event_log` | Список записей журнала событий |
| `get_event_log` | Получить запись по ID |
| `add_event_log` | Добавить запись в журнал |
| `clear_event_log` | Удалить записи старше N дней (по умолчанию 30) |

### Почтовые события
| Инструмент | Описание |
|---|---|
| `list_mail_event_types` | Список типов почтовых событий |
| `get_mail_event_type` | Получить тип по символьному коду |
| `list_mail_templates` | Список шаблонов писем |
| `get_mail_template` | Получить шаблон по ID |
| `add_mail_template` | Создать шаблон |
| `update_mail_template` | Обновить шаблон |
| `delete_mail_template` | Удалить шаблон |

---

## В разработке

### Планируется
- **Каталог и магазин** — товары, цены, заказы
- **CRM** — лиды, сделки, контакты
- **Задачи и бизнес-процессы**

---

## Обновление

Чтобы обновить сервер до последней версии:

```bash
docker pull ghcr.io/warenikov/mcp-bitrix:latest
```

Перезапускать Claude Code после этого не нужно — образ подтянется при следующем вызове инструмента.

---

## Как это работает

```
Claude → MCP protocol (stdio) → docker run --rm -i -v /project:/var/www/html ghcr.io/warenikov/mcp-bitrix → Bitrix PHP API → JSON
```

При каждом вызове инструмента Claude запускает одноразовый Docker-контейнер, который монтирует папку вашего проекта и напрямую обращается к ядру Битрикса. Никаких внешних зависимостей — MCP-протокол реализован без сторонних библиотек.

## Разработка и тесты

### Юнит-тесты

Не требуют Битрикса — запускаются локально в Docker:

```bash
docker run --rm -v $(pwd):/app -w /app php:8.2-cli sh -c \
  "php vendor/bin/phpunit tests/Unit"
```

Или если PHP установлен на хосте:

```bash
composer test
```

### Интеграционные тесты

Запускаются внутри контейнера с реальным Битриксом. Нужен собранный образ `mcp-bitrix:local` и доступ к БД.

```bash
# Сборка локального образа
docker build -t mcp-bitrix:local .

# Запуск интеграционных тестов
docker run --rm \
  --network <сеть_проекта> \
  -v /путь/к/битриксу:/var/www/html \
  -e BITRIX_DOCUMENT_ROOT=/var/www/html \
  mcp-bitrix:local \
  php vendor/bin/phpunit -c phpunit-integration.xml
```

### Пересборка образа после изменений

```bash
docker build -t mcp-bitrix:local .
```

После этого выполните `/mcp reconnect bitrix` в Claude Code — новый образ подхватится автоматически.

---

## Лицензия

MIT
