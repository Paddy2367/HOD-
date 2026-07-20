<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'faculty') {
    header("Location: login.php?role=faculty");
    exit;
}

$user = $_SESSION['user'];
$db = get_db();

$success_message = '';
$error_message = '';

// Handle Approve / Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $leave_id = intval($_POST['leave_id']);

    $updated = false;
    foreach ($db['leaves'] as &$leave) {
        if ($leave['id'] === $leave_id) {
            if ($action === 'approve') {
                $leave['status'] = 'Approved';
                $success_message = 'Leave request #' . $leave_id . ' (Reason: ' . $leave['reason'] . ') has been Approved.';
                $updated = true;
            } elseif ($action === 'reject') {
                $leave['status'] = 'Rejected';
                $success_message = 'Leave request #' . $leave_id . ' (Reason: ' . $leave['reason'] . ') has been Rejected.';
                $updated = true;
            }
            break;
        }
    }

    if ($updated) {
        save_db($db);
    } else {
        $error_message = 'Failed to update leave request status. Request #' . $leave_id . ' not found.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_notice') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['desc']);
    $expiry = trim($_POST['expiry']);
    $file_name = '';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = $upload_dir . basename($_FILES['attachment']['name']);
        move_uploaded_file($_FILES['attachment']['tmp_name'], $file_name);
    }
    
    if (!empty($title) && !empty($desc)) {
        $db['notices'][] = [
            'id' => count($db['notices']) + 1,
            'title' => $title,
            'desc' => $desc,
            'author' => $user['name'],
            'role' => 'Faculty (' . $user['dept'] . ')',
            'date' => date('d M Y'),
            'expiry' => $expiry,
            'attachment' => $file_name,
            'size' => $file_name ? '1.5MB' : ''
        ];
        save_db($db);
        $success_message = "Notice published successfully.";
    } else {
        $error_message = "Title and Description are required.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_assignment') {
    $unit = intval($_POST['unit']);
    $marks = trim($_POST['marks']);
    
    $updated = false;
    foreach ($db['assignments'] as &$a) {
        if ($a['unit'] === $unit) {
            $a['status'] = 'graded';
            $a['marks'] = $marks;
            $updated = true;
            break;
        }
    }
    if ($updated) {
        save_db($db);
        $success_message = "Assignment graded successfully.";
    }
}

// Reload database to get fresh updates
$db = get_db();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College ERP Portal - Faculty Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="theme-faculty">
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="sidebar-brand">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div>
                        <span>College ERP</span>
                        <span class="sub">Faculty Portal</span>
                    </div>
                </div>
                <ul class="sidebar-nav">
                    <li><a class="sidebar-nav-item active" onclick="switchTab('leaves', this)"><i class="fa-solid fa-envelope-open-text"></i><span>Leave Approvals</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('assignments', this)"><i class="fa-solid fa-file-invoice"></i><span>Manage Assignments</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('notices', this)"><i class="fa-solid fa-bullhorn"></i><span>Publish Notices</span></a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="logout.php" class="sidebar-nav-item" style="background: rgba(239, 68, 68, 0.1); color: #f87171;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
            </div>
        </aside>

        <!-- Main Dashboard View Area -->
        <main class="main-content">
            <!-- Header Widget -->
            <header class="dashboard-header">
                <div class="page-title-box">
                    <h2 id="currentTabTitle">Leave Approvals</h2>
                    <p id="currentTabSubtitle">Review, approve or reject student leave requests submitted for review.</p>
                </div>
                <div class="user-profile-widget">
                    <div class="notification-bell">
                        <i class="fa-regular fa-bell"></i>
                    </div>
                    <div class="user-avatar-box">
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="User Avatar">
                        <div class="user-details">
                            <span class="name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <span class="role"><?php echo htmlspecialchars($user['dept']); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Success/Error alert banner -->
            <?php if (!empty($success_message)): ?>
                <div class="error-message" style="display:flex; background: #ecfdf5; border-color: #a7f3d0; color: #065f46; margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-message" style="display:flex; margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- 1. LEAVE APPROVALS TAB                       -->
            <!-- ============================================ -->
            <div id="tab-leaves" class="app-view active">
                <div class="data-table-container">
                    <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">Active Leave Requests</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Student Details</th>
                                <th>Reason</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Leave Form</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center; width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['leaves'] as $leave): ?>
                                <tr>
                                    <td><?php echo $leave['id']; ?></td>
                                    <td>
                                        <div class="publisher-cell">
                                            <span class="pub-name"><?php echo htmlspecialchars($leave['applicant_name'] ?? 'Prasad Kulkarni'); ?></span>
                                            <span class="pub-role"><?php echo htmlspecialchars($leave['applicant_role'] ?? 'Student'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($leave['reason']); ?></span>
                                    </td>
                                    <td>
                                        <span class="date-cell"><?php echo htmlspecialchars($leave['from']); ?></span>
                                    </td>
                                    <td>
                                        <span class="date-cell"><?php echo htmlspecialchars($leave['to']); ?></span>
                                    </td>
                                    <td>
                                        <div class="publisher-cell" style="flex-direction:row; align-items:center; gap:0.5rem;">
                                            <?php 
                                                $ext = pathinfo($leave['file'], PATHINFO_EXTENSION);
                                                $is_pdf = (strtolower($ext) === 'pdf');
                                            ?>
                                            <i class="fa-solid <?php echo $is_pdf?'fa-file-pdf':'fa-file-word'; ?>" style="font-size:1.15rem; color:<?php echo $is_pdf?'#ef4444':'#0284c7'; ?>"></i>
                                            <a href="<?= htmlspecialchars($leave['file']) ?>" class="pub-name" style="font-size:0.9rem; font-weight:500; text-decoration:none; color: var(--primary-color);" target="_blank">
                                                <?php echo basename(htmlspecialchars($leave['file'])); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php 
                                            $status = strtolower($leave['status']);
                                            $pill_class = ($status === 'approved') ? 'graded' : (($status === 'pending') ? 'pending' : 'rejected');
                                        ?>
                                        <span class="status-pill <?php echo $pill_class; ?>"><?php echo htmlspecialchars($leave['status']); ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($status === 'pending'): ?>
                                            <div class="faculty-actions-cell">
                                                <form method="POST" action="faculty_dashboard.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" class="btn-approve">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="faculty_dashboard.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" class="btn-reject">
                                                        <i class="fa-solid fa-xmark"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">No Action Needed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- ASSIGNMENTS TAB                              -->
            <!-- ============================================ -->
            <div id="tab-assignments" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">Grade Assignments</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject/Unit</th>
                                <th>Student</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['assignments'] as $a): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($a['title'] ?? 'Unit Assignment') ?></strong><br>
                                    <span style="font-size:0.85rem;color:var(--text-muted);">Unit <?= htmlspecialchars($a['unit']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($a['student_name'] ?? 'Prasad Kulkarni') ?></td>
                                <td><?= htmlspecialchars($a['due']) ?></td>
                                <td><span class="status-pill <?= htmlspecialchars($a['status']) ?>"><?= ucfirst(htmlspecialchars($a['status'])) ?></span></td>
                                <td>
                                    <?php if ($a['status'] === 'submitted' || $a['status'] === 'pending'): ?>
                                    <form method="POST" style="display:flex; gap:0.5rem; align-items:center;">
                                        <input type="hidden" name="action" value="grade_assignment">
                                        <input type="hidden" name="unit" value="<?= $a['unit'] ?>">
                                        <input type="text" name="marks" placeholder="Marks (e.g. 10/10)" style="padding:0.25rem; width: 100px; font-size:0.85rem;" required>
                                        <button type="submit" class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:0.85rem;">Grade</button>
                                    </form>
                                    <?php elseif ($a['status'] === 'graded'): ?>
                                    <span style="font-size:0.85rem; font-weight:600; color:var(--primary-color);">Graded: <?= htmlspecialchars($a['marks']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- NOTICES TAB                                  -->
            <!-- ============================================ -->
            <div id="tab-notices" class="app-view">
                <div class="settings-form-container" style="margin: 0 auto; max-width: 600px; padding: 2rem; background: white; border: 1px solid var(--border-color); border-radius: 8px;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="publish_notice">
                        <h3 style="margin-bottom:1.5rem; color: #111827;">Publish New Notice</h3>
                        <div class="form-group-col" style="margin-bottom:1rem;">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="e.g. Extra Class Scheduled" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                        </div>
                        <div class="form-group-col" style="margin-bottom:1rem;">
                            <label>Description</label>
                            <textarea name="desc" rows="4" required placeholder="Enter notice details..." style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; font-family: inherit;"></textarea>
                        </div>
                        <div class="form-group-col" style="margin-bottom:1rem;">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                        </div>
                        <div class="form-group-col" style="margin-bottom:1.5rem;">
                            <label>Attachment (Optional)</label>
                            <input type="file" name="attachment" style="padding:0.5rem 0;">
                        </div>
                        <div style="display:flex; justify-content:flex-end;">
                            <button type="submit" class="btn-hod-action" style="background:var(--primary-color);color:white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Publish Notice</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <!-- JavaScript code for navigation -->
    <script>
        function switchTab(tabName, element) {
            const items = document.querySelectorAll('.sidebar-nav-item');
            items.forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            const panels = document.querySelectorAll('.app-view');
            panels.forEach(p => p.classList.remove('active'));

            const headerTitle = document.getElementById('currentTabTitle');
            const headerSubtitle = document.getElementById('currentTabSubtitle');

            document.getElementById('tab-' + tabName).classList.add('active');

            if (tabName === 'leaves') {
                headerTitle.textContent = "Leave Approvals";
                headerSubtitle.textContent = "Review, approve or reject student leave requests submitted for review.";
            } else if (tabName === 'assignments') {
                headerTitle.textContent = "Manage Assignments";
                headerSubtitle.textContent = "View and grade submitted student code assignments.";
            } else if (tabName === 'notices') {
                headerTitle.textContent = "Publish Notices";
                headerSubtitle.textContent = "Compose and publish academic notices directly to the Student Portal.";
            }
        }
    </script>
</body>
</html>
