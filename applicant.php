<?php
require 'auth.php';
checkRole('Applicant');
require 'core/dbConfig.php';

// Fetch Job Posts
try {
    $jobPosts = $pdo->query("SELECT * FROM job_posts")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $jobPosts = []; // Default to an empty array if the query fails
}

// Fetch Notifications
try {
    $notificationsStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC");
    $notificationsStmt->execute(['user_id' => $_SESSION['user_id']]);
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = []; // Default to an empty array if the query fails or no notifications exist
}

// Fetch Messages (Applicant's messages to HR and vice versa)
try {
    $messagesStmt = $pdo->prepare("SELECT m.*, u.username AS sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE (m.sender_id = :user_id OR m.recipient_id = :user_id) ORDER BY m.created_at DESC");
    $messagesStmt->execute(['user_id' => $_SESSION['user_id']]);
    $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = []; // Default to an empty array if the query fails or no messages exist
}

// Fetch HR Users (for the dropdown)
try {
    $hrUsersStmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'HR'");
    $hrUsersStmt->execute();
    $hrUsers = $hrUsersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hrUsers = []; // Default to an empty array if the query fails or no HR users exist
}

// Handle Job Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    $jobId = $_POST['job_id'];
    $message = $_POST['message'];
    $resume = $_FILES['resume'];

    // Check if a valid file is uploaded
    if ($resume['error'] === UPLOAD_ERR_OK && $resume['type'] == 'application/pdf') {
        $resumePath = 'uploads/' . uniqid() . '.pdf';

        // Move the uploaded resume to the correct location
        if (move_uploaded_file($resume['tmp_name'], $resumePath)) {
            // Insert the application data into the database
            $stmt = $pdo->prepare("INSERT INTO applications (job_id, applicant_id, message, resume_path) VALUES (:job_id, :applicant_id, :message, :resume_path)");
            $stmt->execute([
                'job_id' => $jobId,
                'applicant_id' => $_SESSION['user_id'],
                'message' => $message,
                'resume_path' => $resumePath
            ]);

            // Optionally, display a success message
            echo "Application submitted successfully!";
        } else {
            echo "Error uploading the resume.";
        }
    } else {
        echo "Invalid resume file. Please upload a PDF.";
    }
}

// Handle sending a message to HR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $messageContent = $_POST['message_content'];
    $recipientId = $_POST['hr_user']; // Get the selected HR user ID

    // Insert the new message into the database
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, message) VALUES (:sender_id, :recipient_id, :message)");
    $stmt->execute([
        'sender_id' => $_SESSION['user_id'],
        'recipient_id' => $recipientId,
        'message' => $messageContent
    ]);

    // Optionally, display a success message
    echo "Message sent successfully!";
}

//notif and message delete

// Handle Notification Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notificationId = $_POST['notification_id'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $notificationId, 'user_id' => $_SESSION['user_id']]);
}

// Handle Message Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $messageId = $_POST['message_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = :id AND (sender_id = :user_id OR recipient_id = :user_id)");
    $stmt->execute(['id' => $messageId, 'user_id' => $_SESSION['user_id']]);
}
?>

<!-- HTML for the Applicant Dashboard -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
        }
        img {
            margin-left: 1vw;
        }

        .logout {
            margin-right: 1vw;
        }

        .notif {
            border-radius: 10px;
            border: 1px solid white;
            padding: 5px;
            border-top-left-radius: 0px;
            border-bottom-left-radius: 0px;

        }

        .job-post {
            border-radius: 10px;
            border-top-right-radius: 0px;
            border-bottom-right-radius: 0px;
            padding: 5px;
            border: 1px solid white;
            padding-bottom: 0px;
        }

        /*dark mode*/
        body.dark-mode {
        background-color: #1E201E;
        color: #FFFFFF;
        }
        .navbar.dark-mode {
            background-color: #00a4ad !important;
            color: #FFFFFF;
        }
        .dark-mode .btn-outline-secondary {
         
        }
        .dark-mode .btn-outline-secondary:hover {
            color: #1E201E;
        }

    </style>
</head>
<body>
<nav class="navbar navbar-light bg-light">
    <a class="navbar-brand" href="applicant.php">
        <img src="images/findhire_name.png" width="120" height="70" class="d-inline-block align-top" alt="">
    </a>
    <div class="ms-auto d-flex align-items-center">
        <button id="themeToggle" class="btn btn-outline-secondary me-2">☼</button>
        <form action="logout.php" method="POST" class="d-inline">
            <button class="logout btn btn-outline-danger my-2 my-sm-0" type="submit">Logout</button>
        </form>
    </div>
</nav>

<div class="container mt-5">
    <div class="row">
        <!-- Job Posts -->
        <div class="job-post col-md-7">
            <h1>Applicant Dashboard</h1>
            <h2>Job Posts</h2>
            <?php if (empty($jobPosts)): ?>
                <p>No job posts available.</p>
            <?php else: ?>
                <?php foreach ($jobPosts as $job): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars($job['description']) ?></p>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                <div class="mb-3">
                                    <textarea name="message" class="form-control" placeholder="Why are you the best fit for this role?" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="resume" class="form-label">Upload Resume (PDF)</label>
                                    <input type="file" name="resume" id="resume" class="form-control" required>
                                </div>
                                <button type="submit" name="apply" class="btn btn-primary">Apply</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Notifications and Messages -->
        <div class="col-md-5">
            <!-- Notifications -->
            <div class="notif">
                <h2>Notifications</h2>
                <?php if (empty($notifications)): ?>
                    <p>No notifications yet.</p>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="alert 
                            <?= strpos(strtolower($notification['message']), 'accepted') !== false ? 'alert-success' : 'alert-danger' ?> d-flex justify-content-between align-items-center" 
                            role="alert">
                            <span><?= htmlspecialchars($notification['message']) ?></span>
                            <form method="POST" class="ms-auto">
                                <input type="hidden" name="notification_id" value="<?= htmlspecialchars($notification['id']) ?>">
                                <button type="submit" name="delete_notification" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Messages -->
            <div class="mt-3 notif">
                <h2>Messages</h2>
                    <?php if (empty($messages)): ?>
                        <p>No messages yet.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($messages as $message): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($message['sender_name']) ?>:</strong>
                                        <p><?= htmlspecialchars($message['message']) ?></p>
                                        <small><?= $message['created_at'] ?></small>
                                    </div>
                                    <form method="POST" class="ms-auto">
                                        <input type="hidden" name="message_id" value="<?= htmlspecialchars($message['id']) ?>">
                                        <button type="submit" name="delete_message" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <textarea name="message_content" class="form-control" placeholder="Send a message to HR..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="hr_user" class="form-label">Select HR Representative</label>
                        <select name="hr_user" class="form-control" required>
                            <option value="" disabled selected>Select HR</option>
                            <?php foreach ($hrUsers as $hr): ?>
                                <option value="<?= $hr['id'] ?>"><?= htmlspecialchars($hr['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                </form>
            </div>
    
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const navbar = document.getElementById('navbar');
        const themeToggle = document.getElementById('themeToggle');
        

        // Load the saved theme from localStorage
        if (localStorage.getItem('theme') === 'dark') {
            body.classList.add('dark-mode');
            navbar.classList.add('dark-mode');
            themeToggle.textContent = 'Light Mode';
        }

        // Toggle the theme
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            navbar.classList.toggle('dark-mode');

            // Update the button text
            if (body.classList.contains('dark-mode')) {
                themeToggle.textContent = 'Light Mode';
                localStorage.setItem('theme', 'dark');
            } else {
                themeToggle.textContent = 'Dark Mode';
                localStorage.setItem('theme', 'light');
            }
        });
    });
</script>
</body>
</html>
