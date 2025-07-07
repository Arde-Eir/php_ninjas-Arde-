<?php
// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once 'db.php';

// Include helper functions
include_once 'helpers.php';

// Include service classes
include_once 'movieService.php';
include_once 'userService.php';
include_once 'bookingService.php';
include_once 'adminService.php';

// Include reusable components
include_once 'components.php';

// Initialize services
$movieService = new MovieService($conn);
$userService = new UserService($conn);
$bookingService = new BookingService($conn);
$adminService = new AdminService($conn);

// Set base URL for links
$base_url = '/php_ninjas/';

