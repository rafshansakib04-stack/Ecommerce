<?php
/**
 * Product Details Page
 * Comprehensive product view with gallery, reviews, and purchase options
 */

require_once 'includes/functions.php';

$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: /products.php');
    exit();
}

// Get product details
$product = getProductDetails($productId);

if (!$product) {
    header('Location: /404.php');
    exit();
}

// Get related products
$db = getDB();
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, b.name as brand_name,
           COALESCE(AVG(pr.rating), 0) as average_rating,
           COUNT(pr.id) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN product_reviews pr ON p.id = pr.product_id AND pr.is_approved = TRUE
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = TRUE
    GROUP BY p.id
    ORDER BY p.is_featured DESC, p.created_at DESC
    LIMIT 4
");
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll();

// Check if user has purchased this product (for review eligibility)
$canReview = false;
if (isLoggedIn()) {
    $user = getCurrentUser();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
    ");
    $stmt->execute([$user['id'], $productId]);
    $canReview = $stmt->fetchColumn() > 0;
}

// Handle review submission
if ($_POST && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        $error = 'Please login to submit a review';
    } elseif (!$canReview) {
        $error = 'You can only review products you have purchased';
    } elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $title = sanitizeInput($_POST['title'] ?? '');
        $reviewText = sanitizeInput($_POST['review_text'] ?? '');
        $pros = sanitizeInput($_POST['pros'] ?? '');
        $cons = sanitizeInput($_POST['cons'] ?? '');
        
        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a rating between 1 and 5 stars';
        } elseif (empty($title) || empty($reviewText)) {
            $error = 'Please provide both a title and review text';
        } else {
            // Check if user already reviewed this product
            $stmt = $db->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
            $stmt->execute([$productId, $user['id']]);
            $existingReview = $stmt->fetch();
            
            if ($existingReview) {
                $error = 'You have already reviewed this product';
            } else {
                try {
                    $reviewData = [
                        'product_id' => $productId,
                        'user_id' => $user['id'],
                        'rating' => $rating,
                        'title' => $title,
                        'review_text' => $reviewText,
                        'pros' => $pros,
                        'cons' => $cons,
                        'is_verified_purchase' => true,
                        'is_approved' => false // Admin approval required
                    ];
                    
                    insertRecord('product_reviews', $reviewData);
                    
                    $success = 'Review submitted successfully! It will be published after admin approval.';
                    
                    // Log activity
                    logActivity($user['id'], 'review_submitted', 'product', $productId, 'Product review submitted');
                    
                } catch (Exception $e) {
                    error_log("Review submission error: " . $e->getMessage());
                    $error = 'Failed to submit review. Please try again.';
                }
            }
        }
    }
}

// Track product view
if (isLoggedIn()) {
    $user = getCurrentUser();
    logActivity($user['id'], 'product_viewed', 'product', $productId, "Viewed product: {$product['name']}");
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product['name'] ?> - PureFit Bangladesh</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($product['short_description'] ?: substr($product['description'], 0, 160)) ?>">
    <meta name="keywords" content="<?= $product['name'] ?>, <?= $product['category_name'] ?>, <?= $product['brand_name'] ?>, water purifier bangladesh">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= $product['name'] ?> - PureFit Bangladesh">
    <meta property="og:description" content="<?= htmlspecialchars($product['short_description']) ?>">
    <meta property="og:image" content="<?= APP_URL ?>/uploads/products/<?= $product['images'][0]['image_path'] ?? 'placeholder.jpg' ?>">
    <meta property="og:url" content="<?= APP_URL ?>/product-details.php?id=<?= $productId ?>">
    <meta property="og:type" content="product">
    <meta property="product:price:amount" content="<?= $product['sale_price'] ?: $product['price'] ?>">
    <meta property="product:price:currency" content="BDT">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "<?= addslashes($product['name']) ?>",
        "description": "<?= addslashes($product['description']) ?>",
        "image": "<?= APP_URL ?>/uploads/products/<?= $product['images'][0]['image_path'] ?? 'placeholder.jpg' ?>",
        "brand": {
            "@type": "Brand",
            "name": "<?= $product['brand_name'] ?>"
        },
        "category": "<?= $product['category_name'] ?>",
        "sku": "<?= $product['sku'] ?>",
        "offers": {
            "@type": "Offer",
            "price": "<?= $product['sale_price'] ?: $product['price'] ?>",
            "priceCurrency": "BDT",
            "availability": "<?= $product['stock_quantity'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>",
            "seller": {
                "@type": "Organization",
                "name": "PureFit Bangladesh"
            }
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "<?= $product['average_rating'] ?>",
            "reviewCount": "<?= $product['review_count'] ?>"
        }
    }
    </script>
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
                    <li class="breadcrumb-item"><a href="/products.php">Products</a></li>
                    <li class="breadcrumb-item"><a href="/products.php?category=<?= $product['category_id'] ?>"><?= $product['category_name'] ?></a></li>
                    <li class="breadcrumb-item active"><?= $product['name'] ?></li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Product Details -->
    <section class="product-details-section py-5">
        <div class="container">
            <div class="row">
                <!-- Product Images -->
                <div class="col-lg-6 mb-4">
                    <div class="product-gallery">
                        <!-- Main Image -->
                        <div class="main-image-container">
                            <div class="product-badges">
                                <?php if ($product['sale_price']): ?>
                                <span class="badge bg-danger">
                                    <?= round((($product['price'] - $product['sale_price']) / $product['price']) * 100) ?>% OFF
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($product['is_featured']): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-star"></i> Featured
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($product['stock_quantity'] <= 0): ?>
                                <span class="badge bg-secondary">Out of Stock</span>
                                <?php elseif ($product['stock_quantity'] <= 5): ?>
                                <span class="badge bg-warning">Low Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="main-image">
                                <?php if (!empty($product['images'])): ?>
                                <img src="/uploads/products/<?= $product['images'][0]['image_path'] ?>" 
                                     alt="<?= $product['name'] ?>" 
                                     class="img-fluid main-product-image"
                                     data-lightbox="product-gallery"
                                     data-title="<?= $product['name'] ?>">
                                <?php else: ?>
                                <img src="/assets/images/placeholder-product.jpg" 
                                     alt="<?= $product['name'] ?>" 
                                     class="img-fluid main-product-image">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Thumbnail Images -->
                        <?php if (count($product['images']) > 1): ?>
                        <div class="thumbnail-images mt-3">
                            <div class="swiper thumbnail-swiper">
                                <div class="swiper-wrapper">
                                    <?php foreach ($product['images'] as $index => $image): ?>
                                    <div class="swiper-slide">
                                        <img src="/uploads/products/<?= $image['image_path'] ?>" 
                                             alt="<?= $image['alt_text'] ?: $product['name'] ?>" 
                                             class="img-fluid thumbnail-image <?= $index === 0 ? 'active' : '' ?>"
                                             onclick="changeMainImage(this)"
                                             data-lightbox="product-gallery"
                                             data-title="<?= $product['name'] ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="swiper-button-next"></div>
                                <div class="swiper-button-prev"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Product Information -->
                <div class="col-lg-6">
                    <div class="product-info">
                        <!-- Product Header -->
                        <div class="product-header mb-4">
                            <div class="product-category">
                                <a href="/products.php?category=<?= $product['category_id'] ?>" class="text-primary">
                                    <?= $product['category_name'] ?>
                                </a>
                                <?php if ($product['brand_name']): ?>
                                • <a href="/products.php?brand=<?= $product['brand_id'] ?>" class="text-muted">
                                    <?= $product['brand_name'] ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <h1 class="product-title"><?= $product['name'] ?></h1>
                            
                            <div class="product-meta">
                                <div class="product-rating">
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= $product['average_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-text">
                                        <?= number_format($product['average_rating'], 1) ?> 
                                        (<?= $product['review_count'] ?> reviews)
                                    </span>
                                    <a href="#reviews" class="ms-2">Write a Review</a>
                                </div>
                                
                                <div class="product-sku">
                                    <small class="text-muted">SKU: <?= $product['sku'] ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pricing -->
                        <div class="product-pricing mb-4">
                            <?php if ($product['sale_price']): ?>
                            <div class="price-container">
                                <span class="current-price"><?= formatCurrency($product['sale_price']) ?></span>
                                <span class="original-price"><?= formatCurrency($product['price']) ?></span>
                                <span class="savings-badge">
                                    Save <?= formatCurrency($product['price'] - $product['sale_price']) ?>
                                </span>
                            </div>
                            <?php else: ?>
                            <div class="price-container">
                                <span class="current-price"><?= formatCurrency($product['price']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- EMI Information -->
                            <div class="emi-info mt-2">
                                <small class="text-info">
                                    <i class="fas fa-credit-card me-1"></i>
                                    EMI available from <?= formatCurrency(($product['sale_price'] ?: $product['price']) / 12) ?>/month
                                </small>
                            </div>
                        </div>
                        
                        <!-- Product Variants -->
                        <?php if (!empty($product['variants'])): ?>
                        <div class="product-variants mb-4">
                            <h6>Available Options:</h6>
                            <div class="variant-options">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="variant" 
                                           value="" id="variant_default" checked>
                                    <label class="form-check-label" for="variant_default">
                                        <div class="variant-option">
                                            <span class="variant-name">Standard</span>
                                            <span class="variant-price"><?= formatCurrency($product['sale_price'] ?: $product['price']) ?></span>
                                        </div>
                                    </label>
                                </div>
                                
                                <?php foreach ($product['variants'] as $variant): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="variant" 
                                           value="<?= $variant['id'] ?>" id="variant_<?= $variant['id'] ?>">
                                    <label class="form-check-label" for="variant_<?= $variant['id'] ?>">
                                        <div class="variant-option">
                                            <span class="variant-name"><?= $variant['name'] ?></span>
                                            <span class="variant-price"><?= formatCurrency($variant['sale_price'] ?: $variant['price']) ?></span>
                                            <?php if ($variant['stock_quantity'] <= 0): ?>
                                            <span class="variant-stock text-danger">Out of Stock</span>
                                            <?php elseif ($variant['stock_quantity'] <= 5): ?>
                                            <span class="variant-stock text-warning">Low Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quantity and Actions -->
                        <div class="product-actions mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="quantity-selector">
                                        <label for="quantity" class="form-label">Quantity:</label>
                                        <div class="input-group">
                                            <button class="btn btn-outline-secondary" type="button" onclick="decreaseQuantity()">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" class="form-control text-center" id="quantity" 
                                                   value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                                            <button class="btn btn-outline-secondary" type="button" onclick="increaseQuantity()">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-8">
                                    <div class="action-buttons">
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                        <button class="btn btn-primary btn-lg me-2" onclick="addToCartWithVariant()">
                                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                        </button>
                                        <button class="btn btn-success btn-lg" onclick="buyNow()">
                                            <i class="fas fa-bolt me-2"></i>Buy Now
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-secondary btn-lg" disabled>
                                            <i class="fas fa-times me-2"></i>Out of Stock
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-primary" onclick="addToWishlist(<?= $productId ?>)">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                        
                                        <button class="btn btn-outline-primary" onclick="shareProduct()">
                                            <i class="fas fa-share-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Features -->
                        <?php if ($product['features']): ?>
                        <div class="product-features mb-4">
                            <h6>Key Features:</h6>
                            <ul class="features-list">
                                <?php foreach (json_decode($product['features'], true) as $feature): ?>
                                <li><i class="fas fa-check text-success me-2"></i><?= $feature ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Warranty Info -->
                        <div class="warranty-info mb-4">
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-shield-alt text-success"></i>
                                </div>
                                <div class="info-content">
                                    <strong><?= $product['warranty_period'] ?> Months Warranty</strong>
                                    <small class="text-muted d-block">Comprehensive coverage with free service</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delivery Info -->
                        <div class="delivery-info mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-truck text-primary"></i>
                                        </div>
                                        <div class="info-content">
                                            <strong>Free Delivery</strong>
                                            <small class="text-muted d-block">Within Dhaka (24-48 hours)</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-tools text-info"></i>
                                        </div>
                                        <div class="info-content">
                                            <strong>Free Installation</strong>
                                            <small class="text-muted d-block">Professional setup included</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact for Bulk Orders -->
                        <div class="bulk-order-info">
                            <div class="alert alert-info">
                                <i class="fas fa-building me-2"></i>
                                <strong>Bulk Orders:</strong> 
                                Special pricing available for offices and institutions. 
                                <a href="tel:<?= BUSINESS_PHONE ?>" class="alert-link">Call <?= BUSINESS_PHONE ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Details Tabs -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="product-details-tabs">
                        <ul class="nav nav-tabs" id="productTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="description-tab" data-bs-toggle="tab" 
                                        data-bs-target="#description" type="button" role="tab">
                                    <i class="fas fa-info-circle me-2"></i>Description
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" 
                                        data-bs-target="#specifications" type="button" role="tab">
                                    <i class="fas fa-cog me-2"></i>Specifications
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" 
                                        data-bs-target="#reviews" type="button" role="tab">
                                    <i class="fas fa-star me-2"></i>Reviews (<?= $product['review_count'] ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="warranty-tab" data-bs-toggle="tab" 
                                        data-bs-target="#warranty" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i>Warranty & Service
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="productTabsContent">
                            <!-- Description Tab -->
                            <div class="tab-pane fade show active" id="description" role="tabpanel">
                                <div class="tab-content-card">
                                    <div class="description-content">
                                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                                    </div>
                                    
                                    <?php if ($product['features']): ?>
                                    <div class="features-grid mt-4">
                                        <h6>Product Features:</h6>
                                        <div class="row">
                                            <?php foreach (json_decode($product['features'], true) as $feature): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="feature-item">
                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                    <?= $feature ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Specifications Tab -->
                            <div class="tab-pane fade" id="specifications" role="tabpanel">
                                <div class="tab-content-card">
                                    <?php if ($product['specifications']): ?>
                                    <div class="specifications-table">
                                        <table class="table table-striped">
                                            <?php foreach (json_decode($product['specifications'], true) as $key => $value): ?>
                                            <tr>
                                                <td class="spec-label"><?= ucwords(str_replace('_', ' ', $key)) ?></td>
                                                <td class="spec-value"><?= $value ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted">No specifications available for this product.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Reviews Tab -->
                            <div class="tab-pane fade" id="reviews" role="tabpanel">
                                <div class="tab-content-card">
                                    <!-- Review Summary -->
                                    <div class="review-summary mb-4">
                                        <div class="row">
                                            <div class="col-md-4 text-center">
                                                <div class="overall-rating">
                                                    <div class="rating-number"><?= number_format($product['average_rating'], 1) ?></div>
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?= $i <= $product['average_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="rating-count"><?= $product['review_count'] ?> reviews</div>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <!-- Rating Breakdown -->
                                                <div class="rating-breakdown">
                                                    <?php
                                                    $stmt = $db->prepare("
                                                        SELECT rating, COUNT(*) as count
                                                        FROM product_reviews 
                                                        WHERE product_id = ? AND is_approved = TRUE
                                                        GROUP BY rating
                                                        ORDER BY rating DESC
                                                    ");
                                                    $stmt->execute([$productId]);
                                                    $ratingBreakdown = $stmt->fetchAll();
                                                    
                                                    $totalReviews = $product['review_count'];
                                                    for ($i = 5; $i >= 1; $i--):
                                                        $count = 0;
                                                        foreach ($ratingBreakdown as $breakdown) {
                                                            if ($breakdown['rating'] == $i) {
                                                                $count = $breakdown['count'];
                                                                break;
                                                            }
                                                        }
                                                        $percentage = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0;
                                                    ?>
                                                    <div class="rating-bar">
                                                        <span class="rating-label"><?= $i ?> star</span>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-warning" style="width: <?= $percentage ?>%"></div>
                                                        </div>
                                                        <span class="rating-count"><?= $count ?></span>
                                                    </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Write Review -->
                                    <?php if ($canReview): ?>
                                    <div class="write-review mb-4">
                                        <h6>Write a Review</h6>
                                        
                                        <?php if (isset($error)): ?>
                                        <div class="alert alert-danger"><?= $error ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($success)): ?>
                                        <div class="alert alert-success"><?= $success ?></div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="review-form">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            
                                            <div class="form-group mb-3">
                                                <label class="form-label">Rating *</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" required>
                                                    <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group mb-3">
                                                <label for="review_title" class="form-label">Review Title *</label>
                                                <input type="text" class="form-control" id="review_title" name="title" 
                                                       placeholder="Summarize your experience" required maxlength="255">
                                            </div>
                                            
                                            <div class="form-group mb-3">
                                                <label for="review_text" class="form-label">Your Review *</label>
                                                <textarea class="form-control" id="review_text" name="review_text" 
                                                          rows="4" placeholder="Share your experience with this product" 
                                                          required maxlength="1000"></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="pros" class="form-label">What you liked</label>
                                                        <textarea class="form-control" id="pros" name="pros" 
                                                                  rows="3" placeholder="Positive aspects" maxlength="500"></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="cons" class="form-label">What could be better</label>
                                                        <textarea class="form-control" id="cons" name="cons" 
                                                                  rows="3" placeholder="Areas for improvement" maxlength="500"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="submit_review" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Review
                                            </button>
                                        </form>
                                    </div>
                                    <?php elseif (isLoggedIn()): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        You can write a review after purchasing and receiving this product.
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        <a href="/customer/login.php">Login</a> to write a review.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Reviews List -->
                                    <div class="reviews-list">
                                        <?php if (empty($product['reviews'])): ?>
                                        <div class="no-reviews text-center py-4">
                                            <i class="fas fa-star fa-2x text-muted mb-3"></i>
                                            <h6>No reviews yet</h6>
                                            <p class="text-muted">Be the first to review this product!</p>
                                        </div>
                                        <?php else: ?>
                                        <?php foreach ($product['reviews'] as $review): ?>
                                        <div class="review-item">
                                            <div class="review-header">
                                                <div class="reviewer-info">
                                                    <div class="reviewer-avatar">
                                                        <?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?>
                                                    </div>
                                                    <div class="reviewer-details">
                                                        <h6><?= $review['reviewer_name'] ?></h6>
                                                        <div class="review-date">
                                                            <small class="text-muted"><?= timeAgo($review['created_at']) ?></small>
                                                            <?php if ($review['is_verified_purchase']): ?>
                                                            <span class="badge bg-success ms-2">Verified Purchase</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="review-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="review-content">
                                                <h6><?= htmlspecialchars($review['title']) ?></h6>
                                                <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                                
                                                <?php if ($review['pros'] || $review['cons']): ?>
                                                <div class="pros-cons">
                                                    <?php if ($review['pros']): ?>
                                                    <div class="pros">
                                                        <strong class="text-success">
                                                            <i class="fas fa-thumbs-up me-1"></i>Pros:
                                                        </strong>
                                                        <p><?= nl2br(htmlspecialchars($review['pros'])) ?></p>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($review['cons']): ?>
                                                    <div class="cons">
                                                        <strong class="text-warning">
                                                            <i class="fas fa-thumbs-down me-1"></i>Cons:
                                                        </strong>
                                                        <p><?= nl2br(htmlspecialchars($review['cons'])) ?></p>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="review-actions">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="likeReview(<?= $review['id'] ?>)">
                                                        <i class="fas fa-thumbs-up me-1"></i>Helpful (<?= $review['helpful_count'] ?>)
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Warranty Tab -->
                            <div class="tab-pane fade" id="warranty" role="tabpanel">
                                <div class="tab-content-card">
                                    <div class="warranty-details">
                                        <h6>Warranty Coverage</h6>
                                        <ul class="warranty-list">
                                            <li><i class="fas fa-check text-success me-2"></i><?= $product['warranty_period'] ?> months comprehensive warranty</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Free repair and replacement of defective parts</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Annual maintenance service included</li>
                                            <li><i class="fas fa-check text-success me-2"></i>24/7 customer support</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Nationwide service network</li>
                                        </ul>
                                        
                                        <div class="service-info mt-4">
                                            <h6>Service Information</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="service-card">
                                                        <i class="fas fa-tools text-primary"></i>
                                                        <h6>Installation</h6>
                                                        <p>Professional installation within 24-48 hours of delivery</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="service-card">
                                                        <i class="fas fa-calendar text-success"></i>
                                                        <h6>Maintenance</h6>
                                                        <p>Regular maintenance every 6 months to ensure optimal performance</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if (!empty($relatedProducts)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h4 class="mb-4">Related Products</h4>
                    <div class="row">
                        <?php foreach ($relatedProducts as $relatedProduct): ?>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="/uploads/products/<?= $relatedProduct['image'] ?? 'placeholder.jpg' ?>" 
                                         alt="<?= $relatedProduct['name'] ?>" class="img-fluid">
                                    <div class="product-overlay">
                                        <div class="product-actions">
                                            <button class="btn btn-sm btn-white" onclick="addToWishlist(<?= $relatedProduct['id'] ?>)">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                            <a href="/product-details.php?id=<?= $relatedProduct['id'] ?>" class="btn btn-sm btn-white">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-primary" onclick="addToCart(<?= $relatedProduct['id'] ?>)">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="product-content">
                                    <h6 class="product-title">
                                        <a href="/product-details.php?id=<?= $relatedProduct['id'] ?>"><?= $relatedProduct['name'] ?></a>
                                    </h6>
                                    <div class="product-price">
                                        <?= formatCurrency($relatedProduct['sale_price'] ?: $relatedProduct['price']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
    
    <!-- Firebase SDK -->
    <?= FirebaseConfig::renderFirebaseScript() ?>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    
    <script>
        let selectedVariant = null;
        
        $(document).ready(function() {
            // Initialize product page
            initializeProductPage();
            
            // Initialize image gallery
            initializeImageGallery();
            
            // Initialize review system
            initializeReviews();
            
            // Track product view
            trackProductView(<?= $productId ?>);
        });
        
        function initializeProductPage() {
            // Variant selection
            $('input[name="variant"]').change(function() {
                selectedVariant = this.value || null;
                updateProductPrice();
                updateStockInfo();
            });
            
            // Star rating for reviews
            $('.star-rating input').change(function() {
                const rating = this.value;
                updateStarDisplay(rating);
            });
        }
        
        function initializeImageGallery() {
            // Initialize thumbnail swiper
            new Swiper('.thumbnail-swiper', {
                slidesPerView: 4,
                spaceBetween: 10,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    768: {
                        slidesPerView: 5,
                    }
                }
            });
            
            // Configure lightbox
            lightbox.option({
                'resizeDuration': 200,
                'wrapAround': true,
                'albumLabel': 'Image %1 of %2'
            });
        }
        
        function changeMainImage(thumbnail) {
            const mainImage = document.querySelector('.main-product-image');
            mainImage.src = thumbnail.src;
            mainImage.setAttribute('data-lightbox', 'product-gallery');
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-image').forEach(img => {
                img.classList.remove('active');
            });
            thumbnail.classList.add('active');
        }
        
        function updateProductPrice() {
            if (selectedVariant) {
                // Get variant price via AJAX
                $.get('/api/products/variant-price.php', {
                    variant_id: selectedVariant
                })
                .done(function(response) {
                    if (response.success) {
                        $('.current-price').text(formatCurrency(response.price));
                        if (response.original_price && response.original_price > response.price) {
                            $('.original-price').text(formatCurrency(response.original_price)).show();
                        } else {
                            $('.original-price').hide();
                        }
                    }
                });
            }
        }
        
        function updateStockInfo() {
            const maxQty = selectedVariant ? 
                $(`#variant_${selectedVariant}`).closest('.form-check').find('.variant-stock').length > 0 ? 0 : 10 :
                <?= $product['stock_quantity'] ?>;
            
            $('#quantity').attr('max', maxQty);
            
            if (maxQty <= 0) {
                $('.action-buttons .btn-primary, .action-buttons .btn-success').prop('disabled', true);
            } else {
                $('.action-buttons .btn-primary, .action-buttons .btn-success').prop('disabled', false);
            }
        }
        
        function increaseQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            const maxValue = parseInt(input.max);
            
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
            }
        }
        
        function decreaseQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
        }
        
        function addToCartWithVariant() {
            const quantity = parseInt(document.getElementById('quantity').value);
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            button.disabled = true;
            
            $.post('/api/cart/add.php', {
                product_id: <?= $productId ?>,
                variant_id: selectedVariant,
                quantity: quantity
            })
            .done(function(response) {
                if (response.success) {
                    showToast('Success', 'Product added to cart!', 'success');
                    updateCartCount();
                    
                    // Track add to cart event
                    if (window.FirebaseHelpers) {
                        window.FirebaseHelpers.trackEvent('add_to_cart', {
                            currency: 'BDT',
                            value: response.product.price * quantity,
                            items: [{
                                item_id: <?= $productId ?>,
                                item_name: '<?= addslashes($product['name']) ?>',
                                quantity: quantity,
                                price: response.product.price
                            }]
                        });
                    }
                } else {
                    showToast('Error', response.message, 'error');
                }
            })
            .fail(function() {
                showToast('Error', 'Failed to add product to cart', 'error');
            })
            .always(function() {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        function buyNow() {
            const quantity = parseInt(document.getElementById('quantity').value);
            
            // Add to cart first
            $.post('/api/cart/add.php', {
                product_id: <?= $productId ?>,
                variant_id: selectedVariant,
                quantity: quantity
            })
            .done(function(response) {
                if (response.success) {
                    // Redirect to checkout
                    window.location.href = '/checkout.php';
                } else {
                    showToast('Error', response.message, 'error');
                }
            })
            .fail(function() {
                showToast('Error', 'Failed to proceed to checkout', 'error');
            });
        }
        
        function shareProduct() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= addslashes($product['name']) ?>',
                    text: '<?= addslashes($product['short_description']) ?>',
                    url: window.location.href
                });
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    showToast('Success', 'Product link copied to clipboard!', 'success');
                });
            }
        }
        
        function initializeReviews() {
            // Star rating interaction
            $('.star-rating label').hover(
                function() {
                    const rating = $(this).prev('input').val();
                    highlightStars(rating);
                },
                function() {
                    const selectedRating = $('.star-rating input:checked').val() || 0;
                    highlightStars(selectedRating);
                }
            );
        }
        
        function highlightStars(rating) {
            $('.star-rating label').each(function(index) {
                const star = $(this).find('i');
                if (index < rating) {
                    star.removeClass('text-muted').addClass('text-warning');
                } else {
                    star.removeClass('text-warning').addClass('text-muted');
                }
            });
        }
        
        function updateStarDisplay(rating) {
            highlightStars(rating);
        }
        
        function likeReview(reviewId) {
            $.post('/api/reviews/like.php', {
                review_id: reviewId
            })
            .done(function(response) {
                if (response.success) {
                    const button = event.target;
                    const countSpan = button.querySelector('.helpful-count') || 
                                    button.textContent.match(/\((\d+)\)/);
                    
                    if (countSpan) {
                        const newCount = response.helpful_count;
                        button.innerHTML = `<i class="fas fa-thumbs-up me-1"></i>Helpful (${newCount})`;
                    }
                    
                    showToast('Success', 'Thank you for your feedback!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            })
            .fail(function() {
                showToast('Error', 'Failed to record feedback', 'error');
            });
        }
    </script>
    
    <style>
        .product-gallery {
            position: sticky;
            top: 100px;
        }
        
        .main-image-container {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .product-badges {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .main-image {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            cursor: pointer;
        }
        
        .thumbnail-image {
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all var(--transition-fast);
            opacity: 0.7;
        }
        
        .thumbnail-image:hover,
        .thumbnail-image.active {
            opacity: 1;
            box-shadow: var(--shadow);
        }
        
        .product-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .current-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
        }
        
        .original-price {
            font-size: 1.5rem;
            color: var(--gray-500);
            text-decoration: line-through;
            margin-left: 1rem;
        }
        
        .savings-badge {
            background: var(--success-color);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            margin-left: 1rem;
        }
        
        .variant-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .form-check-input:checked + .form-check-label .variant-option {
            border-color: var(--primary-color);
            background: rgba(0, 102, 204, 0.05);
        }
        
        .info-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .info-icon {
            font-size: 1.5rem;
        }
        
        .tab-content-card {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }
        
        .review-item {
            border-bottom: 1px solid var(--gray-200);
            padding: 2rem 0;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .reviewer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            gap: 0.2rem;
        }
        
        .star-rating input {
            display: none;
        }
        
        .star-rating label {
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--gray-300);
            transition: color var(--transition-fast);
        }
        
        .star-rating label:hover,
        .star-rating input:checked ~ label {
            color: var(--warning-color);
        }
        
        .overall-rating {
            background: var(--primary-color);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
        }
        
        .rating-number {
            font-size: 3rem;
            font-weight: 700;
        }
        
        .rating-breakdown {
            padding: 1rem 0;
        }
        
        .rating-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .rating-label {
            min-width: 60px;
            font-size: 0.9rem;
        }
        
        .rating-count {
            min-width: 30px;
            text-align: right;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .product-title {
                font-size: 1.5rem;
            }
            
            .current-price {
                font-size: 2rem;
            }
            
            .action-buttons {
                text-align: center;
            }
            
            .action-buttons .btn {
                margin-bottom: 0.5rem;
            }
            
            .product-gallery {
                position: static;
            }
        }
    </style>
</body>
</html>