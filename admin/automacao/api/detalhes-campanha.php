<?php
/**
 * API: GET CAMPAIGN DETAILS
 * Retorna detalhes da campanha WhatsApp.
 */


session_start();
require_once __DIR__ . '/../../../php/config.php';
require_once __DIR__ . '/../../../php/seguranca.php';

header('Content-Type: application/json; charset=utf-8');

verificarSessao();
require_permissao(['admin', 'gestor', 'operador']);

try {
    $idCampanha = (int) ($_GET['id'] ?? 0);
    if ($idCampanha <= 0) {
        throw new Exception('ID da campanha não fornecido');
    }

    $sql = "SELECT c.*,
                   COUNT(CASE WHEN cw.status = 'enviada' THEN 1 END) AS enviadas,
                   COUNT(CASE WHEN cw.status = 'entregue' THEN 1 END) AS entregues,
                   COUNT(CASE WHEN cw.status = 'lida' THEN 1 END) AS lidas,
                   COUNT(CASE WHEN cw.status = 'clicou' THEN 1 END) AS cliques
            FROM campanhas_whatsapp c
            LEFT JOIN campanhas_whatsapp_logs cw ON c.id = cw.id_campanha
            WHERE c.id = $idCampanha
            GROUP BY c.id";

    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Campanha não encontrada');
    }

    $campanha = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) ($campanha['id'] ?? 0),
            'nome' => htmlspecialchars((string) ($campanha['nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'mensagem' => htmlspecialchars((string) ($campanha['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars((string) ($campanha['status'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'created_at' => $campanha['created_at'] ?? null,
            'agendada_para' => $campanha['agendada_para'] ?? null,
            'statistics' => [
                'enviadas' => (int) ($campanha['enviadas'] ?? 0),
                'entregues' => (int) ($campanha['entregues'] ?? 0),
                'lidas' => (int) ($campanha['lidas'] ?? 0),
                'cliques' => (int) ($campanha['cliques'] ?? 0),
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

