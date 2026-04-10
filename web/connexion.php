<?php
/**
 * Connexion PDO centralisée — QS Rankings
 * Toutes les pages font : require_once __DIR__ . '/connexion.php';
 * ou, depuis un sous-dossier : require_once dirname(__DIR__) . '/connexion.php';
 */

// Force UTF-8 pour PHP et le header HTTP (corrige les accents sur Windows)
mb_internal_encoding('UTF-8');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Supporte Railway (MYSQLHOST, MYSQLDATABASE...) et local (localhost/root)
define('DB_HOST', getenv('MYSQLHOST')     ?: (getenv('DB_HOST') ?: 'localhost'));
define('DB_NAME', getenv('MYSQLDATABASE') ?: (getenv('RAILWAY_DATABASE') ?: (getenv('DB_NAME') ?: 'qs_rankings')));
define('DB_USER', getenv('MYSQLUSER')     ?: (getenv('DB_USER') ?: 'root'));
define('DB_PASS', getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: ''));
define('DB_PORT', getenv('MYSQLPORT')     ?: (getenv('DB_PORT') ?: '3306'));

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
} catch (PDOException $e) {
    // En production : ne jamais exposer le message d'erreur PDO
    error_log('Connexion BDD échouée : ' . $e->getMessage());
    http_response_code(503);
    die('<p style="font-family:sans-serif;color:#c0392b;padding:2rem;">
        Service temporairement indisponible. Veuillez réessayer ultérieurement.
    </p>');
}

/**
 * Retourne l'id_edition de la dernière édition disponible.
 */
function getLastEditionId(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT id_edition FROM EDITION_QS ORDER BY annee DESC LIMIT 1');
    return (int) $stmt->fetchColumn();
}

/**
 * Retourne toutes les éditions sous forme de tableau associatif [id_edition => annee].
 */
function getAllEditions(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id_edition, annee FROM EDITION_QS ORDER BY annee ASC');
    return $stmt->fetchAll();
}
