    <?php
    session_start();
    require_once "./config/connection.php";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        $student_id = mysqli_real_escape_string($conn, $_POST['id']);
        $password   = $_POST['password'];

        // Fetch user by student_id
        $query = "SELECT * FROM users WHERE student_id = '$student_id' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) == 1) {

            $user = mysqli_fetch_assoc($result);

            // Verify password
            if (password_verify($password, $user['password'])) {

                // Set session variables
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['role']       = $user['role'];

                $status = "success";
                $role   = $user['role'];

            $user_id = $user['id'];
            $activity = "Logged in";

            mysqli_query(
                $conn,
                "INSERT INTO logs (user_id, activity) VALUES ('$user_id', '$activity')"
            );



            } else {
                $status = "wrong_password";
            }

        } else {
            $status = "not_found";
        }
    }
    ?>

    <!DOCTYPE html>
    <html>
    <head>
        <title>Login</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>

    <?php if (isset($status)): ?>
    <script>
    <?php if ($status === "success"): ?>
        Swal.fire({
            icon: 'success',
            title: 'Login Successful',
            confirmButtonColor: '#1e40af'
        }).then(() => {
            <?php if ($role === "admin"): ?>
                window.location.href = "admin_dashboard.php";
            <?php else: ?>
                window.location.href = "voter_dashboard.php";
            <?php endif; ?>
        });

    <?php elseif ($status === "wrong_password"): ?>
        Swal.fire({
            icon: 'error',
            title: 'Incorrect Password',
            text: 'Please try again',
            confirmButtonColor: '#dc2626'
        }).then(() => {
            window.history.back();
        });

    <?php elseif ($status === "not_found"): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Student ID Not Found',
            text: 'Please check your Student ID',
            confirmButtonColor: '#f59e0b'
        }).then(() => {
            window.history.back();
        });

    <?php else: ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: 'Something went wrong',
            confirmButtonColor: '#dc2626'
        }).then(() => {
            window.history.back();
        });
    <?php endif; ?>
    </script>
    <?php endif; ?>

    </body>
    </html>
