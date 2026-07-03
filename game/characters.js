/**
 * DEV SURVIVOR - characters.js
 * Fabrica de personagens jogaveis e agregacao de bonus (skins + equipamentos + passiva).
 * Carregado antes de player.js / engine.js.
 *
 * Os EFEITOS das ultimates/especiais sao implementados no engine.js
 * (precisam manipular inimigos/projeteis). Aqui ficam apenas os dados.
 */

const CharacterFactory = {
    /** Fallback caso o banco esteja vazio */
    fallback: {
        slug: 'dev-frontend', name: 'Dev Front-End', color: '#39c2ff',
        base_health: 100, base_speed: 210, base_damage: 1.0, base_defense: 0,
        passive_key: 'none', passive_value: 0,
        special_key: 'none', ultimate_key: 'none',
        special_name: '—', ultimate_name: '—',
    },

    /** Normaliza uma linha do banco em um objeto pronto para o jogo. */
    build(row) {
        const r = row || this.fallback;
        return {
            slug: r.slug,
            name: r.name,
            color: r.color || '#4df3a3',
            baseHealth: Number(r.base_health) || 100,
            baseSpeed: Number(r.base_speed) || 210,
            baseDamage: Number(r.base_damage) || 1.0,
            baseDefense: Number(r.base_defense) || 0,
            passiveKey: r.passive_key || 'none',
            passiveValue: Number(r.passive_value) || 0,
            specialKey: r.special_key || 'none',
            specialName: r.special_name || '—',
            ultimateKey: r.ultimate_key || 'none',
            ultimateName: r.ultimate_name || '—',
        };
    },

    /**
     * Agrega TODOS os bonus do jogador (passiva do personagem + skins + equipamentos)
     * em um unico objeto de modificadores aplicado no Player.
     *
     * loadoutEffects = {
     *   charSkin:   { effect_key, effect_value } | null,
     *   weaponSkin: { effect_key, effect_value, color } | null,
     *   equipment:  [ { bonus_key, bonus_value }, ... ],
     *   ...cores visuais...
     * }
     */
    computeMods(character, loadoutEffects) {
        const mods = {
            maxHealthFlat: 0, maxHealthPct: 0, speedPct: 0, damagePct: 0, defenseAdd: 0,
            xpPct: 0, coinPct: 0, cooldownPct: 0,
            dodge: 0, antiBot: 0, antiMalware: 0,
            slowChance: 0, burnChance: 0, pierce: 0, debuffResist: 0,
        };
        const fx = loadoutEffects || {};

        // ---- Passiva do personagem ----
        const pv = character.passiveValue;
        switch (character.passiveKey) {
            case 'speed':         mods.speedPct += pv; break;
            case 'defense':       mods.defenseAdd += pv; break;
            case 'anti_bot':      mods.antiBot += pv; break;
            case 'debuff_resist': mods.debuffResist += pv; break;
            case 'xp_gain':       mods.xpPct += pv; break;
            case 'cooldown':      mods.cooldownPct += pv; break;
            case 'max_health':    mods.maxHealthPct += pv; break;
            case 'coin_gain':     mods.coinPct += pv; break;
            case 'balanced':      // +5% em velocidade, dano, defesa e vida
                mods.speedPct += pv; mods.damagePct += pv; mods.defenseAdd += pv; mods.maxHealthPct += pv; break;
            case 'all_bonus':     // bonus geral
                mods.speedPct += pv; mods.damagePct += pv; mods.defenseAdd += pv;
                mods.maxHealthPct += pv; mods.xpPct += pv; mods.coinPct += pv; break;
        }

        // ---- Skin de personagem ----
        if (fx.charSkin && fx.charSkin.effect_key) {
            const v = Number(fx.charSkin.effect_value) || 0;
            switch (fx.charSkin.effect_key) {
                case 'speed':    mods.speedPct += v; break;
                case 'anti_bot': mods.antiBot += v; break;
                case 'defense':  mods.defenseAdd += v; break;
                case 'dodge':    mods.dodge += v; break;
                case 'xp':       mods.xpPct += v; break;
                case 'hybrid':   mods.damagePct += v; mods.maxHealthPct += v; break;
            }
        }

        // ---- Skin de arma ----
        if (fx.weaponSkin && fx.weaponSkin.effect_key) {
            const v = Number(fx.weaponSkin.effect_value) || 0;
            switch (fx.weaponSkin.effect_key) {
                case 'damage':       mods.damagePct += v; break;
                case 'anti_malware': mods.antiMalware += v; break;
                case 'slow_chance':  mods.slowChance += v; break;
                case 'burn_chance':  mods.burnChance += v; break;
                case 'pierce':       mods.pierce += Math.round(v) || 1; break;
                // 'trail' e apenas visual (cor do projetil)
            }
        }

        // ---- Equipamentos ----
        (fx.equipment || []).forEach((eq) => {
            const v = Number(eq.bonus_value) || 0;
            switch (eq.bonus_key) {
                case 'max_health': mods.maxHealthFlat += v; break; // valor sempre flat
                case 'damage':     mods.damagePct += v; break;
                case 'defense':    mods.defenseAdd += v; break;
                case 'speed':      mods.speedPct += v; break;
                case 'xp':         mods.xpPct += v; break;
                case 'coins':      mods.coinPct += v; break;
                case 'cooldown':   mods.cooldownPct += v; break;
            }
        });

        return mods;
    },
};
