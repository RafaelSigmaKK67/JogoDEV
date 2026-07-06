<?php
/**
 * DEV SURVIVOR - Configuracao do banco de dados
 *
 * Conexao via PDO com prepared statements (protecao contra SQL injection).
 * Ajuste as constantes abaixo se o seu MySQL usar usuario/senha diferentes.
 * No XAMPP padrao: usuario "root" sem senha.
 */

// Le variaveis de ambiente quando existirem (deploy em nuvem: Railway/Render);
// caso contrario usa os padroes do XAMPP local (root sem senha).
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'dev_survivor');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * BASE_URL e detectada automaticamente a partir da pasta do projeto
 * dentro do htdocs. Se a deteccao falhar, define manualmente, ex.:
 *   define('BASE_URL', '/dev-survivor');
 */
if (!defined('BASE_URL')) {
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRoot     = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';

    if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
        define('BASE_URL', rtrim(substr($projectRoot, strlen($docRoot)), '/'));
    } else {
        define('BASE_URL', '/dev-survivor'); // fallback manual
    }
}

/**
 * Retorna a conexao PDO (singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // erros viram excecoes
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statements nativos
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(
                '<div style="font-family:monospace;background:#0a0f14;color:#ff4d5e;padding:40px;min-height:100vh">' .
                '<h1>[ERRO] Falha na conexao com o banco de dados</h1>' .
                '<p style="color:#9fb3c8">Verifique se o MySQL esta rodando no XAMPP e se o banco ' .
                '<strong style="color:#4df3a3">dev_survivor</strong> foi importado no phpMyAdmin (arquivo install.sql).</p>' .
                '<p style="color:#5c6b7a">Detalhe: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>' .
                '</div>'
            );
        }
    }

    return $pdo;
}
