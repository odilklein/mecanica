# Stack tecnológica

Levantamento feito em `composer.json`, `composer.lock`, `Dockerfile`,
`docker-compose.yml` e no código em execução (novembro de 2026).

## Aplicação

| Camada | Tecnologia | Versão | Onde |
|---|---|---|---|
| Linguagem | PHP | 8.2 | `composer.json`, `Dockerfile` |
| Servidor web | Apache + `mod_rewrite` | imagem `php:8.2-apache` | `Dockerfile` |
| Banco de dados | MySQL | 8.4 | `docker-compose.yml` |
| Driver | PDO MySQL | extensão nativa | `Kernel\Database\Connection` |
| Front controller | `public/index.php` | — | raiz |
| Gerenciador de dependências | Composer | 2.x | `composer.lock` |

## Bibliotecas PHP

| Pacote | Versão | Uso |
|---|---|---|
| `bramus/router` | 1.6.1 | Roteamento HTTP |
| `league/container` | 4.2.5 | Injeção de dependência e autowiring |
| `league/plates` | 3.6.0 | Templates das views |
| `nyholm/psr7` | 1.8.2 | Request/Response PSR-7 |
| `vlucas/phpdotenv` | 5.8.0 | Leitura do `.env` |
| `phpunit/phpunit` | 11.5.57 | Testes (dependência de desenvolvimento) |
| `symfony/var-dumper` | 7.4.22 | Depuração (desenvolvimento) |

## Front-end

| Tecnologia | Uso |
|---|---|
| Bootstrap 5 | Grid, componentes e utilitários |
| JavaScript modular (ES modules) | Formulários, busca, assistente, gráficos |
| CSS próprio | Tema, variáveis de cor e layout |
| Chart.js | Gráficos do dashboard e dos relatórios |

Os arquivos estáticos ficam em `public/assets/` e são servidos diretamente pelo
Apache. Não há etapa de build de front-end (sem bundler): o navegador carrega os
módulos ES nativamente.

## Infraestrutura

| Recurso | Definição |
|---|---|
| `Dockerfile` | PHP 8.2 + Apache, extensões `pdo_mysql`, `mbstring` e `zip` |
| `docker-compose.yml` | Serviços `app` (porta 8080), `db` (3306) e `phpmyadmin` (8081) |
| Volumes | Código montado em `/var/www/html`; banco em volume nomeado |
| Endurecimento | `expose_php=Off`, `display_errors=Off`, cookies `HttpOnly` e `SameSite=Lax`, `upload_max_filesize=25M` |
| Apache | `DocumentRoot` apontando para `public/`, `AllowOverride All`, `mod_rewrite` |

## Ferramentas de documentação (fora da aplicação)

| Ferramenta | Uso | Execução |
|---|---|---|
| PlantUML | Casos de uso, classes, sequência, arquitetura | Docker `plantuml/plantuml` |
| D2 | Diagrama de fluxo da OS | Docker `terrastruct/d2` |
| Mermaid | Diagramas de entidade-relacionamento | `mmdc` (Node) ou imagem Docker |
| python-docx | Montagem do DOCX no template da FATEC | Python 3 |
| Pillow | Tratamento das imagens | Python 3 |
| PyMuPDF | Conferência página a página do PDF | Python 3 |
| LibreOffice | Conversão DOCX → PDF | Docker `linuxserver/libreoffice` |

## Observações

- O projeto **não usa framework PHP** (Laravel, Symfony, CodeIgniter). O núcleo
  em `src/Kernel/` implementa container, roteador, sessão, autenticação, CRUD
  genérico e fábrica de views.
- Não há ORM: as consultas são SQL escrito à mão com `PDO` e *prepared
  statements*.
- As migrations são arquivos PHP com arrays `up`/`down`, executados por
  `bin/migrate.php`. São 32 migrations registradas na tabela `migrations`.
- O banco `mecanica` tem 48 tabelas: 39 da aplicação atual e 9 remanescentes do
  protótipo procedural (listadas em `engenharia/04-modelo-dados.md`).
