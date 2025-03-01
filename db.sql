-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 01, 2025 at 12:16 PM
-- Server version: 8.0.37-cll-lve
-- PHP Version: 8.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `blfusngm_Msdtesttesttestbot_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `buttons`
--

CREATE TABLE `buttons` (
  `id` int NOT NULL,
  `button_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_position` int NOT NULL,
  `col_position` int NOT NULL,
  `is_var` tinyint(1) DEFAULT '0',
  `is_intro` tinyint(1) DEFAULT '0',
  `prev_button` int DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buttons`
--

INSERT INTO `buttons` (`id`, `button_name`, `row_position`, `col_position`, `is_var`, `is_intro`, `prev_button`, `content`) VALUES
(1, 'این دکمه اینترو است', 0, 0, 0, 1, 0, 'photo|AgACAgQAAxkBAAItWWe7hWVc8LvJObCf2geJFkeqoNO9AALutTEb452MU0Cb13q0AAGmDwEAAwIAA3gAAzYE|🙋‍ شما توی منوی اصلی ربات هستین.\n لطفاً گزینه مورد نظرتون رو انتخاب کنید! ❤'),
(67, 'دکمه تست ۱', 1, 1, 0, 0, NULL, '[[\"audio\",\"CQACAgQAAxkBAAIwaWe7pNof5w7KcWJ2ylSCMNfyrsG_AAIyBwACJuW1U25LPAEJwfquNgQ\",\"\\u06cc\\u06a9 \\u062f\\u0642\\u06cc\\u0642\\u0647 \\u0648\\u06cc\\u0633 \\u0628\\u0631\\u0646\\u0627\\u0645\\u0647 \\u063a\\u0630\\u0627\\u06cc\\u06cc \\u0631\\u0627 \\u06af\\u0648\\u0634 \\u06a9\\u0646 \\ud83d\\ude0d\\n\\n\\u0645\\u0637\\u0627\\u0628\\u0642 \\u0634\\u0631\\u0627\\u06cc\\u0637 \\u0632\\u0646\\u062f\\u06af\\u06cc ( \\u0628\\u0631\\u0646\\u062c\\u06cc \\u0627\\u06cc\\u0631\\u0627\\u0646\\u06cc \\/ \\u0641\\u0633\\u062a \\u0641\\u0648\\u062f \\/ \\u0648\\u06af\\u0627\\u0646 \\/ \\u06af\\u06cc\\u0627\\u0647\\u062e\\u0648\\u0627\\u0631 \\u0648 ...)\\n      \\n\\u0648 \\u0642\\u0644\\u0642 \\u0628\\u062f\\u0646\\u062a\\ud83d\\udc48 ( \\u0633\\u0631\\u062f\\u06cc \\u2744\\ufe0f\\u06af\\u0631\\u0645\\u06cc \\ud83e\\udd75 \\u0627\\u0633\\u06cc\\u062f\\u06cc \\/ \\u0642\\u0644\\u06cc\\u0627\\u06cc\\u06cc \\u063a\\u0630\\u0627\\u0647\\u0627 \\u06a9\\u0647 \\u0628\\u0647 \\u0628\\u062f\\u0646\\u062a \\u0628\\u0633\\u0627\\u0632\\u0647 )  \\n\\n\\u0648 \\u067e\\u0634\\u062a\\u06cc\\u0628\\u0627\\u0646\\u06cc \\u062f\\u0627\\u0626\\u0645\\u06cc (\\u0628\\u0631\\u0627\\u06cc \\u067e\\u0634\\u062a\\u06cc\\u0628\\u0627\\u0646\\u06cc \\u0627\\u062f\\u0639\\u0627\\u06cc\\u06cc \\u0646\\u062f\\u0627\\u0631\\u0645 \\u0686\\u0648\\u0646 \\u0645\\u0639\\u0631\\u0648\\u0641\\u0645 \\u0628\\u0647 \\u06af\\u0631\\u062f\\u0646 \\u06af\\u06cc\\u0631\\u06cc \\u0648 \\u067e\\u0634\\u062a\\u06cc\\u0628\\u0627\\u0646\\u06cc \\u062f\\u0627\\u0626\\u0645\\u06cc\\u0645 \\ud83d\\udc4d\\ud83e\\udd79)\\n\\n\\u0628\\u0627 \\u0647\\u0632\\u06cc\\u0646\\u0647 \\u0646\\u0627\\u0642\\u0627\\u0628\\u0644 \\u06cc\\u06a9 \\u062a\\u0648\\u0645\\u0646 \\u0627\\u0631\\u0627\\u0626\\u0647 \\u0645\\u06cc\\u0634\\u0648\\u062f\\n\\n\\u0645\\u0639\\u0645\\u0648\\u0644\\u0627 \\u0628\\u0686\\u0647 \\u0647\\u0627 \\u0645\\u0627\\u0647\\u0627\\u0646\\u0647 \\u0628\\u0631\\u0646\\u0627\\u0645\\u0634\\u0648\\u0646\\u0648 \\u0639\\u0648\\u0636 \\u0645\\u06cc\\u06a9\\u0646\\u0646 \\n\\n\\u0628\\u0627 \\u0627\\u06cc\\u0646\\u06a9\\u0647 \\u0645\\u0646 \\u0633\\u0639\\u06cc \\u0645\\u06cc\\u06a9\\u0646\\u0645 \\u062a\\u0646\\u0648\\u0639 \\u062f\\u0631\\u062d\\u062f\\u06cc \\u0628\\u0627\\u0634\\u0647 \\n\\n\\u06a9 \\u0628\\u06cc\\u0634\\u062a\\u0631 \\u0627\\u0632 \\u06cc\\u06a9\\u0645\\u0627\\u0647 \\u0628\\u062a\\u0648\\u0646\\u06cc\\u062f \\u0627\\u0633\\u062a\\u0641\\u0627\\u062f\\u0647 \\u06a9\\u0646\\u06cc\\u062f\\n\\n\\u062f\\u0631 \\u062a\\u0639\\u0648\\u06cc\\u0636 \\u0628\\u0631\\u0646\\u0627\\u0645\\u0647 \\u0647\\u0627 \\u0628\\u0639\\u062f \\u06cc\\u06a9\\u0645\\u0627\\u0647 \\u0647\\u06cc\\u0686 \\u0627\\u062c\\u0628\\u0627\\u0631\\u06cc \\u0646\\u06cc\\u0633\\u062a \\ud83d\\ude03\\n      \\n\\u062c\\u0647\\u062a \\u062f\\u0631\\u06cc\\u0627\\u0641\\u062a \\u0628\\u0631\\u0646\\u0627\\u0645\\u0647 \\u063a\\u0630\\u0627\\u06cc\\u06cc \\u0627\\u0632 \\u0645\\u0646\\u0648\\u06cc \\u0631\\u0628\\u0627\\u062a \\u0644\\u0637\\u0641\\u0627 \\u062f\\u0631\\u06cc\\u0627\\u0641\\u062a \\u0628\\u0631\\u0646\\u0627\\u0645\\u0647 \\u063a\\u0630\\u0627\\u06cc\\u06cc \\u0631\\u0627 \\u0627\\u0646\\u062a\\u062e\\u0627\\u0628 \\u06a9\\u0646\\u06cc\\u062f \\ud83e\\udd79\\ud83d\\ude4f\"]]'),
(68, 'دکمه تست ۲', 1, 2, 0, 0, NULL, '[[\"photo\",\"AgACAgQAAxkBAAIwaGe7pNqxFAv5Lxft1NZvNiacCwifAAJjtjEb67K1U2s0V6DgjlkmAQADAgADdwADNgQ\",\"\"]]'),
(69, 'دکمه تست ۳', 2, 1, 0, 0, NULL, '[[\"photo\",\"AgACAgQAAxkBAAI5xGfCyTGq5aDrMDIQ7Fz2Ecx3scT-AAJxyDEbf-ehUaDUgI5NcHVaAQADAgADeAADNgQ\",\"\\ud83c\\udf1f\\n\\u0627\\u0631\\u0632\\u0627\\u0646\\u200c\\u062a\\u0631\\u06cc\\u0646 \\u0641\\u06cc\\u0644\\u062a\\u0631\\u0634\\u06a9\\u0646 \\u0627\\u06cc\\u0631\\u0627\\u0646! \\u2764\\ufe0f\\ud83d\\udd25\\u0627\\u0631\\u0632\\u0627\\u0646 \\u062a\\u0631\\u06cc\\u0646 \\u0648 \\u067e\\u0631 \\u0633\\u0631\\u0639\\u062a \\u062a\\u0631\\u06cc\\u0646 \\u0633\\u0631\\u0648\\u0631 \\u0647\\u0627\\u06cc ssh \\u0648 v2ray \\u0631\\u0627 \\u0627\\u0632 \\u0645\\u0627 \\u062f\\u0631\\u06cc\\u0627\\u0641\\u062a \\u06a9\\u0646\\u06cc\\u062f \\u0633\\u0631\\u0648\\u0631 \\u0647\\u0627\\u2b55\\ufe0f \\u0641\\u0642\\u0637 \\u06f5\\u06f0 \\u0647\\u0632\\u0627\\u0631 \\u062a\\u0648\\u0645\\u0627\\u0646 \\u0645\\u0627\\u0647\\u06cc\\u0627\\u0646\\u0647 \\u2013 \\u0646\\u0627\\u0645\\u062d\\u062f\\u0648\\u062f \\u0648 \\u067e\\u0631\\u0633\\u0631\\u0639\\u062a! \\n\\ud83d\\udd39 \\u0633\\u0631\\u0639\\u062a \\u0628\\u06cc\\u200c\\u0646\\u0638\\u06cc\\u0631 \\u0648 \\u06a9\\u0627\\u0645\\u0644\\u0627\\u064b \\u067e\\u0627\\u06cc\\u062f\\u0627\\u0631 \\u2013 \\u062a\\u062c\\u0631\\u0628\\u0647\\u200c\\u0627\\u06cc \\u0633\\u0631\\u06cc\\u0639 \\u0648 \\u0631\\u0648\\u0627\\u0646! \\ud83d\\udd25\\n\\ud83d\\udd39 \\u0627\\u06cc\\u0646\\u062a\\u0631\\u0646\\u062a \\u0646\\u06cc\\u0645\\u200c\\u0628\\u0647\\u0627 \\u2013 \\u0645\\u0635\\u0631\\u0641 \\u0641\\u0642\\u0637 \\u06cc\\u06a9\\u200c\\u0633\\u0648\\u0645! \\u0628\\u062f\\u0648\\u0646 \\u0647\\u0632\\u06cc\\u0646\\u0647 \\u0627\\u0636\\u0627\\u0641\\u06cc \\ud83d\\ude10\\n\\ud83d\\udd39 \\u0622\\u06cc\\u200c\\u067e\\u06cc \\u062b\\u0627\\u0628\\u062a \\u2013 \\u0645\\u0646\\u0627\\u0633\\u0628 \\u0628\\u0631\\u0627\\u06cc \\u0627\\u06cc\\u0646\\u0633\\u062a\\u0627\\u06af\\u0631\\u0627\\u0645\\u060c \\u062a\\u0631\\u06cc\\u062f \\u0648 \\u06af\\u06cc\\u0645\\u06cc\\u0646\\u06af \\u062d\\u0631\\u0641\\u0647\\u200c\\u0627\\u06cc \\ud83c\\udfae\\n\\ud83d\\udcf6 \\u0633\\u0627\\u0632\\u06af\\u0627\\u0631 \\u0628\\u0627 \\u062a\\u0645\\u0627\\u0645 \\u0627\\u067e\\u0631\\u0627\\u062a\\u0648\\u0631\\u0647\\u0627 \\u0648 \\u0633\\u06cc\\u0633\\u062a\\u0645\\u200c\\u0639\\u0627\\u0645\\u0644\\u200c\\u0647\\u0627 \\ud83c\\udf10\\n\\n\\ud83d\\udcaf \\u067e\\u0634\\u062a\\u06cc\\u0628\\u0627\\u0646\\u06cc \\u06f2\\u06f4 \\u0633\\u0627\\u0639\\u062a\\u0647 \\u0648 \\u0628\\u0627\\u0632\\u06af\\u0634\\u062a \\u0648\\u062c\\u0647 \\u062f\\u0631 \\u0635\\u0648\\u0631\\u062a \\u0646\\u0627\\u0631\\u0636\\u0627\\u06cc\\u062a\\u06cc \\ud83d\\udcaf\\n\\u2757\\ufe0f \\u062d\\u062a\\u06cc \\u0627\\u06af\\u0631 \\u062f\\u0631 \\u0622\\u062e\\u0631\\u06cc\\u0646 \\u0631\\u0648\\u0632 \\u0627\\u0634\\u062a\\u0631\\u0627\\u06a9\\u200c\\u062a\\u0648\\u0646 \\u0647\\u0645 \\u0631\\u0627\\u0636\\u06cc \\u0646\\u0628\\u0648\\u062f\\u06cc\\u062f\\u060c \\u0628\\u0647 \\u0631\\u0627\\u062d\\u062a\\u06cc \\u067e\\u06cc\\u0627\\u0645 \\u0628\\u062f\\u06cc\\u062f \\u0648 \\u0648\\u062c\\u0647\\u200c\\u062a\\u0648\\u0646 \\u0631\\u0648 \\u0628\\u0631\\u0645\\u06cc\\u200c\\u06af\\u0631\\u062f\\u0648\\u0646\\u06cc\\u0645!\\ud83d\\udd25\\u0633\\u0631\\u0648\\u0631 \\u0647\\u0627\\u06cc v2ray \\u0628\\u0627 \\u0642\\u06cc\\u0645\\u062a \\u0648 \\u0633\\u0631\\u0639\\u062a\\u06cc \\u0628\\u0627\\u0648\\u0631\\u0646\\u06a9\\u0631\\u062f\\u0646\\u06cc \\u0647\\u0631 \\u06f7\\u06f0 \\u06af\\u06cc\\u06af \\u0641\\u0642\\u0637 \\u0648 \\u0641\\u0642\\u0637 \\u06f9\\u06f0 \\u0647\\u0632\\u0627\\u0631\\u062a\\u0648\\u0645\\u0646\\ud83d\\udd25\\n\\u0628\\u0647 \\u0647\\u0645\\u0631\\u0627\\u0647\\u00a0 \\u062a\\u0633\\u062a \\u0631\\u0627\\u06cc\\u06af\\u0627\\u0646 \\u0628\\u0631\\u0627\\u06cc \\u0627\\u0648\\u0644\\u06cc\\u0646 \\u0628\\u0627\\u0631 \\u0628\\u0627 \\u0642\\u06cc\\u0645\\u062a \\u0647\\u0627\\u06cc \\u0628\\u0627\\u0648\\u0631\\u0646\\u06a9\\u0631\\u062f\\u0646\\u06cc \\u06a9\\u0647 \\u067e\\u0634\\u0645\\u0627\\u06cc \\u0647\\u0645\\u0647\\u00a0 \\u0628\\u0631\\u06cc\\u0632\\u0647\\ud83d\\ude09\\ud83d\\udd25\\u0628\\u0631\\u0627\\u06cc \\u0627\\u0637\\u0644\\u0627\\u0639 \\u0627\\u0632 \\u062a\\u0639\\u0631\\u0641\\u0647 \\u0647\\u0627 \\u0648 \\u0642\\u06cc\\u0645\\u062a\\u0627\\u06cc \\u0633\\u0631\\u0648\\u06cc\\u0633 \\u0647\\u0627\\u0645\\u0648\\u0646 \\u0645\\u0646\\u062a\\u0638\\u0631 \\u062d\\u0636\\u0648\\u0631 \\u06af\\u0631\\u0645 \\u0634\\u0645\\u0627 \\u0647\\u0633\\u062a\\u06cc\\u0645\\u2764\\ufe0f\\n\\u06a9\\u0627\\u0646\\u0627\\u0644= https:\\/\\/t.me\\/mmartssh_shop \\n\\n\\u0627\\u062f\\u0645\\u06cc\\u0646 \\u067e\\u0634\\u062a\\u06cc\\u0628\\u0627\\u0646\\u06cc = @MRR_SUPPORTT\"]]');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `user_id` bigint NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT '0',
  `last_menu` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `broadcast_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `username`, `first_name`, `last_name`, `is_admin`, `last_menu`, `broadcast_data`) VALUES
(4, 246995361, 'masoud_bngr', 'M', 'B', 1, 'main', NULL),
(5, 6400035835, 'msdbngr', 'Msd', 'Banagar', 0, 'main', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `buttons`
--
ALTER TABLE `buttons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prev_button` (`prev_button`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `buttons`
--
ALTER TABLE `buttons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
