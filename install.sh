#!/usr/bin/env bash
set -e

IMAGE="ghcr.io/warenikov/mcp-bitrix:latest"

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

# --- Скачиваем образ ---
echo ""
echo "Скачиваю образ $IMAGE..."
if docker pull "$IMAGE"; then
  echo -e "${GREEN}✓ Образ загружен${NC}"
else
  echo -e "${RED}✗ Не удалось скачать образ.${NC}"
  exit 1
fi

# --- Определяем путь к проекту ---
PROJECT_PATH="$(pwd)"
echo ""
echo -e "${GREEN}✓ Путь к проекту: ${BOLD}$PROJECT_PATH${NC}"

# --- Генерируем .mcp.json ---
MCP_FILE=".mcp.json"

ENTRY='{"command":"docker","args":["run","--rm","-i","-v","'"$PROJECT_PATH"':/var/www/html","'"$IMAGE"'"]}'

if [ -f "$MCP_FILE" ]; then
  echo ""
  echo -e "${YELLOW}⚠ Файл $MCP_FILE уже существует.${NC}"

  # Пробуем добавить через python3
  if command -v python3 &>/dev/null; then
    python3 - "$MCP_FILE" "$ENTRY" <<'PYEOF'
import json, sys

path = sys.argv[1]
entry = json.loads(sys.argv[2])

with open(path) as f:
    data = json.load(f)

if "mcpServers" not in data:
    data["mcpServers"] = {}

data["mcpServers"]["bitrix"] = entry

with open(path, "w") as f:
    json.dump(data, f, indent=2, ensure_ascii=False)
    f.write("\n")
PYEOF
    echo -e "${GREEN}✓ Сервер bitrix добавлен в $MCP_FILE${NC}"
  else
    echo "  Добавьте вручную в секцию mcpServers:"
    echo ""
    echo '  "bitrix": {'
    echo '    "command": "docker",'
    echo "    \"args\": [\"run\", \"--rm\", \"-i\", \"-v\", \"$PROJECT_PATH:/var/www/html\", \"$IMAGE\"]"
    echo '  }'
  fi
else
  cat > "$MCP_FILE" <<EOF
{
  "mcpServers": {
    "bitrix": {
      "command": "docker",
      "args": ["run", "--rm", "-i", "-v", "$PROJECT_PATH:/var/www/html", "$IMAGE"]
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
echo "Опционально — режим только чтения (безопасно для продакшена):"
echo '  Добавьте в .mcp.json: "env": { "BITRIX_READONLY": "true" }'
echo ""
