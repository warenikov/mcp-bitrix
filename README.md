# mcp-bitrix

[![Tests](https://github.com/warenikov/mcp-bitrix/actions/workflows/tests.yml/badge.svg)](https://github.com/warenikov/mcp-bitrix/actions/workflows/tests.yml)

MCP-сервер для управления Битриксом через Claude. Позволяет создавать инфоблоки, управлять элементами, работать с пользователями и многое другое — прямо из чата.

## Требования

- Битрикс CMS (любая версия с поддержкой D7)
- Docker с запущенным PHP-контейнером
- Composer внутри PHP-контейнера
- [Claude Code](https://claude.ai/code)

## Установка

### Быстрый старт — скрипт установки

Запустите в корне вашего Битрикс-проекта:

```bash
curl -sL https://raw.githubusercontent.com/warenikov/mcp-bitrix/main/install.sh | bash
```

Скрипт автоматически:
- найдёт ваш PHP-контейнер
- установит пакет через composer
- создаст `.mcp.json` в текущей папке

После этого **перезапустите Claude Code** — сервер подключится автоматически.

---

### Ручная установка

Если предпочитаете делать всё самостоятельно:

**1. Убедитесь что в PHP-контейнере есть Composer**

Добавьте в `Dockerfile` вашего PHP-сервиса:

```dockerfile
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
```

Пересоберите:

```bash
docker compose build php && docker compose up -d php
```

**2. Установите пакет внутри контейнера**

```bash
docker exec ИМЯ_КОНТЕЙНЕРА composer require warenikov/mcp-bitrix
```

Имя контейнера можно узнать командой `docker ps`.

**3. Создайте `.mcp.json` в корне проекта**

```json
{
  "mcpServers": {
    "bitrix": {
      "command": "docker",
      "args": ["exec", "-i", "ИМЯ_КОНТЕЙНЕРА", "php", "/var/www/html/vendor/bin/server"]
    }
  }
}
```

**4. Перезапустите Claude Code**

---

## Настройка

### Режим только чтения

Чтобы запретить любые изменения данных, добавьте в `.mcp.json`:

```json
{
  "mcpServers": {
    "bitrix": {
      "command": "docker",
      "args": ["exec", "-i", "ИМЯ_КОНТЕЙНЕРА", "php", "/var/www/html/vendor/bin/server"],
      "env": {
        "BITRIX_READONLY": "true"
      }
    }
  }
}
```

В этом режиме все операции создания, обновления и удаления заблокированы.

### Нестандартный путь к сайту

Если Битрикс установлен не в `/var/www/html`, укажите путь:

```json
"args": ["exec", "-i", "ИМЯ_КОНТЕЙНЕРА", "-e", "BITRIX_DOCUMENT_ROOT=/var/www/mysite", "php", "/var/www/mysite/vendor/bin/server"]
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

---

## В разработке

### Ближайшие (Фаза 1)
- **Highload-блоки** — CRUD для HL-блоков, полей и элементов
- **Пользователи** — создание, группы, пользовательские поля
- **ORM** — генерация DataManager-классов, создание таблиц

### Планируется
- **Каталог и магазин** — товары, цены, заказы
- **CRM** — лиды, сделки, контакты
- **Задачи и бизнес-процессы**
- **Системные инструменты** — агенты, события, настройки модулей

---

## Как это работает

```
Claude → MCP protocol (stdio) → docker exec -i {container} php vendor/bin/server → Bitrix PHP API → JSON
```

Сервер запускается внутри Docker-контейнера через `docker exec`. PHP-процесс напрямую инициализирует ядро Битрикса и вызывает его API. Никаких внешних зависимостей — MCP-протокол реализован без сторонних библиотек.

## Лицензия

MIT
