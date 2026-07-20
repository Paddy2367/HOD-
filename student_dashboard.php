<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php?role=student");
    exit;
}

$user = $_SESSION['user'];
$db = get_db();

$success_message = '';
$error_message = '';

// Handle POST submissions (Leave applications or Assignment uploads)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
        $reason = trim($_POST['reason']);
        $from_date = trim($_POST['from_date']);
        $to_date = trim($_POST['to_date']);
        $file_name = 'Leave_Form_' . date('d_M_Y') . '.pdf'; // Default fallback

        // Handle uploaded file if present
        if (isset($_FILES['leave_file']) && $_FILES['leave_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_name = $upload_dir . basename($_FILES['leave_file']['name']);
            move_uploaded_file($_FILES['leave_file']['tmp_name'], $file_name);
        } elseif (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
            $file_name = trim($_POST['file_name']);
        }

        if (!empty($reason) && !empty($from_date) && !empty($to_date)) {
            // Read, append, and save
            $new_leave = [
                'id' => count($db['leaves']) + 1,
                'file' => $file_name,
                'reason' => $reason,
                'from' => date('d M Y', strtotime($from_date)),
                'to' => date('d M Y', strtotime($to_date)),
                'status' => 'Pending'
            ];
            $db['leaves'][] = $new_leave;
            save_db($db);
            $success_message = 'Leave application submitted successfully! It has been routed to the Faculty Dashboard for approval.';
        } else {
            $error_message = 'Please fill out all leave application fields.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'upload_assignment') {
        $unit = intval($_POST['unit']);
        $file_name = 'Assignment_Unit_' . $unit . '.pdf';

        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_name = $upload_dir . basename($_FILES['assignment_file']['name']);
            move_uploaded_file($_FILES['assignment_file']['tmp_name'], $file_name);
        } elseif (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
            $file_name = trim($_POST['file_name']);
        }

        // Find assignment by unit and update status
        $updated = false;
        foreach ($db['assignments'] as &$assignment) {
            if ($assignment['unit'] === $unit) {
                $assignment['status'] = 'submitted';
                $assignment['file'] = $file_name;
                $assignment['marks'] = 'Pending';
                $updated = true;
                break;
            }
        }
        if ($updated) {
            save_db($db);
            $success_message = 'Assignment for Unit ' . $unit . ' submitted successfully!';
        } else {
            $error_message = 'Failed to submit assignment. Unit not found.';
        }
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
            $success_message = 'Grievance submitted successfully!';
        } else {
            $error_message = 'Please fill out all grievance fields.';
        }
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
    <title>College ERP Portal - Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/sweetalert2.all.min.js"></script>
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
                    <li><a class="sidebar-nav-item" onclick="switchTab('assignments', this)"><i class="fa-solid fa-file-invoice"></i><span>Assignments</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('leaves', this)"><i class="fa-solid fa-envelope-open-text"></i><span>Leave Requests</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('grievance', this)"><i class="fa-solid fa-circle-question"></i><span>Grievance</span></a></li>
                    <li><a class="sidebar-nav-item active" onclick="switchTab('notices', this)"><i class="fa-solid fa-bullhorn"></i><span>Notices</span></a></li>
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
                    <h2 id="currentTabTitle">Notices</h2>
                    <p id="currentTabSubtitle">Stay updated with the latest announcements and important information.</p>
                </div>
                <div class="user-profile-widget">
                    <div class="notification-bell" onclick="Swal.fire({title: 'Build in progress', text: 'This feature is currently under construction.', icon: 'info', confirmButtonColor: '#3b82f6'});">
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
            <!-- 1. NOTICES PAGE                              -->
            <!-- ============================================ -->
            <div id="tab-notices" class="app-view active">
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
                            <?php foreach ($db['notices'] as $notice): ?>
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
                                            <a href="#" onclick="showBuildAlert(event)" class="attachment-badge <?php echo $badge_class; ?>">
                                                <i class="fa-regular <?php echo ($badge_class==='pdf')?'fa-file-pdf':'fa-file-word'; ?>"></i>
                                                <span><?php echo basename(htmlspecialchars($notice['attachment'])); ?> (<?php echo $notice['size']; ?>)</span>
                                            </a>
                                            <a href="#" onclick="showBuildAlert(event)" class="btn-icon-download" style="margin-left: 0.5rem; text-decoration: none;">
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
                    <div class="pagination-container">
                        <button class="pagination-btn"><i class="fa-solid fa-angle-left"></i></button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn"><i class="fa-solid fa-angle-right"></i></button>
                    </div>
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
                            <?php foreach ($db['assignments'] as $assign): ?>
                                <tr>
                                    <td><?php echo $assign['unit']; ?></td>
                                    <td>
                                        <div class="notice-title"><?php echo htmlspecialchars($assign['title']); ?></div>
                                        <div class="notice-desc"><?php echo htmlspecialchars($assign['desc']); ?></div>
                                    </td>
                                    <td>
                                        <div class="date-cell"><i class="fa-regular fa-clock" style="margin-right: 0.35rem; color: #f59e0b;"></i><?php echo htmlspecialchars($assign['due']); ?></div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($assign['status'] === 'graded' || $assign['status'] === 'submitted'): ?>
                                            <div class="upload-success-text">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <span title="<?php echo htmlspecialchars($assign['file']); ?>">Submitted</span>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn-upload-assignment" onclick="openUploadModal(<?php echo $assign['unit']; ?>, '<?php echo htmlspecialchars($assign['title']); ?>')">
                                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                                <span>Upload</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($assign['status'] === 'graded'): ?>
                                            <span class="status-pill graded"><?php echo htmlspecialchars($assign['marks']); ?></span>
                                        <?php else: ?>
                                            <span class="status-pill pending">Pending</span>
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
                                        <input type="date" id="leaveFromDate" name="from_date" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="leaveToDate"><i class="fa-regular fa-calendar-days"></i> To Date</label>
                                    <div class="input-wrapper">
                                        <input type="date" id="leaveToDate" name="to_date" required>
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
                                <?php foreach ($db['leaves'] as $leave): ?>
                                    <tr>
                                        <td><?php echo $leave['id']; ?></td>
                                        <td>
                                            <div class="publisher-cell" style="flex-direction:row; align-items:center; gap:0.5rem;">
                                                <?php 
                                                    $ext = pathinfo($leave['file'], PATHINFO_EXTENSION);
                                                    $is_pdf = (strtolower($ext) === 'pdf');
                                                ?>
                                                <i class="fa-solid <?php echo $is_pdf?'fa-file-pdf':'fa-file-word'; ?>" style="font-size:1.15rem; color:<?php echo $is_pdf?'#ef4444':'#0284c7'; ?>"></i>
                                                <span class="pub-name" style="font-size:0.9rem; font-weight:500;"><?php echo htmlspecialchars($leave['file']); ?></span>
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
            <!-- 5. STUDENT PROFILE PAGE                      -->
            <!-- ============================================ -->
            <div id="tab-profile" class="app-view">
                <div class="settings-form-container" style="max-width: 800px; margin: 0 auto; background: white; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 2rem; box-shadow: var(--box-shadow-subtle);">
                    <div style="display: flex; gap: 2rem; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 2rem; margin-bottom: 2rem;">
                        <img src="<?= htmlspecialchars($user['avatar'] ?? 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=150&auto=format&fit=crop') ?>" alt="Student Avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-light);">
                        <div>
                            <h2 style="font-size: 1.75rem; font-weight: 800; color: #111827; margin: 0 0 0.5rem 0;"><?= htmlspecialchars($user['name']) ?></h2>
                            <span class="status-pill graded" style="font-size: 0.85rem; padding: 0.25rem 0.75rem;">Active Student</span>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 0.95rem;">ID: <?= htmlspecialchars($user['username']) ?> | <?= htmlspecialchars($user['dept']) ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group-col">
                            <label>Full Name</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['name']) ?>" style="background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label>Student ID / Roll No</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['username']) ?>" style="background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
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
        function showBuildAlert(e) {
            e.preventDefault();
            console.log("showBuildAlert triggered");
            try {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Build in Progress',
                        text: 'This feature is currently under development.',
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });
                } else {
                    console.warn("Swal is undefined, falling back to native alert");
                    alert('Build in Progress');
                }
            } catch (err) {
                console.error("SweetAlert error:", err);
                alert('Build in Progress');
            }
        }
    </script>
</body>
</html>
