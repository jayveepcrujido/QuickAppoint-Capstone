<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}
include '../conn.php';

// Fetch departments with services
$stmt = $pdo->query("SELECT d.*, 
                            GROUP_CONCAT(s.service_name ORDER BY s.id SEPARATOR ', ') AS services
                     FROM departments d
                     LEFT JOIN department_services s ON d.id = s.department_id
                     GROUP BY d.id ORDER BY d.created_at DESC");
$departments = $stmt->fetchAll();

// Fetch all services and requirements per department
$serviceMap = [];
$stmt = $pdo->query("SELECT ds.id AS service_id, ds.department_id, ds.service_name, sr.requirement
                     FROM department_services ds
                     LEFT JOIN service_requirements sr ON ds.id = sr.service_id
                     ORDER BY ds.department_id, ds.id");

while ($row = $stmt->fetch()) {
    $deptId = $row['department_id'];
    $serviceId = $row['service_id'];

    if (!isset($serviceMap[$deptId][$serviceId])) {
        $serviceMap[$deptId][$serviceId] = [
            'name' => $row['service_name'],
            'requirements' => []
        ];
    }

    if ($row['requirement']) {
        $serviceMap[$deptId][$serviceId]['requirements'][] = $row['requirement'];
    }
}

// Return just the grid HTML
?>
<div class="row" id="departmentGrid">
    <?php foreach ($departments as $d): 
        $searchText = strtolower($d['name'] . ' ' . $d['acronym'] . ' ' . $d['description'] . ' ' . $d['services']);
        $services = array_filter(array_map('trim', explode(',', $d['services'])));
    ?>
    <div class="col-lg-4 col-md-6 dept-card-wrapper" data-search="<?= htmlspecialchars($searchText) ?>">
        <div class="dept-card" data-toggle="modal" data-target="#viewModal<?= $d['id'] ?>">
            <div class="dept-card-header">
                <h4 class="dept-acronym">
                    <i class='bx bx-building-house'></i>
                    <?= htmlspecialchars($d['acronym']) ?>
                </h4>
                <p class="dept-name"><?= htmlspecialchars($d['name']) ?></p>
            </div>
            <div class="dept-card-body">
                <?php if ($services): ?>
                    <div>
                        <?php foreach (array_slice($services, 0, 3) as $s): ?>
                            <span class="service-badge"><?= htmlspecialchars($s) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($services) > 3): ?>
                            <span class="service-badge">+<?= count($services) - 3 ?> more</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-services">
                        <i class='bx bx-info-circle'></i>
                        No services available
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals remain the same as in your original code -->
    <!-- Copy your entire modal code here for viewModal, editModal -->
    
    <?php endforeach; ?>
</div>

<!-- Also output stats for updating -->
<div style="display:none;">
    <span class="stat-number"><?= count($departments) ?></span>
    <span class="stat-number"><?= array_sum(array_map(function($d) use ($serviceMap) { return isset($serviceMap[$d['id']]) ? count($serviceMap[$d['id']]) : 0; }, $departments)) ?></span>
</div>