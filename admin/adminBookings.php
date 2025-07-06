<?php
include '../includes/init.php';
requireAdmin();
require_once '../services/BookingService.php'; // adjust path if needed

$bookingService = new BookingService($conn);
$bookings = $bookingService->getAllBookingsWithStatus();

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>All Bookings - Admin Panel</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .booking-status.confirmed {
      color: green;
      font-weight: bold;
    }
    .booking-status.cancelled {
      color: red;
      font-weight: bold;
    }
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .data-table th, .data-table td {
      padding: 10px;
      border: 1px solid #ccc;
      text-align: center;
    }
    .data-table thead {
      background-color: #f3f3f3;
    }
    .info-message {
      margin-top: 20px;
      padding: 15px;
      background-color: #eee;
      border: 1px solid #ccc;
      text-align: center;
    }
  </style>
</head>
<body>
<div class="admin-wrapper">
  <?php renderAdminSidebar('adminBookings.php'); ?>

  <main class="admin-content">
    <div class="admin-header">
      <div class="admin-title">
        <h1>All Bookings</h1>
      </div>
      <div class="admin-user">
        <span>Welcome, <?php echo e($_SESSION['display_name'] ?? $_SESSION['username']); ?></span>
        <span><?php echo date('F j, Y'); ?></span>
      </div>
    </div>

    <div class="admin-section">
      <div class="admin-section-header">
        <h2>Booked & Cancelled Seats</h2>
      </div>

      <?php if ($bookings && $bookings->num_rows > 0): ?>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Movie</th>
                <th>Date</th>
                <th>Seats</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = $bookings->fetch_assoc()): ?>
                <tr>
                  <td><?php echo e($row['username']); ?></td>
                  <td><?php echo e($row['title']); ?></td>
                  <td><?php echo formatDate($row['show_date']); ?></td>
                  <td><?php echo e($row['seats']); ?></td>
                  <td><?php echo formatCurrency($row['total_amount']); ?></td>
                  <td>
                    <span class="booking-status <?php echo $row['booking_status'] === 'cancelled' ? 'cancelled' : 'confirmed'; ?>">
                      <?php echo ucfirst($row['booking_status']); ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="info-message">No bookings found.</div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
