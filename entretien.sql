-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 05 avr. 2026 à 17:34
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `carrieri`
--

-- --------------------------------------------------------

--
-- Structure de la table `entretien`
--

CREATE TABLE `entretien` (
  `id` int(11) NOT NULL,
  `date_entretien` datetime NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT 'planifie',
  `postulation_id` int(11) DEFAULT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `candidat_id` int(11) DEFAULT NULL,
  `rendu_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `entretien`
--

INSERT INTO `entretien` (`id`, `date_entretien`, `type`, `status`, `postulation_id`, `lien`, `notes`, `candidat_id`, `rendu_id`) VALUES
(5, '2026-03-04 09:00:00', 'Entretien Technique', 'SCHEDULED', 34, NULL, NULL, NULL, NULL),
(6, '2026-03-04 09:00:00', 'Entretien Technique', 'SCHEDULED', 34, NULL, NULL, NULL, NULL),
(7, '2026-03-04 09:00:00', 'Entretien Technique', 'SCHEDULED', 34, NULL, NULL, NULL, NULL),
(15, '2026-04-07 05:34:00', 'Technique', 'planifie', NULL, NULL, NULL, 15, 7),
(17, '2026-04-16 17:29:00', 'Technique', 'planifie', NULL, NULL, NULL, 15, 10);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `entretien`
--
ALTER TABLE `entretien`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_2B58D6DAD749FDF1` (`postulation_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `entretien`
--
ALTER TABLE `entretien`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `entretien`
--
ALTER TABLE `entretien`
  ADD CONSTRAINT `fk_entretien_postulation` FOREIGN KEY (`postulation_id`) REFERENCES `postulation` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
