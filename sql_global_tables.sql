-- ========================================
-- GLOBAL TABLES - TOUS LES GROUPS
-- Base de données: rapport
-- ========================================

USE rapport;

-- ----------------------------------------
-- 1. TABLE GLOBAL KILOMETRAGE
-- ----------------------------------------
CREATE TABLE IF NOT EXISTS `global_kilometrage` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transporteur_id` INT NOT NULL,
  `transporteur_nom` VARCHAR(100) NOT NULL,
  `vehicule` VARCHAR(100) NOT NULL,
  `debut` DATETIME NOT NULL,
  `fin` DATETIME NOT NULL,
  `duree` VARCHAR(50) DEFAULT NULL,
  `kilometrage` FLOAT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_transporteur` (`transporteur_id`),
  INDEX `idx_vehicule` (`vehicule`),
  INDEX `idx_debut` (`debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------
-- 2. TABLE GLOBAL INFRACTIONS
-- ----------------------------------------
CREATE TABLE IF NOT EXISTS `global_infractions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transporteur_id` INT NOT NULL,
  `transporteur_nom` VARCHAR(100) NOT NULL,
  `vehicule` VARCHAR(100) NOT NULL,
  `debut` DATETIME NOT NULL,
  `fin` DATETIME NOT NULL,
  `emplacement` TEXT DEFAULT NULL,
  `infraction` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_transporteur` (`transporteur_id`),
  INDEX `idx_vehicule` (`vehicule`),
  INDEX `idx_debut` (`debut`),
  INDEX `idx_infraction` (`infraction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------
-- 3. TABLE GLOBAL EVALUATION (Eco-conduite)
-- ----------------------------------------
CREATE TABLE IF NOT EXISTS `global_evaluation` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transporteur_id` INT NOT NULL,
  `transporteur_nom` VARCHAR(100) NOT NULL,
  `vehicule` VARCHAR(100) NOT NULL,
  `debut` DATETIME NOT NULL,
  `fin` DATETIME NOT NULL,
  `emplacement` TEXT DEFAULT NULL,
  `penalites` FLOAT DEFAULT 0,
  `evaluation` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_transporteur` (`transporteur_id`),
  INDEX `idx_vehicule` (`vehicule`),
  INDEX `idx_debut` (`debut`),
  INDEX `idx_evaluation` (`evaluation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- MESSAGE DE SUCCÈS
-- ========================================
SELECT '✅ Tables GLOBAL créées avec succès!' AS message;
SELECT COUNT(*) AS nb_tables FROM information_schema.tables WHERE table_schema='rapport' AND table_name LIKE 'global_%';
