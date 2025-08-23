<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
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
    
    <style>
        :root {
            --header-height: 3rem;
            --nav-width: 68px;
            --first-color: rgb(47, 133, 225);
            --first-color-light: #AFA5D9;
            --white-color: #F7F6FB;
            --body-font: 'Nunito', sans-serif;
            --normal-font-size: 1rem;
        }

        body {
            margin: var(--header-height) 0 0 0;
            padding: 0;
            font-family: var(--body-font);
            background: linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)), url('images/background.png') no-repeat center center/cover;
            background-attachment: fixed;
        }

        .header {
            width: 100%;
            height: var(--header-height);
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            background-image: linear-gradient(to right, #0D92F4, #27548A);
            z-index: 100;
        }

        .header_toggle {
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .l-navbar {
            position: fixed;
            top: 0;
            left: -250px;
            width: 250px;
            height: 100vh;
            background-color: #0D92F4;
            padding: 1rem 0;
            transition: 0.5s;
            z-index: 100;
        }

        .nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav_link {
            display: flex;
            align-items: center;
            color: var(--white-color);
            padding: 12px 16px;
            margin: 5px 0;
            text-decoration: none !important;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav_link i {
            margin-right: 12px;
            font-size: 1.3rem;
            transition: transform 0.3s ease;
        }

        .nav_link:hover {
            background-color: #27548A;
            transform: scale(1.02);
            color: #fff;
        }

        .nav_link:hover i {
            transform: rotate(10deg) scale(1.2);
        }

        .nav h4 {
            text-align: center;
            color: white;
            font-size: 1.1rem;
            margin: 10px 0 20px;
            letter-spacing: 0.5px;
        }

        .show {
            left: 0;
        }

        .body-pd {
            padding-left: 250px;
        }

        .content-area {
            margin-top: var(--header-height);
            padding: 10px;
            transition: 0.5s;
        }

        #sidebar-logo {
            display: block;
            margin: 0 auto 10px auto;
            width: 120px;
            height: auto;
            max-width: 80%;
        }

        .card-header {
            background-image: linear-gradient(to right, #0D92F4, #27548A);
        }

        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .card:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .border-left-primary { border-left: 5px solid #007bff !important; }
        .border-left-success { border-left: 5px solid #28a745 !important; }
        .border-left-warning { border-left: 5px solid #ffc107 !important; }
        .border-left-danger  { border-left: 5px solid #dc3545 !important; }
        .border-left-info    { border-left: 5px solid #17a2b8 !important; }

        .me-3 { margin-right: 1rem; }

        .header_img {
            display: block;
            margin: 0 auto 10px;
            max-width: 80px;
            border-radius: 50%;
            transition: transform 0.4s ease-in-out;
        }

        .header_img:hover {
            transform: scale(1.2);
        }
    </style>

    <script>
        $(document).ready(function () {
            const toggle = $('#header-toggle');
            const nav = $('#nav-bar');
            const bodyPd = $('#body-pd');
            const contentArea = $('#content-area');

            toggle.click(() => {
                nav.toggleClass('show');
                bodyPd.toggleClass('body-pd');
            });

            contentArea.click(() => {
                if (nav.hasClass('show')) {
                    nav.removeClass('show');
                    bodyPd.removeClass('body-pd');
                }
            });
        });

        function loadContent(page) {
            $("#content-area").load(page);
        }

        function toggleDropdown(id) {
            $("#" + id).slideToggle("fast");
        }

        // Profile dropdown toggle
        $(document).ready(function(){
            $('#profileDropdownToggle').click(function(e){
                e.stopPropagation();
                $('#profileDropdownMenu').toggleClass('show');
            });

            // Close dropdown if click outside
            $(document).click(function(){
                $('#profileDropdownMenu').removeClass('show');
            });

            $('#profileDropdownMenu').click(function(e){
                e.stopPropagation(); // Prevent closing when clicking inside dropdown
            });
        });

        function showLogoutModal() {
            $('#logoutModal').modal('show');
        }
    </script>
</head>
<body id="body-pd">
    <!-- Header -->
    <header class="header" id="header">
        <div class="header_toggle"> <i class='bx bx-menu' id="header-toggle"></i> </div>

        <!-- Profile Dropdown -->
        <div class="dropdown position-relative">
            <i id="profileDropdownToggle" class='bx bx-user-circle text-primary' style="font-size: 40px; cursor:pointer;"></i>
            <div id="profileDropdownMenu" class="dropdown-menu dropdown-menu-right">
                <a href="#" class="dropdown-item" onclick="loadContent('view_profile.php')"><i class='bx bx-id-card mr-2'></i> View Profile</a>
                <a href="#" class="dropdown-item" onclick="loadContent('profile.php')"><i class='bx bx-edit-alt mr-2'></i> Edit Profile</a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item text-danger" onclick="showLogoutModal()"><i class='bx bx-log-out-circle mr-2'></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <div class="l-navbar" id="nav-bar">
        <img src="images/Unisan_logo.png" id= "sidebar-logo" alt="Sidebar Logo" class="header_img">
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
                <i class='bx bx-message-rounded-dots'></i> <span>Select Feedback</span> <i class='bx bx-chevron-down ml-auto'></i>
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
            <a href="#" class="nav_link" onclick="showLogoutModal()">
                <i class='bx bx-log-out'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Content Area -->
    <div class="content-area" id="content-area">
        <!-- Your default dashboard content here -->
        <div class="container mt-4">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class='bx bx-grid-alt'></i> Welcome, Admin!</h3>
                </div>
                <div class="card-body">
                    <p class="lead">
                        <?php if (isset($_SESSION['user_name'])): ?>
                            Hello, <strong><?php echo $_SESSION['user_name']; ?></strong>! You have administrative access.
                        <?php else: ?>
                            Hello, <strong>Admin</strong>! Welcome.
                        <?php endif; ?>
                    </p>
                    <p>Use the sidebar to navigate through administrative features:</p>
                    <!-- Add cards for dashboard shortcuts -->
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
                    <a href="logout.php" class="btn btn-danger">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
