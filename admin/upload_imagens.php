<?php
require_once __DIR__ . '/../php/sd_authorization.php';
sdRequireRole('admin');
require_once __DIR__ . '/../php/conexao.php';
if (!isset($conexao) || !($conexao instanceof mysqli)) { http_response_code(503); exit('Banco indisponível.'); }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $received = (string)($_POST['csrf_token'] ?? '');
    if ($received === '' || !hash_equals($_SESSION['csrf_token'], $received)) {
        http_response_code(403);
        exit('Operação não autorizada.');
    }
}

// FunÃ§Ã£o para executar uma consulta preparada e retornar o resultado
function executarConsulta($conexao, $sql, $params = null) {
    $stmt = $conexao->prepare($sql);
    if ($params) {
        $stmt->bind_param(...$params);
    }
    if (!$stmt->execute()) {
        // Tratamento de erro, se a consulta nÃ£o for bem-sucedida
        return false;
    }
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// FunÃ§Ã£o para contar registros retornados por uma consulta
function contarRegistros($conexao, $sql, $params = null) {
    $stmt = $conexao->prepare($sql);
    if ($params) {
        $stmt->bind_param(...$params);
    }
    if (!$stmt->execute()) {
        // Tratamento de erro, se a consulta nÃ£o for bem-sucedida
        return 0;
    }
    $result = $stmt->get_result();
    return $result->num_rows;
}

// Recupera o nome de usuÃ¡rio da sessÃ£o
$username = $_SESSION['username'];

// Consulta o banco de dados para obter informaÃ§Ãµes do usuÃ¡rio
$sqlUsuario = "SELECT * FROM usuarios WHERE usuario = ?";
$usuario = executarConsulta($conexao, $sqlUsuario, ['s', $username]);

// Verifica se o usuÃ¡rio foi encontrado
if ($usuario) {
    // Use as informaÃ§Ãµes do usuÃ¡rio
    $nome = htmlspecialchars($usuario['nome']);
    $email = htmlspecialchars($usuario['email']);
    // e assim por diante...
} else {
    // Trate o caso em que o usuÃ¡rio nÃ£o foi encontrado
    $nome = "UsuÃ¡rio Desconhecido";
    $email = "email@example.com";
    // ou qualquer valor padrÃ£o que vocÃª preferir
}
// Consulta o perfil do usuÃ¡rio
$sqlPerfilUsuario = "SELECT perfil FROM usuarios WHERE usuario = ?";
$perfilUsuario = executarConsulta($conexao, $sqlPerfilUsuario, ['s', $username]);
$perfil_usuario = isset($perfilUsuario) && isset($perfilUsuario['perfil']) ? $perfilUsuario['perfil'] : "UsuÃ¡rio"; // CORRIGIDO: TernÃ¡rio completo

// Consulta o banco de dados para obter a mÃ©dia de tempo que o visitante fica no site
$sqlMediaTempo = "SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo))) AS media_tempo FROM contador_visitas";
$mediaTempo = executarConsulta($conexao, $sqlMediaTempo);

// FunÃ§Ã£o para validar upload de imagem
function validarUploadImagem($arquivo) {
    $erros = [];
    
    // Validar existÃªncia
    if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        switch ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) {
            case UPLOAD_ERR_NO_FILE: $erros[] = 'Nenhum arquivo foi selecionado'; break;
            case UPLOAD_ERR_INI_SIZE: $erros[] = 'Arquivo maior que o limite do servidor'; break;
            case UPLOAD_ERR_FORM_SIZE: $erros[] = 'Arquivo maior que o limite do formulÃ¡rio'; break;
            default: $erros[] = 'Erro ao fazer upload';
        }
        return ['valido' => false, 'erros' => $erros];
    }
    
    // Validar tamanho (5MB)
    $tamanho_maximo = 5 * 1024 * 1024;
    if (($arquivo['size'] ?? 0) > $tamanho_maximo) {
        $erros[] = 'Arquivo muito grande (mÃ¡ximo 5MB)';
    }
    
    // Validar extensÃ£o
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $extensoes_permitidas)) {
        $erros[] = 'ExtensÃ£o nÃ£o permitida. Permitidas: ' . implode(', ', $extensoes_permitidas);
    }
    
    // Validar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tipo_mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($tipo_mime, $tipos_permitidos)) {
        $erros[] = 'Tipo de arquivo nÃ£o permitido';
    }
    
    // Validar conteÃºdo (se Ã© realmente uma imagem)
    $finfo_img = @getimagesize($arquivo['tmp_name']);
    if ($finfo_img === false) {
        $erros[] = 'Arquivo nÃ£o Ã© uma imagem vÃ¡lida';
    }
    
    return [
        'valido' => empty($erros),
        'erros' => $erros,
        'extensao' => $extensao
    ];
}

// Verificar se o formulÃ¡rio de upload de imagem foi enviado
if(isset($_POST["submit"]) && isset($_FILES["imagem"])) {
    // Validar upload
    $validacao = validarUploadImagem($_FILES["imagem"]);
    
    if ($validacao['valido']) {
        $diretorio_destino = "../assets/img/slider/";
        
        // Gerar nome Ãºnico para evitar conflitos
        $nome_arquivo = 'slider_' . time() . '_' . uniqid() . '.' . $validacao['extensao'];
        $caminho_completo = $diretorio_destino . $nome_arquivo;
        
        // Garantir que o diretÃ³rio existe
        if (!is_dir($diretorio_destino)) {
            mkdir($diretorio_destino, 0755, true);
        }
        
        // Move a imagem para o diretÃ³rio de destino
        if(move_uploaded_file($_FILES["imagem"]["tmp_name"], $caminho_completo)) {
            // Insira o nome do arquivo no banco de dados
            $sql = "INSERT INTO imagens_carrossel (nome_arquivo, data_upload) VALUES (?, NOW())";
            $stmt = $conexao->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $nome_arquivo);
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Imagem enviada com sucesso!';
                } else {
                    $_SESSION['error'] = 'Erro ao salvar no banco de dados';
                    unlink($caminho_completo); // Remover arquivo se BD falhar
                }
                $stmt->close();
            }
        } else {
            $_SESSION['error'] = 'Erro ao mover o arquivo para o diretÃ³rio';
        }
    } else {
        $_SESSION['error'] = 'Erro na validaÃ§Ã£o: ' . implode('; ', $validacao['erros']);
    }
}

// Verificar se hÃ¡ uma solicitaÃ§Ã£o para excluir uma imagem
if(isset($_POST['excluir_imagem'])) {
    $nome_arquivo = basename((string)($_POST['nome_arquivo'] ?? ''));
    if (!preg_match('/^[a-zA-Z0-9_.-]+\.(?:jpg|jpeg|png|gif|webp)$/i', $nome_arquivo)) { http_response_code(400); exit('Nome de arquivo inválido.'); }
    $caminho_arquivo = "../assets/img/slider/" . $nome_arquivo;

    // Remover o registro primeiro. O arquivo físico só é removido após a confirmação.
    $sql = "DELETE FROM imagens_carrossel WHERE nome_arquivo = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('s', $nome_arquivo);
    if ($stmt->execute()) {
        if (is_file($caminho_arquivo)) unlink($caminho_arquivo);
        $_SESSION['success'] = 'Slide excluído.';
    }
    $stmt->close();
}

// Consulta ao banco de dados para recuperar as imagens do carrossel
$sql = "SELECT * FROM imagens_carrossel";
$result = mysqli_query($conexao, $sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Painel Administrativo">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Painel Administrativo</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
            <link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
    <!-- include da navbar -->
    <?php include 'navbar.php'; ?>

    <!-- include da sidebar -->
    

    <!-- conteÃºdo principal -->
    <div class="page-wrapper cardhead">
            <div class="content">
                <div class="page-header">
                    <div class="row">
                        <div class="col-sm-12">
                            <h3 class="page-title">Alterar Slides</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Alterar Slides</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Slides</div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    // Loop atravÃ©s de cada linha de resultado
                                    while ($row = $result->fetch_assoc()) {
                                    echo '
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <a href="../assets/img/slider/' . $row["nome_arquivo"] . '" class="image-popup">
                                            <img src="../assets/img/slider/' . $row["nome_arquivo"] . '" class="img-fluid" alt="Slide">
                                        </a>
                                    </div>
                                    ';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FormulÃ¡rio para adicionar slide -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Adicionar Slide</div>
                            </div>
                            <div class="card-body">
                                <form action="" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="form-group">
                                        <label for="imagem">Selecione uma imagem para o slide:</label>
                                        <input type="file" class="form-control" id="imagem" name="imagem" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary" name="submit">Adicionar Slide</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- FormulÃ¡rio para excluir slide -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Excluir Slide</div>
                            </div>
                            <div class="card-body">
                                <form action="" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="form-group">
                                        <label for="nome_arquivo">Selecione o slide a ser excluÃ­do:</label>
                                        <select class="form-control" id="nome_arquivo" name="nome_arquivo" required>
                                            <option value="">Selecione o slide</option>
                                            <?php
                                            // Reinicie a consulta para exibir novamente os slides
                                            $result = mysqli_query($conexao, $sql);
                                            // Loop atravÃ©s de cada linha de resultado
                                            while ($row = $result->fetch_assoc()) {
                                            echo '
                                            <option value="' . $row[" nome_arquivo"] . '">' . $row["nome_arquivo"] . '</option>' ;
                                                    }
                                                    ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-danger" name="excluir_imagem">Excluir Slide</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- include do footer -->
    <?php include 'footer.php'; ?>
</body>
</html>

