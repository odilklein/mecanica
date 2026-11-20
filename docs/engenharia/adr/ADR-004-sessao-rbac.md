# ADR-004 — Sessão no servidor e autorização por papéis (RBAC)

**Status:** aceito
**Data:** 2026-09

## Contexto

A oficina tem quatro perfis com necessidades distintas: administrador,
secretaria, mecânico-chefe e mecânico. O sistema é interno e acessado pelo
navegador, muitas vezes no balcão.

## Decisão

- Autenticação por e-mail e senha, com `password_hash`.
- Sessão PHP no servidor, com regeneração de ID no login, `HttpOnly` e
  `SameSite=Lax`.
- Autorização por papel (`admin`, `secretary`, `lead_mechanic`, `mechanic`) no
  servidor, via `Auth::requireRole` e `Resource::roles()`.
- CSRF obrigatório em todo `POST`.
- Rate limit de login por e-mail + IP.

## Por que não JWT

Um sistema interno, servido no mesmo domínio, não ganha nada com JWT e perde a
possibilidade de invalidar a sessão imediatamente. A sessão no servidor é mais
simples e mais segura para esse cenário.

## Consequências

- Positivas: logout e bloqueio imediatos; nenhum token no navegador; regras de
  acesso declaradas junto do recurso.
- Negativas: sessões presas ao servidor (não há escala horizontal pronta).
- Aceitável para uma instalação mono-oficina.

## Regras associadas

- Ocultar um botão não é autorização: a rota também valida o papel.
- O mecânico só acessa OS em que é responsável, líder ou criador.
