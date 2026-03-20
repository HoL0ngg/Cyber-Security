CREATE DATABASE anm;
USE anm;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(255),
    balance INT DEFAULT 1000
);
INSERT INTO users (username, password, balance)
VALUES (
        'admin',
        '$2y$10$o2Jn3bqPhTrU9DdQrQvQuOEz3fQcAw4fNYY3kSHa/5Bwasnzk5/BG',
        5000
    ),
    (
        'long',
        '$2y$10$o2Jn3bqPhTrU9DdQrQvQuOEz3fQcAw4fNYY3kSHa/5Bwasnzk5/BG',
        2000
    ),
    (
        'test',
        '$2y$10$o2Jn3bqPhTrU9DdQrQvQuOEz3fQcAw4fNYY3kSHa/5Bwasnzk5/BG',
        1000
    );
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT
);
INSERT INTO comments (content)
VALUES ('Xin chào'),
    ('Test XSS here');