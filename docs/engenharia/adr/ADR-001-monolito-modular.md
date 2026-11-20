# ADR-001 — Monólito modular em PHP, sem framework

**Status:** aceito
**Data:** 2026-08

## Contexto

O Projeto Integrador precisava entregar uma aplicação web completa em um
semestre, com equipe pequena e um domínio bem delimitado (gestão de oficina).
O curso pede que o grupo mostre domínio de PHP, banco de dados, arquitetura e
implantação. Um framework pronto esconderia boa parte dessas decisões.

## Decisão

Construir um monólito modular em PHP 8.2, com um núcleo próprio e módulos por
domínio (`Core`, `Mechanic`, `Agent`), sem Laravel, Symfony ou CodeIgniter.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Laravel | Entregaria muita coisa pronta e esconderia as decisões que o curso quer avaliar; o custo de aprendizagem competiria com o prazo |
| Microserviços | Complexidade operacional desproporcional para uma oficina e para o prazo |
| PHP procedural | Já existia como protótipo e foi justamente o que se quis substituir |

## Consequências

- Positivas: controle total do ciclo da requisição, código legível, nenhuma
  dependência pesada, facilidade para explicar cada camada na banca.
- Negativas: responsabilidade sobre segurança, roteamento, sessão e CRUD é do
  grupo; não há um framework por trás.
- Mitigação: uso de bibliotecas pequenas e consolidadas (`bramus/router`,
  `league/container`, `league/plates`, `nyholm/psr7`).
