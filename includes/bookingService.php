<?php
/**
 * BookingService class for handling booking-related operations
 */
class BookingService {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Get showtimes for a movie
     */
    public function getShowtimes($movieId) {
        try {
            $stmt = $this->conn->prepare("SELECT id, show_date, show_time FROM showtimes WHERE movie_id = ? ORDER BY show_date, show_time");
            $stmt->bind_param("i", $movieId);
            $stmt->execute();
            return $stmt->get_result();
        } catch (Exception $e) {
            error_log("Error fetching showtimes: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get booked seats for a showtime (excluding cancelled bookings)
     */
    public function getBookedSeats($showtimeId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT bs.seat_number 
                FROM booked_seats bs
                JOIN bookings b ON bs.booking_id = b.id
                WHERE bs.showtime_id = ? AND b.booking_status != 'cancelled'
            ");
            $stmt->bind_param("i", $showtimeId);
            $stmt->execute();
            $result = $stmt->get_result();

            $bookedSeats = [];
            while ($row = $result->fetch_assoc()) {
                $bookedSeats[] = $row['seat_number'];
            }
            return $bookedSeats;
        } catch (Exception $e) {
            error_log("Error fetching booked seats: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if seats are available (with locking)
     */
    public function checkSeatAvailability($showtimeId, $seats) {
        try {
            $this->conn->begin_transaction();
            $this->conn->query("LOCK TABLES booked_seats READ, bookings READ");

            $unavailableSeats = [];
            $checkStmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM booked_seats bs
                JOIN bookings b ON bs.booking_id = b.id
                WHERE bs.showtime_id = ? AND bs.seat_number = ? AND b.booking_status != 'cancelled'
            ");

            foreach ($seats as $seat) {
                $checkStmt->bind_param("is", $showtimeId, $seat);
                $checkStmt->execute();
                $result = $checkStmt->get_result()->fetch_assoc();
                if ($result['count'] > 0) {
                    $unavailableSeats[] = $seat;
                }
            }

            $this->conn->query("UNLOCK TABLES");
            $this->conn->commit();

            return $unavailableSeats;
        } catch (Exception $e) {
            $this->conn->query("UNLOCK TABLES");
            $this->conn->rollback();
            error_log("Error checking seat availability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create booking with transaction safety
     */
    public function createBooking($userId, $movieId, $showtimeId, $seats, $totalAmount, $paymentMethod) {
        try {
            $this->conn->begin_transaction();

            $unavailableSeats = $this->checkSeatAvailability($showtimeId, $seats);
            if (!empty($unavailableSeats)) {
                throw new Exception("Seats no longer available: " . implode(", ", $unavailableSeats));
            }

            $showtimeStmt = $this->conn->prepare("SELECT show_date, show_time FROM showtimes WHERE id = ?");
            $showtimeStmt->bind_param("i", $showtimeId);
            $showtimeStmt->execute();
            $showtime = $showtimeStmt->get_result()->fetch_assoc();

            $bookingStmt = $this->conn->prepare("
                INSERT INTO bookings (user_id, movie_id, showtime_id, booking_date, show_time, 
                    seats_booked, total_amount, payment_method, payment_status, 
                    payment_reference, booking_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $paymentReference = generatePaymentReference();
            $bookingDate = date('Y-m-d');
            $seatCount = count($seats);
            $paymentStatus = 'completed';
            $bookingStatus = 'confirmed';

            $bookingStmt->bind_param(
                "iiissidssss",
                $userId, $movieId, $showtimeId, $bookingDate, $showtime['show_time'],
                $seatCount, $totalAmount, $paymentMethod, $paymentStatus,
                $paymentReference, $bookingStatus
            );

            $bookingStmt->execute();
            $bookingId = $this->conn->insert_id;

            $seatStmt = $this->conn->prepare("
                INSERT INTO booked_seats (showtime_id, seat_number, user_id, booking_id)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($seats as $seatNumber) {
                $seatStmt->bind_param("isii", $showtimeId, $seatNumber, $userId, $bookingId);
                $seatStmt->execute();
            }

            $this->conn->commit();
            return $bookingId;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error creating booking: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel booking
     */
    public function cancelBooking($bookingId, $userId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE bookings 
                SET booking_status = 'cancelled' 
                WHERE id = ? AND user_id = ? AND booking_status != 'cancelled'
            ");
            $stmt->bind_param("ii", $bookingId, $userId);
            return $stmt->execute() && $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log("Error cancelling booking: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all bookings (booked and cancelled) for admin panel
     */
    public function getAllBookingsWithStatus() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    b.id,
                    u.username,
                    m.title,
                    s.show_date,
                    GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seats,
                    b.total_amount,
                    b.booking_status
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN movies m ON b.movie_id = m.id
                JOIN showtimes s ON b.showtime_id = s.id
                LEFT JOIN booked_seats bs ON bs.booking_id = b.id
                GROUP BY b.id
                ORDER BY b.created_at DESC
            ");
            $stmt->execute();
            return $stmt->get_result();
        } catch (Exception $e) {
            error_log("Error fetching bookings with status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total count of bookings with optional filters
     */
    public function getFilteredBookingsCount($status, $search) {
        $sql = "
            SELECT COUNT(DISTINCT b.id) as total
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN movies m ON b.movie_id = m.id
            JOIN showtimes s ON b.showtime_id = s.id
            LEFT JOIN booked_seats bs ON bs.booking_id = b.id
            WHERE 1=1
        ";
        $params = [];
        $types = '';

        if ($status) {
            $sql .= " AND b.booking_status = ?";
            $types .= 's';
            $params[] = $status;
        }
        if ($search) {
            $sql .= " AND (u.username LIKE ? OR m.title LIKE ?)";
            $types .= 'ss';
            $searchWildcard = "%$search%";
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return 0;
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)$row['total'];
    }

    /**
     * Get filtered bookings with pagination
     */
    public function getFilteredBookings($status, $search, $limit, $offset) {
        $sql = "
            SELECT 
                b.id,
                u.username,
                m.title,
                s.show_date,
                GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seats,
                b.total_amount,
                b.booking_status
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN movies m ON b.movie_id = m.id
            JOIN showtimes s ON b.showtime_id = s.id
            LEFT JOIN booked_seats bs ON bs.booking_id = b.id
            WHERE 1=1
        ";
        $params = [];
        $types = '';

        if ($status) {
            $sql .= " AND b.booking_status = ?";
            $types .= 's';
            $params[] = $status;
        }
        if ($search) {
            $sql .= " AND (u.username LIKE ? OR m.title LIKE ?)";
            $types .= 'ss';
            $searchWildcard = "%$search%";
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
        }

        $sql .= " GROUP BY b.id ORDER BY b.created_at DESC LIMIT ? OFFSET ?";

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }
}
?>
