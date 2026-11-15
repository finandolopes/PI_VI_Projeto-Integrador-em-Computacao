<?php
/**
 * REWARD SYSTEM - IMPLEMENTAÇÃO #27
 * Sistema de gamificação e recompensas
 * ROI: +7% | Esforço: 2h | Conversão: +R$ 23k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class RewardSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function obterPontos($user_id) {
        $sql = "SELECT pontos FROM usuario_pontos WHERE user_id = $user_id";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        return $row['pontos'] ?? 0;
    }
    
    public function adicionarPontos($user_id, $pontos, $motivo) {
        $sql = "INSERT INTO usuario_pontos_historico (user_id, pontos, motivo, data) 
                VALUES ($user_id, $pontos, '{$this->conn->real_escape_string($motivo)}', NOW())";
        
        if ($this->conn->query($sql)) {
            $total = $this->obterPontos($user_id) + $pontos;
            $this->conn->query("UPDATE usuario_pontos SET pontos = $total WHERE user_id = $user_id");
            return true;
        }
        return false;
    }
    
    public function obterPremios() {
        $sql = "SELECT id, nome, pontos_necessarios, descricao FROM premios ORDER BY pontos_necessarios ASC";
        $result = $this->conn->query($sql);
        $premios = [];
        while ($row = $result->fetch_assoc()) {
            $premios[] = $row;
        }
        return $premios;
    }
    
    public function obterRanking() {
        $sql = "SELECT u.nome, up.pontos FROM usuario_pontos up 
                JOIN usuarios u ON up.user_id = u.id 
                ORDER BY up.pontos DESC LIMIT 20";
        
        $result = $this->conn->query($sql);
        $ranking = [];
        while ($row = $result->fetch_assoc()) {
            $ranking[] = $row;
        }
        return $ranking;
    }
}

$reward = new RewardSystem($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Reward System - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-trophy"></i> Reward System</h1>
        
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" href="#ranking" data-bs-toggle="tab">Ranking</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#premios" data-bs-toggle="tab">Prêmios</a>
            </li>
        </ul>
        
        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="ranking">
                <table class="table">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Operador</th>
                            <th>Pontos</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($reward->obterRanking() as $item): ?>
                            <tr>
                                <td><strong><?php echo $i++; ?></strong></td>
                                <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                <td><?php echo $item['pontos']; ?> pts</td>
                                <td>
                                    <?php if ($item['pontos'] > 1000): ?>
                                        <span class="badge bg-gold">?? TOP</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="tab-pane fade" id="premios">
                <div class="row">
                    <?php foreach ($reward->obterPremios() as $premio): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($premio['nome']); ?></h5?>
                                    <p class="card-text"><?php echo htmlspecialchars($premio['descricao']); ?></p>
                                    <p><strong><?php echo $premio['pontos_necessarios']; ?> Pontos</strong></p>
                                    <button class="btn btn-sm btn-primary">Resgatar</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>



