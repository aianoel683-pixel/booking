<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = 'Reports';
$db = db();

// Booking status distribution
$statuses = ['confirmed','checked_in','checked_out','cancelled'];
$statusCounts = [];
foreach ($statuses as $st) {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM bookings WHERE status = :st");
    $stmt->execute([':st' => $st]);
    $statusCounts[$st] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
}

// Revenue current month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM bookings WHERE created_at BETWEEN :s AND :e AND payment_status = 'paid'");
$stmt->execute([':s' => $month_start, ':e' => $month_end]);
$monthly_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0);

include '../includes/admin_header.php';
?>

<div class="row">
  <div class="col-md-4 mb-4">
    <div class="card admin-card">
      <div class="card-body dashboard-metric">
        <div>
          <div class="label">Revenue (Month)</div>
          <div class="value">₱<?php echo number_format($monthly_revenue, 2); ?></div>
        </div>
        <i class="bi bi-cash-coin metric-icon text-success"></i>
      </div>
    </div>
  </div>
  <div class="col-md-8 mb-4">
    <div class="card admin-card">
      <div class="card-header"><h6 class="mb-0">Bookings by Status</h6></div>
      <div class="card-body"><canvas id="bookingsStatusChart" class="admin-chart"></canvas></div>
    </div>
  </div>
</div>

<div class="card admin-card">
  <div class="card-header"><h6 class="mb-0">Reports Center</h6></div>
  <div class="card-body">
    <p class="text-muted-soft mb-2">More detailed revenue and occupancy reports will be added here.</p>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn btn-outline-primary">Revenue Report</button>
      <button class="btn btn-outline-secondary">Occupancy Report</button>
      <button class="btn btn-outline-info">Bookings Report</button>
    </div>
  </div>
</div>

<script>
  (function(){
    var el = document.getElementById('bookingsStatusChart');
    if (!el) return;
    var ctx = el.getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Confirmed','Checked In','Checked Out','Cancelled'],
        datasets: [{
          label: 'Count',
          backgroundColor: ['#e9c46a','#2a9d8f','#3a86ff','#e76f51'],
          data: [
            <?php echo (int)$statusCounts['confirmed']; ?>,
            <?php echo (int)$statusCounts['checked_in']; ?>,
            <?php echo (int)$statusCounts['checked_out']; ?>,
            <?php echo (int)$statusCounts['cancelled']; ?>
          ]
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
      }
    });
  })();
</script>

<?php include '../includes/admin_footer.php'; ?>
