<?php
include_once('../php/conexao.php');
include_once('../php/funcoes_requisicoes.php');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    header('Location: ../index.php'); exit();
}

function columnExists($conexao, $table, $column) {
    // SHOW COLUMNS não aceita placeholders, então usar query direta
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table); // Sanitizar nome tabela
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column); // Sanitizar nome coluna
    
    $query = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $result = $conexao->query($query);
    
    if (!$result) {
        return false;
    }
    
    $exists = $result->num_rows > 0;
    return $exists;
}

$hasOrigem = columnExists($conexao, 'requisicoes', 'origem');
$hasResponsavel = columnExists($conexao, 'requisicoes', 'responsavel');

$period = $_GET['period'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

if ($period === 'hoje') {
    $data_inicio = date('Y-m-d');
    $data_fim = date('Y-m-d');
} elseif ($period === '7dias') {
    $data_inicio = date('Y-m-d', strtotime('-7 days'));
    $data_fim = date('Y-m-d');
} elseif ($period === '30dias') {
    $data_inicio = date('Y-m-d', strtotime('-30 days'));
    $data_fim = date('Y-m-d');
}

$filtersActive = !empty($period) || !empty($categoria) || (!empty($data_inicio) && !empty($data_fim));

$selectExtras = '';
if ($hasOrigem) {
    $selectExtras .= ', r.origem';
}
if ($hasResponsavel) {
    $selectExtras .= ', r.responsavel';
}

$sql = "SELECT r.id_requisicao, c.nome AS nome_cliente, c.email, c.telefone, r.tipo, r.categoria, r.horario_contato, r.data_requisicao, r.status$selectExtras FROM requisicoes r INNER JOIN clientes c ON r.id_cliente = c.id_cliente";
$conditions = [];
$params = [];
$types = '';

if (!empty($data_inicio) && !empty($data_fim)) {
    $conditions[] = 'r.data_requisicao BETWEEN ? AND ?';
    $types .= 'ss';
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

if (!empty($categoria)) {
    $conditions[] = 'r.categoria LIKE ?';
    $types .= 's';
    $params[] = '%' . $categoria . '%';
}

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY r.data_requisicao DESC';

$stmt = $conexao->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar'])) {
    $requisicoes = [];
    while ($row = $result->fetch_assoc()) {
        $requisicoes[] = $row;
    }

    if (!empty($requisicoes)) {
        $xml = new SimpleXMLElement('<requisicoes></requisicoes>');
        foreach ($requisicoes as $requisicao) {
            $requisicaoXML = $xml->addChild('requisicao');
            $requisicaoXML->addChild('id', $requisicao['id_requisicao']);
            $requisicaoXML->addChild('nome', $requisicao['nome_cliente']);
            $requisicaoXML->addChild('email', $requisicao['email']);
            $requisicaoXML->addChild('telefone', $requisicao['telefone']);
            $requisicaoXML->addChild('horario_contato', $requisicao['horario_contato']);
            $requisicaoXML->addChild('tipo', $requisicao['tipo']);
            $requisicaoXML->addChild('categoria', $requisicao['categoria']);
            $requisicaoXML->addChild('data_requisicao', $requisicao['data_requisicao']);
            $requisicaoXML->addChild('status', $requisicao['status'] ?? '');
            if ($hasOrigem) {
                $requisicaoXML->addChild('origem', $requisicao['origem']);
            }
            if ($hasResponsavel) {
                $requisicaoXML->addChild('responsavel', $requisicao['responsavel']);
            }
        }

        header('Content-Disposition: attachment; filename="requisicoes.xml"');
        header('Content-Type: text/xml');
        echo $xml->asXML();
        exit;
    }
}
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
    <?php include 'navbar.php'; ?>
    

    <div class="page-wrapper">
        <div class="content container-fluid">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-sm-8">
                        <h3 class="page-title">Lista de Requisi��es</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Requisi��es</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="table-filters">
                <div class="quick-filters" role="group" aria-label="Filtros rápidos">
                    <a class="btn btn-outline-primary btn-sm" href="requisicoes.php?period=hoje">Hoje</a>
                    <a class="btn btn-outline-primary btn-sm" href="requisicoes.php?period=7dias">7 dias</a>
                    <a class="btn btn-outline-primary btn-sm" href="requisicoes.php?period=30dias">30 dias</a>
                </div>
                <a class="btn btn-link btn-sm" href="requisicoes.php">Limpar filtros</a>
            </div>

            <form method="get" class="filter-form" aria-label="Filtrar requisições por período">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                <div class="filter-group">
                    <label for="data_inicio">Data de Início:</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>">
                </div>
                <div class="filter-group">
                    <label for="data_fim">Data Fim:</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>">
                </div>
                <div class="filter-group">
                    <label for="categoria">Categoria:</label>
                    <select id="categoria" name="categoria" class="form-select">
                        <option value="">Todas</option>
                        <option value="Aposentado" <?php echo $categoria === 'Aposentado' ? 'selected' : ''; ?>>Aposentado</option>
                        <option value="Pensionista" <?php echo $categoria === 'Pensionista' ? 'selected' : ''; ?>>Pensionista</option>
                        <option value="Servidor Público" <?php echo $categoria === 'Servidor Público' ? 'selected' : ''; ?>>Servidor Público</option>
                        <option value="Outros" <?php echo $categoria === 'Outros' ? 'selected' : ''; ?>>Outros</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a class="btn btn-outline-secondary" href="requisicoes.php">Limpar</a>
                </div>
            </form>

            <div class="export-options">
                <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" name="exportar" class="btn btn-primary">Exportar XML</button>
                    <i class="fa fa-print" style="font-size: 24px;" onclick="window.print()" aria-hidden="true"></i> Imprimir
                </form>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table datanew">
                                    <thead>
                                        <tr>
                                            <th>Nome do Cliente</th>
                                            <th>E-mail</th>
                                            <th>Telefone</th>
                                            <th>Tipo</th>
                                            <th>Categoria</th>
                                            <?php if ($hasOrigem) { ?><th>Origem</th><?php } ?>
                                            <?php if ($hasResponsavel) { ?><th>Responsável</th><?php } ?>
                                            <th>Horário para Contato</th>
                                            <th>Data</th>
                                            <th>Status</th>
                                            <th>Resumo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $hoje = date('Y-m-d');
                                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $statusRaw = $row['status'] ?? 'pendente';
                                                if ($statusRaw === 'pendente' && $row['data_requisicao'] === $hoje) {
                                                    $statusLabel = 'Novo';
                                                    $statusClass = 'status-badge status-new';
                                                } elseif ($statusRaw === 'em_analise') {
                                                    $statusLabel = 'Em análise';
                                                    $statusClass = 'status-badge status-progress';
                                                } elseif (in_array($statusRaw, ['aprovado', 'reprovado', 'cancelado'], true)) {
                                                    $statusLabel = 'Finalizado';
                                                    $statusClass = 'status-badge status-final';
                                                } else {
                                                    $statusLabel = 'Pendente';
                                                    $statusClass = 'status-badge status-default';
                                                }

                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['nome_cliente']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['telefone']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['tipo']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['categoria']) . "</td>";
                                                if ($hasOrigem) {
                                                    echo "<td>" . htmlspecialchars($row['origem']) . "</td>";
                                                }
                                                if ($hasResponsavel) {
                                                    echo "<td>" . htmlspecialchars($row['responsavel']) . "</td>";
                                                }
                                                echo "<td>" . htmlspecialchars($row['horario_contato']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['data_requisicao']) . "</td>";
                                                echo '<td><span class="' . htmlspecialchars($statusClass) . '">' . htmlspecialchars($statusLabel) . '</span></td>';
                                                $resumoData = [
                                                    'nome' => $row['nome_cliente'],
                                                    'email' => $row['email'],
                                                    'telefone' => $row['telefone'],
                                                    'tipo' => $row['tipo'],
                                                    'categoria' => $row['categoria'],
                                                    'horario' => $row['horario_contato'],
                                                    'data' => $row['data_requisicao'],
                                                    'status' => $statusLabel,
                                                    'origem' => $hasOrigem ? ($row['origem'] ?? '') : '',
                                                    'responsavel' => $hasResponsavel ? ($row['responsavel'] ?? '') : ''
                                                ];
                                                echo '<td><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resumoModal" data-resumo="' . htmlspecialchars(json_encode($resumoData), ENT_QUOTES, 'UTF-8') . '">Ver</button></td>';
                                                echo "</tr>";
                                            }
                                        } else {
                                            $colspan = 9 + ($hasOrigem ? 1 : 0) + ($hasResponsavel ? 1 : 0);
                                            echo '<tr><td colspan="' . $colspan . '"><div class="empty-state">Nenhuma requisição encontrada.';
                                            if ($filtersActive) {
                                                echo ' <a class="btn btn-sm btn-outline-primary ms-2" href="requisicoes.php">Ver todas as requisições</a>';
                                            }
                                            echo "</div></td></tr>";
                                        }

                                        $stmt->close();
                                        $conexao->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="resumoModal" tabindex="-1" aria-labelledby="resumoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resumoModalLabel">Resumo da requisição</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Cliente</dt><dd class="col-7" data-field="nome"></dd>
                        <dt class="col-5">E-mail</dt><dd class="col-7" data-field="email"></dd>
                        <dt class="col-5">Telefone</dt><dd class="col-7" data-field="telefone"></dd>
                        <dt class="col-5">Tipo</dt><dd class="col-7" data-field="tipo"></dd>
                        <dt class="col-5">Categoria</dt><dd class="col-7" data-field="categoria"></dd>
                        <dt class="col-5">Horário</dt><dd class="col-7" data-field="horario"></dd>
                        <dt class="col-5">Data</dt><dd class="col-7" data-field="data"></dd>
                        <dt class="col-5">Status</dt><dd class="col-7" data-field="status"></dd>
                        <div id="resumo-extra"></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('resumoModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
          const button = event.relatedTarget;
          if (!button) return;
          const data = button.getAttribute('data-resumo');
          if (!data) return;
          let resumo = {};
          try { resumo = JSON.parse(data); } catch (e) { return; }
          modal.querySelectorAll('[data-field]').forEach((el) => {
            const key = el.getAttribute('data-field');
            el.textContent = resumo[key] || '-';
          });
          const extra = modal.querySelector('#resumo-extra');
          if (extra) {
            let html = '';
            if (resumo.origem) {
              html += `<dt class="col-5">Origem</dt><dd class="col-7">${resumo.origem}</dd>`;
            }
            if (resumo.responsavel) {
              html += `<dt class="col-5">Responsável</dt><dd class="col-7">${resumo.responsavel}</dd>`;
            }
            extra.innerHTML = html;
          }
        });
      });
    </script>

    <?php include 'footer.php'; ?>
<script src="assets/js/jquery-3.6.0.min.js"></script>
<script src="assets/js/dataTables.min.js"></script>
<script src="assets/js/dataTables.bootstrap4.min.js"></script>
<script>
(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDataTable);
    } else {
        initDataTable();
    }

    function initDataTable() {
        if ($.fn.DataTable.isDataTable('.datanew')) {
            return;
        }
        
        $('.datanew').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
            },
            "pageLength": 25,
            "ordering": true,
            "searching": true,
            "paging": true,
            "info": true
        });
    }
})();
</script>
</body>
</html>


