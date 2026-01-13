<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../config/csp.php';

requireAdminLogin($pdo, $logAdmin, $logConcern); // Ensures only logged-in admins can access this page
// INITIALIZATION of PARAMETERS


csrf_protection_start($logAdmin); //Initialize CSRF protection for all POST actions on this page

// Fetch the complete list of users for admin display
try {
    $users = dbFetchAllUsers($pdoLow); // Retrieves basic account details for each user
} catch (PDOException $e) {
   logAdminActions($logAdmin, 'fetching users', $_SESSION['email'], 'error', null); // Logs DB issues for admin auditing
}

// Fetch all uploaded media entries
try {
    $uploads = dbFetchAllMediasAndInfo($pdoLow); // Retrieves each upload plus uploader information
} catch (PDOException $e) {
   logAdminActions($logAdmin, 'fetching uploads', $_SESSION['email'], 'error', null); // Logs failed retrieval
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css"> <!-- Admin UI styling -->
    <script src="../assets/js/admin_dashboard.js" defer></script>    <!-- Client-side dashboard behavior -->
</head>
<body>

<header class="dashboard-header">
    <h1>Admin Dashboard</h1>
    <div class="admin-welcome">
        <!-- Display currently logged-in admin email safely -->
        <span>Welcome, Admin - <?= htmlspecialchars($_SESSION['email']) ?></span>

        <!-- Quick access to password change -->
        <a href="admin_change_password.php" class="btn change-password-btn">Change Password</a>

        <!-- Admin logout -->
        <a href="logout.php" class="btn logout-btn">Logout</a>
    </div>
</header>

<main class="container">

    <!-- USERS MANAGEMENT SECTION -->
    <section class="admin-section">
        <h2>Users</h2>

        <div class="table-wrapper">
            <table class="styled-table">
                <thead>
                    <tr>
                        <!-- Columns describing user account details -->
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th> <!-- Premium toggle, status toggle -->
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <!-- Display user info dynamically -->
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>

                        <td>
                            <!-- Show whether the user is a premium subscriber -->
                            <?= 
                                $user['is_premium']
                                ? '<span class="badge premium">Premium</span>'
                                : '<span class="badge free">Free</span>'
                            ?>
                        </td>

                        <!-- Show account status (active/inactive) -->
                        <td><?= htmlspecialchars($user['status']) ?></td>

                        <td>
                            <!-- ADMIN ACTION: Toggle premium membership -->
                            <form action="toggle_premium.php" method="post" class="action-form">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button class="btn small <?= $user['is_premium'] ? 'btn-secondary' : 'btn-gold' ?>">
                                    <?= $user['is_premium'] ? 'Remove Premium' : 'Give Premium' ?>
                                </button>
                            </form>

                            <!-- ADMIN ACTION: Activate or deactivate account -->
                            <form action="toggle_status.php" method="post" class="action-form">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button class="btn small <?= $user['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>">
                                    <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- UPLOADED MEDIA MANAGEMENT SECTION -->
    <section class="admin-section">
        <h2>Media Uploads</h2>

        <div class="table-wrapper">
            <table class="styled-table">
                <thead>
                    <tr>
                        <!-- Columns describing uploaded media -->
                        <th>ID</th>
                        <th>Title</th>
                        <th>Premium</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th>Actions</th> <!-- Delete upload -->
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($uploads as $upload): ?>
                    <tr>
                        <!-- Display basic media info -->
                        <td><?= $upload['id'] ?></td>
                        <td><?= htmlspecialchars($upload['title']) ?></td>
                        <td><?= $upload['is_premium'] ? 'Yes' : 'No' ?></td>

                        <!-- Show uploader username or fallback email -->
                        <td><?= htmlspecialchars($upload['username'] ?: $upload['email']) ?></td>

                        <!-- Timestamp of the upload -->
                        <td><?= $upload['created_at'] ?></td>

                        <td>
                            <!-- ADMIN ACTION: Delete uploaded media -->
                            <form action="delete_upload.php" method="post" class="action-form">
                                <input type="hidden" name="media_id" value="<?= $upload['id'] ?>">
                                <button class="btn small btn-danger">Delete</button>
                            </form>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>
