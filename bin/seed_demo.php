<?php
// =============================================
// SEED DEMO — popula a base com dados de teste
// (users, clientes, veículos, OS, peças,
// orçamentos, notificações e auditoria).
//
// Uso (dentro do container):
//   php bin/seed_demo.php
//   php bin/seed_demo.php --fresh   (remove os dados de seed antes)
//
// Os registros criados são marcados com "seed-demo"
// (notes/internal_notes/notes) para permitir a limpeza.
// =============================================

declare(strict_types=1);

use Kernel\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
if (is_file($basePath . '/.env')) {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

$pdo = Connection::fromEnv();
$fresh = in_array('--fresh', $argv, true);

/** Executa uma query preparada. */
function q(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Busca um id por coluna única. */
function findId(PDO $pdo, string $table, string $column, string $value): ?int
{
    $stmt = q($pdo, "SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1", [$value]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

/** Gera um CPF válido determinístico para os mocks. */
function mockCpf(int $seed): string
{
    $base = str_pad((string) (100000000 + $seed * 7919), 9, '0', STR_PAD_LEFT);
    $digits = array_map('intval', str_split($base));
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += $digits[$i] * (($t + 1) - $i);
        }
        $digits[$t] = ((10 * $sum) % 11) % 10;
    }
    $raw = implode('', $digits);
    return substr($raw, 0, 3) . '.' . substr($raw, 3, 3) . '.' . substr($raw, 6, 3) . '-' . substr($raw, 9, 2);
}

function money(float $value): string
{
    return number_format($value, 2, '.', '');
}

// ---------------------------------------------------------------------
// Limpeza opcional
// ---------------------------------------------------------------------
if ($fresh) {
    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM budget_items WHERE budget_id IN (SELECT id FROM budgets WHERE notes LIKE '[seed]%')");
        $pdo->exec("DELETE FROM budgets WHERE notes LIKE '[seed]%'");
        $pdo->exec("DELETE FROM part_order_items WHERE part_order_id IN (SELECT id FROM part_orders WHERE notes LIKE '[seed]%')");
        $pdo->exec("DELETE FROM part_orders WHERE notes LIKE '[seed]%'");
        $pdo->exec("DELETE FROM service_order_events WHERE order_id IN (SELECT id FROM service_orders WHERE internal_notes LIKE '[seed]%')");
        $pdo->exec("DELETE FROM service_order_responsibles WHERE order_id IN (SELECT id FROM service_orders WHERE internal_notes LIKE '[seed]%')");
        $pdo->exec("DELETE FROM service_order_tasks WHERE order_id IN (SELECT id FROM service_orders WHERE internal_notes LIKE '[seed]%')");
        $pdo->exec("DELETE FROM service_order_deliveries WHERE order_id IN (SELECT id FROM service_orders WHERE internal_notes LIKE '[seed]%')");
        $pdo->exec("DELETE FROM service_orders WHERE internal_notes LIKE '[seed]%'");
        $pdo->exec("DELETE FROM vehicles WHERE notes = 'seed-demo'");
        $pdo->exec("DELETE FROM clients WHERE notes = 'seed-demo'");

        // Solta referências dos usuários de seed em registros que não serão apagados.
        $seedEmails = "'carlos.souza@mecanica.com','fernanda.lima@mecanica.com','rafael.alves@mecanica.com','juliana.prado@mecanica.com'";
        $seedUserIds = "SELECT id FROM users WHERE email IN ({$seedEmails})";
        $pdo->exec("UPDATE service_orders SET lead_mechanic_id = NULL WHERE lead_mechanic_id IN ({$seedUserIds})");
        $pdo->exec("UPDATE service_orders SET created_by = NULL WHERE created_by IN ({$seedUserIds})");
        $pdo->exec("UPDATE service_order_tasks SET created_by = NULL WHERE created_by IN ({$seedUserIds})");
        $pdo->exec("UPDATE service_order_events SET created_by = NULL WHERE created_by IN ({$seedUserIds})");
        $pdo->exec("UPDATE part_orders SET created_by = NULL WHERE created_by IN ({$seedUserIds})");
        $pdo->exec("UPDATE part_orders SET received_by = NULL WHERE received_by IN ({$seedUserIds})");
        $pdo->exec("UPDATE budgets SET created_by = NULL WHERE created_by IN ({$seedUserIds})");
        $pdo->exec("UPDATE audit_logs SET user_id = NULL WHERE user_id IN ({$seedUserIds})");
        $pdo->exec("DELETE FROM service_order_responsibles WHERE user_id IN ({$seedUserIds})");
        $pdo->exec("DELETE FROM notifications WHERE user_id IN ({$seedUserIds})");
        $pdo->exec("DELETE FROM users WHERE email IN ({$seedEmails})");
        $pdo->commit();
        echo "Dados de seed removidos.\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, 'Erro ao limpar: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

// ---------------------------------------------------------------------
// Seed
// ---------------------------------------------------------------------
$alreadySeeded = (int) $pdo->query(
    "SELECT COUNT(*) FROM service_orders WHERE internal_notes LIKE '[seed]%'"
)->fetchColumn();

if ($alreadySeeded > 0 && !$fresh) {
    echo "Os dados de demonstração já estão na base. Use --fresh para recriar.\n";
    exit(0);
}

$pdo->beginTransaction();

try {
    // Senha dos usuários de demonstração: --password=... ou SEED_PASSWORD.
    // Sem isso, gera uma senha aleatória e imprime no final (nunca "admin123").
    $seedPassword = '';
    foreach ($argv as $arg) {
        if (preg_match('/^--password=(.+)$/', $arg, $m) === 1) {
            $seedPassword = $m[1];
        }
    }
    if ($seedPassword === '') {
        $seedPassword = (string) env('SEED_PASSWORD', '');
    }
    $seedPasswordGenerated = false;
    if ($seedPassword === '') {
        $seedPassword = bin2hex(random_bytes(9));
        $seedPasswordGenerated = true;
    }

    $hash = password_hash($seedPassword, PASSWORD_DEFAULT);
    $now = new DateTimeImmutable('now');

    // ---------------- Usuários ----------------
    $seedUsers = [
        ['Carlos Souza', 'carlos.souza@mecanica.com', 'lead_mechanic', 'Mecânico-chefe', 6500.00, '2019-03-12'],
        ['Fernanda Lima', 'fernanda.lima@mecanica.com', 'mechanic', 'Mecânica', 4200.00, '2021-07-01'],
        ['Rafael Alves', 'rafael.alves@mecanica.com', 'mechanic', 'Mecânico', 3900.00, '2022-01-17'],
        ['Juliana Prado', 'juliana.prado@mecanica.com', 'secretary', 'Recepcionista', 3100.00, '2023-05-02'],
    ];

    $userIds = [];
    foreach ($seedUsers as $i => [$name, $email, $role, $job, $salary, $hire]) {
        q(
            $pdo,
            "INSERT IGNORE INTO users (name, email, password, role, cpf, phone, salary, hire_date, job_title, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $name,
                $email,
                $hash,
                $role,
                mockCpf(20 + $i),
                '(13) 98811-' . str_pad((string) (1001 + $i), 4, '0', STR_PAD_LEFT),
                money($salary),
                $hire,
                $job,
            ]
        );
        $userIds[$email] = findId($pdo, 'users', 'email', $email);
    }

    $adminId = findId($pdo, 'users', 'email', 'admin@mecanica.com') ?: 1;
    $mechanicIds = array_values(array_filter([
        $userIds['carlos.souza@mecanica.com'] ?? null,
        $userIds['fernanda.lima@mecanica.com'] ?? null,
        $userIds['rafael.alves@mecanica.com'] ?? null,
        findId($pdo, 'users', 'email', 'mecanico@mecanica.com'),
    ]));

    // ---------------- Clientes ----------------
    $seedClients = [
        ['Ana Paula Ribeiro', '(13) 99101-0001', 'ana.ribeiro@email.com', '11010-000', 'Av. Ana Costa', '120', 'Gonzaga', 'Santos', 'SP'],
        ['Bruno Carvalho', '(13) 99101-0002', 'bruno.carvalho@email.com', '11320-000', 'Rua das Palmeiras', '45', 'Centro', 'São Vicente', 'SP'],
        ['Camila Fernandes', '(13) 99101-0003', 'camila.fernandes@email.com', '11700-000', 'Av. Presidente Kennedy', '890', 'Guilhermina', 'Praia Grande', 'SP'],
        ['Diego Martins', '(13) 99101-0004', 'diego.martins@email.com', '11060-000', 'Rua Carvalho de Mendonça', '310', 'Vila Belmiro', 'Santos', 'SP'],
        ['Eduarda Nogueira', '(13) 99101-0005', 'eduarda.nogueira@email.com', '11500-000', 'Rua São João', '77', 'Centro', 'Cubatão', 'SP'],
        ['Felipe Barbosa', '(13) 99101-0006', 'felipe.barbosa@email.com', '11410-000', 'Av. Santos Dumont', '221', 'Pitangueiras', 'Guarujá', 'SP'],
        ['Gabriela Rocha', '(13) 99101-0007', 'gabriela.rocha@email.com', '11045-000', 'Rua Galeão Coutinho', '18', 'Boqueirão', 'Santos', 'SP'],
        ['Henrique Dias', '(13) 99101-0008', 'henrique.dias@email.com', '11340-000', 'Rua Frei Gaspar', '502', 'Centro', 'São Vicente', 'SP'],
        ['Isabela Teixeira', '(13) 99101-0009', 'isabela.teixeira@email.com', '11720-000', 'Av. Walter José de Souza', '140', 'Tupi', 'Praia Grande', 'SP'],
        ['João Pedro Almeida', '(13) 99101-0010', 'joao.almeida@email.com', '11030-000', 'Rua XV de Novembro', '66', 'Centro', 'Santos', 'SP'],
        ['Larissa Moreira', '(13) 99101-0011', 'larissa.moreira@email.com', '11510-000', 'Av. Nove de Abril', '950', 'Centro', 'Cubatão', 'SP'],
        ['Marcos Vinícius', '(13) 99101-0012', 'marcos.vinicius@email.com', '11420-000', 'Rua Mario Ribeiro', '333', 'Enseada', 'Guarujá', 'SP'],
    ];

    $clientIds = [];
    foreach ($seedClients as $i => [$name, $phone, $email, $zip, $address, $number, $neighborhood, $city, $state]) {
        $cpf = mockCpf(40 + $i);
        q(
            $pdo,
            "INSERT IGNORE INTO clients (name, cpf, phone, email, zip, address, address_number, neighborhood, city, state, notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'seed-demo', 1)",
            [$name, $cpf, $phone, $email, $zip, $address, $number, $neighborhood, $city, $state]
        );
        $clientIds[] = findId($pdo, 'clients', 'cpf', $cpf);
    }
    $clientIds = array_values(array_filter($clientIds));

    // ---------------- Veículos ----------------
    $seedVehicles = [
        [0, 'BRA-2E19', 'VW - VolksWagen', '59', '5585', 'Gol 1.0 Flex', 2013, '2013-3', 38900.00, 'Prata', 98000],
        [1, 'FGH-4J21', 'Fiat', '21', '11401', 'Argo Drive 1.0', 2020, '2020-3', 68900.00, 'Branco', 42000],
        [2, 'JKL-6M23', 'GM - Chevrolet', '23', '10695', 'Onix 1.0 Turbo', 2022, '2022-3', 82900.00, 'Azul', 31000],
        [3, 'MNO-8P25', 'Ford', '22', '8440', 'Ka SE 1.5', 2018, '2018-3', 45900.00, 'Vermelho', 76000],
        [4, 'PQR-2S27', 'Toyota', '56', '3236', 'Corolla XEi 2.0', 2021, '2021-3', 132900.00, 'Preto', 38000],
        [5, 'STU-4V29', 'Honda', '25', '5982', 'Civic EXL 2.0', 2020, '2020-3', 118500.00, 'Cinza', 51000],
        [6, 'VWX-6Y31', 'Hyundai', '26', '10063', 'HB20 Comfort 1.0', 2019, '2019-3', 58900.00, 'Prata', 64000],
        [7, 'YZA-8B33', 'Renault', '48', '8075', 'Sandero Stepway', 2018, '2018-3', 49900.00, 'Laranja', 88000],
        [8, 'BCD-2E35', 'Nissan', '43', '9876', 'Kicks SV 1.6', 2021, '2021-3', 94900.00, 'Branco', 29000],
        [9, 'EFG-4H37', 'Jeep', '29', '7895', 'Renegade Sport 1.8', 2020, '2020-3', 96900.00, 'Verde', 47000],
        [10, 'HIJ-6K39', 'VW - VolksWagen', '59', '11036', 'T-Cross Highline 1.4', 2022, '2022-3', 139900.00, 'Azul', 22000],
        [11, 'KLM-8N41', 'Fiat', '21', '9493', 'Toro Endurance 1.3', 2021, '2021-3', 118900.00, 'Cinza', 54000],
        [12, 'NOP-2Q43', 'GM - Chevrolet', '23', '11264', 'Tracker Premier 1.2', 2022, '2022-3', 124900.00, 'Vermelho', 19000],
        [13, 'QRS-4T45', 'Toyota', '56', '10123', 'Yaris XL 1.5', 2020, '2020-3', 78900.00, 'Prata', 36000],
    ];

    $vehicleIds = [];
    foreach ($seedVehicles as [$clientIdx, $plate, $brand, $brandCode, $modelCode, $model, $year, $yearCode, $fipeValue, $color, $km]) {
        $clientId = $clientIds[$clientIdx] ?? $clientIds[0];
        q(
            $pdo,
            "INSERT IGNORE INTO vehicles
                (client_id, plate, brand, model, year, color, current_km, notes,
                 fipe_brand_code, fipe_model_code, fipe_year_code, fipe_year, fipe_value, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'seed-demo', ?, ?, ?, ?, ?, 1)",
            [$clientId, $plate, $brand, $model, $year, $color, $km, $brandCode, $modelCode, $yearCode, $year, money($fipeValue)]
        );
        $id = findId($pdo, 'vehicles', 'plate', $plate);
        if ($id) {
            $vehicleIds[] = ['id' => $id, 'client_id' => $clientId];
        }
    }
    $vehicleIds = array_values(array_filter($vehicleIds));

    // ---------------- OS ----------------
    $statuses = ['received', 'in_analysis', 'awaiting_approval', 'awaiting_part', 'in_progress', 'ready_for_delivery', 'delivered', 'cancelled'];
    $priorities = ['low', 'normal', 'high', 'urgent'];
    $complaints = [
        'Barulho na suspensão ao passar em lombadas.',
        'Luz de injeção acesa no painel.',
        'Ar-condicionado não está gelando.',
        'Freio fazendo ruído ao frear.',
        'Vazamento de óleo na parte inferior do motor.',
        'Carro não liga pela manhã.',
        'Direção puxando para a direita.',
        'Superaquecimento em congestionamento.',
    ];
    $diagnoses = [
        'Buchas da suspensão dianteira desgastadas.',
        'Sensor de oxigênio com falha intermitente.',
        'Falta de gás e filtro do ar-condicionado saturado.',
        'Pastilhas de freio no limite de desgaste.',
        'Junta do cárter ressecada.',
        'Bateria com baixa capacidade de partida.',
        'Alinhamento e balanceamento fora do padrão.',
        'Válvula termostática travada.',
    ];

    $nextOs = (int) ($pdo->query('SELECT COALESCE(MAX(number), 0) FROM service_orders')->fetchColumn()) + 1;
    $osIds = [];
    $osCount = 18;

    for ($i = 0; $i < $osCount; $i++) {
        $vehicle = $vehicleIds[$i % count($vehicleIds)];
        $status = $statuses[$i % count($statuses)];
        $priority = $priorities[$i % count($priorities)];
        $daysAgo = 65 - ($i * 3);
        $entry = $now->modify('-' . max(1, $daysAgo) . ' days')->format('Y-m-d');
        $completion = in_array($status, ['delivered', 'ready_for_delivery'], true)
            ? $now->modify('-' . max(0, $daysAgo - 2) . ' days')->format('Y-m-d')
            : null;
        $lead = $mechanicIds[$i % count($mechanicIds)];

        q(
            $pdo,
            "INSERT INTO service_orders
                (number, client_id, vehicle_id, status, priority, initial_complaint, internal_notes, diagnosis,
                 entry_date, completion_date, lead_mechanic_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $nextOs + $i,
                $vehicle['client_id'],
                $vehicle['id'],
                $status,
                $priority,
                $complaints[$i % count($complaints)],
                '[seed] Atendimento gerado para demonstração.',
                $diagnoses[$i % count($diagnoses)],
                $entry,
                $completion,
                $lead,
                $adminId,
            ]
        );
        $orderId = (int) $pdo->lastInsertId();
        $osIds[] = $orderId;

        // Tarefas
        $taskLabels = ['Diagnóstico completo', 'Revisar freios', 'Trocar óleo e filtros', 'Alinhamento e balanceamento', 'Inspeção da suspensão'];
        $taskStatus = in_array($status, ['delivered', 'ready_for_delivery'], true)
            ? 'done'
            : ($status === 'in_progress' ? 'in_progress' : 'pending');
        for ($t = 0; $t < 3; $t++) {
            q(
                $pdo,
                'INSERT INTO service_order_tasks (order_id, description, status, created_by) VALUES (?, ?, ?, ?)',
                [$orderId, $taskLabels[($i + $t) % count($taskLabels)], $taskStatus, $lead]
            );
        }

        // Responsáveis
        q($pdo, 'INSERT IGNORE INTO service_order_responsibles (order_id, user_id) VALUES (?, ?)', [$orderId, $lead]);
        $second = $mechanicIds[($i + 1) % count($mechanicIds)];
        q($pdo, 'INSERT IGNORE INTO service_order_responsibles (order_id, user_id) VALUES (?, ?)', [$orderId, $second]);

        // Eventos
        q(
            $pdo,
            'INSERT INTO service_order_events (order_id, type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
            [$orderId, 'created', 'OS criada (seed).', $adminId, $entry . ' 09:15:00']
        );
        q(
            $pdo,
            'INSERT INTO service_order_events (order_id, type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
            [$orderId, 'status', 'Status atualizado para ' . $status . '.', $lead, $entry . ' 11:40:00']
        );

        // Entrega
        if ($status === 'delivered') {
            q(
                $pdo,
                "INSERT INTO service_order_deliveries (order_id, modality, responsible, destination, exit_km, status)
                 VALUES (?, 'pickup', 'Cliente', 'Oficina', ?, 'delivered')",
                [$orderId, 42000 + ($i * 1300)]
            );
        }
    }

    // ---------------- Pedidos de peças ----------------
    $suppliers = ['Auto Peças Silva', 'Distribuidora Central', 'Peças & Cia', 'Oficina Brasil Parts'];
    $partStatuses = ['requested', 'validated', 'ordered', 'on_the_way', 'received'];
    $partNames = ['Pastilha de freio', 'Filtro de óleo', 'Filtro de ar', 'Amortecedor', 'Correia dentada', 'Bomba d\'água', 'Sensor de oxigênio', 'Kit embreagem', 'Bateria 60Ah', 'Radiador'];

    foreach (array_slice($osIds, 0, 9) as $n => $orderId) {
        $partStatus = $partStatuses[$n % count($partStatuses)];
        $received = $partStatus === 'received';
        q(
            $pdo,
            "INSERT INTO part_orders (service_order_id, supplier, status, expected_date, notes, created_by, received_by, received_at)
             VALUES (?, ?, ?, ?, '[seed] Pedido de peças para demonstração.', ?, ?, ?)",
            [
                $orderId,
                $suppliers[$n % count($suppliers)],
                $partStatus,
                $now->modify('+' . (3 + $n) . ' days')->format('Y-m-d'),
                $adminId,
                $received ? $adminId : null,
                $received ? $now->modify('-1 day')->format('Y-m-d H:i:s') : null,
            ]
        );
        $partOrderId = (int) $pdo->lastInsertId();
        for ($p = 0; $p < 3; $p++) {
            q(
                $pdo,
                'INSERT INTO part_order_items (part_order_id, description, quantity) VALUES (?, ?, ?)',
                [$partOrderId, $partNames[($n + $p) % count($partNames)], 1 + (($n + $p) % 2)]
            );
        }
    }

    // ---------------- Orçamentos ----------------
    $nextBudget = (int) ($pdo->query('SELECT COALESCE(MAX(number), 0) FROM budgets')->fetchColumn()) + 1;
    $budgetStatuses = ['open', 'approved', 'rejected', 'cancelled'];
    $partItems = ['Filtro de óleo', 'Óleo 5W30 (4L)', 'Pastilha de freio', 'Correia dentada', 'Bateria 60Ah'];
    $serviceItems = ['Mão de obra - diagnóstico', 'Mão de obra - troca de óleo', 'Mão de obra - freios', 'Alinhamento e balanceamento', 'Revisão geral'];

    foreach (array_slice($osIds, 0, 10) as $n => $orderId) {
        $order = q($pdo, 'SELECT client_id, vehicle_id FROM service_orders WHERE id = ?', [$orderId])->fetch();
        if (!$order) {
            continue;
        }
        $status = $budgetStatuses[$n % count($budgetStatuses)];
        $discount = (float) (10 * ($n % 4));
        $partsTotal = 0.0;
        $laborTotal = 0.0;

        $budgetDate = $now->modify('-' . (40 - $n * 2) . ' days')->format('Y-m-d');
        $validUntil = $now->modify('+' . (10 + $n) . ' days')->format('Y-m-d');

        q(
            $pdo,
            "INSERT INTO budgets
                (number, client_id, vehicle_id, service_order_id, budget_date, valid_until, status, discount,
                 parts_total, labor_total, total, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, '[seed] Orçamento para demonstração.', ?)",
            [
                $nextBudget + $n,
                $order['client_id'],
                $order['vehicle_id'],
                $orderId,
                $budgetDate,
                $validUntil,
                $status,
                money($discount),
                $adminId,
            ]
        );
        $budgetId = (int) $pdo->lastInsertId();

        $lineCount = 3 + ($n % 3);
        for ($line = 0; $line < $lineCount; $line++) {
            $isPart = $line % 2 === 0;
            $desc = $isPart
                ? $partItems[($n + $line) % count($partItems)]
                : $serviceItems[($n + $line) % count($serviceItems)];
            $qty = $isPart ? (1 + ($line % 3)) : 1;
            $price = $isPart ? (35.0 + (($n + $line) * 17.5)) : (80.0 + (($n + $line) * 25.0));
            $subtotal = round($qty * $price, 2);
            if ($isPart) {
                $partsTotal += $subtotal;
            } else {
                $laborTotal += $subtotal;
            }
            q(
                $pdo,
                'INSERT INTO budget_items (budget_id, type, description, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)',
                [$budgetId, $isPart ? 'part' : 'service', $desc, $qty, money($price), money($subtotal)]
            );
        }

        $total = max(0, $partsTotal + $laborTotal - $discount);
        q(
            $pdo,
            'UPDATE budgets SET parts_total = ?, labor_total = ?, total = ? WHERE id = ?',
            [money($partsTotal), money($laborTotal), money($total), $budgetId]
        );
    }

    // ---------------- Notificações ----------------
    $notifUsers = array_values(array_filter(array_merge([$adminId], $mechanicIds)));
    $notifTypes = ['os_created', 'os_status', 'part', 'task'];
    $notifMessages = [
        'Você foi designado para uma nova ordem de serviço.',
        'O status de uma OS foi atualizado.',
        'Um pedido de peças foi recebido.',
        'Uma nova tarefa foi adicionada à sua OS.',
    ];

    foreach (array_slice($osIds, 0, 12) as $n => $orderId) {
        $userId = $notifUsers[$n % count($notifUsers)];
        $notifType = $notifTypes[$n % count($notifTypes)];
        $creatorId = $notifType === 'os_created'
            ? $adminId
            : $mechanicIds[$n % count($mechanicIds)];

        q(
            $pdo,
            'INSERT INTO notifications (user_id, created_by, type, message, link, read_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $creatorId,
                $notifType,
                $notifMessages[$n % count($notifMessages)] . ' (OS #' . ($nextOs + ($n % $osCount)) . ')',
                '/service-orders/' . $orderId,
                $n % 3 === 0 ? $now->modify('-1 hour')->format('Y-m-d H:i:s') : null,
                $now->modify('-' . $n . ' hours')->format('Y-m-d H:i:s'),
            ]
        );
    }

    // ---------------- Auditoria ----------------
    $auditEntities = ['service_orders', 'budgets', 'clients', 'vehicles', 'users'];
    $auditActions = ['create', 'update', 'status', 'delete'];
    foreach (array_slice($osIds, 0, 14) as $n => $orderId) {
        q(
            $pdo,
            'INSERT INTO audit_logs (user_id, entity, entity_id, action, data, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $adminId,
                $auditEntities[$n % count($auditEntities)],
                $orderId,
                $auditActions[$n % count($auditActions)],
                json_encode(['seed' => true, 'message' => 'Registro de demonstração'], JSON_UNESCAPED_UNICODE),
                $now->modify('-' . ($n + 1) . ' hours')->format('Y-m-d H:i:s'),
            ]
        );
    }

    $pdo->commit();

    echo "Seed aplicado com sucesso.\n";
    echo "  Usuários: " . count($seedUsers) . " novos\n";
    echo "  Clientes: " . count($clientIds) . "\n";
    echo "  Veículos: " . count($vehicleIds) . "\n";
    echo "  Ordens de serviço: " . count($osIds) . "\n";
    echo "  Pedidos de peças: 9\n";
    echo "  Orçamentos: 10\n";

    if ($seedPasswordGenerated) {
        echo "\nSenha gerada para os usuários de demonstração: {$seedPassword}\n";
        echo "Troque no primeiro acesso ou crie contas com bin/create_admin.php.\n";
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Erro no seed: ' . $e->getMessage() . "\n");
    exit(1);
}
