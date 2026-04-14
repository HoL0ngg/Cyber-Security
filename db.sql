CREATE DATABASE anm;
USE anm;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(255),
    balance INT DEFAULT 1000,
    citizen_id VARCHAR(20),
    phone VARCHAR(20),
    home_address VARCHAR(255)
);
INSERT INTO users (username, password, balance, citizen_id, phone, home_address)
VALUES (
        'admin',
        '$2y$10$o2Jn3bqPhTrU9DdQrQvQuOEz3fQcAw4fNYY3kSHa/5Bwasnzk5/BG',
        5000,
        '001203000001',
        '0901234567',
        '1 Le Loi, Q1, TP.HCM'
    ),
    (
        'long',
        '$2y$10$o2Jn3bqPhTrU9DdQrQvQuOEz3fQcAw4fNYY3kSHa/5Bwasnzk5/BG',
        2000,
        '001203000002',
        '0912345678',
        '12 Tran Hung Dao, Ha Noi'
    ),
    (
        'test',
        '$2y$10$o2Jn3bqPhTrU9DdQrQvQuOEz3fQcAw4fNYY3kSHa/5Bwasnzk5/BG',
        1000,
        '001203000003',
        '0987654321',
        '99 Nguyen Hue, Da Nang'
    );
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT
);
INSERT INTO comments (content)
VALUES ('Xin chào'),
    ('Test XSS here');