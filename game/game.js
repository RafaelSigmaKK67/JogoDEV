/**
 * DEV SURVIVOR - game.js
 * Bootstrap: liga window.GAME_DB (vindo do PHP) aos modulos do jogo,
 * controla os overlays (inicio, level up, pausa, vitoria, game over)
 * e salva a partida no banco ao concluir o mapa ou morrer.
 */
(function () {
    'use strict';

    const DB = window.GAME_DB || {};
    const canvas = document.getElementById('gameCanvas');

    /* ---------- Sprites opcionais (futuro) ---------- */
    window.GameAssets = { images: {} };
    ['player'].forEach((key) => {
        const img = new Image();
        img.onload = () => { GameAssets.images[key] = img; };
        img.src = (DB.baseUrl || '') + '/assets/img/' + key + '.png';
    });

    /* ---------- Carrega dados do banco nos modulos ---------- */
    WeaponFactory.setRows(DB.weapons);
    EnemyFactory.setRows(DB.enemies);
    Skills.setRows(DB.skills);

    const mapRuntime = GameMaps.buildRuntime(DB.selectedMap || GameMaps.fallback[0], DB.difficulty || {});
    const character = CharacterFactory.build(DB.character);
    const mods = CharacterFactory.computeMods(character, DB.loadoutEffects || {});

    // Arsenal possuido (armas extras iniciais)
    const ownedWeaponCfgs = (DB.ownedWeapons || []).map((s) => WeaponFactory.bySlug(s)).filter(Boolean);
    // Arma inicial (sandbox pode forcar uma arma de teste)
    let starterCfg = WeaponFactory.starter();
    if (DB.startWeapon) {
        const w = WeaponFactory.bySlug(DB.startWeapon);
        if (w) starterCfg = w;
    }

    const fx = DB.loadoutEffects || {};

    /* ---------- UI ---------- */
    const ui = {
        start:    document.getElementById('startOverlay'),
        levelup:  document.getElementById('levelupOverlay'),
        pause:    document.getElementById('pauseOverlay'),
        victory:  document.getElementById('victoryOverlay'),
        gameover: document.getElementById('gameoverOverlay'),
        skillChoices: document.getElementById('skillChoices'),
    };
    const show = (el) => el.classList.remove('hidden');
    const hide = (el) => el.classList.add('hidden');

    // Preenche a tela inicial
    document.getElementById('startMapName').textContent = mapRuntime.name;
    document.getElementById('startMapDesc').textContent = mapRuntime.description;
    document.getElementById('startCharName').textContent = character.name;
    document.getElementById('startDiffName').textContent = DB.difficulty ? DB.difficulty.name : 'Normal';
    const diffBadge = document.getElementById('startMapDiff');
    diffBadge.textContent = (DB.difficulty ? DB.difficulty.name : 'Normal').toUpperCase();
    diffBadge.className = 'badge';
    diffBadge.style.background = (DB.difficulty ? DB.difficulty.color : '#39c2ff') + '22';
    diffBadge.style.color = DB.difficulty ? DB.difficulty.color : '#39c2ff';

    const objective = document.getElementById('startObjective');
    if (DB.mode === 'sandbox') {
        document.getElementById('startSandboxTag').innerHTML = ' <span class="tx-red">[SANDBOX]</span>';
        objective.textContent = '// modo livre — nao conta no ranking oficial';
    } else if (DB.boss) {
        const t = mapRuntime.timeRequired;
        objective.innerHTML = '🎯 Objetivo: sobreviva ' + Math.floor(t / 60) + 'm ' + (t % 60) + 's e derrote <strong>' + escapeHtml(DB.boss.name) + '</strong>';
    } else {
        objective.textContent = '🎯 Objetivo: sobreviva o maximo possivel';
    }

    let engine = null;

    /* ---------- Callbacks ---------- */
    const callbacks = {
        onLevelUp(choices) {
            ui.skillChoices.innerHTML = '';
            choices.forEach((skill) => {
                const stacks = Skills.stacksOf(engine.player, skill);
                const card = document.createElement('button');
                card.className = 'skill-card';
                card.innerHTML =
                    '<span class="skill-icon">' + escapeHtml(skill.icon) + '</span>' +
                    '<span class="skill-name">' + escapeHtml(skill.name) + '</span>' +
                    '<span class="skill-desc">' + escapeHtml(skill.description) + '</span>' +
                    '<span class="skill-stacks">' + stacks + '/' + skill.maxStacks + '</span>';
                card.addEventListener('click', () => { hide(ui.levelup); engine.applySkillChoice(skill); });
                ui.skillChoices.appendChild(card);
            });
            show(ui.levelup);
        },
        onPause(paused) { paused ? show(ui.pause) : hide(ui.pause); },
        onGameOver(stats) {
            document.getElementById('goKiller').textContent = stats.died_to;
            document.getElementById('goScore').textContent  = stats.score.toLocaleString('pt-BR');
            document.getElementById('goKills').textContent  = stats.kills;
            document.getElementById('goTime').textContent   = formatTime(stats.survival_time);
            document.getElementById('goLevel').textContent  = stats.level_reached;
            document.getElementById('goXp').textContent     = '+' + stats.xp_earned;
            document.getElementById('goCoins').textContent  = '+' + stats.coins_earned;
            show(ui.gameover);
            saveMatch(stats, document.getElementById('saveStatus'));
        },
        onVictory(stats) {
            document.getElementById('vicBoss').textContent  = DB.boss ? DB.boss.name : 'o boss';
            document.getElementById('vicScore').textContent = stats.score.toLocaleString('pt-BR');
            document.getElementById('vicKills').textContent = stats.kills;
            document.getElementById('vicTime').textContent  = formatTime(stats.survival_time);
            document.getElementById('vicXp').textContent    = '+' + stats.xp_earned;
            document.getElementById('vicCoins').textContent = '+' + stats.coins_earned;
            show(ui.victory);
            saveMatch(stats, document.getElementById('vicStatus'));
        },
    };

    /* ---------- Salvamento ---------- */
    function saveMatch(stats, statusEl) {
        statusEl.textContent = '> salvando no banco...';
        statusEl.className = 'save-status';

        fetch((DB.baseUrl || '') + '/game/save_match.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': DB.csrfToken || '' },
            body: JSON.stringify(stats),
        })
        .then((res) => res.json())
        .then((data) => {
            if (!data.ok) {
                statusEl.textContent = '> erro ao salvar: ' + (data.error || 'desconhecido');
                statusEl.className = 'save-status save-err';
                return;
            }
            if (data.mode === 'sandbox') {
                statusEl.textContent = '> partida sandbox registrada (nao conta no ranking).';
                statusEl.className = 'save-status save-ok';
                return;
            }
            let msg = '> salvo! XP total: ' + data.xp_total + ' | conta nivel ' + data.level;
            if (data.leveled_up) msg += ' ★ SUBIU DE NIVEL!';
            if (data.medals && data.medals.length) msg += '  🏅 Medalhas: ' + data.medals.join(', ');
            if (data.reward_coins) msg += '  +' + data.reward_coins + '¢ de recompensa de mapa';
            statusEl.textContent = msg;
            statusEl.className = 'save-status save-ok';
        })
        .catch(() => {
            statusEl.textContent = '> falha de conexao ao salvar.';
            statusEl.className = 'save-status save-err';
        });
    }

    /* ---------- Inicio ---------- */
    function startGame() {
        hide(ui.start);
        engine = new Engine(canvas, {
            mode: DB.mode || 'normal',
            map: mapRuntime,
            difficulty: DB.difficulty,
            boss: DB.boss,
            character: character,
            mods: mods,
            sandbox: DB.sandbox || null,
            ids: DB.ids || {},
            accountLevel: (DB.player && DB.player.level) || 1,
            playerName: (DB.player && DB.player.name) || 'dev',
            weaponCfg: starterCfg,
            ownedWeaponCfgs: ownedWeaponCfgs,
            bulletColor: fx.bulletColor || null,
            auraColor: fx.auraColor || null,
            killColor: fx.killColor || null,
            callbacks: callbacks,
        });
        engine.start();
    }

    document.getElementById('btnStart').addEventListener('click', startGame);
    document.getElementById('btnResume').addEventListener('click', () => engine && engine.togglePause());
    document.getElementById('btnRestart').addEventListener('click', () => window.location.reload());
    document.getElementById('btnVicRestart').addEventListener('click', () => window.location.reload());

    /* ---------- Helpers ---------- */
    function formatTime(s) {
        const m = Math.floor(s / 60); const r = s % 60;
        return m > 0 ? m + 'm ' + r + 's' : r + 's';
    }
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }
})();
