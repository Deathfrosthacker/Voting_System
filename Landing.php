<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Voting System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f8;
            color: #333;
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

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .hero {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 30px;
        }

        .hero-text {
            flex: 1;
            margin-top: 100px;
        }

        .hero-text h2 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #1e40af;
        }

        .hero-text p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .buttons a {
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 5px;
            margin-right: 10px;
            font-size: 15px;
            display: inline-block;
        }

        .btn-primary {
            background: #1e40af;
            color: white;
        }

        .btn-secondary {
            border: 2px solid #1e40af;
            color: #1e40af;
        }

        .features {
            margin-top: 60px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .feature-card h3 {
            margin-bottom: 10px;
            color: #1e40af;
        }

        footer {
            margin-top: 60px;
            background: #111827;
            color: #d1d5db;
            text-align: center;
            padding: 15px;
            font-size: 14px;
        }
        .flex-area {
            display: flex;
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 26px;
            }

            .hero-text h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

<header>
    <h1> Voting System</h1>
    <p>Secure, reliable, and easy-to-use digital voting platform</p>
</header>

<div class="container">
    <section class="hero">
        <div class="flex-area">
            <div class="hero-text">
                <h2>Welcome to the Voting System</h2>
                <p>
                    This system is designed to provide a secure and transparent way
                    for users to participate in elections online. Voters can log in,
                    cast their votes once, and view results with confidence.
                </p>
                <div class="buttons">
                    <a href="Login.php" class="btn-primary">Login</a>
                    <a href="Register.php" class="btn-secondary">Register</a>
                </div>
            </div>

            <div class="hero-image">
                <img width="400" src="./images/vote.jpg" alt="Voting System">
            </div>
        </div>
    </section>

    <section class="features">
        <div class="feature-card">
            <h3>Secure Voting</h3>
            <p>
                Each voter is authenticated and allowed to vote only once,
                ensuring fairness and integrity.
            </p>
        </div>

        <div class="feature-card">
            <h3>Easy to Use</h3>
            <p>
                Simple and intuitive interface that makes voting quick and
                accessible for everyone.
            </p>
        </div>

        <div class="feature-card">
            <h3>Real-Time Results</h3>
            <p>
                Votes are counted automatically and results can be viewed
                instantly by the administrator.
            </p>
        </div>
    </section>
</div>

<footer>
    <p>&copy; 2026 Online Voting System | Computer Science Project</p>
</footer>

</body>
</html>
