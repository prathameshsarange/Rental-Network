<?php
require_once __DIR__ . '/../includes/db.php';

require_admin();

$page_title = 'Manage Categories';
$allowedStatuses = ['Active', 'Inactive'];
$errors = [];
$editingCategory = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($action === 'save') {
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';

        if ($categoryName === '' || strlen($categoryName) > 100) {
            $errors[] = 'Enter a category name of up to 100 characters.';
        }
        if ($description === '') {
            $errors[] = 'Enter a category description.';
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = 'Select a valid category status.';
        }

        if (empty($errors)) {
            try {
                if ($categoryId > 0) {
                    $statement = $pdo->prepare('UPDATE categories SET category_name = ?, description = ?, status = ? WHERE category_id = ?');
                    $statement->execute([$categoryName, $description, $status, $categoryId]);
                    set_flash('success', 'Category updated successfully.');
                } else {
                    $statement = $pdo->prepare('INSERT INTO categories (category_name, description, status) VALUES (?, ?, ?)');
                    $statement->execute([$categoryName, $description, $status]);
                    set_flash('success', 'Category added successfully.');
                }
                redirect('admin/categories.php');
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $errors[] = 'A category with this name already exists.';
                } else {
                    $errors[] = 'The category could not be saved. Please try again.';
                }
            }
        }

        $editingCategory = [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'description' => $description,
            'status' => $status,
        ];
    }

    if ($action === 'toggle' && $categoryId > 0) {
        $status = $_POST['status'] ?? '';
        if (in_array($status, $allowedStatuses, true)) {
            $statement = $pdo->prepare('UPDATE categories SET status = ? WHERE category_id = ?');
            $statement->execute([$status, $categoryId]);
            set_flash('success', 'Category status updated.');
        }
        redirect('admin/categories.php');
    }

    if ($action === 'delete' && $categoryId > 0) {
        $itemStatement = $pdo->prepare('SELECT COUNT(*) FROM items WHERE category_id = ?');
        $itemStatement->execute([$categoryId]);

        if ((int) $itemStatement->fetchColumn() > 0) {
            set_flash('warning', 'This category has items assigned to it and cannot be deleted. You can mark it inactive instead.');
        } else {
            $statement = $pdo->prepare('DELETE FROM categories WHERE category_id = ?');
            $statement->execute([$categoryId]);
            set_flash('success', 'Category deleted successfully.');
        }
        redirect('admin/categories.php');
    }
}

if ($editingCategory === null && isset($_GET['edit'])) {
    $categoryId = (int) $_GET['edit'];
    if ($categoryId > 0) {
        $statement = $pdo->prepare('SELECT category_id, category_name, description, status FROM categories WHERE category_id = ?');
        $statement->execute([$categoryId]);
        $editingCategory = $statement->fetch() ?: null;
    }
}

$categoriesStatement = $pdo->query("
    SELECT c.category_id, c.category_name, c.description, c.status, c.created_at, COUNT(i.item_id) AS item_count
    FROM categories c
    LEFT JOIN items i ON i.category_id = c.category_id
    GROUP BY c.category_id, c.category_name, c.description, c.status, c.created_at
    ORDER BY c.category_name
");
$categories = $categoriesStatement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-4">
    <div>
        <h1 class="h3 mb-1">Manage Categories</h1>
        <p class="text-muted mb-0">Create and organize the categories used for item listings.</p>
    </div>
    <a href="<?php echo e(base_url('categories/categories.php')); ?>" class="btn btn-outline-brand">View Categories</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3"><?php echo $editingCategory ? 'Edit Category' : 'Add Category'; ?></h2>

                <?php if (!empty($errors)) : ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error) : ?>
                            <div><?php echo e($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo e(base_url('admin/categories.php')); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="category_id" value="<?php echo e($editingCategory['category_id'] ?? ''); ?>">

                    <div class="mb-3">
                        <label for="category_name" class="form-label required">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" maxlength="100" required value="<?php echo e($editingCategory['category_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label required">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo e($editingCategory['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($allowedStatuses as $status) : ?>
                                <option value="<?php echo e($status); ?>" <?php echo (($editingCategory['status'] ?? 'Active') === $status) ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-brand"><?php echo $editingCategory ? 'Save Changes' : 'Add Category'; ?></button>
                        <?php if ($editingCategory) : ?>
                            <a href="<?php echo e(base_url('admin/categories.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Category</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category) : ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold"><?php echo e($category['category_name']); ?></div>
                                        <div class="small text-muted category-description"><?php echo e($category['description']); ?></div>
                                    </td>
                                    <td><span class="badge text-bg-<?php echo $category['status'] === 'Active' ? 'success' : 'secondary'; ?>"><?php echo e($category['status']); ?></span></td>
                                    <td><?php echo e($category['item_count']); ?></td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                            <a class="btn btn-sm btn-outline-brand" href="<?php echo e(base_url('admin/categories.php?edit=' . $category['category_id'])); ?>">Edit</a>
                                            <form method="post" action="<?php echo e(base_url('admin/categories.php')); ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="category_id" value="<?php echo e($category['category_id']); ?>">
                                                <input type="hidden" name="status" value="<?php echo $category['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $category['status'] === 'Active' ? 'Disable' : 'Enable'; ?></button>
                                            </form>
                                            <?php if ((int) $category['item_count'] === 0) : ?>
                                                <form method="post" action="<?php echo e(base_url('admin/categories.php')); ?>" data-confirm="Delete this empty category?">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="category_id" value="<?php echo e($category['category_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
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
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
