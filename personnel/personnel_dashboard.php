<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: ../login.php");
    exit();
}

// Get user name from session (set during ../login.php)
$user_name = $_SESSION['user_name'] ?? 'LGU Personnel';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Personnel Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/personnel.css">
    

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

        function loadContent(page) {
            $("#content-area").load(page, function(response, status, xhr) {
                if (status === "success") {
                    if (page === 'create_available_dates.php') {
                        generateCalendar(currentMonth, currentYear);
                    }
                } else {
                    console.error("Error loading content:", xhr.statusText);
                }
            });
        }

        function toggleProfileMenu() {
            const menu = document.getElementById("profileMenu");
            menu.style.display = (menu.style.display === "none" || menu.style.display === "") ? "block" : "none";
        }

        window.addEventListener('click', function(e) {
            const trigger = document.querySelector('[onclick="toggleProfileMenu()"]');
            const menu = document.getElementById("profileMenu");
            if (!trigger.contains(e.target) && !menu.contains(e.target)) {
                menu.style.display = "none";
            }
        });
    </script>
</head>
<body id="body-pd">
    <!-- Header -->
    <header class="header d-flex justify-content-between align-items-center px-3" id="header">
        <div class="header_toggle"> 
            <i class='bx bx-menu' id="header-toggle"></i> 
        </div>

        <!-- Profile Dropdown -->
        <div class="position-relative d-inline-block" style="z-index: 1050;">
            <div onclick="toggleProfileMenu()" title="My Profile" style="cursor: pointer;">
                <i class='bx bx-user-circle text-primary' style="font-size: 40px;"></i>
            </div>
            <div id="profileMenu" class="profile-dropdown bg-white shadow border rounded position-absolute py-2"
                style="display: none; min-width: 200px; top: 100%; right: 0; z-index: 2000;">
                <a href="#" onclick="loadContent('../view_profile.php')" class="dropdown-item px-3 py-2 d-flex align-items-center text-dark">
                    <i class='bx bx-id-card mr-2'></i> View Profile
                </a>
                <a href="#" onclick="loadContent('../profile.php')" class="dropdown-item px-3 py-2 d-flex align-items-center text-dark">
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
        <img src="../assets/images/Unisan_logo.png" id="sidebar-logo" alt="Sidebar Logo" class="header_img">
        <h4 style="text-align: center; color: white;">Personnel Menu</h4>
        <nav class="nav">
            <a href="#" class="nav_link" onclick="loadContent('personnel_analytics.php')">
                <i class='bx bx-home-alt'></i> <span>Dashboard</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('personnel_manage_appointments.php')">
                <i class='bx bx-calendar'></i> <span>View Appointments</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('personnel_view_appointments_status.php')">
                <i class='bx bx-calendar-check'></i> <span>Appointments Status</span>
            </a>
            <a href="#" class="nav_link" onclick="loadContent('create_available_dates.php')">
                <i class='bx bx-calendar-plus'></i> <span>Create Available Dates</span>
            </a>
            <!-- <a href="#" class="nav_link" onclick="loadContent('personnel_view_feedbacks.php')">
                <i class='bx bx-message-dots'></i> <span>View Feedbacks</span>
            </a> -->
            <a href="#" data-toggle="modal" data-target="#logoutModal" class="nav_link">
                <i class='bx bx-log-out'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <div id="overlay" class="overlay"></div>       

    <!-- Content Area -->
    <div class="content-area" id="content-area">
        <div class="container mt-4">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0"><i class='bx bx-home-alt'></i> Welcome to LGU Personnel Dashboard</h3>
                </div>
                <div class="card-body">
                    <p class="lead">
                        Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong>! You are logged in as LGU Personnel.
                    </p>
                    <p>Use the menu on the left to manage your tasks effectively:</p>

                    <div class="row">
                        <!-- View Appointments -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 shadow-sm border-left-danger p-3 hover-card">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-calendar-event bx-lg text-danger me-3'></i>
                                    <div>
                                        <h5 class="mb-0">View Appointments</h5>
                                        <small>Review and manage scheduled appointments</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Create Available Dates -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 shadow-sm border-left-primary p-3 hover-card">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-calendar-plus bx-lg text-primary me-3'></i>
                                    <div>
                                        <h5 class="mb-0">Create Available Dates</h5>
                                        <small>Set your availability for resident bookings</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- View Feedback -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 shadow-sm border-left-warning p-3 hover-card">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-message-dots bx-lg text-warning me-3'></i>
                                    <div>
                                        <h5 class="mb-0">View Feedback</h5>
                                        <small>Read feedback from residents about completed appointments</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Logout -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 shadow-sm border-left-secondary p-3 hover-card" onclick="window.location.href='../logout.php'" style="cursor:pointer;">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-log-out bx-lg text-secondary me-3'></i>
                                    <div>
                                        <h5 class="mb-0">Logout</h5>
                                        <small>Click here to securely logout</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- row -->
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
