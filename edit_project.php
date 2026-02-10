<?php
session_start();
include "db.php";

// Protect page
if (!isset($_SESSION['role'])) {
    header("Location: login.html");
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get project ID
if (!isset($_GET['id'])) {
    header("Location: project.php");
    exit;
}

$project_id = intval($_GET['id']);

// Fetch existing project data
$stmt = $conn->prepare("SELECT * FROM projects WHERE id=?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    header("Location: project.php");
    exit;
}

// Handle update
if (isset($_POST['update_project'])) {

    $project_name = $_POST['project_name'];
    $client_name  = $_POST['client_name'];
    $location     = $_POST['location'];
    $budget       = $_POST['budget'];
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'];
    $status       = $_POST['status'];
    $manager_id   = $_POST['project_manager'];

    // 🔐 SAFETY FIX: Keep old end date if empty
    if (empty($end_date)) {
        $end_date = $project['end_date'];
    }

    $stmt = $conn->prepare("
        UPDATE projects 
        SET project_name=?, client_name=?, location=?, budget=?, start_date=?, end_date=?, status=?, project_manager_id=? 
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssdsssii",
        $project_name,
        $client_name,
        $location,
        $budget,
        $start_date,
        $end_date,
        $status,
        $manager_id,
        $project_id
    );

    $stmt->execute();
    $stmt->close();

    header("Location: project.php");
    exit;
}

// Fetch project managers
$managers = $conn->query("SELECT id, full_name FROM users WHERE role='ProjectManager'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Project</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
body { background:#f4f4f4; }

.topbar {
    background:#2f8f3f;
    color:white;
    padding:15px 25px;
    display:flex;
    justify-content:space-between;
}

.container { display:flex; }

.sidebar {
    width:220px;
    background:#256d31;
    min-height:100vh;
    padding-top:20px;
}

.sidebar a {
    display:block;
    padding:15px 20px;
    color:white;
    text-decoration:none;
}

.sidebar a:hover { background:#2f8f3f; }

.main {
    flex:1;
    padding:30px;
    background:#f4f4f4;
}

form {
    background:white;
    padding:25px;
    border-radius:8px;
    max-width:600px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

form label {
    font-weight:bold;
    margin-top:10px;
    display:block;
}

form input, form select {
    width:100%;
    padding:10px;
    margin:5px 0 15px 0;
    border-radius:6px;
    border:1px solid #ccc;
}

button {
    background:#2f8f3f;
    color:white;
    padding:10px 15px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

button:hover { background:#256d31; }
</style>
</head>

<body>
<div class="main">
    <h3>Edit Project</h3>

    <form method="POST">
        <label>Project Name</label>
        <input type="text" name="project_name" value="<?php echo $project['project_name']; ?>" required>

        <label>Client Name</label>
        <input type="text" name="client_name" value="<?php echo $project['client_name']; ?>" required>

        <label>Location</label>
        <input type="text" name="location" value="<?php echo $project['location']; ?>" required>

        <label>Budget</label>
        <input type="number" name="budget" step="0.01" value="<?php echo $project['budget']; ?>" required>

        <label>Start Date</label>
        <input type="date" name="start_date" value="<?php echo $project['start_date']; ?>" required>

        <label>End Date</label>
        <input type="date" name="end_date" value="<?php echo $project['end_date']; ?>">

        <label>Status</label>
        <label>Status</label>
<select name="status" required>
    <option value="Pending" <?php if($project['status']=='Pending') echo 'selected'; ?>>Pending</option>
    <option value="Ongoing" <?php if($project['status']=='Ongoing') echo 'selected'; ?>>Ongoing</option>
    <option value="On Hold" <?php if($project['status']=='On Hold') echo 'selected'; ?>>On Hold</option>
    <option value="Completed" <?php if($project['status']=='Completed') echo 'selected'; ?>>Completed</option>
</select>


        <label>Project Manager</label>
        <select name="project_manager">
            <?php while($m = $managers->fetch_assoc()) { ?>
                <option value="<?php echo $m['id']; ?>" <?php if($m['id']==$project['project_manager_id']) echo 'selected'; ?>>
                    <?php echo $m['full_name']; ?>
                </option>
            <?php } ?>
        </select>

        <button type="submit" name="update_project">Update Project</button>
    </form>
</div>

</div>

</body>
</html>
