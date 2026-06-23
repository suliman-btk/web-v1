<?php
/**
 * Admin: create or edit a product (the "C" + "U" of CRUD).
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$categories = db_all('SELECT * FROM categories ORDER BY name');
$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

// Defaults / load existing
$p = [
    'name' => '', 'brand' => '', 'category_id' => $categories[0]['category_id'] ?? 1,
    'description' => '', 'price' => '', 'discount_price' => '', 'stock_quantity' => 0,
    'image_path' => '', 'is_featured' => 0, 'status' => 'active',
];
if ($isEdit) {
    $found = db_one('SELECT * FROM products WHERE product_id = ?', [$id]);
    if (!$found) { set_flash('error', 'Product not found.'); redirect('admin/products.php'); }
    $p = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch. Please try again.';
    }
    foreach (['name','brand','description','image_path','status'] as $f) $p[$f] = trim($_POST[$f] ?? '');
    $p['category_id']    = (int) ($_POST['category_id'] ?? 0);
    $p['price']          = $_POST['price'] ?? '';
    $p['discount_price'] = $_POST['discount_price'] ?? '';
    $p['stock_quantity'] = (int) ($_POST['stock_quantity'] ?? 0);
    $p['is_featured']    = isset($_POST['is_featured']) ? 1 : 0;

    if ($p['name'] === '' || mb_strlen($p['name']) < 2) $errors['name'] = 'Product name is required.';
    if (!db_one('SELECT category_id FROM categories WHERE category_id = ?', [$p['category_id']])) $errors['category_id'] = 'Choose a valid category.';
    if (!is_numeric($p['price']) || (float) $p['price'] <= 0) $errors['price'] = 'Enter a valid price greater than 0.';
    if ($p['discount_price'] !== '' && (!is_numeric($p['discount_price']) || (float) $p['discount_price'] <= 0))
        $errors['discount_price'] = 'Discount price must be a positive number.';
    elseif ($p['discount_price'] !== '' && (float) $p['discount_price'] >= (float) $p['price'])
        $errors['discount_price'] = 'Discount price must be lower than the normal price.';
    if ($p['stock_quantity'] < 0) $errors['stock_quantity'] = 'Stock cannot be negative.';
    if (!in_array($p['status'], ['active','inactive'], true)) $p['status'] = 'active';

    // Image upload (takes priority over the typed path when a file is provided)
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = upload_product_image($_FILES['image_file']);
        if ($up['error']) {
            $errors['image_file'] = $up['error'];
        } elseif ($up['path']) {
            $p['image_path'] = $up['path'];
        }
    }

    if (!$errors) {
        $discount = $p['discount_price'] === '' ? null : (float) $p['discount_price'];
        $image = $p['image_path'] !== '' ? $p['image_path'] : null;
        if ($isEdit) {
            db()->prepare(
                'UPDATE products SET name=?, brand=?, category_id=?, description=?, price=?,
                    discount_price=?, stock_quantity=?, image_path=?, is_featured=?, status=?
                 WHERE product_id=?'
            )->execute([
                $p['name'], $p['brand'], $p['category_id'], $p['description'], (float) $p['price'],
                $discount, $p['stock_quantity'], $image, $p['is_featured'], $p['status'], $id,
            ]);
            set_flash('success', 'Product updated successfully.');
        } else {
            db()->prepare(
                'INSERT INTO products (name, brand, category_id, description, price,
                    discount_price, stock_quantity, image_path, is_featured, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $p['name'], $p['brand'], $p['category_id'], $p['description'], (float) $p['price'],
                $discount, $p['stock_quantity'], $image, $p['is_featured'], $p['status'],
            ]);
            set_flash('success', 'Product added successfully.');
        }
        redirect('admin/products.php');
    }
}

$page_title = $isEdit ? 'Edit Product' : 'Add Product';
$heading = $isEdit ? 'Edit Product' : 'Add New Product';
require __DIR__ . '/../includes/admin_header.php';
?>
<p><a href="<?= e(url('admin/products.php')) ?>">← Back to Products</a></p>

<div class="panel" style="max-width:720px">
    <?php if (!empty($errors['general'])): ?>
        <div class="flash flash-error"><?= e($errors['general']) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('admin/product_form.php' . ($isEdit ? '?id=' . $id : ''))) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
            <label for="name">Product Name</label>
            <input type="text" id="name" name="name" value="<?= e($p['name']) ?>">
            <span class="error-msg"><?= e($errors['name'] ?? '') ?></span>
        </div>
        <div class="grid-2">
            <div class="field">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" value="<?= e($p['brand']) ?>">
            </div>
            <div class="field <?= isset($errors['category_id']) ? 'has-error' : '' ?>">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>" <?= (int) $p['category_id'] === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="error-msg"><?= e($errors['category_id'] ?? '') ?></span>
            </div>
        </div>
        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= e($p['description']) ?></textarea>
        </div>
        <div class="grid-2">
            <div class="field <?= isset($errors['price']) ? 'has-error' : '' ?>">
                <label for="price">Price (RM)</label>
                <input type="number" step="0.01" min="0" id="price" name="price" value="<?= e($p['price']) ?>">
                <span class="error-msg"><?= e($errors['price'] ?? '') ?></span>
            </div>
            <div class="field <?= isset($errors['discount_price']) ? 'has-error' : '' ?>">
                <label for="discount_price">Discount Price (optional)</label>
                <input type="number" step="0.01" min="0" id="discount_price" name="discount_price" value="<?= e($p['discount_price']) ?>">
                <span class="error-msg"><?= e($errors['discount_price'] ?? '') ?></span>
            </div>
        </div>
        <div class="grid-2">
            <div class="field <?= isset($errors['stock_quantity']) ? 'has-error' : '' ?>">
                <label for="stock_quantity">Stock Quantity</label>
                <input type="number" min="0" id="stock_quantity" name="stock_quantity" value="<?= e($p['stock_quantity']) ?>">
                <span class="error-msg"><?= e($errors['stock_quantity'] ?? '') ?></span>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active"   <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="field <?= isset($errors['image_file']) ? 'has-error' : '' ?>">
            <label for="image_file">Product Image</label>
            <?php if (!empty($p['image_path'])): ?>
                <div style="margin-bottom:8px"><img src="<?= e(url($p['image_path'])) ?>" alt="" style="max-height:80px;border:1px solid var(--line);border-radius:6px"></div>
            <?php endif; ?>
            <input type="file" id="image_file" name="image_file" accept="image/png,image/jpeg,image/webp,image/gif">
            <span class="error-msg"><?= e($errors['image_file'] ?? '') ?></span>
            <div class="hint">Upload JPG, PNG, WEBP or GIF (max 3 MB).<?= $isEdit ? ' Leave empty to keep the current image.' : '' ?></div>
        </div>
        <div class="field">
            <label for="image_path">…or Image Path (optional)</label>
            <input type="text" id="image_path" name="image_path" value="<?= e($p['image_path']) ?>"
                   placeholder="assets/images/products/laptops.svg">
            <div class="hint">Used only when no file is uploaded. Leave blank for a default placeholder.</div>
        </div>
        <div class="field">
            <label style="font-weight:400;display:flex;gap:8px;align-items:center">
                <input type="checkbox" name="is_featured" value="1" style="width:auto" <?= $p['is_featured'] ? 'checked' : '' ?>>
                Show on homepage as a featured product
            </label>
        </div>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Product' : 'Add Product' ?></button>
        <a href="<?= e(url('admin/products.php')) ?>" class="btn btn-ghost">Cancel</a>
    </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
