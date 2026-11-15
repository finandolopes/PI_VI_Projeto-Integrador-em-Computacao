<?php
/**
 * ========================================
 * PROCESSAMENTO DE REQUISIÇÕES - Versão Segura
 * Validação robustra, prepared statements e error handling
 * Data: 07/02/2026
 * ========================================
 */

session_start();

include_once('conexao.php');
include_once('metrics.php');

// Registrar requisição recebida para o painel administrativo
$ip_cliente = $_SERVER['REMOTE_ADDR'];
$user_agent = substr($_SERVER['HTTP_USER_AGENT'], 0, 255);
$timestamp_requisicao = date('Y-m-d H:i:s');

// ========================================
// 1. VALIDAÇÃO CSRF
// ========================================
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log("Tentativa de ataque CSRF em process.php: " . $_SERVER['REMOTE_ADDR']);
    $_SESSION['form_error'] = 'Validação de segurança falhou. Tente novamente.';
    header('Location: ../index.php#requi');
    exit();
}

// ========================================
// 2. RATE LIMITING POR IP
// ========================================
function verificarRateLimitRequisicao($chave, $tentativas_max = 3, $intervalo = 600) {
    $arquivo_rate = sys_get_temp_dir() . '/rate_limit_req_' . md5($chave);
    
    if (file_exists($arquivo_rate)) {
        $dados = unserialize(file_get_contents($arquivo_rate));
        
        if (time() - $dados['timestamp'] < $intervalo) {
            if ($dados['count'] >= $tentativas_max) {
                return ['bloqueado' => true, 'tempo_restante' => $intervalo - (time() - $dados['timestamp'])];
            }
            $dados['count']++;
        } else {
            $dados['count'] = 1;
            $dados['timestamp'] = time();
        }
    } else {
        $dados = ['count' => 1, 'timestamp' => time()];
    }
    
    file_put_contents($arquivo_rate, serialize($dados));
    return ['bloqueado' => false, 'tentativas_restantes' => $tentativas_max - $dados['count']];
}

$chave_rate = 'requisicao_' . $_SERVER['REMOTE_ADDR'];
$rate = verificarRateLimitRequisicao($chave_rate);

if ($rate['bloqueado']) {
    error_log("Rate limit de requisição excedido: " . $_SERVER['REMOTE_ADDR']);
    $_SESSION['form_error'] = 'Muitas requisições em pouco tempo. Tente novamente em alguns minutos.';
    header('Location: ../index.php#requi');
    exit();
}

// ========================================
// 3. CONECTAR AO BANCO
// ========================================
$db = isset($conexao) ? $conexao : (isset($con) ? $con : null);

if (!$db) {
    error_log("Falha ao conectar ao banco de dados em process.php");
    $_SESSION['form_error'] = 'Erro ao conectar ao banco de dados. Tente novamente.';
    header('Location: ../index.php#requi');
    exit();
}

// ========================================
// 4. VALIDAÇÃO DE CAMPOS OBRIGATÓRIOS
// ========================================
$campos_obrigatorios = ['nome', 'data_nascimento', 'email', 'telefone', 'horario_contato', 'tipo'];
foreach ($campos_obrigatorios as $campo) {
    if (empty($_POST[$campo])) {
        error_log("Campo obrigatório faltando em process.php: $campo");
        $_SESSION['form_error'] = "O campo '$campo' é obrigatório.";
        header('Location: ../index.php#requi');
        exit();
    }
}

// ========================================
// 5. VALIDAÇÃO E SANITIZAÇÃO DE CAMPOS
// ========================================

// Nome: apenas letras, números e espaços
$nome = trim($_POST['nome']);
if (empty($nome) || strlen($nome) < 3 || strlen($nome) > 150) {
    $_SESSION['form_error'] = 'Nome deve conter entre 3 e 150 caracteres.';
    header('Location: ../index.php#requi');
    exit();
}
if (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/u', $nome)) {
    $_SESSION['form_error'] = 'Nome contém caracteres inválidos.';
    header('Location: ../index.php#requi');
    exit();
}
$nome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');

// Email: validação com filter_var
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['form_error'] = 'Email inválido.';
    header('Location: ../index.php#requi');
    exit();
}

// Data de nascimento: validação de formato e lógica
$data_br = trim($_POST['data_nascimento']);
if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data_br)) {
    $_SESSION['form_error'] = 'Data de nascimento com formato inválido. Use DD/MM/YYYY.';
    header('Location: ../index.php#requi');
    exit();
}

// Converter e validar data
$data_parts = explode('/', $data_br);
$data_mysql = checkdate((int)$data_parts[1], (int)$data_parts[0], (int)$data_parts[2]) 
    ? $data_parts[2] . '-' . $data_parts[1] . '-' . $data_parts[0]
    : null;

if (!$data_mysql) {
    $_SESSION['form_error'] = 'Data de nascimento inválida.';
    header('Location: ../index.php#requi');
    exit();
}

// Validar se idade é plausível (18-120 anos)
$idade = (int)date('Y') - (int)$data_parts[2];
if ($idade < 18 || $idade > 120) {
    $_SESSION['form_error'] = 'Idade deve estar entre 18 e 120 anos.';
    header('Location: ../index.php#requi');
    exit();
}

$data_nascimento = $data_mysql;

// Telefone: remover caracteres especiais e validar
$telefone = preg_replace('/[^0-9]/', '', trim($_POST['telefone']));
if (strlen($telefone) < 10 || strlen($telefone) > 11) {
    $_SESSION['form_error'] = 'Telefone deve conter 10 ou 11 dígitos.';
    header('Location: ../index.php#requi');
    exit();
}

// Horário de contato: validar entre valores permitidos
$horarios_validos = ['manha', 'tarde', 'noite'];
$horario_contato = strtolower(trim($_POST['horario_contato']));
if (!in_array($horario_contato, $horarios_validos)) {
    $_SESSION['form_error'] = 'Horário de contato inválido.';
    header('Location: ../index.php#requi');
    exit();
}

// Tipo: validar entre valores permitidos
$tipos_validos = ['duvida', 'reclamacao', 'sugestao', 'elogio'];
$tipo = strtolower(trim($_POST['tipo']));
if (!in_array($tipo, $tipos_validos)) {
    $_SESSION['form_error'] = 'Tipo de requisição inválido.';
    header('Location: ../index.php#requi');
    exit();
}

// Categoria: pode ser array
$categoria_lista = [];
if (isset($_POST['categoria']) && is_array($_POST['categoria'])) {
    $categorias_validas = ['produto', 'entrega', 'atendimento', 'website', 'outro'];
    foreach ($_POST['categoria'] as $cat) {
        $cat_limpo = strtolower(trim($cat));
        if (in_array($cat_limpo, $categorias_validas)) {
            $categoria_lista[] = $cat_limpo;
        }
    }
}

if (empty($categoria_lista)) {
    $_SESSION['form_error'] = 'Selecione pelo menos uma categoria.';
    header('Location: ../index.php#requi');
    exit();
}

$categoria = implode(', ', $categoria_lista);

// Outros info: se categoria inclui "outro"
$outros_info = '';
if (in_array('outro', $categoria_lista)) {
    if (empty($_POST['outros_info'])) {
        $_SESSION['form_error'] = 'Descreva a categoria em "Outros".';
        header('Location: ../index.php#requi');
        exit();
    }
    
    $outros_info = trim($_POST['outros_info']);
    if (strlen($outros_info) < 5 || strlen($outros_info) > 500) {
        $_SESSION['form_error'] = 'Descrição deve conter entre 5 e 500 caracteres.';
        header('Location: ../index.php#requi');
        exit();
    }
    $outros_info = htmlspecialchars($outros_info, ENT_QUOTES, 'UTF-8');
}

// ========================================
// 6. INSERIR CLIENTE
// ========================================
$sqlClientes = "INSERT INTO clientes (nome, data_nascimento, email, telefone) VALUES (?, ?, ?, ?)";
$stmtClientes = $db->prepare($sqlClientes);

if (!$stmtClientes) {
    error_log("Erro ao preparar INSERT clientes: " . $db->error);
    $_SESSION['form_error'] = 'Erro ao processar solicitação. Tente novamente.';
    header('Location: ../index.php#requi');
    exit();
}

$stmtClientes->bind_param("ssss", $nome, $data_nascimento, $email, $telefone);

if (!$stmtClientes->execute()) {
    error_log("Erro ao inserir cliente: " . $stmtClientes->error . " | Email: " . $email);
    $_SESSION['form_error'] = 'Erro ao registrar cliente. Email já pode estar cadastrado.';
    $stmtClientes->close();
    header('Location: ../index.php#requi');
    exit();
}

$id_cliente = $stmtClientes->insert_id;
$stmtClientes->close();

if ($id_cliente <= 0) {
    error_log("Falha ao obter ID do cliente inserido");
    $_SESSION['form_error'] = 'Erro ao processar solicitação. Tente novamente.';
    header('Location: ../index.php#requi');
    exit();
}

// ========================================
// 7. INSERIR REQUISIÇÃO
// ========================================
$sqlRequisicoes = "INSERT INTO requisicoes (id_cliente, horario_contato, tipo, categoria, outros_info, data_requisicao, data_hora) 
                   VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())";

$stmtRequisicoes = $db->prepare($sqlRequisicoes);

if (!$stmtRequisicoes) {
    error_log("Erro ao preparar INSERT requisicoes: " . $db->error);
    $_SESSION['form_error'] = 'Erro ao processar solicitação. Tente novamente.';
    header('Location: ../index.php#requi');
    exit();
}

$stmtRequisicoes->bind_param("issss", $id_cliente, $horario_contato, $tipo, $categoria, $outros_info);

if (!$stmtRequisicoes->execute()) {
    error_log("Erro ao inserir requisição: " . $stmtRequisicoes->error . " | Cliente: " . $id_cliente);
    $_SESSION['form_error'] = 'Erro ao registrar requisição. Tente novamente.';
    $stmtRequisicoes->close();
    header('Location: ../index.php#requi');
    exit();
}

$id_requisicao = $stmtRequisicoes->insert_id;
$stmtRequisicoes->close();

// ========================================
// 8. REGISTRAR AUDITORIA NA TABELA logs_sistema
// ========================================
if ($id_requisicao > 0) {
    $acao = "Nova requisição de crédito enviada";
    $tabela = 'requisicoes';
    $dados_novos = json_encode([
        'id_cliente' => $id_cliente,
        'email' => $email,
        'telefone' => $telefone,
        'tipo' => $tipo,
        'categoria' => $categoria,
        'ip_address' => $ip_cliente,
        'user_agent' => $user_agent
    ]);
    
    $sqlLog = "INSERT INTO logs_sistema (acao, tabela_afetada, registro_id, dados_novos, ip_address, user_agent, tipo) 
               VALUES (?, ?, ?, ?, ?, ?, 'INSERT')";
    
    $stmtLog = $db->prepare($sqlLog);
    if ($stmtLog) {
        $stmtLog->bind_param("ssiss", $acao, $tabela, $id_requisicao, $dados_novos, $ip_cliente, $user_agent);
        $stmtLog->execute();
        $stmtLog->close();
    }
}

// ========================================
// 9. SUCESSO
// ========================================
if ($id_requisicao > 0) {
    // Log de sucesso
    error_log("Requisição processada com sucesso - ID: $id_requisicao | Cliente: $id_cliente | Email: $email");
    $_SESSION['form_success'] = 'Requisição registrada com sucesso! Entraremos em contato em breve.';
    
    // Enviar email de confirmação
    $to = "contato@confinter.com.br";
    $subject = "Nova Requisição de Análise de Crédito - ID: #$id_requisicao";
    $message = "Uma nova requisição de análise de crédito foi enviada.\n\n";
    $message .= "=== DADOS DO CLIENTE ===\n";
    $message .= "Nome: $nome\n";
    $message .= "Email: $email\n";
    $message .= "Telefone: $telefone\n";
    $message .= "Data de Nascimento: " . str_replace('-', '/', $data_nascimento) . "\n\n";
    $message .= "=== DADOS DA REQUISIÇÃO ===\n";
    $message .= "ID Requisição: #$id_requisicao\n";
    $message .= "Tipo: $tipo\n";
    $message .= "Categoria: $categoria\n";
    $message .= "Horário de Contato: $horario_contato\n";
    if (!empty($outros_info)) {
        $message .= "Observações: $outros_info\n";
    }
    $message .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n";
    $message .= "IP do Cliente: $ip_cliente\n";
    $message .= "\nEsta requisição foi armazenada no sistema para análise posterior.";
    
    $headers = "From: contato@confinter.com.br\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        error_log("Email enviado com sucesso para requisição #$id_requisicao");
    } else {
        error_log("Falha ao enviar email para requisição #$id_requisicao");
    }
    
    header('Location: ../index.php#requi');
    exit();
} else {
    error_log("Falha ao obter ID da requisição inserida");
    $_SESSION['form_error'] = 'Erro ao processar solicitação. Tente novamente.';
    header('Location: ../index.php#requi');
    exit();
}

// Fechar a conexão
$db->close();
?>
