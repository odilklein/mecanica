# Auto Mecânica Leme — Sistema de Gestão de Oficina

Projeto Integrador (PI) de um sistema web para gestão de oficina mecânica.
Stack simples e funcional: **PHP (puro) + MySQL + JavaScript + HTML/CSS + Bootstrap 5**.

## Funcionalidades

- **Autenticação** com perfis: administrador, secretaria, mecânico-chefe e mecânico.
- **Clientes, veículos e funcionários** (CRUD).
- **Ordens de serviço** com wizard, diagnóstico, tarefas (com áudio), responsáveis,
  histórico de eventos e controle de status.
- **Pedidos de peças** com recebimento parcial e vínculo à OS.
- **Orçamentos** (editor, impressão e relatórios).
- **Estoque** de peças com alerta de mínimo e movimentações.
- **Notificações** internas em tempo real.
- **Auditoria** de ações sensíveis e **backup** do banco (admin).

## Requisitos

- PHP 8.1+ (extensões `pdo_mysql` e `mbstring`)
- MySQL 5.7+ / MariaDB 10.4+
- Apache com `.htaccess` habilitado (XAMPP serve)

## Instalação (XAMPP)

1. Copie o projeto para `htdocs/mecanica`.
2. Crie um banco vazio e importe `sql/mecanica_teste.sql` pelo phpMyAdmin.
3. Copie `.env.example` para `.env` e ajuste as credenciais do banco.
4. Acesse `http://localhost/mecanica/` no navegador.

Se preferir não usar `.env`, o sistema assume os padrões (`localhost`, usuário
`root`, sem senha, banco `mecanica`).

## Credenciais de teste

Todos os usuários usam a senha **admin123**:

| Perfil          | E-mail                    |
|-----------------|---------------------------|
| Administrador   | admin@mecanica.com        |
| Secretaria      | secretaria@mecanica.com   |
| Mecânico-chefe  | chefe@mecanica.com        |
| Mecânico        | mecanico1@mecanica.com    |
| Mecânico        | mecanico2@mecanica.com    |

## Estrutura

```
config/      conexão e configuração da empresa
includes/    sessão, header, sidebar, footer, helpers
pages/       módulos do sistema (um diretório por área)
api/         endpoints JSON (ex.: veículos por cliente)
css/ js/     estilos e scripts
sql/         script de criação do banco com dados de demonstração
uploads/     áudios das tarefas e backups gerados
```

## Segurança

- Senhas com `password_hash` (bcrypt).
- Prepared statements (PDO) em todas as consultas.
- Token CSRF nos formulários e endpoints de escrita.
- Controle de acesso por perfil (`exigir_papel`).
- `.htaccess` bloqueando `.env`, `.sql`, logs e acesso direto a `config/` e `sql/`.
