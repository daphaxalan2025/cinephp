-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 08, 2026 at 11:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cinema_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetDashboardStats` ()   BEGIN
    SELECT 
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM users WHERE parent_id IS NOT NULL) AS total_link_accounts,
        (SELECT COUNT(*) FROM movies) AS total_movies,
        (SELECT COUNT(*) FROM screenings) AS total_screenings,
        (SELECT COUNT(*) FROM tickets WHERE status = 'pending') AS pending_tickets,
        (SELECT COUNT(*) FROM tickets WHERE ticket_type = 'online') AS online_tickets,
        (SELECT COUNT(*) FROM tickets WHERE ticket_type = 'physical') AS physical_tickets,
        (SELECT IFNULL(SUM(amount), 0) FROM payments WHERE payment_status = 'completed') AS total_revenue;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetRevenueByDateRange` (IN `start_date` DATE, IN `end_date` DATE)   BEGIN
    SELECT 
        DATE(payment_date) AS date,
        COUNT(*) AS transaction_count,
        SUM(amount) AS daily_revenue
    FROM payments
    WHERE payment_status = 'completed'
        AND DATE(payment_date) BETWEEN start_date AND end_date
    GROUP BY DATE(payment_date)
    ORDER BY date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetTopMovies` (IN `limit_count` INT)   BEGIN
    SELECT 
        m.id,
        m.title,
        COUNT(t.id) AS ticket_count,
        SUM(t.total_price) AS revenue
    FROM movies m
    JOIN screenings s ON m.id = s.movie_id
    JOIN tickets t ON s.id = t.screening_id
    WHERE t.status IN ('paid', 'used')
    GROUP BY m.id, m.title
    ORDER BY ticket_count DESC
    LIMIT limit_count;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cinemas`
--

CREATE TABLE `cinemas` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `location` varchar(500) NOT NULL,
  `total_screens` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cinemas`
--

INSERT INTO `cinemas` (`id`, `name`, `location`, `total_screens`, `created_at`) VALUES
(1, 'SM North', 'Cebu City', 3, '2026-03-05 09:25:39'),
(2, 'SM Cebu', 'Cebu City', 1, '2026-03-05 10:31:48');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `movie_id`, `created_at`) VALUES
(1, 2, 2, '2026-03-06 20:13:39');

-- --------------------------------------------------------

--
-- Table structure for table `link_accounts`
--

CREATE TABLE `link_accounts` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `duration` int(11) NOT NULL,
  `rating` varchar(10) NOT NULL,
  `genre` varchar(100) NOT NULL,
  `price` decimal(10,2) DEFAULT 12.50,
  `poster` varchar(200) NOT NULL,
  `trailer_url` varchar(500) DEFAULT NULL,
  `streaming_url` varchar(500) DEFAULT NULL,
  `release_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`id`, `title`, `description`, `duration`, `rating`, `genre`, `price`, `poster`, `trailer_url`, `streaming_url`, `release_date`, `created_at`) VALUES
(1, 'Flow', 'Enter the orld of cats.', 120, 'G', 'Adventure', 12.50, '69a9204cedc61_1772691532.jpg', 'https://www.youtube.com/embed/ZgZccxuj2RY?si=9tFwQhAPt7d0NgG_', '', '2026-05-25', '2026-03-05 09:09:45'),
(2, 'Demon Slayer', 'Slaying Demons.', 120, 'PG', 'Action', 20.00, '69a9313fec219_1772695871.png', 'https://www.youtube.com/embed/VQGCKyvzIM4?si=_V4DKgOij8xdSqoa', '', '2026-03-05', '2026-03-05 10:31:11');

-- --------------------------------------------------------

--
-- Table structure for table `online_schedule`
--

CREATE TABLE `online_schedule` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `max_viewers` int(11) DEFAULT 100,
  `current_viewers` int(11) DEFAULT 0,
  `price` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `online_schedule`
--

INSERT INTO `online_schedule` (`id`, `movie_id`, `show_date`, `show_time`, `max_viewers`, `current_viewers`, `price`, `status`, `created_at`) VALUES
(1, 2, '2026-03-06', '20:00:00', 100, 1, 10.00, 'scheduled', '2026-03-06 19:25:45'),
(2, 1, '2026-03-06', '20:00:00', 100, 0, 10.00, 'scheduled', '2026-03-06 19:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` varchar(20) DEFAULT 'pending',
  `transaction_id` varchar(200) DEFAULT NULL,
  `proof_of_transaction` varchar(255) DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `ticket_id`, `user_id`, `amount`, `payment_method`, `payment_status`, `transaction_id`, `proof_of_transaction`, `expiry_date`, `payment_date`, `processed_by`, `processed_at`, `notes`) VALUES
(1, 1, 2, 21.50, 'credit_card', 'failed', 'TXN-69AA36B273189', NULL, NULL, '2026-03-06 05:06:42', NULL, NULL, NULL),
(2, 2, 2, 21.50, 'credit_card', 'completed', 'TXN-69AA4B60E1C25', NULL, NULL, '2026-03-06 06:34:56', NULL, '2026-03-06 06:36:51', NULL),
(6, 3, 2, 15.50, 'gcash', 'completed', 'TXN1772819200500', 'proof_1772819200_69ab1300e2ca0.png', NULL, '2026-03-06 20:46:40', NULL, '2026-03-06 22:34:27', NULL),
(7, 4, 2, 13.00, 'gcash', 'pending', 'TXN1772825242383', 'proof_1772825242_69ab2a9a30c27.png', NULL, '2026-03-06 22:27:22', NULL, NULL, NULL);

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `before_payment_update` BEFORE UPDATE ON `payments` FOR EACH ROW BEGIN
    IF NEW.payment_status = 'completed' AND OLD.payment_status != 'completed' THEN
        SET NEW.processed_at = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `screenings`
--

CREATE TABLE `screenings` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `cinema_id` int(11) NOT NULL,
  `screen_number` int(11) NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `available_seats` int(11) NOT NULL DEFAULT 50,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `screenings`
--

INSERT INTO `screenings` (`id`, `movie_id`, `cinema_id`, `screen_number`, `show_date`, `show_time`, `price`, `available_seats`, `created_at`) VALUES
(1, 1, 1, 3, '2026-03-05', '07:00:00', 12.50, 40, '2026-03-05 09:26:08'),
(2, 2, 2, 2, '2026-03-05', '10:00:00', 20.00, 36, '2026-03-05 10:32:20'),
(3, 1, 1, 2, '2026-03-06', '09:00:00', 12.50, 38, '2026-03-06 16:56:20');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_code` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `screening_id` int(11) DEFAULT NULL,
  `online_schedule_id` int(11) DEFAULT NULL,
  `ticket_type` varchar(20) NOT NULL DEFAULT 'cinema',
  `quantity` int(11) DEFAULT 1,
  `seat_numbers` text DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `expiry_date` datetime DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT 'pending',
  `qr_code` varchar(200) DEFAULT NULL,
  `pdf_path` varchar(200) DEFAULT NULL,
  `purchase_date` datetime DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `streaming_views` int(11) DEFAULT 0,
  `max_streaming_views` int(11) DEFAULT 3,
  `streaming_start` datetime DEFAULT NULL,
  `streaming_expire` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_code`, `user_id`, `screening_id`, `online_schedule_id`, `ticket_type`, `quantity`, `seat_numbers`, `total_price`, `status`, `expiry_date`, `payment_id`, `payment_status`, `qr_code`, `pdf_path`, `purchase_date`, `used_at`, `verified_by`, `streaming_views`, `max_streaming_views`, `streaming_start`, `streaming_expire`) VALUES
(1, 'TIX-69AA36B272079-20260306', 2, 2, NULL, 'physical', 1, 'A4', 21.50, 'pending', NULL, NULL, 'pending', NULL, NULL, '2026-03-06 05:06:42', NULL, NULL, 0, 3, NULL, NULL),
(2, 'TIX-69AA4B60E0CD5-20260306', 2, 2, NULL, 'physical', 1, 'E3', 21.50, 'paid', NULL, NULL, 'pending', NULL, NULL, '2026-03-06 06:34:56', NULL, NULL, 0, 3, NULL, NULL),
(3, 'TIX17728192005049A82', 2, 3, NULL, 'cinema', 1, 'A4', 15.50, 'paid', '2026-03-06 09:00:00', 6, 'completed', NULL, NULL, '2026-03-06 20:46:40', NULL, NULL, 0, 3, NULL, NULL),
(4, 'TIX17728252424417D0F', 4, NULL, 1, 'online', 1, NULL, 13.00, 'paid', '2026-03-06 20:00:00', 7, 'completed', NULL, NULL, '2026-03-06 22:27:22', NULL, NULL, 1, 3, NULL, NULL);

--
-- Triggers `tickets`
--
DELIMITER $$
CREATE TRIGGER `after_ticket_insert` AFTER INSERT ON `tickets` FOR EACH ROW BEGIN
    UPDATE screenings 
    SET available_seats = available_seats - NEW.quantity
    WHERE id = NEW.screening_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_ticket_status_update` AFTER UPDATE ON `tickets` FOR EACH ROW BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        UPDATE screenings 
        SET available_seats = available_seats + NEW.quantity
        WHERE id = NEW.screening_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(80) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(200) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthdate` date NOT NULL,
  `account_type` varchar(20) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `country` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `profile_pic` varchar(200) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `theme_preference` varchar(20) DEFAULT 'prestige',
  `is_active` tinyint(1) DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `cinema_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `streaming_token` varchar(100) DEFAULT NULL
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `birthdate`, `account_type`, `gender`, `country`, `phone`, `profile_pic`, `bio`, `theme_preference`, `is_active`, `parent_id`, `cinema_id`, `created_at`, `last_login`, `streaming_token`) VALUES
(1, 'q123', 'q@gmail.com', '$2y$10$hKZSdSdxjce3hS/6MTURVeH9yLuBHCi46A1JdNGndd98rlKWv1bPW', 'fa', 'ax', '2006-03-04', 'admin', 'female', 'PH', '+639756452322', 'profile_1_1772695515.jpg', NULL, 'prestige', 1, NULL, NULL, '2026-03-05 08:34:24', '2026-03-08 09:12:31', NULL),
(2, 'jdoe23', 'j@gmail.com', '$2y$10$o3Mc2MILKosUlLDP6BUzhOwaq9X.1QIRRk5Iy7VTI3XnQ65K9j9Jy', 'jane', 'doe', '2006-11-16', 'adult', 'male', 'PH', '+639847657922', 'profile_2_1772761615.jpg', NULL, 'neon', 1, NULL, NULL, '2026-03-05 10:28:21', '2026-03-06 22:26:23', NULL),
(3, 'min123', 'm@gmail.com', '$2y$10$6ioHWQZ/T3WSYN5vekqadeayiBCOLXtU6mvJFrz5maUF5EreJPZHi', 'jas', 'min', '2000-01-01', 'staff', '', '', '', 'profile_3_1772827475.jpg', NULL, 'prestige', 1, NULL, NULL, '2026-03-06 04:40:49', '2026-03-06 22:36:25', NULL),
(4, 'dilina', 'ln@gmail.com', '$2y$10$0Q/8ql/ZjZLDOAd7MhHL7ej7MJOuyQGJju9laiKoz8XxyGXfBVhwy', 'dina', 'muli', '2017-07-12', 'kid', 'male', 'PH', '0000000000', NULL, NULL, 'prestige', 1, 2, NULL, '2026-03-06 04:46:00', NULL, NULL),
(5, 'tanny', 't@gmail.com', '$2y$10$u/HDkDWCuhBnk9W5cShFAugWQwDqxYq0apr3ehHlP.ni.p7QDoWDu', 'tan', 'tan', '2000-01-25', 'adult', 'male', 'PH', '+639643577534', NULL, NULL, 'prestige', 1, NULL, NULL, '2026-03-06 06:40:40', '2026-03-06 06:40:48', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_active_screenings`
-- (See below for the actual view)
--
CREATE TABLE `vw_active_screenings` (
`id` int(11)
,`movie_title` varchar(200)
,`rating` varchar(10)
,`duration` int(11)
,`cinema_name` varchar(200)
,`location` varchar(500)
,`screen_number` int(11)
,`show_date` date
,`show_time` time
,`price` decimal(10,2)
,`available_seats` int(11)
,`seats_sold` bigint(12)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_ticket_sales`
-- (See below for the actual view)
--
CREATE TABLE `vw_ticket_sales` (
`sale_date` date
,`ticket_type` varchar(20)
,`tickets_sold` bigint(21)
,`total_revenue` decimal(32,2)
,`avg_ticket_price` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `watch_history`
--

CREATE TABLE `watch_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `watched_at` datetime DEFAULT current_timestamp(),
  `completed` tinyint(1) DEFAULT 0,
  `watch_duration` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `vw_active_screenings`
--
DROP TABLE IF EXISTS `vw_active_screenings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_active_screenings`  AS SELECT `s`.`id` AS `id`, `m`.`title` AS `movie_title`, `m`.`rating` AS `rating`, `m`.`duration` AS `duration`, `c`.`name` AS `cinema_name`, `c`.`location` AS `location`, `s`.`screen_number` AS `screen_number`, `s`.`show_date` AS `show_date`, `s`.`show_time` AS `show_time`, `s`.`price` AS `price`, `s`.`available_seats` AS `available_seats`, 50 - `s`.`available_seats` AS `seats_sold` FROM ((`screenings` `s` join `movies` `m` on(`s`.`movie_id` = `m`.`id`)) join `cinemas` `c` on(`s`.`cinema_id` = `c`.`id`)) WHERE `s`.`show_date` >= curdate() ;

-- --------------------------------------------------------

--
-- Structure for view `vw_ticket_sales`
--
DROP TABLE IF EXISTS `vw_ticket_sales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_ticket_sales`  AS SELECT cast(`t`.`purchase_date` as date) AS `sale_date`, `t`.`ticket_type` AS `ticket_type`, count(0) AS `tickets_sold`, sum(`t`.`total_price`) AS `total_revenue`, avg(`t`.`total_price`) AS `avg_ticket_price` FROM `tickets` AS `t` WHERE `t`.`status` in ('paid','used') GROUP BY cast(`t`.`purchase_date` as date), `t`.`ticket_type` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cinemas`
--
ALTER TABLE `cinemas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`movie_id`),
  ADD KEY `idx_favorites_user` (`user_id`),
  ADD KEY `idx_favorites_movie` (`movie_id`);

--
-- Indexes for table `link_accounts`
--
ALTER TABLE `link_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_link` (`parent_id`,`child_id`),
  ADD KEY `idx_link_parent` (`parent_id`),
  ADD KEY `idx_link_child` (`child_id`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `title` (`title`),
  ADD KEY `idx_movies_rating` (`rating`),
  ADD KEY `idx_movies_genre` (`genre`),
  ADD KEY `idx_movies_release_date` (`release_date`);

--
-- Indexes for table `online_schedule`
--
ALTER TABLE `online_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_online_show` (`movie_id`,`show_date`,`show_time`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_payments_ticket` (`ticket_id`),
  ADD KEY `idx_payments_user` (`user_id`),
  ADD KEY `idx_payments_status` (`payment_status`),
  ADD KEY `idx_payments_date` (`payment_date`),
  ADD KEY `idx_payments_transaction` (`transaction_id`);

--
-- Indexes for table `screenings`
--
ALTER TABLE `screenings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_screening` (`cinema_id`,`screen_number`,`show_date`,`show_time`),
  ADD KEY `idx_screenings_movie` (`movie_id`),
  ADD KEY `idx_screenings_cinema` (`cinema_id`),
  ADD KEY `idx_screenings_date` (`show_date`),
  ADD KEY `idx_screenings_datetime` (`show_date`,`show_time`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_code` (`ticket_code`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_tickets_code` (`ticket_code`),
  ADD KEY `idx_tickets_user` (`user_id`),
  ADD KEY `idx_tickets_screening` (`screening_id`),
  ADD KEY `idx_tickets_status` (`status`),
  ADD KEY `idx_tickets_purchase_date` (`purchase_date`),
  ADD KEY `online_schedule_id` (`online_schedule_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_account_type` (`account_type`),
  ADD KEY `idx_users_parent_id` (`parent_id`),
  ADD KEY `fk_users_cinema` (`cinema_id`);

--
-- Indexes for table `watch_history`
--
ALTER TABLE `watch_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_user` (`user_id`),
  ADD KEY `idx_history_movie` (`movie_id`),
  ADD KEY `idx_history_watched` (`watched_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cinemas`
--
ALTER TABLE `cinemas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `link_accounts`
--
ALTER TABLE `link_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `online_schedule`
--
ALTER TABLE `online_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `screenings`
--
ALTER TABLE `screenings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `watch_history`
--
ALTER TABLE `watch_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `link_accounts`
--
ALTER TABLE `link_accounts`
  ADD CONSTRAINT `link_accounts_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `link_accounts_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `online_schedule`
--
ALTER TABLE `online_schedule`
  ADD CONSTRAINT `online_schedule_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `online_schedule_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `screenings`
--
ALTER TABLE `screenings`
  ADD CONSTRAINT `fk_screenings_cinema` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `screenings_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `screenings_ibfk_2` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`online_schedule_id`) REFERENCES `online_schedule` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_cinema` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `watch_history`
--
ALTER TABLE `watch_history`
  ADD CONSTRAINT `watch_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `watch_history_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
