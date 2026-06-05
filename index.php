<?php
require __DIR__ . '/data.php';
require __DIR__ . '/database.php';
require __DIR__ . '/functions.php';

$page = $_GET['page'] ?? 'home';
$id = $_GET['id'] ?? null;
$validPages = ['home','product','profile','messages','cart','about','sell','register','register_buyer','signin','checkout','payment_return','payment_cancel','payment_notify','logout','orders','admin'];
if (!in_array($page, $validPages, true)) $page = 'home';
$posted = $_SERVER['REQUEST_METHOD'] === 'POST';
$notice = $_GET['notice'] ?? '';
$error = '';
$currentUser = current_user();

if ($page === 'admin') {
  if (!$currentUser) {
    redirect_to('signin', ['notice' => 'Please sign in first.']);
  }
  if (!is_admin($currentUser)) {
    redirect_to('home', ['notice' => 'Access denied. Administrator privileges required.']);
  }
}

if (in_array($page, ['cart', 'sell', 'checkout', 'orders'], true) && $currentUser && is_admin($currentUser)) {
  redirect_to('admin', ['notice' => 'Access denied. Administrator accounts cannot access ' . $page . '.']);
}

if ($page === 'logout') {
  session_destroy();
  redirect_to('home', ['notice' => 'Signed out successfully.']);
}

if ($page === 'payment_notify' && $posted) {
  $orderId = $_POST['m_payment_id'] ?? $_POST['custom_int1'] ?? null;
  if ($orderId && strtoupper($_POST['payment_status'] ?? '') === 'COMPLETE') {
    db_update_order_status($orderId, 'paid');
  }
  http_response_code(200);
  echo 'OK';
  exit;
}

if ($page === 'payment_cancel' && isset($_GET['order'])) {
  db_update_order_status($_GET['order'], 'cancelled');
}

if ($page === 'payment_return' && isset($_GET['order'])) {
  db_update_order_status($_GET['order'], 'paid');
  $ord = db_find_order($_GET['order']);
  if ($ord) {
    cart_remove($ord['product_id']);
  }
}

if ($posted) {
  try {
    // Prevent administrators from executing buying or selling actions
    if ($currentUser && is_admin($currentUser)) {
      $action = $_POST['action'] ?? '';
      if (in_array($action, ['add_to_cart', 'update_cart', 'remove_from_cart'], true) || in_array($page, ['checkout', 'sell'], true)) {
        throw new RuntimeException('Action denied. Administrators are not permitted to buy or sell.');
      }
    }

    // Admin POST action handlers
    if (str_starts_with($_POST['action'] ?? '', 'admin_')) {
      if (!$currentUser || !is_admin($currentUser)) {
        throw new RuntimeException('Access denied. Administrator privileges required.');
      }
      
      if ($_POST['action'] === 'admin_update_user') {
        $userId = $_POST['user_id'] ?? '';
        $data = [
          'name' => trim($_POST['name'] ?? ''),
          'verified' => isset($_POST['verified']) ? 1 : 0,
          'rating' => (float)($_POST['rating'] ?? 5.0),
          'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
        ];
        db_update_user($userId, $data);
        redirect_to('admin', ['tab' => 'users', 'notice' => 'User account updated successfully.']);
      }

      if ($_POST['action'] === 'admin_delete_user') {
        $userId = $_POST['user_id'] ?? '';
        db_delete_user($userId);
        redirect_to('admin', ['tab' => 'users', 'notice' => 'User account deleted.']);
      }

      if ($_POST['action'] === 'admin_update_product') {
        $productId = $_POST['product_id'] ?? '';
        $data = [
          'price' => (float)($_POST['price'] ?? 0.0),
          'featured' => isset($_POST['featured']) ? 1 : 0,
          'category' => $_POST['category'] ?? 'other',
        ];
        if (isset($_POST['title'])) {
          $data['title'] = trim($_POST['title']);
        }
        db_update_product($productId, $data);
        redirect_to('admin', ['tab' => 'listings', 'notice' => 'Listing updated successfully.']);
      }

      if ($_POST['action'] === 'admin_delete_product') {
        $productId = $_POST['product_id'] ?? '';
        db_delete_product($productId);
        redirect_to('admin', ['tab' => 'listings', 'notice' => 'Listing deleted.']);
      }

      if ($_POST['action'] === 'admin_update_order_status') {
        $orderId = $_POST['order_id'] ?? '';
        $status = $_POST['status'] ?? 'placed';
        db_update_order_status($orderId, $status);
        redirect_to('admin', ['tab' => 'orders', 'notice' => 'Order status updated successfully.']);
      }

      if ($_POST['action'] === 'admin_delete_order') {
        $orderId = $_POST['order_id'] ?? '';
        db_delete_order($orderId);
        redirect_to('admin', ['tab' => 'orders', 'notice' => 'Order deleted.']);
      }
    }

    if (in_array($_POST['action'] ?? '', ['add_to_cart', 'update_cart', 'remove_from_cart'], true)) {
      $action = $_POST['action'];
      $productId = $_POST['product_id'] ?? '';

      if ($action === 'add_to_cart') {
        if (!cart_add($productId, $_POST['quantity'] ?? 1)) throw new RuntimeException('Product not found.');
        $target = $_POST['redirect'] ?? url_for('cart', ['notice' => 'Added to cart.']);
        if (str_contains($target, "\n") || str_starts_with($target, 'http')) $target = url_for('cart', ['notice' => 'Added to cart.']);
        header('Location: ' . $target);
        exit;
      }

      if ($action === 'update_cart') {
        cart_update($productId, $_POST['quantity'] ?? 1);
        redirect_to('cart', ['notice' => 'Cart updated.']);
      }

      if ($action === 'remove_from_cart') {
        cart_remove($productId);
        redirect_to('cart', ['notice' => 'Item removed.']);
      }
    }

    if (!db_available() && !in_array($page, ['signin', 'register_buyer'], true)) {
      throw new RuntimeException('Database is not connected yet. Import database.sql and create config.php first.');
    }

    if ($page === 'register_buyer') {
      $_SESSION['user_id'] = db_create_user($_POST);
      redirect_to('profile', ['id' => $_SESSION['user_id'], 'notice' => 'Buyer account created successfully.']);
    }

    if ($page === 'signin') {
      $user = db_authenticate($_POST['email'] ?? '', $_POST['password'] ?? '');
      if (!$user) throw new RuntimeException('Email or password is incorrect.');
      $_SESSION['user_id'] = $user['id'];
      redirect_to('home', ['notice' => 'Signed in successfully.']);
    }

    if (!$currentUser) {
      throw new RuntimeException('Please sign in first.');
    }

    if ($page === 'sell') {
      $productId = db_create_product($_POST, $currentUser['id']);
      redirect_to('product', ['id' => $productId, 'notice' => 'Listing saved successfully.']);
    }

    if ($page === 'product' && ($_POST['action'] ?? '') === 'save_listing') {
      db_save_listing($currentUser['id'], $id);
      redirect_to('product', ['id' => $id, 'notice' => 'Listing saved to your account.']);
    }

    if ($page === 'checkout') {
      if (!payfast_configured()) {
        throw new RuntimeException('PayFast sandbox is not configured yet. Add your sandbox Merchant ID and Merchant Key to config.php.');
      }
      $product = find_product($id ?: '1');
      if (!$product) throw new RuntimeException('Product not found.');
      $orderId = db_create_order($_POST, $currentUser['id'], $product);
      redirect_to('checkout', ['id' => $product['id'], 'payfast_order' => $orderId]);
    }

    if ($page === 'messages') {
      $receiverId = $_POST['receiver_id'] ?? $_GET['user'] ?? null;
      if (!$receiverId) throw new RuntimeException('Choose a seller to message first.');
      db_send_message($currentUser['id'], $receiverId, $_POST['message'] ?? '');
      redirect_to('messages', ['user' => $receiverId, 'notice' => 'Message sent.']);
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}
?><!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Malawa Express</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg?v=1">
  <meta name="theme-color" content="#050505">
  <link rel="stylesheet" href="style.css?v=<?= h(filemtime(__DIR__ . '/style.css')) ?>">
</head>
<body>
<?php render_header($page === 'product' ? 'home' : $page); ?>
<?php if ($notice || $error): ?>
  <div class="notice-bar"><?= app_notice($notice ?: $error, $error ? 'error' : 'success') ?></div>
<?php endif; ?>

<?php if ($page === 'home'): ?>
  <section class="hero">
    <div class="hero-watermark">MEZA</div>
    <div class="hero-content">
      <div class="hero-copy">
        <p class="hero-kicker">MZ &harr; ZA Marketplace</p>
        <h1><?= h(t('home.title')) ?></h1>
        <p><?= h(t('home.subtitle')) ?></p>
        <span class="hero-brandline">Reliable. Secure. Regional.</span>
      </div>
      <label class="search-box"><?= render_icon('search') ?><input id="searchInput" placeholder="<?= h(t('home.search')) ?>"></label>
    </div>
  </section>
  <section class="trust-band">
    <div><span class="terracotta"><?= render_icon('check') ?></span><strong><?= h(t('trust.verified')) ?></strong><small>Authenticity guaranteed</small></div>
    <div><span class="gold"><?= render_icon('star') ?></span><strong><?= h(t('trust.secure')) ?></strong><small>Seamless transactions</small></div>
    <div><span class="green"><?= render_icon('shield') ?></span><strong><?= h(t('trust.protection')) ?></strong><small>Complete peace of mind</small></div>
  </section>
  <section class="brand-strip">
    <div class="brand-mark">ME</div>
    <div>
      <strong>Malawa Express</strong>
      <span>Cross-border trade for MZ &harr; ZA</span>
    </div>
  </section>
  <main class="container">
    <div class="category-tabs" id="categoryTabs">
      <?php foreach (product_categories() as $cat): ?>
        <a class="<?= $cat === 'all' ? 'active' : '' ?>" href="<?= h(category_url($cat)) ?>" data-category="<?= h($cat) ?>"><?= h(category_label($cat)) ?></a>
      <?php endforeach; ?>
    </div>
    <section class="listing-section">
      <div class="section-heading"><h2><?= h(t('home.featured')) ?></h2><a href="<?= h(category_url('all')) ?>">View All</a></div>
      <div class="product-grid">
        <?php foreach (array_slice(all_products(true), 0, 8) as $product) render_product_card($product); ?>
      </div>
    </section>
    <section class="listing-section">
      <div class="section-heading"><h2><?= h(t('home.recent')) ?></h2></div>
      <div class="product-grid">
        <?php foreach (array_slice(all_products(false), 0, 8) as $product) render_product_card($product); ?>
      </div>
      <p class="empty-state" id="emptyState" hidden>No products found matching your criteria.</p>
    </section>
    <?php foreach (array_slice(product_categories(), 1) as $cat): ?>
      <section class="listing-section category-preview">
        <div class="section-heading"><h2><?= h(category_label($cat)) ?></h2></div>
        <div class="product-grid">
          <?php foreach (products_by_category($cat, 4) as $product) render_product_card($product); ?>
        </div>
        <div class="section-actions"><a class="btn btn-light" href="<?= h(category_url($cat)) ?>">See More</a></div>
      </section>
    <?php endforeach; ?>
  </main>

<?php elseif ($page === 'product'):
  $product = find_product($id ?: '1');
  if (!$product):
    echo '<main class="center-screen">Product not found</main>';
  else:
    $seller = find_user($product['sellerId']);
    $productReviews = product_reviews($product['id']);
?>
  <main class="container detail-layout">
    <div class="detail-main">
      <div class="detail-image"><img src="<?= h(img_src($product['image'])) ?>" alt="<?= h(product_title($product)) ?>"></div>
      <section class="panel">
        <div class="product-title-row"><div><h1><?= h(product_title($product)) ?></h1><p class="muted"><?= render_icon('pin') ?><?= h($product['location'] . ', ' . $product['country']) ?></p></div><div class="big-price"><?= h(price($product['price'], $product['currency'])) ?><small><?= h(converted_price($product['priceConverted'])) ?></small></div></div>
        <p><span class="tag"><?= h(t('product.' . $product['condition'])) ?></span> <span class="tag gray"><?= h(t('category.' . $product['category'])) ?></span></p>
        <hr><h2><?= h(t('product.description')) ?></h2><p class="lead"><?= h(product_description($product)) ?></p>
      </section>
      <section class="panel">
        <h2><?= h(t('product.reviews')) ?> (<?= count($productReviews) ?>)</h2>
        <?php foreach ($productReviews as $review): ?>
          <article class="review"><img src="<?= h(img_src($review['avatar'])) ?>" alt="<?= h($review['userName']) ?>"><div><strong><?= h($review['userName']) ?></strong><small><?= h($review['date']) ?></small><p><?= h($review['comment']) ?></p></div></article>
        <?php endforeach; ?>
      </section>
    </div>
    <aside class="sidebar">
      <section class="panel"><h3>Seller Information</h3><a class="seller-card" href="<?= h(url_for('profile', ['id'=>$seller['id']])) ?>"><img src="<?= h(img_src($seller['avatar'])) ?>" alt="<?= h($seller['name']) ?>"><div><strong><?= h($seller['name']) ?></strong><p><?= h($seller['totalReviews']) ?> <?= h(t('product.reviews')) ?></p></div></a><div class="mini-stats"><div><span><?= h(t('profile.responseRate')) ?></span><strong><?= h($seller['responseRate']) ?>%</strong></div><div><span><?= h(t('profile.sold')) ?></span><strong><?= h($seller['itemsSold']) ?></strong></div></div><a class="btn btn-green full" href="<?= h(url_for('messages', ['user' => $seller['id']])) ?>">Contact Seller</a>
      <?php if ($currentUser && is_admin($currentUser)): ?>
        <div style="padding: 12px; text-align: center; font-size: 0.95rem; color: var(--muted); font-weight: bold; border: 1px dashed var(--line); border-radius: 8px; margin-top: 10px;">Admin Mode (No Buy/Sell)</div>
      <?php else: ?>
        <form method="post" action="<?= h(url_for('cart')) ?>"><input type="hidden" name="action" value="add_to_cart"><input type="hidden" name="product_id" value="<?= h($product['id']) ?>"><input type="hidden" name="redirect" value="<?= h(url_for('cart', ['notice' => 'Added to cart.'])) ?>"><button class="btn btn-orange full" type="submit"><?= render_icon('cart') ?>Add to Cart</button></form><a class="btn btn-light full" href="<?= h(url_for('checkout', ['id' => $product['id']])) ?>">Buy Now</a><form method="post" action="<?= h(url_for('product', ['id' => $product['id']])) ?>"><input type="hidden" name="action" value="save_listing"><button class="btn btn-light full" type="submit">Save Listing</button></form>
      <?php endif; ?></section>
      <section class="panel"><h3><?= render_icon('shield') ?><?= h(t('payment.methods')) ?></h3><?php foreach ($seller['paymentMethods'] as $method): ?><p class="method">✓ <?= h($method) ?></p><?php endforeach; ?><small><?= h(t('payment.secureTransaction')) ?></small></section>
      <section class="panel"><h3><?= h(t('collection.title')) ?></h3><?php foreach (all_collection_points($product['country']) as $point): ?><div class="collection"><strong><?= h($point['city']) ?></strong><small><?= h($point['address']) ?></small></div><?php endforeach; ?></section>
    </aside>
  </main>
<?php endif; ?>

<?php elseif ($page === 'profile'):
  $user = find_user($id ?: ($currentUser['id'] ?? 'u1'));
  if (!$user):
    echo '<main class="center-screen">User not found</main>';
  else:
?>
  <main class="container">
    <section class="profile-hero"><div class="cover"></div><div class="profile-info"><img src="<?= h(img_src($user['avatar'])) ?>" alt="<?= h($user['name']) ?>"><div><h1><?= h($user['name']) ?></h1><p><?= render_icon('pin') ?><?= h($user['location'] . ', ' . $user['country']) ?> · <?= h(t('profile.member')) ?> <?= h(date('M j, Y', strtotime($user['memberSince']))) ?></p></div><a class="btn btn-green" href="<?= h(url_for('messages')) ?>"><?= h(t('product.contactSeller')) ?></a></div></section>
    <section class="stats-grid"><?php foreach ([[t('profile.listings'),$user['activeListings']],[t('profile.sold'),$user['itemsSold']],[t('product.rating'),$user['rating']],[t('profile.responseRate'),$user['responseRate'].'%']] as $stat): ?><div class="panel"><small><?= h($stat[0]) ?></small><strong><?= h($stat[1]) ?></strong></div><?php endforeach; ?></section>
    <section class="panel"><h2><?= h(t('payment.accepted') . ' ' . t('payment.methods')) ?></h2><div class="pill-row"><?php foreach ($user['paymentMethods'] as $method): ?><span><?= h($method) ?></span><?php endforeach; ?></div></section>
    
    <?php if ($currentUser && (string)$user['id'] === (string)$currentUser['id']): ?>
      <?php 
        $userOrders = function_exists('db_user_orders') ? db_user_orders($currentUser['id']) : [];
      ?>
      <section class="panel">
        <h2>Order History</h2>
        <?php if (empty($userOrders)): ?>
          <p class="muted" style="margin-top: 10px;">You have not placed or received any orders yet.</p>
        <?php else: ?>
          <div class="orders-list" style="display: flex; flex-direction: column; gap: 16px; margin-top: 16px;">
            <?php foreach ($userOrders as $ord): 
              $isSeller = (string)$ord['seller_id'] === (string)$currentUser['id'];
              $statusClass = strtolower($ord['status']);
              
              // Map statuses to elegant colors
              $statusColors = [
                'placed' => ['bg' => '#fef3c7', 'text' => '#d97706'], // Amber
                'payment_pending_confirmation' => ['bg' => '#ffedd5', 'text' => '#ea580c'], // Orange
                'paid' => ['bg' => '#dcfce7', 'text' => '#16a34a'], // Green
                'cancelled' => ['bg' => '#fee2e2', 'text' => '#dc2626'] // Red
              ];
              $style = $statusColors[$statusClass] ?? ['bg' => '#f3f4f6', 'text' => '#4b5563'];
            ?>
              <div class="order-card" style="border: 1px solid var(--border); padding: 20px; border-radius: 12px; display: flex; gap: 20px; align-items: center; justify-content: space-between; flex-wrap: wrap; background-color: rgba(255,255,255,0.02); transition: transform 0.2s ease;">
                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                  <img src="<?= h(img_src($ord['image_key'] ?: 'smartphone-black')) ?>" alt="<?= h($ord['title']) ?>" style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">
                  <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: bold; letter-spacing: 0.05em; color: <?= $isSeller ? '#3b82f6' : '#10b981' ?>; background-color: <?= $isSeller ? 'rgba(59, 130, 246, 0.1)' : 'rgba(16, 185, 129, 0.1)' ?>; padding: 3px 8px; border-radius: 4px; display: inline-block; margin-bottom: 6px;">
                      <?= $isSeller ? 'Selling' : 'Buying' ?>
                    </span>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 600; color: var(--text);"><?= h($ord['title']) ?></h3>
                    <p style="margin: 6px 0; font-size: 0.9rem;" class="muted">
                      Order #<?= h($ord['id']) ?> · 
                      <strong><?= h(price($ord['total_amount'], $ord['currency'])) ?></strong> ·
                      <?= $isSeller ? 'Buyer: ' . h($ord['buyer_name']) : 'Seller: ' . h($ord['seller_name']) ?>
                    </p>
                    <small class="muted" style="font-size: 0.8rem;"><?= h(date('M j, Y \a\t H:i', strtotime($ord['created_at']))) ?></small>
                  </div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                  <span class="status-pill" style="padding: 6px 14px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; background-color: <?= $style['bg'] ?>; color: <?= $style['text'] ?>;">
                    <?= str_replace('_', ' ', $ord['status']) ?>
                  </span>
                  <?php if (!$isSeller && $ord['status'] === 'placed'): ?>
                    <a class="btn btn-orange" href="<?= h(url_for('checkout', ['id' => $ord['product_id'], 'payfast_order' => $ord['id']])) ?>" style="font-size: 0.8rem; padding: 6px 12px; margin-top: 4px; display: inline-block;">Complete Payment</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="panel"><h2><?= h(t('profile.myListings')) ?></h2><div class="product-grid"><?php foreach (user_products($user['id']) as $product) render_product_card($product); ?></div></section>
  </main>

<?php endif; elseif ($page === 'messages'): ?>
  <?php if (!$currentUser || !db_available()): ?>
    <main class="form-page"><section class="panel"><h1><?= h(t('messages.inbox')) ?></h1><p class="error"><?= h($currentUser ? 'Connect the database first to use real messages.' : 'Please sign in to send and read messages.') ?></p><a class="btn btn-dark" href="<?= h(url_for('signin')) ?>">Sign in</a></section></main>
  <?php else:
    $dbConversations = db_conversations($currentUser['id']);
    $otherId = $_GET['user'] ?? ($dbConversations[0]['otherUser'] ?? null);
    $otherUser = $otherId ? find_user($otherId) : null;
    $threadMessages = $otherId ? db_messages_between($currentUser['id'], $otherId) : [];
  ?>
    <main class="messages-layout">
      <aside class="conversation-list"><h1><?= h(t('messages.inbox')) ?></h1><label class="field"><?= render_icon('search') ?><input placeholder="Search messages..."></label><?php foreach ($dbConversations as $conversation): ?><a href="<?= h(url_for('messages', ['user' => $conversation['otherUser']])) ?>"><article class="<?= (string)$conversation['otherUser'] === (string)$otherId ? 'selected' : '' ?>"><img src="<?= h(img_src($conversation['avatar'])) ?>" alt="<?= h($conversation['name']) ?>"><div><strong><?= h($conversation['name']) ?> ✓</strong><p><?= h($conversation['lastMessage']) ?></p></div></article></a><?php endforeach; ?></aside>
      <section class="chat-panel">
        <?php if ($otherUser): ?>
          <header><img src="<?= h(img_src($otherUser['avatar'])) ?>" alt="<?= h($otherUser['name']) ?>"><div><strong><?= h($otherUser['name']) ?> ✓</strong><p><?= h($otherUser['location'] . ', ' . $otherUser['country']) ?></p></div></header>
          <div class="chat-body"><?php foreach ($threadMessages as $message): ?><p class="bubble <?= (int)$message['sender_id'] === (int)$currentUser['id'] ? 'mine' : '' ?>"><?= h($message['body']) ?><small><?= h(date('H:i', strtotime($message['created_at']))) ?></small></p><?php endforeach; ?></div>
          <form method="post" action="<?= h(url_for('messages', ['user' => $otherUser['id']])) ?>"><input type="hidden" name="receiver_id" value="<?= h($otherUser['id']) ?>"><input name="message" placeholder="<?= h(t('messages.typeMessage')) ?>" required><button class="btn btn-green" type="submit"><?= render_icon('send') ?></button></form>
        <?php else: ?>
          <div class="empty-chat"><h2>No conversation selected</h2><p>Open a product and contact the seller to start a message thread.</p></div>
        <?php endif; ?>
      </section>
    </main>
  <?php endif; ?>

<?php elseif ($page === 'cart'):
  $cartItems = cart_items();
?>
  <main class="container cart-page">
    <section class="section-heading"><h2>Your Cart</h2><span><?= h(cart_count()) ?> item<?= cart_count() === 1 ? '' : 's' ?></span></section>
    <?php if (!$cartItems): ?>
      <section class="panel cart-empty">
        <h1>Your cart is empty</h1>
        <p class="lead">Add products from the marketplace and they will appear here.</p>
        <a class="btn btn-orange" href="<?= h(url_for('home')) ?>"><?= render_icon('cart') ?>Browse Products</a>
      </section>
    <?php else: ?>
      <section class="cart-layout">
        <div class="cart-list">
          <?php foreach ($cartItems as $item):
            $product = $item['product'];
          ?>
            <article class="cart-row">
              <a href="<?= h(url_for('product', ['id' => $product['id']])) ?>"><img src="<?= h(img_src($product['image'])) ?>" alt="<?= h(product_title($product)) ?>"></a>
              <div>
                <h3><?= h(product_title($product)) ?></h3>
                <p class="muted"><?= render_icon('pin') ?><?= h($product['location'] . ', ' . $product['country']) ?></p>
                <p class="cart-price"><?= h(price($product['price'], $product['currency'])) ?> <small><?= h(converted_price($product['priceConverted'])) ?></small></p>
              </div>
              <form class="quantity-form" method="post" action="<?= h(url_for('cart')) ?>">
                <input type="hidden" name="action" value="update_cart">
                <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
                <label>Qty<input type="number" name="quantity" min="1" value="<?= h($item['quantity']) ?>"></label>
                <button class="btn btn-light" type="submit">Update</button>
              </form>
              <div class="cart-row-total">
                <strong><?= h(price($item['lineTotal'], $product['currency'])) ?></strong>
                <form method="post" action="<?= h(url_for('cart')) ?>">
                  <input type="hidden" name="action" value="remove_from_cart">
                  <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
                  <button class="text-button" type="submit">Remove</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <aside class="panel cart-summary">
          <h2>Cart Summary</h2>
          <p><span>Items</span><strong><?= h(cart_count()) ?></strong></p>
          <?php foreach (cart_totals_by_currency() as $currency => $total): ?>
            <p><span>Estimated <?= h($currency) ?> total</span><strong><?= h(price($total, $currency)) ?></strong></p>
          <?php endforeach; ?>
          <small>Checkout currently creates one secure order at a time. Choose an item below to continue.</small>
          <?php foreach ($cartItems as $item): ?>
            <a class="btn btn-orange full" href="<?= h(url_for('checkout', ['id' => $item['product']['id']])) ?>">Checkout <?= h(product_title($item['product'])) ?></a>
          <?php endforeach; ?>
        </aside>
      </section>
    <?php endif; ?>
  </main>

<?php elseif ($page === 'about'): ?>
  <section class="about-hero"><h1><?= lang() === 'pt' ? 'Sobre Malawa Express' : 'About Malawa Express' ?></h1><p><?= lang() === 'pt' ? 'Conectando Comerciantes Sul-Africanos e Moçambicanos' : 'Connecting South African and Mozambican Traders' ?></p><small><?= lang() === 'pt' ? 'Um mercado bilingue dedicado a empoderar comerciantes informais através das fronteiras.' : 'A dedicated bilingual marketplace empowering informal traders across borders.' ?></small></section>
  <main class="container about-page">
    <section class="panel"><h2><?= lang() === 'pt' ? 'Nossa Missão' : 'Our Mission' ?></h2><p class="lead"><?= lang() === 'pt' ? 'Malawa Express visa empoderar comerciantes informais na África do Sul e Moçambique, fornecendo um mercado digital seguro e bilingue.' : 'Malawa Express aims to empower informal traders in South Africa and Mozambique by providing a secure, bilingual digital marketplace. We enable cross-border commerce through integrated payment systems, verified seller profiles, and simplified logistics.' ?></p></section>
    <section class="panel"><h2><?= lang() === 'pt' ? 'The Problem We Solve' : 'The Problem We Solve' ?></h2><?php foreach (['Limited digital infrastructure for informal C2C trade','Heavy reliance on cash transactions','High logistical and border costs','Trust deficit between buyers and sellers','Language barriers in cross-border commerce'] as $item): ?><p class="list-line"><?= render_icon('check') ?><?= h($item) ?></p><?php endforeach; ?></section>
    <section><h2 class="center"><?= lang() === 'pt' ? 'Nossa Solução' : 'Our Solution' ?></h2><div class="feature-grid"><?php foreach ([['Bilingual Platform','Full English and Portuguese support for seamless communication'],['Secure Payments','Integrated M-Pesa, mKesh, eMola, and South African payment gateways'],['Verified Sellers','Identity verification and rating systems to build trust'],['Collection Points','Strategic hubs in major cities to simplify logistics']] as $feature): ?><div class="panel feature"><?= render_icon('shield') ?><h3><?= h($feature[0]) ?></h3><p><?= h($feature[1]) ?></p></div><?php endforeach; ?></div></section>
    <section class="impact"><h2>Our Impact</h2><div><?php foreach ([['100+','Pilot Users'],['40%','Reduction in Cash Transactions'],['5','Collection Points'],['2','Countries Connected']] as $stat): ?><p><strong><?= h($stat[0]) ?></strong><span><?= h($stat[1]) ?></span></p><?php endforeach; ?></div></section>
  </main>

<?php elseif ($page === 'sell'): ?>
  <main class="form-page"><section class="panel"><h1><?= h(t('nav.sell')) ?></h1><?php if (!$currentUser): ?><p class="error">Please sign in before creating a listing.</p><?php endif; ?><form class="stack-form" method="post" action="<?= h(url_for('sell')) ?>"><div class="upload-box"><?= render_icon('upload') ?><strong><?= lang() === 'pt' ? 'Clique para carregar imagens' : 'Click to upload images' ?></strong><small>PNG, JPG up to 10MB</small></div><?php foreach ([['title','Title (English)'],['titlePt','Title (Portuguese)'],['price',lang()==='pt'?'Preço':'Price'],['location',t('product.location')]] as $field): ?><label><?= h($field[1]) ?><input name="<?= h($field[0]) ?>" <?= $field[0]==='price'?'type="number" min="1" step="1"':'' ?> required></label><?php endforeach; ?><label>Description (English)<textarea name="description" rows="4" required></textarea></label><label>Description (Portuguese)<textarea name="descriptionPt" rows="4" required></textarea></label><div class="two-col"><label>Category<select name="category"><option value="electronics"><?= h(t('category.electronics')) ?></option><option value="clothing"><?= h(t('category.clothing')) ?></option><option value="handicrafts"><?= h(t('category.handicrafts')) ?></option><option value="household"><?= h(t('category.household')) ?></option><option value="other"><?= h(t('category.other')) ?></option></select></label><label>Country<select name="country"><option value="ZA">South Africa</option><option value="MZ">Mozambique</option></select></label></div><div class="two-col"><label>Currency<select name="currency"><option value="ZAR">ZAR (South African Rand)</option><option value="MZN">MZN (Mozambican Metical)</option></select></label><label><?= h(t('product.condition')) ?><select name="condition"><option value="new"><?= h(t('product.new')) ?></option><option value="used"><?= h(t('product.used')) ?></option></select></label></div><label><?= h(t('collection.choose')) ?><select name="collectionPoint" required><option value="">Select a collection point...</option><?php foreach (all_collection_points() as $point): ?><option><?= h($point['city'] . ', ' . $point['country'] . ' - ' . $point['address']) ?></option><?php endforeach; ?></select></label><p class="notice">Buyers will be able to pay using M-Pesa, mKesh, eMola, or South African payment methods.</p><div class="two-col"><button class="btn btn-green" type="submit">List Product</button><a class="btn btn-light" href="<?= h(url_for('home')) ?>"><?= h(t('common.cancel')) ?></a></div></form></section></main>

<?php elseif ($page === 'register'): ?>
  <main class="register-page"><section class="register-intro"><h1>Join the Experience</h1><p>Choose how you want to interact with our platform. Experience premium craftsmanship and authentic interactions.</p></section><section class="choice-grid"><article class="choice-card buyer"><?= render_icon('bag') ?><h2>I'm a Buyer</h2><p>Discover unique, hand-crafted products directly from authentic creators.</p><ul><li>Access to exclusive collections</li><li>Secure, guaranteed transactions</li><li>Direct communication with artisans</li></ul><a class="btn btn-dark full" href="<?= h(url_for('register_buyer')) ?>">Register as Buyer</a></article><article class="choice-card seller"><?= render_icon('store') ?><h2>I'm a Seller</h2><p>Join our curated marketplace of premium artisans.</p><ul><li>Premium storefront presence</li><li>Powerful analytics and tools</li><li>Dedicated seller support</li></ul><a class="btn btn-green full" href="<?= h(url_for('sell')) ?>">Start Selling</a></article></section><p class="signin">Already a buyer? <a href="<?= h(url_for('signin')) ?>">Sign in</a></p></main>
<?php elseif ($page === 'register_buyer'): ?>
  <main class="form-page">
    <section class="panel">
      <h1>Register as Buyer</h1>
      <p class="lead">Create a buyer account to message sellers, save products, and buy securely across South Africa and Mozambique.</p>
      <form class="stack-form" method="post" action="<?= h(url_for('register_buyer')) ?>">
        <div class="two-col">
          <label>First name<input name="first_name" autocomplete="given-name" required></label>
          <label>Last name<input name="last_name" autocomplete="family-name" required></label>
        </div>
        <label>Email address<input type="email" name="email" autocomplete="email" required></label>
        <label>Phone number<input type="tel" name="phone" autocomplete="tel" required></label>
        <div class="two-col">
          <label>Country<select name="country" required><option value="">Choose country...</option><option>South Africa</option><option>Mozambique</option></select></label>
          <label>Preferred language<select name="language" required><option>English</option><option>Portuguese</option></select></label>
        </div>
        <label>Password<input type="password" name="password" autocomplete="new-password" required></label>
        <p class="notice">Buyer accounts can use secure payments, collection points, and in-platform messaging.</p>
        <div class="two-col">
          <button class="btn btn-dark" type="submit">Create Buyer Account</button>
          <a class="btn btn-light" href="<?= h(url_for('register')) ?>">Back</a>
        </div>
      </form>
    </section>
  </main>
<?php elseif ($page === 'signin'): ?>
  <main class="form-page">
    <section class="panel">
      <h1>Sign in</h1>
      <p class="lead">Access your buyer account, messages, saved products, and secure checkout.</p>
      <form class="stack-form" method="post" action="<?= h(url_for('signin')) ?>">
        <label>Email address<input type="email" name="email" autocomplete="email" required></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <div class="two-col">
          <button class="btn btn-dark" type="submit">Sign in</button>
          <a class="btn btn-light" href="<?= h(url_for('register_buyer')) ?>">Create Buyer Account</a>
        </div>
      </form>
    </section>
  </main>
<?php elseif ($page === 'checkout'):
  $product = find_product($id ?: '1');
  if (!$product):
    echo '<main class="center-screen">Product not found</main>';
  else:
    $payfastOrder = isset($_GET['payfast_order']) ? db_find_order($_GET['payfast_order']) : null;
?>
  <main class="form-page">
    <section class="panel">
      <h1>Checkout</h1>
      <?php if (!$currentUser): ?><p class="error">Please sign in before placing an order.</p><?php endif; ?>
      <div class="checkout-summary">
        <img src="<?= h(img_src($product['image'])) ?>" alt="<?= h(product_title($product)) ?>">
        <div>
          <h2><?= h(product_title($product)) ?></h2>
          <p class="lead"><?= h(price($product['price'], $product['currency'])) ?> <small><?= h(converted_price($product['priceConverted'])) ?></small></p>
          <p class="muted"><?= render_icon('pin') ?><?= h($product['location'] . ', ' . $product['country']) ?></p>
        </div>
      </div>
      <?php if ($payfastOrder): ?>
        <p class="notice">Redirecting to PayFast sandbox for secure payment.</p>
        <?php render_payfast_form($payfastOrder); ?>
      <?php else: ?>
        <form class="stack-form" method="post" action="<?= h(url_for('checkout', ['id' => $product['id']])) ?>">
          <label>Payment method<select name="payment_method" required><option value="PayFast Sandbox">PayFast Sandbox</option></select></label>
          <label><?= h(t('collection.choose')) ?><select name="collection_point" required><?php foreach (all_collection_points($product['country']) as $point): ?><option><?= h($point['city'] . ', ' . $point['country'] . ' - ' . $point['address']) ?></option><?php endforeach; ?></select></label>
          <div class="two-col">
            <button class="btn btn-dark" type="submit">Pay with PayFast</button>
            <a class="btn btn-light" href="<?= h(url_for('product', ['id' => $product['id']])) ?>">Back to Product</a>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </main>
<?php endif; ?>
<?php elseif ($page === 'payment_return'): 
  $orderId = $_GET['order'] ?? null;
  $ord = $orderId ? db_find_order($orderId) : null;
?>
  <main class="form-page">
    <section class="panel" style="max-width: 600px; margin: 40px auto; text-align: center; padding: 40px; border-radius: 16px; border: 1px solid var(--border); background-color: rgba(255,255,255,0.01);">
      <div style="width: 80px; height: 80px; background: rgba(22, 163, 74, 0.1); color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2.5rem; font-weight: bold; box-shadow: 0 0 20px rgba(22, 163, 74, 0.2);">
        ✓
      </div>
      <h1 style="font-size: 2.2rem; font-weight: 700; margin-bottom: 8px; color: var(--text);">Payment Successful!</h1>
      <p class="success" style="font-size: 1.1rem; color: #16a34a; margin-bottom: 30px;">Thank you! Your transaction was successfully completed.</p>
      
      <?php if ($ord): ?>
        <div style="border: 1px solid var(--border); border-radius: 12px; padding: 24px; text-align: left; margin-bottom: 30px; background: rgba(255,255,255,0.02);">
          <h3 style="margin-top: 0; border-bottom: 1px solid var(--border); padding-bottom: 12px; font-size: 1.25rem; font-weight: 600; color: var(--text);">Order Summary</h3>
          <table style="width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 0.95rem; line-height: 1.8;">
            <tr>
              <td style="color: var(--muted); padding: 4px 0; width: 40%;">Order ID:</td>
              <td style="font-weight: 600; padding: 4px 0;">#<?= h($ord['id']) ?></td>
            </tr>
            <tr>
              <td style="color: var(--muted); padding: 4px 0;">Product:</td>
              <td style="font-weight: 600; padding: 4px 0;"><?= h($ord['title']) ?></td>
            </tr>
            <tr>
              <td style="color: var(--muted); padding: 4px 0;">Amount Paid:</td>
              <td style="font-weight: 700; font-size: 1.15rem; color: var(--text); padding: 4px 0;"><?= h(price($ord['total_amount'], $ord['currency'])) ?></td>
            </tr>
            <tr>
              <td style="color: var(--muted); padding: 4px 0;">Payment Method:</td>
              <td style="font-weight: 600; padding: 4px 0;"><?= h($ord['payment_method']) ?></td>
            </tr>
            <tr>
              <td style="color: var(--muted); padding: 4px 0;">Collection Point:</td>
              <td style="font-weight: 600; padding: 4px 0; font-size: 0.85rem;"><?= h($ord['collection_point']) ?></td>
            </tr>
          </table>
        </div>
      <?php endif; ?>

      <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
        <a class="btn btn-dark" href="<?= h(url_for('home')) ?>" style="padding: 12px 24px;">Continue Shopping</a>
        <a class="btn btn-orange" href="<?= h(url_for('orders')) ?>" style="padding: 12px 24px;">View My Orders</a>
      </div>
    </section>
  </main>

<?php elseif ($page === 'orders'): ?>
  <?php if (!$currentUser): ?>
    <main class="form-page"><section class="panel"><h1>Order History</h1><p class="error">Please sign in to view your order history.</p><a class="btn btn-dark" href="<?= h(url_for('signin')) ?>">Sign in</a></section></main>
  <?php else:
    $userOrders = function_exists('db_user_orders') ? db_user_orders($currentUser['id']) : [];
  ?>
    <main class="container" style="max-width: 900px; margin: 40px auto; min-height: 60vh;">
      <section class="panel" style="padding: 30px; border-radius: 16px; border: 1px solid var(--border);">
        <h1 style="margin-top: 0; font-size: 2.2rem; font-weight: 700; color: var(--text);">Order History</h1>
        <p class="muted" style="margin-bottom: 24px; font-size: 1rem;">Track all your cross-border purchases and sales in one place.</p>
        
        <?php if (empty($userOrders)): ?>
          <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 3rem; margin-bottom: 16px; color: var(--muted);">📦</div>
            <p class="muted" style="font-size: 1.1rem; margin-bottom: 24px;">You have not placed or received any orders yet.</p>
            <a class="btn btn-orange" href="<?= h(url_for('home')) ?>" style="padding: 12px 28px;">Browse Marketplace</a>
          </div>
        <?php else: ?>
          <div class="orders-list" style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach ($userOrders as $ord): 
              $isSeller = (string)$ord['seller_id'] === (string)$currentUser['id'];
              $statusClass = strtolower($ord['status']);
              
              $statusColors = [
                'placed' => ['bg' => '#fef3c7', 'text' => '#d97706'],
                'payment_pending_confirmation' => ['bg' => '#ffedd5', 'text' => '#ea580c'],
                'paid' => ['bg' => '#dcfce7', 'text' => '#16a34a'],
                'cancelled' => ['bg' => '#fee2e2', 'text' => '#dc2626']
              ];
              $style = $statusColors[$statusClass] ?? ['bg' => '#f3f4f6', 'text' => '#4b5563'];
            ?>
              <div class="order-card" style="border: 1px solid var(--border); padding: 24px; border-radius: 12px; display: flex; gap: 24px; align-items: center; justify-content: space-between; flex-wrap: wrap; background-color: rgba(255,255,255,0.02); transition: all 0.2s ease;">
                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                  <img src="<?= h(img_src($ord['image_key'] ?: 'smartphone-black')) ?>" alt="<?= h($ord['title']) ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">
                  <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: bold; letter-spacing: 0.05em; color: <?= $isSeller ? '#3b82f6' : '#10b981' ?>; background-color: <?= $isSeller ? 'rgba(59, 130, 246, 0.1)' : 'rgba(16, 185, 129, 0.1)' ?>; padding: 3px 8px; border-radius: 4px; display: inline-block; margin-bottom: 6px;">
                      <?= $isSeller ? 'Selling' : 'Buying' ?>
                    </span>
                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: var(--text);"><?= h($ord['title']) ?></h3>
                    <p style="margin: 6px 0; font-size: 0.95rem;" class="muted">
                      Order #<?= h($ord['id']) ?> · 
                      <strong style="color: var(--text);"><?= h(price($ord['total_amount'], $ord['currency'])) ?></strong> ·
                      <?= $isSeller ? 'Buyer: ' . h($ord['buyer_name']) : 'Seller: ' . h($ord['seller_name']) ?>
                    </p>
                    <small class="muted" style="font-size: 0.8rem;">Placed on: <?= h(date('M j, Y \a\t H:i', strtotime($ord['created_at']))) ?></small>
                  </div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                  <span class="status-pill" style="padding: 6px 14px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; background-color: <?= $style['bg'] ?>; color: <?= $style['text'] ?>;">
                    <?= str_replace('_', ' ', $ord['status']) ?>
                  </span>
                  <?php if (!$isSeller && $ord['status'] === 'placed'): ?>
                    <a class="btn btn-orange" href="<?= h(url_for('checkout', ['id' => $ord['product_id'], 'payfast_order' => $ord['id']])) ?>" style="font-size: 0.8rem; padding: 6px 12px; margin-top: 4px; display: inline-block;">Complete Payment</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  <?php endif; ?>

<?php elseif ($page === 'admin'): ?>
  <?php
    $tab = $_GET['tab'] ?? 'overview';
    $usersList = all_users();
    $listingsList = all_products();
    $ordersList = all_orders();

    $totalUsers = count($usersList);
    $totalListings = count($listingsList);
    $totalOrders = count($ordersList);

    $totalVolumeZAR = 0;
    $totalVolumeMZN = 0;
    foreach ($ordersList as $ord) {
      if (($ord['status'] ?? '') === 'paid') {
        if (($ord['currency'] ?? '') === 'ZAR') {
          $totalVolumeZAR += (float)$ord['total_amount'];
        } else {
          $totalVolumeMZN += (float)$ord['total_amount'];
        }
      }
    }
  ?>
  <style>
    .admin-container { max-width:1280px; margin:0 auto; padding:48px 24px 80px; min-height:80vh; }
    .admin-header { margin-bottom:36px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; }
    .admin-header h1 { font-size:2.2rem; font-weight:900; margin:0; color:var(--ink); letter-spacing:-.03em; }
    .admin-header h1::before { content:""; display:inline-block; width:12px; height:12px; border-radius:999px; background:var(--orange); margin-right:14px; vertical-align:.15em; }
    .admin-tabs { display:flex; gap:4px; border-bottom:1px solid var(--line); margin-bottom:36px; overflow-x:auto; }
    .admin-tab { padding:14px 22px; color:var(--muted); text-decoration:none; font-weight:800; font-size:13px; text-transform:uppercase; letter-spacing:.14em; transition:all .2s; white-space:nowrap; border-bottom:2px solid transparent; }
    .admin-tab:hover { color:var(--ink); }
    .admin-tab.active { color:var(--orange); border-bottom-color:var(--orange); }
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; margin-bottom:40px; }
    .kpi-card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:24px; box-shadow:0 8px 30px rgba(15,23,42,.06); transition:transform .2s,box-shadow .2s; position:relative; overflow:hidden; }
    .kpi-card:hover { transform:translateY(-3px); box-shadow:0 12px 40px rgba(15,23,42,.1); }
    .kpi-card::before { content:''; position:absolute; top:0; left:0; width:100%; height:4px; background:linear-gradient(90deg,var(--card-accent,var(--orange)),transparent); }
    .kpi-label { font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.16em; color:var(--muted); margin-bottom:10px; }
    .kpi-value { font-size:2.2rem; font-weight:900; color:var(--ink); margin-bottom:4px; letter-spacing:-.03em; }
    .kpi-sub { font-size:.8rem; color:var(--muted); }
    .admin-panel { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:28px; box-shadow:0 8px 30px rgba(15,23,42,.06); margin-bottom:32px; }
    .panel-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:16px; }
    .panel-title { font-size:1.25rem; font-weight:800; color:var(--ink); margin:0; }
    .search-input { background:#f9fafb; border:1px solid var(--line); color:var(--ink); padding:10px 16px; border-radius:10px; font-size:.9rem; width:100%; max-width:320px; transition:all .2s; }
    .search-input::placeholder { color:#9ca3af; }
    .search-input:focus { border-color:var(--orange); outline:none; box-shadow:0 0 0 3px rgba(249,115,22,.1); }
    .table-responsive { overflow-x:auto; }
    .admin-table { width:100%; border-collapse:collapse; text-align:left; font-size:.9rem; }
    .admin-table th { padding:12px 16px; border-bottom:2px solid var(--line); color:var(--muted); font-weight:800; font-size:11px; text-transform:uppercase; letter-spacing:.1em; }
    .admin-table td { padding:14px 16px; border-bottom:1px solid #f3f4f6; color:var(--ink); vertical-align:middle; }
    .admin-table tbody tr { transition:background .15s; }
    .admin-table tbody tr:hover { background:#fafafa; }
    .admin-badge { padding:5px 10px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; display:inline-block; }
    .badge-admin { background:#f3e8ff; color:#7c3aed; }
    .badge-user { background:#dbeafe; color:#1e40af; }
    .badge-verified { background:#dcfce7; color:#16a34a; }
    .badge-unverified { background:#f3f4f6; color:#9ca3af; }
    .badge-placed { background:#fef3c7; color:#d97706; }
    .badge-payment_pending_confirmation { background:#ffedd5; color:#ea580c; }
    .badge-paid { background:#dcfce7; color:#16a34a; }
    .badge-cancelled { background:#fee2e2; color:#dc2626; }
    .actions-cell { display:flex; gap:8px; flex-wrap:nowrap; }
    .btn-admin { padding:7px 14px; border-radius:999px; font-size:12px; font-weight:700; cursor:pointer; border:1px solid transparent; transition:all .15s; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; }
    .btn-admin-edit { background:#f9fafb; border-color:var(--line); color:var(--ink); }
    .btn-admin-edit:hover { background:var(--ink); color:white; border-color:var(--ink); }
    .btn-admin-delete { background:#fff5f5; border-color:#fecaca; color:#dc2626; }
    .btn-admin-delete:hover { background:#dc2626; color:white; border-color:#dc2626; }
    .edit-modal-backdrop { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.45); backdrop-filter:blur(6px); z-index:1000; align-items:center; justify-content:center; padding:20px; }
    .edit-modal { background:white; border:1px solid var(--line); width:100%; max-width:480px; border-radius:18px; padding:32px; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); position:relative; }
    .modal-close { position:absolute; top:20px; right:20px; background:#f3f4f6; border:none; color:var(--muted); font-size:1.2rem; cursor:pointer; width:32px; height:32px; border-radius:999px; display:grid; place-items:center; transition:.15s; }
    .modal-close:hover { background:var(--ink); color:white; }
    .form-group { margin-bottom:20px; }
    .form-group label { display:block; font-size:12px; font-weight:800; color:var(--muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:.08em; }
    .form-control { width:100%; max-width:100%; min-width:0; background:#f9fafb; border:1px solid var(--line); color:var(--ink); padding:11px 14px; border-radius:10px; font-size:.95rem; transition:.2s; }
    .form-control:focus { border-color:var(--orange); outline:none; box-shadow:0 0 0 3px rgba(249,115,22,.1); }
    .form-check { display:flex; align-items:center; gap:10px; margin-top:8px; }
    .form-check input { width:18px; height:18px; accent-color:var(--orange); }
    .form-check label { margin-bottom:0!important; text-transform:none!important; letter-spacing:0!important; font-size:.95rem!important; color:var(--ink)!important; }
    .admin-modal-title { font-size:1.4rem; font-weight:800; margin-top:0; margin-bottom:24px; color:var(--ink); border-bottom:1px solid var(--line); padding-bottom:16px; }
    .empty-state { text-align:center; padding:48px; color:var(--muted); }
    @media(max-width:768px) { .admin-container{padding:24px 16px 60px} .admin-header h1{font-size:1.6rem} .kpi-grid{grid-template-columns:repeat(2,1fr);gap:12px} .kpi-value{font-size:1.6rem} .admin-panel{padding:20px} .admin-table th,.admin-table td{padding:10px 8px;font-size:.8rem} }
  </style>



  <div class="admin-container">
    <div class="admin-header">
      <div>
        <p class="muted" style="margin: 0; font-size: 0.9rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Malawa Express Control Room</p>
        <h1>Admin Control Center</h1>
      </div>
      <div class="muted" style="font-size: 0.95rem;">
        Logged in as: <strong style="color: var(--text);"><?= h($currentUser['name']) ?></strong> (Superuser)
      </div>
    </div>

    <div class="admin-tabs">
      <a href="index.php?page=admin&tab=overview" class="admin-tab <?= $tab === 'overview' ? 'active' : '' ?>">Overview</a>
      <a href="index.php?page=admin&tab=users" class="admin-tab <?= $tab === 'users' ? 'active' : '' ?>">User Accounts (<?= $totalUsers ?>)</a>
      <a href="index.php?page=admin&tab=listings" class="admin-tab <?= $tab === 'listings' ? 'active' : '' ?>">Listings (<?= $totalListings ?>)</a>
      <a href="index.php?page=admin&tab=orders" class="admin-tab <?= $tab === 'orders' ? 'active' : '' ?>">Orders (<?= $totalOrders ?>)</a>
      <a href="index.php?page=admin&tab=cart_system" class="admin-tab <?= $tab === 'cart_system' ? 'active' : '' ?>">Cart System</a>
    </div>

    <?php if ($tab === 'overview'): ?>
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-label">Total Users</div>
          <div class="kpi-value"><?= $totalUsers ?></div>
          <div class="kpi-sub">Registered platform users</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Total Listings</div>
          <div class="kpi-value"><?= $totalListings ?></div>
          <div class="kpi-sub">Active &amp; expired offers</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Orders Placed</div>
          <div class="kpi-value"><?= $totalOrders ?></div>
          <div class="kpi-sub">Total sales volume transactions</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Revenue Volume</div>
          <div class="kpi-value" style="font-size: 1.5rem; line-height: 1.4; margin-top: 10px;">
            R <?= number_format($totalVolumeZAR) ?><br>
            <?= number_format($totalVolumeMZN) ?> MT
          </div>
          <div class="kpi-sub">Aggregated paid transactions</div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px; margin-top: 32px;">
        <div class="admin-panel" style="margin-bottom: 0;">
          <h3 class="panel-title" style="margin-bottom: 20px;">Environment Info</h3>
          <table class="admin-table">
            <tbody>
              <tr>
                <td style="color: #a1a1aa; font-weight: 600; padding: 12px 0;">Database Status</td>
                <td style="text-align: right; padding: 12px 0;">
                  <?php if (db_available()): ?>
                    <span class="admin-badge badge-paid">Connected (MySQL)</span>
                  <?php else: ?>
                    <span class="admin-badge badge-cancelled">Offline (Mock Mode active)</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <td style="color: #a1a1aa; font-weight: 600; padding: 12px 0;">Active Admin Email</td>
                <td style="text-align: right; padding: 12px 0;"><?= h($currentUser['email']) ?></td>
              </tr>
              <tr>
                <td style="color: #a1a1aa; font-weight: 600; padding: 12px 0;">System Date</td>
                <td style="text-align: right; padding: 12px 0;"><?= date('Y-m-d H:i:s') ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="admin-panel" style="margin-bottom: 0;">
          <h3 class="panel-title" style="margin-bottom: 20px;">Quick Moderate Guidelines</h3>
          <p class="muted" style="font-size: 0.95rem; line-height: 1.6; margin: 0;">
            As an administrator, you have full write access to the main database and session variables:
          </p>
          <ul class="muted" style="font-size: 0.9rem; line-height: 1.8; margin-top: 12px; padding-left: 20px;">
            <li>Promote regular accounts to <strong>Superuser (admin)</strong> status.</li>
            <li>Flag items or mark users as <strong>Verified</strong> to boost platform trust.</li>
            <li>Change order status to complete delivery tracking.</li>
            <li>Directly delete listings or orders violating regional regulations.</li>
          </ul>
        </div>
      </div>

    <?php elseif ($tab === 'users'): ?>
      <div class="admin-panel">
        <div class="panel-header">
          <h3 class="panel-title">User Account Registry</h3>
          <input type="text" id="usersSearch" class="search-input" placeholder="Search by name, email, location..." onkeyup="filterTableAll('usersSearch', 'usersTableBody')">
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Avatar</th>
                <th>Name</th>
                <th>Email</th>
                <th>Location</th>
                <th>Role</th>
                <th>Verified</th>
                <th>Rating</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">
              <?php foreach ($usersList as $u): ?>
                <tr>
                  <td>
                    <img src="<?= h(img_src($u['avatar'])) ?>" alt="avatar" style="width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--border); object-fit: cover;">
                  </td>
                  <td><strong><?= h($u['name']) ?></strong></td>
                  <td><?= h($u['email'] ?: 'No email configured') ?></td>
                  <td><?= h($u['location']) ?>, <?= h($u['country']) ?></td>
                  <td>
                    <?php if (is_admin($u)): ?>
                      <span class="admin-badge badge-admin">Admin</span>
                    <?php else: ?>
                      <span class="admin-badge badge-user">User</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($u['verified']): ?>
                      <span class="admin-badge badge-verified">Yes</span>
                    <?php else: ?>
                      <span class="admin-badge badge-unverified">No</span>
                    <?php endif; ?>
                  </td>
                  <td><?= number_format($u['rating'], 1) ?> <span class="muted">(<?= $u['totalReviews'] ?>)</span></td>
                  <td style="text-align: right;">
                    <div class="actions-cell" style="justify-content: flex-end;">
                      <button class="btn-admin btn-admin-edit" onclick="openUserModal('<?= h($u['id']) ?>', '<?= h(addslashes($u['name'])) ?>', <?= $u['verified'] ? 1 : 0 ?>, <?= $u['rating'] ?>, <?= is_admin($u) ? 1 : 0 ?>)">
                        Edit
                      </button>
                      <?php if ($u['id'] !== $currentUser['id']): ?>
                        <form method="post" action="index.php?page=admin&tab=users" onsubmit="return confirm('Are you sure you want to delete user <?= h(addslashes($u['name'])) ?>? All their listings will be affected.');" style="display:inline;">
                          <input type="hidden" name="action" value="admin_delete_user">
                          <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                          <button type="submit" class="btn-admin btn-admin-delete">Delete</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($tab === 'listings'): ?>
      <div class="admin-panel">
        <div class="panel-header">
          <h3 class="panel-title">Active Market Listings</h3>
          <input type="text" id="listingsSearch" class="search-input" placeholder="Search listings..." onkeyup="filterTableAll('listingsSearch', 'listingsTableBody')">
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Category</th>
                <th>Seller</th>
                <th>Price</th>
                <th>Featured</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody id="listingsTableBody">
              <?php foreach ($listingsList as $p): ?>
                <tr>
                  <td>
                    <img src="<?= h(img_src($p['image'])) ?>" alt="product" style="width: 48px; height: 48px; border-radius: 6px; border: 1px solid var(--border); object-fit: cover;">
                  </td>
                  <td><strong><?= h(product_title($p)) ?></strong></td>
                  <td><span class="admin-badge badge-user"><?= h($p['category']) ?></span></td>
                  <td><?= h($p['sellerName']) ?> <span class="muted">(<?= h($p['location']) ?>)</span></td>
                  <td><strong><?= h(price($p['price'], $p['currency'])) ?></strong></td>
                  <td>
                    <?php if ($p['featured']): ?>
                      <span class="admin-badge badge-verified">Featured</span>
                    <?php else: ?>
                      <span class="admin-badge badge-unverified">Standard</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right;">
                    <div class="actions-cell" style="justify-content: flex-end;">
                      <button class="btn-admin btn-admin-edit" onclick="openProductModal('<?= h($p['id']) ?>', '<?= h(addslashes(product_title($p))) ?>', <?= $p['price'] ?>, '<?= h($p['category']) ?>', <?= $p['featured'] ? 1 : 0 ?>)">
                        Edit
                      </button>
                      <form method="post" action="index.php?page=admin&tab=listings" onsubmit="return confirm('Are you sure you want to delete listing <?= h(addslashes(product_title($p))) ?>?');" style="display:inline;">
                        <input type="hidden" name="action" value="admin_delete_product">
                        <input type="hidden" name="product_id" value="<?= h($p['id']) ?>">
                        <button type="submit" class="btn-admin btn-admin-delete">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($tab === 'orders'): ?>
      <div class="admin-panel">
        <div class="panel-header">
          <h3 class="panel-title">Client Transaction History</h3>
          <input type="text" id="ordersSearch" class="search-input" placeholder="Search orders..." onkeyup="filterTableAll('ordersSearch', 'ordersTableBody')">
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Product</th>
                <th>Seller</th>
                <th>Buyer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Created At</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody">
              <?php if (empty($ordersList)): ?>
                <tr>
                  <td colspan="8" class="empty-state">No orders placed on the system yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($ordersList as $o): ?>
                  <tr>
                    <td><strong>#<?= h($o['id']) ?></strong></td>
                    <td><?= h($o['title'] ?? 'Product #' . $o['product_id']) ?></td>
                    <td><?= h($o['seller_name'] ?? 'Seller #' . $o['seller_id']) ?></td>
                    <td><?= h($o['buyer_name']) ?> <span class="muted">(<?= h($o['buyer_email'] ?? '') ?>)</span></td>
                    <td><strong><?= h(price($o['total_amount'], $o['currency'])) ?></strong></td>
                    <td>
                      <span class="admin-badge badge-<?= strtolower($o['status']) ?>">
                        <?= str_replace('_', ' ', $o['status']) ?>
                      </span>
                    </td>
                    <td><small class="muted"><?= h(date('Y-m-d H:i', strtotime($o['created_at']))) ?></small></td>
                    <td style="text-align: right;">
                      <div class="actions-cell" style="justify-content: flex-end;">
                        <button class="btn-admin btn-admin-edit" onclick="openOrderModal('<?= h($o['id']) ?>', '<?= h($o['status']) ?>')">
                          Status
                        </button>
                        <form method="post" action="index.php?page=admin&tab=orders" onsubmit="return confirm('Are you sure you want to delete order #<?= h($o['id']) ?>?');" style="display:inline;">
                          <input type="hidden" name="action" value="admin_delete_order">
                          <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
                          <button type="submit" class="btn-admin btn-admin-delete">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php elseif ($tab === 'cart_system'): ?>
      <div class="admin-panel">
        <h3 class="panel-title" style="margin-bottom: 24px;">Platform Cart System &amp; Rules</h3>
        
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 32px;">
          <h4 style="margin-top: 0; color: #0f172a; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
            <?= render_icon('shield') ?> Administrative Restriction Configuration
          </h4>
          <p style="color: #475569; font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px;">
            To maintain structural integrity and neutral oversight, the system disables active marketplace participation for administrators.
          </p>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-align: center;">
              <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: #ef4444; letter-spacing: 0.05em;">Purchasing Capability</span>
              <div style="font-size: 1.25rem; font-weight: bold; margin-top: 6px; color: #ef4444;">Blocked</div>
            </div>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-align: center;">
              <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: #ef4444; letter-spacing: 0.05em;">Selling Capability</span>
              <div style="font-size: 1.25rem; font-weight: bold; margin-top: 6px; color: #ef4444;">Blocked</div>
            </div>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-align: center;">
              <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: #22c55e; letter-spacing: 0.05em;">Listing Moderation</span>
              <div style="font-size: 1.25rem; font-weight: bold; margin-top: 6px; color: #22c55e;">Enabled</div>
            </div>
          </div>
        </div>

        <h4 style="font-size: 1.1rem; color: var(--ink); margin-top: 32px; margin-bottom: 12px;">Active Cart Implementation Details</h4>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 250px;">Function / Component</th>
                <th>Description</th>
                <th>Data Store</th>
                <th>Target Permission</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Session Storage</strong></td>
                <td>Client session-based cart memory using <code>$_SESSION['cart']</code> mapping product IDs to integer quantities.</td>
                <td>PHP Session</td>
                <td>Standard Users Only</td>
              </tr>
              <tr>
                <td><strong>cart_add($productId, $qty)</strong></td>
                <td>Adds a validated listing to the active buyer session cart, keeping values positive.</td>
                <td>PHP Session</td>
                <td>Standard Users Only</td>
              </tr>
              <tr>
                <td><strong>cart_update($productId, $qty)</strong></td>
                <td>Updates quantity or completely unsets a product if quantity is zero.</td>
                <td>PHP Session</td>
                <td>Standard Users Only</td>
              </tr>
              <tr>
                <td><strong>cart_remove($productId)</strong></td>
                <td>Unsets product key from the cart array, deleting the item.</td>
                <td>PHP Session</td>
                <td>Standard Users Only</td>
              </tr>
              <tr>
                <td><strong>cart_total() / cart_totals_by_currency()</strong></td>
                <td>Calculates aggregated prices separated by Mozambican Metical (MZN) and South African Rand (ZAR).</td>
                <td>PHP Session</td>
                <td>Standard Users Only</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Modal Overlays -->
  <div id="editUserModal" class="edit-modal-backdrop">
    <div class="edit-modal">
      <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
      <h3 class="admin-modal-title">Edit User Account</h3>
      <form method="post" action="index.php?page=admin&tab=users">
        <input type="hidden" name="action" value="admin_update_user">
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="form-group">
          <label for="edit_user_name">Name</label>
          <input type="text" name="name" id="edit_user_name" class="form-control" required>
        </div>
        <div class="form-group">
          <div class="form-check">
            <input type="checkbox" name="verified" id="edit_user_verified" value="1">
            <label for="edit_user_verified">Verified Status</label>
          </div>
        </div>
        <div class="form-group">
          <label for="edit_user_rating">Rating (0.0 - 5.0)</label>
          <input type="number" name="rating" id="edit_user_rating" class="form-control" step="0.1" min="0" max="5" required>
        </div>
        <div class="form-group">
          <div class="form-check">
            <input type="checkbox" name="is_admin" id="edit_user_is_admin" value="1">
            <label for="edit_user_is_admin">Superuser (Admin)</label>
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
          <button type="button" class="btn-admin btn-admin-edit" onclick="closeModal('editUserModal')">Cancel</button>
          <button type="submit" class="btn btn-orange" style="margin: 0; padding: 10px 20px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <div id="editListingModal" class="edit-modal-backdrop">
    <div class="edit-modal">
      <button class="modal-close" onclick="closeModal('editListingModal')">&times;</button>
      <h3 class="admin-modal-title">Edit Listing</h3>
      <form method="post" action="index.php?page=admin&tab=listings">
        <input type="hidden" name="action" value="admin_update_product">
        <input type="hidden" name="product_id" id="edit_product_id">
        <div class="form-group">
          <label for="edit_product_title">Title</label>
          <input type="text" name="title" id="edit_product_title" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="edit_product_price">Price</label>
          <input type="number" name="price" id="edit_product_price" class="form-control" step="0.01" min="0" required>
        </div>
        <div class="form-group">
          <label for="edit_product_category">Category</label>
          <select name="category" id="edit_product_category" class="form-control" required>
            <option value="electronics">electronics</option>
            <option value="clothing">clothing</option>
            <option value="handicrafts">handicrafts</option>
            <option value="household">household</option>
            <option value="other">other</option>
          </select>
        </div>
        <div class="form-group">
          <div class="form-check">
            <input type="checkbox" name="featured" id="edit_product_featured" value="1">
            <label for="edit_product_featured">Featured Listing</label>
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
          <button type="button" class="btn-admin btn-admin-edit" onclick="closeModal('editListingModal')">Cancel</button>
          <button type="submit" class="btn btn-orange" style="margin: 0; padding: 10px 20px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <div id="editOrderModal" class="edit-modal-backdrop">
    <div class="edit-modal">
      <button class="modal-close" onclick="closeModal('editOrderModal')">&times;</button>
      <h3 class="admin-modal-title">Update Order Status</h3>
      <form method="post" action="index.php?page=admin&tab=orders">
        <input type="hidden" name="action" value="admin_update_order_status">
        <input type="hidden" name="order_id" id="edit_order_id">
        <div class="form-group">
          <label for="edit_order_status">Order Status</label>
          <select name="status" id="edit_order_status" class="form-control" required>
            <option value="placed">placed</option>
            <option value="payment_pending_confirmation">payment_pending_confirmation</option>
            <option value="paid">paid</option>
            <option value="cancelled">cancelled</option>
          </select>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
          <button type="button" class="btn-admin btn-admin-edit" onclick="closeModal('editOrderModal')">Cancel</button>
          <button type="submit" class="btn btn-orange" style="margin: 0; padding: 10px 20px;">Update Status</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openUserModal(id, name, verified, rating, isAdmin) {
      document.getElementById('edit_user_id').value = id;
      document.getElementById('edit_user_name').value = name;
      document.getElementById('edit_user_verified').checked = !!verified;
      document.getElementById('edit_user_rating').value = rating;
      document.getElementById('edit_user_is_admin').checked = !!isAdmin;
      document.getElementById('editUserModal').style.display = 'flex';
    }

    function openProductModal(id, title, price, category, featured) {
      document.getElementById('edit_product_id').value = id;
      document.getElementById('edit_product_title').value = title;
      document.getElementById('edit_product_price').value = price;
      document.getElementById('edit_product_category').value = category;
      document.getElementById('edit_product_featured').checked = !!featured;
      document.getElementById('editListingModal').style.display = 'flex';
    }

    function openOrderModal(id, status) {
      document.getElementById('edit_order_id').value = id;
      document.getElementById('edit_order_status').value = status;
      document.getElementById('editOrderModal').style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    function filterTableAll(inputId, tableBodyId) {
      var input = document.getElementById(inputId);
      var filter = input.value.toLowerCase();
      var tbody = document.getElementById(tableBodyId);
      var rows = tbody.getElementsByTagName('tr');

      for (var i = 0; i < rows.length; i++) {
        var text = rows[i].textContent || rows[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
          rows[i].style.display = '';
        } else {
          rows[i].style.display = 'none';
        }
      }
    }
  </script>

<?php elseif ($page === 'payment_cancel'): ?>
  <main class="form-page"><section class="panel"><h1>Payment Cancelled</h1><p class="error">The PayFast payment was cancelled. Your order was not marked as paid.</p><a class="btn btn-dark" href="<?= h(url_for('home')) ?>">Back to Home</a></section></main>
<?php endif; ?>

<?php render_footer(); ?>

<script src="script.js"></script>
</body>
</html>
