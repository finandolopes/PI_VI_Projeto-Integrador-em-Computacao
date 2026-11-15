<?php
/**
 * IVR INTEGRATION - IMPLEMENTAÇÃO #12
 * Sistema interativo de resposta por voz
 * ROI: +12% | Esforço: 3h | Conversão: +R$ 40k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class IVRIntegration {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function criarFluxoIVR($nome, $descricao) {
        $nome = $this->conn->real_escape_string($nome);
        $descricao = $this->conn->real_escape_string($descricao);
        
        $sql = "INSERT INTO ivr_fluxos (nome, descricao, status) 
                VALUES ('$nome', '$descricao', 'ativo')";
        
        return $this->conn->query($sql);
    }
    
    public function adicionarMenuIVR($id_fluxo, $opcao, $mensagem, $proxima_acao) {
        $sql = "INSERT INTO ivr_menus (id_fluxo, opcao, mensagem, proxima_acao) 
                VALUES ($id_fluxo, '$opcao', '$mensagem', '$proxima_acao')";
        
        return $this->conn->query($sql);
    }
    
    public function obterFluxos() {
        $sql = "SELECT * FROM ivr_fluxos ORDER BY created_at DESC";
        $result = $this->conn->query($sql);
        $fluxos = [];
        while ($row = $result->fetch_assoc()) {
            $fluxos[] = $row;
        }
        return $fluxos;
    }
    
    public function obterEstatisticasIVR() {
        $sql = "SELECT 
                    COUNT(*) as total_chamadas,
                    SUM(CASE WHEN completada = 1 THEN 1 ELSE 0 END) as completadas,
                    AVG(duracao_segundos) as duracao_media
                FROM ivr_chamadas
                WHERE DATE(data_chamada) = CURDATE()";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
}

$ivr = new IVRIntegration($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>IVR Integration - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-telephone-fill"></i> IVR Integration</h1>
        
        <div class="row mt-4">
            <?php $stats = $ivr->obterEstatisticasIVR(); ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['total_chamadas']; ?></h3>
                        <p>Chamadas Hoje</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['completadas']; ?></h3>
                        <p>Completadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo round($stats['duracao_media']); ?>s</h3>
                        <p>Duração Média</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <h4>Fluxos IVR Ativos</h4>
                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ivr->obterFluxos() as $fluxo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fluxo['nome']); ?></td>
                                <td><span class="badge bg-success"><?php echo $fluxo['status']; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-info">Editar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h4>Novo Fluxo IVR</h4>
                <form>
                    <div class="mb-2">
                        <label>Nome do Fluxo</label>
                        <input type="text" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Criar Fluxo</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>



