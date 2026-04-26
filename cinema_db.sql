-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2026 at 04:41 AM
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
  `total_screens` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seats_per_screen` int(11) NOT NULL DEFAULT 40
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cinemas`
--

INSERT INTO `cinemas` (`id`, `name`, `location`, `total_screens`, `created_at`, `seats_per_screen`) VALUES
(1, 'SM North EDSA', 'Quezon City', 4, '2026-04-16 05:28:47', 40),
(2, 'SM Mall of Asia', 'Pasay City', 6, '2026-04-16 05:28:47', 40),
(3, 'Ayala Malls Cinemas', 'Makati City', 3, '2026-04-16 05:28:47', 50),
(4, 'Gateway Cinema', 'Quezon City', 5, '2026-04-16 05:28:47', 40),
(5, 'Robinsons Galleria', 'Pasig City', 4, '2026-04-16 05:28:47', 40),
(6, 'SCC Film Showing', 'Minglanilla, Cebu City', 2, '2026-04-16 20:58:24', 40);

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `movie_id`, `created_at`) VALUES
(1, 7, 11, '2026-04-16 20:41:07'),
(2, 9, 2, '2026-04-18 14:38:11'),
(3, 9, 8, '2026-04-18 14:38:17');

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` int(11) DEFAULT 120,
  `rating` varchar(10) DEFAULT 'PG',
  `genre` varchar(100) DEFAULT NULL,
  `poster` varchar(255) DEFAULT NULL,
  `trailer_url` varchar(500) DEFAULT NULL,
  `streaming_url` varchar(500) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 12.50,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`id`, `title`, `description`, `duration`, `rating`, `genre`, `poster`, `trailer_url`, `streaming_url`, `release_date`, `price`, `created_at`) VALUES
(1, 'Dune: Part Two', 'Paul Atreides unites with Chani and the Fremen while seeking revenge.', 166, 'PG-13', 'Sci-Fi', '69e0b34cbca8c_1776333644.jpg', 'https://www.youtube.com/embed/Way9Dexny3w', '', '2024-03-01', 349.99, '2026-04-16 05:28:47'),
(2, 'Kung Fu Panda 4', 'Po must train a new warrior to take his place as Dragon Warrior.', 94, 'PG', 'Animation', '69e0b35b7e4ef_1776333659.jpg', 'https://www.youtube.com/embed/_inKs4eeHiI', '', '2024-03-08', 249.99, '2026-04-16 05:28:47'),
(3, 'Godzilla x Kong', 'The Titans clash in an epic battle for supremacy.', 115, 'PG-13', 'Action', '69e0b39c5ebd6_1776333724.jpg', 'https://www.youtube.com/embed/qqrpMRDuPfc', '', '2024-03-15', 299.99, '2026-04-16 05:28:47'),
(4, 'Inside Out 2', 'Return to Riley\'s mind for new emotional adventures.', 100, 'PG', 'Animation', '69e0b3df662ed_1776333791.jpg', 'https://www.youtube.com/embed/LEjhY15eCx0', '', '2024-06-14', 250.00, '2026-04-16 05:28:47'),
(5, 'Deadpool 3', 'The Merc with a Mouth returns for another wild adventure.', 120, 'R', 'Action', '69e0b40fb5c80_1776333839.jpg', 'https://www.youtube.com/embed/73_1biulkYk?si=ebKnc-MdrGjLJdzj', 'https://youtu.be/563VkYS7ckc?si=sAUZwnP58lWa1QcK', '2026-04-25', 349.99, '2026-04-16 05:28:47'),
(6, 'Girl Boy Bakla Tomboy', 'A filipino comedy film starring Vice Ganda in four roles as quadruplets separated at birth. A wealthy pair (raised in the US) and a poor pair (raised in the Philippines) must reunite for a liver transplant, leading to chaotic, humorous, and heartwarming family confrontations.', 120, 'PG-13', 'Comedy, Drama', '69e0b4d902931_1776334041.jpg', 'https://www.youtube.com/embed/P7mAsauoYJQ?si=JXTEy1ivv1USiRow', 'https://youtu.be/cJRwiNt8Yhs?si=1uk73md9SPDZDofj', '2026-04-30', 300.00, '2026-04-16 10:07:21'),
(7, 'White Chicks', 'Two FBI agent brothers, Marcus (Marlon Wayans) and Kevin Copeland (Shawn Wayans), accidentally foil a drug bust. As punishment, they are forced to escort a pair of socialites (Anne Dudek, Rochelle Aytes) to the Hamptons, where they\'re going to be used as bait for a kidnapper. But when the girls realize the FBI\'s plan, they refuse to go. Left without options, Marcus and Kevin decide to pose as the sisters, transforming themselves from African-American men into a pair of blonde, white women.', 120, 'PG-13', 'Comedy, Action', '69e0b570ae4e7_1776334192.jpg', 'https://www.youtube.com/embed/aeVkbNka9HM?si=pEa0qgDKN0MVp4da', 'https://youtu.be/ywgFa00pG7s?si=E7xVW_gep0_sXLN2', '2026-04-24', 350.00, '2026-04-16 10:09:52'),
(8, 'Dr. Stone(Season 1)', 'Dr. Stone Season 1 (24 episodes) follows genius teenager Senku Ishigami, who awakens 3,700 years after a mysterious flash petrifies all humanity. Alongside his friend Taiju, Senku uses science to rebuild civilization from scratch. The season focuses on creating a \"Kingdom of Science,\" reviving humans, and battling a rival faction led by the primal-minded Tsukasa Shishio, who threatens to stop them.', 120, 'PG', 'Animation, Science Fiction', '69e0b60264488_1776334338.jpg', 'https://www.youtube.com/embed/S6OmSIxSj14?si=H6W57NASflh0l2s_', '', '2026-05-09', 300.00, '2026-04-16 10:12:18'),
(9, 'Flow', 'Cat is a solitary animal, but as its home is devastated by a great flood, he finds refuge on a boat populated by various species, and will have to team up with them despite their differences.', 120, 'G', 'Adventure', '69e0b68266d24_1776334466.jpg', 'https://www.youtube.com/embed/82WW9dVbglI?si=SCM4lSPtFy8TTlaH', '', '2026-11-18', 200.00, '2026-04-16 10:14:26'),
(10, 'Day Zero', 'Day Zero (2022) is a Filipino action-horror film following Emon (Brandon Vera), a former elite soldier recently released from prison, who must navigate a society ravaged by a zombie virus to reach his estranged wife and deaf daughter. Directed by Joey De Guzman, this 1h 22m movie focuses on brutal combat as Emon fights through urban ruins to reunite with his family.', 120, 'R', 'Thriller, Action, Science Fiction', '69e0b6de1989a_1776334558.jpg', 'https://www.youtube.com/embed/d9ndpn4LaKs?si=y4nNQAOw3wAu1zoz', '', '2026-06-18', 350.00, '2026-04-16 10:15:58'),
(11, 'Kingsman', 'Gary \"Eggsy\" Unwin (Taron Egerton), whose late father secretly worked for a spy organization, lives in a South London housing estate and seems headed for a life behind bars. However, dapper agent Harry Hart (Colin Firth) recognizes potential in the youth and recruits him to be a trainee in the Secret Service. Meanwhile, villainous Richmond Valentine (Samuel L. Jackson) launches a diabolical plan to solve the problem of climate change via a worldwide killing spree.', 120, 'R', 'Action', '69e0b750718a1_1776334672.jpg', 'https://www.youtube.com/embed/t7ybRKVCUxM?si=Ek36qHRthHptMXXW', '', '2026-04-30', 350.00, '2026-04-16 10:17:52'),
(12, 'how to train your dragon the hidden world', 'How to Train Your Dragon: The Hidden World (2027) follows Chief Hiccup as he seeks a mythical dragon utopia to save Berk from overpopulation and the evil hunter Grimmel, who targets Toothless with a female Light Fury. As Toothless falls for the Light Fury, Hiccup learns to let go, leading his friends and dragons to safety and freedom', 120, 'G', 'Fantasy, Animation, Romance', '69e0b827e5c25_1776334887.jpg', 'https://www.youtube.com/embed/SkcucKDrbOI?si=_krmeT-euyOyxo6d', '', '2027-06-16', 299.99, '2026-04-16 10:21:27'),
(13, 'Lucy', 'When a boyfriend tricks Lucy (Scarlett Johansson) into delivering a briefcase to a supposed business contact, the once-carefree student is abducted by thugs who intend to turn her into a drug mule. She is surgically implanted with a package containing a powerful chemical, but it leaks into her system, giving her superhuman abilities, including telekinesis and telepathy. With her former captors in pursuit, Lucy seeks out a neurologist (Morgan Freeman), who she hopes will be able to help her.', 150, 'R', 'Action, Science Fiction,', '69e14cd80d6e7_1776372952.jpg', 'https://www.youtube.com/embed/bN7ksFEVO9U?si=GGssphQZz3w8X1gD', '', '2026-04-22', 299.99, '2026-04-16 20:55:52');

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
(1, 5, '2026-04-17', '10:00:00', 100, 0, 10.00, 'expired', '2026-04-16 05:28:48'),
(2, 4, '2026-04-17', '14:00:00', 100, 0, 10.00, 'expired', '2026-04-16 05:28:48'),
(3, 5, '2026-04-18', '18:00:00', 100, 0, 10.00, 'expired', '2026-04-16 05:28:48'),
(4, 5, '2026-04-18', '22:00:00', 100, 0, 10.00, 'expired', '2026-04-16 05:28:48'),
(5, 11, '2026-04-17', '01:00:00', 100, 0, 350.00, 'expired', '2026-04-16 20:46:12'),
(6, 11, '2026-04-17', '20:00:00', 100, 0, 350.00, 'expired', '2026-04-16 21:20:05'),
(7, 11, '2026-04-21', '05:00:00', 100, 0, 350.00, 'expired', '2026-04-21 02:14:54'),
(8, 6, '2026-04-21', '06:00:00', 100, 0, 300.00, 'expired', '2026-04-21 02:16:54'),
(9, 6, '2026-04-21', '08:00:00', 100, 0, 300.00, 'expired', '2026-04-21 02:17:11'),
(10, 6, '2026-04-21', '09:00:00', 100, 0, 300.00, 'expired', '2026-04-21 02:17:29'),
(11, 6, '2026-04-21', '10:00:00', 100, 1, 300.00, 'expired', '2026-04-21 02:17:42'),
(12, 6, '2026-04-21', '23:00:00', 100, 1, 300.00, 'expired', '2026-04-21 02:17:52'),
(13, 6, '2026-04-21', '11:00:00', 100, 1, 300.00, 'expired', '2026-04-21 02:18:01'),
(14, 6, '2026-04-21', '12:00:00', 100, 1, 300.00, 'expired', '2026-04-21 02:18:11'),
(15, 6, '2026-04-21', '13:00:00', 100, 1, 300.00, 'expired', '2026-04-21 02:18:29'),
(16, 6, '2026-04-21', '15:00:00', 100, 1, 300.00, 'expired', '2026-04-21 02:18:42'),
(17, 6, '2026-04-21', '16:00:00', 100, 0, 300.00, 'expired', '2026-04-21 02:18:51'),
(18, 6, '2026-04-21', '17:00:00', 100, 0, 300.00, 'expired', '2026-04-21 02:18:59'),
(19, 7, '2026-04-24', '00:00:00', 100, 0, 350.00, 'expired', '2026-04-23 15:57:01'),
(20, 7, '2026-04-24', '01:00:00', 100, 0, 350.00, 'expired', '2026-04-23 15:57:19'),
(21, 7, '2026-04-24', '02:00:00', 100, 0, 350.00, 'expired', '2026-04-23 15:57:35'),
(22, 7, '2026-04-24', '15:00:00', 100, 0, 350.00, 'expired', '2026-04-23 15:57:46'),
(23, 7, '2026-04-24', '04:00:00', 100, 0, 350.00, 'expired', '2026-04-23 15:57:59'),
(24, 7, '2026-04-24', '05:00:00', 100, 0, 350.00, 'expired', '2026-04-23 15:58:14'),
(25, 7, '2026-04-25', '08:00:00', 100, 0, 350.00, 'expired', '2026-04-23 15:58:33'),
(26, 7, '2026-04-30', '08:00:00', 100, 0, 350.00, 'scheduled', '2026-04-23 15:58:50'),
(27, 7, '2026-04-27', '08:00:00', 100, 0, 350.00, 'scheduled', '2026-04-23 15:59:09'),
(28, 6, '2026-04-30', '20:00:00', 100, 0, 300.00, 'scheduled', '2026-04-25 04:23:30'),
(29, 5, '2026-04-25', '20:00:00', 100, 0, 349.99, 'expired', '2026-04-25 15:09:12'),
(30, 5, '2026-04-25', '21:00:00', 100, 0, 349.99, 'expired', '2026-04-25 15:09:22'),
(31, 5, '2026-04-26', '20:00:00', 100, 0, 349.99, 'scheduled', '2026-04-25 15:09:36'),
(32, 5, '2026-04-27', '20:00:00', 100, 0, 349.99, 'scheduled', '2026-04-25 15:09:47'),
(33, 5, '2026-04-28', '20:00:00', 100, 0, 349.99, 'scheduled', '2026-04-25 15:09:55'),
(34, 5, '2026-04-29', '20:00:00', 100, 0, 349.99, 'scheduled', '2026-04-25 15:10:07'),
(35, 5, '2026-04-30', '20:00:00', 100, 0, 349.99, 'scheduled', '2026-04-25 15:10:26');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` varchar(20) DEFAULT 'pending',
  `transaction_id` varchar(200) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` varchar(255) DEFAULT NULL,
  `paymongo_checkout_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `ticket_id`, `amount`, `payment_method`, `payment_status`, `transaction_id`, `payment_date`, `description`, `paymongo_checkout_id`) VALUES
(40, 7, 40, 64.00, '', 'refund_pending', 'pay_9a8SKNLDhJiaBiYLef9swC2i', '2026-04-23 14:21:21', 'Booking BK-20260423-4188', 'cs_314ad051655e5acfbcb78b4d'),
(41, 7, 41, 400.00, '', 'completed', 'pay_xxBejTyuyWuWEUGFP8YCVZVt', '2026-04-23 14:59:52', 'Booking BK-20260423-7190', 'cs_fe8289aa87f1a7d6d8f3d81a'),
(42, 7, 42, 350.00, '', 'completed', 'pay_sjbg43KhYZeMgRbucsqfWJ7y', '2026-04-25 04:00:16', 'Booking BK-20260425-1260', 'cs_62888a582fdbe0d7f11002a0'),
(43, 7, 43, 350.00, '', 'completed', 'pay_BaUNoWbj2G22UKV1F2iB9bpf', '2026-04-25 04:05:51', 'Booking BK-20260425-4635', 'cs_8b6ae8018df90eea0a48cfb3'),
(49, 7, 49, 350.00, '', 'completed', 'pay_8NWvVZgzGqNmCGpFebfJPeT1', '2026-04-25 10:43:30', 'Booking BK-20260425-5449 - Girl Boy Bakla Tomboy', 'cs_e0968a2a8a43384da8f083b4'),
(50, 7, 50, 350.00, 'paymaya', 'pending', 'TXN1777118495631', '2026-04-25 12:01:35', NULL, 'cs_148dd5bf3c3a29f9984638ff'),
(51, 7, 51, 350.00, '', 'completed', 'pay_8XFVKJagYoSoDgmBvWt9jA8h', '2026-04-25 11:02:01', 'Booking BK-20260425-9266 - Girl Boy Bakla Tomboy', 'cs_dd022b013b411c49393ae5ff'),
(52, 7, 52, 350.00, '', 'completed', 'pay_ok6PoFQyPvmwRdqTp2yBXoYZ', '2026-04-25 12:36:56', 'Booking BK-20260425-3266 - Dr. Stone(Season 1)', 'cs_4d4679e83f531c19b43480f7'),
(53, 7, 53, 350.00, 'gcash', 'pending', 'TXN1777129289498', '2026-04-25 15:01:29', NULL, 'cs_d2df91f1fc8ca8f105a541e8'),
(54, 7, 54, 399.99, '', 'completed', 'pay_F2ur4EM4fnimRhMT3nXYmWoR', '2026-04-25 14:12:30', 'Booking BK-20260425-6571 - Deadpool 3', 'cs_56d4ffbd1bfcbde8c41fd238'),
(55, 7, 55, 399.99, '', 'completed', 'pay_RzsAmRYyHuawgUxqKtATyLj3', '2026-04-25 14:13:49', 'Booking BK-20260425-6605 - Deadpool 3', 'cs_ade4f1fc80015dc09ea92878');

-- --------------------------------------------------------

--
-- Table structure for table `screenings`
--

CREATE TABLE `screenings` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `cinema_id` int(11) NOT NULL,
  `screen_number` int(11) DEFAULT 1,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `available_seats` int(11) DEFAULT 40,
  `status` varchar(20) DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `screenings`
--

INSERT INTO `screenings` (`id`, `movie_id`, `cinema_id`, `screen_number`, `show_date`, `show_time`, `price`, `available_seats`, `status`, `created_at`) VALUES
(1, 2, 1, 1, '2026-04-17', '10:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(2, 4, 1, 1, '2026-04-17', '13:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(3, 4, 1, 1, '2026-04-18', '16:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(4, 2, 1, 1, '2026-04-19', '19:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(5, 5, 1, 1, '2026-04-23', '22:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(6, 5, 1, 2, '2026-04-17', '10:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(7, 1, 1, 2, '2026-04-17', '13:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(8, 3, 1, 2, '2026-04-18', '16:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(9, 3, 1, 2, '2026-04-19', '19:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(10, 3, 1, 2, '2026-04-23', '22:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(11, 4, 1, 3, '2026-04-17', '10:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(12, 5, 1, 3, '2026-04-17', '13:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(13, 4, 1, 3, '2026-04-18', '16:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(14, 3, 1, 3, '2026-04-19', '19:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(15, 1, 1, 3, '2026-04-23', '22:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(16, 1, 2, 1, '2026-04-17', '10:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(17, 2, 2, 1, '2026-04-17', '13:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(18, 5, 2, 1, '2026-04-18', '16:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(19, 3, 2, 1, '2026-04-19', '19:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(20, 4, 2, 1, '2026-04-23', '22:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(21, 5, 2, 2, '2026-04-17', '10:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(22, 4, 2, 2, '2026-04-17', '13:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(23, 5, 2, 2, '2026-04-18', '16:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(24, 3, 2, 2, '2026-04-19', '19:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(25, 1, 2, 2, '2026-04-23', '22:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(26, 4, 2, 3, '2026-04-17', '10:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(27, 3, 2, 3, '2026-04-17', '13:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(28, 5, 2, 3, '2026-04-18', '16:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(29, 5, 2, 3, '2026-04-19', '19:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(30, 5, 2, 3, '2026-04-23', '22:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(31, 2, 3, 1, '2026-04-17', '10:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(32, 5, 3, 1, '2026-04-17', '13:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(33, 5, 3, 1, '2026-04-18', '16:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(34, 1, 3, 1, '2026-04-19', '19:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(35, 3, 3, 1, '2026-04-23', '22:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(36, 1, 3, 2, '2026-04-17', '10:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(37, 2, 3, 2, '2026-04-17', '13:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(38, 1, 3, 2, '2026-04-18', '16:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(39, 3, 3, 2, '2026-04-19', '19:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(40, 1, 3, 2, '2026-04-23', '22:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(41, 3, 3, 3, '2026-04-17', '10:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(42, 3, 3, 3, '2026-04-17', '13:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(43, 1, 3, 3, '2026-04-18', '16:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(44, 1, 3, 3, '2026-04-19', '19:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(45, 2, 3, 3, '2026-04-23', '22:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(46, 5, 4, 1, '2026-04-17', '10:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(47, 2, 4, 1, '2026-04-17', '13:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(48, 4, 4, 1, '2026-04-18', '16:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(49, 1, 4, 1, '2026-04-19', '19:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(50, 1, 4, 1, '2026-04-23', '22:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(51, 2, 4, 2, '2026-04-17', '10:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(52, 2, 4, 2, '2026-04-17', '13:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(53, 5, 4, 2, '2026-04-18', '16:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(54, 1, 4, 2, '2026-04-19', '19:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(55, 5, 4, 2, '2026-04-23', '22:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:47'),
(56, 4, 4, 3, '2026-04-17', '10:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(57, 5, 4, 3, '2026-04-17', '13:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(58, 2, 4, 3, '2026-04-18', '16:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(59, 5, 4, 3, '2026-04-19', '19:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(60, 2, 4, 3, '2026-04-23', '22:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:47'),
(61, 3, 5, 1, '2026-04-17', '10:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(62, 3, 5, 1, '2026-04-17', '13:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(63, 2, 5, 1, '2026-04-18', '16:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(64, 4, 5, 1, '2026-04-19', '19:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(65, 3, 5, 1, '2026-04-23', '22:00:00', 13.00, 40, 'expired', '2026-04-16 05:28:47'),
(66, 2, 5, 2, '2026-04-17', '10:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:48'),
(67, 5, 5, 2, '2026-04-17', '13:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:48'),
(68, 4, 5, 2, '2026-04-18', '16:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:48'),
(69, 2, 5, 2, '2026-04-19', '19:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:48'),
(70, 4, 5, 2, '2026-04-23', '22:00:00', 13.50, 40, 'expired', '2026-04-16 05:28:48'),
(71, 5, 5, 3, '2026-04-17', '10:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:48'),
(72, 1, 5, 3, '2026-04-17', '13:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:48'),
(73, 3, 5, 3, '2026-04-18', '16:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:48'),
(74, 4, 5, 3, '2026-04-19', '19:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:48'),
(75, 5, 5, 3, '2026-04-23', '22:00:00', 14.00, 40, 'expired', '2026-04-16 05:28:48'),
(76, 11, 5, 2, '2026-04-17', '12:00:00', 350.00, 40, 'expired', '2026-04-16 20:45:36'),
(77, 13, 6, 2, '2026-04-17', '20:00:00', 299.99, 40, 'expired', '2026-04-16 21:04:04'),
(78, 11, 4, 2, '2026-04-17', '19:00:00', 350.00, 40, 'expired', '2026-04-16 21:12:11'),
(79, 11, 4, 2, '2026-04-17', '20:00:00', 350.00, 40, 'expired', '2026-04-16 21:30:40'),
(80, 4, 4, 1, '2026-04-17', '08:00:00', 300.00, 40, 'expired', '2026-04-16 23:29:21'),
(81, 5, 6, 1, '2026-04-17', '21:00:00', 16.00, 40, 'expired', '2026-04-16 23:36:49'),
(82, 6, 3, 3, '2026-04-20', '21:00:00', 300.00, 40, 'expired', '2026-04-18 09:57:40'),
(83, 6, 4, 5, '2026-04-21', '19:00:00', 300.00, 40, 'expired', '2026-04-18 10:07:07'),
(84, 8, 4, 2, '2026-04-21', '17:00:00', 300.00, 40, 'expired', '2026-04-18 14:49:37'),
(85, 6, 4, 2, '2026-04-30', '19:00:00', 300.00, 40, 'scheduled', '2026-04-25 04:22:17'),
(86, 6, 3, 2, '2026-04-30', '20:00:00', 300.00, 39, 'scheduled', '2026-04-25 04:22:35'),
(87, 6, 3, 3, '2026-04-30', '09:00:00', 300.00, 40, 'scheduled', '2026-04-25 04:23:07'),
(88, 8, 3, 1, '2026-05-25', '19:00:00', 300.00, 50, 'scheduled', '2026-04-25 12:38:08'),
(89, 8, 3, 1, '2026-05-30', '19:00:00', 300.00, 50, 'scheduled', '2026-04-25 13:02:51'),
(90, 5, 3, 1, '2026-04-25', '19:00:00', 349.99, 50, 'expired', '2026-04-25 15:10:48'),
(91, 5, 4, 3, '2026-04-27', '19:00:00', 349.99, 40, 'scheduled', '2026-04-25 15:11:04'),
(92, 5, 5, 3, '2026-04-30', '19:00:00', 349.99, 40, 'scheduled', '2026-04-25 15:11:20'),
(93, 5, 2, 5, '2026-05-02', '19:00:00', 349.99, 40, 'scheduled', '2026-04-25 15:11:46');

-- --------------------------------------------------------

--
-- Table structure for table `staff_cinemas`
--

CREATE TABLE `staff_cinemas` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `cinema_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_cinemas`
--

INSERT INTO `staff_cinemas` (`id`, `staff_id`, `cinema_id`, `assigned_by`, `created_at`) VALUES
(1, 8, 3, 6, '2026-04-25 12:16:21');

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
  `ticket_type` varchar(20) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `total_price` decimal(10,2) NOT NULL,
  `seat_numbers` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `purchase_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `week_expiry` date DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_code`, `user_id`, `screening_id`, `online_schedule_id`, `ticket_type`, `quantity`, `total_price`, `seat_numbers`, `status`, `purchase_date`, `week_expiry`, `used_at`, `verified_by`, `cancelled_at`, `cancelled_by`) VALUES
(40, 'TKT-69EA38F110D92-20260423', 9, 75, NULL, 'cinema', 1, 64.00, 'B3', 'cancelled', '2026-04-23 15:21:21', NULL, NULL, NULL, '2026-04-25 10:47:56', 6),
(41, 'TKT-69EA41F880A80-20260423', 7, NULL, 19, 'online', 1, 400.00, NULL, 'paid', '2026-04-23 15:59:52', '2026-05-01', NULL, NULL, NULL, NULL),
(42, 'TKT-69EC4A5FC07AD-20260425', 7, 85, NULL, 'cinema', 1, 350.00, 'B6', 'cancelled', '2026-04-25 05:00:15', NULL, NULL, NULL, '2026-04-25 10:39:53', 1),
(43, 'TKT-69EC4BAE62F1C-20260425', 7, NULL, 28, 'online', 1, 350.00, NULL, 'paid', '2026-04-25 05:05:50', '2026-05-07', NULL, NULL, NULL, NULL),
(49, 'TKT-69ECA8E209130-20260425', 7, 86, NULL, 'cinema', 1, 350.00, 'B4', 'used', '2026-04-25 11:43:30', NULL, '2026-04-25 12:04:32', 8, NULL, NULL),
(50, 'TKT-69ECAD1FD3D9D-20260425', 7, 85, NULL, 'cinema', 1, 350.00, 'B2', 'pending', '2026-04-25 12:01:35', NULL, NULL, NULL, NULL, NULL),
(51, 'TKT-69ECAD394728E-20260425', 7, 87, NULL, 'cinema', 1, 350.00, 'B5', 'paid', '2026-04-25 12:02:01', NULL, NULL, NULL, NULL, NULL),
(52, 'TKT-69ECC3788C380-20260425', 7, 88, NULL, 'cinema', 1, 350.00, 'C5', 'paid', '2026-04-25 13:36:56', NULL, NULL, NULL, NULL, NULL),
(53, 'TKT-69ECD749670B1-20260425', 7, 86, NULL, 'cinema', 1, 350.00, 'F3', 'pending', '2026-04-25 15:01:29', NULL, NULL, NULL, NULL, NULL),
(54, 'TKT-69ECD9DE436F4-20260425', 7, 91, NULL, 'cinema', 1, 399.99, 'D4', 'paid', '2026-04-25 15:12:30', NULL, NULL, NULL, NULL, NULL),
(55, 'TKT-69ECDA2DB13E3-20260425', 7, NULL, 29, 'online', 1, 399.99, NULL, 'paid', '2026-04-25 15:13:49', '2026-05-02', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthdate` date NOT NULL,
  `account_type` varchar(20) DEFAULT 'user',
  `gender` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `theme_preference` varchar(20) DEFAULT 'dark',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `birthdate`, `account_type`, `gender`, `country`, `phone`, `profile_pic`, `theme_preference`, `is_active`, `created_at`, `last_login`) VALUES
(6, 'jciscrazy', 'jc@gmail.com', '$2y$10$SFFaDp1z.vskn2j.RfPpcO3w4Q8.OKlYAqg.J0Sj.otOkHNhWt/Wi', 'Jake', 'Cruz', '2000-02-08', 'admin', 'male', 'PH', '+639874452100', 'profile_6_1776333413.jpg', 'dark', 1, '2026-04-16 05:37:35', '2026-04-26 02:19:06'),
(7, 'Dandadan', 'dan@gmail.com', '$2y$10$m8RwBSp5YIdAduod6mWYrOgp8Pcdpk4bJ9S.J3WLN6EU7kUG/pFkG', 'Danilo', 'Roger', '2000-05-31', 'adult', 'male', 'PH', '639478852699', 'profile_7_1776732083.png', 'dark', 1, '2026-04-16 20:37:16', '2026-04-26 01:18:09'),
(8, 'HIRAYA', 'hi@gmail.com', '$2y$10$7xXYZU8NWyqJYwD7iPBqDeuHZer6WHGGS6.8gaN5BiRg7qp9pz7fa', 'Halia', 'Nimaya', '2000-01-01', 'staff', 'other', 'PH', '0000000000', 'profile_8_1776522615.jpg', 'dark', 1, '2026-04-16 21:22:05', '2026-04-26 01:37:19'),
(9, 'tantanyy', 'tanni@gmail.com', '$2y$10$Pn8ZSQV/if6bhNVf7R3DleLxLCFgVpkVzkhz2cBR8bFe5Z4fbrsHi', 'Tanya', 'Gil', '2018-07-06', 'kid', 'female', 'PH', '+630000000000', 'profile_9_1776523068.jpg', 'dark', 1, '2026-04-16 22:28:17', '2026-04-21 06:46:34'),
(10, 'WELYN', 'ruelyn84@gmail.com', '$2y$10$KsPlLzsXRm/47Rv30e.Wg.LF.BRxPzjBBJir8T79uB/uKH3G4raM6', 'RUELYN', 'TOLENTIN', '2006-04-20', 'adult', 'female', 'PH', '+639615774280', NULL, 'dark', 1, '2026-04-21 03:44:12', '2026-04-21 03:44:35'),
(11, 'ruen', 'rt@gmail.com', '$2y$10$rjVwG0Qfn79GLo6lq1.9VO3606WmCCdzGoNtwInaY2CgTPAB8lVwm', 'ruelyn', 'tolen', '2010-03-25', 'teen', 'female', 'PH', '0000000000', NULL, 'dark', 1, '2026-04-21 06:48:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `profile_type` enum('adult','teen','kid') DEFAULT 'adult',
  `avatar` varchar(255) DEFAULT NULL,
  `pin` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `profile_name`, `profile_type`, `avatar`, `pin`, `is_active`, `created_at`) VALUES
(1, 7, 'Ivan', 'kid', 'cool.png', NULL, 1, '2026-04-23 08:35:25'),
(2, 7, 'Dan Cruz', 'adult', 'father.png', '$2y$10$/dJoULtTIVXxZj3f1K6No.mImUeWi7UK.ZYFgdS5prr6KE1lMR45G', 1, '2026-04-23 08:37:51'),
(3, 7, 'Winnie', 'teen', 'pinkshirtgirl.png', NULL, 1, '2026-04-25 13:36:25');

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
  `watched_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed` tinyint(1) DEFAULT 0,
  `watch_duration` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `watch_history`
--

INSERT INTO `watch_history` (`id`, `user_id`, `movie_id`, `watched_at`, `completed`, `watch_duration`) VALUES
(1, 7, 6, '2026-04-21 02:23:39', 1, 0),
(2, 7, 6, '2026-04-21 02:25:45', 1, 0),
(3, 7, 6, '2026-04-21 02:26:46', 1, 0),
(4, 7, 6, '2026-04-21 03:07:19', 1, 0),
(5, 7, 6, '2026-04-21 03:08:14', 1, 0),
(6, 7, 6, '2026-04-21 03:10:05', 1, 0),
(7, 7, 6, '2026-04-21 06:38:46', 1, 0),
(8, 7, 6, '2026-04-21 06:43:46', 1, 0),
(9, 7, 7, '2026-04-25 09:23:13', 0, 0),
(10, 7, 5, '2026-04-25 15:14:22', 0, 0);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`movie_id`),
  ADD KEY `movie_id` (`movie_id`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `online_schedule`
--
ALTER TABLE `online_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_online_show` (`movie_id`,`show_date`,`show_time`),
  ADD KEY `idx_online_date` (`show_date`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `idx_status` (`payment_status`);

--
-- Indexes for table `screenings`
--
ALTER TABLE `screenings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `cinema_id` (`cinema_id`),
  ADD KEY `idx_show_date` (`show_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `staff_cinemas`
--
ALTER TABLE `staff_cinemas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_staff_cinema` (`staff_id`,`cinema_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_cinema` (`cinema_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_code` (`ticket_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `screening_id` (`screening_id`),
  ADD KEY `online_schedule_id` (`online_schedule_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_code` (`ticket_code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `watch_history`
--
ALTER TABLE `watch_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `idx_user_watched` (`user_id`,`watched_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cinemas`
--
ALTER TABLE `cinemas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `online_schedule`
--
ALTER TABLE `online_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `screenings`
--
ALTER TABLE `screenings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `staff_cinemas`
--
ALTER TABLE `staff_cinemas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `watch_history`
--
ALTER TABLE `watch_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- Constraints for table `online_schedule`
--
ALTER TABLE `online_schedule`
  ADD CONSTRAINT `online_schedule_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `screenings`
--
ALTER TABLE `screenings`
  ADD CONSTRAINT `screenings_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `screenings_ibfk_2` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_cinemas`
--
ALTER TABLE `staff_cinemas`
  ADD CONSTRAINT `staff_cinemas_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_cinemas_ibfk_2` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_cinemas_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`online_schedule_id`) REFERENCES `online_schedule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
