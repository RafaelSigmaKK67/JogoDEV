<?php
/**
 * DEV SURVIVOR - Endpoint de roleta (API JSON)
 * POST { type: free|common|rare|legendary } com header X-CSRF-Token.
 * Cobra o custo (ou usa o giro gratis diario), sorteia e aplica a recompensa.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function spinFail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (!isLoggedIn())                                      spinFail('Nao autenticado.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')             spinFail('Metodo invalido.', 405);
if (!csrfValidate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) spinFail('Token CSRF invalido.', 403);

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$type = $payload['type'] ?? 'common';
if (!in_array($type, ['free', 'common', 'rare', 'legendary'], true)) {
    spinFail('Tipo de roleta invalido.');
}

$userId = (int)$_SESSION['user_id'];
$pdo = db();

// ----- Custo / disponibilidade -----
if ($type === 'free') {
    // UPDATE condicional ATOMICO: o lock de linha do InnoDB garante que apenas
    // uma de duas requisicoes simultaneas marque o giro do dia (rowCount() === 1).
    $stmt = $pdo->prepare(
        'UPDATE players_stats SET last_free_roulette = CURDATE()
         WHERE user_id = ? AND (last_free_roulette IS NULL OR last_free_roulette <> CURDATE())'
    );
    $stmt->execute([$userId]);
    if ($stmt->rowCount() === 0) {
        spinFail('Voce ja usou o giro gratis de hoje. Volte amanha!');
    }
} else {
    $cost = ROULETTE_COSTS[$type] ?? 0;
    if (playerCoins($userId) < $cost) {
        spinFail('Moedas insuficientes (custa ' . $cost . ').');
    }
    addCoins($userId, -$cost);
}

// ----- Sorteia e aplica -----
$result = spinRoulette($userId, $type);
if (empty($result['ok'])) {
    spinFail($result['error'] ?? 'Falha ao girar a roleta.', 500);
}

// ----- Monta o "rolo" para a animacao -----
$stmt = $pdo->prepare('SELECT label, color, rarity FROM roulette_rewards WHERE roulette_type = ?');
$stmt->execute([$type]);
$pool = $stmt->fetchAll();

$reel = [];
for ($i = 0; $i < 30; $i++) {
    $p = $pool[array_rand($pool)];
    $reel[] = ['label' => $p['label'], 'color' => $p['color']];
}
$resultIndex = 25; // posicao onde o rolo para
$reel[$resultIndex] = ['label' => $result['label'], 'color' => $result['color']];

echo json_encode([
    'ok'          => true,
    'label'       => $result['label'],
    'rarity'      => $result['rarity'],
    'color'       => $result['color'],
    'reward_type' => $result['reward_type'],
    'coins'       => playerCoins($userId),
    'reel'        => $reel,
    'result_index'=> $resultIndex,
]);
