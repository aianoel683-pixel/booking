<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin','manager'])) { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Room Categories';
$db = db();

$success = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  if ($action === 'add_category') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = $_POST['base_price'] ?? '';
    $max_occupancy = (int)($_POST['max_occupancy'] ?? 1);
    $amenities = trim($_POST['amenities'] ?? '');
    if ($name && $base_price !== '') {
      try {
        $stmt = $db->prepare("INSERT INTO room_categories (name, description, base_price, max_occupancy, amenities) VALUES (:name, :description, :base_price, :max_occupancy, :amenities)");
        $stmt->execute([':name'=>$name, ':description'=>$description, ':base_price'=>$base_price, ':max_occupancy'=>$max_occupancy, ':amenities'=>$amenities]);
        $success = 'Category added successfully!';
      } catch (Exception $e) { $error = 'Error adding category.'; }
    } else { $error = 'Please fill required fields.'; }
  }
  elseif ($action === 'update_category') {
    $id = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = $_POST['base_price'] ?? '';
    $max_occupancy = (int)($_POST['max_occupancy'] ?? 1);
    $amenities = trim($_POST['amenities'] ?? '');
    if ($id && $name && $base_price !== '') {
      try {
        $stmt = $db->prepare("UPDATE room_categories SET name=:name, description=:description, base_price=:base_price, max_occupancy=:max_occupancy, amenities=:amenities WHERE id=:id");
        $stmt->execute([':id'=>$id, ':name'=>$name, ':description'=>$description, ':base_price'=>$base_price, ':max_occupancy'=>$max_occupancy, ':amenities'=>$amenities]);
        $success = 'Category updated successfully!';
      } catch (Exception $e) { $error = 'Error updating category.'; }
    } else { $error = 'Please fill required fields.'; }
  }
  elseif ($action === 'delete_category') {
    $id = (int)($_POST['category_id'] ?? 0);
    if ($id) {
      try {
        $c = $db->prepare('SELECT COUNT(*) FROM rooms WHERE category_id=:id'); $c->execute([':id'=>$id]);
        if ((int)$c->fetchColumn() > 0) { $error = 'Cannot delete: category in use.'; }
        else { $db->prepare('DELETE FROM room_categories WHERE id=:id')->execute([':id'=>$id]); $success = 'Category deleted.'; }
      } catch (Exception $e) { $error = 'Error deleting category.'; }
    }
  }
}

$categories = $db->query('SELECT * FROM room_categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

include '../includes/admin_header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <?php echo $error; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="card admin-card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="card-title mb-0">Add Category</h6>
    <a href="rooms.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-door-closed me-2"></i>Back to Rooms</a>
  </div>
  <form method="POST">
    <div class="card-body">
      <input type="hidden" name="action" value="add_category">
      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Base Price</label>
            <input type="number" step="0.01" class="form-control" name="base_price" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Max Occupancy</label>
            <input type="number" min="1" class="form-control" name="max_occupancy" value="1" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label">Amenities</label>
            <textarea class="form-control" name="amenities" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="4"></textarea>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <button type="submit" class="btn btn-primary">Add Category</button>
    </div>
  </form>
</div>

<div class="card admin-card mt-4">
  <div class="card-header">
    <h6 class="card-title mb-0">All Categories</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover table-modern">
        <thead>
          <tr>
            <th>Name</th>
            <th>Base Price</th>
            <th>Max Occupancy</th>
            <th>Amenities</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
            <td>₱<?php echo number_format($cat['base_price'], 2); ?></td>
            <td><?php echo (int)$cat['max_occupancy']; ?></td>
            <td><span class="text-muted"><?php echo htmlspecialchars($cat['amenities']); ?></span></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                        data-category-id="<?php echo $cat['id']; ?>"
                        data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                        data-description="<?php echo htmlspecialchars($cat['description']); ?>"
                        data-base-price="<?php echo $cat['base_price']; ?>"
                        data-max-occupancy="<?php echo (int)$cat['max_occupancy']; ?>"
                        data-amenities="<?php echo htmlspecialchars($cat['amenities']); ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="delete_category">
                  <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                  <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete this category?');">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($categories)): ?>
          <tr><td colspan="5" class="text-muted">No categories found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="update_category">
          <input type="hidden" name="category_id" id="edit_cat_id">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="name" id="edit_cat_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Base Price</label>
            <input type="number" step="0.01" class="form-control" name="base_price" id="edit_cat_price" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Max Occupancy</label>
            <input type="number" min="1" class="form-control" name="max_occupancy" id="edit_cat_occupancy" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Amenities</label>
            <textarea class="form-control" name="amenities" id="edit_cat_amenities" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" id="edit_cat_desc" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var editCatModal = document.getElementById('editCategoryModal');
  if (editCatModal) {
    editCatModal.addEventListener('show.bs.modal', function(event){
      var btn = event.relatedTarget;
      document.getElementById('edit_cat_id').value = btn.getAttribute('data-category-id');
      document.getElementById('edit_cat_name').value = btn.getAttribute('data-name');
      document.getElementById('edit_cat_price').value = btn.getAttribute('data-base-price');
      document.getElementById('edit_cat_occupancy').value = btn.getAttribute('data-max-occupancy');
      document.getElementById('edit_cat_amenities').value = btn.getAttribute('data-amenities');
      document.getElementById('edit_cat_desc').value = btn.getAttribute('data-description');
    });
  }
});
</script>

<?php include '../includes/admin_footer.php'; ?>

