#!/usr/bin/env python3
"""Converte o DOCX em PDF, calcula as páginas do sumário e converte de novo.

Uso:
    python3 docs/build/export_pdf.py [--qa]

A primeira conversão serve para descobrir em que página cada título caiu.
O sumário é atualizado e o PDF é gerado novamente. Com --qa, cada página é
rasterizada em docs/saida/qa/ para conferência visual.
"""

from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

import pymupdf
from docx import Document
from docx.oxml.ns import qn

RAIZ = Path(__file__).resolve().parents[2]
SAIDA = RAIZ / "docs" / "saida"
DOCX = SAIDA / "PI-ES2-2026.docx"
PDF = SAIDA / "PI-ES2-2026.pdf"
MANIFESTO = SAIDA / "toc.json"


def converter() -> None:
    subprocess.run(
        [
            "docker", "run", "--rm",
            "-v", f"{SAIDA}:/data",
            "-w", "/data",
            "linuxserver/libreoffice",
            "soffice", "--headless", "--convert-to", "pdf", "--outdir", "/data",
            DOCX.name,
        ],
        check=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )


def normalizar(texto: str) -> str:
    texto = texto.strip().lower()
    texto = re.sub(r"^\d+(\.\d+)*\.?\s*", "", texto)
    texto = re.sub(r"\s+", " ", texto)
    return texto


def paginas_do_pdf() -> dict[str, int]:
    doc = pymupdf.open(PDF)
    mapa: dict[str, int] = {}
    for nivel, titulo, pagina in doc.get_toc():
        chave = normalizar(titulo)
        mapa.setdefault(chave, pagina)
    doc.close()
    return mapa


def atualizar_sumario(paginas: dict[str, int]) -> int:
    headings = json.loads(MANIFESTO.read_text(encoding="utf-8"))
    doc = Document(DOCX)
    body = doc.element.body
    sdt = None
    for el in body.iterchildren():
        if el.tag == qn("w:sdt"):
            galeria = el.find(".//" + qn("w:docPartGallery"))
            if galeria is not None and galeria.get(qn("w:val")) == "Table of Contents":
                sdt = el
                break
    if sdt is None:
        raise RuntimeError("sumário não encontrado no DOCX")

    conteudo = sdt.find(qn("w:sdtContent"))
    paragrafos = [p for p in conteudo.iterchildren(qn("w:p"))][1:]
    if len(paragrafos) != len(headings):
        raise RuntimeError(
            f"sumário tem {len(paragrafos)} linhas e o manifesto {len(headings)}"
        )

    faltando = 0
    for heading, p in zip(headings, paragrafos):
        chave = normalizar(heading["texto"])
        pagina = paginas.get(chave)
        if pagina is None:
            faltando += 1
            pagina = 0
        runs = list(p.iterchildren(qn("w:r")))
        if not runs:
            continue
        ultimo = runs[-1]
        t = ultimo.find(qn("w:t"))
        if t is None:
            t = ultimo.makeelement(qn("w:t"), {})
            ultimo.append(t)
        t.text = str(pagina)

    doc.save(DOCX)
    return faltando


def rasterizar() -> None:
    destino = SAIDA / "qa"
    destino.mkdir(parents=True, exist_ok=True)
    for antigo in destino.glob("p*.png"):
        antigo.unlink()
    doc = pymupdf.open(PDF)
    for i, pagina in enumerate(doc):
        pagina.get_pixmap(dpi=90).save(destino / f"p{i + 1:02d}.png")
    print(f"{doc.page_count} páginas -> {destino}")
    doc.close()


def main() -> None:
    if not DOCX.is_file():
        raise SystemExit("rode build_docx.py antes")

    print("== 1/3 convertendo para descobrir a paginação")
    converter()
    paginas = paginas_do_pdf()

    print("== 2/3 atualizando o sumário")
    faltando = atualizar_sumario(paginas)
    if faltando:
        print(f"   aviso: {faltando} título(s) sem página no índice do PDF")

    print("== 3/3 gerando o PDF final")
    converter()
    print(f"gerado: {PDF}")

    if "--qa" in sys.argv:
        rasterizar()


if __name__ == "__main__":
    main()
