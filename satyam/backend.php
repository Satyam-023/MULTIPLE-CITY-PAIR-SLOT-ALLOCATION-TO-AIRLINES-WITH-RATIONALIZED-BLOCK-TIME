<?php
// Enable CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// For preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'skyconnect_db';
$username = 'root'; // Change to your MySQL username
$password = ''; // Change to your MySQL password

// Connect to database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Process different endpoints
$endpoint = isset($_GET['action']) ? $_GET['action'] : '';

switch ($endpoint) {
    case 'search_flights':
        searchFlights($pdo);
        break;

    case 'book_flight':
        bookFlight($pdo);
        break;

    case 'get_bookings':
        getBookings($pdo);
        break;

    case 'cancel_booking':
        cancelBooking($pdo);
        break;

    case 'allocate_slots':
        allocateSlots($pdo);
        break;

    case 'get_dashboard_stats':
        getDashboardStats($pdo);
        break;

    case 'get_all_flights':
        getAllFlights($pdo);
        break;

    case 'get_all_slots':
        getAllSlots($pdo);
        break;

    case 'get_all_bookings':
        getAllBookings($pdo);
        break;

    case 'add_flight':
        addFlight($pdo);
        break;

    case 'update_flight':
        updateFlight($pdo);
        break;

    case 'delete_flight':
        deleteFlight($pdo);
        break;

    case 'get_flight':
        getFlight($pdo);
        break;

    case 'admin_cancel_booking':
        adminCancelBooking($pdo);
        break;

    case 'admin_restore_booking':
        adminRestoreBooking($pdo);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

// Function to search flights
function searchFlights($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input data
    if (!$data || !isset($data['origin']) || !isset($data['destination']) || !isset($data['departureDate'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        // Prepare SQL statement
        $stmt = $pdo->prepare("SELECT * FROM flights
            WHERE origin = :origin
            AND destination = :destination
            AND DATE(departure_date) = :departureDate");

        $stmt->bindParam(':origin', $data['origin']);
        $stmt->bindParam(':destination', $data['destination']);
        $stmt->bindParam(':departureDate', $data['departureDate']);
        $stmt->execute();

        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no flights found, return empty array
        if (empty($flights)) {
            echo json_encode([]);
            return;
        }

        // Format flights for frontend
        $formattedFlights = [];
        foreach ($flights as $flight) {
            $formattedFlights[] = [
                'id' => $flight['id'],
                'airline' => $flight['airline'],
                'departureTime' => substr($flight['departure_time'], 0, 5),
                'arrivalTime' => substr($flight['arrival_time'], 0, 5),
                'departureCity' => $flight['origin'],
                'arrivalCity' => $flight['destination'],
                'duration' => $flight['duration'],
                'stops' => $flight['stops'] == 0 ? 'Non-stop' : $flight['stops'] . ' stop(s)',
                'price' => $flight['price'],
                'blockTime' => $flight['block_time']
            ];
        }

        echo json_encode($formattedFlights);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to book a flight
function bookFlight($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input data
    if (!$data || !isset($data['flightId']) || !isset($data['firstName']) ||
        !isset($data['lastName']) || !isset($data['email']) || !isset($data['phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Create booking record
        $bookingId = 'BK' . time();
        $status = 'Confirmed';
        $bookingDate = date('Y-m-d');

        $stmt = $pdo->prepare("INSERT INTO bookings (id, flight_id, first_name, last_name, email, phone, status, booking_date)
            VALUES (:id, :flightId, :firstName, :lastName, :email, :phone, :status, :bookingDate)");

        $stmt->bindParam(':id', $bookingId);
        $stmt->bindParam(':flightId', $data['flightId']);
        $stmt->bindParam(':firstName', $data['firstName']);
        $stmt->bindParam(':lastName', $data['lastName']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':bookingDate', $bookingDate);
        $stmt->execute();

        // Commit transaction
        $pdo->commit();

        // Get flight details for response
        $stmt = $pdo->prepare("SELECT * FROM flights WHERE id = :flightId");
        $stmt->bindParam(':flightId', $data['flightId']);
        $stmt->execute();
        $flight = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return booking confirmation
        echo json_encode([
            'success' => true,
            'bookingId' => $bookingId,
            'flight' => [
                'id' => $flight['id'],
                'airline' => $flight['airline'],
                'departureTime' => substr($flight['departure_time'], 0, 5),
                'arrivalTime' => substr($flight['arrival_time'], 0, 5),
                'departureCity' => $flight['origin'],
                'arrivalCity' => $flight['destination']
            ],
            'passenger' => [
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName']
            ],
            'status' => $status,
            'date' => $bookingDate
        ]);

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to get user bookings
function getBookings($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input data
    if (!$data || !isset($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        // Get bookings for this user
        $stmt = $pdo->prepare("SELECT b.*, f.airline, f.origin, f.destination, f.departure_time,
                f.arrival_time, f.price
            FROM bookings b
            JOIN flights f ON b.flight_id = f.id
            WHERE b.email = :email
            ORDER BY b.booking_date DESC");

        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format bookings for frontend
        $formattedBookings = [];
        foreach ($bookings as $booking) {
            $formattedBookings[] = [
                'id' => $booking['id'],
                'flight' => [
                    'id' => $booking['flight_id'],
                    'airline' => $booking['airline'],
                    'departureTime' => substr($booking['departure_time'], 0, 5),
                    'arrivalTime' => substr($booking['arrival_time'], 0, 5),
                    'departureCity' => $booking['origin'],
                    'arrivalCity' => $booking['destination'],
                    'price' => $booking['price']
                ],
                'passenger' => [
                    'firstName' => $booking['first_name'],
                    'lastName' => $booking['last_name']
                ],
                'status' => $booking['status'],
                'date' => $booking['booking_date']
            ];
        }

        echo json_encode($formattedBookings);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to cancel a booking
function cancelBooking($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input data
    if (!$data || !isset($data['bookingId']) || !isset($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        // Update booking status to cancelled
        $stmt = $pdo->prepare("UPDATE bookings
            SET status = 'Cancelled'
            WHERE id = :bookingId AND email = :email");

        $stmt->bindParam(':bookingId', $data['bookingId']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Booking not found or not authorized']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to allocate slots
function allocateSlots($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input data
    if (!$data || !isset($data['cityPairs']) || !isset($data['maxBlockTime'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        $allocatedSlots = [];
        $airlines = ['SkyWays', 'Global Air', 'Air Express', 'StarJet'];

        foreach ($data['cityPairs'] as $index => $cityPair) {
            // Store slot allocation in database
            $slotId = 'SL' . (1000 + $index);
            $airline = $airlines[array_rand($airlines)];
            $blockTime = min(rand(120, 200), $data['maxBlockTime']);

            $stmt = $pdo->prepare("INSERT INTO slots
                (id, origin, destination, preferred_time, aircraft_type, airline, block_time)
                VALUES (:id, :origin, :destination, :preferredTime, :aircraftType, :airline, :blockTime)");

            $stmt->bindParam(':id', $slotId);
            $stmt->bindParam(':origin', $cityPair['origin']);
            $stmt->bindParam(':destination', $cityPair['destination']);
            $stmt->bindParam(':preferredTime', $cityPair['preferredTime']);
            $stmt->bindParam(':aircraftType', $cityPair['aircraftType']);
            $stmt->bindParam(':airline', $airline);
            $stmt->bindParam(':blockTime', $blockTime);
            $stmt->execute();

            // Add to response
            $allocatedSlots[] = [
                'id' => $slotId,
                'origin' => $cityPair['origin'],
                'destination' => $cityPair['destination'],
                'slotTime' => $cityPair['preferredTime'],
                'airline' => $airline,
                'aircraft' => $cityPair['aircraftType'] === 'narrow' ? 'Narrow-body' : 'Wide-body',
                'blockTime' => $blockTime
            ];
        }

        echo json_encode($allocatedSlots);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getDashboardStats($pdo) {
    try {
        // Get total flights
        $stmt = $pdo->query("SELECT COUNT(*) FROM flights");
        $totalFlights = $stmt->fetchColumn();

        // Get total slots
        $stmt = $pdo->query("SELECT COUNT(*) FROM slots");
        $totalSlots = $stmt->fetchColumn();

        // Get total bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
        $totalBookings = $stmt->fetchColumn();

        // Get total revenue
        $stmt = $pdo->query("SELECT SUM(f.price)
                            FROM bookings b
                            JOIN flights f ON b.flight_id = f.id
                            WHERE b.status = 'Confirmed'");
        $totalRevenue = $stmt->fetchColumn() ?: 0;

        echo json_encode([
            'totalFlights' => $totalFlights,
            'totalSlots' => $totalSlots,
            'totalBookings' => $totalBookings,
            'totalRevenue' => number_format($totalRevenue, 2, '.', '')
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
function getAllFlights($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM flights ORDER BY departure_date DESC, departure_time ASC");
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($flights);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
function getAllSlots($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM slots ORDER BY id DESC");
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($slots);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
function getAllBookings($pdo) {
    try {
        $stmt = $pdo->query("SELECT b.*, f.origin, f.destination
                            FROM bookings b
                            JOIN flights f ON b.flight_id = f.id
                            ORDER BY b.booking_date DESC");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($bookings);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Replace lines 411-451 with this corrected function

function addFlight($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        // Create a shorter flight ID
        // Use airline code (max 3 chars) + flight number (limited to 4 digits)
        $airlineCode = substr($data['airline'], 0, 3); // Take first 3 chars of airline
        $flightNumber = substr($data['flightNumber'], 0, 4); // Limit flight number to 4 digits
        $id = $airlineCode . $flightNumber;

        // Make sure the ID doesn't exceed 10 characters total
        $id = substr($id, 0, 10);

        $stmt = $pdo->prepare("INSERT INTO flights (
            id, airline, origin, destination, departure_date, departure_time, arrival_time,
            duration, block_time, stops, price, status
        ) VALUES (
            :id, :airline, :origin, :destination, :departureDate, :departureTime, :arrivalTime,
            :duration, :blockTime, :stops, :price, :status
        )");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':airline', $data['airline']);
        $stmt->bindParam(':origin', $data['origin']);
        $stmt->bindParam(':destination', $data['destination']);
        $stmt->bindParam(':departureDate', $data['departureDate']);
        $stmt->bindParam(':departureTime', $data['departureTime']);
        $stmt->bindParam(':arrivalTime', $data['arrivalTime']);
        $stmt->bindParam(':duration', $data['duration']);
        $stmt->bindParam(':blockTime', $data['blockTime']);
        $stmt->bindParam(':stops', $data['stops']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':status', $data['status']);

        $stmt->execute();

        echo json_encode(['success' => true, 'id' => $id]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateFlight($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }
    try {
        $stmt = $pdo->prepare("UPDATE flights SET
            airline = :airline,
            origin = :origin,
            destination = :destination,
            departure_date = :departureDate,
            departure_time = :departureTime,
            arrival_time = :arrivalTime,
            duration = :duration,
            block_time = :blockTime,
            stops = :stops,
            price = :price,
            status = :status
            WHERE id = :id");

        $stmt->bindParam(':id', $data['id']);
        $stmt->bindParam(':airline', $data['airline']);
        $stmt->bindParam(':origin', $data['origin']);
        $stmt->bindParam(':destination', $data['destination']);
        $stmt->bindParam(':departureDate', $data['departureDate']);
        $stmt->bindParam(':departureTime', $data['departureTime']);
        $stmt->bindParam(':arrivalTime', $data['arrivalTime']);
        $stmt->bindParam(':duration', $data['duration']);
        $stmt->bindParam(':blockTime', $data['blockTime']);
        $stmt->bindParam(':stops', $data['stops']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':status', $data['status']);

        $stmt->execute();

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteFlight($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        // Check if there are any bookings for this flight
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE flight_id = :id AND status = 'Confirmed'");
        $stmt->bindParam(':id', $data['id']);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete flight with active bookings']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM flights WHERE id = :id");
        $stmt->bindParam(':id', $data['id']);
        $stmt->execute();

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
function getFlight($pdo) {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Flight ID is required']);
        return;
    }

    $flightId = $_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM flights WHERE id = :id");
        $stmt->bindParam(':id', $flightId);
        $stmt->execute();

        $flight = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$flight) {
            http_response_code(404);
            echo json_encode(['error' => 'Flight not found']);
            return;
        }

        echo json_encode($flight);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function adminCancelBooking($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = :id");
        $stmt->bindParam(':id', $data['id']);
        $stmt->execute();

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function adminRestoreBooking($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = :id");
        $stmt->bindParam(':id', $data['id']);
        $stmt->execute();

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>