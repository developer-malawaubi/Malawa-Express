<?php
require __DIR__ . '/data.php';
require __DIR__ . '/database.php';
require __DIR__ . '/functions.php';

$category = $categoryPage ?? 'all';
if (!in_array($category, product_categories(), true)) $category = 'all';
$categoryProducts = products_by_category($category);
$pageTitle = category_label($category) . ' | Malawa Express';
?><!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg?v=1">
  <meta name="theme-color" content="#050505">
  <link rel="stylesheet" href="style.css?v=<?= h(filemtime(__DIR__ . '/style.css')) ?>">
</head>
<body>
<?php render_header('home'); ?>

<main class="container category-page">
  <section class="category-page-hero">
    <p class="hero-kicker">Shop Malawa Express</p>
    <h1><?= h(category_label($category)) ?></h1>
    <p><?= h(count($categoryProducts)) ?> trusted listing<?= count($categoryProducts) === 1 ? '' : 's' ?> available across South Africa and Mozambique.</p>
  </section>
  <nav class="category-tabs page-tabs" aria-label="Product categories">
    <?php foreach (product_categories() as $cat): ?>
      <a class="<?= $cat === $category ? 'active' : '' ?>" href="<?= h(category_url($cat)) ?>"><?= h(category_label($cat)) ?></a>
    <?php endforeach; ?>
  </nav>
  <section class="listing-section">
    <div class="section-heading"><h2><?= h(category_label($category)) ?></h2><a href="<?= h(url_for('home')) ?>">Back Home</a></div>
    <div class="product-grid">
      <?php foreach ($categoryProducts as $product) render_product_card($product); ?>
    </div>
    <?php if (!$categoryProducts): ?><p class="empty-state">No products found in this category yet.</p><?php endif; ?>
  </section>
</main>

<?php render_footer(); ?>
<script src="script.js"></script>
</body>
</html>
