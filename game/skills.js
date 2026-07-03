/**
 * DEV SURVIVOR - skills.js
 * Habilidades escolhidas ao subir de nivel dentro da partida.
 *
 * Efeitos suportados (coluna `effect` no banco):
 *   speed | damage | max_health | regen | double_shot | shield |
 *   area_attack | damage_reduction | xp_boost | crit_chance
 */

const Skills = {
    /** Fallback caso o banco esteja vazio */
    fallback: [
        { id: 0, name: 'Refatoracao Agil', slug: 'velocidade', description: '+10% velocidade.',
          effect: 'speed', effect_value: 0.10, max_stacks: 5, icon: '>>' },
        { id: 0, name: 'Codigo Otimizado', slug: 'dano', description: '+15% de dano.',
          effect: 'damage', effect_value: 0.15, max_stacks: 5, icon: '++' },
        { id: 0, name: 'RAM Extra', slug: 'vida-maxima', description: '+20 vida maxima.',
          effect: 'max_health', effect_value: 20, max_stacks: 5, icon: '[]' },
    ],

    pool: [],

    /** Normaliza as linhas do banco */
    setRows(rows) {
        const source = (rows && rows.length) ? rows : this.fallback;
        this.pool = source.map(r => ({
            id: Number(r.id) || 0,
            name: r.name,
            slug: r.slug,
            description: r.description || '',
            effect: r.effect,
            value: Number(r.effect_value) || 0,
            maxStacks: Number(r.max_stacks) || 1,
            icon: r.icon || '*',
        }));
    },

    /** Sorteia N habilidades ainda disponiveis (respeita max_stacks) */
    pickChoices(n, player) {
        const available = this.pool.filter(s => (player.skillStacks[s.slug] || 0) < s.maxStacks);
        // Embaralha (Fisher-Yates)
        const shuffled = available.slice();
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled.slice(0, n);
    },

    /** Aplica o efeito da habilidade no jogador */
    apply(player, skill) {
        player.skillStacks[skill.slug] = (player.skillStacks[skill.slug] || 0) + 1;
        player.skillsTaken.push(skill.name);

        switch (skill.effect) {
            case 'speed':
                player.baseSpeed *= (1 + skill.value);
                break;
            case 'damage':
                player.damageMult += skill.value;
                break;
            case 'max_health':
                player.maxHp += skill.value;
                player.hp = Math.min(player.maxHp, player.hp + skill.value);
                break;
            case 'regen':
                player.regen += skill.value;
                break;
            case 'double_shot':
                player.projectilesBonus += Math.round(skill.value) || 1;
                break;
            case 'shield':
                player.shieldMax += skill.value;
                player.addShield(skill.value);
                break;
            case 'area_attack':
                player.novaLevel += 1;
                break;
            case 'damage_reduction':
                player.damageReduction = Math.min(0.6, player.damageReduction + skill.value);
                break;
            case 'xp_boost':
                player.xpBoost += skill.value;
                break;
            case 'crit_chance':
                player.critChance = Math.min(0.6, player.critChance + skill.value);
                break;
        }
    },

    /** Quantas vezes o jogador ja pegou essa habilidade (para a UI) */
    stacksOf(player, skill) {
        return player.skillStacks[skill.slug] || 0;
    },
};
