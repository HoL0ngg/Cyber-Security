CREATE DATABASE anm;
USE anm;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(50),
    balance INT DEFAULT 1000
);

INSERT INTO users (username, password, balance) VALUES ('admin', '123456', 5000), ('hoanglong', 'long2005', 1000);

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT
);