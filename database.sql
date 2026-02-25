-- ============================================
-- 1. Création de la base de données
-- ============================================
CREATE DATABASE IF NOT EXISTS flotte_transport
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE flotte_transport;

-- ============================================
-- 2. Création de la table
-- ============================================
CREATE TABLE IF NOT EXISTS vehicules (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(50)  NOT NULL,
    note_conduite    VARCHAR(10)  DEFAULT NULL,   -- NULL = inactif, sinon ex: "72"
    alertes_critiques INT         DEFAULT NULL,
    heures_conduite  VARCHAR(10)  DEFAULT NULL,   -- ex: "0,1"
    kilometrage      INT          DEFAULT NULL,
    alertes_100km    VARCHAR(10)  DEFAULT NULL,   -- ex: "0,12"
    charge_conduite  VARCHAR(20)  DEFAULT NULL,   -- Inactif / Faible / Moyenne / Élevée
    risque_global    VARCHAR(20)  DEFAULT NULL,   -- Inactif / Faible / Critique
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rapport_vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entreprise VARCHAR(150),
    nom_vehicule VARCHAR(150),
    note_conduite DECIMAL(7,2) DEFAULT NULL,
    nb_infractions INT DEFAULT 0,
    kilometrage DECIMAL(14,2) DEFAULT 0,
    date_import TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ============================================
-- 3. Insertion des données
-- ============================================
INSERT INTO vehicules
    (nom, note_conduite, alertes_critiques, heures_conduite, kilometrage, alertes_100km, charge_conduite, risque_global)
VALUES
    ('CADDY 83982',    NULL,  NULL, NULL,  NULL, NULL,   'Inactif', 'Inactif'),
    ('DOKKER 29495',   '72',  0,    '0,1', 1,    '0,0',  'Faible',  'Faible'),
    ('MITSUBISHI 15120','85', 1,    '18',  820,  '0,12', 'Moyenne', 'Faible'),
    ('TOYOTA 69248',   '42',  112,  '42',  2160, '5,2',  'Élevée',  'Critique');