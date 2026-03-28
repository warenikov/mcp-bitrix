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

---

## В разработке

### Ближайшие (Фаза 2)
- **Пользователи** — создание, группы, пользовательские поля
- **ORM** — генерация DataManager-классов, создание таблиц

### Планируется
- **Каталог и магазин** — товары, цены, заказы
- **CRM** — лиды, сделки, контакты
- **Задачи и бизнес-процессы**
- **Системные инструменты** — агенты, события, настройки модулей

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

## Лицензия

MIT
