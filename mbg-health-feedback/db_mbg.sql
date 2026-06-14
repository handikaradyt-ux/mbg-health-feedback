-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versi server:                 8.4.8 - MySQL Community Server - GPL
-- OS Server:                    Win64
-- HeidiSQL Versi:               12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Membuang struktur basisdata untuk db_mbg
CREATE DATABASE IF NOT EXISTS `db_mbg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `db_mbg`;

-- membuang struktur untuk table db_mbg.audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `target_table` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `target_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Membuang data untuk tabel db_mbg.audit_logs: ~61 rows (lebih kurang)
INSERT INTO `audit_logs` (`log_id`, `user_id`, `action_type`, `target_table`, `target_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
	(1, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: admin1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:32:18'),
	(2, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: admin1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-12 17:32:54'),
	(3, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-12 17:33:53'),
	(4, NULL, 'REGISTER', 'users', 5, 'Registrasi akun baru: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-12 17:34:26'),
	(5, NULL, 'LOGIN', 'users', 5, 'Login berhasil sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-12 17:34:34'),
	(6, NULL, 'LOGOUT', 'users', 5, 'Logout dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-12 17:40:49'),
	(7, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-12 17:41:01'),
	(8, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:46:23'),
	(9, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: admin1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:12:04'),
	(10, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:12:15'),
	(11, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:12:31'),
	(12, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: petugas1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:12:44'),
	(13, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: petugas1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:12:56'),
	(14, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: petugas1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:13:16'),
	(15, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: petugas1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:14:47'),
	(16, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:18:03'),
	(17, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:18:37'),
	(18, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-13 03:18:55'),
	(19, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: Radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-13 03:19:04'),
	(20, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-13 03:19:15'),
	(21, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:31:01'),
	(22, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-13 03:32:14'),
	(23, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-13 03:32:21'),
	(24, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: petugas1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.124.2 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', '2026-06-13 03:32:30'),
	(25, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:38:13'),
	(26, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:38:23'),
	(27, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:38:39'),
	(28, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: petugas1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:38:47'),
	(29, 10, 'LOGIN', 'users', 10, 'Login berhasil sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:41:26'),
	(30, 10, 'LOGOUT', 'users', 10, 'Logout dari sistem sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:42:44'),
	(31, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:42:55'),
	(32, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:44:52'),
	(33, 10, 'LOGIN', 'users', 10, 'Login berhasil sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:44:58'),
	(34, 10, 'LOGOUT', 'users', 10, 'Logout dari sistem sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:50:06'),
	(35, 3, 'REGISTER', 'users', 11, 'Registrasi akun baru: radyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:55:39'),
	(36, 3, 'LOGIN', 'users', 11, 'Login berhasil sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:55:44'),
	(37, 3, 'LOGOUT', 'users', 11, 'Logout dari sistem sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:55:50'),
	(38, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: admin1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:55:56'),
	(39, 10, 'LOGIN', 'users', 10, 'Login berhasil sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:56:07'),
	(40, 10, 'LOGOUT', 'users', 10, 'Logout dari sistem sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:56:15'),
	(41, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:56:38'),
	(42, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:56:49'),
	(43, 3, 'LOGIN', 'users', 11, 'Login berhasil sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 03:56:54'),
	(44, 3, 'LOGOUT', 'users', 11, 'Logout dari sistem sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 04:16:01'),
	(45, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 04:16:09'),
	(46, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 04:22:57'),
	(47, 3, 'LOGIN', 'users', 11, 'Login berhasil sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 04:23:04'),
	(49, 3, 'LOGIN', 'users', 3, 'Login berhasil sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 05:54:14'),
	(50, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 09:15:26'),
	(51, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 09:16:42'),
	(52, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 09:35:56'),
	(53, 10, 'LOGIN', 'users', 10, 'Login berhasil sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 10:36:16'),
	(54, 10, 'VALIDATE_HEALTH', 'health_records', 2, 'Status diubah dari \'pending\' menjadi \'approved\'. Catatan: ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 11:06:53'),
	(55, 10, 'LOGOUT', 'users', 10, 'Logout dari sistem sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 11:14:45'),
	(56, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 11:14:53'),
	(57, 8, 'LOGOUT', 'users', 8, 'Logout dari sistem sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 11:15:17'),
	(58, 10, 'LOGIN', 'users', 10, 'Login berhasil sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-13 11:15:25'),
	(59, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 05:53:19'),
	(60, 8, 'DEACTIVATE', 'users', 3, 'Berhasil menonaktifkan pengguna \'radyt\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 06:42:18'),
	(61, 8, 'ACTIVATE', 'users', 3, 'Berhasil mengaktifkan pengguna \'radyt\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 06:42:20'),
	(62, 10, 'LOGIN', 'users', 10, 'Login berhasil sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 08:33:45'),
	(63, 10, 'VALIDATE_HEALTH', 'health_records', 1, 'Status diubah dari \'pending\' menjadi \'approved\'. Catatan: ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 08:52:06'),
	(64, 10, 'VALIDATE_FEEDBACK', 'feedbacks', 29, 'Status diubah dari \'pending\' menjadi \'approved\'. Catatan: ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 09:17:21'),
	(65, NULL, 'LOGIN_FAILED', 'users', NULL, 'Percobaan login gagal untuk username: rdyt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 09:20:05'),
	(66, 3, 'LOGIN', 'users', 3, 'Login berhasil sebagai user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 09:20:20'),
	(67, 10, 'VALIDATE_FEEDBACK', 'feedbacks', 26, 'Status diubah dari \'pending\' menjadi \'revised\'. Catatan: ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 09:26:17'),
	(68, 8, 'LOGIN', 'users', 8, 'Login berhasil sebagai admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 14:04:26'),
	(69, 10, 'LOGIN', 'users', 10, 'Login berhasil sebagai petugas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-14 14:04:35');

-- membuang struktur untuk table db_mbg.feedbacks
CREATE TABLE IF NOT EXISTS `feedbacks` (
  `feedback_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `menu_id` int NOT NULL,
  `rating` int NOT NULL,
  `comment` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `validation_status` enum('pending','approved','revised','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `feedback_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  KEY `user_id` (`user_id`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `feedbacks_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`menu_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Membuang data untuk tabel db_mbg.feedbacks: ~0 rows (lebih kurang)
INSERT INTO `feedbacks` (`feedback_id`, `user_id`, `menu_id`, `rating`, `comment`, `validation_status`, `feedback_date`, `created_at`) VALUES
	(21, 3, 1, 5, 'Ayam gorengnya enak dan renyah, porsinya juga pas', 'approved', '2025-04-01', '2026-06-13 18:50:00'),
	(22, 3, 1, 4, 'Sambalnya pedas mantap, tapi nasi agak dingin', 'approved', '2025-04-01', '2026-06-13 18:51:00'),
	(23, 3, 2, 4, 'Telur baladonya pas, sayur bayamnya segar', 'approved', '2025-04-02', '2026-06-13 18:52:00'),
	(24, 3, 2, 3, 'Rasanya biasa saja, kurang bumbu menurut saya', 'approved', '2025-04-02', '2026-06-13 18:53:00'),
	(25, 3, 3, 5, 'Bubur kacang hijaunya manis dan hangat, cocok untuk pagi', 'approved', '2025-04-03', '2026-06-13 18:54:00'),
	(26, 3, 3, 2, 'Porsinya terlalu sedikit untuk anak sekolah', 'revised', '2025-04-03', '2026-06-13 18:55:00'),
	(27, 3, 4, 5, 'Soto ayamnya segar, kuahnya gurih banget', 'approved', '2025-04-04', '2026-06-13 18:56:00'),
	(28, 3, 4, 4, 'Enak, tapi keruparnya kurang renyah', 'approved', '2025-04-04', '2026-06-13 18:57:00'),
	(29, 3, 5, 3, 'Tempe oreknya terlalu manis untuk saya', 'approved', '2025-04-07', '2026-06-13 18:58:00'),
	(30, 3, 5, 4, 'Tahu gorengnya enak dan gurih', 'approved', '2025-04-07', '2026-06-13 18:59:00'),
	(31, 3, 6, 5, 'Mie gorengnya enak, sayurannya banyak', 'approved', '2025-04-08', '2026-06-13 19:00:00'),
	(32, 3, 6, 4, 'Rasanya pas, tapi agak terlalu berminyak', 'approved', '2025-04-08', '2026-06-13 19:01:00'),
	(33, 3, 7, 4, 'Ikan pindangnya enak, bumbu kuningnya meresap', 'approved', '2025-04-09', '2026-06-13 19:02:00'),
	(34, 3, 7, 1, 'Ikannya bau dan kurang segar', 'rejected', '2025-04-09', '2026-06-13 19:03:00'),
	(35, 3, 8, 5, 'Lelenya krispi banget, sambalnya juga mantap', 'approved', '2025-04-10', '2026-06-13 19:04:00'),
	(36, 3, 8, 4, 'Enak, lalapannya segar', 'approved', '2025-04-10', '2026-06-13 19:05:00'),
	(37, 3, 9, 5, 'Gado-gadonya enak, bumbu kacangnya kental dan gurih', 'approved', '2025-04-11', '2026-06-13 19:06:00'),
	(38, 3, 9, 3, 'Lontongnya agak keras', 'pending', '2025-04-11', '2026-06-13 19:07:00'),
	(39, 3, 10, 5, 'Rendangnya empuk dan bumbunya nendang banget', 'approved', '2025-04-14', '2026-06-13 19:08:00'),
	(40, 3, 10, 4, 'Porsi daging agak sedikit tapi rasanya enak', 'approved', '2025-04-14', '2026-06-13 19:09:00');

-- membuang struktur untuk table db_mbg.health_records
CREATE TABLE IF NOT EXISTS `health_records` (
  `health_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `height_cm` decimal(5,1) NOT NULL,
  `weight_kg` decimal(5,1) NOT NULL,
  `bmi_value` decimal(5,2) NOT NULL,
  `bmi_category` enum('Underweight','Normal','Overweight','Obese') COLLATE utf8mb4_general_ci NOT NULL,
  `validation_status` enum('pending','approved','revised','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `input_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`health_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Membuang data untuk tabel db_mbg.health_records: ~10 rows (lebih kurang)
INSERT INTO `health_records` (`health_id`, `user_id`, `height_cm`, `weight_kg`, `bmi_value`, `bmi_category`, `validation_status`, `input_date`, `notes`, `created_at`) VALUES
	(1, 3, 170.0, 45.0, 15.57, 'Underweight', 'approved', '2026-05-12', NULL, '2026-06-13 05:35:38'),
	(2, 3, 180.0, 75.0, 23.15, 'Normal', 'approved', '2026-06-13', NULL, '2026-06-13 05:37:03'),
	(3, 3, 170.0, 60.0, 20.76, 'Normal', 'approved', '2025-11-10', 'November', '2026-06-13 05:53:27'),
	(4, 3, 170.0, 63.0, 21.80, 'Normal', 'approved', '2025-12-10', 'Desember', '2026-06-13 05:53:27'),
	(5, 3, 170.0, 65.0, 22.49, 'Normal', 'approved', '2026-01-10', 'Januari', '2026-06-13 05:53:27'),
	(6, 3, 170.0, 69.0, 23.88, 'Normal', 'approved', '2026-02-10', 'Februari', '2026-06-13 05:53:27'),
	(7, 3, 170.0, 72.0, 24.91, 'Normal', 'approved', '2026-03-10', 'Maret', '2026-06-13 05:53:27'),
	(8, 3, 170.0, 74.0, 25.61, 'Overweight', 'approved', '2026-04-10', 'April', '2026-06-13 05:53:27'),
	(9, 3, 170.0, 71.0, 24.57, 'Normal', 'approved', '2026-05-10', 'Mei', '2026-06-13 05:53:27'),
	(10, 3, 170.0, 68.0, 23.53, 'Normal', 'approved', '2026-06-10', 'Juni', '2026-06-13 05:53:27');

-- membuang struktur untuk table db_mbg.menus
CREATE TABLE IF NOT EXISTS `menus` (
  `menu_id` int NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `menu_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`menu_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `menus_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Membuang data untuk tabel db_mbg.menus: ~0 rows (lebih kurang)
INSERT INTO `menus` (`menu_id`, `menu_name`, `description`, `menu_date`, `is_active`, `created_by`, `created_at`) VALUES
	(1, 'Nasi Ayam Goreng', 'Nasi putih dengan ayam goreng krispi, sambal, dan lalapan segar', '2025-04-01', 1, 1, '2026-06-14 08:44:40'),
	(2, 'Nasi Telur Balado', 'Nasi putih dengan telur balado pedas dan sayur bening bayam', '2025-04-02', 1, 1, '2026-06-14 08:44:40'),
	(3, 'Bubur Kacang Hijau', 'Bubur kacang hijau hangat dengan santan dan gula merah', '2025-04-03', 1, 1, '2026-06-14 08:44:40'),
	(4, 'Soto Ayam', 'Soto ayam bening dengan soun, telur rebus, dan kerupuk', '2025-04-04', 1, 1, '2026-06-14 08:44:40'),
	(5, 'Nasi Tempe Orek', 'Nasi putih dengan tempe orek manis pedas dan tahu goreng', '2025-04-07', 1, 1, '2026-06-14 08:44:40'),
	(6, 'Mie Goreng Sayur', 'Mie goreng dengan aneka sayuran, telur, dan saus spesial', '2025-04-08', 1, 1, '2026-06-14 08:44:40'),
	(7, 'Nasi Ikan Pindang', 'Nasi putih dengan ikan pindang bumbu kuning dan tumis kangkung', '2025-04-09', 1, 1, '2026-06-14 08:44:40'),
	(8, 'Pecel Lele', 'Lele goreng krispi dengan nasi, sambal terasi, dan lalapan', '2025-04-10', 1, 1, '2026-06-14 08:44:40'),
	(9, 'Gado-Gado', 'Sayuran rebus dengan bumbu kacang spesial, lontong, dan kerupuk', '2025-04-11', 1, 1, '2026-06-14 08:44:40'),
	(10, 'Nasi Rendang', 'Nasi putih dengan rendang daging sapi empuk bumbu rempah lengkap', '2025-04-14', 1, 1, '2026-06-14 08:44:40');

-- membuang struktur untuk table db_mbg.report_downloads
CREATE TABLE IF NOT EXISTS `report_downloads` (
  `download_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `report_type` enum('health','feedback') COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`download_id`),
  KEY `user_id` (`user_id`),
  KEY `report_type` (`report_type`),
  CONSTRAINT `report_downloads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Membuang data untuk tabel db_mbg.report_downloads: ~0 rows (lebih kurang)

-- membuang struktur untuk table db_mbg.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('user','petugas','admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Membuang data untuk tabel db_mbg.users: ~3 rows (lebih kurang)
INSERT INTO `users` (`user_id`, `full_name`, `username`, `password_hash`, `role`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
	(3, 'Radyt Guruh Handika', 'radyt', '$2y$10$cdDNivRJ.1aw09btHbRTtOd3PgfFLEKRtqzO38Pz0.uEqBOuTURLO', 'user', 'backupv145@gmail.com', 1, '2026-06-13 03:55:39', '2026-06-14 06:42:20'),
	(8, 'Administrator Sistem', 'admin1', '$2y$10$6CFoN9W.WVTm/fZN9XkxIe6ZfiCd4rEO3iz2UrGW1EMbGHUJ2jhQW', 'admin', 'admin@mbg.local', 1, '2026-06-12 17:40:40', '2026-06-12 17:40:40'),
	(10, 'Petugas Validasi', 'petugas1', '$2y$10$hZMUGHuicUruBuGGzHQrM.mxYr5KJtN72xSGBkwOcSbs0a20Izx3O', 'petugas', 'petugas@mbg.local', 1, '2026-06-13 03:41:17', '2026-06-13 03:41:17');

-- membuang struktur untuk table db_mbg.validation_records
CREATE TABLE IF NOT EXISTS `validation_records` (
  `validation_id` int NOT NULL AUTO_INCREMENT,
  `validator_id` int NOT NULL,
  `data_type` enum('health_record','feedback') COLLATE utf8mb4_general_ci NOT NULL,
  `data_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `validation_notes` text COLLATE utf8mb4_general_ci,
  `validated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`validation_id`),
  KEY `validator_id` (`validator_id`),
  CONSTRAINT `validation_records_ibfk_1` FOREIGN KEY (`validator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Membuang data untuk tabel db_mbg.validation_records: ~1 rows (lebih kurang)
INSERT INTO `validation_records` (`validation_id`, `validator_id`, `data_type`, `data_id`, `old_status`, `new_status`, `validation_notes`, `validated_at`) VALUES
	(1, 10, 'health_record', 2, 'pending', 'approved', '', '2026-06-13 11:06:53'),
	(2, 10, 'health_record', 1, 'pending', 'approved', '', '2026-06-14 08:52:06'),
	(3, 10, 'feedback', 29, 'pending', 'approved', '', '2026-06-14 09:17:21'),
	(4, 10, 'feedback', 26, 'pending', 'revised', '', '2026-06-14 09:26:17');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
