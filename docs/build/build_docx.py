#!/usr/bin/env python3
"""Monta o DOCX oficial dentro do template da FATEC.

- Preserva capa, ficha de controle, cabeçalho, logotipo da FATEC e estilos.
- Todo o texto vem de docs/entrega/*.md.
- Tabelas de RF/RNF, comparativo e cronograma são geradas do Markdown.
- Dicionário de dados vem de docs/engenharia/04-modelo-dados.md.
- O sumário é preenchido em duas passagens (build_docx.py -> export_pdf.py).

Uso:
    python3 docs/build/build_docx.py
"""

from __future__ import annotations

import json
import re
import shutil
from pathlib import Path

from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.opc.constants import RELATIONSHIP_TYPE as RT
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor
from docx.text.paragraph import Paragraph
from PIL import Image

RAIZ = Path(__file__).resolve().parents[2]
DOCS = RAIZ / "docs"
TEMPLATE = DOCS / "_template" / "template-fatec.docx"
SAIDA = DOCS / "saida" / "PI-ES2-2026.docx"
ENTREGA = DOCS / "entrega"
DICIONARIO = DOCS / "engenharia" / "04-modelo-dados.md"
MANIFESTO = DOCS / "saida" / "toc.json"

# Endereço público da documentação (Cloudflare Pages). As figuras do DOCX/PDF
# apontam para a versão interativa no site.
SITE_URL = "https://torque-zenitec.pages.dev"

LARGURA_TEXTO = Cm(15.7)
ALTURA_MAX = Cm(17.8)
FONTE_TABELA = Pt(8.5)
FONTE_DICIONARIO = Pt(7.8)

# Larguras específicas para tabelas do relatório de segurança (e outras que
# fogem do padrão de 2 a 4 colunas). A chave é o cabeçalho em minúsculas.
LARGURAS_POR_CABECALHO = {
    ("id", "severidade", "achado", "situação"): [1.4, 2.1, 7.6, 4.6],
    ("id", "severidade", "achado", "status"): [1.4, 2.1, 7.6, 4.6],
    ("severidade", "quantidade", "destaques"): [2.4, 2.2, 11.1],
    ("ferramenta", "objetivo", "execução"): [3.3, 6.2, 6.2],
    ("componente", "configuração"): [4.2, 11.5],
    ("porta", "estado", "serviço", "observação"): [1.8, 2.0, 4.8, 7.1],
    ("caminho", "sem sessão", "observação"): [5.0, 3.0, 7.7],
    ("controle", "evidência"): [5.0, 10.7],
    ("etapa", "volume", "resultado"): [4.0, 4.0, 7.7],
    ("data", "atividade", "responsável"): [2.6, 9.6, 3.5],
}

# Paleta única do documento (a mesma do sistema: vinho #b8270f).
COR_VINHO = "B8270F"
COR_VINHO_ESCURO = "8E1F0C"
COR_VINHO_CLARO = "F7E4E0"
COR_AREIA = "FBF7F1"
COR_BORDA = "D8D0C6"
COR_TEXTO = "1F2933"
COR_CINZA = "6B7280"
COR_AMBAR = "B7791F"
COR_AMBAR_CLARO = "FDF3E1"
COR_VERDE = "2F6F4E"
COR_VERDE_CLARO = "E8F3EC"
COR_AZUL = "3B5B7A"
COR_AZUL_CLARO = "E8EEF5"
COR_TEAL = "1F6F6B"
COR_TEAL_CLARO = "E6F2F1"

# Tabelas que aparecem no dicionário impresso. O restante (cache FIPE/Canal da
# Peça e tabelas internas do assistente) fica documentado no repositório.
DICIONARIO_INCLUIR = {
    "users", "login_tentativas", "settings", "notifications", "audit_logs",
    "clients", "vehicles", "vehicle_specs",
    "service_orders", "service_order_tasks", "service_order_responsibles",
    "service_order_events", "service_order_deliveries",
    "budgets", "budget_items",
    "parts", "part_categories", "suppliers", "part_orders", "part_order_items",
    "tickets", "ticket_messages", "ticket_reads",
    "agent_proposals",
}

GRUPOS_DICIONARIO = {
    "Acesso, configuração e rastreabilidade",
    "Cadastros e dados veiculares",
    "Operação da oficina",
    "Orçamentos",
    "Peças, fornecedores e pedidos",
    "Catálogo externo (Canal da Peça)",
    "Atendimento e comunicação",
    "Assistente de IA",
}

ARQUIVOS = [
    "01-empresa.md",
    "02-escopo.md",
    "03-sistema.md",
    "04-testes.md",
    "05-consideracoes.md",
    "referencias.md",
    "anexos/01-diario-de-bordo.md",
    "anexos/02-cronograma-efetivo.md",
    "anexos/03-evidencias.md",
    "anexos/04-relatorio-seguranca.md",
]

HEADINGS: list[dict] = []
H1_ATUAL = 0


# --------------------------------------------------------------------------
# utilidades de formatação
# --------------------------------------------------------------------------

def set_texto(paragrafo: Paragraph, texto: str) -> None:
    """Substitui o texto do parágrafo preservando a formatação do primeiro run."""
    if not paragrafo.runs:
        paragrafo.add_run(texto)
        return
    paragrafo.runs[0].text = texto
    for run in paragrafo.runs[1:]:
        run.text = ""


def substituir_conteudo(paragrafo: Paragraph, texto: str) -> None:
    """Remove runs e content controls do parágrafo e grava um texto único.

    A ficha de controle do template usa controles de conteúdo (w:sdt) com
    valores padrão. Mantê-los faria o valor antigo aparecer ao lado do novo.
    """
    for filho in list(paragrafo._p.iterchildren()):
        if filho.tag != qn("w:pPr"):
            paragrafo._p.remove(filho)
    run = paragrafo.add_run(texto)
    run.font.size = Pt(11)


def sombrear(celula, cor: str = "F2F2F2") -> None:
    tcPr = celula._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), cor)
    tcPr.append(shd)


def sem_quebra(linha) -> None:
    trPr = linha._tr.get_or_add_trPr()
    trPr.append(OxmlElement("w:cantSplit"))


def repetir_cabecalho(linha) -> None:
    trPr = linha._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    trPr.append(el)


def ajustar_larguras(tabela, larguras_cm: list[float]) -> None:
    tabela.autofit = False
    for i, largura in enumerate(larguras_cm):
        largura = Cm(largura)
        tabela.columns[i].width = largura
        for celula in tabela.columns[i].cells:
            celula.width = largura


def sombrear_run(run, cor: str, cor_texto: str | None = None) -> None:
    rPr = run._element.get_or_add_rPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), cor)
    rPr.append(shd)
    if cor_texto:
        run.font.color.rgb = RGBColor.from_string(cor_texto)


def bordas_tabela(tabela, cor: str = COR_BORDA) -> None:
    tblPr = tabela._tbl.tblPr
    borders = OxmlElement("w:tblBorders")
    for lado in ("top", "left", "bottom", "right", "insideH", "insideV"):
        el = OxmlElement(f"w:{lado}")
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), "4")
        el.set(qn("w:space"), "0")
        el.set(qn("w:color"), cor)
        borders.append(el)
    tblPr.append(borders)


def margens_celula(tabela, topo: int = 40, esq: int = 70, baixo: int = 40, dir_: int = 70) -> None:
    tblPr = tabela._tbl.tblPr
    mar = OxmlElement("w:tblCellMar")
    for nome, valor in (("top", topo), ("left", esq), ("bottom", baixo), ("right", dir_)):
        el = OxmlElement(f"w:{nome}")
        el.set(qn("w:w"), str(valor))
        el.set(qn("w:type"), "dxa")
        mar.append(el)
    tblPr.append(mar)


def borda_paragrafo(paragrafo: Paragraph, lado: str, cor: str, tamanho: int, espaco: int) -> None:
    pPr = paragrafo._p.get_or_add_pPr()
    pBdr = pPr.find(qn("w:pBdr"))
    if pBdr is None:
        pBdr = OxmlElement("w:pBdr")
        pPr.append(pBdr)
    el = OxmlElement(f"w:{lado}")
    el.set(qn("w:val"), "single")
    el.set(qn("w:sz"), str(tamanho))
    el.set(qn("w:space"), str(espaco))
    el.set(qn("w:color"), cor)
    pBdr.append(el)


def cor_da_prioridade(prioridade: str) -> str:
    return {
        "Essencial": COR_VINHO,
        "Importante": COR_AMBAR,
        "Desejável": COR_VERDE,
    }.get(prioridade, COR_CINZA)


def fonte_celulas(tabela, tamanho: Pt, negrito: bool = False) -> None:
    for linha in tabela.rows:
        for celula in linha.cells:
            for p in celula.paragraphs:
                p.paragraph_format.space_after = Pt(0)
                p.paragraph_format.space_before = Pt(0)
                for run in p.runs:
                    run.font.size = tamanho
                    if negrito:
                        run.font.bold = True


def adicionar_link(
    paragrafo: Paragraph,
    texto: str,
    url: str,
    *,
    size: float | None = None,
    bold: bool = False,
    italic: bool = False,
) -> None:
    """Acrescenta um run clicável (hyperlink externo) ao parágrafo."""
    r_id = paragrafo.part.relate_to(url, RT.HYPERLINK, is_external=True)
    link = OxmlElement("w:hyperlink")
    link.set(qn("r:id"), r_id)

    run = OxmlElement("w:r")
    rPr = OxmlElement("w:rPr")
    if bold:
        rPr.append(OxmlElement("w:b"))
    if italic:
        rPr.append(OxmlElement("w:i"))
    cor = OxmlElement("w:color")
    cor.set(qn("w:val"), COR_VINHO)
    rPr.append(cor)
    sublinhado = OxmlElement("w:u")
    sublinhado.set(qn("w:val"), "single")
    rPr.append(sublinhado)
    if size:
        sz = OxmlElement("w:sz")
        sz.set(qn("w:val"), str(int(size * 2)))
        rPr.append(sz)
    run.append(rPr)

    t = OxmlElement("w:t")
    t.set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
    t.text = texto
    run.append(t)
    link.append(run)
    paragrafo._p.append(link)


def texto_formatado(paragrafo: Paragraph, texto: str) -> None:
    """Interpreta **negrito**, *itálico*, `código` e links markdown/URL."""
    partes = re.split(
        r"(\[[^\]]+\]\([^)]+\)|https?://[^\s)]+|\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)",
        texto,
    )
    for parte in partes:
        if not parte:
            continue
        if parte.startswith("[") and "](" in parte:
            m = re.fullmatch(r"\[([^\]]+)\]\(([^)]+)\)", parte)
            if m:
                adicionar_link(paragrafo, m.group(1), m.group(2))
            else:
                paragrafo.add_run(parte)
        elif re.match(r"https?://", parte):
            url = parte.rstrip(".,;:")
            adicionar_link(paragrafo, url, url)
            if url != parte:
                paragrafo.add_run(parte[len(url):])
        elif parte.startswith("**") and parte.endswith("**"):
            run = paragrafo.add_run(parte[2:-2])
            run.bold = True
        elif parte.startswith("`") and parte.endswith("`"):
            run = paragrafo.add_run(parte[1:-1])
            run.font.name = "Consolas"
            run.font.size = Pt(9)
        elif parte.startswith("*") and parte.endswith("*") and len(parte) > 2:
            run = paragrafo.add_run(parte[1:-1])
            run.italic = True
        else:
            paragrafo.add_run(parte)


def paragrafo(
    doc: Document,
    texto: str,
    *,
    size: float | None = None,
    bold: bool = False,
    italic: bool = False,
    align: WD_ALIGN_PARAGRAPH | None = None,
    space_after: float | None = None,
    keep_next: bool = False,
    style: str | None = None,
) -> Paragraph:
    p = doc.add_paragraph(style=style)
    texto_formatado(p, texto)
    if size:
        for run in p.runs:
            run.font.size = Pt(size)
    if bold:
        for run in p.runs:
            run.bold = True
    if italic:
        for run in p.runs:
            run.italic = True
    if align is not None:
        p.alignment = align
    if space_after is not None:
        p.paragraph_format.space_after = Pt(space_after)
    if keep_next:
        p.paragraph_format.keep_with_next = True
    return p


def titulo(doc: Document, texto: str, nivel: int) -> Paragraph:
    global H1_ATUAL
    p = doc.add_heading(level=nivel)
    run = p.add_run(texto)
    run.bold = True
    if nivel == 1:
        run.font.color.rgb = RGBColor.from_string(COR_VINHO)
        borda_paragrafo(p, "bottom", COR_VINHO, 8, 4)
    elif nivel == 2:
        run.font.color.rgb = RGBColor.from_string(COR_TEXTO)
        borda_paragrafo(p, "left", COR_VINHO, 18, 8)
        p.paragraph_format.left_indent = Cm(0.25)
    else:
        run.font.color.rgb = RGBColor.from_string(COR_VINHO_ESCURO)
    if nivel == 1:
        H1_ATUAL += 1
        p.paragraph_format.page_break_before = True
        if texto.strip().lower().startswith("anexo"):
            pPr = p._p.get_or_add_pPr()
            numPr = pPr.get_or_add_numPr()
            numPr.get_or_add_ilvl().val = 0
            numPr.get_or_add_numId().val = 0
        HEADINGS.append({"nivel": 1, "texto": texto, "numero": H1_ATUAL})
    else:
        HEADINGS.append({"nivel": nivel, "texto": texto, "numero": None})
    return p


def url_da_figura(caminho: Path) -> str:
    """Aponta a figura para o diagrama interativo ou para o documento online."""
    if caminho.parent.name == "png" and "diagramas" in caminho.parts:
        return f"{SITE_URL}/#/diagramas/{caminho.stem}"
    return f"{SITE_URL}/#/documento"


def adicionar_figura(doc: Document, caminho: Path, legenda: str) -> None:
    if not caminho.is_file():
        raise FileNotFoundError(f"imagem não encontrada: {caminho}")
    with Image.open(caminho) as im:
        largura_px, altura_px = im.size
    aspecto = largura_px / altura_px
    largura_max = LARGURA_TEXTO.cm
    altura_max = ALTURA_MAX.cm
    largura_cm = largura_max
    if largura_cm / aspecto > altura_max:
        altura_cm = altura_max
        largura_cm = altura_cm * aspecto
    url = url_da_figura(caminho)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(2)
    p.paragraph_format.keep_with_next = True
    run = p.add_run()
    run.add_picture(str(caminho), width=Cm(largura_cm))
    # A própria imagem vira um link para o site.
    r_id = p.part.relate_to(url, RT.HYPERLINK, is_external=True)
    link = OxmlElement("w:hyperlink")
    link.set(qn("r:id"), r_id)
    run._r.addprevious(link)
    link.append(run._r)

    legenda_p = doc.add_paragraph()
    legenda_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    legenda_p.paragraph_format.space_after = Pt(10)
    # A legenda também é clicável e leva para a versão online.
    adicionar_link(legenda_p, legenda, url, size=10, italic=True)


def adicionar_tabela(
    doc: Document,
    linhas: list[list[str]],
    *,
    legenda: str | None = None,
    fonte: str | None = None,
    tamanho: float = 8.5,
    larguras: list[float] | None = None,
    cabecalho: bool = True,
    suave: bool = False,
    destacar_coluna: int | None = None,
    manter_junto: bool = True,
) -> None:
    if not linhas:
        return

    if legenda:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.keep_with_next = True
        p.paragraph_format.space_before = Pt(8)
        p.paragraph_format.space_after = Pt(4)
        # "Tabela 1" em vinho; o resto do título em cinza-escuro.
        partes = legenda.split("–", 1)
        run = p.add_run(partes[0].strip() + (" –" if len(partes) > 1 else ""))
        run.bold = True
        run.font.size = Pt(9.5)
        run.font.color.rgb = RGBColor.from_string(COR_VINHO)
        if len(partes) > 1:
            run = p.add_run(partes[1])
            run.bold = True
            run.font.size = Pt(9.5)
            run.font.color.rgb = RGBColor.from_string(COR_TEXTO)

    colunas = max(len(l) for l in linhas)
    tabela = doc.add_table(rows=len(linhas), cols=colunas)
    tabela.style = doc.styles["Table Grid"]
    tabela.alignment = WD_TABLE_ALIGNMENT.CENTER

    for i, linha in enumerate(linhas):
        for j in range(colunas):
            celula = tabela.cell(i, j)
            celula.text = ""
            p = celula.paragraphs[0]
            valor = linha[j] if j < len(linha) else ""
            p.add_run(valor)
            if i == 0 and cabecalho:
                for run in p.runs:
                    run.bold = True
                    run.font.color.rgb = RGBColor.from_string(
                        COR_VINHO_ESCURO if suave else "FFFFFF"
                    )
                sombrear(celula, COR_VINHO_CLARO if suave else COR_VINHO)
            else:
                if i % 2 == 0:
                    sombrear(celula, COR_AREIA)
                if destacar_coluna is not None and j == destacar_coluna:
                    sombrear(celula, COR_VINHO_CLARO)
        sem_quebra(tabela.rows[i])

    if cabecalho:
        repetir_cabecalho(tabela.rows[0])

    if larguras:
        ajustar_larguras(tabela, larguras)
    fonte_celulas(tabela, Pt(tamanho))
    bordas_tabela(tabela)
    if suave:
        margens_celula(tabela, topo=20, esq=60, baixo=20, dir_=60)
    else:
        margens_celula(tabela)

    try:
        from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT
        for linha in tabela.rows:
            for celula in linha.cells:
                celula.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    except Exception:
        pass

    # Tabelas curtas não devem quebrar entre páginas.
    if manter_junto and len(linhas) <= 14:
        for linha in tabela.rows[:-1]:
            for celula in linha.cells:
                for p in celula.paragraphs:
                    p.paragraph_format.keep_with_next = True

    if fonte:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(3)
        p.paragraph_format.space_after = Pt(9)
        run = p.add_run(fonte)
        run.italic = True
        run.font.size = Pt(8)
        run.font.color.rgb = RGBColor.from_string(COR_CINZA)


def adicionar_bullets(doc: Document, itens: list[str]) -> None:
    for item in itens:
        p = doc.add_paragraph()
        # Sem recuo pendente: o LibreOffice inverte o w:hanging na conversão
        # DOCX -> PDF e o texto quebrava fora da margem.
        p.paragraph_format.left_indent = Cm(0)
        p.paragraph_format.first_line_indent = Cm(0)
        p.paragraph_format.space_after = Pt(2)
        p.add_run("• ")
        texto_formatado(p, item)


def adicionar_tabela_requisitos(
    doc: Document, itens: list[tuple[str, str, str, str]], tipo: str
) -> None:
    """Uma tabela consolidada por seção, com prioridade em etiqueta colorida."""
    if not itens:
        return

    cabecalho = ["ID", "Requisito", "Prioridade", "Enunciado"]
    linhas = [cabecalho] + [[i, n, p, d] for (i, n, p, d) in itens]
    tabela = doc.add_table(rows=len(linhas), cols=4)
    tabela.style = doc.styles["Table Grid"]
    tabela.alignment = WD_TABLE_ALIGNMENT.CENTER

    for j, valor in enumerate(cabecalho):
        celula = tabela.cell(0, j)
        celula.text = ""
        run = celula.paragraphs[0].add_run(valor)
        run.bold = True
        run.font.size = Pt(8.5)
        run.font.color.rgb = RGBColor.from_string("FFFFFF")
        if j == 2:
            celula.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
        sombrear(celula, COR_VINHO)

    for i, (ident, nome, prioridade, descricao) in enumerate(itens, start=1):
        # ID
        celula = tabela.cell(i, 0)
        celula.text = ""
        p = celula.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = p.add_run(ident)
        run.bold = True
        run.font.size = Pt(8.5)
        run.font.color.rgb = RGBColor.from_string(COR_VINHO_ESCURO)
        sombrear(celula, COR_VINHO_CLARO)

        # Requisito
        celula = tabela.cell(i, 1)
        celula.text = ""
        texto_formatado(celula.paragraphs[0], nome)
        for run in celula.paragraphs[0].runs:
            run.bold = True
            run.font.size = Pt(8.8)

        # Prioridade (etiqueta)
        celula = tabela.cell(i, 2)
        celula.text = ""
        p = celula.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = p.add_run(f" {prioridade} ")
        run.bold = True
        run.font.size = Pt(7.8)
        sombrear_run(run, cor_da_prioridade(prioridade), "FFFFFF")

        # Enunciado
        celula = tabela.cell(i, 3)
        celula.text = ""
        p = celula.paragraphs[0]
        texto_formatado(p, descricao)
        for run in p.runs:
            run.font.size = Pt(9.2)

        if i % 2 == 0:
            for j in (1, 3):
                sombrear(tabela.cell(i, j), COR_AREIA)

    for linha in tabela.rows:
        sem_quebra(linha)
    ajustar_larguras(tabela, [1.4, 4.2, 2.4, 7.7])
    fonte_celulas(tabela, Pt(8.8))
    bordas_tabela(tabela)
    margens_celula(tabela, topo=50, baixo=50)
    repetir_cabecalho(tabela.rows[0])

    try:
        from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT
        for linha in tabela.rows:
            for celula in linha.cells:
                celula.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    except Exception:
        pass

    doc.add_paragraph().paragraph_format.space_after = Pt(2)


# --------------------------------------------------------------------------
# dicionário de dados
# --------------------------------------------------------------------------

def renderizar_dicionario(doc: Document) -> None:
    linhas = DICIONARIO.read_text(encoding="utf-8").splitlines()
    grupos = _grupos_do_dicionario(linhas)
    tabela_atual: str | None = None
    i = 0
    while i < len(linhas):
        linha = linhas[i].rstrip()
        if linha.startswith("## "):
            nome = linha[3:].strip()
            tem_tabela = any(t in DICIONARIO_INCLUIR for t in grupos.get(nome, []))
            if nome in GRUPOS_DICIONARIO and tem_tabela:
                p = doc.add_paragraph()
                p.paragraph_format.space_before = Pt(10)
                p.paragraph_format.space_after = Pt(4)
                p.paragraph_format.keep_with_next = True
                run = p.add_run(nome)
                run.bold = True
                run.font.size = Pt(11)
            tabela_atual = None
            i += 1
            continue

        if linha.startswith("### `"):
            nome = linha[5:].strip("` \t")
            tabela_atual = nome if nome in DICIONARIO_INCLUIR else None
            if tabela_atual:
                p = doc.add_paragraph()
                p.paragraph_format.space_before = Pt(8)
                p.paragraph_format.space_after = Pt(2)
                p.paragraph_format.keep_with_next = True
                run = p.add_run(nome)
                run.bold = True
                run.font.name = "Consolas"
                run.font.size = Pt(9.5)
            i += 1
            continue

        if linha.startswith("Registros na base"):
            i += 1
            continue

        if linha.startswith("|") and tabela_atual:
            bloco = []
            while i < len(linhas) and linhas[i].startswith("|"):
                bloco.append(linhas[i])
                i += 1
            dados = _parse_tabela(bloco)
            if dados and dados[0][0].strip("` ") == "Coluna":
                adicionar_tabela(
                    doc,
                    dados,
                    tamanho=7.6,
                    larguras=[4.4, 4.0, 1.4, 1.3, 4.6],
                    suave=True,
                    manter_junto=False,
                )
            continue

        if linha.startswith("- `") and tabela_atual:
            itens = []
            while i < len(linhas) and linhas[i].strip().startswith("- "):
                itens.append(linhas[i].strip()[2:])
                i += 1
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Cm(0.3)
            p.paragraph_format.space_after = Pt(2)
            run = p.add_run("Chaves estrangeiras: ")
            run.bold = True
            run.font.size = Pt(8.5)
            for item in itens:
                run = p.add_run(item.replace("`", "") + "  ")
                run.font.size = Pt(8.5)
            continue

        i += 1


def _grupos_do_dicionario(linhas: list[str]) -> dict[str, list[str]]:
    grupos: dict[str, list[str]] = {}
    atual = ""
    for linha in linhas:
        if linha.startswith("## "):
            atual = linha[3:].strip()
            grupos.setdefault(atual, [])
        elif linha.startswith("### `"):
            grupos.setdefault(atual, []).append(linha[5:].strip("` \t"))
    return grupos


def _parse_tabela(bloco: list[str]) -> list[list[str]]:
    dados = []
    for linha in bloco:
        if re.match(r"^\|[-:\s|]+\|$", linha):
            continue
        celulas = [c.strip() for c in linha.strip().strip("|").split("|")]
        dados.append([c.replace("`", "") for c in celulas])
    return dados


# --------------------------------------------------------------------------
# leitura do markdown
# --------------------------------------------------------------------------

def ler_identificacao() -> tuple[dict[str, str], list[tuple[str, str]], list[str]]:
    texto = (ENTREGA / "00-identificacao.md").read_text(encoding="utf-8")
    info: dict[str, str] = {}
    integrantes: list[tuple[str, str]] = []
    notas: list[str] = []
    secao = ""
    for linha in texto.splitlines():
        if linha.startswith("## "):
            secao = linha[3:].strip().lower()
            continue
        if ":" in linha and not linha.startswith("|") and not linha.startswith("#"):
            chave, valor = linha.split(":", 1)
            if chave.strip() and not chave.startswith("-"):
                info[chave.strip()] = valor.strip()
        if linha.startswith("|") and "---" not in linha:
            partes = [p.strip() for p in linha.strip().strip("|").split("|")]
            if len(partes) >= 2 and partes[0] not in ("Nome", "Integrante"):
                if secao.startswith("integrantes"):
                    integrantes.append((partes[0], partes[1]))
                elif secao.startswith("notas"):
                    notas.append(partes[0])
    return info, integrantes, notas


def renderizar_arquivo(doc: Document, caminho: Path, *, esquerda: bool = False) -> None:
    linhas = caminho.read_text(encoding="utf-8").splitlines()
    i = 0
    legenda_tabela: str | None = None
    requisitos: list[tuple[str, str, str, str]] = []
    tipo_req = ""

    def flush_requisitos() -> None:
        nonlocal requisitos
        if requisitos:
            adicionar_tabela_requisitos(doc, requisitos, tipo_req)
            requisitos = []

    while i < len(linhas):
        linha = linhas[i].strip()

        if not linha.strip():
            i += 1
            continue

        if linha.strip() == "{{DICIONARIO}}":
            flush_requisitos()
            renderizar_dicionario(doc)
            i += 1
            continue

        if linha.startswith("# "):
            flush_requisitos()
            titulo(doc, linha[2:].strip(), 1)
            i += 1
            continue

        if linha.startswith("## "):
            flush_requisitos()
            titulo(doc, linha[3:].strip(), 2)
            i += 1
            continue

        if linha.startswith("### "):
            conteudo = linha[4:].strip()
            m = re.match(r"(R(?:F|NF)\d{2})\s+—\s+(.+?)\s+—\s+(Essencial|Importante|Desejável)$", conteudo)
            if m:
                j = i + 1
                while j < len(linhas) and not linhas[j].strip():
                    j += 1
                descricao = linhas[j].strip() if j < len(linhas) else ""
                tipo = "Funcionais" if m.group(1).startswith("RF") else "Não funcionais"
                if requisitos and tipo != tipo_req:
                    flush_requisitos()
                tipo_req = tipo
                requisitos.append((m.group(1), m.group(2), m.group(3), descricao))
                i = j + 1
                continue
            titulo(doc, conteudo, 3)
            i += 1
            continue

        m = re.match(r"!\[(.*?)\]\((.*?)\)", linha.strip())
        if m:
            legenda, caminho_img = m.group(1), m.group(2)
            adicionar_figura(doc, (DOCS / caminho_img).resolve(), legenda)
            i += 1
            continue

        if linha.startswith("|"):
            bloco = []
            while i < len(linhas) and linhas[i].startswith("|"):
                bloco.append(linhas[i])
                i += 1
            dados = _parse_tabela(bloco)
            # tira o cabeçalho duplicado de separadores já removido
            if dados:
                qtd_colunas = len(dados[0])
                cabecalho = [c.lower() for c in dados[0]]
                larguras = LARGURAS_POR_CABECALHO.get(tuple(cabecalho))
                if larguras is None:
                    if qtd_colunas == 9:
                        larguras = [3.2] + [1.56] * 8
                    elif qtd_colunas == 4:
                        if cabecalho[0] == "etapa":
                            larguras = [5.0, 3.9, 3.9, 2.9]
                        else:
                            larguras = [7.4, 2.7, 2.7, 2.9]
                    elif qtd_colunas == 3:
                        if cabecalho[0] == "data":
                            larguras = [2.6, 9.6, 3.5]
                        elif cabecalho[0] == "item":
                            larguras = [5.6, 5.0, 5.1]
                        else:
                            larguras = [7.4, 4.6, 3.7]
                    elif qtd_colunas == 2:
                        larguras = [7.8, 7.9]
                destaque = 3 if cabecalho[0].startswith("funcionalidade") else None
                adicionar_tabela(
                    doc, dados, legenda=legenda_tabela, larguras=larguras,
                    tamanho=8.0 if qtd_colunas >= 9 else 8.5,
                    destacar_coluna=destaque,
                )
                legenda_tabela = None
            continue

        if linha.startswith("- "):
            itens = []
            while i < len(linhas) and linhas[i].strip().startswith("- "):
                itens.append(linhas[i].strip()[2:].strip())
                i += 1
            adicionar_bullets(doc, itens)
            continue

        if re.match(r"^\d+\.\s", linha):
            itens = []
            while i < len(linhas) and re.match(r"^\d+\.\s", linhas[i].strip()):
                itens.append(linhas[i].strip())
                i += 1
            for item in itens:
                p = doc.add_paragraph()
                p.paragraph_format.left_indent = Cm(0)
                p.paragraph_format.first_line_indent = Cm(0)
                p.paragraph_format.space_after = Pt(2)
                # "1) " evita que o LibreOffice converta o texto em lista
                # automática e aplique um recuo só na primeira linha.
                item = re.sub(r"^(\d+)\.\s+", r"\1) ", item)
                texto_formatado(p, item)
            continue

        if linha.startswith("Tabela "):
            legenda_tabela = linha.strip()
            i += 1
            continue

        if linha.startswith("Fonte:"):
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.space_after = Pt(9)
            run = p.add_run(linha.strip())
            run.italic = True
            run.font.size = Pt(8)
            run.font.color.rgb = RGBColor.from_string(COR_CINZA)
            i += 1
            continue

        if linha.startswith("```"):
            i += 1
            continue

        # parágrafo comum (uma linha por parágrafo)
        somente_negrito = bool(re.fullmatch(r"\*\*[^*]+\*\*", linha.strip()))
        p = paragrafo(doc, linha.strip(), keep_next=somente_negrito, space_after=4 if somente_negrito else None)
        if esquerda:
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        i += 1

    flush_requisitos()


# --------------------------------------------------------------------------
# capa, ficha e sumário
# --------------------------------------------------------------------------

def substituir_partes(
    paragrafo: Paragraph, partes: list[tuple[str, str, bool]], size: float = 10
) -> None:
    for filho in list(paragrafo._p.iterchildren()):
        if filho.tag != qn("w:pPr"):
            paragrafo._p.remove(filho)
    for texto, cor, negrito in partes:
        run = paragrafo.add_run(texto)
        run.bold = negrito
        run.font.size = Pt(size)
        run.font.color.rgb = RGBColor.from_string(cor)


def estilizar_tabela_simples(tabela) -> None:
    for celula in tabela.rows[0].cells:
        sombrear(celula, COR_VINHO_CLARO)
        for p in celula.paragraphs:
            for run in p.runs:
                run.bold = True
                run.font.color.rgb = RGBColor.from_string(COR_VINHO_ESCURO)
    bordas_tabela(tabela)
    margens_celula(tabela)


def preencher_capa(doc: Document, info: dict[str, str], integrantes: list[tuple[str, str]]) -> None:
    for p in doc.paragraphs:
        t = p.text.strip()
        if t.startswith("Grupo(n)"):
            substituir_partes(
                p,
                [("Grupo: ", COR_CINZA, True), (info.get("grupo", ""), COR_VINHO, True)],
                11,
            )
        elif t.startswith("Sistema:"):
            substituir_partes(
                p,
                [("Sistema: ", COR_CINZA, True), (info.get("sistema", ""), COR_VINHO, True)],
                11,
            )

    tabela = doc.tables[0]
    for i, (nome, papel) in enumerate(integrantes, start=1):
        if i < len(tabela.rows):
            tabela.cell(i, 0).text = nome
            tabela.cell(i, 1).text = papel
    for linha in list(tabela.rows)[len(integrantes) + 1:]:
        linha._tr.getparent().remove(linha._tr)
    estilizar_tabela_simples(tabela)

    for p in doc.paragraphs:
        t = p.text
        if t.startswith("DISCIPLINA CHAVE:"):
            substituir_partes(
                p,
                [("DISCIPLINA CHAVE: ", COR_CINZA, True), (info.get("disciplina", ""), COR_TEXTO, True)],
            )
        elif t.startswith("PROFESSOR:"):
            substituir_partes(
                p,
                [("PROFESSOR: ", COR_CINZA, True), (info.get("professor", ""), COR_TEXTO, True)],
            )
        elif t.startswith("GRUPO:"):
            substituir_partes(
                p,
                [
                    ("GRUPO: ", COR_CINZA, True),
                    (info.get("grupo", ""), COR_TEXTO, True),
                    ("      SEMESTRE: ", COR_CINZA, True),
                    (info.get("semestre", ""), COR_TEXTO, True),
                ],
            )
        elif t.startswith("TÍTULO DO PROJETO:"):
            substituir_partes(
                p,
                [("TÍTULO DO PROJETO: ", COR_CINZA, True), (info.get("titulo", ""), COR_TEXTO, True)],
            )
        elif t.startswith("DATA DA APRESENTAÇÃO:"):
            substituir_partes(
                p,
                [("DATA DA APRESENTAÇÃO: ", COR_CINZA, True), (info.get("data", ""), COR_TEXTO, True)],
            )
        elif t.startswith("INTEGRANTES DO GRUPO:"):
            substituir_partes(p, [("INTEGRANTES DO GRUPO:", COR_VINHO, True)])
        elif t.strip() == "Araras,":
            substituir_conteudo(p, "Araras, 24 de novembro de 2026.")

    if len(doc.tables) > 1:
        notas = doc.tables[1]
        for i, (nome, _) in enumerate(integrantes, start=1):
            if i < len(notas.rows):
                notas.cell(i, 0).text = nome
        for linha in list(notas.rows)[len(integrantes) + 1:]:
            linha._tr.getparent().remove(linha._tr)
        estilizar_tabela_simples(notas)


def remover_corpo(doc: Document) -> None:
    body = doc.element.body
    filhos = list(body.iterchildren())
    inicio = None
    for i, filho in enumerate(filhos):
        if filho.tag == qn("w:p"):
            p = Paragraph(filho, doc)
            if p.style.name == "Heading 1" and p.text.strip().startswith("Apresenta"):
                inicio = i
                break
    if inicio is None:
        raise RuntimeError("não encontrei o início do corpo no template")
    for filho in filhos[inicio:]:
        if filho.tag != qn("w:sectPr"):
            body.remove(filho)


def montar_sumario(doc: Document) -> None:
    body = doc.element.body
    sdt = None
    for el in body.iterchildren():
        if el.tag == qn("w:sdt"):
            galeria = el.find(".//" + qn("w:docPartGallery"))
            if galeria is not None and galeria.get(qn("w:val")) == "Table of Contents":
                sdt = el
                break
    if sdt is None:
        return
    conteudo = sdt.find(qn("w:sdtContent"))
    if conteudo is None:
        return
    paragrafos = list(conteudo.iterchildren(qn("w:p")))
    # mantém o título "Sumário"
    for p in paragrafos[1:]:
        conteudo.remove(p)

    estilos = {s.style_id for s in doc.styles if s.style_id}
    for heading in HEADINGS:
        nivel = heading["nivel"]
        estilo = f"Sumrio{min(nivel, 3)}"
        if estilo not in estilos:
            estilo = "Sumrio2"
        p = OxmlElement("w:p")
        pPr = OxmlElement("w:pPr")
        pStyle = OxmlElement("w:pStyle")
        pStyle.set(qn("w:val"), estilo)
        pPr.append(pStyle)
        tabs = OxmlElement("w:tabs")
        tab = OxmlElement("w:tab")
        tab.set(qn("w:val"), "right")
        tab.set(qn("w:leader"), "dot")
        tab.set(qn("w:pos"), "9019")
        tabs.append(tab)
        pPr.append(tabs)
        p.append(pPr)

        texto = heading["texto"]
        if nivel == 1 and not texto.lower().startswith("anexo"):
            texto = f"{heading['numero']}. {texto}"

        r1 = OxmlElement("w:r")
        t1 = OxmlElement("w:t")
        t1.set(qn("xml:space"), "preserve")
        t1.text = texto
        r1.append(t1)
        p.append(r1)

        r2 = OxmlElement("w:r")
        r2.append(OxmlElement("w:tab"))
        p.append(r2)

        r3 = OxmlElement("w:r")
        t3 = OxmlElement("w:t")
        t3.text = "00"
        r3.append(t3)
        p.append(r3)

        conteudo.append(p)


def adicionar_rodape(doc: Document) -> None:
    secao = doc.sections[0]
    rodape = secao.footer
    rodape.is_linked_to_previous = False
    p = rodape.paragraphs[0] if rodape.paragraphs else rodape.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(6)

    def campo(instr: str) -> None:
        fld = OxmlElement("w:fldSimple")
        fld.set(qn("w:instr"), instr)
        run = OxmlElement("w:r")
        t = OxmlElement("w:t")
        t.text = "1"
        run.append(t)
        fld.append(run)
        p._p.append(fld)

    r = p.add_run("Página ")
    r.font.size = Pt(9)
    campo(" PAGE ")
    r = p.add_run(" de ")
    r.font.size = Pt(9)
    campo(" NUMPAGES ")
    for run in p.runs:
        run.font.size = Pt(9)


def main() -> None:
    if not TEMPLATE.is_file():
        raise SystemExit(f"template não encontrado: {TEMPLATE}")

    info, integrantes, _ = ler_identificacao()
    doc = Document(TEMPLATE)

    props = doc.core_properties
    props.title = info.get("titulo", "Torque — Sistema de Gestão")
    props.subject = "Projeto Integrador — DSM — Fatec Araras"
    props.author = info.get("grupo", "Team Zenitec")
    props.comments = "Documento gerado a partir de docs/entrega/."

    preencher_capa(doc, info, integrantes)
    remover_corpo(doc)

    for arquivo in ARQUIVOS:
        caminho = ENTREGA / arquivo
        if not caminho.is_file():
            raise SystemExit(f"arquivo de entrega não encontrado: {caminho}")
        renderizar_arquivo(doc, caminho, esquerda=(arquivo == "referencias.md"))

    montar_sumario(doc)
    adicionar_rodape(doc)

    SAIDA.parent.mkdir(parents=True, exist_ok=True)
    doc.save(SAIDA)
    MANIFESTO.write_text(
        json.dumps(HEADINGS, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print(f"gerado: {SAIDA}")
    print(f"títulos: {len(HEADINGS)}")


if __name__ == "__main__":
    main()
