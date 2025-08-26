<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Departments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="p-4">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h3><i class='bx bx-buildings'></i> Manage Departments</h3>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addModal">
            <i class='bx bx-plus'></i> Add Department
        </button>
    </div>

    <div class="input-group mb-4">
        <input type="text" class="form-control" id="searchInput" placeholder="Search department...">
        <div class="input-group-append">
            <button class="btn btn-outline-secondary" id="clearFilters">Clear Filters</button>
        </div>
    </div>

    <div class="row">
        <?php foreach ($departments as $d): 
            $searchText = strtolower($d['name'] . ' ' . $d['acronym'] . ' ' . $d['description'] . ' ' . $d['services']);
            $services = array_filter(array_map('trim', explode(',', $d['services'])));
        ?>
        <div class="col-md-4 mb-4 dept-card" data-search="<?= htmlspecialchars($searchText) ?>">
            <div class="card h-100 shadow-sm border-0" data-toggle="modal" data-target="#viewModal<?= $d['id'] ?>">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class='bx bx-building-house'></i>
                        <?= htmlspecialchars($d['acronym'] ? $d['acronym'] . ' - ' : '') ?><?= htmlspecialchars($d['name']) ?>
                    </h5>
                    <?php foreach ($services as $svc): ?>
                        <span class="badge bg-info text-dark me-1 mb-1"><?= htmlspecialchars($svc) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- View Modal -->
        <div class="modal fade" id="viewModal<?= $d['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?= htmlspecialchars($d['acronym'] ? $d['acronym'] . ' - ' : '') ?><?= htmlspecialchars($d['name']) ?>
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Acronym:</strong> <?= htmlspecialchars($d['acronym']) ?></p>
                        <p><strong>Description:</strong> <?= htmlspecialchars($d['description']) ?></p>
                        <p><strong>Services & Requirements:</strong></p>
                        <ul class="list-unstyled">
                            <?php if (isset($serviceMap[$d['id']])): ?>
                                <?php foreach ($serviceMap[$d['id']] as $svc): ?>
                                    <li class="mb-2">
                                        <strong class="text-primary">🔹 <?= htmlspecialchars($svc['name']) ?></strong>
                                        <?php if (!empty($svc['requirements'])): ?>
                                            <ul class="ml-4 mt-1 text-muted">
                                                <?php foreach ($svc['requirements'] as $req): ?>
                                                    <li>📌 <?= htmlspecialchars($req) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <div class="ml-4 text-muted"><em>No requirements listed.</em></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No services available.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button class="btn btn-sm btn-warning mt-2" data-toggle="modal" data-target="#editModal<?= $d['id'] ?>">
                            ✏️ Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $d['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="post" action="ajax_update_department.php">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class='bx bx-edit'></i> Edit Department</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="department_id" value="<?= $d['id'] ?>">

                        <label>Department Name:</label>
                        <input name="name" class="form-control mb-2" value="<?= htmlspecialchars($d['name']) ?>" required>

                        <label>Acronym:</label>
                        <input name="acronym" class="form-control mb-2" value="<?= htmlspecialchars($d['acronym']) ?>">

                        <label>Description:</label>
                        <textarea name="description" class="form-control mb-3"><?= htmlspecialchars($d['description']) ?></textarea>

                        <label>Services & Requirements:</label>
                        <div class="service-edit-area">
                            <?php if (isset($serviceMap[$d['id']])): ?>
                                <?php foreach ($serviceMap[$d['id']] as $svcId => $svc): ?>
                                    <div class="service-edit-block mb-3 p-2 border rounded">
                                        <input type="hidden" name="service_ids[]" value="<?= $svcId ?>">
                                        <input type="text" name="service_names[]" class="form-control mb-1" value="<?= htmlspecialchars($svc['name']) ?>" placeholder="Service Name" required>
                                        
                                        <div class="requirement-group">
                                            <?php if (!empty($svc['requirements'])): ?>
                                                <?php foreach ($svc['requirements'] as $req): ?>
                                                    <div class="input-group mb-1">
                                                        <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control" value="<?= htmlspecialchars($req) ?>" placeholder="Requirement">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-danger remove-req">X</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <div class="input-group mb-2">
                                                <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control" placeholder="Add new requirement (optional)">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-danger remove-req">X</button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary add-req">+ Add Requirement</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-success mt-2" id="addNewServiceBtn<?= $d['id'] ?>">+ Add New Service</button>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-warning">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <?php endforeach; ?>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="addForm" class="modal-content shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class='bx bx-plus-circle'></i> Add Department</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input name="name" class="form-control mb-2" placeholder="Department Name" required>
                <input name="acronym" class="form-control mb-2" placeholder="Acronym (e.g., DOH)">
                <textarea name="description" class="form-control mb-2" placeholder="Description"></textarea>

                <label>Services Offered:</label>
                <div id="serviceFields" class="mb-2">
                    <div class="service-group">
                        <div class="input-group mb-2">
                            <input type="text" name="services[]" class="form-control" placeholder="Enter a service" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-danger removeService">X</button>
                            </div>
                        </div>
                        <input type="text" name="requirements[]" class="form-control mb-2" placeholder="Requirement for above service (optional)">
                    </div>
                </div>
                <button type="button" id="addService" class="btn btn-sm btn-secondary">+ Add Service</button>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success">Add Department</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).on('click', '#addService', function () {
    const group = `
        <div class="service-group">
            <div class="input-group mb-2">
                <input type="text" name="services[]" class="form-control" placeholder="Enter a service" required>
                <div class="input-group-append">
                    <button type="button" class="btn btn-danger removeService">X</button>
                </div>
            </div>
            <input type="text" name="requirements[]" class="form-control mb-2" placeholder="Requirement for above service (optional)">
        </div>`;
    $('#serviceFields').append(group);
});
$(document).on('click', '.remove-req', function () {
    $(this).closest('.input-group').remove();
});
$(document).on('click', '.add-req', function () {
    const reqField = `
        <div class="input-group mb-2">
            <input type="text" name="" class="form-control" placeholder="Requirement">
            <div class="input-group-append">
                <button type="button" class="btn btn-danger remove-req">X</button>
            </div>
        </div>`;
    const reqGroup = $(this).siblings('.requirement-group');
    const serviceId = $(this).closest('.service-edit-block').find('input[name="service_ids[]"]').val();
    reqGroup.append($(reqField).find('input').attr('name', `requirements[${serviceId}][]`).end());
});
$('[id^="addNewServiceBtn"]').click(function () {
    const deptId = $(this).attr('id').replace('addNewServiceBtn', '');
    const block = `
        <div class="service-edit-block mb-3 p-2 border rounded">
            <input type="hidden" name="service_ids[]" value="new">
            <input type="text" name="service_names[]" class="form-control mb-1" placeholder="Service Name" required>
            <div class="requirement-group">
                <div class="input-group mb-2">
                    <input type="text" name="requirements[new_` + Date.now() + `][]" class="form-control" placeholder="Requirement">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-danger remove-req">X</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary add-req">+ Add Requirement</button>
        </div>`;
    $(this).siblings('.service-edit-area').append(block);
});
$(document).on('click', '.removeService', function () {
    $(this).closest('.service-group').remove();
});
$('#addForm').submit(function(e) {
    e.preventDefault();
    if (confirm("Add this department and services?")) {
        $.post('ajax_add_department_with_services.php', $(this).serialize(), function(response) {
            location.reload();
        }).fail(function(xhr) {
            alert("Error: " + xhr.responseText);
        });
    }
});
$('#searchInput').on('input', function () {
    const val = $(this).val().toLowerCase();
    $('.dept-card').each(function () {
        const searchable = $(this).data('search');
        $(this).toggle(searchable.includes(val));
    });
});
$('#clearFilters').click(function () {
    $('#searchInput').val('');
    $('.dept-card').show();
});
$('form').on('submit', function (e) {
    e.preventDefault();
    const form = $(this);
    $.post(form.attr('action'), form.serialize(), function (response) {
        form.closest('.modal').modal('hide');
        setTimeout(() => { location.reload(); }, 500);
    }).fail(function (xhr) {
        alert("Update failed: " + xhr.responseText);
    });
});
</script>
</body>
</html>
