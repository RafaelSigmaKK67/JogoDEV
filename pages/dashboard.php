<?php
/**
 * DEV SURVIVOR - Dashboard do jogador
 * Mostra progresso, partidas recentes, inventario e selecao de mapas.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();
$accountLevel = (int)($user['level'] ?? 1);
$xp = (int)($user['xp'] ?? 0);

// Progresso de XP da conta para o proximo nivel
$xpCurrentLevel = xpForLevel($accountLevel);
$xpNextLevel    = xpForLevel($accountLevel + 1);
$xpProgress     = $xpNextLevel > $xpCurrentLevel
    ? min(100, (int)round(100 * ($xp - $xpCurrentLevel) / ($xpNextLevel - $xpCurrentLevel)))
    : 100;

// Ultimas 10 partidas do jogador
$stmt = db()->prepare(
    'SELECT m.*, p.name AS map_name
     FROM matches m
     LEFT JOIN maps p ON p.id = m.map_id
     WHERE m.user_id = ?
     ORDER BY m.created_at DESC
     LIMIT 10'
);
$stmt->execute([$user['id']]);
$recentMatches = $stmt->fetchAll();

// Mapas disponiveis
$maps = db()->query('SELECT * FROM maps ORDER BY unlock_level, id')->fetchAll();

// Inventario de armas coletadas
$stmt = db()->prepare(
    'SELECT w.name, w.color, i.quantity
     FROM inventory i
     JOIN weapons w ON w.id = i.item_id AND i.item_type = "weapon"
     WHERE i.user_id = ?
     ORDER BY i.quantity DESC'
);
$stmt->execute([$user['id']]);
$inventory = $stmt->fetchAll();

// Posicao no ranking global
$stmt = db()->prepare(
    'SELECT COUNT(*) + 1 AS pos FROM rankings WHERE best_score > (SELECT best_score FROM rankings WHERE user_id = ?)'
);
$stmt->execute([$user['id']]);
$rankPos = (int)$stmt->fetchColumn();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Dashboard de <span class="tx-green"><?= e($user['name']) ?></span><span class="cursor">_</span></h1>

<nav class="admin-tabs">
    <a href="<?= e(BASE_URL) ?>/pages/maps.php" class="active">&#9654; Jogar (Mapas)</a>
    <a href="<?= e(BASE_URL) ?>/pages/characters.php">Personagens</a>
    <a href="<?= e(BASE_URL) ?>/pages/store.php">Loja</a>
    <a href="<?= e(BASE_URL) ?>/pages/locker.php">Armario</a>
    <a href="<?= e(BASE_URL) ?>/pages/roulette.php">Roleta</a>
    <a href="<?= e(BASE_URL) ?>/pages/medals.php">Medalhas</a>
    <a href="<?= e(BASE_URL) ?>/pages/rewards.php">Recompensas</a>
    <a href="<?= e(BASE_URL) ?>/pages/sandbox.php">Sandbox</a>
</nav>

<!-- Cartoes de estatisticas -->
<section class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Nivel da conta</span>
        <span class="stat-value tx-green"><?= e($accountLevel) ?></span>
        <div class="progress-bar"><div class="progress-fill" style="width: <?= e($xpProgress) ?>%"></div></div>
        <span class="stat-hint"><?= e($xp) ?> / <?= e($xpNextLevel) ?> XP</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Moedas</span>
        <span class="stat-value tx-yellow"><?= e(number_format((int)$user['coins'], 0, ',', '.')) ?> &cent;</span>
        <span class="stat-hint">creditos acumulados</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Melhor pontuacao</span>
        <span class="stat-value tx-blue"><?= e(number_format((int)$user['best_score'], 0, ',', '.')) ?></span>
        <span class="stat-hint">#<?= e($rankPos) ?> no ranking global</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Anomalias eliminadas</span>
        <span class="stat-value tx-red"><?= e(number_format((int)$user['total_kills'], 0, ',', '.')) ?></span>
        <span class="stat-hint"><?= e((int)$user['total_matches']) ?> partidas jogadas</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Maior sobrevivencia</span>
        <span class="stat-value tx-purple"><?= e(formatTime((int)$user['best_survival_time'])) ?></span>
        <span class="stat-hint">total: <?= e(formatTime((int)$user['total_survival_time'])) ?></span>
    </div>
</section>

<!-- Selecao de mapas -->
<h2 class="section-title">// Escolha um mapa e jogue</h2>
<section class="maps-grid">
    <?php foreach ($maps as $map):
        $locked = $accountLevel < (int)$map['unlock_level'];
    ?>
        <div class="map-card <?= $locked ? 'map-locked' : '' ?>" style="--map-accent: <?= e($map['accent_color']) ?>">
            <div class="map-head">
                <h3><?= e($map['name']) ?></h3>
                <span class="badge badge-<?= e($map['difficulty']) ?>"><?= e(strtoupper($map['difficulty'])) ?></span>
            </div>
            <p class="map-desc"><?= e($map['description']) ?></p>
            <div class="map-meta">
                <span><?= e($map['width']) ?>x<?= e($map['height']) ?>px</span>
                <span>inimigos x<?= e($map['enemy_multiplier']) ?></span>
                <?php if ($map['has_hazards']): ?><span class="tx-yellow">&#9888; zonas perigosas</span><?php endif; ?>
            </div>
            <?php if ($locked): ?>
                <span class="btn btn-disabled btn-block">&#128274; Requer nivel <?= e($map['unlock_level']) ?></span>
            <?php else: ?>
                <a class="btn btn-primary btn-block" href="<?= e(BASE_URL) ?>/game/index.php?map=<?= e($map['slug']) ?>">&#9654; JOGAR</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<div class="two-cols">
    <!-- Partidas recentes -->
    <section>
        <h2 class="section-title">// Partidas recentes</h2>
        <?php if (!$recentMatches): ?>
            <p class="empty-msg">Nenhuma partida ainda. <a href="<?= e(BASE_URL) ?>/game/index.php">Jogue a primeira!</a></p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Mapa</th><th>Pontos</th><th>Kills</th><th>Tempo</th><th>Nivel</th><th>Morto por</th><th>Data</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentMatches as $m): ?>
                        <tr>
                            <td><?= e($m['map_name'] ?? '—') ?></td>
                            <td class="tx-green"><?= e(number_format((int)$m['score'], 0, ',', '.')) ?></td>
                            <td><?= e($m['kills']) ?></td>
                            <td><?= e(formatTime((int)$m['survival_time'])) ?></td>
                            <td><?= e($m['level_reached']) ?></td>
                            <td class="tx-red"><?= e($m['died_to'] ?? '—') ?></td>
                            <td class="tx-dim"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Inventario -->
    <section>
        <h2 class="section-title">// Arsenal coletado</h2>
        <?php if (!$inventory): ?>
            <p class="empty-msg">Colete armas durante as partidas para preencher seu arsenal.</p>
        <?php else: ?>
            <ul class="inventory-list">
                <?php foreach ($inventory as $item): ?>
                    <li>
                        <span class="inv-dot" style="background: <?= e($item['color']) ?>"></span>
                        <span class="inv-name"><?= e($item['name']) ?></span>
                        <span class="inv-qty">x<?= e($item['quantity']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
