<?php
// =============================================
// MIGRATION — rotaciona senhas herdadas do seed antigo.
//
// As contas criadas pela versão anterior desta migration (e pelos seeds de
// demonstração) usavam a senha "admin123", publicada no README. Aqui cada
// conta que ainda aceita essa senha recebe uma senha aleatória e a credencial
// temporária é impressa uma única vez no console.
//
// Contas já rotacionadas (hash diferente) não são tocadas, então a migration
// é segura para rodar em qualquer base.
// =============================================

return [
    'up' => [
        static function (PDO $pdo): void {
            // Contas antigas compartilhavam a senha "admin123" (hash literal do
            // seed ou hash gerado em runtime por seed_demo/seed_operacao).
            // Testa a senha em cada conta para cobrir os dois casos.
            $users = $pdo->query('SELECT id, email, password FROM users ORDER BY id')->fetchAll();
            $legacyPassword = 'admin123';

            $targets = [];
            foreach ($users as $user) {
                $hash = (string) $user['password'];
                if ($hash === '' || !password_verify($legacyPassword, $hash)) {
                    continue;
                }
                $targets[] = $user;
            }

            if (!$targets) {
                return;
            }

            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
            $max = strlen($alphabet) - 1;
            $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');

            fwrite(STDERR, "\n[security] Senhas padrão (admin123) rotacionadas:\n");

            foreach ($targets as $user) {
                $password = '';
                for ($i = 0; $i < 20; $i++) {
                    $password .= $alphabet[random_int(0, $max)];
                }

                $update->execute([
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => (int) $user['id'],
                ]);

                fwrite(STDERR, sprintf("  %-32s %s\n", (string) $user['email'], $password));
            }

            fwrite(STDERR, "[security] Guarde as senhas e troque no primeiro acesso.\n\n");
        },
    ],
    'down' => [],
];
