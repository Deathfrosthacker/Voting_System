<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Voting System</title>
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
            background: url('https://images.unsplash.com/photo-1529107386315-e1a2ed48a620')
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
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
            color: #374151;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .btn-login {
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

        .btn-login:hover {
            background: #1d4ed8;
        }

        .extra-links {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 13px;
        }

        .extra-links a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }

        .extra-links a:hover {
            text-decoration: underline;
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
                Securely log in to cast your vote and participate
                in a transparent digital election process.
            </p>
        </div>
    </div>

    <!-- RIGHT FORM -->
    <div class="form-section">
        <h2>Welcome Back</h2>
        <p>Please log in to continue</p>

        <form action="logindb.php" method="POST">
            <div class="form-group">
                <label>Student ID</label>
                <input type="number" name="id" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="extra-links">
            <a href="register.php">Create Account</a>
        </div>
    </div>

</div>

</body>
</html>
