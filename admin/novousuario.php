<?php
require_once __DIR__ . '/../php/sd_authorization.php';
sdRequireRole('admin');
?>
<?php
header('Location: addusuario.php');
exit();
?>
