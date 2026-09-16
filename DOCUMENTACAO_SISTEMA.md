# Documentacao do Sistema - Auto Mecanica

> A documentacao navegavel em `mecanica-docs/` (site estatico, publicado no Cloudflare Pages)
> e a referencia principal e costuma ser a mais atual. Este Markdown resume o sistema para
> anexar ao relatorio do PI.

## 1. Objetivo

Este projeto e um sistema interno de operacao para uma oficina mecanica. O nucleo operacional cobre:

1. Clientes e veiculos.
2. Funcionarios e permissoes (4 perfis: admin, secretaria, mecanico-chefe e mecanico).
3. Ordens de servico (OS) — centro do dominio.
4. Pedidos de pecas vinculados a OS.

Modulos de apoio: orcamentos, notificacoes e auditoria. A OS registra a queixa do cliente, o diagnostico, as tarefas, os funcionarios
responsaveis, as pecas necessarias, o andamento e a entrega/retirada do veiculo.

O sistema usa PHP procedural, PDO/MySQL, Bootstrap, CSS proprio (tema claro/escuro e responsivo),
Lucide Icons e Cloudflare Turnstile no login. Nao ha framework PHP, Composer, API REST formal nem
camada de servicos separada. As credenciais ficam fora do Git (`.env`).

---

## 2. Regra de negocio principal

Fluxo operacional:

```text
Cliente/veiculo
  -> Nova OS
  -> Designacao de funcionarios
  -> Tarefas e diagnostico
  -> Solicitacao de pecas, se necessario
  -> Validacao da solicitacao
  -> Pedido da peca pela secretaria
  -> Recebimento da peca
  -> Execucao do trabalho
  -> Entrega ou retirada
  -> Historico do cliente
```

### Regra de ouro

A OS deve ser a fonte do andamento operacional. Clientes, veiculos, tarefas, pecas e historico devem se relacionar com a OS sempre que fizerem parte de um atendimento.

---

## 3. Perfis e permissoes

Os valores validos para `usuarios.tipo` sao:

- `admin`: acesso administrativo completo.
- `secretaria`: clientes, veiculos, funcionarios, OS, pedidos de pecas e orcamentos.
- `mecanico_chefe`: supervisiona OS, cria atendimentos, designa funcionarios, valida solicitacoes e acompanha a operacao.
- `mecanico`: ve somente OS atribuidas, atualiza tarefas/diagnostico e solicita pecas para as proprias OS.

### Matriz de acesso atual

| Area/acao | Admin | Secretaria | Mecanico chefe | Mecanico |
|---|---:|---:|---:|---:|
| Dashboard | Sim | Sim | Sim | Sim, somente dados das OS atribuidas |
| Clientes | Sim | Sim | Sim | Nao |
| Dados de contato sensiveis | Sim | Sim | Sim | Nao |
| Veiculos | Sim | Sim | Sim | Nao |
| Funcionarios | Sim | Sim | Sim | Nao |
| Criar OS | Sim | Sim | Sim | Nao |
| Ver todas as OS | Sim | Sim | Sim | Nao |
| Ver OS atribuida | Sim | Sim | Sim | Sim |
| Designar funcionarios | Sim | Nao | Sim | Nao |
| Atualizar tarefas | Sim | Nao | Sim | Sim, nas OS atribuidas |
| Diagnostico tecnico | Sim | Visualizacao | Sim | Sim, nas OS atribuidas |
| Solicitar peca | Sim | Sim | Sim | Sim, apenas para OS atribuida |
| Validar solicitacao | Sim | Sim | Sim | Nao |
| Confirmar pedido ao fornecedor | Sim | Sim | Nao | Nao |
| Registrar recebimento | Sim | Sim | Nao | Nao |
| Orcamentos | Sim | Sim | Sim | Nao |
| Entrega/retirada | Sim | Sim | Sim | Nao |

A autorizacao deve existir no backend. Ocultar um item da sidebar nao e considerado controle de seguranca.

---

## 4. Estrutura de pastas

```text
index.php                       Dashboard por papel
api/
  notificacoes.php              JSON de notificacoes + marcar lida
config/
  conexao.php                   Conexao PDO e helpers de escaping/moeda
  env.php                       Carrega o .env da raiz e expoe env()
  empresa.php                   Dados visuais da empresa
css/style.css                   Design system e estilos globais
includes/
  base_url.php                  Calcula caminho base da aplicacao
  header.php                    Cabecalho, CSS, Bootstrap e Lucide
  footer.php                    Scripts finais
  mensagens.php                 Mensagens flash
  notificacoes.php              Helpers: notificar_usuarios/tipos/responsaveis_os
  auditoria.php                 Registro de acoes sensiveis na tabela auditoria
  sessao.php                    Sessao, login obrigatorio, CSRF e roles
  sidebar.php                   Navegacao por papel
js/main.js                      Mascara, wizard, listas dinamicas, tema, notificacoes
pages/
  auth/                         Login, logout e perfil (Meus dados)
  clientes/                     CRUD e detalhe do cliente
  funcionarios/                 CRUD de funcionarios
  veiculos/                     CRUD de veiculos
  ordens_servico/               Criacao (wizard), fila, detalhe e processamento da OS
  pedidos_pecas/                Solicitacao, validacao, pedido, recebimento parcial e total
  notificacoes/                 Tela dedicada com filtros, busca e paginacao
  orcamentos/                   Documento comercial manual, restrito a secretaria/chefia/admin
sql/
  mecanica_teste.sql            Banco completo recriado com dados de demonstracao e logins
docker-compose.yml              Stack local (app + MySQL + phpMyAdmin)
Dockerfile                      Imagem Apache+PHP com pdo_mysql/mbstring
```

---

## 5. Arquivos principais

### Sessao e seguranca

`includes/sessao.php`:

- Inicia sessao com `HttpOnly`, `SameSite=Lax` e `Secure` quando HTTPS esta ativo.
- Redireciona paginas protegidas sem sessao para o login.
- Cria token CSRF na sessao.
- Expoe `csrf_token()`, `validar_csrf()`, `exigir_papel()` e `tem_papel()`.

`config/conexao.php`:

- Cria conexao PDO MySQL usando `DB_HOST`, `DB_USER`, `DB_PASS` e `DB_NAME` do ambiente.
- Valores fallback atuais: `localhost`, `root`, senha vazia e banco `mecanica`.
- `limpar()` apenas normaliza entrada com `trim()`.
- `esc()` faz escaping HTML para saida.
- `formatar_moeda()` formata valores em reais.

`config/env.php`:

- Expoe `env($chave, $padrao)`, que le variaveis do processo (Docker) ou, na falta
  delas, um unico arquivo `.env` na raiz do projeto. Nao usa `putenv()`.

### Dashboard

`index.php` exibe apenas informacoes relacionadas ao novo produto:

- Clientes.
- Funcionarios ativos.
- OS em andamento.
- Pecas pendentes.
- Tabela de OS filtrada para mecanicos.
- Acoes rapidas conforme role.

Nao adicionar ao dashboard novo metricas de faturamento ou agendamento sem uma decisao explicita de produto.

### Sidebar

`includes/sidebar.php` mostra atualmente:

- Painel.
- Notificacoes.
- Clientes.
- Veiculos.
- Funcionarios.
- Ordens de servico.
- Pedidos de pecas.
- Orcamentos, para admin/secretaria/mecanico chefe.
- Relatorios, para admin/secretaria/mecanico chefe.

A navegacao principal cobre apenas o fluxo operacional descrito acima. Modulos de estoque, servicos e agendamentos foram removidos do sistema.

---

## 6. Fluxo de clientes e veiculos

### Cliente

Tabela `clientes` guarda:

- Nome.
- CPF.
- Telefone.
- E-mail.
- Endereco/cidade.
- Observacoes.
- Datas de cadastro e atualizacao.

Paginas principais:

- `pages/clientes/listar.php`
- `pages/clientes/cadastrar.php`
- `pages/clientes/editar.php`
- `pages/clientes/excluir.php`
- `pages/clientes/detalhes.php`
- `pages/clientes/processar.php`

O detalhe do cliente mostra:

- Dados cadastrais para papeis autorizados.
- Veiculos vinculados.
- Historico de OS/servicos.
- Link para criar veiculo.
- Links para abrir cada OS.

### Veiculo

Tabela `veiculos` pertence a um cliente por `cliente_id`.

Um veiculo deve sempre permanecer vinculado ao cliente correto. A placa e unica.

Campos principais:

- Placa.
- Marca/modelo.
- Ano.
- Cor.
- Quilometragem.
- Chassi.

Mecanicos nao acessam o CRUD completo de veiculos. Eles veem apenas dados operacionais do veiculo dentro de uma OS, sem telefone/e-mail do cliente.

### Historico

Tabela `cliente_servicos_historico` relaciona:

- Cliente.
- Veiculo.
- OS encerrada.
- Descricao do trabalho.
- Data.
- Observacoes.

O historico e criado quando a OS e encerrada por entrega/retirada confirmada.

---

## 7. Funcionarios

Tabela `usuarios` tambem representa funcionarios do sistema.

Campos adicionais:

- `cpf`
- `telefone`
- `salario`
- `data_admissao`
- `cargo`
- `ativo`
- `tipo`

Paginas:

- `pages/funcionarios/listar.php`
- `pages/funcionarios/cadastrar.php`
- `pages/funcionarios/editar.php`
- `pages/funcionarios/processar.php`

Regras:

- Secretaria e mecanico chefe podem gerenciar funcionarios conforme a role.
- Somente admin pode criar outro admin.
- Um usuario nao pode desativar o proprio acesso.
- Senhas sao armazenadas com `password_hash()`.
- A senha precisa ter pelo menos 8 caracteres ao criar/alterar.
- Funcionarios inativos nao devem ser designados a novas OS.

---

## 8. Ordem de servico

Tabela principal: `ordens_servico`.

Campos principais:

- `numero`: identificador visivel da OS.
- `cliente_id` e `veiculo_id`.
- `status`.
- `prioridade`.
- `queixa_inicial`.
- `diagnostico`.
- `observacoes_internas`.
- `responsavel_chefe_id`.
- `criado_por`.
- Datas de entrada, atualizacao e conclusao.

Paginas:

- `pages/ordens_servico/cadastrar.php`
- `pages/ordens_servico/listar.php`
- `pages/ordens_servico/detalhes.php`
- `pages/ordens_servico/processar.php`

### Criacao da OS

O formulario pode receber:

- Cliente.
- Telefone para localizar/cadastrar cliente rapidamente.
- Marca/modelo/placa.
- Prioridade.
- Queixa inicial.
- Observacoes internas.
- Tarefas, uma por linha.
- Funcionarios designados.
- Pecas necessarias, no formato `Descricao | quantidade`.

Ao criar:

1. Cliente e criado ou localizado por telefone.
2. Veiculo e criado/atualizado pela placa.
3. OS e criada.
4. Funcionarios sao inseridos em `os_responsaveis`.
5. Tarefas sao inseridas em `os_tarefas`.
6. Se houver pecas, um pedido e criado automaticamente em `pedidos_pecas`.
7. Um evento e gravado em `os_eventos`.

### Status da OS

Estados existentes:

```text
recebida
triagem
aguardando_diagnostico
aguardando_orcamento
aguardando_aprovacao
aguardando_peca
pronta_para_execucao
em_execucao
aguardando_retorno
pronta_para_entrega
entregue
cancelada
```

Transicoes permitidas ficam em `pages/ordens_servico/processar.php`. Nao permitir saltos arbitrarios.

A OS nao pode ser marcada como `entregue` manualmente. Deve haver entrega/retirada confirmada em `os_entregas`.

### Tarefas

Tabela `os_tarefas`:

- Descricao.
- Status: `pendente`, `em_execucao`, `concluida`, `bloqueada`.
- Criador.
- Quem concluiu.
- Datas.

Mecanicos podem atualizar tarefas somente nas OS atribuidas a eles.

### Responsaveis

Tabela `os_responsaveis` permite varios funcionarios na mesma OS.

O mecânico comum deve ser filtrado por:

```sql
os_responsaveis.usuario_id = usuario_logado
AND concluido_em IS NULL
```

Nao remover esse filtro de listagens, detalhes, dashboard ou pedidos de pecas.

---

## 9. Pedidos de pecas

Tabelas:

- `pedidos_pecas`: cabecalho do pedido.
- `pedido_pecas_itens`: descricao, quantidade e recebimento.

### Fluxo

```text
solicitado
  -> validado
  -> pedido
  -> a_caminho
  -> recebido
```

Tambem pode terminar em `cancelado` quando permitido.

### Regras por papel

- Mecanico: cria solicitacao apenas para OS atribuida.
- Mecanico: nao escolhe fornecedor.
- Secretaria ou mecanico chefe: valida a solicitacao.
- Secretaria: confirma pedido ao fornecedor e registra chegada.
- Mecanico: acompanha somente pedidos relacionados as suas OS.

Status `solicitado` significa que ainda nao houve validacao. Nao tratar como compra realizada.

Ao receber:

- Registrar recebimento por item em `pages/pedidos_pecas/editar.php` (secao "Registrar recebimento").
- O sistema aceita recebimento parcial: enquanto houver itens pendentes, o pedido permanece em andamento.
- Quando todos os itens estao recebidos, o pedido vai para `recebido`.
- Registrar usuario/data de recebimento.
- Notificar responsaveis da OS.
- Liberar a OS para `pronta_para_execucao` quando aplicavel.

---

## 10. Orcamentos

O modulo ainda existe, mas nao e o centro operacional. Ele e acessivel apenas a admin, secretaria e mecanico chefe.

### Formulario atual

`pages/orcamentos/cadastrar.php` usa blocos manuais:

- Tipo: `servico` ou `peca`.
- Descricao livre.
- Quantidade.
- Valor unitario.
- Subtotal calculado no navegador para revisao visual.

A secretaria pode adicionar/remover blocos e revisar o documento antes de salvar.

### Regra de backend

`pages/orcamentos/processar.php`:

- Recebe descricao, quantidade e preco de cada item.
- Recalcula subtotal no servidor.
- Valida quantidade, valor e descricao.
- Valida cliente e OS quando a OS e informada.

O modulo e 100% manual: nao depende de catalogo de pecas/servicos nem de estoque.
As telas de editar/detalhes/imprimir usam apenas `orcamento_itens.descricao_item`.

---

## 11. Banco de dados

### Schema de teste recomendado

Use `sql/mecanica_teste.sql` em um banco de testes vazio. O script:

- Cria o banco `mecanica`.
- Remove tabelas existentes do mesmo banco.
- Recria tabelas atuais.
- Insere clientes, veiculos, funcionarios, OS, tarefas, pedidos, eventos e notificacoes.

Logins do seed sao documentados dentro do proprio arquivo SQL. Eles sao somente para ambiente local/teste e nao devem ser usados em producao.

### Migracoes e banco de testes

O arquivo unico de referencia e `sql/mecanica_teste.sql`. Ele:

- Cria o banco `mecanica`.
- Remove tabelas existentes do mesmo banco.
- Recria tabelas atuais.
- Insere clientes, veiculos, funcionarios, OS, tarefas, pedidos, eventos, orcamentos, entregas e notificacoes de demonstracao.
- Documenta os logins de teste no proprio arquivo.

Para ambiente de teste, basta importar `mecanica_teste.sql` em um banco vazio no phpMyAdmin.

As migracoes incrementais antigas (`sql/mecanica.sql`, `002_operacao_oficina.sql`, `003_simplificacao_operacional.sql`) foram consolidadas no `mecanica_teste.sql` e nao sao mais entregues separadamente.

### Tabelas relevantes

| Tabela | Responsabilidade |
|---|---|
| `usuarios` | Login, funcionario, role e dados trabalhistas |
| `clientes` | Cadastro dos clientes |
| `veiculos` | Carros vinculados a clientes |
| `ordens_servico` | Processo principal do atendimento |
| `os_eventos` | Historico de mudancas da OS |
| `os_responsaveis` | Funcionarios designados |
| `os_tarefas` | Trabalho detalhado da OS |
| `pedidos_pecas` | Solicitacao/compra/recebimento |
| `pedido_pecas_itens` | Itens e quantidades do pedido |
| `os_entregas` | Entrega/retirada do carro |
| `cliente_servicos_historico` | Historico final do cliente |
| `notificacoes` | Avisos internos |
| `auditoria` | Registro de acoes sensiveis |
| `orcamentos` | Documento comercial manual |

---

## 12. Ambiente local

### Docker / Podman (recomendado)

- `docker-compose.yml` sobe tres servicos: `app` (Apache+PHP), `db` (MySQL 8.4) e `phpmyadmin`.
- Aplicacao em `http://localhost:8080`; phpMyAdmin em `http://localhost:8081`.
- O banco `mecanica` e criado e populado com `sql/mecanica_teste.sql` no primeiro start.
- Credenciais padrao: usuario `mecanica`, senha `root123` (ajustaveis no `.env`).
- `docker compose up -d --build` (ou `podman-compose up -d`) sobe a stack.
- Para recriar o banco: `docker compose down -v` e subir novamente.

### XAMPP

1. Inicie Apache e MySQL no XAMPP.
2. Abra o phpMyAdmin.
3. Em ambiente de teste, importe `sql/mecanica_teste.sql`.
4. Acesse `http://localhost/mecanica/`.
5. Use um login de teste criado pelo seed.

### Configuracao do banco

`config/conexao.php` le `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` e `DB_NAME`
via `env()` (`config/env.php`). A precedencia e: variaveis do processo
(ex.: Docker) -> arquivo `.env` na raiz -> padroes (`localhost`, `root`, senha
vazia, banco `mecanica`).

O `.env` e ignorado pelo Git e nao deve receber segredos de producao.

---

## 13. Convencoes para novas alteracoes

### PHP

- Incluir `includes/sessao.php` primeiro.
- Incluir `config/conexao.php` para acesso ao PDO e helpers.
- Proteger pagina com `exigir_papel([...])`.
- Proteger todo POST mutavel com `validar_csrf()`.
- Usar prepared statements.
- Usar `limpar()` apenas na entrada.
- Usar `esc()` na saida HTML.
- Nunca exibir excecao PDO para o usuario.
- Registrar erros tecnicos com `error_log()`.
- Fazer transacao quando alterar OS, pedido, evento e notificacao juntos.
- Nao confiar em precos, totais, IDs relacionados ou status vindos somente da interface.

### Interface

- Usar Lucide Icons em botoes e labels importantes.
- Usar `btn-icon` e `action-button` para manter botoes compactos.
- Respeitar o visual existente em `css/style.css`.
- Modulos de estoque, servicos e agendamentos foram removidos; nao reintroduzir sem decisao de produto.
- Telas de mecanico devem ser operacionais e sem dados pessoais desnecessarios.

### Roles

Sempre proteger duas camadas:

1. Sidebar: nao exibir link para quem nao tem permissao.
2. Backend: bloquear acesso direto por URL e POST.

Ao adicionar uma nova area, atualizar:

- `includes/sidebar.php`.
- A matriz de permissoes.
- O backend da pagina.
- O dashboard, se houver KPI relacionado.
- O seed SQL.

---

## 14. Checklist para outra IA

Antes de editar:

- Ler este arquivo.
- Verificar `git status`.
- Identificar se a mudanca afeta OS, role ou pedido de peca.
- Conferir se existe dado sensivel no fluxo.
- Verificar as tabelas e foreign keys envolvidas.

Durante a implementacao:

- Fazer a menor alteracao coerente com o dominio.
- Nao criar um segundo conceito para algo que ja existe na OS.
- Aplicar role e CSRF no backend.
- Registrar eventos quando o estado mudar.
- Preservar historico em vez de apagar registros.

Depois da implementacao:

- Rodar verificacao de erros do editor.
- Rodar `git diff --check`.
- Testar usuario de cada role.
- Testar URL direta com papel incorreto.
- Testar CSRF ausente.
- Testar OS atribuida e OS nao atribuida.
- Testar solicitacao de peca do inicio ao recebimento.
- Testar cliente com varios veiculos e historico.
- Testar o seed em banco vazio.

---

## 15. Gaps conhecidos e proximas melhorias

1. PHP CLI nao esta disponivel neste ambiente; a validacao final deve ser feita no PHP do XAMPP.
2. Nao ha suite automatizada de testes.
3. O modulo de notificacoes tem UI de sino, leitura e tela dedicada com filtros/paginacao; os registros sao criados em `notificacoes`.
4. O recebimento de pecas aceita recebimento parcial por item; pode evoluir para conferencia com notas de entrega.
5. A entrega possui estrutura de dados, mas pode ganhar fluxo separado para tentativa/retorno (hoje o registro ja conclui).
6. Orcamentos possuem telas de edicao e impressao alinhadas ao formato manual (descricao livre).
7. Nao ha controle de estoque; as pecas sao descritas livremente nos pedidos.
8. A senha de teste deve ser alterada ou removida antes de qualquer ambiente acessivel externamente.
9. O sistema ainda precisa de rotacao de logs, politica de senha e rate limit de login.
10. O sistema ainda nao possui portal do cliente, integracao real com WhatsApp, e-mail transacional ou pagamento Pix.

---

## 16. Regra para nao quebrar o produto

Ao implementar uma nova funcionalidade, mantenha esta ordem de dependencia:

```text
Role/autorizacao
  -> Cliente/veiculo
    -> OS
      -> tarefas/responsaveis
        -> solicitacao de peca
          -> validacao/pedido/recebimento
            -> execucao
              -> entrega
                -> historico do cliente
```

Se uma proposta criar um fluxo paralelo fora dessa cadeia, revisar a modelagem antes de codar.

---

## 17. Notificacoes, perfil e documentacao

### Notificacoes

- Tabela `notificacoes`: usuario, OS, tipo, mensagem, `lida_em`, `criada_em`.
- Helpers em `includes/notificacoes.php`:
  - `notificar_usuarios()` - para IDs especificos.
  - `notificar_tipos()` - para todos os usuarios ativos de determinados perfis (com `ignorar_usuario`).
  - `notificar_responsaveis_os()` - para os vinculados ativos da OS (`concluido_em IS NULL`).
- Endpoint `api/notificacoes.php` (JSON):
  - GET: contador de nao lidas + ultimas 15 com tempo relativo.
  - POST `marcar_lida` / `marcar_todas_lidas` (exige `X-CSRF-Token`).
- Interface: sino no topbar com contador, atualizacao a cada 30s, clique marca lida e abre o detalhe da OS.

### Perfil do usuario

- `pages/auth/perfil.php` / `perfil_processar.php`: edicao de `nome`, `email` e `senha` pelo proprio usuario, com validacao de email unico e senha >= 8 caracteres. O perfil (`tipo`) nao pode ser alterado pelo proprio usuario.

### Auditoria

- Tabela `auditoria`: `usuario_id`, `entidade`, `entidade_id`, `acao`, `dados` (JSON) e data.
- Helper em `includes/auditoria.php`: `registrar_auditoria($conn, $usuario_id, $acao, $entidade, $entidade_id, $descricao, $dados)`.
- Registra login, logout, criacao/edicao de clientes, veiculos, funcionarios, OS, pedidos de pecas e orcamentos, e exclusoes.
- Uma falha ao gravar auditoria nunca bloqueia a acao principal (apenas `error_log`).

### Documentacao (site separado)

- A documentacao do sistema (este arquivo em formato navegavel) vive em um **site estatico separado**, na pasta irma `mecanica-docs/` (fora do repositorio), servida por exemplo em `http://localhost/mecanica-docs/`.
- Paginas: visao geral, regras de negocio, perfis/permissões, funcoes do sistema, banco de dados, seguranca, instalacao e convencoes.
- E construida com HTML/CSS/JS puro (`assets/css/docs.css`, `assets/js/docs.js`), diagramas Mermaid via CDN, e nao exige login.
