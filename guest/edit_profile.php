<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['registered_guest_id'])) {
    header('Location: ../landing.php');
    exit();
}

$db = db();
$gid = (int)$_SESSION['registered_guest_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $photo_url = null;
            // Handle File Upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/guest_photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileTmpPath = $_FILES['photo']['tmp_name'];
                $fileName = $_FILES['photo']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $dest_path = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $photo_url = 'uploads/guest_photos/' . $newFileName;
                    } else {
                        throw new Exception('Error moving uploaded file.');
                    }
                } else {
                    throw new Exception('Invalid file type. Allowed: jpg, gif, png, jpeg.');
                }
            }

            // Update Database
            $sql = "UPDATE guests SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?";
            $params = [$first_name, $last_name, $email, $phone, $address];

            if ($photo_url) {
                $sql .= ", id_photo_url = ?";
                $params[] = $photo_url;
            }

            $sql .= " WHERE id = ?";
            $params[] = $gid;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $_SESSION['registered_guest_name'] = $first_name . ' ' . $last_name;
            $message = 'Profile updated successfully!';
        } catch (Exception $e) {
            $error = $e->getMessage(); // Show specific error
        }
    }
}

$stmt = $db->prepare('SELECT * FROM guests WHERE id = :id');
$stmt->execute([':id' => $gid]);
$guest = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Hotel Info for header
$hotel = null;
try {
    $hotel = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-color: #d4af37;
            --accent-hover: #b39020;
            --bg-light: #f8f9fa;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--primary-color);
        }
        .brand-font { font-family: 'Playfair Display', serif; }
        .btn-gold {
            background-color: var(--accent-color);
            color: white;
            border: none;
        }
        .btn-gold:hover {
            background-color: var(--accent-hover);
            color: white;
        }
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand brand-font" href="dashboard.php">
                <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel System'); ?>
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-5">
                        <h3 class="fw-bold brand-font mb-4 text-center">Edit Profile</h3>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-12 text-center mb-3">
                                    <?php if (!empty($guest['id_photo_url'])): ?>
                                        <div class="mb-2">
                                            <img src="../<?php echo htmlspecialchars($guest['id_photo_url']); ?>" alt="Profile Photo" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 100px; height: 100px;">
                                            <i class="bi bi-person fs-1 text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <label for="photo" class="form-label small text-muted">Update Profile Photo</label>
                                        <input type="file" name="photo" id="photo" class="form-control form-control-sm w-50 mx-auto">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($guest['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($guest['last_name']); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($guest['email']); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($guest['phone']); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($guest['address']); ?></textarea>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-gold w-100 py-2">Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
