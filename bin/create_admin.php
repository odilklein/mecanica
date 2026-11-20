<?php
// =============================================
// CLI — cria ou redefine a senha de um usuário.
//
// Uso (dentro do container):
//   php bin/create_admin.php --email=admin@mecanica.com --password='SuaSenhaForte'
//   php bin/create_admin.php --email=fulano@mecanica.com --role=mechanic --password='...'
//   php bin/create_admin.php --email=admin@mecanica.com   (gera senha aleatória)
//
// Sem --password, a senha vem de ADMIN_PASSWORD ou é gerada e impressa.
// =============================================

declare(strict_types=1);

use Kernel\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
if (is_file($basePath . '/.env')) {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

$options = [
    'email' => 'admin@mecanica.com',
    'name' => null,
    'role' => 'admin',
    'password' => null,
];

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z_]+)=(.*)$/s', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    }
}

$email = trim((string) $options['email']);
$role = trim((string) $options['role']);
$name = trim((string) ($options['name'] ?? ''));
$password = (string) ($options['password'] ?? '');

if ($password === '') {
    $password = (string) env('ADMIN_PASSWORD', '');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Informe um e-mail válido: --email=usuario@dominio.com\n");
    exit(2);
}

if ($role === '') {
    fwrite(STDERR, "Informe o perfil: --role=admin|secretary|lead_mechanic|mechanic\n");
    exit(2);
}

$generated = false;
if ($password === '') {
    $password = bin2hex(random_bytes(12));
    $generated = true;
}

if (strlen($password) < 12) {
    fwrite(STDERR, "A senha precisa ter ao menos 12 caracteres.\n");
    exit(2);
}

try {
    $pdo = Connection::fromEnv();

    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $existing = $stmt->fetch();

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($existing) {
        $update = $pdo->prepare('UPDATE users SET password = :password, role = :role, is_active = 1'
            . ($name !== '' ? ', name = :name' : '') . ' WHERE id = :id');
        $params = [':password' => $hash, ':role' => $role, ':id' => (int) $existing['id']];
        if ($name !== '') {
            $params[':name'] = $name;
        }
        $update->execute($params);
        echo "Senha redefinida para {$email} (perfil: {$role}).\n";
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, is_active) VALUES (:name, :email, :password, :role, 1)'
        );
        $insert->execute([
            ':name' => $name !== '' ? $name : ucfirst($role),
            ':email' => $email,
            ':password' => $hash,
            ':role' => $role,
        ]);
        echo "Usuário criado: {$email} (perfil: {$role}).\n";
    }

    if ($generated) {
        echo "Senha gerada: {$password}\n";
        echo "Guarde com segurança e troque no primeiro acesso.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
