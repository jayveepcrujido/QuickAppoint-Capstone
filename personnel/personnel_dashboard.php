<?php
// Add session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 86400); // 24 hours
session_set_cookie_params(86400); // 24 hours

session_start();

// Regenerate session periodically to prevent fixation
if (!isset($_SESSION['last_regenerate'])) {
    $_SESSION['last_regenerate'] = time();
} elseif (time() - $_SESSION['last_regenerate'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regenerate'] = time();
}

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
    <link rel="stylesheet" href="../assets/css/personnel.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-color: #1e293b;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-top: -3rem;
        }

        /* Header Improvements */
        .header {
            background: linear-gradient(to right, #0D92F4, #27548A);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-top: -3rem;
        }

        .header_toggle i {
            font-size: 1.8rem;
            color: var(--light-bg);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .header_toggle i:hover {
            transform: scale(1.1);
        }

        .profile-trigger {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .profile-trigger:hover {
            transform: translateY(-2px);
        }

        .profile-trigger i {
            font-size: 2.5rem;
            color: var(--light-bg);
            transition: all 0.3s ease;
        }

        .profile-trigger:hover i {
            color: #0D92F4;
        }

        .profile-dropdown {
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Content Area */
        .content-area {
            padding: 1rem 1rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 768px) {
            .content-area {
                padding: 2.5rem 2rem;
            }
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(to right, #0D92F4, #27548A);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            color: white;
            box-shadow: var(--card-shadow-hover);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .welcome-card h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 576px) {
            .welcome-card {
                padding: 1.5rem 1rem;
            }
            .welcome-card h2 {
                font-size: 1.5rem;
            }
            .welcome-card p {
                font-size: 1rem;
            }
        }

        /* Feature Cards */
        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-color);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .icon-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: var(--danger-color);
        }

        .icon-primary {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: var(--primary-color);
        }

        .icon-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: var(--warning-color);
        }

        .icon-secondary {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            color: #64748b;
        }

        .icon-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: var(--secondary-color);
        }

        .feature-card h5 {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .feature-card small {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Stats Badge */
        .stats-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Responsive Grid */
        @media (max-width: 767px) {
            .feature-card {
                margin-bottom: 1rem;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .feature-col {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        /* Animation for cards on load */
        .feature-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .feature-card:nth-child(1) { animation-delay: 0.1s; }
        .feature-card:nth-child(2) { animation-delay: 0.2s; }
        .feature-card:nth-child(3) { animation-delay: 0.3s; }
        .feature-card:nth-child(4) { animation-delay: 0.4s; }
        .feature-card:nth-child(5) { animation-delay: 0.5s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Section Title */
        .section-title {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* Logout Modal Improvements */
        .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: none;
            padding: 1.5rem;
        }

        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .notification-trigger {
            cursor: pointer;
            position: relative;
            padding: 8px;
            transition: all 0.3s ease;
            }

        .notification-trigger:hover {
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 50%;
        }

        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 10px;
            height: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
    </style>

<script>
        // Global cleanup registry
        window.pageCleanupRegistry = {
            intervals: [],
            timeouts: [],
            eventHandlers: [],
            ajaxRequests: []
        };

        // Override setInterval to track all intervals
        const originalSetInterval = window.setInterval;
        window.setInterval = function(fn, delay) {
            const id = originalSetInterval(fn, delay);
            window.pageCleanupRegistry.intervals.push(id);
            return id;
        };

        // Override setTimeout to track all timeouts
        const originalSetTimeout = window.setTimeout;
        window.setTimeout = function(fn, delay) {
            const id = originalSetTimeout(fn, delay);
            window.pageCleanupRegistry.timeouts.push(id);
            return id;
        };

        // Track jQuery AJAX requests
        if (typeof jQuery !== 'undefined') {
            $(document).ajaxSend(function(event, jqXHR, settings) {
                window.pageCleanupRegistry.ajaxRequests.push(jqXHR);
            });
        }

        // Universal cleanup function
window.cleanupAllPages = function() {
    console.log('=== STARTING UNIVERSAL CLEANUP ===');
    
    // Clear all intervals
    console.log('Clearing', window.pageCleanupRegistry.intervals.length, 'intervals');
    window.pageCleanupRegistry.intervals.forEach(function(id) {
        clearInterval(id);
    });
    window.pageCleanupRegistry.intervals = [];
    
    // Clear all timeouts
    console.log('Clearing', window.pageCleanupRegistry.timeouts.length, 'timeouts');
    window.pageCleanupRegistry.timeouts.forEach(function(id) {
        clearTimeout(id);
    });
    window.pageCleanupRegistry.timeouts = [];
    
    // Abort all pending AJAX requests
    console.log('Aborting', window.pageCleanupRegistry.ajaxRequests.length, 'AJAX requests');
    window.pageCleanupRegistry.ajaxRequests.forEach(function(jqXHR) {
        if (jqXHR && jqXHR.abort) {
            try {
                jqXHR.abort();
            } catch(e) {
                console.warn('Error aborting AJAX request:', e);
            }
        }
    });
    window.pageCleanupRegistry.ajaxRequests = [];
    
    // Clean up specific page cleanup functions
    if (window.availableDatesCleanup && typeof window.availableDatesCleanup === 'function') {
        console.log('Running availableDatesCleanup...');
        try {
            window.availableDatesCleanup();
        } catch(e) {
            console.error('Error in availableDatesCleanup:', e);
        }
    }
    
    if (window.appointmentStatusCleanup && typeof window.appointmentStatusCleanup === 'function') {
        console.log('Running appointmentStatusCleanup...');
        try {
            window.appointmentStatusCleanup();
        } catch(e) {
            console.error('Error in appointmentStatusCleanup:', e);
        }
    }
    
    if (window.manageAppointmentsCleanup && typeof window.manageAppointmentsCleanup === 'function') {
        console.log('Running manageAppointmentsCleanup...');
        try {
            window.manageAppointmentsCleanup();
        } catch(e) {
            console.error('Error in manageAppointmentsCleanup:', e);
        }
    }
    
    // Clean up all document event handlers with namespaces
    // Only if jQuery is available
    if (typeof jQuery !== 'undefined' && jQuery) {
        const namespaces = ['availDates', 'appointmentStatus', 'manageAppt', 'analytics'];
        namespaces.forEach(function(ns) {
            console.log('Removing event handlers for namespace:', ns);
            $(document).off('.' + ns);
            $(document).off('.editBtn_' + ns);
            $(document).off('.deleteBtn_' + ns);
            $(document).off('.removeBtn_' + ns);
        });
        
        // Clean up any lingering modals - with safety check
        try {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            
            // Only try to hide modals if Bootstrap modal is available
            if (typeof $.fn.modal !== 'undefined') {
                $('.modal').modal('hide');
            } else {
                console.warn('⚠️ Bootstrap modal not available, using manual hide');
                $('.modal').hide();
                $('.modal').attr('aria-hidden', 'true');
            }
        } catch(e) {
            console.warn('Error cleaning up modals:', e);
        }
        
        // Remove inline styles that might have been added
        $('body').css('overflow', '');
        $('body').css('padding-right', '');
    } else {
        console.warn('jQuery not available during cleanup');
        // Fallback: use vanilla JavaScript
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        });
        
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function(backdrop) {
            backdrop.remove();
        });
        
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    
    console.log('=== CLEANUP COMPLETE ===');
};

        // Store the currently loaded page
        let currentPage = null;
        let isLoading = false;
        let loadAbortController = null;

        document.addEventListener("DOMContentLoaded", function () {
            const toggle = document.getElementById('header-toggle');
            const nav = document.getElementById('nav-bar');
            const overlay = document.getElementById('overlay');

            // Toggle sidebar
            if (toggle) {
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    nav.classList.toggle('show');
                    overlay.classList.toggle('show');
                });
            }

            // Close sidebar if clicking outside or on overlay
            if (overlay) {
                overlay.addEventListener('click', () => {
                    nav.classList.remove('show');
                    overlay.classList.remove('show');
                });
            }

            // Extra safety: clicking anywhere outside
            document.addEventListener('click', (e) => {
                if (nav && nav.classList.contains('show') && !nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });

            // Keep session alive with periodic pings
            const keepAliveInterval = setInterval(function() {
                $.ajax({
                    url: 'keep_alive.php',
                    method: 'GET',
                    cache: false,
                    error: function(xhr) {
                        if (xhr.status === 403) {
                            console.warn('Session expired, redirecting to login...');
                            clearInterval(keepAliveInterval);
                            window.location.href = '../login.php?timeout=1';
                        }
                    }
                });
            }, 300000); // Ping every 5 minutes
        });

        function loadContent(page) {
            console.log('\n>>> NAVIGATION REQUEST: ' + page + ' <<<');
            
            // Prevent loading if already loading
            if (isLoading) {
                console.warn('⚠️ Already loading content, please wait...');
                return false;
            }

            // Prevent reloading the same page
            if (currentPage === page) {
                console.log('ℹ️ Already on page:', page);
                return false;
            }

            console.log('✓ Navigation approved from', currentPage || 'homepage', 'to', page);
            isLoading = true;
            
            // Close sidebar on mobile after clicking
            const nav = document.getElementById('nav-bar');
            const overlay = document.getElementById('overlay');
            if (nav && nav.classList.contains('show')) {
                nav.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
            
            // *** CRITICAL: CLEANUP EVERYTHING ***
            console.log('🧹 Initiating comprehensive cleanup...');
            window.cleanupAllPages();
            
            // Add cache buster to prevent cached content
            const cacheBuster = '?_=' + new Date().getTime();
            
            // Clear content area immediately
            const contentArea = $("#content-area");
            contentArea.html(
                '<div class="text-center p-5">' +
                '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">' +
                '<span class="sr-only">Loading...</span>' +
                '</div>' +
                '<p class="mt-3 font-weight-bold">Loading ' + page + '...</p>' +
                '</div>'
            );
            
            // Force a small delay to ensure cleanup is complete
            setTimeout(function() {
                console.log('📥 Loading content from:', page + cacheBuster);
                
                contentArea.load(page + cacheBuster, function(response, status, xhr) {
                    isLoading = false;
                    
                    if (status === "error") {
                        console.error("❌ Error loading content:", xhr.status, xhr.statusText);
                        
                        // Check if it's a session error
                        if (xhr.status === 403 || response.includes('Unauthorized') || response.includes('Session expired')) {
                            contentArea.html(
                                '<div class="alert alert-danger m-4">' +
                                '<h4><i class="bx bx-error-circle"></i> Session Expired</h4>' +
                                '<p>Your session has expired. Please log in again.</p>' +
                                '<a href="../login.php" class="btn btn-primary">Go to Login</a>' +
                                '</div>'
                            );
                            setTimeout(function() {
                                window.location.href = '../login.php?timeout=1';
                            }, 2000);
                        } else if (xhr.status === 404) {
                            contentArea.html(
                                '<div class="alert alert-warning m-4">' +
                                '<h4><i class="bx bx-error"></i> Page Not Found</h4>' +
                                '<p>The requested page could not be found: <strong>' + page + '</strong></p>' +
                                '<button class="btn btn-primary" onclick="loadContent(\'personnel_analytics.php\')">Go to Dashboard</button>' +
                                '</div>'
                            );
                            currentPage = null;
                        } else {
                            contentArea.html(
                                '<div class="alert alert-danger m-4">' +
                                '<h4><i class="bx bx-error-circle"></i> Error Loading Content</h4>' +
                                '<p>Failed to load the requested page. Please try again.</p>' +
                                '<button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>' +
                                '</div>'
                            );
                            currentPage = null;
                        }
                    } else if (status === "success") {
                        console.log('✅ Content loaded successfully:', page);
                        currentPage = page;
                        
                        // Scroll to top of content area smoothly
                        $('html, body').animate({ scrollTop: 0 }, 300);
                        
                        // Give scripts time to initialize
                        setTimeout(function() {
                            console.log('⚙️ Content initialization phase complete for:', page);
                            
                            // Special handling for create_available_dates page
                            if (page.includes('create_available_dates.php')) {
                                console.log('📅 Verifying calendar initialization...');
                                
                                setTimeout(function() {
                                    if ($('#calendar').length > 0) {
                                        if ($('#calendar').children().length === 0) {
                                            console.warn('⚠️ Calendar empty, attempting manual init...');
                                            if (window.availableDatesModule && 
                                                typeof window.availableDatesModule.generateCalendar === 'function') {
                                                const now = new Date();
                                                window.availableDatesModule.generateCalendar(now.getMonth(), now.getFullYear());
                                                window.availableDatesModule.loadExistingDates();
                                                window.availableDatesModule.checkSubmitButton();
                                            }
                                        } else {
                                            console.log('✓ Calendar initialized with', $('#calendar').children().length, 'elements');
                                        }
                                    }
                                }, 300);
                            }
                        }, 200);
                    }
                });
            }, 150); // 150ms delay to ensure cleanup completes
            
            return false; // Prevent default link behavior
        }

        function toggleDropdown(id) {
            $("#" + id).slideToggle("fast");
        }

        function toggleProfileMenu() {
            const menu = document.getElementById("profileMenu");
            if (menu) {
                menu.style.display = (menu.style.display === "none" || menu.style.display === "") ? "block" : "none";
            }
        }

        // Close profile menu when clicking outside
        window.addEventListener('click', function(e) {
            const trigger = document.querySelector('.profile-trigger');
            const menu = document.getElementById("profileMenu");
            if (trigger && menu && !trigger.contains(e.target) && !menu.contains(e.target)) {
                menu.style.display = "none";
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            if (currentPage) {
                console.log('⏮️ Browser navigation detected');
                const pageToLoad = currentPage;
                currentPage = null; // Reset to allow reload
                loadContent(pageToLoad);
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            console.log('🚪 Page unloading, running final cleanup...');
            window.cleanupAllPages();
        });

        // Global error handler for debugging
        window.addEventListener('error', function(e) {
            console.error('💥 Global error:', e.message, 'at', e.filename + ':' + e.lineno);
        });

        // Expose debug function
        window.debugLoadContent = function() {
            console.log('=== DEBUG INFO ===');
            console.log('Current page:', currentPage);
            console.log('Is loading:', isLoading);
            console.log('Intervals tracked:', window.pageCleanupRegistry.intervals.length);
            console.log('Timeouts tracked:', window.pageCleanupRegistry.timeouts.length);
            console.log('AJAX requests tracked:', window.pageCleanupRegistry.ajaxRequests.length);
            console.log('==================');
        };
        function checkNewNotifications() {
            $.ajax({
                url: 'check_notifications.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.hasNew) {
                        $('#notificationBadge').show();
                    } else {
                        $('#notificationBadge').hide();
                    }
                },
                error: function() {
                    console.log('Error checking notifications');
                }
            });
        }

        // Check for notifications on page load
        $(document).ready(function() {
            checkNewNotifications();
            
            // Check every 30 seconds for new notifications
            setInterval(checkNewNotifications, 30000);
        });

    </script>

</head>
<body id="body-pd">
    <!-- Header -->
    <header class="header d-flex justify-content-between align-items-center" id="header">
        <div class="header_toggle"> 
            <i class='bx bx-menu' id="header-toggle"></i> 
        </div>

        <div class="d-flex align-items-center">
            <!-- Notification Icon -->
            <div class="position-relative d-inline-block mr-3" style="z-index: 1050;">
                <div class="notification-trigger" onclick="loadContent('personnel_notifications.php')" title="Notifications">
                    <i class='bx bx-bell' style="font-size: 24px; cursor: pointer; color: #ffffffff;"></i>
                    <span id="notificationBadge" class="notification-badge" style="display: none;"></span>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="position-relative d-inline-block" style="z-index: 1050;">
                <div class="profile-trigger" onclick="toggleProfileMenu()" title="My Profile">
                    <i class='bx bx-user-circle'></i>
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
        </div>
    </header>
    
    <!-- Sidebar -->
    <div class="l-navbar" id="nav-bar">
        <img src="../assets/images/Unisan_logo.png" id="sidebar-logo" alt="Sidebar Logo" class="header_img">
        <h4 style="text-align: center; color: white;">Personnel Menu</h4>
        <nav class="nav">
            <a href="javascript:void(0);" class="nav_link" onclick="return loadContent('personnel_analytics.php');">
                <i class='bx bx-home-alt'></i> <span>Dashboard</span>
            </a>
            <a href="javascript:void(0);" class="nav_link" onclick="return loadContent('personnel_manage_appointments.php');">
                <i class='bx bx-calendar'></i> <span>View Appointments</span>
            </a>
            <a href="javascript:void(0);" class="nav_link" onclick="return loadContent('personnel_view_appointments_status.php');">
                <i class='bx bx-calendar-check'></i> <span>Appointments Status</span>
            </a>
            <a href="javascript:void(0);" class="nav_link" onclick="return loadContent('create_available_dates.php');">
                <i class='bx bx-calendar-plus'></i> <span>Create Available Dates</span>
            </a>
            <a href="javascript:void(0);" class="nav_link" data-toggle="modal" data-target="#logoutModal">
                <i class='bx bx-log-out'></i> <span>Logout</span>
            </a>
        </nav>
    </div>

    <div id="overlay" class="overlay"></div>       

    <!-- Content Area -->
    <div class="content-area" id="content-area">
        <div class="container-fluid">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2><i class='bx bx-wave'></i> Welcome Back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                <p>You're logged in as LGU Personnel. Manage your appointments and availability from your dashboard.</p>
            </div>

            <!-- Section Title -->
            <h3 class="section-title">Quick Actions</h3>

    <!-- Feature Cards -->
    <div class="row">
        <!-- Dashboard Analytics -->
        <div class="col-lg-4 col-md-6 mb-4 feature-col">
            <div class="feature-card" onclick="loadContent('personnel_analytics.php');">
                <div class="feature-icon icon-success">
                    <i class='bx bx-line-chart'></i>
                </div>
                <h5>Dashboard Analytics</h5>
                <small>View comprehensive statistics and insights about appointments and bookings</small>
            </div>
        </div>

            <!-- View Appointments -->
            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('personnel_manage_appointments.php');">
                    <div class="feature-icon icon-danger">
                        <i class='bx bx-calendar-event'></i>
                    </div>
                    <h5>View Appointments</h5>
                    <small>Review and manage all scheduled appointments with residents</small>
                </div>
            </div>

            <!-- Appointments Status -->
            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('personnel_view_appointments_status.php');">
                    <div class="feature-icon icon-primary">
                        <i class='bx bx-calendar-check'></i>
                    </div>
                    <h5>Appointments Status</h5>
                    <small>Track the status of all appointments in real-time</small>
                </div>
            </div>

            <!-- Create Available Dates -->
            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('create_available_dates.php');">
                    <div class="feature-icon icon-success">
                        <i class='bx bx-calendar-plus'></i>
                    </div>
                    <h5>Create Available Dates</h5>
                    <small>Set your availability schedule for resident bookings</small>
                </div>
            </div>

            <!-- View Feedback -->
            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('personnel_view_feedbacks.php');">
                    <div class="feature-icon icon-warning">
                        <i class='bx bx-message-dots'></i>
                    </div>
                    <h5>View Feedback</h5>
                    <small>Read and respond to feedback from residents about services</small>
                </div>
            </div>

            <!-- Logout -->
            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" data-toggle="modal" data-target="#logoutModal">
                    <div class="feature-icon icon-secondary">
                        <i class='bx bx-log-out'></i>
                    </div>
                    <h5>Logout</h5>
                    <small>Securely sign out of your account</small>
                </div>
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
            <i class='bx bx-error-circle' style="font-size: 4rem; color: #ef4444;"></i>
            <p class="mt-3 mb-0">Are you sure you want to log out?</p>
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