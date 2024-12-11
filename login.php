<?php
require 'core/dbConfig.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] === 'Applicant') {
            header("Location: applicant.php");
        } elseif ($user['role'] === 'HR') {
            header("Location: hr.php");
        }
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #fff;
            overflow: hidden; 
        }
        .card {
            margin-top: 15%;
        }

        .btn-login {
            width: 10vw;
            background-color: #00a4ad;
            border-color: #00a4ad;
        }

        img {
            align-self: center;
            height: 220px;
            width: 430px;
        }

        /**blob */
        .blob {
        position: absolute;
        top: 0;
        left: 0;
        opacity: 0.5;
        width: 400px;
        aspect-ratio: 1/1;
        animation: animate 10s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite alternate forwards;
        filter: blur(40px);
        z-index: -1;
        background: linear-gradient(
            47deg,
            rgba(255, 88, 139, 1) 21%,
            rgb(0, 164, 173) 67%,
            rgba(118, 74, 166, 1) 81%
        );
    }

    @keyframes animate {
        0% {
            transform: translate(40vw, -25vh);
            border-radius: 60% 40% 30% 70% / 100% 85% 92% 74%;
        }
        50% {
            transform: translate(0vw, 13vh);
            border-radius: 20% 71% 47% 70% / 81% 15% 22% 54%;
            rotate: 41deg;
            scale: 1.15;
        }
        100% {
            transform: translate(-45vw, 39vh);
            border-radius: 100% 75% 92% 74% / 60% 80% 30% 70%;
            rotate: -60deg;
            scale: 1.05;
        }
    }

    .register-link {
        color: #FF588B;
    }

    </style>
</head>
<body>
<div class="container mt-5">
<div class="blob">
</div>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4">
                <img class="d-flex justify-content-center" src="images\findhire_logo.png" alt="">
                <h2 class="text-center">Login</h2>
                <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" id="password" required>
                    </div>
                    <div class="d-flex justify-content-start">
                        <a href="register.php" class="register-link btn btn-link p-0">Don't have an account? Register</a>
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-login">Login</button>
                    </div>
                    <div class="blob">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
