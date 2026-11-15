<?php
/**
 * Dashboard Metrics Engine
 * Fase 2: Lead Management & CRM
 * 
 * Real-time KPIs:
 * - Conversion rate by status/period
 * - Average lead quality score
 * - Assignment ratio (leads assigned vs pending)
 * - Sales team response time
 * - Lead source attribution
 * 
 * @author CONFINTER
 * @version 1.0
 */

class DashboardMetrics {
    
    private $db;
    private $cacheEnabled = true;
    private $cacheDuration = 300; // 5 minutos
    
    public function __construct($db, $cacheEnabled = true) {
        $this->db = $db;
        $this->cacheEnabled = $cacheEnabled;
    }
    
    /**
     * ObtÃ©m dashboard completo com todas as mÃ©tricas
     */
    public function getDashboard($period = 'today') {
        $cacheKey = "dashboard_metrics_{$period}";
        
        // Tentar obter do cache
        if ($this->cacheEnabled) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached) {
                return $cached;
            }
        }
        
        $dashboard = [
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'metrics' => [
                'conversion' => $this->getConversionMetrics($period),
                'leads' => $this->getLeadMetrics($period),
                'team' => $this->getTeamMetrics($period),
                'quality' => $this->getQualityMetrics($period),
                'source' => $this->getSourceMetrics($period),
                'performance' => $this->getPerformanceMetrics($period)
            ]
        ];
        
        // Guardar em cache
        if ($this->cacheEnabled) {
            $this->setInCache($cacheKey, $dashboard, $this->cacheDuration);
        }
        
        return $dashboard;
    }
    
    /**
     * MÃ©tricas de conversÃ£o
     */
    private function getConversionMetrics($period) {
        $dateFilter = $this->getDateFilter($period);
        
        // Total de leads no perÃ­odo
        $sql = "SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as converted_leads,
                    ROUND(SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as conversion_rate,
                    AVG(DATEDIFF(status_won_at, created_at)) as avg_days_to_conversion
                FROM leads
                WHERE created_at {$dateFilter['operator']} :date_from
                    AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFilter['value']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_leads' => (int)$result['total_leads'],
            'converted_leads' => (int)$result['converted_leads'],
            'conversion_rate' => (float)$result['conversion_rate'],
            'avg_days_to_conversion' => round($result['avg_days_to_conversion'] ?? 0, 1)
        ];
    }
    
    /**
     * MÃ©tricas de leads por status
     */
    private function getLeadMetrics($period) {
        $dateFilter = $this->getDateFilter($period);
        
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    ROUND(COUNT(*) / (SELECT COUNT(*) FROM leads WHERE created_at {$dateFilter['operator']} :date_from AND deleted_at IS NULL) * 100, 2) as percentage
                FROM leads
                WHERE created_at {$dateFilter['operator']} :date_from
                    AND deleted_at IS NULL
                GROUP BY status
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFilter['value']]);
        
        $byStatus = [];
        $total = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byStatus[$row['status']] = [
                'count' => (int)$row['count'],
                'percentage' => (float)$row['percentage']
            ];
            $total += $row['count'];
        }
        
        // EstatÃ­sticas de assignment
        $sql = "SELECT 
                    SUM(CASE WHEN assigned_to IS NOT NULL THEN 1 ELSE 0 END) as assigned,
                    SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned
                FROM leads
                WHERE created_at {$dateFilter['operator']} :date_from
                    AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFilter['value']]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'by_status' => $byStatus,
            'assigned' => (int)$assignment['assigned'],
            'unassigned' => (int)$assignment['unassigned'],
            'assignment_rate' => $total > 0 ? round(($assignment['assigned'] / $total) * 100, 2) : 0
        ];
    }
    
    /**
     * MÃ©tricas do time de vendas
     */
    private function getTeamMetrics($period) {
        $dateFilter = $this->getDateFilter($period);
        
        $sql = "SELECT 
                    u.id,
                    u.name,
                    COUNT(DISTINCT l.id) as total_leads,
                    SUM(CASE WHEN l.status = 'won' THEN 1 ELSE 0 END) as won_leads,
                    ROUND(SUM(CASE WHEN l.status = 'won' THEN 1 ELSE 0 END) / COUNT(DISTINCT l.id) * 100, 2) as conversion_rate,
                    AVG(l.lead_score) as avg_lead_score,
                    AVG(TIME_TO_SEC(TIMEDIFF(COALESCE(l.first_contact_at, NOW()), l.assigned_at))) / 3600 as avg_response_hours
                FROM users u
                LEFT JOIN leads l ON l.assigned_to = u.id 
                    AND l.created_at {$dateFilter['operator']} :date_from
                    AND l.deleted_at IS NULL
                WHERE u.role = 'sales' AND u.deleted_at IS NULL
                GROUP BY u.id
                ORDER BY won_leads DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFilter['value']]);
        
        $team = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $team[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'total_leads' => (int)$row['total_leads'],
                'won_leads' => (int)$row['won_leads'],
                'conversion_rate' => (float)$row['conversion_rate'],
                'avg_lead_score' => round($row['avg_lead_score'] ?? 0, 1),
                'avg_response_hours' => round($row['avg_response_hours'] ?? 0, 1)
            ];
        }
        
        return $team;
    }
    
    /**
     * MÃ©tricas de qualidade de leads
     */
    private function getQualityMetrics($period) {
        $dateFilter = $this->getDateFilter($period);
        
        // Score distribution
        $sql = "SELECT 
                    SUM(CASE WHEN lead_score >= 80 THEN 1 ELSE 0 END) as hot_leads,
                    SUM(CASE WHEN lead_score BETWEEN 60 AND 79 THEN 1 ELSE 0 END) as warm_leads,
                    SUM(CASE WHEN lead_score BETWEEN 40 AND 59 THEN 1 ELSE 0 END) as cool_leads,
                    SUM(CASE WHEN lead_score < 40 THEN 1 ELSE 0 END) as cold_leads,
                    ROUND(AVG(lead_score), 2) as avg_score
                FROM leads
                WHERE created_at {$dateFilter['operator']} :date_from
                    AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFilter['value']]);
        $quality = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total = ($quality['hot_leads'] ?? 0) + ($quality['warm_leads'] ?? 0) + 
                 ($quality['cool_leads'] ?? 0) + ($quality['cold_leads'] ?? 0);
        
        return [
            'hot_leads' => (int)($quality['hot_leads'] ?? 0),
            'warm_leads' => (int)($quality['warm_leads'] ?? 0),
            'cool_leads' => (int)($quality['cool_leads'] ?? 0),
            'cold_leads' => (int)($quality['cold_leads'] ?? 0),
            'avg_score' => (float)($quality['avg_score'] ?? 0),
            'distribution' => [
                'hot' => $total > 0 ? round((($quality['hot_leads'] ?? 0) / $total) * 100, 2) : 0,
                'warm' => $total > 0 ? round((($quality['warm_leads'] ?? 0) / $total) * 100, 2) : 0,
                'cool' => $total > 0 ? round((($quality['cool_leads'] ?? 0) / $total) * 100, 2) : 0,
                'cold' => $total > 0 ? round((($quality['cold_leads'] ?? 0) / $total) * 100, 2) : 0
            ]
        ];
    }
    
    /**
     * MÃ©tricas por fonte
     */
    private function getSourceMetrics($period) {
        $dateFilter = $this->getDateFilter($period);
        
        $sql = "SELECT 
                    source,
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as converted,
                    ROUND(SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as conversion_rate,
                    ROUND(AVG(lead_score), 2) as avg_score
                FROM leads
                WHERE created_at {$dateFilter['operator']} :date_from
                    AND deleted_at IS NULL
                GROUP BY source
                ORDER BY conversion_rate DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFilter['value']]);
        
        $bySource = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bySource[$row['source'] ?? 'unknown'] = [
                'total_leads' => (int)$row['total_leads'],
                'converted' => (int)$row['converted'],
                'conversion_rate' => (float)$row['conversion_rate'],
                'avg_score' => (float)$row['avg_score']
            ];
        }
        
        return $bySource;
    }
    
    /**
     * MÃ©tricas de performance
     */
    private function getPerformanceMetrics($period) {
        $dateFilter = $this->getDateFilter($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_interactions,
                    SUM(CASE WHEN interaction_type = 'call' THEN 1 ELSE 0 END) as calls,
                    SUM(CASE WHEN interaction_type = 'email' THEN 1 ELSE 0 END) as emails,
                    SUM(CASE WHEN interaction_type = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp
                FROM lead_interactions
                WHERE created_at {$dateFilter['operator']} :date_from";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFilter['value']]);
        $interactions = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_interactions' => (int)($interactions['total_interactions'] ?? 0),
            'calls' => (int)($interactions['calls'] ?? 0),
            'emails' => (int)($interactions['emails'] ?? 0),
            'whatsapp' => (int)($interactions['whatsapp'] ?? 0)
        ];
    }
    
    /**
     * ObtÃ©m filtro de data baseado no perÃ­odo
     */
    private function getDateFilter($period) {
        $operators = [
            'today' => [
                'operator' => '>=',
                'value' => date('Y-m-d 00:00:00')
            ],
            'yesterday' => [
                'operator' => 'BETWEEN',
                'value' => date('Y-m-d 00:00:00', strtotime('-1 day'))
            ],
            'week' => [
                'operator' => '>=',
                'value' => date('Y-m-d 00:00:00', strtotime('-7 days'))
            ],
            'month' => [
                'operator' => '>=',
                'value' => date('Y-m-01 00:00:00')
            ],
            'quarter' => [
                'operator' => '>=',
                'value' => date('Y-m-d 00:00:00', strtotime('-90 days'))
            ],
            'year' => [
                'operator' => '>=',
                'value' => date('Y-01-01 00:00:00')
            ]
        ];
        
        return $operators[$period] ?? $operators['today'];
    }
    
    /**
     * ObtÃ©m dados do cache
     */
    private function getFromCache($key) {
        if (!isset($_SESSION['metrics_cache'])) {
            return null;
        }
        
        $cached = $_SESSION['metrics_cache'][$key] ?? null;
        if ($cached && time() - $cached['timestamp'] < $this->cacheDuration) {
            return $cached['data'];
        }
        
        return null;
    }
    
    /**
     * Salva dados em cache
     */
    private function setInCache($key, $data, $ttl) {
        if (!isset($_SESSION['metrics_cache'])) {
            $_SESSION['metrics_cache'] = [];
        }
        
        $_SESSION['metrics_cache'][$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    /**
     * ObtÃ©m grÃ¡fico de conversÃ£o por dia (Ãºltimos 30 dias)
     */
    public function getConversionTrendLine($days = 30) {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as converted,
                    ROUND(SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as conversion_rate
                FROM leads
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND deleted_at IS NULL
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m comparaÃ§Ã£o com perÃ­odo anterior
     */
    public function getComparison($period = 'month') {
        $current = $this->getConversionMetrics($period);
        
        // PerÃ­odo anterior
        $previousPeriod = $period === 'today' ? 'yesterday' : $period;
        $previous = $this->getConversionMetrics($previousPeriod);
        
        return [
            'current' => $current,
            'previous' => $previous,
            'change' => [
                'leads_change' => $current['total_leads'] - $previous['total_leads'],
                'conversion_change' => round($current['conversion_rate'] - $previous['conversion_rate'], 2)
            ]
        ];
    }
}

// API Endpoint para dashboard
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'dashboard') {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $metrics = new DashboardMetrics($db);
        $period = $_GET['period'] ?? 'today';
        
        $dashboard = $metrics->getDashboard($period);
        
        header('Content-Type: application/json');
        echo json_encode($dashboard);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>
