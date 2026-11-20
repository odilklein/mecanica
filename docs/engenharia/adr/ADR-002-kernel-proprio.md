# ADR-002 — Núcleo próprio com container, router, sessão e CRUD genérico

**Status:** aceito
**Data:** 2026-08

## Contexto

Sem framework, era preciso um conjunto mínimo de serviços transversais que
evitasse repetição em 91 rotas e em dezenas de telas de cadastro.

## Decisão

Concentrar em `src/Kernel/` um núcleo reutilizável com:

- container de injeção de dependência com autowiring;
- roteador com suporte a `[Classe::class, 'metodo']`;
- sessão com *flash messages* e token CSRF;
- `Auth` com papéis e `requireRole`;
- `CrudController` + `Resource` para CRUD declarativo;
- `ViewFactory` sobre o Plates, com uma pasta de views por módulo;
- `Registry` que descobre módulos pelo `module.json`.

## Consequências

- Positivas: uma tela CRUD nova exige descrever o `Resource`, não reescrever
  controller e view; o menu e as permissões saem da mesma declaração.
- Negativas: o `CrudController` precisa de pontos de extensão para casos
  específicos (OS, orçamento e pedidos sobrescrevem métodos).
- O núcleo é reutilizável em outros projetos do curso, mas não é um produto
  genérico.
