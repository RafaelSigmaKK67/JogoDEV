/**
 * DEV SURVIVOR - player.js
 * O personagem jogavel. Agora carrega a classe escolhida (characters.js),
 * os bonus agregados (skins + equipamentos + passiva), a habilidade especial (Q)
 * e a ultimate (R, com barra de carga).
 */

class Player {
    /**
     * opts = {
     *   name, character (normalizado), mods (CharacterFactory.computeMods),
     *   weaponCfg (arma inicial), extraWeaponCfgs (arsenal possuido),
     *   bulletColor (cor da skin de arma, opcional)
     * }
     */
    constructor(x, y, opts) {
        opts = opts || {};
        const char = opts.character || CharacterFactory.build(null);
        const mods = opts.mods || {};

        this.name = opts.name || 'dev';
        this.character = char;
        this.mods = mods;
        this.x = x;
        this.y = y;
        this.radius = 15;
        this.color = char.color;

        // ---------- Atributos derivados (classe + bonus) ----------
        const maxHpPct  = 1 + (mods.maxHealthPct || 0);
        this.maxHp = Math.round(char.baseHealth * maxHpPct) + (mods.maxHealthFlat || 0);
        this.hp = this.maxHp;
        this.baseSpeed = char.baseSpeed * (1 + (mods.speedPct || 0));
        this.damageMult = char.baseDamage * (1 + (mods.damagePct || 0));
        this.damageReduction = GameUtils.clamp(char.baseDefense + (mods.defenseAdd || 0), 0, 0.85);

        // Bonus diversos
        this.xpBoost   = 1 + (mods.xpPct || 0);
        this.coinBoost = 1 + (mods.coinPct || 0);
        this.cooldownMult = GameUtils.clamp(1 - (mods.cooldownPct || 0), 0.3, 1);
        this.dodgeChance  = mods.dodge || 0;
        this.antiBot      = mods.antiBot || 0;       // dano extra vs bots/malware
        this.antiMalware  = mods.antiMalware || 0;   // dano extra vs malware
        this.slowChance   = mods.slowChance || 0;    // skin congelante
        this.burnChance   = mods.burnChance || 0;    // skin flamejante
        this.pierceBonus  = mods.pierce || 0;        // skin laser quantico
        this.debuffResist = mods.debuffResist || 0;

        // Cor dos projeteis (skin de arma / efeito de tiro) e aura (efeito de personagem)
        this.bulletColor = opts.bulletColor || null;
        this.auraColor = opts.auraColor || null;

        // ---------- Defesa / regen (skills) ----------
        this.regen = 0;
        this.critChance = 0;
        this.projectilesBonus = 0;
        this.novaLevel = 0;

        // ---------- Escudo ----------
        this.shield = 0;
        this.shieldMax = 0;
        this.shieldRegenDelay = 0;

        // ---------- Nivel / XP da partida ----------
        this.level = 1;
        this.xp = 0;
        this.xpToNext = this.xpRequired(1);
        this.totalXpGained = 0;

        // ---------- Estado ----------
        this.aimAngle = 0;
        this.slowTimer = 0;
        this.slowFactor = 1;
        this.invulnTimer = 0;
        this.lastDodged = false;

        // ---------- Buffs temporarios (habilidades) ----------
        this.tempDamageMult = 1;   // buff de dano (ultimate/special)
        this.fireRateMult = 1;     // disparo rapido
        this.buffTimer = 0;        // dano
        this.rapidTimer = 0;       // fire rate
        this.infiniteHp = false;   // sandbox

        // ---------- Habilidade especial (Q) e Ultimate (R) ----------
        this.specialKey = char.specialKey;
        this.ultimateKey = char.ultimateKey;
        this.specialCooldownMax = 9 * this.cooldownMult;
        this.specialCooldown = 0;
        this.ultCharge = 0;        // 0..100
        this.ultCooldown = 0;      // pequeno bloqueio apos usar

        // ---------- Armas ----------
        this.weapons = [WeaponFactory.build(opts.weaponCfg || WeaponFactory.starter())];
        (opts.extraWeaponCfgs || []).forEach((cfg) => {
            if (cfg && !this.weapons.some((w) => w.slug === cfg.slug)) {
                this.weapons.push(WeaponFactory.build(cfg));
            }
        });
        this.weaponIndex = 0;

        // ---------- Habilidades (skills de level up) ----------
        this.skillStacks = {};
        this.skillsTaken = [];
    }

    xpRequired(level) {
        return Math.floor(50 * Math.pow(level, 1.3));
    }

    get speed() {
        return this.baseSpeed * (this.slowTimer > 0 ? this.slowFactor : 1);
    }

    /** Dano efetivo considerando buffs temporarios. */
    getDamageMult() {
        return this.damageMult * this.tempDamageMult;
    }

    currentWeapon() { return this.weapons[this.weaponIndex]; }

    addWeapon(cfg) {
        if (this.weapons.some((w) => w.slug === cfg.slug)) return false;
        if (this.weapons.length >= 9) return false;
        this.weapons.push(WeaponFactory.build(cfg));
        return true;
    }

    switchWeapon(index) {
        if (index >= 0 && index < this.weapons.length) this.weaponIndex = index;
    }

    cycleWeapon(delta) {
        const n = this.weapons.length;
        this.weaponIndex = ((this.weaponIndex + delta) % n + n) % n;
    }

    /** Carrega a ultimate (ao causar/receber dano e ao matar). */
    addUltCharge(amount) {
        if (this.ultCooldown > 0) return;
        this.ultCharge = GameUtils.clamp(this.ultCharge + amount, 0, 100);
    }

    get ultReady() { return this.ultCharge >= 100 && this.ultCooldown <= 0; }
    get specialReady() { return this.specialCooldown <= 0; }

    update(dt, input, map) {
        // Movimento WASD com colisao por eixo
        let mx = 0, my = 0;
        if (input.keys['w'] || input.keys['arrowup'])    my -= 1;
        if (input.keys['s'] || input.keys['arrowdown'])  my += 1;
        if (input.keys['a'] || input.keys['arrowleft'])  mx -= 1;
        if (input.keys['d'] || input.keys['arrowright']) mx += 1;

        if (mx !== 0 || my !== 0) {
            const len = Math.hypot(mx, my);
            const step = this.speed * dt;
            const newX = GameUtils.clamp(this.x + (mx / len) * step, this.radius, map.width - this.radius);
            if (!map.obstacles.some((ob) => GameUtils.circleRect(newX, this.y, this.radius, ob))) this.x = newX;
            const newY = GameUtils.clamp(this.y + (my / len) * step, this.radius, map.height - this.radius);
            if (!map.obstacles.some((ob) => GameUtils.circleRect(this.x, newY, this.radius, ob))) this.y = newY;
        }

        // Temporizadores
        if (this.slowTimer > 0)      this.slowTimer -= dt;
        if (this.invulnTimer > 0)    this.invulnTimer -= dt;
        if (this.specialCooldown > 0) this.specialCooldown -= dt;
        if (this.ultCooldown > 0)    this.ultCooldown -= dt;

        // Buffs
        if (this.buffTimer > 0) { this.buffTimer -= dt; if (this.buffTimer <= 0) this.tempDamageMult = 1; }
        if (this.rapidTimer > 0) { this.rapidTimer -= dt; if (this.rapidTimer <= 0) this.fireRateMult = 1; }

        // Regen de vida (skill Auto Save)
        if (this.regen > 0 && this.hp < this.maxHp) {
            this.hp = Math.min(this.maxHp, this.hp + this.regen * dt);
        }
        // Regen de escudo (skill Try/Catch)
        if (this.shieldMax > 0 && this.shield < this.shieldMax) {
            this.shieldRegenDelay -= dt;
            if (this.shieldRegenDelay <= 0) this.shield = Math.min(this.shieldMax, this.shield + 12 * dt);
        }

        for (const w of this.weapons) w.update(dt);
    }

    /** Aplica dano (dodge -> escudo -> vida). Retorna true se morreu. */
    takeDamage(amount) {
        this.lastDodged = false;
        if (this.invulnTimer > 0) return false;
        if (this.infiniteHp) return false;

        // Chance de desviar (skin Dev Fantasma)
        if (this.dodgeChance > 0 && Math.random() < this.dodgeChance) {
            this.lastDodged = true;
            this.invulnTimer = 0.2;
            return false;
        }

        let dmg = amount * (1 - this.damageReduction);
        if (this.shield > 0) {
            const absorbed = Math.min(this.shield, dmg);
            this.shield -= absorbed;
            dmg -= absorbed;
        }
        this.hp -= dmg;
        this.invulnTimer = 0.35;
        this.shieldRegenDelay = 6;
        return this.hp <= 0;
    }

    heal(amount) { this.hp = Math.min(this.maxHp, this.hp + amount); }

    addShield(amount) {
        const cap = Math.max(this.shieldMax, 80);
        this.shield = Math.min(cap, this.shield + amount);
    }

    applySlow(duration, factor) {
        this.slowTimer = duration * (1 - this.debuffResist);
        this.slowFactor = factor;
    }

    /** Ganha XP. Retorna quantos niveis subiu. */
    gainXP(amount) {
        const gained = Math.round(amount * this.xpBoost);
        this.xp += gained;
        this.totalXpGained += gained;
        let levels = 0;
        while (this.xp >= this.xpToNext) {
            this.xp -= this.xpToNext;
            this.level++;
            levels++;
            this.xpToNext = this.xpRequired(this.level);
        }
        return levels;
    }

    draw(ctx) {
        ctx.save();

        // Aura (efeito de personagem comprado na loja)
        if (this.auraColor) {
            ctx.save();
            ctx.globalAlpha = 0.18;
            ctx.shadowColor = this.auraColor;
            ctx.shadowBlur = 18;
            ctx.fillStyle = this.auraColor;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius + 9, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        }

        if (this.invulnTimer > 0 && Math.floor(this.invulnTimer * 20) % 2 === 0) {
            ctx.globalAlpha = 0.45;
        }

        const sprite = (window.GameAssets && GameAssets.images.player) || null;
        if (sprite) {
            const s = this.radius * 2.4;
            ctx.translate(this.x, this.y);
            ctx.rotate(this.aimAngle + Math.PI / 2);
            ctx.drawImage(sprite, -s / 2, -s / 2, s, s);
        } else {
            ctx.shadowColor = this.color;
            ctx.shadowBlur = 14;
            ctx.fillStyle = this.color + '22';
            ctx.strokeStyle = this.color;
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();

            ctx.shadowBlur = 0;
            const ex = Math.cos(this.aimAngle), ey = Math.sin(this.aimAngle);
            ctx.fillStyle = this.color;
            ctx.fillRect(this.x + ex * 6 - ey * 5 - 2, this.y + ey * 6 + ex * 5 - 2, 4, 4);
            ctx.fillRect(this.x + ex * 6 + ey * 5 - 2, this.y + ey * 6 - ex * 5 - 2, 4, 4);

            const weapon = this.currentWeapon();
            ctx.strokeStyle = this.bulletColor || (weapon ? weapon.color : this.color);
            ctx.lineWidth = 4;
            ctx.beginPath();
            ctx.moveTo(this.x + ex * this.radius, this.y + ey * this.radius);
            ctx.lineTo(this.x + ex * (this.radius + 11), this.y + ey * (this.radius + 11));
            ctx.stroke();
        }
        ctx.restore();

        if (this.shield > 0) {
            ctx.save();
            ctx.strokeStyle = 'rgba(77, 125, 255, 0.7)';
            ctx.lineWidth = 2;
            ctx.setLineDash([6, 5]);
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius + 7, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();
        }

        ctx.save();
        ctx.fillStyle = 'rgba(159, 179, 200, 0.9)';
        ctx.font = '11px monospace';
        ctx.textAlign = 'center';
        ctx.fillText(this.name, this.x, this.y - this.radius - 10);
        ctx.restore();
        ctx.textAlign = 'left';
    }
}
