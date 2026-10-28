<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/dashboard_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();
admin_require_admin_role();

$page_title = 'Reports';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d', strtotime('+1 day'));
if (strtotime($from) === false) $from = date('Y-m-01');
if (strtotime($to) === false || $to <= $from) $to = date('Y-m-d', strtotime($from . ' +1 month'));

$booking_stats = dash_booking_stats($conn);
$booking_trend = dash_revenue_trend($conn, 12); // has both revenue + booking counts per month

$revenue = dash_revenue_summary($conn);
$revenue_by_type = dash_revenue_by_type($conn);

$customer_count = dash_customer_count($conn);
$signup_trend = dash_customer_signup_trend($conn, 12);
$top_customers = dash_top_customers($conn, 10);

$occupancy = dash_occupancy_by_type($conn, $from, $to);

include __DIR__ . '/header.php';
?>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-bookings">Booking Reports</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-revenue">Revenue Reports</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-customers">Customer Reports</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-occupancy" id="occupancy">Room Occupancy</a></li>
</ul>

<div class="tab-content">

    <!-- Booking Reports -->
    <div class="tab-pane fade show active" id="tab-bookings">
        <div class="row">
            <div class="col-md-4">
                <table class="table table-sm">
                    <tr><th>Total Bookings</th><td><?php echo $booking_stats['total'] ?></td></tr>
                    <tr><th>Pending</th><td><?php echo $booking_stats['pending'] ?></td></tr>
                    <tr><th>Confirmed</th><td><?php echo $booking_stats['confirmed'] ?></td></tr>
                    <tr><th>Rejected</th><td><?php echo $booking_stats['rejected'] ?></td></tr>
                    <tr><th>Cancelled</th><td><?php echo $booking_stats['cancelled'] ?></td></tr>
                </table>
                <a href="bookings.php" class="btn btn-sm btn-outline-secondary">View all bookings &rarr;</a>
            </div>
            <div class="col-md-8">
                <canvas id="bookingTrendChart" height="140"></canvas>
            </div>
        </div>
    </div>

    <!-- Revenue Reports -->
    <div class="tab-pane fade" id="tab-revenue">
        <div class="row mb-3">
            <div class="col-md-4"><div class="card bg-light"><div class="card-body"><div class="text-muted">Confirmed Revenue</div><h4>$<?php echo number_format($revenue['confirmed_revenue'], 2) ?></h4></div></div></div>
            <div class="col-md-4"><div class="card bg-light"><div class="card-body"><div class="text-muted">Collected</div><h4 class="text-success">$<?php echo number_format($revenue['collected'], 2) ?></h4></div></div></div>
            <div class="col-md-4"><div class="card bg-light"><div class="card-body"><div class="text-muted">Outstanding</div><h4 class="text-danger">$<?php echo number_format($revenue['outstanding'], 2) ?></h4></div></div></div>
        </div>
        <div class="row">
            <div class="col-md-7">
                <canvas id="revenueTrendChart" height="180"></canvas>
            </div>
            <div class="col-md-5">
                <h6>Revenue by Room Type</h6>
                <table class="table table-sm">
                    <thead><tr><th>Room Type</th><th>Bookings</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($revenue_by_type as $r): ?>
                        <tr><td><?php echo admin_esc($r['name']) ?></td><td><?php echo (int)$r['bookings'] ?></td><td>$<?php echo number_format((float)$r['revenue'], 2) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Reports -->
    <div class="tab-pane fade" id="tab-customers">
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card bg-light"><div class="card-body"><div class="text-muted">Total Customers</div><h4><?php echo $customer_count ?></h4></div></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <h6>New Signups (last 12 months)</h6>
                <canvas id="signupTrendChart" height="180"></canvas>
            </div>
            <div class="col-md-6">
                <h6>Top Customers by Spend</h6>
                <table class="table table-sm">
                    <thead><tr><th>Name</th><th>Bookings</th><th>Total Spent</th></tr></thead>
                    <tbody>
                        <?php if (empty($top_customers)): ?>
                        <tr><td colspan="3" class="text-center text-muted">No confirmed bookings yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($top_customers as $c): ?>
                        <tr><td><?php echo admin_esc($c['full_name']) ?><br><span class="text-muted small"><?php echo admin_esc($c['email']) ?></span></td><td><?php echo (int)$c['bookings'] ?></td><td>$<?php echo number_format((float)$c['total_spent'], 2) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Room Occupancy Reports -->
    <div class="tab-pane fade" id="tab-occupancy">
        <form method="GET" class="form-inline mb-3">
            <input type="hidden" name="tab" value="occupancy">
            <label class="mr-2">From</label>
            <input type="date" name="from" class="form-control mr-2" value="<?php echo admin_esc($from) ?>">
            <label class="mr-2">To</label>
            <input type="date" name="to" class="form-control mr-2" value="<?php echo admin_esc($to) ?>">
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
        <div class="row">
            <div class="col-md-7">
                <canvas id="occupancyChart" height="180"></canvas>
            </div>
            <div class="col-md-5">
                <table class="table table-sm">
                    <thead><tr><th>Room Type</th><th>Enabled Rooms</th><th>Booked Nights</th><th>Occupancy</th></tr></thead>
                    <tbody>
                        <?php foreach ($occupancy as $o): ?>
                        <tr>
                            <td><?php echo admin_esc($o['name']) ?></td>
                            <td><?php echo $o['enabled_rooms'] ?></td>
                            <td><?php echo $o['booked_nights'] ?> / <?php echo $o['capacity_nights'] ?></td>
                            <td><?php echo $o['rate'] ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
var bookingTrend = <?php echo json_encode($booking_trend) ?>;
var revenueTrend = bookingTrend;
var signupTrend = <?php echo json_encode($signup_trend) ?>;
var occupancy = <?php echo json_encode($occupancy) ?>;

new Chart(document.getElementById('bookingTrendChart'), {
    type: 'line',
    data: {
        labels: bookingTrend.map(function (r) { return r.label; }),
        datasets: [{ label: 'Confirmed Bookings', data: bookingTrend.map(function (r) { return r.bookings; }), borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', tension: 0.3 }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('revenueTrendChart'), {
    type: 'bar',
    data: {
        labels: revenueTrend.map(function (r) { return r.label; }),
        datasets: [{ label: 'Revenue ($)', data: revenueTrend.map(function (r) { return r.revenue; }), backgroundColor: '#007bff' }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('signupTrendChart'), {
    type: 'line',
    data: {
        labels: signupTrend.map(function (r) { return r.label; }),
        datasets: [{ label: 'New Customers', data: signupTrend.map(function (r) { return r.count; }), borderColor: '#6f42c1', backgroundColor: 'rgba(111,66,193,0.1)', tension: 0.3 }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('occupancyChart'), {
    type: 'bar',
    data: {
        labels: occupancy.map(function (o) { return o.name; }),
        datasets: [{ label: 'Occupancy %', data: occupancy.map(function (o) { return o.rate; }), backgroundColor: '#17a2b8' }]
    },
    options: { indexAxis: 'y', scales: { x: { beginAtZero: true, max: 100 } } }
});

// If we arrived with #occupancy in the URL (linked from the dashboard), show that tab.
if (window.location.hash === '#occupancy') {
    $('a[href="#tab-occupancy"]').tab('show');
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
