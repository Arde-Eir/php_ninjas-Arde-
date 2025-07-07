<?php
/**
 * Movie listing component - reusable across index.php and movies.php
 */
function renderMovieList($movies, $basePath = '') {
    if (!$movies || $movies->num_rows === 0) {
        echo '<div class="no-movies">No movies found.</div>';
        return;
    }
    
    echo '<div class="movie-list">';
    while ($movie = $movies->fetch_assoc()) {
        $posterUrl = $basePath ? $basePath . $movie['poster_url'] : $movie['poster_url'];
        $viewLink = $basePath ? 'viewMovie.php?movie_id=' . $movie['id'] : 'user/viewMovie.php?movie_id=' . $movie['id'];
        
        echo '<a href="' . e($viewLink) . '" style="text-decoration: none; color: inherit;">';
        echo '<div class="movies-card">';
        echo '<div class="poster-section">';
        echo '<img src="' . e($posterUrl) . '" alt="' . e($movie['title']) . '">';
        echo '</div>';
        echo '<div class="title-section">';
        echo '<h3>' . e($movie['title']) . '</h3>';
        echo '</div>';
        echo '</div>';
        echo '</a>';
    }
    echo '</div>';
}

/**
 * Movie status toggle component
 */
function renderMovieStatusToggle($currentView, $basePath = '') {
    $nowShowingClass = ($currentView === 'now-showing') ? 'toggle-active' : 'toggle-inactive';
    $comingSoonClass = ($currentView === 'coming-soon') ? 'toggle-active' : 'toggle-inactive';
    $nowShowingLink = ($currentView === 'now-showing') ? 'span' : 'a href="?view=now-showing"';
    $comingSoonLink = ($currentView === 'coming-soon') ? 'span' : 'a href="?view=coming-soon"';
    
    echo '<h2>';
    echo '<' . $nowShowingLink . ' class="' . $nowShowingClass . '">Now Showing</' . ($nowShowingLink === 'span' ? 'span' : 'a') . '>';
    echo '<span class="toggle-divider">|</span>';
    echo '<' . $comingSoonLink . ' class="' . $comingSoonClass . '">Coming Soon</' . ($comingSoonLink === 'span' ? 'span' : 'a') . '>';
    echo '</h2>';
}

/**
 * Dashboard booking card component
 */
function renderBookingCard($booking, $showCancelButton = false, $isPastBooking = false) {
    $cardClass = 'booking-card' . ($isPastBooking ? ' past-booking' : '');
    
    echo '<div class="' . $cardClass . '">';
    echo '<div class="booking-poster">';
    echo '<img src="' . e($booking['poster_url']) . '" alt="' . e($booking['title']) . '">';
    echo '</div>';
    echo '<div class="booking-details">';
    echo '<h3>' . e($booking['title']) . '</h3>';
    echo '<p>';
    echo '<strong>Date:</strong> ' . formatDate($booking['show_date']) . '<br>';
    echo '<strong>Time:</strong> ' . formatTime($booking['show_time']) . '<br>';
    echo '<strong>Seats:</strong> ' . e($booking['seats'] ?? $booking['seats_booked'] ?? 'N/A') . '<br>';
    echo '<strong>Amount:</strong> ' . formatCurrency($booking['total_amount']) . '<br>';
    
    if (!$isPastBooking) {
        echo '<strong>Status:</strong> ';
        echo '<span class="booking-status ' . strtolower($booking['booking_status']) . '">';
        echo ucfirst($booking['booking_status']);
        echo '</span>';
    }
    
    echo '</p>';
    
    if ($showCancelButton && $booking['booking_status'] !== 'cancelled' && !$isPastBooking) {
        $cancelLink = getLinkPath('user/cancelBooking.php');
        echo '<a href="' . $cancelLink . '?id=' . $booking['id'] . '" class="button cancel-button">Cancel Booking</a>';
    }
    
    echo '</div>';
    echo '</div>';
}

/**
 * Admin sidebar component
 */
function renderAdminSidebar($activePage = '') {
    $menuItems = [
        'dashboard.php' => ['Dashboard', 'fas fa-tachometer-alt'],
        'manageMovies.php' => ['Movies', 'fas fa-film'],
        '#users' => ['Users', 'fas fa-users'],
        'adminBookings.php' => ['Bookings', 'fas fa-ticket-alt'],
        '#showtimes' => ['Showtimes', 'fas fa-calendar-alt'],
        '#reports' => ['Reports', 'fas fa-chart-bar'],
        '../logout.php' => ['Logout', 'fas fa-sign-out-alt']

    ];
    
    echo '<aside class="admin-sidebar">';
    echo '<div class="admin-logo"><h2>Cinema Admin</h2></div>';
    echo '<ul class="sidebar-menu">';
    
    foreach ($menuItems as $link => $item) {
        $activeClass = (basename($_SERVER['PHP_SELF']) === basename($link)) ? ' class="active"' : '';
        echo '<li><a href="' . e($link) . '"' . $activeClass . '>';
        echo '<i class="' . $item[1] . '"></i>';
        echo '<span>' . $item[0] . '</span>';
        echo '</a></li>';
    }
    
    echo '</ul>';
    echo '</aside>';
}
?>
