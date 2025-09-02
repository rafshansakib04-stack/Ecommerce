<?php
/**
 * PureFit Bangladesh - Homepage
 * Modern, responsive homepage for water purifier business
 */

require_once 'includes/functions.php';

// Get featured products
$featuredProducts = getProducts(['featured' => true], 1, 8);

// Get categories
$categories = getRecords('categories', ['is_active' => true], '*', 'sort_order ASC');

// Get recent reviews
$db = getDB();
$stmt = $db->prepare("
    SELECT pr.*, p.name as product_name, CONCAT(u.first_name, ' ', u.last_name) as customer_name
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    JOIN users u ON pr.user_id = u.id
    WHERE pr.is_approved = TRUE
    ORDER BY pr.created_at DESC
    LIMIT 6
");
$stmt->execute();
$recentReviews = $stmt->fetchAll();

// Get business stats
$stmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
        (SELECT COUNT(*) FROM orders WHERE status = 'delivered') as total_orders,
        (SELECT COUNT(*) FROM products WHERE is_active = TRUE) as total_products,
        (SELECT COUNT(*) FROM service_requests WHERE status = 'completed') as total_services
");
$stmt->execute();
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureFit Bangladesh - Premium Water Purifiers | Best RO Systems in Bangladesh</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="PureFit Bangladesh offers premium water purifiers, RO systems, and water treatment solutions. Best prices, professional installation, and 24/7 service support across Bangladesh.">
    <meta name="keywords" content="water purifier bangladesh, RO system dhaka, water filter, aquaguard, kent, pureit, water treatment, clean water">
    <meta name="author" content="PureFit Bangladesh">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="PureFit Bangladesh - Premium Water Purifiers">
    <meta property="og:description" content="Best water purifiers and RO systems in Bangladesh. Professional installation and service support.">
    <meta property="og:image" content="<?= APP_URL ?>/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= APP_URL ?>">
    <meta property="og:type" content="website">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "PureFit Bangladesh",
        "description": "Premium water purifiers and RO systems in Bangladesh",
        "url": "<?= APP_URL ?>",
        "telephone": "<?= BUSINESS_PHONE ?>",
        "email": "<?= BUSINESS_EMAIL ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "<?= BUSINESS_ADDRESS ?>",
            "addressCountry": "Bangladesh"
        },
        "sameAs": [
            "<?= getSetting('facebook_page') ?>",
            "<?= getSetting('instagram_page') ?>",
            "<?= getSetting('youtube_channel') ?>"
        ]
    }
    </script>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <img src="/assets/images/logo.png" alt="PureFit" class="loading-logo animate__animated animate__pulse animate__infinite">
            <div class="loading-text">Loading PureFit...</div>
            <div class="loading-spinner"></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="/assets/images/logo.png" alt="PureFit Bangladesh" height="40">
                <span class="brand-text">PureFit</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Products
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($categories as $category): ?>
                            <li><a class="dropdown-item" href="/products.php?category=<?= $category['id'] ?>"><?= $category['name'] ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/contact.php">Contact</a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <!-- Search -->
                    <form class="d-flex me-3" action="/products.php" method="GET">
                        <div class="input-group">
                            <input class="form-control" type="search" name="search" placeholder="Search products..." aria-label="Search">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Cart -->
                    <a href="/cart.php" class="nav-link position-relative me-3">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                            <?= count(getCartItems()) ?>
                        </span>
                    </a>
                    
                    <!-- User Account -->
                    <?php if (isLoggedIn()): ?>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?= getCurrentUser()['first_name'] ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/customer/dashboard.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="/customer/orders.php">My Orders</a></li>
                                <li><a class="dropdown-item" href="/customer/wishlist.php">Wishlist</a></li>
                                <li><a class="dropdown-item" href="/customer/profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/customer/logout.php">Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/customer/login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="/customer/register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-background">
            <div class="hero-overlay"></div>
            <video autoplay muted loop class="hero-video">
                <source src="/assets/videos/water-purifier-hero.mp4" type="video/mp4">
            </video>
        </div>
        
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-content animate__animated animate__fadeInLeft">
                        <h1 class="hero-title">
                            Pure Water, <span class="text-primary">Pure Life</span>
                        </h1>
                        <p class="hero-subtitle">
                            Experience the finest water purification technology in Bangladesh. 
                            From RO systems to UV purifiers, we ensure every drop is perfect for your family.
                        </p>
                        
                        <div class="hero-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?= number_format($stats['total_customers']) ?>+</div>
                                <div class="stat-label">Happy Customers</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= number_format($stats['total_orders']) ?>+</div>
                                <div class="stat-label">Orders Delivered</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">24/7</div>
                                <div class="stat-label">Service Support</div>
                            </div>
                        </div>
                        
                        <div class="hero-actions">
                            <a href="/products.php" class="btn btn-primary btn-lg me-3">
                                <i class="fas fa-tint me-2"></i>Shop Now
                            </a>
                            <a href="/services.php" class="btn btn-outline-white btn-lg">
                                <i class="fas fa-tools me-2"></i>Book Service
                            </a>
                        </div>
                        
                        <div class="hero-features">
                            <div class="feature-item">
                                <i class="fas fa-shipping-fast"></i>
                                <span>Free Installation</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>2 Year Warranty</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-headset"></i>
                                <span>24/7 Support</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="hero-image animate__animated animate__fadeInRight">
                        <img src="/assets/images/hero-purifier.png" alt="Premium Water Purifier" class="img-fluid">
                        
                        <!-- Floating Elements -->
                        <div class="floating-element element-1 animate__animated animate__fadeInUp animate__delay-1s">
                            <i class="fas fa-tint text-primary"></i>
                            <span>99.9% Pure</span>
                        </div>
                        <div class="floating-element element-2 animate__animated animate__fadeInUp animate__delay-2s">
                            <i class="fas fa-leaf text-success"></i>
                            <span>Eco Friendly</span>
                        </div>
                        <div class="floating-element element-3 animate__animated animate__fadeInUp animate__delay-3s">
                            <i class="fas fa-award text-warning"></i>
                            <span>Certified</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="scroll-indicator animate__animated animate__bounce animate__infinite">
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card text-center animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-truck text-primary"></i>
                        </div>
                        <h5>Free Delivery</h5>
                        <p>Free delivery and installation across Dhaka within 24 hours</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card text-center animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-tools text-success"></i>
                        </div>
                        <h5>Professional Installation</h5>
                        <p>Expert technicians ensure perfect installation and setup</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card text-center animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt text-warning"></i>
                        </div>
                        <h5>Extended Warranty</h5>
                        <p>Up to 3 years warranty with comprehensive coverage</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="feature-card text-center animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-headset text-info"></i>
                        </div>
                        <h5>24/7 Support</h5>
                        <p>Round-the-clock customer support and emergency service</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Categories -->
    <section class="categories-section py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title animate-on-scroll">
                        Choose Your <span class="text-primary">Perfect Purifier</span>
                    </h2>
                    <p class="section-subtitle">Explore our comprehensive range of water purification solutions</p>
                </div>
            </div>
            
            <div class="row">
                <?php foreach ($categories as $index => $category): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="category-card animate-on-scroll" data-delay="<?= $index * 100 ?>">
                        <div class="category-image">
                            <img src="/assets/images/categories/<?= $category['slug'] ?>.jpg" alt="<?= $category['name'] ?>" class="img-fluid">
                            <div class="category-overlay">
                                <a href="/products.php?category=<?= $category['id'] ?>" class="btn btn-white">
                                    Explore <?= $category['name'] ?>
                                </a>
                            </div>
                        </div>
                        <div class="category-content">
                            <h5><?= $category['name'] ?></h5>
                            <p><?= $category['description'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="featured-products py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title animate-on-scroll">
                        <span class="text-primary">Featured</span> Products
                    </h2>
                    <p class="section-subtitle">Our most popular and highly-rated water purifiers</p>
                </div>
            </div>
            
            <div class="row">
                <?php foreach ($featuredProducts as $index => $product): ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="product-card animate-on-scroll" data-delay="<?= $index * 100 ?>">
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
                        
                        <div class="product-image">
                            <img src="/uploads/products/<?= $product['images'][0]['image_path'] ?? 'placeholder.jpg' ?>" alt="<?= $product['name'] ?>" class="img-fluid">
                            <div class="product-overlay">
                                <div class="product-actions">
                                    <button class="btn btn-sm btn-white" onclick="addToWishlist(<?= $product['id'] ?>)">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                    <button class="btn btn-sm btn-white" onclick="quickView(<?= $product['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="addToCart(<?= $product['id'] ?>)">
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
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="/products.php" class="btn btn-primary btn-lg">
                    View All Products <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="why-choose-us py-5 bg-primary text-white">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title text-white animate-on-scroll">
                        Why Choose <span class="text-warning">PureFit?</span>
                    </h2>
                    <p class="section-subtitle text-white-50">Leading water purification solutions in Bangladesh</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="why-card animate-on-scroll">
                        <div class="why-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h5>Certified Quality</h5>
                        <p>All our products are certified by international standards and tested for Bangladesh water conditions.</p>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="why-card animate-on-scroll" data-delay="200">
                        <div class="why-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5>Expert Team</h5>
                        <p>Our trained technicians provide professional installation, maintenance, and repair services.</p>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="why-card animate-on-scroll" data-delay="400">
                        <div class="why-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h5>Best Prices</h5>
                        <p>Competitive pricing with flexible payment options including EMI and cash on delivery.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Customer Reviews -->
    <section class="reviews-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title animate-on-scroll">
                        What Our <span class="text-primary">Customers Say</span>
                    </h2>
                    <p class="section-subtitle">Real reviews from satisfied customers across Bangladesh</p>
                </div>
            </div>
            
            <div class="swiper reviews-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($recentReviews as $review): ?>
                    <div class="swiper-slide">
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?= strtoupper(substr($review['customer_name'], 0, 1)) ?>
                                    </div>
                                    <div class="reviewer-details">
                                        <h6><?= $review['customer_name'] ?></h6>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="review-date">
                                    <?= timeAgo($review['created_at']) ?>
                                </div>
                            </div>
                            
                            <div class="review-content">
                                <h6><?= $review['title'] ?></h6>
                                <p><?= $review['review_text'] ?></p>
                                <div class="review-product">
                                    <small class="text-muted">Product: <?= $review['product_name'] ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
    </section>

    <!-- Service Areas -->
    <section class="service-areas py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title animate-on-scroll">
                        We Serve <span class="text-primary">All Over Bangladesh</span>
                    </h2>
                    <p class="section-subtitle">Professional installation and service support nationwide</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="service-areas-grid">
                        <div class="area-item animate-on-scroll">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Dhaka Division</span>
                        </div>
                        <div class="area-item animate-on-scroll" data-delay="100">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Chittagong Division</span>
                        </div>
                        <div class="area-item animate-on-scroll" data-delay="200">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Sylhet Division</span>
                        </div>
                        <div class="area-item animate-on-scroll" data-delay="300">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Khulna Division</span>
                        </div>
                        <div class="area-item animate-on-scroll" data-delay="400">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Rajshahi Division</span>
                        </div>
                        <div class="area-item animate-on-scroll" data-delay="500">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Barisal Division</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter-section py-5 bg-primary">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="newsletter-content text-white">
                        <h3 class="animate-on-scroll">Stay Updated with PureFit</h3>
                        <p class="animate-on-scroll">Get exclusive offers, maintenance tips, and product updates delivered to your inbox.</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <form class="newsletter-form animate-on-scroll" id="newsletter-form">
                        <div class="input-group">
                            <input type="email" class="form-control" name="email" placeholder="Enter your email address" required>
                            <button class="btn btn-warning" type="submit">
                                <i class="fas fa-paper-plane me-2"></i>Subscribe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-brand">
                        <img src="/assets/images/logo-white.png" alt="PureFit Bangladesh" height="40" class="mb-3">
                        <p>Leading provider of premium water purification solutions in Bangladesh. Ensuring pure, safe water for every home and office.</p>
                        
                        <div class="social-links">
                            <a href="<?= getSetting('facebook_page') ?>" class="social-link">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="<?= getSetting('instagram_page') ?>" class="social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="<?= getSetting('youtube_channel') ?>" class="social-link">
                                <i class="fab fa-youtube"></i>
                            </a>
                            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', BUSINESS_WHATSAPP) ?>" class="social-link">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="footer-title">Products</h6>
                    <ul class="footer-links">
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <li><a href="/products.php?category=<?= $category['id'] ?>"><?= $category['name'] ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="footer-title">Services</h6>
                    <ul class="footer-links">
                        <li><a href="/services.php">Installation</a></li>
                        <li><a href="/services.php">Maintenance</a></li>
                        <li><a href="/services.php">Repair</a></li>
                        <li><a href="/warranty.php">Warranty</a></li>
                        <li><a href="/support.php">Support</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="footer-title">Company</h6>
                    <ul class="footer-links">
                        <li><a href="/about.php">About Us</a></li>
                        <li><a href="/contact.php">Contact</a></li>
                        <li><a href="/careers.php">Careers</a></li>
                        <li><a href="/blog.php">Blog</a></li>
                        <li><a href="/privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="footer-title">Contact Info</h6>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-phone text-primary"></i>
                            <span><?= BUSINESS_PHONE ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope text-primary"></i>
                            <span><?= BUSINESS_EMAIL ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span><?= BUSINESS_ADDRESS ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="footer-divider">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="copyright">&copy; <?= date('Y') ?> PureFit Bangladesh. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="payment-methods">
                        <img src="/assets/images/payments/bkash.png" alt="bKash" height="30">
                        <img src="/assets/images/payments/nagad.png" alt="Nagad" height="30">
                        <img src="/assets/images/payments/rocket.png" alt="Rocket" height="30">
                        <img src="/assets/images/payments/visa.png" alt="Visa" height="30">
                        <img src="/assets/images/payments/mastercard.png" alt="Mastercard" height="30">
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top -->
    <button id="back-to-top" class="back-to-top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Quick Contact -->
    <div class="quick-contact">
        <div class="quick-contact-item">
            <a href="tel:<?= BUSINESS_PHONE ?>" class="quick-contact-btn phone-btn">
                <i class="fas fa-phone"></i>
            </a>
        </div>
        <div class="quick-contact-item">
            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', BUSINESS_WHATSAPP) ?>" class="quick-contact-btn whatsapp-btn" target="_blank">
                <i class="fab fa-whatsapp"></i>
            </a>
        </div>
        <div class="quick-contact-item">
            <button class="quick-contact-btn chat-btn" onclick="openLiveChat()">
                <i class="fas fa-comments"></i>
            </button>
        </div>
    </div>

    <!-- Modals -->
    <!-- Quick View Modal -->
    <div class="modal fade" id="quickViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Quick View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="quickViewContent">
                    <!-- Quick view content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    
    <!-- Firebase SDK -->
    <?= FirebaseConfig::renderFirebaseScript() ?>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    
    <script>
        // Initialize page
        $(document).ready(function() {
            // Hide loading screen
            setTimeout(() => {
                $('#loading-screen').fadeOut(500);
            }, 1000);
            
            // Initialize Swiper
            new Swiper('.reviews-swiper', {
                slidesPerView: 1,
                spaceBetween: 30,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    768: {
                        slidesPerView: 2,
                    },
                    1024: {
                        slidesPerView: 3,
                    }
                }
            });
            
            // Animate on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const delay = element.dataset.delay || 0;
                        
                        setTimeout(() => {
                            element.classList.add('animate__animated', 'animate__fadeInUp');
                        }, delay);
                        
                        observer.unobserve(element);
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.animate-on-scroll').forEach(el => {
                observer.observe(el);
            });
            
            // Newsletter form
            $('#newsletter-form').on('submit', function(e) {
                e.preventDefault();
                
                const email = $(this).find('input[name="email"]').val();
                
                $.post('/api/newsletter/subscribe.php', { email: email })
                    .done(function(response) {
                        if (response.success) {
                            showToast('Success', 'Thank you for subscribing!', 'success');
                            $('#newsletter-form')[0].reset();
                        } else {
                            showToast('Error', response.message, 'error');
                        }
                    })
                    .fail(function() {
                        showToast('Error', 'Something went wrong. Please try again.', 'error');
                    });
            });
            
            // Back to top button
            $(window).scroll(function() {
                if ($(this).scrollTop() > 300) {
                    $('#back-to-top').fadeIn();
                } else {
                    $('#back-to-top').fadeOut();
                }
            });
            
            $('#back-to-top').click(function() {
                $('html, body').animate({scrollTop: 0}, 800);
            });
        });
        
        // Product functions
        function addToCart(productId, variantId = null, quantity = 1) {
            $.post('/api/cart/add.php', {
                product_id: productId,
                variant_id: variantId,
                quantity: quantity
            })
            .done(function(response) {
                if (response.success) {
                    updateCartCount();
                    showToast('Success', 'Product added to cart!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            })
            .fail(function() {
                showToast('Error', 'Please login to add items to cart', 'error');
            });
        }
        
        function addToWishlist(productId) {
            $.post('/api/wishlist/add.php', { product_id: productId })
                .done(function(response) {
                    if (response.success) {
                        showToast('Success', 'Product added to wishlist!', 'success');
                    } else {
                        showToast('Error', response.message, 'error');
                    }
                })
                .fail(function() {
                    showToast('Error', 'Please login to add items to wishlist', 'error');
                });
        }
        
        function quickView(productId) {
            $('#quickViewModal').modal('show');
            $('#quickViewContent').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
            
            $.get('/api/products/quick-view.php', { id: productId })
                .done(function(response) {
                    $('#quickViewContent').html(response);
                })
                .fail(function() {
                    $('#quickViewContent').html('<div class="alert alert-danger">Failed to load product details</div>');
                });
        }
        
        function updateCartCount() {
            $.get('/api/cart/count.php')
                .done(function(response) {
                    $('#cart-count').text(response.count);
                });
        }
        
        function openLiveChat() {
            // Initialize live chat
            if (typeof LiveChat !== 'undefined') {
                LiveChat.open();
            } else {
                // Fallback to contact form or WhatsApp
                window.open('https://wa.me/<?= str_replace(['+', '-', ' '], '', BUSINESS_WHATSAPP) ?>?text=Hello, I need help with water purifiers', '_blank');
            }
        }
        
        function showToast(title, message, type = 'info') {
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <strong>${title}:</strong> ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            const toastContainer = document.getElementById('toast-container') || (() => {
                const container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
                return container;
            })();
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toast = new bootstrap.Toast(toastContainer.lastElementChild);
            toast.show();
        }
        
        // Track page view
        if (window.FirebaseHelpers) {
            FirebaseHelpers.trackEvent('page_view', {
                page_title: document.title,
                page_location: window.location.href
            });
        }
    </script>
</body>
</html>