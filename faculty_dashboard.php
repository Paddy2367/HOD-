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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_notifications') {
    $db['recent_activity'] = [];
    save_db($db);
    echo json_encode(['success' => true]);
    exit;
}

$success_message = '';
$error_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle Approve / Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'])) {
    $action = $_POST['action'];
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;

    $updated = false;
    foreach ($db['leaves'] as &$leave) {
        if ($leave['id'] === $leave_id) {
            if ($action === 'approve') {
                $leave['status'] = 'Approved';
                $_SESSION['success_message'] = 'Leave request #' . $leave_id . ' (Reason: ' . $leave['reason'] . ') has been Approved.';
                $updated = true;
            } elseif ($action === 'reject') {
                $leave['status'] = 'Rejected';
                $_SESSION['success_message'] = 'Leave request #' . $leave_id . ' (Reason: ' . $leave['reason'] . ') has been Rejected.';
                $updated = true;
            }
            break;
        }
    }

    if ($updated) {
        save_db($db);
    } else {
        $_SESSION['error_message'] = 'Failed to update leave request status. Request #' . $leave_id . ' not found.';
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_notice') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['desc']);
    $expiry = trim($_POST['expiry']);
    $file_name = '';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['attachment']['name']);
        if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
        move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
        $file_name = 'uploads/' . $file_name;
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
        $_SESSION['success_message'] = "Notice published successfully.";
    } else {
        $_SESSION['error_message'] = "Title and Description are required.";
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_assignment') {
    $sub_id = intval($_POST['assignment_id']);
    $marks = trim($_POST['marks']);
    
    $updated = false;
    if (isset($db['assignment_submissions'])) {
        foreach ($db['assignment_submissions'] as &$sub) {
            if ($sub['id'] === $sub_id) {
                $sub['status'] = 'graded';
                $sub['marks'] = $marks . ' / 10';
                $updated = true;
                break;
            }
        }
    }
    if ($updated) {
        save_db($db);
        $_SESSION['success_message'] = "Assignment graded successfully.";
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_assignment') {
    $title = trim($_POST['title'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $file_name = '';

    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['assignment_file']['name']);
        if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
        move_uploaded_file($_FILES['assignment_file']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
        $file_name = 'uploads/' . $file_name;
    }

    if (!empty($title)) {
        $formatted_due = $due_date ? date('d M Y 11:59 A\M', strtotime($due_date)) : 'No Due Date';
        $new_id = count($db['assignments']) + 1;
        
        $db['assignments'][] = [
            'id' => $new_id,
            'unit' => $new_id,
            'title' => $title,
            'desc' => 'Complete the assignment as per instructions.',
            'due' => $formatted_due,
            'status' => 'pending',
            'file' => $file_name,
            'marks' => 'Pending',
            'created_by' => $user['name']
        ];
        save_db($db);
        $_SESSION['success_message'] = "Assignment published successfully.";
    } else {
        $_SESSION['error_message'] = "Assignment Title is required.";
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_grievance') {
    $g_id = intval($_POST['grievance_id']);
    $updated = false;
    foreach ($db['grievances'] as &$g) {
        if ($g['id'] === $g_id) {
            $g['status'] = 'Resolved';
            $updated = true;
            break;
        }
    }
    if ($updated) {
        save_db($db);
        $_SESSION['success_message'] = "Grievance marked as resolved.";
    }
    header("Location: faculty_dashboard.php");
    exit;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <li><a class="sidebar-nav-item" onclick="switchTab('profile', this)"><i class="fa-solid fa-id-card"></i><span>My Profile</span></a></li>
                    <li><a class="sidebar-nav-item active" onclick="switchTab('dashboard', this)"><i class="fa-solid fa-border-all"></i><span>Dashboard</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('leaves', this)"><i class="fa-solid fa-envelope-open-text"></i><span>Leave Approvals</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('assignments', this)"><i class="fa-solid fa-file-invoice"></i><span>Manage Assignments</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('notices', this)"><i class="fa-solid fa-bullhorn"></i><span>Publish Notices</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('grievances', this)"><i class="fa-solid fa-circle-exclamation"></i><span>Grievance</span></a></li>
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
                    <h2 id="currentTabTitle">Dashboard</h2>
                    <p id="currentTabSubtitle">Quick access to all essential faculty services.</p>
                </div>
                <div class="user-profile-widget">
                    <div class="notification-wrapper" style="position: relative;">
                        <div class="notification-bell" id="notificationToggle" style="cursor:pointer;">
                            <i class="fa-regular fa-bell"></i>
                            <?php if (!empty($db['recent_activity'])): ?>
                            <span class="badge" style="position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; border-radius: 50%; width: 16px; height: 16px; font-size: 0.6rem; display: flex; align-items: center; justify-content: center; font-weight: bold;"><?php echo min(count($db['recent_activity']), 9); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-dropdown" id="notificationDropdown" style="display: none; position: absolute; top: 120%; right: 0; width: 320px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--border-color); z-index: 100; overflow: hidden; cursor: default;">
                            <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                                <h4 style="margin: 0; font-size: 1rem; color: #1e293b;">Notifications</h4>
                                <span style="font-size: 0.75rem; color: var(--primary-color); cursor: pointer; font-weight: 600;" onclick="fetch(window.location.href, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=clear_notifications'}).then(() => { this.parentElement.nextElementSibling.innerHTML='<div style=\'padding: 2rem 1rem; text-align: center; color: #64748b; font-size: 0.9rem;\'><i class=\'fa-regular fa-bell-slash\' style=\'font-size: 1.5rem; margin-bottom: 0.5rem; color: #cbd5e1;\'></i><br>No new notifications</div>'; let b = document.querySelector('#notificationToggle .badge'); if(b) b.style.display='none'; });">Mark all as read</span>
                            </div>
                            <div style="max-height: 350px; overflow-y: auto; text-align: left;">
                                <?php if (empty($db['recent_activity'])): ?>
                                    <div style="padding: 2rem 1rem; text-align: center; color: #64748b; font-size: 0.9rem;">
                                        <i class="fa-regular fa-bell-slash" style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #cbd5e1;"></i><br>
                                        No new notifications
                                    </div>
                                <?php else: ?>
                                    <?php foreach(array_slice($db['recent_activity'], 0, 5) as $idx => $activity): ?>
                                    <?php
                                    $targetTab = 'dashboard';
                                    $t = strtolower($activity['title'] ?? '');
                                    if (strpos($t, 'leave') !== false) $targetTab = 'leaves';
                                    elseif (strpos($t, 'grievance') !== false) $targetTab = 'grievance';
                                    elseif (strpos($t, 'assignment') !== false) $targetTab = 'assignments';
                                    elseif (strpos($t, 'notice') !== false) $targetTab = 'notices';
                                    ?>
                                    <div onclick="triggerTab('<?php echo $targetTab; ?>')" style="padding: 1rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.2s; <?php echo $idx === 0 ? 'background: #f0f9ff;' : ''; ?>" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='<?php echo $idx === 0 ? '#f0f9ff' : 'transparent'; ?>'">
                                        <div style="display: flex; gap: 0.75rem;">
                                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <i class="fa-solid fa-bolt"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 0.9rem; color: #334155; margin-bottom: 0.15rem;"><?php echo htmlspecialchars($activity['title'] ?? 'Notification'); ?></div>
                                                <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($activity['desc'] ?? ''); ?></div>
                                                <div style="font-size: 0.7rem; color: #94a3b8;"><i class="fa-regular fa-clock" style="margin-right: 3px;"></i> <?php echo htmlspecialchars($activity['time'] ?? 'Just now'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                        </div>
                        <script>
                            function triggerTab(tabName) {
                                if (!tabName) return;
                                if (tabName === 'grievance') {
                                    let hasGrievances = false;
                                    document.querySelectorAll('.sidebar-nav-item').forEach(el => {
                                        if ((el.getAttribute('onclick')||'').includes("'grievances'") || el.getAttribute('data-tab') === 'grievances') hasGrievances = true;
                                    });
                                    if (hasGrievances) tabName = 'grievances';
                                }
                                if (tabName === 'grievances') {
                                    let hasGrievance = false;
                                    document.querySelectorAll('.sidebar-nav-item').forEach(el => {
                                        if ((el.getAttribute('onclick')||'').includes("'grievance'") && !(el.getAttribute('onclick')||'').includes("'grievances'")) hasGrievance = true;
                                        if (el.getAttribute('data-tab') === 'grievance') hasGrievance = true;
                                    });
                                    if (hasGrievance) tabName = 'grievance';
                                }
                                
                                document.getElementById('notificationDropdown').style.display = 'none';
                                
                                let items = document.querySelectorAll('.sidebar-nav-item');
                                let targetEl = null;
                                for (let i=0; i<items.length; i++) {
                                    let onclick = items[i].getAttribute('onclick') || '';
                                    let dataTab = items[i].getAttribute('data-tab') || '';
                                    if (onclick.includes("'" + tabName + "'") || dataTab === tabName) {
                                        targetEl = items[i];
                                        break;
                                    }
                                }
                                
                                if (typeof switchTab === 'function') {
                                    if (targetEl && switchTab.length === 2) {
                                        switchTab(tabName, targetEl);
                                    } else {
                                        try { switchTab(tabName); } catch(e) {}
                                    }
                                }
                            }

                            document.getElementById('notificationToggle').addEventListener('click', function(e) {
                                e.stopPropagation();
                                var dropdown = document.getElementById('notificationDropdown');
                                dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
                            });
                            document.addEventListener('click', function(e) {
                                var dropdown = document.getElementById('notificationDropdown');
                                var toggle = document.getElementById('notificationToggle');
                                if (dropdown && !dropdown.contains(e.target) && !toggle.contains(e.target)) {
                                    dropdown.style.display = 'none';
                                }
                            });
                        </script>
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
                <div class="toast-notification toast-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="toast-notification toast-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- 0. DASHBOARD PAGE                            -->
            <!-- ============================================ -->
            <div id="tab-dashboard" class="app-view active">
                <h3 style="margin-bottom: 1.5rem; color: #1e293b;">Portal Summary</h3>
                <?php
                // Calculate summaries
                // Leaves
                $pending_leaves = 0;
                $total_leaves = count($db['leaves'] ?? []);
                foreach ($db['leaves'] ?? [] as $l) {
                    if (($l['status'] ?? '') === 'Pending') $pending_leaves++;
                }

                // Assignments
                $ungraded_submissions = 0;
                $total_submissions = count($db['assignment_submissions'] ?? []);
                foreach ($db['assignment_submissions'] ?? [] as $sub) {
                    if (($sub['status'] ?? '') === 'submitted' || strtolower($sub['marks'] ?? '') === 'pending') {
                        $ungraded_submissions++;
                    }
                }
                
                // Grievances
                $active_grievances = 0;
                foreach ($db['grievances'] ?? [] as $g) {
                    if (($g['status'] ?? '') !== 'Resolved') {
                        $active_grievances++;
                    }
                }
                
                // Notices
                $total_notices = count($db['notices'] ?? []);
                ?>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
                    
                    <!-- Leave Approvals Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('leaves', document.querySelectorAll('.sidebar-nav-item')[2])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-envelope-open-text"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Leaves Pending</h4>
                        <div style="color: #10b981; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $pending_leaves ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Out of <?= $total_leaves ?> total leaves</p>
                    </div>

                    <!-- Manage Assignments Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('assignments', document.querySelectorAll('.sidebar-nav-item')[3])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #f3e8ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-file-invoice"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Ungraded Work</h4>
                        <div style="color: #6366f1; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $ungraded_submissions ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Out of <?= $total_submissions ?> submissions</p>
                    </div>

                    <!-- Publish Notices Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('notices', document.querySelectorAll('.sidebar-nav-item')[4])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #dbeafe; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Notices</h4>
                        <div style="color: #3b82f6; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $total_notices ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Recent updates</p>
                    </div>

                    <!-- Grievance Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('grievances', document.querySelectorAll('.sidebar-nav-item')[5])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #ffedd5; color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Grievances</h4>
                        <div style="color: #f97316; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $active_grievances ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Requires resolution</p>
                    </div>

                </div>
            </div>

            <!-- ============================================ -->
            <!-- -1. PROFILE PAGE                             -->
            <!-- ============================================ -->
            <div id="tab-profile" class="app-view">
                <div class="settings-form-container" style="max-width: 800px; margin: 0 auto; background: white; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 2rem; box-shadow: var(--box-shadow-subtle);">
                    <div style="display: flex; gap: 2rem; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 2rem; margin-bottom: 2rem;">
                        <img src="<?= htmlspecialchars($user['avatar'] ?? 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=150&auto=format&fit=crop') ?>" alt="Faculty Avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-light);">
                        <div>
                            <h2 style="font-size: 1.75rem; font-weight: 800; color: #111827; margin: 0 0 0.5rem 0;"><?= htmlspecialchars($user['name']) ?></h2>
                            <span class="status-pill graded" style="font-size: 0.85rem; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d;">Active Faculty</span>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 0.95rem;">ID: <?= htmlspecialchars($user['username']) ?> | <?= htmlspecialchars($user['dept']) ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Full Name</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['name']) ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Employee ID</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['username']) ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Email Address</label>
                            <input type="text" readonly value="rajesh.sharma@erp.edu" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Phone Number</label>
                            <input type="text" readonly value="+91 98765 43210" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Department</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['dept']) ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Designation</label>
                            <input type="text" readonly value="Associate Professor" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 1. LEAVE APPROVALS TAB                       -->
            <!-- ============================================ -->
            <div id="tab-leaves" class="app-view">
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
                                            <a href="<?php echo htmlspecialchars($leave['file']); ?>" target="_blank" class="pub-name" style="font-size:0.9rem; font-weight:500; text-decoration:none; color: var(--primary-color);">
                                                <?php echo htmlspecialchars($leave['file']); ?>
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
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="font-size: 2.25rem; color: #4f46e5; font-weight: 800; margin-bottom: 0.5rem;">Assignment</h2>
                    <p style="color: var(--text-muted);">Upload your assignment files and track your submissions.</p>
                </div>

                <!-- Upload Assignment Form -->
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 3rem; box-shadow: var(--box-shadow-subtle);">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="publish_assignment">
                        <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 250px;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Assignment Title</label>
                                <input type="text" name="title" placeholder="e.g. Introduction to Basics" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit;">
                            </div>
                            <div style="flex: 1; min-width: 250px;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Due Date</label>
                                <input type="date" name="due_date" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit;">
                            </div>
                        </div>
                        
                        <div style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2rem; background: #f8fafc; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1.25rem;">
                                <div style="width: 56px; height: 56px; background: #e0e7ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <div style="text-align: left;">
                                    <h4 style="font-weight: 600; margin-bottom: 0.25rem; font-size: 1.05rem; color: #1e293b;">Upload Document / Image</h4>
                                    <p style="font-size: 0.9rem; color: var(--text-muted);">Click here to <label for="file-upload" style="color: #4f46e5; font-weight: 600; cursor: pointer;">browse</label> and select a file</p>
                                    <input id="file-upload" type="file" name="assignment_file" style="display: none;">
                                    <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.35rem;">Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</p>
                                </div>
                            </div>
                            <label for="file-upload" style="background: white; border: 1px solid var(--border-color); padding: 0.65rem 1.25rem; border-radius: 6px; color: #4f46e5; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i> Choose File
                            </label>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" style="background: #10b981; color: white; border: none; padding: 0.85rem 1.75rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 1rem; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); transition: transform 0.2s, box-shadow 0.2s;">Publish Assignment</button>
                        </div>
                    </form>
                </div>

                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 1.5rem; color: #1e293b;">Your Assignments</h3>

                <?php 
                foreach ($db['assignments'] as $index => $a): 
                    // Fetch submissions for this assignment
                    $submissions = [];
                    if (isset($db['assignment_submissions'])) {
                        foreach ($db['assignment_submissions'] as $sub) {
                            if ($sub['assignment_unit'] == $a['unit']) {
                                $submissions[] = $sub;
                            }
                        }
                    }
                    $has_submissions = (count($submissions) > 0);
                ?>
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 1.5rem; box-shadow: var(--box-shadow-subtle); overflow: hidden;">
                    <div style="padding: 1.5rem; display: flex; gap: 1.25rem; align-items: flex-start; border-bottom: <?php echo $has_submissions ? '1px solid var(--border-color)' : 'none'; ?>;">
                        <div style="width: 48px; height: 48px; background: #f5f3ff; color: #8b5cf6; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                        <div>
                            <h4 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.35rem; color: #1e293b;"><?= htmlspecialchars($a['title'] ?? 'Unit Assignment') ?></h4>
                            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.65rem;"><?= htmlspecialchars($a['desc'] ?? 'Complete the assignment as per instructions.') ?></p>
                            <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.85rem; color: #4f46e5; font-weight: 500;">
                                <span><i class="fa-regular fa-calendar"></i> Due: <?= htmlspecialchars($a['due']) ?></span>
                                <?php if (!empty($a['file'])): ?>
                                    <a href="<?= htmlspecialchars($a['file']) ?>" target="_blank" style="color: #0284c7; text-decoration: none;"><i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars($a['file']) ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($has_submissions): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 700px;">
                            <thead style="background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">
                                <tr>
                                    <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; border-bottom: 1px solid var(--border-color); width: 60px;">#</th>
                                    <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; border-bottom: 1px solid var(--border-color); width: 140px;">STUDENT ID</th>
                                    <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; border-bottom: 1px solid var(--border-color);">STUDENT NAME</th>
                                    <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; border-bottom: 1px solid var(--border-color);">SUBMITTED FILE</th>
                                    <th style="padding: 1rem 1.5rem; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color); width: 220px;">MARKS (OUT OF 10)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $i => $sub): ?>
                                <tr style="border-bottom: 1px solid var(--border-color); background: white;">
                                    <td style="padding: 1rem 1.5rem; font-size: 0.9rem; color: #334155;"><?php echo $i + 1; ?></td>
                                    <td style="padding: 1rem 1.5rem; font-size: 0.9rem; color: #334155;"><?php echo htmlspecialchars($sub['student_id']); ?></td>
                                    <td style="padding: 1rem 1.5rem; font-size: 0.9rem; color: #334155;"><?php echo htmlspecialchars($sub['student_name']); ?></td>
                                    <td style="padding: 1rem 1.5rem; font-size: 0.9rem;">
                                        <a href="<?php echo htmlspecialchars($sub['file']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; color: #0284c7; font-weight: 500; text-decoration: none;">
                                            <i class="fa-solid fa-paperclip" style="color: #0284c7; font-size: 1.1rem;"></i> View Document
                                        </a>
                                    </td>
                                    <td style="padding: 1rem 1.5rem; text-align: center;">
                                        <form method="POST" action="faculty_dashboard.php" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                            <input type="hidden" name="action" value="grade_assignment">
                                            <input type="hidden" name="assignment_id" value="<?php echo $sub['id']; ?>">
                                            <input type="number" name="marks" value="<?php echo floatval($sub['marks']); ?>" min="0" max="10" style="width: 50px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; font-family: inherit; font-size: 0.9rem;"> 
                                            <span style="color: #64748b; font-size: 0.9rem; white-space: nowrap;">/ 10</span>
                                            <button type="submit" style="padding: 0.3rem 0.6rem; border: none; border-radius: 4px; background-color: #3b82f6; color: white; cursor: pointer; font-size: 0.8rem;">Save</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div style="padding: 2.5rem; text-align: center; color: var(--text-muted); border-top: 1px solid var(--border-color);">
                        <div style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 0.75rem;">
                            <i class="fa-solid fa-inbox"></i>
                        </div>
                        <p style="font-size: 0.95rem;">No student submissions yet for this assignment.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ============================================ -->
            <!-- NOTICES TAB                                  -->
            <!-- ============================================ -->
            <div id="tab-notices" class="app-view">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="font-size: 2.25rem; color: #3b82f6; font-weight: 800; margin-bottom: 0.5rem;">Publish Notice</h2>
                    <p style="color: var(--text-muted);">Post announcements and broadcast updates to everyone.</p>
                </div>
                
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 3rem; box-shadow: var(--box-shadow-subtle);">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="publish_notice">
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Notice Title</label>
                            <input type="text" name="title" required placeholder="e.g. Extra Class Scheduled" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 1rem;">
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Description</label>
                            <textarea name="desc" rows="4" required placeholder="Enter notice details..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 1rem; resize: vertical;"></textarea>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Expiry Date (Optional)</label>
                            <input type="date" name="expiry" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 1rem;">
                        </div>
                        
                        <div style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2rem; background: #f8fafc; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1.25rem;">
                                <div style="width: 56px; height: 56px; background: #dbeafe; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-paperclip"></i>
                                </div>
                                <div style="text-align: left;">
                                    <h4 style="font-weight: 600; margin-bottom: 0.25rem; font-size: 1.05rem; color: #1e293b;">Attach File (Optional)</h4>
                                    <p style="font-size: 0.9rem; color: var(--text-muted);">Click here to <label for="notice-file-upload" style="color: #3b82f6; font-weight: 600; cursor: pointer;">browse</label> and select a file</p>
                                    <input id="notice-file-upload" type="file" name="attachment" style="display: none;">
                                    <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.35rem;">Supported formats: PDF, DOCX, JPG, PNG (Max 5MB)</p>
                                </div>
                            </div>
                            <label for="notice-file-upload" style="background: white; border: 1px solid var(--border-color); padding: 0.65rem 1.25rem; border-radius: 6px; color: #3b82f6; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i> Choose File
                            </label>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 0.85rem 1.75rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 1rem; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2); transition: transform 0.2s, box-shadow 0.2s;">Publish Notice</button>
                        </div>
                    </form>
                </div>
                
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-top: 3rem; margin-bottom: 1.5rem; color: #1e293b;">Published Notices</h3>
                
                <?php foreach ($db['notices'] as $n): ?>
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 1.5rem; box-shadow: var(--box-shadow-subtle); overflow: hidden;">
                    <div style="padding: 1.5rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; background: #fff1f2; color: #e11d48; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.35rem; color: #1e293b;"><?= htmlspecialchars($n['title']) ?></h4>
                            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.65rem;"><?= htmlspecialchars($n['desc']) ?></p>
                            <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.85rem; color: #475569; font-weight: 500;">
                                <span><i class="fa-regular fa-calendar" style="color: #64748b;"></i> Published: <?= htmlspecialchars($n['date']) ?></span>
                                <span><i class="fa-regular fa-clock" style="color: #64748b;"></i> Expiry: <?= htmlspecialchars($n['expiry'] ?: 'N/A') ?></span>
                                <?php if (!empty($n['attachment'])): ?>
                                    <a href="<?= htmlspecialchars($n['attachment']) ?>" target="_blank" style="color: #0284c7; text-decoration: none;"><i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars($n['attachment']) ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
            </div>

            <!-- ============================================ -->
            <!-- GRIEVANCES TAB                               -->
            <!-- ============================================ -->
            <div id="tab-grievances" class="app-view">
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: #1e293b;">Submitted Grievances</h3>
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; overflow-x: auto; box-shadow: var(--box-shadow-subtle);">
                    <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                        <thead style="background: #f8fafc; font-size: 0.85rem; color: #1e293b; font-weight: 600;">
                            <tr>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); width: 60px;">#</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Student Details</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Subject</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Date Submitted</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Render grievances from newest to oldest
                            $grievances = array_reverse($db['grievances']);
                            foreach ($grievances as $idx => $g): 
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1.25rem 1.5rem; font-size: 0.95rem; color: #334155;"><?= $idx + 1 ?></td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <?php 
                                            $parts = explode(" ", $g['student_name']);
                                            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                        ?>
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #f3e8ff; color: #6b21a8; font-weight: 600; font-size: 1rem; display: flex; align-items: center; justify-content: center;">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem;"><?= htmlspecialchars($g['student_name']) ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.15rem;">PRN: <?= htmlspecialchars($g['student_id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin-bottom: 0.25rem;"><?= htmlspecialchars($g['category']) ?></div>
                                    <div style="font-size: 0.85rem; color: #475569; margin-bottom: 0.35rem;"><?= htmlspecialchars($g['title']) ?></div>
                                    <a href="#" style="font-size: 0.8rem; color: #4f46e5; font-weight: 600; text-decoration: none;">View Details</a>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; font-size: 0.9rem; color: #334155;">
                                    <?= htmlspecialchars($g['date']) ?>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: center;">
                                    <?php if (isset($g['status']) && $g['status'] === 'Resolved'): ?>
                                        <span style="display: inline-block; padding: 0.35rem 1rem; background: #dcfce7; color: #166534; font-size: 0.85rem; font-weight: 600; border-radius: 6px;">Resolved</span>
                                    <?php else: ?>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="resolve_grievance">
                                            <input type="hidden" name="grievance_id" value="<?= $g['id'] ?>">
                                            <button type="submit" style="background: white; border: 1px solid #4f46e5; color: #4f46e5; padding: 0.4rem 0.85rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;">
                                                <i class="fa-solid fa-check" style="margin-right: 0.35rem;"></i> Mark as Resolved
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

            // Show selected panel
            if (tabName === 'leaves') {
                document.getElementById('tab-leaves').classList.add('active');
                headerTitle.textContent = "Leave Approvals";
                headerSubtitle.textContent = "Manage and respond to student leave requests.";
            } else if (tabName === 'assignments') {
                document.getElementById('tab-assignments').classList.add('active');
                headerTitle.textContent = "Manage Assignments";
                headerSubtitle.textContent = "Create assignments and grade student submissions.";
            } else if (tabName === 'notices') {
                document.getElementById('tab-notices').classList.add('active');
                headerTitle.textContent = "Publish Notices";
                headerSubtitle.textContent = "Create and broadcast important announcements to students.";
            } else if (tabName === 'grievances') {
                document.getElementById('tab-grievances').classList.add('active');
                headerTitle.textContent = "Grievance";
                headerSubtitle.textContent = "Review and address student issues and complaints.";
            } else if (tabName === 'dashboard') {
                document.getElementById('tab-dashboard').classList.add('active');
                headerTitle.textContent = "Dashboard";
                headerSubtitle.textContent = "Quick access to all essential faculty services.";
            } else if (tabName === 'profile') {
                document.getElementById('tab-profile').classList.add('active');
                headerTitle.textContent = "My Profile";
                headerSubtitle.textContent = "View and manage your professional credentials.";
            }
        }
    </script>
</body>
</html>
