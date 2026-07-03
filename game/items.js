/**
 * DEV SURVIVOR - items.js
 * Drops de inimigos: moedas, vida, e armas. Coleta com efeito ima.
 */

const Items = {
    list: [],

    reset() {
        this.list = [];
    },

    /**
     * Chamado quando um inimigo morre. Pode dropar itens.
     * weaponPool: array de configs de armas elegiveis para drop.
     * rareChance: chance de dropar arma (escala com a dificuldade).
     */
    maybeDrop(enemy, x, y, weaponPool, rareChance) {
        rareChance = rareChance || 0.05;
        // Moedas: sempre dropa o valor do inimigo
        if (enemy.coinReward > 0) {
            this.list.push(this.make('coin', x + GameUtils.rand(-8, 8), y + GameUtils.rand(-8, 8), enemy.coinReward));
        }
        // Vida: 8% de chance
        if (Math.random() < 0.08) {
            this.list.push(this.make('health', x, y, 25));
        }
        // Arma: chance da dificuldade (bosses sempre dropam)
        if (weaponPool && weaponPool.length && (Math.random() < rareChance || enemy.isBoss)) {
            const cfg = GameUtils.choose(weaponPool);
            const item = this.make('weapon', x, y, 0);
            item.weaponSlug = cfg.slug;
            item.weaponName = cfg.name;
            item.color = cfg.color;
            this.list.push(item);
        }
    },

    make(type, x, y, value) {
        const colors = { coin: '#ffd24d', health: '#4dff88', weapon: '#39c2ff' };
        return { type, x, y, value, color: colors[type], life: 25, bob: Math.random() * Math.PI * 2 };
    },

    /** Atualiza fisica/coleta. Retorna eventos de coleta para o engine. */
    update(dt, player, engine) {
        for (let i = this.list.length - 1; i >= 0; i--) {
            const item = this.list[i];
            item.life -= dt;
            item.bob += dt * 4;

            if (item.life <= 0) {
                this.list.splice(i, 1);
                continue;
            }

            const d = GameUtils.dist(item.x, item.y, player.x, player.y);

            // Efeito ima: o item desliza ate o jogador quando perto
            if (d < 110 && d > 1) {
                const pull = 260 * dt;
                item.x += ((player.x - item.x) / d) * pull;
                item.y += ((player.y - item.y) / d) * pull;
            }

            // Coleta
            if (d < player.radius + 12) {
                this.collect(item, player, engine);
                this.list.splice(i, 1);
            }
        }
    },

    collect(item, player, engine) {
        switch (item.type) {
            case 'coin':
                engine.addCoins(item.value); // aplica bonus de moedas (equip/skin/passiva)
                engine.addFloatText(item.x, item.y, '+' + item.value + '¢', '#ffd24d');
                break;
            case 'health':
                player.heal(item.value);
                engine.addFloatText(item.x, item.y, '+' + item.value + ' HP', '#4dff88');
                break;
            case 'weapon': {
                const added = engine.giveWeapon(item.weaponSlug);
                if (added) {
                    engine.addFloatText(item.x, item.y, item.weaponName + '!', '#39c2ff');
                } else {
                    // Arma repetida vira pontos
                    engine.stats.score += 50;
                    engine.addFloatText(item.x, item.y, '+50 pts (duplicada)', '#9fb3c8');
                }
                break;
            }
        }
        engine.sfx.pickup();
    },

    draw(ctx) {
        for (const item of this.list) {
            const y = item.y + Math.sin(item.bob) * 3;
            const blink = item.life < 4 && Math.floor(item.life * 6) % 2 === 0;
            if (blink) continue; // pisca antes de sumir

            ctx.save();
            ctx.shadowColor = item.color;
            ctx.shadowBlur = 10;

            if (item.type === 'coin') {
                ctx.fillStyle = item.color;
                ctx.beginPath();
                ctx.arc(item.x, y, 6, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#7a5c00';
                ctx.font = 'bold 8px monospace';
                ctx.textAlign = 'center';
                ctx.fillText('¢', item.x, y + 3);
            } else if (item.type === 'health') {
                ctx.fillStyle = item.color;
                ctx.fillRect(item.x - 7, y - 2.5, 14, 5);
                ctx.fillRect(item.x - 2.5, y - 7, 5, 14);
            } else { // weapon
                ctx.strokeStyle = item.color;
                ctx.lineWidth = 2;
                ctx.strokeRect(item.x - 8, y - 8, 16, 16);
                ctx.fillStyle = item.color;
                ctx.font = 'bold 10px monospace';
                ctx.textAlign = 'center';
                ctx.fillText('W', item.x, y + 4);
            }
            ctx.restore();
        }
        ctx.textAlign = 'left';
    },
};
