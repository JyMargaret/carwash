-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 04:38 AM
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
-- Database: `smartwash_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_loyalty_points` (IN `p_customer_id` INT, IN `p_amount` DECIMAL(10,2))   BEGIN
    DECLARE points_earned INT;
    DECLARE points_per_peso INT;
    
    SELECT CAST(setting_value AS UNSIGNED) INTO points_per_peso 
    FROM system_settings 
    WHERE setting_key = 'points_per_peso';
    
    SET points_earned = FLOOR(p_amount * points_per_peso);
    
    UPDATE customers 
    SET loyalty_points = loyalty_points + points_earned
    WHERE customer_id = p_customer_id;
    
    INSERT INTO loyalty_transactions (customer_id, points_change, transaction_type, balance_after)
    SELECT p_customer_id, points_earned, 'Earned', loyalty_points
    FROM customers
    WHERE customer_id = p_customer_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_membership_tier` (IN `p_customer_id` INT)   BEGIN
    DECLARE current_points INT;
    DECLARE new_tier VARCHAR(20);
    
    SELECT loyalty_points INTO current_points
    FROM customers
    WHERE customer_id = p_customer_id;
    
    CASE
        WHEN current_points >= 2000 THEN SET new_tier = 'Platinum';
        WHEN current_points >= 1000 THEN SET new_tier = 'Gold';
        WHEN current_points >= 500 THEN SET new_tier = 'Silver';
        ELSE SET new_tier = 'Bronze';
    END CASE;
    
    UPDATE customers
    SET membership_tier = new_tier
    WHERE customer_id = p_customer_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `status` enum('Pending','Confirmed','In Progress','Completed','Cancelled','No Show') DEFAULT 'Pending',
  `bay_number` varchar(10) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('Pending','Paid','Refunded') DEFAULT 'Pending',
  `payment_method` enum('Cash','Card','GCash','PayMaya','Bank Transfer') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `bookings`
--
DELIMITER $$
CREATE TRIGGER `tr_after_booking_complete` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF NEW.status = 'Completed' AND OLD.status != 'Completed' THEN
        UPDATE customers
        SET total_spent = total_spent + NEW.final_amount,
            total_visits = total_visits + 1
        WHERE customer_id = NEW.customer_id;
        
        CALL sp_calculate_loyalty_points(NEW.customer_id, NEW.final_amount);
        CALL sp_update_membership_tier(NEW.customer_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `booking_addons`
--

CREATE TABLE `booking_addons` (
  `booking_addon_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_hours`
--

CREATE TABLE `business_hours` (
  `hours_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `open_time` time NOT NULL,
  `close_time` time NOT NULL,
  `is_open` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_hours`
--

INSERT INTO `business_hours` (`hours_id`, `day_of_week`, `open_time`, `close_time`, `is_open`) VALUES
(1, 'Monday', '08:00:00', '18:00:00', 1),
(2, 'Tuesday', '08:00:00', '18:00:00', 1),
(3, 'Wednesday', '08:00:00', '18:00:00', 1),
(4, 'Thursday', '08:00:00', '18:00:00', 1),
(5, 'Friday', '08:00:00', '18:00:00', 1),
(6, 'Saturday', '08:00:00', '17:00:00', 1),
(7, 'Sunday', '09:00:00', '15:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `membership_tier` enum('Bronze','Silver','Gold','Platinum') DEFAULT 'Bronze',
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `total_visits` int(11) DEFAULT 0,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_rewards`
--

CREATE TABLE `customer_rewards` (
  `customer_reward_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `status` enum('Available','Used','Expired') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_reports`
--

CREATE TABLE `daily_reports` (
  `report_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `total_bookings` int(11) DEFAULT 0,
  `completed_bookings` int(11) DEFAULT 0,
  `cancelled_bookings` int(11) DEFAULT 0,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `total_customers` int(11) DEFAULT 0,
  `new_customers` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_code` varchar(20) NOT NULL,
  `position` varchar(50) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_tasks_completed` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `user_id`, `employee_code`, `position`, `hire_date`, `hourly_rate`, `rating`, `total_tasks_completed`, `is_active`, `name`) VALUES
(1, 3, 'EMP001', 'Car Wash Technician', '2024-01-15', 100.00, 0.00, 0, 1, ''),
(2, 9, 'EMP002', 'Senior Car Wash Technician', '2023-01-15', 120.00, 4.90, 342, 1, ''),
(3, 10, 'EMP003', 'Car Wash Technician', '2023-06-01', 100.00, 4.70, 256, 1, ''),
(4, 11, 'EMP004', 'Car Wash Technician', '2024-02-10', 100.00, 4.80, 189, 1, ''),
(5, 12, 'EMP005', 'Junior Car Wash Technician', '2024-08-01', 85.00, 4.60, 98, 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `employee_attendance`
--

CREATE TABLE `employee_attendance` (
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `clock_in` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `clock_out` timestamp NULL DEFAULT NULL,
  `shift_date` date NOT NULL,
  `total_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('On Duty','Off Duty','On Break') DEFAULT 'On Duty',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_breaks`
--

CREATE TABLE `employee_breaks` (
  `break_id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `break_start` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `break_end` timestamp NULL DEFAULT NULL,
  `break_duration_minutes` int(11) DEFAULT 0,
  `break_type` enum('Lunch','Rest','Emergency') DEFAULT 'Rest'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` enum('Cleaning Supplies','Equipment','Tools','Consumables','Other') NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 0,
  `unit` varchar(20) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `last_restocked` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `transaction_type` enum('Purchase','Usage','Adjustment','Waste') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_transactions`
--

CREATE TABLE `loyalty_transactions` (
  `transaction_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `points_change` int(11) NOT NULL,
  `transaction_type` enum('Earned','Redeemed','Expired','Adjusted') NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `balance_after` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('Booking','Payment','Promotion','System','Alert') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Card','GCash','PayMaya','Bank Transfer') NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `payment_status` enum('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `service_quality_rating` int(11) DEFAULT NULL CHECK (`service_quality_rating` >= 1 and `service_quality_rating` <= 5),
  `cleanliness_rating` int(11) DEFAULT NULL CHECK (`cleanliness_rating` >= 1 and `cleanliness_rating` <= 5),
  `speed_rating` int(11) DEFAULT NULL CHECK (`speed_rating` >= 1 and `speed_rating` <= 5),
  `is_verified` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `reward_id` int(11) NOT NULL,
  `reward_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_required` int(11) NOT NULL,
  `reward_type` enum('Discount','Free Service','Upgrade','Gift') NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`reward_id`, `reward_name`, `description`, `points_required`, `reward_type`, `discount_amount`, `discount_percentage`, `is_active`, `valid_from`, `valid_until`) VALUES
(1, '10% Off Next Wash', 'Get 10% discount on your next service', 100, 'Discount', 0.00, 10.00, 1, NULL, NULL),
(2, 'Free Basic Wash', 'Complimentary basic wash service', 250, 'Free Service', 0.00, 0.00, 1, NULL, NULL),
(3, 'Free Premium Upgrade', 'Upgrade to Premium wash for free', 400, 'Upgrade', 0.00, 0.00, 1, NULL, NULL),
(4, '?500 Discount', 'Get ?500 off any service', 500, 'Discount', 0.00, 0.00, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `service_type` enum('Basic','Premium','Ultimate','Custom') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `description`, `base_price`, `duration_minutes`, `service_type`, `is_active`, `created_at`, `updated_at`, `price`) VALUES
(1, 'Basic Wash', 'Exterior Wash, Wheel Cleaning, Basic Dry, Air Freshener', 250.00, 15, 'Basic', 1, '2025-11-17 02:34:42', '2025-11-17 02:34:42', 0.00),
(2, 'Premium Wash', 'Everything in Basic + Interior Vacuum, Tire Shine, Wax Protection, Dashboard Polish', 450.00, 30, 'Premium', 1, '2025-11-17 02:34:42', '2025-11-17 02:34:42', 0.00),
(3, 'Ultimate Wash', 'Everything in Premium + Engine Bay Cleaning, Leather Treatment, Ceramic Coating, Headlight Restoration', 750.00, 60, 'Ultimate', 1, '2025-11-17 02:34:42', '2025-11-17 02:34:42', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `service_addons`
--

CREATE TABLE `service_addons` (
  `addon_id` int(11) NOT NULL,
  `addon_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_addons`
--

INSERT INTO `service_addons` (`addon_id`, `addon_name`, `description`, `price`, `is_active`) VALUES
(1, 'Engine Bay Cleaning', 'Deep clean of engine compartment', 300.00, 1),
(2, 'Undercarriage Wash', 'Thorough cleaning underneath vehicle', 200.00, 1),
(3, 'Pet Hair Removal', 'Specialized pet hair cleaning', 150.00, 1),
(4, 'Odor Removal', 'Deep odor elimination treatment', 250.00, 1),
(5, 'Leather Conditioning', 'Premium leather care treatment', 200.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('String','Number','Boolean','JSON') DEFAULT 'String',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'points_per_peso', '1', 'Number', 'Loyalty points earned per peso spent', '2025-11-17 02:34:43'),
(2, 'bronze_threshold', '0', 'Number', 'Points needed for Bronze tier', '2025-11-17 02:34:43'),
(3, 'silver_threshold', '500', 'Number', 'Points needed for Silver tier', '2025-11-17 02:34:43'),
(4, 'gold_threshold', '1000', 'Number', 'Points needed for Gold tier', '2025-11-17 02:34:43'),
(5, 'platinum_threshold', '2000', 'Number', 'Points needed for Platinum tier', '2025-11-17 02:34:43'),
(6, 'booking_advance_days', '30', 'Number', 'Maximum days in advance for booking', '2025-11-17 02:34:43'),
(7, 'cancellation_hours', '24', 'Number', 'Minimum hours before booking for cancellation', '2025-11-17 02:34:43');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `task_name` varchar(100) NOT NULL,
  `priority` enum('Low','Normal','Urgent') DEFAULT 'Normal',
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `tasks`
--
DELIMITER $$
CREATE TRIGGER `tr_after_task_complete` AFTER UPDATE ON `tasks` FOR EACH ROW BEGIN
    IF NEW.status = 'Completed' AND OLD.status != 'Completed' THEN
        UPDATE employees
        SET total_tasks_completed = total_tasks_completed + 1
        WHERE employee_id = NEW.employee_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_type` enum('customer','employee','admin') NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `user_type`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin@smartwash.com', 'admin123', 'Admin', 'User', '+639171234567', 'admin', 'active', '2025-11-17 02:34:42', '2025-11-17 02:46:33', NULL),
(2, 'user@smartwash.com', 'user123', 'Customer', 'User', '+639171234568', 'customer', 'active', '2025-11-17 02:34:42', '2025-11-17 02:46:42', NULL),
(3, 'employee@smartwash.com', 'employee123', 'Employee', 'User', '+639171234569', 'employee', 'active', '2025-11-17 02:34:42', '2025-11-17 02:51:42', NULL),
(4, 'juan.delacruz@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan', 'Dela Cruz', '+639171234570', 'customer', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(5, 'maria.santos@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria', 'Santos', '+639171234571', 'customer', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(6, 'pedro.garcia@yahoo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro', 'Garcia', '+639171234572', 'customer', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(7, 'ana.reyes@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana', 'Reyes', '+639171234573', 'customer', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(8, 'carlos.mendoza@outlook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos', 'Mendoza', '+639171234574', 'customer', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(9, 'mark.santos@smartwash.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mark', 'Santos', '+639171234580', 'employee', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(10, 'jose.rivera@smartwash.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jose', 'Rivera', '+639171234581', 'employee', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(11, 'angel.cruz@smartwash.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Angel', 'Cruz', '+639171234582', 'employee', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(12, 'robert.fernandez@smartwash.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Robert', 'Fernandez', '+639171234583', 'employee', 'active', '2025-11-17 02:34:42', '2025-11-17 02:34:42', NULL),
(13, 'wayne@gmail.com', '$2y$10$o0Egy34M.T31kz/DXnmfj.1EegMF2Z77HgRu9A3p.Uok2bSoGt8jK', 'Wayne', '', '09362748263', 'customer', 'active', '2025-11-17 02:37:51', '2025-11-17 02:37:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `make` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` int(11) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `plate_number` varchar(20) NOT NULL,
  `vehicle_type` enum('Sedan','SUV','Truck','Van','Motorcycle','Other') NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_customer_bookings`
-- (See below for the actual view)
--
CREATE TABLE `v_customer_bookings` (
`booking_id` int(11)
,`booking_date` date
,`booking_time` time
,`status` enum('Pending','Confirmed','In Progress','Completed','Cancelled','No Show')
,`final_amount` decimal(10,2)
,`customer_id` int(11)
,`customer_name` varchar(101)
,`email` varchar(100)
,`phone` varchar(20)
,`service_name` varchar(100)
,`vehicle` varchar(101)
,`plate_number` varchar(20)
,`employee_name` varchar(101)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_employee_performance`
-- (See below for the actual view)
--
CREATE TABLE `v_employee_performance` (
`employee_id` int(11)
,`employee_name` varchar(101)
,`position` varchar(50)
,`rating` decimal(3,2)
,`total_tasks_completed` int(11)
,`total_bookings` bigint(21)
,`average_customer_rating` decimal(14,4)
,`total_revenue_generated` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Structure for view `v_customer_bookings`
--
DROP TABLE IF EXISTS `v_customer_bookings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_customer_bookings`  AS SELECT `b`.`booking_id` AS `booking_id`, `b`.`booking_date` AS `booking_date`, `b`.`booking_time` AS `booking_time`, `b`.`status` AS `status`, `b`.`final_amount` AS `final_amount`, `c`.`customer_id` AS `customer_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `customer_name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `s`.`service_name` AS `service_name`, concat(`v`.`make`,' ',`v`.`model`) AS `vehicle`, `v`.`plate_number` AS `plate_number`, concat(`e`.`first_name`,' ',`e`.`last_name`) AS `employee_name` FROM ((((((`bookings` `b` join `customers` `c` on(`b`.`customer_id` = `c`.`customer_id`)) join `users` `u` on(`c`.`user_id` = `u`.`user_id`)) join `services` `s` on(`b`.`service_id` = `s`.`service_id`)) join `vehicles` `v` on(`b`.`vehicle_id` = `v`.`vehicle_id`)) left join `employees` `emp` on(`b`.`employee_id` = `emp`.`employee_id`)) left join `users` `e` on(`emp`.`user_id` = `e`.`user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_employee_performance`
--
DROP TABLE IF EXISTS `v_employee_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_employee_performance`  AS SELECT `emp`.`employee_id` AS `employee_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `employee_name`, `emp`.`position` AS `position`, `emp`.`rating` AS `rating`, `emp`.`total_tasks_completed` AS `total_tasks_completed`, count(distinct `b`.`booking_id`) AS `total_bookings`, coalesce(avg(`r`.`rating`),0) AS `average_customer_rating`, sum(case when `b`.`status` = 'Completed' then `b`.`final_amount` else 0 end) AS `total_revenue_generated` FROM (((`employees` `emp` join `users` `u` on(`emp`.`user_id` = `u`.`user_id`)) left join `bookings` `b` on(`emp`.`employee_id` = `b`.`employee_id`)) left join `reviews` `r` on(`emp`.`employee_id` = `r`.`employee_id`)) GROUP BY `emp`.`employee_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `idx_booking_date` (`booking_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_booking_date_status` (`booking_date`,`status`);

--
-- Indexes for table `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD PRIMARY KEY (`booking_addon_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `addon_id` (`addon_id`);

--
-- Indexes for table `business_hours`
--
ALTER TABLE `business_hours`
  ADD PRIMARY KEY (`hours_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_customer_loyalty` (`loyalty_points`);

--
-- Indexes for table `customer_rewards`
--
ALTER TABLE `customer_rewards`
  ADD PRIMARY KEY (`customer_reward_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `reward_id` (`reward_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `daily_reports`
--
ALTER TABLE `daily_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `report_date` (`report_date`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `idx_employee_rating` (`rating`);

--
-- Indexes for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_shift_date` (`shift_date`);

--
-- Indexes for table `employee_breaks`
--
ALTER TABLE `employee_breaks`
  ADD PRIMARY KEY (`break_id`),
  ADD KEY `attendance_id` (`attendance_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_sender` (`sender_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read` (`is_read`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_booking` (`booking_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`reward_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `service_addons`
--
ALTER TABLE `service_addons`
  ADD PRIMARY KEY (`addon_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_plate` (`plate_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_addons`
--
ALTER TABLE `booking_addons`
  MODIFY `booking_addon_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_hours`
--
ALTER TABLE `business_hours`
  MODIFY `hours_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_rewards`
--
ALTER TABLE `customer_rewards`
  MODIFY `customer_reward_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_reports`
--
ALTER TABLE `daily_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_breaks`
--
ALTER TABLE `employee_breaks`
  MODIFY `break_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rewards`
--
ALTER TABLE `rewards`
  MODIFY `reward_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `service_addons`
--
ALTER TABLE `service_addons`
  MODIFY `addon_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`),
  ADD CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD CONSTRAINT `booking_addons_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_addons_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `service_addons` (`addon_id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_rewards`
--
ALTER TABLE `customer_rewards`
  ADD CONSTRAINT `customer_rewards_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_rewards_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`reward_id`),
  ADD CONSTRAINT `customer_rewards_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD CONSTRAINT `employee_attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_breaks`
--
ALTER TABLE `employee_breaks`
  ADD CONSTRAINT `employee_breaks_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `employee_attendance` (`attendance_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD CONSTRAINT `loyalty_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loyalty_transactions_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
