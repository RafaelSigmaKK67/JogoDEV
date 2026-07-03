<?php
/**
 * DEV SURVIVOR - Loja
 * Compra de skins, efeitos, molduras, equipamentos, etc. com moedas.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = db();
$userId = (int)$_SESSION['user_id'];

// Abas: cada aba mapeia para categorias de store_items, ou para a tabela equipment
$tabs = [
    'skins'    => ['label' => 'Skins de Personagem', 'cats' => ['character_skin']],
    'armas'    => ['label' => 'Skins de Arma',        'cats' => ['weapon_skin']],
    'efeitos'  => ['label' => 'Efeitos',              'cats' => ['char_effect', 'shot_effect', 'kill_effect']],
    'entrada'  => ['label' => 'Entradas',             'cats' => ['entrance']],
    'molduras' => ['label' => 'Molduras',             'cats' => ['frame']],
    'equip'    => ['label' => 'Equipamentos',         'cats' => ['__equipment__']],
    'roletas'  => ['label' => 'Roletas',              'cats' => ['roulette']],
    'moedas'   => ['label' => 'Pacotes de Moedas',    'cats' => ['coin_pack']],
];
$tab = $_GET['tab'] ?? 'skins';
if (!isset($tabs[$tab])) { $tab = 'skins'; }

/* ---------- Compras (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['action'] ?? '';
    $slug   = $_POST['slug'] ?? '';
    $user   = currentUser();
    $level  = (int)$user['level'];

    if ($action === 'buy_equipment') {
        $eq = equipmentBySlug($slug);
        if (!$eq || !$eq['active']) {
            flash('error', 'Equipamento indisponivel.');
        } elseif (playerOwnsEquipment($userId, (int)$eq['id'])) {
            flash('error', 'Voce ja possui esse equipamento.');
        } elseif ($level < (int)$eq['unlock_level']) {
            flash('error', 'Requer nivel ' . (int)$eq['unlock_level'] . '.');
        } elseif (playerCoins($userId) < (int)$eq['price']) {
            flash('error', 'Moedas insuficientes.');
        } else {
            addCoins($userId, -(int)$eq['price']);
            grantEquipment($userId, (int)$eq['id']);
            flash('success', 'Equipamento "' . $eq['name'] . '" comprado! Equipe no Armario.');
        }
    } elseif ($action === 'buy') {
        $item = storeItemBySlug($slug);
        if (!$item || !$item['active']) {
            flash('error', 'Item indisponivel.');
        } elseif ($level < (int)$item['unlock_level']) {
            flash('error', 'Requer nivel ' . (int)$item['unlock_level'] . '.');
        } elseif ($item['category'] === 'roulette') {
            // Compra = um giro imediato da roleta correspondente
            $map = ['roul-common' => 'common', 'roul-rare' => 'rare', 'roul-legendary' => 'legendary'];
            $rtype = $map[$item['slug']] ?? 'common';
            // Debito + giro de forma ATOMICA: se o giro falhar, o debito e revertido.
            $pdo->beginTransaction();
            try {
                if (playerCoins($userId) < (int)$item['price']) {
                    $pdo->rollBack();
                    flash('error', 'Moedas insuficientes.');
                } else {
                    addCoins($userId, -(int)$item['price']);
                    $res = spinRoulette($userId, $rtype, true);
                    if (empty($res['ok'])) {
                        $pdo->rollBack();
                        flash('error', $res['error'] ?? 'Falha ao girar a roleta.');
                    } else {
                        $pdo->commit();
                        flash('success', 'Roleta girada! Voce ganhou: ' . ($res['label'] ?? '—'));
                    }
                }
            } catch (\Throwable $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                flash('error', 'Falha ao processar a roleta. Tente novamente.');
            }
        } elseif (playerOwnsStoreItem($userId, (int)$item['id'])) {
            flash('error', 'Voce ja possui esse item.');
        } elseif (playerCoins($userId) < (int)$item['price']) {
            flash('error', 'Moedas insuficientes.');
        } else {
            addCoins($userId, -(int)$item['price']);
            grantStoreItem($userId, (int)$item['id']);
            flash('success', '"' . $item['name'] . '" comprado! Equipe no Armario.');
        }
    }
    redirect('/pages/store.php?tab=' . urlencode($tab));
}

/* ---------- Carrega itens da aba atual ---------- */
$user = currentUser();
$isEquip = $tabs[$tab]['cats'][0] === '__equipment__';

if ($isEquip) {
    $items = $pdo->query('SELECT * FROM equipment WHERE active = 1 ORDER BY FIELD(slot,"helmet","armor","gloves","boots","chip","amulet"), price')->fetchAll();
    $stmt = $pdo->prepare('SELECT equipment_id FROM player_equipment WHERE user_id = ?');
    $stmt->execute([$userId]);
    $ownedEquip = array_column($stmt->fetchAll(), 'equipment_id');
} else {
    $placeholders = implode(',', array_fill(0, count($tabs[$tab]['cats']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM store_items WHERE category IN ($placeholders) ORDER BY FIELD(rarity,'comum','incomum','raro','epico','lendario','mitico'), price");
    $stmt->execute($tabs[$tab]['cats']);
    $items = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT store_item_id FROM player_items WHERE user_id = ?');
    $stmt->execute([$userId]);
    $ownedItems = array_column($stmt->fetchAll(), 'store_item_id');
}

$pageTitle = 'Loja';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Loja<span class="cursor">_</span></h1>
<p class="page-sub">// gaste moedas em cosmeticos e equipamentos. Saldo: <span class="tx-yellow"><?= e(number_format((int)$user['coins'],0,',','.')) ?> &cent;</span></p>

<nav class="admin-tabs">
    <?php foreach ($tabs as $key => $t): ?>
        <a href="<?= e(BASE_URL) ?>/pages/store.php?tab=<?= e($key) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e($t['label']) ?></a>
    <?php endforeach; ?>
</nav>

<section class="shop-grid">
    <?php if ($isEquip): foreach ($items as $eq):
        $owned = in_array($eq['id'], $ownedEquip);
        $rc = rarityColor($eq['rarity']);
    ?>
        <div class="shop-card" style="--rarity: <?= e($rc) ?>">
            <div class="shop-icon" style="color: <?= e($eq['color']) ?>">&#9881;</div>
            <span class="rarity-tag" style="background: <?= e($rc) ?>22; color: <?= e($rc) ?>"><?= e(rarityLabel($eq['rarity'])) ?></span>
            <h3><?= e($eq['name']) ?></h3>
            <p class="shop-slot"><?= e(strtoupper($eq['slot'])) ?></p>
            <p class="shop-desc"><?= e($eq['description']) ?></p>
            <?php if ($owned): ?>
                <span class="btn btn-disabled btn-block">&#10003; Adquirido</span>
            <?php else: ?>
                <form method="post" action="<?= e(BASE_URL) ?>/pages/store.php?tab=<?= e($tab) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="slug" value="<?= e($eq['slug']) ?>">
                    <button name="action" value="buy_equipment" class="btn btn-buy btn-block"><?= e($eq['price']) ?> &cent;</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; else: foreach ($items as $item):
        $owned = isset($ownedItems) && in_array($item['id'], $ownedItems);
        $rc = rarityColor($item['rarity']);
        $isRoulette = $item['category'] === 'roulette';
        $isCoinPack = $item['category'] === 'coin_pack';
    ?>
        <div class="shop-card" style="--rarity: <?= e($rc) ?>">
            <div class="shop-icon" style="color: <?= e($item['color']) ?>"><?= $isRoulette ? '&#127920;' : ($isCoinPack ? '&cent;' : '&#9670;') ?></div>
            <span class="rarity-tag" style="background: <?= e($rc) ?>22; color: <?= e($rc) ?>"><?= e(rarityLabel($item['rarity'])) ?></span>
            <h3><?= e($item['name']) ?></h3>
            <p class="shop-desc"><?= e($item['description']) ?></p>
            <?php if ($isCoinPack || !$item['active']): ?>
                <span class="btn btn-disabled btn-block">Indisponivel (premium)</span>
            <?php elseif ($owned): ?>
                <span class="btn btn-disabled btn-block">&#10003; Adquirido</span>
            <?php else: ?>
                <form method="post" action="<?= e(BASE_URL) ?>/pages/store.php?tab=<?= e($tab) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                    <button name="action" value="buy" class="btn btn-buy btn-block">
                        <?= $isRoulette ? 'Girar — ' : '' ?><?= e($item['price']) ?> &cent;
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
    <?php if (!$items): ?><p class="empty-msg">Nada por aqui ainda.</p><?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
