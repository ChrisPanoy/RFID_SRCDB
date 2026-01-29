<?php
// Test script to check teacher barcode functionality
include 'includes/db.php';

echo "<h2>Teacher Barcode System Test</h2>";

// Check if barcode column exists
$check_column = $conn->query("SHOW COLUMNS FROM teachers LIKE 'barcode'");
if ($check_column->num_rows == 0) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>⚠️ Database Migration Required!</strong><br>";
    echo "The 'barcode' column does not exist in the teachers table.<br>";
    echo "Please run the SQL script: <code>database/add_teacher_barcode.sql</code><br>";
    echo "You can run it in phpMyAdmin or your MySQL client.";
    echo "</div>";
} else {
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "<strong>✅ Database Ready!</strong><br>";
    echo "The 'barcode' column exists in the teachers table.";
    echo "</div>";
    
    // Show current teachers and their barcode status
    $teachers = $conn->query("SELECT id, teacher_id, name, email, barcode FROM teachers ORDER BY name");
    
    if ($teachers->num_rows > 0) {
        echo "<h3>Current Teachers Status:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Teacher ID</th><th>Name</th><th>Email</th><th>Barcode</th><th>Status</th>";
        echo "</tr>";
        
        while ($teacher = $teachers->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($teacher['id']) . "</td>";
            echo "<td>" . htmlspecialchars($teacher['teacher_id'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($teacher['name']) . "</td>";
            echo "<td>" . htmlspecialchars($teacher['email'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($teacher['barcode'] ?? '-') . "</td>";
            
            // Status logic
            $has_teacher_id = !empty($teacher['teacher_id']);
            $has_barcode = !empty($teacher['barcode']);
            $barcode_file_exists = false;
            
            if ($has_barcode) {
                $barcode_file = __DIR__ . '/assets/barcodes/barcode_' . $teacher['teacher_id'] . '.svg';
                $barcode_file_exists = file_exists($barcode_file);
            }
            
            if ($has_barcode && $barcode_file_exists) {
                echo "<td style='color: green;'>✅ Ready for scanning</td>";
            } elseif ($has_teacher_id) {
                echo "<td style='color: orange;'>🟠 Ready to generate</td>";
            } else {
                echo "<td style='color: red;'>❌ No Teacher ID</td>";
            }
            
            echo "</tr>";
        }
        echo "</table>";
        
        // Test barcode directory
        $barcode_dir = __DIR__ . '/assets/barcodes/';
        if (!is_dir($barcode_dir)) {
            echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
            echo "<strong>ℹ️ Info:</strong> Barcode directory will be created automatically when first barcode is generated.";
            echo "</div>";
        } else {
            $barcode_files = glob($barcode_dir . 'barcode_*.svg');
            echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
            echo "<strong>ℹ️ Barcode Files:</strong> " . count($barcode_files) . " barcode files found in assets/barcodes/";
            echo "</div>";
        }
        
    } else {
        echo "<p>No teachers found in the database.</p>";
    }
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If database migration is needed, run the SQL script in phpMyAdmin</li>";
echo "<li>Go to <a href='manage_teachers.php'>Manage Teachers</a> to generate barcodes</li>";
echo "<li>Test login at <a href='teacher_login.php'>Teacher Login</a></li>";
echo "</ol>";

echo "<p><a href='manage_teachers.php'>← Back to Manage Teachers</a></p>";
?>
