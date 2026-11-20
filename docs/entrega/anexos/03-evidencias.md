# Anexo III – Evidências

**Repositório**

- Código-fonte: https://github.com/odilklein/mecanica
- Histórico de commits: https://github.com/odilklein/mecanica/commits
- Documentação técnica: pasta `docs/` do repositório
- Documentação online: https://torque-zenitec.pages.dev

**Relatório de segurança**

- Relatório completo: `docs/seguranca/RELATORIO_PENTEST_HEXSTRIKE.md`
- Artefatos brutos: `docs/seguranca/artefatos/`
- Versão online: https://torque-zenitec.pages.dev/#/engenharia/07-seguranca-ofensiva

**Ambiente de demonstração**

- Aplicação: http://localhost:8080
- Usuários de demonstração: `admin@mecanica.com` (administrador),
  `secretaria@mecanica.com` (secretaria) e `mecanico@mecanica.com` (mecânico).
  As senhas não ficam no repositório: são definidas no ambiente
  (`bin/create_admin.php` ou `SEED_PASSWORD` no seed) e trocadas no primeiro
  acesso.

**Evidências visuais**

As capturas de tela do sistema estão reunidas neste documento (Figuras 16 a 30) e nos arquivos de `docs/imagens/prints/`.

**Evidências de banco de dados**

- 39 tabelas da aplicação, 32 migrations aplicadas.
- 182 ordens de serviço, 159 orçamentos, 2.359 peças de catálogo.
- 479 registros de auditoria e 1.339 eventos de OS.

**Evidências de código**

- 91 rotas registradas nos três módulos.
- Dicionário de dados gerado do `information_schema` (`docs/engenharia/04-modelo-dados.md`).
- Diagramas com fontes versionadas em `docs/diagramas/fontes/`.

**Observação**

Todos os dados usados na demonstração são fictícios. Nenhum dado pessoal real foi utilizado no desenvolvimento ou na apresentação.
