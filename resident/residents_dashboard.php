<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident's Dashboard</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/resident.css">

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

        function toggleProfileMenu() {
            const menu = document.getElementById("profileMenu");
            menu.style.display = (menu.style.display === "none" || menu.style.display === "") ? "block" : "none";
        }

        // Optional: Close when clicking outside
        window.addEventListener('click', function(e) {
            const trigger = document.querySelector('[onclick="toggleProfileMenu()"]');
            const menu = document.getElementById("profileMenu");
            if (!trigger.contains(e.target) && !menu.contains(e.target)) {
            menu.style.display = "none";
            }
        });
        overlay.classList.toggle('show');
        function toggleDropdown(id) {
            $("#" + id).slideToggle("fast");
        }
    </script>
</head>
<body id="body-pd">
    <!-- Header -->
    <header class="header" id="header">
        <div class="header_toggle"> <i class='bx bx-menu' id="header-toggle"></i> </div>

        <!-- Avatar icon for profile -->
        <div class="position-relative d-inline-block" style="z-index: 1050;">
        <!-- Profile Icon Trigger -->
        <div onclick="toggleProfileMenu()" title="My Profile" style="cursor: pointer;">
            <i class='bx bx-user-circle text-primary' style="font-size: 40px;"></i>
        </div>

        <!-- Dropdown Menu -->
        <div id="profileMenu" class="profile-dropdown bg-white shadow border rounded position-absolute py-2"
            style="display: none; min-width: 200px; top: 100%; right: 0; z-index: 2000;">
            <a href="#" onclick="loadContent('view_profile.php')" class="dropdown-item px-3 py-2 d-flex align-items-center text-dark">
            <i class='bx bx-id-card mr-2'></i> View Profile
            </a>
            <a href="#" onclick="loadContent('profile.php')" class="dropdown-item px-3 py-2 d-flex align-items-center text-dark">
            <i class='bx bx-edit-alt mr-2'></i> Edit Profile
            </a>
            <div class="dropdown-divider my-1"></div>
                <a href="#" class="dropdown-item px-3 py-2 d-flex align-items-center text-danger" data-toggle="modal" data-target="#logoutModal">
                    <i class='bx bx-log-out-circle mr-2'></i> Logout
                </a>
        </div>
        </div>
    </header>

    <!-- Sidebar -->
    <div class="l-navbar" id="nav-bar">
        <img src="../assets/images/Unisan_logo.png" id= "sidebar-logo" alt="Sidebar Logo" class="header_img">
        <h4 style="text-align: center; color: white;">Residents Menu</h4>
        <nav class="nav">
            <a href="javascript:void(0);" class="nav_link" onclick="toggleDropdown('appointmentDropdown')">
                <i class='bx bx-calendar'></i> <span>Appointments</span> <i class='bx bx-chevron-down ml-auto'></i>
            </a>
            <div id="appointmentDropdown" class="dropdown-submenu" style="display:none;">
                <a href="#" class="nav_link sub_link" onclick="loadContent('residents_pending_appointments.php')">Pending Appointments</a>
                <a href="#" class="nav_link sub_link" onclick="loadContent('residents_completed_appointments.php')">Completed Appointments</a>
            </div>
            <a href="#" class="nav_link" onclick="loadContent('residents_view_departments.php')">
                <i class='bx bx-user'></i> <span>View Departments</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('residents_select_form.php')">
                <i class='bx bx-message-square'></i> <span>Feedback</span>
            </a>

            <a href="#"  data-toggle="modal" data-target="#logoutModal" class="nav_link">
                <i class='bx bx-log-out'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <div id="overlay" class="overlay"></div>

    <!-- Content Area -->
<div class="content-area" id="content-area">
    <div class="container mt-4">
        <div class="card shadow-lg border-0 rounded-lg">
            <div class="card-header bg-info text-white">
                <h3 class="mb-0"><i class='bx bx-home-alt'></i> Welcome to Resident Dashboard</h3>
            </div>
            <div class="card-body">
                <p class="lead">
                    <?php if (isset($_SESSION['user_name'])): ?>
                        Hello, <strong><?php echo $_SESSION['user_name']; ?></strong>! Welcome to your personal dashboard.
                    <?php else: ?>
                        Hello, <strong>Resident</strong>! Welcome to your personal dashboard.
                    <?php endif; ?>
                </p>
                <p>Use the menu on the left or the options below to manage your appointments and provide feedback:</p>

                <div class="row">
                    <!-- View Appointments -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-primary p-3 hover-card" onclick="loadContent('resident_view_appointments.php')" style="cursor: pointer;">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-calendar bx-lg text-primary me-3'></i>
                                <div>
                                    <h5 class="mb-0">View Appointments</h5>
                                    <small>Check status and details of past or upcoming appointments</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View Departments -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-success p-3 hover-card" onclick="loadContent('view_departments.php')" style="cursor: pointer;">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-building-house bx-lg text-success me-3'></i>
                                <div>
                                    <h5 class="mb-0">View Departments</h5>
                                    <small>Explore departments and request appointments</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Feedback -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-warning p-3 hover-card" onclick="loadContent('submit_feedback.php')" style="cursor: pointer;">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-message-square-dots bx-lg text-warning me-3'></i>
                                <div>
                                    <h5 class="mb-0">Resident Feedback</h5>
                                    <small>Share your experience with past appointments</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logout -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-left-danger p-3 hover-card" onclick="window.location.href='../logout.php'" style="cursor:pointer;">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-log-out bx-lg text-danger me-3'></i>
                                <div>
                                    <h5 class="mb-0">Logout</h5>
                                    <small>Click here to safely logout of your account</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- row -->
            </div>
        </div>
    </div>
</div>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
