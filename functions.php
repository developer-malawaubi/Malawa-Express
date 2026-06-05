<?php
function h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function lang() {
  return ($_GET['lang'] ?? 'en') === 'pt' ? 'pt' : 'en';
}

function t($key) {
  global $translations;
  $lang = lang();
  return $translations[$lang][$key] ?? $key;
}

function url_for($page, $params = []) {
  $params = array_merge(['page' => $page, 'lang' => lang()], $params);
  if ($page === 'home') {
    unset($params['page']);
  }
  return 'index.php?' . http_build_query($params);
}

function product_categories() {
  return ['all','electronics','clothing','handicrafts','household','other'];
}

function category_label($category) {
  return $category === 'all' ? t('home.allCategories') : t('category.' . $category);
}

function category_url($category) {
  $files = [
    'all' => 'all-categories.php',
    'electronics' => 'electronics.php',
    'clothing' => 'clothing.php',
    'handicrafts' => 'handicrafts.php',
    'household' => 'household.php',
    'other' => 'other.php',
  ];
  $file = $files[$category] ?? $files['all'];
  return $file . '?' . http_build_query(['lang' => lang()]);
}

function img_src($key) {
  if (preg_match('#^https?://#', (string)$key)) return $key;
  global $images;
  return $images[$key] ?? $images['smartphone-black'];
}

function product_title($product) {
  return lang() === 'pt' ? $product['titlePt'] : $product['title'];
}

function product_description($product) {
  return lang() === 'pt' ? $product['descriptionPt'] : $product['description'];
}

function price($amount, $currency) {
  return $currency === 'ZAR' ? 'R ' . number_format($amount) : number_format($amount) . ' MT';
}

function converted_price($converted) {
  if (!$converted) return '';
  return '≈ ' . price($converted['amount'], $converted['currency']);
}

function cart_items_raw() {
  return $_SESSION['cart'] ?? [];
}

function cart_count() {
  return array_sum(array_map('intval', cart_items_raw()));
}

function cart_add($productId, $quantity = 1) {
  $product = find_product($productId);
  if (!$product) return false;
  $id = (string)$product['id'];
  $_SESSION['cart'][$id] = max(1, (int)($_SESSION['cart'][$id] ?? 0) + (int)$quantity);
  return true;
}

function cart_update($productId, $quantity) {
  $id = (string)$productId;
  $quantity = max(0, (int)$quantity);
  if ($quantity === 0) {
    unset($_SESSION['cart'][$id]);
    return;
  }
  if (find_product($id)) $_SESSION['cart'][$id] = $quantity;
}

function cart_remove($productId) {
  unset($_SESSION['cart'][(string)$productId]);
}

function cart_items() {
  $items = [];
  foreach (cart_items_raw() as $id => $quantity) {
    $product = find_product($id);
    if (!$product) continue;
    $items[] = [
      'product' => $product,
      'quantity' => max(1, (int)$quantity),
      'lineTotal' => (float)$product['price'] * max(1, (int)$quantity),
    ];
  }
  return $items;
}

function cart_total() {
  return array_sum(array_map(fn($item) => $item['lineTotal'], cart_items()));
}

function cart_totals_by_currency() {
  $totals = [];
  foreach (cart_items() as $item) {
    $currency = $item['product']['currency'];
    $totals[$currency] = ($totals[$currency] ?? 0) + $item['lineTotal'];
  }
  return $totals;
}

function find_product($id) {
  if (function_exists('db_find_product') && db_available()) {
    $product = db_find_product($id);
    if ($product) return $product;
  }
  if (!isset($_SESSION['mock_products'])) {
    global $products;
    $_SESSION['mock_products'] = $products;
  }
  foreach ($_SESSION['mock_products'] as $product) {
    if ($product['id'] === (string)$id) return $product;
  }
  return null;
}

function user_products($userId) {
  if (function_exists('db_user_products') && db_available()) {
    $dbProducts = db_user_products($userId);
    if ($dbProducts !== null) {
      return $dbProducts;
    }
  }
  if (!isset($_SESSION['mock_products'])) {
    global $products;
    $_SESSION['mock_products'] = $products;
  }
  return array_values(array_filter($_SESSION['mock_products'], fn($product) => $product['sellerId'] === (string)$userId));
}

function all_products($featured = null) {
  if (function_exists('db_products') && db_available()) {
    $dbProducts = db_products($featured);
    if ($dbProducts !== null) {
      return $dbProducts;
    }
  }
  if (!isset($_SESSION['mock_products'])) {
    global $products;
    $_SESSION['mock_products'] = $products;
  }
  $prods = $_SESSION['mock_products'];
  return $featured === null
    ? $prods
    : array_values(array_filter($prods, fn($product) => (bool)$product['featured'] === (bool)$featured));
}

function products_by_category($category, $limit = null) {
  $products = all_products(null);
  if ($category !== 'all') {
    $products = array_values(array_filter($products, fn($product) => $product['category'] === $category));
  }
  if ($limit !== null) return array_slice($products, 0, $limit);
  return $products;
}

function is_admin($user) {
  if (!$user) return false;
  return !empty($user['is_admin']);
}

function all_users() {
  if (function_exists('db_all_users') && db_available()) {
    $dbUsers = db_all_users();
    if ($dbUsers !== null) return $dbUsers;
  }
  if (!isset($_SESSION['mock_users'])) {
    global $users;
    $_SESSION['mock_users'] = $users;
  }
  return array_values($_SESSION['mock_users']);
}

function find_user($id) {
  if (function_exists('db_find_user') && db_available()) {
    $user = db_find_user($id);
    if ($user) return $user;
  }
  if (!isset($_SESSION['mock_users'])) {
    global $users;
    $_SESSION['mock_users'] = $users;
  }
  return $_SESSION['mock_users'][$id] ?? null;
}

function all_orders() {
  if (function_exists('db_all_orders') && db_available()) {
    return db_all_orders();
  }
  if (!isset($_SESSION['mock_orders'])) {
    $_SESSION['mock_orders'] = [
      [
        'id' => 'o1',
        'buyer_id' => 'u1',
        'seller_id' => 'u2',
        'product_id' => '2',
        'payment_method' => 'M-Pesa',
        'collection_point' => 'Avenida Julius Nyerere 789',
        'total_amount' => 450,
        'currency' => 'MZN',
        'status' => 'placed',
        'created_at' => '2026-05-20 12:00:00',
        'title' => 'Traditional Capulana Fabric',
        'image_key' => 'african-fabric-colorful',
        'product_currency' => 'MZN',
        'seller_name' => 'Maria Santos',
        'buyer_name' => 'Thabo Nkosi',
        'buyer_email' => 'thabo@example.com'
      ],
      [
        'id' => 'o2',
        'buyer_id' => 'u2',
        'seller_id' => 'u1',
        'product_id' => '1',
        'payment_method' => 'Cash on Collection',
        'collection_point' => 'Corner of Main & 5th Street, City Centre',
        'total_amount' => 6500,
        'currency' => 'ZAR',
        'status' => 'paid',
        'created_at' => '2026-05-21 09:30:00',
        'title' => 'Samsung Galaxy A54 5G',
        'image_key' => 'smartphone-black',
        'product_currency' => 'ZAR',
        'seller_name' => 'Thabo Nkosi',
        'buyer_name' => 'Maria Santos',
        'buyer_email' => 'maria@example.com'
      ]
    ];
  }
  return $_SESSION['mock_orders'];
}

function all_collection_points($country = null) {
  if (function_exists('db_collection_points')) {
    $points = db_collection_points($country);
    if ($points !== null) return $points;
  }
  global $collectionPoints;
  if (!$country) return $collectionPoints;
  return array_values(array_filter($collectionPoints, fn($point) => $point['country'] === $country));
}

function product_reviews($productId) {
  if (function_exists('db_reviews')) {
    $reviews = db_reviews($productId);
    if ($reviews !== null) return $reviews;
  }
  global $reviews;
  return $reviews;
}

function app_notice($message, $type = 'success') {
  if (!$message) return '';
  return '<p class="' . h($type) . '">' . h($message) . '</p>';
}

function app_base_url() {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\');
  return $scheme . '://' . $host . ($path ? $path : '');
}

function payfast_configured() {
  return defined('PAYFAST_MERCHANT_ID') && defined('PAYFAST_MERCHANT_KEY')
    && PAYFAST_MERCHANT_ID !== 'your_sandbox_merchant_id'
    && PAYFAST_MERCHANT_KEY !== 'your_sandbox_merchant_key';
}

function payfast_url() {
  return (defined('PAYFAST_SANDBOX') && PAYFAST_SANDBOX)
    ? 'https://sandbox.payfast.co.za/eng/process'
    : 'https://www.payfast.co.za/eng/process';
}

function payfast_signature($data) {
  $pairs = [];
  foreach ($data as $key => $value) {
    if ($key === 'signature' || $value === '') continue;
    $pairs[] = $key . '=' . urlencode(trim((string)$value));
  }
  $query = implode('&', $pairs);
  if (defined('PAYFAST_PASSPHRASE') && PAYFAST_PASSPHRASE !== '') {
    $query .= '&passphrase=' . urlencode(trim(PAYFAST_PASSPHRASE));
  }
  return md5($query);
}

function payfast_payment_data($order) {
  $amount = number_format((float)$order['total_amount'], 2, '.', '');
  $baseUrl = app_base_url();
  $nameParts = preg_split('/\s+/', trim($order['buyer_name'] ?? 'Customer'), 2);
  
  $buyerEmail = $order['buyer_email'] ?? '';
  // If sandbox mode is enabled and the buyer's email matches the sandbox merchant email,
  // we use a test sandbox email so PayFast doesn't block the transaction.
  if (defined('PAYFAST_SANDBOX') && PAYFAST_SANDBOX && strtolower(trim($buyerEmail)) === 'malawaubi@gmail.com') {
    $buyerEmail = 'buyer@example.com';
  }

  $data = [
    'merchant_id' => PAYFAST_MERCHANT_ID,
    'merchant_key' => PAYFAST_MERCHANT_KEY,
    'return_url' => $baseUrl . '/index.php?page=payment_return&order=' . $order['id'],
    'cancel_url' => $baseUrl . '/index.php?page=payment_cancel&order=' . $order['id'],
    'notify_url' => $baseUrl . '/index.php?page=payment_notify',
    'name_first' => $nameParts[0] ?? 'Customer',
    'name_last' => $nameParts[1] ?? '',
    'email_address' => $buyerEmail,
    'm_payment_id' => $order['id'],
    'amount' => $amount,
    'item_name' => substr($order['title'] ?? 'Malawa Express Order', 0, 100),
    'item_description' => substr($order['description'] ?? 'Marketplace purchase', 0, 255),
    'custom_int1' => $order['id'],
  ];
  $data['signature'] = payfast_signature($data);
  return $data;
}

function render_payfast_form($order) {
  $data = payfast_payment_data($order);
  echo '<form id="payfastForm" method="post" action="' . h(payfast_url()) . '">';
  foreach ($data as $key => $value) {
    echo '<input type="hidden" name="' . h($key) . '" value="' . h($value) . '">';
  }
  echo '<button class="btn btn-dark full" type="submit">Continue to PayFast Sandbox</button></form>';
  echo '<script>document.getElementById("payfastForm").submit();</script>';
}

function render_icon($name) {
  $icons = [
    'search'=>'<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>',
    'pin'=>'<svg viewBox="0 0 24 24"><path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12z"></path><circle cx="12" cy="9" r="2.5"></circle></svg>',
    'check'=>'<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"></path></svg>',
    'star'=>'<svg viewBox="0 0 24 24"><path d="m12 2 3 6.4 7 .9-5.1 5 1.3 6.9L12 17.8 5.8 21.2l1.3-6.9-5.1-5 7-.9L12 2z"></path></svg>',
    'shield'=>'<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    'bag'=>'<svg viewBox="0 0 24 24"><path d="M6 8h12l-1 13H7L6 8z"></path><path d="M9 8a3 3 0 0 1 6 0"></path></svg>',
    'store'=>'<svg viewBox="0 0 24 24"><path d="M4 10h16l-1.5-6h-13L4 10z"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path></svg>',
    'send'=>'<svg viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4 20-7z"></path><path d="M22 2 11 13"></path></svg>',
    'upload'=>'<svg viewBox="0 0 24 24"><path d="M12 16V4"></path><path d="m7 9 5-5 5 5"></path><path d="M20 16v4H4v-4"></path></svg>',
    'cart'=>'<svg viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.5"></circle><circle cx="18" cy="20" r="1.5"></circle><path d="M3 4h2l2.5 11h11l2-8H7"></path></svg>',
    'menu'=>'<svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>',
    'phone'=>'<svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 1.9z"></path></svg>',
    'box'=>'<svg viewBox="0 0 24 24"><path d="m21 8-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
    'user'=>'<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>',
  ];
  return '<span class="icon">' . ($icons[$name] ?? $icons['check']) . '</span>';
}

function render_brand_icon($name) {
  $icons = [
    'facebook'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5V7.2c0-.8.5-1.2 1.3-1.2H17V3.3c-.8-.1-1.6-.2-2.4-.2-2.4 0-4.1 1.5-4.1 4.2v1.2H8v3h2.5V21H14v-9.5h2.7l.4-3H14z"></path></svg>',
    'instagram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="5"></rect><circle cx="12" cy="12" r="3.6"></circle><circle cx="16.8" cy="7.2" r="1"></circle></svg>',
    'x'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h4.7l4.1 5.4L17.5 4H20l-6 6.9L21 20h-4.7l-4.5-5.9L6.7 20H4.2l6.6-7.5L4 4zm3.2 1.8 10 12.4h1.6l-10-12.4H7.2z"></path></svg>',
  ];
  return '<span class="brand-icon">' . ($icons[$name] ?? '') . '</span>';
}

function render_header($active) {
  $currentUser = function_exists('current_user') ? current_user() : null;
  $toggleLang = lang() === 'en' ? 'pt' : 'en';
  $current = $_GET;
  $current['lang'] = $toggleLang;
  $toggleUrl = 'index.php?' . http_build_query($current);
  
  $isAdmin = is_admin($currentUser);
  
  $links = [
    'home' => ['label' => t('nav.home'), 'url' => url_for('home')],
    'messages' => ['label' => t('nav.messages'), 'url' => url_for('messages')],
    'profile' => ['label' => t('nav.profile'), 'url' => url_for('profile', ['id' => $currentUser['id'] ?? 'u1'])],
  ];
  if ($currentUser) {
    if ($isAdmin) {
      $links['admin'] = ['label' => 'Admin Dashboard', 'url' => url_for('admin')];
    } else {
      $links['orders'] = ['label' => 'Orders', 'url' => url_for('orders')];
    }
  }
  if (!$isAdmin) {
    $links['cart'] = ['label' => 'Cart', 'url' => url_for('cart')];
  }
  $links['about'] = ['label' => t('nav.about'), 'url' => url_for('about')];

  $menuCategories = [
    ['label' => category_label('all'), 'url' => category_url('all')],
    ['label' => category_label('electronics'), 'url' => category_url('electronics')],
    ['label' => category_label('clothing'), 'url' => category_url('clothing')],
    ['label' => category_label('handicrafts'), 'url' => category_url('handicrafts')],
    ['label' => category_label('household'), 'url' => category_url('household')],
    ['label' => category_label('other'), 'url' => category_url('other')],
  ];
  $featuredMenuProducts = array_slice(all_products(true), 0, 3);  $startSellingLink = !$isAdmin ? '<a href="' . h(url_for('sell')) . '">Start selling</a>' : '';
  echo '<header class="site-header"><div class="announcement-bar"><div class="social-cluster"><a href="https://www.facebook.com/login" aria-label="Log in to Facebook">' . render_brand_icon('facebook') . '</a><a href="https://www.instagram.com/accounts/login/" aria-label="Log in to Instagram">' . render_brand_icon('instagram') . '</a><a href="https://x.com/i/flow/login" aria-label="Log in to X">' . render_brand_icon('x') . '</a></div><p>Trusted cross-border trade - every day</p>' . $startSellingLink . '</div><div class="utility-bar"><span>' . render_icon('box') . ' Secure collection across MZ &amp; ZA</span><span>' . render_icon('phone') . ' Buyer and seller support</span></div><div class="header-inner"><div class="header-left"><details class="header-menu"><summary class="icon-btn" aria-label="Open menu">' . render_icon('menu') . '</summary><div class="menu-panel"><div class="menu-section"><span class="menu-title">Shop</span>';
  foreach ($menuCategories as $category) {
    echo '<a class="menu-link" href="' . h($category['url']) . '"><span>' . h($category['label']) . '</span><span aria-hidden="true">+</span></a>';
  }
  if ($currentUser) {
    echo '</div><div class="menu-section"><span class="menu-title">Account</span>';
    if ($isAdmin) {
      echo '<a class="menu-link" href="' . h(url_for('admin')) . '"><span>Admin Dashboard</span><span aria-hidden="true">&rarr;</span></a>';
    } else {
      echo '<a class="menu-link" href="' . h(url_for('orders')) . '"><span>Order History</span><span aria-hidden="true">&rarr;</span></a>';
    }
  }
  echo '</div><div class="menu-section"><span class="menu-title">Featured Products</span>';
  foreach ($featuredMenuProducts as $product) {
    echo '<a class="menu-product" href="' . h(url_for('product', ['id' => $product['id']])) . '"><img src="' . h(img_src($product['image'])) . '" alt="' . h(product_title($product)) . '"><span><strong>' . h(product_title($product)) . '</strong><small>' . h(price($product['price'], $product['currency'])) . '</small></span></a>';
  }
  echo '</div><div class="menu-actions">';
  if ($currentUser) {
    echo '<a class="btn btn-light" href="' . h(url_for('profile', ['id' => $currentUser['id']])) . '">' . h($currentUser['name']) . '</a><a class="btn btn-dark" href="' . h(url_for('logout')) . '">Sign out</a>';
  } else {
    echo '<a class="btn btn-light" href="' . h(url_for('signin')) . '">Sign in</a><a class="btn btn-dark" href="' . h(url_for('register')) . '">Sign up</a>';
  }
  if (!$isAdmin) {
    echo '<a class="btn btn-dark" href="' . h(url_for('sell')) . '">' . h(t('nav.sell')) . '</a>';
  }
  echo '</div></div></details><details class="header-search"><summary class="icon-btn" aria-label="Search products">' . render_icon('search') . '</summary><div class="search-panel"><label class="header-search-box">' . render_icon('search') . '<input id="headerSearchInput" type="search" autocomplete="off" placeholder="' . h(t('home.search')) . '"></label><div class="header-search-results" id="headerSearchResults">';
  foreach (all_products(null) as $product) {
    $searchText = implode(' ', [
      $product['title'] ?? '',
      $product['titlePt'] ?? '',
      $product['description'] ?? '',
      $product['descriptionPt'] ?? '',
      category_label($product['category'] ?? 'other'),
      $product['condition'] ?? '',
      $product['sellerName'] ?? '',
      $product['location'] ?? '',
      $product['country'] ?? '',
      $product['currency'] ?? '',
      (string)($product['price'] ?? ''),
    ]);
    echo '<a class="header-search-result" href="' . h(url_for('product', ['id' => $product['id']])) . '" data-search="' . h(strtolower($searchText)) . '"><img src="' . h(img_src($product['image'])) . '" alt="' . h(product_title($product)) . '"><span><strong>' . h(product_title($product)) . '</strong><small>' . h(category_label($product['category']) . ' · ' . price($product['price'], $product['currency']) . ' · ' . $product['location']) . '</small></span></a>';
  }
  echo '</div><p class="header-search-empty" id="headerSearchEmpty" hidden>No matching products found.</p></div></details></div><a class="brand" href="' . h(url_for('home')) . '" aria-label="Malawa Express home"><strong>MEZA</strong></a><nav class="desktop-nav">';
  foreach ($links as $key => $link) {
    echo '<a class="' . ($active === $key ? 'active' : '') . '" href="' . h($link['url']) . '">' . h($link['label']) . '</a>';
  }
  echo '</nav><div class="header-actions"><a class="cart-pill account-pill" href="' . h(url_for('profile', ['id' => $currentUser['id'] ?? 'u1'])) . '" aria-label="Account">' . render_icon('user') . '</a>';
  if (!$isAdmin) {
    echo '<a class="cart-pill" href="' . h(url_for('cart')) . '" aria-label="Cart">' . render_icon('cart') . '<span>' . h(cart_count()) . '</span></a>';
  }
  echo '<a class="lang-pill" href="' . h($toggleUrl) . '">' . strtoupper($toggleLang) . '</a></div></div><nav class="mobile-nav">';
  foreach ($links as $key => $link) {
    echo '<a class="' . ($active === $key ? 'active' : '') . '" href="' . h($link['url']) . '">' . h($link['label']) . '</a>';
  }
  echo '</nav></header>';
}

function render_product_card($product) {
  $title = product_title($product);
  $searchText = implode(' ', [
    $product['title'] ?? '',
    $product['titlePt'] ?? '',
    $product['description'] ?? '',
    $product['descriptionPt'] ?? '',
    category_label($product['category'] ?? 'other'),
    $product['condition'] ?? '',
    $product['sellerName'] ?? '',
    $product['location'] ?? '',
    $product['country'] ?? '',
    $product['currency'] ?? '',
    (string)($product['price'] ?? ''),
  ]);
  echo '<article class="product-card" data-category="' . h($product['category']) . '" data-search="' . h(strtolower($searchText)) . '" data-title="' . h(strtolower($product['title'] . ' ' . $product['titlePt'])) . '">';
  echo '<a href="' . h(url_for('product', ['id' => $product['id']])) . '"><div class="product-media"><img src="' . h(img_src($product['image'])) . '" alt="' . h($title) . '">';
  echo '<div class="badges">';
  if ($product['featured']) echo '<span>' . h(t('home.featured')) . '</span>';
  if ($product['condition'] === 'new') echo '<span class="light">' . h(t('product.new')) . '</span>';
  echo '</div></div><div class="product-body"><div class="product-top"><h3>' . h($title) . '</h3><div class="price"><strong>' . h(price($product['price'], $product['currency'])) . '</strong><small>' . h(converted_price($product['priceConverted'])) . '</small></div></div>';
  echo '<div class="seller-row"><div><p class="seller-name">' . h($product['sellerName']) . '</p><p class="muted">' . render_icon('pin') . h($product['location'] . ', ' . $product['country']) . '</p></div></div></div></a>';
  $currentUser = function_exists('current_user') ? current_user() : null;
  $isAdmin = is_admin($currentUser);
  if (!$isAdmin) {
    echo '<form class="card-cart-form" method="post" action="' . h(url_for('cart')) . '"><input type="hidden" name="action" value="add_to_cart"><input type="hidden" name="product_id" value="' . h($product['id']) . '"><input type="hidden" name="redirect" value="' . h($_SERVER['REQUEST_URI'] ?? 'index.php') . '"><button class="cart-add-btn" type="submit">' . render_icon('cart') . 'Add to cart</button></form>';
  } else {
    echo '<div class="card-cart-form" style="padding:10px;text-align:center;font-size:0.8rem;color:var(--muted);font-weight:bold;">Admin Mode (No Buy/Sell)</div>';
  }
  echo '</article>';
}

function render_footer() {
  $currentUser = function_exists('current_user') ? current_user() : null;
  $isAdmin = is_admin($currentUser);
  $startSellingLink = !$isAdmin ? '<a href="' . h(url_for('sell')) . '">Start Selling</a>' : '';
  echo '<footer class="site-footer"><div class="footer-inner"><div class="footer-brand"><h2>MEZA</h2><p>A cross-border marketplace connecting trusted traders across South Africa and Mozambique through a modern, bilingual experience.</p><div class="footer-icons"><a href="' . h(url_for('home')) . '" aria-label="Share Malawa Express">' . render_icon('send') . '</a><a href="' . h(url_for('about')) . '" aria-label="Learn about Malawa Express">' . render_icon('shield') . '</a></div></div><div><h3>Discover</h3><a href="' . h(url_for('home')) . '">Marketplace</a><a href="' . h(url_for('about')) . '">Our Story</a>' . $startSellingLink . '</div><div><h3>Support</h3><a href="' . h(url_for('messages')) . '">Messages</a><a href="' . h(url_for('register')) . '">Create Account</a><a href="' . h(url_for('signin')) . '">Sign in</a></div><div><h3>Contact</h3><p>South Africa | Mozambique</p><p>hello@malawa-express.com</p></div></div><div class="footer-bottom"><p>&copy; ' . date('Y') . ' Malawa Express. Bridging borders through trusted trade.</p></div></footer>';
}
