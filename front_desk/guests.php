<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Guests';
$db = db();

if (isset($_GET['action']) && $_GET['action'] === 'guest') {
    $id = (int)($_GET['id'] ?? 0);
    $out = ['ok' => false];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM guests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($guest) {
            $b = $db->prepare("SELECT b.*, r.room_number FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE b.guest_id=:id ORDER BY b.created_at DESC LIMIT 5");
            $b->execute([':id' => $id]);
            $out = ['ok' => true, 'guest' => $guest, 'bookings' => $b->fetchAll(PDO::FETCH_ASSOC)];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_guest') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($first_name && $last_name) {
        $stmt = $db->prepare("INSERT INTO guests (first_name,last_name,email,phone,address) VALUES (:fn,:ln,:em,:ph,:ad)");
        $stmt->execute([':fn'=>$first_name, ':ln'=>$last_name, ':em'=>$email, ':ph'=>$phone, ':ad'=>$address]);
        $success = 'Guest added.';
    }
}

$guests = $db->query("SELECT g.*, (SELECT COUNT(*) FROM bookings b WHERE b.guest_id=g.id) AS booking_count FROM guests g ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
$count_all = count($guests);
$count_with = 0; foreach ($guests as $gx) { if ((int)($gx['booking_count'] ?? 0) > 0) $count_with++; }
$count_none = $count_all - $count_with;

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4>Guests</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGuestModal">
    <i class="bi bi-person-plus me-2"></i>Add Guest
  </button>
</div>

<div class="card admin-card">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs" id="guestTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-all-link" data-bs-toggle="tab" data-bs-target="#tabAll" type="button" role="tab">All Guests <span class="badge bg-secondary ms-1"><?php echo (int)$count_all; ?></span></button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-with-link" data-bs-toggle="tab" data-bs-target="#tabWith" type="button" role="tab">With Booking History <span class="badge bg-primary ms-1"><?php echo (int)$count_with; ?></span></button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-none-link" data-bs-toggle="tab" data-bs-target="#tabNone" type="button" role="tab">No Booking History <span class="badge bg-warning text-dark ms-1"><?php echo (int)$count_none; ?></span></button>
      </li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="tabAll" role="tabpanel" aria-labelledby="tab-all-link">
        <div class="table-responsive">
          <table class="table table-hover table-modern">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($guests as $g): ?>
              <tr>
                <td><?php echo $g['first_name'].' '.$g['last_name']; ?></td>
                <td><?php echo $g['email']; ?></td>
                <td><?php echo $g['phone']; ?></td>
                <td><?php echo $g['address']; ?></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-info me-2 btn-view-guest" data-guest-id="<?php echo $g['id']; ?>">View</button>
                  <button type="button" class="btn btn-sm btn-secondary btn-id-card" data-guest-id="<?php echo $g['id']; ?>" data-guest-name="<?php echo htmlspecialchars($g['first_name'].' '.$g['last_name']); ?>" data-guest-email="<?php echo htmlspecialchars($g['email']); ?>" data-guest-phone="<?php echo htmlspecialchars($g['phone']); ?>">ID Card</button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($guests)): ?>
              <tr><td colspan="5" class="text-muted">No guests found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="tab-pane fade" id="tabWith" role="tabpanel" aria-labelledby="tab-with-link">
        <div class="table-responsive">
          <table class="table table-hover table-modern">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $hasRows=false; foreach ($guests as $g): if ((int)($g['booking_count'] ?? 0) > 0) { $hasRows=true; ?>
              <tr>
                <td><?php echo $g['first_name'].' '.$g['last_name']; ?></td>
                <td><?php echo $g['email']; ?></td>
                <td><?php echo $g['phone']; ?></td>
                <td><?php echo $g['address']; ?></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-info me-2 btn-view-guest" data-guest-id="<?php echo $g['id']; ?>">View</button>
                  <button type="button" class="btn btn-sm btn-secondary btn-id-card" data-guest-id="<?php echo $g['id']; ?>" data-guest-name="<?php echo htmlspecialchars($g['first_name'].' '.$g['last_name']); ?>" data-guest-email="<?php echo htmlspecialchars($g['email']); ?>" data-guest-phone="<?php echo htmlspecialchars($g['phone']); ?>">ID Card</button>
                </td>
              </tr>
              <?php } endforeach; if(!$hasRows): ?>
              <tr><td colspan="5" class="text-muted">No guests with booking history.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="tab-pane fade" id="tabNone" role="tabpanel" aria-labelledby="tab-none-link">
        <div class="table-responsive">
          <table class="table table-hover table-modern">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $hasRows=false; foreach ($guests as $g): if ((int)($g['booking_count'] ?? 0) === 0) { $hasRows=true; ?>
              <tr>
                <td><?php echo $g['first_name'].' '.$g['last_name']; ?></td>
                <td><?php echo $g['email']; ?></td>
                <td><?php echo $g['phone']; ?></td>
                <td><?php echo $g['address']; ?></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-info me-2 btn-view-guest" data-guest-id="<?php echo $g['id']; ?>">View</button>
                  <button type="button" class="btn btn-sm btn-secondary btn-id-card" data-guest-id="<?php echo $g['id']; ?>" data-guest-name="<?php echo htmlspecialchars($g['first_name'].' '.$g['last_name']); ?>" data-guest-email="<?php echo htmlspecialchars($g['email']); ?>" data-guest-phone="<?php echo htmlspecialchars($g['phone']); ?>">ID Card</button>
                </td>
              </tr>
              <?php } endforeach; if(!$hasRows): ?>
              <tr><td colspan="5" class="text-muted">No guests without booking history.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="guestDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Guest Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-2"><strong id="gdName"></strong></div>
            <div class="mb-1"><span class="text-muted">Email</span>: <span id="gdEmail"></span></div>
            <div class="mb-1"><span class="text-muted">Phone</span>: <span id="gdPhone"></span></div>
            <div class="mb-1"><span class="text-muted">Address</span>: <span id="gdAddress"></span></div>
            <div class="mb-1"><span class="text-muted">ID Type</span>: <span id="gdIdType"></span></div>
            <div class="mb-1"><span class="text-muted">ID Number</span>: <span id="gdIdNumber"></span></div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">Recent Bookings</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead>
                    <tbody id="gdBookings"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-primary" id="gdOpenIdCard">Open ID Card</button>
      </div>
    </div>
  </div>
 </div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

.id-card {
    width: 420px;
    height: 260px;
    background-color: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    font-family: 'Inter', sans-serif;
    border: 1px solid rgba(0,0,0,0.05);
}
.id-card-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top right, rgba(212, 175, 55, 0.1), transparent 40%);
    pointer-events: none;
    z-index: 0;
}
.id-card-header {
    background-color: #1e1e1e;
    color: #d4af37;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}
.id-brand {
    font-weight: 700;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.id-badge {
    background: rgba(212, 175, 55, 0.2);
    color: #d4af37;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    border: 1px solid rgba(212, 175, 55, 0.3);
}
.id-card-body {
    flex: 1;
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
    z-index: 1;
}
.id-info-left {
    flex: 1;
    padding-right: 20px;
    display: flex;
    flex-direction: column;
}
.id-avatar-circle {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #d4af37, #b39020);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 22px;
    margin-bottom: 16px;
    box-shadow: 0 4px 10px rgba(212, 175, 55, 0.3);
}
.id-name {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
    line-height: 1.2;
}
.id-meta {
    font-size: 11px;
    color: #6c757d;
    line-height: 1.5;
}
.id-meta div {
    margin-bottom: 2px;
}
.id-meta span {
    color: #1a1a1a;
    font-weight: 600;
}
.id-qr-box {
    padding: 8px;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
.id-card-footer {
    background: #f8f9fa;
    border-top: 1px solid #eee;
    padding: 10px 24px;
    font-size: 10px;
    color: #adb5bd;
    display: flex;
    justify-content: space-between;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    z-index: 1;
}
.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}
@media print {
  body * { visibility: hidden; }
  #printableIdCard, #printableIdCard * { visibility: visible; }
  #printableIdCard { position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%); box-shadow: none; border: 1px solid #ccc; }
  .modal { position: static !important; }
  .id-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
@page { size: auto; margin: 0mm; }
</style>

<div class="modal fade" id="idCardModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Guest ID Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body d-flex justify-content-center p-4">
        <div id="printableIdCard" class="id-card">
          <div class="id-card-bg"></div>
          <div class="id-card-header">
            <div class="id-brand"><i class="bi bi-building icon"></i><span>HOTEL GUEST</span></div>
            <div class="id-badge">VALID PASS</div>
          </div>
          <div class="id-card-body">
            <div class="id-info-left">
                <div class="id-avatar-circle"><span id="idInitials"></span></div>
                <div class="id-name" id="idName"></div>
                <div class="id-meta">
                    <div>ID: <span id="idGuestId"></span></div>
                    <div id="idEmail" class="text-truncate"></div>
                    <div id="idPhone"></div>
                </div>
            </div>
            <div class="id-qr-box">
                <div id="idQr"></div>
            </div>
          </div>
          <div class="id-card-footer">
              <span>Authorized Guest</span>
              <span><?php echo date('Y'); ?></span>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="idPrintBtn"><i class="bi bi-printer me-2"></i>Print Card</button>
      </div>
    </div>
  </div>
 </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var viewButtons = document.querySelectorAll('.btn-view-guest');
  var idButtons = document.querySelectorAll('.btn-id-card');
  function fetchGuest(id){ return fetch('guests.php?action=guest&id=' + encodeURIComponent(id)).then(function(r){ return r.json(); }); }
  function fillGuestModal(data){
    document.getElementById('gdName').textContent = data.guest.first_name + ' ' + data.guest.last_name;
    document.getElementById('gdEmail').textContent = data.guest.email || '';
    document.getElementById('gdPhone').textContent = data.guest.phone || '';
    document.getElementById('gdAddress').textContent = data.guest.address || '';
    document.getElementById('gdIdType').textContent = data.guest.id_type || '';
    document.getElementById('gdIdNumber').textContent = data.guest.id_number || '';
    var tbody = document.getElementById('gdBookings');
    tbody.innerHTML = '';
    if (data.bookings && data.bookings.length) {
      data.bookings.forEach(function(b){
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>#' + b.id + '</td><td>' + (b.room_number || '') + '</td><td>' + b.check_in_date + '</td><td>' + b.check_out_date + '</td><td>' + b.status + '</td>';
        tbody.appendChild(tr);
      });
    } else {
      var tr = document.createElement('tr'); tr.innerHTML = '<td colspan="5" class="text-muted">No recent bookings.</td>'; tbody.appendChild(tr);
    }
  }
  function toTitleCase(str){ return (str||'').toLowerCase().replace(/\b[\w']+/g, function(w){ return w.charAt(0).toUpperCase() + w.slice(1); }); }
  function openIdCard(info){
    var nameFormatted = toTitleCase(info.name || '');
    document.getElementById('idName').textContent = nameFormatted;
    document.getElementById('idEmail').textContent = info.email || '';
    document.getElementById('idPhone').textContent = info.phone || '';
    var idSpan = document.getElementById('idGuestId'); if(idSpan) idSpan.textContent = String(info.id).padStart(5, '0');
    var code = 'C-' + info.id;
    var initials = (nameFormatted || '').trim().split(/\s+/).map(function(p){ return p && p[0] ? p[0].toUpperCase() : ''; }).slice(0,2).join('');
    var initEl = document.getElementById('idInitials'); if (initEl) { initEl.textContent = initials || 'G'; }
    var qrEl = document.getElementById('idQr');
    qrEl.innerHTML = '';
    var payload = 'HOTEL|GUEST:' + info.id + '|CODE:' + code + '|NAME:' + nameFormatted + '|PHONE:' + (info.phone || '') + '|EMAIL:' + (info.email || '');
    new QRCode(qrEl, { text: payload, width: 90, height: 90, colorDark : "#1a1a1a", colorLight : "#ffffff" });
    document.querySelectorAll('.modal.show').forEach(function(m){ var inst = bootstrap.Modal.getInstance(m); if (inst) inst.hide(); });
    bootstrap.Modal.getOrCreateInstance(document.getElementById('idCardModal')).show();
  }
  viewButtons.forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = this.getAttribute('data-guest-id');
      fetchGuest(id).then(function(data){ if (data.ok) { fillGuestModal(data); bootstrap.Modal.getOrCreateInstance(document.getElementById('guestDetailsModal')).show(); } });
      document.getElementById('gdOpenIdCard').onclick = function(){
        fetchGuest(id).then(function(data){ if (data.ok) { openIdCard({ id: data.guest.id, name: data.guest.first_name + ' ' + data.guest.last_name, email: data.guest.email, phone: data.guest.phone }); } });
      };
    });
  });
  idButtons.forEach(function(btn){
    btn.addEventListener('click', function(){
      openIdCard({ id: this.getAttribute('data-guest-id'), name: this.getAttribute('data-guest-name'), email: this.getAttribute('data-guest-email'), phone: this.getAttribute('data-guest-phone') });
    });
  });
  var printBtn = document.getElementById('idPrintBtn');
  if (printBtn) { printBtn.addEventListener('click', function(){ window.print(); }); }
});
</script>
<div class="modal fade" id="addGuestModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Guest</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="add_guest">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" name="first_name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" name="last_name" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email">
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" class="form-control" name="phone">
          </div>
          <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea class="form-control" name="address" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Guest</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
