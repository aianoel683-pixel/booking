<?php
session_start();
require_once '../config/database.php';

// Check if user is admin/manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "User Management";
$db = db();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_user') {
        $username = trim($_POST['username']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $role = $_POST['role'];
        
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) 
                                  VALUES (:username, :password, :email, :first_name, :last_name, :role)");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            
            $success = "User added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding user: " . $e->getMessage();
        }
    }
    elseif ($action == 'update_user') {
        $user_id = $_POST['user_id'];
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        try {
            $stmt = $db->prepare("UPDATE users SET email = :email, first_name = :first_name, 
                                  last_name = :last_name, role = :role, status = :status WHERE id = :id");
            $stmt->bindParam(':id', $user_id);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            $success = "User updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating user: " . $e->getMessage();
        }
    }
    elseif ($action == 'delete_user') {
        $user_id = $_POST['user_id'];
        
        try {
            // Prevent deleting own account
            if ($user_id == $_SESSION['user_id']) {
                $error = "Cannot delete your own account!";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
                
                $success = "User deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY role, first_name, last_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>User Management</h4>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus me-2"></i>Add New User
    </button>
</div>

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
    <div class="card-header">
        <h6 class="card-title mb-0">System Users</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-modern">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                        <td><?php echo $user['username']; ?></td>
                        <td><?php echo $user['email']; ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($user['role']) {
                                    case 'admin': echo 'danger'; break;
                                    case 'manager': echo 'warning'; break;
                                    case 'front_desk': echo 'primary'; break;
                                    case 'housekeeping': echo 'info'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" 
                                        data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                        data-user-id="<?php echo $user['id']; ?>"
                                        data-username="<?php echo $user['username']; ?>"
                                        data-email="<?php echo $user['email']; ?>"
                                        data-first-name="<?php echo $user['first_name']; ?>"
                                        data-last-name="<?php echo $user['last_name']; ?>"
                                        data-role="<?php echo $user['role']; ?>"
                                        data-status="<?php echo $user['status']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger" 
                                            onclick="return confirm('Are you sure you want to delete this user?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="manager">Manager</option>
                                <option value="front_desk">Front Desk</option>
                                <option value="housekeeping">Housekeeping</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" readonly>
                        <small class="text-muted">Username cannot be changed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="admin">Administrator</option>
                                <option value="manager">Manager</option>
                                <option value="front_desk">Front Desk</option>
                                <option value="housekeeping">Housekeeping</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Handle edit modal data
    document.addEventListener('DOMContentLoaded', function() {
        const editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('edit_user_id').value = button.getAttribute('data-user-id');
            document.getElementById('edit_username').value = button.getAttribute('data-username');
            document.getElementById('edit_email').value = button.getAttribute('data-email');
            document.getElementById('edit_first_name').value = button.getAttribute('data-first-name');
            document.getElementById('edit_last_name').value = button.getAttribute('data-last-name');
            document.getElementById('edit_role').value = button.getAttribute('data-role');
            document.getElementById('edit_status').value = button.getAttribute('data-status');
        });
    });
</script>

<?php include '../includes/admin_footer.php'; ?>
