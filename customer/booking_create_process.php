<?php
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../bookings_functions.php';
require_once __DIR__ . '/../payments_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_is_logged_in()) {
    customer_json(false, 'Please log in to book a room.', ['require_login' => true]);
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$category_id = (int)($_POST['category_id'] ?? 0);
$date_in     = rooms_parse_date($_POST['date_in'] ?? '', '');
$date_out    = rooms_parse_date($_POST['date_out'] ?? '', '');
$guests      = isset($_POST['guests']) ? (int)$_POST['guests'] : 0;

if ($category_id <= 0) {
    customer_json(false, 'Please choose a room.');
}
if ($date_in === '' || $date_out === '') {
    customer_json(false, 'Please provide valid check-in and check-out dates.');
}

// Server-side date validation - never trust the client here even
// though book_form.php also checks; this endpoint can be hit directly.
$date_error = booking_validate_dates($date_in, $date_out);
if ($date_error) {
    customer_json(false, $date_error);
}

if ($guests < 1) {
    $guests = 1;
}
if ($guests > 20) {
    customer_json(false, 'Please enter a realistic number of guests.');
}

// Payment method: only the methods this build actually supports are
// ever accepted, regardless of what the client sends.
$allowed_payment_methods = payment_customer_methods();
$payment_method = customer_clean($_POST['payment_method'] ?? 'cash_on_arrival');
if (!array_key_exists($payment_method, $allowed_payment_methods)) {
    $payment_method = 'cash_on_arrival';
}

// Optional reference number (e.g. a bank transfer already made). Not
// required at booking time - the customer can also add/update it
// later from the booking confirmation page while it's still pending.
$payment_reference = customer_clean($_POST['reference_no'] ?? '');
if (mb_strlen($payment_reference) > 100) {
    $payment_reference = mb_substr($payment_reference, 0, 100);
}

// Look up the room type fresh from the database - price and capacity
// always come from here, never from the browser.
$stmt = $conn->prepare("SELECT * FROM room_categories WHERE id = ? LIMIT 1");
if ($stmt === false) {
    customer_json(false, 'Database query failed: ' . $conn->error);
}
$stmt->bind_param('i', $category_id);
$stmt->execute();
$room_type = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room_type) {
    customer_json(false, 'This room type could not be found.');
}
if ($guests > (int)$room_type['capacity']) {
    customer_json(false, 'This room type sleeps up to ' . (int)$room_type['capacity'] . ' guests.');
}

// Look up the logged-in customer's own contact details - never trust
// a name/phone submitted from the browser for who's booking.
$stmt = $conn->prepare("SELECT id, full_name, phone FROM customers WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $_SESSION['customer_id']);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    customer_json(false, 'Your account could not be found. Please log in again.');
}

$nights = rooms_nights($date_in, $date_out);
$total_price = booking_calculate_total($room_type['price'], $nights);

// ---- Transaction-guarded room assignment to prevent double booking ----
$conn->begin_transaction();
try {
    $room_id = booking_lock_available_room($conn, $category_id, $date_in, $date_out);

    if ($room_id === null) {
        $conn->rollback();
        customer_json(false, 'Sorry, this room type just became fully booked for those dates. Please try different dates.');
    }

    $ref_no = booking_generate_ref_no($conn);
    $date_in_dt  = $date_in . ' 00:00:00';
    $date_out_dt = $date_out . ' 00:00:00';

    $stmt = $conn->prepare(
        "INSERT INTO checked
            (ref_no, room_id, name, contact_no, date_in, date_out, booked_cid, guests, nights, total_price, status, booking_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'pending')"
    );
    // Column order: ref_no(s) room_id(i) name(s) contact_no(s) date_in(s)
    // date_out(s) booked_cid(i) guests(i) nights(i) total_price(d)
    $stmt->bind_param(
        'sissssiiid',
        $ref_no,
        $room_id,
        $customer['full_name'],
        $customer['phone'],
        $date_in_dt,
        $date_out_dt,
        $customer['id'],
        $guests,
        $nights,
        $total_price
    );

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        customer_json(false, 'Could not create your booking. Please try again.');
    }
    $stmt->close();

    $booking_id = $conn->insert_id;

    // One payment record per booking, created pending right away so
    // there's always something for the admin to verify and something
    // for the confirmation page to display - no separate "checkout"
    // step to forget. See payments_functions.php for the future
    // online-gateway integration point this is designed around.
    payment_create_pending($conn, $booking_id, $total_price, $payment_method, $payment_reference);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    customer_json(false, 'Could not create your booking. Please try again.');
}

customer_json(true, 'Your booking request (#' . $ref_no . ') has been submitted and is awaiting approval.', [
    'redirect' => 'index.php?page=booking-details&ref=' . urlencode($ref_no),
]);
