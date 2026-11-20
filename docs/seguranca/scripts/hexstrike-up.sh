#!/usr/bin/env bash
# =============================================================================
# Sobe o HexStrike AI em container isolado (Kali) para as varreduras.
#
# O código do orquestrador vive fora do repositório da aplicação:
#   HEXSTRIKE_SRC (default: /home/odil/tools/hexstrike-ai)
#
# O container usa --network host para alcançar http://127.0.0.1:8080 e expor a
# API em 127.0.0.1:8888 (usada pelos scripts de scan e por clientes MCP).
# =============================================================================
set -euo pipefail

HEXSTRIKE_SRC="${HEXSTRIKE_SRC:-/home/odil/tools/hexstrike-ai}"
IMAGE="${HEXSTRIKE_IMAGE:-hexstrike-ai:kali}"
NAME="${HEXSTRIKE_NAME:-hexstrike}"

if [ ! -f "$HEXSTRIKE_SRC/hexstrike_server.py" ]; then
  echo "[!] Código não encontrado em $HEXSTRIKE_SRC" >&2
  echo "    git clone https://github.com/0x4m4/hexstrike-ai \"$HEXSTRIKE_SRC\"" >&2
  exit 1
fi

if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
  echo "[*] Construindo imagem $IMAGE (pode levar alguns minutos)..."
  docker build -t "$IMAGE" "$HEXSTRIKE_SRC"
fi

if docker container inspect "$NAME" >/dev/null 2>&1; then
  echo "[*] Container $NAME já existe; iniciando..."
  docker start "$NAME" >/dev/null
else
  echo "[*] Criando container $NAME..."
  docker run -d --name "$NAME" --network host --restart unless-stopped "$IMAGE" >/dev/null
fi

echo -n "[*] Aguardando API em http://127.0.0.1:8888"
for _ in $(seq 1 30); do
  if curl -sf -m 2 http://127.0.0.1:8888/health >/dev/null; then
    echo " — ok"
    curl -s http://127.0.0.1:8888/health | python3 -c 'import json,sys; d=json.load(sys.stdin); print("    status=%s versao=%s ferramentas=%s/%s" % (d["status"], d["version"], d["total_tools_available"], d["total_tools_count"]))'
    exit 0
  fi
  echo -n "."
  sleep 2
done

echo
echo "[!] A API não respondeu. Veja: docker logs $NAME" >&2
exit 1
