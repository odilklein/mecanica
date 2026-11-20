#!/usr/bin/env bash
# Site da documentação (React + Tailwind + React Flow) rodando via Docker.
#
#   ./site.sh install   # instala as dependências (uma vez)
#   ./site.sh dev       # servidor de desenvolvimento em http://localhost:5173
#   ./site.sh build     # gera docs/site/dist
#   ./site.sh preview   # serve o build em http://localhost:4173
#   ./site.sh deploy    # publica no Cloudflare Pages (produção)
set -euo pipefail

SITE="$(cd "$(dirname "$0")/../site" && pwd)"
RAIZ="$(cd "$SITE/../.." && pwd)"
IMG="${NODE_IMAGE:-node:22-alpine}"
UID_GID="$(id -u):$(id -g)"

run() {
  docker run --rm \
    --user "$UID_GID" \
    -e HOME=/tmp \
    -e npm_config_cache=/tmp/.npm \
    -v "$RAIZ:/workspace" \
    -w /workspace/docs/site \
    "$IMG" "$@"
}

case "${1:-build}" in
  install)
    run npm install --no-audit --no-fund
    ;;
  content)
    run npm run prepare-content
    ;;
  dev)
    docker run --rm -it \
      --user "$UID_GID" \
      -e HOME=/tmp \
      -e npm_config_cache=/tmp/.npm \
      -p 5173:5173 \
      -v "$RAIZ:/workspace" \
      -w /workspace/docs/site \
      "$IMG" npm run dev -- --host 0.0.0.0
    ;;
  build)
    if [ ! -d "$SITE/node_modules" ]; then
      echo "== instalando dependências"
      run npm install --no-audit --no-fund
    fi
    run npm run build
    echo "== build em $SITE/dist"
    ;;
  deploy)
    # Publica em https://torque-zenitec.pages.dev. Usa o login do wrangler
    # (~/.wrangler); rode `npx wrangler login` uma vez se ainda não estiver logado.
    if [ ! -d "$SITE/dist" ]; then
      echo "== nenhum build encontrado; gerando"
      "$0" build
    fi
    docker run --rm \
      --user "$UID_GID" \
      -e HOME=/tmp \
      -e npm_config_cache=/tmp/.npm \
      -v "$RAIZ:/workspace" \
      -v "$HOME/.wrangler:/tmp/.wrangler" \
      -w /workspace/docs/site \
      "$IMG" sh -c "npx --yes wrangler@latest pages deploy dist --project-name torque-zenitec --branch main --commit-dirty=true"
    ;;
  preview)
    docker run --rm \
      --user "$UID_GID" \
      -e HOME=/tmp \
      -e npm_config_cache=/tmp/.npm \
      -p 4173:4173 \
      -v "$RAIZ:/workspace" \
      -w /workspace/docs/site \
      "$IMG" npm run preview -- --host 0.0.0.0
    ;;
  *)
    echo "uso: $0 [install|content|dev|build|preview|deploy]"
    exit 1
    ;;
esac
