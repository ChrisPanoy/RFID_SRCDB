<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['message']) || trim($data['message']) === '') {
    echo json_encode(['error' => 'No message provided.']);
    exit;
}

$message = strtolower(trim($data['message']));

// FAQ pairs: question keywords => answer
$faq = [
    'record attendance' => 'Go to the Attendance page, scan the student’s barcode, and the system will record their attendance automatically.',
    'scan attendance' => 'To scan attendance, use the barcode scanner on the Attendance page.',
    'view attendance history' => 'Click on “See All” in the attendance section to view the full attendance history.',
    'forgot my password' => 'Please contact the system administrator to reset your password.',
    'update my profile' => 'Go to your profile page and click the Edit button to update your information.',
    'technical support' => 'For technical support, please contact the system administrator or IT department.',
    'add subject' => 'To add a subject, go to the Subjects page and click the Add Subject button.',
    'add teacher' => 'To add a teacher, go to the Teachers page and click the Add Teacher button.',
    'logout' => 'To logout, click the Logout button at the top right of the dashboard.',
    'change password' => 'To change your password, go to your profile and select Change Password.',
    'student profile' => 'You can view and edit student profiles from the Students page.',
    'teacher profile' => 'You can view and edit teacher profiles from the Teachers page.',
    'assign subject' => 'To assign a subject, go to the Assign Subjects page and select the teacher and subject.',
    'dashboard' => 'The dashboard shows an overview of attendance, students, and teachers. Use the menu to navigate to different sections.',
];

$answer = null;
foreach ($faq as $keyword => $reply) {
    if (strpos($message, $keyword) !== false) {
        $answer = $reply;
        break;
    }
}

if ($answer) {
    echo json_encode(['reply' => $answer]);
} else {
    echo json_encode(['reply' => "Sorry, I don't know the answer to that. Please contact the system administrator for further assistance."]);
} 