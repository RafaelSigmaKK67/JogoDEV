<?php
/**
 * DEV SURVIVOR - Modo Sandbox
 * Jogo livre: qualquer mapa/dificuldade/personagem, toggles de boss,
 * vida infinita, moedas infinitas e arma de teste. Nao afeta o ranking.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = db();
$maps        = $pdo->query('SELECT id, name, slug FROM maps ORDER BY id')->fetchAll();
$diffs       = $pdo->query('SELECT name, slug FROM difficulty_modes ORDER BY order_index')->fetchAll();
$characters  = $pdo->query('SELECT name, slug FROM characters ORDER BY id')->fetchAll();
$weapons     = $pdo->query('SELECT name, slug FROM weapons ORDER BY id')->fetchAll();

$pageTitle = 'Sandbox';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Modo Sandbox<span class="cursor">_</span></h1>
<p class="page-sub">// teste tudo livremente. As partidas Sandbox sao salvas separadamente e <strong>nao contam no ranking oficial</strong>.</p>

<form method="get" action="<?= e(BASE_URL) ?>/game/index.php" class="sandbox-form">
    <input type="hidden" name="sandbox" value="1">

    <div class="sandbox-grid">
        <label class="field">
            <span class="field-label">Mapa</span>
            <select name="map">
                <?php foreach ($maps as $m): ?>
                    <option value="<?= e($m['slug']) ?>"><?= e($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Dificuldade</span>
            <select name="diff">
                <?php foreach ($diffs as $d): ?>
                    <option value="<?= e($d['slug']) ?>" <?= $d['slug'] === 'normal' ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Personagem (testar qualquer um)</span>
            <select name="char">
                <?php foreach ($characters as $c): ?>
                    <option value="<?= e($c['slug']) ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Arma de teste (inicial)</span>
            <select name="weapon">
                <option value="">(padrao do personagem)</option>
                <?php foreach ($weapons as $w): ?>
                    <option value="<?= e($w['slug']) ?>"><?= e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Quantidade de inimigos</span>
            <select name="enemies">
                <option value="0">Nenhum</option>
                <option value="0.5">Poucos (x0.5)</option>
                <option value="1" selected>Normal (x1)</option>
                <option value="2">Muitos (x2)</option>
                <option value="3">Horda (x3)</option>
                <option value="5">Caos (x5)</option>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Boss</span>
            <select name="boss">
                <option value="1" selected>Ativado</option>
                <option value="0">Desativado</option>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Vida infinita</span>
            <select name="inf_hp">
                <option value="0" selected>Nao</option>
                <option value="1">Sim</option>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Moedas infinitas (na partida)</span>
            <select name="inf_coins">
                <option value="0" selected>Nao</option>
                <option value="1">Sim</option>
            </select>
        </label>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">&#9654; INICIAR SANDBOX</button>
    <p class="locker-hint">// dica: o Sandbox usa as skins e equipamentos do seu Armario, mas permite trocar o personagem aqui.</p>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
