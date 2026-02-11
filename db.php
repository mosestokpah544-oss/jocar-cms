<?php
$host = getenv("MYSQL_HOST");
$port = getenv("MYSQL_PORT");
$user = getenv("MYSQL_USER");
$pass = getenv("MYSQL_PASSWORD");
$db   = getenv("MYSQL_DATABASE");

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
