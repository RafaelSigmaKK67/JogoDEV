/**
 * DEV SURVIVOR - engine.js
 * Game loop, input, camera, spawns, colisoes, dificuldade, bosses por mapa,
 * ultimates/especiais dos personagens, efeitos de skin/equipamento,
 * conclusao de mapa (vitoria), zona segura (Garbage Collector), HUD e efeitos.
 */

/* ============================================================
 * EFEITOS SONOROS (WebAudio - sem arquivos externos)
 * ============================================================ */
class Sfx {
    constructor() { this.ctx = null; this.muted = false; }

    ensure() {
        if (!this.ctx) {
            try { this.ctx = new (window.AudioContext || window.webkitAudioContext)(); }
            catch (e) { this.muted = true; }
        }
        if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
    }

    beep(freq, dur, type, vol, slideTo) {
        if (this.muted) return;
        this.ensure();
        if (!this.ctx) return;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.type = type || 'square';
        osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
        if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, this.ctx.currentTime + dur);
        gain.gain.setValueAtTime(vol || 0.05, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + dur);
        osc.connect(gain).connect(this.ctx.destination);
        osc.start();
        osc.stop(this.ctx.currentTime + dur);
    }

    shoot()     { this.beep(880, 0.07, 'square', 0.03, 440); }
    laser()     { this.beep(1400, 0.18, 'sawtooth', 0.04, 200); }
    hit()       { this.beep(220, 0.08, 'sawtooth', 0.04); }
    explosion() { this.beep(90, 0.3, 'triangle', 0.08, 40); }
    pickup()    { this.beep(1200, 0.09, 'sine', 0.05, 1800); }
    heal()      { this.beep(600, 0.15, 'sine', 0.05, 1000); }
    hurt()      { this.beep(140, 0.18, 'sawtooth', 0.07, 80); }
    levelup()   { this.beep(523, 0.1, 'square', 0.05); setTimeout(() => this.beep(659, 0.1, 'square', 0.05), 100); setTimeout(() => this.beep(784, 0.18, 'square', 0.05), 200); }
    ultimate()  { this.beep(300, 0.12, 'sawtooth', 0.07, 900); setTimeout(() => this.beep(900, 0.25, 'square', 0.06, 1400), 90); }
    special()   { this.beep(700, 0.1, 'triangle', 0.05, 1200); }
    gameover()  { this.beep(400, 0.5, 'sawtooth', 0.08, 60); }
    victory()   { this.beep(523, 0.12, 'square', 0.06); setTimeout(()=>this.beep(659,0.12,'square',0.06),120); setTimeout(()=>this.beep(784,0.12,'square',0.06),240); setTimeout(()=>this.beep(1046,0.3,'square',0.06),360); }
    boss()      { this.beep(60, 0.6, 'sawtooth', 0.1, 120); }

    toggle() { this.muted = !this.muted; return this.muted; }
}

/* ============================================================
 * ENGINE PRINCIPAL
 * ============================================================ */
class Engine {
    constructor(canvas, opts) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.map = opts.map;
        this.mode = opts.mode || 'normal';      // normal | sandbox
        this.sandbox = opts.sandbox || null;
        this.ids = opts.ids || {};
        this.accountLevel = opts.accountLevel || 1;
        this.callbacks = opts.callbacks || {};
        this.bossRow = opts.boss || null;
        this.diffName = opts.difficulty ? opts.difficulty.name : 'Normal';
        this.diffColor = opts.difficulty ? (opts.difficulty.color || '#39c2ff') : '#39c2ff';

        this.state = 'idle';
        this.elapsed = 0;
        this.lastTime = 0;

        // ---------- Player ----------
        this.player = new Player(this.map.width / 2, this.map.height / 2, {
            name: opts.playerName,
            character: opts.character,
            mods: opts.mods,
            weaponCfg: opts.weaponCfg || WeaponFactory.starter(),
            extraWeaponCfgs: opts.ownedWeaponCfgs || [],
            bulletColor: opts.bulletColor || null,
            auraColor: opts.auraColor || null,
        });
        this.charName = this.player.character.name;
        this.killColor = opts.killColor || null;

        // Sandbox: vida infinita
        if (this.sandbox && this.sandbox.infHp) this.player.infiniteHp = true;
        this.infCoins = !!(this.sandbox && this.sandbox.infCoins);

        // ---------- Entidades ----------
        this.enemies = [];
        this.bullets = [];
        this.enemyBullets = [];
        this.particles = [];
        this.floatTexts = [];
        this.beams = [];
        this.hazards = [];
        Items.reset();

        this.stats = { score: 0, kills: 0, coins: 0, diedTo: '' };
        this.weaponsCollected = new Set();
        this.pendingLevelUps = 0;

        // ---------- Spawn ----------
        this.spawnTimer = 1.5;
        this.scoreTimer = 0;
        this.hazardTimer = 5;
        this.novaTimer = 0;

        // Multiplicador de quantidade (dificuldade x mapa x sandbox)
        this.countMult = this.map.countMult * this.map.enemyMult;
        if (this.sandbox) this.countMult = this.map.countMult * this.sandbox.enemies;
        this.enemiesEnabled = !this.sandbox || this.sandbox.enemies > 0;

        // ---------- Boss ----------
        BossFactory.setRow(this.bossRow);
        this.boss = null;
        this.bossDefeated = false;
        const bossAllowed = this.bossRow && (!this.sandbox || this.sandbox.boss);
        this.bossTime = bossAllowed ? this.map.timeRequired : Infinity;
        this.bossWarnTimer = 0;

        // ---------- Zona segura ----------
        const maxR = Math.hypot(this.map.width, this.map.height) / 2;
        this.zone = { x: this.map.width / 2, y: this.map.height / 2, r: maxR, targetR: maxR, phase: 0, timer: 45 };

        this.cam = { x: 0, y: 0 };
        this.input = { keys: {}, mouse: { x: canvas.width / 2, y: canvas.height / 2, down: false } };
        this.sfx = new Sfx();
        this.boundHandlers = {};
        this.bindInput();
    }

    /* ---------------- INPUT ---------------- */
    bindInput() {
        const h = this.boundHandlers;
        h.keydown = (e) => {
            const k = e.key.toLowerCase();
            this.input.keys[k] = true;
            if (k === 'p' || k === 'escape') this.togglePause();
            if (k === 'm') this.sfx.toggle();
            if (k === 'r') this.tryUltimate();
            if (k === 'q') this.trySpecial();
            if (k >= '1' && k <= '9') this.player.switchWeapon(parseInt(k, 10) - 1);
            if (['w','a','s','d',' ','arrowup','arrowdown','arrowleft','arrowright','q','r'].includes(k)) e.preventDefault();
        };
        h.keyup = (e) => { this.input.keys[e.key.toLowerCase()] = false; };
        h.mousemove = (e) => {
            const rect = this.canvas.getBoundingClientRect();
            this.input.mouse.x = (e.clientX - rect.left) * (this.canvas.width / rect.width);
            this.input.mouse.y = (e.clientY - rect.top) * (this.canvas.height / rect.height);
        };
        h.mousedown = (e) => { if (e.button === 0) { this.input.mouse.down = true; this.sfx.ensure(); } };
        h.mouseup = (e) => { if (e.button === 0) this.input.mouse.down = false; };
        h.wheel = (e) => { e.preventDefault(); this.player.cycleWeapon(e.deltaY > 0 ? 1 : -1); };
        h.contextmenu = (e) => e.preventDefault();

        window.addEventListener('keydown', h.keydown);
        window.addEventListener('keyup', h.keyup);
        this.canvas.addEventListener('mousemove', h.mousemove);
        this.canvas.addEventListener('mousedown', h.mousedown);
        window.addEventListener('mouseup', h.mouseup);
        this.canvas.addEventListener('wheel', h.wheel, { passive: false });
        this.canvas.addEventListener('contextmenu', h.contextmenu);
    }

    destroy() {
        const h = this.boundHandlers;
        window.removeEventListener('keydown', h.keydown);
        window.removeEventListener('keyup', h.keyup);
        this.canvas.removeEventListener('mousemove', h.mousemove);
        this.canvas.removeEventListener('mousedown', h.mousedown);
        window.removeEventListener('mouseup', h.mouseup);
        this.canvas.removeEventListener('wheel', h.wheel);
        this.canvas.removeEventListener('contextmenu', h.contextmenu);
    }

    /* ---------------- CONTROLE DE ESTADO ---------------- */
    start() {
        this.state = 'playing';
        this.lastTime = performance.now();
        requestAnimationFrame((t) => this.loop(t));
    }

    togglePause() {
        if (this.state === 'playing') { this.state = 'paused'; if (this.callbacks.onPause) this.callbacks.onPause(true); }
        else if (this.state === 'paused') { this.state = 'playing'; if (this.callbacks.onPause) this.callbacks.onPause(false); }
    }

    applySkillChoice(skill) {
        Skills.apply(this.player, skill);
        this.pendingLevelUps = Math.max(0, this.pendingLevelUps - 1);
        if (this.pendingLevelUps > 0) this.onLevelUp();
        else this.state = 'playing';
    }

    /* ---------------- GAME LOOP ---------------- */
    loop(now) {
        if (this.state === 'gameover' || this.state === 'victory') return;
        let dt = (now - this.lastTime) / 1000;
        this.lastTime = now;
        dt = Math.min(dt, 0.05);
        if (this.state === 'playing') this.update(dt);
        this.draw();
        requestAnimationFrame((t) => this.loop(t));
    }

    /* ---------------- UPDATE ---------------- */
    update(dt) {
        const player = this.player;
        this.elapsed += dt;

        const wx = this.input.mouse.x + this.cam.x;
        const wy = this.input.mouse.y + this.cam.y;
        player.aimAngle = Math.atan2(wy - player.y, wx - player.x);

        player.update(dt, this.input, this.map);

        if (this.input.mouse.down) player.currentWeapon().fire(player, player.aimAngle, this);

        this.updateZone(dt);
        this.updateHazards(dt);
        this.updateSpawning(dt);

        for (const enemy of this.enemies) enemy.update(dt, this);
        this.updateBullets(dt);

        // Projeteis dos bosses
        for (let i = this.enemyBullets.length - 1; i >= 0; i--) {
            const b = this.enemyBullets[i];
            if (!b.update(dt)) { this.enemyBullets.splice(i, 1); continue; }
            if (GameUtils.dist(b.x, b.y, player.x, player.y) < b.radius + player.radius) {
                this.damagePlayer(b.damage, this.bossRow ? this.bossRow.name : 'Boss');
                this.enemyBullets.splice(i, 1);
            }
        }

        // Stack Overflow (nova periodica)
        if (player.novaLevel > 0) {
            this.novaTimer -= dt;
            if (this.novaTimer <= 0) { this.novaTimer = 5; this.novaPulse(); }
        }

        Items.update(dt, player, this);

        // Pontuacao por tempo
        this.scoreTimer += dt;
        if (this.scoreTimer >= 1) {
            this.scoreTimer -= 1;
            this.stats.score += Math.round(5 * this.map.scoreMult);
        }

        // Particulas / textos / feixes
        for (let i = this.particles.length - 1; i >= 0; i--) {
            const p = this.particles[i];
            p.x += p.vx * dt; p.y += p.vy * dt; p.life -= dt;
            p.vx *= 0.92; p.vy *= 0.92;
            if (p.life <= 0) this.particles.splice(i, 1);
        }
        for (let i = this.floatTexts.length - 1; i >= 0; i--) {
            const t = this.floatTexts[i];
            t.y -= 26 * dt; t.life -= dt;
            if (t.life <= 0) this.floatTexts.splice(i, 1);
        }
        for (let i = this.beams.length - 1; i >= 0; i--) {
            this.beams[i].t -= dt;
            if (this.beams[i].t <= 0) this.beams.splice(i, 1);
        }
        if (this.bossWarnTimer > 0) this.bossWarnTimer -= dt;

        this.cam.x = GameUtils.clamp(player.x - this.canvas.width / 2, 0, Math.max(0, this.map.width - this.canvas.width));
        this.cam.y = GameUtils.clamp(player.y - this.canvas.height / 2, 0, Math.max(0, this.map.height - this.canvas.height));
    }

    /* ---------------- ZONA SEGURA ---------------- */
    updateZone(dt) {
        const z = this.zone;
        z.timer -= dt;
        if (z.timer <= 0 && z.targetR <= z.r) {
            z.phase++;
            z.targetR = Math.max(260, z.r * 0.72);
            z.timer = 45;
            this.addFloatText(this.player.x, this.player.y - 50, '>> GARBAGE COLLECTOR ATIVADO <<', '#ffd24d');
        }
        if (z.r > z.targetR) z.r = Math.max(z.targetR, z.r - 26 * dt);

        if (GameUtils.dist(this.player.x, this.player.y, z.x, z.y) > z.r) {
            this.zoneDamageAcc = (this.zoneDamageAcc || 0) + (2 + z.phase) * dt;
            if (this.zoneDamageAcc >= 1 && !this.player.infiniteHp) {
                const dmg = Math.floor(this.zoneDamageAcc);
                this.zoneDamageAcc -= dmg;
                this.player.hp -= dmg;
                if (this.player.hp <= 0) this.gameOver('Garbage Collector');
            }
        }
    }

    /* ---------------- HAZARDS ---------------- */
    updateHazards(dt) {
        if (!this.map.hasHazards) return;
        this.hazardTimer -= dt;
        if (this.hazardTimer <= 0) {
            this.hazardTimer = 7;
            this.hazards.push({ x: GameUtils.rand(150, this.map.width - 150), y: GameUtils.rand(150, this.map.height - 150), r: 95, t: 0, warn: 1.2, active: 4 });
        }
        for (let i = this.hazards.length - 1; i >= 0; i--) {
            const hz = this.hazards[i];
            hz.t += dt;
            if (hz.t > hz.warn + hz.active) { this.hazards.splice(i, 1); continue; }
            if (hz.t > hz.warn && GameUtils.dist(this.player.x, this.player.y, hz.x, hz.y) < hz.r) {
                this.hazardDamageAcc = (this.hazardDamageAcc || 0) + 12 * dt;
                if (this.hazardDamageAcc >= 1) {
                    const dmg = Math.floor(this.hazardDamageAcc);
                    this.hazardDamageAcc -= dmg;
                    this.damagePlayer(dmg, 'Instabilidade do mapa');
                }
            }
        }
    }

    /* ---------------- SPAWN ---------------- */
    updateSpawning(dt) {
        // Boss
        if (this.bossTime !== Infinity && this.elapsed >= this.bossTime && !this.boss && !this.bossDefeated) {
            this.spawnBoss();
        }
        if (!this.enemiesEnabled) return;

        this.spawnTimer -= dt;
        if (this.spawnTimer > 0) return;

        const pressure = Math.max(0.3, 1 - this.elapsed / 480);
        this.spawnTimer = (1.15 * pressure) / (this.map.spawnRate * Math.max(0.2, this.countMult));

        if (this.enemies.length >= 260) return;

        const cfg = EnemyFactory.pickSpawn(this.elapsed);
        if (!cfg) return;

        const hpScale  = (1 + (this.elapsed / 240) * 0.6) * this.map.hpMult;
        const dmgScale = (1 + (this.elapsed / 600) * 0.5) * this.map.enemyDmgMult;

        let count = 1;
        if (cfg.behavior === 'swarm') count = GameUtils.randInt(6, 10);
        else if (cfg.behavior === 'group') count = GameUtils.randInt(3, 5);
        count = Math.max(1, Math.round(count * Math.min(2, this.countMult)));

        const base = this.randomSpawnPoint();
        for (let i = 0; i < count; i++) {
            const x = GameUtils.clamp(base.x + GameUtils.rand(-50, 50), 20, this.map.width - 20);
            const y = GameUtils.clamp(base.y + GameUtils.rand(-50, 50), 20, this.map.height - 20);
            this.enemies.push(new Enemy(cfg, x, y, hpScale, dmgScale, this.map.speedMult));
        }
    }

    randomSpawnPoint() {
        for (let i = 0; i < 10; i++) {
            const angle = GameUtils.rand(0, Math.PI * 2);
            const dist = GameUtils.rand(760, 980);
            const x = this.player.x + Math.cos(angle) * dist;
            const y = this.player.y + Math.sin(angle) * dist;
            if (x > 20 && x < this.map.width - 20 && y > 20 && y < this.map.height - 20) return { x, y };
        }
        return { x: GameUtils.rand(50, this.map.width - 50), y: GameUtils.rand(50, this.map.height - 50) };
    }

    spawnBoss() {
        const p = this.randomSpawnPoint();
        const hpScale = this.map.hpMult;
        const dmgScale = this.map.enemyDmgMult;
        this.boss = BossFactory.build(p.x, p.y, hpScale, dmgScale, this.map.speedMult);
        if (!this.boss) { this.bossTime = Infinity; return; }
        this.enemies.push(this.boss);
        this.bossWarnTimer = 3;
        this.sfx.boss();
        this.addFloatText(this.player.x, this.player.y - 60, '!!! ' + (this.bossRow.name || 'BOSS').toUpperCase() + ' !!!', '#ff0044');
    }

    /* ---------------- ATAQUES ESPECIAIS DO BOSS ---------------- */
    bossSpecial(boss) {
        switch (boss.special) {
            case 'summon': {
                const minionCfg = EnemyFactory.bySlug('virus-comum') || EnemyFactory.configs.find(c => !c.isBoss);
                if (minionCfg) {
                    for (let i = 0; i < (boss.enraged ? 6 : 4); i++) {
                        const a = GameUtils.rand(0, Math.PI * 2);
                        this.enemies.push(new Enemy(minionCfg, boss.x + Math.cos(a) * 70, boss.y + Math.sin(a) * 70, 1.2 * this.map.hpMult, this.map.enemyDmgMult, this.map.speedMult));
                    }
                    this.addFloatText(boss.x, boss.y - boss.size - 20, 'fork() fork()', boss.color);
                }
                break;
            }
            case 'charge': {
                const ang = Math.atan2(this.player.y - boss.y, this.player.x - boss.x);
                boss.chargeVx = Math.cos(ang) * boss.speed * 5;
                boss.chargeVy = Math.sin(ang) * boss.speed * 5;
                boss.chargeTimer = 0.8;
                this.addFloatText(boss.x, boss.y - boss.size - 16, '>> INVESTIDA <<', boss.color);
                this.sfx.boss();
                break;
            }
            case 'spread': {
                const baseAng = Math.atan2(this.player.y - boss.y, this.player.x - boss.x);
                const n = boss.enraged ? 9 : 6;
                for (let i = 0; i < n; i++) {
                    const a = baseAng + (i - (n - 1) / 2) * 0.18;
                    this.enemyBullets.push(new EnemyBullet(boss.x, boss.y, a, 240, Math.round(boss.damage * 0.5)));
                }
                this.sfx.explosion();
                break;
            }
            case 'laser': {
                // Feixe telegrafado na direcao do jogador
                const ang = Math.atan2(this.player.y - boss.y, this.player.x - boss.x);
                const x2 = boss.x + Math.cos(ang) * 1200, y2 = boss.y + Math.sin(ang) * 1200;
                this.beams.push({ x1: boss.x, y1: boss.y, x2, y2, color: boss.color, t: 0.3, tMax: 0.3 });
                if (GameUtils.distToSegment(this.player.x, this.player.y, boss.x, boss.y, x2, y2) < this.player.radius + 10) {
                    this.damagePlayer(Math.round(boss.damage * 1.2), boss.name);
                }
                this.sfx.laser();
                break;
            }
            case 'nova': {
                // Anel expansivo + rajada radial densa
                const shots = boss.enraged ? 24 : 16;
                for (let i = 0; i < shots; i++) {
                    this.enemyBullets.push(new EnemyBullet(boss.x, boss.y, (Math.PI * 2 / shots) * i, 210, Math.round(boss.damage * 0.45)));
                }
                this.beams.push({ nova: true, x: boss.x, y: boss.y, r: 160, t: 0.35, tMax: 0.35 });
                this.sfx.explosion();
                break;
            }
            default: { // radial
                const shots = boss.enraged ? 18 : 12;
                for (let i = 0; i < shots; i++) {
                    this.enemyBullets.push(new EnemyBullet(boss.x, boss.y, (Math.PI * 2 / shots) * i, 220, Math.round(boss.damage * 0.5)));
                }
                this.sfx.explosion();
            }
        }
    }

    /* ---------------- PROJETEIS DO JOGADOR ---------------- */
    updateBullets(dt) {
        for (let i = this.bullets.length - 1; i >= 0; i--) {
            const b = this.bullets[i];
            if (!b.update(dt)) {
                if (b.special === 'area') this.explode(b.x, b.y, b.damage);
                this.bullets.splice(i, 1);
                continue;
            }
            let removed = false;
            for (const ob of this.map.obstacles) {
                if (GameUtils.circleRect(b.x, b.y, b.radius, ob)) {
                    if (b.special === 'area') this.explode(b.x, b.y, b.damage);
                    else this.spawnParticles(b.x, b.y, b.color, 4, 60);
                    this.bullets.splice(i, 1);
                    removed = true;
                    break;
                }
            }
            if (removed) continue;

            for (const enemy of this.enemies) {
                if (GameUtils.dist(b.x, b.y, enemy.x, enemy.y) < b.radius + enemy.size) {
                    if (b.special === 'area') {
                        this.explode(b.x, b.y, b.damage);
                        this.bullets.splice(i, 1);
                    } else {
                        this.hitEnemy(enemy, b.damage, true);
                        if (b.pierce > 0) { b.pierce--; } // atravessa (skin Laser Quantico)
                        else { this.bullets.splice(i, 1); }
                    }
                    break;
                }
            }
        }
    }

    /** Verifica se o inimigo e do tipo "bot/malware" (para bonus de skin/passiva). */
    isBotOrMalware(enemy) {
        const s = enemy.cfg ? (enemy.cfg.slug || '') : '';
        return s.indexOf('bot') >= 0 || s.indexOf('malware') >= 0 || enemy.behavior === 'group';
    }

    hitEnemy(enemy, damage, fromWeapon) {
        const player = this.player;
        let dmg = damage;

        // Bonus de dano vs bots/malware (skin Hacker / passiva)
        if ((player.antiBot > 0 || player.antiMalware > 0) && this.isBotOrMalware(enemy)) {
            dmg *= (1 + player.antiBot + player.antiMalware);
        }

        // Critico (skill Exploit Critico)
        const crit = Math.random() < player.critChance;
        const final = Math.round(crit ? dmg * 2 : dmg);

        // Efeitos de skin de arma ao atingir
        if (fromWeapon) {
            if (player.slowChance > 0 && Math.random() < player.slowChance) enemy.slowedTimer = 2.5;
            if (player.burnChance > 0 && Math.random() < player.burnChance) { enemy.burnTimer = 3; enemy.burnDamage = Math.max(4, final * 0.4); }
        }

        this.addFloatText(enemy.x, enemy.y - enemy.size - 4, String(final) + (crit ? '!' : ''), crit ? '#ffd24d' : '#ffffff');
        this.sfx.hit();
        player.addUltCharge(0.6); // carrega ultimate ao causar dano

        if (enemy.takeDamage(final)) this.killEnemy(enemy);
    }

    killEnemy(enemy) {
        const idx = this.enemies.indexOf(enemy);
        if (idx === -1) return;
        this.enemies.splice(idx, 1);

        this.stats.kills++;
        this.stats.score += Math.round(enemy.scoreReward * this.map.scoreMult);
        this.spawnParticles(enemy.x, enemy.y, this.killColor || enemy.color, enemy.isBoss ? 40 : 10, enemy.isBoss ? 240 : 130);
        this.player.addUltCharge(enemy.isBoss ? 60 : 5);

        // Drops (chance de raro escala com a dificuldade)
        Items.maybeDrop(enemy, enemy.x, enemy.y, WeaponFactory.dropPool(this.accountLevel), this.map.rareDrop);

        // XP (com multiplicador de dificuldade)
        const levels = this.player.gainXP(Math.round(enemy.xpReward * this.map.xpMult));
        if (levels > 0) { this.pendingLevelUps += levels; this.onLevelUp(); }

        // Moedas
        this.addCoins(Math.round(enemy.coinReward * this.map.coinMult));

        if (enemy.isBoss) {
            this.boss = null;
            this.bossDefeated = true;
            this.sfx.explosion();
            this.addFloatText(enemy.x, enemy.y, (this.bossRow.name || 'BOSS') + ' DERROTADO!', '#4df3a3');
            // Vitoria (modo normal) ou continua (sandbox)
            if (this.mode === 'normal') {
                this.victory();
            } else {
                // Sandbox: boss pode reaparecer apos um tempo
                this.bossDefeated = false;
                this.bossTime = this.elapsed + 60;
            }
        }
    }

    explode(x, y, damage) {
        const radius = 85;
        this.spawnParticles(x, y, '#ff9f1c', 22, 200);
        this.sfx.explosion();
        for (const enemy of this.enemies.slice()) {
            if (GameUtils.dist(x, y, enemy.x, enemy.y) < radius + enemy.size) this.hitEnemy(enemy, damage * 0.85, true);
        }
    }

    novaPulse() {
        const player = this.player;
        const radius = 120 + 35 * player.novaLevel;
        const damage = (12 + 10 * player.novaLevel) * player.getDamageMult();
        this.spawnParticles(player.x, player.y, '#9b4dff', 26, 260);
        this.sfx.explosion();
        for (const enemy of this.enemies.slice()) {
            if (GameUtils.dist(player.x, player.y, enemy.x, enemy.y) < radius + enemy.size) this.hitEnemy(enemy, damage, false);
        }
        this.beams.push({ nova: true, x: player.x, y: player.y, r: radius, t: 0.25, tMax: 0.25 });
    }

    fireLaser(player, angle, weapon) {
        const cfg = weapon.cfg;
        const x2 = player.x + Math.cos(angle) * cfg.bulletRange;
        const y2 = player.y + Math.sin(angle) * cfg.bulletRange;
        this.beams.push({ x1: player.x, y1: player.y, x2, y2, color: player.bulletColor || cfg.color, t: 0.12, tMax: 0.12 });
        const damage = cfg.damage * player.getDamageMult();
        for (const enemy of this.enemies.slice()) {
            if (GameUtils.distToSegment(enemy.x, enemy.y, player.x, player.y, x2, y2) < enemy.size + 4) this.hitEnemy(enemy, damage, true);
        }
    }

    /* ---------------- ULTIMATE / ESPECIAL ---------------- */
    tryUltimate() {
        if (this.state !== 'playing' || !this.player.ultReady) return;
        this.activateUltimate(this.player.ultimateKey);
        this.player.ultCharge = 0;
        this.player.ultCooldown = 2;
        this.sfx.ultimate();
    }

    trySpecial() {
        if (this.state !== 'playing' || !this.player.specialReady) return;
        this.activateSpecial(this.player.specialKey);
        this.player.specialCooldown = this.player.specialCooldownMax;
        this.sfx.special();
    }

    activateUltimate(key) {
        const p = this.player;
        this.addFloatText(p.x, p.y - 40, 'ULTIMATE: ' + p.character.ultimateName, '#ffd700');
        this.spawnParticles(p.x, p.y, '#ffd700', 40, 300);

        switch (key) {
            case 'rain': { // Chuva de Componentes
                for (let i = 0; i < 28; i++) {
                    const a = (Math.PI * 2 / 28) * i;
                    this.bullets.push(new Bullet(p.x, p.y, a, 480, 26 * p.getDamageMult(), 520, p.bulletColor || '#39c2ff', null, 1));
                }
                break;
            }
            case 'bigheal': { p.heal(p.maxHp); p.addShield(150); break; } // Deploy Seguro
            case 'buffnova': { p.tempDamageMult = 1.7; p.buffTimer = 10; this.bigNova(220, 60); break; } // Sistema Integrado
            case 'nukeall': { this.damageAll(80, true); break; } // Exploit Reverso (extra vs bots)
            case 'invuln': { p.invulnTimer = 4.5; this.addFloatText(p.x, p.y - 24, 'INVULNERAVEL', '#4d7dff'); break; } // Protecao Absoluta
            case 'slowxp': { this.enemies.forEach(e => { if (!e.isBoss) e.slowedTimer = 5; }); const lv = p.gainXP(300); if (lv) { this.pendingLevelUps += lv; this.onLevelUp(); } break; } // Modelo Preditivo
            case 'rapidfire': { p.fireRateMult = 3; p.rapidTimer = 9; p.specialCooldown = 0; break; } // Pipeline Supremo
            case 'fortify': { p.heal(p.maxHp); p.addShield(160); p.tempDamageMult = 1.3; p.buffTimer = 8; break; } // Arquitetura Imbativel
            case 'clearcoins': { // Stack Overflow Divino
                this.enemies.slice().forEach(e => { if (!e.isBoss && e.hp <= 60) this.hitEnemy(e, 9999, false); });
                this.addCoins(150); this.addFloatText(p.x, p.y - 24, '+150 moedas!', '#ffd24d');
                break;
            }
            case 'wipe': { // Reset do Sistema (limpa a tela)
                this.bigNova(360, 30);
                this.enemies.slice().forEach(e => { if (!e.isBoss) this.hitEnemy(e, 9999, false); else this.hitEnemy(e, e.maxHp * 0.25, false); });
                this.addFloatText(p.x, p.y - 24, 'SYSTEM RESET!', '#ff0044');
                break;
            }
            default: { this.bigNova(200, 40); }
        }
    }

    activateSpecial(key) {
        const p = this.player;
        switch (key) {
            case 'dash': { // avanco rapido
                const dist = 170;
                const nx = GameUtils.clamp(p.x + Math.cos(p.aimAngle) * dist, p.radius, this.map.width - p.radius);
                const ny = GameUtils.clamp(p.y + Math.sin(p.aimAngle) * dist, p.radius, this.map.height - p.radius);
                if (!this.map.obstacles.some(ob => GameUtils.circleRect(nx, ny, p.radius, ob))) { p.x = nx; p.y = ny; }
                p.invulnTimer = 0.4;
                this.spawnParticles(p.x, p.y, p.color, 16, 160);
                break;
            }
            case 'shield': { p.addShield(60); break; }
            case 'rapid':  { p.fireRateMult = 2.5; p.rapidTimer = 5; break; }
            case 'slowtime': { this.enemies.forEach(e => { if (!e.isBoss) e.slowedTimer = 3; }); this.addFloatText(p.x, p.y - 24, 'TEMPO LENTO', '#c64dff'); break; }
            case 'heal':   { p.heal(40); break; }
            default:       { p.addShield(40); }
        }
        this.spawnParticles(p.x, p.y, p.color, 12, 120);
    }

    /** Nova grande de dano em area ao redor do jogador. */
    bigNova(radius, damage) {
        const p = this.player;
        this.beams.push({ nova: true, x: p.x, y: p.y, r: radius, t: 0.4, tMax: 0.4 });
        for (const enemy of this.enemies.slice()) {
            if (GameUtils.dist(p.x, p.y, enemy.x, enemy.y) < radius + enemy.size) this.hitEnemy(enemy, damage * p.getDamageMult(), false);
        }
    }

    /** Dano a todos os inimigos na tela (ultimate). */
    damageAll(damage, antiBotBonus) {
        const p = this.player;
        for (const enemy of this.enemies.slice()) {
            let d = damage * p.getDamageMult();
            if (antiBotBonus && this.isBotOrMalware(enemy)) d *= 2;
            if (enemy.isBoss) d *= 0.5; // boss recebe menos
            this.hitEnemy(enemy, d, false);
        }
    }

    /* ---------------- DANO AO JOGADOR ---------------- */
    damagePlayer(amount, sourceName) {
        const died = this.player.takeDamage(amount);
        if (this.player.lastDodged) {
            this.addFloatText(this.player.x, this.player.y - 24, 'DESVIOU!', '#b0c4de');
            return;
        }
        if (this.player.invulnTimer >= 0.34) {
            this.sfx.hurt();
            this.spawnParticles(this.player.x, this.player.y, '#ff4d5e', 8, 120);
            this.player.addUltCharge(4); // carrega ultimate ao receber dano
        }
        if (died) this.gameOver(sourceName);
    }

    onLevelUp() {
        const choices = Skills.pickChoices(3, this.player);
        if (!choices.length) { this.pendingLevelUps = 0; this.state = 'playing'; return; }
        this.state = 'levelup';
        this.sfx.levelup();
        if (this.callbacks.onLevelUp) this.callbacks.onLevelUp(choices);
    }

    /* ---------------- FIM DE PARTIDA ---------------- */
    buildStats(completed) {
        return {
            mode: this.mode,
            map_id: this.map.id,
            difficulty_id: this.ids.difficultyId || null,
            character_id: this.ids.characterId || null,
            score: Math.round(this.stats.score),
            kills: this.stats.kills,
            survival_time: Math.floor(this.elapsed),
            xp_earned: this.player.totalXpGained,
            coins_earned: this.stats.coins,
            level_reached: this.player.level,
            completed: completed,
            died_to: this.stats.diedTo,
            weapons_collected: Array.from(this.weaponsCollected),
            sandbox_settings: this.sandbox ? JSON.stringify(this.sandbox) : null,
        };
    }

    gameOver(killerName) {
        if (this.state === 'gameover' || this.state === 'victory') return;
        this.state = 'gameover';
        this.stats.diedTo = killerName || 'Anomalia desconhecida';
        this.sfx.gameover();
        if (this.callbacks.onGameOver) this.callbacks.onGameOver(this.buildStats(false));
    }

    victory() {
        if (this.state === 'gameover' || this.state === 'victory') return;
        this.state = 'victory';
        this.sfx.victory();
        if (this.callbacks.onVictory) this.callbacks.onVictory(this.buildStats(true));
    }

    /* ---------------- HELPERS ---------------- */
    addCoins(amount) {
        this.stats.coins += Math.round(amount * (this.player.coinBoost || 1));
    }

    giveWeapon(slug) {
        const cfg = WeaponFactory.bySlug(slug);
        if (!cfg) return false;
        const added = this.player.addWeapon(cfg);
        if (added) this.weaponsCollected.add(slug);
        return added;
    }

    spawnParticles(x, y, color, count, speed) {
        for (let i = 0; i < count; i++) {
            const angle = GameUtils.rand(0, Math.PI * 2);
            const v = GameUtils.rand(speed * 0.3, speed);
            this.particles.push({ x, y, vx: Math.cos(angle) * v, vy: Math.sin(angle) * v, life: GameUtils.rand(0.25, 0.6), color, size: GameUtils.rand(1.5, 3.5) });
        }
    }

    addFloatText(x, y, txt, color) { this.floatTexts.push({ x, y, txt, color, life: 1.1 }); }

    /* ============================================================
     * RENDER
     * ============================================================ */
    draw() {
        const ctx = this.ctx;
        const W = this.canvas.width, H = this.canvas.height;
        ctx.fillStyle = this.map.bgColor;
        ctx.fillRect(0, 0, W, H);

        ctx.save();
        ctx.translate(-this.cam.x, -this.cam.y);
        this.drawGrid(ctx);
        this.drawZone(ctx);
        this.drawHazards(ctx);
        for (const ob of this.map.obstacles) GameMaps.drawObstacle(ctx, ob, this.map.accentColor);
        Items.draw(ctx);
        for (const enemy of this.enemies) enemy.draw(ctx);
        for (const b of this.bullets) b.draw(ctx);
        for (const b of this.enemyBullets) b.draw(ctx);
        this.drawBeams(ctx);
        this.player.draw(ctx);
        this.drawParticles(ctx);
        this.drawFloatTexts(ctx);
        ctx.restore();

        this.drawHud(ctx, W, H);
    }

    drawGrid(ctx) {
        const grid = 100;
        ctx.strokeStyle = 'rgba(255,255,255,0.04)';
        ctx.lineWidth = 1;
        const x0 = Math.floor(this.cam.x / grid) * grid;
        const y0 = Math.floor(this.cam.y / grid) * grid;
        ctx.beginPath();
        for (let x = x0; x <= this.cam.x + this.canvas.width; x += grid) { ctx.moveTo(x, this.cam.y); ctx.lineTo(x, this.cam.y + this.canvas.height); }
        for (let y = y0; y <= this.cam.y + this.canvas.height; y += grid) { ctx.moveTo(this.cam.x, y); ctx.lineTo(this.cam.x + this.canvas.width, y); }
        ctx.stroke();
        ctx.strokeStyle = this.map.accentColor;
        ctx.lineWidth = 4;
        ctx.strokeRect(0, 0, this.map.width, this.map.height);
    }

    drawZone(ctx) {
        const z = this.zone;
        ctx.save();
        ctx.beginPath();
        ctx.rect(this.cam.x - 50, this.cam.y - 50, this.canvas.width + 100, this.canvas.height + 100);
        ctx.arc(z.x, z.y, z.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255, 60, 40, 0.13)';
        ctx.fill('evenodd');
        ctx.restore();
        ctx.strokeStyle = 'rgba(255, 120, 60, 0.8)';
        ctx.lineWidth = 3;
        ctx.setLineDash([14, 10]);
        ctx.beginPath();
        ctx.arc(z.x, z.y, z.r, 0, Math.PI * 2);
        ctx.stroke();
        ctx.setLineDash([]);
    }

    drawHazards(ctx) {
        for (const hz of this.hazards) {
            const active = hz.t > hz.warn;
            ctx.save();
            ctx.beginPath();
            ctx.arc(hz.x, hz.y, hz.r, 0, Math.PI * 2);
            if (active) { ctx.fillStyle = 'rgba(255, 210, 77, 0.22)'; ctx.fill(); ctx.strokeStyle = '#ffd24d'; }
            else { ctx.strokeStyle = 'rgba(255, 210, 77, 0.5)'; if (Math.floor(hz.t * 8) % 2 === 0) ctx.setLineDash([8, 8]); }
            ctx.lineWidth = 2;
            ctx.stroke();
            ctx.restore();
        }
    }

    drawBeams(ctx) {
        for (const beam of this.beams) {
            const alpha = beam.t / beam.tMax;
            ctx.save();
            ctx.globalAlpha = Math.max(0, alpha);
            if (beam.nova) {
                ctx.strokeStyle = '#ffd700';
                ctx.lineWidth = 4;
                ctx.beginPath();
                ctx.arc(beam.x, beam.y, beam.r * (1 - alpha * 0.4), 0, Math.PI * 2);
                ctx.stroke();
            } else {
                ctx.strokeStyle = beam.color;
                ctx.lineWidth = 5;
                ctx.shadowColor = beam.color;
                ctx.shadowBlur = 14;
                ctx.beginPath();
                ctx.moveTo(beam.x1, beam.y1);
                ctx.lineTo(beam.x2, beam.y2);
                ctx.stroke();
            }
            ctx.restore();
        }
    }

    drawParticles(ctx) {
        for (const p of this.particles) {
            ctx.globalAlpha = Math.max(0, p.life * 2);
            ctx.fillStyle = p.color;
            ctx.fillRect(p.x - p.size / 2, p.y - p.size / 2, p.size, p.size);
        }
        ctx.globalAlpha = 1;
    }

    drawFloatTexts(ctx) {
        ctx.font = 'bold 13px monospace';
        ctx.textAlign = 'center';
        for (const t of this.floatTexts) {
            ctx.globalAlpha = Math.max(0, t.life);
            ctx.fillStyle = t.color;
            ctx.fillText(t.txt, t.x, t.y);
        }
        ctx.globalAlpha = 1;
        ctx.textAlign = 'left';
    }

    /* ---------------- HUD ---------------- */
    drawHud(ctx, W, H) {
        const player = this.player;
        ctx.save();
        ctx.font = '13px monospace';

        // ----- Vida -----
        const barW = 230, barH = 17;
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(18, 16, barW, barH);
        const hpRatio = Math.max(0, player.hp / player.maxHp);
        ctx.fillStyle = hpRatio > 0.5 ? '#4df3a3' : (hpRatio > 0.25 ? '#ffd24d' : '#ff4d5e');
        ctx.fillRect(18, 16, barW * hpRatio, barH);
        ctx.strokeStyle = 'rgba(255,255,255,0.25)';
        ctx.strokeRect(18, 16, barW, barH);
        ctx.fillStyle = '#061008';
        ctx.font = 'bold 11px monospace';
        ctx.fillText('HP ' + (player.infiniteHp ? '∞' : Math.ceil(Math.max(0, player.hp)) + '/' + player.maxHp), 24, 28);

        if (player.shield > 0) {
            const cap = Math.max(player.shieldMax, 80);
            ctx.fillStyle = '#4d7dff';
            ctx.fillRect(18, 35, barW * Math.min(1, player.shield / cap), 5);
        }

        // ----- Personagem / mapa / dificuldade (abaixo da vida) -----
        ctx.font = '11px monospace';
        ctx.fillStyle = player.color;
        ctx.fillText('👤 ' + this.charName, 18, 56);
        ctx.fillStyle = '#9fb3c8';
        ctx.fillText('🗺 ' + this.map.name, 18, 70);
        ctx.fillStyle = this.diffColor;
        ctx.fillText('⚙ ' + this.diffName + (this.mode === 'sandbox' ? '  [SANDBOX]' : ''), 18, 84);

        // ----- Tempo (topo centro) + objetivo -----
        const mins = Math.floor(this.elapsed / 60), secs = Math.floor(this.elapsed % 60);
        ctx.font = 'bold 20px monospace';
        ctx.fillStyle = '#e8f0ff';
        ctx.textAlign = 'center';
        ctx.fillText((mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs, W / 2, 32);
        if (this.bossTime !== Infinity && !this.bossDefeated && this.elapsed < this.bossTime) {
            ctx.font = '11px monospace';
            ctx.fillStyle = '#ffd24d';
            ctx.fillText('Boss em ' + Math.ceil(this.bossTime - this.elapsed) + 's', W / 2, 48);
        }
        ctx.textAlign = 'left';

        // ----- Pontuacao / kills / moedas / nivel conta (direita) -----
        ctx.textAlign = 'right';
        ctx.font = 'bold 17px monospace';
        ctx.fillStyle = '#4df3a3';
        ctx.fillText(String(Math.round(this.stats.score)).padStart(7, '0') + ' pts', W - 18, 28);
        ctx.font = '13px monospace';
        ctx.fillStyle = '#ff4d5e';
        ctx.fillText('kills: ' + this.stats.kills, W - 18, 48);
        ctx.fillStyle = '#ffd24d';
        ctx.fillText('moedas: ' + (this.infCoins ? '∞' : this.stats.coins), W - 18, 66);
        ctx.fillStyle = '#9fb3c8';
        ctx.fillText('conta nv ' + this.accountLevel, W - 18, 84);
        ctx.textAlign = 'left';

        // ----- Barra de XP (rodape) -----
        const xpY = H - 24;
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(18, xpY, W - 36, 10);
        ctx.fillStyle = '#39c2ff';
        ctx.fillRect(18, xpY, (W - 36) * Math.min(1, player.xp / player.xpToNext), 10);
        ctx.strokeStyle = 'rgba(255,255,255,0.25)';
        ctx.strokeRect(18, xpY, W - 36, 10);
        ctx.fillStyle = '#9fb3c8';
        ctx.font = '11px monospace';
        ctx.fillText('LVL ' + player.level + ' (partida)  —  ' + player.xp + '/' + player.xpToNext + ' XP', 18, xpY - 5);

        // ----- Ultimate + Especial (rodape direita) -----
        this.drawAbilityBars(ctx, W, H);

        // ----- Armas (slots) -----
        const slotY = H - 110;
        for (let i = 0; i < player.weapons.length; i++) {
            const weapon = player.weapons[i];
            const x = 18 + i * 40;
            const active = i === player.weaponIndex;
            ctx.fillStyle = active ? 'rgba(77,243,163,0.18)' : 'rgba(0,0,0,0.5)';
            ctx.fillRect(x, slotY, 34, 34);
            ctx.strokeStyle = active ? '#4df3a3' : 'rgba(255,255,255,0.2)';
            ctx.lineWidth = active ? 2 : 1;
            ctx.strokeRect(x, slotY, 34, 34);
            ctx.fillStyle = weapon.color;
            ctx.fillRect(x + 11, slotY + 8, 12, 12);
            ctx.fillStyle = '#9fb3c8';
            ctx.font = '10px monospace';
            ctx.fillText(String(i + 1), x + 3, slotY + 31);
        }
        ctx.fillStyle = '#e8f0ff';
        ctx.font = 'bold 13px monospace';
        ctx.fillText(player.currentWeapon().name, 18, slotY - 8);

        // ----- Barra de vida do boss -----
        if (this.boss) {
            const bw = 420, bx = (W - bw) / 2;
            ctx.fillStyle = 'rgba(0,0,0,0.6)';
            ctx.fillRect(bx, 60, bw, 14);
            ctx.fillStyle = this.boss.color;
            ctx.fillRect(bx, 60, bw * Math.max(0, this.boss.hp / this.boss.maxHp), 14);
            ctx.strokeStyle = this.boss.color;
            ctx.strokeRect(bx, 60, bw, 14);
            ctx.fillStyle = '#ffe';
            ctx.font = 'bold 11px monospace';
            ctx.textAlign = 'center';
            ctx.fillText((this.bossRow.name || 'BOSS').toUpperCase(), W / 2, 71);
            ctx.textAlign = 'left';
        }

        if (this.bossWarnTimer > 0 && Math.floor(this.bossWarnTimer * 4) % 2 === 0) {
            ctx.fillStyle = '#ff0044';
            ctx.font = 'bold 24px monospace';
            ctx.textAlign = 'center';
            ctx.fillText('!! ' + (this.bossRow.name || 'BOSS').toUpperCase() + ' !!', W / 2, H / 2 - 120);
            ctx.textAlign = 'left';
        }

        const z = this.zone;
        if (GameUtils.dist(player.x, player.y, z.x, z.y) > z.r) {
            ctx.fillStyle = '#ffd24d';
            ctx.font = 'bold 17px monospace';
            ctx.textAlign = 'center';
            ctx.fillText('⚠ FORA DA ZONA SEGURA — O GC VAI TE COLETAR ⚠', W / 2, 100);
            ctx.textAlign = 'left';
        }

        this.drawMinimap(ctx, W);
        if (this.sfx.muted) { ctx.fillStyle = '#9fb3c8'; ctx.font = '11px monospace'; ctx.fillText('[som off - M]', 18, H - 130); }
        ctx.restore();
    }

    drawAbilityBars(ctx, W, H) {
        const p = this.player;
        const bw = 150, bx = W - bw - 18;

        // Ultimate (R)
        const ultY = H - 44;
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(bx, ultY, bw, 12);
        ctx.fillStyle = p.ultReady ? '#ffd700' : '#b8860b';
        ctx.fillRect(bx, ultY, bw * (p.ultCharge / 100), 12);
        ctx.strokeStyle = p.ultReady ? '#ffd700' : 'rgba(255,255,255,0.2)';
        ctx.strokeRect(bx, ultY, bw, 12);
        ctx.fillStyle = p.ultReady ? '#1a1400' : '#9fb3c8';
        ctx.font = 'bold 10px monospace';
        ctx.fillText(p.ultReady ? '[R] ULTIMATE PRONTA!' : '[R] Ultimate ' + Math.floor(p.ultCharge) + '%', bx + 4, ultY + 10);

        // Especial (Q)
        const spY = H - 60;
        const spReady = p.specialReady;
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(bx, spY, bw, 12);
        ctx.fillStyle = spReady ? '#39c2ff' : '#2a5a78';
        const spRatio = spReady ? 1 : 1 - (p.specialCooldown / p.specialCooldownMax);
        ctx.fillRect(bx, spY, bw * spRatio, 12);
        ctx.strokeStyle = spReady ? '#39c2ff' : 'rgba(255,255,255,0.2)';
        ctx.strokeRect(bx, spY, bw, 12);
        ctx.fillStyle = spReady ? '#011018' : '#9fb3c8';
        ctx.font = 'bold 10px monospace';
        ctx.fillText(spReady ? '[Q] ' + p.character.specialName : '[Q] ' + Math.ceil(p.specialCooldown) + 's', bx + 4, spY + 10);
    }

    drawMinimap(ctx, W) {
        const size = 140;
        const mx = W - size - 16, my = 96;
        const scale = size / Math.max(this.map.width, this.map.height);
        ctx.save();
        ctx.globalAlpha = 0.85;
        ctx.fillStyle = 'rgba(0,0,0,0.6)';
        ctx.fillRect(mx, my, size, size);
        ctx.strokeStyle = this.map.accentColor;
        ctx.lineWidth = 1;
        ctx.strokeRect(mx, my, size, size);
        ctx.strokeStyle = 'rgba(255,120,60,0.9)';
        ctx.beginPath();
        ctx.arc(mx + this.zone.x * scale, my + this.zone.y * scale, this.zone.r * scale, 0, Math.PI * 2);
        ctx.stroke();
        ctx.fillStyle = 'rgba(255,77,94,0.8)';
        for (const enemy of this.enemies) ctx.fillRect(mx + enemy.x * scale - 1, my + enemy.y * scale - 1, 2, 2);
        if (this.boss) { ctx.fillStyle = this.boss.color; ctx.beginPath(); ctx.arc(mx + this.boss.x * scale, my + this.boss.y * scale, 4, 0, Math.PI * 2); ctx.fill(); }
        ctx.fillStyle = '#4df3a3';
        ctx.beginPath();
        ctx.arc(mx + this.player.x * scale, my + this.player.y * scale, 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }
}
