/**
 * DEV SURVIVOR - maps.js
 * Utilitarios compartilhados (GameUtils) + construcao dos mapas.
 * Este arquivo e carregado PRIMEIRO pelos scripts do jogo.
 */

/* ============================================================
 * UTILITARIOS GERAIS
 * ============================================================ */
const GameUtils = {
    clamp(v, min, max) { return v < min ? min : (v > max ? max : v); },

    dist(x1, y1, x2, y2) { return Math.hypot(x2 - x1, y2 - y1); },

    rand(min, max) { return min + Math.random() * (max - min); },

    randInt(min, max) { return Math.floor(this.rand(min, max + 1)); },

    choose(arr) { return arr[Math.floor(Math.random() * arr.length)]; },

    /** RNG com semente (mapas geram sempre os mesmos obstaculos) */
    mulberry32(seed) {
        return function () {
            seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
            let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
            t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    },

    /** Colisao circulo (cx,cy,r) vs retangulo {x,y,w,h} */
    circleRect(cx, cy, r, rect) {
        const nx = this.clamp(cx, rect.x, rect.x + rect.w);
        const ny = this.clamp(cy, rect.y, rect.y + rect.h);
        const dx = cx - nx, dy = cy - ny;
        return (dx * dx + dy * dy) <= r * r;
    },

    /** Distancia de um ponto (px,py) ao segmento (x1,y1)-(x2,y2) */
    distToSegment(px, py, x1, y1, x2, y2) {
        const dx = x2 - x1, dy = y2 - y1;
        const lenSq = dx * dx + dy * dy;
        if (lenSq === 0) return this.dist(px, py, x1, y1);
        let t = ((px - x1) * dx + (py - y1) * dy) / lenSq;
        t = this.clamp(t, 0, 1);
        return this.dist(px, py, x1 + t * dx, y1 + t * dy);
    },
};

/* ============================================================
 * MAPAS
 * ============================================================ */
const GameMaps = {
    /** Fallback caso o banco esteja vazio (mesma estrutura das linhas SQL) */
    fallback: [{
        id: 0, name: 'Servidor Local', slug: 'servidor-local',
        description: 'Ambiente seguro de localhost.', difficulty: 'facil',
        width: 2000, height: 2000, bg_color: '#06120c', accent_color: '#2bbf7f',
        obstacle_style: 'blocks', obstacle_count: 18,
        enemy_multiplier: 1, spawn_rate: 1, has_hazards: 0, unlock_level: 1,
    }],

    /**
     * Converte uma linha do banco (mapa) + a dificuldade escolhida em um mapa
     * pronto para o engine. Os multiplicadores da dificuldade sao "assados" aqui.
     */
    buildRuntime(row, diff) {
        diff = diff || {};
        const order = Number(diff.order_index) || 0;
        const map = {
            id: Number(row.id) || 0,
            name: row.name,
            slug: row.slug,
            description: row.description || '',
            difficulty: row.difficulty || 'facil',
            theme: row.theme || '',
            width: Number(row.width) || 2000,
            height: Number(row.height) || 2000,
            bgColor: row.bg_color || '#06120c',
            accentColor: row.accent_color || '#2bbf7f',
            obstacleStyle: row.obstacle_style || 'blocks',
            enemyMult: Number(row.enemy_multiplier) || 1,
            spawnRate: Number(row.spawn_rate) || 1,
            hasHazards: Number(row.has_hazards) === 1,
            // ----- Multiplicadores da DIFICULDADE -----
            hpMult:      Number(diff.hp_mult) || 1,
            enemyDmgMult:Number(diff.damage_mult) || 1,
            speedMult:   Number(diff.speed_mult) || 1,
            countMult:   Number(diff.count_mult) || 1,
            coinMult:    Number(diff.coin_mult) || 1,
            xpMult:      Number(diff.xp_mult) || 1,
            rareDrop:    Number(diff.rare_drop_chance) || 0.05,
            timeRequired:Number(diff.time_required) || 150,
            scoreMult:   1 + order * 0.4, // pontuacao escala com a dificuldade
            obstacles: [],
        };
        map.obstacles = this.generateObstacles(map, Number(row.obstacle_count) || 18);
        return map;
    },

    /** Gera obstaculos deterministas (mesma semente = mesmo layout) */
    generateObstacles(map, count) {
        const rng = GameUtils.mulberry32(map.id * 7919 + 42);
        const obstacles = [];
        const margin = 80;
        const cx = map.width / 2, cy = map.height / 2;

        for (let i = 0; i < count; i++) {
            let placed = false;
            for (let attempt = 0; attempt < 12 && !placed; attempt++) {
                let w, h;
                switch (map.obstacleStyle) {
                    case 'tables':   w = 110 + rng() * 70;  h = 70 + rng() * 50;  break;
                    case 'nodes':    w = 60 + rng() * 60;   h = w;                break;
                    case 'clouds':   w = 130 + rng() * 100; h = 60 + rng() * 50;  break;
                    case 'glitch':   w = 20 + rng() * 30;   h = 90 + rng() * 160; break;
                    case 'firewall': w = 150 + rng() * 90;  h = 36 + rng() * 26;  break;
                    case 'servers':  w = 56 + rng() * 40;   h = 120 + rng() * 90; break;
                    case 'terminal': w = 120 + rng() * 80;  h = 80 + rng() * 50;  break;
                    case 'ai':       w = 70 + rng() * 60;   h = w;                break;
                    case 'core':     w = 60 + rng() * 70;   h = w;                break;
                    default:         w = 50 + rng() * 90;   h = 70 + rng() * 110; // blocks
                }
                const x = margin + rng() * (map.width - w - margin * 2);
                const y = margin + rng() * (map.height - h - margin * 2);

                // Mantem a area central livre (spawn do jogador)
                const obCx = x + w / 2, obCy = y + h / 2;
                if (Math.abs(obCx - cx) < 240 + w / 2 && Math.abs(obCy - cy) < 240 + h / 2) continue;

                obstacles.push({ x, y, w, h, style: map.obstacleStyle, seed: rng() });
                placed = true;
            }
        }
        return obstacles;
    },

    /** Desenha um obstaculo de acordo com o estilo do mapa */
    drawObstacle(ctx, ob, accent) {
        ctx.save();
        switch (ob.style) {
            case 'tables': { // tabelas SQL quebradas
                ctx.fillStyle = 'rgba(20, 28, 52, 0.92)';
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                ctx.fillRect(ob.x, ob.y, ob.w, ob.h);
                ctx.strokeRect(ob.x, ob.y, ob.w, ob.h);
                ctx.fillStyle = accent;
                ctx.globalAlpha = 0.85;
                ctx.fillRect(ob.x, ob.y, ob.w, 16); // cabecalho da tabela
                ctx.globalAlpha = 0.35;
                for (let y = ob.y + 30; y < ob.y + ob.h - 6; y += 16) {
                    ctx.fillRect(ob.x + 5, y, ob.w - 10, 2); // linhas da tabela
                }
                ctx.globalAlpha = 1;
                ctx.fillStyle = '#0a0f1e';
                ctx.font = 'bold 10px monospace';
                ctx.fillText('TABLE', ob.x + 5, ob.y + 12);
                break;
            }
            case 'nodes': { // nos de rede
                const r = ob.w / 2;
                ctx.beginPath();
                ctx.arc(ob.x + r, ob.y + r, r, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(30, 16, 48, 0.92)';
                ctx.fill();
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2.5;
                ctx.stroke();
                ctx.beginPath();
                ctx.arc(ob.x + r, ob.y + r, r * 0.45, 0, Math.PI * 2);
                ctx.strokeStyle = accent;
                ctx.globalAlpha = 0.5;
                ctx.stroke();
                ctx.globalAlpha = 1;
                break;
            }
            case 'clouds': { // blocos de nuvem
                ctx.fillStyle = 'rgba(40, 56, 80, 0.85)';
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                const r = 18;
                ctx.beginPath();
                ctx.moveTo(ob.x + r, ob.y);
                ctx.arcTo(ob.x + ob.w, ob.y, ob.x + ob.w, ob.y + ob.h, r);
                ctx.arcTo(ob.x + ob.w, ob.y + ob.h, ob.x, ob.y + ob.h, r);
                ctx.arcTo(ob.x, ob.y + ob.h, ob.x, ob.y, r);
                ctx.arcTo(ob.x, ob.y, ob.x + ob.w, ob.y, r);
                ctx.fill();
                ctx.stroke();
                ctx.fillStyle = accent;
                ctx.globalAlpha = 0.6;
                ctx.font = '11px monospace';
                ctx.fillText('::cloud', ob.x + 8, ob.y + 16);
                ctx.globalAlpha = 1;
                break;
            }
            case 'glitch': { // fragmentos glitchados
                ctx.fillStyle = 'rgba(60, 10, 20, 0.9)';
                ctx.fillRect(ob.x, ob.y, ob.w, ob.h);
                ctx.fillStyle = 'rgba(255, 61, 94, 0.55)';
                ctx.fillRect(ob.x + 3, ob.y - 2, ob.w, ob.h * 0.3);
                ctx.fillStyle = 'rgba(57, 194, 255, 0.4)';
                ctx.fillRect(ob.x - 3, ob.y + ob.h * 0.6, ob.w, ob.h * 0.25);
                ctx.strokeStyle = accent;
                ctx.lineWidth = 1.5;
                ctx.strokeRect(ob.x, ob.y, ob.w, ob.h);
                break;
            }
            case 'firewall': { // tijolos de firewall
                ctx.fillStyle = 'rgba(40, 14, 6, 0.95)';
                ctx.fillRect(ob.x, ob.y, ob.w, ob.h);
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                ctx.strokeRect(ob.x, ob.y, ob.w, ob.h);
                ctx.globalAlpha = 0.5;
                for (let bx = ob.x + 6; bx < ob.x + ob.w - 6; bx += 28) ctx.strokeRect(bx, ob.y + 4, 24, ob.h - 8);
                ctx.globalAlpha = 1;
                break;
            }
            case 'servers': { // torres de servidor altas
                ctx.fillStyle = 'rgba(10, 20, 22, 0.95)';
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                ctx.fillRect(ob.x, ob.y, ob.w, ob.h);
                ctx.strokeRect(ob.x, ob.y, ob.w, ob.h);
                for (let y = ob.y + 8; y < ob.y + ob.h - 6; y += 14) {
                    ctx.fillStyle = (Math.floor(y + ob.seed * 90) % 3 === 0) ? accent : 'rgba(127,212,192,0.3)';
                    ctx.fillRect(ob.x + 6, y, ob.w - 12, 4);
                }
                break;
            }
            case 'terminal': { // janela de terminal
                ctx.fillStyle = 'rgba(2, 10, 6, 0.96)';
                ctx.fillRect(ob.x, ob.y, ob.w, ob.h);
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                ctx.strokeRect(ob.x, ob.y, ob.w, ob.h);
                ctx.fillStyle = accent;
                ctx.font = '12px monospace';
                ctx.globalAlpha = 0.8;
                ctx.fillText('> _', ob.x + 8, ob.y + 18);
                ctx.globalAlpha = 0.3;
                for (let y = ob.y + 28; y < ob.y + ob.h - 6; y += 12) ctx.fillRect(ob.x + 8, y, (ob.w - 16) * (0.4 + (Math.sin(y + ob.seed) + 1) * 0.25), 3);
                ctx.globalAlpha = 1;
                break;
            }
            case 'ai': { // nucleo de IA (hexagono com circulo)
                const r = ob.w / 2, cx = ob.x + r, cy = ob.y + r;
                ctx.beginPath();
                for (let i = 0; i < 6; i++) {
                    const a = (Math.PI / 3) * i;
                    const px = cx + Math.cos(a) * r, py = cy + Math.sin(a) * r;
                    i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
                }
                ctx.closePath();
                ctx.fillStyle = 'rgba(30, 10, 44, 0.9)';
                ctx.fill();
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                ctx.stroke();
                ctx.beginPath();
                ctx.arc(cx, cy, r * 0.4, 0, Math.PI * 2);
                ctx.strokeStyle = accent;
                ctx.stroke();
                break;
            }
            case 'core': { // fragmento do nucleo (losango brilhante)
                const r = ob.w / 2, cx = ob.x + r, cy = ob.y + r;
                ctx.shadowColor = accent;
                ctx.shadowBlur = 16;
                ctx.beginPath();
                ctx.moveTo(cx, cy - r); ctx.lineTo(cx + r, cy);
                ctx.lineTo(cx, cy + r); ctx.lineTo(cx - r, cy);
                ctx.closePath();
                ctx.fillStyle = 'rgba(50, 0, 12, 0.9)';
                ctx.fill();
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                ctx.stroke();
                break;
            }
            default: { // blocks: racks de servidor
                ctx.fillStyle = 'rgba(12, 32, 24, 0.95)';
                ctx.strokeStyle = accent;
                ctx.lineWidth = 2;
                ctx.fillRect(ob.x, ob.y, ob.w, ob.h);
                ctx.strokeRect(ob.x, ob.y, ob.w, ob.h);
                // LEDs do rack
                for (let y = ob.y + 10; y < ob.y + ob.h - 8; y += 18) {
                    ctx.fillStyle = (Math.floor(y + ob.seed * 100) % 2 === 0) ? '#4df3a3' : '#ff4d5e';
                    ctx.fillRect(ob.x + 6, y, 4, 4);
                    ctx.fillStyle = 'rgba(77, 243, 163, 0.25)';
                    ctx.fillRect(ob.x + 16, y, ob.w - 24, 3);
                }
            }
        }
        ctx.restore();
    },
};
