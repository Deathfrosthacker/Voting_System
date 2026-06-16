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
            height: 750px;
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
            overflow-y: auto;
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
            margin-bottom: 14px;
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
            background: white;
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
                <label>ID Number</label>
                <input placeholder="Enter your ID Number" type="text" name="id" required>
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input placeholder="Enter your Full Name" type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input placeholder="Enter your Email Address" type="email" name="email" required>
            </div>

            <!-- Date of Birth Field -->
            <div class="form-group">
                <label>Date of Birth <span style="color:#6b7280;font-size:12px;">(Must be 18+ years old)</span></label>
                <input type="date" name="date_of_birth" id="date_of_birth" required max="">
            </div>

            <!-- County Selection for Kenya -->
            <div class="form-group">
                <label>County (Kenya)</label>
                <select name="county" required>
                    <option value="">-- Select Your County --</option>
                    <option value="Mombasa">Mombasa</option>
                    <option value="Kwale">Kwale</option>
                    <option value="Kilifi">Kilifi</option>
                    <option value="Tana River">Tana River</option>
                    <option value="Lamu">Lamu</option>
                    <option value="Taita-Taveta">Taita-Taveta</option>
                    <option value="Garissa">Garissa</option>
                    <option value="Wajir">Wajir</option>
                    <option value="Mandera">Mandera</option>
                    <option value="Marsabit">Marsabit</option>
                    <option value="Isiolo">Isiolo</option>
                    <option value="Meru">Meru</option>
                    <option value="Tharaka-Nithi">Tharaka-Nithi</option>
                    <option value="Embu">Embu</option>
                    <option value="Kitui">Kitui</option>
                    <option value="Machakos">Machakos</option>
                    <option value="Makueni">Makueni</option>
                    <option value="Nyandarua">Nyandarua</option>
                    <option value="Nyeri">Nyeri</option>
                    <option value="Kirinyaga">Kirinyaga</option>
                    <option value="Murang'a">Murang'a</option>
                    <option value="Kiambu">Kiambu</option>
                    <option value="Turkana">Turkana</option>
                    <option value="West Pokot">West Pokot</option>
                    <option value="Samburu">Samburu</option>
                    <option value="Trans Nzoia">Trans Nzoia</option>
                    <option value="Uasin Gishu">Uasin Gishu</option>
                    <option value="Elgeyo-Marakwet">Elgeyo-Marakwet</option>
                    <option value="Nandi">Nandi</option>
                    <option value="Baringo">Baringo</option>
                    <option value="Laikipia">Laikipia</option>
                    <option value="Nakuru">Nakuru</option>
                    <option value="Narok">Narok</option>
                    <option value="Kajiado">Kajiado</option>
                    <option value="Kericho">Kericho</option>
                    <option value="Bomet">Bomet</option>
                    <option value="Kakamega">Kakamega</option>
                    <option value="Vihiga">Vihiga</option>
                    <option value="Bungoma">Bungoma</option>
                    <option value="Busia">Busia</option>
                    <option value="Siaya">Siaya</option>
                    <option value="Kisumu">Kisumu</option>
                    <option value="Homa Bay">Homa Bay</option>
                    <option value="Migori">Migori</option>
                    <option value="Kisii">Kisii</option>
                    <option value="Nyamira">Nyamira</option>
                    <option value="Nairobi">Nairobi</option>
                </select>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input placeholder="8 characters minimum" type="password" name="password" required minlength="8">
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

<script>
    // Set max date to 18 years ago (must be at least 18 to vote)
    const today = new Date();
    const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    document.getElementById('date_of_birth').max = maxDate.toISOString().split('T')[0];
</script>

</body>
</html>