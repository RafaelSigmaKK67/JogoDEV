<?php
/**
 * DEV SURVIVOR - Recompensas por nivel de conta
 * O jogador resgata recompensas ao atingir os niveis definidos.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = db();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $rewardId = (int)($_POST['reward_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM account_level_rewards WHERE id = ?');
    $stmt->execute([$rewardId]);
    $reward = $stmt->fetch();

    $user = currentUser();
    if (!$reward) {
        flash('error', 'Recompensa invalida.');
    } elseif ((int)$user['level'] < (int)$reward['level']) {
        flash('error', 'Voce ainda nao atingiu o nivel ' . (int)$reward['level'] . '.');
    } else {
        // Marca como resgatada (unica) e, se conseguiu, aplica
        $ins = $pdo->prepare('INSERT IGNORE INTO player_account_rewards (user_id, reward_id) VALUES (?, ?)');
        $ins->execute([$userId, $rewardId]);
        if ($ins->rowCount() > 0) {
            $label = applyReward($userId, $reward['reward_type'], $reward['reward_slug'], (int)$reward['reward_amount']);
            flash('success', 'Recompensa resgatada: ' . $label . '!');
        } else {
            flash('error', 'Voce ja resgatou essa recompensa.');
        }
    }
    redirect('/pages/rewards.php');
}

$user = currentUser();
$level = (int)$user['level'];

$rewards = $pdo->query('SELECT * FROM account_level_rewards ORDER BY level')->fetchAll();
$stmt = $pdo->prepare('SELECT reward_id FROM player_account_rewards WHERE user_id = ?');
$stmt->execute([$userId]);
$claimed = array_flip(array_map('intval', array_column($stmt->fetchAll(), 'reward_id')));

// Progresso para o proximo nivel
$xp = (int)$user['xp'];
$xpCur  = xpForLevel($level);
$xpNext = xpForLevel($level + 1);
$prog = $xpNext > $xpCur ? min(100, (int)round(100 * ($xp - $xpCur) / ($xpNext - $xpCur))) : 100;

$pageTitle = 'Recompensas';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Recompensas de Conta<span class="cursor">_</span></h1>
<p class="page-sub">// suba o nivel da conta jogando partidas e resgate recompensas exclusivas.</p>

<div class="reward-level-bar">
    <div class="rlb-head"><span>Nivel da conta <strong class="tx-green"><?= e($level) ?></strong></span><span class="tx-dim"><?= e($xp) ?> / <?= e($xpNext) ?> XP</span></div>
    <div class="progress-bar"><div class="progress-fill" style="width: <?= e($prog) ?>%"></div></div>
</div>

<section class="rewards-track">
    <?php foreach ($rewards as $r):
        $isClaimed = isset($claimed[(int)$r['id']]);
        $reached = $level >= (int)$r['level'];
        $state = $isClaimed ? 'claimed' : ($reached ? 'ready' : 'locked');
    ?>
        <div class="reward-node reward-<?= $state ?>">
            <span class="reward-level">LVL <?= e($r['level']) ?></span>
            <span class="reward-icon"><?= e($r['icon']) ?></span>
            <span class="reward-label"><?= e($r['label']) ?></span>
            <?php if ($state === 'claimed'): ?>
                <span class="reward-state tx-green">&#10003; Resgatada</span>
            <?php elseif ($state === 'ready'): ?>
                <form method="post" action="<?= e(BASE_URL) ?>/pages/rewards.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="reward_id" value="<?= e($r['id']) ?>">
                    <button class="btn btn-buy btn-sm">RESGATAR</button>
                </form>
            <?php else: ?>
                <span class="reward-state tx-dim">&#128274; Nivel <?= e($r['level']) ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
