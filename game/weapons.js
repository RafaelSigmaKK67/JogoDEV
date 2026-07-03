/**
 * DEV SURVIVOR - weapons.js
 * Armas (do banco) e projeteis.
 *
 * Especiais suportados:
 *   area   -> projetil explode causando dano em area
 *   laser  -> feixe instantaneo em linha reta (atravessa inimigos)
 *   heal   -> cura o jogador (Patch de Seguranca)
 *   shield -> concede escudo temporario (Framework Shield)
 */

class Bullet {
    constructor(x, y, angle, speed, damage, range, color, special, pierce) {
        this.x = x;
        this.y = y;
        this.vx = Math.cos(angle) * speed;
        this.vy = Math.sin(angle) * speed;
        this.damage = damage;
        this.range = range;
        this.traveled = 0;
        this.color = color;
        this.special = special || null;
        this.pierce = pierce || 0;       // inimigos extras que o tiro atravessa (skin Laser Quantico)
        this.radius = special === 'area' ? 6 : 4;
    }

    /** Retorna false quando o projetil expira pelo alcance */
    update(dt) {
        const dx = this.vx * dt, dy = this.vy * dt;
        this.x += dx;
        this.y += dy;
        this.traveled += Math.hypot(dx, dy);
        return this.traveled < this.range;
    }

    draw(ctx) {
        ctx.save();
        ctx.shadowColor = this.color;
        ctx.shadowBlur = 8;
        ctx.fillStyle = this.color;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }
}

class Weapon {
    constructor(cfg) {
        this.cfg = cfg;          // config normalizada (ver WeaponFactory)
        this.cooldown = 0;       // tempo restante ate poder disparar
    }

    get name()    { return this.cfg.name; }
    get slug()    { return this.cfg.slug; }
    get color()   { return this.cfg.color; }
    get special() { return this.cfg.special; }

    update(dt) {
        if (this.cooldown > 0) this.cooldown -= dt;
    }

    canFire() {
        return this.cooldown <= 0;
    }

    /**
     * Dispara a arma. O engine fornece o contexto (bullets, lasers, efeitos).
     * Retorna true se o disparo aconteceu.
     */
    fire(player, angle, engine) {
        if (!this.canFire()) return false;
        const cfg = this.cfg;

        switch (cfg.special) {
            case 'heal': {
                if (player.hp >= player.maxHp) return false; // nao desperdica
                player.heal(cfg.damage);
                engine.addFloatText(player.x, player.y - 24, '+' + cfg.damage + ' HP [patch]', '#4dff88');
                engine.spawnParticles(player.x, player.y, '#4dff88', 14, 90);
                engine.sfx.heal();
                break;
            }
            case 'shield': {
                player.addShield(cfg.damage);
                engine.addFloatText(player.x, player.y - 24, '+' + cfg.damage + ' escudo', '#4d7dff');
                engine.spawnParticles(player.x, player.y, '#4d7dff', 14, 90);
                engine.sfx.heal();
                break;
            }
            case 'laser': {
                engine.fireLaser(player, angle, this);
                engine.sfx.laser();
                break;
            }
            default: {
                // Projeteis normais (inclui 'area')
                const total = cfg.projectiles + player.projectilesBonus;
                const spreadBase = cfg.spread;
                const color = player.bulletColor || cfg.color; // cor da skin de arma / efeito de tiro
                for (let i = 0; i < total; i++) {
                    // Distribui os projeteis em leque
                    const offset = total > 1 ? (i - (total - 1) / 2) * Math.max(spreadBase, 0.12) : 0;
                    const jitter = GameUtils.rand(-spreadBase / 2, spreadBase / 2);
                    const damage = cfg.damage * player.getDamageMult();
                    engine.bullets.push(new Bullet(
                        player.x + Math.cos(angle) * (player.radius + 6),
                        player.y + Math.sin(angle) * (player.radius + 6),
                        angle + offset + jitter,
                        cfg.bulletSpeed, damage, cfg.bulletRange, color, cfg.special,
                        cfg.special ? 0 : player.pierceBonus
                    ));
                }
                engine.sfx.shoot();
            }
        }

        // Cadencia considera buff de disparo rapido (ultimate/especial)
        this.cooldown = 1 / (cfg.fireRate * (player.fireRateMult || 1));
        return true;
    }
}

const WeaponFactory = {
    /** Fallback caso o banco esteja vazio */
    fallback: [
        { id: 0, name: 'Teclado Mecanico', slug: 'teclado-mecanico', description: 'Ataque basico.',
          damage: 12, fire_rate: 3.5, bullet_speed: 420, bullet_range: 380,
          projectiles: 1, spread: 0.06, special: null, color: '#4df3a3', unlock_level: 1 },
    ],

    configs: [],

    /** Normaliza as linhas vindas do banco (numeros chegam como string) */
    setRows(rows) {
        const source = (rows && rows.length) ? rows : this.fallback;
        this.configs = source.map(r => ({
            id: Number(r.id) || 0,
            name: r.name,
            slug: r.slug,
            description: r.description || '',
            damage: Number(r.damage) || 10,
            fireRate: Math.max(0.1, Number(r.fire_rate) || 1),
            bulletSpeed: Number(r.bullet_speed) || 400,
            bulletRange: Number(r.bullet_range) || 400,
            projectiles: Number(r.projectiles) || 0,
            spread: Number(r.spread) || 0,
            special: r.special || null,
            color: r.color || '#4df3a3',
            unlockLevel: Number(r.unlock_level) || 1,
        }));
    },

    bySlug(slug) {
        return this.configs.find(c => c.slug === slug) || null;
    },

    /** Arma inicial do jogador */
    starter() {
        return this.bySlug('teclado-mecanico') || this.configs[0];
    },

    /** Armas elegiveis para drop, conforme o nivel da conta */
    dropPool(accountLevel) {
        return this.configs.filter(c => c.unlockLevel <= accountLevel);
    },

    build(cfg) {
        return new Weapon(cfg);
    },
};
