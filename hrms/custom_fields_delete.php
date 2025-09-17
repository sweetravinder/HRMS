<?php
require 'config.php';
require_login();
include 'header.php';
if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Delete values first
        $stmt = $pdo->prepare("DELETE FROM employee_custom_values WHERE field_id=?");
        $stmt->execute([$id]);

        // Delete field definition
        $stmt = $pdo->prepare("DELETE FROM employee_custom_fields WHERE id=?");
        $stmt->execute([$id]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: ".$e->getMessage());
    }
}
header('Location: custom_fields.php');
exit;
?>
<?php include 'footer.php'; ?>

