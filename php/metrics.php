<?php
// Métricas centralizadas para dashboard e admin

function fetchSingleValue($conexao, $sql) {
    $result = mysqli_query($conexao, $sql);
    if (!$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        return null;
    }
    return array_values($row)[0];
}

function getDashboardMetrics($conexao) {
    $totalVisitas = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) as total FROM contador_visitas") ?? 0);
    $totalRequisicoes = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) as total FROM requisicoes") ?? 0);
    $totalClientes = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) as total FROM clientes") ?? 0);
    $totalDepoimentos = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) as total FROM depoimentos") ?? 0);
    $mediaTempo = fetchSingleValue($conexao, "SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo))) AS media_tempo FROM contador_visitas");
    $mediaTempo = $mediaTempo ?: '00:00:00';

    $taxaRequisicoes = $totalClientes > 0 ? round(($totalRequisicoes / $totalClientes) * 100, 1) : 0;
    $taxaConversao = $totalVisitas > 0 ? round(($totalRequisicoes / $totalVisitas) * 100, 2) : 0;

    return [
        'total_visitas' => $totalVisitas,
        'total_requisicoes' => $totalRequisicoes,
        'total_clientes' => $totalClientes,
        'total_depoimentos' => $totalDepoimentos,
        'media_tempo' => $mediaTempo,
        'taxa_requisicoes' => $taxaRequisicoes,
        'taxa_conversao' => $taxaConversao,
    ];
}

// ========================================
// RASTREAMENTO DE VISITANTES
// ========================================

function rastrearVisitante($conexao, $pagina = '/') {
    if (!$conexao) {
        return false;
    }
    
    // Dados do visitante
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'], 0, 255);
    $sessao_id = session_id();
    $referer = isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 500) : null;
    $data_visita = date('Y-m-d H:i:s');
    
    // Extrair informações do navegador e dispositivo
    $dispositivo = extrairDispositivoUA($user_agent);
    $navegador = extrairNavegadorUA($user_agent);
    
    // Inserir na tabela contador_visitas
    $sql = "INSERT INTO contador_visitas (
                ip_address, user_agent, pagina, sessao_id, referer, 
                navegador, dispositivo, data_visita
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar rastreamento de visitante: " . $conexao->error);
        return false;
    }
    
    $stmt->bind_param("ssssssss", $ip_address, $user_agent, $pagina, $sessao_id, $referer, $navegador, $dispositivo, $data_visita);
    
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

function extrairDispositivoUA($user_agent) {
    if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'Android') !== false) {
        return 'Mobile';
    } elseif (strpos($user_agent, 'iPad') !== false || strpos($user_agent, 'Tablet') !== false) {
        return 'Tablet';
    }
    return 'Desktop';
}

function extrairNavegadorUA($user_agent) {
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    return 'Outro';
}

// ========================================
// RASTREAMENTO DE REQUISIÇÕES
// ========================================

function rastrearRequisicao($conexao, $id_requisicao, $tipo_requisicao = 'analise_credito') {
    if (!$conexao || !$id_requisicao) {
        return false;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'], 0, 255);
    $data_hora = date('Y-m-d H:i:s');
    $status = 'nova';
    
    // Inserir log de rastreamento
    $sql = "INSERT INTO logs_sistema (
                acao, tabela_afetada, registro_id, 
                dados_novos, ip_address, user_agent, tipo, data_hora
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar rastreamento de requisição: " . $conexao->error);
        return false;
    }
    
    $acao = "Requisição de $tipo_requisicao registrada";
    $tabela = 'requisicoes';
    $tipo = 'INSERT';
    $dados_novos = json_encode(['id_requisicao' => $id_requisicao, 'tipo' => $tipo_requisicao, 'status' => $status]);
    
    $stmt->bind_param("ssissss", $acao, $tabela, $id_requisicao, $dados_novos, $ip_address, $user_agent, $tipo, $data_hora);
    
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

// ========================================
// RELATÓRIOS POR PERÍODO
// ========================================

function getRequisicoesPorPeriodo($conexao, $data_inicio, $data_fim) {
    $sql = "SELECT 
                COUNT(*) as total,
                DATE(data_hora) as data,
                tipo as tipo_requisicao
            FROM requisicoes 
            WHERE data_hora >= ? AND data_hora <= ?
            GROUP BY DATE(data_hora), tipo
            ORDER BY data DESC";
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ss", $data_inicio, $data_fim);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $requisicoes = [];
    while ($row = $resultado->fetch_assoc()) {
        $requisicoes[] = $row;
    }
    
    $stmt->close();
    return $requisicoes;
}

function getVisitasPorPeriodo($conexao, $data_inicio, $data_fim) {
    $sql = "SELECT 
                COUNT(*) as total,
                DATE(data_visita) as data,
                navegador,
                dispositivo
            FROM contador_visitas 
            WHERE data_visita >= ? AND data_visita <= ?
            GROUP BY DATE(data_visita), navegador, dispositivo
            ORDER BY data DESC";
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ss", $data_inicio, $data_fim);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $visitas = [];
    while ($row = $resultado->fetch_assoc()) {
        $visitas[] = $row;
    }
    
    $stmt->close();
    return $visitas;
}

function getVisitantesUnicos($conexao, $dias = 7) {
    $data_inicio = date('Y-m-d H:i:s', strtotime("-$dias days"));
    
    $sql = "SELECT 
                COUNT(DISTINCT ip_address) as visitantes_unicos,
                COUNT(DISTINCT sessao_id) as sessoes,
                DATE(data_visita) as data
            FROM contador_visitas 
            WHERE data_visita >= ?
            GROUP BY DATE(data_visita)
            ORDER BY data DESC";
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("s", $data_inicio);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $visitantes = [];
    while ($row = $resultado->fetch_assoc()) {
        $visitantes[] = $row;
    }
    
    $stmt->close();
    return $visitantes;
}

// ========================================
// DEPOIMENTOS E AVALIAÇÕES
// ========================================

function getDepoimentosRecentes($conexao, $limite = 5) {
    $sql = "SELECT 
                id, nome_cliente, mensagem, avaliacao, 
                data_envio, status_mod
            FROM depoimentos 
            WHERE aprovado = 1
            ORDER BY data_envio DESC
            LIMIT ?";
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $limite);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $depoimentos = [];
    while ($row = $resultado->fetch_assoc()) {
        $depoimentos[] = $row;
    }
    
    $stmt->close();
    return $depoimentos;
}

function getAvaliacaoMedia($conexao) {
    $sql = "SELECT 
                ROUND(AVG(avaliacao), 1) as media_avaliacoes,
                COUNT(*) as total_avaliacoes,
                SUM(CASE WHEN avaliacao = 5 THEN 1 ELSE 0 END) as avaliacoes_5_estrelas,
                SUM(CASE WHEN avaliacao = 4 THEN 1 ELSE 0 END) as avaliacoes_4_estrelas,
                SUM(CASE WHEN avaliacao = 3 THEN 1 ELSE 0 END) as avaliacoes_3_estrelas,
                SUM(CASE WHEN avaliacao = 2 THEN 1 ELSE 0 END) as avaliacoes_2_estrelas,
                SUM(CASE WHEN avaliacao = 1 THEN 1 ELSE 0 END) as avaliacoes_1_estrela
            FROM depoimentos 
            WHERE aprovado = 1 AND avaliacao IS NOT NULL";
    
    $resultado = mysqli_query($conexao, $sql);
    if (!$resultado) {
        return null;
    }
    
    return mysqli_fetch_assoc($resultado);
}

// ========================================
// ANÁLISE DE PERFORMANCE
// ========================================

function getMetricasPerformance($conexao) {
    $totalVisitas = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) FROM contador_visitas") ?? 0);
    $totalRequisicoes = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) FROM requisicoes") ?? 0);
    $visitasDia = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) FROM contador_visitas WHERE DATE(data_visita) = CURDATE()") ?? 0);
    $requisicoesHoje = (int) (fetchSingleValue($conexao, "SELECT COUNT(*) FROM requisicoes WHERE DATE(data_hora) = CURDATE()") ?? 0);
    
    $taxaConversao = $totalVisitas > 0 ? round(($totalRequisicoes / $totalVisitas) * 100, 2) : 0;
    $mediaRequisicoesPorDia = round($totalRequisicoes / max(1, fetchSingleValue($conexao, "SELECT DATEDIFF(MAX(data_hora), MIN(data_hora)) FROM requisicoes") ?? 1), 2);
    
    return [
        'total_visitas' => $totalVisitas,
        'total_requisicoes' => $totalRequisicoes,
        'visitas_hoje' => $visitasDia,
        'requisicoes_hoje' => $requisicoesHoje,
        'taxa_conversao' => $taxaConversao,
        'media_requisicoes_dia' => $mediaRequisicoesPorDia,
    ];
}

?>
