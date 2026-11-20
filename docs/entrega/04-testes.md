# Testes e Qualidade

## 4.1 Estratégia de testes

A validação foi manual e exploratória, com roteiros tirados dos casos de uso e das regras de negócio. Cada fluxo teve um cenário de sucesso e pelo menos um de exceção, executados sobre a base de demonstração.

Os testes cobriram:

1. Fluxo funcional: login, cadastro, abertura de OS, orçamento, pedido, entrega, ticket e relatório.
2. Regra de negócio: transições de status, bloqueio de edição de orçamento aprovado, KM de saída e limite de login.
3. Autorização: rotas restritas com cada perfil.
4. Integração: ViaCEP, FIPE, Canal da Peça e Groq, inclusive sem chave ou sem rede.
5. Interface: responsividade, mensagens de erro e navegação por perfil.
6. Banco: conferência dos registros em OS, eventos, entregas, auditoria e pedidos.

A base de demonstração tem 46 clientes, 67 veículos, 182 ordens de serviço, 159 orçamentos e 2.359 peças. Isso permitiu testar listagens, filtros e paginação com volume.

## 4.2 Resultados dos testes

Os testes rodaram em 18 de novembro de 2026, no Docker, com os quatro perfis.

| ID | Caso de teste | Resultado |
|---|---|---|
| CT01 | Login com credenciais válidas | Aprovado |
| CT02 | Login com senha incorreta e bloqueio após 5 falhas | Aprovado |
| CT03 | Mecânico acessando a gestão de funcionários | Aprovado (403) |
| CT04 | Cadastro de cliente com CPF inválido | Aprovado (bloqueia) |
| CT05 | Preenchimento de endereço pelo CEP | Aprovado |
| CT06 | Cadastro de veículo com placa inválida | Aprovado (bloqueia) |
| CT07 | Consulta de marca, modelo e valor na FIPE | Aprovado |
| CT08 | Abertura de OS com queixa e prioridade | Aprovado |
| CT09 | Transição de status não permitida | Aprovado (bloqueia) |
| CT10 | Entrega com OS fora de "Pronta para entrega" | Aprovado (bloqueia) |
| CT11 | Entrega com KM menor que o atual | Aprovado (bloqueia) |
| CT12 | Cálculo de totais do orçamento com desconto | Aprovado |
| CT13 | Edição de orçamento aprovado | Aprovado (bloqueia) |
| CT14 | Recebimento de pedido de peças grava autor e data | Aprovado |
| CT15 | Visibilidade de tickets por perfil | Aprovado |
| CT16 | Exportação de relatório em CSV | Aprovado |
| CT17 | Registro de auditoria em ações sensíveis | Aprovado |
| CT18 | Proposta da IA fica pendente até aprovação | Aprovado |
| CT19 | Aplicação de proposta sem o papel exigido | Aprovado (bloqueia) |
| CT20 | Upload acima de 25 MB | Aprovado (bloqueia) |

Ressalvas conhecidas:

- Não há suíte automatizada de testes, embora o PHPUnit esteja declarado no projeto. A validação é manual e depende do roteiro.
- O estoque não é baixado automaticamente ao executar a OS; o saldo é informativo.
- O anexo por áudio e o assistente dependem da chave da Groq. Sem a chave, o recurso fica indisponível e a interface informa o usuário.
- O backup do banco não é feito pelo sistema; usa-se `mysqldump` e o volume do Docker.

## 4.3 Garantia da qualidade

A equipe adotou estas medidas para reduzir defeitos:

- Padrão de código: PSR-4, nomes descritivos, controllers pequenos e regras de status em classes próprias.
- Validação em duas camadas: formulário e servidor, com CPF, placa, e-mail e campos obrigatórios.
- CSRF e autorização: verificados em todo `POST` e em cada recurso.
- Migrations: o schema evolui por arquivos aplicados em sequência e registrados na tabela `migrations`.
- Auditoria: as ações sensíveis deixam usuário, data e dados alterados.
- Revisão manual: cada módulo passou por revisão antes do fechamento, com correção de textos, permissões e consultas.
- Documentação: requisitos, diagramas e dicionário são gerados ou conferidos a partir do código e do banco.

## 4.4 Requisitos mínimos de hardware e software

**Cliente**

| Item | Mínimo | Recomendado |
|---|---|---|
| Processador | Dual-core | Quad-core |
| Memória | 4 GB | 8 GB |
| Tela | 1.280 × 720 | 1.920 × 1.080 |
| Navegador | Chrome, Edge, Firefox ou Safari atualizados | Última versão |
| Conexão | 5 Mbps | 20 Mbps |

**Servidor**

| Item | Mínimo | Recomendado |
|---|---|---|
| Processador | 2 vCPU | 4 vCPU |
| Memória | 4 GB | 8 GB |
| Disco | 20 GB | 50 GB |
| Sistema | Linux com Docker 24+ | Linux com Docker Compose v2 |
| Software | PHP 8.2, MySQL 8.4, Apache | Mesma stack em container |

## 4.5 Contrato para desenvolvimento de software

**CONTRATO DE DESENVOLVIMENTO DE SOFTWARE**

**Contratante:** Auto Mecânica Leme, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 00.000.000/0001-00, com sede em Leme/SP.

**Contratada:** Team Zenitec, empresa fictícia constituída para fins acadêmicos no âmbito do Projeto Integrador da Fatec Araras.

**Cláusula 1ª — Objeto.** A Contratada desenvolverá o sistema Torque — Sistema de Gestão, aplicação web para gestão de oficina mecânica, conforme escopo descrito no capítulo 2 deste documento.

**Cláusula 2ª — Escopo.** O objeto compreende os módulos de acesso, clientes, veículos, ordens de serviço, orçamentos, peças, fornecedores, pedidos de peças, tickets, relatórios, configurações, auditoria e assistente de IA, excluídos os itens listados como fora do escopo.

**Cláusula 3ª — Prazo.** O desenvolvimento será entregue até 24 de novembro de 2026, admitida a prorrogação por acordo entre as partes.

**Cláusula 4ª — Obrigações da Contratada.** Entregar o sistema funcional, a documentação técnica e o manual de uso; prestar suporte à implantação; manter sigilo sobre os dados da Contratante.

**Cláusula 5ª — Obrigações da Contratante.** Fornecer as informações necessárias ao levantamento; validar os requisitos e as entregas; designar um responsável para acompanhar o projeto.

**Cláusula 6ª — Propriedade intelectual.** O código-fonte e a documentação pertencem à Contratada, que concede à Contratante licença de uso não exclusiva, sem direito de revenda.

**Cláusula 7ª — Confidencialidade.** As partes se comprometem a não divulgar dados comerciais, pessoais ou técnicos obtidos durante o projeto, salvo por obrigação legal.

**Cláusula 8ª — Aceite.** A entrega será considerada aceita após a validação dos fluxos descritos no capítulo 4, com registro das ressalvas conhecidas.

**Cláusula 9ª — Garantia.** A Contratada corrigirá defeitos decorrentes da implementação pelo prazo de 30 dias após o aceite, sem custo adicional.

**Cláusula 10ª — Foro.** Fica eleito o foro da Comarca de Araras/SP para dirimir eventuais controvérsias.

Araras, 24 de novembro de 2026.

______________________________________
Auto Mecânica Leme (Contratante)

______________________________________
Team Zenitec (Contratada)
