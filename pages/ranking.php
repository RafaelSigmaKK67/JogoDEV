<?php
/**
 * DEV SURVIVOR - Ranking global
 * Top jogadores por melhor pontuacao (publico, nao exige login).
 */
require_once __DIR__ . '/../includes/auth.php';

$rows = db()->query(
    'SELECT u.name, s.level,
            r.best_score, r.best_kills, r.best_survival_time, r.total_score, r.total_kills
     FROM rankings r
     JOIN users u          ON u.id = r.user_id
     LEFT JOIN players_stats s ON s.user_id = r.user_id
     ORDER BY r.best_score DESC, r.best_kills DESC
     LIMIT 50'
)->fetchAll();

$pageTitle = 'Ranking';
require_once __DIR__ . '/../includes/header.php';

$medals = [1 => "\u{1F947}", 2 => "\u{1F948}", 3 => "\u{1F949}"];
?>

<h1 class="page-title">&gt; Ranking Global<span class="cursor">_</span></h1>
<p class="page-sub">// os 50 devs que mais resistiram ao apocalipse digital</p>

<?php if (!$rows): ?>
    <p class="empty-msg">Nenhuma partida registrada ainda. Seja o primeiro a entrar para a historia!</p>
<?php else: ?>
    <table class="table table-ranking">
        <thead>
            <tr>
                <th>#</th>
                <th>Dev</th>
                <th>Nivel</th>
                <th>Melhor Pontuacao</th>
                <th>Melhor Kills</th>
                <th>Maior Sobrevivencia</th>
                <th>Pontos Totais</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $r):
                $pos = $i + 1;
            ?>
                <tr class="<?= $pos <= 3 ? 'rank-top' : '' ?>">
                    <td class="rank-pos"><?= $medals[$pos] ?? $pos ?></td>
                    <td class="rank-name"><?= e($r['name']) ?></td>
                    <td><span class="badge badge-level">LVL <?= e($r['level'] ?? 1) ?></span></td>
                    <td class="tx-green"><?= e(number_format((int)$r['best_score'], 0, ',', '.')) ?></td>
                    <td class="tx-red"><?= e($r['best_kills']) ?></td>
                    <td class="tx-purple"><?= e(formatTime((int)$r['best_survival_time'])) ?></td>
                    <td class="tx-dim"><?= e(number_format((int)$r['total_score'], 0, ',', '.')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
