/*
Navicat MySQL Data Transfer

Source Server         : LOCALHOST
Source Server Version : 50621
Source Host           : localhost:3306
Source Database       : citstudio_scrap

Target Server Type    : MYSQL
Target Server Version : 50621
File Encoding         : 65001

Date: 2015-01-12 22:07:25
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `webscrap`
-- ----------------------------
DROP TABLE IF EXISTS `webscrap`;
CREATE TABLE `webscrap` (
  `id` int(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `content` text,
  `thumbnail` varchar(100) DEFAULT NULL,
  `slug` varchar(100) NOT NULL DEFAULT '',
  `source_url` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`,`slug`),
  UNIQUE KEY `slug` (`slug`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of webscrap
-- ----------------------------
