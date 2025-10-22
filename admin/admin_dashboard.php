<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        /* Enhanced Dashboard Styles */
        .welcome-banner {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .welcome-banner h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .welcome-banner p {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        .dashboard-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            height: 100%;
            background: white;
            overflow: hidden;
            position: relative;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .dashboard-card:hover::before {
            opacity: 1;
        }
        
        .card-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover .card-icon-wrapper {
            transform: scale(1.1) rotate(5deg);
        }
        
        .card-icon-wrapper i {
            font-size: 28px;
        }
        
        .card-content h5 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: #2d3748;
        }
        
        .card-content small {
            color: #718096;
            font-size: 0.875rem;
        }
        
        /* Color themes for cards */
        .card-primary { color: #667eea; }
        .card-primary .card-icon-wrapper { background: rgba(102, 126, 234, 0.1); }
        
        .card-success { color: #48bb78; }
        .card-success .card-icon-wrapper { background: rgba(72, 187, 120, 0.1); }
        
        .card-warning { color: #f6ad55; }
        .card-warning .card-icon-wrapper { background: rgba(246, 173, 85, 0.1); }
        
        .card-danger { color: #fc8181; }
        .card-danger .card-icon-wrapper { background: rgba(252, 129, 129, 0.1); }
        
        .card-info { color: #4299e1; }
        .card-info .card-icon-wrapper { background: rgba(66, 153, 225, 0.1); }
        
        .card-secondary { color: #a0aec0; }
        .card-secondary .card-icon-wrapper { background: rgba(160, 174, 192, 0.1); }
        
        .card-dark { color: #2d3748; }
        .card-dark .card-icon-wrapper { background: rgba(45, 55, 72, 0.1); }
        
        /* Stats overview */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .welcome-banner {
                padding: 1.5rem;
            }
            
            .welcome-banner h2 {
                font-size: 1.5rem;
            }
            
            .welcome-banner p {
                font-size: 1rem;
            }
            
            .card-icon-wrapper {
                width: 50px;
                height: 50px;
            }
            
            .card-icon-wrapper i {
                font-size: 24px;
            }
            
            .card-content h5 {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.25rem;
            }
        }
        
        @media (max-width: 576px) {
            .dashboard-card {
                margin-bottom: 1rem;
            }
            
            .card-icon-wrapper {
                margin-bottom: 0.5rem;
            }
            
            .dashboard-card .d-flex {
                flex-direction: column;
                text-align: center;
            }
            
            .dashboard-card .d-flex.align-items-center {
                align-items: center !important;
            }
        }
        
        /* Loading animation */
        .content-area {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const toggle = document.getElementById('header-toggle');
            const nav = document.getElementById('nav-bar');
            const overlay = document.getElementById('overlay');

            // Toggle sidebar
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                nav.classList.toggle('show');
                overlay.classList.toggle('show');
            });

            // Close sidebar if clicking outside or on overlay
            overlay.addEventListener('click', () => {
                nav.classList.remove('show');
                overlay.classList.remove('show');
            });

            // Extra safety: clicking anywhere outside
            document.addEventListener('click', (e) => {
                if (nav.classList.contains('show') && !nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });
        });

        function loadContent(page) {
            $("#content-area").load(page, function(response, status, xhr) {
                // This callback runs after content is loaded
                if (status === "success") {
                    // If it's the analytics page, initialize charts
                    if (page.includes('analytics')) {
                        // Small delay to ensure DOM is fully ready
                        setTimeout(function() {
                            if (typeof initializeCharts === 'function') {
                                initializeCharts();
                            }
                        }, 100);
                    }
                }
            });
        }

        function toggleDropdown(id) {
            $("#" + id).slideToggle("fast");
        }
    </script>
</head>
<body id="body-pd">
<!-- Header -->
<header class="header" id="header">
    <div class="header_toggle"> 
        <i class='bx bx-menu' id="header-toggle"></i> 
    </div>

    <!-- Profile Dropdown (Bootstrap-native) -->
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-decoration-none" id="profileDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class='bx bx-user-circle text-primary' style="font-size: 40px; cursor:pointer;"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="profileDropdown">
            <a href="#" class="dropdown-item d-flex align-items-center" onclick="loadContent('../view_profile.php')">
                <i class='bx bx-id-card mr-2'></i> View Profile
            </a>
            <a href="#" class="dropdown-item d-flex align-items-center" onclick="loadContent('../profile.php')">
                <i class='bx bx-edit-alt mr-2'></i> Edit Profile
            </a>
            <div class="dropdown-divider"></div>
            <a href="#" class="dropdown-item text-danger d-flex align-items-center" data-toggle="modal" data-target="#logoutModal">
                <i class='bx bx-log-out-circle mr-2'></i> Logout
            </a>
        </div>
    </div>
</header>

    <!-- Sidebar -->
    <div class="l-navbar" id="nav-bar">
        <img src="../assets/images/Unisan_logo.png" id="sidebar-logo" alt="Sidebar Logo" class="header_img">
        <h4 style="text-align: center; color: white;">Admin Menu</h4>
        <nav class="nav">
            <a href="#" class="nav_link" onclick="loadContent('admin_analytics.php')">
                <i class='bx bx-home-alt'></i> <span>Dashboard</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_create_lgu_personnel.php')">
                <i class='bx bx-user-plus'></i> <span>Manage LGU Personnel</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_manage_departments.php')">
                <i class='bx bx-building-house'></i> <span>Manage Department</span>
            </a>

            <!-- Feedback dropdown -->
            <a href="javascript:void(0);" class="nav_link" onclick="toggleDropdown('feedbackDropdown')">
                <i class='bx bx-message-rounded-dots'></i> 
                <span>Select Feedback</span> <i class='bx bx-chevron-down ml-auto'></i>
            </a>
            <div id="feedbackDropdown" class="dropdown-submenu" style="display:none;">
                <a href="#" class="nav_link sub_link" onclick="loadContent('admin_view_feedback.php')">Service Feedback</a>
                <a href="#" class="nav_link sub_link" onclick="loadContent('admin_view_commendations.php')">Personnel Feedback</a>
                <a href="#" class="nav_link sub_link" onclick="loadContent('admin_view_complaints.php')">System Feedback</a>
            </div>

            <a href="#" class="nav_link" onclick="loadContent('admin_view_appointments.php')">
                <i class='bx bx-calendar-event'></i> <span>View Appointments</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('admin_manage_residents_accounts.php')">
                <i class='bx bx-group'></i> <span>Manage Residents Accounts</span>
            </a>
            <a href="#" class="nav_link" data-toggle="modal" data-target="#logoutModal">
                <i class='bx bx-log-out'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <div id="overlay" class="overlay"></div>

    <!-- Content Area -->
    <div class="content-area" id="content-area">
        <div class="container-fluid px-3 px-md-4 py-4">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2><i class='bx bx-grid-alt mr-2'></i>Welcome Back, 
                    <?php 
                    if (isset($_SESSION['user_name'])) {
                        echo htmlspecialchars($_SESSION['user_name']);
                    } else {
                        echo 'Admin';
                    }
                    ?>!
                </h2>
                <p>Manage your municipality's operations efficiently from your dashboard</p>
            </div>

            <!-- Quick Actions Section -->
            <h3 class="section-title">Quick Actions</h3>
            <div class="row">
                <div class="col-12 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_analytics.php')">
                        <div class="dashboard-card card-dark p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-grid-alt'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Analytics Dashboard</h5>
                                    <small>View comprehensive system analytics and insights</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Management Section -->
            <h3 class="section-title mt-4">Management</h3>
            <div class="row">
                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_create_lgu_personnel.php')">
                        <div class="dashboard-card card-primary p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-user-plus'></i>
                                </div>
                                <div class="card-content">
                                    <h5>LGU Personnel</h5>
                                    <small>Create and manage personnel accounts</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_manage_departments.php')">
                        <div class="dashboard-card card-success p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-building-house'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Departments</h5>
                                    <small>Organize and manage departments</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_manage_residents_accounts.php')">
                        <div class="dashboard-card card-info p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-group'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Resident Accounts</h5>
                                    <small>Monitor and manage resident users</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Services & Feedback Section -->
            <h3 class="section-title mt-4">Services & Feedback</h3>
            <div class="row">
                <div class="col-12 col-md-6 col-lg-6 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_view_appointments.php')">
                        <div class="dashboard-card card-danger p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-calendar-event'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Appointments</h5>
                                    <small>Track and manage all appointments</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-lg-6 mb-3">
                    <a href="#" class="text-decoration-none" onclick="loadContent('admin_view_feedback.php')">
                        <div class="dashboard-card card-warning p-3 p-md-4">
                            <div class="d-flex align-items-center">
                                <div class="card-icon-wrapper">
                                    <i class='bx bx-message-rounded-dots'></i>
                                </div>
                                <div class="card-content">
                                    <h5>Feedback Center</h5>
                                    <small>Review service, personnel, and system feedback</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- System Section -->
            <h3 class="section-title mt-4">System</h3>
            <div class="row">
                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <div class="dashboard-card card-secondary p-3 p-md-4" data-toggle="modal" data-target="#logoutModal" style="cursor:pointer;">
                        <div class="d-flex align-items-center">
                            <div class="card-icon-wrapper">
                                <i class='bx bx-log-out'></i>
                            </div>
                            <div class="card-content">
                                <h5>Logout</h5>
                                <small>Securely end your session</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel"><i class="bx bx-log-out-circle mr-2"></i>Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    Are you sure you want to log out?
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>