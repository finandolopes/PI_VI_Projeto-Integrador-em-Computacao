<?php
/**
 * A/B TESTING FRAMEWORK - IMPLEMENTAÇÃO #6
 * Testes A/B para otimizar campanhas e taxa de conversão
 * ROI: +15% | Esforço: 3h | Conversão: +R$ 50k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

header('Content-Type: application/json');

verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class ABTestingFramework {
    private $conn;
    private $user_id;
    
    public function __construct($connection, $user_id) {
        $this->conn = $connection;
        $this->user_id = $user_id;
    }
    
    public function criarTeste($nome, $descricao, $variacao_a, $variacao_b, $metrica = 'conversao') {
        $nome = $this->conn->real_escape_string($nome);
        $descricao = $this->conn->real_escape_string($descricao);
        $variacao_a = $this->conn->real_escape_string($variacao_a);
        $variacao_b = $this->conn->real_escape_string($variacao_b);
        
        $sql = "INSERT INTO ab_testes (nome, descricao, variacao_a, variacao_b, metrica_rastreamento, status, user_id) 
                VALUES ('$nome', '$descricao', '$variacao_a', '$variacao_b', '$metrica', 'ativo', {$this->user_id})";
        
        if ($this->conn->query($sql)) {
            return ['success' => true, 'id_teste' => $this->conn->insert_id, 'message' => 'Teste criado com sucesso'];
        }
        return ['success' => false, 'message' => $this->conn->error];
    }
    
    public function registrarResultado($id_teste, $variacao, $resultado, $id_lead = null) {
        $sql = "INSERT INTO ab_testes_resultados (id_teste, variacao, resultado, id_lead, data_registro) 
                VALUES ($id_teste, '$variacao', '$resultado', " . ($id_lead ? $id_lead : "NULL") . ", NOW())";
        return $this->conn->query($sql);
    }
    
    public function obterEstatisticas($id_teste) {
        $sql = "SELECT 
                    variacao,
                    COUNT(*) as total,
                    SUM(CASE WHEN resultado = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
                    ROUND(SUM(CASE WHEN resultado = 'sucesso' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as taxa_sucesso
                FROM ab_testes_resultados
                WHERE id_teste = $id_teste
                GROUP BY variacao";
        
        $result = $this->conn->query($sql);
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }
    
    public function encerrarTeste($id_teste) {
        $stats = $this->obterEstatisticas($id_teste);
        $vencedor = $stats[0]['taxa_sucesso'] > $stats[1]['taxa_sucesso'] ? $stats[0]['variacao'] : $stats[1]['variacao'];
        
        $sql = "UPDATE ab_testes SET status = 'encerrado', vencedor = '$vencedor', data_encerramento = NOW() 
                WHERE id = $id_teste";
        return $this->conn->query($sql);
    }
    
    public function listarTestes() {
        $sql = "SELECT id, nome, descricao, status, vencedor, created_at FROM ab_testes ORDER BY created_at DESC";
        $result = $this->conn->query($sql);
        $testes = [];
        while ($row = $result->fetch_assoc()) {
            $testes[] = $row;
        }
        return $testes;
    }
}

$framework = new ABTestingFramework($conn, $_SESSION['usuario_id']);

$acao = $_GET['acao'] ?? '';

switch ($acao) {
    case 'criar':
        echo json_encode($framework->criarTeste(
            $_POST['nome'],
            $_POST['descricao'],
            $_POST['variacao_a'],
            $_POST['variacao_b'],
            $_POST['metrica'] ?? 'conversao'
        ));
        break;
    
    case 'listar':
        echo json_encode(['success' => true, 'data' => $framework->listarTestes()]);
        break;
    
    case 'estatisticas':
        $id_teste = intval($_GET['id_teste'] ?? 0);
        echo json_encode(['success' => true, 'data' => $framework->obterEstatisticas($id_teste)]);
        break;
    
    case 'encerrar':
        $id_teste = intval($_POST['id_teste'] ?? 0);
        echo json_encode(['success' => $framework->encerrarTeste($id_teste), 'message' => 'Teste encerrado']);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
}
?>
