SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `customurl`
--

CREATE TABLE IF NOT EXISTS `customurl` (
  `urlID` int(10) unsigned NOT NULL,
  `customURL` varchar(20) NOT NULL,
  UNIQUE KEY `customURL` (`customURL`),
  KEY `urlID` (`urlID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `urlclicks`
--

CREATE TABLE IF NOT EXISTS `urlclicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urlID` int(11) NOT NULL,
  `clicks` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urlID` (`urlID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `urls`
--

CREATE TABLE IF NOT EXISTS `urls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` mediumtext CHARACTER SET utf8 NOT NULL,
  `hash` char(32) CHARACTER SET ascii NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hash` (`hash`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `urlstats`
--

CREATE TABLE IF NOT EXISTS `urlstats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urlID` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customurl`
--
ALTER TABLE `customurl`
  ADD CONSTRAINT `customurl_ibfk_1` FOREIGN KEY (`urlID`) REFERENCES `urls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

