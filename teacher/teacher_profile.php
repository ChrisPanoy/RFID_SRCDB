<?php
include '../includes/db.php';
session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$msg = '';
$error = '';

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("s", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $profile_pic = $teacher['profile_pic'];

    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        // Delete old pic if exists
        if ($profile_pic && file_exists('../assets/img/' . $profile_pic)) {
            unlink('../assets/img/' . $profile_pic);
        }
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $profile_pic = uniqid('teacher_', true) . '.' . $ext;
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], '../assets/img/' . $profile_pic);
    }

    $update = $conn->prepare("UPDATE teachers SET name=?, email=?, phone=?, profile_pic=? WHERE teacher_id=?");
    $update->bind_param("sssss", $name, $email, $phone, $profile_pic, $teacher_id);
    if ($update->execute()) {
        $msg = "Profile updated successfully!";
        // Update session name if changed
        $_SESSION['teacher_name'] = $name;
        // Refresh teacher info
        $teacher['name'] = $name;
        $teacher['email'] = $email;
        $teacher['phone'] = $phone;
        $teacher['profile_pic'] = $profile_pic;
    } else {
        $error = "Failed to update profile.";
    }
    $update->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Teacher</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <?php include '../includes/sidebar.php'; ?>
    <div class="flex-1 ml-80 min-h-screen main-content">
    <header class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <a href="teacher_dashboard.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
                <span class="text-gray-600">
                    <i class="fas fa-user mr-2"></i><?= htmlspecialchars($teacher['name']) ?>
                </span>
            </div>
        </div>
    </header>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-lg p-8 border border-gray-200">
            <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-user-circle mr-3 text-blue-500"></i>My Profile
            </h1>
            <?php if ($msg): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 text-center">
                    <?= $msg ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 text-center">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="space-y-6">
                <div class="flex flex-col items-center mb-6">
                    <?php if ($teacher['profile_pic']): ?>
                        <img src="../assets/img/<?= htmlspecialchars($teacher['profile_pic']) ?>" alt="Profile" class="w-32 h-32 object-cover rounded-full border-4 border-blue-200 shadow mb-2">
                    <?php else: ?>
                        <div class="w-32 h-32 bg-gray-300 rounded-full border-4 border-blue-200 shadow flex items-center justify-center mb-2">
                            <i class="fas fa-user text-gray-600 text-4xl"></i>
                        </div>
                    <?php endif; ?>
                    <label class="block mt-2 text-sm font-medium text-gray-700">Change Profile Picture</label>
                    <input type="file" name="profile_pic" accept="image/*" class="mt-1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($teacher['name']) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($teacher['email']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($teacher['phone']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition">Save Changes</button>
                    <a href="teacher_dashboard.php" class="bg-gray-400 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-500 transition">Cancel</a>
                </div>
            </form>
        </div>
</div>
</body>
</html> 


