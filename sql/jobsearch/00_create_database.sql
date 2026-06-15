-- Run this in phpMyAdmin only if the hosting account permits CREATE DATABASE.
-- On cPanel hosts the preferred route is usually:
-- cPanel > MySQL Databases > create database "JeMaJobs".
-- cPanel then applies the account prefix, resulting in kerubina_JeMaJobs.

CREATE DATABASE IF NOT EXISTS `kerubina_JeMaJobs`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `kerubina_JeMaJobs`;

