<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SiteSupervisor') {
    header("Location: index.php");
    exit;
}

include("db.php");

/* ===========================
   REQUIRED IDS (FIXED)
=========================== */

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$site_supervisor_id = (int) $_SESSION['user_id'];

/* Fetch assigned project */
$proj_res = $conn->query("
    SELECT p.id, p.project_name, p.location
    FROM projects p
    JOIN users u ON u.assigned_project_id = p.id
    WHERE u.id = $site_supervisor_id
    LIMIT 1
");

$project = $proj_res->fetch_assoc();

if (!$project) {
    die("No project assigned");
}

$project_id = (int) $project['id'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $weather     = $_POST['weather'] ?? '';
    $safety      = $_POST['safety_check'] ?? 'No';

    /* ===========================
       BUILD JSON SECTIONS
    =========================== */

    $crew = json_encode([
        'type'   => $_POST['crew_type'] ?? [],
        'number' => $_POST['crew_number'] ?? [],
        'hours'  => $_POST['hours_worked'] ?? []
    ]);

    $work = json_encode([
        'task'   => $_POST['task_name'] ?? [],
        'status' => $_POST['task_status'] ?? []
    ]);

    $equipment = json_encode([
        'type'   => $_POST['equip_type'] ?? [],
        'number' => $_POST['equip_number'] ?? [],
        'status' => $_POST['equip_status'] ?? [],
        'hours'  => $_POST['equip_hours'] ?? []
    ]);

    $material_quantities = json_encode([
        'type'      => $_POST['mat_type'] ?? [],
        'total'     => $_POST['mat_total'] ?? [],
        'used'      => $_POST['mat_used'] ?? [],
        'remaining' => $_POST['mat_remaining'] ?? []
    ]);

    $material_deliveries = json_encode([
        'type'      => $_POST['mat_del_type'] ?? [],
        'name'      => $_POST['mat_del_name'] ?? [],
        'qty'       => $_POST['mat_del_qty'] ?? [],
        'scheduled' => $_POST['mat_del_sch'] ?? [],
        'actual'    => $_POST['mat_del_act'] ?? []
    ]);

    $potential_delays = json_encode([
        'type'     => $_POST['delay_event_type'] ?? [],
        'name'     => $_POST['delay_event_name'] ?? [],
        'desc'     => $_POST['delay_event_desc'] ?? [],
        'duration' => $_POST['delay_event_duration'] ?? []
    ]);

    $significant_events = json_encode([
        'type'  => $_POST['sig_event_type'] ?? [],
        'name'  => $_POST['sig_event_name'] ?? [],
        'desc'  => $_POST['sig_event_desc'] ?? [],
        'delay' => $_POST['sig_event_delay'] ?? []
    ]);

    $meetings = json_encode([
        'type'      => $_POST['meeting_type'] ?? [],
        'time'      => $_POST['meeting_time'] ?? [],
        'attendees' => $_POST['meeting_attendees'] ?? [],
        'summary'   => $_POST['meeting_summary'] ?? []
    ]);

    $decisions = json_encode([
        'description' => $_POST['decision_desc'] ?? [],
        'action'      => $_POST['decision_action'] ?? [],
        'party'       => $_POST['decision_party'] ?? []
    ]);

    $directions = json_encode([
        'type'      => $_POST['direction_type'] ?? [],
        'given_by' => $_POST['direction_given'] ?? [],
        'detail'    => $_POST['direction_detail'] ?? [],
        'fix'       => $_POST['direction_fix'] ?? []
    ]);

    /* ===========================
       HANDLE ATTACHMENTS (FIXED)
    =========================== */

    $uploadedFiles = [];

    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {

        $uploadDir = __DIR__ . "/uploads/daily_reports/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['attachments']['name'] as $i => $originalName) {

            if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $_FILES['attachments']['tmp_name'][$i];
            $size    = $_FILES['attachments']['size'][$i];
            $ext     = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $allowed = ['jpg','jpeg','png','pdf'];

            if (!in_array($ext, $allowed)) continue;
            if ($size > 5 * 1024 * 1024) continue;

            $newName = uniqid('report_', true) . '.' . $ext;

            if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                $uploadedFiles[] = $newName;
            }
        }
    }

    $attachments = json_encode($uploadedFiles);

    /* ===========================
       INSERT QUERY (100% MATCH)
    =========================== */

    $stmt = $conn->prepare("
        INSERT INTO daily_reports (
            site_supervisor_id,
            project_id,
            report_date,
            weather,
            crew,
            work,
            equipment,
            material_quantities,
            material_deliveries,
            potential_delays,
            significant_events,
            meetings,
            decisions,
            directions,
            safety,
            attachments
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "iissssssssssssss",
        $site_supervisor_id,
        $project_id,
        $report_date,
        $weather,
        $crew,
        $work,
        $equipment,
        $material_quantities,
        $material_deliveries,
        $potential_delays,
        $significant_events,
        $meetings,
        $decisions,
        $directions,
        $safety,
        $attachments
    );

    if ($stmt->execute()) {
        $message = "✅ Daily Report submitted successfully!";
    } else {
        $message = "❌ Failed to submit report!";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Report</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body {background:#f4f4f4;}

.topbar{
    background:#2f8f3f;color:#fff;padding:15px 25px;display:flex;justify-content:space-between;align-items:center;
    position:fixed;top:0;left:0;width:100%;z-index:1000;
}
.topbar h2{font-size:20px;}
.logout{background:#fff;color:#2f8f3f;padding:8px 15px;border-radius:5px;text-decoration:none;font-weight:bold;}
.logout i{margin-right:5px;}

.container{display:flex;}
.sidebar{
    width:200px;background:#256d31;min-height:100vh;padding-top:60px;
    position:fixed;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;
}
.sidebar a{display:flex;align-items:center;gap:10px;padding:15px 20px;color:white;text-decoration:none;font-size:15px;font-weight:400;}
.sidebar a:hover{background:#2f8f3f;}

.main{flex:1;margin-left:220px;padding:30px;}
.main h2,h3{margin-bottom:20px;color:#333;}

.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;}
.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);display:flex;align-items:center;gap:15px;}
.card i{font-size:30px;color:#2f8f3f;}
.card h4{color:#2f8f3f;margin-bottom:5px}
.card p{font-size:22px;font-weight:bold;margin:0}

/* Tables */
table{width:100%;background:#fff;border-collapse:collapse;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:30px;}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;}
th{background:#2f8f3f;color:#fff;}
input, select, textarea{width:100%;padding:6px;border-radius:4px;border:1px solid #ccc;font-size:14px;}
button{padding:10px 20px;background:#2f8f3f;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;margin-top:15px;}
button:hover{background:#256d31;}
.message{margin-bottom:15px;font-weight:bold;font-size:14px;}
.message.success{color:green;}
.message.error{color:red;}
/* Hamburger */
.menu-btn{
    font-size:22px;
    cursor:pointer;
    display:none;
}

/* Sidebar mobile behavior */
.sidebar{
    width:220px;
    background:#256d31;
    height:100vh;
    position:fixed;
    top:0;
    left:-220px;
    padding-top:70px;
    transition:0.3s;
    z-index:1100;
}
.sidebar.active{
    left:0;
}

/* Overlay */
.overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.4);
    display:none;
    z-index:1050;
}
.overlay.active{
    display:block;
}

/* Main mobile */
.main{
    padding:90px 15px 30px;
    margin-left:0;
}

/* Desktop */
@media(min-width:768px){
    .menu-btn{display:none;}
    .sidebar{left:0;}
    .main{margin-left:220px;}
    .overlay{display:none!important;}
}

/* Mobile */
@media(max-width:767px){
    .menu-btn{display:block;}
    .sidebar a{width:100%;}yy
}
.sidebar-logo img{
    width:150px;
    max-width:80%;
    height:auto;
}

</style>
</head>
<body>

<div class="topbar">
    <i class="fas fa-bars menu-btn" onclick="toggleMenu()"></i>
    <h2><i class="fas fa-file-alt"></i> Daily Report</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <div class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="IMG_5436.PNG" alt="Company Logo">
    </div>

    <a href="site_supervisor_dashboard.php">
        <i class="fas fa-home"></i> Home
    </a>
    <a href="ss_daily_reports.php">
        <i class="fas fa-file-alt"></i> Reports
    </a>
    <a href="ss_material_requests.php">
        <i class="fas fa-box"></i> Requests
    </a>
</div>

<div class="overlay" id="overlay" onclick="toggleMenu()"></div>


    <div class="main">
        <h2>Submit Daily Report</h2>

        <?php if($message): ?>
            <div class="message <?= strpos($message,'successfully')!==false ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- Header Section -->
            <h3>Project Details</h3>
            <label>Project Title:</label>
            <input type="text" name="project_title" value="<?= htmlspecialchars($project['project_name'] ?? '') ?>" readonly>
            <label>Date:</label>
            <input type="date" name="report_date" value="<?= date('Y-m-d') ?>">

            <!-- Weather -->
            <label>Weather:</label>
            <select name="weather">
                <option value="">--Select Weather--</option>
                <option value="Sunny">Sunny</option>
                <option value="Cloudy">Cloudy</option>
                <option value="Partially Cloudy">Partially Cloudy</option>
                <option value="Raining">Raining</option>
                <option value="Thunderstorm">Thunderstorm</option>
            </select>

            <!-- Crew Table -->
            <h3>Crew List</h3>
            <table id="crewTable">
                <thead>
                    <tr>
                        <th>Crew Type</th>
                        <th>Crew Number</th>
                        <th>Hours Worked</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="crew_type[]"></td>
                        <td><input type="number" name="crew_number[]"></td>
                        <td><input type="number" step="0.1" name="hours_worked[]"></td>
                    </tr>
                </tbody>
            </table>
<!-- Work Accomplished -->
<h3>Work Accomplished</h3>
<table id="workTable">
    <thead>
        <tr>
            <th>Task Name</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <input type="text" name="task_name[]" placeholder="Enter task name">
            </td>
            <td>
                <select name="task_status[]">
                    <option value="">-- Select Status --</option>
                    <option value="Not Started">Not Started</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Delayed">Delayed</option>
                </select>
            </td>
        </tr>
    </tbody>
</table>

<h3>Equipment In Use / Idle</h3>

<table id="equipmentTable">
    <thead>
        <tr>
            <th>Equipment Type</th>
            <th>Equipment Number</th>
            <th>Status</th>
            <th>Hours Used</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><input type="text" name="equip_type[]"></td>
            <td><input type="text" name="equip_number[]"></td>
            <td>
                <select name="equip_status[]">
                    <option value="">--Select--</option>
                    <option value="In Use">In Use</option>
                    <option value="Idle">Idle</option>
                </select>
            </td>
            <td><input type="number" step="0.1" name="equip_hours[]"></td>
        </tr>
    </tbody>
</table>

<h3>Material Quantities</h3>

<table id="materialQtyTable">
    <thead>
        <tr>
            <th>Material Type</th>
            <th>Total Quantity</th>
            <th>Quantity Used</th>
            <th>Quantity Remaining</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><input type="text" name="mat_type[]"></td>
            <td><input type="number" step="0.01" name="mat_total[]"></td>
            <td><input type="number" step="0.01" name="mat_used[]"></td>
            <td>
                <input type="number" step="0.01" name="mat_remaining[]" readonly>
            </td>
        </tr>
    </tbody>
</table>

<h3>Material Deliveries</h3>

<table id="materialDeliveryTable">
    <thead>
        <tr>
            <th>Material Type</th>
            <th>Material Name</th>
            <th>Quantity Delivered</th>
            <th>Scheduled Arrival Time</th>
            <th>Actual Arrival Time</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><input type="text" name="mat_del_type[]"></td>
            <td><input type="text" name="mat_del_name[]"></td>
            <td><input type="number" step="0.01" name="mat_del_qty[]"></td>
            <td><input type="time" name="mat_del_sch[]"></td>
            <td><input type="time" name="mat_del_act[]"></td>
        </tr>
    </tbody>
</table>

<h3>Potential Delay Events</h3>

<table id="delayEventTable">
    <thead>
        <tr>
            <th>Event Type</th>
            <th>Event Name</th>
            <th>Description</th>
            <th>Potential Delay Duration (Hours / Days)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <select name="delay_event_type[]">
                    <option value="">--Select--</option>
                    <option value="Weather">Weather</option>
                    <option value="Material">Material</option>
                    <option value="Labor">Labor</option>
                    <option value="Equipment">Equipment</option>
                    <option value="Design">Design</option>
                    <option value="Client">Client</option>
                    <option value="Other">Other</option>
                </select>
            </td>
            <td><input type="text" name="delay_event_name[]"></td>
            <td><textarea name="delay_event_desc[]" rows="2"></textarea></td>
            <td><input type="text" name="delay_event_duration[]" placeholder="e.g. 2 Days"></td>
        </tr>
    </tbody>
</table>

<h3>Significant Events</h3>

<table id="significantEventTable">
    <thead>
        <tr>
            <th>Event Type</th>
            <th>Event Name</th>
            <th>Description</th>
            <th>Potential Delay</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <select name="sig_event_type[]">
                    <option value="">--Select--</option>
                    <option value="Accident">Accident</option>
                    <option value="Inspection">Inspection</option>
                    <option value="Instruction">Instruction</option>
                    <option value="Milestone">Milestone</option>
                    <option value="Change">Change</option>
                    <option value="Other">Other</option>
                </select>
            </td>
            <td><input type="text" name="sig_event_name[]"></td>
            <td><textarea name="sig_event_desc[]" rows="2"></textarea></td>
            <td>
                <select name="sig_event_delay[]">
                    <option value="">--Select--</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </td>
        </tr>
    </tbody>
</table>

<h3>Meetings & Directions</h3>

<table id="meetingTable">
    <thead>
        <tr>
            <th>Meeting Type</th>
            <th>Meeting Time</th>
            <th>Attendees</th>
            <th>Summary / Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <select name="meeting_type[]">
                    <option value="">--Select--</option>
                    <option value="Site Meeting">Site Meeting</option>
                    <option value="Client Meeting">Client Meeting</option>
                    <option value="Toolbox Talk">Toolbox Talk</option>
                    <option value="Inspection">Inspection</option>
                    <option value="Instruction">Instruction</option>
                    <option value="Other">Other</option>
                </select>
            </td>
            <td>
                <input type="time" name="meeting_time[]">
            </td>
            <td>
                <input type="text" name="meeting_attendees[]" placeholder="Names / Roles">
            </td>
            <td>
                <textarea name="meeting_summary[]" rows="2"></textarea>
            </td>
        </tr>
    </tbody>
</table>

<h3>Resulting Decisions</h3>

<table id="decisionTable">
    <thead>
        <tr>
            <th>Decision Description</th>
            <th>Action Required</th>
            <th>Responsible Party (Optional)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <textarea name="decision_desc[]" rows="2"></textarea>
            </td>
            <td>
                <textarea name="decision_action[]" rows="2"></textarea>
            </td>
            <td>
                <input type="text" name="decision_party[]" placeholder="Name / Role">
            </td>
        </tr>
    </tbody>
</table>

<h3>Directions</h3>

<table id="directionTable">
    <thead>
        <tr>
            <th>Direction Type</th>
            <th>Given By</th>
            <th>Direction Details</th>
            <th>Way to Fix / Resolve</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <input type="text" name="direction_type[]" placeholder="Verbal / Written / Site Instruction">
            </td>
            <td>
                <input type="text" name="direction_given[]" placeholder="PM / Engineer / Client">
            </td>
            <td>
                <textarea name="direction_detail[]" rows="2"></textarea>
            </td>
            <td>
                <textarea name="direction_fix[]" rows="2"></textarea>
            </td>
        </tr>
    </tbody>
</table>

            <!-- TODO: Add all other tables (Work, Equipment, Materials, Deliveries, Delay Events, Significant Events, Meetings, Decisions, Directions, Safety) here following the same pattern -->

            <!-- Safety -->
            <h3>Safety Check</h3>
            <label>Was a site inspection performed today?</label>
            <input type="radio" name="safety_check" value="Yes"> Yes
            <input type="radio" name="safety_check" value="No" checked> No


                <h3>Attachments (Images / PDF)</h3>
<label>Upload Site Photos or Documents:</label>
<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf">
<p style="font-size:13px;color:#555;">
    Allowed: JPG, PNG, PDF (Max 5MB each)
</p>



            <button type="submit">Submit Report</button>
        </form>
    </div>
</div>

<!-- JS for Dynamic Crew Table -->
<script>
const crewTable = document.getElementById('crewTable').getElementsByTagName('tbody')[0];
crewTable.addEventListener('input', function(e){
    const lastRow = crewTable.rows[crewTable.rows.length-1];
    let filled=false;
    Array.from(lastRow.cells).forEach(cell=>{
        if(cell.querySelector('input').value!=='') filled=true;
    });
    if(filled){
        const newRow=lastRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input=>input.value='');
        crewTable.appendChild(newRow);
    }
});
</script>

<script>
const workTable = document
    .getElementById('workTable')
    .getElementsByTagName('tbody')[0];

workTable.addEventListener('input', function () {
    const lastRow = workTable.rows[workTable.rows.length - 1];

    let filled = false;
    Array.from(lastRow.cells).forEach(cell => {
        const field = cell.querySelector('input, select');
        if (field && field.value !== '') {
            filled = true;
        }
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input, select').forEach(el => el.value = '');
        workTable.appendChild(newRow);
    }
});
</script>

<script>
const equipmentTable = document
    .getElementById('equipmentTable')
    .getElementsByTagName('tbody')[0];

equipmentTable.addEventListener('input', function () {
    const lastRow = equipmentTable.rows[equipmentTable.rows.length - 1];

    let filled = false;
    Array.from(lastRow.querySelectorAll('input, select')).forEach(el => {
        if (el.value !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => input.value = '');
        newRow.querySelectorAll('select').forEach(select => select.value = '');
        equipmentTable.appendChild(newRow);
    }
});
</script>

<script>
const materialTable = document
    .getElementById('materialQtyTable')
    .getElementsByTagName('tbody')[0];

materialTable.addEventListener('input', function (e) {

    const row = e.target.closest('tr');
    if (!row) return;

    const total = row.querySelector('input[name="mat_total[]"]').value;
    const used  = row.querySelector('input[name="mat_used[]"]').value;
    const remainInput = row.querySelector('input[name="mat_remaining[]"]');

    const remaining = (parseFloat(total) || 0) - (parseFloat(used) || 0);
    remainInput.value = remaining >= 0 ? remaining : 0;

    // Auto-add row
    const lastRow = materialTable.rows[materialTable.rows.length - 1];
    let filled = false;

    Array.from(lastRow.querySelectorAll('input')).forEach(input => {
        if (input.value !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => input.value = '');
        materialTable.appendChild(newRow);
    }
});
</script>

<script>
const materialDeliveryTable = document
    .getElementById('materialDeliveryTable')
    .getElementsByTagName('tbody')[0];

materialDeliveryTable.addEventListener('input', function () {

    const lastRow = materialDeliveryTable.rows[materialDeliveryTable.rows.length - 1];
    let filled = false;

    Array.from(lastRow.querySelectorAll('input')).forEach(input => {
        if (input.value !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => input.value = '');
        materialDeliveryTable.appendChild(newRow);
    }
});
</script>

<script>
const delayEventTable = document
    .getElementById('delayEventTable')
    .getElementsByTagName('tbody')[0];

delayEventTable.addEventListener('input', function () {

    const lastRow = delayEventTable.rows[delayEventTable.rows.length - 1];
    let filled = false;

    Array.from(lastRow.querySelectorAll('input, textarea, select')).forEach(el => {
        if (el.value !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input, textarea').forEach(el => el.value = '');
        newRow.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        delayEventTable.appendChild(newRow);
    }
});
</script>

<script>
const sigEventTable = document
    .getElementById('significantEventTable')
    .getElementsByTagName('tbody')[0];

sigEventTable.addEventListener('input', function () {

    const lastRow = sigEventTable.rows[sigEventTable.rows.length - 1];
    let filled = false;

    Array.from(lastRow.querySelectorAll('input, textarea, select')).forEach(el => {
        if (el.value !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input, textarea').forEach(el => el.value = '');
        newRow.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        sigEventTable.appendChild(newRow);
    }
});
</script>

<script>
const meetingTable = document
    .getElementById('meetingTable')
    .getElementsByTagName('tbody')[0];

meetingTable.addEventListener('input', function () {

    const lastRow = meetingTable.rows[meetingTable.rows.length - 1];
    let filled = false;

    Array.from(lastRow.querySelectorAll('input, textarea, select')).forEach(el => {
        if (el.value !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input, textarea').forEach(el => el.value = '');
        newRow.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        meetingTable.appendChild(newRow);
    }
});
</script>

<script>
const decisionTable = document
    .getElementById('decisionTable')
    .getElementsByTagName('tbody')[0];

decisionTable.addEventListener('input', function () {

    const lastRow = decisionTable.rows[decisionTable.rows.length - 1];
    let filled = false;

    Array.from(lastRow.querySelectorAll('textarea, input')).forEach(el => {
        if (el.value.trim() !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('textarea, input').forEach(el => el.value = '');
        decisionTable.appendChild(newRow);
    }
});
</script>

<script>
const directionTable = document
    .getElementById('directionTable')
    .getElementsByTagName('tbody')[0];

directionTable.addEventListener('input', function () {

    const lastRow = directionTable.rows[directionTable.rows.length - 1];
    let filled = false;

    Array.from(lastRow.querySelectorAll('input, textarea')).forEach(el => {
        if (el.value.trim() !== '') filled = true;
    });

    if (filled) {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input, textarea').forEach(el => el.value = '');
        directionTable.appendChild(newRow);
    }
});
</script>



<script>
function toggleMenu(){
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}
</script>

            <!-- TODO: Add all other script (Work, Equipment, Materials, Deliveries, Delay Events, Significant Events, Meetings, Decisions, Directions, Safety) here following the same pattern -->

</body>
</html>
