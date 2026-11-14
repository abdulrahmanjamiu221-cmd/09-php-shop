<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$categorySlug = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'newest';
$sortOptions = [
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_asc' => 'p.price ASC, p.id DESC',
    'price_desc' => 'p.price DESC, p.id DESC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}

$categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();
$sql = 'SELECT p.id, p.name, p.slug, p.price, p.stock, p.image_url, c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1';
$parameters = [];
if ($categorySlug !== '') {
    $sql .= ' AND c.slug = :category';
    $parameters['category'] = $categorySlug;
}
if ($search !== '') {
    $sql .= ' AND (p.name LIKE :search_name OR p.description LIKE :search_description)';
    $searchTerm = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
    $parameters['search_name'] = $searchTerm;
    $parameters['search_description'] = $searchTerm;
}
$sql .= ' ORDER BY ' . $sortOptions[$sort];
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$products = $statement->fetchAll();

$pageTitle = 'Shop everyday essentials';
require __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Good things, thoughtfully chosen</p>
        <h1>Make room for<br><em>the good stuff.</em></h1>
        <p class="hero-text">Useful, beautiful things for the everyday. Find a new favourite, or a gift worth giving.</p>
        <a class="button button-dark" href="#shop">Explore the collection <span aria-hidden="true">→</span></a>
    </div>
    <div class="hero-art" aria-label="A selection of home and everyday goods">
        <img src="https://placehold.co/800x680?text=Good+Things" alt="A thoughtful collection of everyday goods">
        <span class="hero-note">Made for living well</span>
    </div>
</section>

<section class="shop-section" id="shop">
    <div class="section-heading">
        <div>
            <p class="eyebrow">The collection</p>
            <h2>Find your next favourite</h2>
        </div>
        <span class="result-count"><?= count($products) ?> <?= count($products) === 1 ? 'piece' : 'pieces' ?></span>
    </div>
    <form class="filters" method="get" action="<?= e(url()) ?>">
        <label class="search-field">
            <span class="sr-only">Search products</span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search the collection">
            <button type="submit" aria-label="Search">⌕</button>
        </label>
        <label>
            <span class="sr-only">Category</span>
            <select name="category" onchange="this.form.submit()">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span class="sr-only">Sort products</span>
            <select name="sort" onchange="this.form.submit()">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            </select>
        </label>
        <?php if ($search !== '' || $categorySlug !== ''): ?>
            <a class="clear-filters" href="<?= e(url()) ?>">Clear filters</a>
        <?php endif; ?>
    </form>

    <?php if ($products): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <a class="product-image" href="<?= e(url('product.php?slug=' . rawurlencode($product['slug']))) ?>">
                        <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php if ((int) $product['stock'] < 1): ?>
                            <span class="stock-badge">Out of stock</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-meta">
                        <span class="product-category"><?= e($product['category_name']) ?></span>
                        <h3><a href="<?= e(url('product.php?slug=' . rawurlencode($product['slug']))) ?>"><?= e($product['name']) ?></a></h3>
                        <span class="product-price"><?= e(money($product['price'])) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <span class="empty-icon">✳</span>
            <h3>No pieces found</h3>
            <p>Try a different search or browse the full collection.</p>
            <a class="text-link" href="<?= e(url()) ?>">See everything <span aria-hidden="true">→</span></a>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
