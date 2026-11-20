# ADR-006 — Docker como ambiente único de desenvolvimento e execução

**Status:** aceito
**Data:** 2026-08

## Contexto

O grupo usa máquinas diferentes, e a banca precisa executar o sistema sem
instalar PHP, Apache e MySQL manualmente. O projeto anterior (procedural) sofria
com divergência de ambiente.

## Decisão

Empacotar a aplicação em Docker Compose com três serviços:

| Serviço | Imagem | Porta |
|---|---|---|
| `app` | build local (`php:8.2-apache`) | 8080 |
| `db` | `mysql:8.4` | 3306 |
| `phpmyadmin` | `phpmyadmin:latest` | 8081 |

O código é montado como volume, para desenvolvimento com recarga imediata. O
`Dockerfile` já aplica endurecimento básico do PHP e configura o Apache para
servir `public/`.

## Consequências

- Positivas: um comando sobe tudo; a banca roda o mesmo ambiente; o MySQL já
  nasce com o schema aplicado pelas migrations.
- Negativas: exige Docker instalado; em máquinas sem virtualização, é preciso
  alternativa (PHP e MySQL locais).
- O `docker-compose.yml` define healthcheck do banco para evitar corrida na
  inicialização.
