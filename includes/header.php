<?php
/**
 * DEV SURVIVOR - Cabecalho padrao das paginas do site
 * Espera (opcional) a variavel $pageTitle definida antes do include.
 */
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Dev Survivor';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Dev Survivor</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<div class="scanlines"></div>

<header class="topbar">
    <a class="logo" href="<?= e(BASE_URL) ?>/index.php">
        <span class="logo-bracket">&lt;</span>DEV<span class="logo-accent">SURVIVOR</span><span class="logo-bracket">/&gt;</span>
    </a>

    <nav class="nav">
        <?php if (isLoggedIn()): ?>
            <a href="<?= e(BASE_URL) ?>/pages/dashboard.php">Dashboard</a>
            <a href="<?= e(BASE_URL) ?>/pages/maps.php" class="nav-play">&#9654; Jogar</a>
            <a href="<?= e(BASE_URL) ?>/pages/store.php">Loja</a>
            <a href="<?= e(BASE_URL) ?>/pages/locker.php">Armario</a>
            <a href="<?= e(BASE_URL) ?>/pages/characters.php">Personagens</a>
            <a href="<?= e(BASE_URL) ?>/pages/roulette.php">Roleta</a>
            <a href="<?= e(BASE_URL) ?>/pages/medals.php">Medalhas</a>
            <a href="<?= e(BASE_URL) ?>/pages/rewards.php">Recompensas</a>
            <a href="<?= e(BASE_URL) ?>/pages/ranking.php">Ranking</a>
            <a href="<?= e(BASE_URL) ?>/pages/sandbox.php">Sandbox</a>
            <?php if (isAdmin()): ?>
                <a href="<?= e(BASE_URL) ?>/pages/admin.php" class="nav-admin">Admin</a>
            <?php endif; ?>
            <span class="nav-user">
                <span class="nav-user-name"><?= e($user['name'] ?? '') ?></span>
                <span class="nav-user-level">LVL <?= e($user['level'] ?? 1) ?></span>
                <span class="nav-user-coins"><?= e(number_format((int)($user['coins'] ?? 0), 0, ',', '.')) ?> &cent;</span>
            </span>
            <a href="<?= e(BASE_URL) ?>/logout.php" class="nav-logout">Sair</a>
        <?php else: ?>
            <a href="<?= e(BASE_URL) ?>/pages/ranking.php">Ranking</a>
            <a href="<?= e(BASE_URL) ?>/pages/login.php">Login</a>
            <a href="<?= e(BASE_URL) ?>/pages/register.php" class="nav-play">Criar Conta</a>
        <?php endif; ?>
    </nav>
</header>

<?php foreach (getFlashes() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<main class="container">
