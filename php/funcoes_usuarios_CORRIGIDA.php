<?php
// =====================================================
// CONFINTER - Funções de Usuários (SEGURA)
// Arquivo: php/funcoes_usuarios.php
// Data: 07/02/2026 - VERSÃO CORRIGIDA
// =====================================================

/**
 * Criar um novo usuário com validação completa
 * @param mysqli $conexao Conexão com banco de dados
 * @param string $nome Nome do usuário
 * @param string $usuario Nome de usuário (login)
 * @param string $senha Senha em texto plano
 * @param string $email Email do usuário
 * @return array ['sucesso' => bool, 'mensagem' => string]
 */
function criarUsuario($conexao, $nome, $usuario, $senha, $email) {
    // Validar entrada
    $erros = [];
    
    if (empty($nome) || strlen($nome) < 3) {
        $erros[] = 'Nome deve ter no mínimo 3 caracteres';
    }
    
    if (empty($usuario) || strlen($usuario) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $usuario)) {
        $erros[] = 'Usuário inválido (3-20 caracteres, apenas letras, números, _, - e .)';
    }
    
    if (strlen($senha) < 8) {
        $erros[] = 'Senha deve ter no mínimo 8 caracteres';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Email inválido';
    }
    
    if (!empty($erros)) {
        return ['sucesso' => false, 'mensagem' => implode('; ', $erros)];
    }
    
    // Verificar se usuário já existe
    $stmt = $conexao->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Usuário já existe'];
    }
    $stmt->close();
    
    // Hash da senha
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    
    // Sanitizar entrada
    $nome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $usuario = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    
    // Inserir com prepared statement
    $sql = "INSERT INTO usuarios (nome, usuario, senha, email, data_criacao) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conexao->prepare($sql);
    
    if (!$stmt) {
        error_log("Erro ao preparar statement: " . $conexao->error);
        return ['sucesso' => false, 'mensagem' => 'Erro ao criar usuário'];
    }
    
    $stmt->bind_param("ssss", $nome, $usuario, $senhaHash, $email);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['sucesso' => true, 'mensagem' => 'Usuário criado com sucesso', 'id' => $conexao->insert_id];
    } else {
        error_log("Erro ao executar insert: " . $stmt->error);
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Erro ao criar usuário'];
    }
}

/**
 * Editar um usuário existente
 * @param mysqli $conexao Conexão com banco de dados
 * @param int $id ID do usuário
 * @param string $nome Nome do usuário
 * @param string $usuario Nome de usuário (login)
 * @param string $senha Senha em texto plano (deixar vazio para não alterar)
 * @param string $email Email do usuário
 * @return array ['sucesso' => bool, 'mensagem' => string]
 */
function editarUsuario($conexao, $id, $nome, $usuario, $senha, $email) {
    // Validação de ID
    if (!is_numeric($id) || $id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'ID inválido'];
    }
    
    // Validar entrada
    $erros = [];
    
    if (empty($nome) || strlen($nome) < 3) {
        $erros[] = 'Nome deve ter no mínimo 3 caracteres';
    }
    
    if (empty($usuario) || strlen($usuario) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $usuario)) {
        $erros[] = 'Usuário inválido';
    }
    
    if (!empty($senha) && strlen($senha) < 8) {
        $erros[] = 'Senha deve ter no mínimo 8 caracteres';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Email inválido';
    }
    
    if (!empty($erros)) {
        return ['sucesso' => false, 'mensagem' => implode('; ', $erros)];
    }
    
    // Sanitizar entrada
    $nome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $usuario = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    
    // Verificar se há mudança de usuário e se novo usuário já existe
    if (!empty($usuario)) {
        $stmt = $conexao->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $stmt->bind_param("si", $usuario, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            return ['sucesso' => false, 'mensagem' => 'Este usuário já existe'];
        }
        $stmt->close();
    }
    
    // Atualizar com ou sem senha
    if (!empty($senha)) {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET nome = ?, usuario = ?, senha = ?, email = ?, data_atualizacao = NOW() WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ssssi", $nome, $usuario, $senhaHash, $email, $id);
    } else {
        $sql = "UPDATE usuarios SET nome = ?, usuario = ?, email = ?, data_atualizacao = NOW() WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("sssi", $nome, $usuario, $email, $id);
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['sucesso' => true, 'mensagem' => 'Usuário atualizado com sucesso'];
    } else {
        error_log("Erro ao atualizar usuário: " . $stmt->error);
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar usuário'];
    }
}

/**
 * Excluir um usuário
 * @param mysqli $conexao Conexão com banco de dados
 * @param int $id ID do usuário
 * @return array ['sucesso' => bool, 'mensagem' => string]
 */
function excluirUsuario($conexao, $id) {
    // Validação de ID
    if (!is_numeric($id) || $id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'ID inválido'];
    }
    
    // Não permitir excluir o último admin
    $stmt = $conexao->prepare("SELECT COUNT(*) as total FROM usuarios WHERE perfil = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['total'] <= 1) {
        return ['sucesso' => false, 'mensagem' => 'Não é possível excluir o último administrador'];
    }
    
    // Excluir com prepared statement
    $sql = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        return ['sucesso' => true, 'mensagem' => 'Usuário excluído com sucesso'];
    } else {
        error_log("Erro ao excluir usuário: " . $stmt->error);
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Erro ao excluir usuário ou usuário não encontrado'];
    }
}

/**
 * Listar todos os usuários
 * @param mysqli $conexao Conexão com banco de dados
 * @param int $limite Número máximo de registros (padrão: 1000)
 * @param int $offset Offset para paginação
 * @return array Array de usuários
 */
function listarUsuarios($conexao, $limite = 1000, $offset = 0) {
    if (!is_numeric($limite) || !is_numeric($offset)) {
        return [];
    }
    
    $sql = "SELECT id, nome, usuario, email, perfil, data_criacao, data_atualizacao FROM usuarios ORDER BY nome ASC LIMIT ? OFFSET ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $limite, $offset);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuarios = [];
    
    while ($usuario = $resultado->fetch_assoc()) {
        // Nunca retornar senha em listas
        $usuarios[] = $usuario;
    }
    
    $stmt->close();
    return $usuarios;
}

/**
 * Buscar um usuário pelo ID
 * @param mysqli $conexao Conexão com banco de dados
 * @param int $id ID do usuário
 * @return array|null Dados do usuário ou null se não encontrado
 */
function buscarUsuarioPorId($conexao, $id) {
    if (!is_numeric($id) || $id <= 0) {
        return null;
    }
    
    $sql = "SELECT id, nome, usuario, email, perfil, data_criacao, data_atualizacao FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        $stmt->close();
        return $usuario;
    }
    
    $stmt->close();
    return null;
}

/**
 * Buscar um usuário pelo login
 * @param mysqli $conexao Conexão com banco de dados
 * @param string $usuario Nome de usuário
 * @return array|null Dados do usuário (sem retornar senha em list)
 */
function buscarUsuarioPorLogin($conexao, $usuario) {
    if (empty($usuario)) {
        return null;
    }
    
    $usuario = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
    
    $sql = "SELECT id, nome, usuario, email, perfil FROM usuarios WHERE usuario = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $user = $resultado->fetch_assoc();
        $stmt->close();
        return $user;
    }
    
    $stmt->close();
    return null;
}

/**
 * Contar total de usuários
 * @param mysqli $conexao Conexão com banco de dados
 * @return int Total de usuários
 */
function contarUsuarios($conexao) {
    $sql = "SELECT COUNT(*) as total FROM usuarios";
    $stmt = $conexao->prepare($sql);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $resultado['total'];
}

?>
