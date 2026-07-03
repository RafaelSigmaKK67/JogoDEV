<?php
/**
 * DEV SURVIVOR - Painel Administrativo
 *
 * Sections (via ?section=):
 *   overview | players | matches | logs | weapons | enemies | skills | maps
 *
 * O CRUD de weapons/enemies/skills/maps e orientado a configuracao:
 * os campos editaveis de cada tabela ficam no array $entities abaixo.
 * Nomes de tabela/coluna vem SEMPRE da configuracao (whitelist), e os
 * valores passam por prepared statements - sem SQL injection.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

/* ============================================================
 * CONFIGURACAO DAS ENTIDADES EDITAVEIS
 * ============================================================ */
$entities = [
    'weapons' => [
        'title'  => 'Armas',
        'table'  => 'weapons',
        'fields' => [
            'name'         => ['label' => 'Nome',                'type' => 'text', 'required' => true],
            'slug'         => ['label' => 'Slug (sem espacos)',  'type' => 'text', 'required' => true],
            'description'  => ['label' => 'Descricao',           'type' => 'textarea'],
            'damage'       => ['label' => 'Dano',                'type' => 'int'],
            'fire_rate'    => ['label' => 'Disparos por segundo','type' => 'float'],
            'bullet_speed' => ['label' => 'Velocidade do tiro',  'type' => 'int'],
            'bullet_range' => ['label' => 'Alcance (px)',        'type' => 'int'],
            'projectiles'  => ['label' => 'Projeteis por tiro',  'type' => 'int'],
            'spread'       => ['label' => 'Dispersao (rad)',     'type' => 'float'],
            'special'      => ['label' => 'Efeito especial',     'type' => 'select', 'options' => ['', 'area', 'laser', 'heal', 'shield']],
            'color'        => ['label' => 'Cor (hex)',           'type' => 'text'],
            'unlock_level' => ['label' => 'Nivel para liberar',  'type' => 'int'],
        ],
        'listCols' => ['id', 'name', 'damage', 'fire_rate', 'special', 'unlock_level'],
    ],
    'enemies' => [
        'title'  => 'Inimigos',
        'table'  => 'enemies',
        'fields' => [
            'name'         => ['label' => 'Nome',               'type' => 'text', 'required' => true],
            'slug'         => ['label' => 'Slug (sem espacos)', 'type' => 'text', 'required' => true],
            'description'  => ['label' => 'Descricao',          'type' => 'textarea'],
            'health'       => ['label' => 'Vida',               'type' => 'int'],
            'speed'        => ['label' => 'Velocidade (px/s)',  'type' => 'float'],
            'damage'       => ['label' => 'Dano',               'type' => 'int'],
            'xp_reward'    => ['label' => 'XP ao morrer',       'type' => 'int'],
            'coin_reward'  => ['label' => 'Moedas ao morrer',   'type' => 'int'],
            'score_reward' => ['label' => 'Pontos ao morrer',   'type' => 'int'],
            'size'         => ['label' => 'Raio (px)',          'type' => 'int'],
            'color'        => ['label' => 'Cor (hex)',          'type' => 'text'],
            'behavior'     => ['label' => 'Comportamento',      'type' => 'select', 'options' => ['chase', 'slow_heavy', 'swarm', 'group', 'tank', 'slow_player', 'boss']],
            'spawn_weight' => ['label' => 'Peso de spawn (0 = nunca)', 'type' => 'int'],
            'is_boss'      => ['label' => 'E boss?',            'type' => 'bool'],
            'min_time'     => ['label' => 'Aparece apos (s)',   'type' => 'int'],
        ],
        'listCols' => ['id', 'name', 'health', 'damage', 'behavior', 'spawn_weight', 'min_time'],
    ],
    'skills' => [
        'title'  => 'Habilidades',
        'table'  => 'skills',
        'fields' => [
            'name'         => ['label' => 'Nome',               'type' => 'text', 'required' => true],
            'slug'         => ['label' => 'Slug (sem espacos)', 'type' => 'text', 'required' => true],
            'description'  => ['label' => 'Descricao',          'type' => 'textarea'],
            'effect'       => ['label' => 'Efeito (usado no JS)', 'type' => 'select', 'options' => ['speed', 'damage', 'max_health', 'regen', 'double_shot', 'shield', 'area_attack', 'damage_reduction', 'xp_boost', 'crit_chance']],
            'effect_value' => ['label' => 'Valor do efeito',    'type' => 'float'],
            'max_stacks'   => ['label' => 'Maximo de escolhas', 'type' => 'int'],
            'icon'         => ['label' => 'Icone (texto curto)','type' => 'text'],
        ],
        'listCols' => ['id', 'name', 'effect', 'effect_value', 'max_stacks'],
    ],
    'maps' => [
        'title'  => 'Mapas',
        'table'  => 'maps',
        'fields' => [
            'name'             => ['label' => 'Nome',               'type' => 'text', 'required' => true],
            'slug'             => ['label' => 'Slug (sem espacos)', 'type' => 'text', 'required' => true],
            'description'      => ['label' => 'Descricao',          'type' => 'textarea'],
            'theme'            => ['label' => 'Tema visual',        'type' => 'text'],
            'main_enemies'     => ['label' => 'Inimigos principais','type' => 'text'],
            'boss_id'          => ['label' => 'ID do Boss',         'type' => 'int'],
            'difficulty'       => ['label' => 'Tier base (exibicao)','type' => 'select', 'options' => ['facil', 'normal', 'dificil', 'extremo', 'insano', 'pesadelo']],
            'width'            => ['label' => 'Largura (px)',       'type' => 'int'],
            'height'           => ['label' => 'Altura (px)',        'type' => 'int'],
            'bg_color'         => ['label' => 'Cor de fundo (hex)', 'type' => 'text'],
            'accent_color'     => ['label' => 'Cor de destaque',    'type' => 'text'],
            'obstacle_style'   => ['label' => 'Estilo de obstaculo','type' => 'select', 'options' => ['blocks', 'tables', 'nodes', 'clouds', 'glitch', 'firewall', 'servers', 'terminal', 'ai', 'core']],
            'obstacle_count'   => ['label' => 'Qtd. de obstaculos', 'type' => 'int'],
            'enemy_multiplier' => ['label' => 'Multiplicador de inimigos', 'type' => 'float'],
            'spawn_rate'       => ['label' => 'Velocidade de spawn','type' => 'float'],
            'has_hazards'      => ['label' => 'Zonas perigosas?',   'type' => 'bool'],
            'reward_coins'     => ['label' => 'Moedas ao concluir', 'type' => 'int'],
            'reward_xp'        => ['label' => 'XP ao concluir',     'type' => 'int'],
            'unlock_level'     => ['label' => 'Nivel para liberar', 'type' => 'int'],
        ],
        'listCols' => ['id', 'name', 'difficulty', 'boss_id', 'obstacle_style', 'unlock_level'],
    ],
    'bosses' => [
        'title'  => 'Bosses',
        'table'  => 'bosses',
        'fields' => [
            'name'           => ['label' => 'Nome',               'type' => 'text', 'required' => true],
            'slug'           => ['label' => 'Slug',               'type' => 'text', 'required' => true],
            'description'    => ['label' => 'Descricao',          'type' => 'textarea'],
            'map_id'         => ['label' => 'ID do Mapa',         'type' => 'int'],
            'health'         => ['label' => 'Vida',               'type' => 'int'],
            'speed'          => ['label' => 'Velocidade',         'type' => 'float'],
            'damage'         => ['label' => 'Dano',               'type' => 'int'],
            'size'           => ['label' => 'Tamanho (raio px)',  'type' => 'int'],
            'color'          => ['label' => 'Cor (hex)',          'type' => 'text'],
            'special_attack' => ['label' => 'Ataque especial',    'type' => 'select', 'options' => ['radial', 'summon', 'charge', 'spread', 'laser', 'nova']],
            'xp_reward'      => ['label' => 'XP ao derrotar',     'type' => 'int'],
            'coin_reward'    => ['label' => 'Moedas ao derrotar', 'type' => 'int'],
            'score_reward'   => ['label' => 'Pontos ao derrotar', 'type' => 'int'],
        ],
        'listCols' => ['id', 'name', 'map_id', 'health', 'special_attack'],
    ],
    'difficulty_modes' => [
        'title'  => 'Dificuldades',
        'table'  => 'difficulty_modes',
        'fields' => [
            'name'             => ['label' => 'Nome',                  'type' => 'text', 'required' => true],
            'slug'             => ['label' => 'Slug',                  'type' => 'text', 'required' => true],
            'order_index'      => ['label' => 'Ordem',                 'type' => 'int'],
            'hp_mult'          => ['label' => 'Mult. vida inimigos',   'type' => 'float'],
            'damage_mult'      => ['label' => 'Mult. dano inimigos',   'type' => 'float'],
            'speed_mult'       => ['label' => 'Mult. velocidade',      'type' => 'float'],
            'count_mult'       => ['label' => 'Mult. quantidade',      'type' => 'float'],
            'time_required'    => ['label' => 'Tempo p/ boss (s)',     'type' => 'int'],
            'coin_mult'        => ['label' => 'Mult. moedas',          'type' => 'float'],
            'xp_mult'          => ['label' => 'Mult. XP',              'type' => 'float'],
            'rare_drop_chance' => ['label' => 'Chance item raro',      'type' => 'float'],
            'color'            => ['label' => 'Cor (hex)',             'type' => 'text'],
        ],
        'listCols' => ['id', 'name', 'time_required', 'hp_mult', 'damage_mult'],
    ],
    'characters' => [
        'title'  => 'Personagens',
        'table'  => 'characters',
        'fields' => [
            'name'          => ['label' => 'Nome',            'type' => 'text', 'required' => true],
            'slug'          => ['label' => 'Slug',            'type' => 'text', 'required' => true],
            'description'   => ['label' => 'Descricao',       'type' => 'textarea'],
            'base_health'   => ['label' => 'Vida base',       'type' => 'int'],
            'base_speed'    => ['label' => 'Velocidade base', 'type' => 'float'],
            'base_damage'   => ['label' => 'Mult. dano base', 'type' => 'float'],
            'base_defense'  => ['label' => 'Defesa base (0-0.6)','type' => 'float'],
            'passive_key'   => ['label' => 'Passiva (chave)', 'type' => 'select', 'options' => ['none','speed','defense','balanced','anti_bot','debuff_resist','xp_gain','cooldown','max_health','coin_gain','all_bonus']],
            'passive_value' => ['label' => 'Passiva (valor)', 'type' => 'float'],
            'passive_desc'  => ['label' => 'Passiva (texto)', 'type' => 'text'],
            'special_name'  => ['label' => 'Nome da especial','type' => 'text'],
            'special_key'   => ['label' => 'Especial (chave)','type' => 'select', 'options' => ['none','dash','shield','rapid','slowtime','heal']],
            'special_desc'  => ['label' => 'Especial (texto)','type' => 'text'],
            'ultimate_name' => ['label' => 'Nome da ultimate','type' => 'text'],
            'ultimate_key'  => ['label' => 'Ultimate (chave)','type' => 'select', 'options' => ['none','rain','bigheal','buffnova','nukeall','invuln','slowxp','rapidfire','fortify','clearcoins','wipe']],
            'ultimate_desc' => ['label' => 'Ultimate (texto)','type' => 'text'],
            'unlock_type'   => ['label' => 'Desbloqueio',     'type' => 'select', 'options' => ['default','level','coins']],
            'unlock_value'  => ['label' => 'Valor (nivel/moedas)','type' => 'int'],
            'color'         => ['label' => 'Cor (hex)',       'type' => 'text'],
        ],
        'listCols' => ['id', 'name', 'unlock_type', 'unlock_value', 'base_health'],
    ],
    'store_items' => [
        'title'  => 'Itens da Loja',
        'table'  => 'store_items',
        'fields' => [
            'name'           => ['label' => 'Nome',           'type' => 'text', 'required' => true],
            'slug'           => ['label' => 'Slug',           'type' => 'text', 'required' => true],
            'description'    => ['label' => 'Descricao',      'type' => 'textarea'],
            'category'       => ['label' => 'Categoria',      'type' => 'select', 'options' => ['character_skin','weapon_skin','char_effect','shot_effect','kill_effect','entrance','frame','coin_pack','roulette']],
            'rarity'         => ['label' => 'Raridade',       'type' => 'select', 'options' => ['comum','incomum','raro','epico','lendario','mitico']],
            'price'          => ['label' => 'Preco (moedas)', 'type' => 'int'],
            'effect_key'     => ['label' => 'Efeito (chave)', 'type' => 'text'],
            'effect_value'   => ['label' => 'Efeito (valor)', 'type' => 'float'],
            'color'          => ['label' => 'Cor (hex)',      'type' => 'text'],
            'payload'        => ['label' => 'Payload',        'type' => 'int'],
            'character_slug' => ['label' => 'Slug personagem (opcional)','type' => 'text'],
            'active'         => ['label' => 'Ativo?',         'type' => 'bool'],
            'unlock_level'   => ['label' => 'Nivel p/ liberar','type' => 'int'],
        ],
        'listCols' => ['id', 'name', 'category', 'rarity', 'price', 'active'],
    ],
    'equipment' => [
        'title'  => 'Equipamentos',
        'table'  => 'equipment',
        'fields' => [
            'name'         => ['label' => 'Nome',          'type' => 'text', 'required' => true],
            'slug'         => ['label' => 'Slug',          'type' => 'text', 'required' => true],
            'description'  => ['label' => 'Descricao',     'type' => 'textarea'],
            'slot'         => ['label' => 'Slot',          'type' => 'select', 'options' => ['helmet','armor','gloves','boots','chip','amulet']],
            'rarity'       => ['label' => 'Raridade',      'type' => 'select', 'options' => ['comum','incomum','raro','epico','lendario','mitico']],
            'price'        => ['label' => 'Preco',         'type' => 'int'],
            'bonus_key'    => ['label' => 'Bonus (chave)', 'type' => 'select', 'options' => ['max_health','damage','defense','speed','xp','coins','cooldown']],
            'bonus_value'  => ['label' => 'Bonus (valor)', 'type' => 'float'],
            'color'        => ['label' => 'Cor (hex)',     'type' => 'text'],
            'active'       => ['label' => 'Ativo?',        'type' => 'bool'],
            'unlock_level' => ['label' => 'Nivel p/ liberar','type' => 'int'],
        ],
        'listCols' => ['id', 'name', 'slot', 'rarity', 'price', 'active'],
    ],
    'medals' => [
        'title'  => 'Medalhas',
        'table'  => 'medals',
        'fields' => [
            'name'          => ['label' => 'Nome',          'type' => 'text', 'required' => true],
            'slug'          => ['label' => 'Slug',          'type' => 'text', 'required' => true],
            'kind'          => ['label' => 'Tipo',          'type' => 'select', 'options' => ['map_diff','map_master','supreme']],
            'map_id'        => ['label' => 'ID Mapa (opcional)','type' => 'int'],
            'difficulty_id' => ['label' => 'ID Dificuldade (opcional)','type' => 'int'],
            'icon'          => ['label' => 'Icone',         'type' => 'text'],
            'description'   => ['label' => 'Descricao',     'type' => 'text'],
        ],
        'listCols' => ['id', 'name', 'kind', 'map_id'],
    ],
    'account_level_rewards' => [
        'title'  => 'Recompensas por Nivel',
        'table'  => 'account_level_rewards',
        'fields' => [
            'level'         => ['label' => 'Nivel',         'type' => 'int'],
            'reward_type'   => ['label' => 'Tipo',          'type' => 'select', 'options' => ['coins','xp','skin','weapon_skin','effect','weapon','equipment','character','roulette']],
            'reward_slug'   => ['label' => 'Slug do premio', 'type' => 'text'],
            'reward_amount' => ['label' => 'Quantidade',    'type' => 'int'],
            'label'         => ['label' => 'Rotulo',        'type' => 'text', 'required' => true],
            'icon'          => ['label' => 'Icone',         'type' => 'text'],
        ],
        'listCols' => ['id', 'level', 'reward_type', 'label'],
    ],
    'roulette_rewards' => [
        'title'  => 'Premios de Roleta',
        'table'  => 'roulette_rewards',
        'fields' => [
            'roulette_type' => ['label' => 'Roleta',        'type' => 'select', 'options' => ['free','common','rare','legendary']],
            'reward_type'   => ['label' => 'Tipo',          'type' => 'select', 'options' => ['coins','xp','skin','weapon_skin','effect','weapon','equipment','character']],
            'reward_slug'   => ['label' => 'Slug do premio', 'type' => 'text'],
            'reward_amount' => ['label' => 'Quantidade',    'type' => 'int'],
            'label'         => ['label' => 'Rotulo',        'type' => 'text', 'required' => true],
            'rarity'        => ['label' => 'Raridade',      'type' => 'select', 'options' => ['comum','incomum','raro','epico','lendario','mitico']],
            'weight'        => ['label' => 'Peso (sorteio)','type' => 'int'],
            'color'         => ['label' => 'Cor (hex)',     'type' => 'text'],
        ],
        'listCols' => ['id', 'roulette_type', 'reward_type', 'label', 'weight'],
    ],
];

$section = $_GET['section'] ?? 'overview';
$validSections = array_merge(['overview', 'players', 'matches', 'completions', 'roul_history', 'logs'], array_keys($entities));
if (!in_array($section, $validSections, true)) {
    $section = 'overview';
}

$pdo = db();

/* ============================================================
 * PROCESSAMENTO DOS POSTS (sempre com CSRF)
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();
    $action = $_POST['action'] ?? '';

    /* ---------- CRUD generico das entidades ---------- */
    if (isset($entities[$section]) && in_array($action, ['save', 'delete'], true)) {
        $cfg   = $entities[$section];
        $table = $cfg['table']; // vem da whitelist, nunca do usuario
        $id    = (int)($_POST['id'] ?? 0);

        if ($action === 'delete' && $id > 0) {
            $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
            logAdmin("delete_{$table}", "Registro #{$id} excluido");
            flash('success', "Registro #{$id} excluido de {$cfg['title']}.");
            redirect("/pages/admin.php?section={$section}");
        }

        if ($action === 'save') {
            // Coleta e converte os valores conforme o tipo configurado
            $values = [];
            $errors = [];
            foreach ($cfg['fields'] as $column => $field) {
                $raw = $_POST[$column] ?? '';
                switch ($field['type']) {
                    case 'int':    $values[$column] = (int)$raw; break;
                    case 'float':  $values[$column] = (float)str_replace(',', '.', (string)$raw); break;
                    case 'bool':   $values[$column] = (int)((string)$raw === '1'); break;
                    case 'select':
                        $raw = (string)$raw;
                        if (!in_array($raw, $field['options'], true)) {
                            $raw = $field['options'][0];
                        }
                        $values[$column] = ($raw === '') ? null : $raw;
                        break;
                    default:       $values[$column] = trim((string)$raw);
                }
                if (!empty($field['required']) && $values[$column] === '') {
                    $errors[] = "O campo \"{$field['label']}\" e obrigatorio.";
                }
            }

            if ($errors) {
                foreach ($errors as $err) { flash('error', $err); }
                redirect("/pages/admin.php?section={$section}" . ($id ? "&edit={$id}" : ''));
            }

            try {
                // Nome de exibicao (algumas tabelas usam 'label' em vez de 'name')
                $label = $values['name'] ?? ($values['label'] ?? "#{$id}");
                $columns = array_keys($values); // nomes vindos da config (seguros)
                if ($id > 0) {
                    $set = implode(', ', array_map(fn($c) => "{$c} = ?", $columns));
                    $stmt = $pdo->prepare("UPDATE {$table} SET {$set} WHERE id = ?");
                    $stmt->execute([...array_values($values), $id]);
                    logAdmin("update_{$table}", "Registro #{$id} atualizado: {$label}");
                    flash('success', "{$cfg['title']}: \"{$label}\" atualizado.");
                } else {
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $stmt = $pdo->prepare("INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})");
                    $stmt->execute(array_values($values));
                    logAdmin("create_{$table}", "Novo registro: {$label}");
                    flash('success', "{$cfg['title']}: \"{$label}\" cadastrado.");
                }
            } catch (PDOException $ex) {
                // Erro mais comum: slug duplicado (UNIQUE)
                flash('error', 'Erro ao salvar (slug duplicado?). Detalhe: ' . $ex->getMessage());
            }
            redirect("/pages/admin.php?section={$section}");
        }
    }

    /* ---------- Acoes sobre jogadores ---------- */
    if ($section === 'players') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $self     = (int)$_SESSION['user_id'];

        if ($action === 'toggle_admin' && $targetId > 0 && $targetId !== $self) {
            $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?')->execute([$targetId]);
            logAdmin('toggle_admin', "Permissao admin alternada para usuario #{$targetId}");
            flash('success', "Permissoes do usuario #{$targetId} alteradas.");
        } elseif ($action === 'delete_user' && $targetId > 0 && $targetId !== $self) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]); // CASCADE limpa stats/matches/rankings
            logAdmin('delete_user', "Usuario #{$targetId} excluido");
            flash('success', "Usuario #{$targetId} excluido (partidas e stats removidos em cascata).");
        } else {
            flash('error', 'Acao invalida (voce nao pode alterar a si mesmo por aqui).');
        }
        redirect('/pages/admin.php?section=players');
    }

    /* ---------- Excluir partida ---------- */
    if ($section === 'matches' && $action === 'delete_match') {
        $matchId = (int)($_POST['match_id'] ?? 0);
        if ($matchId > 0) {
            $pdo->prepare('DELETE FROM matches WHERE id = ?')->execute([$matchId]);
            logAdmin('delete_match', "Partida #{$matchId} excluida");
            flash('success', "Partida #{$matchId} excluida.");
        }
        redirect('/pages/admin.php?section=matches');
    }
}

/* ============================================================
 * CARREGAMENTO DOS DADOS DA SECTION ATUAL
 * ============================================================ */
$editRow = null;

if ($section === 'overview') {
    $counts = $pdo->query(
        'SELECT
            (SELECT COUNT(*) FROM users)    AS users,
            (SELECT COUNT(*) FROM matches)  AS matches,
            (SELECT COUNT(*) FROM weapons)  AS weapons,
            (SELECT COUNT(*) FROM enemies)  AS enemies,
            (SELECT COUNT(*) FROM skills)   AS skills,
            (SELECT COUNT(*) FROM maps)     AS maps,
            (SELECT COALESCE(SUM(kills),0) FROM matches) AS total_kills'
    )->fetch();
} elseif ($section === 'players') {
    $players = $pdo->query(
        'SELECT u.id, u.name, u.email, u.is_admin, u.created_at,
                s.level, s.xp, s.coins, s.total_matches, s.best_score
         FROM users u
         LEFT JOIN players_stats s ON s.user_id = u.id
         ORDER BY u.id'
    )->fetchAll();
} elseif ($section === 'matches') {
    $matchList = $pdo->query(
        'SELECT m.*, u.name AS player_name, p.name AS map_name
         FROM matches m
         JOIN users u  ON u.id = m.user_id
         LEFT JOIN maps p ON p.id = m.map_id
         ORDER BY m.created_at DESC
         LIMIT 50'
    )->fetchAll();
} elseif ($section === 'completions') {
    $completionList = $pdo->query(
        'SELECT mc.*, u.name AS player_name, m.name AS map_name, d.name AS diff_name
         FROM map_completions mc
         JOIN users u ON u.id = mc.user_id
         LEFT JOIN maps m ON m.id = mc.map_id
         LEFT JOIN difficulty_modes d ON d.id = mc.difficulty_id
         ORDER BY mc.created_at DESC
         LIMIT 80'
    )->fetchAll();
} elseif ($section === 'roul_history') {
    $rouletteHistory = $pdo->query(
        'SELECT rh.*, u.name AS player_name
         FROM roulette_history rh
         JOIN users u ON u.id = rh.user_id
         ORDER BY rh.created_at DESC
         LIMIT 80'
    )->fetchAll();
} elseif ($section === 'logs') {
    $logs = $pdo->query(
        'SELECT l.*, u.name AS admin_name
         FROM admin_logs l
         LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.created_at DESC
         LIMIT 50'
    )->fetchAll();
} elseif (isset($entities[$section])) {
    $cfg  = $entities[$section];
    $rows = $pdo->query("SELECT * FROM {$cfg['table']} ORDER BY id")->fetchAll();

    if (!empty($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM {$cfg['table']} WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editRow = $stmt->fetch() ?: null;
    }
}

$pageTitle = 'Painel Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Painel Administrativo<span class="cursor">_</span></h1>

<nav class="admin-tabs">
    <?php
    $tabs = ['overview' => 'Visao Geral', 'players' => 'Jogadores', 'matches' => 'Partidas',
             'completions' => 'Conclusoes', 'maps' => 'Mapas', 'bosses' => 'Bosses',
             'difficulty_modes' => 'Dificuldades', 'characters' => 'Personagens',
             'weapons' => 'Armas', 'enemies' => 'Inimigos', 'skills' => 'Habilidades',
             'store_items' => 'Loja', 'equipment' => 'Equipamentos', 'medals' => 'Medalhas',
             'account_level_rewards' => 'Recompensas', 'roulette_rewards' => 'Roletas',
             'roul_history' => 'Hist. Roleta', 'logs' => 'Logs'];
    foreach ($tabs as $key => $label): ?>
        <a href="<?= e(BASE_URL) ?>/pages/admin.php?section=<?= e($key) ?>"
           class="<?= $section === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($section === 'overview'): ?>
    <!-- ================= VISAO GERAL ================= -->
    <section class="stats-grid">
        <div class="stat-card"><span class="stat-label">Jogadores</span><span class="stat-value tx-green"><?= e($counts['users']) ?></span></div>
        <div class="stat-card"><span class="stat-label">Partidas</span><span class="stat-value tx-blue"><?= e($counts['matches']) ?></span></div>
        <div class="stat-card"><span class="stat-label">Kills globais</span><span class="stat-value tx-red"><?= e(number_format((int)$counts['total_kills'], 0, ',', '.')) ?></span></div>
        <div class="stat-card"><span class="stat-label">Armas</span><span class="stat-value tx-yellow"><?= e($counts['weapons']) ?></span></div>
        <div class="stat-card"><span class="stat-label">Inimigos</span><span class="stat-value tx-orange"><?= e($counts['enemies']) ?></span></div>
        <div class="stat-card"><span class="stat-label">Habilidades</span><span class="stat-value tx-purple"><?= e($counts['skills']) ?></span></div>
        <div class="stat-card"><span class="stat-label">Mapas</span><span class="stat-value tx-blue"><?= e($counts['maps']) ?></span></div>
    </section>
    <p class="page-sub">// use as abas acima para gerenciar o conteudo do jogo</p>

<?php elseif ($section === 'players'): ?>
    <!-- ================= JOGADORES ================= -->
    <table class="table">
        <thead><tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Nivel</th><th>Moedas</th><th>Partidas</th><th>Recorde</th><th>Admin</th><th>Acoes</th></tr></thead>
        <tbody>
        <?php foreach ($players as $p): ?>
            <tr>
                <td><?= e($p['id']) ?></td>
                <td><?= e($p['name']) ?></td>
                <td class="tx-dim"><?= e($p['email']) ?></td>
                <td><?= e($p['level'] ?? 1) ?></td>
                <td class="tx-yellow"><?= e($p['coins'] ?? 0) ?></td>
                <td><?= e($p['total_matches'] ?? 0) ?></td>
                <td class="tx-green"><?= e($p['best_score'] ?? 0) ?></td>
                <td><?= $p['is_admin'] ? '<span class="tx-yellow">SIM</span>' : 'nao' ?></td>
                <td class="actions-cell">
                    <?php if ((int)$p['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="post" action="<?= e(BASE_URL) ?>/pages/admin.php?section=players" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= e($p['id']) ?>">
                            <button name="action" value="toggle_admin" class="btn btn-sm btn-ghost" title="Alternar admin">&#9881;</button>
                            <button name="action" value="delete_user" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Excluir este usuario e TODOS os seus dados?')">&#10005;</button>
                        </form>
                    <?php else: ?>
                        <span class="tx-dim">(voce)</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($section === 'matches'): ?>
    <!-- ================= PARTIDAS ================= -->
    <table class="table">
        <thead><tr><th>ID</th><th>Jogador</th><th>Mapa</th><th>Pontos</th><th>Kills</th><th>Tempo</th><th>Morto por</th><th>Data</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($matchList as $m): ?>
            <tr>
                <td><?= e($m['id']) ?></td>
                <td><?= e($m['player_name']) ?></td>
                <td><?= e($m['map_name'] ?? '—') ?></td>
                <td class="tx-green"><?= e(number_format((int)$m['score'], 0, ',', '.')) ?></td>
                <td><?= e($m['kills']) ?></td>
                <td><?= e(formatTime((int)$m['survival_time'])) ?></td>
                <td class="tx-red"><?= e($m['died_to'] ?? '—') ?></td>
                <td class="tx-dim"><?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></td>
                <td>
                    <form method="post" action="<?= e(BASE_URL) ?>/pages/admin.php?section=matches" class="inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="match_id" value="<?= e($m['id']) ?>">
                        <button name="action" value="delete_match" class="btn btn-sm btn-danger"
                                onclick="return confirm('Excluir esta partida?')">&#10005;</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($section === 'completions'): ?>
    <!-- ================= CONCLUSOES DE MAPA ================= -->
    <table class="table">
        <thead><tr><th>ID</th><th>Jogador</th><th>Mapa</th><th>Dificuldade</th><th>Pontos</th><th>Kills</th><th>Tempo</th><th>Boss</th><th>Data</th></tr></thead>
        <tbody>
        <?php foreach ($completionList as $c): ?>
            <tr>
                <td><?= e($c['id']) ?></td>
                <td><?= e($c['player_name']) ?></td>
                <td><?= e($c['map_name'] ?? '—') ?></td>
                <td><?= e($c['diff_name'] ?? '—') ?></td>
                <td class="tx-green"><?= e(number_format((int)$c['score'], 0, ',', '.')) ?></td>
                <td><?= e($c['kills']) ?></td>
                <td><?= e(formatTime((int)$c['survival_time'])) ?></td>
                <td><?= $c['boss_defeated'] ? '<span class="tx-green">SIM</span>' : 'nao' ?></td>
                <td class="tx-dim"><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$completionList): ?><tr><td colspan="9" class="tx-dim">Nenhuma conclusao ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>

<?php elseif ($section === 'roul_history'): ?>
    <!-- ================= HISTORICO DE ROLETA ================= -->
    <table class="table">
        <thead><tr><th>ID</th><th>Jogador</th><th>Roleta</th><th>Premio</th><th>Raridade</th><th>Data</th></tr></thead>
        <tbody>
        <?php foreach ($rouletteHistory as $h): ?>
            <tr>
                <td><?= e($h['id']) ?></td>
                <td><?= e($h['player_name']) ?></td>
                <td><?= e(ucfirst($h['roulette_type'])) ?></td>
                <td><?= e($h['label']) ?></td>
                <td style="color: <?= e(rarityColor($h['rarity'])) ?>"><?= e(rarityLabel($h['rarity'])) ?></td>
                <td class="tx-dim"><?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rouletteHistory): ?><tr><td colspan="6" class="tx-dim">Nenhum giro ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>

<?php elseif ($section === 'logs'): ?>
    <!-- ================= LOGS ================= -->
    <table class="table">
        <thead><tr><th>ID</th><th>Admin</th><th>Acao</th><th>Detalhes</th><th>Data</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= e($log['id']) ?></td>
                <td><?= e($log['admin_name'] ?? 'sistema') ?></td>
                <td class="tx-blue"><?= e($log['action']) ?></td>
                <td class="tx-dim"><?= e($log['details']) ?></td>
                <td class="tx-dim"><?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif (isset($entities[$section])):
    $cfg = $entities[$section]; ?>
    <!-- ================= CRUD GENERICO ================= -->
    <div class="admin-crud">
        <section class="admin-list">
            <h2 class="section-title">// <?= e($cfg['title']) ?> cadastrados</h2>
            <table class="table">
                <thead>
                    <tr>
                        <?php foreach ($cfg['listCols'] as $col): ?><th><?= e($col) ?></th><?php endforeach; ?>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($cfg['listCols'] as $col): ?>
                            <td><?= e($row[$col] ?? '—') ?></td>
                        <?php endforeach; ?>
                        <td class="actions-cell">
                            <a class="btn btn-sm btn-ghost"
                               href="<?= e(BASE_URL) ?>/pages/admin.php?section=<?= e($section) ?>&edit=<?= e($row['id']) ?>">&#9998;</a>
                            <form method="post" action="<?= e(BASE_URL) ?>/pages/admin.php?section=<?= e($section) ?>" class="inline-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <button name="action" value="delete" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Excluir este registro?')">&#10005;</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="admin-form">
            <h2 class="section-title">
                // <?= $editRow ? 'Editar registro #' . e($editRow['id']) : 'Cadastrar novo' ?>
            </h2>
            <form method="post" action="<?= e(BASE_URL) ?>/pages/admin.php?section=<?= e($section) ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">

                <?php foreach ($cfg['fields'] as $column => $field):
                    $value = $editRow[$column] ?? ''; ?>
                    <label class="field">
                        <span class="field-label"><?= e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?></span>

                        <?php if ($field['type'] === 'textarea'): ?>
                            <textarea name="<?= e($column) ?>" rows="2"><?= e($value) ?></textarea>

                        <?php elseif ($field['type'] === 'select'): ?>
                            <select name="<?= e($column) ?>">
                                <?php foreach ($field['options'] as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= (string)$value === $opt ? 'selected' : '' ?>>
                                        <?= $opt === '' ? '(nenhum)' : e($opt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($field['type'] === 'bool'): ?>
                            <select name="<?= e($column) ?>">
                                <option value="0" <?= !(int)$value ? 'selected' : '' ?>>Nao</option>
                                <option value="1" <?= (int)$value ? 'selected' : '' ?>>Sim</option>
                            </select>

                        <?php else: ?>
                            <input type="text" name="<?= e($column) ?>" value="<?= e($value) ?>"
                                   <?= !empty($field['required']) ? 'required' : '' ?>>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary btn-block">
                    <?= $editRow ? 'SALVAR ALTERACOES' : 'CADASTRAR' ?>
                </button>
                <?php if ($editRow): ?>
                    <a class="btn btn-ghost btn-block" href="<?= e(BASE_URL) ?>/pages/admin.php?section=<?= e($section) ?>">Cancelar edicao</a>
                <?php endif; ?>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
