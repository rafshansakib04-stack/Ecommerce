<?php
/**
 * Products Catalog Page
 * Advanced product listing with filtering, sorting, and search
 */

require_once 'includes/functions.php';

// Get filter parameters
$categoryId = (int)($_GET['category'] ?? 0);
$brandId = (int)($_GET['brand'] ?? 0);
$search = sanitizeInput($_GET['search'] ?? '');
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;

// Build filters array
$filters = [];
if ($categoryId > 0) $filters['category'] = $categoryId;
if ($brandId > 0) $filters['brand'] = $brandId;
if (!empty($search)) $filters['search'] = $search;
if ($minPrice > 0) $filters['min_price'] = $minPrice;
if ($maxPrice > 0) $filters['max_price'] = $maxPrice;
if (!empty($sort)) $filters['sort'] = $sort;

// Get products
$products = getProducts($filters, $page, $limit);

// Get filter options
$categories = getRecords('categories', ['is_active' => true], '*', 'name ASC');
$brands = getRecords('brands', ['is_active' => true], '*', 'name ASC');

// Get price range
$db = getDB();
$stmt = $db->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE is_active = TRUE");
$stmt->execute();
$priceRange = $stmt->fetch();

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT p.id) FROM products p WHERE p.is_active = TRUE";
$countParams = [];

if ($categoryId > 0) {
    $countSql .= " AND p.category_id = ?";
    $countParams[] = $categoryId;
}

if ($brandId > 0) {
    $countSql .= " AND p.brand_id = ?";
    $countParams[] = $brandId;
}

if (!empty($search)) {
    $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

if ($minPrice > 0) {
    $countSql .= " AND p.price >= ?";
    $countParams[] = $minPrice;
}

if ($maxPrice > 0) {
    $countSql .= " AND p.price <= ?";
    $countParams[] = $maxPrice;
}

$stmt = $db->prepare($countSql);
$stmt->execute($countParams);
$totalProducts = $stmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// Get selected category/brand info
$selectedCategory = null;
$selectedBrand = null;

if ($categoryId > 0) {
    $selectedCategory = getRecord('categories', ['id' => $categoryId]);
}

if ($brandId > 0) {
    $selectedBrand = getRecord('brands', ['id' => $brandId]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php if ($selectedCategory): ?>
            <?= $selectedCategory['name'] ?> - 
        <?php elseif (!empty($search)): ?>
            Search: <?= htmlspecialchars($search) ?> - 
        <?php endif; ?>
        Water Purifiers - PureFit Bangladesh
    </title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php if ($selectedCategory): ?>Shop <?= $selectedCategory['name'] ?> from PureFit Bangladesh. <?= $selectedCategory['description'] ?><?php else: ?>Browse our complete range of water purifiers, RO systems, and water treatment solutions in Bangladesh.<?php endif; ?>">
    <meta name="keywords" content="water purifier, RO system, <?= $selectedCategory['name'] ?? 'water filter' ?>, bangladesh, dhaka, <?= $selectedBrand['name'] ?? 'aquaguard kent pureit' ?>">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/nouislider@15.7.0/dist/nouislider.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Breadcrumb -->
    <section class="breadcrumb-section py-3 bg-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item">Products</li>
                    <?php if ($selectedCategory): ?>
                    <li class="breadcrumb-item active"><?= $selectedCategory['name'] ?></li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Page Header -->
    <section class="page-header py-4 bg-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="page-title mb-2">
                        <?php if ($selectedCategory): ?>
                            <?= $selectedCategory['name'] ?>
                        <?php elseif (!empty($search)): ?>
                            Search Results for "<?= htmlspecialchars($search) ?>"
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle text-muted">
                        Showing <?= count($products) ?> of <?= $totalProducts ?> products
                        <?php if ($selectedCategory): ?>
                            in <?= $selectedCategory['name'] ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <div class="page-actions">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary" id="filter-toggle">
                                <i class="fas fa-filter me-2"></i>Filters
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="grid-view" data-view="grid">
                                <i class="fas fa-th"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="list-view" data-view="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="products-section py-4">
        <div class="container">
            <div class="row">
                <!-- Filters Sidebar -->
                <div class="col-lg-3">
                    <div class="filters-sidebar" id="filters-sidebar">
                        <div class="filters-header">
                            <h5>Filter Products</h5>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllFilters()">
                                Clear All
                            </button>
                        </div>
                        
                        <form id="product-filters">
                            <!-- Category Filter -->
                            <div class="filter-group">
                                <h6 class="filter-title">Category</h6>
                                <div class="filter-options">
                                    <?php foreach ($categories as $category): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="category" 
                                               value="<?= $category['id'] ?>" id="cat_<?= $category['id'] ?>"
                                               <?= $categoryId == $category['id'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="cat_<?= $category['id'] ?>">
                                            <?= $category['name'] ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Brand Filter -->
                            <div class="filter-group">
                                <h6 class="filter-title">Brand</h6>
                                <div class="filter-options">
                                    <?php foreach ($brands as $brand): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="brands[]" 
                                               value="<?= $brand['id'] ?>" id="brand_<?= $brand['id'] ?>"
                                               <?= $brandId == $brand['id'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="brand_<?= $brand['id'] ?>">
                                            <?= $brand['name'] ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Price Range Filter -->
                            <div class="filter-group">
                                <h6 class="filter-title">Price Range</h6>
                                <div class="price-range-container">
                                    <div id="price-range-slider"></div>
                                    <div class="price-range-inputs mt-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <input type="number" class="form-control form-control-sm" 
                                                       id="min-price" name="min_price" placeholder="Min" 
                                                       value="<?= $minPrice ?: '' ?>">
                                            </div>
                                            <div class="col-6">
                                                <input type="number" class="form-control form-control-sm" 
                                                       id="max-price" name="max_price" placeholder="Max" 
                                                       value="<?= $maxPrice ?: '' ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Features Filter -->
                            <div class="filter-group">
                                <h6 class="filter-title">Features</h6>
                                <div class="filter-options">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="ro" id="feature_ro">
                                        <label class="form-check-label" for="feature_ro">RO Technology</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="uv" id="feature_uv">
                                        <label class="form-check-label" for="feature_uv">UV Sterilization</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="uf" id="feature_uf">
                                        <label class="form-check-label" for="feature_uf">UF Filtration</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="alkaline" id="feature_alkaline">
                                        <label class="form-check-label" for="feature_alkaline">Alkaline Water</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="tds_controller" id="feature_tds">
                                        <label class="form-check-label" for="feature_tds">TDS Controller</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rating Filter -->
                            <div class="filter-group">
                                <h6 class="filter-title">Customer Rating</h6>
                                <div class="filter-options">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rating" value="4" id="rating_4">
                                        <label class="form-check-label" for="rating_4">
                                            <span class="rating-stars">
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-muted"></i>
                                            </span>
                                            & Above
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rating" value="3" id="rating_3">
                                        <label class="form-check-label" for="rating_3">
                                            <span class="rating-stars">
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-muted"></i>
                                                <i class="fas fa-star text-muted"></i>
                                            </span>
                                            & Above
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Availability Filter -->
                            <div class="filter-group">
                                <h6 class="filter-title">Availability</h6>
                                <div class="filter-options">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in_stock">
                                        <label class="form-check-label" for="in_stock">In Stock Only</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="featured" value="1" id="featured">
                                        <label class="form-check-label" for="featured">Featured Products</label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="col-lg-9">
                    <!-- Sort and View Options -->
                    <div class="products-toolbar mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="results-info">
                                    <span class="results-count">
                                        Showing <?= count($products) ?> of <?= $totalProducts ?> products
                                    </span>
                                    <?php if (!empty($search)): ?>
                                    <span class="search-query">for "<?= htmlspecialchars($search) ?>"</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="sort-options">
                                    <label for="sort-select" class="form-label me-2">Sort by:</label>
                                    <select id="sort-select" class="form-select form-select-sm d-inline-block w-auto">
                                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                                        <option value="popularity" <?= $sort === 'popularity' ? 'selected' : '' ?>>Most Popular</option>
                                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active Filters -->
                    <?php if (!empty($filters)): ?>
                    <div class="active-filters mb-4">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="text-muted">Active filters:</span>
                            
                            <?php if ($selectedCategory): ?>
                            <span class="filter-tag">
                                Category: <?= $selectedCategory['name'] ?>
                                <button type="button" class="btn-close btn-close-sm ms-2" onclick="removeFilter('category')"></button>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($selectedBrand): ?>
                            <span class="filter-tag">
                                Brand: <?= $selectedBrand['name'] ?>
                                <button type="button" class="btn-close btn-close-sm ms-2" onclick="removeFilter('brand')"></button>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($search)): ?>
                            <span class="filter-tag">
                                Search: "<?= htmlspecialchars($search) ?>"
                                <button type="button" class="btn-close btn-close-sm ms-2" onclick="removeFilter('search')"></button>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($minPrice > 0 || $maxPrice > 0): ?>
                            <span class="filter-tag">
                                Price: <?= formatCurrency($minPrice ?: 0) ?> - <?= formatCurrency($maxPrice ?: 999999) ?>
                                <button type="button" class="btn-close btn-close-sm ms-2" onclick="removeFilter('price')"></button>
                            </span>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllFilters()">
                                Clear All
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Products Grid -->
                    <div class="products-grid" id="products-grid">
                        <?php if (empty($products)): ?>
                        <div class="no-products text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h4>No Products Found</h4>
                            <p class="text-muted">Try adjusting your search criteria or browse our categories</p>
                            <a href="/products.php" class="btn btn-primary">View All Products</a>
                        </div>
                        <?php else: ?>
                        <div class="row" id="products-container">
                            <?php foreach ($products as $product): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="product-card animate__animated animate__fadeIn">
                                    <?php if ($product['sale_price']): ?>
                                    <div class="product-badge sale-badge">
                                        <?= round((($product['price'] - $product['sale_price']) / $product['price']) * 100) ?>% OFF
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($product['is_featured']): ?>
                                    <div class="product-badge featured-badge">
                                        <i class="fas fa-star"></i> Featured
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($product['stock_quantity'] <= 0): ?>
                                    <div class="product-badge stock-badge">
                                        Out of Stock
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-image">
                                        <img src="/uploads/products/<?= $product['images'][0]['image_path'] ?? 'placeholder.jpg' ?>" 
                                             alt="<?= $product['name'] ?>" class="img-fluid">
                                        <div class="product-overlay">
                                            <div class="product-actions">
                                                <button class="btn btn-sm btn-white" onclick="addToWishlist(<?= $product['id'] ?>)" 
                                                        title="Add to Wishlist">
                                                    <i class="fas fa-heart"></i>
                                                </button>
                                                <button class="btn btn-sm btn-white" onclick="quickView(<?= $product['id'] ?>)" 
                                                        title="Quick View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary" onclick="addToCart(<?= $product['id'] ?>)" 
                                                        title="Add to Cart" <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
                                                    <i class="fas fa-cart-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="product-content">
                                        <div class="product-category"><?= $product['category_name'] ?></div>
                                        <h6 class="product-title">
                                            <a href="/product-details.php?id=<?= $product['id'] ?>"><?= $product['name'] ?></a>
                                        </h6>
                                        
                                        <div class="product-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $product['average_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                            <span class="rating-count">(<?= $product['review_count'] ?>)</span>
                                        </div>
                                        
                                        <div class="product-price">
                                            <?php if ($product['sale_price']): ?>
                                                <span class="current-price"><?= formatCurrency($product['sale_price']) ?></span>
                                                <span class="original-price"><?= formatCurrency($product['price']) ?></span>
                                            <?php else: ?>
                                                <span class="current-price"><?= formatCurrency($product['price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="product-features">
                                            <?php 
                                            $features = json_decode($product['features'], true);
                                            if ($features && count($features) > 0):
                                            ?>
                                                <small class="text-muted">
                                                    <?= implode(' • ', array_slice($features, 0, 2)) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="product-stock mt-2">
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i>In Stock (<?= $product['stock_quantity'] ?> available)
                                                </small>
                                            <?php else: ?>
                                                <small class="text-danger">
                                                    <i class="fas fa-times-circle me-1"></i>Out of Stock
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Products pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick View Modal -->
    <div class="modal fade" id="quickViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="quickViewContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/nouislider@15.7.0/dist/nouislider.min.js"></script>
    
    <!-- Firebase SDK -->
    <?= FirebaseConfig::renderFirebaseScript() ?>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize price range slider
            const priceSlider = document.getElementById('price-range-slider');
            if (priceSlider) {
                noUiSlider.create(priceSlider, {
                    start: [<?= $minPrice ?: $priceRange['min_price'] ?>, <?= $maxPrice ?: $priceRange['max_price'] ?>],
                    connect: true,
                    range: {
                        'min': <?= $priceRange['min_price'] ?>,
                        'max': <?= $priceRange['max_price'] ?>
                    },
                    format: {
                        to: function(value) {
                            return Math.round(value);
                        },
                        from: function(value) {
                            return Number(value);
                        }
                    }
                });
                
                priceSlider.noUiSlider.on('update', function(values, handle) {
                    document.getElementById('min-price').value = values[0];
                    document.getElementById('max-price').value = values[1];
                });
                
                priceSlider.noUiSlider.on('end', function(values, handle) {
                    applyFilters();
                });
            }
            
            // Filter toggle for mobile
            $('#filter-toggle').click(function() {
                $('#filters-sidebar').toggleClass('show');
            });
            
            // View toggle
            $('#grid-view, #list-view').click(function() {
                const view = $(this).data('view');
                $('.btn-group button').removeClass('active');
                $(this).addClass('active');
                
                const container = $('#products-container');
                if (view === 'list') {
                    container.removeClass('row').addClass('list-view');
                    container.find('.col-lg-4, .col-md-6').removeClass('col-lg-4 col-md-6').addClass('col-12');
                } else {
                    container.removeClass('list-view').addClass('row');
                    container.find('.col-12').removeClass('col-12').addClass('col-lg-4 col-md-6');
                }
                
                localStorage.setItem('products_view', view);
            });
            
            // Restore saved view
            const savedView = localStorage.getItem('products_view');
            if (savedView === 'list') {
                $('#list-view').click();
            }
            
            // Sort change
            $('#sort-select').change(function() {
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('sort', this.value);
                currentUrl.searchParams.delete('page'); // Reset to first page
                window.location.href = currentUrl.toString();
            });
            
            // Filter changes
            $('#product-filters input, #product-filters select').change(function() {
                if ($(this).attr('type') === 'range') return; // Skip range inputs
                applyFilters();
            });
            
            // Price input changes
            $('#min-price, #max-price').on('input', debounce(function() {
                const minPrice = parseInt($('#min-price').val()) || <?= $priceRange['min_price'] ?>;
                const maxPrice = parseInt($('#max-price').val()) || <?= $priceRange['max_price'] ?>;
                
                if (priceSlider.noUiSlider) {
                    priceSlider.noUiSlider.set([minPrice, maxPrice]);
                }
                
                applyFilters();
            }, 500));
        });
        
        function applyFilters() {
            const formData = new FormData(document.getElementById('product-filters'));
            const params = new URLSearchParams();
            
            // Add form data to params
            for (let [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }
            
            // Add price range
            const minPrice = document.getElementById('min-price').value;
            const maxPrice = document.getElementById('max-price').value;
            
            if (minPrice) params.set('min_price', minPrice);
            if (maxPrice) params.set('max_price', maxPrice);
            
            // Keep search query if exists
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('search')) {
                params.set('search', urlParams.get('search'));
            }
            
            // Keep sort option
            const sortSelect = document.getElementById('sort-select');
            if (sortSelect.value !== 'newest') {
                params.set('sort', sortSelect.value);
            }
            
            // Update URL
            const newUrl = window.location.pathname + '?' + params.toString();
            window.history.pushState({}, '', newUrl);
            
            // Load filtered products
            loadFilteredProducts(params);
        }
        
        function loadFilteredProducts(params) {
            // Show loading state
            $('#products-container').html(`
                <div class="col-12 text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-3">Loading products...</div>
                </div>
            `);
            
            // Load products via AJAX
            fetch('/api/products/filter.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProductsDisplay(data.products);
                        updateResultsInfo(data.total, data.products.length);
                        updatePagination(data.pagination);
                    } else {
                        showToast('Error', 'Failed to load products', 'error');
                    }
                })
                .catch(error => {
                    console.error('Filter error:', error);
                    showToast('Error', 'Failed to load products', 'error');
                });
        }
        
        function updateProductsDisplay(products) {
            const container = document.getElementById('products-container');
            
            if (products.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="no-products text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h4>No Products Found</h4>
                            <p class="text-muted">Try adjusting your search criteria</p>
                        </div>
                    </div>
                `;
                return;
            }
            
            let html = '';
            products.forEach(product => {
                html += generateProductCard(product);
            });
            
            container.innerHTML = html;
        }
        
        function updateResultsInfo(total, showing) {
            document.querySelector('.results-count').textContent = `Showing ${showing} of ${total} products`;
        }
        
        function removeFilter(filterType) {
            const currentUrl = new URL(window.location);
            
            switch (filterType) {
                case 'category':
                    currentUrl.searchParams.delete('category');
                    break;
                case 'brand':
                    currentUrl.searchParams.delete('brand');
                    break;
                case 'search':
                    currentUrl.searchParams.delete('search');
                    break;
                case 'price':
                    currentUrl.searchParams.delete('min_price');
                    currentUrl.searchParams.delete('max_price');
                    break;
            }
            
            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        }
        
        function clearAllFilters() {
            window.location.href = '/products.php';
        }
        
        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Track page view
        if (window.FirebaseHelpers) {
            window.FirebaseHelpers.trackEvent('page_view', {
                page_title: 'Products',
                page_location: window.location.href,
                category: '<?= $selectedCategory['name'] ?? '' ?>',
                search_query: '<?= $search ?>'
            });
        }
    </script>
    
    <style>
        /* Products page specific styles */
        .filters-sidebar {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .filter-group {
            margin-bottom: 2rem;
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }
        
        .filter-options {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .filter-options .form-check {
            margin-bottom: 0.5rem;
        }
        
        .products-toolbar {
            background: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }
        
        .active-filters {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: var(--border-radius);
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            background: var(--primary-color);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }
        
        .no-products {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }
        
        .stock-badge {
            background-color: var(--gray-500);
            color: white;
            top: 1rem;
            right: 1rem;
        }
        
        .list-view .product-card {
            display: flex;
            margin-bottom: 1.5rem;
        }
        
        .list-view .product-image {
            width: 200px;
            height: 150px;
            flex-shrink: 0;
        }
        
        .list-view .product-content {
            flex: 1;
            padding: 1rem;
        }
        
        /* Mobile responsive */
        @media (max-width: 992px) {
            .filters-sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 300px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease;
                overflow-y: auto;
            }
            
            .filters-sidebar.show {
                left: 0;
            }
            
            .filters-sidebar::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: -1;
            }
        }
        
        @media (max-width: 768px) {
            .products-toolbar .row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .sort-options {
                text-align: left !important;
            }
            
            .hero-stats {
                justify-content: space-around;
            }
        }
    </style>
</body>
</html>