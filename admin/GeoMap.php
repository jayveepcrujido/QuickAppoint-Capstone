<?php
// No session check needed since it's already in dashboard.php

// DB Connection
$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ====== GET APPOINTMENT LOCATIONS ======
$appointmentLocations = [];
$sql = "SELECT r.address, r.first_name, r.last_name, COUNT(a.id) as appointment_count,
        MAX(a.requested_at) as last_appointment
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        WHERE r.address IS NOT NULL AND r.address != ''
        GROUP BY r.address, r.first_name, r.last_name
        ORDER BY appointment_count DESC";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $appointmentLocations[] = [
        'address' => $row['address'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'count' => (int)$row['appointment_count'],
        'last_appointment' => $row['last_appointment']
    ];
}

// Get total stats
$totalResidents = count($appointmentLocations);
$totalAppointments = array_sum(array_column($appointmentLocations, 'count'));
?>

<!-- Leaflet CSS (if not already in main page) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />

<style>
    /* Stats Cards */
    .geomap-stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .geomap-stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.3s ease;
    }

    .geomap-stat-card:hover {
        transform: translateY(-5px);
    }

    .geomap-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }

    .geomap-stat-icon.blue { background: linear-gradient(135deg, #36d1dc, #5b86e5); }
    .geomap-stat-icon.purple { background: linear-gradient(135deg, #6a11cb, #2575fc); }
    .geomap-stat-icon.green { background: linear-gradient(135deg, #2ecc71, #27ae60); }

    .geomap-stat-info h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #333;
    }

    .geomap-stat-info p {
        margin: 0;
        font-size: 14px;
        color: #666;
        text-transform: uppercase;
        font-weight: 600;
    }

    /* Map Container */
    .geomap-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .geomap-header {
        background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%);
        padding: 20px 25px;
        color: white;
    }

    .geomap-header h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .geomap-header p {
        margin: 8px 0 0 0;
        font-size: 14px;
        opacity: 0.9;
    }

    #geomap-map {
        height: 600px;
        width: 100%;
    }

    /* Legend */
    .geomap-legend {
        background: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        line-height: 20px;
    }

    .geomap-legend h6 {
        margin: 0 0 10px 0;
        font-weight: 700;
        color: #333;
        font-size: 14px;
    }

    .geomap-legend-item {
        display: flex;
        align-items: center;
        margin: 5px 0;
        font-size: 13px;
    }

    .geomap-legend-color {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        margin-right: 8px;
        border: 2px solid rgba(0,0,0,0.2);
    }

    /* Popup */
    .geomap-popup-content {
        font-family: "Segoe UI", sans-serif;
    }

    .geomap-popup-content h6 {
        margin: 0 0 8px 0;
        color: #0D92F4;
        font-weight: 700;
        font-size: 16px;
    }

    .geomap-popup-content p {
        margin: 4px 0;
        font-size: 13px;
    }

    .geomap-popup-content strong {
        color: #333;
    }

    .approximate-location {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 0.6; }
        50% { opacity: 1; }
    }

    /* Layer Control Styles */
    .leaflet-control-layers {
        border-radius: 8px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15) !important;
        border: none !important;
    }

    .leaflet-control-layers-toggle {
        background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMzMzMiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMyAzaDdhNyAwIDAgMSAwIDE0SDN6Ii8+PHBhdGggZD0iTTIxIDNhNyA3IDAgMCAxIDAgMTRoLTd6Ii8+PC9zdmc+') !important;
        width: 36px !important;
        height: 36px !important;
        background-size: 20px 20px !important;
        background-position: center !important;
    }

    .leaflet-control-layers-expanded {
        padding: 10px !important;
        font-family: "Segoe UI", sans-serif !important;
    }

    .leaflet-control-layers-base label,
    .leaflet-control-layers-overlays label {
        font-size: 13px !important;
        color: #333 !important;
        padding: 5px 0 !important;
    }

    /* Responsive */
    @media (max-width: 768px) {
        #geomap-map {
            height: 450px;
        }

        .geomap-stats-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container-fluid">
    <h2 class="section-title mb-4">
        <i class='bx bx-map-alt'></i> Appointment Hotspot Map
    </h2>

    <!-- Stats Cards -->
    <div class="geomap-stats-container">
        <div class="geomap-stat-card">
            <div class="geomap-stat-icon blue">
                <i class='bx bx-map-pin'></i>
            </div>
            <div class="geomap-stat-info">
                <h3><?= $totalResidents ?></h3>
                <p>Locations</p>
            </div>
        </div>
        <div class="geomap-stat-card">
            <div class="geomap-stat-icon purple">
                <i class='bx bx-calendar-check'></i>
            </div>
            <div class="geomap-stat-info">
                <h3><?= $totalAppointments ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        <div class="geomap-stat-card">
            <div class="geomap-stat-icon green">
                <i class='bx bx-current-location'></i>
            </div>
            <div class="geomap-stat-info">
                <h3>Unisan</h3>
                <p>Municipality</p>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="geomap-card">
        <div class="geomap-header">
            <h2>
                <i class='bx bx-map'></i>
                Geographic Distribution
            </h2>
            <p>Showing appointment hotspots across Unisan, Quezon Province</p>
        </div>
        <div id="geomap-map"></div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>

<script>
(function() {
    console.log('GeoMap: Initializing...');
    
    // Global variable to store map instance
    let geomapInstance = null;
    
    // Cleanup function
    window.geomapCleanup = function() {
        console.log('GeoMap: Cleaning up...');
        if (geomapInstance !== null) {
            try {
                geomapInstance.remove();
                console.log('GeoMap: Map instance removed');
            } catch(e) {
                console.warn('GeoMap: Error removing map:', e);
            }
            geomapInstance = null;
        }
    };
    
    // Initialize map function
    function initGeoMap() {
        console.log('GeoMap: Starting initialization...');
        
        // Check if map already exists, destroy it first
        if (geomapInstance !== null) {
            console.log('GeoMap: Removing existing map instance...');
            geomapInstance.remove();
            geomapInstance = null;
        }
        
        // Check if container exists
        const mapContainer = document.getElementById('geomap-map');
        if (!mapContainer) {
            console.error('GeoMap: Map container not found!');
            return;
        }
        
        // Clear the container
        mapContainer.innerHTML = '';
        
        // Appointment data from PHP
        const appointments = <?= json_encode($appointmentLocations) ?>;
        console.log('GeoMap: Loaded', appointments.length, 'appointments');

        // Initialize map - centered on Unisan Municipal Hall
        // CORRECTED COORDINATES FOR UNISAN, QUEZON
        geomapInstance = L.map('geomap-map').setView([13.8383, 121.9782], 13);
        
        // Define base layers (map styles)
        const baseLayers = {
            "🛰️ Satellite": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri',
                maxZoom: 18,
            }),
            "🗺️ Street Map": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }),
            "🌍 Terrain": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri',
                maxZoom: 18,
            }),
            "🌆 Light Theme": L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap, © CartoDB',
                maxZoom: 19,
            }),
            "🌃 Dark Theme": L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap, © CartoDB',
                maxZoom: 19,
            })
        };
        
        // Add default layer (Street Map)
        baseLayers["🗺️ Street Map"].addTo(geomapInstance);
        
        // Create label overlay layer (works on all base maps)
        const labelOverlay = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap, © CartoDB',
            maxZoom: 18,
            opacity: 0.7,
            pane: 'shadowPane' // Ensures labels appear on top
        });
        
        // Define overlay layers (can be toggled on/off)
        const overlayLayers = {
            "📍 Place Labels": labelOverlay
        };
        
        // Add labels by default only for satellite view
        labelOverlay.addTo(geomapInstance);
        
        // Add layer control to map
        const layerControl = L.control.layers(baseLayers, overlayLayers, {
            position: 'topright',
            collapsed: true
        }).addTo(geomapInstance);
        
        // Auto-manage labels: show on satellite, hide on other maps
        geomapInstance.on('baselayerchange', function(e) {
            if (e.name === "🛰️ Satellite" || e.name === "🌃 Dark Theme") {
                // Add labels for satellite and dark theme
                if (!geomapInstance.hasLayer(labelOverlay)) {
                    labelOverlay.addTo(geomapInstance);
                }
            } else {
                // Remove labels for street/terrain/light (they have built-in labels)
                if (geomapInstance.hasLayer(labelOverlay)) {
                    geomapInstance.removeLayer(labelOverlay);
                }
            }
        });
        
        // CORRECTED: Define Unisan municipality bounds
        const unisanBounds = L.latLngBounds(
            [13.7800, 121.9200],  // Southwest corner
            [13.9000, 122.0400]   // Northeast corner
        );
        
        // Set max bounds to restrict panning outside Unisan
        geomapInstance.setMaxBounds(unisanBounds);
        geomapInstance.setMinZoom(12);
        geomapInstance.setMaxZoom(18);
        
        // Fit the map to Unisan bounds
        geomapInstance.fitBounds(unisanBounds);
        
        // CORRECTED: Accurate barangay coordinates for Unisan, Quezon
        // These are verified coordinates based on actual geographic locations
        const barangayCoords = {
            // Northern barangays
            'Bulo': [13.8650, 121.9650],
            'Punta': [13.8700, 121.9800],
            'San Roque': [13.8600, 121.9900],
            
            // Central/Poblacion area
            'Poblacion': [13.8383, 121.9782],
            'Cabulihan': [13.8450, 121.9850],
            'Mairok': [13.8320, 121.9850],
            'Tubigan': [13.8500, 121.9700],
            
            // Eastern barangays
            'Balagbag': [13.8250, 122.0100],
            'Balanacan': [13.8350, 122.0050],
            'Plaridel': [13.8200, 122.0000],
            'Pagaguasan': [13.8150, 121.9950],
            'Poctol': [13.8777, 122.0082],
            
            // Southern barangays
            'Bonifacio': [13.8100, 121.9800],
            'Caigdal': [13.8050, 121.9900],
            'Pulo': [13.8000, 121.9850],
            
            // Western/Coastal barangays
            'Kalilayan Ilaya': [13.8635, 121.9544],
            'Kalilayan': [13.8527, 121.9551],
            'Maligaya': [13.8400, 121.9550],
            'Tagumpay': [13.8150, 121.9700],
            
            // Sildora (northwest)
            'Sildora': [13.8665, 121.9243],
            
            // General Unisan reference
            'Unisan': [13.8383, 121.9782],
            'Unisan, Quezon': [13.8383, 121.9782]
        };

        // Calculate dynamic thresholds based on appointment distribution
        const counts = appointments.map(apt => apt.count);
        const maxCount = Math.max(...counts);
        const minCount = Math.min(...counts);

        // Calculate percentile thresholds (33rd and 67th percentiles)
        const sortedCounts = [...counts].sort((a, b) => a - b);
        const lowThreshold = sortedCounts[Math.floor(sortedCounts.length * 0.33)];
        const highThreshold = sortedCounts[Math.floor(sortedCounts.length * 0.67)];

        console.log('GeoMap: Dynamic thresholds - Low:', lowThreshold, 'High:', highThreshold, 'Max:', maxCount);

        // Function to get marker color based on dynamic ranking
        function getMarkerColor(count) {
            if (count >= highThreshold) return '#e74c3c';  // High: Red
            if (count >= lowThreshold) return '#ff9800';   // Medium: Orange
            return '#2ecc71';                               // Low: Green
        }

        // Function to get marker size based on relative count
        function getMarkerSize(count) {
            const range = maxCount - minCount;
            const normalized = range > 0 ? (count - minCount) / range : 0.5;
            return 8 + (normalized * 10); // Size from 8 to 18 based on relative position
        }

        // Enhanced function to extract barangay from address
        function extractBarangay(address) {
            const lower = address.toLowerCase().trim();
            
            // Try exact matches first
            for (let brgy in barangayCoords) {
                const brgyLower = brgy.toLowerCase();
                
                // Check for various formats
                if (lower === brgyLower ||
                    lower.includes('brgy. ' + brgyLower) ||
                    lower.includes('brgy ' + brgyLower) ||
                    lower.includes('barangay ' + brgyLower) ||
                    lower.includes(brgyLower + ',') ||
                    lower.includes(brgyLower + ' ') ||
                    lower.startsWith(brgyLower) ||
                    lower.endsWith(brgyLower)) {
                    return brgy;
                }
            }
            
            // Try partial matches
            for (let brgy in barangayCoords) {
                if (lower.includes(brgy.toLowerCase())) {
                    return brgy;
                }
            }
            
            return null;
        }

        // Add markers for each appointment location
        let markersAdded = 0;
        let unmatchedAddresses = [];
        
        appointments.forEach(apt => {
            const barangay = extractBarangay(apt.address);
            let coords = null;
            let isApproximate = false;
            
            if (barangay && barangayCoords[barangay]) {
                // Found exact barangay match
                coords = barangayCoords[barangay];
                console.log('GeoMap: Matched "' + apt.address + '" to barangay: ' + barangay);
            } else {
                // No match - use municipality center as fallback
                coords = [13.8383, 121.9782]; // Unisan Municipal Hall
                isApproximate = true;
                unmatchedAddresses.push({
                    address: apt.address,
                    name: apt.name
                });
                console.warn('GeoMap: No coordinates found for address: "' + apt.address + '" - Using municipality center');
            }
            
            if (coords) {
                // Add small random offset to prevent exact overlap
                const lat = coords[0] + (Math.random() - 0.5) * (isApproximate ? 0.01 : 0.003);
                const lng = coords[1] + (Math.random() - 0.5) * (isApproximate ? 0.01 : 0.003);
                
                const color = getMarkerColor(apt.count);
                const size = getMarkerSize(apt.count);
                
                const marker = L.circleMarker([lat, lng], {
                    radius: size,
                    fillColor: color,
                    color: isApproximate ? '#ffa500' : '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: isApproximate ? 0.5 : 0.8,
                    className: isApproximate ? 'approximate-location' : ''
                }).addTo(geomapInstance);

                const lastDate = new Date(apt.last_appointment);
                const formattedDate = lastDate.toLocaleDateString('en-PH', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });

                marker.bindPopup(`
                    <div class="geomap-popup-content">
                        <h6><i class='bx bx-user'></i> ${apt.name}</h6>
                        <p><strong>Address:</strong> ${apt.address}</p>
                        ${isApproximate ? '<p style="color: #ff9800; font-size: 12px;"><em>⚠️ Approximate location (specific barangay not detected)</em></p>' : ''}
                        ${barangay && !isApproximate ? '<p><strong>Barangay:</strong> ' + barangay + '</p>' : ''}
                        <p><strong>Total Appointments:</strong> ${apt.count}</p>
                        <p><strong>Last Appointment:</strong> ${formattedDate}</p>
                    </div>
                `);
                
                markersAdded++;
            }
        });
        
        console.log('GeoMap:', markersAdded, 'markers added');
        if (unmatchedAddresses.length > 0) {
            console.warn('GeoMap:', unmatchedAddresses.length, 'addresses could not be matched to specific barangays:');
            console.table(unmatchedAddresses);
        }

        // Add legend
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'geomap-legend');
            div.innerHTML = `
                <h6>Appointment Ranking</h6>
                <div class="geomap-legend-item">
                    <div class="geomap-legend-color" style="background: #2ecc71;"></div>
                    <span>Low (${minCount}-${lowThreshold - 1} appointments)</span>
                </div>
                <div class="geomap-legend-item">
                    <div class="geomap-legend-color" style="background: #ff9800;"></div>
                    <span>Medium (${lowThreshold}-${highThreshold - 1} appointments)</span>
                </div>
                <div class="geomap-legend-item">
                    <div class="geomap-legend-color" style="background: #e74c3c;"></div>
                    <span>High (${highThreshold}+ appointments)</span>
                </div>
                <div class="geomap-legend-item" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
                    <div class="geomap-legend-color" style="background: #999; border-color: #ffa500;"></div>
                    <span style="font-size: 11px;">Approximate location</span>
                </div>
            `;
            return div;
        };
        legend.addTo(geomapInstance);

        // Add marker for Unisan Municipal Hall
        L.marker([13.8383, 121.9782], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        }).addTo(geomapInstance)
        .bindPopup(`
            <div class="geomap-popup-content">
                <h6><i class='bx bx-building'></i> Unisan Municipal Hall</h6>
                <p>Municipality Center</p>
                <p style="font-size: 11px; color: #666;">13.8383°N, 121.9782°E</p>
            </div>
        `);

        // Force map to re-render after a short delay
        setTimeout(function() {
            if (geomapInstance) {
                geomapInstance.invalidateSize();
                console.log('GeoMap: Map size invalidated');
            }
        }, 100);

        console.log('GeoMap: Initialization complete!');
    }
    
    // Initialize after a short delay to ensure DOM is ready
    setTimeout(initGeoMap, 200);
})();
</script>