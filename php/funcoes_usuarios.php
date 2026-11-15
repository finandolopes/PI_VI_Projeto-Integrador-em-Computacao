<?php
require_once __DIR__.'/sd_policies.php';
/**
 * Funções de usuários.
 * Compatível com o schema atual da tabela `usuarios`:
 * id, nome, email, usuario, senha, perfil, img_perfil, telefone, data_cadastro, status.
 */

/**
 * @return array{sucesso:bool,mensagem:string,id:int|null}
 */
function criarUsuario(mysqli $conexao, string $nome, string $usuario, string $senha, string $email, string $perfil = 'usuario', string $telefone = '', string $imgPerfil = ''): array
{
    $nome = trim($nome);
    $usuario = trim($usuario);
    $senha = (string) $senha;
    $email = trim($email);
    $perfil = strtolower(trim($perfil));
    $telefone = trim($telefone);
    $imgPerfil = trim($imgPerfil);

    $erros = [];
    if ($nome === '' || mb_strlen($nome) < 3) {
        $erros[] = 'Nome deve ter no mínimo 3 caracteres.';
    }
    if ($usuario === '' || mb_strlen($usuario) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $usuario)) {
        $erros[] = 'Usuário inválido.';
    }
    if (mb_strlen($senha) < 8) {
        $erros[] = 'Senha deve ter no mínimo 8 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }
    if (!in_array($perfil, ['admin', 'usuario'], true)) {
        $perfil = 'usuario';
    }

    if (!empty($erros)) {
        return ['sucesso' => false, 'mensagem' => implode(' ', $erros), 'id' => null];
    }

    $check = $conexao->prepare('SELECT id FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1');
    if (!$check) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao validar usuário.', 'id' => null];
    }
    $check->bind_param('ss', $usuario, $email);
    $check->execute();
    $exists = $check->get_result();
    $jaExiste = $exists && $exists->num_rows > 0;
    $check->close();

    if ($jaExiste) {
        return ['sucesso' => false, 'mensagem' => 'Usuário ou e-mail já cadastrado.', 'id' => null];
    }

    $senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
    $status = 'ativo';

    $sql = 'INSERT INTO usuarios (nome, usuario, senha, email, telefone, perfil, img_perfil, data_cadastro, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)';
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar cadastro.', 'id' => null];
    }

    $stmt->bind_param('ssssssss', $nome, $usuario, $senhaHash, $email, $telefone, $perfil, $imgPerfil, $status);
    $ok = $stmt->execute();
    $novoId = $ok ? (int) $conexao->insert_id : null;
    $stmt->close();

    if (!$ok) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao criar usuário.', 'id' => null];
    }

    return ['sucesso' => true, 'mensagem' => 'Usuário criado com sucesso.', 'id' => $novoId];
}

/**
 * @return array{sucesso:bool,mensagem:string}
 */
function editarUsuario(mysqli $conexao, int $id, string $nome, string $usuario, ?string $senha, string $email, string $perfil = 'usuario', string $telefone = '', string $imgPerfil = ''): array
{
    if ($id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'ID inválido.'];
    }

    $nome = trim($nome);
    $usuario = trim($usuario);
    $email = trim($email);
    $perfil = strtolower(trim($perfil));
    $telefone = trim($telefone);
    $imgPerfil = trim($imgPerfil);

    $erros = [];
    if ($nome === '' || mb_strlen($nome) < 3) {
        $erros[] = 'Nome inválido.';
    }
    if ($usuario === '' || mb_strlen($usuario) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $usuario)) {
        $erros[] = 'Usuário inválido.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }
    if ($senha !== null && $senha !== '' && mb_strlen($senha) < 8) {
        $erros[] = 'Senha deve ter no mínimo 8 caracteres.';
    }
    if (!in_array($perfil, ['admin', 'usuario'], true)) {
        $perfil = 'usuario';
    }

    if (!empty($erros)) {
        return ['sucesso' => false, 'mensagem' => implode(' ', $erros)];
    }

    $check = $conexao->prepare('SELECT id FROM usuarios WHERE (usuario = ? OR email = ?) AND id <> ? LIMIT 1');
    if (!$check) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao validar dados.'];
    }
    $check->bind_param('ssi', $usuario, $email, $id);
    $check->execute();
    $exists = $check->get_result();
    $jaExiste = $exists && $exists->num_rows > 0;
    $check->close();

    if ($jaExiste) {
        return ['sucesso' => false, 'mensagem' => 'Usuário ou e-mail já em uso.'];
    }

    if ($senha !== null && $senha !== '') {
        $senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql = 'UPDATE usuarios SET nome = ?, usuario = ?, senha = ?, email = ?, telefone = ?, perfil = ?, img_perfil = ? WHERE id = ?';
        $stmt = $conexao->prepare($sql);
        if (!$stmt) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao preparar atualização.'];
        }
        $stmt->bind_param('sssssssi', $nome, $usuario, $senhaHash, $email, $telefone, $perfil, $imgPerfil, $id);
    } else {
        $sql = 'UPDATE usuarios SET nome = ?, usuario = ?, email = ?, telefone = ?, perfil = ?, img_perfil = ? WHERE id = ?';
        $stmt = $conexao->prepare($sql);
        if (!$stmt) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao preparar atualização.'];
        }
        $stmt->bind_param('ssssssi', $nome, $usuario, $email, $telefone, $perfil, $imgPerfil, $id);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['sucesso' => true, 'mensagem' => 'Usuário atualizado com sucesso.']
        : ['sucesso' => false, 'mensagem' => 'Erro ao atualizar usuário.'];
}

/**
 * @return array{sucesso:bool,mensagem:string}
 */
function excluirUsuario(mysqli $conexao, int $id): array
{
    if ($id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'ID inválido.'];
    }

    if (sdIsSelfDelete($_SESSION ?? [], $id)) {
        return ['sucesso' => false, 'mensagem' => 'Não é permitido excluir o próprio usuário logado.'];
    }

    $countStmt = $conexao->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE perfil = 'admin'");
    if (!$countStmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao validar administradores.'];
    }
    $countStmt->execute();
    $result = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $admins = (int) ($result['total'] ?? 0);
    $user = buscarUsuarioPorId($conexao, $id);
    if ($user && ($user['perfil'] ?? '') === 'admin' && $admins <= 1) {
        return ['sucesso' => false, 'mensagem' => 'Não é possível excluir o último administrador.'];
    }

    $stmt = $conexao->prepare('DELETE FROM usuarios WHERE id = ?');
    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar exclusão.'];
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        return ['sucesso' => false, 'mensagem' => 'Usuário não encontrado.'];
    }

    return ['sucesso' => true, 'mensagem' => 'Usuário excluído com sucesso.'];
}

/**
 * @return array<int,array<string,mixed>>
 */
function listarUsuarios(mysqli $conexao, int $limite = 1000, int $offset = 0): array
{
    if ($limite <= 0) {
        $limite = 1000;
    }
    if ($offset < 0) {
        $offset = 0;
    }

    $sql = 'SELECT id, nome, usuario, email, telefone, perfil, data_cadastro, status, img_perfil FROM usuarios ORDER BY nome ASC LIMIT ? OFFSET ?';
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $limite, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuarios = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }

    $stmt->close();
    return $usuarios;
}

/**
 * @return array<string,mixed>|null
 */
function buscarUsuarioPorId(mysqli $conexao, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $conexao->prepare('SELECT id, nome, usuario, email, telefone, perfil, data_cadastro, status, img_perfil FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * @return array<string,mixed>|null
 */
function buscarUsuarioPorLogin(mysqli $conexao, string $usuario): ?array
{
    $usuario = trim($usuario);
    if ($usuario === '') {
        return null;
    }

    $stmt = $conexao->prepare('SELECT id, nome, usuario, email, telefone, perfil, data_cadastro, status, img_perfil FROM usuarios WHERE usuario = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function contarUsuarios(mysqli $conexao): int
{
    $stmt = $conexao->prepare('SELECT COUNT(*) AS total FROM usuarios');
    if (!$stmt) {
        return 0;
    }

    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($result['total'] ?? 0);
}
?>
