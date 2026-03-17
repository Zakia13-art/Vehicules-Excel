<?php
/**
 * CONFIG_WIALON.PHP - Paramètres pour l'import Wialon
 * Version Windows XAMPP
 * Chemin: C:\xampp\htdocs\vehicules\config_wialon.php
 */

// ===========================
// 🔐 Paramètres API Wialon
// ===========================
define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');
define('WIALON_API_URL', 'https://hst-api.wialon.com/wialon/ajax.html');

// ===========================
// 📊 Paramètres d'import
// ===========================
define('IMPORT_DAYS', 30);              // Nombre de jours à importer
define('IMPORT_HOUR', 5);               // Heure d'import (5 du matin)
define('IMPORT_MINUTE', 0);             // Minute (0 = sur l'heure)
define('CRON_TIMEOUT', 1800);           // Temps autorisé (30 minutes)

// ===========================
// 🗄️ Paramètres base de données
// ===========================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');                  // Généralement vide dans XAMPP
define('DB_NAME', 'rapport');

// ===========================
// 📝 Paramètres journalisation
// ===========================
$base_path = dirname(__FILE__);
define('LOG_DIR', $base_path . DIRECTORY_SEPARATOR . 'logs');
define('LOG_MAIN', LOG_DIR . DIRECTORY_SEPARATOR . 'auto_import.log');
define('LOG_CRON', LOG_DIR . DIRECTORY_SEPARATOR . 'cron.log');
define('LOG_ERRORS', LOG_DIR . DIRECTORY_SEPARATOR . 'errors.log');
define('LOG_SIZE_LIMIT', 5242880);      // 5 MB

// ===========================
// 🏢 Groupes Wialon et transporteurs
// ===========================
$GLOBALS['GROUP_MAPPING'] = array(
    'BOUTCHRAFINE' => 19022033,
    'SOMATRIN' => 19596491,
    'MARATRANS' => 19631505,
    'G.T.C' => 19590737,
    'DOUKALI' => 19585587,
    'COTRAMAB' => 19585601,
    'CORYAD' => 19585581,
    'CONSMETA' => 19629962,
    'CHOUROUK' => 19630023,
    'CARRE' => 19643391,
    'STB' => 19585942,
    'FASTTRANS' => 19635796
);

$GLOBALS['TRANSPORTEUR_MAPPING'] = array(
    'BOUTCHRAFINE' => 1,
    'SOMATRIN' => 2,
    'MARATRANS' => 3,
    'G.T.C' => 4,
    'DOUKALI' => 5,
    'COTRAMAB' => 6,
    'CORYAD' => 7,
    'CONSMETA' => 8,
    'CHOUROUK' => 9,
    'CARRE' => 10,
    'STB' => 11,
    'FASTTRANS' => 12
);

// ===========================
// 👤 Chauffeur par défaut
// ===========================
define('DEFAULT_DRIVER', 'CD000');

// ===========================
// 🌍 Paramètres géographiques
// ===========================
define('TIMEZONE', 'Africa/Casablanca');
date_default_timezone_set(TIMEZONE);

// ===========================
// ✨ Paramètres de production
// ===========================
define('DEBUG_MODE', false);            // false en production
define('VERBOSE_LOGGING', true);        // Journalisation détaillée
define('AUTO_CLEANUP', true);           // Suppression automatique des anciennes données
define('PREVENT_DUPLICATES', true);     // Prévention des trajets en doublon

// ===========================
// 🎯 Messages du système
// ===========================
$GLOBALS['MESSAGES'] = array(
    'import_start' => 'Démarrage de l\'import automatique',
    'import_success' => 'Import terminé avec succès',
    'import_failed' => 'Échec de l\'import',
    'db_error' => 'Erreur de base de données',
    'api_error' => 'Erreur de connexion à l\'API Wialon',
    'no_data' => 'Aucune donnée disponible',
    'cleanup_success' => 'Suppression des anciennes données réussie',
);

// ===========================
// Création du dossier logs s'il n'existe pas
// ===========================
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// ===========================
// Paramètres de performance
// ===========================
define('CURL_TIMEOUT', 60);
define('CURL_RETRY_COUNT', 3);
define('CURL_RETRY_DELAY', 2);

// ===========================
// Fonction d'aide pour accéder aux paramètres
// ===========================
function get_config($key) {
    // Récupération des paramètres de configuration
    $config = array(
        'groups' => $GLOBALS['GROUP_MAPPING'],
        'transporteurs' => $GLOBALS['TRANSPORTEUR_MAPPING'],
        'messages' => $GLOBALS['MESSAGES'],
        'log_dir' => LOG_DIR,
        'import_days' => IMPORT_DAYS,
        'db_host' => DB_HOST,
        'db_user' => DB_USER,
        'db_name' => DB_NAME,
    );
    
    return $config[$key] ?? null;
}

?>