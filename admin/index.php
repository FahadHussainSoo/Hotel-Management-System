<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/dashboard_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Dashboard';

$room_stats    = dash_room_stats($conn);
$booking_stats = dash_booking_stats($conn);
$customer_count = dash_customer_count($conn);
$revenue       = dash_revenue_summary($conn);
$new_messages  = dash_new_message_count($conn);

$revenue_trend = dash_revenue_trend($conn, 6);
$occupancy_today = dash_occupancy_by_type($conn, date('Y-m-d'), date('Y-m-d', strtotime('+1 day')));

include __DIR__ . '/header.php';
?>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h2><?php echo $room_stats['total'] ?></h2>
                <div>Total Rooms</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <h2><?php echo $room_stats['available'] ?></h2>
                <div>Available Rooms</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-info h-100">
            <div class="card-body">
                <h2><?php echo $room_stats['occupied'] ?></h2>
                <div>Occupied Rooms</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-secondary h-100">
            <div class="card-body">
                <h2><?php echo $room_stats['disabled'] ?></h2>
                <div>Disabled Rooms</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2><?php echo $booking_stats['total'] ?></h2>
                <div class="text-muted">Total Bookings</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="text-warning"><?php echo $booking_stats['pending'] ?></h2>
                <div class="text-muted">Pending Bookings</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="text-success"><?php echo $booking_stats['confirmed'] ?></h2>
                <div class="text-muted">Confirmed Bookings</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="text-danger"><?php echo $booking_stats['cancelled'] + $booking_stats['rejected'] ?></h2>
                <div class="text-muted">Cancelled / Rejected</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2><?php echo $customer_count ?></h2>
                <div class="text-muted">Total Customers</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2><?php echo $new_messages ?></h2>
                <div class="text-muted">New Contact Messages</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100 bg-light">
            <div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted">Confirmed Revenue</span><strong>$<?php echo number_format($revenue['confirmed_revenue'], 2) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Collected</span><strong class="text-success">$<?php echo number_format($revenue['collected'], 2) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Outstanding</span><strong class="text-danger">$<?php echo number_format($revenue['outstanding'], 2) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>Revenue &amp; Bookings (last 6 months)</h5>
                <canvas id="revenueChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>Bookings by Status</h5>
                <canvas id="bookingStatusChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Occupancy by Room Type (today)</h5>
                    <a href="reports.php#occupancy" class="btn btn-sm btn-outline-secondary">Full occupancy report &rarr;</a>
                </div>
                <canvas id="occupancyChart" height="90"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5>Quick Links</h5>
        <a href="room_types.php" class="btn btn-outline-primary mr-2 mb-2"><i class="fas fa-layer-group"></i> Room Types</a>
        <a href="rooms.php" class="btn btn-outline-primary mr-2 mb-2"><i class="fas fa-door-open"></i> Rooms</a>
        <a href="bookings.php" class="btn btn-outline-primary mr-2 mb-2"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="customers.php" class="btn btn-outline-primary mr-2 mb-2"><i class="fas fa-users"></i> Customers</a>
        <a href="payments.php" class="btn btn-outline-primary mr-2 mb-2"><i class="fas fa-money-bill-wave"></i> Payments</a>
        <a href="messages.php" class="btn btn-outline-primary mr-2 mb-2"><i class="fas fa-envelope"></i> Messages</a>
        <a href="reports.php" class="btn btn-outline-primary mb-2"><i class="fas fa-chart-line"></i> Reports</a>
    </div>
</div>

<script>
var revenueTrend = <?php echo json_encode($revenue_trend) ?>;
var bookingStats = <?php echo json_encode($booking_stats) ?>;
var occupancy = <?php echo json_encode($occupancy_today) ?>;

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: revenueTrend.map(function (r) { return r.label; }),
        datasets: [
            {
                type: 'line',
                label: 'Revenue ($)',
                data: revenueTrend.map(function (r) { return r.revenue; }),
                borderColor: '#007bff',
                backgroundColor: 'rgba(0,123,255,0.1)',
                yAxisID: 'y',
                tension: 0.3
            },
            {
                type: 'bar',
                label: 'Bookings',
                data: revenueTrend.map(function (r) { return r.bookings; }),
                backgroundColor: 'rgba(40,167,69,0.5)',
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        scales: {
            y:  { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Revenue ($)' } },
            y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Bookings' } }
        }
    }
});

new Chart(document.getElementById('bookingStatusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Confirmed', 'Rejected', 'Cancelled'],
        datasets: [{
            data: [bookingStats.pending, bookingStats.confirmed, bookingStats.rejected, bookingStats.cancelled],
            backgroundColor: ['#ffc107', '#28a745', '#dc3545', '#6c757d']
        }]
    }
});

new Chart(document.getElementById('occupancyChart'), {
    type: 'bar',
    data: {
        labels: occupancy.map(function (o) { return o.name; }),
        datasets: [{
            label: 'Occupancy %',
            data: occupancy.map(function (o) { return o.rate; }),
            backgroundColor: '#17a2b8'
        }]
    },
    options: {
        indexAxis: 'y',
        scales: { x: { beginAtZero: true, max: 100, title: { display: true, text: '% booked' } } }
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
