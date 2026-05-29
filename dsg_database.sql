-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 09, 2026 at 12:52 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dsg_database`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `delete_doctor_with_photos`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `delete_doctor_with_photos` (IN `doctor_id_param` INT)   BEGIN
    DECLARE photo_path VARCHAR(500);
    DECLARE done INT DEFAULT FALSE;
    
    -- Cursor to collect all photo paths from doctors table
    DECLARE photo_cursor CURSOR FOR
        SELECT profile_photo FROM doctors WHERE id = doctor_id_param
        UNION
        SELECT profile_photo_thumb FROM doctors WHERE id = doctor_id_param
        UNION
        SELECT clinic_photo FROM doctors WHERE id = doctor_id_param
        UNION
        SELECT degree_certificate_image FROM doctors WHERE id = doctor_id_param
        UNION
        SELECT pmdc_certificate_image FROM doctors WHERE id = doctor_id_param
        UNION
        SELECT signature_image FROM doctors WHERE id = doctor_id_param;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Delete from photo_gallery table first
    DELETE FROM photo_gallery WHERE entity_type = 'doctor' AND entity_id = doctor_id_param;
    
    -- Note: Actual file deletion must be done by PHP script
    -- This procedure only marks deletion in database
    
    -- Delete the doctor record
    DELETE FROM doctors WHERE id = doctor_id_param;
    
    -- Log the deletion (optional)
    INSERT INTO audit_logs (admin_id, admin_role, action, target_type, target_id, ip_address, user_agent)
    VALUES (0, 'system', 'doctor_deleted_with_photos', 'doctor', doctor_id_param, 'system', 'stored_procedure');
    
END$$

DROP PROCEDURE IF EXISTS `generate_ref_code`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_ref_code` (OUT `ref_code` VARCHAR(50))   BEGIN
    DECLARE new_code VARCHAR(50);
    DECLARE code_exists INT DEFAULT 1;
    
    WHILE code_exists > 0 DO
        SET new_code = CONCAT('DSG-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', 
                              UPPER(SUBSTRING(MD5(RAND()), 1, 6)));
        SELECT COUNT(*) INTO code_exists FROM `payments` WHERE `ref_code` = new_code;
    END WHILE;
    
    SET ref_code = new_code;
END$$

DROP PROCEDURE IF EXISTS `update_doctor_rating`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_doctor_rating` (IN `doctor_id_param` INT)   BEGIN
    DECLARE avg_rating DECIMAL(2,1);
    DECLARE total_reviews INT;
    
    SELECT AVG(rating), COUNT(*) INTO avg_rating, total_reviews
    FROM `reviews`
    WHERE `doctor_id` = doctor_id_param AND `is_approved` = 1;
    
    UPDATE `doctors`
    SET `average_rating` = COALESCE(avg_rating, 0),
        `total_reviews` = COALESCE(total_reviews, 0)
    WHERE `id` = doctor_id_param;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `doctor_id` int UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `time_slot` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., 10:30 AM - 11:00 AM',
  `location_type` enum('clinic','video') COLLATE utf8mb4_unicode_ci NOT NULL,
  `consultation_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `fees_amount` decimal(10,2) NOT NULL,
  `status` enum('pending_payment','payment_verified','confirmed','completed','cancelled','no_show') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `payment_ref_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `google_meet_link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Generated after payment verification for video',
  `patient_symptoms` text COLLATE utf8mb4_unicode_ci,
  `clinical_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Added by doctor after consult',
  `prescription_id` int UNSIGNED DEFAULT NULL,
  `cancelled_by` enum('patient','doctor','admin') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_ref_code` (`payment_ref_code`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_ref` (`payment_ref_code`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_doctor_date` (`doctor_id`,`appointment_date`),
  KEY `idx_created` (`created_at`),
  KEY `idx_appointments_status_date` (`status`,`appointment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `appointments`
--
DROP TRIGGER IF EXISTS `log_appointment_creation`;
DELIMITER $$
CREATE TRIGGER `log_appointment_creation` AFTER INSERT ON `appointments` FOR EACH ROW BEGIN
    INSERT INTO `audit_logs` (`admin_id`, `admin_role`, `action`, `target_type`, `target_id`, `ip_address`, `user_agent`)
    VALUES (0, 'system', 'appointment_created', 'appointment', NEW.id, 'system', 'auto_trigger');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int UNSIGNED NOT NULL,
  `admin_role` enum('super_admin','sub_admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., verified_doctor, approved_payment',
  `target_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., doctor, payment, appointment',
  `target_id` int UNSIGNED NOT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `featured_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_id` int UNSIGNED NOT NULL COMMENT 'Can be doctor or admin',
  `author_role` enum('doctor','admin','nutritionist') COLLATE utf8mb4_unicode_ci DEFAULT 'doctor',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tags` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Comma separated',
  `status` enum('draft','pending_review','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `views` int DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status_published` (`status`,`published_at`),
  KEY `idx_category` (`category`),
  KEY `idx_author` (`author_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author_id`, `author_role`, `category`, `tags`, `status`, `views`, `is_featured`, `published_at`, `created_at`, `updated_at`) VALUES
(1, '10 Tips for a Healthy Heart', 'tips-for-healthy-heart', '<p>Heart disease is one of the leading causes of death worldwide. Here are 10 tips to keep your heart healthy...</p><p>1. Eat a balanced diet rich in fruits and vegetables</p><p>2. Exercise regularly for at least 30 minutes daily</p><p>3. Avoid smoking and limit alcohol consumption</p><p>4. Manage stress through meditation and relaxation</p><p>5. Get adequate sleep of 7-8 hours</p><p>6. Maintain a healthy weight</p><p>7. Control blood pressure and cholesterol levels</p><p>8. Stay hydrated</p><p>9. Regular health check-ups</p><p>10. Know your family history</p>', 'Discover essential tips to maintain a healthy heart and prevent cardiovascular diseases.', NULL, 1, 'doctor', 'Cardiology', NULL, 'published', 0, 0, '2026-05-05 20:28:51', '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(2, 'Understanding Mental Health', 'understanding-mental-health', '<p>Mental health is just as important as physical health. Here\'s what you need to know...</p><p>Mental health affects how we think, feel, and act. It\'s important to recognize signs of mental health issues and seek help when needed. Common mental health conditions include depression, anxiety, and stress disorders.</p>', 'Learn about mental health awareness and how to take care of your emotional well-being.', NULL, 1, 'doctor', 'Psychiatry', NULL, 'published', 0, 0, '2026-05-05 20:28:51', '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(3, 'Skin Care Routine for All Seasons', 'skin-care-routine', '<p>Proper skin care is essential for maintaining healthy, glowing skin throughout the year...</p><p>Follow this simple routine: Cleanse twice daily, use toner, apply moisturizer, and never skip sunscreen. Different seasons require different care - summer needs lightweight products while winter requires more hydration.</p>', 'Complete guide to skin care routines that work for every season and skin type.', NULL, 1, 'doctor', 'Dermatology', NULL, 'published', 0, 0, '2026-05-05 20:28:51', '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(4, 'Managing Diabetes Naturally', 'managing-diabetes-naturally', '<p>Diabetes management requires a combination of medication, diet, and lifestyle changes...</p><p>Natural ways to manage diabetes include eating a fiber-rich diet, regular exercise, staying hydrated, monitoring blood sugar levels, and taking prescribed medications on time.</p>', 'Natural approaches to manage diabetes effectively alongside medical treatment.', NULL, 1, 'doctor', 'Diabetes', NULL, 'published', 0, 0, '2026-05-05 20:28:51', '2026-05-05 15:28:51', '2026-05-05 15:28:51');

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
CREATE TABLE IF NOT EXISTS `cities` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_province` (`province`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `name`, `district`, `province`, `is_active`) VALUES
(1, 'Karachi', 'Karachi', 'Sindh', 1),
(2, 'Lahore', 'Lahore', 'Punjab', 1),
(3, 'Islamabad', 'Islamabad', 'Islamabad Capital Territory', 1),
(4, 'Rawalpindi', 'Rawalpindi', 'Punjab', 1),
(5, 'Faisalabad', 'Faisalabad', 'Punjab', 1),
(6, 'Multan', 'Multan', 'Punjab', 1),
(7, 'Peshawar', 'Peshawar', 'Khyber Pakhtunkhwa', 1),
(8, 'Quetta', 'Quetta', 'Balochistan', 1),
(9, 'Gujranwala', 'Gujranwala', 'Punjab', 1),
(10, 'Sialkot', 'Sialkot', 'Punjab', 1);

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

DROP TABLE IF EXISTS `doctors`;
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `pmdc_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialty` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_specialty` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualification` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., MBBS, FCPS',
  `experience_years` int DEFAULT '0',
  `clinic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clinic_address` text COLLATE utf8mb4_unicode_ci,
  `clinic_city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consultation_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `video_consultation_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_video_available` tinyint(1) DEFAULT '1',
  `is_clinic_available` tinyint(1) DEFAULT '1',
  `bio` text COLLATE utf8mb4_unicode_ci COMMENT 'Professional introduction',
  `services` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of services offered',
  `average_rating` decimal(2,1) DEFAULT '0.0',
  `total_reviews` int DEFAULT '0',
  `profile_photo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'PMDC verification by admin',
  `verification_documents` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON: file paths of uploaded docs',
  `verification_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Admin notes during verification',
  `verified_by` int UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `profile_views` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_photo_thumb` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Resized thumbnail for listing pages',
  `clinic_photo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clinic/facility photo',
  `degree_certificate_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Uploaded degree certificate image',
  `pmdc_certificate_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Uploaded PMDC registration certificate',
  `signature_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Digital signature for prescriptions',
  PRIMARY KEY (`id`),
  UNIQUE KEY `pmdc_number` (`pmdc_number`),
  KEY `user_id` (`user_id`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_specialty` (`specialty`),
  KEY `idx_city` (`clinic_city`),
  KEY `idx_verified_rating` (`is_verified`,`average_rating`),
  KEY `idx_pmdc` (`pmdc_number`),
  KEY `idx_doctors_specialty_city` (`specialty`,`clinic_city`,`is_verified`),
  KEY `idx_has_photo` (`profile_photo`(100))
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `pmdc_number`, `specialty`, `sub_specialty`, `qualification`, `experience_years`, `clinic_name`, `clinic_address`, `clinic_city`, `consultation_fee`, `video_consultation_fee`, `is_video_available`, `is_clinic_available`, `bio`, `services`, `average_rating`, `total_reviews`, `profile_photo`, `is_verified`, `verification_documents`, `verification_notes`, `verified_by`, `verified_at`, `profile_views`, `created_at`, `updated_at`, `profile_photo_thumb`, `clinic_photo`, `degree_certificate_image`, `pmdc_certificate_image`, `signature_image`) VALUES
(1, 4, 'PMDC-12345', 'Cardiologist', NULL, 'MBBS, FCPS (Cardiology)', 12, 'Salman Heart Clinic', 'Gulberg III, Main Boulevard', 'Lahore', 2500.00, 2000.00, 1, 1, 'Dr. Salman Ahmed is a renowned cardiologist with over 12 years of experience in treating heart diseases.', NULL, 4.8, 45, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL),
(2, 5, 'PMDC-12346', 'Dermatologist', NULL, 'MBBS, FCPS (Dermatology)', 8, 'Fatima Skin & Laser Clinic', 'DHA Phase 5', 'Lahore', 2000.00, 1800.00, 1, 1, 'Dr. Fatima Zafar is a leading dermatologist specializing in skin diseases, hair treatments, and cosmetic dermatology.', NULL, 4.9, 38, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL),
(3, 6, 'PMDC-12347', 'Gynecologist', NULL, 'MBBS, FCPS (Gynecology)', 10, 'Ayesha Maternity Clinic', 'Clifton', 'Karachi', 2500.00, 2000.00, 1, 1, 'Dr. Ayesha Malik is an experienced gynecologist providing comprehensive care for women\'s health.', NULL, 4.7, 52, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL),
(4, 7, 'PMDC-12348', 'General Physician', NULL, 'MBBS, FCPS (Medicine)', 15, 'Umar Medical Center', 'F-10 Markaz', 'Islamabad', 1500.00, 1200.00, 1, 1, 'Dr. Umar Farooq is a highly experienced general physician providing primary healthcare.', NULL, 4.6, 28, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL),
(5, 8, 'PMDC-12349', 'Pediatrician', NULL, 'MBBS, FCPS (Pediatrics)', 7, 'Ramsha Children Clinic', 'Gulshan-e-Iqbal', 'Karachi', 1800.00, 1500.00, 1, 1, 'Dr. Ramsha Khalid specializes in child health from newborns to adolescents.', NULL, 4.9, 41, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL),
(6, 9, 'PMDC-12350', 'Orthopedic Surgeon', NULL, 'MBBS, FCPS (Orthopedics)', 11, 'Zubair Orthopedic Center', 'Johar Town', 'Lahore', 2500.00, 2200.00, 1, 1, 'Dr. Zubair Tariq is an expert orthopedic surgeon specializing in joint replacements.', NULL, 4.8, 34, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL),
(7, 10, 'PMDC-12351', 'Neurologist', NULL, 'MBBS, FCPS (Neurology)', 9, 'Asma Neurology Clinic', 'DHA Phase 8', 'Karachi', 3000.00, 2500.00, 1, 1, 'Dr. Asma Naeem is a neurologist treating disorders of the nervous system.', NULL, 4.7, 29, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL),
(8, 11, 'PMDC-12352', 'Psychiatrist', NULL, 'MBBS, FCPS (Psychiatry)', 8, 'Hassan Mental Health Clinic', 'E-11 Sector', 'Islamabad', 2200.00, 2000.00, 1, 1, 'Dr. Hassan Raza provides compassionate psychiatric care for depression and anxiety.', NULL, 4.8, 23, NULL, 1, NULL, NULL, NULL, NULL, 0, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL, NULL, NULL, NULL);

--
-- Triggers `doctors`
--
DROP TRIGGER IF EXISTS `validate_doctor_photo_before_insert`;
DELIMITER $$
CREATE TRIGGER `validate_doctor_photo_before_insert` BEFORE INSERT ON `doctors` FOR EACH ROW BEGIN
    -- This is a precaution - actual file validation happens in PHP
    -- We're just ensuring the path doesn't contain malicious patterns
    IF NEW.profile_photo IS NOT NULL THEN
        IF NEW.profile_photo NOT REGEXP '^/assets/(images|uploads)/(doctors|profile)/.*.(jpg|jpeg|png|gif|webp)$' THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Invalid profile photo path format. Use /assets/images/doctors/*.jpg';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_availability`
--

DROP TABLE IF EXISTS `doctor_availability`;
CREATE TABLE IF NOT EXISTS `doctor_availability` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` int UNSIGNED NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Monday to 6=Sunday',
  `is_available` tinyint(1) DEFAULT '1',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `slot_duration_minutes` int DEFAULT '30',
  `buffer_between_slots` int DEFAULT '5',
  `location_type` enum('clinic','video','both') COLLATE utf8mb4_unicode_ci DEFAULT 'both',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_doctor_day_type` (`doctor_id`,`day_of_week`,`location_type`),
  KEY `idx_day` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_bookings`
--

DROP TABLE IF EXISTS `lab_bookings`;
CREATE TABLE IF NOT EXISTS `lab_bookings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `booking_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `test_ids_json` json NOT NULL COMMENT 'Array of test IDs and names',
  `total_amount` decimal(10,2) NOT NULL,
  `collection_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `collection_city` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time_slot` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `special_instructions` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending_payment','payment_verified','sample_scheduled','sample_collected','processing','report_ready','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending_payment',
  `payment_ref_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sample_collected_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collected_at` datetime DEFAULT NULL,
  `report_file` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PDF report uploaded by lab',
  `report_ready_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  UNIQUE KEY `payment_ref_code` (`payment_ref_code`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_ref` (`payment_ref_code`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_lab_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

DROP TABLE IF EXISTS `lab_tests`;
CREATE TABLE IF NOT EXISTS `lab_tests` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `preparation_instructions` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `home_collection_available` tinyint(1) DEFAULT '1',
  `report_time_hours` int DEFAULT NULL COMMENT 'Hours until report ready',
  `image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`id`, `test_name`, `category`, `description`, `preparation_instructions`, `price`, `home_collection_available`, `report_time_hours`, `image`, `is_active`, `created_at`, `updated_at`) VALUES
(30, 'Complete Blood Count (CBC)', 'Hematology', 'Measures red blood cells, white blood cells, and platelets.', NULL, 500.00, 1, 6, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(31, 'Lipid Profile', 'Cholesterol', 'Measures cholesterol levels including HDL, LDL, and triglycerides.', NULL, 1200.00, 1, 8, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(32, 'Blood Sugar (Fasting)', 'Diabetes', 'Measures blood glucose level after 8-12 hours fasting.', NULL, 200.00, 1, 4, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(33, 'Hepatitis B Surface Antigen', 'Infectious Diseases', 'Screening test for Hepatitis B virus infection.', NULL, 1500.00, 1, 24, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(34, 'Urine Complete Examination', 'Urinalysis', 'Analyzes urine for infections, kidney disease, and diabetes.', NULL, 350.00, 0, 5, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(35, 'Thyroid Profile (T3, T4, TSH)', 'Hormones', 'Evaluates thyroid gland function.', NULL, 1000.00, 1, 8, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(36, 'Liver Function Test (LFT)', 'Liver', 'Measures liver enzymes and function.', NULL, 800.00, 1, 6, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(37, 'Kidney Function Test (KFT)', 'Kidney', 'Measures creatinine, urea, and electrolytes.', NULL, 700.00, 1, 6, NULL, 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

DROP TABLE IF EXISTS `medicines`;
CREATE TABLE IF NOT EXISTS `medicines` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `strength` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dosage_form` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'tablet, syrup, injection',
  `manufacturer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `requires_prescription` tinyint(1) DEFAULT '1',
  `stock_quantity` int DEFAULT '0',
  `main_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `additional_images` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of additional image paths',
  `box_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Image of medicine box/packaging',
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_category` (`category`),
  KEY `idx_active` (`is_active`),
  KEY `idx_has_image` (`main_image`(100))
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `generic_name`, `category`, `strength`, `dosage_form`, `manufacturer`, `price`, `requires_prescription`, `stock_quantity`, `main_image`, `thumbnail_image`, `image`, `description`, `is_active`, `created_at`, `updated_at`, `additional_images`, `box_image`) VALUES
(51, 'Panadol 500mg', 'Paracetamol', 'Pain Relief', '500mg', 'Tablet', 'GSK', 15.00, 0, 5000, NULL, NULL, NULL, 'Effective pain reliever and fever reducer.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(52, 'Augmentin 625mg', 'Co-amoxiclav', 'Antibiotic', '625mg', 'Tablet', 'GlaxoSmithKline', 250.00, 1, 2000, NULL, NULL, NULL, 'Broad-spectrum antibiotic for bacterial infections.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(53, 'Brufen 400mg', 'Ibuprofen', 'Pain Relief', '400mg', 'Tablet', 'Abbott', 120.00, 0, 3000, NULL, NULL, NULL, 'Anti-inflammatory pain reliever for muscle pain.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(54, 'Ventolin Inhaler', 'Salbutamol', 'Respiratory', '100mcg', 'Inhaler', 'GSK', 450.00, 1, 1000, NULL, NULL, NULL, 'Bronchodilator for asthma and COPD.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(55, 'Ritemed Vitamin C', 'Ascorbic Acid', 'Vitamins', '500mg', 'Tablet', 'Ritemed', 80.00, 0, 5000, NULL, NULL, NULL, 'Immune booster vitamin C supplement.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(56, 'Zythromax 500mg', 'Azithromycin', 'Antibiotic', '500mg', 'Tablet', 'Pfizer', 380.00, 1, 1500, NULL, NULL, NULL, 'Antibiotic for bacterial infections.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(57, 'Roche Panadol Extra', 'Paracetamol + Caffeine', 'Pain Relief', '500mg + 65mg', 'Tablet', 'Roche', 25.00, 0, 4000, NULL, NULL, NULL, 'Extra strength pain reliever.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(58, 'Flagyl 400mg', 'Metronidazole', 'Antibiotic', '400mg', 'Tablet', 'Sanofi', 180.00, 1, 2500, NULL, NULL, NULL, 'Antibiotic for bacterial and parasitic infections.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(59, 'Becosules Capsule', 'Vitamin B Complex', 'Vitamins', 'Multi', 'Capsule', 'Pfizer', 95.00, 0, 3000, NULL, NULL, NULL, 'Vitamin B complex supplement.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(60, 'Flexon 400mg', 'Ibuprofen + Paracetamol', 'Pain Relief', '400mg + 325mg', 'Tablet', 'Macter', 85.00, 0, 3500, NULL, NULL, NULL, 'Combination pain reliever.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(61, 'Omeprazole 20mg', 'Omeprazole', 'Gastric', '20mg', 'Capsule', 'GSK', 120.00, 1, 2000, NULL, NULL, NULL, 'Proton pump inhibitor for acid reflux.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(62, 'Glucomet 500mg', 'Metformin', 'Diabetes', '500mg', 'Tablet', 'Efroze', 60.00, 1, 2500, NULL, NULL, NULL, 'Antidiabetic medication for type 2 diabetes.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(63, 'Betnesol-N Drops', 'Betamethasone + Neomycin', 'Eye/Ear Drops', '0.1% + 0.5%', 'Drops', 'GSK', 195.00, 1, 1500, NULL, NULL, NULL, 'Anti-inflammatory and antibiotic drops.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(64, 'Ciproxin 500mg', 'Ciprofloxacin', 'Antibiotic', '500mg', 'Tablet', 'Bayer', 350.00, 1, 1800, NULL, NULL, NULL, 'Broad-spectrum antibiotic.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL),
(65, 'Calpol 120mg Syrup', 'Paracetamol', 'Pediatric', '120mg/5ml', 'Syrup', 'GSK', 120.00, 0, 2000, NULL, NULL, NULL, 'Pediatric pain and fever medicine.', 1, '2026-05-05 15:28:51', '2026-05-05 15:28:51', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'appointment_reminder, payment_verified, etc.',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `sent_via_sms` tinyint(1) DEFAULT '0',
  `sent_via_email` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-','Unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'Unknown',
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medical_history` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON or plain text of past conditions',
  `allergies` text COLLATE utf8mb4_unicode_ci,
  `profile_photo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo_thumb` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Resized thumbnail',
  `cnic_front_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CNIC front side (for verification)',
  `cnic_back_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CNIC back side (for verification)',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_city` (`city`),
  KEY `idx_has_photo` (`profile_photo`(100))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `date_of_birth`, `blood_group`, `gender`, `address`, `city`, `district`, `province`, `postal_code`, `emergency_contact_name`, `emergency_contact_phone`, `medical_history`, `allergies`, `profile_photo`, `profile_photo_thumb`, `cnic_front_image`, `cnic_back_image`) VALUES
(1, 12, '1990-05-15', 'B+', 'Male', 'House #12, Street 5, Gulberg', 'Lahore', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 13, '1992-08-22', 'O+', 'Female', 'Apartment 3B, Clifton Heights', 'Karachi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 14, '1988-03-10', 'A+', 'Male', 'House #45, F-8/4', 'Islamabad', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 15, '1995-11-30', 'AB-', 'Female', 'Street 12, DHA Phase 3', 'Lahore', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 16, '1985-07-18', 'O-', 'Male', 'House #8, PECHS', 'Karachi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_type` enum('appointment','pharmacy','lab_test') COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` int UNSIGNED NOT NULL COMMENT 'ID from appointments/pharmacy_orders/lab_bookings',
  `user_id` int UNSIGNED NOT NULL COMMENT 'Patient who paid',
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('bank_transfer','jazzcash','easypaisa','cash_deposit','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `proof_file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_uploaded_at` datetime DEFAULT NULL,
  `transaction_id_user` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User-provided transaction ID',
  `sender_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','verified','rejected','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by` int UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejected_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_code` (`ref_code`),
  KEY `user_id` (`user_id`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_ref_code` (`ref_code`),
  KEY `idx_status` (`status`),
  KEY `idx_order` (`order_type`,`order_id`),
  KEY `idx_payments_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_orders`
--

DROP TABLE IF EXISTS `pharmacy_orders`;
CREATE TABLE IF NOT EXISTS `pharmacy_orders` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `items_json` json NOT NULL COMMENT 'Array of {medicine_id, name, quantity, price}',
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_charges` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_city` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_instructions` text COLLATE utf8mb4_unicode_ci,
  `prescription_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Uploaded prescription photo',
  `status` enum('pending_payment','payment_verified','processing','shipped','delivered','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending_payment',
  `payment_ref_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  UNIQUE KEY `payment_ref_code` (`payment_ref_code`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_ref` (`payment_ref_code`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_orders_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `photo_gallery`
--

DROP TABLE IF EXISTS `photo_gallery`;
CREATE TABLE IF NOT EXISTS `photo_gallery` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` enum('doctor','patient','medicine','clinic','lab') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int UNSIGNED NOT NULL,
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `is_primary` tinyint(1) DEFAULT '0',
  `uploaded_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_primary` (`entity_type`,`entity_id`,`is_primary`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` int UNSIGNED NOT NULL,
  `doctor_id` int UNSIGNED NOT NULL,
  `patient_id` int UNSIGNED NOT NULL,
  `medicines_json` json NOT NULL COMMENT 'Array of {medicine_name, dosage, duration, timing, notes}',
  `diagnosis` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `advice` text COLLATE utf8mb4_unicode_ci,
  `follow_up_needed` tinyint(1) DEFAULT '0',
  `follow_up_date` date DEFAULT NULL,
  `prescription_pdf` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Generated PDF path',
  `is_digital_signature` tinyint(1) DEFAULT '0',
  `issued_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` int UNSIGNED NOT NULL,
  `patient_id` int UNSIGNED NOT NULL,
  `doctor_id` int UNSIGNED NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `review_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Admin moderation',
  `doctor_response` text COLLATE utf8mb4_unicode_ci,
  `helpful_count` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_appointment_review` (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `idx_doctor_approved` (`doctor_id`,`is_approved`),
  KEY `idx_rating` (`rating`)
) ;

--
-- Triggers `reviews`
--
DROP TRIGGER IF EXISTS `after_review_approval`;
DELIMITER $$
CREATE TRIGGER `after_review_approval` AFTER UPDATE ON `reviews` FOR EACH ROW BEGIN
    IF NEW.is_approved = 1 AND OLD.is_approved = 0 THEN
        CALL update_doctor_rating(NEW.doctor_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `specialties`
--

DROP TABLE IF EXISTS `specialties`;
CREATE TABLE IF NOT EXISTS `specialties` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `specialties`
--

INSERT INTO `specialties` (`id`, `name`, `slug`, `description`, `icon`, `is_active`, `display_order`) VALUES
(1, 'Cardiologist', 'cardiologist', NULL, NULL, 1, 1),
(2, 'Dermatologist', 'dermatologist', NULL, NULL, 1, 2),
(3, 'Gynecologist', 'gynecologist', NULL, NULL, 1, 3),
(4, 'General Physician', 'general-physician', NULL, NULL, 1, 4),
(5, 'Pediatrician', 'pediatrician', NULL, NULL, 1, 5),
(6, 'Orthopedic Surgeon', 'orthopedic-surgeon', NULL, NULL, 1, 6),
(7, 'Neurologist', 'neurologist', NULL, NULL, 1, 7),
(8, 'Psychiatrist', 'psychiatrist', NULL, NULL, 1, 8),
(9, 'Dentist', 'dentist', NULL, NULL, 1, 9),
(10, 'ENT Specialist', 'ent-specialist', NULL, NULL, 1, 10),
(11, 'Urologist', 'urologist', NULL, NULL, 1, 11),
(12, 'Ophthalmologist', 'ophthalmologist', NULL, NULL, 1, 12);

-- --------------------------------------------------------

--
-- Table structure for table `sub_admin_regions`
--

DROP TABLE IF EXISTS `sub_admin_regions`;
CREATE TABLE IF NOT EXISTS `sub_admin_regions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sub_admin_id` int UNSIGNED NOT NULL,
  `region_type` enum('city','district','province') COLLATE utf8mb4_unicode_ci NOT NULL,
  `region_value` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., Lahore, Punjab',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_admin_region` (`sub_admin_id`,`region_type`,`region_value`),
  KEY `idx_region` (`region_type`,`region_value`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sub_admin_regions`
--

INSERT INTO `sub_admin_regions` (`id`, `sub_admin_id`, `region_type`, `region_value`, `assigned_at`) VALUES
(1, 2, 'city', 'Lahore', '2026-05-05 15:28:51'),
(2, 2, 'city', 'Rawalpindi', '2026-05-05 15:28:51'),
(3, 3, 'city', 'Karachi', '2026-05-05 15:28:51'),
(4, 3, 'city', 'Hyderabad', '2026-05-05 15:28:51');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Patient/doctor who raised ticket',
  `user_role` enum('patient','doctor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('payment','booking','technical','doctor_verification','pharmacy','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `assigned_to` int UNSIGNED DEFAULT NULL COMMENT 'sub_admin or support_staff',
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `user_id` (`user_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `idx_status` (`status`),
  KEY `idx_ticket_number` (`ticket_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

DROP TABLE IF EXISTS `ticket_replies`;
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` int UNSIGNED NOT NULL,
  `sender_id` int UNSIGNED NOT NULL,
  `sender_role` enum('patient','doctor','sub_admin','super_admin','support_staff') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read_by_user` tinyint(1) DEFAULT '0',
  `is_read_by_admin` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_read_status` (`is_read_by_user`,`is_read_by_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

DROP TABLE IF EXISTS `time_slots`;
CREATE TABLE IF NOT EXISTS `time_slots` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` int UNSIGNED NOT NULL,
  `slot_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_booked` tinyint(1) NOT NULL DEFAULT '0',
  `location_type` enum('clinic','video') COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_id` int UNSIGNED DEFAULT NULL COMMENT 'FK to appointments when booked',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_availability` (`doctor_id`,`slot_date`,`is_booked`),
  KEY `idx_date` (`slot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnic` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional: for Pakistani nationals',
  `role` enum('patient','doctor','super_admin','sub_admin','support_staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'patient',
  `profile_photo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Profile photo path (fallback for all roles)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `phone_verified` tinyint(1) NOT NULL DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role_active` (`role`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `cnic`, `role`, `profile_photo`, `is_active`, `email_verified`, `phone_verified`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'superadmin@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ahmed Raza', '03001234567', NULL, 'super_admin', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(2, 'subadmin.lahore@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sana Khan', '03001234568', NULL, 'sub_admin', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(3, 'subadmin.karachi@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Imran Ali', '03001234569', NULL, 'sub_admin', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(4, 'dr.salman@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Salman Ahmed', '03001234571', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(5, 'dr.fatima@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Fatima Zafar', '03001234572', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(6, 'dr.ayesha@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Ayesha Malik', '03001234573', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(7, 'dr.umar@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Umar Farooq', '03001234574', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(8, 'dr.ramsha@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Ramsha Khalid', '03001234575', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(9, 'dr.zubair@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Zubair Tariq', '03001234576', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(10, 'dr.asma@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Asma Naeem', '03001234577', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(11, 'dr.hassan@digitalsehatghar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Hassan Raza', '03001234578', NULL, 'doctor', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(12, 'ali.raza@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ali Raza', '03011234561', NULL, 'patient', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(13, 'sana.ahmed@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sana Ahmed', '03011234562', NULL, 'patient', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(14, 'bilal.khan@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bilal Khan', '03011234563', NULL, 'patient', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(15, 'zara.ali@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Zara Ali', '03011234564', NULL, 'patient', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51'),
(16, 'umer.nadeem@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Umer Nadeem', '03011234565', NULL, 'patient', NULL, 1, 1, 0, NULL, '2026-05-05 15:28:51', '2026-05-05 15:28:51');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_doctors_with_photos`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `view_doctors_with_photos`;
CREATE TABLE IF NOT EXISTS `view_doctors_with_photos` (
`average_rating` decimal(2,1)
,`bio` text
,`clinic_address` text
,`clinic_city` varchar(50)
,`clinic_name` varchar(255)
,`clinic_photo` varchar(500)
,`consultation_fee` decimal(10,2)
,`created_at` timestamp
,`degree_certificate_image` varchar(500)
,`display_photo` varchar(500)
,`display_photo_thumb` text
,`email` varchar(255)
,`experience_years` int
,`full_name` varchar(100)
,`id` int unsigned
,`is_clinic_available` tinyint(1)
,`is_verified` tinyint(1)
,`is_video_available` tinyint(1)
,`phone` varchar(20)
,`pmdc_certificate_image` varchar(500)
,`pmdc_number` varchar(50)
,`profile_photo` varchar(500)
,`profile_photo_thumb` varchar(500)
,`profile_views` int
,`qualification` text
,`services` text
,`signature_image` varchar(500)
,`specialty` varchar(100)
,`sub_specialty` varchar(100)
,`total_reviews` int
,`updated_at` timestamp
,`user_id` int unsigned
,`verification_documents` text
,`verification_notes` text
,`verified_at` datetime
,`verified_by` int unsigned
,`video_consultation_fee` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_patients_with_photos`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `view_patients_with_photos`;
CREATE TABLE IF NOT EXISTS `view_patients_with_photos` (
`address` text
,`allergies` text
,`blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-','Unknown')
,`city` varchar(50)
,`cnic_back_image` varchar(500)
,`cnic_front_image` varchar(500)
,`date_of_birth` date
,`display_photo` varchar(500)
,`display_photo_thumb` text
,`district` varchar(50)
,`email` varchar(255)
,`emergency_contact_name` varchar(100)
,`emergency_contact_phone` varchar(20)
,`full_name` varchar(100)
,`gender` enum('Male','Female','Other')
,`id` int unsigned
,`medical_history` text
,`phone` varchar(20)
,`postal_code` varchar(10)
,`profile_photo` varchar(500)
,`profile_photo_thumb` varchar(500)
,`province` varchar(50)
,`user_id` int unsigned
);

-- --------------------------------------------------------

--
-- Structure for view `view_doctors_with_photos`
--
DROP TABLE IF EXISTS `view_doctors_with_photos`;

DROP VIEW IF EXISTS `view_doctors_with_photos`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_doctors_with_photos`  AS SELECT `d`.`id` AS `id`, `d`.`user_id` AS `user_id`, `d`.`pmdc_number` AS `pmdc_number`, `d`.`specialty` AS `specialty`, `d`.`sub_specialty` AS `sub_specialty`, `d`.`qualification` AS `qualification`, `d`.`experience_years` AS `experience_years`, `d`.`clinic_name` AS `clinic_name`, `d`.`clinic_address` AS `clinic_address`, `d`.`clinic_city` AS `clinic_city`, `d`.`consultation_fee` AS `consultation_fee`, `d`.`video_consultation_fee` AS `video_consultation_fee`, `d`.`is_video_available` AS `is_video_available`, `d`.`is_clinic_available` AS `is_clinic_available`, `d`.`bio` AS `bio`, `d`.`services` AS `services`, `d`.`average_rating` AS `average_rating`, `d`.`total_reviews` AS `total_reviews`, `d`.`profile_photo` AS `profile_photo`, `d`.`is_verified` AS `is_verified`, `d`.`verification_documents` AS `verification_documents`, `d`.`verification_notes` AS `verification_notes`, `d`.`verified_by` AS `verified_by`, `d`.`verified_at` AS `verified_at`, `d`.`profile_views` AS `profile_views`, `d`.`created_at` AS `created_at`, `d`.`updated_at` AS `updated_at`, `d`.`profile_photo_thumb` AS `profile_photo_thumb`, `d`.`clinic_photo` AS `clinic_photo`, `d`.`degree_certificate_image` AS `degree_certificate_image`, `d`.`pmdc_certificate_image` AS `pmdc_certificate_image`, `d`.`signature_image` AS `signature_image`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, (case when ((`d`.`profile_photo` is not null) and (`d`.`profile_photo` <> '')) then `d`.`profile_photo` when ((`u`.`profile_photo` is not null) and (`u`.`profile_photo` <> '')) then `u`.`profile_photo` else '/assets/images/ui/default-avatar-doctor.jpg' end) AS `display_photo`, (case when ((`d`.`profile_photo_thumb` is not null) and (`d`.`profile_photo_thumb` <> '')) then `d`.`profile_photo_thumb` when ((`u`.`profile_photo` is not null) and (`u`.`profile_photo` <> '')) then replace(`u`.`profile_photo`,'.','-thumb.') else '/assets/images/ui/default-avatar-doctor-thumb.jpg' end) AS `display_photo_thumb` FROM (`doctors` `d` join `users` `u` on((`d`.`user_id` = `u`.`id`))) ;

-- --------------------------------------------------------

--
-- Structure for view `view_patients_with_photos`
--
DROP TABLE IF EXISTS `view_patients_with_photos`;

DROP VIEW IF EXISTS `view_patients_with_photos`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_patients_with_photos`  AS SELECT `p`.`id` AS `id`, `p`.`user_id` AS `user_id`, `p`.`date_of_birth` AS `date_of_birth`, `p`.`blood_group` AS `blood_group`, `p`.`gender` AS `gender`, `p`.`address` AS `address`, `p`.`city` AS `city`, `p`.`district` AS `district`, `p`.`province` AS `province`, `p`.`postal_code` AS `postal_code`, `p`.`emergency_contact_name` AS `emergency_contact_name`, `p`.`emergency_contact_phone` AS `emergency_contact_phone`, `p`.`medical_history` AS `medical_history`, `p`.`allergies` AS `allergies`, `p`.`profile_photo` AS `profile_photo`, `p`.`profile_photo_thumb` AS `profile_photo_thumb`, `p`.`cnic_front_image` AS `cnic_front_image`, `p`.`cnic_back_image` AS `cnic_back_image`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, (case when ((`p`.`profile_photo` is not null) and (`p`.`profile_photo` <> '')) then `p`.`profile_photo` when ((`u`.`profile_photo` is not null) and (`u`.`profile_photo` <> '')) then `u`.`profile_photo` else '/assets/images/ui/default-avatar-patient.jpg' end) AS `display_photo`, (case when ((`p`.`profile_photo_thumb` is not null) and (`p`.`profile_photo_thumb` <> '')) then `p`.`profile_photo_thumb` when ((`u`.`profile_photo` is not null) and (`u`.`profile_photo` <> '')) then replace(`u`.`profile_photo`,'.','-thumb.') else '/assets/images/ui/default-avatar-patient-thumb.jpg' end) AS `display_photo_thumb` FROM (`patients` `p` join `users` `u` on((`p`.`user_id` = `u`.`id`))) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctors_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD CONSTRAINT `doctor_availability_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_bookings`
--
ALTER TABLE `lab_bookings`
  ADD CONSTRAINT `lab_bookings_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pharmacy_orders`
--
ALTER TABLE `pharmacy_orders`
  ADD CONSTRAINT `pharmacy_orders_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `photo_gallery`
--
ALTER TABLE `photo_gallery`
  ADD CONSTRAINT `photo_gallery_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_admin_regions`
--
ALTER TABLE `sub_admin_regions`
  ADD CONSTRAINT `sub_admin_regions_ibfk_1` FOREIGN KEY (`sub_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD CONSTRAINT `time_slots_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
