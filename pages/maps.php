<?php
/**
 * DEV SURVIVOR - Selecao de mapas
 * Lista os 10 mapas. Cada um permite escolher a dificuldade e jogar.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();
$accountLevel = (int)($user['level'] ?? 1);

$pdo = db();
$maps = $pdo->query('SELECT m.*, b.name AS boss_name FROM maps m LEFT JOIN bosses b ON b.id = m.boss_id ORDER BY m.unlock_level, m.id')->fetchAll();
$difficulties = $pdo->query('SELECT * FROM difficulty_modes ORDER BY order_index')->fetchAll();

// Medalhas de conclusao que o jogador tem por mapa (para mostrar progresso)
$stmt = $pdo->prepare(
    'SELECT med.map_id, COUNT(*) AS done
     FROM player_medals pm JOIN medals med ON med.id = pm.medal_id
     WHERE pm.user_id = ? AND med.kind = "map_diff"
     GROUP BY med.map_id'
);
$stmt->execute([$user['id']]);
$completed = [];
foreach ($stmt->fetchAll() as $r) { $completed[(int)$r['map_id']] = (int)$r['done']; }
$totalDiffs = count($difficulties);

$pageTitle = 'Mapas';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Mapas do Sistema<span class="cursor">_</span></h1>
<p class="page-sub">// escolha o mapa e a dificuldade. Sobreviva ao tempo e derrote o boss para concluir.</p>

<section class="maps-grid">
    <?php foreach ($maps as $map):
        $locked = $accountLevel < (int)$map['unlock_level'];
        $done = $completed[(int)$map['id']] ?? 0;
    ?>
        <div class="map-card <?= $locked ? 'map-locked' : '' ?>" style="--map-accent: <?= e($map['accent_color']) ?>">
            <div class="map-head">
                <h3><?= e($map['name']) ?></h3>
                <span class="badge badge-<?= e($map['difficulty']) ?>"><?= e(strtoupper($map['difficulty'])) ?></span>
            </div>
            <p class="map-theme">// <?= e($map['theme']) ?></p>
            <p class="map-desc"><?= e($map['description']) ?></p>
            <div class="map-meta">
                <span>&#128126; Inimigos: <?= e($map['main_enemies']) ?></span>
                <span class="tx-red">&#9760; Boss: <?= e($map['boss_name'] ?? '—') ?></span>
            </div>
            <div class="map-meta">
                <span class="tx-yellow">&cent; <?= e($map['reward_coins']) ?></span>
                <span class="tx-blue">XP <?= e($map['reward_xp']) ?></span>
                <span>Medalhas: <?= e($done) ?>/<?= e($totalDiffs) ?></span>
            </div>

            <?php if ($locked): ?>
                <span class="btn btn-disabled btn-block">&#128274; Requer nivel <?= e($map['unlock_level']) ?></span>
            <?php else: ?>
                <form method="get" action="<?= e(BASE_URL) ?>/game/index.php" class="map-launch">
                    <input type="hidden" name="map" value="<?= e($map['slug']) ?>">
                    <select name="diff" class="map-diff-select">
                        <?php foreach ($difficulties as $d): ?>
                            <option value="<?= e($d['slug']) ?>" <?= $d['slug'] === 'normal' ? 'selected' : '' ?>>
                                <?= e($d['name']) ?> (boss em <?= e(formatTime((int)$d['time_required'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-block">&#9654; JOGAR</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
