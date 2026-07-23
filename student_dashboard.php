<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php?role=student");
    exit;
}

$user = $_SESSION['user'];
$student_id = $user['id'];
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

// Handle POST submissions (Leave applications or Assignment uploads)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
        $reason = trim($_POST['reason']);
        $from_date = trim($_POST['from_date']);
        $to_date = trim($_POST['to_date']);
        $file_name = 'Leave_Form_' . date('d_M_Y') . '.pdf'; // Default fallback

        // Handle uploaded file if present
        if (isset($_FILES['leave_file']) && $_FILES['leave_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = basename($_FILES['leave_file']['name']);
            if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
            move_uploaded_file($_FILES['leave_file']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
            $file_name = 'uploads/' . $file_name;
        } elseif (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
            $file_name = trim($_POST['file_name']);
        }

        if (!empty($reason) && !empty($from_date) && !empty($to_date)) {
            // Read, append, and save
            $db['leaves'][] = [
                'id' => count($db['leaves']) + 1,
                'applicant_name' => $user['name'],
                'applicant_role' => 'Student',
                'file' => $file_name,
                'reason' => $reason,
                'from' => $from_date,
                'to' => $to_date,
                'status' => 'Pending',
                'remarks' => ''
            ];
            $db['recent_activity'] = array_merge([
                [
                    'title' => 'New Leave Application',
                    'desc' => $user['name'] . ' applied for ' . $reason . ' Leave',
                    'time' => 'Just now'
                ]
            ], array_slice($db['recent_activity'], 0, 3));
            save_db($db);
            $_SESSION['success_message'] = 'Leave application submitted successfully! It has been routed to the Faculty Dashboard for approval.';
        } else {
            $_SESSION['error_message'] = 'Please fill out all leave application fields.';
        }
        header("Location: student_dashboard.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'upload_assignment') {
        $unit = intval($_POST['unit']);
        $file_name = 'Assignment_Unit_' . $unit . '.pdf';

        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = basename($_FILES['assignment_file']['name']);
            if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
            move_uploaded_file($_FILES['assignment_file']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
            $file_name = 'uploads/' . $file_name;
        } elseif (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
            $file_name = trim($_POST['file_name']);
        }

        // Check if submission already exists
        $found = false;
        if (!isset($db['assignment_submissions'])) { $db['assignment_submissions'] = []; }
        foreach ($db['assignment_submissions'] as &$sub) {
            if ($sub['assignment_unit'] == $unit && $sub['student_id'] === $student_id) {
                $sub['file'] = $file_name;
                $sub['status'] = 'submitted';
                $sub['marks'] = 'Pending';
                $found = true;
                break;
            }
        }

        if (!$found) {
            $db['assignment_submissions'][] = [
                'id' => count($db['assignment_submissions']) + 1,
                'assignment_unit' => $unit,
                'student_id' => $student_id,
                'student_name' => $user['name'],
                'file' => $file_name,
                'status' => 'submitted',
                'marks' => 'Pending'
            ];
        }
        
        save_db($db);
        $_SESSION['success_message'] = 'Assignment for Unit ' . $unit . ' submitted successfully!';
        header("Location: student_dashboard.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'submit_grievance') {
        $title = trim($_POST['title']);
        $category = trim($_POST['category']);
        $desc = trim($_POST['desc']);
        if (!empty($title) && !empty($category) && !empty($desc)) {
            $new_g = [
                'id' => count($db['grievances']) + 1,
                'student_id' => $user['username'],
                'student_name' => $user['name'],
                'title' => $title,
                'category' => $category,
                'desc' => $desc,
                'date' => date('d M Y h:i A'),
                'status' => 'Pending',
                'replies' => []
            ];
            $db['grievances'][] = $new_g;
            
            // Add a recent activity
            $db['recent_activity'] = array_merge([
                [
                    'title' => 'Grievance Raised',
                    'desc' => $user['name'] . ' reported "' . $title . '"',
                    'time' => 'Just now'
                ]
            ], array_slice($db['recent_activity'], 0, 3));
            
            save_db($db);
            $_SESSION['success_message'] = 'Grievance submitted successfully!';
        } else {
            $_SESSION['error_message'] = 'Please fill out all grievance fields.';
        }
        header("Location: student_dashboard.php");
        exit;
    }
}

// Fetch fresh updates
$notices = $db['notices'] ?? [];
$assignments = $db['assignments'] ?? [];
$leaves = [];
foreach ($db['leaves'] ?? [] as $leave) {
    if (isset($leave['applicant_name']) && $leave['applicant_name'] === $user['name']) {
        $leaves[] = $leave;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College ERP Portal - Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="theme-student">
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="sidebar-brand">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div>
                        <span>College ERP</span>
                        <span class="sub">Student Portal</span>
                    </div>
                </div>
                <ul class="sidebar-nav">
                    <li><a class="sidebar-nav-item" onclick="switchTab('profile', this)"><i class="fa-solid fa-id-card"></i><span>My Profile</span></a></li>
                    <li><a class="sidebar-nav-item active" onclick="switchTab('dashboard', this)"><i class="fa-solid fa-border-all"></i><span>Dashboard</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('attendance', this)"><i class="fa-solid fa-calendar-check"></i><span>Attendance</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('assignments', this)"><i class="fa-solid fa-file-invoice"></i><span>Assignments</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('leaves', this)"><i class="fa-solid fa-envelope-open-text"></i><span>Leave Requests</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('grievance', this)"><i class="fa-solid fa-circle-question"></i><span>Grievance</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('notices', this)"><i class="fa-solid fa-bullhorn"></i><span>Notices</span></a></li>
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
                    <p id="currentTabSubtitle">Quick access to all essential student services.</p>
                </div>
                <div class="user-profile-widget">
                    <button class="theme-toggle-btn" title="Toggle Dark/Light Theme" onclick="toggleDarkMode()">
                        <i class="fa-solid fa-moon"></i>
                    </button>
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
                        <?= get_initials_avatar($user['name'], 40, 16, 2) ?>
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
                $student_name = $user['name'] ?? 'Prasad Kulkarni';
                
                // Assignments
                $total_assignments = count($db['assignments'] ?? []);
                $submitted_assignments = 0;
                foreach ($db['assignment_submissions'] ?? [] as $sub) {
                    if (($sub['student_name'] ?? '') === $student_name) {
                        $submitted_assignments++;
                    }
                }
                $pending_assignments = max(0, $total_assignments - $submitted_assignments);
                
                // Leaves
                $my_leaves = 0;
                $pending_leaves = 0;
                foreach ($db['leaves'] ?? [] as $l) {
                    if (($l['applicant_name'] ?? '') === $student_name) {
                        $my_leaves++;
                        if (($l['status'] ?? '') === 'Pending') $pending_leaves++;
                    }
                }
                
                // Grievances
                $active_grievances = 0;
                foreach ($db['grievances'] ?? [] as $g) {
                    if (($g['student_name'] ?? '') === $student_name) {
                        if (($g['status'] ?? '') !== 'Resolved') {
                            $active_grievances++;
                        }
                    }
                }
                
                // Notices
                $total_notices = count($db['notices'] ?? []);
                ?>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.25rem;">
                    
                    <!-- Overall Attendance Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('attendance', document.querySelectorAll('.sidebar-nav-item')[2])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #dcfce7; color: #166534; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Overall Attendance</h4>
                        <div style="color: #166534; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;">87.5%</div>
                        <p style="color: #10b981; font-size: 0.8rem; font-weight: 700; margin-bottom: 0;">Safe Standing (>75%)</p>
                    </div>

                    <!-- Assignments Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('assignments', document.querySelectorAll('.sidebar-nav-item')[3])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #f3e8ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Pending Assignments</h4>
                        <div style="color: #6366f1; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $pending_assignments ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Out of <?= $total_assignments ?> total</p>
                    </div>

                    <!-- Leaves Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('leaves', document.querySelectorAll('.sidebar-nav-item')[4])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #dcfce7; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Leaves Pending</h4>
                        <div style="color: #10b981; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $pending_leaves ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Total Applied: <?= $my_leaves ?></p>
                    </div>

                    <!-- Grievance Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('grievance', document.querySelectorAll('.sidebar-nav-item')[5])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #ffedd5; color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-regular fa-comments"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Grievances</h4>
                        <div style="color: #f97316; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $active_grievances ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Requires resolution</p>
                    </div>

                    <!-- Notice Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('notices', document.querySelectorAll('.sidebar-nav-item')[6])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #dbeafe; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-regular fa-bell"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Notices</h4>
                        <div style="color: #3b82f6; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $total_notices ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Recent updates</p>
                    </div>

                </div>

                <!-- Dashboard Attendance Quick Overview Widget -->
                <div style="margin-top: 2rem; background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 2rem; box-shadow: 0 4px 12px -2px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.35rem; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                            <i class="fa-solid fa-calendar-days" style="color: #4f46e5; font-size: 1.4rem;"></i> Attendance Summary Overview
                        </h3>
                        <a href="#" onclick="switchTab('attendance', document.querySelectorAll('.sidebar-nav-item')[2]); return false;" style="color: #4f46e5; font-size: 0.95rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.35rem;">View Full Attendance Tracker <i class="fa-solid fa-arrow-right"></i></a>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                        <!-- Mini Subject 1 -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <span style="font-weight: 700; font-size: 1.05rem; color: #0f172a; line-height: 1.3;">Data Structures & Algorithms</span>
                                <span style="font-weight: 800; font-size: 1.25rem; color: #10b981;">90.0%</span>
                            </div>
                            <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; margin: 0.75rem 0 0.85rem 0;">
                                <div style="width: 90%; height: 100%; background: #10b981; border-radius: 5px;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #64748b; font-weight: 500;">
                                <span><i class="fa-solid fa-user-tie" style="margin-right: 4px;"></i>Prof. Rajesh Sharma</span>
                                <span style="font-weight: 700; color: #334155;">36 / 40 Attended</span>
                            </div>
                        </div>

                        <!-- Mini Subject 2 -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <span style="font-weight: 700; font-size: 1.05rem; color: #0f172a; line-height: 1.3;">Object Oriented Programming</span>
                                <span style="font-weight: 800; font-size: 1.25rem; color: #8b5cf6;">92.5%</span>
                            </div>
                            <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; margin: 0.75rem 0 0.85rem 0;">
                                <div style="width: 92.5%; height: 100%; background: #8b5cf6; border-radius: 5px;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #64748b; font-weight: 500;">
                                <span><i class="fa-solid fa-user-tie" style="margin-right: 4px;"></i>Prof. Neha Patil</span>
                                <span style="font-weight: 700; color: #334155;">37 / 40 Attended</span>
                            </div>
                        </div>

                        <!-- Mini Subject 3 -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <span style="font-weight: 700; font-size: 1.05rem; color: #0f172a; line-height: 1.3;">Operating Systems</span>
                                <span style="font-weight: 800; font-size: 1.25rem; color: #3b82f6;">80.0%</span>
                            </div>
                            <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; margin: 0.75rem 0 0.85rem 0;">
                                <div style="width: 80%; height: 100%; background: #3b82f6; border-radius: 5px;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #64748b; font-weight: 500;">
                                <span><i class="fa-solid fa-user-gear" style="margin-right: 4px;"></i>Prof. Amit Deshmukh (HOD)</span>
                                <span style="font-weight: 700; color: #334155;">32 / 40 Attended</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 1. NOTICES PAGE                              -->
            <!-- ============================================ -->
            <div id="tab-notices" class="app-view">
                <div class="notice-hero">
                    <div class="notice-hero-icon">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div class="notice-hero-text">
                        <h4>Important Notices</h4>
                        <p>Notices published by faculty and administration will appear here.</p>
                    </div>
                </div>

                <div class="data-table-container">
                    <div class="table-header-filters">
                        <select class="select-filter" id="noticeRoleFilter" onchange="filterNotices()">
                            <option value="all">All Notices</option>
                            <option value="faculty">Faculty Only</option>
                            <option value="admin">Administration Only</option>
                        </select>
                        <select class="select-filter" id="noticeSortFilter" onchange="filterNotices()">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                        </select>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Title</th>
                                <th>Published By</th>
                                <th>Date & Time</th>
                                <th>Attachment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notices as $notice): ?>
                                <tr>
                                    <td><?php echo $notice['id']; ?></td>
                                    <td>
                                        <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                                        <div class="notice-desc"><?php echo htmlspecialchars($notice['desc']); ?></div>
                                    </td>
                                    <td>
                                        <div class="publisher-cell">
                                            <span class="pub-name"><?php echo htmlspecialchars($notice['author']); ?></span>
                                            <span class="pub-role"><?php echo htmlspecialchars($notice['role']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell"><?php echo htmlspecialchars($notice['date']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($notice['attachment'])): ?>
                                            <?php 
                                                $ext = pathinfo($notice['attachment'], PATHINFO_EXTENSION); 
                                                $badge_class = ($ext === 'pdf') ? 'pdf' : 'docx';
                                            ?>
                                            <a href="<?php echo htmlspecialchars($notice['attachment']); ?>" target="_blank" class="attachment-badge <?php echo $badge_class; ?>">
                                                <i class="fa-regular <?php echo ($badge_class==='pdf')?'fa-file-pdf':'fa-file-word'; ?>"></i>
                                                <span><?php echo htmlspecialchars($notice['attachment']); ?> (<?php echo $notice['size']; ?>)</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($notice['attachment']); ?>" target="_blank" class="btn-icon-download" style="margin-left: 0.5rem; text-decoration: none;">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 2. ASSIGNMENTS PAGE                          -->
            <!-- ============================================ -->
            <div id="tab-assignments" class="app-view">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Assignment</th>
                                <th>Due Date</th>
                                <th style="text-align: center;">Upload</th>
                                <th style="text-align: center;">Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assign): 
                                // Find student's submission
                                $my_sub = null;
                                if (isset($db['assignment_submissions'])) {
                                    foreach ($db['assignment_submissions'] as $sub) {
                                        if ($sub['assignment_unit'] == $assign['unit'] && $sub['student_id'] === $student_id) {
                                            $my_sub = $sub;
                                            break;
                                        }
                                    }
                                }
                                $status = $my_sub ? $my_sub['status'] : 'pending';
                                $marks = $my_sub ? $my_sub['marks'] : 'Pending';
                                $file = $my_sub ? $my_sub['file'] : '';
                            ?>
                                <tr>
                                    <td style="font-weight: 500; padding: 1.5rem; text-align: center; color: #4b5563;"><?php echo $assign['unit']; ?></td>
                                    <td style="padding: 1.5rem;">
                                        <div style="display: flex; gap: 1rem; align-items: center;">
                                            <div style="width: 44px; height: 44px; background: #f3e8ff; color: #8b5cf6; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                                                <i class="fa-solid fa-file-lines"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 0.25rem;">Unit <?php echo $assign['unit']; ?> - <?php echo htmlspecialchars($assign['title']); ?></div>
                                                <div style="color: #64748b; font-size: 0.85rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($assign['desc']); ?></div>
                                                <?php if (!empty($assign['file'])): ?>
                                                    <a href="<?php echo htmlspecialchars($assign['file']); ?>" target="_blank" style="font-size: 0.8rem; color: #4f46e5; text-decoration: none; display: inline-block;"><i class="fa-solid fa-paperclip"></i> Question Document</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 1.5rem;">
                                        <?php 
                                            // Split due date into date and time
                                            $parts = explode(' ', htmlspecialchars($assign['due']));
                                            $timeStr = (count($parts) >= 5) ? $parts[3] . ' ' . $parts[4] : '11:59 PM';
                                            $dateStr = (count($parts) >= 3) ? $parts[0] . ' ' . $parts[1] . ' ' . $parts[2] : htmlspecialchars($assign['due']);
                                        ?>
                                        <div style="display: flex; flex-direction: column; gap: 0.25rem; color: #334155;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                                                <i class="fa-regular fa-calendar" style="color: #6366f1;"></i> <?php echo $dateStr; ?>
                                            </div>
                                            <div style="font-size: 0.85rem; color: #64748b; margin-left: 1.5rem;">
                                                <?php echo $timeStr; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: center; padding: 1.5rem;">
                                        <?php if ($status === 'graded' || $status === 'submitted'): ?>
                                            <div style="display: inline-flex; align-items: center; gap: 0.5rem; color: #10b981; font-weight: 600;">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <a href="<?php echo htmlspecialchars($file); ?>" target="_blank" style="color: #10b981; text-decoration: none;" title="<?php echo htmlspecialchars($file); ?>">Submitted</a>
                                            </div>
                                        <?php else: ?>
                                            <button onclick="openUploadModal(<?php echo $assign['unit']; ?>, '<?php echo htmlspecialchars($assign['title']); ?>')" style="background: white; border: 1.5px solid #d8b4fe; color: #8b5cf6; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                                Upload
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; padding: 1.5rem;">
                                        <?php if ($status === 'graded'): ?>
                                            <span style="background: #dcfce7; color: #15803d; padding: 0.4rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-block; min-width: 60px;"><?php echo htmlspecialchars($marks); ?></span>
                                        <?php else: ?>
                                            <span style="background: #ffedd5; color: #f97316; padding: 0.4rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-block; min-width: 60px;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 3. LEAVE REQUESTS PAGE                       -->
            <!-- ============================================ -->
            <div id="tab-leaves" class="app-view">
                <div class="leave-grid">
                    <!-- Submit Request card -->
                    <div class="leave-form-container">
                        <div class="leave-form-header">
                            <h3>Apply for Leave</h3>
                            <p>Upload your filled leave form and submit your dates to request leaves.</p>
                        </div>
                        <form id="leaveApplicationForm" method="POST" action="student_dashboard.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="apply_leave">
                            
                            <!-- File Drop Zone -->
                            <div class="drag-drop-zone" id="leaveDropZone" onclick="document.getElementById('leaveFileInput').click()">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p>Click to choose file or drag & drop here</p>
                                <span>Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</span>
                                <input type="file" id="leaveFileInput" name="leave_file" style="display:none;" onchange="handleFileSelect(event)">
                            </div>
                            
                            <!-- Display selected file info -->
                            <div class="selected-file-display" id="fileDisplayArea">
                                <div class="file-info">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span id="displayFileName">FileName.pdf</span>
                                </div>
                                <button type="button" class="btn-remove-file" onclick="removeSelectedFile()"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                            <!-- Fallback hidden input to pass file name if uploaded directly -->
                            <input type="hidden" id="fallbackFileName" name="file_name" value="">

                            <!-- Reason and Dates -->
                            <div class="leave-form-row">
                                <div class="form-group">
                                    <label for="leaveReason"><i class="fa-solid fa-circle-info"></i> Reason</label>
                                    <div class="input-wrapper">
                                        <select class="select-filter" id="leaveReason" name="reason" style="width: 100%; height: 45px;" required>
                                            <option value="">Select leave reason</option>
                                            <option value="Medical">Medical / Sick Leave</option>
                                            <option value="Personal">Personal Reasons</option>
                                            <option value="Family Function">Family Function</option>
                                            <option value="Exam Preparation">Exam Preparation</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="leaveFromDate"><i class="fa-regular fa-calendar-days"></i> From Date</label>
                                    <div class="input-wrapper">
                                        <input type="date" id="leaveFromDate" name="from_date" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="leaveToDate"><i class="fa-regular fa-calendar-days"></i> To Date</label>
                                    <div class="input-wrapper">
                                        <input type="date" id="leaveToDate" name="to_date" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit-leave">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>Submit Leave Application</span>
                            </button>
                        </form>
                    </div>

                    <!-- Leave list table -->
                    <div class="data-table-container">
                        <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">Your Leave Requests</h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Leave Form</th>
                                    <th>Reason</th>
                                    <th>From Date</th>
                                    <th>To Date</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaves as $leave): ?>
                                    <tr>
                                        <td><?php echo $leave['id']; ?></td>
                                        <td>
                                            <div class="publisher-cell" style="flex-direction:row; align-items:center; gap:0.5rem;">
                                                <?php 
                                                    $ext = pathinfo($leave['file'], PATHINFO_EXTENSION);
                                                    $is_pdf = (strtolower($ext) === 'pdf');
                                                ?>
                                                <i class="fa-solid <?php echo $is_pdf?'fa-file-pdf':'fa-file-word'; ?>" style="font-size:1.15rem; color:<?php echo $is_pdf?'#ef4444':'#0284c7'; ?>"></i>
                                                <a href="<?php echo htmlspecialchars($leave['file']); ?>" target="_blank" class="pub-name" style="font-size:0.9rem; font-weight:500; text-decoration:none; color: var(--primary-color);"><?php echo htmlspecialchars($leave['file']); ?></a>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($leave['reason']); ?></span>
                                        </td>
                                        <td>
                                            <span class="date-cell"><?php echo htmlspecialchars($leave['from']); ?></span>
                                        </td>
                                        <td>
                                            <span class="date-cell"><?php echo htmlspecialchars($leave['to']); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $status = strtolower($leave['status']);
                                                $pill_class = ($status === 'approved') ? 'graded' : (($status === 'pending') ? 'pending' : 'rejected');
                                            ?>
                                            <span class="status-pill <?php echo $pill_class; ?>"><?php echo htmlspecialchars($leave['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 4. GRIEVANCES PAGE                           -->
            <!-- ============================================ -->
            <div id="tab-grievance" class="app-view">
                <div class="leave-grid">
                    <!-- Submit Request card -->
                    <div class="leave-form-container">
                        <div class="leave-form-header">
                            <h3>Submit a Grievance</h3>
                            <p>Report issues to administration or department heads.</p>
                        </div>
                        <form method="POST" action="student_dashboard.php">
                            <input type="hidden" name="action" value="submit_grievance">
                            <div class="leave-form-row">
                                <div class="form-group">
                                    <label><i class="fa-solid fa-heading"></i> Subject Title</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="title" required placeholder="Brief description of the issue">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fa-solid fa-tag"></i> Category</label>
                                    <div class="input-wrapper">
                                        <select class="select-filter" name="category" style="width: 100%; height: 45px;" required>
                                            <option value="">Select Category</option>
                                            <option value="Infrastructure">Infrastructure & Facilities</option>
                                            <option value="Academics">Academics & Grading</option>
                                            <option value="Administration">Administrative Issues</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label><i class="fa-solid fa-align-left"></i> Description</label>
                                <textarea name="desc" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-family: var(--font-primary);" required placeholder="Explain your grievance in detail..."></textarea>
                            </div>
                            <button type="submit" class="btn-submit-leave">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>Submit Grievance</span>
                            </button>
                        </form>
                    </div>

                    <!-- Grievances list -->
                    <div class="data-table-container">
                        <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">My Grievances</h3>
                        </div>
                        <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                            <?php 
                            $my_grievances = array_filter($db['grievances'], function($g) use ($user) {
                                return $g['student_id'] === $user['username'];
                            });
                            
                            if (empty($my_grievances)): ?>
                                <p style="color:var(--text-muted); text-align:center;">You have not submitted any grievances yet.</p>
                            <?php else: foreach ($my_grievances as $g): ?>
                                <div style="border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 1.25rem; background: #fafafa;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom: 1rem;">
                                        <div>
                                            <h4 style="font-size:1.1rem; font-weight:700; color:#111827; margin-bottom:0.25rem;"><?= htmlspecialchars($g['title']) ?></h4>
                                            <span class="notice-desc"><?= htmlspecialchars($g['category']) ?> • <?= htmlspecialchars($g['date']) ?></span>
                                        </div>
                                        <div>
                                            <span class="status-pill <?= strtolower(str_replace(' ', '-', $g['status'])) ?>"><?= htmlspecialchars($g['status']) ?></span>
                                        </div>
                                    </div>
                                    <p style="color:#374151; font-size:0.95rem; margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid var(--border-color);"><?= nl2br(htmlspecialchars($g['desc'])) ?></p>
                                    
                                    <?php if (!empty($g['replies'])): ?>
                                        <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                            <h5 style="font-size:0.9rem; font-weight:600; color:#4b5563;">Responses:</h5>
                                            <?php foreach ($g['replies'] as $reply): ?>
                                                <div style="background: white; border-radius:var(--border-radius-sm); padding:1rem; border:1px solid var(--border-color);">
                                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                                                        <span style="font-weight:600; font-size:0.85rem; color:var(--primary-color);"><?= htmlspecialchars($reply['author']) ?> (<?= htmlspecialchars($reply['role']) ?>)</span>
                                                        <span style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($reply['date']) ?></span>
                                                    </div>
                                                    <div style="font-size:0.9rem; color:#374151;"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="font-size:0.85rem; color:var(--text-muted); font-style:italic;">No responses yet.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- ATTENDANCE TRACKER PAGE                     -->
            <!-- ============================================ -->
            <div id="tab-attendance" class="app-view">
                <!-- Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #dcfce7; color: #166534; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Overall Attendance</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.15rem 0;">87.5%</div>
                            <span style="display: inline-block; background: #dcfce7; color: #15803d; padding: 0.15rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">Safe (>75%)</span>
                        </div>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #dbeafe; color: #1e40af; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Total Conducted</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.15rem 0;">120</div>
                            <span style="color: #64748b; font-size: 0.75rem;">Lectures held</span>
                        </div>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Lectures Attended</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #16a34a; margin: 0.15rem 0;">105</div>
                            <span style="color: #64748b; font-size: 0.75rem;">Present in class</span>
                        </div>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Missed Lectures</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #dc2626; margin: 0.15rem 0;">15</div>
                            <span style="color: #64748b; font-size: 0.75rem;">Includes 4 approved leaves</span>
                        </div>
                    </div>
                </div>

                <!-- Subject-wise Breakdown Section -->
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-book-bookmark" style="color: #4f46e5;"></i> Subject-wise Attendance Breakdown
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
                        <!-- Subject 1 -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">Data Structures & Algorithms</h4>
                                    <span style="font-size: 0.75rem; color: #64748b;"><i class="fa-solid fa-user-tie" style="margin-right: 4px;"></i>Prof. Rajesh Sharma</span>
                                </div>
                                <span style="font-size: 1.1rem; font-weight: 800; color: #10b981;">90.0%</span>
                            </div>
                            <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 0.75rem 0;">
                                <div style="width: 90%; height: 100%; background: #10b981; border-radius: 4px;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b;">
                                <span>Attended: 36 / 40</span>
                                <span>Missed: 4</span>
                            </div>
                        </div>

                        <!-- Subject 2 -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">Object Oriented Programming</h4>
                                    <span style="font-size: 0.75rem; color: #64748b;"><i class="fa-solid fa-user-tie" style="margin-right: 4px;"></i>Prof. Neha Patil</span>
                                </div>
                                <span style="font-size: 1.1rem; font-weight: 800; color: #8b5cf6;">92.5%</span>
                            </div>
                            <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 0.75rem 0;">
                                <div style="width: 92.5%; height: 100%; background: #8b5cf6; border-radius: 4px;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b;">
                                <span>Attended: 37 / 40</span>
                                <span>Missed: 3</span>
                            </div>
                        </div>

                        <!-- Subject 3 (HOD) -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">Operating Systems</h4>
                                    <span style="font-size: 0.75rem; color: #64748b;"><i class="fa-solid fa-user-gear" style="margin-right: 4px;"></i>Prof. Amit Deshmukh (HOD)</span>
                                </div>
                                <span style="font-size: 1.1rem; font-weight: 800; color: #3b82f6;">80.0%</span>
                            </div>
                            <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 0.75rem 0;">
                                <div style="width: 80%; height: 100%; background: #3b82f6; border-radius: 4px;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b;">
                                <span>Attended: 32 / 40</span>
                                <span>Missed: 8</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lecture Logs (When He Attended Lectures) -->
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-clock-rotate-left" style="color: #4f46e5;"></i> Lecture Attendance History Logs
                        </h3>
                        <div style="display: flex; gap: 0.75rem;">
                            <select class="select-filter" id="attendanceSubjectFilter" onchange="filterAttendanceLogs()" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; color: #334155;">
                                <option value="all">All Subjects</option>
                                <option value="Data Structures & Algorithms">Data Structures & Algorithms</option>
                                <option value="Object Oriented Programming">Object Oriented Programming</option>
                                <option value="Operating Systems">Operating Systems</option>
                            </select>
                            <select class="select-filter" id="attendanceStatusFilter" onchange="filterAttendanceLogs()" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; color: #334155;">
                                <option value="all">All Statuses</option>
                                <option value="Present">Present Only</option>
                                <option value="Absent">Absent Only</option>
                                <option value="On Leave">On Leave Only</option>
                            </select>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="data-table" id="attendanceLogsTable">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Subject</th>
                                    <th>Lecture Topic / Unit</th>
                                    <th>Faculty Instructor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr data-subject="Data Structures & Algorithms" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">23 Jul 2026, 10:00 AM</td>
                                    <td>Data Structures & Algorithms</td>
                                    <td>Trees & Binary Search Trees (Unit 4)</td>
                                    <td>Prof. Rajesh Sharma</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Operating Systems" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">23 Jul 2026, 09:00 AM</td>
                                    <td>Operating Systems</td>
                                    <td>Process Synchronization & Semaphores</td>
                                    <td>Prof. Amit Deshmukh (HOD)</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Object Oriented Programming" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">22 Jul 2026, 11:30 AM</td>
                                    <td>Object Oriented Programming</td>
                                    <td>Inheritance, Abstraction & Polymorphism</td>
                                    <td>Prof. Neha Patil</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Operating Systems" data-status="Absent">
                                    <td style="font-weight: 600; color: #1e293b;">21 Jul 2026, 01:00 PM</td>
                                    <td>Operating Systems</td>
                                    <td>CPU Scheduling: FCFS & Round Robin</td>
                                    <td>Prof. Amit Deshmukh (HOD)</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #fee2e2; color: #b91c1c; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-xmark" style="margin-right: 4px;"></i>Absent</span></td>
                                </tr>
                                <tr data-subject="Data Structures & Algorithms" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">20 Jul 2026, 03:00 PM</td>
                                    <td>Data Structures & Algorithms</td>
                                    <td>Stack & Queue Applications in C++</td>
                                    <td>Prof. Rajesh Sharma</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Object Oriented Programming" data-status="On Leave">
                                    <td style="font-weight: 600; color: #1e293b;">20 Jul 2026, 11:30 AM</td>
                                    <td>Object Oriented Programming</td>
                                    <td>Virtual Functions & Dynamic Binding</td>
                                    <td>Prof. Neha Patil</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #fef3c7; color: #b45309; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-file-signature" style="margin-right: 4px;"></i>On Leave</span></td>
                                </tr>
                                <tr data-subject="Object Oriented Programming" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">18 Jul 2026, 02:00 PM</td>
                                    <td>Object Oriented Programming</td>
                                    <td>Classes, Objects & Constructors</td>
                                    <td>Prof. Neha Patil</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Operating Systems" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">17 Jul 2026, 09:00 AM</td>
                                    <td>Operating Systems</td>
                                    <td>Introduction to OS Kernels & System Calls</td>
                                    <td>Prof. Amit Deshmukh (HOD)</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Data Structures & Algorithms" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">16 Jul 2026, 11:30 AM</td>
                                    <td>Data Structures & Algorithms</td>
                                    <td>Array & Linked List Operations</td>
                                    <td>Prof. Rajesh Sharma</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 5. STUDENT PROFILE PAGE                      -->
            <!-- ============================================ -->
            <div id="tab-profile" class="app-view">
                <div class="settings-form-container" style="max-width: 800px; margin: 0 auto; background: white; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 2rem; box-shadow: var(--box-shadow-subtle);">
                    <div style="display: flex; gap: 2rem; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 2rem; margin-bottom: 2rem;">
                        <?= get_initials_avatar($user['name'], 120, 48, 4) ?>
                        <div>
                            <h2 style="font-size: 1.75rem; font-weight: 800; color: #111827; margin: 0 0 0.5rem 0;"><?= htmlspecialchars($user['name']) ?></h2>
                            <span class="status-pill graded" style="font-size: 0.85rem; padding: 0.25rem 0.75rem;">Active Student</span>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 0.95rem;">PRN: <span style="font-weight: 700; color: #4f46e5;"><?= htmlspecialchars($user['prn'] ?? 'IT0001') ?></span> | ID: <?= htmlspecialchars($user['username']) ?> | <?= htmlspecialchars($user['dept']) ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group-col">
                            <label>Full Name</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['name']) ?>" style="background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label>PRN (Permanent Registration Number)</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['prn'] ?? 'IT0001') ?>" style="background: #f9fafb; cursor: not-allowed; font-weight: 700; color: #4f46e5; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>
                    
                    <div class="form-row" style="margin-top: 1rem;">
                        <div class="form-group-col">
                            <label>Email Address</label>
                            <input type="text" readonly value="prasad.kulkarni@erp.edu" style="background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label>Phone Number</label>
                            <input type="text" readonly value="+91 99223 34455" style="background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>

                    <div class="form-row" style="margin-top: 1rem;">
                        <div class="form-group-col">
                            <label>Department</label>
                            <input type="text" readonly value="Information Technology" style="background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label>Current Semester & Division</label>
                            <input type="text" readonly value="5th Semester - Div A (A2)" style="background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; padding: 1.5rem; background: var(--primary-light); border-radius: var(--border-radius-md); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h4 style="margin: 0 0 0.25rem 0; color: var(--primary-color); font-weight: 700; font-size: 1.1rem;">Academic Attendance Tracker</h4>
                            <p style="margin: 0; color: #4b5563; font-size: 0.9rem;">Maintain above 75% attendance to avoid defaulter lists.</p>
                        </div>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary-color);">85%</div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- MOCK TABS PANEL                              -->
            <!-- ============================================ -->
            <div id="tab-mock" class="app-view">
                <div class="mock-page-container">
                    <div class="mock-page-icon" id="mockPageIcon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h3 id="mockPageTitle">Dashboard Summary</h3>
                    <p id="mockPageDesc">This panel displays real-time statistics and summaries related to student profile metrics. Feel free to navigate back to the Notices, Assignments, or Leave Requests panels for live mock interactive elements.</p>
                </div>
            </div>

        </main>
    </div>

    <!-- ============================================ -->
    <!-- ASSIGNMENT UPLOAD MODAL                      -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="uploadModalTitle">Upload Assignment</h3>
                <button class="btn-close-modal" onclick="closeUploadModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="assignmentUploadForm" method="POST" action="student_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_assignment">
                <input type="hidden" id="uploadUnitInput" name="unit" value="0">
                <div class="modal-body">
                    <div class="form-group">
                        <p style="font-size: 0.925rem; color: var(--text-muted); margin-bottom: 1.25rem;" id="uploadModalDesc">Complete all questions given in the unit assignment.</p>
                    </div>
                    
                    <div class="drag-drop-zone" onclick="document.getElementById('modalFileInput').click()" style="padding: 2rem 1.25rem; margin-bottom: 1.25rem;">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2.25rem; margin-bottom: 0.75rem;"></i>
                        <p style="font-size: 0.9rem;">Select assignment file</p>
                        <span style="font-size: 0.75rem;">Supported: PDF, DOC, DOCX (Max 10MB)</span>
                        <input type="file" id="modalFileInput" name="assignment_file" style="display:none;" onchange="handleModalFileSelect(event)">
                    </div>

                    <div class="selected-file-display" id="modalFileDisplay" style="margin-bottom: 0;">
                        <div class="file-info">
                            <i class="fa-solid fa-file-pdf"></i>
                            <span id="modalFileNameText">No file selected</span>
                        </div>
                        <button type="button" class="btn-remove-file" onclick="removeModalSelectedFile()"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                    <input type="hidden" id="modalFallbackFileName" name="file_name" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn-login" style="width: auto; padding: 0.65rem 1.5rem; font-size: 0.9rem;">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Submit Work</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript code for navigation, modal interaction and drag-drop selection -->
    <script>
        // Switch between dashboard tabs
        function switchTab(tabName, element) {
            // Update active states in navigation
            const items = document.querySelectorAll('.sidebar-nav-item');
            items.forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            // Hide all panels
            const panels = document.querySelectorAll('.app-view');
            panels.forEach(p => p.classList.remove('active'));

            const headerTitle = document.getElementById('currentTabTitle');
            const headerSubtitle = document.getElementById('currentTabSubtitle');

            // Show selected panel or show mock panel with custom descriptors
            if (tabName === 'notices') {
                document.getElementById('tab-notices').classList.add('active');
                headerTitle.textContent = "Notices";
                headerSubtitle.textContent = "Stay updated with the latest announcements and important information.";
            } else if (tabName === 'assignments') {
                document.getElementById('tab-assignments').classList.add('active');
                headerTitle.textContent = "Assignments";
                headerSubtitle.textContent = "View your unit assignments and upload your finished answers.";
            } else if (tabName === 'leaves') {
                document.getElementById('tab-leaves').classList.add('active');
                headerTitle.textContent = "Leave Requests";
                headerSubtitle.textContent = "Apply for college leave by submitting your verified leave form.";
            } else if (tabName === 'grievance') {
                document.getElementById('tab-grievance').classList.add('active');
                headerTitle.textContent = "Grievance";
                headerSubtitle.textContent = "Submit issues or report institutional suggestions.";
            } else if (tabName === 'dashboard') {
                document.getElementById('tab-dashboard').classList.add('active');
                headerTitle.textContent = "Dashboard";
                headerSubtitle.textContent = "Quick access to all essential student portals and services.";
            } else if (tabName === 'attendance') {
                document.getElementById('tab-attendance').classList.add('active');
                headerTitle.textContent = "Attendance Tracker";
                headerSubtitle.textContent = "Monitor overall attendance, subject-wise statistics, and lecture history.";
            } else if (tabName === 'profile') {
                document.getElementById('tab-profile').classList.add('active');
                headerTitle.textContent = "My Profile";
                headerSubtitle.textContent = "View and manage your academic profile credentials.";
            } else {
                // Show mock templates
                const mockPanel = document.getElementById('tab-mock');
                mockPanel.classList.add('active');

                const titleText = document.getElementById('mockPageTitle');
                const descText = document.getElementById('mockPageDesc');
                const iconBox = document.getElementById('mockPageIcon');

                headerTitle.textContent = tabName.charAt(0).toUpperCase() + tabName.slice(1);
                headerSubtitle.textContent = `Access student ${tabName} records and configuration setups.`;

                // Update mock details
                titleText.textContent = tabName.toUpperCase();
                
                if (tabName === 'profile') {
                    iconBox.innerHTML = '<i class="fa-solid fa-id-card"></i>';
                    descText.textContent = "Prasad Kulkarni | Student ID: 125UIT1080 | Department of Information Technology (IT-A2). Academic profile status, emergency contact info, and registration logs are managed inside this panel.";
                }
            }
        }

        // Leave Requests file input rendering
        function handleFileSelect(event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('displayFileName').textContent = file.name;
                document.getElementById('fallbackFileName').value = file.name; // Keep name string
                document.getElementById('leaveDropZone').style.display = 'none';
                document.getElementById('fileDisplayArea').style.display = 'flex';
            }
        }

        function removeSelectedFile() {
            document.getElementById('leaveFileInput').value = '';
            document.getElementById('fallbackFileName').value = '';
            document.getElementById('leaveDropZone').style.display = 'block';
            document.getElementById('fileDisplayArea').style.display = 'none';
        }

        // Drag & Drop event bindings for Leave Requests
        const dropZone = document.getElementById('leaveDropZone');
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.add('dragover');
                }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('dragover');
                }, false);
            });
            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    document.getElementById('leaveFileInput').files = files;
                    document.getElementById('displayFileName').textContent = files[0].name;
                    document.getElementById('fallbackFileName').value = files[0].name;
                    dropZone.style.display = 'none';
                    document.getElementById('fileDisplayArea').style.display = 'flex';
                }
            });
        }

        // Assignment Upload Modal flow
        function openUploadModal(unit, title) {
            document.getElementById('uploadUnitInput').value = unit;
            document.getElementById('uploadModalTitle').textContent = `Upload Assignment — Unit ${unit}`;
            document.getElementById('uploadModalDesc').textContent = `Complete all questions and upload your solution file for the unit topic: "${title}".`;
            document.getElementById('uploadModal').classList.add('active');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
            removeModalSelectedFile();
        }

        function handleModalFileSelect(event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('modalFileNameText').textContent = file.name;
                document.getElementById('modalFallbackFileName').value = file.name;
                document.getElementById('modalFileDisplay').style.display = 'flex';
            }
        }

        function removeModalSelectedFile() {
            document.getElementById('modalFileInput').value = '';
            document.getElementById('modalFallbackFileName').value = '';
            document.getElementById('modalFileNameText').textContent = 'No file selected';
            document.getElementById('modalFileDisplay').style.display = 'none';
        }

        // Filtering logic for notices
        function filterNotices() {
            const roleFilter = document.getElementById('noticeRoleFilter').value;
            const sortFilter = document.getElementById('noticeSortFilter').value;
            const tbody = document.querySelector('#tab-notices tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.forEach(row => {
                const roleCell = row.querySelector('.pub-role').textContent.toLowerCase();
                if (roleFilter === 'all') {
                    row.style.display = '';
                } else if (roleFilter === 'faculty' && roleCell.includes('faculty')) {
                    row.style.display = '';
                } else if (roleFilter === 'admin' && roleCell.includes('admin')) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Sorting by ID (simulating date since data is mock/static in structure)
            const sortedRows = rows.sort((a, b) => {
                const idA = parseInt(a.cells[0].textContent);
                const idB = parseInt(b.cells[0].textContent);
                return sortFilter === 'newest' ? idB - idA : idA - idB;
            });

            sortedRows.forEach(row => tbody.appendChild(row));
        }

        // Attendance logs filter
        function filterAttendanceLogs() {
            const subjectFilter = document.getElementById('attendanceSubjectFilter').value;
            const statusFilter = document.getElementById('attendanceStatusFilter').value;
            const rows = document.querySelectorAll('#attendanceLogsTable tbody tr');

            rows.forEach(row => {
                const subjectMatch = (subjectFilter === 'all' || row.getAttribute('data-subject') === subjectFilter);
                const statusMatch = (statusFilter === 'all' || row.getAttribute('data-status') === statusFilter);
                if (subjectMatch && statusMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        // Dark mode toggle handler
        function toggleDarkMode() {
            const isDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme_preference', isDark ? 'dark' : 'light');
            updateThemeIcon(isDark);
        }

        function updateThemeIcon(isDark) {
            const btns = document.querySelectorAll('.theme-toggle-btn');
            btns.forEach(btn => {
                btn.innerHTML = isDark 
                    ? '<i class="fa-solid fa-sun" style="color: #f59e0b;"></i>' 
                    : '<i class="fa-solid fa-moon"></i>';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme_preference') === 'dark') {
                document.body.classList.add('dark-mode');
                updateThemeIcon(true);
            }
        });
    </script>
</body>
</html>
