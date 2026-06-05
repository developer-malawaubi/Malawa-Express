<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
  require $configPath;
}

function db_configured() {
  return defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')
    && DB_HOST !== 'sqlXXX.infinityfree.com'
    && DB_NAME !== 'if0_XXXXXXX_malawa';
}

function db() {
  static $pdo = null;
  static $failed = false;
  if ($pdo) return $pdo;
  if ($failed) return null;
  if (!db_configured()) return null;

  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_TIMEOUT => 5,
    ]);
  } catch (Throwable $e) {
    $failed = true;
    throw $e;
  }
  return $pdo;
}

function db_available() {
  try {
    return db() instanceof PDO;
  } catch (Throwable $e) {
    return false;
  }
}

function redirect_to($page, $params = []) {
  header('Location: ' . url_for($page, $params));
  exit;
}

function normalize_country($country) {
  if ($country === 'South Africa' || $country === 'ZA') return 'ZA';
  if ($country === 'Mozambique' || $country === 'MZ') return 'MZ';
  return 'ZA';
}

function db_product_from_row($row) {
  return [
    'id' => (string)$row['id'],
    'title' => $row['title'],
    'titlePt' => $row['title_pt'] ?: $row['title'],
    'description' => $row['description'],
    'descriptionPt' => $row['description_pt'] ?: $row['description'],
    'price' => (float)$row['price'],
    'currency' => $row['currency'],
    'priceConverted' => $row['converted_amount'] ? ['amount' => (float)$row['converted_amount'], 'currency' => $row['converted_currency']] : null,
    'category' => $row['category'],
    'condition' => $row['product_condition'],
    'image' => $row['image_key'] ?: 'smartphone-black',
    'sellerId' => (string)$row['seller_id'],
    'sellerName' => $row['seller_name'] ?: 'Seller',
    'sellerRating' => (float)($row['seller_rating'] ?: 5),
    'sellerReviews' => (int)($row['seller_reviews'] ?: 0),
    'location' => $row['location'],
    'country' => $row['country'],
    'featured' => (bool)$row['featured'],
  ];
}

function db_user_from_row($row) {
  $methods = $row['payment_methods'] ? explode(',', $row['payment_methods']) : ['Cash on Collection'];
  $isAdmin = (bool)($row['is_admin'] ?? false);
  $email = strtolower(trim($row['email'] ?? ''));
  if ($email === 'malawaubi@gmail.com' || $email === 'thabo@example.com') {
    $isAdmin = true;
  }
  return [
    'id' => (string)$row['id'],
    'name' => $row['name'],
    'verified' => (bool)$row['verified'],
    'rating' => (float)$row['rating'],
    'totalReviews' => (int)$row['total_reviews'],
    'memberSince' => $row['created_at'],
    'activeListings' => (int)($row['active_listings'] ?? 0),
    'itemsSold' => (int)$row['items_sold'],
    'responseRate' => (int)$row['response_rate'],
    'location' => $row['location'] ?: 'Johannesburg',
    'country' => $row['country'] ?: 'ZA',
    'avatar' => $row['avatar_key'] ?: 'man-african',
    'paymentMethods' => array_map('trim', $methods),
    'email' => $row['email'] ?? '',
    'is_admin' => $isAdmin,
  ];
}

function current_user() {
  if (empty($_SESSION['user_id'])) return null;
  if (!db_available()) {
    if (isset($_SESSION['mock_users'][$_SESSION['user_id']])) {
      return $_SESSION['mock_users'][$_SESSION['user_id']];
    }
    global $users;
    if (isset($users[$_SESSION['user_id']])) {
      $user = $users[$_SESSION['user_id']];
      $email = strtolower(trim($user['email'] ?? ''));
      $user['is_admin'] = ($email === 'malawaubi@gmail.com' || $email === 'thabo@example.com' || !empty($user['is_admin']));
      return $user;
    }
    return null;
  }
  $stmt = db()->prepare('SELECT users.*, (SELECT COUNT(*) FROM products WHERE seller_id = users.id) active_listings FROM users WHERE id = ?');
  $stmt->execute([$_SESSION['user_id']]);
  $row = $stmt->fetch();
  return $row ? db_user_from_row($row) : null;
}

function db_products($featured = null) {
  if (!db_available()) return null;
  $where = $featured === null ? '' : 'WHERE p.featured = ?';
  $sql = "SELECT p.*, u.name seller_name, u.rating seller_rating, u.total_reviews seller_reviews
          FROM products p
          JOIN users u ON u.id = p.seller_id
          $where
          ORDER BY p.featured DESC, p.created_at DESC";
  $stmt = db()->prepare($sql);
  $stmt->execute($featured === null ? [] : [(int)$featured]);
  return array_map('db_product_from_row', $stmt->fetchAll());
}

function db_find_product($id) {
  if (!db_available()) return null;
  $stmt = db()->prepare('SELECT p.*, u.name seller_name, u.rating seller_rating, u.total_reviews seller_reviews FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ?');
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  return $row ? db_product_from_row($row) : null;
}

function db_find_user($id) {
  if (!db_available()) return null;
  $stmt = db()->prepare('SELECT users.*, (SELECT COUNT(*) FROM products WHERE seller_id = users.id) active_listings FROM users WHERE id = ?');
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  return $row ? db_user_from_row($row) : null;
}

function db_user_products($userId) {
  if (!db_available()) return null;
  $stmt = db()->prepare('SELECT p.*, u.name seller_name, u.rating seller_rating, u.total_reviews seller_reviews FROM products p JOIN users u ON u.id = p.seller_id WHERE p.seller_id = ? ORDER BY p.created_at DESC');
  $stmt->execute([$userId]);
  return array_map('db_product_from_row', $stmt->fetchAll());
}

function db_reviews($productId) {
  if (!db_available()) return null;
  $stmt = db()->prepare('SELECT r.rating, r.comment, DATE(r.created_at) date, u.name userName, u.avatar_key avatar FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC');
  $stmt->execute([$productId]);
  return $stmt->fetchAll();
}

function db_collection_points($country = null) {
  if (!db_available()) return null;
  $sql = 'SELECT * FROM collection_points' . ($country ? ' WHERE country = ?' : '') . ' ORDER BY city';
  $stmt = db()->prepare($sql);
  $stmt->execute($country ? [$country] : []);
  return $stmt->fetchAll();
}

function db_create_user($post) {
  $name = trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? ''));
  $stmt = db()->prepare('INSERT INTO users (name, email, phone, country, preferred_language, password_hash, avatar_key, payment_methods) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([
    $name,
    strtolower(trim($post['email'] ?? '')),
    trim($post['phone'] ?? ''),
    normalize_country($post['country'] ?? 'ZA'),
    $post['language'] ?? 'English',
    password_hash($post['password'] ?? '', PASSWORD_DEFAULT),
    normalize_country($post['country'] ?? 'ZA') === 'MZ' ? 'woman-african' : 'man-african',
    'EFT,Cash on Collection,M-Pesa',
  ]);
  return db()->lastInsertId();
}

function db_authenticate($email, $password) {
  if (!db_available()) {
    $email = strtolower(trim($email));
    if ($email === 'thabo@example.com' || $email === 'malawaubi@gmail.com') {
      if (!isset($_SESSION['mock_users'])) {
        global $users;
        $_SESSION['mock_users'] = $users;
      }
      $_SESSION['mock_users']['u1']['email'] = $email;
      $_SESSION['mock_users']['u1']['is_admin'] = true;
      return $_SESSION['mock_users']['u1'];
    }
    if (!isset($_SESSION['mock_users'])) {
      global $users;
      $_SESSION['mock_users'] = $users;
    }
    foreach ($_SESSION['mock_users'] as $id => $u) {
      if (isset($u['email']) && strtolower(trim($u['email'])) === $email) {
        return $u;
      }
    }
    // Create new temporary mock user
    $id = 'u_' . uniqid();
    $newUser = [
      'id' => $id,
      'name' => explode('@', $email)[0],
      'verified' => false,
      'rating' => 5.0,
      'totalReviews' => 0,
      'memberSince' => date('Y-m-d'),
      'activeListings' => 0,
      'itemsSold' => 0,
      'responseRate' => 100,
      'location' => 'Johannesburg',
      'country' => 'ZA',
      'avatar' => 'man-african',
      'paymentMethods' => ['Cash on Collection'],
      'email' => $email,
      'is_admin' => false,
    ];
    $_SESSION['mock_users'][$id] = $newUser;
    return $newUser;
  }
  $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
  $stmt->execute([strtolower(trim($email))]);
  $user = $stmt->fetch();
  return $user && password_verify($password, $user['password_hash']) ? $user : null;
}

function db_create_product($post, $sellerId) {
  $currency = str_starts_with($post['currency'] ?? 'ZAR', 'MZN') ? 'MZN' : 'ZAR';
  $country = normalize_country($post['country'] ?? 'ZA');
  $convertedCurrency = $currency === 'ZAR' ? 'MZN' : 'ZAR';
  $convertedAmount = $currency === 'ZAR' ? ((float)$post['price'] * 3.8) : ((float)$post['price'] / 3.8);
  $stmt = db()->prepare('INSERT INTO products (seller_id, title, title_pt, description, description_pt, price, currency, converted_amount, converted_currency, category, product_condition, image_key, location, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([
    $sellerId,
    trim($post['title'] ?? ''),
    trim($post['titlePt'] ?? ''),
    trim($post['description'] ?? ''),
    trim($post['descriptionPt'] ?? ''),
    (float)($post['price'] ?? 0),
    $currency,
    round($convertedAmount),
    $convertedCurrency,
    $post['category'] ?? 'other',
    strtolower($post['condition'] ?? 'used') === 'new' ? 'new' : 'used',
    'smartphone-black',
    trim($post['location'] ?? ''),
    $country,
  ]);
  return db()->lastInsertId();
}

function db_save_listing($userId, $productId) {
  $stmt = db()->prepare('INSERT IGNORE INTO saved_listings (user_id, product_id) VALUES (?, ?)');
  $stmt->execute([$userId, $productId]);
}

function db_create_order($post, $buyerId, $product) {
  $stmt = db()->prepare('INSERT INTO orders (buyer_id, seller_id, product_id, payment_method, collection_point, total_amount, currency, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([
    $buyerId,
    $product['sellerId'],
    $product['id'],
    $post['payment_method'] ?? 'Cash on Collection',
    $post['collection_point'] ?? '',
    $product['price'],
    $product['currency'],
    'placed',
  ]);
  return db()->lastInsertId();
}

function db_find_order($id) {
  if (!db_available()) return null;
  $stmt = db()->prepare('SELECT o.*, p.title, p.description, u.name buyer_name, u.email buyer_email FROM orders o JOIN products p ON p.id = o.product_id JOIN users u ON u.id = o.buyer_id WHERE o.id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

function db_user_orders($userId) {
  if (!db_available()) return [];
  $stmt = db()->prepare('
    SELECT o.*, p.title, p.image_key, p.currency product_currency, s.name seller_name, b.name buyer_name 
    FROM orders o 
    JOIN products p ON p.id = o.product_id 
    JOIN users s ON s.id = o.seller_id 
    JOIN users b ON b.id = o.buyer_id 
    WHERE o.buyer_id = ? OR o.seller_id = ? 
    ORDER BY o.created_at DESC
  ');
  $stmt->execute([$userId, $userId]);
  return $stmt->fetchAll();
}

function db_update_order_status($id, $status) {
  if (!db_available()) {
    if (!isset($_SESSION['mock_orders'])) {
      if (function_exists('all_orders')) {
        all_orders();
      }
    }
    if (isset($_SESSION['mock_orders'])) {
      foreach ($_SESSION['mock_orders'] as &$o) {
        if ($o['id'] === (string)$id) {
          $o['status'] = $status;
          break;
        }
      }
    }
    return;
  }
  $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
  $stmt->execute([$status, $id]);
}

function db_send_message($senderId, $receiverId, $body) {
  $stmt = db()->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
  $stmt->execute([$senderId, $receiverId, trim($body)]);
}

function db_conversations($userId) {
  if (!db_available()) return null;
  $stmt = db()->prepare(
    'SELECT m.*, IF(m.sender_id = ?, m.receiver_id, m.sender_id) other_id, u.name, u.avatar_key, u.country, u.location
     FROM messages m
     JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     WHERE m.sender_id = ? OR m.receiver_id = ?
     ORDER BY m.created_at DESC'
  );
  $stmt->execute([$userId, $userId, $userId, $userId]);
  $seen = [];
  $items = [];
  foreach ($stmt->fetchAll() as $row) {
    if (isset($seen[$row['other_id']])) continue;
    $seen[$row['other_id']] = true;
    $items[] = [
      'otherUser' => (string)$row['other_id'],
      'name' => $row['name'],
      'avatar' => $row['avatar_key'],
      'lastMessage' => $row['body'],
      'timestamp' => $row['created_at'],
      'unread' => !$row['read_at'] && (int)$row['receiver_id'] === (int)$userId,
    ];
  }
  return $items;
}

function db_messages_between($userId, $otherId) {
  if (!db_available()) return null;
  $stmt = db()->prepare('SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC');
  $stmt->execute([$userId, $otherId, $otherId, $userId]);
  return $stmt->fetchAll();
}

function db_all_users() {
  if (!db_available()) {
    if (!isset($_SESSION['mock_users'])) {
      global $users;
      $_SESSION['mock_users'] = $users;
    }
    return array_values($_SESSION['mock_users']);
  }
  $stmt = db()->prepare('SELECT users.*, (SELECT COUNT(*) FROM products WHERE seller_id = users.id) active_listings FROM users ORDER BY created_at DESC');
  $stmt->execute();
  return array_map('db_user_from_row', $stmt->fetchAll());
}

function db_update_user($id, $data) {
  if (!db_available()) {
    if (!isset($_SESSION['mock_users'])) {
      global $users;
      $_SESSION['mock_users'] = $users;
    }
    if (isset($_SESSION['mock_users'][$id])) {
      $_SESSION['mock_users'][$id] = array_merge($_SESSION['mock_users'][$id], $data);
      return true;
    }
    return false;
  }
  $fields = [];
  $params = [];
  foreach ($data as $key => $val) {
    $fields[] = "$key = ?";
    $params[] = $val;
  }
  if (empty($fields)) return false;
  $params[] = $id;
  $stmt = db()->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
  return $stmt->execute($params);
}

function db_delete_user($id) {
  if (!db_available()) {
    if (!isset($_SESSION['mock_users'])) {
      global $users;
      $_SESSION['mock_users'] = $users;
    }
    if (isset($_SESSION['mock_users'][$id])) {
      unset($_SESSION['mock_users'][$id]);
      return true;
    }
    return false;
  }
  $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
  return $stmt->execute([$id]);
}

function db_update_product($id, $data) {
  if (!db_available()) {
    if (!isset($_SESSION['mock_products'])) {
      global $products;
      $_SESSION['mock_products'] = $products;
    }
    foreach ($_SESSION['mock_products'] as &$p) {
      if ($p['id'] === (string)$id) {
        $p = array_merge($p, $data);
        return true;
      }
    }
    return false;
  }
  $fields = [];
  $params = [];
  foreach ($data as $key => $val) {
    $fields[] = "$key = ?";
    $params[] = $val;
  }
  if (empty($fields)) return false;
  $params[] = $id;
  $stmt = db()->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?');
  return $stmt->execute($params);
}

function db_delete_product($id) {
  if (!db_available()) {
    if (!isset($_SESSION['mock_products'])) {
      global $products;
      $_SESSION['mock_products'] = $products;
    }
    foreach ($_SESSION['mock_products'] as $index => $p) {
      if ($p['id'] === (string)$id) {
        unset($_SESSION['mock_products'][$index]);
        $_SESSION['mock_products'] = array_values($_SESSION['mock_products']);
        return true;
      }
    }
    return false;
  }
  $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
  return $stmt->execute([$id]);
}

function db_all_orders() {
  if (!db_available()) {
    if (function_exists('all_orders')) {
      return all_orders();
    }
    return [];
  }
  $stmt = db()->prepare('
    SELECT o.*, p.title, p.image_key, p.currency product_currency, s.name seller_name, b.name buyer_name, b.email buyer_email 
    FROM orders o 
    JOIN products p ON p.id = o.product_id 
    JOIN users s ON s.id = o.seller_id 
    JOIN users b ON b.id = o.buyer_id 
    ORDER BY o.created_at DESC
  ');
  $stmt->execute();
  return $stmt->fetchAll();
}

function db_delete_order($id) {
  if (!db_available()) {
    if (!isset($_SESSION['mock_orders'])) {
      if (function_exists('all_orders')) {
        all_orders();
      }
    }
    if (isset($_SESSION['mock_orders'])) {
      foreach ($_SESSION['mock_orders'] as $index => $o) {
        if ($o['id'] === (string)$id) {
          unset($_SESSION['mock_orders'][$index]);
          $_SESSION['mock_orders'] = array_values($_SESSION['mock_orders']);
          return true;
        }
      }
    }
    return false;
  }
  $stmt = db()->prepare('DELETE FROM orders WHERE id = ?');
  return $stmt->execute([$id]);
}
