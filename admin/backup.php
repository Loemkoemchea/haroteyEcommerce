<?php
// file: admin/backup.php
require_once 'auth_check.php';

// Database configuration
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'harotey';

// Set timeout to avoid interruption
set_time_limit(300);

// Get all tables
$tables = $conn->query("SHOW TABLES");
$sql = "-- Harotey Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

while ($row = $tables->fetch_row()) {
    $table = $row[0];

    // Drop table if exists (optional, for restore)
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";

    // Create table
    $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc();
    $sql .= $create['Create Table'] . ";\n\n";

    // Insert data
    $result = $conn->query("SELECT * FROM `$table`");
    $num_fields = $result->field_count;
    while ($row_data = $result->fetch_row()) {
        $sql .= "INSERT INTO `$table` VALUES(";
        for ($i = 0; $i < $num_fields; $i++) {
            $row_data[$i] = addslashes($row_data[$i]);
            $row_data[$i] = preg_replace("/\n/", "\\n", $row_data[$i]);
            if (isset($row_data[$i])) {
                $sql .= '"' . $row_data[$i] . '"';
            } else {
                $sql .= '""';
            }
            if ($i < ($num_fields - 1)) $sql .= ',';
        }
        $sql .= ");\n";
    }
    $sql .= "\n";
}

// Output as downloadable file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="harotey_backup_' . date('Y-m-d_H-i-s') . '.sql"');
header('Content-Length: ' . strlen($sql));
echo $sql;
exit;
?>