<?php
// Prevent direct access to db.php
if (basename($_SERVER['PHP_SELF']) == 'db.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

define('DB_FILE', __DIR__ . '/database.json');

function init_db() {
    if (!file_exists(DB_FILE)) {
        $default_data = [
            'notices' => [
                [
                    'id' => 1,
                    'title' => 'Internal Exam Schedule',
                    'desc' => 'Internal examinations will be held from 20th July 2026. Please check the timetable.',
                    'author' => 'Prof. Rajesh Sharma',
                    'role' => 'Faculty',
                    'date' => '15 Jul 2026 10:30 AM',
                    'attachment' => 'schedule.pdf',
                    'size' => '245 KB',
                    'expiry' => '2026-08-01'
                ]
            ],
            'assignments' => [
                [
                    'id' => 1,
                    'unit' => 1,
                    'title' => 'Unit 1 - Introduction to Basics',
                    'desc' => 'Solve all the questions given in the assignment.',
                    'due' => '25 Jul 2026 11:59 PM',
                    'status' => 'graded',
                    'file' => 'assignment_1_prasad.pdf',
                    'marks' => '7 / 10',
                    'created_by' => 'Prof. Rajesh Sharma'
                ],
                [
                    'id' => 2,
                    'unit' => 2,
                    'title' => 'Unit 2 - Data Structures',
                    'desc' => 'Answer all questions in detail.',
                    'due' => '08 Aug 2026 11:59 PM',
                    'status' => 'graded',
                    'file' => 'assignment_2_final.pdf',
                    'marks' => '10 / 10',
                    'created_by' => 'Prof. Rajesh Sharma'
                ],
                [
                    'id' => 3,
                    'unit' => 3,
                    'title' => 'Unit 3 - Object Oriented Programming',
                    'desc' => 'Complete the assignment as per instructions.',
                    'due' => '22 Aug 2026 11:59 PM',
                    'status' => 'pending',
                    'file' => '',
                    'marks' => 'Pending',
                    'created_by' => 'Prof. Neha Patil'
                ],
                [
                    'id' => 4,
                    'unit' => 4,
                    'title' => 'Unit 4 - Database Management Systems',
                    'desc' => 'Submit all the questions.',
                    'due' => '05 Sep 2026 11:59 PM',
                    'status' => 'pending',
                    'file' => '',
                    'marks' => 'Pending',
                    'created_by' => 'Prof. Rajesh Sharma'
                ],
                [
                    'id' => 5,
                    'unit' => 5,
                    'title' => 'Unit 5 - Operating Systems',
                    'desc' => 'Answer the given questions.',
                    'due' => '20 Sep 2026 11:59 PM',
                    'status' => 'pending',
                    'file' => '',
                    'marks' => 'Pending',
                    'created_by' => 'Prof. Amit Deshmukh'
                ],
                [
                    'id' => 6,
                    'unit' => 6,
                    'title' => 'Unit 6 - Computer Networks',
                    'desc' => 'Complete and submit the assignment.',
                    'due' => '05 Oct 2026 11:59 PM',
                    'status' => 'pending',
                    'file' => '',
                    'marks' => 'Pending',
                    'created_by' => 'Prof. Neha Patil'
                ]
            ],
            'assignment_submissions' => [
                [
                    'id' => 1,
                    'assignment_unit' => 1,
                    'student_id' => '125UIT1080',
                    'student_name' => 'Prasad Kulkarni',
                    'file' => 'assignment_1_prasad.pdf',
                    'status' => 'graded',
                    'marks' => '7 / 10'
                ],
                [
                    'id' => 2,
                    'assignment_unit' => 2,
                    'student_id' => '125UIT1080',
                    'student_name' => 'Prasad Kulkarni',
                    'file' => 'assignment_2_final.pdf',
                    'status' => 'graded',
                    'marks' => '10 / 10'
                ]
            ],
            'leaves' => [
                [
                    'id' => 1,
                    'applicant_name' => 'Prasad Kulkarni',
                    'applicant_role' => 'Student',
                    'file' => 'Leave_Form_15_Jan_2026.pdf',
                    'reason' => 'Medical',
                    'from' => '15 Jan 2026',
                    'to' => '17 Jan 2026',
                    'status' => 'Approved',
                    'remarks' => 'Approved based on medical certificate.'
                ],
                [
                    'id' => 2,
                    'applicant_name' => 'Prasad Kulkarni',
                    'applicant_role' => 'Student',
                    'file' => 'Leave_Form_02_Feb_2026.docx',
                    'reason' => 'Personal',
                    'from' => '02 Feb 2026',
                    'to' => '03 Feb 2026',
                    'status' => 'Approved',
                    'remarks' => 'Approved. Cover back missed tasks.'
                ],
                [
                    'id' => 3,
                    'applicant_name' => 'Prof. Rajesh Sharma',
                    'applicant_role' => 'Faculty',
                    'file' => 'Leave_Form_10_Mar_2026.pdf',
                    'reason' => 'Family Function',
                    'from' => '10 Mar 2026',
                    'to' => '11 Mar 2026',
                    'status' => 'Approved',
                    'remarks' => 'Arranged backup classes.'
                ],
                [
                    'id' => 4,
                    'applicant_name' => 'Prasad Kulkarni',
                    'applicant_role' => 'Student',
                    'file' => 'Leave_Form_21_Apr_2026.docx',
                    'reason' => 'Medical',
                    'from' => '21 Apr 2026',
                    'to' => '23 Apr 2026',
                    'status' => 'Rejected',
                    'remarks' => 'Rejected. No active medical report found.'
                ],
                [
                    'id' => 5,
                    'applicant_name' => 'Prof. Neha Patil',
                    'applicant_role' => 'Faculty',
                    'file' => 'Leave_Form_05_May_2026.pdf',
                    'reason' => 'Medical',
                    'from' => '05 May 2026',
                    'to' => '07 May 2026',
                    'status' => 'Pending',
                    'remarks' => ''
                ]
            ],
            'students' => [
                [
                    'id' => '125UIT1080',
                    'username' => '125UIT1080',
                    'name' => 'Prasad Kulkarni',
                    'email' => 'prasad.kulkarni@erp.edu',
                    'phone' => '+91 99223 34455',
                    'dept' => 'IT - Div A (A2)',
                    'semester' => '5th Semester',
                    'attendance' => '85%',
                    'status' => 'Active',
                    'avatar' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=150&auto=format&fit=crop'
                ],
                [
                    'id' => '125UIT1081',
                    'username' => 'sneha81',
                    'name' => 'Sneha Deshmukh',
                    'email' => 'sneha.deshmukh@erp.edu',
                    'phone' => '+91 98877 66554',
                    'dept' => 'IT - Div A (A2)',
                    'semester' => '5th Semester',
                    'attendance' => '94%',
                    'status' => 'Active',
                    'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=150&auto=format&fit=crop'
                ],
                [
                    'id' => '125UIT1082',
                    'username' => 'rahul82',
                    'name' => 'Rahul Sharma',
                    'email' => 'rahul.sharma@erp.edu',
                    'phone' => '+91 97766 55443',
                    'dept' => 'IT - Div B (B1)',
                    'semester' => '5th Semester',
                    'attendance' => '72%',
                    'status' => 'Active',
                    'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=150&auto=format&fit=crop'
                ]
            ],
            'faculty' => [
                [
                    'id' => 'faculty1',
                    'username' => 'faculty1',
                    'name' => 'Prof. Rajesh Sharma',
                    'email' => 'rajesh.sharma@erp.edu',
                    'phone' => '+91 91122 33445',
                    'designation' => 'Assistant Professor',
                    'workload' => '16 Hours / Week',
                    'attendance' => '95%',
                    'subjects' => 'Data Structures, Database Management Systems',
                    'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=150&auto=format&fit=crop'
                ],
                [
                    'id' => 'faculty2',
                    'username' => 'faculty2',
                    'name' => 'Prof. Neha Patil',
                    'email' => 'neha.patil@erp.edu',
                    'phone' => '+91 92233 44556',
                    'designation' => 'Associate Professor',
                    'workload' => '12 Hours / Week',
                    'attendance' => '92%',
                    'subjects' => 'Object Oriented Programming, Computer Networks',
                    'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?q=80&w=150&auto=format&fit=crop'
                ],
                [
                    'id' => 'hod1',
                    'username' => 'hod1',
                    'name' => 'Prof. Amit Deshmukh',
                    'email' => 'amit.deshmukh@erp.edu',
                    'phone' => '+91 93344 55667',
                    'designation' => 'Professor & HOD',
                    'workload' => '8 Hours / Week',
                    'attendance' => '98%',
                    'subjects' => 'Operating Systems, Software Engineering',
                    'avatar' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=150&auto=format&fit=crop'
                ]
            ],
            'grievances' => [
                [
                    'id' => 1,
                    'student_id' => '125UIT1080',
                    'student_name' => 'Prasad Kulkarni',
                    'title' => 'Wi-Fi Issues in Lab 2',
                    'category' => 'Infrastructure',
                    'desc' => 'The Wi-Fi connection in Computer Lab 2 is extremely unstable. It disconnects frequently, making it hard to download software libraries during practicals.',
                    'date' => '16 Jul 2026 11:30 AM',
                    'status' => 'In Progress',
                    'replies' => [
                        [
                            'author' => 'Prof. Amit Deshmukh',
                            'role' => 'HOD',
                            'message' => 'I have escalated this issue to the IT systems team. They will audit the router in Lab 2 tomorrow morning.',
                            'date' => '16 Jul 2026 03:45 PM'
                        ]
                    ]
                ],
                [
                    'id' => 2,
                    'student_id' => '125UIT1081',
                    'student_name' => 'Sneha Deshmukh',
                    'title' => 'Reference Books shortage in Library',
                    'category' => 'Academics',
                    'desc' => 'There are only 2 copies of the primary DBMS reference book (Korth) in the library, whereas there are 120 students in the semester. Please procure more copies.',
                    'date' => '14 Jul 2026 09:15 AM',
                    'status' => 'Pending',
                    'replies' => []
                ]
            ],
            'recent_activity' => [
                [
                    'title' => 'New Leave Application',
                    'desc' => 'Prasad Kulkarni applied for Medical Leave',
                    'time' => '2 hours ago'
                ],
                [
                    'title' => 'Notice Published',
                    'desc' => 'Prof. Rajesh Sharma published "Internal Exam Schedule"',
                    'time' => '4 hours ago'
                ],
                [
                    'title' => 'Grievance Raised',
                    'desc' => 'Sneha Deshmukh reported "Shortage of books in Library"',
                    'time' => '1 day ago'
                ],
                [
                    'title' => 'Assignment Graded',
                    'desc' => 'Prof. Rajesh Sharma graded Prasad\'s "Unit 2 Assignment"',
                    'time' => '2 days ago'
                ]
            ],
            'settings' => [
                'dept_name' => 'Information Technology',
                'dept_code' => 'IT-ENGG',
                'intake' => '120',
                'hod_name' => 'Prof. Amit Deshmukh',
                'hod_email' => 'amit.deshmukh@erp.edu',
                'hod_phone' => '+91 93344 55667',
                'notifications_enabled' => true,
                'captcha_enabled' => true,
                'maintenance_mode' => false
            ]
        ];
        file_put_contents(DB_FILE, json_encode($default_data, JSON_PRETTY_PRINT));
    }
}

function get_db() {
    init_db();
    $data = json_decode(file_get_contents(DB_FILE), true);
    
    // Ensure all new keys are seeded if they are missing from an existing database.json file
    $updated = false;
    $defaults = [
        'students' => [
            [
                'id' => '125UIT1080',
                'username' => '125UIT1080',
                'name' => 'Prasad Kulkarni',
                'email' => 'prasad.kulkarni@erp.edu',
                'phone' => '+91 99223 34455',
                'dept' => 'IT - Div A (A2)',
                'semester' => '5th Semester',
                'attendance' => '85%',
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=150&auto=format&fit=crop'
            ],
            [
                'id' => '125UIT1081',
                'username' => 'sneha81',
                'name' => 'Sneha Deshmukh',
                'email' => 'sneha.deshmukh@erp.edu',
                'phone' => '+91 98877 66554',
                'dept' => 'IT - Div A (A2)',
                'semester' => '5th Semester',
                'attendance' => '94%',
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=150&auto=format&fit=crop'
            ],
            [
                'id' => '125UIT1082',
                'username' => 'rahul82',
                'name' => 'Rahul Sharma',
                'email' => 'rahul.sharma@erp.edu',
                'phone' => '+91 97766 55443',
                'dept' => 'IT - Div B (B1)',
                'semester' => '5th Semester',
                'attendance' => '72%',
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=150&auto=format&fit=crop'
            ]
        ],
        'faculty' => [
            [
                'id' => 'faculty1',
                'username' => 'faculty1',
                'name' => 'Prof. Rajesh Sharma',
                'email' => 'rajesh.sharma@erp.edu',
                'phone' => '+91 91122 33445',
                'designation' => 'Assistant Professor',
                'workload' => '16 Hours / Week',
                'attendance' => '95%',
                'subjects' => 'Data Structures, Database Management Systems',
                'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=150&auto=format&fit=crop'
            ],
            [
                'id' => 'faculty2',
                'username' => 'faculty2',
                'name' => 'Prof. Neha Patil',
                'email' => 'neha.patil@erp.edu',
                'phone' => '+91 92233 44556',
                'designation' => 'Associate Professor',
                'workload' => '12 Hours / Week',
                'attendance' => '92%',
                'subjects' => 'Object Oriented Programming, Computer Networks',
                'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?q=80&w=150&auto=format&fit=crop'
            ],
            [
                'id' => 'hod1',
                'username' => 'hod1',
                'name' => 'Prof. Amit Deshmukh',
                'email' => 'amit.deshmukh@erp.edu',
                'phone' => '+91 93344 55667',
                'designation' => 'Professor & HOD',
                'workload' => '8 Hours / Week',
                'attendance' => '98%',
                'subjects' => 'Operating Systems, Software Engineering',
                'avatar' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=150&auto=format&fit=crop'
            ]
        ],
        'grievances' => [
            [
                'id' => 1,
                'student_id' => '125UIT1080',
                'student_name' => 'Prasad Kulkarni',
                'title' => 'Wi-Fi Issues in Lab 2',
                'category' => 'Infrastructure',
                'desc' => 'The Wi-Fi connection in Computer Lab 2 is extremely unstable. It disconnects frequently, making it hard to download software libraries during practicals.',
                'date' => '16 Jul 2026 11:30 AM',
                'status' => 'In Progress',
                'replies' => [
                    [
                        'author' => 'Prof. Amit Deshmukh',
                        'role' => 'HOD',
                        'message' => 'I have escalated this issue to the IT systems team. They will audit the router in Lab 2 tomorrow morning.',
                        'date' => '16 Jul 2026 03:45 PM'
                    ]
                ]
            ],
            [
                'id' => 2,
                'student_id' => '125UIT1081',
                'student_name' => 'Sneha Deshmukh',
                'title' => 'Reference Books shortage in Library',
                'category' => 'Academics',
                'desc' => 'There are only 2 copies of the primary DBMS reference book (Korth) in the library, whereas there are 120 students in the semester. Please procure more copies.',
                'date' => '14 Jul 2026 09:15 AM',
                'status' => 'Pending',
                'replies' => []
            ]
        ],
        'recent_activity' => [
            [
                'title' => 'New Leave Application',
                'desc' => 'Prasad Kulkarni applied for Medical Leave',
                'time' => '2 hours ago'
            ],
            [
                'title' => 'Notice Published',
                'desc' => 'Prof. Rajesh Sharma published "Internal Exam Schedule"',
                'time' => '4 hours ago'
            ],
            [
                'title' => 'Grievance Raised',
                'desc' => 'Sneha Deshmukh reported "Shortage of books in Library"',
                'time' => '1 day ago'
            ],
            [
                'title' => 'Assignment Graded',
                'desc' => 'Prof. Rajesh Sharma graded Prasad\'s "Unit 2 Assignment"',
                'time' => '2 days ago'
            ]
        ],
        'settings' => [
            'dept_name' => 'Information Technology',
            'dept_code' => 'IT-ENGG',
            'intake' => '120',
            'hod_name' => 'Prof. Amit Deshmukh',
            'hod_email' => 'amit.deshmukh@erp.edu',
            'hod_phone' => '+91 93344 55667',
            'notifications_enabled' => true,
            'captcha_enabled' => true,
            'maintenance_mode' => false
        ]
    ];
    
    foreach ($defaults as $key => $val) {
        if (!isset($data[$key])) {
            $data[$key] = $val;
            $updated = true;
        }
    }

    // Ensure assignments have an 'id' and 'created_by'
    if (isset($data['assignments'])) {
        foreach ($data['assignments'] as $idx => &$assign) {
            if (!isset($assign['id'])) {
                $assign['id'] = $idx + 1;
                $updated = true;
            }
            if (!isset($assign['created_by'])) {
                $assign['created_by'] = 'Prof. Rajesh Sharma';
                $updated = true;
            }
        }
    }

    // Ensure notices have an 'expiry'
    if (isset($data['notices'])) {
        foreach ($data['notices'] as &$notice) {
            if (!isset($notice['expiry'])) {
                $notice['expiry'] = '';
                $updated = true;
            }
        }
    }

    // Ensure leaves have applicant_name, applicant_role, and remarks
    if (isset($data['leaves'])) {
        foreach ($data['leaves'] as &$leave) {
            if (!isset($leave['applicant_name'])) {
                $leave['applicant_name'] = 'Prasad Kulkarni';
                $leave['applicant_role'] = 'Student';
                $updated = true;
            }
            if (!isset($leave['remarks'])) {
                $leave['remarks'] = '';
                $updated = true;
            }
        }
    }
    
    if ($updated) {
        save_db($data);
    }
    
    return $data;
}

function save_db($data) {
    file_put_contents(DB_FILE, json_encode($data, JSON_PRETTY_PRINT));
}
?>
