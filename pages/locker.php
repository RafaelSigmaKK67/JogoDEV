<?php
/**
 * DEV SURVIVOR - Armario (loadout)
 * Equipa personagem, skins, efeitos, moldura e equipamentos.
 * Carrega apenas itens que o jogador possui.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = db();
$userId = (int)$_SESSION['user_id'];

// Slots de itens cosmeticos: coluna do loadout => categoria do store_item
$itemSlots = [
    'character_skin_item_id' => ['label' => 'Skin de Personagem', 'cat' => 'character_skin'],
    'weapon_skin_item_id'    => ['label' => 'Skin de Arma',        'cat' => 'weapon_skin'],
    'char_effect_item_id'    => ['label' => 'Efeito de Personagem','cat' => 'char_effect'],
    'shot_effect_item_id'    => ['label' => 'Efeito de Tiro',      'cat' => 'shot_effect'],
    'kill_effect_item_id'    => ['label' => 'Efeito de Eliminacao','cat' => 'kill_effect'],
    'entrance_item_id'       => ['label' => 'Animacao de Entrada', 'cat' => 'entrance'],
    'frame_item_id'          => ['label' => 'Moldura de Perfil',   'cat' => 'frame'],
];
// Slots de equipamento: coluna do loadout => slot do equipamento
$equipSlots = [
    'equip_helmet_id' => ['label' => 'Capacete', 'slot' => 'helmet'],
    'equip_armor_id'  => ['label' => 'Armadura', 'slot' => 'armor'],
    'equip_gloves_id' => ['label' => 'Luvas',    'slot' => 'gloves'],
    'equip_boots_id'  => ['label' => 'Botas',    'slot' => 'boots'],
    'equip_chip_id'   => ['label' => 'Chip',     'slot' => 'chip'],
    'equip_amulet_id' => ['label' => 'Amuleto',  'slot' => 'amulet'],
];

// Helpers de listagem de itens possuidos
$ownedByCat = function (string $cat) use ($pdo, $userId): array {
    $stmt = $pdo->prepare('SELECT si.id, si.name, si.rarity, si.color FROM player_items pi JOIN store_items si ON si.id = pi.store_item_id WHERE pi.user_id = ? AND si.category = ? ORDER BY FIELD(si.rarity,"comum","incomum","raro","epico","lendario","mitico")');
    $stmt->execute([$userId, $cat]);
    return $stmt->fetchAll();
};
$ownedBySlot = function (string $slot) use ($pdo, $userId): array {
    $stmt = $pdo->prepare('SELECT e.id, e.name, e.rarity, e.bonus_key, e.bonus_value FROM player_equipment pe JOIN equipment e ON e.id = pe.equipment_id WHERE pe.user_id = ? AND e.slot = ?');
    $stmt->execute([$userId, $slot]);
    return $stmt->fetchAll();
};

$loadout = getLoadout($userId);

/* ---------- Salvar (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();

    $cols = [];
    $vals = [];

    // Personagem (deve possuir)
    $charId = (int)($_POST['character_id'] ?? 0);
    if ($charId > 0 && playerOwnsCharacter($userId, $charId)) {
        $cols[] = 'character_id = ?';
        $vals[] = $charId;
    }

    // Itens cosmeticos: valida posse + categoria
    foreach ($itemSlots as $col => $def) {
        $val = (int)($_POST[$col] ?? 0);
        $finalVal = null;
        if ($val > 0) {
            $stmt = $pdo->prepare('SELECT si.category FROM player_items pi JOIN store_items si ON si.id = pi.store_item_id WHERE pi.user_id = ? AND pi.store_item_id = ?');
            $stmt->execute([$userId, $val]);
            if ($stmt->fetchColumn() === $def['cat']) {
                $finalVal = $val;
            }
        }
        $cols[] = "{$col} = ?"; // nome vem da whitelist $itemSlots
        $vals[] = $finalVal;
    }

    // Equipamentos: valida posse + slot
    foreach ($equipSlots as $col => $def) {
        $val = (int)($_POST[$col] ?? 0);
        $finalVal = null;
        if ($val > 0) {
            $stmt = $pdo->prepare('SELECT e.slot FROM player_equipment pe JOIN equipment e ON e.id = pe.equipment_id WHERE pe.user_id = ? AND pe.equipment_id = ?');
            $stmt->execute([$userId, $val]);
            if ($stmt->fetchColumn() === $def['slot']) {
                $finalVal = $val;
            }
        }
        $cols[] = "{$col} = ?";
        $vals[] = $finalVal;
    }

    $vals[] = $userId;
    $pdo->prepare('UPDATE player_loadout SET ' . implode(', ', $cols) . ' WHERE user_id = ?')->execute($vals);
    flash('success', 'Loadout salvo! Suas escolhas serao usadas na proxima partida.');
    redirect('/pages/locker.php');
}

$user = currentUser();

// Personagens possuidos (para o select)
$stmt = $pdo->prepare('SELECT c.id, c.name, c.color FROM player_characters pc JOIN characters c ON c.id = pc.character_id WHERE pc.user_id = ? ORDER BY c.id');
$stmt->execute([$userId]);
$myChars = $stmt->fetchAll();

$pageTitle = 'Armario';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Armario<span class="cursor">_</span></h1>
<p class="page-sub">// equipe seu personagem, skins, efeitos e equipamentos. Itens vem da Loja e da Roleta.</p>

<form method="post" action="<?= e(BASE_URL) ?>/pages/locker.php" class="locker-form">
    <?= csrfField() ?>

    <div class="locker-section">
        <h2 class="section-title">// Personagem</h2>
        <label class="field">
            <span class="field-label">Sobrevivente ativo</span>
            <select name="character_id">
                <?php foreach ($myChars as $c): ?>
                    <option value="<?= e($c['id']) ?>" <?= (int)$c['id'] === (int)$loadout['character_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="locker-hint">Desbloqueie mais em <a href="<?= e(BASE_URL) ?>/pages/characters.php">Personagens</a>.</p>
    </div>

    <div class="locker-section">
        <h2 class="section-title">// Cosmeticos</h2>
        <div class="locker-grid">
            <?php foreach ($itemSlots as $col => $def):
                $options = $ownedByCat($def['cat']); ?>
                <label class="field">
                    <span class="field-label"><?= e($def['label']) ?></span>
                    <select name="<?= e($col) ?>">
                        <option value="0">(nenhum)</option>
                        <?php foreach ($options as $o): ?>
                            <option value="<?= e($o['id']) ?>" <?= (int)$o['id'] === (int)($loadout[$col] ?? 0) ? 'selected' : '' ?>>
                                <?= e($o['name']) ?> [<?= e(rarityLabel($o['rarity'])) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="locker-section">
        <h2 class="section-title">// Equipamentos</h2>
        <div class="locker-grid">
            <?php foreach ($equipSlots as $col => $def):
                $options = $ownedBySlot($def['slot']); ?>
                <label class="field">
                    <span class="field-label"><?= e($def['label']) ?></span>
                    <select name="<?= e($col) ?>">
                        <option value="0">(vazio)</option>
                        <?php foreach ($options as $o): ?>
                            <option value="<?= e($o['id']) ?>" <?= (int)$o['id'] === (int)($loadout[$col] ?? 0) ? 'selected' : '' ?>>
                                <?= e($o['name']) ?> [<?= e(rarityLabel($o['rarity'])) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="locker-hint">Compre equipamentos na <a href="<?= e(BASE_URL) ?>/pages/store.php?tab=equip">Loja &rarr; Equipamentos</a>.</p>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">SALVAR LOADOUT</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
