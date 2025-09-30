<?php
echo "<h1>Basic PHP Test</h1>";
echo "<p>PHP is working!</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Session test: ";
session_start();
echo "Session ID: " . session_id() . "</p>";
?>