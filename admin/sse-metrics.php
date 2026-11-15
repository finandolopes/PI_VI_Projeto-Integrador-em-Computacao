<?php
/**
 * ========================================
 * SSE METRICS - Real-time Dashboard Updates
 * ========================================
 * Envia atualizaÃ§Ãµes de mÃ©tricas em tempo real via Server-Sent Events
 * Data: 07/02/2026
 */

// Headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Iniciar sessÃ£o
session_start();

// Verificar autenticaÃ§Ã£o
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die();
}

// ConexÃ£o com banco
include_once('../php/conexao.php');
include_once('../php/metrics.php');

// FunÃ§Ã£o para enviar dados SSE
function sendSSEData($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// FunÃ§Ã£o para obter mÃ©tricas atualizadas
function getUpdatedMetrics($conexao) {
    $metrics = [
        'timestamp' => date('H:i:s'),
        'date' => date('d/m/Y'),
        'visitas' => 0,
        'depoimentos' => 0,
        'requisicoes' => 0,
        'taxa' => 0,
        'usuarios_online' => 0,
    ];

    try {
        // Total de visitas (se houver tabela de tracking)
        $result = $conexao->query("SELECT COUNT(*) as count FROM requisicoes");
        $metrics['requisicoes'] = $result ? $result->fetch_assoc()['count'] : 0;

        // Total de depoimentos
        $result = $conexao->query("SELECT COUNT(*) as count FROM depoimentos");
        $metrics['depoimentos'] = $result ? $result->fetch_assoc()['count'] : 0;

        // UsuÃ¡rios online (Ãºltimos 5 minutos com session ativa)
        $result = $conexao->query(
            "SELECT COUNT(DISTINCT id_usuario) as count FROM usuarios 
             WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        $metrics['usuarios_online'] = $result ? $result->fetch_assoc()['count'] : 0;

        // Taxa de requisiÃ§Ãµes realizadas
        $result = $conexao->query(
            "SELECT COUNT(*) as count FROM requisicoes WHERE status = 'concluÃ­da'"
        );
        $completed = $result ? $result->fetch_assoc()['count'] : 0;
        $metrics['taxa'] = $metrics['requisicoes'] > 0 ?
             round(($completed / $metrics['requisicoes']) * 100, 2)
            : 0;

    } catch (Exception $e) {
        error_log("Erro ao obter mÃ©tricas SSE: " . $e->getMessage());
    }

    return $metrics;
}

// Loop para enviar atualizaÃ§Ãµes
$lastUpdate = time();
$updateInterval = 30; // segundos

while (true) {
    // Verificar se conexÃ£o foi encerrada
    if (connection_aborted()) {
        exit;
    }

    // Obter mÃ©tricas atualizadas
    $metrics = getUpdatedMetrics($conexao);
    
    // Enviar dados
    sendSSEData($metrics);

    // Aguardar antes da prÃ³xima atualizaÃ§Ã£o
    sleep($updateInterval);
}

// Nunca alcanÃ§a aqui, mas deixado para referÃªncia
$conexao->close();
?>
