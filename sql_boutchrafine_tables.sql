-- ========================================
-- BOUTCHRAFINE REPORTS - TABLES SQL
-- Base de données: rapport
-- ========================================

USE rapport;

-- ----------------------------------------
-- 1. TABLE KILOMETRAGE
-- ----------------------------------------
CREATE TABLE IF NOT EXISTS `boutchrafine_kilometrage` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicule` VARCHAR(100) NOT NULL,
  `debut` DATETIME NOT NULL,
  `fin` DATETIME NOT NULL,
  `duree` VARCHAR(50) DEFAULT NULL,
  `kilometrage` FLOAT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_vehicule` (`vehicule`),
  INDEX `idx_debut` (`debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------
-- 2. TABLE INFRACTIONS
-- ----------------------------------------
CREATE TABLE IF NOT EXISTS `boutchrafine_infractions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicule` VARCHAR(100) NOT NULL,
  `debut` DATETIME NOT NULL,
  `fin` DATETIME NOT NULL,
  `emplacement` TEXT DEFAULT NULL,
  `infraction` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_vehicule` (`vehicule`),
  INDEX `idx_debut` (`debut`),
  INDEX `idx_infraction` (`infraction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------
-- 3. TABLE EVALUATION (Eco-conduite)
-- ----------------------------------------
CREATE TABLE IF NOT EXISTS `boutchrafine_evaluation` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicule` VARCHAR(100) NOT NULL,
  `debut` DATETIME NOT NULL,
  `fin` DATETIME NOT NULL,
  `emplacement` TEXT DEFAULT NULL,
  `penalites` FLOAT DEFAULT 0,
  `evaluation` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_vehicule` (`vehicule`),
  INDEX `idx_debut` (`debut`),
  INDEX `idx_evaluation` (`evaluation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- AFFICHER MESSAGE DE SUCCÈS
-- ========================================
SELECT '✅ Tables BOUTCHRAFINE créées avec succès!' AS message;
SELECT COUNT(*) AS nb_tables FROM information_schema.tables WHERE table_schema='rapport' AND table_name LIKE 'boutchrafine_%';
