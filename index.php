<?php
/**
 * DEV SURVIVOR - Tela inicial (landing page)
 */
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Inicio';
require_once __DIR__ . '/includes/header.php';

// Pequenas estatisticas globais para a vitrine
$totals = db()->query(
    'SELECT
        (SELECT COUNT(*) FROM users)   AS players,
        (SELECT COUNT(*) FROM matches) AS matches,
        (SELECT COALESCE(MAX(best_score), 0) FROM rankings) AS top_score'
)->fetch();
?>

<section class="hero">
    <p class="hero-tag">// battle royale 2D &middot; tema dev &middot; sobreviva ao apocalipse digital</p>
    <h1 class="hero-title">
        O sistema foi <span class="glitch-text">COMPROMETIDO</span>
    </h1>
    <p class="hero-sub">
        Voce e um(a) <strong>desenvolvedor(a)</strong> preso(a) dentro de um ambiente digital infestado de
        <span class="tx-red">virus</span>, <span class="tx-orange">malwares</span>,
        <span class="tx-purple">trojans</span>, <span class="tx-yellow">ransomwares</span> e
        <span class="tx-pink">bugs criticos</span>.
        Colete armas, escolha habilidades, derrote o <strong class="tx-red">Kernel Corrompido</strong>
        e sobreviva o maximo possivel.
    </p>

    <div class="hero-actions">
        <?php if (isLoggedIn()): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(BASE_URL) ?>/game/index.php">&#9654; INICIAR PARTIDA</a>
            <a class="btn btn-ghost btn-lg" href="<?= e(BASE_URL) ?>/pages/dashboard.php">Meu Dashboard</a>
        <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(BASE_URL) ?>/pages/register.php">CRIAR CONTA GRATIS</a>
            <a class="btn btn-ghost btn-lg" href="<?= e(BASE_URL) ?>/pages/login.php">Ja tenho conta</a>
        <?php endif; ?>
    </div>

    <div class="hero-stats">
        <div class="stat-chip"><span class="stat-num"><?= e($totals['players']) ?></span> devs cadastrados</div>
        <div class="stat-chip"><span class="stat-num"><?= e($totals['matches']) ?></span> partidas jogadas</div>
        <div class="stat-chip"><span class="stat-num"><?= e(number_format((int)$totals['top_score'], 0, ',', '.')) ?></span> recorde global</div>
    </div>
</section>

<section class="cards-grid">
    <div class="card">
        <h3 class="tx-green">&gt;_ Como jogar</h3>
        <ul class="list-clean">
            <li><kbd>W</kbd><kbd>A</kbd><kbd>S</kbd><kbd>D</kbd> &mdash; mover o personagem</li>
            <li><strong>Mouse</strong> &mdash; mirar | <strong>Clique</strong> &mdash; atirar</li>
            <li><kbd>1</kbd>-<kbd>7</kbd> ou <strong>scroll</strong> &mdash; trocar de arma</li>
            <li><kbd>P</kbd> &mdash; pausar | <kbd>M</kbd> &mdash; som on/off</li>
        </ul>
    </div>
    <div class="card">
        <h3 class="tx-blue">&#9889; Progressao</h3>
        <ul class="list-clean">
            <li>Derrote anomalias para ganhar <strong class="tx-blue">XP</strong> e <strong class="tx-yellow">moedas</strong></li>
            <li>Ao subir de nivel, escolha 1 entre 3 <strong>habilidades</strong></li>
            <li>Sua pontuacao e salva e disputa o <strong>ranking global</strong></li>
            <li>Suba o nivel da conta para liberar <strong>novos mapas</strong></li>
        </ul>
    </div>
    <div class="card">
        <h3 class="tx-red">&#9760; Ameacas</h3>
        <ul class="list-clean">
            <li><span class="tx-red">Virus</span>, <span class="tx-orange">Malware</span>, <span class="tx-purple">Trojan</span>, <span class="tx-yellow">Ransomware</span></li>
            <li><span class="tx-pink">Bugs criticos</span> em enxame e <span class="tx-blue">bots</span> em grupo</li>
            <li><span class="tx-red">Firewall Quebrado</span>: o tanque digital</li>
            <li>BOSS: <strong class="tx-red">Kernel Corrompido</strong> aos 3 minutos</li>
        </ul>
    </div>
    <div class="card">
        <h3 class="tx-yellow">&#9888; Garbage Collector</h3>
        <p>
            A zona segura encolhe com o tempo &mdash; o <strong>Garbage Collector</strong> recolhe tudo
            o que ficar fora dela. Permaneca dentro do circulo ou sera desalocado da memoria.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
