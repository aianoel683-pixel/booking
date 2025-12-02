<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Bookings';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $guest_id = (int)($_POST['guest_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $check_in_date = $_POST['check_in_date'] ?? '';
    $check_out_date = $_POST['check_out_date'] ?? '';
    $adults = (int)($_POST['adults'] ?? 1);
    $children = (int)($_POST['children'] ?? 0);
    $special_requests = trim($_POST['special_requests'] ?? '');
    if ($guest_id && $room_id && $check_in_date && $check_out_date) {
        $s = $db->prepare("SELECT rc.base_price FROM rooms r JOIN room_categories rc ON r.category_id=rc.id WHERE r.id=:room_id");
        $s->execute([':room_id' => $room_id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $nights = max(1, (strtotime($check_out_date) - strtotime($check_in_date)) / (60*60*24));
        $total_amount = ((float)$row['base_price']) * $nights;
        $stmt = $db->prepare("INSERT INTO bookings (guest_id, room_id, check_in_date, check_out_date, adults, children, total_amount, special_requests, created_by) VALUES (:guest_id,:room_id,:ci,:co,:ad,:ch,:ta,:sr,:cb)");
        $stmt->execute([':guest_id'=>$guest_id, ':room_id'=>$room_id, ':ci'=>$check_in_date, ':co'=>$check_out_date, ':ad'=>$adults, ':ch'=>$children, ':ta'=>$total_amount, ':sr'=>$special_requests, ':cb'=>$_SESSION['user_id']]);
        $u = $db->prepare("UPDATE rooms SET status='reserved' WHERE id=:id");
        $u->execute([':id' => $room_id]);
        $success = 'Booking created.';
    }
}

$bookings = $db->query("SELECT b.*, g.first_name, g.last_name, r.room_number, rc.name AS category_name FROM bookings b JOIN guests g ON b.guest_id=g.id JOIN rooms r ON b.room_id=r.id JOIN room_categories rc ON r.category_id=rc.id ORDER BY b.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$available_rooms = $db->query("SELECT r.*, rc.name AS category_name, rc.base_price FROM rooms r JOIN room_categories rc ON r.category_id=rc.id WHERE r.status='available' ORDER BY r.room_number")->fetchAll(PDO::FETCH_ASSOC);
$guests = $db->query("SELECT * FROM guests ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4>Bookings</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBookingModal">
    <i class="bi bi-calendar-plus me-2"></i>Create Booking
  </button>
</div>

<div class="card admin-card">
  <div class="card-header"><h6 class="card-title mb-0">All Bookings</h6></div>
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
            <th>Nights</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): $n=((strtotime($b['check_out_date'])-strtotime($b['check_in_date']))/(60*60*24)); ?>
          <tr>
            <td>#<?php echo $b['id']; ?></td>
            <td><?php echo $b['first_name'].' '.$b['last_name']; ?></td>
            <td>
              <div><?php echo $b['room_number']; ?></div>
              <small class="text-muted"><?php echo $b['category_name']; ?></small>
            </td>
            <td><?php echo date('M d, Y', strtotime($b['check_in_date'])); ?></td>
            <td><?php echo date('M d, Y', strtotime($b['check_out_date'])); ?></td>
            <td><?php echo $n; ?></td>
            <td>₱<?php echo number_format($b['total_amount'],2); ?></td>
            <td>
              <span class="badge bg-<?php echo ($b['status']==='confirmed'?'warning':($b['status']==='checked_in'?'success':($b['status']==='checked_out'?'info':($b['status']==='cancelled'?'danger':'secondary')))); ?>">
                <?php echo ucfirst($b['status']); ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="createBookingModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Booking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="create_booking">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Guest</label>
              <select class="form-select" name="guest_id" required>
                <option value="">Select Guest</option>
                <?php foreach ($guests as $g): ?>
                <option value="<?php echo $g['id']; ?>"><?php echo $g['first_name'].' '.$g['last_name']; ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted"><a href="guests.php">Add guest</a></small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Room</label>
              <select class="form-select" id="room_id" name="room_id" required>
                <option value="">Select Room</option>
                <?php foreach ($available_rooms as $r): ?>
                <option value="<?php echo $r['id']; ?>" data-price="<?php echo $r['base_price']; ?>"><?php echo $r['room_number'].' - '.$r['category_name']; ?> (₱<?php echo number_format($r['base_price'],2); ?>/night)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Check-in</label>
              <input type="date" class="form-control" id="check_in_date" name="check_in_date" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Check-out</label>
              <input type="date" class="form-control" id="check_out_date" name="check_out_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Adults</label>
              <input type="number" class="form-control" name="adults" min="1" max="10" value="1">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Children</label>
              <input type="number" class="form-control" name="children" min="0" max="10" value="0">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Special Requests</label>
            <textarea class="form-control" name="special_requests" rows="3"></textarea>
          </div>
          <div class="alert alert-info">
            <strong>Estimated Total: </strong><span id="estimated_total">₱0.00</span> (<span id="nights_count">0</span> nights)
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function calculateTotal(){
  const roomSelect=document.getElementById('room_id');
  const ci=new Date(document.getElementById('check_in_date').value);
  const co=new Date(document.getElementById('check_out_date').value);
  if(roomSelect && roomSelect.value && ci && co && co>ci){
    const price=parseFloat(roomSelect.options[roomSelect.selectedIndex].getAttribute('data-price'));
    const nights=Math.ceil((co-ci)/(1000*60*60*24));
    const total=price*nights;
    document.getElementById('estimated_total').textContent='₱'+total.toFixed(2);
    document.getElementById('nights_count').textContent=nights;
  }else{
    document.getElementById('estimated_total').textContent='₱0.00';
    document.getElementById('nights_count').textContent='0';
  }
}
['room_id','check_in_date','check_out_date'].forEach(function(id){var el=document.getElementById(id); if(el){el.addEventListener('change',calculateTotal);}});
</script>

<?php include '../includes/footer.php'; ?>
