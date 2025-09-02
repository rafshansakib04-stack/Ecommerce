/**
 * PureFit Bangladesh - Main JavaScript
 * Core functionality for the water purifier business system
 */

// Global variables
let isLoggedIn = false;
let currentUser = null;
let cartCount = 0;

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Application Initialization
 */
function initializeApp() {
    // Check authentication state
    checkAuthState();
    
    // Initialize components
    initializeNavbar();
    initializeModals();
    initializeTooltips();
    initializeFormValidation();
    initializeCartFunctionality();
    initializeSearchFunctionality();
    initializeLiveChat();
    
    // Initialize Firebase features
    if (window.firebaseAuth) {
        initializeFirebaseAuth();
    }
    
    // Update cart count
    updateCartCount();
    
    // Initialize page-specific functionality
    const currentPage = getCurrentPage();
    initializePageSpecific(currentPage);
    
    console.log('PureFit application initialized successfully');
}

/**
 * Authentication Functions
 */
function checkAuthState() {
    // Check if user is logged in via session
    fetch('/api/auth/check.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.user) {
                isLoggedIn = true;
                currentUser = data.user;
                updateUIForLoggedInUser();
            }
        })
        .catch(error => {
            console.error('Auth check error:', error);
        });
}

function initializeFirebaseAuth() {
    // Firebase auth state listener is already set up in firebase.php
    // Additional Firebase-specific initialization can go here
    
    // Request notification permission
    if (window.FirebaseHelpers) {
        window.FirebaseHelpers.requestNotificationPermission()
            .then(token => {
                if (token) {
                    // Save FCM token to backend
                    saveFCMToken(token);
                }
            });
    }
}

function updateUIForLoggedInUser() {
    // Update navigation
    const userMenu = document.querySelector('.user-menu');
    if (userMenu && currentUser) {
        userMenu.style.display = 'block';
        document.querySelector('.user-name').textContent = currentUser.first_name;
    }
    
    // Show/hide login buttons
    const loginButtons = document.querySelectorAll('.login-required');
    const logoutButtons = document.querySelectorAll('.logout-required');
    
    loginButtons.forEach(btn => btn.style.display = isLoggedIn ? 'none' : 'block');
    logoutButtons.forEach(btn => btn.style.display = isLoggedIn ? 'block' : 'none');
}

function saveFCMToken(token) {
    if (!isLoggedIn) return;
    
    fetch('/api/notifications/save-token.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ fcm_token: token })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('FCM token saved successfully');
        }
    })
    .catch(error => {
        console.error('Error saving FCM token:', error);
    });
}

/**
 * Navigation Functions
 */
function initializeNavbar() {
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Mobile menu toggle
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.addEventListener('click', function() {
            this.classList.toggle('active');
        });
    }
}

/**
 * Cart Functions
 */
function initializeCartFunctionality() {
    // Cart item quantity controls
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('qty-increase')) {
            const input = e.target.previousElementSibling;
            input.value = parseInt(input.value) + 1;
            updateCartItem(input.dataset.itemId, input.value);
        }
        
        if (e.target.classList.contains('qty-decrease')) {
            const input = e.target.nextElementSibling;
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                updateCartItem(input.dataset.itemId, input.value);
            }
        }
        
        if (e.target.classList.contains('remove-cart-item')) {
            const itemId = e.target.dataset.itemId;
            removeCartItem(itemId);
        }
    });
}

function addToCart(productId, variantId = null, quantity = 1) {
    // Show loading state
    const addButton = event.target;
    const originalText = addButton.innerHTML;
    addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    addButton.disabled = true;
    
    fetch('/api/cart/add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            variant_id: variantId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount();
            showToast('Success', 'Product added to cart!', 'success');
            
            // Animate cart icon
            const cartIcon = document.querySelector('.fa-shopping-cart');
            if (cartIcon) {
                cartIcon.classList.add('pulse-animation');
                setTimeout(() => cartIcon.classList.remove('pulse-animation'), 1000);
            }
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Add to cart error:', error);
        showToast('Error', 'Failed to add product to cart', 'error');
    })
    .finally(() => {
        // Restore button state
        addButton.innerHTML = originalText;
        addButton.disabled = false;
    });
}

function updateCartItem(itemId, quantity) {
    fetch('/api/cart/update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_id: itemId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount();
            updateCartTotals();
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Update cart error:', error);
    });
}

function removeCartItem(itemId) {
    if (!confirm('Are you sure you want to remove this item from cart?')) {
        return;
    }
    
    fetch('/api/cart/remove.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ item_id: itemId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove item from UI
            const itemElement = document.querySelector(`[data-item-id="${itemId}"]`);
            if (itemElement) {
                itemElement.remove();
            }
            
            updateCartCount();
            updateCartTotals();
            showToast('Success', 'Item removed from cart', 'success');
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Remove cart item error:', error);
    });
}

function updateCartCount() {
    fetch('/api/cart/count.php')
        .then(response => response.json())
        .then(data => {
            const cartCountElement = document.getElementById('cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = data.count;
                cartCount = data.count;
            }
        })
        .catch(error => {
            console.error('Update cart count error:', error);
        });
}

function updateCartTotals() {
    fetch('/api/cart/totals.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update total displays
                const subtotalElement = document.getElementById('cart-subtotal');
                const totalElement = document.getElementById('cart-total');
                
                if (subtotalElement) {
                    subtotalElement.textContent = formatCurrency(data.subtotal);
                }
                
                if (totalElement) {
                    totalElement.textContent = formatCurrency(data.total);
                }
            }
        })
        .catch(error => {
            console.error('Update cart totals error:', error);
        });
}

/**
 * Wishlist Functions
 */
function addToWishlist(productId) {
    if (!isLoggedIn) {
        showToast('Info', 'Please login to add items to wishlist', 'info');
        return;
    }
    
    fetch('/api/wishlist/add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Success', 'Product added to wishlist!', 'success');
            
            // Update wishlist icon
            const wishlistBtn = event.target.closest('button');
            if (wishlistBtn) {
                wishlistBtn.innerHTML = '<i class="fas fa-heart text-danger"></i>';
            }
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Add to wishlist error:', error);
    });
}

function removeFromWishlist(productId) {
    fetch('/api/wishlist/remove.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Success', 'Product removed from wishlist', 'success');
            
            // Remove from UI if on wishlist page
            const itemElement = document.querySelector(`[data-product-id="${productId}"]`);
            if (itemElement) {
                itemElement.remove();
            }
        } else {
            showToast('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Remove from wishlist error:', error);
    });
}

/**
 * Product Functions
 */
function quickView(productId) {
    const modal = document.getElementById('quickViewModal');
    const content = document.getElementById('quickViewContent');
    
    // Show modal with loading
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    content.innerHTML = `
        <div class="text-center p-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3">Loading product details...</div>
        </div>
    `;
    
    // Load product data
    fetch(`/api/products/quick-view.php?id=${productId}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            console.error('Quick view error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load product details. Please try again.
                </div>
            `;
        });
}

function filterProducts(filters) {
    const params = new URLSearchParams(filters);
    const url = '/products.php?' + params.toString();
    
    // Show loading state
    showProductsLoading();
    
    // Update URL without page reload
    history.pushState({}, '', url);
    
    // Load filtered products
    fetch('/api/products/filter.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProductsGrid(data.products);
                updatePagination(data.pagination);
            } else {
                showToast('Error', 'Failed to load products', 'error');
            }
        })
        .catch(error => {
            console.error('Filter products error:', error);
            showToast('Error', 'Failed to load products', 'error');
        })
        .finally(() => {
            hideProductsLoading();
        });
}

function showProductsLoading() {
    const productsGrid = document.getElementById('products-grid');
    if (productsGrid) {
        productsGrid.innerHTML = `
            <div class="col-12 text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-3">Loading products...</div>
            </div>
        `;
    }
}

function hideProductsLoading() {
    // Loading state will be replaced by updateProductsGrid
}

function updateProductsGrid(products) {
    const productsGrid = document.getElementById('products-grid');
    if (!productsGrid) return;
    
    if (products.length === 0) {
        productsGrid.innerHTML = `
            <div class="col-12 text-center p-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5>No products found</h5>
                <p class="text-muted">Try adjusting your search criteria</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    products.forEach(product => {
        html += generateProductCard(product);
    });
    
    productsGrid.innerHTML = html;
}

function generateProductCard(product) {
    const salePercentage = product.sale_price ? 
        Math.round(((product.price - product.sale_price) / product.price) * 100) : 0;
    
    return `
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="product-card animate__animated animate__fadeIn">
                ${product.sale_price ? `<div class="product-badge sale-badge">${salePercentage}% OFF</div>` : ''}
                ${product.is_featured ? '<div class="product-badge featured-badge"><i class="fas fa-star"></i> Featured</div>' : ''}
                
                <div class="product-image">
                    <img src="/uploads/products/${product.image || 'placeholder.jpg'}" alt="${product.name}" class="img-fluid">
                    <div class="product-overlay">
                        <div class="product-actions">
                            <button class="btn btn-sm btn-white" onclick="addToWishlist(${product.id})" title="Add to Wishlist">
                                <i class="fas fa-heart"></i>
                            </button>
                            <button class="btn btn-sm btn-white" onclick="quickView(${product.id})" title="Quick View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="addToCart(${product.id})" title="Add to Cart">
                                <i class="fas fa-cart-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="product-content">
                    <div class="product-category">${product.category_name}</div>
                    <h6 class="product-title">
                        <a href="/product-details.php?id=${product.id}">${product.name}</a>
                    </h6>
                    
                    <div class="product-rating">
                        ${generateStarRating(product.average_rating)}
                        <span class="rating-count">(${product.review_count})</span>
                    </div>
                    
                    <div class="product-price">
                        ${product.sale_price ? 
                            `<span class="current-price">${formatCurrency(product.sale_price)}</span>
                             <span class="original-price">${formatCurrency(product.price)}</span>` :
                            `<span class="current-price">${formatCurrency(product.price)}</span>`
                        }
                    </div>
                    
                    ${product.features ? `
                        <div class="product-features">
                            <small class="text-muted">${JSON.parse(product.features).slice(0, 2).join(' • ')}</small>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

function generateStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<i class="fas fa-star ${i <= rating ? 'text-warning' : 'text-muted'}"></i>`;
    }
    return stars;
}

/**
 * Search Functions
 */
function initializeSearchFunctionality() {
    const searchForm = document.querySelector('.navbar form[action="/products.php"]');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput.value.trim().length < 2) {
                e.preventDefault();
                showToast('Info', 'Please enter at least 2 characters to search', 'info');
            }
        });
    }
    
    // Auto-complete search
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    showSearchSuggestions(query);
                }, 300);
            } else {
                hideSearchSuggestions();
            }
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                hideSearchSuggestions();
            }
        });
    }
}

function showSearchSuggestions(query) {
    fetch(`/api/search/suggestions.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suggestions.length > 0) {
                displaySearchSuggestions(data.suggestions);
            }
        })
        .catch(error => {
            console.error('Search suggestions error:', error);
        });
}

function displaySearchSuggestions(suggestions) {
    const searchInput = document.querySelector('input[name="search"]');
    let suggestionsContainer = document.getElementById('search-suggestions');
    
    if (!suggestionsContainer) {
        suggestionsContainer = document.createElement('div');
        suggestionsContainer.id = 'search-suggestions';
        suggestionsContainer.className = 'search-suggestions';
        searchInput.parentNode.appendChild(suggestionsContainer);
    }
    
    let html = '<div class="suggestions-list">';
    suggestions.forEach(suggestion => {
        html += `
            <div class="suggestion-item" onclick="selectSearchSuggestion('${suggestion.name}')">
                <i class="fas fa-search text-muted me-2"></i>
                ${suggestion.name}
            </div>
        `;
    });
    html += '</div>';
    
    suggestionsContainer.innerHTML = html;
    suggestionsContainer.style.display = 'block';
}

function hideSearchSuggestions() {
    const suggestionsContainer = document.getElementById('search-suggestions');
    if (suggestionsContainer) {
        suggestionsContainer.style.display = 'none';
    }
}

function selectSearchSuggestion(suggestion) {
    const searchInput = document.querySelector('input[name="search"]');
    searchInput.value = suggestion;
    hideSearchSuggestions();
    
    // Submit search
    const searchForm = searchInput.closest('form');
    if (searchForm) {
        searchForm.submit();
    }
}

/**
 * Modal Functions
 */
function initializeModals() {
    // Auto-focus on modal inputs
    document.addEventListener('shown.bs.modal', function(e) {
        const firstInput = e.target.querySelector('input:not([type="hidden"]):not([readonly])');
        if (firstInput) {
            firstInput.focus();
        }
    });
    
    // Clear modal content on hide
    document.addEventListener('hidden.bs.modal', function(e) {
        const forms = e.target.querySelectorAll('form');
        forms.forEach(form => form.reset());
        
        // Clear any error messages
        const alerts = e.target.querySelectorAll('.alert');
        alerts.forEach(alert => alert.remove());
    });
}

/**
 * Form Validation
 */
function initializeFormValidation() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Real-time validation for specific fields
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateEmail(this);
        });
    });
    
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name*="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateBangladeshPhone(this);
        });
    });
}

function validateEmail(input) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const isValid = emailRegex.test(input.value);
    
    updateFieldValidation(input, isValid, 'Please enter a valid email address');
    return isValid;
}

function validateBangladeshPhone(input) {
    const phoneRegex = /^(\+880|880|0)?1[3-9]\d{8}$/;
    const isValid = phoneRegex.test(input.value.replace(/\s|-/g, ''));
    
    updateFieldValidation(input, isValid, 'Please enter a valid Bangladesh phone number');
    return isValid;
}

function updateFieldValidation(input, isValid, errorMessage) {
    const feedback = input.parentNode.querySelector('.invalid-feedback') || 
                    input.nextElementSibling?.classList.contains('invalid-feedback') ? 
                    input.nextElementSibling : null;
    
    if (isValid) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        
        if (feedback) {
            feedback.textContent = errorMessage;
        }
    }
}

/**
 * Live Chat Functions
 */
function initializeLiveChat() {
    // Initialize chat widget
    const chatWidget = document.getElementById('chat-widget');
    if (chatWidget) {
        // Set up chat functionality
        setupChatWidget();
    }
}

function openLiveChat() {
    // Check if user is logged in
    if (!isLoggedIn) {
        // Redirect to login or show guest chat
        showGuestChatModal();
        return;
    }
    
    // Open chat window
    const chatWindow = document.getElementById('chat-window');
    if (chatWindow) {
        chatWindow.style.display = 'block';
        initializeChatSession();
    }
}

function showGuestChatModal() {
    const modalHtml = `
        <div class="modal fade" id="guestChatModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Contact Support</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Choose how you'd like to contact us:</p>
                        <div class="d-grid gap-2">
                            <a href="https://wa.me/${window.businessWhatsApp}?text=Hello, I need help with water purifiers" 
                               class="btn btn-success" target="_blank">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp Chat
                            </a>
                            <a href="tel:${window.businessPhone}" class="btn btn-primary">
                                <i class="fas fa-phone me-2"></i>Call Now
                            </a>
                            <a href="/customer/login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login for Live Chat
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('guestChatModal'));
    modal.show();
    
    // Remove modal from DOM when hidden
    document.getElementById('guestChatModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

/**
 * Notification Functions
 */
function showToast(title, message, type = 'info') {
    const toastId = 'toast-' + Date.now();
    const typeClass = type === 'success' ? 'bg-success' : 
                     type === 'error' ? 'bg-danger' : 
                     type === 'warning' ? 'bg-warning' : 'bg-primary';
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${typeClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: type === 'error' ? 5000 : 3000
    });
    
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

function showNotification(title, message, type = 'info') {
    // Show browser notification if permission granted
    if (Notification.permission === 'granted') {
        new Notification(title, {
            body: message,
            icon: '/assets/images/logo.png',
            badge: '/assets/images/logo.png'
        });
    }
    
    // Also show toast
    showToast(title, message, type);
}

/**
 * Utility Functions
 */
function formatCurrency(amount, currency = 'BDT') {
    return new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(amount).replace('BDT', '৳');
}

function formatDate(date, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    
    return new Intl.DateTimeFormat('en-BD', { ...defaultOptions, ...options })
        .format(new Date(date));
}

function formatDateTime(datetime, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    return new Intl.DateTimeFormat('en-BD', { ...defaultOptions, ...options })
        .format(new Date(datetime));
}

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

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

function getCurrentPage() {
    const path = window.location.pathname;
    const page = path.split('/').pop().replace('.php', '') || 'home';
    return page;
}

function initializePageSpecific(page) {
    switch (page) {
        case 'home':
        case '':
            initializeHomePage();
            break;
        case 'products':
            initializeProductsPage();
            break;
        case 'product-details':
            initializeProductDetailsPage();
            break;
        case 'cart':
            initializeCartPage();
            break;
        case 'checkout':
            initializeCheckoutPage();
            break;
        case 'dashboard':
            initializeDashboardPage();
            break;
    }
}

/**
 * Page-Specific Initializations
 */
function initializeHomePage() {
    // Initialize hero video
    const heroVideo = document.querySelector('.hero-video');
    if (heroVideo) {
        heroVideo.addEventListener('loadeddata', function() {
            this.play();
        });
    }
    
    // Initialize newsletter form
    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[name="email"]').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Subscribing...';
            submitBtn.disabled = true;
            
            fetch('/api/newsletter/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Thank you for subscribing to our newsletter!', 'success');
                    this.reset();
                } else {
                    showToast('Error', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Newsletter subscription error:', error);
                showToast('Error', 'Failed to subscribe. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

function initializeProductsPage() {
    // Initialize product filters
    initializeProductFilters();
    
    // Initialize product sorting
    initializeProductSorting();
    
    // Initialize infinite scroll or pagination
    initializePagination();
}

function initializeProductFilters() {
    const filterForm = document.getElementById('product-filters');
    if (filterForm) {
        const inputs = filterForm.querySelectorAll('input, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', debounce(function() {
                applyProductFilters();
            }, 500));
        });
    }
    
    // Price range slider
    const priceRange = document.getElementById('price-range');
    if (priceRange) {
        priceRange.addEventListener('input', debounce(function() {
            updatePriceDisplay();
            applyProductFilters();
        }, 500));
    }
}

function applyProductFilters() {
    const filterForm = document.getElementById('product-filters');
    if (!filterForm) return;
    
    const formData = new FormData(filterForm);
    const filters = Object.fromEntries(formData.entries());
    
    filterProducts(filters);
}

function initializeProductSorting() {
    const sortSelect = document.getElementById('product-sort');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const filters = getCurrentFilters();
            filters.sort = this.value;
            filterProducts(filters);
        });
    }
}

function getCurrentFilters() {
    const params = new URLSearchParams(window.location.search);
    const filters = {};
    
    for (const [key, value] of params.entries()) {
        filters[key] = value;
    }
    
    return filters;
}

/**
 * Tooltip Initialization
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Analytics and Tracking
 */
function trackEvent(eventName, parameters = {}) {
    // Firebase Analytics
    if (window.FirebaseHelpers) {
        window.FirebaseHelpers.trackEvent(eventName, parameters);
    }
    
    // Custom analytics
    fetch('/api/analytics/track.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event: eventName,
            parameters: parameters,
            timestamp: Date.now(),
            page: window.location.pathname
        })
    })
    .catch(error => {
        console.error('Analytics tracking error:', error);
    });
}

function trackProductView(productId) {
    trackEvent('product_view', {
        product_id: productId,
        page_location: window.location.href
    });
}

function trackAddToCart(productId, quantity = 1) {
    trackEvent('add_to_cart', {
        product_id: productId,
        quantity: quantity
    });
}

function trackPurchase(orderId, value, currency = 'BDT') {
    trackEvent('purchase', {
        transaction_id: orderId,
        value: value,
        currency: currency
    });
}

/**
 * Error Handling
 */
function handleAjaxError(xhr, status, error) {
    console.error('AJAX Error:', status, error);
    
    if (xhr.status === 401) {
        showToast('Error', 'Please login to continue', 'error');
        setTimeout(() => {
            window.location.href = '/customer/login.php';
        }, 2000);
    } else if (xhr.status === 403) {
        showToast('Error', 'Access denied', 'error');
    } else if (xhr.status === 500) {
        showToast('Error', 'Server error. Please try again later.', 'error');
    } else {
        showToast('Error', 'Something went wrong. Please try again.', 'error');
    }
}

// Global AJAX error handler
$(document).ajaxError(function(event, xhr, settings, error) {
    handleAjaxError(xhr, 'error', error);
});

/**
 * Performance Optimization
 */

// Lazy loading for images
function initializeLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

// Preload critical resources
function preloadCriticalResources() {
    const criticalImages = [
        '/assets/images/logo.png',
        '/assets/images/hero-purifier.png'
    ];
    
    criticalImages.forEach(src => {
        const img = new Image();
        img.src = src;
    });
}

/**
 * Accessibility Functions
 */
function initializeAccessibility() {
    // Skip to main content link
    const skipLink = document.getElementById('skip-to-main');
    if (skipLink) {
        skipLink.addEventListener('click', function(e) {
            e.preventDefault();
            const mainContent = document.getElementById('main-content');
            if (mainContent) {
                mainContent.focus();
                mainContent.scrollIntoView();
            }
        });
    }
    
    // Keyboard navigation for modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close any open modals
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                }
            });
        }
    });
}

/**
 * Progressive Web App Features
 */
function initializePWA() {
    // Service worker registration
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('Service Worker registered successfully');
            })
            .catch(error => {
                console.log('Service Worker registration failed');
            });
    }
    
    // Add to home screen prompt
    let deferredPrompt;
    
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // Show install button
        const installBtn = document.getElementById('install-app-btn');
        if (installBtn) {
            installBtn.style.display = 'block';
            installBtn.addEventListener('click', () => {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    }
                    deferredPrompt = null;
                });
            });
        }
    });
}

/**
 * Initialize everything when page loads
 */
window.addEventListener('load', function() {
    // Initialize lazy loading
    initializeLazyLoading();
    
    // Preload critical resources
    preloadCriticalResources();
    
    // Initialize accessibility features
    initializeAccessibility();
    
    // Initialize PWA features
    initializePWA();
    
    // Hide loading screen
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        setTimeout(() => {
            loadingScreen.style.opacity = '0';
            setTimeout(() => {
                loadingScreen.style.display = 'none';
            }, 500);
        }, 1000);
    }
});

// Export functions for global use
window.PureFit = {
    addToCart,
    addToWishlist,
    removeFromWishlist,
    quickView,
    showToast,
    showNotification,
    trackEvent,
    formatCurrency,
    formatDate,
    formatDateTime
};

// Set global business contact info
window.businessPhone = '+880-1700-123456';
window.businessWhatsApp = '8801700123456';
window.businessEmail = 'info@purefitbd.com';