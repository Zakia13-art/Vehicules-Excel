<?php
// ============================================
// config.php — Connexion à la base de données
// ============================================
// ⚠️  Modifie ces valeurs selon ton environnement

define('DB_HOST',    'localhost');
define('DB_NAME',    'rapport');
define('DB_USER',    'root');       // ton utilisateur MySQL
define('DB_PASS',    '');           // ton mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');

// Adresse email destinataire du rapport
define('MAIL_TO',   'zakia.controlflot@gmail.com');
define('MAIL_FROM', 'noreply@flotte-transport.com');
define('MAIL_NAME', 'Flotte Transport – Rapport Auto');

// ============================================
// Connexion PDO (utilisée par tous les scripts)
// ============================================
function getDB(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}