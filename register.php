<?php
require 'core/dbConfig.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
    try {
        $pdo->beginTransaction();
        $stmt->execute(['username' => $username, 'password' => $password, 'role' => $role]);
        $pdo->commit();
        header("Location: login.php");
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<style>
    body {
        overflow: hidden;
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
</style>
<body>
<div class="blob">

</div>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4">
            <img src="images\findhire_name.png" alt="">
                <h2 class="text-center">Register</h2>
                <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" id="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" id="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Register as</label>
                        <div>
                            <button type="submit" name="role" value="Applicant" class="btn me-2" style="background-color: #00a4ad; color: white;">Applicant</button>
                            <button type="submit" name="role" value="HR" class="btn" style="border-color: #764AA6; color: #764AA6;">HR</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
