<?php
require 'auth.php';
checkRole('HR');
require 'core/dbConfig.php';

// Fetching applications (filter out accepted/rejected)
$applications = $pdo->query("SELECT a.*, u.username 
                             FROM applications a 
                             JOIN users u ON a.applicant_id = u.id
                             WHERE a.status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);

// Fetch accepted applicants data (initial fetch)
$sql = "SELECT 
            u.username AS applicant_name,
            jp.title AS job_title,
            hr.username AS accepted_by,
            aa.accepted_date
        FROM accepted_applicants aa
        JOIN applications a ON aa.application_id = a.id
        JOIN users u ON a.applicant_id = u.id
        JOIN job_posts jp ON a.job_id = jp.id
        JOIN users hr ON aa.accepted_by = hr.id";
$acceptedApplicants = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Handling form submissions for accepting/rejecting applications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create Job Post
    if (isset($_POST['create_job'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $stmt = $pdo->prepare("INSERT INTO job_posts (title, description, created_by) VALUES (:title, :description, :created_by)");
        $stmt->execute(['title' => $title, 'description' => $description, 'created_by' => $_SESSION['user_id']]);
    }

    // Update Application Status and Notify Applicant
    if (isset($_POST['status']) && isset($_POST['application_id']) && isset($_POST['applicant_id'])) {
        $status = $_POST['status'];
        $applicationId = $_POST['application_id'];
        $applicantId = $_POST['applicant_id'];

        // Update application status
        $stmt = $pdo->prepare("UPDATE applications SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $applicationId]);

        // Insert notification for the applicant
        $notification = "Your application has been " . strtolower($status) . ".";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $stmt->execute(['user_id' => $applicantId, 'message' => $notification]);
    }

    // Accepting job applicants and displaying who's accepted and on what job post
    if (isset($_POST['accept'])) {
        $applicationId = $_POST['application_id'];
        $hrUserId = $_SESSION['user_id']; // Assuming logged-in user ID is stored in session

        // Update status in `applications`
        $updateStmt = $pdo->prepare("UPDATE applications SET status = 'Accepted' WHERE id = :id");
        $updateStmt->execute(['id' => $applicationId]);

        // Insert record in `accepted_applicants`
        $insertStmt = $pdo->prepare("INSERT INTO accepted_applicants (application_id, accepted_by) VALUES (:application_id, :accepted_by)");
        $insertStmt->execute(['application_id' => $applicationId, 'accepted_by' => $hrUserId]);

        // Fetch the updated list of accepted applicants after acceptance
        $acceptedApplicants = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Send a reply to the applicant's message
    if (isset($_POST['send_reply']) && isset($_POST['message_id']) && isset($_POST['reply_message'])) {
        $messageId = $_POST['message_id'];
        $replyMessage = $_POST['reply_message'];

        // Insert reply with reference to the original message
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, message, reply_to) VALUES (:sender_id, :recipient_id, :message, :reply_to)");
        $stmt->execute([ 
            'sender_id' => $_SESSION['user_id'],
            'recipient_id' => $_POST['recipient_id'],
            'message' => $replyMessage,
            'reply_to' => $messageId
        ]);
    }
}

// Fetch job posts created by the logged-in HR user
$jobPosts = $pdo->query("SELECT * FROM job_posts WHERE created_by = " . $_SESSION['user_id'])->fetchAll();

// Fetch messages for HR (with replies)
$messages = $pdo->prepare("SELECT m.*, u.username AS sender_name
                           FROM messages m
                           JOIN users u ON m.sender_id = u.id
                           WHERE m.recipient_id = :hr_id
                           ORDER BY m.created_at DESC");
$messages->execute(['hr_id' => $_SESSION['user_id']]);
$messages = $messages->fetchAll();

?>

<!DOCTYPE html>
<html>
<head>
    <title>HR Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
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
            border: 1px solid #00a4ad;
            background-color: ;
            padding: 5px;
            border-top-left-radius: 0px;
            border-bottom-left-radius: 0px;

        }

        .job-post {
            border-radius: 10px;
  
            border-top-right-radius: 0px;
            border-bottom-right-radius: 0px;
            background: rgba( 0, 164, 173, 0.3 );
            box-shadow: 0 8px 32px 0 rgba( 31, 38, 135, 0.37 );
            backdrop-filter: blur( 5px );
            -webkit-backdrop-filter: blur( 5px );
            padding: 5px;
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
    <h1>HR Dashboard</h1>

    <!-- Create Job Post Section -->
    <div class="row">
        <div class="col-md-6">
            <h2>Create Job Post</h2>
            <form method="POST">
                <div class="mb-3">
                    <label for="title" class="form-label">Job Title</label>
                    <input type="text" class="form-control" name="title" id="title" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" name="description" id="description" rows="4" required></textarea>
                </div>
                <button type="submit" name="create_job" class="btn btn-primary">Post Job</button>
            </form>
        </div>

        <!-- Applications Section -->
        <div class="col-md-6">
            <h2>Applications</h2>
            <?php foreach ($applications as $application): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Application for Job ID: <?= htmlspecialchars($application['job_id']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($application['message']) ?></p>
                        <p>By: <?= htmlspecialchars($application['username']) ?></p>
                        <a href="<?= htmlspecialchars($application['resume_path']) ?>" class="btn btn-link" target="_blank">View Resume</a>

                        <!-- Accept/Reject Application buttons -->
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="application_id" value="<?= htmlspecialchars($application['id']) ?>">
                            <input type="hidden" name="applicant_id" value="<?= htmlspecialchars($application['applicant_id']) ?>">
                            <button type="submit" name="accept" class="btn btn-success">Accept</button>
                            <button type="submit" name="status" value="Rejected" class="btn btn-danger">Reject</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Messages Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <h2>Messages</h2>
            <ul class="list-group">
                <?php foreach ($messages as $message): ?>
                    <li class="list-group-item">
                        <strong>From <?= htmlspecialchars($message['sender_name']) ?>:</strong> <?= htmlspecialchars($message['message']) ?>
                        <?php if ($message['reply_to']): ?>
                            <div class="mt-2">
                                <strong>Reply:</strong> <?= htmlspecialchars($message['message']) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                            <input type="hidden" name="recipient_id" value="<?= $message['sender_id'] ?>">
                            <textarea name="reply_message" class="form-control" placeholder="Write your reply..." required></textarea>
                            <button type="submit" name="send_reply" class="btn btn-primary mt-2">Send Reply</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Accepted applicants table -->
    <div class="container mt-5">
        <h2 class="mb-4">Accepted Applicants</h2>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">Applicant Name</th>
                        <th scope="col">Job Title</th>
                        <th scope="col">Accepted By</th>
                        <th scope="col">Accepted Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($acceptedApplicants): ?>
                    <?php foreach ($acceptedApplicants as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['applicant_name']) ?></td>
                            <td><?= htmlspecialchars($row['job_title']) ?></td>
                            <td><?= htmlspecialchars($row['accepted_by']) ?></td>
                            <td><?= htmlspecialchars($row['accepted_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No accepted applicants found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// JavaScript for dark mode toggle
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
