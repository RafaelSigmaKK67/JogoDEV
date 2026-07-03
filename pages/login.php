<?php
/**
 * DEV SURVIVOR - Login
 * Autenticacao com password_verify + sessao.
 */
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequire();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Preencha e-mail e senha.';
    } else {
        $stmt = db()->prepare('SELECT id, name, password, is_admin FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // password_verify compara a senha digitada com o hash salvo
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // previne session fixation
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['is_admin'] = (int)$user['is_admin'];

            flash('success', "Login efetuado. Bem-vindo(a) de volta, {$user['name']}!");
            redirect('/pages/dashboard.php');
        }

        $errors[] = 'E-mail ou senha incorretos.';
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-box">
    <h1 class="auth-title">&gt; Login<span class="cursor">_</span></h1>
    <p class="auth-sub">// autentique-se para acessar o sistema</p>

    <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="" novalidate>
        <?= csrfField() ?>

        <label class="field">
            <span class="field-label">E-mail</span>
            <input type="email" name="email" value="<?= e($email) ?>" required maxlength="120"
                   placeholder="voce@exemplo.com" autofocus>
        </label>

        <label class="field">
            <span class="field-label">Senha</span>
            <input type="password" name="password" required placeholder="********">
        </label>

        <button type="submit" class="btn btn-primary btn-block">ENTRAR</button>
    </form>

    <p class="auth-alt">
        Nao tem conta? <a href="<?= e(BASE_URL) ?>/pages/register.php">Criar conta gratis</a>
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
