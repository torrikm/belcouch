CREATE TABLE IF NOT EXISTS regions (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(100) NOT NULL UNIQUE
);

INSERT INTO regions (name) VALUES 
  ('Брестская область'), 
  ('Витебская область'), 
  ('Гомельская область'), 
  ('Гродненская область'), 
  ('Минская область'), 
  ('Могилёвская область');

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

CREATE TABLE IF NOT EXISTS navigation (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title varchar(255) NOT NULL,
  url varchar(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS slider_images (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title varchar(255) NOT NULL,
  image mediumblob
);

CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email varchar(255) NOT NULL UNIQUE,
  first_name varchar(100) NOT NULL,
  last_name varchar(100) NOT NULL,
  password_hash varchar(255) NOT NULL,
  password_salt varchar(100) NOT NULL,
  birthdate date DEFAULT NULL,
  registration_date timestamp NOT NULL DEFAULT current_timestamp(),
  is_verify tinyint(1) NOT NULL DEFAULT 0,
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
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE RESTRICT,
  FOREIGN KEY (property_type_id) REFERENCES property_types(id) ON DELETE RESTRICT,
  FOREIGN KEY (stay_duration_id) REFERENCES stay_durations(id),
  avg_rating DECIMAL(3,2) DEFAULT NULL
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