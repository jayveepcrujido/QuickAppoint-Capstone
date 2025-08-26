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
                <a href="#" class="dropdown-item" onclick="loadContent('../view_profile.php')"><i class='bx bx-id-card mr-2'></i> View Profile</a>
                <a href="#" class="dropdown-item" onclick="loadContent('../profile.php')"><i class='bx bx-edit-alt mr-2'></i> Edit Profile</a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item text-danger" onclick="showLogoutModal()"><i class='bx bx-log-out-circle mr-2'></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <div class="l-navbar" id="nav-bar">
        <img src="../assets/images/Unisan_logo.png" id= "sidebar-logo" alt="Sidebar Logo" class="header_img">
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

    <div id="overlay" class="overlay"></div>

    <!-- Content Area -->
    <div class="content-area" id="content-area">
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

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="card h-100 shadow-sm border-dark p-3">
                            <a href="#" class="text-dark" style="text-decoration: none;" onclick="loadContent('admin_analytics.php')">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-grid-alt bx-lg text-dark me-3'></i>
                                <div>
                                <h5 class="mb-0">Dashboard</h5>
                                <small>Return to the main admin overview</small>
                                </div>
                            </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-primary p-3">
                            <a href="#" class="text-dark" style="text-decoration: none;" onclick="loadContent('admin_create_lgu_personnel.php')">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-user-plus bx-lg text-primary me-3'></i>
                                <div>
                                    <h5 class="mb-0">Create LGU Personnel</h5>
                                    <small>Add new users with LGU Personnel role</small>
                                </div>
                            </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-success p-3">
                            <a href="#" class="text-dark" style="text-decoration: none;" onclick="loadContent('admin_manage_departments.php')">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-building-house bx-lg text-success me-3'></i>
                                <div>
                                    <h5 class="mb-0">Manage Departments</h5>
                                    <small>Add, edit, or delete department records</small>
                                </div>
                            </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-warning p-3">
                            <a href="#" class="text-dark" style="text-decoration: none;" onclick="loadContent('admin_view_feedback.php')">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-message-rounded-dots bx-lg text-warning me-3'></i>
                                <div>
                                    <h5 class="mb-0">View Feedback</h5>
                                    <small>Review feedback submitted by residents</small>
                                </div>
                            </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-danger p-3">
                            <a href="#" class="text-dark" style="text-decoration: none;" onclick="loadContent('admin_view_appointments.php')">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-calendar-event bx-lg text-danger me-3'></i>
                                <div>
                                    <h5 class="mb-0">View Appointments</h5>
                                    <small>See all appointments and their statuses</small>
                                </div>
                            </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-info p-3">
                            <a href="#" class="text-dark" style="text-decoration: none;" onclick="loadContent('admin_manage_residents_accounts.php')">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-group bx-lg text-info me-3'></i>
                                <div>
                                    <h5 class="mb-0">Manage User Accounts</h5>
                                    <small>View and manage all resident user accounts</small>
                                </div>
                            </div>
                            </a>
                        </div>
                    </div>
                        <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-secondary p-3 hover-card" onclick="window.location.href='../logout.php'" style="cursor:pointer;">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-log-out bx-lg text-secondary me-3'></i>
                                <div>
                                    <h5 class="mb-0">Logout</h5>
                                    <small>You can just click here to securely logout</small>
                                </div>
                            </div>
                        </div>x
                    </div>
                </div> <!-- row -->
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

<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
