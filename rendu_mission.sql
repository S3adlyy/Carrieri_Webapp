-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 05 avr. 2026 à 17:33
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
-- Structure de la table `rendu_mission`
--

CREATE TABLE `rendu_mission` (
  `id` int(11) NOT NULL,
  `code_solution` text DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `date_rendu` datetime NOT NULL,
  `score` double DEFAULT NULL,
  `resultat` varchar(255) DEFAULT NULL,
  `mission_id` int(11) NOT NULL,
  `candidat_id` int(11) NOT NULL,
  `feedback` text DEFAULT NULL,
  `langue` varchar(50) DEFAULT 'python	',
  `statut` varchar(50) DEFAULT 'en_attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `rendu_mission`
--

INSERT INTO `rendu_mission` (`id`, `code_solution`, `fichier`, `date_rendu`, `score`, `resultat`, `mission_id`, `candidat_id`, `feedback`, `langue`, `statut`) VALUES
(1, 'def sum(a,b):\n	return a+b\na=int(input())\nb=int(input())\nresult=sum(a,b)\nprint(result)', NULL, '2026-02-27 00:00:00', 60, 'ACCEPTED', 2, 1, '❌ Code rejected. Score: 60% below minimum requirement of 65%', 'python', 'en_attente'),
(2, 'print(\"hello world\")', NULL, '2026-02-27 00:00:00', 5, 'REJECTED', 2, 1, '❌ Code rejected. Score: 5% below minimum requirement of 65%', 'python', 'en_attente'),
(3, 'print(\"hello world\")', NULL, '2026-02-28 00:00:00', 5, 'REJECTED', 2, 11, '❌ Code rejected. Score: 5% below minimum requirement of 65%', 'python', 'en_attente'),
(5, 'print(\"hello world\")', NULL, '2026-03-03 00:00:00', 5, 'REJECTED', 2, 6, '❌ Code rejected. Score: 5% below minimum requirement of 65%', 'python', 'en_attente'),
(6, 'print(\"helo world\")', NULL, '2026-04-04 09:19:50', 0, '<div class=\"test-results\"><div class=\"test-summary\"><h4>Résultats des tests : 0/3 passés</h4><div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width: 0%\"></div></div></div><div class=\"test-details\"><div class=\"test-item test-error\">\r\n           ', 13, 15, 'try next time ', 'python', 'refuse'),
(7, '# Solution Python pour Two Sum\r\n# Essayez votre code ici !\r\n\r\ndef twoSum(nums, target):\r\n    \"\"\"\r\n    :type nums: List[int]\r\n    :type target: int\r\n    :rtype: List[int]\r\n    \"\"\"\r\n    seen = {}\r\n    for i, num in enumerate(nums):\r\n        complement = target - num\r\n        if complement in seen:\r\n            return [seen[complement], i]\r\n        seen[num] = i\r\n    return []\r\n\r\n# Testez votre code avec print\r\nprint(\"Bienvenue dans l\'éditeur de code!\")\r\nprint(\"Exemple d\'utilisation:\")\r\nresult = twoSum([2, 7, 11, 15], 9)\r\nprint(f\"twoSum([2, 7, 11, 15], 9) = {result}\")', NULL, '2026-04-04 09:58:10', 100, '<div class=\"test-results\"><div class=\"test-summary\"><h4>Résultats des tests : 3/3 passés</h4><div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width: 100%\"></div></div></div><div class=\"test-details\"><div class=\"test-item test-success\">\r\n       ', 13, 15, 'Bravooo ', 'python', 'accepte'),
(8, '# Solution Python pour Two Sum\r\n# Essayez votre code ici !\r\n\r\ndef twoSum(nums, target):\r\n    \"\"\"\r\n    :type nums: List[int]\r\n    :type target: int\r\n    :rtype: List[int]\r\n    \"\"\"\r\n    seen = {}\r\n    for i, num in enumerate(nums):\r\n        complement = target - num\r\n        if complement in seen:\r\n            return [seen[complement], i]\r\n        seen[num] = i\r\n    return []\r\n\r\n# Testez votre code avec print\r\nprint(\"Bienvenue dans l\'éditeur de code!\")\r\nprint(\"Exemple d\'utilisation:\")\r\nresult = twoSum([2, 7, 11, 15], 9)\r\nprint(f\"twoSum([2, 7, 11, 15], 9) = {result}\")', NULL, '2026-04-04 11:37:39', 100, '<div class=\"test-results\"><div class=\"test-summary\"><h4>Résultats des tests : 3/3 passés</h4><div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width: 100%\"></div></div></div><div class=\"test-details\"><div class=\"test-item test-success\">\r\n       ', 13, 15, 'Désolé, votre solution n\'a pas été retenue.', 'python', 'refuse'),
(9, 'print(\"hello world\")', NULL, '2026-04-04 23:37:22', 0, '<div class=\"test-results\"><div class=\"test-summary\"><h4>Résultats des tests : 0/3 passés</h4><div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width: 0%\"></div></div></div><div class=\"test-details\"><div class=\"test-item test-error\">\r\n           ', 17, 15, 'Félicitations ! Votre solution a été acceptée.', 'python', 'accepte'),
(10, '# Solution Python pour Two Sum\r\n# Essayez votre code ici !\r\n\r\ndef twoSum(nums, target):\r\n    \"\"\"\r\n    :type nums: List[int]\r\n    :type target: int\r\n    :rtype: List[int]\r\n    \"\"\"\r\n    seen = {}\r\n    for i, num in enumerate(nums):\r\n        complement = target - num\r\n        if complement in seen:\r\n            return [seen[complement], i]\r\n        seen[num] = i\r\n    return []\r\n\r\n# Testez votre code avec print\r\nprint(\"Bienvenue dans l\'éditeur de code!\")\r\nprint(\"Exemple d\'utilisation:\")\r\nresult = twoSum([2, 7, 11, 15], 9)\r\nprint(f\"twoSum([2, 7, 11, 15], 9) = {result}\")', NULL, '2026-04-05 14:37:40', 100, '<div class=\"test-results\"><div class=\"test-summary\"><h4>Résultats des tests : 3/3 passés</h4><div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width: 100%\"></div></div></div><div class=\"test-details\"><div class=\"test-item test-success\">\r\n       ', 17, 15, 'hhihihihihhi', 'python', 'accepte'),
(11, '# Solution Python pour Two Sum\r\n# Essayez votre code ici !\r\n\r\ndef twoSum(nums, target):\r\n    \"\"\"\r\n    :type nums: List[int]\r\n    :type target: int\r\n    :rtype: List[int]\r\n    \"\"\"\r\n    seen = {}\r\n    for i, num in enumerate(nums):\r\n        complement = target - num\r\n        if complement in seen:\r\n            return [seen[complement], i]\r\n        seen[num] = i\r\n    return []\r\n\r\n# Testez votre code avec print\r\nprint(\"Bienvenue dans l\'éditeur de code!\")\r\nprint(\"Exemple d\'utilisation:\")\r\nresult = twoSum([2, 7, 11, 15], 9)\r\nprint(f\"twoSum([2, 7, 11, 15], 9) = {result}\")', NULL, '2026-04-05 15:28:19', 100, '<div class=\"test-results\"><div class=\"test-summary\"><h4>Résultats des tests : 3/3 passés</h4><div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width: 100%\"></div></div></div><div class=\"test-details\"><div class=\"test-item test-success\">\r\n       ', 17, 15, 'not accepted', 'python', 'refuse');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `rendu_mission`
--
ALTER TABLE `rendu_mission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_84BAC7D3BE6CAE90` (`mission_id`),
  ADD KEY `IDX_84BAC7D38D0EB82` (`candidat_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `rendu_mission`
--
ALTER TABLE `rendu_mission`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `rendu_mission`
--
ALTER TABLE `rendu_mission`
  ADD CONSTRAINT `FK_84BAC7D38D0EB82` FOREIGN KEY (`candidat_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `FK_84BAC7D3BE6CAE90` FOREIGN KEY (`mission_id`) REFERENCES `mission` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
