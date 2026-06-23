-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 23. Jun 2026 um 16:44
-- Server-Version: 10.4.32-MariaDB
-- PHP-Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `eventavoa`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `addresses`
--

CREATE TABLE `addresses` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `address_type` enum('billing','shipping') NOT NULL,
  `strasse` varchar(100) NOT NULL,
  `plz` varchar(10) NOT NULL,
  `ort` varchar(50) NOT NULL,
  `land` varchar(50) NOT NULL DEFAULT 'Österreich',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `address_type`, `strasse`, `plz`, `ort`, `land`, `is_default`, `created_at`) VALUES
(3, 2, 'billing', 'Höchstädtplatz 7', '1200', 'Wien', 'Österreich', 1, '2026-06-21 18:38:27'),
(4, 2, 'shipping', 'Industriestraße 4', '1220', 'Wien', 'Österreich', 1, '2026-06-21 18:38:27'),
(5, 3, 'billing', 'Höchstädtplatz 7', '1100', 'Wien', 'Österreich', 1, '2026-06-21 18:59:16'),
(6, 4, 'billing', 'Winarskistrasse 1', '1200', 'Wien', 'Österreich', 1, '2026-06-21 19:05:44'),
(7, 3, 'shipping', 'Hoechstaedtplatz 7', '1200', 'Wien', 'Österreich', 1, '2026-06-23 14:40:43');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Lichttechnik'),
(2, 'Tontechnik'),
(3, 'Bühnentechnik'),
(4, 'Zubehör');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `zahlung_id` int(10) UNSIGNED DEFAULT NULL,
  `gutschein_id` int(10) UNSIGNED DEFAULT NULL,
  `zwischensumme` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gutscheinbetrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gesamt` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'offen',
  `rechnungsnummer` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `zahlung_id`, `gutschein_id`, `zwischensumme`, `gutscheinbetrag`, `gesamt`, `status`, `rechnungsnummer`, `created_at`) VALUES
(1, 3, 1, NULL, 129.90, 0.00, 129.90, 'offen', 'RE-2026-000001', '2026-06-22 00:50:18'),
(2, 3, 1, NULL, 518.80, 0.00, 518.80, 'offen', 'RE-2026-000002', '2026-06-23 14:40:55'),
(3, 3, 2, 2, 299.80, 100.00, 199.80, 'offen', 'RE-2026-000003', '2026-06-23 14:43:14'),
(4, 3, NULL, 1, 1398.50, 1398.50, 0.00, 'offen', 'RE-2026-000004', '2026-06-23 14:43:29');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `produktname` varchar(150) NOT NULL DEFAULT '',
  `menge` int(11) NOT NULL,
  `einzelpreis` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `produktname`, `menge`, `einzelpreis`) VALUES
(1, 1, 1, 'LED PAR Scheinwerfer', 1, 129.90),
(2, 2, 1, 'LED PAR Scheinwerfer', 1, 129.90),
(3, 2, 6, 'Lichtstativ', 1, 99.90),
(4, 2, 2, 'PA Lautsprecher', 1, 289.00),
(5, 3, 4, 'Funkmikrofon', 1, 199.90),
(6, 3, 6, 'Lichtstativ', 1, 99.90),
(7, 4, 1, 'LED PAR Scheinwerfer', 1, 129.90),
(8, 4, 5, 'Nebelmaschine', 1, 159.90),
(9, 4, 7, 'Mischpult', 1, 599.90),
(10, 4, 2, 'PA Lautsprecher', 1, 289.00),
(11, 4, 3, 'XLR Kabel', 1, 19.90),
(12, 4, 4, 'Funkmikrofon', 1, 199.90);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'EUR',
  `rating` decimal(2,1) NOT NULL DEFAULT 0.0,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `currency`, `rating`, `stock_quantity`, `is_active`, `created_at`) VALUES
(1, 1, 'LED PAR Scheinwerfer', 'LED PAR-Scheinwerfer für Bühnen- und Eventbeleuchtung\r\nLeistung: 90 W\r\nLichtquelle: 18 × RGBW-LEDs (4-in-1)\r\nAbstrahlwinkel: 25°\r\nDMX-Modi: 4, 8 oder 12 Kanäle\r\nBetriebsmodi: DMX, Automatik, Sound-to-Light, Master/Slave\r\nStroboskop-Funktion: 0–20 Hz\r\nStromversorgung: 100–240 V AC\r\nRobustes Metallgehäuse\r\nFarbe: Schwarz\r\nGewicht: 2,8 kg\r\nInklusive Doppelbügel zur Montage an Traversen oder Stativen', 129.90, 'EUR', 4.2, 15, 1, '2026-06-21 18:14:22'),
(2, 2, 'PA Lautsprecher', 'Aktiver 2-Wege-Lautsprecher\r\nLeistung: 350 W RMS / 700 W Peak\r\nFrequenzbereich: 55 Hz – 20 kHz\r\nMax. Schalldruckpegel: 122 dB\r\n12-Zoll-Tieftöner und 1-Zoll-Hochtöner\r\nEingänge: XLR und 6,3-mm-Klinke\r\nAusgang: XLR Line-Out\r\nIntegrierter Verstärker\r\nStromversorgung: 230 V AC\r\nGewicht: 14,5 kg\r\nFarbe: Schwarz\r\nRobustes Kunststoffgehäuse', 289.00, 'EUR', 3.5, 10, 1, '2026-06-21 18:14:22'),
(3, 4, 'XLR Kabel', 'Hochwertiges XLR-Audiokabel\r\nLänge: 10 Meter\r\nXLR-Stecker auf XLR-Stecker\r\nStörungsarme und verlustfreie Signalübertragung\r\nEffektive Abschirmung gegen elektromagnetische Einflüsse\r\nRobuste und langlebige Verarbeitung\r\nFlexibler Kabelmantel für einfache Handhabung\r\nIdeal für Mikrofone, Mischpulte, Audio-Interfaces, Lautsprecher und professionelle Audiotechnik\r\nGeeignet für Studio-, Bühnen- und Veranstaltungsanwendungen', 19.90, 'EUR', 4.5, 50, 1, '2026-06-21 18:14:22'),
(4, 3, 'Funkmikrofon', 'UHF-Funkmikrofon\r\nFrequenzbereich: 630–680 MHz\r\nReichweite: bis 50 m\r\nBatterielaufzeit: bis 8 Stunden\r\nInklusive Empfänger', 199.90, 'EUR', 5.0, 999, 1, '2026-06-23 14:35:52'),
(5, 3, 'Nebelmaschine', 'Leistung: 900 W\r\nAufheizzeit: ca. 4 Minuten\r\nTankvolumen: 1 Liter\r\nAusstoßweite: bis 5 m\r\nKabelgebundene Fernbedienung', 159.90, 'EUR', 3.1, 999, 1, '2026-06-23 14:37:15'),
(6, 1, 'Lichtstativ', 'Höhenverstellbar: 1,2–3,0 m\r\nMaximale Traglast: 25 kg\r\nMaterial: Aluminium\r\nKlappbare Standfüße\r\nGewicht: 3,5 kg', 99.90, 'EUR', 5.0, 999, 1, '2026-06-23 14:38:04'),
(7, 2, 'Mischpult', '8-Kanal-Audiomischpult\r\n4 Mikrofoneingänge (XLR)\r\n2-Band-EQ pro Kanal\r\nIntegrierte Effektsektion\r\nUSB-Audio-Interface', 599.90, 'EUR', 4.0, 999, 1, '2026-06-23 14:39:08');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `product_images`
--

CREATE TABLE `product_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `alt_text`, `sort_order`, `is_primary`, `created_at`) VALUES
(1, 1, 'assets/products/led-par.jpg', 'LED PAR', 0, 1, '2026-06-21 18:14:22'),
(2, 2, 'assets/products/pa-speaker.jpg', 'PA Lautsprecher', 0, 1, '2026-06-21 18:14:22'),
(3, 3, 'assets/products/xlr-kabel.jpg', 'XLR Kabel', 0, 1, '2026-06-21 18:14:22'),
(4, 4, 'assets/products/product-4-6c2ae2bf.jpg', 'Funkmikrofon', 0, 1, '2026-06-23 14:35:52'),
(5, 5, 'assets/products/product-5-74956220.jpg', 'Nebelmaschine', 0, 1, '2026-06-23 14:37:15'),
(6, 6, 'assets/products/product-6-7789e32a.webp', 'Lichtstativ', 0, 1, '2026-06-23 14:38:04'),
(7, 7, 'assets/products/product-7-5a4bf01b.webp', 'Mischpult', 0, 1, '2026-06-23 14:39:08');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `anrede` varchar(10) DEFAULT NULL,
  `vorname` varchar(50) NOT NULL,
  `nachname` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `benutzername` varchar(50) NOT NULL,
  `passwort_hash` varchar(255) NOT NULL,
  `rolle` enum('admin','user') NOT NULL DEFAULT 'user',
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `anrede`, `vorname`, `nachname`, `email`, `benutzername`, `passwort_hash`, `rolle`, `aktiv`, `remember_token`, `created_at`) VALUES
(2, 'Herr', 'Admin', 'Eventavoa', 'admin@eventavoa.at', 'admin', '$2y$10$TA6mmSmNTEP9e5pvPuZWmetJbSpV1PoNinAeWC40Pud3Mv8yvz2bi', 'admin', 1, NULL, '2026-06-21 18:26:45'),
(3, 'Herr', 'user', 'student', 'student@technikum-wien.at', 'student', '$2y$10$XdZQ/ih5hn7axIrLsDJIW.78z5lwZp/VntTFFZmXaI.tsOkmSgkl2', 'user', 1, NULL, '2026-06-21 18:59:16'),
(4, 'Herr', 'Max', 'Musterfrau', 'max@frau.at', 'Maxi', '$2y$10$TA6mmSmNTEP9e5pvPuZWmetJbSpV1PoNinAeWC40Pud3Mv8yvz2bi', 'user', 1, NULL, '2026-06-21 19:05:44');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` char(5) NOT NULL,
  `initial_value` decimal(10,2) NOT NULL,
  `remaining_value` decimal(10,2) NOT NULL,
  `expires_at` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `initial_value`, `remaining_value`, `expires_at`, `created_at`) VALUES
(1, 'NZH3P', 10000.00, 8601.50, '2030-01-01', '2026-06-23 14:42:31'),
(2, 'Y38BN', 100.00, 0.00, '2030-01-01', '2026-06-23 14:42:41');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `zahlungsmoeglichkeiten`
--

CREATE TABLE `zahlungsmoeglichkeiten` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `typ` varchar(20) NOT NULL,
  `inhaber` varchar(100) NOT NULL,
  `nummer` varchar(100) NOT NULL,
  `pruefziffer` varchar(255) DEFAULT NULL,
  `gueltig_bis` varchar(5) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `zahlungsmoeglichkeiten`
--

INSERT INTO `zahlungsmoeglichkeiten` (`id`, `user_id`, `typ`, `inhaber`, `nummer`, `pruefziffer`, `gueltig_bis`, `created_at`) VALUES
(1, 3, 'Rechnung', 'student', 'a@a.com', NULL, NULL, '2026-06-22 00:49:23'),
(2, 3, 'Rechnung', 'student', 's@s.at', NULL, NULL, '2026-06-23 14:41:25'),
(3, 3, 'PayPal', 'student', 's@s.at', NULL, NULL, '2026-06-23 14:41:41');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_addresses_user_id` (`user_id`);

--
-- Indizes für die Tabelle `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_rechnungsnummer` (`rechnungsnummer`),
  ADD KEY `idx_orders_user_id` (`user_id`),
  ADD KEY `fk_orders_zahlung` (`zahlung_id`),
  ADD KEY `fk_orders_gutschein` (`gutschein_id`);

--
-- Indizes für die Tabelle `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order_id` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`);

--
-- Indizes für die Tabelle `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_products_category_id` (`category_id`);

--
-- Indizes für die Tabelle `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_images_product_id` (`product_id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_benutzername` (`benutzername`);

--
-- Indizes für die Tabelle `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vouchers_code` (`code`);

--
-- Indizes für die Tabelle `zahlungsmoeglichkeiten`
--
ALTER TABLE `zahlungsmoeglichkeiten`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_zahlung_user_id` (`user_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `zahlungsmoeglichkeiten`
--
ALTER TABLE `zahlungsmoeglichkeiten`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_gutschein` FOREIGN KEY (`gutschein_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_orders_zahlung` FOREIGN KEY (`zahlung_id`) REFERENCES `zahlungsmoeglichkeiten` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `zahlungsmoeglichkeiten`
--
ALTER TABLE `zahlungsmoeglichkeiten`
  ADD CONSTRAINT `fk_zahlung_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
