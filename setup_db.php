<?php
// setup_db.php
require_once 'config.php';

echo "<h2>Initializing Database Setup</h2>";

try {
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS erp_system DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE erp_system");
    echo "<p>Database 'erp_system' created or already exists.</p>";

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        role ENUM('student', 'faculty', 'hod', 'admin') NOT NULL,
        dept VARCHAR(100),
        avatar VARCHAR(255)
    )");
    echo "<p>Table 'users' created.</p>";

    // Create notices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        author VARCHAR(100) NOT NULL,
        role VARCHAR(50) NOT NULL,
        date_posted DATETIME NOT NULL,
        attachment VARCHAR(255),
        size VARCHAR(20)
    )");
    echo "<p>Table 'notices' created.</p>";

    // Create assignments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        due_date DATETIME NOT NULL,
        status ENUM('pending', 'submitted', 'graded') DEFAULT 'pending',
        file VARCHAR(255),
        marks VARCHAR(50) DEFAULT 'Pending',
        student_id INT,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'assignments' created.</p>";

    // Create leaves table
    $pdo->exec("CREATE TABLE IF NOT EXISTS leaves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file VARCHAR(255) NOT NULL,
        reason VARCHAR(100) NOT NULL,
        from_date DATE NOT NULL,
        to_date DATE NOT NULL,
        status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        student_id INT,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'leaves' created.</p>";

    // Insert Default Users
    $password = password_hash('12345678', PASSWORD_DEFAULT);
    $admin_password = password_hash('12345', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $users = [
            ['125UIT1080', $password, 'Prasad Kulkarni', 'student', 'IT - Div A (A2)', 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=150&auto=format&fit=crop'],
            ['faculty1', $password, 'Prof. Rajesh Sharma', 'faculty', 'IT Department', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=150&auto=format&fit=crop'],
            ['hod1', $password, 'Prof. Amit Deshmukh', 'hod', 'IT Department Head', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=150&auto=format&fit=crop'],
            ['admin1', $admin_password, 'System Admin', 'admin', 'Administration', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=150&auto=format&fit=crop']
        ];
        
        $insert_user = $pdo->prepare("INSERT INTO users (username, password_hash, name, role, dept, avatar) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($users as $u) {
            $insert_user->execute($u);
        }
        echo "<p>Default users inserted.</p>";
        
        // Insert sample notices
        $notices = [
            ['Internal Exam Schedule', 'Internal examinations will be held from 20th July 2026. Please check the timetable.', 'Prof. Rajesh Sharma', 'Faculty', '2026-07-15 10:30:00', 'schedule.pdf', '245 KB'],
            ['Project Submission', 'Final year project reports to be submitted by 5th August 2026.', 'Prof. Neha Patil', 'Faculty', '2026-07-14 14:15:00', 'guidelines.docx', '512 KB'],
            ['Holiday Notice', 'College will remain closed on 18th July 2026 on account of Muharram.', 'Admin Office', 'Administration', '2026-07-12 09:00:00', '', '']
        ];
        $insert_notice = $pdo->prepare("INSERT INTO notices (title, description, author, role, date_posted, attachment, size) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($notices as $n) {
            $insert_notice->execute($n);
        }
        echo "<p>Sample notices inserted.</p>";
        
        // Insert sample assignments for student 1 (Prasad Kulkarni)
        $assignments = [
            [1, 'Unit 1 - Introduction to Basics', 'Solve all the questions given in the assignment.', '2026-07-25 23:59:00', 'graded', 'assignment_1_prasad.pdf', '7 / 10', 1],
            [2, 'Unit 2 - Data Structures', 'Answer all questions in detail.', '2026-08-08 23:59:00', 'graded', 'assignment_2_final.pdf', '10 / 10', 1],
            [3, 'Unit 3 - Object Oriented Programming', 'Complete the assignment as per instructions.', '2026-08-22 23:59:00', 'pending', '', 'Pending', 1]
        ];
        $insert_assign = $pdo->prepare("INSERT INTO assignments (unit, title, description, due_date, status, file, marks, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($assignments as $a) {
            $insert_assign->execute($a);
        }
        echo "<p>Sample assignments inserted.</p>";

        // Insert sample leaves for student 1
        $leaves = [
            ['Leave_Form_15_Jan_2026.pdf', 'Medical', '2026-01-15', '2026-01-17', 'Approved', 1],
            ['Leave_Form_02_Feb_2026.docx', 'Personal', '2026-02-02', '2026-02-03', 'Pending', 1]
        ];
        $insert_leave = $pdo->prepare("INSERT INTO leaves (file, reason, from_date, to_date, status, student_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($leaves as $l) {
            $insert_leave->execute($l);
        }
        echo "<p>Sample leaves inserted.</p>";

    } else {
        // Update admin1 password if database is already seeded
        $update_admin = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin1'");
        $update_admin->execute([$admin_password]);
        echo "<p>Admin password updated to '12345'.</p>";
        echo "<p>Users already exist. Skipping seed data.</p>";
    }

    echo "<h3>Setup Complete! <a href='login.php'>Go to Login</a></h3>";

} catch(PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>
