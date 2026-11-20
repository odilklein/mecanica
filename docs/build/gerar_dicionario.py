#!/usr/bin/env python3
"""Gera o dicionário de dados a partir do banco MySQL em execução.

Uso:
    python3 docs/build/gerar_dicionario.py

Requisito: container `mecanica-db` em execução (docker compose up -d).
Saída: docs/engenharia/04-modelo-dados.md
"""

from __future__ import annotations

import subprocess
from collections import OrderedDict
from pathlib import Path

RAIZ = Path(__file__).resolve().parents[2]
SAIDA = RAIZ / "docs" / "engenharia" / "04-modelo-dados.md"
DB = "mecanica"
CONTAINER = "mecanica-db"

GRUPOS = [
    ("Acesso, configuração e rastreabilidade", [
        "users", "login_tentativas", "settings", "notifications",
        "audit_logs", "migrations",
    ]),
    ("Cadastros e dados veiculares", [
        "clients", "vehicles", "fipe_brands", "fipe_models", "fipe_years",
        "fipe_prices", "vehicle_specs",
    ]),
    ("Operação da oficina", [
        "service_orders", "service_order_tasks", "service_order_responsibles",
        "service_order_events", "service_order_deliveries",
    ]),
    ("Orçamentos", ["budgets", "budget_items"]),
    ("Peças, fornecedores e pedidos", [
        "parts", "part_categories", "suppliers", "part_stock_movements",
        "part_orders", "part_order_items",
    ]),
    ("Catálogo externo (Canal da Peça)", [
        "cdp_products", "cdp_offers", "cdp_vehicle_makes",
        "cdp_vehicle_models", "cdp_vehicle_years",
    ]),
    ("Atendimento e comunicação", ["tickets", "ticket_messages", "ticket_reads"]),
    ("Assistente de IA", [
        "agent_sessions", "agent_messages", "agent_attachments",
        "agent_proposals", "agent_usage",
    ]),
]

LEGADAS = [
    "clientes", "usuarios", "veiculos", "ordens_servico", "os_eventos",
    "os_responsaveis", "os_tarefas", "pedidos_pecas",
    "vw_ordens_servico_detalhada",
]

DESCRICOES = {
    "users": "Funcionários e usuários do sistema, com perfil de acesso (`admin`, `secretary`, `lead_mechanic`, `mechanic`).",
    "login_tentativas": "Falhas de login por e-mail e IP, usadas pelo bloqueio temporário.",
    "settings": "Configuração chave-valor da oficina (nome, contato, tema, textos de orçamento e SEO).",
    "notifications": "Notificações endereçadas a um usuário, com marcação de leitura.",
    "audit_logs": "Trilha de auditoria das ações sensíveis, com usuário, entidade, ação e dados em JSON.",
    "migrations": "Controle das migrations aplicadas e do lote de execução.",
    "clients": "Clientes da oficina, com CPF, contato, endereço e exclusão lógica.",
    "vehicles": "Veículos vinculados a um cliente, com placa, dados FIPE, KM e chassi.",
    "fipe_brands": "Cache de marcas da tabela FIPE.",
    "fipe_models": "Cache de modelos da tabela FIPE, por marca.",
    "fipe_years": "Cache de anos/versões de cada modelo FIPE.",
    "fipe_prices": "Cache de preços FIPE por modelo, ano e combustível.",
    "vehicle_specs": "Especificações técnicas por marca/modelo/ano (óleo, filtros, pneus), usadas pelo assistente.",
    "service_orders": "Ordens de serviço: cliente, veículo, status, prioridade, queixa, diagnóstico e líder técnico.",
    "service_order_tasks": "Tarefas de cada OS, com responsável, prazo e status.",
    "service_order_responsibles": "Equipe alocada em cada OS e data de conclusão individual.",
    "service_order_events": "Histórico cronológico de eventos e mudanças de status da OS.",
    "service_order_deliveries": "Registro de entrega ou retirada do veículo, com modalidade, responsável e KM de saída.",
    "budgets": "Orçamentos vinculados a cliente, veículo e, opcionalmente, a uma OS.",
    "budget_items": "Itens do orçamento, do tipo peça ou mão de obra, com quantidade, preço e subtotal.",
    "parts": "Catálogo de peças, com preço, custo, estoque, estoque mínimo e dados do catálogo externo.",
    "part_categories": "Categorias de peças, com ícone e cor.",
    "suppliers": "Fornecedores de peças.",
    "part_stock_movements": "Movimentações de estoque (estrutura criada; ainda sem uso pela interface).",
    "part_orders": "Pedidos de peças vinculados a uma OS, com fornecedor, previsão e status.",
    "part_order_items": "Itens de cada pedido de peças, com SKU, quantidade e preço.",
    "cdp_products": "Cache de produtos do catálogo Canal da Peça.",
    "cdp_offers": "Ofertas de vendedores por produto do Canal da Peça, com preço e estoque.",
    "cdp_vehicle_makes": "Cache de marcas de veículo do Canal da Peça.",
    "cdp_vehicle_models": "Cache de modelos do Canal da Peça.",
    "cdp_vehicle_years": "Cache de anos compatíveis do Canal da Peça.",
    "tickets": "Tickets de conversa interna, geralmente vinculados a uma OS.",
    "ticket_messages": "Mensagens dos tickets, incluindo mensagens de sistema.",
    "ticket_reads": "Última leitura de cada ticket por usuário.",
    "agent_sessions": "Sessões de conversa do assistente de IA.",
    "agent_messages": "Mensagens do assistente, com papel, ferramentas chamadas e consumo de tokens.",
    "agent_attachments": "Anexos enviados ao assistente (imagem, PDF, texto ou áudio).",
    "agent_proposals": "Propostas de alteração geradas pelo assistente, sujeitas a aprovação humana.",
    "agent_usage": "Consumo diário de tokens por usuário.",
}


def mysql(sql: str) -> list[list[str]]:
    cmd = [
        "docker", "exec", CONTAINER, "sh", "-c",
        'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -B -e ' + shquote(sql),
    ]
    out = subprocess.run(cmd, capture_output=True, text=True, check=True).stdout
    return [line.split("\t") for line in out.splitlines() if line]


def shquote(s: str) -> str:
    return "'" + s.replace("'", "'\\''") + "'"


def main() -> None:
    colunas = OrderedDict()
    for row in mysql(
        "SELECT table_name, column_name, column_type, is_nullable, column_key, "
        "extra, IFNULL(column_default,'') FROM information_schema.columns "
        f"WHERE table_schema='{DB}' ORDER BY table_name, ordinal_position"
    ):
        colunas.setdefault(row[0], []).append(row)

    fks = OrderedDict()
    for row in mysql(
        "SELECT table_name, column_name, referenced_table_name, "
        "referenced_column_name, constraint_name FROM information_schema.key_column_usage "
        f"WHERE table_schema='{DB}' AND referenced_table_name IS NOT NULL "
        "ORDER BY table_name, ordinal_position"
    ):
        fks.setdefault(row[0], []).append(row)

    contagens = {}
    for row in mysql(
        "SELECT table_name, table_rows FROM information_schema.tables "
        f"WHERE table_schema='{DB}'"
    ):
        contagens[row[0]] = row[1]

    linhas: list[str] = []
    add = linhas.append

    add("# Modelo de dados")
    add("")
    add("Dicionário gerado automaticamente do schema do banco `mecanica` (MySQL 8.4).")
    add("Para regenerar: `python3 docs/build/gerar_dicionario.py`.")
    add("")
    add("## Visão geral")
    add("")
    add("| Grupo | Tabelas |")
    add("|---|---|")
    for nome, tabelas in GRUPOS:
        add(f"| {nome} | {len(tabelas)} |")
    add(f"| **Total da aplicação** | **{sum(len(t) for _, t in GRUPOS)}** |")
    add("")
    add("O banco tem ainda 9 tabelas remanescentes do protótipo procedural, mantidas")
    add("apenas como histórico e **não utilizadas** pela aplicação atual. Elas estão")
    add("listadas no fim deste documento.")
    add("")
    add("## Domínios")
    add("")
    add("O modelo se organiza em quatro domínios, ligados pela OS:")
    add("")
    add("1. **Cadastros** — `clients` e `vehicles` são a base do atendimento.")
    add("2. **Operação** — `service_orders` concentra o serviço; tarefas, responsáveis,")
    add("   eventos e entrega pendem dela.")
    add("3. **Comercial** — `budgets` e `part_orders` documentam o que foi orçado e o")
    add("   que precisou ser comprado.")
    add("4. **Apoio** — usuários, auditoria, notificações, catálogo externo e assistente.")
    add("")

    for nome, tabelas in GRUPOS:
        add(f"## {nome}")
        add("")
        for tabela in tabelas:
            if tabela not in colunas:
                add(f"> Tabela `{tabela}` não encontrada no banco.")
                add("")
                continue
            add(f"### `{tabela}`")
            add("")
            desc = DESCRICOES.get(tabela, "")
            if desc:
                add(desc)
                add("")
            qtd = contagens.get(tabela)
            if qtd not in (None, ""):
                add(f"Registros na base de demonstração: **{qtd}**.")
                add("")
            add("| Coluna | Tipo | Nulo | Chave | Extra | Padrão |")
            add("|---|---|---|---|---|---|")
            for c in colunas[tabela]:
                _, col, tipo, nulo, chave, extra, padrao = c
                padrao = padrao.replace("|", "\\|")
                add(f"| `{col}` | `{tipo}` | {nulo} | {chave or ''} | {extra or ''} | {padrao} |")
            add("")
            if tabela in fks:
                add("**Chaves estrangeiras**")
                add("")
                for _, col, ref_t, ref_c, nome_fk in fks[tabela]:
                    add(f"- `{col}` → `{ref_t}.{ref_c}` (`{nome_fk}`)")
                add("")

    add("## Tabelas legadas (fora de uso)")
    add("")
    add("| Tabela | Situação |")
    add("|---|---|")
    for t in LEGADAS:
        add(f"| `{t}` | Remanescente do protótipo procedural; não é lida pela aplicação. |")
    add("")

    SAIDA.parent.mkdir(parents=True, exist_ok=True)
    SAIDA.write_text("\n".join(linhas), encoding="utf-8")
    print(f"gerado: {SAIDA} ({len(linhas)} linhas)")


if __name__ == "__main__":
    main()
