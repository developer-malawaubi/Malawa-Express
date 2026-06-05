-- Malawa Express Admin Dashboard Migration
-- Run this script on your database to add the is_admin column to the users table and set the initial superusers.

ALTER TABLE users ADD is_admin TINYINT(1) NOT NULL DEFAULT 0;

UPDATE users SET is_admin = 1 WHERE email = 'malawaubi@gmail.com' OR email = 'thabo@example.com';
