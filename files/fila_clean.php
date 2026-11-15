<?php
/** Alias legado: fila administrativa oficial mantida em admin/fila.php. */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(307);
} else {
    http_response_code(302);
}
header('Location: ../admin/fila.php');
exit;
