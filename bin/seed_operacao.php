<?php
// =============================================
// SEED OPERAÇÃO — simula uma oficina real por
// vários meses (clientes, veículos, OS com ciclo
// de vida, orçamentos, peças, tickets e eventos).
//
// Uso (dentro do container):
//   php bin/seed_operacao.php                 (recria apenas este seed)
//   php bin/seed_operacao.php --fresh          (remove também o seed-demo antigo)
//   php bin/seed_operacao.php --clean          (só remove)
//   php bin/seed_operacao.php --months=8       (período, padrão 6)
//   php bin/seed_operacao.php --os=220         (quantidade aproximada de OS)
//
// Todos os registros criados são marcados com
// "seed-op" para permitir recriação sem afetar
// dados reais ou cadastrados manualmente.
// =============================================

declare(strict_types=1);

use Kernel\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
if (is_file($basePath . '/.env')) {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

$pdo = Connection::fromEnv();

$flags = array_slice($argv, 1);
$onlyClean = in_array('--clean', $flags, true);
$withLegacy = in_array('--fresh', $flags, true);
$months = 6;
$targetOs = null;
foreach ($flags as $flag) {
    if (preg_match('/^--months=(\d+)$/', $flag, $m)) {
        $months = max(5, min(24, (int) $m[1]));
    }
    if (preg_match('/^--os=(\d+)$/', $flag, $m)) {
        $targetOs = max(40, min(600, (int) $m[1]));
    }
}

// Reproduzível: mesmo seed => mesmos números.
mt_srand(20261108);

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------
function q(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function findId(PDO $pdo, string $table, string $column, string $value): ?int
{
    $stmt = q($pdo, "SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1", [$value]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

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

/** Escolhe uma chave de um mapa [valor => peso]. */
function weighted(array $options): mixed
{
    $total = 0;
    foreach ($options as $weight) {
        $total += $weight;
    }
    $roll = mt_rand(1, max(1, (int) $total));
    $acc = 0;
    foreach ($options as $value => $weight) {
        $acc += $weight;
        if ($roll <= $acc) {
            return $value;
        }
    }
    return array_key_first($options);
}

function between(int $min, int $max): int
{
    return mt_rand($min, $max);
}

function pick(array $list): mixed
{
    return $list[array_rand($list)];
}

/** Soma horas a uma data e devolve datetime SQL. */
function at(DateTimeImmutable $date, int $hours, int $minutes = 0): string
{
    return $date->modify('+' . $hours . ' hours')->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s');
}

/** Data/hora dentro do dia (para eventos). */
function dayAt(DateTimeImmutable $date, int $hour, int $minute = 0): string
{
    return $date->setTime($hour, $minute)->format('Y-m-d H:i:s');
}

$today = new DateTimeImmutable('today');
$periodStart = $today->modify('first day of this month')->modify('-' . ($months - 1) . ' months');

// ---------------------------------------------------------------------
// Limpeza
// ---------------------------------------------------------------------
function cleanupSeed(PDO $pdo, bool $legacy): void
{
    $like = $legacy ? "[seed%" : "[seed-op%";
    $notesLike = $legacy ? "[seed%" : "[seed-op%";

    $orderIds = $pdo->query(
        "SELECT id FROM service_orders WHERE internal_notes LIKE '{$like}'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $orderIds = array_map('intval', $orderIds);

    if ($orderIds) {
        $in = implode(',', $orderIds);

        $ticketIds = $pdo->query(
            "SELECT id FROM tickets WHERE service_order_id IN ({$in})"
        )->fetchAll(PDO::FETCH_COLUMN);
        $ticketIds = array_map('intval', $ticketIds);
        if ($ticketIds) {
            $tin = implode(',', $ticketIds);
            $pdo->exec("DELETE FROM ticket_messages WHERE ticket_id IN ({$tin})");
            $pdo->exec("DELETE FROM ticket_reads WHERE ticket_id IN ({$tin})");
            $pdo->exec("DELETE FROM tickets WHERE id IN ({$tin})");
        }

        $links = array_map(static fn (int $id): string => '/service-orders/' . $id, $orderIds);
        $linkIn = "'" . implode("','", $links) . "'";
        $pdo->exec("DELETE FROM notifications WHERE link IN ({$linkIn})");

        $pdo->exec("DELETE FROM budget_items WHERE budget_id IN (SELECT id FROM budgets WHERE service_order_id IN ({$in}) OR notes LIKE '{$notesLike}')");
        $pdo->exec("DELETE FROM budgets WHERE service_order_id IN ({$in}) OR notes LIKE '{$notesLike}'");
        $pdo->exec("DELETE FROM part_order_items WHERE part_order_id IN (SELECT id FROM part_orders WHERE service_order_id IN ({$in}) OR notes LIKE '{$notesLike}')");
        $pdo->exec("DELETE FROM part_orders WHERE service_order_id IN ({$in}) OR notes LIKE '{$notesLike}'");
        $pdo->exec("DELETE FROM service_order_events WHERE order_id IN ({$in})");
        $pdo->exec("DELETE FROM service_order_responsibles WHERE order_id IN ({$in})");
        $pdo->exec("DELETE FROM service_order_tasks WHERE order_id IN ({$in})");
        $pdo->exec("DELETE FROM service_order_deliveries WHERE order_id IN ({$in})");
        $pdo->exec("DELETE FROM service_orders WHERE id IN ({$in})");
    }

    if ($legacy) {
        $pdo->exec("DELETE FROM vehicles WHERE notes IN ('seed-op','seed-demo')");
        $pdo->exec("DELETE FROM clients WHERE notes IN ('seed-op','seed-demo')");
        $pdo->exec("DELETE FROM audit_logs WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.seed')) IN ('seed-op','demo')");
    } else {
        $pdo->exec("DELETE FROM vehicles WHERE notes = 'seed-op'");
        $pdo->exec("DELETE FROM clients WHERE notes = 'seed-op'");
        $pdo->exec("DELETE FROM audit_logs WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.seed')) = 'seed-op'");
    }

    // Notificações antigas apontando para OS que não existem mais.
    $pdo->exec(
        "DELETE FROM notifications
          WHERE link LIKE '/service-orders/%'
            AND NOT EXISTS (
                SELECT 1 FROM service_orders so
                 WHERE CONCAT('/service-orders/', so.id) = notifications.link
            )"
    );
}

cleanupSeed($pdo, $withLegacy);
echo "Seed anterior removido" . ($withLegacy ? ' (incluindo o seed-demo legado)' : '') . ".\n";

if ($onlyClean) {
    echo "Limpeza concluída. Nada foi criado.\n";
    exit(0);
}

// ---------------------------------------------------------------------
// Usuários
// ---------------------------------------------------------------------
// Senha dos usuários de teste: --password=... ou SEED_PASSWORD.
// Sem isso, gera uma senha aleatória e imprime no final.
$seedPassword = '';
foreach ($flags as $flag) {
    if (preg_match('/^--password=(.+)$/', $flag, $pm) === 1) {
        $seedPassword = $pm[1];
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

$seedUsers = [
    ['Carlos Souza', 'carlos.souza@mecanica.com', 'lead_mechanic', 'Mecânico-chefe', 6500.00, '2019-03-12'],
    ['Fernanda Lima', 'fernanda.lima@mecanica.com', 'mechanic', 'Mecânica', 4200.00, '2021-07-01'],
    ['Rafael Alves', 'rafael.alves@mecanica.com', 'mechanic', 'Mecânico', 3900.00, '2022-01-17'],
    ['Juliana Prado', 'juliana.prado@mecanica.com', 'secretary', 'Recepcionista', 3100.00, '2023-05-02'],
    ['André Santos', 'andre.santos@mecanica.com', 'mechanic', 'Mecânico', 4100.00, '2020-11-09'],
    ['Patrícia Gomes', 'patricia.gomes@mecanica.com', 'secretary', 'Atendente', 2900.00, '2024-02-19'],
];

foreach ($seedUsers as $i => [$name, $email, $role, $job, $salary, $hire]) {
    q(
        $pdo,
        'INSERT IGNORE INTO users (name, email, password, role, cpf, phone, salary, hire_date, job_title, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
        [
            $name,
            $email,
            $hash,
            $role,
            mockCpf(120 + $i),
            '(13) 98811-' . str_pad((string) (2001 + $i), 4, '0', STR_PAD_LEFT),
            money($salary),
            $hire,
            $job,
        ]
    );
}

$adminId = findId($pdo, 'users', 'email', 'admin@mecanica.com') ?: 1;
$secretaryIds = array_values(array_filter([
    findId($pdo, 'users', 'email', 'juliana.prado@mecanica.com'),
    findId($pdo, 'users', 'email', 'patricia.gomes@mecanica.com'),
]));
$mechanicIds = array_values(array_filter([
    findId($pdo, 'users', 'email', 'carlos.souza@mecanica.com'),
    findId($pdo, 'users', 'email', 'fernanda.lima@mecanica.com'),
    findId($pdo, 'users', 'email', 'rafael.alves@mecanica.com'),
    findId($pdo, 'users', 'email', 'andre.santos@mecanica.com'),
]));
$leadMechanicId = findId($pdo, 'users', 'email', 'carlos.souza@mecanica.com') ?: $mechanicIds[0];

// ---------------------------------------------------------------------
// Clientes
// ---------------------------------------------------------------------
$firstNames = [
    'Ana', 'Bruno', 'Camila', 'Diego', 'Eduarda', 'Felipe', 'Gabriela', 'Henrique',
    'Isabela', 'João', 'Larissa', 'Marcos', 'Natália', 'Otávio', 'Paula', 'Ricardo',
    'Sofia', 'Thiago', 'Vanessa', 'Wagner', 'Yasmin', 'André', 'Beatriz', 'Caio',
    'Débora', 'Emerson', 'Flávia', 'Gustavo', 'Helena', 'Igor', 'Jéssica', 'Leandro',
    'Mariana', 'Nicolas', 'Olívia', 'Pedro', 'Renata', 'Sérgio', 'Tatiane', 'Vinícius',
    'Aline', 'Breno', 'Carolina', 'Daniel', 'Elisa', 'Fábio', 'Giovana', 'Hugo',
];
$lastNames = [
    'Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Lima', 'Costa', 'Ferreira',
    'Almeida', 'Ribeiro', 'Carvalho', 'Gomes', 'Martins', 'Rocha', 'Barbosa', 'Araújo',
    'Nogueira', 'Teixeira', 'Moreira', 'Dias', 'Cardoso', 'Mendes', 'Freitas', 'Pinto',
    'Azevedo', 'Correia', 'Monteiro', 'Farias', 'Cavalcanti', 'Bezerra',
];
$cities = [
    ['Santos', 'SP', '11010-000'], ['São Vicente', 'SP', '11320-000'],
    ['Praia Grande', 'SP', '11700-000'], ['Guarujá', 'SP', '11410-000'],
    ['Cubatão', 'SP', '11500-000'], ['Mongaguá', 'SP', '11730-000'],
    ['Itanhaém', 'SP', '11740-000'],
];
$streets = [
    'Av. Ana Costa', 'Rua das Palmeiras', 'Av. Presidente Kennedy', 'Rua Carvalho de Mendonça',
    'Rua São João', 'Av. Santos Dumont', 'Rua Galeão Coutinho', 'Rua Frei Gaspar',
    'Av. Washington Luís', 'Rua XV de Novembro', 'Av. Nove de Abril', 'Rua Mario Ribeiro',
    'Av. São Paulo', 'Rua Ipiranga', 'Av. Conselheiro Nébias', 'Rua Tocantins',
];
$usedNames = [];
$clients = [];
$clientCount = 46;

for ($i = 0; $i < $clientCount; $i++) {
    do {
        $name = $firstNames[($i * 7 + 3) % count($firstNames)] . ' ' . $lastNames[($i * 11 + 5) % count($lastNames)];
    } while (isset($usedNames[$name]));
    $usedNames[$name] = true;

    [$city, $state, $zip] = $cities[$i % count($cities)];
    $cpf = mockCpf(400 + $i);
    $created = $periodStart->modify('-' . between(5, 420) . ' days')->format('Y-m-d H:i:s');

    q(
        $pdo,
        'INSERT INTO clients (name, cpf, phone, email, zip, address, address_number, neighborhood, city, state, notes, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [
            $name,
            $cpf,
            '(13) 99' . between(100, 999) . '-' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_RIGHT),
            'cliente' . ($i + 1) . '@email.com',
            $zip,
            $streets[$i % count($streets)],
            (string) between(10, 1800),
            ['Centro', 'Gonzaga', 'Boqueirão', 'Vila Belmiro', 'Pitangueiras', 'Tupi', 'Guilhermina'][$i % 7],
            $city,
            $state,
            'seed-op',
            $created,
        ]
    );

    $clients[] = [
        'id' => (int) $pdo->lastInsertId(),
        'name' => $name,
        'city' => $city,
    ];
}

// ---------------------------------------------------------------------
// Veículos
// ---------------------------------------------------------------------
$vehicleModels = [
    ['VW - VolksWagen', 'Gol 1.0 Flex', '59', '5585', 2014, 39900],
    ['VW - VolksWagen', 'T-Cross Highline 1.4', '59', '11036', 2022, 139900],
    ['Fiat', 'Argo Drive 1.0', '21', '11401', 2021, 70900],
    ['Fiat', 'Toro Endurance 1.3', '21', '9493', 2022, 122900],
    ['Fiat', 'Mobi Like 1.0', '21', '10911', 2020, 49900],
    ['GM - Chevrolet', 'Onix 1.0 Turbo', '23', '10695', 2023, 86900],
    ['GM - Chevrolet', 'Tracker Premier 1.2', '23', '11264', 2022, 129900],
    ['GM - Chevrolet', 'Spin LTZ 1.8', '23', '9151', 2019, 72900],
    ['Ford', 'Ka SE 1.5', '22', '8440', 2019, 48900],
    ['Ford', 'Ranger XLS 3.2', '22', '9845', 2021, 219900],
    ['Toyota', 'Corolla XEi 2.0', '56', '3236', 2022, 149900],
    ['Toyota', 'Yaris XL 1.5', '56', '10123', 2021, 84900],
    ['Toyota', 'Hilux SRV 2.8', '56', '10229', 2020, 289900],
    ['Honda', 'Civic EXL 2.0', '25', '5982', 2021, 129900],
    ['Honda', 'HR-V EX 1.8', '25', '6521', 2020, 109900],
    ['Honda', 'Fit LX 1.5', '25', '4592', 2018, 69900],
    ['Hyundai', 'HB20 Comfort 1.0', '26', '10063', 2020, 62900],
    ['Hyundai', 'Creta Action 1.6', '26', '11209', 2022, 112900],
    ['Renault', 'Sandero Stepway 1.6', '48', '8075', 2019, 54900],
    ['Renault', 'Duster Intense 1.6', '48', '10291', 2021, 89900],
    ['Nissan', 'Kicks SV 1.6', '43', '9876', 2022, 99900],
    ['Nissan', 'Versa SV 1.6', '43', '10954', 2021, 79900],
    ['Jeep', 'Renegade Sport 1.8', '29', '7895', 2021, 102900],
    ['Jeep', 'Compass Longitude 2.0', '29', '11279', 2022, 169900],
    ['Citroën', 'C3 Live 1.0', '13', '10839', 2021, 69900],
    ['Peugeot', '208 Allure 1.6', '44', '11189', 2020, 75900],
    ['Mitsubishi', 'L200 Triton 2.4', '41', '10521', 2020, 199900],
    ['Kia', 'Sportage EX 2.0', '38', '10021', 2019, 139900],
];
$colors = ['Prata', 'Branco', 'Preto', 'Cinza', 'Vermelho', 'Azul', 'Verde', 'Laranja'];
$plateLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$usedPlates = [];

$vehicles = [];
$vehicleCount = 64;
for ($i = 0; $i < $vehicleCount; $i++) {
    $client = $clients[$i % count($clients)];
    $model = $vehicleModels[$i % count($vehicleModels)];

    // Placa Mercosul única (ex.: BRA2E19).
    do {
        $plate = '';
        for ($p = 0; $p < 3; $p++) {
            $plate .= $plateLetters[mt_rand(0, 25)];
        }
        $plate .= mt_rand(0, 9) . $plateLetters[mt_rand(0, 25)] . str_pad((string) mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
    } while (isset($usedPlates[$plate]));
    $usedPlates[$plate] = true;

    q(
        $pdo,
        'INSERT INTO vehicles
            (client_id, plate, brand, model, year, color, current_km, notes,
             fipe_brand_code, fipe_model_code, fipe_year_code, fipe_year, fipe_value, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [
            $client['id'],
            $plate,
            $model[0],
            $model[1],
            $model[4],
            $colors[$i % count($colors)],
            between(18000, 185000),
            'seed-op',
            $model[2],
            $model[3],
            $model[4] . '-3',
            $model[4],
            money((float) $model[5]),
            $periodStart->modify('-' . between(5, 400) . ' days')->format('Y-m-d H:i:s'),
        ]
    );

    $vehicles[] = [
        'id' => (int) $pdo->lastInsertId(),
        'client_id' => (int) $client['id'],
        'model' => $model[1],
        'brand' => $model[0],
        'km' => (int) $pdo->lastInsertId(), // substituído abaixo
    ];
}
// Recarrega km real (evita depender do lastInsertId acima).
foreach ($vehicles as &$vehicle) {
    $vehicle['km'] = (int) q($pdo, 'SELECT current_km FROM vehicles WHERE id = ?', [$vehicle['id']])->fetchColumn();
}
unset($vehicle);

// ---------------------------------------------------------------------
// Catálogo de serviços
// ---------------------------------------------------------------------
$catalog = [
    'revisao' => [
        'weight' => 20,
        'complaints' => [
            'Revisão preventiva dos 10.000 km.',
            'Troca de óleo e filtros.',
            'Carro pedindo revisão no painel.',
            'Revisão antes de viagem.',
        ],
        'diagnoses' => [
            'Revisão preventiva concluída conforme plano do fabricante.',
            'Óleo e filtros substituídos; níveis conferidos.',
            'Itens de desgaste inspecionados, sem anomalias.',
        ],
        'tasks' => ['Trocar óleo do motor', 'Substituir filtro de óleo', 'Conferir níveis e correias', 'Teste de rodagem'],
        'parts' => [
            ['Óleo de motor 5W30 (4L)', 210, 330, 1],
            ['Filtro de óleo', 35, 65, 1],
            ['Filtro de ar', 55, 95, 1],
            ['Filtro de combustível', 70, 120, 1],
        ],
        'labor' => [['Mão de obra - revisão preventiva', 150, 260, 1]],
        'days' => [1, 3],
    ],
    'freios' => [
        'weight' => 15,
        'complaints' => [
            'Ruído ao frear e pedal baixo.',
            'Freio fazendo barulho na dianteira.',
            'Carro puxando para o lado ao frear.',
            'Luz de freio acesa no painel.',
        ],
        'diagnoses' => [
            'Pastilhas dianteiras no limite de desgaste.',
            'Discos com empenamento e pastilhas gastas.',
            'Fluido de freio contaminado; sistema com ar.',
        ],
        'tasks' => ['Inspecionar sistema de freios', 'Substituir pastilhas', 'Sangrar fluido de freio', 'Teste de frenagem'],
        'parts' => [
            ['Pastilha de freio dianteira', 120, 260, 1],
            ['Disco de freio', 220, 420, 2],
            ['Fluido de freio DOT4', 35, 70, 1],
        ],
        'labor' => [['Mão de obra - sistema de freios', 180, 320, 1]],
        'days' => [1, 2],
    ],
    'suspensao' => [
        'weight' => 12,
        'complaints' => [
            'Barulho na suspensão ao passar em lombadas.',
            'Direção puxando e volante tremendo.',
            'Carro batendo seco na frente.',
            'Pneu gastando irregularmente.',
        ],
        'diagnoses' => [
            'Buchas da suspensão dianteira desgastadas.',
            'Amortecedores dianteiros sem carga.',
            'Pivôs e terminais com folga.',
        ],
        'tasks' => ['Inspecionar suspensão', 'Substituir buchas', 'Alinhar e balancear', 'Teste de rodagem'],
        'parts' => [
            ['Kit buchas da suspensão', 180, 340, 1],
            ['Amortecedor dianteiro', 280, 520, 2],
            ['Pivô de suspensão', 90, 180, 2],
        ],
        'labor' => [['Mão de obra - suspensão', 220, 420, 1], ['Alinhamento e balanceamento', 90, 150, 1]],
        'days' => [1, 4],
    ],
    'motor' => [
        'weight' => 12,
        'complaints' => [
            'Luz de injeção acesa no painel.',
            'Motor falhando em marcha lenta.',
            'Perda de potência ao acelerar.',
            'Consumo de combustível alto.',
        ],
        'diagnoses' => [
            'Sensor de oxigênio com falha intermitente.',
            'Bobina de ignição com resistência fora do padrão.',
            'Corpo de borboleta carbonizado.',
            'Falha de compressão no cilindro 3.',
        ],
        'tasks' => ['Diagnóstico eletrônico', 'Testar sensores', 'Substituir componente com falha', 'Teste de rodagem'],
        'parts' => [
            ['Sensor de oxigênio', 220, 420, 1],
            ['Bobina de ignição', 180, 350, 1],
            ['Jogo de velas de ignição', 120, 260, 1],
            ['Limpeza de bicos injetores', 180, 320, 1],
        ],
        'labor' => [['Mão de obra - injeção eletrônica', 200, 380, 1]],
        'days' => [1, 5],
    ],
    'ar_condicionado' => [
        'weight' => 8,
        'complaints' => [
            'Ar-condicionado não está gelando.',
            'Ar sai quente e com cheiro ruim.',
            'Ventilação fraca no painel.',
        ],
        'diagnoses' => [
            'Falta de gás e filtro do ar-condicionado saturado.',
            'Compressor com baixa pressão.',
            'Vazamento no condensador.',
        ],
        'tasks' => ['Testar pressão do sistema', 'Localizar vazamento', 'Recarregar gás', 'Higienizar dutos'],
        'parts' => [
            ['Gás refrigerante R134a', 90, 160, 1],
            ['Filtro de cabine', 55, 110, 1],
            ['Compressor do ar-condicionado', 900, 1600, 1],
        ],
        'labor' => [['Mão de obra - ar-condicionado', 180, 320, 1]],
        'days' => [1, 3],
    ],
    'eletrica' => [
        'weight' => 10,
        'complaints' => [
            'Carro não liga pela manhã.',
            'Bateria descarregando com facilidade.',
            'Farol e seta com falha.',
            'Vidros elétricos lentos.',
        ],
        'diagnoses' => [
            'Bateria com baixa capacidade de partida.',
            'Alternador com diodo queimado.',
            'Mau contato no chicote do farol.',
        ],
        'tasks' => ['Testar bateria e alternador', 'Inspecionar chicotes', 'Substituir componente', 'Teste de carga'],
        'parts' => [
            ['Bateria 60Ah', 320, 520, 1],
            ['Alternador remanufaturado', 650, 1100, 1],
            ['Lâmpada automotiva', 25, 60, 2],
        ],
        'labor' => [['Mão de obra - elétrica', 150, 280, 1]],
        'days' => [1, 2],
    ],
    'transmissao' => [
        'weight' => 8,
        'complaints' => [
            'Embreagem patinando em subida.',
            'Dificuldade para engatar marchas.',
            'Barulho na caixa de câmbio.',
        ],
        'diagnoses' => [
            'Kit de embreagem no fim da vida útil.',
            'Atuador hidráulico com vazamento.',
            'Rolamento da caixa com ruído.',
        ],
        'tasks' => ['Teste de engate', 'Remover caixa', 'Substituir kit', 'Teste de rodagem'],
        'parts' => [
            ['Kit de embreagem', 700, 1250, 1],
            ['Atuador de embreagem', 280, 520, 1],
            ['Óleo de câmbio', 70, 140, 2],
        ],
        'labor' => [['Mão de obra - transmissão', 350, 620, 1]],
        'days' => [2, 5],
    ],
    'alinhamento' => [
        'weight' => 9,
        'complaints' => [
            'Alinhamento e balanceamento.',
            'Volante desalinhado após buraco.',
            'Carro puxando para a direita.',
        ],
        'diagnoses' => [
            'Alinhamento fora do padrão de fábrica.',
            'Balanceamento com pesos ausentes.',
        ],
        'tasks' => ['Medir geometria', 'Regular alinhamento', 'Balancear rodas', 'Teste de rodagem'],
        'parts' => [
            ['Contrapeso de balanceamento', 10, 25, 4],
        ],
        'labor' => [['Alinhamento e balanceamento', 100, 180, 1]],
        'days' => [1, 1],
    ],
    'arrefecimento' => [
        'weight' => 6,
        'complaints' => [
            'Superaquecimento em congestionamento.',
            'Nível de água baixando.',
            'Radiador vazando.',
        ],
        'diagnoses' => [
            'Válvula termostática travada.',
            'Radiador com vazamento no colmeia.',
            'Mangueira ressecada; sistema com ar.',
        ],
        'tasks' => ['Testar pressão do sistema', 'Substituir componente', 'Completar aditivo', 'Teste de rodagem'],
        'parts' => [
            ['Radiador', 380, 700, 1],
            ['Válvula termostática', 90, 180, 1],
            ['Aditivo radiador', 35, 70, 2],
            ['Mangueira do radiador', 60, 130, 1],
        ],
        'labor' => [['Mão de obra - arrefecimento', 180, 320, 1]],
        'days' => [1, 3],
    ],
];

$suppliers = [
    'Auto Peças Silva', 'Distribuidora Central', 'Peças & Cia',
    'Oficina Brasil Parts', 'Rodrigues Auto Peças', 'Comercial Bandeirantes',
];

$statusByAge = static function (int $age): string {
    if ($age <= 1) {
        return weighted(['received' => 32, 'in_analysis' => 34, 'awaiting_approval' => 14, 'awaiting_part' => 8, 'in_progress' => 12]);
    }
    if ($age <= 3) {
        return weighted(['received' => 14, 'in_analysis' => 26, 'awaiting_approval' => 20, 'awaiting_part' => 14, 'in_progress' => 20, 'ready_for_delivery' => 6]);
    }
    if ($age <= 6) {
        return weighted(['in_analysis' => 8, 'awaiting_approval' => 14, 'awaiting_part' => 16, 'in_progress' => 30, 'ready_for_delivery' => 20, 'delivered' => 10, 'cancelled' => 2]);
    }
    if ($age <= 10) {
        return weighted(['awaiting_approval' => 4, 'awaiting_part' => 10, 'in_progress' => 18, 'ready_for_delivery' => 14, 'delivered' => 50, 'cancelled' => 4]);
    }
    if ($age <= 20) {
        return weighted(['awaiting_part' => 4, 'in_progress' => 4, 'ready_for_delivery' => 4, 'delivered' => 84, 'cancelled' => 4]);
    }
    return weighted(['awaiting_part' => 3, 'ready_for_delivery' => 2, 'delivered' => 90, 'cancelled' => 5]);
};

// ---------------------------------------------------------------------
// Distribuição das OS por mês
// ---------------------------------------------------------------------
$monthKeys = [];
$cursor = $periodStart;
while ($cursor <= $today) {
    $monthKeys[] = $cursor->format('Y-m');
    $cursor = $cursor->modify('first day of next month');
}

$baseOsPerMonth = $targetOs !== null
    ? (int) round($targetOs / max(1, count($monthKeys)) * 1.08)
    : 30;

$osDates = [];
foreach ($monthKeys as $mIdx => $monthKey) {
    $monthStart = new DateTimeImmutable($monthKey . '-01');
    $monthEnd = $monthStart->modify('last day of this month');
    if ($monthStart > $today) {
        continue;
    }
    if ($monthEnd > $today) {
        $monthEnd = $today;
    }

    $candidates = [];
    $day = $monthStart;
    while ($day <= $monthEnd) {
        $dow = (int) $day->format('N');
        $candidates[$day->format('Y-m-d')] = $dow >= 6 ? ($dow === 6 ? 26 : 3) : 100;
        $day = $day->modify('+1 day');
    }

    $growth = 1 + ($mIdx * 0.05);
    $count = (int) round($baseOsPerMonth * $growth);
    if ($mIdx === count($monthKeys) - 1 && $monthEnd < $monthStart->modify('last day of this month')) {
        $elapsedRatio = $monthEnd->format('j') / $monthStart->modify('last day of this month')->format('j');
        $count = max(4, (int) round($count * $elapsedRatio));
    }

    for ($i = 0; $i < $count; $i++) {
        $osDates[] = weighted($candidates);
    }
}
sort($osDates);

// ---------------------------------------------------------------------
// OS e registros dependentes
// ---------------------------------------------------------------------
$nextOs = (int) ($pdo->query('SELECT COALESCE(MAX(number), 0) FROM service_orders')->fetchColumn()) + 1;
$nextBudget = (int) ($pdo->query('SELECT COALESCE(MAX(number), 0) FROM budgets')->fetchColumn()) + 1;

$catalogKeys = array_keys($catalog);
$catalogWeights = [];
foreach ($catalog as $key => $data) {
    $catalogWeights[$key] = $data['weight'];
}

$countOs = 0;
$countBudgets = 0;
$countPartOrders = 0;
$countTickets = 0;

foreach ($osDates as $dateKey) {
    $entry = new DateTimeImmutable($dateKey);
    $age = (int) $entry->diff($today)->days;

    $serviceKey = weighted($catalogWeights);
    $service = $catalog[$serviceKey];

    // Cliente/veículo: 60% recorrência entre os 18 primeiros clientes.
    if (mt_rand(1, 100) <= 60) {
        $client = $clients[between(0, min(17, count($clients) - 1))];
    } else {
        $client = pick($clients);
    }
    $clientVehicles = array_values(array_filter($vehicles, static fn (array $v): bool => $v['client_id'] === $client['id']));
    $vehicle = $clientVehicles ? pick($clientVehicles) : pick($vehicles);

    $status = $statusByAge($age);
    $priority = weighted(['low' => 14, 'normal' => 56, 'high' => 22, 'urgent' => 8]);

    $completion = null;
    if (in_array($status, ['delivered', 'ready_for_delivery'], true)) {
        [$minDays, $maxDays] = $service['days'];
        $completionDate = $entry->modify('+' . between($minDays, max($minDays, $maxDays)) . ' days');
        if ($completionDate > $today) {
            $completionDate = $today;
        }
        $completion = $completionDate->format('Y-m-d');
    } else {
        $completionDate = null;
    }

    $lead = pick($mechanicIds);
    $creator = pick($secretaryIds) ?: $adminId;
    $number = $nextOs + $countOs;

    q(
        $pdo,
        'INSERT INTO service_orders
            (number, client_id, vehicle_id, status, priority, initial_complaint, internal_notes, diagnosis,
             entry_date, completion_date, lead_mechanic_id, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $number,
            $client['id'],
            $vehicle['id'],
            $status,
            $priority,
            pick($service['complaints']),
            '[seed-op] Atendimento gerado para simulação de operação.',
            pick($service['diagnoses']),
            $entry->format('Y-m-d'),
            $completion,
            $lead,
            $creator,
            dayAt($entry, 8, between(0, 45)),
            ($completion ? $completion . ' 17:30:00' : dayAt($entry, 17, between(0, 30))),
        ]
    );
    $orderId = (int) $pdo->lastInsertId();
    $countOs++;

    // Tarefas
    $taskLabels = $service['tasks'];
    if (count($taskLabels) > 1) {
        shuffle($taskLabels);
    }
    $taskTotal = min(count($taskLabels), between(2, 4));
    for ($t = 0; $t < $taskTotal; $t++) {
        if (in_array($status, ['delivered', 'ready_for_delivery'], true)) {
            $taskStatus = 'done';
        } elseif ($status === 'in_progress') {
            $taskStatus = $t === 0 ? 'done' : ($t === 1 ? 'in_progress' : 'pending');
        } elseif ($status === 'in_analysis' && $t === 0) {
            $taskStatus = 'in_progress';
        } else {
            $taskStatus = 'pending';
        }

        q(
            $pdo,
            'INSERT INTO service_order_tasks (order_id, description, status, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
            [$orderId, $taskLabels[$t], $taskStatus, $lead, dayAt($entry, 9, between(0, 50))]
        );
    }

    // Responsáveis
    $assigned = [$lead];
    if (mt_rand(1, 100) <= 65) {
        $helper = pick(array_values(array_filter($mechanicIds, static fn (int $id): bool => $id !== $lead)));
        if ($helper) {
            $assigned[] = $helper;
        }
    }
    foreach (array_unique($assigned) as $userId) {
        q(
            $pdo,
            'INSERT INTO service_order_responsibles (order_id, user_id, assigned_at, completed_at) VALUES (?, ?, ?, ?)',
            [
                $orderId,
                $userId,
                dayAt($entry, 8, between(30, 59)),
                in_array($status, ['delivered', 'ready_for_delivery'], true) ? ($completion . ' 16:30:00') : null,
            ]
        );
    }

    // Eventos (created + responsável + ciclo de status + entrega)
    q(
        $pdo,
        'INSERT INTO service_order_events (order_id, type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
        [$orderId, 'created', 'OS aberta no balcão.', $creator, dayAt($entry, 8, between(0, 20))]
    );
    q(
        $pdo,
        'INSERT INTO service_order_events (order_id, type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
        [$orderId, 'responsible', 'Responsável técnico designado.', $lead, dayAt($entry, 8, between(40, 59))]
    );

    $statusPath = [];
    switch ($status) {
        case 'received':
            break;
        case 'in_analysis':
            $statusPath = ['in_analysis'];
            break;
        case 'awaiting_approval':
            $statusPath = ['in_analysis', 'awaiting_approval'];
            break;
        case 'awaiting_part':
            $statusPath = ['in_analysis', 'awaiting_approval', 'awaiting_part'];
            break;
        case 'in_progress':
            $statusPath = ['in_analysis', 'awaiting_approval'];
            if (mt_rand(1, 100) <= 45) {
                $statusPath[] = 'awaiting_part';
            }
            $statusPath[] = 'in_progress';
            break;
        case 'ready_for_delivery':
            $statusPath = ['in_analysis', 'awaiting_approval', 'in_progress', 'ready_for_delivery'];
            break;
        case 'delivered':
            $statusPath = ['in_analysis', 'awaiting_approval', 'in_progress', 'ready_for_delivery', 'delivered'];
            break;
        case 'cancelled':
            $statusPath = mt_rand(1, 100) <= 55 ? ['in_analysis', 'cancelled'] : ['cancelled'];
            break;
    }

    $statusLabels = [
        'in_analysis' => 'Em análise',
        'awaiting_approval' => 'Aguardando aprovação',
        'awaiting_part' => 'Aguardando peça',
        'in_progress' => 'Em execução',
        'ready_for_delivery' => 'Pronta para entrega',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelada',
    ];

    $stepCount = count($statusPath);
    $spanHours = max(4, (int) (($completion ? (new DateTimeImmutable($completion))->diff($entry)->days : min($age, 8)) * 9));
    foreach ($statusPath as $index => $statusStep) {
        $offset = (int) round(($spanHours / max(1, $stepCount)) * ($index + 1));
        q(
            $pdo,
            'INSERT INTO service_order_events (order_id, type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
            [
                $orderId,
                'status',
                'Status alterado para "' . ($statusLabels[$statusStep] ?? $statusStep) . '".',
                $lead,
                at($entry, 8 + $offset, between(0, 59)),
            ]
        );
    }

    if ($status === 'delivered' && $completion) {
        q(
            $pdo,
            'INSERT INTO service_order_events (order_id, type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
            [$orderId, 'delivery', 'Veículo entregue ao cliente.', $creator, $completion . ' 17:20:00']
        );

        q(
            $pdo,
            "INSERT INTO service_order_deliveries (order_id, modality, responsible, destination, exit_km, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'delivered', ?)",
            [
                $orderId,
                weighted(['pickup' => 72, 'delivery' => 28]) === 'delivery' ? 'delivery' : 'pickup',
                mt_rand(1, 100) <= 70 ? 'Cliente' : 'Motorista da oficina',
                mt_rand(1, 100) <= 70 ? 'Oficina' : $client['city'],
                (int) $vehicle['km'] + between(5, 40),
                $completion . ' 17:25:00',
            ]
        );
    }

    // ---------------- Orçamento ----------------
    $hasBudget = $status !== 'received' && mt_rand(1, 100) <= 90;
    if ($hasBudget) {
        if ($status === 'cancelled') {
            $budgetStatus = weighted(['cancelled' => 55, 'rejected' => 30, 'open' => 15]);
        } elseif ($status === 'awaiting_approval') {
            $budgetStatus = weighted(['open' => 80, 'rejected' => 12, 'approved' => 8]);
        } elseif ($status === 'in_analysis') {
            $budgetStatus = weighted(['open' => 75, 'approved' => 15, 'rejected' => 10]);
        } elseif (in_array($status, ['awaiting_part', 'in_progress', 'ready_for_delivery', 'delivered'], true)) {
            $budgetStatus = weighted(['approved' => 88, 'rejected' => 6, 'open' => 6]);
        } else {
            $budgetStatus = weighted(['open' => 90, 'approved' => 5, 'rejected' => 5]);
        }

        $budgetDate = $entry->modify('+' . between(0, 2) . ' days');
        if ($budgetDate > $today) {
            $budgetDate = $today;
        }
        $validUntil = $budgetDate->modify('+' . between(10, 20) . ' days');

        $partsTotal = 0.0;
        $laborTotal = 0.0;
        $items = [];

        $partPool = $service['parts'];
        shuffle($partPool);
        $partCount = min(count($partPool), between(1, 3));
        for ($p = 0; $p < $partCount; $p++) {
            [$description, $minPrice, $maxPrice, $maxQty] = $partPool[$p];
            $qty = between(1, max(1, $maxQty));
            $price = between($minPrice, $maxPrice) + (mt_rand(0, 9) / 10);
            $subtotal = round($qty * $price, 2);
            $partsTotal += $subtotal;
            $items[] = ['part', $description, $qty, $price, $subtotal];
        }

        foreach ($service['labor'] as [$description, $minPrice, $maxPrice]) {
            $price = between($minPrice, $maxPrice);
            $subtotal = round($price, 2);
            $laborTotal += $subtotal;
            $items[] = ['service', $description, 1, $price, $subtotal];
        }

        $discountPercent = weighted(['0' => 60, '3' => 15, '5' => 15, '8' => 10]);
        $gross = $partsTotal + $laborTotal;
        $discount = round($gross * ((int) $discountPercent / 100), 2);
        $total = round(max(0, $gross - $discount), 2);

        q(
            $pdo,
            "INSERT INTO budgets
                (number, client_id, vehicle_id, service_order_id, budget_date, valid_until, status,
                 parts_total, labor_total, discount, total, notes, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '[seed-op] Orçamento da simulação.', ?, ?)",
            [
                $nextBudget + $countBudgets,
                $client['id'],
                $vehicle['id'],
                $orderId,
                $budgetDate->format('Y-m-d'),
                $validUntil->format('Y-m-d'),
                $budgetStatus,
                money($partsTotal),
                money($laborTotal),
                money($discount),
                money($total),
                $creator,
                dayAt($budgetDate, 10, between(0, 59)),
            ]
        );
        $budgetId = (int) $pdo->lastInsertId();
        $countBudgets++;

        foreach ($items as [$type, $description, $qty, $price, $subtotal]) {
            q(
                $pdo,
                'INSERT INTO budget_items (budget_id, type, description, quantity, unit_price, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$budgetId, $type, $description, $qty, money((float) $price), money((float) $subtotal)]
            );
        }
    }

    // ---------------- Pedido de peças ----------------
    $wantsParts = in_array($status, ['awaiting_part', 'in_progress', 'ready_for_delivery', 'delivered'], true);
    if ($wantsParts && mt_rand(1, 100) <= ($status === 'awaiting_part' ? 100 : 55)) {
        if ($status === 'awaiting_part') {
            $partStatus = weighted(['requested' => 25, 'validated' => 20, 'ordered' => 25, 'on_the_way' => 30]);
        } elseif ($status === 'in_progress') {
            $partStatus = weighted(['received' => 75, 'on_the_way' => 15, 'ordered' => 10]);
        } else {
            $partStatus = weighted(['received' => 95, 'cancelled' => 5]);
        }

        $partCreated = $entry->modify('+' . between(0, 2) . ' days');
        if ($partCreated > $today) {
            $partCreated = $today;
        }
        $expected = $partCreated->modify('+' . between(2, 7) . ' days');
        $received = $partStatus === 'received';
        $receivedAt = null;
        if ($received) {
            $receivedDate = $partCreated->modify('+' . between(1, 5) . ' days');
            if ($receivedDate > $today) {
                $receivedDate = $today;
            }
            if ($completion && $receivedDate->format('Y-m-d') > $completion) {
                $receivedDate = new DateTimeImmutable($completion);
            }
            $receivedAt = $receivedDate->format('Y-m-d H:i:s');
        }

        q(
            $pdo,
            "INSERT INTO part_orders
                (service_order_id, supplier, status, expected_date, notes, created_by, received_by, created_at, received_at)
             VALUES (?, ?, ?, ?, '[seed-op] Pedido de peças da simulação.', ?, ?, ?, ?)",
            [
                $orderId,
                pick($suppliers),
                $partStatus,
                $expected->format('Y-m-d'),
                $creator,
                $received ? $lead : null,
                $partCreated->format('Y-m-d H:i:s'),
                $receivedAt,
            ]
        );
        $partOrderId = (int) $pdo->lastInsertId();
        $countPartOrders++;

        $partPool = $service['parts'];
        shuffle($partPool);
        $partCount = min(count($partPool), between(1, 2));
        for ($p = 0; $p < $partCount; $p++) {
            [$description, $minPrice, $maxPrice, $maxQty] = $partPool[$p];
            q(
                $pdo,
                'INSERT INTO part_order_items (part_order_id, description, quantity, unit_price, supplier)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $partOrderId,
                    $description,
                    between(1, max(1, $maxQty)),
                    money((float) between($minPrice, $maxPrice)),
                    pick($suppliers),
                ]
            );
        }
    }

    // ---------------- Ticket (um por OS, como no fluxo real) ----------------
    $ticketStatus = 'open';
    if (in_array($status, ['delivered', 'cancelled'], true) && $age > 3 && mt_rand(1, 100) <= 85) {
        $ticketStatus = 'closed';
    } elseif ($age > 20 && mt_rand(1, 100) <= 75) {
        $ticketStatus = 'closed';
    }

    $ticketSubject = 'OS #' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . ' - ' . $client['name'];
    q(
        $pdo,
        'INSERT INTO tickets (subject, service_order_id, created_by, assigned_to, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            mb_substr($ticketSubject, 0, 200),
            $orderId,
            $creator,
            $lead,
            $ticketStatus,
            dayAt($entry, 8, between(10, 40)),
            $completion ? $completion . ' 17:30:00' : dayAt($entry, 17, between(0, 50)),
        ]
    );
    $ticketId = (int) $pdo->lastInsertId();
    $countTickets++;

    // Conversas em ~18% dos tickets.
    if (mt_rand(1, 100) <= 18) {
        $threads = [
            ['Bom dia! O cliente aprovou o orçamento por telefone.', 'Perfeito, já vou iniciar o serviço.', 'Combinado. Qualquer novidade me avise.'],
            ['Conseguiu confirmar o prazo da peça com o fornecedor?', 'Confirmou para quinta-feira.', 'Ótimo, vou avisar o cliente.'],
            ['O cliente pediu para priorizar a entrega hoje.', 'Consigo finalizar até o fim da tarde.', 'Fechado, obrigado!'],
            ['Chegou a peça?', 'Chegou agora, já estou montando.', 'Maravilha.'],
            ['Precisamos de mais um orçamento para o cliente aprovar.', 'Vou preparar e envio ainda hoje.', 'Obrigado!'],
        ];
        $thread = pick($threads);
        $messageCount = min(count($thread) + between(0, 2), 5);
        $participants = [$creator, $lead, pick($secretaryIds) ?: $creator];
        for ($m = 0; $m < $messageCount; $m++) {
            $msgAt = $entry->modify('+' . between(1, 3) . ' days');
            if ($msgAt > $today) {
                $msgAt = $today;
            }
            q(
                $pdo,
                'INSERT INTO ticket_messages (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, ?)',
                [
                    $ticketId,
                    $participants[$m % count($participants)],
                    $thread[$m % count($thread)],
                    $msgAt->setTime(between(8, 17), between(0, 59))->format('Y-m-d H:i:s'),
                ]
            );
        }
    }

    // Leituras do ticket
    foreach (array_unique([$creator, $lead]) as $readerId) {
        q(
            $pdo,
            'INSERT IGNORE INTO ticket_reads (ticket_id, user_id, last_read_at) VALUES (?, ?, ?)',
            [$ticketId, $readerId, dayAt($entry, 18, between(0, 59))]
        );
    }
}

// ---------------------------------------------------------------------
// Notificações recentes
// ---------------------------------------------------------------------
$recentOrders = $pdo->query(
    "SELECT so.id, so.number, so.status, so.lead_mechanic_id, so.created_by
       FROM service_orders so
      WHERE so.internal_notes LIKE '[seed-op]%'
        AND so.entry_date >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)
      ORDER BY so.id DESC
      LIMIT 18"
)->fetchAll();

$notifMessages = [
    'os_created' => 'Você foi designado para a OS #%s.',
    'os_status' => 'A OS #%s teve o status atualizado.',
    'part' => 'Pedido de peças da OS #%s foi atualizado.',
];

foreach ($recentOrders as $n => $order) {
    $userIds = array_values(array_unique(array_filter([
        (int) $order['lead_mechanic_id'],
        (int) $order['created_by'],
        $adminId,
    ])));
    $type = ['os_created', 'os_status', 'part'][$n % 3];
    foreach (array_slice($userIds, 0, 2) as $k => $userId) {
        q(
            $pdo,
            'INSERT INTO notifications (user_id, created_by, type, message, link, read_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $adminId,
                $type,
                sprintf($notifMessages[$type], str_pad((string) $order['number'], 4, '0', STR_PAD_LEFT)),
                '/service-orders/' . (int) $order['id'],
                ($n + $k) % 3 === 0 ? null : $today->modify('-' . between(1, 20) . ' hours')->format('Y-m-d H:i:s'),
                $today->modify('-' . between(1, 60) . ' hours')->format('Y-m-d H:i:s'),
            ]
        );
    }
}

// ---------------------------------------------------------------------
// Auditoria
// ---------------------------------------------------------------------
$auditOrders = $pdo->query(
    "SELECT id, number FROM service_orders WHERE internal_notes LIKE '[seed-op]%' ORDER BY RAND() LIMIT 60"
)->fetchAll();

$auditActions = ['create', 'update', 'status', 'delivery'];
$auditEntities = ['service_orders', 'budgets', 'clients', 'vehicles'];
foreach ($auditOrders as $n => $order) {
    $when = $periodStart->modify('+' . between(0, max(1, (int) $periodStart->diff($today)->days)) . ' days');
    if ($when > $today) {
        $when = $today;
    }
    q(
        $pdo,
        'INSERT INTO audit_logs (user_id, entity, entity_id, action, data, created_at)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $adminId,
            $auditEntities[$n % count($auditEntities)],
            (int) $order['id'],
            $auditActions[$n % count($auditActions)],
            json_encode(['seed' => 'seed-op', 'order' => (int) $order['number']], JSON_UNESCAPED_UNICODE),
            $when->setTime(between(8, 18), between(0, 59))->format('Y-m-d H:i:s'),
        ]
    );
}

// ---------------------------------------------------------------------
// Resumo
// ---------------------------------------------------------------------
$from = $periodStart->format('d/m/Y');
$to = $today->format('d/m/Y');
$totals = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM clients WHERE notes = 'seed-op') clients,
        (SELECT COUNT(*) FROM vehicles WHERE notes = 'seed-op') vehicles,
        (SELECT COUNT(*) FROM service_orders WHERE internal_notes LIKE '[seed-op]%') os,
        (SELECT COUNT(*) FROM budgets WHERE notes LIKE '[seed-op]%') budgets,
        (SELECT COUNT(*) FROM part_orders WHERE notes LIKE '[seed-op]%') part_orders,
        (SELECT COUNT(*) FROM tickets t JOIN service_orders so ON so.id = t.service_order_id WHERE so.internal_notes LIKE '[seed-op]%') tickets,
        (SELECT COALESCE(SUM(total),0) FROM budgets WHERE notes LIKE '[seed-op]%' AND status = 'approved') revenue"
)->fetch();

echo "Seed de operação criado com sucesso ({$from} a {$to}).\n";
echo '  Clientes:        ' . (int) $totals['clients'] . "\n";
echo '  Veículos:        ' . (int) $totals['vehicles'] . "\n";
echo '  Ordens de serviço: ' . (int) $totals['os'] . "\n";
echo '  Orçamentos:      ' . (int) $totals['budgets'] . "\n";
echo '  Pedidos de peças: ' . (int) $totals['part_orders'] . "\n";
echo '  Tickets:         ' . (int) $totals['tickets'] . "\n";
echo '  Receita aprovada: R$ ' . number_format((float) $totals['revenue'], 2, ',', '.') . "\n";
if ($seedPasswordGenerated) {
    echo "\nSenha gerada para os usuários de teste: {$seedPassword}\n";
    echo "Troque no primeiro acesso ou use bin/create_admin.php.\n";
}
