# Documentação do Sistema

O sistema foi construído em ciclos curtos, com o banco e os diagramas acompanhando o código.

## 3.1 Metodologia de Desenvolvimento

O trabalho foi feito em ciclos curtos, com uma funcionalidade verificável por vez. O quadro Kanban tinha quatro colunas: *a fazer*, *em andamento*, *revisão* e *concluído*.

As etapas foram:

1. **Levantamento e modelagem**: entrevista, requisitos, casos de uso e modelo de dados.
2. **Construção da base**: núcleo da aplicação, autenticação, layout e CRUD genérico.
3. **Módulos de negócio**: clientes, veículos, OS, orçamentos, peças, pedidos, tickets e relatórios.
4. **Integrações**: ViaCEP, FIPE, Canal da Peça, Groq e Turnstile.
5. **Assistente de IA**: ferramentas, propostas e aprovação humana.
6. **Revisão e documentação**: testes manuais, correções e escrita do documento.

O versionamento usou Git e GitHub, com mensagens no padrão `tipo(escopo): descrição`. As mudanças de banco ficaram em migrations aplicadas por `bin/migrate.php`. O ambiente é o mesmo no desenvolvimento e na apresentação, com Docker Compose.

Os diagramas têm fontes versionadas e o documento oficial é gerado a partir de Markdown dentro do template da FATEC. O texto pode ser corrigido sem mexer na formatação.

## 3.2 Diagramas UML

Os diagramas saíram do código e do banco em execução, com PlantUML. As fontes ficam em `docs/diagramas/fontes/` e as imagens em `docs/diagramas/png/`.

### 3.2.1 Diagrama de casos de uso

Os casos de uso estão separados por perfil. O administrador cuida de funcionários, configurações e auditoria. A secretaria concentra o atendimento. O mecânico-chefe acumula a operação técnica e a equipe. O mecânico executa as OS às quais está vinculado.

![Figura 2 — Casos de uso do perfil administrador.](diagramas/png/01_casos_uso_admin.png)

![Figura 3 — Casos de uso do perfil secretaria.](diagramas/png/02_casos_uso_secretaria.png)

![Figura 4 — Casos de uso do perfil mecânico-chefe.](diagramas/png/03_casos_uso_chefe.png)

![Figura 5 — Casos de uso do perfil mecânico.](diagramas/png/04_casos_uso_mecanico.png)

A autenticação aparece em todos os perfis. As demais ações são limitadas no servidor, conforme a matriz de permissões em 3.5.

### 3.2.2 Diagrama de classes

O diagrama de classes mostra as entidades centrais e as relações. As tabelas de cache e as de apoio ficaram fora para não pesar a leitura; elas estão no dicionário de dados.

![Figura 6 — Diagrama de classes do domínio.](diagramas/png/05_classes.png)

A classe `ServiceOrder` fica no centro. Dela dependem tarefas, responsáveis, eventos, entrega, orçamentos, pedidos e tickets. O cliente tem veículos, e cada veículo acumula ordens de serviço. Orçamento e pedido têm itens próprios, o que permite calcular totais e guardar o que foi proposto e comprado.

### 3.2.3 Diagramas de sequência

Os três fluxos abaixo são os que mais afetam a operação: abertura da OS, aprovação do orçamento e entrega do veículo.

![Figura 7 — Sequência de abertura de ordem de serviço.](diagramas/png/06_sequencia_abrir_os.png)

Na abertura, o controller valida o CSRF e o perfil, grava a OS com status `received`, registra o evento inicial, cria o ticket de comunicação e notifica os envolvidos.

![Figura 8 — Sequência de aprovação de orçamento.](diagramas/png/07_sequencia_aprovar_orcamento.png)

A aprovação só é permitida a partir do status `open`. O controller recalcula os totais no servidor, grava a mudança de status, registra auditoria e notifica o criador e os responsáveis.

![Figura 9 — Sequência de entrega do veículo.](diagramas/png/08_sequencia_entrega.png)

A entrega exige que a OS esteja em `ready_for_delivery`, que ainda não exista entrega registrada e que o KM de saída não seja menor que o KM atual. A gravação ocorre em transação, atualizando a entrega, o KM do veículo, o status da OS e o histórico.

A Figura 10 mostra o fluxo completo de status.

![Figura 10 — Fluxo de status da ordem de serviço.](diagramas/png/14_fluxo_os.png)

## 3.3 Modelagem de Banco de Dados

O banco `mecanica` tem 39 tabelas em uso pela aplicação, organizadas em quatro domínios: cadastros, operação, comercial e apoio. Há ainda nove tabelas remanescentes do protótipo procedural, mantidas apenas como histórico e não lidas pelo sistema atual.

### 3.3.1 Modelo conceitual

O modelo conceitual representa as entidades principais e as cardinalidades, sem os atributos. A leitura é feita da esquerda para a direita: o cliente possui veículos e solicita ordens de serviço; cada OS se desdobra em orçamento, pedido de peças e ticket; a peça participa do pedido.

![Figura 11 — Modelo conceitual do sistema.](diagramas/png/09_er_conceitual.png)

### 3.3.2 Modelo lógico

O modelo lógico foi dividido em três recortes para facilitar a leitura. O primeiro reúne os cadastros e o acesso; o segundo, a operação da oficina; o terceiro, os documentos comerciais (orçamento e pedido de peças).

![Figura 12 — Modelo lógico: cadastros e acesso.](diagramas/png/10_er_logico_cadastros.png)

![Figura 13 — Modelo lógico: operação da oficina.](diagramas/png/11_er_logico_operacao.png)

![Figura 14 — Modelo lógico: orçamentos e pedidos de peças.](diagramas/png/12_er_logico_documentos.png)

### 3.3.3 Dicionário de dados

O dicionário abaixo foi gerado a partir do `information_schema` do MySQL em execução, não escrito à mão. Para cada tabela são apresentados os campos, os tipos, a nulidade, as chaves e os valores padrão, além das chaves estrangeiras.

O documento detalha as 24 tabelas centrais. As tabelas de cache, as internas do assistente e a de migrations estão no dicionário completo do repositório (`docs/engenharia/04-modelo-dados.md`).

{{DICIONARIO}}

## 3.4 Arquitetura da Solução

O Torque é um monólito modular em PHP 8.2, servido pelo Apache, sem framework de aplicação. A arquitetura separa o núcleo reutilizável (`src/Kernel/`), os módulos de negócio (`src/Modules/`) e a infraestrutura (`bin/`, Docker).

![Figura 15 — Arquitetura em camadas do sistema.](diagramas/png/13_arquitetura.png)

O ciclo de uma requisição é:

1. O Apache recebe a requisição e, se o caminho não for um arquivo real, reescreve para `public/index.php`.
2. O front controller carrega o autoload e o `.env`, e inicializa o Kernel.
3. O Kernel inicia a sessão, monta o container, registra os serviços compartilhados e descobre os módulos pelo `module.json`.
4. O roteador resolve o controller pelo container.
5. O controller valida CSRF e perfil, executa a regra de negócio com PDO e devolve uma view ou um redirecionamento.
6. A view é renderizada pelo Plates dentro do layout comum.

Um módulo novo não exige mudança no núcleo: basta criar a pasta com `module.json`, o provider e as views. O módulo `Agent` entrou assim, depois de `Core` e `Mechanic`.

## 3.5 Segurança e Integrações

### 3.5.1 Segurança

| Controle | Implementação |
|---|---|
| Senhas | Hash com `password_hash` e rehash automático |
| Sessão | `HttpOnly`, `SameSite=Lax`, regeneração no login |
| CSRF | Token por sessão em todo `POST` |
| Autorização | Verificação no servidor por perfil |
| SQL | PDO com prepared statements |
| XSS | Escape na saída |
| Cabeçalhos | `nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` |
| Login | Bloqueio após 5 falhas em 15 minutos |
| Auditoria | Registro de ações sensíveis |
| IA | Propostas com aprovação humana e verificação de papel |

A matriz de permissões fica no recurso e é aplicada no controller. O mecânico vê a OS, mas só altera aquelas em que é responsável, líder ou criador. A rota também recusa a operação; esconder o botão não basta.

### 3.5.2 Integrações

| Serviço | Uso | Tratamento de falha |
|---|---|---|
| ViaCEP | Preencher endereço pelo CEP | Formulário continua editável |
| FIPE | Marca, modelo, ano e preço de referência | Cache no banco; cadastro manual disponível |
| Canal da Peça | Catálogo, ofertas e compatibilidade | Cache em arquivo e *fallback* para cadastro manual |
| Groq | Assistente, transcrição e textos assistidos | Módulo fica inativo; restante do sistema funciona |
| Cloudflare Turnstile | Proteção do login | Captcha desativado se as chaves não existirem |

Nenhuma integração é obrigatória. Sem internet ou sem chave de API, o fluxo de OS, orçamento e entrega continua funcionando.

## 3.6 Recursos e ferramentas

| Recurso | Uso |
|---|---|
| PHP 8.2 | Linguagem da aplicação |
| MySQL 8.4 | Banco de dados relacional |
| Apache | Servidor web e reescrita de URL |
| Bootstrap 5 | Layout responsivo e componentes |
| Chart.js | Gráficos do dashboard e dos relatórios |
| Composer | Dependências PHP |
| Git e GitHub | Versionamento e histórico |
| Docker e Docker Compose | Ambiente reproduzível |
| PlantUML | Diagramas UML e de banco |
| phpMyAdmin | Inspeção do banco em desenvolvimento |
| Groq API | Modelos de linguagem do assistente |
| python-docx, Pillow e PyMuPDF | Geração e conferência do documento |
| LibreOffice | Conversão do DOCX para PDF |

## 3.7 Funcionalidades implementadas

A tabela lista o que está pronto e o que ficou parcial.

| Módulo | Funcionalidade | Estado |
|---|---|---|
| Acesso | Login, logout, sessão, bloqueio por tentativas | Implementado |
| Funcionários | CRUD, perfis, salário, admissão, desativação | Implementado |
| Clientes | CRUD, CPF, endereço por CEP, histórico | Implementado |
| Veículos | CRUD, placa, FIPE, KM, chassi | Implementado |
| Ordens de serviço | Abertura, diagnóstico, tarefas, equipe, status, histórico | Implementado |
| Orçamentos | Itens, desconto, validade, status, impressão | Implementado |
| Peças | Catálogo, categorias, preços, estoque mínimo | Implementado |
| Fornecedores | CRUD e vínculo com pedidos | Implementado |
| Pedidos de peças | Itens, fornecedor, previsão, fluxo de status | Implementado |
| Tickets | Conversa por OS, anexos, leitura, notificações | Implementado |
| Dashboard | KPIs, faturamento, ticket médio, fila de OS | Implementado |
| Relatórios | Período, totais e exportação CSV | Implementado |
| Auditoria | Filtros, detalhes e links para o registro | Implementado |
| Configurações | Dados da oficina, tema, SEO, termos | Implementado |
| Assistente de IA | Chat, ferramentas, propostas e aprovação | Implementado |
| Preenchimento assistido | Cliente, veículo e nova OS | Implementado |
| Estoque | Saldo e mínimo no cadastro | Parcial |
| Baixa automática de estoque | Movimento na execução da OS | Não implementado |
| Backup | Rotina automática no sistema | Não implementado |

As entregas ficaram no Git em ciclos diários entre 6 e 13 de novembro de 2026. Cada módulo foi revisado antes do fechamento da versão de apresentação.

## 3.8 Interface do usuário

As telas vêm da base de demonstração, com dados fictícios. A cor da marca é configurável e o layout se adapta ao tamanho da tela.

![Figura 16 — Tela de login.](imagens/prints/print-01-login.png)

![Figura 17 — Dashboard administrativo com indicadores e fila de OS.](imagens/prints/print-02-dashboard.png)

![Figura 18 — Listagem de clientes com filtros e ações.](imagens/prints/print-03-clientes.png)

![Figura 19 — Cadastro de veículo com dados FIPE.](imagens/prints/print-04-veiculo.png)

![Figura 20 — Abertura de ordem de serviço em etapas.](imagens/prints/print-05-os-wizard.png)

![Figura 21 — Detalhe da ordem de serviço.](imagens/prints/print-06-os-detalhe.png)

![Figura 22 — Orçamento com itens de peça e mão de obra.](imagens/prints/print-07-orcamento.png)

![Figura 23 — Catálogo de peças.](imagens/prints/print-08-pecas.png)

![Figura 24 — Pedido de peças vinculado à OS.](imagens/prints/print-09-pedido.png)

![Figura 25 — Tickets de atendimento interno.](imagens/prints/print-10-tickets.png)

![Figura 26 — Relatórios por período.](imagens/prints/print-11-relatorios.png)

![Figura 27 — Configurações da oficina e do tema.](imagens/prints/print-12-configuracoes.png)

![Figura 28 — Auditoria de ações.](imagens/prints/print-13-auditoria.png)

![Figura 29 — Assistente de IA com proposta de alteração.](imagens/prints/print-14-assistente.png)

![Figura 30 — Interface em dispositivo móvel.](imagens/prints/print-15-mobile.png)
