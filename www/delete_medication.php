<?php
require_once 'config/db_conn.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM medications WHERE medication_id = :id");
    $stmt->execute([':id' => $id]);
}

header("Location: index.php?status=deleted");
exit();
?>