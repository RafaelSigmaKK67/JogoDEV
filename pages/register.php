<?php
/**
 * DEV SURVIVOR - Cadastro de jogador
 */
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Validacao basica de formulario
    if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
        $errors[] = 'O nome deve ter entre 3 e 60 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail valido.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
    }
    if ($password !== $confirm) {
        $errors[] = 'As senhas nao conferem.';
    }

    // E-mail ja cadastrado?
    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Este e-mail ja esta cadastrado. Faca login.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Cria o usuario com senha protegida por password_hash()
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();

            // Cria progresso, ranking, personagem inicial e loadout padrao
            ensurePlayerSetup($userId);

            $pdo->commit();

            // Loga automaticamente apos o cadastro
            session_regenerate_id(true);
            $_SESSION['user_id']  = $userId;
            $_SESSION['is_admin'] = 0;

            flash('success', "Bem-vindo(a) ao sistema, {$name}! Sua jornada comeca agora.");
            redirect('/pages/dashboard.php');
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Erro ao criar a conta. Tente novamente.';
        }
    }
}

$pageTitle = 'Criar Conta';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-box">
    <h1 class="auth-title">&gt; Criar conta<span class="cursor">_</span></h1>
    <p class="auth-sub">// registre-se para entrar no sistema</p>

    <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="" novalidate>
        <?= csrfField() ?>

        <label class="field">
            <span class="field-label">Nome de dev</span>
            <input type="text" name="name" value="<?= e($name) ?>" required minlength="3" maxlength="60"
                   placeholder="ex: ana_dev">
        </label>

        <label class="field">
            <span class="field-label">E-mail</span>
            <input type="email" name="email" value="<?= e($email) ?>" required maxlength="120"
                   placeholder="voce@exemplo.com">
        </label>

        <label class="field">
            <span class="field-label">Senha (minimo 6 caracteres)</span>
            <input type="password" name="password" required minlength="6" placeholder="********">
        </label>

        <label class="field">
            <span class="field-label">Confirmar senha</span>
            <input type="password" name="password_confirm" required minlength="6" placeholder="********">
        </label>

        <button type="submit" class="btn btn-primary btn-block">CRIAR CONTA</button>
    </form>

    <p class="auth-alt">
        Ja tem conta? <a href="<?= e(BASE_URL) ?>/pages/login.php">Fazer login</a>
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
