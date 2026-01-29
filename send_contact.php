<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    echo json_encode(['error' => 'All fields are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email address.']);
    exit;
}

$to = 'christophermadeja7@gmail.com';
$subject = 'Support Request from Teacher';
$body = "You have received a new support request from a teacher.\n\n" .
        "Name: $name\n" .
        "Email: $email\n" .
        "Message:\n$message\n";
$headers = "From: $name <$email>\r\nReply-To: $email\r\n";

// Try to use PHPMailer if available
$phpmailer_path = __DIR__ . '/includes/PHPMailer/PHPMailer-master/src/PHPMailer.php';
if (file_exists($phpmailer_path)) {
    require_once $phpmailer_path;
    require_once __DIR__ . '/includes/PHPMailer/PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/includes/PHPMailer/PHPMailer-master/src/Exception.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->isMail();
    $mail->setFrom($email, $name);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;
    if ($mail->send()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to send email.']);
    }
    exit;
}

// Fallback to mail()
if (mail($to, $subject, $body, $headers)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to send email.']);
} 