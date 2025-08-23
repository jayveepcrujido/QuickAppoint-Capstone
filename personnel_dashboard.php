<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: login.php");
    exit();
}
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
    <style>
        :root {
            --header-height: 3rem;
            --nav-width: 68px;
            --first-color: #4723D9;
            --first-color-light: #AFA5D9;
            --white-color: #f7f6fb;
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
            color: #27548A;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .header_img img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
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

        .nav h4 {
            margin-top: 20px;
            text-align: center;
            color: #1a3b96;
        }

        .nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav_link {
            color: var(--white-color);
            padding: 1rem 1.5rem;
            text-decoration: none !important;
        }

        .nav_link:hover {
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 5px;
            text-decoration: none !important;
        }

        .show {
            left: 0;
        }

        .body-pd {
            padding-left: 250px;
        }

        .content-area {
            margin-top: var(--header-height);
            padding: 20px;
            transition: 0.5s;
        }
        .card{
         transition: transform 0.2s ease, box-shadow 0.2s ease;
         box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease; /* smooth effect */
        }
        .card-header{
            background-image: linear-gradient(to right, #0D92F4, #27548A);
        }
        #sidebar-logo{
            display:block;
            margin:0 auto 10px auto;
            width:120px; height:auto;
            max-width:80%; 
        }
        
        .header img {
        object-fit: cover;
        border: 2px solid #fff;
        transition: transform 0.2s ease;
        }
        .header img:hover {
            transform: scale(1.05);
        }
        .l-navbar .nav_link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            margin: 5px 0;
            color: #ecf0f1;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .l-navbar .nav_link i {
            margin-right: 12px;
            font-size: 1.3rem;
            transition: transform 0.3s ease;
        }

        .l-navbar .nav_link:hover {
            background-color: #27548A;
            transform: scale(1.02);
            color: #fff;
        }

        .l-navbar .nav_link:hover i {
            transform: rotate(10deg) scale(1.2);
        }

        .l-navbar h4 {
            margin-top: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            text-align: center;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }

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
        document.addEventListener("DOMContentLoaded", function() {
            const toggle = document.getElementById('header-toggle');
            const nav = document.getElementById('nav-bar');
            const bodyPd = document.getElementById('body-pd');
            const contentArea = document.getElementById('content-area');

            toggle.addEventListener('click', () => {
                nav.classList.toggle('show');
                bodyPd.classList.toggle('body-pd');
            });

            contentArea.addEventListener('click', () => {
                if (nav.classList.contains('show')) { 
                    nav.classList.remove('show');
                    bodyPd.classList.remove('body-pd');
                }
            });
        });
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

        // Optional: Close when clicking outside
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

    <!-- Avatar icon for profile -->
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
            </a>
        </div>
        </div>
</header>

    <!-- Sidebar -->
    <div class="l-navbar" id="nav-bar">
        <img src="images/Unisan_logo.png" id= "sidebar-logo" alt="Sidebar Logo" class="header_img">
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

            <a href="#"  data-toggle="modal" data-target="#logoutModal" class="nav_link">
                <i class='bx bx-log-out'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Content Area -->
    <div class="content-area" id="content-area">
    <div class="container mt-4">
        <div class="card shadow-lg border-0 rounded-lg">
            <div class="card-header bg-success text-white">
                <h3 class="mb-0"><i class='bx bx-home-alt'></i> Welcome to LGU Personnel Dashboard</h3>
            </div>
            <div class="card-body">
                <p class="lead">
                    <?php if (isset($_SESSION['user_name'])): ?>
                        Welcome, <strong><?php echo $_SESSION['user_name']; ?></strong>! You are logged in as LGU Personnel.
                    <?php else: ?>
                        Welcome, <strong>LGU Personnel</strong>!
                    <?php endif; ?>
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
                        <div class="card h-100 shadow-sm border-left-secondary p-3 hover-card" onclick="window.location.href='logout.php'" style="cursor:pointer;">
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
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
