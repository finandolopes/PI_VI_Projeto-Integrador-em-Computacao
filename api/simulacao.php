<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido.']);
    exit;
}

$valor = isset($input['valor']) ? (float) $input['valor'] : 0;
$parcelas = isset($input['parcelas']) ? (int) $input['parcelas'] : 0;
$cpf = isset($input['cpf']) ? trim($input['cpf']) : '';

if ($valor <= 0 || $parcelas <= 0 || $parcelas > 96 || $cpf === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Dados inválidos para simulação.']);
    exit;
}

// Simulação básica com taxa mensal fixa (ajuste conforme política da empresa)
$taxa = 0.0129;
$parcela = $valor * ($taxa / (1 - pow(1 + $taxa, -$parcelas)));

echo json_encode([
    'parcela' => number_format($parcela, 2, ',', '.'),
]);
