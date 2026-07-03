<?php
/**
 * DEV SURVIVOR - Salvamento de partida (API JSON)
 *
 * Recebe o resultado da partida e:
 *  - modo SANDBOX: grava apenas em sandbox_matches (nao mexe no ranking).
 *  - modo NORMAL : grava matches, atualiza players_stats, rankings e inventario;
 *                  se o mapa foi CONCLUIDO (boss derrotado), registra a conclusao,
 *                  concede medalhas e a recompensa do mapa (na 1a vez).
 *
 * Protegido por sessao + token CSRF (header X-CSRF-Token).
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function jsonFail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if (!isLoggedIn())                                       jsonFail('Nao autenticado.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')              jsonFail('Metodo invalido.', 405);
if (!csrfValidate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) jsonFail('Token CSRF invalido.', 403);

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) jsonFail('JSON invalido.');

function clampInt($v, int $min, int $max): int { return max($min, min($max, (int)$v)); }

$userId       = (int)$_SESSION['user_id'];
$mode         = ($payload['mode'] ?? 'normal') === 'sandbox' ? 'sandbox' : 'normal';
$score        = clampInt($payload['score'] ?? 0,         0, 5000000);
$kills        = clampInt($payload['kills'] ?? 0,         0, 50000);
$survivalTime = clampInt($payload['survival_time'] ?? 0, 0, 7200);
$xpEarned     = clampInt($payload['xp_earned'] ?? 0,     0, 500000);
$coinsEarned  = clampInt($payload['coins_earned'] ?? 0,  0, 200000);
$levelReached = clampInt($payload['level_reached'] ?? 1, 1, 500);
$completed    = !empty($payload['completed']);
$diedTo       = mb_substr(trim((string)($payload['died_to'] ?? '')), 0, 80);
$mapId        = (int)($payload['map_id'] ?? 0);
$diffId       = (int)($payload['difficulty_id'] ?? 0);
$charId       = (int)($payload['character_id'] ?? 0);
$weaponSlugs  = is_array($payload['weapons_collected'] ?? null)
    ? array_slice(array_filter($payload['weapons_collected'], 'is_string'), 0, 20) : [];

$pdo = db();

// Valida referencias
$validMapId = null;
if ($mapId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM maps WHERE id = ?');
    $stmt->execute([$mapId]);
    if ($stmt->fetch()) $validMapId = $mapId;
}
$validDiffId = null;
if ($diffId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM difficulty_modes WHERE id = ?');
    $stmt->execute([$diffId]);
    if ($stmt->fetch()) $validDiffId = $diffId;
}
$validCharId = null;
if ($charId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM characters WHERE id = ?');
    $stmt->execute([$charId]);
    if ($stmt->fetch()) $validCharId = $charId;
}

/* ============================================================
 * MODO SANDBOX: registra separado e encerra.
 * ============================================================ */
if ($mode === 'sandbox') {
    $settings = isset($payload['sandbox_settings']) ? mb_substr((string)$payload['sandbox_settings'], 0, 500) : null;
    $pdo->prepare(
        'INSERT INTO sandbox_matches (user_id, map_id, difficulty_id, character_id, score, kills, survival_time, settings_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$userId, $validMapId, $validDiffId, $validCharId, $score, $kills, $survivalTime, $settings]);

    echo json_encode(['ok' => true, 'mode' => 'sandbox']);
    exit;
}

/* ============================================================
 * MODO NORMAL
 * ============================================================ */
$pdo->beginTransaction();
try {
    // 1. Registra a partida
    $pdo->prepare(
        'INSERT INTO matches (user_id, map_id, difficulty_id, character_id, score, kills, survival_time, xp_earned, coins_earned, level_reached, completed, died_to)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$userId, $validMapId, $validDiffId, $validCharId, $score, $kills, $survivalTime,
                $xpEarned, $coinsEarned, $levelReached, $completed ? 1 : 0, $diedTo ?: null]);

    // 2. Conclusao de mapa (na 1a vez nesta dificuldade concede recompensa)
    $medalNames = [];
    $rewardCoins = 0;
    $rewardXp = 0;
    if ($completed && $validMapId && $validDiffId) {
        $stmt = $pdo->prepare('SELECT id FROM map_completions WHERE user_id = ? AND map_id = ? AND difficulty_id = ?');
        $stmt->execute([$userId, $validMapId, $validDiffId]);
        $firstTime = !$stmt->fetch();

        $newMedals = registerMapCompletion($userId, $validMapId, $validDiffId, $survivalTime, $score, $kills);
        if ($newMedals) {
            $in = implode(',', array_fill(0, count($newMedals), '?'));
            $stmt = $pdo->prepare("SELECT name FROM medals WHERE id IN ($in)");
            $stmt->execute($newMedals);
            $medalNames = array_column($stmt->fetchAll(), 'name');
        }
        if ($firstTime) {
            $stmt = $pdo->prepare('SELECT reward_coins, reward_xp FROM maps WHERE id = ?');
            $stmt->execute([$validMapId]);
            $map = $stmt->fetch();
            $rewardCoins = (int)($map['reward_coins'] ?? 0);
            $rewardXp    = (int)($map['reward_xp'] ?? 0);
        }
    }

    // 3. Atualiza o progresso acumulado
    $stmt = $pdo->prepare('SELECT * FROM players_stats WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    if (!$stats) {
        $pdo->prepare('INSERT INTO players_stats (user_id) VALUES (?)')->execute([$userId]);
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
    }

    $oldLevel    = (int)$stats['level'];
    $totalXpGain = $xpEarned + $rewardXp;
    $totalCoins  = $coinsEarned + $rewardCoins;
    $newXp       = (int)$stats['xp'] + $totalXpGain;
    $newLevel    = accountLevel($newXp);

    $pdo->prepare(
        'UPDATE players_stats SET
            xp = ?, level = ?, coins = coins + ?,
            total_score = total_score + ?, total_kills = total_kills + ?,
            total_matches = total_matches + 1, total_survival_time = total_survival_time + ?,
            best_score = GREATEST(best_score, ?),
            best_kills = GREATEST(best_kills, ?),
            best_survival_time = GREATEST(best_survival_time, ?)
         WHERE user_id = ?'
    )->execute([$newXp, $newLevel, $totalCoins, $score, $kills, $survivalTime,
                $score, $kills, $survivalTime, $userId]);

    // 4. Ranking
    $pdo->prepare(
        'INSERT INTO rankings (user_id, best_score, best_kills, best_survival_time, total_score, total_kills)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            best_score = GREATEST(best_score, VALUES(best_score)),
            best_kills = GREATEST(best_kills, VALUES(best_kills)),
            best_survival_time = GREATEST(best_survival_time, VALUES(best_survival_time)),
            total_score = total_score + VALUES(total_score),
            total_kills = total_kills + VALUES(total_kills)'
    )->execute([$userId, $score, $kills, $survivalTime, $score, $kills]);

    // 5. Armas coletadas -> inventario
    if ($weaponSlugs) {
        foreach (array_unique($weaponSlugs) as $slug) {
            grantWeaponBySlug($userId, $slug);
        }
    }

    $pdo->commit();
} catch (Exception $ex) {
    $pdo->rollBack();
    jsonFail('Erro ao salvar a partida: ' . $ex->getMessage(), 500);
}

echo json_encode([
    'ok'           => true,
    'mode'         => 'normal',
    'xp_total'     => $newXp,
    'level'        => $newLevel,
    'leveled_up'   => $newLevel > $oldLevel,
    'xp_next'      => xpForLevel($newLevel + 1),
    'coins_earned' => $totalCoins,
    'medals'       => $medalNames,
    'reward_coins' => $rewardCoins,
]);
