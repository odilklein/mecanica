<?php
declare(strict_types=1);

/**
 * =============================================================
 * SEED DE MOCKS - Sistema de Gestão de Oficina Mecânica
 * -------------------------------------------------------------
 * Gera um volume realista de dados para simular produção:
 * clientes, veículos, ordens de serviço (com histórico, tarefas,
 * responsáveis, pedidos de peças, entregas), orçamentos + itens,
 * notificações e auditoria.
 *
 * Uso (dentro do container):
 *   php scripts/seed_mocks.php --yes
 *   php scripts/seed_mocks.php --yes --clientes=500 --os=3000
 *   php scripts/seed_mocks.php --yes --seed=123
 *
 * ATENÇÃO: apaga os dados transacionais atuais. A tabela `usuarios`
 * (logins) é preservada e usada como referência.
 * =============================================================
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via linha de comando.\n");
    exit(1);
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/status_os.php';

$opcoes = getopt('', ['yes', 'clientes::', 'os::', 'seed::']);
$confirmado = array_key_exists('yes', $opcoes);
$qtdClientes = isset($opcoes['clientes']) ? max(1, (int) $opcoes['clientes']) : 300;
$qtdOs = isset($opcoes['os']) ? max(1, (int) $opcoes['os']) : 1200;
$seed = isset($opcoes['seed']) ? (int) $opcoes['seed'] : 20260925;

if (!$confirmado) {
    fwrite(STDERR, "Este comando APAGA clientes, veículos, OS, orçamentos, pedidos, notificações e auditoria.\n");
    fwrite(STDERR, "Rode novamente com --yes para confirmar.\n");
    exit(1);
}

mt_srand($seed);

// =============================================================
// HELPERS
// =============================================================
function inserir(PDO $conn, string $sql, array $params): int
{
    static $stmts = [];
    if (!isset($stmts[$sql])) {
        $stmts[$sql] = $conn->prepare($sql);
    }
    $stmts[$sql]->execute($params);
    return (int) $conn->lastInsertId();
}

function pick(array $itens)
{
    return $itens[mt_rand(0, count($itens) - 1)];
}

function chance(int $percentual): bool
{
    return mt_rand(1, 100) <= $percentual;
}

function entre($min, $max)
{
    return mt_rand($min, $max);
}

function entreDec(float $min, float $max): float
{
    return round($min + ($max - $min) * (mt_rand(0, 10000) / 10000), 2);
}

function ponderado(array $opcoes)
{
    $total = array_sum($opcoes);
    $alvo = mt_rand(1, $total);
    $soma = 0;
    foreach ($opcoes as $valor => $peso) {
        $soma += $peso;
        if ($alvo <= $soma) {
            return $valor;
        }
    }
    return array_key_first($opcoes);
}

function dt(int $timestamp): string
{
    return date('Y-m-d H:i:s', $timestamp);
}

function data(int $timestamp): string
{
    return date('Y-m-d', $timestamp);
}

function slug(string $texto): string
{
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'Ä' => 'a',
        'É' => 'e', 'Ê' => 'e', 'È' => 'e', 'Í' => 'i', 'Ó' => 'o',
        'Õ' => 'o', 'Ô' => 'o', 'Ú' => 'u', 'Ç' => 'c',
    ];
    $texto = strtr($texto, $mapa);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '.', $texto) ?? '';
    return trim($texto, '.');
}

function gerar_cpf(): string
{
    do {
        $n = [];
        for ($i = 0; $i < 9; $i++) {
            $n[$i] = mt_rand(0, 9);
        }
    } while (count(array_unique($n)) === 1);

    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $n[$i] * (10 - $i);
    }
    $d1 = ($soma * 10) % 11;
    if ($d1 >= 10) {
        $d1 = 0;
    }
    $n[9] = $d1;

    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $n[$i] * (11 - $i);
    }
    $d2 = ($soma * 10) % 11;
    if ($d2 >= 10) {
        $d2 = 0;
    }
    $n[10] = $d2;

    return sprintf(
        '%d%d%d.%d%d%d.%d%d%d-%d%d',
        $n[0], $n[1], $n[2], $n[3], $n[4], $n[5], $n[6], $n[7], $n[8], $n[9], $n[10]
    );
}

function gerar_placa(array &$usadas): string
{
    $letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    do {
        if (chance(50)) {
            $placa = $letras[mt_rand(0, 25)] . $letras[mt_rand(0, 25)] . $letras[mt_rand(0, 25)]
                . '-' . mt_rand(1000, 9999);
        } else {
            $placa = $letras[mt_rand(0, 25)] . $letras[mt_rand(0, 25)] . $letras[mt_rand(0, 25)]
                . mt_rand(0, 9) . $letras[mt_rand(0, 25)] . mt_rand(0, 9) . mt_rand(0, 9);
        }
    } while (isset($usadas[$placa]));
    $usadas[$placa] = true;
    return $placa;
}

// =============================================================
// POOLS DE DADOS
// =============================================================
$nomes = [
    'Carlos', 'Maria', 'João', 'Ana', 'Roberto', 'Luciana', 'Fernando', 'Patrícia', 'Marcos', 'Camila',
    'Paulo', 'Juliana', 'Rafael', 'Aline', 'Gustavo', 'Bruna', 'Rodrigo', 'Vanessa', 'Thiago', 'Letícia',
    'Eduardo', 'Cristiane', 'Felipe', 'Renata', 'André', 'Débora', 'Bruno', 'Tatiane', 'Leonardo', 'Simone',
    'Diego', 'Priscila', 'Vinícius', 'Michele', 'Fábio', 'Carla', 'Alexandre', 'Bianca', 'Henrique', 'Cláudia',
    'Márcio', 'Sandra', 'Ricardo', 'Elaine', 'Sérgio', 'Adriana', 'Daniel', 'Mônica', 'Antônio', 'Rosa',
    'Marcelo', 'Vera', 'Luiz', 'Helena', 'Pedro', 'Larissa', 'Gabriel', 'Natália', 'Matheus', 'Isabela',
];

$sobrenomes = [
    'Silva', 'Costa', 'Santos', 'Oliveira', 'Souza', 'Lima', 'Pereira', 'Ferreira', 'Almeida', 'Rodrigues',
    'Nascimento', 'Carvalho', 'Araújo', 'Ribeiro', 'Gomes', 'Martins', 'Barbosa', 'Rocha', 'Dias', 'Monteiro',
    'Cardoso', 'Reis', 'Teixeira', 'Moreira', 'Nunes', 'Vieira', 'Sales', 'Machado', 'Freitas', 'Campos',
    'Pinto', 'Moura', 'Batista', 'Duarte', 'Fonseca', 'Azevedo', 'Lopes', 'Mendes', 'Correia', 'Cunha',
];

$cidades = [
    'São Paulo', 'Campinas', 'Santos', 'Ribeirão Preto', 'Sorocaba', 'São José dos Campos',
    'Rio de Janeiro', 'Niterói', 'Nova Iguaçu', 'Belo Horizonte', 'Uberlândia', 'Juiz de Fora',
    'Curitiba', 'Londrina', 'Maringá', 'Porto Alegre', 'Caxias do Sul', 'Florianópolis',
    'Salvador', 'Feira de Santana', 'Recife', 'Fortaleza', 'Natal', 'Goiânia', 'Brasília',
    'Manaus', 'Belém', 'Vitória', 'Guarulhos', 'Osasco', 'Santo André', 'Joinville',
];

$logradouros = ['Rua', 'Avenida', 'Travessa', 'Alameda', 'Rodovia', 'Praça'];

$marcas_modelos = [
    'Toyota' => ['Corolla', 'Etios', 'Yaris', 'Hilux', 'SW4', 'RAV4', 'Corolla Cross', 'Camry'],
    'Honda' => ['Civic', 'City', 'Fit', 'HR-V', 'WR-V', 'Accord', 'CR-V'],
    'Volkswagen' => ['Gol', 'Polo', 'Golf', 'T-Cross', 'Nivus', 'Virtus', 'Jetta', 'Saveiro', 'Amarok'],
    'Chevrolet' => ['Onix', 'Prisma', 'Cruze', 'Tracker', 'Montana', 'S10', 'Spin', 'Celta'],
    'Fiat' => ['Uno', 'Argo', 'Mobi', 'Cronos', 'Pulse', 'Fastback', 'Toro', 'Strada', 'Palio'],
    'Hyundai' => ['HB20', 'Creta', 'Tucson', 'i30', 'ix35'],
    'Ford' => ['Ka', 'EcoSport', 'Ranger', 'Bronco Sport', 'Territory'],
    'Jeep' => ['Renegade', 'Compass', 'Commander', 'Wrangler'],
    'Renault' => ['Kwid', 'Sandero', 'Logan', 'Duster', 'Captur', 'Oroch'],
    'Nissan' => ['March', 'Versa', 'Kicks', 'Frontier'],
    'Citroën' => ['C3', 'C4 Cactus'],
    'Peugeot' => ['208', '2008', '3008'],
    'Mitsubishi' => ['L200', 'ASX', 'Outlander'],
    'BMW' => ['320i', 'X1', 'X3'],
    'Mercedes-Benz' => ['A200', 'C180', 'GLA'],
    'Audi' => ['A3', 'Q3'],
    'Kia' => ['Sportage', 'Seltos'],
    'Caoa Chery' => ['Tiggo 5x', 'Tiggo 7'],
    'Ram' => ['Rampage'],
    'BYD' => ['Dolphin', 'Song Plus'],
];

$cores = ['Branco', 'Preto', 'Prata', 'Cinza', 'Grafite', 'Azul', 'Azul Escuro', 'Verde', 'Vermelho', 'Vinho', 'Laranja', 'Amarelo', 'Bege', 'Dourado', 'Marrom', 'Roxo', 'Rosa'];

$queixas = [
    'Veículo não liga', 'Barulho no motor', 'Tremedeira ao frear', 'Ar-condicionado não gela',
    'Luz de injeção acesa', 'Vazamento de óleo', 'Superaquecimento do motor', 'Direção puxando para a direita',
    'Freio esponjoso', 'Carro morrendo em marcha lenta', 'Barulho nas rodas dianteiras', 'Consumo de combustível alto',
    'Embreagem patinando', 'Motor fazendo ruído metálico', 'Revisão preventiva', 'Troca de óleo e filtros',
    'Pneu furando com frequência', 'Vibração no volante', 'Fumaça branca no escapamento', 'Câmbio com trancos',
    'Bateria descarregando', 'Farol baixo queimado', 'Escapamento barulhento', 'Suspensão batendo em lombadas',
    'Cheiro de combustível no habitáculo', 'Painel com luzes acesas', 'Direção hidráulica pesada',
    'Motor esquentando no trânsito', 'Freio rangendo', 'Perda de potência em subida',
];

$diagnosticos = [
    'Pastilhas e discos com desgaste avançado.', 'Correia dentada próxima do limite de uso.',
    'Sensor de oxigênio apresentando falha intermitente.', 'Vazamento identificado na junta da tampa de válvulas.',
    'Bateria sem capacidade de carga.', 'Buchas da suspensão comprometidas.', 'Rolamento dianteiro com ruído.',
    'Necessário substituir kit de embreagem completo.', 'Sistema de arrefecimento com baixa eficiência.',
    'Bobina de ignição com falha no cilindro 3.', 'Filtros saturados, necessária substituição.',
    'Bomba de combustível com pressão abaixo do especificado.', 'Amortecedores com vazamento de óleo.',
    'Correia do alternador ressecada.', 'Injeção eletrônica necessitando limpeza de bicos.',
];

$observacoes_internas = [
    'Serviço autorizado pelo cliente.', 'Aguardando peça do fornecedor.', 'Veículo liberado para retirada.',
    'Garantia de 90 dias para o serviço.', 'Cliente optou por aguardar no local.',
    'Necessário retorno em 30 dias para revisão.', 'Orçamento aprovado por telefone.',
    'Peças conferidas no recebimento.', 'Serviço executado com peças originais.',
    'Cliente solicitou orçamento por escrito.', 'Prioridade definida pelo chefe da oficina.',
    'Veículo com histórico de manutenções em dia.',
];

$descricoes_tarefas = [
    'Remover e inspecionar componente', 'Conferir nível de fluidos', 'Testar em rodagem',
    'Substituir peça danificada', 'Limpar e montar conjunto', 'Realizar diagnóstico eletrônico',
    'Calibrar e ajustar', 'Conferir torque dos parafusos', 'Realizar teste de estanqueidade',
    'Higienizar e finalizar', 'Registrar fotos do serviço', 'Conferir alinhamento',
];

$servicos_catalogo = [
    ['Troca de Óleo', 90, 180], ['Troca de Filtro de Óleo', 40, 90], ['Troca de Filtro de Ar', 50, 110],
    ['Troca de Filtro de Combustível', 60, 130], ['Troca de Filtro de Cabine', 60, 140],
    ['Alinhamento', 90, 160], ['Balanceamento', 80, 150], ['Geometria de Direção', 120, 220],
    ['Troca de Pastilhas de Freio', 120, 260], ['Troca de Discos de Freio', 180, 360],
    ['Revisão de Freios', 100, 200], ['Troca de Fluido de Freio', 90, 180],
    ['Troca de Correia Dentada', 350, 750], ['Troca de Correia do Alternador', 120, 280],
    ['Diagnóstico Eletrônico', 100, 220], ['Troca de Embreagem', 450, 1100],
    ['Reparo de Suspensão', 280, 700], ['Troca de Amortecedores', 260, 620],
    ['Troca de Rolamento', 180, 420], ['Higienização de Ar-Condicionado', 120, 260],
    ['Troca de Bateria', 80, 140], ['Reparo de Injeção Eletrônica', 220, 540],
    ['Limpeza de Bicos Injetores', 180, 420], ['Troca de Velas de Ignição', 100, 240],
    ['Revisão Completa', 250, 600], ['Reparo de Motor', 800, 2600], ['Reparo de Câmbio', 600, 1800],
    ['Troca de Bomba d\'Água', 260, 560], ['Troca de Radiador', 300, 680],
    ['Serviço de Funilaria e Pintura', 400, 1500],
];

$pecas_catalogo = [
    ['Óleo Motor 5W30 Sintético 1L', 40, 70], ['Filtro de Óleo', 25, 60], ['Filtro de Ar', 30, 80],
    ['Filtro de Combustível', 35, 90], ['Filtro de Cabine', 40, 100], ['Pastilha de Freio Dianteira', 120, 220],
    ['Pastilha de Freio Traseira', 100, 200], ['Disco de Freio Dianteiro', 200, 380],
    ['Disco de Freio Traseiro', 180, 340], ['Correia Dentada', 180, 350], ['Kit Correia Dentada', 320, 620],
    ['Tensor de Correia', 120, 260], ['Bomba d\'Água', 180, 420], ['Vela de Ignição', 30, 90],
    ['Cabo de Vela', 80, 200], ['Bateria 60Ah', 380, 560], ['Bateria 70Ah', 450, 680],
    ['Amortecedor Dianteiro', 260, 520], ['Amortecedor Traseiro', 240, 480], ['Mola Helicoidal', 180, 380],
    ['Pivô de Suspensão', 90, 200], ['Terminal de Direção', 90, 210], ['Bandeja de Suspensão', 200, 460],
    ['Kit Embreagem', 700, 1400], ['Atuador de Embreagem', 180, 380], ['Radiador', 380, 780],
    ['Sensor de Oxigênio', 220, 480], ['Bomba de Combustível', 260, 560], ['Sensor MAP', 180, 380],
    ['Bobina de Ignição', 150, 360], ['Lâmpada H4', 25, 70], ['Palheta Limpador', 40, 110],
    ['Fluido de Freio DOT4', 30, 70], ['Aditivo Radiador', 25, 60], ['Rolamento de Roda', 120, 320],
];

$fornecedores = [
    'AutoPeças Central', 'Distribuidora MotorMax', 'FreiosMaster', 'CorreiasPro', 'BateriasVolt',
    'ImportParts Brasil', 'SuspensõesFortes', 'ElétricaCar', 'Global Parts', 'OficinaSupply',
];

$pipeline = [
    'recebida', 'em_analise', 'aguardando_aprovacao', 'aguardando_peca',
    'em_execucao', 'pronta_para_entrega', 'entregue',
];
$indice_pipeline = array_flip($pipeline);

$rotulos_status = os_status_labels();

// =============================================================
// USUÁRIOS EXISTENTES (preservados)
// =============================================================
$usuarios = $conn->query("SELECT id, nome, tipo FROM usuarios WHERE ativo = 1")->fetchAll();
$mecanicos = [];
$gestores = [];
foreach ($usuarios as $u) {
    if ($u['tipo'] === 'mecanico') {
        $mecanicos[] = (int) $u['id'];
    } else {
        $gestores[] = (int) $u['id'];
    }
}
if (!$mecanicos) {
    $mecanicos = array_column($usuarios, 'id') ?: [1];
}
if (!$gestores) {
    $gestores = $mecanicos;
}
$usuariosIds = array_column($usuarios, 'id') ?: [1];

// =============================================================
// LIMPEZA (preserva usuarios)
// =============================================================
echo "Limpando dados transacionais...\n";
$conn->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'auditoria', 'notificacoes', 'cliente_servicos_historico', 'os_entregas',
    'pedido_pecas_itens', 'pedidos_pecas', 'os_tarefas', 'os_responsaveis', 'os_eventos',
    'orcamento_itens', 'orcamentos', 'ordens_servico', 'veiculos', 'clientes',
] as $tabela) {
    $conn->exec("TRUNCATE TABLE $tabela");
}
$conn->exec('SET FOREIGN_KEY_CHECKS = 1');

// =============================================================
// CLIENTES E VEÍCULOS
// =============================================================
echo "Gerando {$qtdClientes} clientes e veículos...\n";
$clientes = [];
$veiculosPorCliente = [];
$placasUsadas = [];
$cpfsUsados = [];
$emailsUsados = [];
$agora = time();
$limitePassado = $agora - (3 * 365 * 86400);

$conn->beginTransaction();

for ($i = 0; $i < $qtdClientes; $i++) {
    $nome = pick($nomes) . ' ' . pick($sobrenomes);
    if (chance(45)) {
        $nome .= ' ' . pick($sobrenomes);
    }

    do {
        $cpf = gerar_cpf();
    } while (isset($cpfsUsados[$cpf]));
    $cpfsUsados[$cpf] = true;

    $baseEmail = slug($nome);
    $email = $baseEmail . '@email.com';
    if (isset($emailsUsados[$email])) {
        $email = $baseEmail . mt_rand(10, 9999) . '@email.com';
    }
    $emailsUsados[$email] = true;

    $telefone = '(' . entre(11, 89) . ') 9' . entre(1000, 9999) . '-' . entre(1000, 9999);
    $endereco = pick($logradouros) . ' ' . pick($sobrenomes) . ', ' . entre(1, 2500);
    $cidade = pick($cidades);
    $observacoes = chance(25) ? pick([
        'Cliente preferencial.', 'Indicação de outro cliente.', 'Atende somente pela manhã.',
        'Prefere contato por WhatsApp.', 'Cliente com frota própria.',
    ]) : null;
    $cadastro = mt_rand($limitePassado, $agora);

    $clienteId = inserir(
        $conn,
        'INSERT INTO clientes (nome, cpf, telefone, email, endereco, cidade, observacoes, data_cadastro)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$nome, $cpf, $telefone, $email, $endereco, $cidade, $observacoes, dt($cadastro)]
    );
    $clientes[] = ['id' => $clienteId, 'nome' => $nome, 'cidade' => $cidade];
    $veiculosPorCliente[$clienteId] = [];

    $qtdVeiculos = ponderado([1 => 55, 2 => 32, 3 => 13]);
    for ($v = 0; $v < $qtdVeiculos; $v++) {
        $marca = pick(array_keys($marcas_modelos));
        $modelo = pick($marcas_modelos[$marca]);
        $ano = entre(2008, 2025);
        $cor = pick($cores);
        $placa = gerar_placa($placasUsadas);
        $km = entre(1000, 180000);

        $veiculoId = inserir(
            $conn,
            'INSERT INTO veiculos (cliente_id, placa, marca, modelo, ano, cor, km_atual, chassi)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$clienteId, $placa, $marca, $modelo, $ano, $cor, $km, null]
        );
        $veiculosPorCliente[$clienteId][] = [
            'id' => $veiculoId, 'marca' => $marca, 'modelo' => $modelo, 'placa' => $placa, 'km' => $km,
        ];
    }
}

// =============================================================
// ORDENS DE SERVIÇO + DEPENDÊNCIAS + ORÇAMENTOS
// =============================================================
echo "Gerando {$qtdOs} ordens de serviço...\n";

$numeroOs = 1001;
$numeroOrcamento = 1;
$contadores = [
    'os' => 0, 'responsaveis' => 0, 'tarefas' => 0, 'eventos' => 0, 'pedidos' => 0,
    'pedido_itens' => 0, 'entregas' => 0, 'historico' => 0, 'notificacoes' => 0,
    'orcamentos' => 0, 'orcamento_itens' => 0, 'auditoria' => 0,
];

for ($i = 0; $i < $qtdOs; $i++) {
    $cliente = $clientes[mt_rand(0, count($clientes) - 1)];
    $veiculosCliente = $veiculosPorCliente[$cliente['id']];
    $veiculo = (chance(97) && $veiculosCliente) ? pick($veiculosCliente) : null;

    $entradaTs = mt_rand($agora - (18 * 30 * 86400), $agora);
    $idadeDias = (int) floor(($agora - $entradaTs) / 86400);

    if ($idadeDias > 60) {
        $status = ponderado(['entregue' => 80, 'cancelada' => 10, 'pronta_para_entrega' => 3, 'em_execucao' => 5, 'aguardando_peca' => 2]);
    } elseif ($idadeDias > 30) {
        $status = ponderado(['entregue' => 55, 'cancelada' => 8, 'em_execucao' => 17, 'aguardando_peca' => 7, 'pronta_para_entrega' => 8, 'aguardando_aprovacao' => 5]);
    } elseif ($idadeDias > 15) {
        $status = ponderado(['entregue' => 35, 'cancelada' => 5, 'em_execucao' => 22, 'aguardando_peca' => 10, 'pronta_para_entrega' => 10, 'aguardando_aprovacao' => 8, 'em_analise' => 10]);
    } else {
        $status = ponderado(['recebida' => 22, 'em_analise' => 26, 'aguardando_aprovacao' => 12, 'aguardando_peca' => 10, 'em_execucao' => 24, 'pronta_para_entrega' => 4, 'entregue' => 2]);
    }

    $prioridade = ponderado(['baixa' => 15, 'normal' => 55, 'alta' => 22, 'urgente' => 8]);

    $conclusaoTs = null;
    if ($status === 'entregue') {
        $conclusaoTs = min($agora, $entradaTs + entre(1, 12) * 86400);
    }

    $temDiagnostico = isset($indice_pipeline[$status]) && $indice_pipeline[$status] >= $indice_pipeline['em_analise'];
    $diagnostico = $temDiagnostico ? pick($diagnosticos) : null;

    $osId = inserir(
        $conn,
        'INSERT INTO ordens_servico
            (numero, cliente_id, veiculo_id, status, prioridade, queixa_inicial, diagnostico,
             observacoes_internas, responsavel_chefe_id, criado_por, data_entrada, data_conclusao)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $numeroOs++,
            $cliente['id'],
            $veiculo['id'] ?? null,
            $status,
            $prioridade,
            pick($queixas),
            $diagnostico,
            chance(60) ? pick($observacoes_internas) : null,
            pick($gestores),
            pick($gestores),
            dt($entradaTs),
            $conclusaoTs ? dt($conclusaoTs) : null,
        ]
    );
    $contadores['os']++;

    // ---- Responsáveis ----
    $mecanicosAtribuidos = [];
    $qtdResp = ponderado([1 => 65, 2 => 35]);
    $disponiveis = $mecanicos;
    shuffle($disponiveis);
    for ($r = 0; $r < min($qtdResp, count($disponiveis)); $r++) {
        $mecanicoId = $disponiveis[$r];
        $mecanicosAtribuidos[] = $mecanicoId;
        $concluidoEm = $status === 'entregue' ? dt($conclusaoTs) : (chance(30) ? dt(min($agora, $entradaTs + entre(1, 6) * 86400)) : null);
        inserir(
            $conn,
            'INSERT INTO os_responsaveis (os_id, usuario_id, atribuido_por, atribuido_em, concluido_em, observacoes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$osId, $mecanicoId, pick($gestores), dt($entradaTs + entre(1, 8) * 3600), $concluidoEm, chance(40) ? pick($observacoes_internas) : null]
        );
        $contadores['responsaveis']++;
    }

    // ---- Tarefas ----
    $qtdTarefas = entre(2, 5);
    for ($t = 0; $t < $qtdTarefas; $t++) {
        if ($status === 'entregue') {
            $statusTarefa = 'concluida';
        } elseif (($indice_pipeline[$status] ?? 0) >= ($indice_pipeline['em_execucao'] ?? 0)) {
            $statusTarefa = ponderado(['concluida' => 55, 'em_execucao' => 25, 'pendente' => 15, 'bloqueada' => 5]);
        } else {
            $statusTarefa = ponderado(['pendente' => 70, 'bloqueada' => 20, 'em_execucao' => 10]);
        }
        $criadoEm = $entradaTs + entre(1, 24) * 3600;
        $concluidoEm = $statusTarefa === 'concluida' ? dt(min($agora, $criadoEm + entre(2, 72) * 3600)) : null;
        inserir(
            $conn,
            'INSERT INTO os_tarefas (os_id, descricao, status, criado_por, concluido_por, criado_em, concluido_em)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$osId, pick($descricoes_tarefas), $statusTarefa, pick($gestores), $concluidoEm ? pick($mecanicosAtribuidos ?: $mecanicos) : null, dt($criadoEm), $concluidoEm]
        );
        $contadores['tarefas']++;
    }

    // ---- Eventos (histórico de status) ----
    $eventoTs = $entradaTs;
    inserir(
        $conn,
        'INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$osId, 'criacao', null, 'recebida', 'Atendimento criado', pick($gestores), dt($eventoTs)]
    );
    $contadores['eventos']++;

    if ($status === 'cancelada') {
        $indiceFinal = entre(1, 8);
    } else {
        $indiceFinal = $indice_pipeline[$status] ?? 0;
    }
    $anterior = 'recebida';
    for ($p = 1; $p <= $indiceFinal; $p++) {
        $eventoTs = min($agora, $eventoTs + entre(2, 72) * 3600);
        $novo = $pipeline[$p];
        $tipo = match ($novo) {
            'em_analise' => 'diagnostico',
            'aguardando_peca' => 'peca_solicitada',
            default => 'status',
        };
        inserir(
            $conn,
            'INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $osId, $tipo, $anterior, $novo,
                'Status alterado de "' . $rotulos_status[$anterior] . '" para "' . $rotulos_status[$novo] . '".',
                pick($usuariosIds), dt($eventoTs),
            ]
        );
        $contadores['eventos']++;
        $anterior = $novo;
    }
    if ($status === 'cancelada') {
        $eventoTs = min($agora, $eventoTs + entre(2, 48) * 3600);
        inserir(
            $conn,
            'INSERT INTO os_eventos (os_id, tipo, status_anterior, status_novo, descricao, criado_por, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$osId, 'status', $anterior, 'cancelada', 'Ordem de serviço cancelada.', pick($gestores), dt($eventoTs)]
        );
        $contadores['eventos']++;
    }

    // ---- Pedidos de peças ----
    $geraPedido = in_array($status, ['aguardando_peca', 'em_execucao', 'pronta_para_entrega', 'entregue'], true)
        ? chance(55)
        : chance(18);
    if ($geraPedido) {
        if ($status === 'entregue') {
            $statusPedido = ponderado(['recebido' => 85, 'cancelado' => 8, 'a_caminho' => 7]);
        } elseif ($status === 'aguardando_peca') {
            $statusPedido = ponderado(['solicitado' => 30, 'validado' => 25, 'pedido' => 25, 'a_caminho' => 20]);
        } else {
            $statusPedido = ponderado(['recebido' => 70, 'a_caminho' => 15, 'pedido' => 10, 'cancelado' => 5]);
        }
        $criadoPedido = $entradaTs + entre(1, 5) * 86400;
        $previsao = $criadoPedido + entre(3, 12) * 86400;
        $recebidoEm = $statusPedido === 'recebido' ? min($agora, $previsao + entre(0, 4) * 86400) : null;
        $criadoPor = pick($gestores);

        $pedidoId = inserir(
            $conn,
            'INSERT INTO pedidos_pecas (os_id, fornecedor, status, previsao_entrega, observacoes, criado_por, recebido_por, criado_em, recebido_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $osId, pick($fornecedores), $statusPedido, data($previsao),
                chance(50) ? 'Pedido gerado automaticamente pelo sistema.' : null,
                $criadoPor, $recebidoEm ? $criadoPor : null, dt($criadoPedido), $recebidoEm ? dt($recebidoEm) : null,
            ]
        );
        $contadores['pedidos']++;

        $qtdItens = entre(1, 3);
        for ($pi = 0; $pi < $qtdItens; $pi++) {
            [$descPeca, $minPeca, $maxPeca] = pick($pecas_catalogo);
            $qtd = entre(1, 4);
            $recebido = $statusPedido === 'recebido' ? $qtd : (chance(30) ? entre(1, $qtd) : 0);
            inserir(
                $conn,
                'INSERT INTO pedido_pecas_itens (pedido_id, descricao, quantidade, custo_unitario, recebido, observacoes, recebido_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$pedidoId, $descPeca, $qtd, entreDec($minPeca, $maxPeca), $recebido, chance(30) ? 'Item conferido sem avarias.' : null, $recebido > 0 ? dt($recebidoEm ?? $previsao) : null]
            );
            $contadores['pedido_itens']++;
        }
    }

    // ---- Entrega ----
    if ($status === 'entregue' && chance(90)) {
        $modalidade = ponderado(['retirada' => 70, 'entrega' => 30]);
        $criadoEntrega = max($entradaTs, $conclusaoTs - 86400);
        inserir(
            $conn,
            'INSERT INTO os_entregas (os_id, modalidade, responsavel, destino, km_saida, checklist, observacoes, status, criado_por, concluido_por, criado_em, concluido_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $osId, $modalidade,
                $modalidade === 'retirada' ? $cliente['nome'] : pick(['Motorista A', 'Motorista B', 'Equipe de entrega']),
                $modalidade === 'entrega' ? $cliente['cidade'] : null,
                $veiculo['km'] ?? null,
                'Óleo conferido; ferramentas retiradas; veículo limpo.',
                chance(40) ? 'Entrega concluída sem ocorrências.' : null,
                'concluida', pick($gestores), pick($gestores), dt($criadoEntrega), dt($conclusaoTs),
            ]
        );
        $contadores['entregas']++;
    } elseif ($status === 'pronta_para_entrega' && chance(40)) {
        inserir(
            $conn,
            'INSERT INTO os_entregas (os_id, modalidade, responsavel, destino, km_saida, checklist, observacoes, status, criado_por, concluido_por, criado_em, concluido_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$osId, 'retirada', $cliente['nome'], null, $veiculo['km'] ?? null, null, 'Aguardando retirada do cliente.', 'planejada', pick($gestores), null, dt($agora - entre(1, 3) * 86400), null]
        );
        $contadores['entregas']++;
    }

    // ---- Histórico do cliente ----
    if ($status === 'entregue') {
        $descHistorico = 'Atendimento #' . ($numeroOs - 1) . ': ' . pick($servicos_catalogo)[0];
        inserir(
            $conn,
            'INSERT INTO cliente_servicos_historico (cliente_id, veiculo_id, os_id, descricao, realizado_em, observacoes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$cliente['id'], $veiculo['id'] ?? null, $osId, $descHistorico, dt($conclusaoTs), chance(35) ? pick($observacoes_internas) : null]
        );
        $contadores['historico']++;
    }

    // ---- Notificação ----
    if (chance(80)) {
        $destinatario = $mecanicosAtribuidos ? pick($mecanicosAtribuidos) : pick($usuariosIds);
        $lidaEm = chance(60) ? dt(min($agora, $eventoTs + entre(1, 72) * 3600)) : null;
        inserir(
            $conn,
            'INSERT INTO notificacoes (usuario_id, os_id, tipo, mensagem, lida_em, criada_em)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $destinatario, $osId, 'os_status',
                'OS #' . ($numeroOs - 1) . ' agora está "' . $rotulos_status[$status] . '".',
                $lidaEm, dt($eventoTs),
            ]
        );
        $contadores['notificacoes']++;
    }

    // ---- Orçamento ----
    if (chance(88)) {
        if ($status === 'entregue') {
            $statusOrc = ponderado(['aprovado' => 88, 'reprovado' => 7, 'cancelado' => 5]);
        } elseif ($status === 'cancelada') {
            $statusOrc = ponderado(['cancelado' => 60, 'reprovado' => 25, 'aberto' => 15]);
        } elseif (in_array($status, ['aguardando_aprovacao', 'em_analise'], true)) {
            $statusOrc = 'aberto';
        } else {
            $statusOrc = ponderado(['aprovado' => 55, 'aberto' => 35, 'reprovado' => 10]);
        }

        $dataOrcamento = min($agora, $entradaTs + entre(1, 4) * 86400);
        $validade = $dataOrcamento + 30 * 86400;

        $itensOrcamento = [];
        $totalPecas = 0.0;
        $totalMao = 0.0;

        $qtdPecas = ponderado([0 => 15, 1 => 35, 2 => 30, 3 => 20]);
        for ($pi = 0; $pi < $qtdPecas; $pi++) {
            [$desc, $min, $max] = pick($pecas_catalogo);
            $qtd = entre(1, 4);
            $preco = entreDec($min, $max);
            $subtotal = round($preco * $qtd, 2);
            $totalPecas += $subtotal;
            $itensOrcamento[] = ['peca', $desc, $qtd, $preco, $subtotal];
        }

        $qtdServicos = entre(1, 3);
        for ($si = 0; $si < $qtdServicos; $si++) {
            [$desc, $min, $max] = pick($servicos_catalogo);
            $preco = entreDec($min, $max);
            $totalMao += $preco;
            $itensOrcamento[] = ['servico', $desc, 1, $preco, $preco];
        }

        $desconto = chance(25) ? round(($totalPecas + $totalMao) * (entre(3, 15) / 100), 2) : 0.0;
        $totalGeral = max(0, round($totalPecas + $totalMao - $desconto, 2));

        $orcamentoId = inserir(
            $conn,
            'INSERT INTO orcamentos
                (numero, cliente_id, veiculo_id, os_id, data_orcamento, data_validade, status,
                 total_pecas, total_mao_obra, total_geral, desconto, observacoes, criado_por, data_criacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $numeroOrcamento++,
                $cliente['id'],
                $veiculo['id'] ?? null,
                $osId,
                data($dataOrcamento),
                data($validade),
                $statusOrc,
                round($totalPecas, 2),
                round($totalMao, 2),
                $totalGeral,
                $desconto,
                chance(40) ? 'Orçamento elaborado conforme diagnóstico técnico.' : null,
                pick($gestores),
                dt($dataOrcamento),
            ]
        );
        $contadores['orcamentos']++;

        foreach ($itensOrcamento as $item) {
            inserir(
                $conn,
                'INSERT INTO orcamento_itens (orcamento_id, tipo, descricao_item, quantidade, preco_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$orcamentoId, $item[0], $item[1], $item[2], $item[3], $item[4]]
            );
            $contadores['orcamento_itens']++;
        }
    }
}

// =============================================================
// AUDITORIA
// =============================================================
echo "Gerando registros de auditoria...\n";
$acoes = [
    ['clientes', 'criar'], ['clientes', 'atualizar'], ['veiculos', 'criar'],
    ['ordens_servico', 'criar'], ['ordens_servico', 'atualizar_status'],
    ['orcamentos', 'criar'], ['orcamentos', 'aprovar'], ['pedidos_pecas', 'criar'],
    ['usuarios', 'login'],
];
$qtdAuditoria = (int) round($qtdOs * 2.2);
for ($i = 0; $i < $qtdAuditoria; $i++) {
    [$entidade, $acao] = pick($acoes);
    $quando = mt_rand($agora - (18 * 30 * 86400), $agora);
    $dados = chance(60) ? json_encode([
        'origem' => 'seed',
        'referencia' => entre(1, max(2, $qtdOs)),
    ], JSON_UNESCAPED_UNICODE) : null;
    inserir(
        $conn,
        'INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, dados, criado_em)
         VALUES (?, ?, ?, ?, ?, ?)',
        [pick($usuariosIds), $entidade, entre(1, max(2, $qtdOs)), $acao, $dados, dt($quando)]
    );
    $contadores['auditoria']++;
}

$conn->commit();

// =============================================================
// RESUMO
// =============================================================
echo "\nSeed concluído com sucesso (seed={$seed}, clientes={$qtdClientes}, os={$qtdOs}).\n";
echo str_repeat('-', 40) . "\n";
$rotulos = [
    'os' => 'Ordens de serviço', 'responsaveis' => 'Responsáveis (OS)', 'tarefas' => 'Tarefas',
    'eventos' => 'Eventos de OS', 'pedidos' => 'Pedidos de peças', 'pedido_itens' => 'Itens de pedido',
    'entregas' => 'Entregas', 'historico' => 'Histórico de clientes', 'notificacoes' => 'Notificações',
    'orcamentos' => 'Orçamentos', 'orcamento_itens' => 'Itens de orçamento', 'auditoria' => 'Auditoria',
];
foreach ($contadores as $chave => $valor) {
    printf("%-22s %6d\n", $rotulos[$chave] ?? $chave, $valor);
}
printf("%-22s %6d\n", 'Clientes', (int) $conn->query('SELECT COUNT(*) FROM clientes')->fetchColumn());
printf("%-22s %6d\n", 'Veículos', (int) $conn->query('SELECT COUNT(*) FROM veiculos')->fetchColumn());
printf("%-22s %6d\n", 'Usuários (mantidos)', (int) $conn->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
