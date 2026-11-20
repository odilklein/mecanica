#!/usr/bin/env python3
"""Rasteriza os SVGs do PlantUML em PNG de alta resolução.

Usa o MuPDF (PyMuPDF), que já é dependência do fluxo de conferência do PDF.
"""

from __future__ import annotations

from pathlib import Path

import pymupdf

RAIZ = Path(__file__).resolve().parents[1]
ENTRADA = RAIZ / "diagramas" / "fontes" / "svg"
SAIDA = RAIZ / "diagramas" / "png"
ESCALA = 4  # 4x sobre o tamanho em pontos


def main() -> None:
    SAIDA.mkdir(parents=True, exist_ok=True)
    svgs = sorted(ENTRADA.glob("*.svg"))
    if not svgs:
        raise SystemExit(f"nenhum SVG em {ENTRADA}")
    for svg in svgs:
        doc = pymupdf.open(svg)
        pagina = doc[0]
        pix = pagina.get_pixmap(matrix=pymupdf.Matrix(ESCALA, ESCALA), alpha=False)
        destino = SAIDA / (svg.stem + ".png")
        pix.save(destino)
        print(f"{destino.name:38} {pix.width}x{pix.height}")
        doc.close()


if __name__ == "__main__":
    main()
