<?php
/**
 * admin/dashboard_functions.php
 *
 * Stat/report queries shared by admin/index.php (the dashboard) and
 * admin/reports.php. Kept in one place so the two pages can never
 * disagree about what "confirmed bookings" or "revenue" means.
 *
 * Definitions used throughout:
 *   - Room "occupied" = has a `checked` row with status = 1 (currently
 *     checked in) RIGHT NOW. "Available" = enabled (rooms.status = 0)
 *     and not currently occupied. This is a snapshot for "right now",
 *     not for any particular future date range (list.php/room-details.php
 *     already handle date-range availability for the booking flow).
 *   - Booking "Confirmed" = checked.booking_status = 'approved' (the
 *     task's wording; the schema's own enum calls this "approved").
 *     A booking stays 'approved' through check-in/check-out - only the
 *     separate `status` column changes - so this one condition already
 *     covers not-yet-arrived, checked-in, AND checked-out stays.
 *   - "Revenue" = SUM(total_price) for confirmed (approved) bookings -
 *     i.e. money the hotel is owed/has earned from stays that actually
 *     happened or will happen, excluding pending/rejected/cancelled.
 *   - "Collected" = SUM(payments.amount), the money actually recorded
 *     as received via the Payments screen. "Outstanding" is the gap
 *     between the two.
 */

require_once __DIR__ . '/../bookings_functions.php';

/** @return array{total:int,disabled:int,occupied:int,available:int} */
function dash_room_stats(mysqli $conn) {
    $total = (int)$conn->query("SELECT COUNT(*) c FROM rooms")->fetch_assoc()['c'];
    $disabled = (int)$conn->query("SELECT COUNT(*) c FROM rooms WHERE status = 1")->fetch_assoc()['c'];
    $occupied = (int)$conn->query("SELECT COUNT(DISTINCT room_id) c FROM checked WHERE status = 1")->fetch_assoc()['c'];
    $available = max(0, $total - $disabled - $occupied);
    return ['total' => $total, 'disabled' => $disabled, 'occupied' => $occupied, 'available' => $available];
}

/** @return array{total:int,pending:int,confirmed:int,rejected:int,cancelled:int} */
function dash_booking_stats(mysqli $conn) {
    $row = $conn->query(
        "SELECT
            COUNT(*) AS total,
            SUM(booking_status = 'pending')   AS pending,
            SUM(booking_status = 'approved')  AS confirmed,
            SUM(booking_status = 'rejected')  AS rejected,
            SUM(booking_status = 'cancelled') AS cancelled
         FROM checked"
    )->fetch_assoc();
    return array_map(function ($v) { return (int)$v; }, $row);
}

function dash_customer_count(mysqli $conn) {
    return (int)$conn->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'];
}

function dash_new_message_count(mysqli $conn) {
    return (int)$conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status = 'new'")->fetch_assoc()['c'];
}

/** @return array{confirmed_revenue:float,collected:float,outstanding:float} */
function dash_revenue_summary(mysqli $conn) {
    $confirmed_revenue = (float)$conn->query(
        "SELECT COALESCE(SUM(total_price),0) s FROM checked WHERE booking_status = 'approved'"
    )->fetch_assoc()['s'];
    $collected = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments")->fetch_assoc()['s'];
    $outstanding = max(0, $confirmed_revenue - $collected);
    return ['confirmed_revenue' => $confirmed_revenue, 'collected' => $collected, 'outstanding' => $outstanding];
}

/**
 * Revenue + confirmed-booking-count per month for the last $months
 * months (including the current one), oldest first. Always returns
 * exactly $months rows, filling in zero for months with no data, so
 * charts don't have to handle gaps themselves.
 *
 * @return array<int, array{label:string, revenue:float, bookings:int}>
 */
function dash_revenue_trend(mysqli $conn, $months = 6) {
    $months = max(1, (int)$months);
    $start = date('Y-m-01', strtotime("-" . ($months - 1) . " months"));

    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(date_in, '%Y-%m') AS ym,
                COALESCE(SUM(total_price), 0) AS revenue,
                COUNT(*) AS bookings
         FROM checked
         WHERE booking_status = 'approved' AND date_in >= ?
         GROUP BY ym"
    );
    $stmt->bind_param('s', $start);
    $stmt->execute();
    $res = $stmt->get_result();
    $by_month = [];
    while ($row = $res->fetch_assoc()) {
        $by_month[$row['ym']] = $row;
    }
    $stmt->close();

    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("-$i months");
        $ym = date('Y-m', $ts);
        $row = $by_month[$ym] ?? null;
        $out[] = [
            'label'    => date('M Y', $ts),
            'revenue'  => $row ? (float)$row['revenue'] : 0.0,
            'bookings' => $row ? (int)$row['bookings'] : 0,
        ];
    }
    return $out;
}

/**
 * New customer signups per month for the last $months months,
 * oldest first, zero-filled like dash_revenue_trend().
 *
 * @return array<int, array{label:string, count:int}>
 */
function dash_customer_signup_trend(mysqli $conn, $months = 6) {
    $months = max(1, (int)$months);
    $start = date('Y-m-01', strtotime("-" . ($months - 1) . " months"));

    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
         FROM customers WHERE created_at >= ? GROUP BY ym"
    );
    $stmt->bind_param('s', $start);
    $stmt->execute();
    $res = $stmt->get_result();
    $by_month = [];
    while ($row = $res->fetch_assoc()) {
        $by_month[$row['ym']] = (int)$row['c'];
    }
    $stmt->close();

    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("-$i months");
        $ym = date('Y-m', $ts);
        $out[] = ['label' => date('M Y', $ts), 'count' => $by_month[$ym] ?? 0];
    }
    return $out;
}

/**
 * Occupancy rate per room type: enabled rooms in that type vs. how
 * many of that type's room-nights are currently booked (approved,
 * not-yet-checked-out) within [from, to). A simple, honest measure:
 * (booked room-nights) / (enabled rooms x nights in range).
 *
 * @return array<int, array{name:string, enabled_rooms:int, booked_nights:int, capacity_nights:int, rate:float}>
 */
function dash_occupancy_by_type(mysqli $conn, $from, $to) {
    $nights_in_range = max(1, (int)round((strtotime($to) - strtotime($from)) / 86400));

    $types = $conn->query("SELECT id, name FROM room_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $out = [];

    foreach ($types as $t) {
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM rooms WHERE category_id = ? AND status = 0");
        $stmt->bind_param('i', $t['id']);
        $stmt->execute();
        $enabled_rooms = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        // Sum, per overlapping booking, the number of nights that fall inside [from, to).
        $stmt = $conn->prepare(
            "SELECT c.date_in, c.date_out FROM checked c
             INNER JOIN rooms r ON r.id = c.room_id
             WHERE r.category_id = ?
               AND c.booking_status = 'approved'
               AND DATE(c.date_in) < ? AND DATE(c.date_out) > ?"
        );
        $stmt->bind_param('iss', $t['id'], $to, $from);
        $stmt->execute();
        $res = $stmt->get_result();
        $booked_nights = 0;
        while ($row = $res->fetch_assoc()) {
            $overlap_start = max(strtotime($from), strtotime(date('Y-m-d', strtotime($row['date_in']))));
            $overlap_end   = min(strtotime($to),   strtotime(date('Y-m-d', strtotime($row['date_out']))));
            $booked_nights += max(0, (int)round(($overlap_end - $overlap_start) / 86400));
        }
        $stmt->close();

        $capacity_nights = $enabled_rooms * $nights_in_range;
        $rate = $capacity_nights > 0 ? min(100, round(($booked_nights / $capacity_nights) * 100, 1)) : 0.0;

        $out[] = [
            'name' => $t['name'],
            'enabled_rooms' => $enabled_rooms,
            'booked_nights' => $booked_nights,
            'capacity_nights' => $capacity_nights,
            'rate' => $rate,
        ];
    }
    return $out;
}

/**
 * Confirmed (approved-booking) revenue and booking count per room
 * type, highest revenue first.
 *
 * @return array<int, array{name:string, bookings:int, revenue:float}>
 */
function dash_revenue_by_type(mysqli $conn) {
    $sql = "SELECT rc.name AS name, COUNT(c.id) AS bookings, COALESCE(SUM(c.total_price), 0) AS revenue
            FROM room_categories rc
            LEFT JOIN rooms r ON r.category_id = rc.id
            LEFT JOIN checked c ON c.room_id = r.id AND c.booking_status = 'approved'
            GROUP BY rc.id
            ORDER BY revenue DESC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

/**
 * Top customers by total confirmed booking spend, highest first.
 *
 * @return array<int, array{full_name:string, email:string, bookings:int, total_spent:float}>
 */
function dash_top_customers(mysqli $conn, $limit = 10) {
    $limit = max(1, (int)$limit);
    $sql = "SELECT cu.full_name, cu.email,
                   COUNT(ch.id) AS bookings,
                   COALESCE(SUM(ch.total_price), 0) AS total_spent
            FROM customers cu
            INNER JOIN checked ch ON ch.booked_cid = cu.id AND ch.booking_status = 'approved'
            GROUP BY cu.id
            ORDER BY total_spent DESC
            LIMIT $limit";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
