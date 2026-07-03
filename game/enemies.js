/**
 * DEV SURVIVOR - enemies.js
 * Anomalias digitais e seus comportamentos.
 *
 * Comportamentos (coluna `behavior` no banco):
 *   chase       -> persegue o jogador em linha reta
 *   slow_heavy  -> persegue devagar, golpe pesado (Trojan)
 *   swarm       -> spawna em enxames, movimento erratico (Bug Critico)
 *   group       -> spawna em grupos que flanqueiam (Bot Corrompido)
 *   tank        -> lento e com muita vida (Firewall Quebrado)
 *   slow_player -> ao acertar, reduz a velocidade do jogador (Ransomware)
 *   boss        -> Kernel Corrompido: rajadas radiais + invoca lacaios
 */

class EnemyBullet {
    constructor(x, y, angle, speed, damage) {
        this.x = x;
        this.y = y;
        this.vx = Math.cos(angle) * speed;
        this.vy = Math.sin(angle) * speed;
        this.damage = damage;
        this.life = 3.5;
        this.radius = 6;
    }

    update(dt) {
        this.x += this.vx * dt;
        this.y += this.vy * dt;
        this.life -= dt;
        return this.life > 0;
    }

    draw(ctx) {
        ctx.save();
        ctx.shadowColor = '#ff0044';
        ctx.shadowBlur = 10;
        ctx.fillStyle = '#ff0044';
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }
}

class Enemy {
    /**
     * cfg: config normalizada do EnemyFactory
     * hpScale/dmgScale: escala de dificuldade conforme o tempo de partida
     */
    constructor(cfg, x, y, hpScale, dmgScale, speedMult) {
        this.cfg = cfg;
        this.x = x;
        this.y = y;
        this.maxHp = Math.round(cfg.health * hpScale);
        this.hp = this.maxHp;
        this.speed = cfg.speed * speedMult;
        this.damage = Math.round(cfg.damage * dmgScale);
        this.size = cfg.size;
        this.color = cfg.color;
        this.behavior = cfg.behavior;
        this.isBoss = cfg.isBoss;
        this.name = cfg.name;
        this.xpReward = cfg.xpReward;
        this.coinReward = cfg.coinReward;
        this.scoreReward = cfg.scoreReward;

        this.attackTimer = 0;
        this.hitFlash = 0;
        this.wobble = Math.random() * Math.PI * 2;
        this.burnTimer = 0;        // dano continuo (skin flamejante)
        this.burnDamage = 0;
        this.slowedTimer = 0;      // lentidao aplicada pela skin congelante

        // group: angulo de flanqueamento fixo por inimigo
        this.flankAngle = GameUtils.rand(0, Math.PI * 2);

        // boss: tipo de ataque especial e temporizadores
        this.special = cfg.special || null; // radial|summon|charge|spread|laser|nova
        this.specialTimer = 4;
        this.chargeTimer = 0;      // usado pelo ataque 'charge'
        this.chargeVx = 0;
        this.chargeVy = 0;
        this.enraged = false;
    }

    update(dt, engine) {
        const player = engine.player;
        this.attackTimer -= dt;
        this.hitFlash -= dt;
        this.wobble += dt * 5;

        // Dano continuo (queimadura) da skin flamejante
        if (this.burnTimer > 0) {
            this.burnTimer -= dt;
            if (this.takeDamage(this.burnDamage * dt)) { engine.killEnemy(this); return; }
        }
        // Lentidao temporaria da skin congelante
        const slowMult = this.slowedTimer > 0 ? 0.5 : 1;
        if (this.slowedTimer > 0) this.slowedTimer -= dt;

        // Ataque em investida (boss 'charge') sobrepoe o movimento normal
        if (this.chargeTimer > 0) {
            this.chargeTimer -= dt;
            this.x += this.chargeVx * dt;
            this.y += this.chargeVy * dt;
            this.x = GameUtils.clamp(this.x, this.size, engine.map.width - this.size);
            this.y = GameUtils.clamp(this.y, this.size, engine.map.height - this.size);
            const dC = GameUtils.dist(this.x, this.y, player.x, player.y);
            if (dC < this.size + player.radius + 2 && this.attackTimer <= 0) {
                this.attackTimer = 0.6;
                engine.damagePlayer(this.damage, this.name);
            }
            return;
        }

        // ---------- Movimento ----------
        let tx = player.x, ty = player.y;

        if (this.behavior === 'swarm') {
            // Enxame: trajetoria com ruido senoidal
            tx += Math.sin(this.wobble) * 60;
            ty += Math.cos(this.wobble * 0.8) * 60;
        } else if (this.behavior === 'group') {
            // Grupo: cada bot mira um ponto ao redor do jogador (flanqueio)
            const d = GameUtils.dist(this.x, this.y, player.x, player.y);
            if (d > 120) {
                tx = player.x + Math.cos(this.flankAngle) * 90;
                ty = player.y + Math.sin(this.flankAngle) * 90;
            }
        }

        const dx = tx - this.x, ty2 = ty - this.y;
        const dist = Math.hypot(dx, ty2) || 1;
        let vx = (dx / dist) * this.speed * slowMult;
        let vy = (ty2 / dist) * this.speed * slowMult;

        // Boss enfurecido abaixo de 30% de vida
        if (this.behavior === 'boss' && !this.enraged && this.hp < this.maxHp * 0.3) {
            this.enraged = true;
            this.speed *= 1.4;
            engine.addFloatText(this.x, this.y - this.size - 14, '!! KERNEL PANIC !!', '#ff0044');
        }

        this.x += vx * dt;
        this.y += vy * dt;

        // Resolve colisao com obstaculos (empurra para fora)
        for (const ob of engine.map.obstacles) {
            if (GameUtils.circleRect(this.x, this.y, this.size, ob)) {
                const cx = GameUtils.clamp(this.x, ob.x, ob.x + ob.w);
                const cy = GameUtils.clamp(this.y, ob.y, ob.y + ob.h);
                const ddx = this.x - cx, ddy = this.y - cy;
                const dd = Math.hypot(ddx, ddy) || 1;
                const push = this.size - dd + 0.5;
                this.x += (ddx / dd) * push;
                this.y += (ddy / dd) * push;
            }
        }

        // Mantem dentro do mapa
        this.x = GameUtils.clamp(this.x, this.size, engine.map.width - this.size);
        this.y = GameUtils.clamp(this.y, this.size, engine.map.height - this.size);

        // ---------- Ataque corpo a corpo ----------
        const dPlayer = GameUtils.dist(this.x, this.y, player.x, player.y);
        if (dPlayer < this.size + player.radius + 2 && this.attackTimer <= 0) {
            this.attackTimer = 0.9;
            engine.damagePlayer(this.damage, this.name);

            // Ransomware: "criptografa" o jogador, reduzindo a velocidade
            if (this.behavior === 'slow_player') {
                player.applySlow(2.5, 0.55);
                engine.addFloatText(player.x, player.y - 26, 'ARQUIVOS CRIPTOGRAFADOS! (lento)', '#ffd24d');
            }
        }

        // ---------- Ataque especial do boss (varia por tipo) ----------
        if (this.behavior === 'boss') {
            this.specialTimer -= dt;
            if (this.specialTimer <= 0) {
                this.specialTimer = this.enraged ? 4 : 6.5;
                engine.bossSpecial(this);
            }
        }
    }

    /** Aplica dano. Retorna true se morreu. */
    takeDamage(amount) {
        this.hp -= amount;
        this.hitFlash = 0.1;
        return this.hp <= 0;
    }

    draw(ctx) {
        ctx.save();

        const flash = this.hitFlash > 0;
        ctx.shadowColor = this.color;
        ctx.shadowBlur = this.isBoss ? 24 : 10;
        ctx.fillStyle = flash ? '#ffffff' : this.color;

        // Corpo: forma varia por comportamento
        ctx.beginPath();
        if (this.behavior === 'tank') {
            // Firewall: hexagono
            for (let i = 0; i < 6; i++) {
                const a = (Math.PI / 3) * i + Math.PI / 6;
                const px = this.x + Math.cos(a) * this.size;
                const py = this.y + Math.sin(a) * this.size;
                i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
            }
            ctx.closePath();
        } else if (this.behavior === 'slow_heavy') {
            // Trojan: losango (cavalo disfarcado)
            ctx.moveTo(this.x, this.y - this.size);
            ctx.lineTo(this.x + this.size, this.y);
            ctx.lineTo(this.x, this.y + this.size);
            ctx.lineTo(this.x - this.size, this.y);
            ctx.closePath();
        } else if (this.behavior === 'swarm') {
            // Bug: triangulo pequeno
            const a = this.wobble;
            ctx.moveTo(this.x + Math.cos(a) * this.size, this.y + Math.sin(a) * this.size);
            ctx.lineTo(this.x + Math.cos(a + 2.4) * this.size, this.y + Math.sin(a + 2.4) * this.size);
            ctx.lineTo(this.x + Math.cos(a - 2.4) * this.size, this.y + Math.sin(a - 2.4) * this.size);
            ctx.closePath();
        } else if (this.behavior === 'boss') {
            // Kernel: circulo pulsante com nucleo
            const pulse = this.size + Math.sin(this.wobble) * 4;
            ctx.arc(this.x, this.y, pulse, 0, Math.PI * 2);
        } else {
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        }
        ctx.fill();

        // Detalhes
        if (this.behavior === 'boss') {
            ctx.fillStyle = '#1a0008';
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size * 0.5, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#ff0044';
            ctx.font = 'bold 12px monospace';
            ctx.textAlign = 'center';
            ctx.fillText('KERNEL', this.x, this.y + 4);
        } else if (this.behavior === 'group') {
            // Bot: "antena"
            ctx.strokeStyle = flash ? '#fff' : this.color;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(this.x, this.y - this.size);
            ctx.lineTo(this.x, this.y - this.size - 7);
            ctx.stroke();
        }

        // Barra de vida (apenas se ja tomou dano; boss tem barra no HUD)
        if (this.hp < this.maxHp && !this.isBoss) {
            const w = this.size * 2;
            ctx.shadowBlur = 0;
            ctx.fillStyle = 'rgba(0,0,0,0.6)';
            ctx.fillRect(this.x - w / 2, this.y - this.size - 9, w, 4);
            ctx.fillStyle = '#ff4d5e';
            ctx.fillRect(this.x - w / 2, this.y - this.size - 9, w * Math.max(0, this.hp / this.maxHp), 4);
        }

        ctx.restore();
        ctx.textAlign = 'left';
    }
}

const EnemyFactory = {
    /** Fallback caso o banco esteja vazio */
    fallback: [
        { id: 0, name: 'Virus Comum', slug: 'virus-comum', description: 'Rapido e fraco.',
          health: 18, speed: 130, damage: 6, xp_reward: 6, coin_reward: 1, score_reward: 10,
          size: 14, color: '#ff4d5e', behavior: 'chase', spawn_weight: 40, is_boss: 0, min_time: 0 },
    ],

    configs: [],

    /** Normaliza as linhas vindas do banco */
    setRows(rows) {
        const source = (rows && rows.length) ? rows : this.fallback;
        this.configs = source.map(r => ({
            id: Number(r.id) || 0,
            name: r.name,
            slug: r.slug,
            description: r.description || '',
            health: Number(r.health) || 20,
            speed: Number(r.speed) || 100,
            damage: Number(r.damage) || 8,
            xpReward: Number(r.xp_reward) || 5,
            coinReward: Number(r.coin_reward) || 1,
            scoreReward: Number(r.score_reward) || 10,
            size: Number(r.size) || 14,
            color: r.color || '#ff4d5e',
            behavior: r.behavior || 'chase',
            spawnWeight: Number(r.spawn_weight) || 0,
            isBoss: Number(r.is_boss) === 1,
            minTime: Number(r.min_time) || 0,
        }));
    },

    bySlug(slug) {
        return this.configs.find(c => c.slug === slug) || null;
    },

    /** Config do boss (primeiro is_boss=1) */
    bossConfig() {
        return this.configs.find(c => c.isBoss) || null;
    },

    /** Sorteio ponderado entre inimigos liberados pelo tempo de partida */
    pickSpawn(elapsed) {
        const eligible = this.configs.filter(c => !c.isBoss && c.spawnWeight > 0 && c.minTime <= elapsed);
        if (!eligible.length) return null;
        const totalWeight = eligible.reduce((sum, c) => sum + c.spawnWeight, 0);
        let roll = Math.random() * totalWeight;
        for (const c of eligible) {
            roll -= c.spawnWeight;
            if (roll <= 0) return c;
        }
        return eligible[eligible.length - 1];
    },
};

/* ============================================================
 * BOSS FACTORY - cria o boss do mapa a partir da linha da tabela `bosses`
 * ============================================================ */
const BossFactory = {
    row: null,

    setRow(bossRow) {
        this.row = bossRow || null;
    },

    /** Cria a instancia do boss (um Enemy com behavior 'boss' + ataque especial). */
    build(x, y, hpScale, dmgScale, speedMult) {
        const r = this.row;
        if (!r) return null;
        const cfg = {
            name: r.name,
            slug: r.slug,
            health: Number(r.health) || 1500,
            speed: Number(r.speed) || 60,
            damage: Number(r.damage) || 30,
            xpReward: Number(r.xp_reward) || 400,
            coinReward: Number(r.coin_reward) || 80,
            scoreReward: Number(r.score_reward) || 1000,
            size: Number(r.size) || 46,
            color: r.color || '#ff0044',
            behavior: 'boss',
            special: r.special_attack || 'radial',
            isBoss: true,
        };
        return new Enemy(cfg, x, y, hpScale, dmgScale, speedMult);
    },
};
