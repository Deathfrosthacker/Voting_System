<?php
require_once "./config/connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number  = mysqli_real_escape_string($conn, $_POST['id']);
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $region_id = isset($_POST['region_id']) ? (int)$_POST['region_id'] : null;
    $date_of_birth = $_POST['date_of_birth'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Default role
    $role = "voter";

    // Check password match
    if ($password !== $confirm_password) {
        $status = "password_mismatch";
    } else {

        // FIX: Age validation - must be 18+ years old
        $dob = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($dob)->y;

        if ($age < 18) {
            $status = "underage";
        } else {

            // Check if ID number already exists
            $checkId = mysqli_query($conn, "SELECT id FROM users WHERE id_number='$id_number'");

            if (mysqli_num_rows($checkId) > 0) {
                $status = "id_exists";
            } else {

                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert user with region_id and date_of_birth
                $sql = "INSERT INTO users (id_number, name, email, region_id, date_of_birth, password, role)
                        VALUES ( '$id_number', '$name', '$email', " . 
                        ($region_id ? "'$region_id'" : "NULL") . 
                        ", '$date_of_birth', '$hashedPassword', '$role')";

                if (mysqli_query($conn, $sql)) {
                    $status = "success";
                } else {
                    $status = "error";
                }
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

    <?php elseif ($status === "id_exists"): ?>
        Swal.fire({
            icon: 'error',
            title: 'ID Number Already Exists',
            text: 'Please use another ID Number',
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

    <?php elseif ($status === "underage"): ?>
        Swal.fire({
            icon: 'error',
            title: 'Age Requirement Not Met',
            text: 'You must be at least 18 years old to register as a voter.',
            confirmButtonColor: '#dc2626'
        }).then(() => {
            window.history.back();
        });

    <?php else: ?>
        Swal.fire({
            icon: 'error',
            title: 'Registration Failed',
            text: 'Something went wrong. Please try again.',
            confirmButtonColor: '#dc2626'
        }).then(() => {
            window.history.back();
        });
    <?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>