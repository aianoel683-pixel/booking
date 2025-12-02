<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Check-Out';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_out_booking') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    if ($booking_id > 0) {
        $stmt = $db->prepare("UPDATE bookings SET status='checked_out' WHERE id=:id");
        $stmt->execute([':id' => $booking_id]);
        $stmt = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id=b.room_id SET r.status='available', r.housekeeping_status='dirty' WHERE b.id=:id");
        $stmt->execute([':id' => $booking_id]);
        $success = 'Guest checked out.';
    }
}

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT b.*, g.first_name, g.last_name, r.room_number FROM bookings b JOIN guests g ON b.guest_id=g.id JOIN rooms r ON b.room_id=r.id WHERE b.check_out_date=:d AND b.status='checked_in' ORDER BY b.check_out_date ASC");
$stmt->execute([':d' => $today]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card admin-card">
  <div class="card-header"><h6 class="card-title mb-0">Today's Check-outs</h6></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover table-modern">
        <thead>
          <tr>
            <th>Booking</th>
            <th>Guest</th>
            <th>Room</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
          <tr>
            <td>#<?php echo $b['id']; ?></td>
            <td><?php echo $b['first_name'].' '.$b['last_name']; ?></td>
            <td><?php echo $b['room_number']; ?></td>
            <td><?php echo date('M d, Y', strtotime($b['check_in_date'])); ?></td>
            <td><?php echo date('M d, Y', strtotime($b['check_out_date'])); ?></td>
            <td>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="check_out_booking">
                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                <button type="submit" class="btn btn-sm btn-danger">
                  <i class="bi bi-box-arrow-right me-1"></i>Check Out
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($bookings)): ?>
          <tr><td colspan="6" class="text-muted">No check-outs scheduled for today.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include '../includes/footer.php'; ?>
