<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = 'Front Desk Dashboard';
$db = db();

if (isset($_GET['action']) && $_GET['action'] === 'events') {
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    $start_date = date('Y-m-d', strtotime($start));
    $end_date = date('Y-m-d', strtotime($end));
    $status = $_GET['status'] ?? 'all';
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT b.*, g.first_name, g.last_name, r.room_number FROM bookings b JOIN guests g ON b.guest_id=g.id JOIN rooms r ON b.room_id=r.id WHERE b.status IN ('confirmed','checked_in') AND b.check_out_date >= :start AND b.check_in_date <= :end";
    $params = [':start' => $start_date, ':end' => $end_date];
    if (in_array($status, ['confirmed','checked_in'])) { $sql .= " AND b.status = :status"; $params[':status'] = $status; }
    if ($q !== '') { $sql .= " AND (g.first_name LIKE :q OR g.last_name LIKE :q OR r.room_number LIKE :q)"; $params[':q'] = "%$q%"; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id' => $row['id'],
            'title' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['room_number'] . ')',
            'start' => $row['check_in_date'],
            'end' => date('Y-m-d', strtotime($row['check_out_date'] . ' +1 day')),
            'allDay' => true,
            'color' => ($row['status'] === 'checked_in') ? '#198754' : '#0d6efd',
            'extendedProps' => [
                'guest' => $row['first_name'] . ' ' . $row['last_name'],
                'room_number' => $row['room_number'],
                'status' => $row['status'],
                'check_in_date' => $row['check_in_date'],
                'check_out_date' => $row['check_out_date'],
                'nights' => max(1, (strtotime($row['check_out_date']) - strtotime($row['check_in_date'])) / (60*60*24)),
                'total_amount' => $row['total_amount']
            ]
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($events);
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'day') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $d = date('Y-m-d', strtotime($date));
    $stmt = $db->prepare("SELECT b.*, g.first_name, g.last_name, r.room_number FROM bookings b JOIN guests g ON b.guest_id=g.id JOIN rooms r ON b.room_id=r.id WHERE b.check_in_date=:d AND b.status IN ('confirmed','checked_in') ORDER BY r.room_number, g.first_name");
    $stmt->execute([':d' => $d]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'guest' => $row['first_name'].' '.$row['last_name'],
            'room_number' => $row['room_number'],
            'status' => $row['status'],
            'check_in_date' => $row['check_in_date'],
            'check_out_date' => $row['check_out_date']
        ];
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'bookings' => $out]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'qr_check_in') {
    $guest_id = (int)($_POST['guest_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    if ($guest_id > 0) {
        $s = $db->prepare("SELECT b.* FROM bookings b WHERE b.guest_id=:gid AND b.check_in_date=:d AND b.status='confirmed' ORDER BY b.created_at DESC LIMIT 1");
        $s->execute([':gid' => $guest_id, ':d' => $date]);
        $booking = $s->fetch(PDO::FETCH_ASSOC);
        if ($booking) {
            $stmt = $db->prepare("UPDATE bookings SET status='checked_in' WHERE id=:id");
            $stmt->execute([':id' => $booking['id']]);
            $stmt = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id=b.room_id SET r.status='occupied' WHERE b.id=:id");
            $stmt->execute([':id' => $booking['id']]);
            $success = 'QR check-in completed.';
        }
    }
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
    $check_in_date = $_POST['check_in_date'] ?? date('Y-m-d');
    $check_out_date = $_POST['check_out_date'] ?? date('Y-m-d', strtotime('+1 day'));
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
            $stmt = $db->prepare("INSERT INTO bookings (guest_id, room_id, check_in_date, check_out_date, adults, children, total_amount, special_requests, created_by, status) VALUES (:guest_id,:room_id,:ci,:co,:ad,:ch,:ta,:sr,:cb,'checked_in')");
            $stmt->execute([':guest_id'=>$guest_id, ':room_id'=>$room_id, ':ci'=>$check_in_date, ':co'=>$check_out_date, ':ad'=>$adults, ':ch'=>$children, ':ta'=>$total_amount, ':sr'=>$special_requests, ':cb'=>$_SESSION['user_id']]);
            $u2 = $db->prepare("UPDATE rooms SET status='occupied' WHERE id=:id");
            $u2->execute([':id' => $room_id]);
            $success = 'Walk-in check-in completed.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $ok = false;
    if ($booking_id && in_array($new_status, ['checked_in','checked_out'])) {
        $s = $db->prepare("UPDATE bookings SET status=:s WHERE id=:id");
        $s->execute([':s' => $new_status, ':id' => $booking_id]);
        if ($new_status === 'checked_in') {
            $s = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id=b.room_id SET r.status='occupied' WHERE b.id=:id");
            $s->execute([':id' => $booking_id]);
        } elseif ($new_status === 'checked_out') {
            $s = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id=b.room_id SET r.status='available', r.housekeeping_status='dirty' WHERE b.id=:id");
            $s->execute([':id' => $booking_id]);
        }
        $ok = true;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok]);
    exit();
}

// Stats
$available_rooms = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE status='available'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
$occupied_rooms = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE status='occupied'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
$reserved_rooms = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE status='reserved'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;

$today = date('Y-m-d');
$today_checkins = $db->prepare("SELECT COUNT(*) AS c FROM bookings WHERE check_in_date = :d AND status='confirmed'");
$today_checkins->execute([':d' => $today]);
$today_checkins = $today_checkins->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;

$today_checkouts = $db->prepare("SELECT COUNT(*) AS c FROM bookings WHERE check_out_date = :d AND status='checked_in'");
$today_checkouts->execute([':d' => $today]);
$today_checkouts = $today_checkouts->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;


$available_rooms = $db->query("SELECT r.*, rc.name AS category_name, rc.base_price FROM rooms r JOIN room_categories rc ON r.category_id=rc.id WHERE r.status='available' ORDER BY r.room_number")->fetchAll(PDO::FETCH_ASSOC);
$guests = $db->query("SELECT * FROM guests ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
  <div class="col-md-3 mb-4">
    <div class="card admin-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Available Rooms</div>
          <div class="h3 mb-0"><?php echo (int)$available_rooms; ?></div>
        </div>
        <i class="bi bi-door-open fs-1 text-success"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card admin-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Occupied Rooms</div>
          <div class="h3 mb-0"><?php echo (int)$occupied_rooms; ?></div>
        </div>
        <i class="bi bi-door-closed fs-1 text-danger"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card admin-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Reserved Rooms</div>
          <div class="h3 mb-0"><?php echo (int)$reserved_rooms; ?></div>
        </div>
        <i class="bi bi-calendar-event fs-1 text-warning"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card admin-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Today's Check-ins</div>
          <div class="h3 mb-0"><?php echo (int)$today_checkins; ?></div>
        </div>
        <i class="bi bi-box-arrow-in-right fs-1 text-primary"></i>
      </div>
    </div>
  </div>
</div>


<div class="row">
  <div class="col-md-12 mb-4">
    <div class="card admin-card">
      <div class="card-header"><h6 class="card-title mb-0">Booking Calendar</h6></div>
      <div class="card-body">
        <div class="row g-2 align-items-center mb-3">
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="calStatus">
              <option value="all">All statuses</option>
              <option value="confirmed">Confirmed</option>
              <option value="checked_in">Checked-in</option>
            </select>
          </div>
          <div class="col-md-5">
            <input type="text" class="form-control form-control-sm" id="calQuery" placeholder="Search guest or room">
          </div>
          <div class="col-md-4 text-end">
            <div class="btn-group btn-group-sm" role="group">
              <button type="button" class="btn btn-outline-secondary" id="calToday">Today</button>
              <button type="button" class="btn btn-outline-secondary" id="calMonth">Month</button>
              <button type="button" class="btn btn-outline-secondary" id="calWeek">Week</button>
              <button type="button" class="btn btn-outline-secondary" id="calDay">Day</button>
            </div>
          </div>
        </div>
        <div class="d-flex gap-3 mb-2">
          <span class="badge" style="background:#0d6efd">Confirmed</span>
          <span class="badge" style="background:#198754">Checked-in</span>
        </div>
        <div id="fd-booking-calendar"></div>
      </div>
    </div>
  </div>


<div class="row">
  <div class="col-md-6 mb-4">
    <div class="card admin-card">
      <div class="card-header"><h6 class="card-title mb-0">Today's Flow</h6></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-success"><i class="bi bi-box-arrow-in-right me-1"></i>Check-ins: <?php echo $today_checkins; ?></span>
          <span class="badge bg-danger"><i class="bi bi-box-arrow-right me-1"></i>Check-outs: <?php echo $today_checkouts; ?></span>
        </div>
        <div class="progress progress-slim">
          <div class="progress-bar bg-success" style="width: <?php echo ($today_checkins / max($today_checkins + $today_checkouts, 1)) * 100; ?>%"></div>
          <div class="progress-bar bg-danger" style="width: <?php echo ($today_checkouts / max($today_checkins + $today_checkouts, 1)) * 100; ?>%"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function() {
  var el = document.getElementById('fd-booking-calendar');
  if (!el || typeof FullCalendar === 'undefined') return;
  var statusEl = document.getElementById('calStatus');
  var queryEl = document.getElementById('calQuery');
  var calendar = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    weekends: true,
    events: {
      url: 'dashboard.php?action=events',
      method: 'GET',
      extraParams: function() {
        return { status: statusEl ? statusEl.value : 'all', q: queryEl ? queryEl.value : '' };
      }
    },
    eventDisplay: 'block',
    eventContent: function(arg) {
      var guest = arg.event.extendedProps && arg.event.extendedProps.guest ? arg.event.extendedProps.guest : arg.event.title;
      var room = arg.event.extendedProps && arg.event.extendedProps.room_number ? arg.event.extendedProps.room_number : '';
      var inner = document.createElement('div');
      inner.innerHTML = '<div><strong>' + guest + '</strong></div>' + (room ? '<div class="small text-muted">Room ' + room + '</div>' : '');
      return { domNodes: [inner] };
    },
    eventDidMount: function(info) {
      var p = info.event.extendedProps || {};
      var t = 'Guest: ' + (p.guest || info.event.title) + '\nRoom: ' + (p.room_number || '') + '\nCheck-in: ' + (p.check_in_date || info.event.startStr) + '\nCheck-out: ' + (p.check_out_date || '') + '\nNights: ' + (p.nights || '') + '\nStatus: ' + (p.status || '');
      info.el.setAttribute('title', t);
      if (window.bootstrap && bootstrap.Tooltip) { bootstrap.Tooltip.getOrCreateInstance(info.el); }
    },
    dateClick: function(arg) {
      var dateStr = arg.dateStr;
      var dm = document.getElementById('calendarDayModal');
      if (!dm) return;
      dm.querySelector('[data-field="day-date"]').textContent = dateStr;
      var tbody = dm.querySelector('#dayBookings');
      tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Loading...</td></tr>';
      fetch('dashboard.php?action=day&date=' + encodeURIComponent(dateStr))
        .then(function(r){ return r.json(); })
        .then(function(data){
          tbody.innerHTML = '';
          if (data.bookings && data.bookings.length) {
            data.bookings.forEach(function(b){
              var tr = document.createElement('tr');
              var actions = '';
              if (b.status === 'confirmed') actions = '<button class="btn btn-sm btn-success day-checkin" data-id="'+b.id+'">Check-In</button>';
              if (b.status === 'checked_in') actions = '<button class="btn btn-sm btn-danger day-checkout" data-id="'+b.id+'">Check-Out</button>';
              tr.innerHTML = '<td>#'+b.id+'</td><td>'+b.guest+'</td><td>'+b.room_number+'</td><td><span class="badge bg-'+(b.status==='confirmed'?'primary':'success')+'">'+(b.status==='confirmed'?'Confirmed':'Checked-in')+'</span> '+actions+'</td>';
              tbody.appendChild(tr);
            });
          } else {
            var tr = document.createElement('tr'); tr.innerHTML = '<td colspan="4" class="text-muted">No bookings on this date.</td>'; tbody.appendChild(tr);
          }
        });
      var modal = bootstrap.Modal.getOrCreateInstance(dm);
      modal.show();
      setTimeout(function(){ if (window.Html5Qrcode) {
        var camId = 'qr-reader-day';
        var el = document.getElementById(camId);
        if (el) {
          dm._qrInstance && dm._qrInstance.stop().catch(function(){});
          dm._qrInstance = new Html5Qrcode(camId);
          Html5Qrcode.getCameras().then(function(devices){ var d = devices && devices.length ? devices[0].id : { facingMode: 'environment' }; dm._qrInstance.start(d, { fps: 10, qrbox: 220 }, function(text){ dm._qrInstance.stop().then(function(){ var m = String(text).match(/GUEST:(\d+)/); var gid = m ? parseInt(m[1]) : 0; var fd = new URLSearchParams(); fd.set('action','qr_check_in'); fd.set('guest_id', gid); fd.set('date', dateStr); fetch('dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() }).then(function(){ modal.hide(); location.reload(); }); }); }); });
        }
      } }, 300);
    },
    eventClick: function(info) {
      var p = info.event.extendedProps || {};
      var mEl = document.getElementById('calendarBookingModal');
      if (!mEl) return;
      mEl.querySelector('[data-field="guest"]').textContent = p.guest || info.event.title;
      mEl.querySelector('[data-field="room"]').textContent = p.room_number || '';
      mEl.querySelector('[data-field="checkin"]').textContent = p.check_in_date || info.event.startStr;
      mEl.querySelector('[data-field="checkout"]').textContent = p.check_out_date || '';
      mEl.querySelector('[data-field="nights"]').textContent = p.nights || '';
      mEl.querySelector('[data-field="amount"]').textContent = p.total_amount ? '₱' + Number(p.total_amount).toFixed(2) : '';
      mEl.querySelector('[data-field="status"]').textContent = p.status || '';
      mEl.querySelector('#modalBookingId').value = info.event.id;
      var btnIn = mEl.querySelector('#modalCheckIn');
      var btnOut = mEl.querySelector('#modalCheckOut');
      if (p.status === 'confirmed') { btnIn.classList.remove('d-none'); } else { btnIn.classList.add('d-none'); }
      if (p.status === 'checked_in') { btnOut.classList.remove('d-none'); } else { btnOut.classList.add('d-none'); }
      var modal = bootstrap.Modal.getOrCreateInstance(mEl);
      modal.show();
    }
  });
  calendar.render();
  if (statusEl) { statusEl.addEventListener('change', function(){ calendar.refetchEvents(); }); }
  if (queryEl) { queryEl.addEventListener('input', function(){ calendar.refetchEvents(); }); }
  var btnToday = document.getElementById('calToday');
  var btnMonth = document.getElementById('calMonth');
  var btnWeek = document.getElementById('calWeek');
  var btnDay = document.getElementById('calDay');
  if (btnToday) btnToday.addEventListener('click', function(){ calendar.today(); });
  if (btnMonth) btnMonth.addEventListener('click', function(){ calendar.changeView('dayGridMonth'); });
  if (btnWeek) btnWeek.addEventListener('click', function(){ calendar.changeView('timeGridWeek'); });
  if (btnDay) btnDay.addEventListener('click', function(){ calendar.changeView('timeGridDay'); });
  var mEl = document.getElementById('calendarBookingModal');
  if (mEl) {
    var btnIn = mEl.querySelector('#modalCheckIn');
    var btnOut = mEl.querySelector('#modalCheckOut');
    var hiddenId = mEl.querySelector('#modalBookingId');
    function updateStatus(s) {
      var fd = new URLSearchParams();
      fd.set('action', 'update_status');
      fd.set('booking_id', hiddenId.value);
      fd.set('status', s);
      fetch('dashboard.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() })
        .then(function(r){ return r.json(); })
        .then(function(){ bootstrap.Modal.getOrCreateInstance(mEl).hide(); calendar.refetchEvents(); });
    }
    if (btnIn) btnIn.addEventListener('click', function(){ updateStatus('checked_in'); });
    if (btnOut) btnOut.addEventListener('click', function(){ updateStatus('checked_out'); });
  }
});
</script>

<div class="modal fade" id="calendarBookingModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Booking Details</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="modalBookingId">
        <div class="mb-2"><strong data-field="guest"></strong></div>
        <div class="mb-1"><span class="text-muted">Room</span>: <span data-field="room"></span></div>
        <div class="mb-1"><span class="text-muted">Check-in</span>: <span data-field="checkin"></span></div>
        <div class="mb-1"><span class="text-muted">Check-out</span>: <span data-field="checkout"></span></div>
        <div class="mb-1"><span class="text-muted">Nights</span>: <span data-field="nights"></span></div>
        <div class="mb-1"><span class="text-muted">Amount</span>: <span data-field="amount"></span></div>
        <div class="mb-1"><span class="text-muted">Status</span>: <span data-field="status"></span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success btn-sm" id="modalCheckIn">Check-In</button>
        <button type="button" class="btn btn-danger btn-sm" id="modalCheckOut">Check-Out</button>
        <a class="btn btn-outline-secondary btn-sm" href="bookings.php">Open Bookings</a>
      </div>
    </div>
  </div>
 </div>

<div class="modal fade" id="calendarDayModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Day Actions: <span data-field="day-date"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Scheduled Check-ins</strong>
          </div>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Booking</th><th>Guest</th><th>Room</th><th>Status/Actions</th></tr></thead>
              <tbody id="dayBookings"></tbody>
            </table>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">Scan QR to Check-In</div>
              <div class="card-body">
                <div id="qr-reader-day" style="width:100%; max-width:320px"></div>
                <div class="mt-2 d-flex gap-2">
                  <input type="text" class="form-control form-control-sm" id="qrManualDay" placeholder="Paste QR text">
                  <button class="btn btn-sm btn-primary" id="qrManualDayBtn">Check-In</button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">Walk-In Check-In</div>
              <div class="card-body">
                <form method="POST">
                  <input type="hidden" name="action" value="walk_in_check_in">
                  <div class="mb-2">
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="guest_mode" id="day_guest_existing" value="existing" checked>
                      <label class="form-check-label" for="day_guest_existing">Existing Guest</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="guest_mode" id="day_guest_new" value="new">
                      <label class="form-check-label" for="day_guest_new">New Guest</label>
                    </div>
                  </div>
                  <div id="day_existing_fields" class="mb-2">
                    <select class="form-select form-select-sm" id="day_guest_id" name="guest_id" required>
                      <option value="">Select Guest</option>
                      <?php foreach ($guests as $g): ?>
                      <option value="<?php echo $g['id']; ?>"><?php echo $g['first_name'].' '.$g['last_name']; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div id="day_new_fields" class="mb-2" style="display:none">
                    <div class="row g-2">
                      <div class="col-6"><input type="text" class="form-control form-control-sm" id="day_first_name" name="first_name" placeholder="First name"></div>
                      <div class="col-6"><input type="text" class="form-control form-control-sm" id="day_last_name" name="last_name" placeholder="Last name"></div>
                      <div class="col-6"><input type="email" class="form-control form-control-sm" name="email" placeholder="Email"></div>
                      <div class="col-6"><input type="text" class="form-control form-control-sm" name="phone" placeholder="Phone"></div>
                    </div>
                  </div>
                  <div class="row g-2">
                    <div class="col-6">
                      <select class="form-select form-select-sm" id="day_room_id" name="room_id" required>
                        <option value="">Select Room</option>
                        <?php foreach ($available_rooms as $r): ?>
                        <option value="<?php echo $r['id']; ?>" data-price="<?php echo $r['base_price']; ?>"><?php echo $r['room_number'].' - '.$r['category_name']; ?> (₱<?php echo number_format($r['base_price'],2); ?>/night)</option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-3"><input type="date" class="form-control form-control-sm" id="day_check_in_date" name="check_in_date" required></div>
                    <div class="col-3"><input type="date" class="form-control form-control-sm" id="day_check_out_date" name="check_out_date" required></div>
                  </div>
                  <div class="row g-2 mt-2">
                    <div class="col-6"><input type="number" class="form-control form-control-sm" name="adults" min="1" max="10" value="1"></div>
                    <div class="col-6"><input type="number" class="form-control form-control-sm" name="children" min="0" max="10" value="0"></div>
                  </div>
                  <div class="mt-2">
                    <textarea class="form-control form-control-sm" name="special_requests" rows="2" placeholder="Special requests"></textarea>
                  </div>
                  <div class="alert alert-info mt-2 p-2"><strong>Estimated Total: </strong><span id="day_estimated_total">₱0.00</span> (<span id="day_nights_count">1</span> nights)</div>
                  <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-sm">Check In</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
 </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var dm = document.getElementById('calendarDayModal');
  if (!dm) return;
  dm.addEventListener('hidden.bs.modal', function(){ if (dm._qrInstance) { dm._qrInstance.stop().catch(function(){}); dm._qrInstance = null; } });
  var qrm = document.getElementById('qrManualDay'); var qrb = document.getElementById('qrManualDayBtn'); if (qrb) { qrb.addEventListener('click', function(){ var dateStr = dm.querySelector('[data-field="day-date"]').textContent; var m = String(qrm.value).match(/GUEST:(\d+)/); var gid = m ? parseInt(m[1]) : 0; var fd = new URLSearchParams(); fd.set('action','qr_check_in'); fd.set('guest_id', gid); fd.set('date', dateStr); fetch('dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() }).then(function(){ bootstrap.Modal.getOrCreateInstance(dm).hide(); location.reload(); }); }); }
  function dayCalc(){ var rs=document.getElementById('day_room_id'); var ci=document.getElementById('day_check_in_date'); var co=document.getElementById('day_check_out_date'); if(rs && rs.value && ci && co && ci.value && co.value){ var ciD=new Date(ci.value); var coD=new Date(co.value); if(coD>ciD){ var price=parseFloat(rs.options[rs.selectedIndex].getAttribute('data-price')); var nights=Math.ceil((coD-ciD)/(1000*60*60*24)); document.getElementById('day_estimated_total').textContent='₱'+(price*nights).toFixed(2); document.getElementById('day_nights_count').textContent=nights; } } }
  ['day_room_id','day_check_in_date','day_check_out_date'].forEach(function(id){ var el=document.getElementById(id); if(el){ el.addEventListener('change', dayCalc); }});
  function dayToggle(){ var ex=document.getElementById('day_guest_existing'); var nf=document.getElementById('day_new_fields'); var ef=document.getElementById('day_existing_fields'); var sel=document.getElementById('day_guest_id'); var fn=document.getElementById('day_first_name'); var ln=document.getElementById('day_last_name'); if(ex && nf && ef){ if(ex.checked){ nf.style.display='none'; ef.style.display='block'; if(sel) sel.required=true; if(fn) fn.required=false; if(ln) ln.required=false; } else { nf.style.display='block'; ef.style.display='none'; if(sel) sel.required=false; if(fn) fn.required=true; if(ln) ln.required=true; } } }
  ['day_guest_existing','day_guest_new'].forEach(function(id){ var el=document.getElementById(id); if(el){ el.addEventListener('change', dayToggle); }});
  document.getElementById('calendarDayModal')?.addEventListener('show.bs.modal', function(){ var ds = this.querySelector('[data-field="day-date"]').textContent; var ci = document.getElementById('day_check_in_date'); var co = document.getElementById('day_check_out_date'); if (ci) ci.value = ds; if (co) { var d = new Date(ds); d.setDate(d.getDate()+1); co.value = d.toISOString().slice(0,10); } dayCalc(); });
  var tbody = document.getElementById('dayBookings');
  tbody?.addEventListener('click', function(e){ var t = e.target; if (t.classList.contains('day-checkin')) { var id = t.getAttribute('data-id'); var fd = new URLSearchParams(); fd.set('action','update_status'); fd.set('booking_id', id); fd.set('status','checked_in'); fetch('dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() }).then(function(){ location.reload(); }); } else if (t.classList.contains('day-checkout')) { var id = t.getAttribute('data-id'); var fd = new URLSearchParams(); fd.set('action','update_status'); fd.set('booking_id', id); fd.set('status','checked_out'); fetch('dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() }).then(function(){ location.reload(); }); } });
});
</script>
 

<?php include '../includes/footer.php'; ?>
