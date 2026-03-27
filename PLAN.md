# mcp-bitrix — План реализации

MCP сервер для управления Битриксом через Claude.
Пакет: `warenikov/mcp-bitrix`

## Механика работы

```
Claude → MCP protocol (stdio) → docker exec -i {container} php vendor/bin/server → Bitrix PHP API → JSON
```

Сервер запускается **внутри** Docker контейнера через `docker exec -i`.
PHP-процесс живёт внутри контейнера и имеет прямой доступ к Битриксу.
Внешний MCP SDK не нужен — протокол MCP (JSON-RPC 2.0 + stdio) реализован напрямую.

Конфигурация в `.mcp.json` проекта:
```json
{
  "mcpServers": {
    "bitrix": {
      "command": "docker",
      "args": ["exec", "-i", "CONTAINER_NAME", "php", "/var/www/html/vendor/bin/server"],
      "env": {
        "BITRIX_DOCUMENT_ROOT": "/var/www/html"
      }
    }
  }
}
```

Установка в проект:
```bash
composer require warenikov/mcp-bitrix
```

## Структура проекта

```
mcp-bitrix/
├── bin/
│   └── server              # точка входа (#!/usr/bin/env php)
├── src/
│   ├── Server.php           # bootstrap + регистрация всех Tools
│   ├── BitrixBootstrap.php  # инициализация Битрикса (require prolog)
│   └── Tools/
│       └── ...
├── composer.json
├── .gitignore
└── README.md
```

---

## Фаза 1 — Ядро (MVP)

### Инфраструктура
- [ ] `composer.json` — пакет, зависимости, autoload, bin
- [ ] `bin/server` — точка входа
- [ ] `src/BitrixBootstrap.php` — инициализация Битрикса
- [ ] `src/Server.php` — регистрация инструментов и запуск сервера

### iblock — Типы инфоблоков
- [ ] `list_iblock_types` — список типов ИБ
- [ ] `create_iblock_type` — создать тип ИБ
- [ ] `delete_iblock_type` — удалить тип ИБ

### iblock — Инфоблоки
- [ ] `list_iblocks` — список ИБ (фильтр по типу)
- [ ] `get_iblock` — получить ИБ по ID
- [ ] `create_iblock` — создать ИБ
- [ ] `update_iblock` — обновить ИБ
- [ ] `delete_iblock` — удалить ИБ

### iblock — Свойства
- [ ] `list_iblock_properties` — список свойств ИБ
- [ ] `add_iblock_property` — добавить свойство
- [ ] `update_iblock_property` — обновить свойство
- [ ] `delete_iblock_property` — удалить свойство

### iblock — Секции
- [ ] `list_iblock_sections` — список секций
- [ ] `add_iblock_section` — добавить секцию
- [ ] `update_iblock_section` — обновить секцию
- [ ] `delete_iblock_section` — удалить секцию

### iblock — Элементы
- [ ] `get_iblock_elements` — выборка элементов (фильтр, поля, постраничка)
- [ ] `get_iblock_element` — получить элемент по ID
- [ ] `add_iblock_element` — добавить элемент
- [ ] `update_iblock_element` — обновить элемент
- [ ] `delete_iblock_element` — удалить элемент

### highloadblock — HL-блоки
- [ ] `list_hlblocks` — список HL-блоков
- [ ] `get_hlblock` — получить HL-блок по ID
- [ ] `create_hlblock` — создать HL-блок
- [ ] `update_hlblock` — обновить HL-блок
- [ ] `delete_hlblock` — удалить HL-блок

### highloadblock — Поля HL
- [ ] `list_hlblock_fields` — список полей HL-блока
- [ ] `add_hlblock_field` — добавить поле
- [ ] `update_hlblock_field` — обновить поле
- [ ] `delete_hlblock_field` — удалить поле

### highloadblock — Элементы HL
- [ ] `get_hlblock_elements` — выборка элементов
- [ ] `add_hlblock_element` — добавить элемент
- [ ] `update_hlblock_element` — обновить элемент
- [ ] `delete_hlblock_element` — удалить элемент

### main — Пользователи
- [ ] `list_users` — список пользователей (фильтр)
- [ ] `get_user` — получить пользователя по ID
- [ ] `create_user` — создать пользователя
- [ ] `update_user` — обновить пользователя
- [ ] `delete_user` — удалить пользователя

### main — Группы пользователей
- [ ] `list_user_groups` — список групп
- [ ] `create_user_group` — создать группу
- [ ] `update_user_group` — обновить группу
- [ ] `delete_user_group` — удалить группу
- [ ] `set_user_groups` — назначить пользователя в группы

### main — Пользовательские поля
- [ ] `list_user_fields` — список UF-полей (по entity)
- [ ] `add_user_field` — добавить UF-поле
- [ ] `delete_user_field` — удалить UF-поле

### main — ORM и таблицы
- [ ] `list_custom_tables` — список кастомных таблиц в БД
- [ ] `create_custom_table` — создать таблицу (с описанием полей)
- [ ] `generate_orm_class` — сгенерировать PHP DataManager-класс для таблицы

---

## Фаза 2 — Коммерция

### catalog — Каталог
- [ ] `list_catalog_iblocks` — список каталогов
- [ ] `list_price_types` — типы цен
- [ ] `get_product` — получить товар
- [ ] `add_product` — добавить товар
- [ ] `update_product_price` — обновить цену
- [ ] `list_offers` — торговые предложения

### sale — Магазин
- [ ] `list_orders` — список заказов
- [ ] `get_order` — получить заказ
- [ ] `list_payment_systems` — платёжные системы
- [ ] `list_deliveries` — службы доставки

---

## Фаза 3 — CRM и задачи

### crm
- [ ] `list_crm_leads` — лиды
- [ ] `list_crm_deals` — сделки
- [ ] `list_crm_contacts` — контакты
- [ ] `list_crm_companies` — компании
- [ ] `add_crm_lead` / `update_crm_lead` / `delete_crm_lead`
- [ ] `add_crm_deal` / `update_crm_deal` / `delete_crm_deal`
- [ ] `list_crm_fields` — список полей CRM-сущности

### tasks
- [ ] `list_tasks` — список задач
- [ ] `get_task` — получить задачу
- [ ] `add_task` — создать задачу
- [ ] `update_task` — обновить задачу
- [ ] `delete_task` — удалить задачу

### bizproc
- [ ] `list_bp_templates` — шаблоны БП
- [ ] `start_bp` — запустить БП

---

## Фаза 4 — Системное

### main — Агенты
- [ ] `list_agents` — список агентов
- [ ] `add_agent` — добавить агент
- [ ] `delete_agent` — удалить агент
- [ ] `run_agent` — запустить агент вручную

### main — События и почта
- [ ] `list_event_types` — типы событий
- [ ] `list_event_messages` — почтовые шаблоны
- [ ] `add_event_message` — добавить шаблон
- [ ] `update_event_message` — обновить шаблон
- [ ] `list_event_handlers` — обработчики событий

### main — Настройки
- [ ] `get_option` — получить настройку модуля
- [ ] `set_option` — установить настройку модуля
- [ ] `list_modules` — список установленных модулей

---

## Публикация

- [ ] Создать репозиторий `warenikov/mcp-bitrix` на GitHub
- [ ] Зарегистрировать пакет на Packagist
- [ ] Написать README с инструкцией по установке и настройке
- [ ] Добавить пример `.mcp.json` для разных окружений (local, Docker)

---

## Версия с нативным PHP на хосте (отдельная задача)

Альтернативный режим для серверов без Docker.
PHP-процесс запускается напрямую на хосте, `BITRIX_DOCUMENT_ROOT` указывает на файлы сайта.
```json
{
  "mcpServers": {
    "bitrix": {
      "command": "php",
      "args": ["vendor/bin/server"],
      "env": { "BITRIX_DOCUMENT_ROOT": "/var/www/mysite" }
    }
  }
}
```
