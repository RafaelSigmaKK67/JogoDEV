<?php
/**
 * DEV SURVIVOR - Medalhas
 * Mostra todas as medalhas (mapa x dificuldade, mestre por mapa e a suprema).
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = db();
$userId = (int)$_SESSION['user_id'];

$maps  = $pdo->query('SELECT * FROM maps ORDER BY id')->fetchAll();
$diffs = $pdo->query('SELECT * FROM difficulty_modes ORDER BY order_index')->fetchAll();
$allMedals = $pdo->query('SELECT * FROM medals')->fetchAll();

// Indexa medalhas
$byMapDiff = [];   // [map_id][difficulty_id] = medal
$masterByMap = []; // [map_id] = medal
$supreme = null;
foreach ($allMedals as $m) {
    if ($m['kind'] === 'map_diff')   { $byMapDiff[(int)$m['map_id']][(int)$m['difficulty_id']] = $m; }
    elseif ($m['kind'] === 'map_master') { $masterByMap[(int)$m['map_id']] = $m; }
    elseif ($m['kind'] === 'supreme')    { $supreme = $m; }
}

// Medalhas conquistadas pelo jogador
$stmt = $pdo->prepare('SELECT medal_id FROM player_medals WHERE user_id = ?');
$stmt->execute([$userId]);
$earned = array_flip(array_map('intval', array_column($stmt->fetchAll(), 'medal_id')));
$totalEarned = count($earned);
$totalMedals = count($allMedals);

$pageTitle = 'Medalhas';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Medalhas<span class="cursor">_</span></h1>
<p class="page-sub">// conquistou <span class="tx-yellow"><?= e($totalEarned) ?></span> de <?= e($totalMedals) ?> medalhas</p>

<?php if ($supreme):
    $hasSupreme = isset($earned[(int)$supreme['id']]); ?>
    <div class="supreme-medal <?= $hasSupreme ? 'medal-earned' : 'medal-locked' ?>">
        <span class="supreme-icon"><?= e($supreme['icon']) ?></span>
        <div>
            <h2><?= e($supreme['name']) ?></h2>
            <p><?= e($supreme['description']) ?></p>
        </div>
        <span class="supreme-status"><?= $hasSupreme ? 'CONQUISTADA' : 'BLOQUEADA' ?></span>
    </div>
<?php endif; ?>

<section class="medals-maps">
    <?php foreach ($maps as $map): ?>
        <div class="medal-map-row">
            <div class="medal-map-name">
                <strong><?= e($map['name']) ?></strong>
                <?php if (isset($masterByMap[(int)$map['id']])):
                    $master = $masterByMap[(int)$map['id']];
                    $hasMaster = isset($earned[(int)$master['id']]); ?>
                    <span class="master-chip <?= $hasMaster ? 'earned' : '' ?>" title="<?= e($master['name']) ?>">
                        <?= e($master['icon']) ?> Mestre
                    </span>
                <?php endif; ?>
            </div>
            <div class="medal-icons">
                <?php foreach ($diffs as $d):
                    $medal = $byMapDiff[(int)$map['id']][(int)$d['id']] ?? null;
                    $has = $medal && isset($earned[(int)$medal['id']]); ?>
                    <span class="medal-chip <?= $has ? 'medal-earned' : 'medal-locked' ?>"
                          title="<?= e($medal['name'] ?? '') ?>" style="--diff-color: <?= e($d['color']) ?>">
                        <span class="medal-emoji"><?= $has ? '🏅' : '🔒' ?></span>
                        <span class="medal-diff"><?= e($d['name']) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
