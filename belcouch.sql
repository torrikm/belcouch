CREATE DATABASE IF NOT EXISTS belcouch_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE belcouch_db;


CREATE TABLE IF NOT EXISTS regions (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(100) NOT NULL UNIQUE,
  code varchar(50) NOT NULL UNIQUE,
  city varchar(50) NOT NULL UNIQUE
);

INSERT INTO regions (name, code, city) VALUES 
  ('Брестая область', 'brest', 'Брест'), 
  ('Витебская область', 'vitebsk', 'Витебск'), 
  ('Гомельская область', 'gomel', 'Гомель'), 
  ('Гродненская область', 'grodno', 'Гродно'), 
  ('Минская область', 'minsk', 'Минск'), 
  ('Могилёвская область', 'mogilev', 'Могилёв');

CREATE TABLE IF NOT EXISTS property_types (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(100) NOT NULL UNIQUE
);

INSERT INTO property_types (name) VALUES 
  ('Комната'), 
  ('Спальное место'), 
  ('Квартира'), 
  ('Дом'), 
  ('Другое');

CREATE TABLE IF NOT EXISTS stay_durations (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(100) NOT NULL,
  days int(11) DEFAULT NULL,
  UNIQUE KEY unique_name (name)
);

INSERT INTO stay_durations (name, days) VALUES 
  ('Сутки', 1),
  ('Двое суток', 2),
  ('Не более 5 суток', 5),
  ('Не более 10 дней', 10),
  ('Другое', NULL);

CREATE TABLE IF NOT EXISTS slider_images (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title varchar(255) NOT NULL,
  image mediumblob
);

CREATE TABLE IF NOT EXISTS rules (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(255) NOT NULL,
  icon varchar(100) NOT NULL
);

INSERT INTO rules (name, icon) VALUES 
  ('Можно с детьми любого возраста', 'children-any-age.svg'),
  ('Можно с детьми после 3-ех лет', 'children-3-plus.svg'),
  ('Можно с детьми после 7 лет', 'children-7-plus.svg'),
  ('С детьми запрещено', 'no-children.svg'),
  ('Курение запрещено', 'no-smoking.svg'),
  ('Курение разрешено', 'smoking-allowed.svg'),
  ('Можно шуметь', 'noise-allowed.svg'),
  ('Нейтрально к шуму', 'noise-neutral.svg'),
  ('Шуметь запрещено', 'noise-not-allowed.svg'),
  ('Можно с животными', 'pets-allowed.svg'),
  ('С животными запрещено', 'no-pets.svg'),
  ('Есть животное', 'has-pet.svg');

CREATE TABLE IF NOT EXISTS amenities (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(255) NOT NULL,
  icon varchar(100) NOT NULL
);

INSERT INTO amenities (name, icon) VALUES 
  ('Wi-Fi', 'wifi.svg'),
  ('Постельное белье', 'bed-linen.svg'),
  ('Телевизор', 'tv.svg'),
  ('Полотенца', 'towel.svg'),
  ('Микроволновка', 'microwave.svg'),
  ('Фен', 'hairdryer.svg'),
  ('Предоставление питания', 'food.svg'),
  ('Трансфер', 'transfer.svg'),
  ('Паркинг', 'parking.svg');

CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email varchar(255) NOT NULL UNIQUE,
  first_name varchar(100) NOT NULL,
  last_name varchar(100) DEFAULT NULL,
  password_hash varchar(255) NOT NULL,
  password_salt varchar(100) NOT NULL,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  birthdate date DEFAULT NULL,
  registration_date timestamp NOT NULL DEFAULT current_timestamp(),
  is_verify tinyint(1) NOT NULL DEFAULT 0,
  is_online tinyint(1) NOT NULL DEFAULT 0,
  avatar_image mediumblob,
  avg_rating DECIMAL(3,2) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS user_details (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id int(11) NOT NULL,
  description text,
  education varchar(255) DEFAULT NULL,
  occupation varchar(255) DEFAULT NULL,
  interests varchar(255) DEFAULT NULL,
  gender ENUM('male', 'female', 'not_specified') DEFAULT 'not_specified',
  birthdate date DEFAULT NULL,
  region_id int(11) DEFAULT NULL,
  district varchar(100) DEFAULT NULL,
  city varchar(100) DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_ratings (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id int(11) NOT NULL,
  rater_id int(11) NOT NULL,
  comment text NOT NULL,
  rating int(1) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS listings (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id int(11) NOT NULL,
  region_id int(11) NOT NULL,
  city varchar(100) NOT NULL,
  title varchar(255) NOT NULL,
  notes text,
  property_type_id int(11) NOT NULL,
  max_guests int(2) NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  stay_duration_id int(11) DEFAULT NULL,
  avg_rating DECIMAL(3,2) DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE RESTRICT,
  FOREIGN KEY (property_type_id) REFERENCES property_types(id) ON DELETE RESTRICT,
  FOREIGN KEY (stay_duration_id) REFERENCES stay_durations(id)
);

CREATE TABLE IF NOT EXISTS listing_ratings (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  comment text NOT NULL,
  rating int(1) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS listing_rules (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id int(11) NOT NULL,
  rule_id int(11) NOT NULL,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE CASCADE,
  UNIQUE KEY listing_rule_unique (listing_id, rule_id)
);

CREATE TABLE IF NOT EXISTS listing_amenities (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id int(11) NOT NULL,
  amenity_id int(11) NOT NULL,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE,
  UNIQUE KEY listing_amenity_unique (listing_id, amenity_id)
);

CREATE TABLE IF NOT EXISTS favorites (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  listing_id INT(11) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY user_listing (user_id, listing_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sender_id int(11) NOT NULL,
  receiver_id int(11) NOT NULL,
  listing_id int(11) DEFAULT NULL,
  message text NOT NULL,
  is_read tinyint(1) NOT NULL DEFAULT 0,
  reply_to_message_id int(11) DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  edited_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  FOREIGN KEY (reply_to_message_id) REFERENCES messages(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS message_attachments (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  message_id int(11) NOT NULL,
  file_path varchar(255) NOT NULL,
  file_type ENUM('image', 'video') NOT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS verification_requests (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id int(11) NOT NULL,
  document_image longblob NULL,
  document_mime varchar(100) NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  admin_note text DEFAULT NULL,
  reviewed_by int(11) DEFAULT NULL,
  reviewed_at timestamp NULL DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_messages_sender_receiver_id ON messages (sender_id, receiver_id, id);
CREATE INDEX idx_messages_receiver_sender_is_read ON messages (receiver_id, sender_id, is_read, id);
CREATE INDEX idx_messages_reply_to_message_id ON messages (reply_to_message_id);
CREATE INDEX idx_message_attachments_message_id_sort_order ON message_attachments (message_id, sort_order, id);

DELIMITER //
CREATE TRIGGER update_listing_rating AFTER INSERT ON listing_ratings
FOR EACH ROW
BEGIN
  DECLARE avg_rating DECIMAL(3,2);
  
  SELECT AVG(rating) INTO avg_rating
  FROM listing_ratings
  WHERE listing_id = NEW.listing_id;
  
  UPDATE listings SET avg_rating = avg_rating WHERE id = NEW.listing_id;
END //
DELIMITER ;
