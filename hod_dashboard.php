<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'hod') {
    header("Location: login.php?role=hod");
    exit;
}

$user = $_SESSION['user'];
$db = get_db();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // -- LEAVE ACTIONS --
    if ($action === 'approve_leave' || $action === 'reject_leave') {
        $leave_id = intval($_POST['leave_id']);
        $new_status = ($action === 'approve_leave') ? 'Approved' : 'Rejected';
        
        $updated = false;
        foreach ($db['leaves'] as &$leave) {
            if ($leave['id'] === $leave_id) {
                $leave['status'] = $new_status;
                $updated = true;
                break;
            }
        }
        if ($updated) {
            save_db($db);
            $success_message = "Leave #$leave_id has been $new_status.";
        } else {
            $error_message = "Leave request not found.";
        }
    }
    
    // -- SETTINGS --
    elseif ($action === 'save_settings') {
        $db['settings']['dept_name'] = $_POST['dept_name'] ?? $db['settings']['dept_name'];
        $db['settings']['dept_code'] = $_POST['dept_code'] ?? $db['settings']['dept_code'];
        $db['settings']['hod_name']  = $_POST['hod_name'] ?? $db['settings']['hod_name'];
        $db['settings']['hod_email'] = $_POST['hod_email'] ?? $db['settings']['hod_email'];
        $db['settings']['maintenance_mode'] = isset($_POST['maintenance_mode']);
        
        save_db($db);
        $success_message = "Settings updated successfully.";
    }
    
    // -- NOTICES --
    elseif ($action === 'publish_notice') {
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
                'role' => 'Head of Department',
                'date' => date('d M Y'),
                'expiry' => $expiry,
                'attachment' => $file_name,
                'size' => $file_name ? '1.5MB' : ''
            ];
            
            $db['recent_activity'] = array_merge([
                [
                    'title' => 'Notice Published',
                    'desc' => 'HOD published a new notice: ' . $title,
                    'time' => 'Just now'
                ]
            ], array_slice($db['recent_activity'], 0, 3));
            
            save_db($db);
            $success_message = "Notice published successfully.";
        } else {
            $error_message = "Title and Description are required.";
        }
    }
    
    // -- GRIEVANCE REPLY --
    elseif ($action === 'reply_grievance') {
        $grievance_id = intval($_POST['grievance_id']);
        $reply_msg = trim($_POST['reply_msg']);
        $new_status = trim($_POST['status']);
        
        if (!empty($reply_msg)) {
            $updated = false;
            foreach ($db['grievances'] as &$g) {
                if ($g['id'] === $grievance_id) {
                    $g['status'] = $new_status;
                    $g['replies'][] = [
                        'author' => $user['name'],
                        'role' => 'Head of Department',
                        'date' => date('d M Y h:i A'),
                        'message' => $reply_msg
                    ];
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                save_db($db);
                $success_message = "Reply sent and status updated.";
            } else {
                $error_message = "Grievance not found.";
            }
        }
    }
    
    // -- ASSIGNMENT GRADING --
    elseif ($action === 'grade_assignment') {
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
    
    // -- REPORTS --
    elseif ($action === 'download_report') {
        $type = $_POST['report_type'];
        $sem = $_POST['semester'];
        
        // Output CSV and exit
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        
        if ($type === 'Attendance Defaulters') {
            fputcsv($output, ['Student Name', 'ID', 'Department', 'Semester', 'Attendance']);
            foreach ($db['students'] as $s) {
                if (intval($s['attendance']) < 75 && $s['semester'] == $sem) {
                    fputcsv($output, [$s['name'], $s['id'], $s['dept'], $s['semester'], $s['attendance']]);
                }
            }
        } elseif ($type === 'Grievance Summary') {
            fputcsv($output, ['Title', 'Student', 'Category', 'Status', 'Date']);
            foreach ($db['grievances'] as $g) {
                fputcsv($output, [$g['title'], $g['student_name'], $g['category'], $g['status'], $g['date']]);
            }
        } else {
            fputcsv($output, ['Report Type', 'Semester']);
            fputcsv($output, [$type, $sem]);
        }
        
        fclose($output);
        exit;
    }
}

// Calculators for dashboard stats
$total_students = count($db['students']);
$total_faculty = count($db['faculty']);
$total_notices = count($db['notices']);
$unresolved_grievances = 0;
foreach ($db['grievances'] as $g) {
    if ($g['status'] !== 'Resolved') {
        $unresolved_grievances++;
    }
}
$pending_leaves = 0;
foreach ($db['leaves'] as $l) {
    if ($l['status'] === 'Pending') {
        $pending_leaves++;
    }
}
$pending_approvals = $pending_leaves + $unresolved_grievances;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - Student Welfare Portal</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="theme-hod">
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" style="background: #ffffff; color: #4b5563; border-right: 1px solid var(--border-color);">
            <div class="sidebar-top">
                <div class="sidebar-brand" style="color: #111827;">
                    <i class="fa-solid fa-building-columns" style="color: var(--primary-color);"></i>
                    <div>
                        <span>SW Portal</span>
                        <span class="sub" style="color: var(--text-muted);">HOD Dashboard</span>
                    </div>
                </div>
                <ul class="sidebar-nav">
                    <li><a class="sidebar-nav-item active" style="color: #4b5563;" data-tab="dashboard" onclick="switchTab('dashboard')"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="assignments" onclick="switchTab('assignments')"><i class="fa-solid fa-book"></i><span>Assignments</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="leaves" onclick="switchTab('leaves')"><i class="fa-solid fa-calendar-minus"></i><span>Leaves</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="grievances" onclick="switchTab('grievances')"><i class="fa-solid fa-circle-exclamation"></i><span>Grievances</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="notices" onclick="switchTab('notices')"><i class="fa-solid fa-bullhorn"></i><span>Notices</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="students" onclick="switchTab('students')"><i class="fa-solid fa-user-graduate"></i><span>Students</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="faculty" onclick="switchTab('faculty')"><i class="fa-solid fa-chalkboard-user"></i><span>Faculty</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="reports" onclick="switchTab('reports')"><i class="fa-solid fa-chart-column"></i><span>Reports</span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="approvals" onclick="switchTab('approvals')"><i class="fa-solid fa-check-double"></i><span>Approvals <span class="badge" style="background:var(--primary-color);color:white;border-radius:10px;padding:2px 6px;font-size:0.7rem;margin-left:auto;"><?= $pending_approvals ?></span></span></a></li>
                    <li><a class="sidebar-nav-item" style="color: #4b5563;" data-tab="settings" onclick="switchTab('settings')"><i class="fa-solid fa-gear"></i><span>Settings</span></a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="logout.php" class="sidebar-nav-item" style="color: #ef4444;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <div class="page-title-box">
                    <h2 id="currentTabTitle">Overview</h2>
                    <p id="currentTabSubtitle">Welcome back, <?= htmlspecialchars($user['name']) ?></p>
                </div>
                <div class="user-profile-widget">
                    <div class="notification-bell">
                        <i class="fa-regular fa-bell"></i>
                    </div>
                    <div class="user-avatar-box">
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="User Avatar">
                        <div class="user-details">
                            <span class="name"><?= htmlspecialchars($user['name']) ?></span>
                            <span class="role">Head of Department</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Success/Error alert banner -->
            <?php if (!empty($success_message)): ?>
                <div class="error-message" style="display:flex; background: #ecfdf5; border-color: #a7f3d0; color: #065f46; margin-bottom: 1.5rem; margin-top: 1.5rem; margin-left: 1.5rem; margin-right: 1.5rem;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-message" style="display:flex; margin-bottom: 1.5rem; margin-top: 1.5rem; margin-left: 1.5rem; margin-right: 1.5rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Dashboard View -->
            <div id="view-dashboard" class="app-view active">
                <div class="hod-grid">
                    <!-- Assignments Card -->
                    <div class="hod-card purple">
                        <div class="hod-card-header">
                            <div class="hod-card-icon"><i class="fa-solid fa-book"></i></div>
                            <div class="hod-card-title">
                                <h3>Assignments</h3>
                                <p>Manage & Review</p>
                            </div>
                        </div>
                        <div class="hod-card-body">
                            <div class="hod-stat">
                                <span class="hod-stat-label">Total Published</span>
                                <span class="hod-stat-value"><?= count($db['assignments']) ?></span>
                            </div>
                        </div>
                        <div class="hod-card-footer">
                            <button class="btn-hod-action" onclick="switchTab('assignments')">Manage</button>
                        </div>
                    </div>

                    <!-- Leaves Card -->
                    <div class="hod-card green">
                        <div class="hod-card-header">
                            <div class="hod-card-icon"><i class="fa-solid fa-calendar-minus"></i></div>
                            <div class="hod-card-title">
                                <h3>Leave Requests</h3>
                                <p>Faculty & Students</p>
                            </div>
                        </div>
                        <div class="hod-card-body">
                            <div class="hod-stat">
                                <span class="hod-stat-label">Pending Approval</span>
                                <span class="hod-stat-value"><?= $pending_leaves ?></span>
                            </div>
                        </div>
                        <div class="hod-card-footer">
                            <button class="btn-hod-action" onclick="switchTab('leaves')">Review</button>
                        </div>
                    </div>

                    <!-- Grievance Card -->
                    <div class="hod-card orange">
                        <div class="hod-card-header">
                            <div class="hod-card-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                            <div class="hod-card-title">
                                <h3>Grievances</h3>
                                <p>Student Complaints</p>
                            </div>
                        </div>
                        <div class="hod-card-body">
                            <div class="hod-stat">
                                <span class="hod-stat-label">Unresolved</span>
                                <span class="hod-stat-value"><?= $unresolved_grievances ?></span>
                            </div>
                        </div>
                        <div class="hod-card-footer">
                            <button class="btn-hod-action" onclick="switchTab('grievances')">Resolve</button>
                        </div>
                    </div>

                    <!-- Notices Card -->
                    <div class="hod-card blue">
                        <div class="hod-card-header">
                            <div class="hod-card-icon"><i class="fa-solid fa-bullhorn"></i></div>
                            <div class="hod-card-title">
                                <h3>Notices</h3>
                                <p>Announcements</p>
                            </div>
                        </div>
                        <div class="hod-card-body">
                            <div class="hod-stat">
                                <span class="hod-stat-label">Active Notices</span>
                                <span class="hod-stat-value"><?= $total_notices ?></span>
                            </div>
                        </div>
                        <div class="hod-card-footer">
                            <button class="btn-hod-action" onclick="switchTab('notices')">Publish</button>
                        </div>
                    </div>

                    <!-- Students Card -->
                    <div class="hod-card teal">
                        <div class="hod-card-header">
                            <div class="hod-card-icon"><i class="fa-solid fa-user-graduate"></i></div>
                            <div class="hod-card-title">
                                <h3>Students</h3>
                                <p>Directory & Analytics</p>
                            </div>
                        </div>
                        <div class="hod-card-body">
                            <div class="hod-stat">
                                <span class="hod-stat-label">Total Enrolled</span>
                                <span class="hod-stat-value"><?= $total_students ?></span>
                            </div>
                        </div>
                        <div class="hod-card-footer">
                            <button class="btn-hod-action" onclick="switchTab('students')">View</button>
                        </div>
                    </div>

                    <!-- Faculty Card -->
                    <div class="hod-card indigo">
                        <div class="hod-card-header">
                            <div class="hod-card-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                            <div class="hod-card-title">
                                <h3>Faculty</h3>
                                <p>Staff Management</p>
                            </div>
                        </div>
                        <div class="hod-card-body">
                            <div class="hod-stat">
                                <span class="hod-stat-label">Active Staff</span>
                                <span class="hod-stat-value"><?= $total_faculty ?></span>
                            </div>
                        </div>
                        <div class="hod-card-footer">
                            <button class="btn-hod-action" onclick="switchTab('faculty')">View</button>
                        </div>
                    </div>
                </div>

                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">Recent Activity</h4>
                    </div>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($db['recent_activity'] as $activity): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($activity['title']) ?></div>
                                    <div style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($activity['desc']) ?></div>
                                </td>
                                <td style="text-align:right;color:var(--text-muted);font-size:0.85rem;">
                                    <?= htmlspecialchars($activity['time']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Assignments View -->
            <div id="view-assignments" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">All Assignments</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Unit / Title</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['assignments'] as $a): ?>
                            <tr>
                                <td>
                                    <div class="notice-title">Unit <?= htmlspecialchars($a['unit']) ?> - <?= htmlspecialchars($a['title']) ?></div>
                                    <div class="notice-desc">By <?= htmlspecialchars($a['created_by']) ?></div>
                                </td>
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

            <!-- Leaves View -->
            <div id="view-leaves" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">Leave Requests</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['leaves'] as $l): ?>
                            <tr>
                                <td>
                                    <div class="notice-title"><?= htmlspecialchars($l['applicant_name']) ?></div>
                                    <div class="notice-desc"><?= htmlspecialchars($l['applicant_role']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($l['from']) ?> to <?= htmlspecialchars($l['to']) ?></td>
                                <td><?= htmlspecialchars($l['reason']) ?></td>
                                <td><span class="status-pill <?= strtolower($l['status']) ?>"><?= htmlspecialchars($l['status']) ?></span></td>
                                <td class="faculty-actions-cell">
                                    <?php if($l['status'] === 'Pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_leave">
                                        <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-approve">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reject_leave">
                                        <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-reject">Reject</button>
                                    </form>
                                    <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:0.85rem;">Reviewed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grievances View -->
            <div id="view-grievances" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">Grievances</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Issue</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['grievances'] as $g): ?>
                            <tr>
                                <td>
                                    <div class="notice-title"><?= htmlspecialchars($g['student_name']) ?></div>
                                    <div class="notice-desc"><?= htmlspecialchars($g['student_id']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:500;"><?= htmlspecialchars($g['title']) ?></div>
                                    <div class="notice-desc"><?= htmlspecialchars($g['category']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($g['date']) ?></td>
                                <td><span class="status-pill <?= strtolower(str_replace(' ', '-', $g['status'])) ?>"><?= htmlspecialchars($g['status']) ?></span></td>
                                <td>
                                    <button class="btn-secondary" onclick="openGrievanceModal(<?= $g['id'] ?>)">View Chat</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notices View -->
            <div id="view-notices" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">Notices</h4>
                        <button class="btn-approve" onclick="document.getElementById('modal-publish-notice').classList.add('active')">Publish New</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Notice</th>
                                <th>Publisher</th>
                                <th>Date / Expiry</th>
                                <th>Attachment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['notices'] as $n): ?>
                            <tr>
                                <td>
                                    <div class="notice-title"><?= htmlspecialchars($n['title']) ?></div>
                                    <div class="notice-desc"><?= htmlspecialchars($n['desc']) ?></div>
                                </td>
                                <td class="publisher-cell">
                                    <span class="pub-name"><?= htmlspecialchars($n['author']) ?></span>
                                    <span class="pub-role"><?= htmlspecialchars($n['role']) ?></span>
                                </td>
                                <td>
                                    <div class="date-cell"><?= htmlspecialchars($n['date']) ?></div>
                                    <?php if($n['expiry']): ?>
                                    <div class="notice-desc" style="color:#ef4444;">Exp: <?= htmlspecialchars($n['expiry']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($n['attachment']): ?>
                                        <?php $ext = pathinfo($n['attachment'], PATHINFO_EXTENSION); ?>
                                        <a href="<?= htmlspecialchars($n['attachment']) ?>" class="attachment-badge <?= $ext ?>" target="_blank" style="text-decoration:none;">
                                            <i class="fa-solid fa-file-<?= $ext ?>"></i> <?= basename(htmlspecialchars($n['attachment'])) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="notice-desc">No File</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Students View -->
            <div id="view-students" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">Enrolled Students</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>Contact</th>
                                <th>Department</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['students'] as $s): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:1rem;">
                                        <img src="<?= htmlspecialchars($s['avatar']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                        <div>
                                            <div class="notice-title"><?= htmlspecialchars($s['name']) ?></div>
                                            <div class="notice-desc"><?= htmlspecialchars($s['id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($s['email']) ?></div>
                                    <div class="notice-desc"><?= htmlspecialchars($s['phone']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($s['dept']) ?></div>
                                    <div class="notice-desc"><?= htmlspecialchars($s['semester']) ?></div>
                                </td>
                                <td><span style="font-weight:600;color:var(--primary-color);"><?= htmlspecialchars($s['attendance']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Faculty View -->
            <div id="view-faculty" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">Teaching Faculty</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>Contact</th>
                                <th>Workload</th>
                                <th>Subjects</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['faculty'] as $f): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:1rem;">
                                        <img src="<?= htmlspecialchars($f['avatar']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                        <div>
                                            <div class="notice-title"><?= htmlspecialchars($f['name']) ?></div>
                                            <div class="notice-desc"><?= htmlspecialchars($f['designation']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($f['email']) ?></div>
                                    <div class="notice-desc"><?= htmlspecialchars($f['phone']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($f['workload']) ?></div>
                                    <div class="notice-desc">Att: <?= htmlspecialchars($f['attendance']) ?></div>
                                </td>
                                <td>
                                    <div style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($f['subjects']) ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reports View -->
            <div id="view-reports" class="app-view">
                <div class="settings-form-container" style="margin: 0 auto;">
                    <h3 style="margin-bottom:1.5rem;">Generate Reports</h3>
                    <form method="POST" action="hod_dashboard.php">
                        <input type="hidden" name="action" value="download_report">
                        <div class="form-row">
                            <div class="form-group-col">
                                <label>Report Type</label>
                                <select name="report_type">
                                    <option>Attendance Defaulters</option>
                                    <option>Academic Performance</option>
                                    <option>Grievance Summary</option>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Semester</label>
                                <select name="semester">
                                    <option>5th Semester</option>
                                    <option>6th Semester</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group-col" style="margin-bottom:1.5rem;">
                            <label>Additional Filters</label>
                            <input type="text" placeholder="e.g. Attendance < 75%">
                        </div>
                        <div style="display:flex;gap:1rem;justify-content:flex-end;">
                            <button type="submit" class="btn-secondary"><i class="fa-solid fa-file-csv"></i> Download CSV</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Approvals View -->
            <div id="view-approvals" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters">
                        <h4 style="margin-right:auto;font-weight:700;">Pending Actions</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Details</th>
                                <th>Requested By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['leaves'] as $l): if($l['status'] === 'Pending'): ?>
                            <tr>
                                <td><span class="status-pill pending">Leave</span></td>
                                <td><?= htmlspecialchars($l['reason']) ?> (<?= htmlspecialchars($l['from']) ?> - <?= htmlspecialchars($l['to']) ?>)</td>
                                <td><?= htmlspecialchars($l['applicant_name']) ?></td>
                                <td class="faculty-actions-cell">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_leave">
                                        <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-approve">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reject_leave">
                                        <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; endforeach; ?>
                            
                            <?php foreach ($db['grievances'] as $g): if($g['status'] !== 'Resolved'): ?>
                            <tr>
                                <td><span class="status-pill pending">Grievance</span></td>
                                <td><?= htmlspecialchars($g['title']) ?></td>
                                <td><?= htmlspecialchars($g['student_name']) ?></td>
                                <td class="faculty-actions-cell">
                                    <button class="btn-secondary" onclick="openGrievanceModal(<?= $g['id'] ?>)">Resolve</button>
                                </td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Settings View -->
            <div id="view-settings" class="app-view">
                <div class="settings-form-container" style="margin: 0 auto;">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <h3 style="margin-bottom:1.5rem;">Department Settings</h3>
                        <div class="form-row">
                            <div class="form-group-col">
                                <label>Department Name</label>
                                <input type="text" name="dept_name" value="<?= htmlspecialchars($db['settings']['dept_name']) ?>">
                            </div>
                            <div class="form-group-col">
                                <label>Department Code</label>
                                <input type="text" name="dept_code" value="<?= htmlspecialchars($db['settings']['dept_code']) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group-col">
                                <label>HOD Name</label>
                                <input type="text" name="hod_name" value="<?= htmlspecialchars($db['settings']['hod_name']) ?>">
                            </div>
                            <div class="form-group-col">
                                <label>Contact Email</label>
                                <input type="email" name="hod_email" value="<?= htmlspecialchars($db['settings']['hod_email']) ?>">
                            </div>
                        </div>
                        <div class="form-row" style="margin-bottom: 2rem;">
                            <div class="form-group-col" style="flex-direction:row;align-items:center;gap:1rem;">
                                <input type="checkbox" id="maintenance" name="maintenance_mode" <?= $db['settings']['maintenance_mode'] ? 'checked' : '' ?> style="width:20px;height:20px;">
                                <label for="maintenance">Maintenance Mode (Hide portal for students)</label>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn-hod-action" style="background:var(--primary-color);color:white;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Publish Notice Modal -->
            <div class="modal-overlay" id="modal-publish-notice">
                <div class="modal-card" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3>Publish New Notice</h3>
                        <button class="btn-close-modal" onclick="document.getElementById('modal-publish-notice').classList.remove('active');"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="publish_notice">
                        <div class="modal-body">
                            <div class="form-group-col" style="margin-bottom:1rem;">
                                <label>Title</label>
                                <input type="text" name="title" required placeholder="e.g. Mid-term Exam Schedule">
                            </div>
                            <div class="form-group-col" style="margin-bottom:1rem;">
                                <label>Description</label>
                                <textarea name="desc" rows="4" required placeholder="Enter notice details..."></textarea>
                            </div>
                            <div class="form-group-col" style="margin-bottom:1rem;">
                                <label>Expiry Date</label>
                                <input type="date" name="expiry">
                            </div>
                            <div class="form-group-col" style="margin-bottom:1rem;">
                                <label>Attachment (Optional)</label>
                                <input type="file" name="attachment" style="padding:0.5rem 0;">
                            </div>
                            <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:1.5rem;">
                                <button type="button" class="btn-secondary" onclick="document.getElementById('modal-publish-notice').classList.remove('active');">Cancel</button>
                                <button type="submit" class="btn-hod-action" style="background:var(--primary-color);color:white;">Publish</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Grievance Chat Modal -->
            <div class="modal-overlay" id="modal-grievance">
                <div class="modal-card">
                    <div class="modal-header">
                        <h3>Grievance Details</h3>
                        <button class="btn-close-modal" onclick="document.getElementById('modal-grievance').classList.remove('active');"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
                        <div style="margin-bottom:1.5rem;">
                            <h4 id="modalGrievanceTitle" style="font-size:1.1rem;font-weight:700;margin-bottom:0.25rem;">Grievance Title</h4>
                            <div id="modalGrievanceAuthorDate" style="font-size:0.85rem;color:var(--text-muted);">Reported by Student on Date</div>
                        </div>
                        
                        <div class="grievance-chat" id="modalChatContainer">
                            <!-- Dynamically loaded -->
                        </div>
                        
                        <div class="chat-reply-box">
                            <label style="font-size:0.9rem;font-weight:600;">Add Reply / Change Status</label>
                            <form method="POST">
                                <input type="hidden" name="action" value="reply_grievance">
                                <input type="hidden" id="modal_grievance_id" name="grievance_id" value="">
                                <textarea name="reply_msg" rows="3" style="width:100%;padding:0.75rem;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);font-family:var(--font-primary);" placeholder="Type your resolution..." required></textarea>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.5rem;">
                                    <select name="status" id="modalGrievanceStatusSelect" style="padding:0.5rem;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);">
                                        <option value="Pending">Mark as Pending</option>
                                        <option value="In Progress">Mark as In Progress</option>
                                        <option value="Resolved">Mark as Resolved</option>
                                    </select>
                                    <button type="submit" class="btn-hod-action" style="background:var(--primary-color);color:white;">Send Reply</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        const grievancesData = <?= json_encode($db['grievances']) ?>;

        const titles = {
            'dashboard': { title: 'Overview', sub: 'Welcome back, <?= addslashes(htmlspecialchars($user["name"])) ?>' },
            'assignments': { title: 'Assignments', sub: 'Manage and review student assignments' },
            'leaves': { title: 'Leave Requests', sub: 'Review and approve leave applications' },
            'grievances': { title: 'Grievances', sub: 'Address and resolve student complaints' },
            'notices': { title: 'Notice Board', sub: 'Publish and manage department announcements' },
            'students': { title: 'Student Directory', sub: 'View student profiles and analytics' },
            'faculty': { title: 'Faculty Directory', sub: 'Manage teaching staff and workload' },
            'reports': { title: 'Reports & Analytics', sub: 'Generate and download department reports' },
            'approvals': { title: 'Pending Approvals', sub: 'Consolidated view of all pending actions' },
            'settings': { title: 'Settings', sub: 'Configure department preferences' }
        };

        function switchTab(tabId) {
            const el = document.querySelector('.sidebar-nav-item[data-tab="' + tabId + '"]');
            
            document.querySelectorAll('.sidebar-nav-item').forEach(nav => nav.classList.remove('active'));
            document.querySelectorAll('.sidebar-nav-item').forEach(nav => nav.style.background = 'transparent');
            document.querySelectorAll('.sidebar-nav-item').forEach(nav => nav.style.color = '#4b5563');
            
            if (el) {
                el.classList.add('active');
                el.style.background = 'var(--primary-light)';
                el.style.color = 'var(--primary-color)';
            }
            
            document.querySelectorAll('.app-view').forEach(view => view.classList.remove('active'));
            document.getElementById('view-' + tabId).classList.add('active');
            
            if(titles[tabId]) {
                document.getElementById('currentTabTitle').textContent = titles[tabId].title;
                document.getElementById('currentTabSubtitle').textContent = titles[tabId].sub;
            }
        }
        
        
        function openGrievanceModal(id) {
            const g = grievancesData.find(item => item.id === id);
            if (!g) return;

            document.getElementById('modal_grievance_id').value = id;
            document.getElementById('modalGrievanceTitle').textContent = g.title;
            document.getElementById('modalGrievanceAuthorDate').textContent = 'Reported by ' + g.student_name + ' (' + g.student_id + ') on ' + g.date;
            document.getElementById('modalGrievanceStatusSelect').value = g.status;

            const chatContainer = document.getElementById('modalChatContainer');
            chatContainer.innerHTML = '';

            // Add original student grievance description as the first bubble
            const studentBubble = document.createElement('div');
            studentBubble.className = 'chat-bubble student-msg';
            studentBubble.innerHTML = `
                <div class="chat-header">
                    <span class="chat-author">${g.student_name}</span>
                </div>
                <div class="chat-message">${escapeHtml(g.desc)}</div>
            `;
            chatContainer.appendChild(studentBubble);

            // Add replies
            if (g.replies && g.replies.length > 0) {
                g.replies.forEach(reply => {
                    const replyBubble = document.createElement('div');
                    replyBubble.className = 'chat-bubble admin-reply';
                    replyBubble.innerHTML = `
                        <div class="chat-header">
                            <span class="chat-author">${reply.author} (${reply.role})</span>
                            <span class="chat-time" style="font-size:0.75rem;margin-left:0.5rem;color:var(--text-muted);">${reply.date}</span>
                        </div>
                        <div class="chat-message">${escapeHtml(reply.message)}</div>
                    `;
                    chatContainer.appendChild(replyBubble);
                });
            }

            document.getElementById('modal-grievance').classList.add('active');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Initialize first tab colors
        document.querySelector('.sidebar-nav-item.active').style.background = 'var(--primary-light)';
        document.querySelector('.sidebar-nav-item.active').style.color = 'var(--primary-color)';
    </script>
</body>
</html>
