<?php
/**
 * Central database connection for the Hotel Management System.
 * InfinityFree configuration.
 */

// ---------------------------------------------------------------
// InfinityFree Database Settings
// ---------------------------------------------------------------
if (!defined('DB_HOST')) {
    define('DB_HOST', 'sql213.infinityfree.com');
    define('DB_USER', 'if0_42720549');
    define('DB_PASS', 'DKLuKkF6deaBGN');
    define('DB_NAME', 'if0_42720549_hotel_db');
    define('DB_PORT', 3306);
}

// Avoid reconnecting if this file is included more than once.
if (isset($conn) && $conn instanceof mysqli) {
    return;
}

mysqli_report(MYSQLI_REPORT_OFF);

// ---------------------------------------------------------------
// Connect to InfinityFree MySQL
// ---------------------------------------------------------------
$conn = mysqli_connect(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT
);

// ---------------------------------------------------------------
// Connection Error
// ---------------------------------------------------------------
if (!$conn) {
    die(
        'Database connection failed: ' .
        mysqli_connect_error() .
        '<br><br>' .
        'Please check the InfinityFree MySQL database settings.'
    );
}

// UTF-8 support
mysqli_set_charset($conn, 'utf8mb4');

// ---------------------------------------------------------------
// Database Schema Check
// ---------------------------------------------------------------
if (!defined('HMS_SCHEMA_CHECKED')) {
    define('HMS_SCHEMA_CHECKED', true);

    $hms_table_exists = function ($table) use ($conn) {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        return $res && $res->num_rows > 0;
    };

    $hms_column_exists = function ($table, $column) use ($conn) {
        $table = str_replace('`', '', $table);
        $column = $conn->real_escape_string($column);

        $res = $conn->query(
            "SHOW COLUMNS FROM `$table` LIKE '$column'"
        );

        return $res && $res->num_rows > 0;
    };

    $hms_migration_checks = [
        'database/customers_update.sql' =>
            ['table' => 'customers'],

        'database/bookings_update.sql' =>
            ['table' => 'checked', 'column' => 'booking_status'],

        'database/rooms_management_update.sql' =>
            ['table' => 'room_category_images'],

        'database/admin_dashboard_update.sql' =>
            ['table' => 'contact_messages'],

        'database/payment_confirmation_update.sql' =>
            ['table' => 'payments', 'column' => 'status'],

        'database/staff_management_update.sql' =>
            ['table' => 'users', 'column' => 'status'],
    ];

    $hms_missing = [];

    foreach ($hms_migration_checks as $file => $check) {

        if (!$hms_table_exists($check['table'])) {
            $hms_missing[] = $file;
            continue;
        }

        if (
            isset($check['column']) &&
            !$hms_column_exists(
                $check['table'],
                $check['column']
            )
        ) {
            $hms_missing[] = $file;
        }
    }

    // -----------------------------------------------------------
    // Show missing migration information
    // -----------------------------------------------------------
    if (!empty($hms_missing)) {

        $hms_items = '';

        foreach ($hms_missing as $file) {
            $hms_items .=
                '<li><code>' .
                htmlspecialchars(
                    $file,
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</code></li>';
        }

        die(
            '<div style="
                font-family:Arial,Helvetica,sans-serif;
                max-width:700px;
                margin:60px auto;
                padding:24px;
                border:1px solid #f5c2c7;
                background:#f8d7da;
                border-radius:8px;
                color:#58151c;
            ">
                <h2 style="margin-top:0;">
                    Database setup is incomplete
                </h2>

                <p>
                    The InfinityFree database
                    <code>' .
                    htmlspecialchars(
                        DB_NAME,
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    '</code>
                    is missing these database migrations:
                </p>

                <ul>' .
                $hms_items .
                '</ul>

                <p>
                    Open phpMyAdmin on InfinityFree,
                    select your database, click
                    <b>Import</b>, and import the missing
                    SQL files from your project.
                </p>
            </div>'
        );
    }
}
?>