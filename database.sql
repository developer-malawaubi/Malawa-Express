CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(40) DEFAULT NULL,
  country VARCHAR(2) NOT NULL DEFAULT 'ZA',
  preferred_language VARCHAR(30) NOT NULL DEFAULT 'English',
  password_hash VARCHAR(255) NOT NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  total_reviews INT NOT NULL DEFAULT 0,
  items_sold INT NOT NULL DEFAULT 0,
  response_rate INT NOT NULL DEFAULT 95,
  location VARCHAR(120) DEFAULT NULL,
  avatar_key VARCHAR(60) DEFAULT 'man-african',
  payment_methods VARCHAR(255) DEFAULT 'EFT,Cash on Collection',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  title_pt VARCHAR(180) DEFAULT NULL,
  description TEXT NOT NULL,
  description_pt TEXT DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'ZAR',
  converted_amount DECIMAL(10,2) DEFAULT NULL,
  converted_currency VARCHAR(3) DEFAULT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'other',
  product_condition VARCHAR(20) NOT NULL DEFAULT 'used',
  image_key VARCHAR(80) DEFAULT 'smartphone-black',
  location VARCHAR(120) NOT NULL,
  country VARCHAR(2) NOT NULL DEFAULT 'ZA',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE collection_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  city VARCHAR(120) NOT NULL,
  country VARCHAR(2) NOT NULL,
  address VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE saved_listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  buyer_id INT NOT NULL,
  seller_id INT NOT NULL,
  product_id INT NOT NULL,
  payment_method VARCHAR(80) NOT NULL,
  collection_point VARCHAR(255) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'placed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  body TEXT NOT NULL,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  rating INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (id, name, email, phone, country, password_hash, verified, rating, total_reviews, items_sold, location, avatar_key, payment_methods) VALUES
(1, 'Thabo Nkosi', 'thabo@example.com', '+27110000001', 'ZA', '$2y$12$Mjitxm4fL6C0KUuwWuu3r.0q6kiZMXOyBHmVYWqxSmzDx/ADmuEv6', 1, 4.8, 45, 89, 'Johannesburg', 'man-african', 'EFT,Instant EFT,M-Pesa'),
(2, 'Maria Santos', 'maria@example.com', '+258840000001', 'MZ', '$2y$12$Mjitxm4fL6C0KUuwWuu3r.0q6kiZMXOyBHmVYWqxSmzDx/ADmuEv6', 1, 4.9, 87, 156, 'Maputo', 'woman-african', 'M-Pesa,mKesh,eMola');

INSERT INTO products (seller_id, title, title_pt, description, description_pt, price, currency, converted_amount, converted_currency, category, product_condition, image_key, location, country, featured) VALUES
(1, 'Samsung Galaxy A54 5G', 'Samsung Galaxy A54 5G', 'Brand new Samsung Galaxy A54, unlocked, 128GB storage, excellent condition.', 'Samsung Galaxy A54 novo, desbloqueado, 128GB de armazenamento, excelente condição.', 6500, 'ZAR', 25000, 'MZN', 'electronics', 'new', 'smartphone-black', 'Johannesburg', 'ZA', 1),
(2, 'Traditional Capulana Fabric', 'Tecido Capulana Tradicional', 'Beautiful handmade capulana fabric, authentic Mozambican design, perfect for clothing or decoration.', 'Linda capulana artesanal, design moçambicano autêntico, perfeito para roupa ou decoração.', 450, 'MZN', 120, 'ZAR', 'handicrafts', 'new', 'african-fabric-colorful', 'Maputo', 'MZ', 1);

INSERT INTO collection_points (city, country, address) VALUES
('Johannesburg', 'ZA', 'Corner of Main & 5th Street, City Centre'),
('Durban', 'ZA', '123 Victoria Street, Durban Central'),
('Cape Town', 'ZA', '45 Long Street, Cape Town CBD'),
('Maputo', 'MZ', 'Avenida Julius Nyerere 789'),
('Ressano Garcia', 'MZ', 'Rua Principal, Centro');

INSERT INTO messages (sender_id, receiver_id, body) VALUES
(2, 1, 'Hello! I am interested in your product.'),
(1, 2, 'Hi! Thank you for your interest. What would you like to know?');
