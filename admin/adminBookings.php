<?php

include '../includes/init.php';

requireAdmin();  
// Use the BookingService instance from init.php ($bookingService)

// filter
$statusFilter = $_GET['status'] ?? '';
$searchTerm = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

//filter options
$validStatuses = ['pending', 'confirmed', 'cancelled'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = '';
}

$limit = 10;
$offset = ($page - 1) * $limit;

// Get total bookings count and bookings for current page
$totalCount = $bookingService->getFilteredBookingsCount($statusFilter, $searchTerm);
$totalPages = ceil($totalCount / $limit);
$bookings = $bookingService->getFilteredBookings($statusFilter, $searchTerm, $limit, $offset);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>All Bookings - Admin Panel</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    .booking-status.confirmed { color: green; font-weight: bold; }
    .booking-status.cancelled { color: red; font-weight: bold; }
    .booking-status.pending { color: orange; font-weight: bold; }

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
      background-color: rgb(233, 28, 28);
      color: white;
    }
    .info-message {
      margin-top: 20px;
      padding: 15px;
      background-color: #eee;
      border: 1px solid #ccc;
      text-align: center;
    }
    .pagination {
      margin-top: 20px;
      text-align: center;
    }
    .pagination a, .pagination span {
      display: inline-block;
      padding: 8px 12px;
      margin: 0 4px;
      border: 1px solid #ccc;
      color: #333;
      text-decoration: none;
      border-radius: 4px;
    }
    .pagination a:hover {
      background-color: #f44336;
      color: white;
      border-color: #f44336;
    }
    .pagination .current-page {
      background-color: #f44336;
      color: white;
      border-color: #f44336;
      pointer-events: none;
    }
    form.filter-form {
      margin-top: 20px;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 15px;
      flex-wrap: wrap;
    }
    form.filter-form label {
      font-weight: bold;
    }
    form.filter-form input[type="text"], form.filter-form select {
      padding: 5px 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    form.filter-form button {
      padding: 6px 15px;
      background-color: #f44336;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    form.filter-form button:hover {
      background-color: #d32f2f;
    }
  </style>
</head>
<body>
<div class="admin-wrapper">
  <?php renderAdminSidebar('adminBookings.php'); ?>

  <main class="admin-content">
    <div class="admin-header">
      <div class="admin-title"><h1>All Bookings</h1></div>
      <div class="admin-user">
        <span>Welcome, <?php echo e($_SESSION['display_name'] ?? $_SESSION['username']); ?></span>
        <span><?php echo date('F j, Y'); ?></span>
      </div>
    </div>

    <div class="admin-section">
      <div class="admin-section-header"><h2>Booked & Cancelled Seats</h2></div>

      <!--Filter Form -->
      <form method="get" class="filter-form" action="adminBookings.php" aria-label="Filter bookings">
        <label for="status">Status:</label>
        <select name="status" id="status">
          <option value="" <?= $statusFilter === '' ? 'selected' : ''; ?>>All</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
          <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>

        <label for="search">Search:</label>
        <input
          type="text"
          name="search"
          id="search"
          value="<?php echo e($searchTerm); ?>"
          placeholder="Username or Movie Title"
          aria-label="Search bookings"
        />

        <button type="submit"><i class="fa fa-filter" aria-hidden="true"></i> Filter</button>
      </form>

      <!-- Bookings Table -->
      <?php if ($bookings && $bookings->num_rows > 0): ?>
        <div class="table-container" role="region" aria-live="polite" aria-label="Bookings table">
          <table class="data-table" aria-describedby="bookings-description">
            <caption id="bookings-description" class="sr-only">List of all bookings with filters applied</caption>
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
              <?php while ($row = $bookings->fetch_assoc()): ?>
                <tr>
                  <td><?php echo e($row['username']); ?></td>
                  <td><?php echo e($row['title']); ?></td>
                  <td><?php echo formatDate($row['show_date']); ?></td>
                  <td><?php echo e($row['seats']); ?></td>
                  <td><?php echo formatCurrency($row['total_amount']); ?></td>
                  <td>
                    <span class="booking-status <?php echo e($row['booking_status']); ?>">
                      <?php echo ucfirst(e($row['booking_status'])); ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <nav class="pagination" role="navigation" aria-label="Pagination Navigation">
            <?php
              $queryBase = $_GET;
              for ($p = 1; $p <= $totalPages; $p++):
                $queryBase['page'] = $p;
                $url = 'adminBookings.php?' . http_build_query($queryBase);
            ?>
              <?php if ($p == $page): ?>
                <span class="current-page" aria-current="page"><?php echo $p; ?></span>
              <?php else: ?>
                <a href="<?php echo e($url); ?>"><?php echo $p; ?></a>
              <?php endif; ?>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>

      <?php else: ?>
        <div class="info-message" role="alert">No bookings found.</div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
