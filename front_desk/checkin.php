<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Check-In';
$db = db();

function get_default_housekeeper_id($db) {
    try {
        $row = $db->query("SELECT id FROM users WHERE role='housekeeping' AND status='active' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) return (int)$row['id'];
    } catch (Exception $e) {}
    return (int)($_SESSION['user_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'walk_in_check_in') {
    $guest_mode = $_POST['guest_mode'] ?? 'existing';
    $guest_id = 0;
    if ($guest_mode === 'existing') {
        $guest_id = (int)($_POST['guest_id'] ?? 0);
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($first_name && $last_name) {
            $stmt = $db->prepare("INSERT INTO guests (first_name,last_name,email,phone) VALUES (:fn,:ln,:em,:ph)");
            $stmt->execute([':fn'=>$first_name, ':ln'=>$last_name, ':em'=>$email, ':ph'=>$phone]);
            $guest_id = (int)$db->lastInsertId();
        }
    }
    $room_id = (int)($_POST['room_id'] ?? 0);
    $check_in_date = $_POST['check_in_date'] ?? '';
    $check_out_date = $_POST['check_out_date'] ?? '';
    $adults = (int)($_POST['adults'] ?? 1);
    $children = (int)($_POST['children'] ?? 0);
    $special_requests = trim($_POST['special_requests'] ?? '');
    if ($guest_id && $room_id && $check_in_date && $check_out_date) {
        $s = $db->prepare("SELECT rc.base_price FROM rooms r JOIN room_categories rc ON r.category_id=rc.id WHERE r.id=:room_id AND r.status='available'");
        $s->execute([':room_id' => $room_id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $nights = max(1, (strtotime($check_out_date) - strtotime($check_in_date)) / (60*60*24));
            $total_amount = ((float)$row['base_price']) * $nights;
            $stmt = $db->prepare("INSERT INTO bookings (guest_id, room_id, check_in_date, check_out_date, adults, children, total_amount, special_requests, created_by) VALUES (:guest_id,:room_id,:ci,:co,:ad,:ch,:ta,:sr,:cb)");
            $stmt->execute([':guest_id'=>$guest_id, ':room_id'=>$room_id, ':ci'=>$check_in_date, ':co'=>$check_out_date, ':ad'=>$adults, ':ch'=>$children, ':ta'=>$total_amount, ':sr'=>$special_requests, ':cb'=>$_SESSION['user_id']]);
            $bid = (int)$db->lastInsertId();
            if ($bid) {
                $u = $db->prepare("UPDATE bookings SET status='checked_in' WHERE id=:id");
                $u->execute([':id' => $bid]);
                $u2 = $db->prepare("UPDATE rooms SET status='occupied' WHERE id=:id");
                $u2->execute([':id' => $room_id]);
                try {
                    $hk = get_default_housekeeper_id($db);
                    $desc = 'Guest checked in (Booking #' . $bid . '). Please inspect and assist as needed.';
                    $ins = $db->prepare("INSERT INTO housekeeping_tasks (room_id, assigned_to, task_type, status, priority, description) VALUES (:room_id, :assigned_to, 'inspection', 'pending', 'medium', :description)");
                    $ins->execute([':room_id' => $room_id, ':assigned_to' => $hk, ':description' => $desc]);
                } catch (Exception $e) { }
                $success = 'Walk-in check-in completed.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'qr_check_in') {
    $guest_id = (int)($_POST['guest_id'] ?? 0);
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $booking = null;

    if ($booking_id > 0) {
        // Check-in by Booking ID
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = :id AND status = 'confirmed'");
        $stmt->execute([':id' => $booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Optional: strict date check
        // if ($booking && $booking['check_in_date'] !== date('Y-m-d')) { $booking = null; $error = 'Booking is not for today.'; }
    } elseif ($guest_id > 0) {
        // Check-in by Guest ID (find today's booking)
        $d = date('Y-m-d');
        $s = $db->prepare("SELECT b.* FROM bookings b WHERE b.guest_id=:gid AND b.check_in_date=:d AND b.status='confirmed' ORDER BY b.created_at DESC LIMIT 1");
        $s->execute([':gid' => $guest_id, ':d' => $d]);
        $booking = $s->fetch(PDO::FETCH_ASSOC);
    }

    if ($booking) {
        $stmt = $db->prepare("UPDATE bookings SET status='checked_in' WHERE id=:id");
        $stmt->execute([':id' => $booking['id']]);
        $stmt = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id=b.room_id SET r.status='occupied' WHERE b.id=:id");
        $stmt->execute([':id' => $booking['id']]);
        try {
            $hk = get_default_housekeeper_id($db);
            $desc = 'Guest checked in (Booking #' . $booking['id'] . '). Please inspect and assist as needed.';
            $ins = $db->prepare("INSERT INTO housekeeping_tasks (room_id, assigned_to, task_type, status, priority, description) VALUES (:room_id, :assigned_to, 'inspection', 'pending', 'medium', :description)");
            $ins->execute([':room_id' => $booking['room_id'], ':assigned_to' => $hk, ':description' => $desc]);
        } catch (Exception $e) { }
        $success = 'QR check-in completed for Booking #' . $booking['id'];
    } else {
        // If we failed to find a booking
        // $error = 'No valid confirmed booking found for check-in.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_in_booking') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    if ($booking_id > 0) {
        $stmt = $db->prepare("UPDATE bookings SET status='checked_in' WHERE id=:id");
        $stmt->execute([':id' => $booking_id]);
        $stmt = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id=b.room_id SET r.status='occupied' WHERE b.id=:id");
        $stmt->execute([':id' => $booking_id]);
        try {
            $r = $db->prepare("SELECT room_id FROM bookings WHERE id=:id");
            $r->execute([':id' => $booking_id]);
            $room_id = (int)($r->fetch(PDO::FETCH_ASSOC)['room_id'] ?? 0);
            if ($room_id) {
                $hk = get_default_housekeeper_id($db);
                $desc = 'Guest checked in (Booking #' . $booking_id . '). Please inspect and assist as needed.';
                $ins = $db->prepare("INSERT INTO housekeeping_tasks (room_id, assigned_to, task_type, status, priority, description) VALUES (:room_id, :assigned_to, 'inspection', 'pending', 'medium', :description)");
                $ins->execute([':room_id' => $room_id, ':assigned_to' => $hk, ':description' => $desc]);
            }
        } catch (Exception $e) { }
        $success = 'Guest checked in.';
    }
}

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT b.*, g.first_name, g.last_name, r.room_number FROM bookings b JOIN guests g ON b.guest_id=g.id JOIN rooms r ON b.room_id=r.id WHERE b.check_in_date=:d AND b.status='confirmed' ORDER BY b.check_in_date ASC");
$stmt->execute([':d' => $today]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="row mb-4">
  <div class="col-md-6">
    <div class="card admin-card">
      <div class="card-header"><h6 class="card-title mb-0">Scan QR to Check-In</h6></div>
      <div class="card-body">
        <div id="qr-reader" style="width:100%; max-width:360px"></div>
        <div class="mt-2">
          <input type="text" class="form-control form-control-sm" id="qrManual" placeholder="Paste QR text">
          <button class="btn btn-sm btn-primary mt-2" id="qrManualBtn">Check-In</button>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card admin-card">
      <div class="card-header d-flex justify-content-between align-items-center"><h6 class="card-title mb-0">Walk-In Check-In</h6><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#walkInModal"><i class="bi bi-plus-circle me-1"></i>New Walk-In</button></div>
      <div class="card-body">
        <div class="text-muted">Create a booking and check in immediately for guests without prior reservations.</div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="walkInModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Walk-In Check-In</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="walk_in_check_in">
          <div class="mb-3">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="guest_mode" id="wi_guest_existing" value="existing" checked>
              <label class="form-check-label" for="wi_guest_existing">Existing Guest</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="guest_mode" id="wi_guest_new" value="new">
              <label class="form-check-label" for="wi_guest_new">New Guest</label>
            </div>
          </div>
          <div id="wi_existing_fields" class="mb-3">
            <label class="form-label">Guest</label>
            <select class="form-select" id="wi_guest_id" name="guest_id" required>
              <option value="">Select Guest</option>
              <?php foreach ($guests as $g): ?>
              <option value="<?php echo $g['id']; ?>"><?php echo $g['first_name'].' '.$g['last_name']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="wi_new_fields" class="mb-3" style="display:none">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" id="wi_first_name" name="first_name">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control" id="wi_last_name" name="last_name">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Room</label>
              <select class="form-select" id="wi_room_id" name="room_id" required>
                <option value="">Select Room</option>
                <?php foreach ($available_rooms as $r): ?>
                <option value="<?php echo $r['id']; ?>" data-price="<?php echo $r['base_price']; ?>"><?php echo $r['room_number'].' - '.$r['category_name']; ?> (₱<?php echo number_format($r['base_price'],2); ?>/night)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Check-in</label>
              <input type="date" class="form-control" id="wi_check_in_date" name="check_in_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Check-out</label>
              <input type="date" class="form-control" id="wi_check_out_date" name="check_out_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
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
            <strong>Estimated Total: </strong><span id="wi_estimated_total">₱0.00</span> (<span id="wi_nights_count">1</span> nights)
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Check In</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card admin-card">
  <div class="card-header"><h6 class="card-title mb-0">Today's Confirmed Check-ins</h6></div>
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
                <input type="hidden" name="action" value="check_in_booking">
                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                <button type="submit" class="btn btn-sm btn-success">
                  <i class="bi bi-box-arrow-in-right me-1"></i>Check In
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($bookings)): ?>
          <tr><td colspan="6" class="text-muted">No check-ins scheduled for today.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  function parseQR(text){ 
    var m = String(text).match(/GUEST:(\d+)/); 
    if (m) return { type: 'guest', id: parseInt(m[1]) };
    m = String(text).match(/BOOKING:(\d+)/);
    if (m) return { type: 'booking', id: parseInt(m[1]) };
    return null;
  }
  function doCheckIn(data){ 
    if (!data) return; 
    var fd = new URLSearchParams(); 
    fd.set('action','qr_check_in'); 
    if (data.type === 'guest') fd.set('guest_id', data.id);
    else if (data.type === 'booking') fd.set('booking_id', data.id);
    
    fetch('checkin.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
    .then(function(r){ return r.text(); })
    .then(function(){ window.location.reload(); }); 
  }
  var qrm = document.getElementById('qrManual'); 
  var qrb = document.getElementById('qrManualBtn'); 
  if (qrb) { 
    qrb.addEventListener('click', function(){ doCheckIn(parseQR(qrm.value)); }); 
  }
  if (window.Html5Qrcode) {
    var camId = 'qr-reader';
    var html5QrCode = new Html5Qrcode(camId);
    Html5Qrcode.getCameras().then(function(devices){ 
      var d = devices && devices.length ? devices[0].id : { facingMode: 'environment' }; 
      html5QrCode.start(d, { fps: 10, qrbox: 250 }, function(text){ 
        html5QrCode.stop().then(function(){ doCheckIn(parseQR(text)); }); 
      }); 
    }); 
  }
  function wiCalc(){ var rs=document.getElementById('wi_room_id'); var ci=document.getElementById('wi_check_in_date'); var co=document.getElementById('wi_check_out_date'); if(rs && rs.value && ci && co && ci.value && co.value){ var ciD=new Date(ci.value); var coD=new Date(co.value); if(coD>ciD){ var price=parseFloat(rs.options[rs.selectedIndex].getAttribute('data-price')); var nights=Math.ceil((coD-ciD)/(1000*60*60*24)); document.getElementById('wi_estimated_total').textContent='₱'+(price*nights).toFixed(2); document.getElementById('wi_nights_count').textContent=nights; } } }
  ['wi_room_id','wi_check_in_date','wi_check_out_date'].forEach(function(id){ var el=document.getElementById(id); if(el){ el.addEventListener('change', wiCalc); }});
  function wiToggle(){ var ex=document.getElementById('wi_guest_existing'); var nf=document.getElementById('wi_new_fields'); var ef=document.getElementById('wi_existing_fields'); var sel=document.getElementById('wi_guest_id'); var fn=document.getElementById('wi_first_name'); var ln=document.getElementById('wi_last_name'); if(ex && nf && ef){ if(ex.checked){ nf.style.display='none'; ef.style.display='block'; if(sel) sel.required=true; if(fn) fn.required=false; if(ln) ln.required=false; } else { nf.style.display='block'; ef.style.display='none'; if(sel) sel.required=false; if(fn) fn.required=true; if(ln) ln.required=true; } } }
  ['wi_guest_existing','wi_guest_new'].forEach(function(id){ var el=document.getElementById(id); if(el){ el.addEventListener('change', wiToggle); }});
  wiCalc(); wiToggle();
});
</script>
<?php include '../includes/footer.php'; ?>
