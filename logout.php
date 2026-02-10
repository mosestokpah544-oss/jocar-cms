<?php
session_start();

session_unset();
session_destroy();

/* Always go back to the SAME login page */
header("Location: index.php");
exit;
