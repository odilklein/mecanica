# Considerações Finais

O sistema está em funcionamento e cobre o atendimento da oficina, do cadastro do cliente à entrega do veículo. A base de demonstração tem 39 tabelas, 91 rotas e dados suficientes para executar os fluxos de ponta a ponta.

Construir sem framework deu mais trabalho no início. Foi preciso escrever o container, o roteador, a sessão, a autorização, o CRUD e as views antes de chegar às regras da oficina. Em troca, cada uma dessas partes ficou visível no código. Na apresentação, é possível abrir o arquivo e mostrar onde a requisição entra, onde a permissão é verificada e como o CSRF é tratado.

O banco cresceu junto com o sistema, em 32 migrations. O dicionário de dados é gerado a partir do banco em execução, então não fica desatualizado quando uma tabela muda.

A IA ficou restrita a consultar, resumir e propor. Nenhuma alteração é gravada sem aprovação do usuário e checagem de permissão. Isso evita que um erro do modelo vire um orçamento ou uma OS errada.

Ficaram de fora os testes automatizados, a baixa automática de estoque, o módulo financeiro, a emissão fiscal, o aplicativo nativo e o backup feito pelo próprio sistema. O prazo do semestre foi o limite.

Trabalhos futuros:

- escrever testes automatizados para os fluxos críticos;
- ligar a baixa de estoque à execução da OS;
- criar o portal do cliente para acompanhar a OS;
- integrar o envio de mensagens por WhatsApp;
- adicionar módulo financeiro e emissão de NFS-e;
- usar o histórico do veículo para sugerir diagnósticos no assistente;
- documentar uma rotina de backup e restauração.

**Contribuições individuais**

| Integrante | Contribuição |
|---|---|
| Odil Klein da Silva Neto | Concepção do sistema, arquitetura, desenvolvimento back-end e front-end, modelagem do banco, integrações, testes e redação técnica |
| Nícolas da Cunha de Godoy | Documentação, revisão dos requisitos e conferência do texto |
| Andreo Rafael | Documentação, revisão da modelagem e conferência das tabelas |
| Alex Rodrigo Candido | Documentação, revisão das interfaces e conferência das referências |

A divisão foi acertada no início e ajustada durante o semestre. Odil concentrou o desenvolvimento e a redação técnica; Nícolas, Andreo e Alex revisaram o material e conferiram as tabelas, as telas e as referências.
