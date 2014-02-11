CREATE DATABASE pubman DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;
CREATE USER 'pubman'@'localhost' IDENTIFIED BY 'changeit';
GRANT ALL PRIVILEGES ON pubman.* TO 'pubman'@'localhost';
FLUSH PRIVILEGES;
