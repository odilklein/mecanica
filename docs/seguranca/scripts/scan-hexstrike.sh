#!/usr/bin/env bash
# =============================================================================
# Varredura de segurança reproduzível — HexStrike AI contra a aplicação local.
#
# Pré-requisitos:
#   - container "hexstrike" rodando (--network host, API em 127.0.0.1:8888)
#   - aplicação no ar em http://127.0.0.1:8080 (docker compose up -d app)
#
# Uso:
#   bash docs/seguranca/scripts/scan-hexstrike.sh [saida]
#
# Gera arquivos .json brutos da API e .txt normalizados.
# =============================================================================
set -uo pipefail

API="${HEXSTRIKE_API:-http://127.0.0.1:8888}"
TARGET="${TARGET:-http://127.0.0.1:8080}"
OUT="${1:-/tmp/hexstrike-mecanica}"
H='Content-Type: application/json'

mkdir -p "$OUT/art"

if ! curl -sf -m 5 "$API/health" >/dev/null; then
  echo "[!] HexStrike não respondeu em $API. Rode: docker start hexstrike" >&2
  exit 1
fi

post() { # nome endpoint json timeout
  local name=$1 ep=$2 body=$3 tmo=${4:-600}
  echo "[*] $name"
  curl -s -m "$tmo" -X POST "$API$ep" -H "$H" -d "$body" -o "$OUT/$name.json"
  python3 - "$OUT/$name.json" "$OUT/art/$name.txt" <<'PY'
import json, sys
try:
    d = json.load(open(sys.argv[1]))
except Exception:
    open(sys.argv[2], 'w').write('(resposta não-JSON)\n'); raise SystemExit
out = d.get('stdout') or ''
if d.get('stderr'):
    out += "\n--- stderr ---\n" + d['stderr']
open(sys.argv[2], 'w').write(out or '(vazio)\n')
PY
}

# 1. Cabeçalhos, métodos e caminhos sensíveis
{
  for p in "" login .env .git/HEAD server-status vendor/ src/ storage/ composer.json; do
    echo "### GET /$p"
    curl -si -m 20 "$TARGET/$p" | sed -n '1,20p'
    echo
  done
  for m in OPTIONS TRACE PUT DELETE; do
    echo "### $m /login"
    curl -s -o /dev/null -w 'status=%{http_code}\n' -m 20 -X "$m" "$TARGET/login"
  done
} > "$OUT/art/headers.txt" 2>&1

# 2. Descoberta e fingerprint
post nmap   /api/tools/nmap   "{\"target\":\"127.0.0.1\",\"scan_type\":\"-sCV\",\"ports\":\"22,80,443,8080,3306,8081\",\"additional_args\":\"-T4 -Pn\"}" 300
post wafw00f /api/tools/wafw00f "{\"target\":\"$TARGET\"}" 300
post nikto  /api/tools/nikto  "{\"target\":\"$TARGET\",\"additional_args\":\"-maxtime 300s -Tuning 123b -nointeractive\"}" 600

# 3. Templates de vulnerabilidade (atualiza a base na primeira execução)
post nuclei /api/tools/nuclei "{\"target\":\"$TARGET\",\"severity\":\"critical,high,medium\",\"additional_args\":\"-silent -no-color -stats -timeout 10 -retries 1\"}" 900

# 4. Conteúdo
post gobuster /api/tools/gobuster "{\"url\":\"$TARGET\",\"mode\":\"dir\",\"wordlist\":\"/usr/share/dirb/wordlists/common.txt\",\"additional_args\":\"-x php,txt,js,json,env,sql,bak -t 20 --timeout 10s\"}" 600
post ffuf     /api/tools/ffuf     "{\"url\":\"$TARGET\",\"wordlist\":\"/usr/share/dirb/wordlists/big.txt\",\"mode\":\"directory\",\"additional_args\":\"-t 30 -timeout 10 -s\"}" 600

echo "[+] Concluído. Resultados em $OUT (brutos) e $OUT/art (texto)."
