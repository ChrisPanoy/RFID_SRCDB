<?php
include '../includes/db.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if last_name is provided
if (!isset($_POST['last_name']) || empty(trim($_POST['last_name']))) {
    echo json_encode(['error' => 'Last name is required']);
    exit;
}

$lastName = trim($_POST['last_name']);

// Prepare the SQL query to search by last name (using last_name column)
$query = "SELECT student_id, rfid_number, first_name, middle_name, last_name FROM students WHERE last_name LIKE ? ORDER BY last_name, first_name, student_id";
$stmt = $conn->prepare($query);

if ($stmt) {
    $searchTerm = "%" . $lastName . "%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    // Return the results as JSON
    echo json_encode($students);
    $stmt->close();
} else {
    // Handle error
    echo json_encode(['error' => 'Database query error']);
}

$conn->close();
?>




