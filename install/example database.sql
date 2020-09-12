SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `bikes` (
  `bikeNum` int(11) NOT NULL,
  `currentUser` int(11) DEFAULT NULL,
  `currentStand` int(11) DEFAULT NULL,
  `currentCode` int(11) NOT NULL,
  `note` varchar(100) DEFAULT NULL,
  `name` text DEFAULT NULL,
  `bikelock` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `coupons` (
  `coupon` varchar(6) NOT NULL,
  `value` float(5,2) DEFAULT NULL,
  `status` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `credit` (
  `userId` int(11) NOT NULL,
  `credit` decimal(5,2) DEFAULT NULL,
  `sub` float(5,0) DEFAULT 0,
  `soc` decimal(5,2) NOT NULL DEFAULT 100.00,
  `mollieId` varchar(50) DEFAULT NULL,
  `mollieMandate` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `geolocation` (
  `userId` int(10) UNSIGNED NOT NULL,
  `longitude` double(20,17) NOT NULL,
  `latitude` double(20,17) NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `history` (
  `userId` int(11) NOT NULL,
  `bikeNum` int(11) NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `action` varchar(20) NOT NULL,
  `parameter` text NOT NULL,
  `standId` int(11) DEFAULT NULL,
  `pairAction` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `limits` (
  `userId` int(11) NOT NULL,
  `userLimit` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `monthpasses` (
  `userId` int(11) NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `valid` timestamp NULL DEFAULT NULL,
  `type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `notes` (
  `bikeNum` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `note` varchar(100) DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `pairing` (
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `standid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `received` (
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `sms_uuid` varchar(60) DEFAULT NULL,
  `sender` varchar(20) NOT NULL,
  `receive_time` varchar(20) NOT NULL,
  `sms_text` varchar(200) NOT NULL,
  `IP` varchar(20) NOT NULL,
  `id` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `registration` (
  `userId` int(11) NOT NULL,
  `userKey` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sent` (
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `number` varchar(20) NOT NULL,
  `text` varchar(200) NOT NULL,
  `id` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sessions` (
  `userId` int(10) UNSIGNED NOT NULL,
  `sessionId` varchar(256) CHARACTER SET latin1 NOT NULL,
  `timeStamp` varchar(256) CHARACTER SET latin1 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `stands` (
  `standId` int(11) NOT NULL,
  `standName` varchar(50) NOT NULL,
  `standDescription` varchar(255) DEFAULT NULL,
  `standPhoto` varchar(255) DEFAULT NULL,
  `serviceTag` int(10) DEFAULT NULL,
  `placeName` varchar(50) NOT NULL,
  `longitude` double(20,17) DEFAULT NULL,
  `latitude` double(20,17) DEFAULT NULL,
  `standExplore` varchar(255) DEFAULT NULL,
  `standLink` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `userId` int(11) NOT NULL,
  `userName` varchar(50) NOT NULL,
  `password` text NOT NULL,
  `mail` varchar(50) NOT NULL,
  `number` varchar(30) NOT NULL,
  `privileges` int(11) NOT NULL DEFAULT 0,
  `note` text NOT NULL,
  `recommendations` text NOT NULL,
  `userlang` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `bikes`
  ADD PRIMARY KEY (`bikeNum`);

ALTER TABLE `coupons`
  ADD UNIQUE KEY `coupon` (`coupon`);

ALTER TABLE `credit`
  ADD PRIMARY KEY (`userId`);

ALTER TABLE `limits`
  ADD PRIMARY KEY (`userId`);

ALTER TABLE `monthpasses`
  ADD UNIQUE KEY `time` (`time`);

ALTER TABLE `notes`
  ADD PRIMARY KEY (`time`);

ALTER TABLE `received`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `registration`
  ADD UNIQUE KEY `userId` (`userId`);

ALTER TABLE `sent`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sessions`
  ADD UNIQUE KEY `userId` (`userId`),
  ADD KEY `sessionId` (`sessionId`);

ALTER TABLE `stands`
  ADD PRIMARY KEY (`standId`),
  ADD UNIQUE KEY `standName` (`standName`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`);


ALTER TABLE `limits`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `received`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sent`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `stands`
  MODIFY `standId` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
