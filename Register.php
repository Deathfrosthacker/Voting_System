<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Registration | Voting System</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-container {
            width: 100%;
            height: 600px;
            background: white;
            display: flex;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        /* LEFT IMAGE SECTION */
        .image-section {
            flex: 1;
            background: url('./images/vote2.jpg')
                        center/cover no-repeat;
            position: relative;
        }

        .image-overlay {
            position: absolute;
            inset: 0;
            background: rgba(30, 64, 175, 0.75);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .image-overlay h1 {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .image-overlay p {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
        }

        /* RIGHT FORM SECTION */
        .form-section {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-section h2 {
            color: #1e40af;
            margin-bottom: 8px;
        }

        .form-section p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .btn-register {
            width: 100%;
            background: #1e40af;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-register:hover {
            background: #1d4ed8;
        }

        .login-link {
            text-align: center;
            margin-top: 18px;
            font-size: 14px;
        }

        .login-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
         header {
            background: #1e40af;
            color: white;
            padding: 20px;
            text-align: center;
        }

        header h1 {
            font-size: 32px;
        }

        header p {
            margin-top: 10px;
            font-size: 16px;
            opacity: 0.9;
        }


        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                height: auto;
            }

            .image-section {
                display: none;
            }

            .form-section {
                padding: 30px;
            }
        }
    </style>
</head>

<body>
   

<div class="main-container">

    <!-- LEFT IMAGE -->
    <div class="image-section">
        <div class="image-overlay">
            <h1>Online Voting System</h1>
            <p>
                A secure and transparent platform that allows registered
                voters to participate in elections online with confidence.
            </p>
        </div>
    </div>

    <!-- RIGHT FORM -->
    <div class="form-section">
        <h2>Create Account</h2>
        <p>Register to participate in the voting process</p>

        <form action="registerdb.php" method="POST">
            <div class="form-group">
                <label>Student ID</label>
                <input placeholder="Enter your Student ID" type="text" name="id" required>
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input placeholder="Enter your Full Name" type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input placeholder="Enter your Email Address" type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input placeholder="8 characters minimum" type="Password" type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input placeholder="Confirm Password" type="password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-register">Register</button>
        </form>

        <div class="login-link">
            Already have an account?
            <a href="login.php">Login</a>
        </div>
    </div>

</div>

</body>
</html>
