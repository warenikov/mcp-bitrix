#!/usr/bin/env bash
set -e

BOLD='\033[1m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo ""
echo -e "${BOLD}mcp-bitrix installer${NC}"
echo "─────────────────────────────────────"

# --- Проверяем Docker ---
if ! command -v docker &>/dev/null; then
  echo -e "${RED}✗ Docker не найден. Установите Docker и попробуйте снова.${NC}"
  exit 1
fi

if ! docker info &>/dev/null; then
  echo -e "${RED}✗ Docker не запущен. Запустите Docker Desktop и попробуйте снова.${NC}"
  exit 1
fi

echo -e "${GREEN}✓ Docker запущен${NC}"

# --- Ищем PHP-контейнер с Битриксом ---
echo ""
echo "Ищу PHP-контейнеры..."

PHP_CONTAINERS=$(docker ps --format "{{.Names}}" | grep -i php || true)

if [ -z "$PHP_CONTAINERS" ]; then
  echo -e "${RED}✗ Не найдено запущенных PHP-контейнеров.${NC}"
  echo "  Убедитесь что ваш Битрикс-проект запущен (docker compose up -d)"
  exit 1
fi

CONTAINER_COUNT=$(echo "$PHP_CONTAINERS" | wc -l | tr -d ' ')

if [ "$CONTAINER_COUNT" -eq 1 ]; then
  CONTAINER=$(echo "$PHP_CONTAINERS" | tr -d '[:space:]')
  echo -e "${GREEN}✓ Найден контейнер: ${BOLD}$CONTAINER${NC}"
else
  echo "Найдено несколько PHP-контейнеров:"
  echo "$PHP_CONTAINERS" | nl -w2 -s') '
  echo ""
  read -p "Введите номер нужного контейнера: " CHOICE
  CONTAINER=$(echo "$PHP_CONTAINERS" | sed -n "${CHOICE}p" | tr -d '[:space:]')
  if [ -z "$CONTAINER" ]; then
    echo -e "${RED}✗ Неверный выбор.${NC}"
    exit 1
  fi
fi

# --- Проверяем composer в контейнере ---
echo ""
if ! docker exec "$CONTAINER" composer --version &>/dev/null; then
  echo -e "${RED}✗ Composer не найден в контейнере $CONTAINER.${NC}"
  echo ""
  echo "  Добавьте в Dockerfile вашего PHP-контейнера:"
  echo "  COPY --from=composer:latest /usr/bin/composer /usr/bin/composer"
  echo ""
  echo "  Затем пересоберите образ:"
  echo "  docker compose build php && docker compose up -d php"
  exit 1
fi

echo -e "${GREEN}✓ Composer найден${NC}"

# --- Определяем document root ---
DOCUMENT_ROOT=$(docker exec "$CONTAINER" sh -c 'echo ${DOCUMENT_ROOT:-/var/www/html}')
echo -e "${GREEN}✓ Document root: $DOCUMENT_ROOT${NC}"

# --- Устанавливаем пакет ---
echo ""
echo "Устанавливаю warenikov/mcp-bitrix..."
echo ""

if docker exec "$CONTAINER" composer require warenikov/mcp-bitrix --working-dir "$DOCUMENT_ROOT"; then
  echo ""
  echo -e "${GREEN}✓ Пакет установлен${NC}"
else
  echo -e "${RED}✗ Ошибка установки пакета.${NC}"
  exit 1
fi

# --- Генерируем .mcp.json ---
echo ""
MCP_FILE=".mcp.json"

if [ -f "$MCP_FILE" ]; then
  # Файл существует — добавляем сервер через python/node если доступны
  echo -e "${YELLOW}⚠ Файл $MCP_FILE уже существует.${NC}"
  echo "  Добавьте вручную в секцию mcpServers:"
  echo ""
  echo '  "bitrix": {'
  echo "    \"command\": \"docker\","
  echo "    \"args\": [\"exec\", \"-i\", \"$CONTAINER\", \"php\", \"$DOCUMENT_ROOT/vendor/bin/server\"]"
  echo '  }'
else
  cat > "$MCP_FILE" <<EOF
{
  "mcpServers": {
    "bitrix": {
      "command": "docker",
      "args": ["exec", "-i", "$CONTAINER", "php", "$DOCUMENT_ROOT/vendor/bin/server"]
    }
  }
}
EOF
  echo -e "${GREEN}✓ Создан $MCP_FILE${NC}"
fi

# --- Готово ---
echo ""
echo "─────────────────────────────────────"
echo -e "${GREEN}${BOLD}Установка завершена!${NC}"
echo ""
echo "Следующий шаг:"
echo -e "  Перезапустите ${BOLD}Claude Code${NC} чтобы подключить MCP-сервер."
echo ""
echo "Для включения режима только чтения добавьте в .mcp.json:"
echo "  \"env\": { \"BITRIX_READONLY\": \"true\" }"
echo ""
