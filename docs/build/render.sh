#!/usr/bin/env bash
# Pipeline de documentação do Torque.
#
#   ./render.sh diagramas   # PlantUML -> PNG
#   ./render.sh documento   # Markdown -> DOCX -> PDF
#   ./render.sh tudo
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/../.." && pwd)"
DIAGRAMAS="$RAIZ/docs/diagramas"

diagramas() {
  echo "== diagramas (PlantUML)"
  docker run --rm -v "$DIAGRAMAS:/w" -w /w plantuml/plantuml \
    -DPLANTUML_LIMIT_SIZE=8192 -tpng fontes/[0-9]*.puml
  for arquivo in "$DIAGRAMAS"/fontes/[0-9]*.png; do
    mv "$arquivo" "$DIAGRAMAS/png/"
  done
  echo "   $(ls "$DIAGRAMAS"/png/*.png | wc -l) imagens em docs/diagramas/png"
}

documento() {
  echo "== documento"
  python3 "$RAIZ/docs/build/gerar_dicionario.py"
  python3 "$RAIZ/docs/build/build_docx.py"
  python3 "$RAIZ/docs/build/export_pdf.py" --qa
}

case "${1:-tudo}" in
  diagramas) diagramas ;;
  documento) documento ;;
  tudo) diagramas; documento ;;
  *) echo "uso: $0 [diagramas|documento|tudo]"; exit 1 ;;
esac
