<?php
// =============================================
// MIGRATION — usuário administrador inicial.
//
// Não cria mais contas com senha fixa. A senha vem de
// SEED_ADMIN_PASSWORD; se não estiver definida, uma senha aleatória é
// gerada e impressa uma única vez no console da migration.
//
// Para criar/ajustar contas depois:
//   php bin/create_admin.php --email=admin@mecanica.com --password='...'
// =============================================

return [
    'up' => [
        static function (PDO $pdo): void {
            $email = trim((string) env('SEED_ADMIN_EMAIL', 'admin@mecanica.com'));
            if ($email === '') {
                $email = 'admin@mecanica.com';
            }

            $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $exists->execute([':email' => $email]);
            if ((int) $exists->fetchColumn() > 0) {
                return;
            }

            $password = (string) env('SEED_ADMIN_PASSWORD', '');
            $generated = false;
            if ($password === '') {
                $password = bin2hex(random_bytes(12));
                $generated = true;
            }

            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password, role, is_active) VALUES (:name, :email, :password, :role, 1)'
            );
            $insert->execute([
                ':name' => 'Administrador',
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'admin',
            ]);

            if ($generated) {
                fwrite(STDERR, "\n[seed] Senha inicial do administrador ({$email}): {$password}\n");
                fwrite(STDERR, "[seed] Troque no primeiro acesso (Perfil > Alterar senha).\n\n");
            }
        },
    ],
    'down' => [],
];
