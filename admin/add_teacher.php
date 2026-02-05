<?php
include '../includes/db.php';

// Ensure session is started before checking auth so header redirects work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch available roles
$roleQuery = $conn->query("SELECT * FROM roles ORDER BY role_name ASC");
$roles = [];
while($r = $roleQuery->fetch_assoc()) {
    $roles[] = $r;
}

include '../includes/header.php';

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Use null-coalescing to avoid undefined index notices
    $teacher_id = trim($_POST['teacher_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? 'College of Computing Studies');
    $role_id = intval($_POST['role_id'] ?? 0);
    $password = trim($_POST['password'] ?? '');
    $profile_pic = null;
    
    // Validation
    if (empty($email) || empty($name) || empty($password)) {
        $error = "Email, Name, and Password are required!";
    } elseif (strlen($password) < 8 || !preg_match('/[^a-zA-Z0-9]/', $password)) {
        // password must be 8+ and contain at least one special character
        $error = "Password must be at least 8 characters long and include at least one special character.";
    } else {
        // Check if email already exists in employees table
        $check = $conn->prepare("SELECT employee_id FROM employees WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            // Handle profile picture upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $profile_pic = uniqid('teacher_', true) . '.' . $ext;
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], '../assets/img/' . $profile_pic);
            }
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Hash the password for storage
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Derive firstname/lastname from full name for employees table
                $firstname = $name;
                $lastname = '';
                if (strpos($name, ' ') !== false) {
                    $parts = preg_split('/\s+/', $name);
                    $firstname = array_shift($parts);
                    $lastname = implode(' ', $parts);
                }
                $emp_id_int = (int)$teacher_id;

                // Create employee account for teacher login including profile picture
                $stmt = $conn->prepare("INSERT INTO employees (employee_id, firstname, lastname, email, password, role_id, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssis", $emp_id_int, $firstname, $lastname, $email, $hashed_password, $role_id, $profile_pic);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $message = "Teacher added successfully! Teacher can now login using Email: <strong>$email</strong> and the password you set.";
                // Clear form data
                $_POST = array();
                
                if (isset($stmt) && $stmt) $stmt->close();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error adding teacher: " . $e->getMessage();
            }
        }
        $check->close();
    }
}
?>

<style>
    .app-content { margin-left: 20rem; padding: 3rem 1.5rem; min-height:100vh; box-sizing:border-box; background: linear-gradient(90deg,#eaf6ff 0%, #f8f4ff 100%); }
    .app-container { max-width: 1800px; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 2.5rem; box-shadow: 0 12px 40px rgba(2,6,23,0.06); }
    @media (max-width:900px){ .app-content{margin-left:0;padding:1.25rem;} .app-container{padding:1rem;} }
</style>
<div class="app-content">
    <div class="app-container">
        <div class="flex flex-col sm:flex-row justify-between items-center border-b-2 border-blue-100 pb-4 mb-8 gap-4">
            <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-2">
                <span></span> Add Faculty
            </h2>
        </div>

        <!-- Display success/error messages here -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 bg-green-50 border border-green-300 text-green-800 rounded-lg px-4 py-3 font-semibold text-center">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-50 border border-red-300 text-red-800 rounded-lg px-4 py-3 font-semibold text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Your add teacher form goes here -->
        <form method="POST" class="space-y-6" enctype="multipart/form-data">
            <div class="form-group">
                <label for="teacher_id" class="form-label">FACULTY ID *</label>
                <input type="text" id="teacher_id" name="teacher_id" value="<?= htmlspecialchars($_POST['teacher_id'] ?? '') ?>" required
                    class="form-input" placeholder="Enter Teacher ID">
            </div>
            <div class="form-group">
                <label for="name" class="form-label">Name *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required
                    class="form-input" placeholder="Enter Full Name">
            </div>
            <div class="form-group">
                <label for="email" class="form-label">Email *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                    class="form-input" placeholder="Enter Email Address">
            </div>
            <div class="form-group">
                <label for="department" class="form-label">Department *</label>
                <input type="text" id="department" name="department" value="College of Computing Studies" readonly class="form-input bg-gray-100 cursor-not-allowed"
                    tabindex="-1">
                <input type="hidden" name="department" value="College of Computing Studies">
            </div>
            <div class="form-group">
                <label for="role_id" class="form-label">Role *</label>
                <select id="role_id" name="role_id" required class="form-input">
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['role_id'] ?>" <?= (($_POST['role_id'] ?? '') == $role['role_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($role['role_name'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password *</label>
                <input type="password" id="password" name="password" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>" required
                    class="form-input" placeholder="Enter Password">
                <small class="form-help">Faculty will use this password to login</small>
            </div>
            <div class="form-group">
                <label for="profile_pic" class="form-label">Profile Picture</label>
                <input type="file" id="profile_pic" name="profile_pic" accept="image/*" onchange="previewProfilePic(event)"
                    class="form-input">
                <small class="form-help">Optional. Upload a profile photo for the Faculty.</small>
                <div id="profile-pic-preview-container" style="margin-top:10px; display:none;">
                    <img id="profile-pic-preview" src="#" alt="Profile Preview" style="max-width:100px; max-height:100px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);" />
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-4 mt-6">
                <button type="submit" class="btn btn-primary flex-1">
                    <i class="fas fa-plus mr-2"></i>Add Teacher
                </button>
                <button type="reset" class="btn btn-secondary flex-1">
                    <i class="fas fa-redo mr-2"></i>Reset
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    body {
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        margin: 0;
        padding: 0;
        background: linear-gradient(120deg, #e0eafc 0%, #cfdef3 100%);
        color: #222;
    }
    .container {
        max-width: 520px;
        margin: 48px auto;
        padding: 0 16px;
    }
    .form-container {
        background: #fff;
        border-radius: 18px;
        padding: 38px 28px 32px 28px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.13);
        position: relative;
    }
    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
        border-bottom: 2px solid #f3f6fa;
        padding-bottom: 18px;
    }
    .form-header h2 {
        margin: 0;
        color: #1a237e;
        font-size: 1.45rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .back-btn {
        text-decoration: none;
        color: #2563eb;
        font-weight: 500;
        padding: 8px 18px;
        border-radius: 7px;
        border: 1.5px solid #2563eb;
        background: #f5f8ff;
        transition: all 0.2s;
        font-size: 1rem;
    }
    .back-btn:hover {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 2px 8px rgba(37,99,235,0.08);
    }
    .alert {
        padding: 14px 18px;
        border-radius: 9px;
        margin-bottom: 22px;
        font-weight: 500;
        font-size: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .alert.success {
        background: #e6f9ed;
        color: #1b5e20;
        border: 1.5px solid #b2dfdb;
    }
    .alert.error {
        background: #fff0f0;
        color: #b71c1c;
        border: 1.5px solid #ffcdd2;
    }
    .form-group {
        margin-bottom: 22px;
    }
    .form-group label {
        display: block;
        margin-bottom: 7px;
        font-weight: 600;
        color: #222;
        font-size: 1.05rem;
    }
    .form-group label .required {
        color: #e53935;
        font-weight: bold;
        font-size: 1.1em;
        margin-left: 2px;
    }
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 13px 14px;
        border: 2px solid #e3e7ee;
        border-radius: 9px;
        font-size: 1.07rem;
        background: #f8fafc;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37,99,235,0.10);
        background: #fff;
    }
    .form-help {
        display: block;
        margin-top: 4px;
        font-size: 0.93rem;
        color: #6b7280;
        font-style: italic;
    }
    .form-actions {
        display: flex;
        gap: 18px;
        margin-top: 34px;
    }
    .btn-primary,
    .btn-secondary {
        padding: 13px 0;
        border: none;
        border-radius: 8px;
        font-size: 1.08rem;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.18s, transform 0.18s;
        flex: 1;
        letter-spacing: 0.2px;
    }
    .btn-primary {
        background: linear-gradient(90deg, #2563eb 60%, #1e40af 100%);
        color: #fff;
        box-shadow: 0 2px 8px rgba(37,99,235,0.07);
    }
    .btn-primary:hover {
        background: linear-gradient(90deg, #1e40af 60%, #2563eb 100%);
        transform: translateY(-2px) scale(1.03);
    }
    .btn-secondary {
        background: #e3e7ee;
        color: #222;
    }
    .btn-secondary:hover {
        background: #cfd8dc;
        color: #1a237e;
        transform: translateY(-2px) scale(1.03);
    }
    #profile-pic-preview-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    #profile-pic-preview {
        border: 1.5px solid #e3e7ee;
        background: #f8fafc;
    }
    .add-form .form-flex-row {
        display: flex;
        gap: 32px;
        margin-bottom: 0;
    }
    .add-form .form-col {
        flex: 1 1 0;
        min-width: 0;
    }
    @media screen and (max-width: 900px) {
        .add-form .form-flex-row {
            gap: 16px;
        }
    }
    @media screen and (max-width: 700px) {
        .add-form .form-flex-row {
            flex-direction: column;
            gap: 0;
        }
        .add-form .form-col {
            width: 100%;
        }
    }
</style>
<script>
function previewProfilePic(event) {
    const input = event.target;
    const previewContainer = document.getElementById('profile-pic-preview-container');
    const preview = document.getElementById('profile-pic-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'flex';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '#';
        previewContainer.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>



