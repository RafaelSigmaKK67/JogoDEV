<?php
/**
 * DEV SURVIVOR - Tela do jogo (Canvas HTML5)
 *
 * Carrega o mapa, a dificuldade, o boss, o personagem (do loadout ou do sandbox)
 * e os efeitos equipados (skins/equipamentos), e injeta tudo em window.GAME_DB.
 *
 * URL normal : game/index.php?map=<slug>&diff=<slug>
 * URL sandbox: game/index.php?sandbox=1&map=&diff=&char=&enemies=&boss=&inf_hp=&inf_coins=&weapon=
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();
$userId = (int)$user['id'];
$accountLevel = (int)($user['level'] ?? 1);
$pdo = db();

$isSandbox = !empty($_GET['sandbox']);

/* ---------- Catalogos ---------- */
$weapons = $pdo->query('SELECT * FROM weapons ORDER BY unlock_level, id')->fetchAll();
$enemies = $pdo->query('SELECT * FROM enemies WHERE is_boss = 0 ORDER BY min_time, id')->fetchAll();
$skills  = $pdo->query('SELECT * FROM skills ORDER BY id')->fetchAll();
$maps    = $pdo->query('SELECT * FROM maps ORDER BY unlock_level, id')->fetchAll();
$difficulties = $pdo->query('SELECT * FROM difficulty_modes ORDER BY order_index')->fetchAll();

/* ---------- Dificuldade selecionada ---------- */
$diffSlug = $_GET['diff'] ?? 'normal';
$difficulty = null;
foreach ($difficulties as $d) { if ($d['slug'] === $diffSlug) { $difficulty = $d; break; } }
if (!$difficulty) { $difficulty = $difficulties[0] ?? null; }

/* ---------- Mapa selecionado ---------- */
$mapSlug = $_GET['map'] ?? '';
$selectedMap = null;
foreach ($maps as $m) {
    if ($m['slug'] === $mapSlug) {
        // Em modo normal, respeita o nivel de desbloqueio
        if (!$isSandbox && $accountLevel < (int)$m['unlock_level']) { break; }
        $selectedMap = $m;
        break;
    }
}
if (!$selectedMap) {
    // Fallback: primeiro mapa liberado
    foreach ($maps as $m) {
        if ($isSandbox || $accountLevel >= (int)$m['unlock_level']) { $selectedMap = $m; break; }
    }
}
if (!$selectedMap && $maps) { $selectedMap = $maps[0]; }

/* ---------- Boss do mapa ---------- */
$boss = null;
if ($selectedMap && $selectedMap['boss_id']) {
    $stmt = $pdo->prepare('SELECT * FROM bosses WHERE id = ?');
    $stmt->execute([(int)$selectedMap['boss_id']]);
    $boss = $stmt->fetch() ?: null;
}

/* ---------- Loadout do jogador ---------- */
$loadout = getLoadout($userId);

/* ---------- Personagem ---------- */
$character = null;
if ($isSandbox && !empty($_GET['char'])) {
    $character = characterBySlug($_GET['char']); // sandbox permite testar qualquer um
}
if (!$character && !empty($loadout['character_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM characters WHERE id = ?');
    $stmt->execute([(int)$loadout['character_id']]);
    $character = $stmt->fetch() ?: null;
}
if (!$character) {
    $character = $pdo->query("SELECT * FROM characters WHERE unlock_type = 'default' ORDER BY id LIMIT 1")->fetch() ?: null;
}

/* ---------- Resolve os efeitos equipados ---------- */
$storeById = function ($id) use ($pdo) {
    if (!$id) return null;
    $stmt = $pdo->prepare('SELECT * FROM store_items WHERE id = ?');
    $stmt->execute([(int)$id]);
    return $stmt->fetch() ?: null;
};
$equipById = function ($id) use ($pdo) {
    if (!$id) return null;
    $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id = ?');
    $stmt->execute([(int)$id]);
    return $stmt->fetch() ?: null;
};

$charSkin   = $storeById($loadout['character_skin_item_id'] ?? null);
$weaponSkin = $storeById($loadout['weapon_skin_item_id'] ?? null);
$charEffect = $storeById($loadout['char_effect_item_id'] ?? null);
$shotEffect = $storeById($loadout['shot_effect_item_id'] ?? null);
$killEffect = $storeById($loadout['kill_effect_item_id'] ?? null);

$equipBonuses = [];
foreach (['equip_helmet_id','equip_armor_id','equip_gloves_id','equip_boots_id','equip_chip_id','equip_amulet_id'] as $col) {
    $eq = $equipById($loadout[$col] ?? null);
    if ($eq) { $equipBonuses[] = ['bonus_key' => $eq['bonus_key'], 'bonus_value' => (float)$eq['bonus_value']]; }
}

// Cor dos projeteis: efeito de tiro tem prioridade; senao a skin de arma
$bulletColor = $shotEffect['color'] ?? ($weaponSkin['color'] ?? null);

$loadoutEffects = [
    'charSkin'    => $charSkin ? ['effect_key' => $charSkin['effect_key'], 'effect_value' => (float)$charSkin['effect_value']] : null,
    'weaponSkin'  => $weaponSkin ? ['effect_key' => $weaponSkin['effect_key'], 'effect_value' => (float)$weaponSkin['effect_value'], 'color' => $weaponSkin['color']] : null,
    'auraColor'   => $charEffect['color'] ?? null,
    'bulletColor' => $bulletColor,
    'killColor'   => $killEffect['color'] ?? null,
    'equipment'   => $equipBonuses,
];

/* ---------- Arsenal possuido (vira armas iniciais extras) ---------- */
$stmt = $pdo->prepare('SELECT w.slug FROM inventory i JOIN weapons w ON w.id = i.item_id AND i.item_type = "weapon" WHERE i.user_id = ?');
$stmt->execute([$userId]);
$ownedWeapons = array_column($stmt->fetchAll(), 'slug');

/* ---------- Parametros de sandbox ---------- */
$sandbox = null;
$startWeapon = null;
if ($isSandbox) {
    $sandbox = [
        'enemies'  => max(0, min(5, (float)($_GET['enemies'] ?? 1))),
        'boss'     => ($_GET['boss'] ?? '1') === '1',
        'infHp'    => ($_GET['inf_hp'] ?? '0') === '1',
        'infCoins' => ($_GET['inf_coins'] ?? '0') === '1',
    ];
    $startWeapon = !empty($_GET['weapon']) ? $_GET['weapon'] : null;
}

$gameDb = [
    'baseUrl'        => BASE_URL,
    'csrfToken'      => csrfToken(),
    'mode'           => $isSandbox ? 'sandbox' : 'normal',
    'player'         => ['name' => $user['name'], 'level' => $accountLevel, 'xp' => (int)$user['xp'], 'coins' => (int)$user['coins']],
    'weapons'        => $weapons,
    'enemies'        => $enemies,
    'skills'         => $skills,
    'selectedMap'    => $selectedMap,
    'difficulty'     => $difficulty,
    'boss'           => $boss,
    'character'      => $character,
    'loadoutEffects' => $loadoutEffects,
    'ownedWeapons'   => $ownedWeapons,
    'startWeapon'    => $startWeapon,
    'sandbox'        => $sandbox,
    'ids'            => [
        'mapId'        => $selectedMap ? (int)$selectedMap['id'] : null,
        'difficultyId' => $difficulty ? (int)$difficulty['id'] : null,
        'characterId'  => $character ? (int)$character['id'] : null,
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jogando | Dev Survivor</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/game.css">
</head>
<body class="game-body">

<div class="game-wrap">
    <canvas id="gameCanvas" width="1280" height="720"></canvas>

    <!-- Inicio -->
    <div id="startOverlay" class="overlay">
        <div class="overlay-box">
            <h1 class="overlay-title">&lt;DEV<span class="tx-green">SURVIVOR</span>/&gt;</h1>
            <p class="overlay-map">
                Mapa: <strong id="startMapName" class="tx-green"></strong>
                <span id="startMapDiff" class="badge"></span>
            </p>
            <p class="overlay-desc" id="startMapDesc"></p>
            <p class="overlay-loadout">
                Personagem: <strong id="startCharName" class="tx-blue"></strong> ·
                Dificuldade: <strong id="startDiffName" class="tx-yellow"></strong>
                <span id="startSandboxTag"></span>
            </p>
            <p class="overlay-objective" id="startObjective"></p>
            <div class="overlay-controls">
                <span><kbd>W</kbd><kbd>A</kbd><kbd>S</kbd><kbd>D</kbd> mover</span>
                <span><strong>mouse</strong> mirar/atirar</span>
                <span><kbd>Q</kbd> especial</span>
                <span><kbd>R</kbd> ultimate</span>
                <span><kbd>1</kbd>-<kbd>9</kbd>/scroll arma</span>
                <span><kbd>P</kbd> pausa</span>
                <span><kbd>M</kbd> som</span>
            </div>
            <button id="btnStart" class="btn btn-primary btn-lg">&#9654; INICIAR</button>
            <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>/pages/maps.php">&larr; Mapas</a>
        </div>
    </div>

    <!-- Level up -->
    <div id="levelupOverlay" class="overlay hidden">
        <div class="overlay-box">
            <h2 class="overlay-title tx-blue">LEVEL UP!</h2>
            <p class="overlay-desc">// escolha uma habilidade para compilar</p>
            <div id="skillChoices" class="skill-choices"></div>
        </div>
    </div>

    <!-- Pausa -->
    <div id="pauseOverlay" class="overlay hidden">
        <div class="overlay-box">
            <h2 class="overlay-title tx-yellow">PAUSADO</h2>
            <p class="overlay-desc">// pressione P para continuar</p>
            <button id="btnResume" class="btn btn-primary">CONTINUAR</button>
        </div>
    </div>

    <!-- Vitoria (mapa concluido) -->
    <div id="victoryOverlay" class="overlay hidden">
        <div class="overlay-box">
            <h2 class="overlay-title tx-green">MAPA CONCLUIDO!</h2>
            <p class="overlay-desc">// voce derrotou <strong id="vicBoss" class="tx-green"></strong> e sobreviveu ao sistema</p>
            <div class="go-stats">
                <div><span class="go-label">Pontuacao</span><span id="vicScore" class="go-value tx-green">0</span></div>
                <div><span class="go-label">Kills</span><span id="vicKills" class="go-value tx-red">0</span></div>
                <div><span class="go-label">Tempo</span><span id="vicTime" class="go-value tx-purple">0s</span></div>
                <div><span class="go-label">XP ganho</span><span id="vicXp" class="go-value tx-blue">0</span></div>
                <div><span class="go-label">Moedas</span><span id="vicCoins" class="go-value tx-yellow">0</span></div>
            </div>
            <p id="vicStatus" class="save-status">salvando conclusao...</p>
            <div class="overlay-actions">
                <button id="btnVicRestart" class="btn btn-primary">&#8635; JOGAR DE NOVO</button>
                <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>/pages/medals.php">Medalhas</a>
                <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>/pages/maps.php">Mapas</a>
            </div>
        </div>
    </div>

    <!-- Game over -->
    <div id="gameoverOverlay" class="overlay hidden">
        <div class="overlay-box">
            <h2 class="overlay-title tx-red">SEGMENTATION FAULT</h2>
            <p class="overlay-desc">// voce foi desalocado da memoria por <strong id="goKiller" class="tx-red"></strong></p>
            <div class="go-stats">
                <div><span class="go-label">Pontuacao</span><span id="goScore" class="go-value tx-green">0</span></div>
                <div><span class="go-label">Kills</span><span id="goKills" class="go-value tx-red">0</span></div>
                <div><span class="go-label">Tempo</span><span id="goTime" class="go-value tx-purple">0s</span></div>
                <div><span class="go-label">Nivel</span><span id="goLevel" class="go-value tx-blue">1</span></div>
                <div><span class="go-label">XP ganho</span><span id="goXp" class="go-value tx-blue">0</span></div>
                <div><span class="go-label">Moedas</span><span id="goCoins" class="go-value tx-yellow">0</span></div>
            </div>
            <p id="saveStatus" class="save-status">salvando pontuacao no banco...</p>
            <div class="overlay-actions">
                <button id="btnRestart" class="btn btn-primary">&#8635; JOGAR DE NOVO</button>
                <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>/pages/ranking.php">Ranking</a>
                <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>/pages/maps.php">Mapas</a>
            </div>
        </div>
    </div>
</div>

<script>
    window.GAME_DB = <?= json_encode($gameDb, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= e(BASE_URL) ?>/game/maps.js"></script>
<script src="<?= e(BASE_URL) ?>/game/items.js"></script>
<script src="<?= e(BASE_URL) ?>/game/weapons.js"></script>
<script src="<?= e(BASE_URL) ?>/game/skills.js"></script>
<script src="<?= e(BASE_URL) ?>/game/characters.js"></script>
<script src="<?= e(BASE_URL) ?>/game/enemies.js"></script>
<script src="<?= e(BASE_URL) ?>/game/player.js"></script>
<script src="<?= e(BASE_URL) ?>/game/engine.js"></script>
<script src="<?= e(BASE_URL) ?>/game/game.js"></script>
</body>
</html>
