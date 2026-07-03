<?php
/**
 * DEV SURVIVOR - helpers.php
 * Helpers de economia e dados do jogo: raridades, propriedade de itens,
 * loja, personagens, equipamentos, loadout, roletas, medalhas e recompensas.
 *
 * Carregado automaticamente por includes/auth.php (que ja garante db()).
 */

/* ============================================================
 * RARIDADES
 * ============================================================ */
const RARITY_COLORS = [
    'comum'    => '#9fb3c8',
    'incomum'  => '#4dff88',
    'raro'     => '#39c2ff',
    'epico'    => '#c64dff',
    'lendario' => '#ffd700',
    'mitico'   => '#ff00aa',
];
const RARITY_ORDER = ['comum' => 0, 'incomum' => 1, 'raro' => 2, 'epico' => 3, 'lendario' => 4, 'mitico' => 5];

function rarityColor(?string $rarity): string
{
    return RARITY_COLORS[$rarity] ?? '#9fb3c8';
}

function rarityLabel(?string $rarity): string
{
    return ucfirst($rarity ?? 'comum');
}

/* ============================================================
 * NIVEL DE CONTA / XP
 * (accountLevel e xpForLevel ficam em auth.php)
 * ============================================================ */

/** Adiciona XP de conta, recalcula o nivel e retorna [novoXp, novoNivel, subiu]. */
function addAccountXp(int $userId, int $amount): array
{
    $stmt = db()->prepare('SELECT xp, level FROM players_stats WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        db()->prepare('INSERT INTO players_stats (user_id) VALUES (?)')->execute([$userId]);
        $row = ['xp' => 0, 'level' => 1];
    }
    $oldLevel = (int)$row['level'];
    $newXp    = (int)$row['xp'] + max(0, $amount);
    $newLevel = accountLevel($newXp);
    db()->prepare('UPDATE players_stats SET xp = ?, level = ? WHERE user_id = ?')
        ->execute([$newXp, $newLevel, $userId]);
    return [$newXp, $newLevel, $newLevel > $oldLevel];
}

/**
 * Soma moedas ao jogador. Aceita valor NEGATIVO (gasto) e nunca deixa o
 * saldo abaixo de zero (coluna UNSIGNED). Os chamadores ja validam o saldo
 * antes de gastar; o GREATEST e uma protecao extra.
 */
function addCoins(int $userId, int $amount): void
{
    db()->prepare('UPDATE players_stats SET coins = GREATEST(0, CAST(coins AS SIGNED) + ?) WHERE user_id = ?')
        ->execute([$amount, $userId]);
}

/** Saldo de moedas do jogador. */
function playerCoins(int $userId): int
{
    $stmt = db()->prepare('SELECT coins FROM players_stats WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/* ============================================================
 * PROPRIEDADE DE ITENS DA LOJA
 * ============================================================ */

function storeItemBySlug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM store_items WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function playerOwnsStoreItem(int $userId, int $itemId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM player_items WHERE user_id = ? AND store_item_id = ?');
    $stmt->execute([$userId, $itemId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Concede um item da loja ao jogador (propriedade).
 * Grava em player_items (unificada) e na tabela tipada da categoria.
 * Retorna true se foi concedido agora (false se ja possuia).
 */
function grantStoreItem(int $userId, int $itemId): bool
{
    $stmt = db()->prepare('SELECT category FROM store_items WHERE id = ?');
    $stmt->execute([$itemId]);
    $category = $stmt->fetchColumn();
    if ($category === false) {
        return false;
    }

    // Tabela unificada
    $ins = db()->prepare('INSERT IGNORE INTO player_items (user_id, store_item_id) VALUES (?, ?)');
    $ins->execute([$userId, $itemId]);
    $granted = $ins->rowCount() > 0;

    // Tabela tipada (espelho por categoria)
    $typed = [
        'character_skin' => 'player_skins',
        'weapon_skin'    => 'player_weapon_skins',
        'char_effect'    => 'player_effects',
        'shot_effect'    => 'player_effects',
        'kill_effect'    => 'player_effects',
        'entrance'       => 'player_effects',
        'frame'          => 'player_effects',
    ];
    if (isset($typed[$category])) {
        $table = $typed[$category]; // vem da whitelist acima
        db()->prepare("INSERT IGNORE INTO {$table} (user_id, store_item_id) VALUES (?, ?)")
            ->execute([$userId, $itemId]);
    }

    return $granted;
}

function grantStoreItemBySlug(int $userId, string $slug): bool
{
    $item = storeItemBySlug($slug);
    return $item ? grantStoreItem($userId, (int)$item['id']) : false;
}

/* ============================================================
 * PERSONAGENS
 * ============================================================ */

function characterBySlug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM characters WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function playerOwnsCharacter(int $userId, int $characterId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM player_characters WHERE user_id = ? AND character_id = ?');
    $stmt->execute([$userId, $characterId]);
    return (bool)$stmt->fetchColumn();
}

function grantCharacter(int $userId, int $characterId): bool
{
    $ins = db()->prepare('INSERT IGNORE INTO player_characters (user_id, character_id) VALUES (?, ?)');
    $ins->execute([$userId, $characterId]);
    return $ins->rowCount() > 0;
}

function grantCharacterBySlug(int $userId, string $slug): bool
{
    $c = characterBySlug($slug);
    return $c ? grantCharacter($userId, (int)$c['id']) : false;
}

/* ============================================================
 * EQUIPAMENTOS
 * ============================================================ */

function equipmentBySlug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM equipment WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function playerOwnsEquipment(int $userId, int $equipmentId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM player_equipment WHERE user_id = ? AND equipment_id = ?');
    $stmt->execute([$userId, $equipmentId]);
    return (bool)$stmt->fetchColumn();
}

function grantEquipment(int $userId, int $equipmentId): bool
{
    $ins = db()->prepare('INSERT IGNORE INTO player_equipment (user_id, equipment_id) VALUES (?, ?)');
    $ins->execute([$userId, $equipmentId]);
    return $ins->rowCount() > 0;
}

function grantEquipmentBySlug(int $userId, string $slug): bool
{
    $eq = equipmentBySlug($slug);
    return $eq ? grantEquipment($userId, (int)$eq['id']) : false;
}

/** Concede uma arma (inventario legado, vira arsenal inicial nas partidas). */
function grantWeaponBySlug(int $userId, string $slug): bool
{
    $stmt = db()->prepare('SELECT id FROM weapons WHERE slug = ?');
    $stmt->execute([$slug]);
    $weaponId = $stmt->fetchColumn();
    if (!$weaponId) {
        return false;
    }
    db()->prepare(
        'INSERT INTO inventory (user_id, item_type, item_id, quantity) VALUES (?, "weapon", ?, 1)
         ON DUPLICATE KEY UPDATE quantity = quantity + 1'
    )->execute([$userId, (int)$weaponId]);
    return true;
}

/* ============================================================
 * LOADOUT
 * ============================================================ */

/** Retorna o loadout do jogador, criando um padrao se nao existir. */
function getLoadout(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM player_loadout WHERE user_id = ?');
    $stmt->execute([$userId]);
    $loadout = $stmt->fetch();

    if (!$loadout) {
        // Personagem default
        $defChar = db()->query("SELECT id FROM characters WHERE unlock_type = 'default' ORDER BY id LIMIT 1")->fetchColumn();
        db()->prepare('INSERT INTO player_loadout (user_id, character_id) VALUES (?, ?)')
            ->execute([$userId, $defChar ?: null]);
        $stmt->execute([$userId]);
        $loadout = $stmt->fetch();
    }
    return $loadout ?: [];
}

/**
 * Garante a configuracao inicial de um jogador novo:
 * stats, ranking, personagens default e loadout.
 */
function ensurePlayerSetup(int $userId): void
{
    db()->prepare('INSERT IGNORE INTO players_stats (user_id) VALUES (?)')->execute([$userId]);
    db()->prepare('INSERT IGNORE INTO rankings (user_id) VALUES (?)')->execute([$userId]);

    // Concede todos os personagens default
    $defaults = db()->query("SELECT id FROM characters WHERE unlock_type = 'default'")->fetchAll();
    foreach ($defaults as $c) {
        grantCharacter($userId, (int)$c['id']);
    }
    // Concede a skin gratuita (Dev Classico, preco 0)
    $freeSkins = db()->query("SELECT id FROM store_items WHERE price = 0 AND category = 'character_skin'")->fetchAll();
    foreach ($freeSkins as $s) {
        grantStoreItem($userId, (int)$s['id']);
    }
    getLoadout($userId); // cria o loadout padrao
}

/* ============================================================
 * RECOMPENSAS GENERICAS (usadas por roleta e nivel de conta)
 * ============================================================ */

/**
 * Aplica uma recompensa de qualquer tipo ao jogador.
 * Retorna um rotulo legivel do que foi concedido.
 */
function applyReward(int $userId, string $type, ?string $slug, int $amount): string
{
    switch ($type) {
        case 'coins':
            addCoins($userId, $amount);
            return "+{$amount} moedas";

        case 'xp':
            addAccountXp($userId, $amount);
            return "+{$amount} XP";

        case 'skin':
        case 'weapon_skin':
        case 'effect':
            if ($slug) {
                $item = storeItemBySlug($slug);
                if ($item) {
                    grantStoreItem($userId, (int)$item['id']);
                    return $item['name'];
                }
            }
            return 'Item cosmetico';

        case 'weapon':
            if ($slug) {
                grantWeaponBySlug($userId, $slug);
                $stmt = db()->prepare('SELECT name FROM weapons WHERE slug = ?');
                $stmt->execute([$slug]);
                return (string)($stmt->fetchColumn() ?: 'Arma');
            }
            return 'Arma';

        case 'equipment':
            if ($slug) {
                $eq = equipmentBySlug($slug);
                if ($eq) {
                    grantEquipment($userId, (int)$eq['id']);
                    return $eq['name'];
                }
            }
            return 'Equipamento';

        case 'character':
            if ($slug) {
                $c = characterBySlug($slug);
                if ($c) {
                    grantCharacter($userId, (int)$c['id']);
                    return $c['name'];
                }
            }
            return 'Personagem';

        case 'roulette':
            // Recompensa que concede um giro imediato (sem custo) de uma roleta
            $result = spinRoulette($userId, $slug ?: 'common', true);
            return 'Roleta ' . ($slug ?: 'common') . ': ' . ($result['label'] ?? '—');

        default:
            return 'Recompensa';
    }
}

/* ============================================================
 * ROLETAS
 * ============================================================ */

const ROULETTE_COSTS = ['free' => 0, 'common' => 300, 'rare' => 800, 'legendary' => 2000];

function canSpinFreeRoulette(int $userId): bool
{
    $stmt = db()->prepare('SELECT last_free_roulette FROM players_stats WHERE user_id = ?');
    $stmt->execute([$userId]);
    $last = $stmt->fetchColumn();
    return $last !== date('Y-m-d');
}

/**
 * Sorteia e aplica uma recompensa de roleta.
 * $free = true ignora o custo (giro concedido por recompensa).
 * Retorna ['ok'=>bool, 'label'=>..., 'rarity'=>..., 'color'=>..., 'reward_type'=>...].
 */
function spinRoulette(int $userId, string $type, bool $free = false): array
{
    $type = in_array($type, ['free', 'common', 'rare', 'legendary'], true) ? $type : 'common';

    // Carrega a loot table
    $stmt = db()->prepare('SELECT * FROM roulette_rewards WHERE roulette_type = ?');
    $stmt->execute([$type]);
    $rewards = $stmt->fetchAll();
    if (!$rewards) {
        return ['ok' => false, 'error' => 'Roleta sem premios configurados.'];
    }

    // Sorteio ponderado
    $total = array_sum(array_map(fn($r) => (int)$r['weight'], $rewards));
    $roll = mt_rand(1, max(1, $total));
    $chosen = $rewards[count($rewards) - 1];
    foreach ($rewards as $r) {
        $roll -= (int)$r['weight'];
        if ($roll <= 0) { $chosen = $r; break; }
    }

    // Aplica o premio
    $label = applyReward($userId, $chosen['reward_type'], $chosen['reward_slug'], (int)$chosen['reward_amount']);

    // Historico
    db()->prepare(
        'INSERT INTO roulette_history (user_id, roulette_type, reward_type, label, rarity) VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $type, $chosen['reward_type'], $label, $chosen['rarity']]);

    return [
        'ok'          => true,
        'label'       => $label,
        'rarity'      => $chosen['rarity'],
        'color'       => $chosen['color'],
        'reward_type' => $chosen['reward_type'],
    ];
}

/* ============================================================
 * MEDALHAS / CONCLUSAO DE MAPA
 * ============================================================ */

function awardMedal(int $userId, int $medalId): bool
{
    $ins = db()->prepare('INSERT IGNORE INTO player_medals (user_id, medal_id) VALUES (?, ?)');
    $ins->execute([$userId, $medalId]);
    return $ins->rowCount() > 0;
}

/**
 * Registra a conclusao de um mapa em uma dificuldade e concede as medalhas
 * (mapa+dificuldade, mestre do mapa e lenda suprema). Retorna lista de medalhas novas.
 */
function registerMapCompletion(int $userId, int $mapId, int $difficultyId, int $survival, int $score, int $kills): array
{
    $pdo = db();
    $newMedals = [];

    // Medalha mapa+dificuldade
    $stmt = $pdo->prepare("SELECT id FROM medals WHERE kind = 'map_diff' AND map_id = ? AND difficulty_id = ?");
    $stmt->execute([$mapId, $difficultyId]);
    $medalId = $stmt->fetchColumn();
    $medalId = $medalId ? (int)$medalId : null;

    // Recompensas do mapa
    $stmt = $pdo->prepare('SELECT reward_coins, reward_xp FROM maps WHERE id = ?');
    $stmt->execute([$mapId]);
    $map = $stmt->fetch() ?: ['reward_coins' => 0, 'reward_xp' => 0];

    // Salva a conclusao (mantem o melhor score)
    $pdo->prepare(
        'INSERT INTO map_completions (user_id, map_id, difficulty_id, survival_time, score, kills, boss_defeated, medal_id, reward_coins, reward_xp)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            survival_time = GREATEST(survival_time, VALUES(survival_time)),
            score = GREATEST(score, VALUES(score)),
            kills = GREATEST(kills, VALUES(kills))'
    )->execute([$userId, $mapId, $difficultyId, $survival, $score, $kills, $medalId,
                (int)$map['reward_coins'], (int)$map['reward_xp']]);

    if ($medalId && awardMedal($userId, $medalId)) {
        $newMedals[] = $medalId;
    }

    // Medalha "Mestre do mapa": concluiu todas as dificuldades desse mapa
    $totalDiffs = (int)$pdo->query('SELECT COUNT(*) FROM difficulty_modes')->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT difficulty_id) FROM map_completions WHERE user_id = ? AND map_id = ?');
    $stmt->execute([$userId, $mapId]);
    if ((int)$stmt->fetchColumn() >= $totalDiffs) {
        $stmt = $pdo->prepare("SELECT id FROM medals WHERE kind = 'map_master' AND map_id = ?");
        $stmt->execute([$mapId]);
        $masterId = $stmt->fetchColumn();
        if ($masterId && awardMedal($userId, (int)$masterId)) {
            $newMedals[] = (int)$masterId;
        }
    }

    // Medalha "Lenda Suprema": todas as combinacoes mapa x dificuldade
    $totalCombos = (int)$pdo->query('SELECT COUNT(*) FROM maps') ->fetchColumn() * $totalDiffs;
    $done = (int)$pdo->query("SELECT COUNT(*) FROM map_completions WHERE user_id = {$userId}")->fetchColumn();
    if ($done >= $totalCombos) {
        $supremeId = $pdo->query("SELECT id FROM medals WHERE kind = 'supreme' LIMIT 1")->fetchColumn();
        if ($supremeId && awardMedal($userId, (int)$supremeId)) {
            $newMedals[] = (int)$supremeId;
        }
    }

    // Atualiza contador de mapas concluidos (distintos)
    $distinct = (int)$pdo->query("SELECT COUNT(DISTINCT map_id) FROM map_completions WHERE user_id = {$userId}")->fetchColumn();
    $pdo->prepare('UPDATE players_stats SET maps_completed = ? WHERE user_id = ?')->execute([$distinct, $userId]);

    return $newMedals;
}
