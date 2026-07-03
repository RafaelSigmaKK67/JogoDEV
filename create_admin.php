<?php
/**
 * DEV SURVIVOR - Utilitario de emergencia: (re)cria o usuario admin.
 *
 * Use apenas se o login admin do install.sql nao funcionar.
 * Acesse uma vez em: http://localhost/dev-survivor/create_admin.php
 * e DEPOIS APAGUE ESTE ARQUIVO por seguranca.
 */
require_once __DIR__ . '/config/database.php';

$email    = 'admin@devsurvivor.com';
$password = 'admin123';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare('UPDATE users SET password = ?, is_admin = 1 WHERE id = ?')
        ->execute([$hash, $existing['id']]);
    $userId = (int)$existing['id'];
    $message = 'Senha do admin REDEFINIDA com sucesso.';
} else {
    $pdo->prepare('INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, 1)')
        ->execute(['Admin', $email, $hash]);
    $userId = (int)$pdo->lastInsertId();
    $message = 'Usuario admin CRIADO com sucesso.';
}

// Garante as linhas de stats/ranking
$pdo->prepare('INSERT IGNORE INTO players_stats (user_id) VALUES (?)')->execute([$userId]);
$pdo->prepare('INSERT IGNORE INTO rankings (user_id) VALUES (?)')->execute([$userId]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Admin criado</title></head>
<body style="font-family: monospace; background: #05080d; color: #4df3a3; padding: 50px;">
    <h1>&gt; <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>E-mail: <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p>Senha: <strong><?= htmlspecialchars($password, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p style="color: #ff4d5e;">&#9888; APAGUE o arquivo create_admin.php agora!</p>
    <p><a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pages/login.php" style="color:#39c2ff">Ir para o login</a></p>
</body>
</html>
