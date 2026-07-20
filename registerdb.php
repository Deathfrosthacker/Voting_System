<?php
require_once "./config/connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number  = trim($_POST['id'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'];

    // Default role
    $role = "voter";

    // Check password match
    if ($password !== $confirm_password) {
        $status = "password_mismatch";
    } else {

        /*Validate date format and catch DateTime exceptions */
        try {
            $dob = new DateTime($date_of_birth);
            $today = new DateTime();
            
            // Validate the date is real
            $formatted_dob = $dob->format('Y-m-d');
            if ($formatted_dob !== $date_of_birth) {
                throw new Exception("Invalid date format");
            }
            
            // Check date is in the past
            if ($dob > $today) {
                $status = "future_dob";
            } else {
                $age = $today->diff($dob)->y;

                if ($age < 18) {
                    $status = "underage";
                } else {

                    // Check if ID number already exists
                    $checkId = mysqli_prepare($conn, "SELECT id FROM users WHERE id_number = ? LIMIT 1");
                    mysqli_stmt_bind_param($checkId, "s", $id_number);
                    mysqli_stmt_execute($checkId);
                    $idResult = mysqli_stmt_get_result($checkId);

                    if (mysqli_num_rows($idResult) > 0) {
                        $status = "id_exists";
                    } else {

                        // Check if email already exists
                        $checkEmail = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
                        mysqli_stmt_bind_param($checkEmail, "s", $email);
                        mysqli_stmt_execute($checkEmail);
                        $emailResult = mysqli_stmt_get_result($checkEmail);

                        if (mysqli_num_rows($emailResult) > 0) {
                            $status = "email_exists";
                        } else {
                            // Hash password
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                            /* Self-registered users set password (they chose their own password) */
                            $stmt = mysqli_prepare($conn, 
                                "INSERT INTO users (id_number, name, email, date_of_birth, region_id, password, role, password_changed) VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
                            );
                            /* Bind region_id as integer (can be NULL) */
                            mysqli_stmt_bind_param($stmt, "ssssiss", $id_number, $name, $email, $date_of_birth, $region_id, $hashedPassword, $role);

                            if (mysqli_stmt_execute($stmt)) {
                                $status = "success";
                            } else {
                                $status = "error";
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $status = "invalid_dob";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if (isset($status)): ?>
<script>
    <?php if ($status === "success"): ?>
        Swal.fire({
            icon: 'success', title: 'Registration Successful',
            text: 'You can now log in', confirmButtonColor: '#1e40af'
        }).then(() => { window.location.href = "login.php"; });

    <?php elseif ($status === "id_exists"): ?>
        Swal.fire({
            icon: 'error', title: 'ID Number Already Exists',
            text: 'Please use another ID Number', confirmButtonColor: '#dc2626'
        }).then(() => { window.history.back(); });

    <?php elseif ($status === "email_exists"): ?>
        Swal.fire({
            icon: 'error', title: 'Email Already Exists',
            text: 'Please use another email address', confirmButtonColor: '#dc2626'
        }).then(() => { window.history.back(); });

    <?php elseif ($status === "password_mismatch"): ?>
        Swal.fire({
            icon: 'warning', title: 'Passwords Do Not Match', confirmButtonColor: '#f59e0b'
        }).then(() => { window.history.back(); });

    <?php elseif ($status === "underage"): ?>
        Swal.fire({
            icon: 'error', title: 'Age Requirement Not Met',
            text: 'You must be at least 18 years old to register as a voter.',
            confirmButtonColor: '#dc2626'
        }).then(() => { window.history.back(); });

    <?php elseif ($status === "invalid_dob"): ?>
        Swal.fire({
            icon: 'error', title: 'Invalid Date of Birth',
            text: 'Please enter a valid date of birth.',
            confirmButtonColor: '#dc2626'
        }).then(() => { window.history.back(); });

    <?php elseif ($status === "future_dob"): ?>
        Swal.fire({
            icon: 'error', title: 'Invalid Date of Birth',
            text: 'Date of birth cannot be in the future.',
            confirmButtonColor: '#dc2626'
        }).then(() => { window.history.back(); });

    <?php else: ?>
        Swal.fire({
            icon: 'error', title: 'Registration Failed',
            text: 'Something went wrong. Please try again.', confirmButtonColor: '#dc2626'
        }).then(() => { window.history.back(); });
    <?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>