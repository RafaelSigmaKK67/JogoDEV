<?php
/**
 * DEV SURVIVOR - Personagens (sobreviventes)
 * Ver, desbloquear (moedas/nivel) e selecionar o personagem ativo.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = db();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['action'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $char = characterBySlug($slug);

    if (!$char) {
        flash('error', 'Personagem invalido.');
    } elseif ($action === 'buy') {
        $cid = (int)$char['id'];
        if (playerOwnsCharacter($userId, $cid)) {
            flash('error', 'Voce ja possui esse personagem.');
        } elseif ($char['unlock_type'] === 'level') {
            flash('error', 'Esse personagem desbloqueia no nivel ' . (int)$char['unlock_value'] . '.');
        } elseif ($char['unlock_type'] === 'coins') {
            $price = (int)$char['unlock_value'];
            if (playerCoins($userId) < $price) {
                flash('error', 'Moedas insuficientes. Custa ' . $price . ' moedas.');
            } else {
                addCoins($userId, -$price);
                grantCharacter($userId, $cid);
                flash('success', 'Personagem "' . $char['name'] . '" desbloqueado!');
            }
        } else {
            flash('error', 'Esse personagem nao esta a venda.');
        }
    } elseif ($action === 'select') {
        if (playerOwnsCharacter($userId, (int)$char['id'])) {
            getLoadout($userId); // garante a linha
            $pdo->prepare('UPDATE player_loadout SET character_id = ? WHERE user_id = ?')
                ->execute([(int)$char['id'], $userId]);
            flash('success', '"' . $char['name'] . '" selecionado como personagem ativo.');
        } else {
            flash('error', 'Voce ainda nao possui esse personagem.');
        }
    }
    redirect('/pages/characters.php');
}

$user = currentUser();
$accountLevel = (int)$user['level'];
$loadout = getLoadout($userId);
$activeCharId = (int)($loadout['character_id'] ?? 0);

$characters = $pdo->query('SELECT * FROM characters ORDER BY id')->fetchAll();
$owned = [];
$stmt = $pdo->prepare('SELECT character_id FROM player_characters WHERE user_id = ?');
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $r) { $owned[(int)$r['character_id']] = true; }

$pageTitle = 'Personagens';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Sobreviventes<span class="cursor">_</span></h1>
<p class="page-sub">// cada classe tem passiva, habilidade especial (Q) e ultimate (R). Moedas: <span class="tx-yellow"><?= e(number_format((int)$user['coins'],0,',','.')) ?> &cent;</span></p>

<section class="char-grid">
    <?php foreach ($characters as $c):
        $isOwned = isset($owned[(int)$c['id']]);
        $isActive = (int)$c['id'] === $activeCharId;
    ?>
        <div class="char-card <?= $isActive ? 'char-active' : '' ?>" style="--char-color: <?= e($c['color']) ?>">
            <div class="char-avatar" style="background: <?= e($c['color']) ?>22; border-color: <?= e($c['color']) ?>">
                <span style="color: <?= e($c['color']) ?>">&lt;/&gt;</span>
            </div>
            <h3><?= e($c['name']) ?><?= $isActive ? ' <span class="tag-active">ATIVO</span>' : '' ?></h3>
            <p class="char-desc"><?= e($c['description']) ?></p>
            <ul class="char-stats">
                <li>&#10084; Vida <strong><?= e($c['base_health']) ?></strong></li>
                <li>&#9889; Velocidade <strong><?= e((int)$c['base_speed']) ?></strong></li>
                <li>&#9876; Dano <strong>x<?= e($c['base_damage']) ?></strong></li>
                <li>&#128737; Defesa <strong><?= e(round($c['base_defense']*100)) ?>%</strong></li>
            </ul>
            <div class="char-abilities">
                <p><span class="ab-tag">Passiva</span> <?= e($c['passive_desc']) ?></p>
                <p><span class="ab-tag ab-q">Q</span> <strong><?= e($c['special_name']) ?></strong> — <?= e($c['special_desc']) ?></p>
                <p><span class="ab-tag ab-r">R</span> <strong><?= e($c['ultimate_name']) ?></strong> — <?= e($c['ultimate_desc']) ?></p>
            </div>

            <?php if ($isOwned): ?>
                <?php if ($isActive): ?>
                    <span class="btn btn-disabled btn-block">&#10003; Selecionado</span>
                <?php else: ?>
                    <form method="post" action="<?= e(BASE_URL) ?>/pages/characters.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="slug" value="<?= e($c['slug']) ?>">
                        <button name="action" value="select" class="btn btn-primary btn-block">Selecionar</button>
                    </form>
                <?php endif; ?>
            <?php elseif ($c['unlock_type'] === 'level'): ?>
                <span class="btn btn-disabled btn-block">&#128274; Nivel <?= e($c['unlock_value']) ?>
                    <?= $accountLevel >= (int)$c['unlock_value'] ? '(reivindique em Recompensas)' : '' ?></span>
            <?php else: /* coins */ ?>
                <form method="post" action="<?= e(BASE_URL) ?>/pages/characters.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="slug" value="<?= e($c['slug']) ?>">
                    <button name="action" value="buy" class="btn btn-buy btn-block">Comprar — <?= e($c['unlock_value']) ?> &cent;</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
