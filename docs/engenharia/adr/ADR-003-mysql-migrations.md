# ADR-003 — MySQL 8.4 com migrations em PHP e SQL explícito

**Status:** aceito
**Data:** 2026-08

## Contexto

O sistema tem 39 tabelas, relacionamentos fortes (cliente → veículo → OS →
orçamento/pedido) e histórico que não pode ser perdido. O banco precisava ser
reproduzível em qualquer máquina e evolutivo durante o semestre.

## Decisão

- MySQL 8.4 no Docker.
- Migrations versionadas em PHP (`up`/`down`), executadas por `bin/migrate.php`
  e registradas na tabela `migrations`.
- Acesso por PDO com *prepared statements* e `EMULATE_PREPARES = false`.
- **Sem ORM**: o SQL é escrito à mão e legível.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Eloquent/Doctrine | Abstrairia consultas que o grupo precisa entender e explicar |
| SQLite | Não representa o ambiente de produção nem recursos usados (enum, JSON) |
| Schema criado à mão no phpMyAdmin | Não é reproduzível nem versionado |

## Consequências

- Positivas: qualquer máquina sobe o schema com um comando; o histórico de
  mudanças fica no Git; consultas complexas (relatórios, dashboards) são
  explícitas.
- Negativas: mais SQL manual e risco de divergência entre migrations e banco de
  desenvolvimento.
- Mitigação: 32 migrations aplicadas, `bin/migrate.php status` e o dicionário
  gerado a partir do `information_schema`.
