<?php
/**
 * reCAPTCHA v3 SERVER VERIFICATION
 * Verificação de token reCAPTCHA no servidor
 * Arquivo: api/verify-recaptcha.php
 */

header('Content-Type: application/json');

// Obter dados POST
$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$action = $input['action'] ?? 'submit';

// Validar token
if (empty($token)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'score' => 0,
        'error' => 'Token não fornecido'
    ]);
    exit;
}

// Obter secret key
$secretKey = getenv('RECAPTCHA_SECRET_KEY');
if (!$secretKey) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'score' => 0,
        'error' => 'Configuração do servidor incompleta'
    ]);
    exit;
}

// Verificar token com Google
$response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'secret' => $secretKey,
            'response' => $token
        ])
    ]
]));

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'score' => 0,
        'error' => 'Erro ao conectar com Google reCAPTCHA'
    ]);
    exit;
}

$data = json_decode($response, true);

// Validar resposta
if (!$data['success']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'score' => 0,
        'error' => 'Verificação falhou',
        'errors' => $data['error-codes'] ?? []
    ]);
    exit;
}

// Verificar timeout
if (!empty($data['challenge_ts'])) {
    $challengeTime = strtotime($data['challenge_ts']);
    $currentTime = time();
    if (($currentTime - $challengeTime) > 120) { // Mais de 2 minutos
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'score' => 0,
            'error' => 'Token expirado'
        ]);
        exit;
    }
}

// Verificar action
if ($data['action'] !== $action) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'score' => 0,
        'error' => 'Action mismatch'
    ]);
    exit;
}

// Registrar verificação (opcional)
if (class_exists('PDO')) {
    try {
        // Assumindo que $db está disponível
        $db = null; // Será injetado
        if ($db) {
            $query = "
                INSERT INTO recaptcha_logs (action, score, hostname, timestamp)
                VALUES (?, ?, ?, NOW())
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $action,
                $data['score'],
                $data['hostname'] ?? ''
            ]);
        }
    } catch (Exception $e) {
        error_log('Erro ao registrar reCAPTCHA: ' . $e->getMessage());
    }
}

// Retornar resultado
http_response_code(200);
echo json_encode([
    'success' => true,
    'score' => $data['score'],
    'action' => $data['action'],
    'challenge_ts' => $data['challenge_ts'] ?? null,
    'hostname' => $data['hostname'] ?? null
]);
?>
