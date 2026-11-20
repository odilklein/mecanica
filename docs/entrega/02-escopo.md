# Escopo do sistema

O **Torque** é um sistema web para a operação interna de uma oficina mecânica. O atendimento gira em torno da ordem de serviço: nela ficam o cliente, o veículo, o diagnóstico, as tarefas, as peças e a entrega. Fora da OS, o sistema mantém cadastros, orçamentos, pedidos, tickets, relatórios, auditoria e um assistente de IA que só altera dados com aprovação.

O acesso é interno, pelo navegador, com quatro perfis: administrador, secretaria, mecânico-chefe e mecânico. A instalação usa Docker.

## 2.1 Objetivos do projeto

- Desenvolver um sistema web para auxiliar no gerenciamento das atividades de uma oficina mecânica.
- Concentrar o atendimento e o acompanhamento dos serviços na ordem de serviço.
- Cadastrar e manter clientes, veículos e funcionários.
- Registrar diagnóstico, tarefas e andamento dos serviços.
- Solicitar, validar, pedir e receber peças.
- Emitir orçamentos com itens de peça e mão de obra, desconto, validade e impressão.
- Acompanhar a OS até a entrega do veículo ao cliente, com registro de quem retirou e do KM de saída.
- Guardar o histórico de cada cliente e de cada veículo.
- Separar o acesso por perfil.
- Registrar auditoria e emitir relatórios por período.
- Substituir cadernos e planilhas por um fluxo único.

## 2.2 Técnica de levantamento de requisitos

O levantamento teve três frentes: entrevista semiestruturada com o responsável pela oficina, observação do atendimento e análise dos controles usados no dia a dia (caderno, planilhas e conversas de aplicativo).

A entrevista partiu de um roteiro com perguntas abertas sobre a chegada do cliente, a abertura do serviço, a comunicação com o mecânico, a aprovação do orçamento, a compra de peças e a entrega do veículo. As respostas foram registradas em ata e convertidas em requisitos.

Na observação, o grupo acompanhou o caminho de um atendimento completo, do recebimento do veículo à entrega, e anotou os pontos em que a informação era repetida, perdida ou registrada em mais de um lugar. Também analisou as planilhas de controle e os modelos de orçamento que a oficina já usava.

O resultado foi consolidado em requisitos funcionais, requisitos não funcionais e regras de negócio. As funcionalidades foram priorizadas em essenciais, importantes e desejáveis, considerando o prazo do semestre e o impacto na operação. O grupo validou cada requisito com o cliente antes da implementação, quando havia tempo.

## 2.3 Requisitos funcionais

Os requisitos funcionais dizem o que o sistema faz e como reage às entradas. A prioridade mostra o que não pode faltar na operação.

### RF01 — Autenticação — Essencial

O sistema deve permitir que os usuários realizem login com e-mail e senha, mantendo a sessão ativa e encerrando-a no logout. Após cinco falhas do mesmo e-mail no mesmo endereço IP em quinze minutos, o acesso deve ser temporariamente bloqueado.

### RF02 — Controle de usuários — Essencial

O sistema deve permitir que o administrador cadastre, edite, desative e redefina o perfil de funcionários, informando nome, e-mail, CPF, telefone, cargo, salário e data de admissão.

### RF03 — Controle de acesso — Essencial

O sistema deve restringir as funcionalidades de acordo com o perfil do usuário, verificando a permissão no servidor e não apenas ocultando opções na interface.

### RF04 — Cadastro de clientes — Essencial

O sistema deve permitir cadastrar, consultar, editar e desativar clientes, com validação de CPF e preenchimento automático do endereço a partir do CEP.

### RF05 — Cadastro de veículos — Essencial

O sistema deve permitir cadastrar e gerenciar veículos vinculados a um cliente, com validação de placa nos formatos antigo e Mercosul, além de consulta à tabela FIPE para marca, modelo, ano e valor de referência.

### RF06 — Ordem de serviço — Essencial

O sistema deve permitir abrir, consultar, editar e encerrar ordens de serviço, com número sequencial, cliente, veículo, prioridade, queixa inicial e responsável técnico.

### RF07 — Diagnóstico — Essencial

O sistema deve permitir registrar a queixa inicial, o diagnóstico técnico e as observações internas da OS, mantendo o histórico das alterações.

### RF08 — Tarefas e equipe — Importante

O sistema deve permitir cadastrar tarefas, atribuir responsáveis, definir prazos e acompanhar a conclusão de cada tarefa da OS.

### RF09 — Acompanhamento da OS — Essencial

O sistema deve permitir acompanhar o status da OS por um fluxo controlado, registrando cada mudança em um histórico com data, autor e descrição.

### RF10 — Peças e catálogo — Importante

O sistema deve permitir gerenciar o catálogo de peças, com nome, marca, código, categoria, preços de venda e custo, estoque, estoque mínimo e dados de compatibilidade.

### RF11 — Pedidos de peças — Importante

O sistema deve permitir solicitar peças vinculadas a uma OS, informar fornecedor e previsão de entrega, e acompanhar o pedido até o recebimento.

### RF12 — Orçamentos — Essencial

O sistema deve permitir criar orçamentos com itens de peça e de mão de obra, quantidade, preço unitário, desconto, validade e impressão em layout próprio.

### RF13 — Entrega do veículo — Essencial

O sistema deve permitir registrar a entrega ou a retirada do veículo, com modalidade, responsável, destino e KM de saída, atualizando o KM do veículo e o status da OS.

### RF14 — Histórico e auditoria — Importante

O sistema deve disponibilizar o histórico de eventos da OS e registrar em auditoria as ações sensíveis realizadas pelos usuários.

### RF15 — Notificações — Desejável

O sistema deve notificar os usuários sobre eventos da operação, como mudança de status, pedido recebido e novas mensagens, com marcação de leitura.

### RF16 — Tickets internos — Importante

O sistema deve permitir a conversa interna por OS em tickets, com mensagens, anexos, controle de leitura e visibilidade restrita aos envolvidos.

### RF17 — Relatórios — Importante

O sistema deve emitir relatórios por período, com totais de atendimentos, faturamento, ticket médio e exportação em CSV.

### RF18 — Assistente de IA — Desejável

O sistema deve oferecer um assistente capaz de consultar os dados da oficina, preencher formulários para revisão e gerar textos de comunicação, aplicando alterações somente após aprovação humana.

## 2.4 Requisitos não funcionais

Os requisitos não funcionais tratam de qualidade, segurança, desempenho e manutenção.

### RNF01 — Segurança — Essencial

As senhas devem ser armazenadas com hash e nunca em texto puro. As consultas ao banco devem usar prepared statements, e as saídas HTML devem ser escapadas para evitar injeção de SQL e XSS.

### RNF02 — Autenticação e sessão — Essencial

A sessão deve usar cookies HttpOnly e SameSite, regenerar o identificador no login e invalidar o acesso no logout. As requisições de escrita devem exigir token CSRF.

### RNF03 — Controle de acesso — Essencial

Cada recurso deve declarar os perfis autorizados. A verificação deve ocorrer no servidor, retornando erro 403 quando o usuário não tiver permissão.

### RNF04 — Usabilidade — Importante

A interface deve ser responsiva, com navegação por perfil, busca global, mensagens de retorno claras e tema de cor configurável.

### RNF05 — Desempenho — Importante

As listagens devem ser paginadas no servidor e as consultas frequentes devem usar índices. Integrações externas devem usar cache para reduzir latência.

### RNF06 — Disponibilidade — Essencial

O ambiente deve ser reproduzível com Docker Compose, com healthcheck do banco e reinício automático dos serviços.

### RNF07 — Integridade dos dados — Essencial

As relações devem ser protegidas por chaves estrangeiras e as operações que alteram mais de uma tabela devem usar transação.

### RNF08 — Rastreabilidade — Importante

As ações sensíveis devem registrar usuário, data, entidade, identificador e dados alterados, permitindo reconstruir o histórico.

### RNF09 — Backup — Importante

O banco deve poder ser copiado e restaurado por ferramenta padrão (mysqldump), com rotina definida pela infraestrutura. O sistema não executa backup automático nesta versão.

### RNF10 — Manutenibilidade — Importante

O código deve seguir a PSR-4, organizar-se em módulos com manifesto e evoluir por migrations versionadas.

### RNF11 — Compatibilidade — Importante

O sistema deve funcionar nos navegadores modernos (Chrome, Edge, Firefox e Safari), sem instalação de plugins ou aplicativos no cliente.

### RNF12 — Escalabilidade — Desejável

Novos módulos devem poder ser adicionados sem alterar o núcleo, desde que respeitem o contrato de provider e o manifesto do módulo.

## 2.5 Comparativo entre sistemas

Para situar o Torque no mercado, a equipe comparou suas funcionalidades com as de duas soluções comerciais para oficinas mecânicas: o Bruning Eixo e o Actana ERP. A comparação considera apenas recursos divulgados publicamente pelos fornecedores e não avalia preço, suporte ou qualidade de implementação.

Na Tabela 1, **X** indica que o recurso existe e **–** que não foi identificado na documentação pública.

Tabela 1 – Comparativo das funcionalidades da aplicação

| Funcionalidade | Bruning Eixo | Actana ERP | Torque |
|---|---|---|---|
| Ordem de serviço digital | X | X | X |
| Orçamento com itens e impressão | X | X | X |
| Cadastro de clientes e veículos | X | X | X |
| Histórico por veículo | X | X | X |
| Catálogo de peças e estoque | X | X | X |
| Pedido de peças vinculado à OS | X | X | X |
| Controle de status da OS | X | X | X |
| Checklist de inspeção | X | X | – |
| Agendamento de serviços | – | X | – |
| Contas a pagar e a receber | X | X | – |
| Emissão de NF-e / NFS-e | X | X | – |
| Aplicativo móvel | X | – | – |
| Assistente de inteligência artificial | – | – | X |
| Aprovação humana das ações da IA | – | – | X |
| Integração FIPE e consulta por placa | X | X | X |
| Perfis de acesso | X | X | X |

Fonte: elaborado pelos autores com base nos sites dos fornecedores (2026).

O Torque não compete em amplitude com os ERPs comerciais: não tem módulo fiscal, financeiro nem aplicativo nativo. O que ele oferece é um fluxo enxuto de oficina junto a um assistente de IA que apenas propõe alterações, sempre sujeitas à aprovação do usuário.

## 2.6 Cronograma

A Tabela 2 marca com **X** os períodos planejados e executados de cada atividade.

Tabela 2 – Cronograma do projeto

| Tarefa | 26/08 a 02/09 | 02/09 a 09/09 | 09/09 a 16/09 | 16/09 a 30/09 | 30/09 a 14/10 | 21/10 a 11/11 | 11/11 a 20/11 | 24/11 |
|---|---|---|---|---|---|---|---|---|
| Definição dos grupos | X | | | | | | | |
| Criação da marca | | X | | | | | | |
| Definição da empresa | | | X | | | | | |
| Escopo do sistema | | | X | X | | | | |
| Requisitos | | | X | X | X | X | | |
| Diagramas | | | | X | X | X | | |
| Protótipo | | | | X | X | X | X | |
| Documentação | | X | X | X | X | X | X | |
| Desenvolvimento | | | | X | X | X | X | |
| Entrega | | | | | | | X | |
| Apresentação | | | | | | | | X |
