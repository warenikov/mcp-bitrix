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

# --- Определяем Docker-сеть проекта ---
NETWORK=""

# Ищем docker-compose.yml и вычисляем имя сети по имени папки
if [ -f "docker-compose.yml" ] || [ -f "docker-compose.yaml" ]; then
  PROJECT_NAME=$(basename "$PROJECT_PATH" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]//g')
  CANDIDATE="${PROJECT_NAME}_default"
  if docker network inspect "$CANDIDATE" &>/dev/null; then
    NETWORK="$CANDIDATE"
    echo -e "${GREEN}✓ Docker-сеть: ${BOLD}$NETWORK${NC}"
  fi
fi

# Если не нашли — ищем контейнер, у которого смонтирован наш путь
if [ -z "$NETWORK" ]; then
  CONTAINER_WITH_MOUNT=$(docker ps --quiet | while read -r cid; do
    mounts=$(docker inspect "$cid" --format '{{range .Mounts}}{{.Source}} {{end}}')
    if echo "$mounts" | grep -q "$PROJECT_PATH"; then
      echo "$cid"
      break
    fi
  done)

  if [ -n "$CONTAINER_WITH_MOUNT" ]; then
    NETWORK=$(docker inspect "$CONTAINER_WITH_MOUNT" --format '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}' | head -1)
    if [ -n "$NETWORK" ]; then
      echo -e "${GREEN}✓ Docker-сеть: ${BOLD}$NETWORK${NC}"
    fi
  fi
fi

if [ -z "$NETWORK" ]; then
  echo -e "${YELLOW}⚠ Docker-сеть не определена. Если Битрикс не подключается к БД — добавьте вручную:${NC}"
  echo '  "args": [..., "--network", "ИМЯ_СЕТИ", ...]'
fi

# --- Формируем аргументы docker run ---
if [ -n "$NETWORK" ]; then
  DOCKER_ARGS='["run","--rm","-i","--network","'"$NETWORK"'","-v","'"$PROJECT_PATH"':/var/www/html","'"$IMAGE"'"]'
else
  DOCKER_ARGS='["run","--rm","-i","-v","'"$PROJECT_PATH"':/var/www/html","'"$IMAGE"'"]'
fi

ENTRY='{"command":"docker","args":'"$DOCKER_ARGS"'}'

# --- Генерируем .mcp.json ---
MCP_FILE=".mcp.json"

if [ -f "$MCP_FILE" ]; then
  echo ""
  echo -e "${YELLOW}⚠ Файл $MCP_FILE уже существует.${NC}"

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
    echo -e "${GREEN}✓ Сервер bitrix обновлён в $MCP_FILE${NC}"
  else
    echo "  Добавьте вручную в секцию mcpServers:"
    echo ""
    echo "  \"bitrix\": $(echo "$ENTRY" | python3 -m json.tool 2>/dev/null || echo "$ENTRY")"
  fi
else
  python3 - "$MCP_FILE" "$ENTRY" <<'PYEOF'
import json, sys

path = sys.argv[1]
entry = json.loads(sys.argv[2])

data = {"mcpServers": {"bitrix": entry}}

with open(path, "w") as f:
    json.dump(data, f, indent=2, ensure_ascii=False)
    f.write("\n")
PYEOF
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
