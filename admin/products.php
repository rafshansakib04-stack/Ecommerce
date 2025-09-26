<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_product':
            $result = addProduct($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_product':
            $result = updateProduct($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'delete_product':
            $result = deleteProduct($db, $_POST['id']);
            echo json_encode($result);
            exit();
            
        case 'update_stock':
            $result = updateStock($db, $_POST);
            echo json_encode($result);
            exit();
    }
}

// Get products with pagination and search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($category)) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$products = $db->fetchAll("
    SELECT p.*, c.name as category_name,
           (SELECT SUM(quantity) FROM stock_movements sm WHERE sm.product_id = p.id AND sm.movement_type = 'in') as total_in,
           (SELECT SUM(quantity) FROM stock_movements sm WHERE sm.product_id = p.id AND sm.movement_type = 'out') as total_out
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $whereClause
    ORDER BY p.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

$totalProducts = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM products p 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalProducts / $limit);

// Get categories for filter
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");

function addProduct($db, $data) {
    try {
        $db->beginTransaction();
        
        // Create product
        $productId = $db->insert('products', [
            'name' => $data['name'],
            'description' => $data['description'],
            'category_id' => $data['category_id'],
            'sku' => $data['sku'],
            'price' => $data['price'],
            'cost_price' => $data['cost_price'],
            'stock_quantity' => $data['stock_quantity'],
            'min_stock_level' => $data['min_stock_level'],
            'unit' => $data['unit'],
            'status' => 'active'
        ]);
        
        // Add initial stock movement
        if ($data['stock_quantity'] > 0) {
            $db->insert('stock_movements', [
                'product_id' => $productId,
                'movement_type' => 'in',
                'quantity' => $data['stock_quantity'],
                'reference_type' => 'adjustment',
                'notes' => 'Initial stock',
                'created_by' => $_SESSION['user_id']
            ]);
        }
        
        logActivity($_SESSION['user_id'], 'product_added', "Added product: " . $data['name']);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Product added successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error adding product: ' . $e->getMessage()];
    }
}

function updateProduct($db, $data) {
    try {
        $db->update('products', [
            'name' => $data['name'],
            'description' => $data['description'],
            'category_id' => $data['category_id'],
            'sku' => $data['sku'],
            'price' => $data['price'],
            'cost_price' => $data['cost_price'],
            'min_stock_level' => $data['min_stock_level'],
            'unit' => $data['unit'],
            'status' => $data['status']
        ], 'id = ?', [$data['id']]);
        
        logActivity($_SESSION['user_id'], 'product_updated', "Updated product ID: " . $data['id']);
        
        return ['success' => true, 'message' => 'Product updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating product: ' . $e->getMessage()];
    }
}

function deleteProduct($db, $productId) {
    try {
        // Check if product is used in any orders
        $orderCount = $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM sales_order_items 
            WHERE product_id = ?
        ", [$productId])['count'];
        
        if ($orderCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete product that is used in orders'];
        }
        
        $db->delete('products', 'id = ?', [$productId]);
        
        logActivity($_SESSION['user_id'], 'product_deleted', "Deleted product ID: " . $productId);
        
        return ['success' => true, 'message' => 'Product deleted successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting product: ' . $e->getMessage()];
    }
}

function updateStock($db, $data) {
    try {
        $db->beginTransaction();
        
        $productId = $data['product_id'];
        $quantity = $data['quantity'];
        $movementType = $data['movement_type'];
        $notes = $data['notes'] ?? '';
        
        // Get current stock
        $product = $db->fetchOne('SELECT stock_quantity FROM products WHERE id = ?', [$productId]);
        $currentStock = $product['stock_quantity'];
        
        // Calculate new stock
        $newStock = $movementType === 'in' ? $currentStock + $quantity : $currentStock - $quantity;
        
        if ($newStock < 0) {
            return ['success' => false, 'message' => 'Insufficient stock for this operation'];
        }
        
        // Update product stock
        $db->update('products', ['stock_quantity' => $newStock], 'id = ?', [$productId]);
        
        // Add stock movement record
        $db->insert('stock_movements', [
            'product_id' => $productId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'reference_type' => 'adjustment',
            'notes' => $notes,
            'created_by' => $_SESSION['user_id']
        ]);
        
        logActivity($_SESSION['user_id'], 'stock_updated', "Updated stock for product ID: $productId");
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Stock updated successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error updating stock: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tint me-2"></i>Water Purifier ERP
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-box me-2"></i>Product Management
                        </h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-1"></i>Add Product
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-primary" onclick="exportProducts()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-warning" onclick="showLowStock()">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Low Stock
                                </button>
                            </div>
                        </div>

                        <!-- Products Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <?php if ($product['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td>
                                            <div>
                                                <strong>₹<?php echo number_format($product['price'], 2); ?></strong>
                                                <?php if ($product['cost_price']): ?>
                                                <br><small class="text-muted">Cost: ₹<?php echo number_format($product['cost_price'], 2); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-<?php echo $product['stock_quantity'] <= $product['min_stock_level'] ? 'danger' : 'success'; ?>">
                                                    <?php echo $product['stock_quantity']; ?> <?php echo $product['unit']; ?>
                                                </span>
                                                <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                                <i class="fas fa-exclamation-triangle text-warning ms-2" title="Low Stock"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($product['status']); ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewProduct(<?php echo $product['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editProduct(<?php echo $product['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="updateStock(<?php echo $product['id']; ?>)" title="Update Stock">
                                                    <i class="fas fa-warehouse"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Product pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addProductForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="product_name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="product_name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="product_sku" class="form-label">SKU *</label>
                                <input type="text" class="form-control" id="product_sku" name="sku" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="product_description" class="form-label">Description</label>
                            <textarea class="form-control" id="product_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="product_category" class="form-label">Category *</label>
                                <select class="form-select" id="product_category" name="category_id" required>
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="product_unit" class="form-label">Unit</label>
                                <select class="form-select" id="product_unit" name="unit">
                                    <option value="piece">Piece</option>
                                    <option value="kg">Kilogram</option>
                                    <option value="liter">Liter</option>
                                    <option value="box">Box</option>
                                    <option value="set">Set</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="product_price" class="form-label">Selling Price (₹) *</label>
                                <input type="number" class="form-control" id="product_price" name="price" step="0.01" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="product_cost" class="form-label">Cost Price (₹)</label>
                                <input type="number" class="form-control" id="product_cost" name="cost_price" step="0.01">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="product_stock" class="form-label">Initial Stock</label>
                                <input type="number" class="form-control" id="product_stock" name="stock_quantity" value="0" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="min_stock" class="form-label">Minimum Stock Level</label>
                                <input type="number" class="form-control" id="min_stock" name="min_stock_level" value="5" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="product_status" class="form-label">Status</label>
                                <select class="form-select" id="product_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div class="modal fade" id="updateStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-warehouse me-2"></i>Update Stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateStockForm">
                    <input type="hidden" id="stock_product_id" name="product_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="stock_movement" class="form-label">Movement Type</label>
                            <select class="form-select" id="stock_movement" name="movement_type" required>
                                <option value="in">Stock In (+)</option>
                                <option value="out">Stock Out (-)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="stock_quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="stock_quantity" name="quantity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="stock_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="stock_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/products.js"></script>
</body>
</html>