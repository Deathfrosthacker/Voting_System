<?php
require_once "./config/connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id  = mysqli_real_escape_string($conn, $_POST['id']);
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Default role
    $role = "voter";

    // Check password match
    if ($password !== $confirm_password) {
        $status = "password_mismatch";
    } else {

        // Check if email already exists
        $checkEmail = mysqli_query($conn, "SELECT id FROM users WHERE student_id='$student_id'");

        if (mysqli_num_rows($checkEmail) > 0) {
            $status = "Student ID already exists";
        } else {

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $sql = "INSERT INTO users (student_id, name, email, password, role)
                    VALUES ( '$student_id', '$name', '$email', '$hashedPassword', '$role')";

            if (mysqli_query($conn, $sql)) {
                $status = "success";
            } else {
                $status = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>

    <!-- SweetAlert CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if (isset($status)): ?>
<script>
    <?php if ($status === "success"): ?>
        Swal.fire({
            icon: 'success',
            title: 'Registration Successful',
            text: 'You can now log in',
            confirmButtonColor: '#1e40af'
        }).then(() => {
            window.location.href = "login.php";
        });

    <?php elseif ($status === "email_exists"): ?>
        Swal.fire({
            icon: 'error',
            title: 'Email Already Exists',
            text: 'Please use another email',
            confirmButtonColor: '#dc2626'
        }).then(() => {
            window.history.back();
        });

    <?php elseif ($status === "password_mismatch"): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Passwords Do Not Match',
            confirmButtonColor: '#f59e0b'
        }).then(() => {
            window.history.back();
        });

    <?php else: ?>
        Swal.fire({
            icon: 'error',
            title: 'Registration Failed',
            text: 'Student ID already exists',
            confirmButtonColor: '#dc2626'
        }).then(() => {
            window.history.back();
        });
    <?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>
