<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?role=admin");
    exit;
}

$user = $_SESSION['user'];
$db = get_db();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_system_config') {
        $db['settings']['maintenance_mode'] = isset($_POST['maintenance_mode']);
        $db['settings']['captcha_enabled'] = isset($_POST['captcha_enabled']);
        $db['settings']['notifications_enabled'] = isset($_POST['notifications_enabled']);
        save_db($db);
        $success_message = "System configurations updated successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College ERP Portal - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="theme-admin">
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="sidebar-brand">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div>
                        <span>College ERP</span>
                        <span class="sub">Admin Portal</span>
                    </div>
                </div>
                <ul class="sidebar-nav">
                    <li><a class="sidebar-nav-item active" onclick="switchTab('users', this)"><i class="fa-solid fa-user-gear"></i><span>User Management</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('departments', this)"><i class="fa-solid fa-building-columns"></i><span>Departments</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('notices', this)"><i class="fa-solid fa-rectangle-ad"></i><span>Notice Board</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('reports', this)"><i class="fa-solid fa-file-invoice-dollar"></i><span>Report Generation</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('system', this)"><i class="fa-solid fa-sliders"></i><span>System Config</span></a></li>
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
                    <h2 id="currentTabTitle">User Management</h2>
                    <p id="currentTabSubtitle">Admin portal for user credentials, roles, and security access profiles.</p>
                </div>
                <div class="user-profile-widget">
                    <div class="notification-bell" onclick="Swal.fire({title: 'Build in progress', text: 'This feature is currently under construction.', icon: 'info', confirmButtonColor: '#f97316'});">
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

            <!-- Users View -->
            <div id="view-users" class="app-view active" style="padding: 1.5rem;">
                <div class="data-table-container" style="margin-bottom: 2rem;">
                    <div class="table-header-filters" style="padding: 1rem 1.5rem;">
                        <h4 style="margin-right:auto;font-weight:700;">Student Accounts</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name / Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['students'] as $s): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:0.75rem;">
                                        <img src="<?= htmlspecialchars($s['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                        <div>
                                            <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                                            <span style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($s['username']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?= htmlspecialchars($s['phone']) ?></td>
                                <td><?= htmlspecialchars($s['dept']) ?></td>
                                <td><span class="status-pill graded"><?= htmlspecialchars($s['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="data-table-container">
                    <div class="table-header-filters" style="padding: 1rem 1.5rem;">
                        <h4 style="margin-right:auto;font-weight:700;">Faculty & HOD Accounts</h4>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name / Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Designation</th>
                                <th>Subjects</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($db['faculty'] as $f): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:0.75rem;">
                                        <img src="<?= htmlspecialchars($f['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                        <div>
                                            <strong><?= htmlspecialchars($f['name']) ?></strong><br>
                                            <span style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($f['username']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($f['email']) ?></td>
                                <td><?= htmlspecialchars($f['phone']) ?></td>
                                <td><?= htmlspecialchars($f['designation']) ?></td>
                                <td><span style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($f['subjects']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Config View -->
            <div id="view-system" class="app-view" style="padding: 1.5rem;">
                <div class="settings-form-container" style="margin: 0 auto; max-width: 600px; background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 2rem;">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_system_config">
                        <h3 style="margin-bottom:1.5rem; color:#111827;">System Configuration</h3>
                        
                        <div style="display:flex; flex-direction:column; gap:1.5rem;">
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?= ($db['settings']['maintenance_mode'] ?? false) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--primary-color);">
                                <label for="maintenance_mode" style="font-size:0.95rem;color:#374151;cursor:pointer;"><strong>Maintenance Mode</strong> (Hides the student portal for system upgrades)</label>
                            </div>
                            
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <input type="checkbox" id="captcha_enabled" name="captcha_enabled" <?= ($db['settings']['captcha_enabled'] ?? false) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--primary-color);">
                                <label for="captcha_enabled" style="font-size:0.95rem;color:#374151;cursor:pointer;"><strong>Enable Captcha Login</strong> (Adds random character verification to sign-in)</label>
                            </div>
                            
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <input type="checkbox" id="notifications_enabled" name="notifications_enabled" <?= ($db['settings']['notifications_enabled'] ?? false) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--primary-color);">
                                <label for="notifications_enabled" style="font-size:0.95rem;color:#374151;cursor:pointer;"><strong>System Notifications</strong> (Toggle background email alerts and notification bells)</label>
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;margin-top:2.5rem;">
                            <button type="submit" class="btn-hod-action" style="background:var(--primary-color);color:white;border:none;border-radius:4px;padding:0.75rem 1.5rem;cursor:pointer;font-weight:600;">Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mock Template View (for other tabs) -->
            <div id="view-mock" class="app-view" style="padding: 1.5rem;">
                <div class="mock-page-container">
                    <div class="mock-page-icon" id="mockPageIcon">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <h3 id="mockPageTitle">User Management</h3>
                    <p id="mockPageDesc">Manage university users. Generate accounts, check login activity registers, update roles, and reset student/faculty passwords.</p>
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

            const headerTitle = document.getElementById('currentTabTitle');
            const headerSubtitle = document.getElementById('currentTabSubtitle');
            
            // Hide all app-views
            document.querySelectorAll('.app-view').forEach(view => view.classList.remove('active'));

            if (tabName === 'users') {
                document.getElementById('view-users').classList.add('active');
                headerTitle.textContent = "User Management";
                headerSubtitle.textContent = "Admin portal for user credentials, roles, and security access profiles.";
            } else if (tabName === 'system') {
                document.getElementById('view-system').classList.add('active');
                headerTitle.textContent = "System Configuration";
                headerSubtitle.textContent = "Manage global system parameters, theme colors, and portal security levels.";
            } else {
                document.getElementById('view-mock').classList.add('active');
                
                const titleText = document.getElementById('mockPageTitle');
                const descText = document.getElementById('mockPageDesc');
                const iconBox = document.getElementById('mockPageIcon');

                headerTitle.textContent = tabName.charAt(0).toUpperCase() + tabName.slice(1);
                titleText.textContent = tabName.toUpperCase();

                if (tabName === 'departments') {
                    headerTitle.textContent = "Department Management";
                    headerSubtitle.textContent = "Configure curriculum courses, subject lines, and department designations.";
                    iconBox.innerHTML = '<i class="fa-solid fa-building-columns"></i>';
                    descText.textContent = "Set up new branches of studies, add branches heads, link courses structures, and map faculty to specific branch modules.";
                } else if (tabName === 'notices') {
                    headerTitle.textContent = "Notice Management";
                    headerSubtitle.textContent = "Publish global and targeted news notices to the ERP bulletin boards.";
                    iconBox.innerHTML = '<i class="fa-solid fa-rectangle-ad"></i>';
                    descText.textContent = "Publish news events, holidays guidelines, and administrative alerts. Target notices to specific profiles (e.g. all students or all staff).";
                } else if (tabName === 'reports') {
                    headerTitle.textContent = "Report Generation";
                    headerSubtitle.textContent = "Compile system metrics, resource logs, and institution audit details.";
                    iconBox.innerHTML = '<i class="fa-solid fa-file-invoice-dollar"></i>';
                    descText.textContent = "Generate reports regarding registrations, active sessions count, security alerts logs, and database size stats.";
                }
            }
        }
    </script>
</body>
</html>
