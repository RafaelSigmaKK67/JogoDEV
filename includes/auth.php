<?php
/**
 * DEV SURVIVOR - Autenticacao, sessao e helpers de seguranca
 *
 * Incluir este arquivo no topo de TODAS as paginas:
 *   require_once __DIR__ . '/../includes/auth.php';
 */

require_once __DIR__ . '/../config/database.php';

// Inicia a sessao com cookies mais seguros
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,   // JS nao acessa o cookie de sessao
        'samesite' => 'Lax',  // mitiga CSRF em navegacao cross-site
    ]);
    session_start();
}

/* ============================================================
 * HELPERS GERAIS
 * ============================================================ */

/** Escapa saida HTML (protecao contra XSS). Use em TODO echo de dado dinamico. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Redireciona para um caminho relativo ao projeto e encerra o script. */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/* ============================================================
 * MENSAGENS FLASH (exibidas uma unica vez apos redirect)
 * ============================================================ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ============================================================
 * CSRF (token unico por sessao, validado em todo POST)
 * ============================================================ */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Gera o input hidden para colocar dentro dos formularios. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Valida o token recebido. Retorna true se for valido. */
function csrfValidate(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Atalho: aborta a requisicao se o token POST for invalido. */
function csrfRequire(): void
{
    if (!csrfValidate($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Token CSRF invalido. Recarregue a pagina e tente novamente.');
    }
}

/* ============================================================
 * AUTENTICACAO
 * ============================================================ */

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && !empty($_SESSION['is_admin']);
}

/** Bloqueia paginas privadas: redireciona para o login se nao autenticado. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        flash('error', 'Voce precisa estar logado para acessar essa pagina.');
        redirect('/pages/login.php');
    }
}

/** Bloqueia o painel admin para usuarios comuns. */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        flash('error', 'Acesso restrito a administradores.');
        redirect('/pages/dashboard.php');
    }
}

/** Retorna os dados do usuario logado + estatisticas (com cache por requisicao). */
function currentUser(): ?array
{
    static $user = null;
    static $loaded = false;

    if (!$loaded) {
        $loaded = true;
        if (isLoggedIn()) {
            $stmt = db()->prepare(
                'SELECT u.id, u.name, u.email, u.is_admin, u.created_at,
                        s.level, s.xp, s.coins, s.total_score, s.total_kills,
                        s.total_matches, s.total_survival_time,
                        s.best_score, s.best_kills, s.best_survival_time
                 FROM users u
                 LEFT JOIN players_stats s ON s.user_id = u.id
                 WHERE u.id = ?'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }

    return $user;
}

/* ============================================================
 * NIVEL DA CONTA (formula compartilhada entre paginas e save_match)
 * Nivel L exige 100 * (L-1)^2 de XP total.
 * ============================================================ */

function accountLevel(int $xp): int
{
    return (int)floor(sqrt($xp / 100)) + 1;
}

/** XP total necessario para atingir um determinado nivel. */
function xpForLevel(int $level): int
{
    return 100 * ($level - 1) * ($level - 1);
}

/* ============================================================
 * LOG DE ACOES ADMINISTRATIVAS
 * ============================================================ */

function logAdmin(string $action, string $details = ''): void
{
    $stmt = db()->prepare('INSERT INTO admin_logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$_SESSION['user_id'] ?? null, $action, $details]);
}

/* ============================================================
 * FORMATADORES
 * ============================================================ */

/** Formata segundos como "3m 27s". */
function formatTime(int $seconds): string
{
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
}

// Helpers de economia/jogo (loja, personagens, roletas, medalhas, recompensas)
require_once __DIR__ . '/helpers.php';
