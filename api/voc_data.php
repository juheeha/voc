<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리 (CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    $fgs_db = new PDO("mysql:host=118.130.18.129;dbname=c_fgs_gd;charset=utf8", "fgs027", "cafe24@001");
    $fgs_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 요청된 데이터 타입 확인
    $type = $_GET['type'] ?? 'all';

    $response = [];

    switch($type) {
        case 'reason_stats':
            $response = getReasonStats($fgs_db);
            break;
        case 'monthly_trend':
            $response = getMonthlyTrend($fgs_db);
            break;
        case 'hourly_distribution':
            $response = getHourlyDistribution($fgs_db);
            break;
        case 'customer_analysis':
            $response = getCustomerTypeAnalysis($fgs_db);
            break;
        case 'improvement_priority':
            $response = getImprovementPriority($fgs_db);
            break;
        case 'detailed_reasons':
            $response = getDetailedReasonAnalysis($fgs_db);
            break;
        case 'quarterly_trend':
            $response = getQuarterlyTrend($fgs_db);
            break;
        case 'realtime_status':
            $response = getRealtimeVOCStatus($fgs_db);
            break;
        case 'customer_reason_matrix':
            $response = getCustomerReasonMatrix($fgs_db);
            break;
        case 'all':
        default:
            $response = [
                'reasonStats' => getReasonStats($fgs_db),
                'monthlyTrend' => getMonthlyTrend($fgs_db),
                'hourlyData' => getHourlyDistribution($fgs_db),
                'customerData' => getCustomerTypeAnalysis($fgs_db),
                'priorityData' => getImprovementPriority($fgs_db),
                'detailedReasons' => getDetailedReasonAnalysis($fgs_db),
                'quarterlyTrend' => getQuarterlyTrend($fgs_db),
                'realtimeStatus' => getRealtimeVOCStatus($fgs_db),
                'customerReasonMatrix' => getCustomerReasonMatrix($fgs_db),
                'lastUpdate' => date('Y-m-d H:i:s')
            ];
            break;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => '데이터베이스 연결 오류',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// VOC 유형별 통계
function getReasonStats($db) {
    $sql = "
        SELECT 
            voc_type as reason_category,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM cafe24pro_sales_voc WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 2) as percentage
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY voc_type 
        ORDER BY count DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 월별 VOC 트렌드
function getMonthlyTrend($db) {
    $sql = "
        SELECT 
            DATE_FORMAT(reg_date, '%Y-%m') as month,
            voc_type as reason_category,
            COUNT(*) as count
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(reg_date, '%Y-%m'), voc_type
        ORDER BY month DESC, count DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 시간대별 VOC 분포
function getHourlyDistribution($db) {
    $sql = "
        SELECT 
            HOUR(reg_date) as hour,
            COUNT(*) as count
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY HOUR(reg_date)
        ORDER BY hour
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 쇼핑몰별 VOC 분석
function getCustomerTypeAnalysis($db) {
    $sql = "
        SELECT 
            mall_id as customer_type,
            COUNT(*) as count
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY mall_id
        ORDER BY count DESC
        LIMIT 10
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// VOC 유형별 우선순위 (빈도 기준)
function getImprovementPriority($db) {
    $sql = "
        SELECT 
            voc_type as reason_category,
            COUNT(*) as frequency,
            CASE 
                WHEN voc_type = 'K2K_SALES' THEN 5.0
                WHEN voc_type = 'K2G_SALES' THEN 4.8
                WHEN voc_type = 'RETENTION' THEN 4.5
                WHEN voc_type = 'K2K_MARKET' THEN 4.2
                WHEN voc_type = 'K2G_MARKET' THEN 4.0
                WHEN voc_type = 'TOP_SALES' THEN 3.5
                ELSE 3.0
            END as avg_urgency,
            COUNT(*) * CASE 
                WHEN voc_type = 'K2K_SALES' THEN 5.0
                WHEN voc_type = 'K2G_SALES' THEN 4.8
                WHEN voc_type = 'RETENTION' THEN 4.5
                WHEN voc_type = 'K2K_MARKET' THEN 4.2
                WHEN voc_type = 'K2G_MARKET' THEN 4.0
                WHEN voc_type = 'TOP_SALES' THEN 3.5
                ELSE 3.0
            END as priority_score
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY voc_type
        ORDER BY priority_score DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// VOC 키워드 분석 (내용에서 키워드 추출)
function getVOCKeywords($db) {
    $sql = "
        SELECT 
            voc,
            voc_type,
            reg_date
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY reg_date DESC
        LIMIT 100
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 최근 VOC 동향
function getRecentVOCTrend($db) {
    $sql = "
        SELECT 
            DATE(reg_date) as date,
            COUNT(*) as daily_count,
            GROUP_CONCAT(DISTINCT voc_type) as voc_types
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(reg_date)
        ORDER BY date DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 신청사유 상세 분석
function getDetailedReasonAnalysis($db) {
    $sql = "
        SELECT 
            voc_type,
            COUNT(*) as total_count,
            ROUND(AVG(CHAR_LENGTH(voc)), 2) as avg_content_length,
            COUNT(DISTINCT mall_id) as unique_malls,
            DATE(MIN(reg_date)) as first_occurrence,
            DATE(MAX(reg_date)) as last_occurrence,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM cafe24pro_sales_voc WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 2) as percentage
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY voc_type
        ORDER BY total_count DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 분기별 트렌드
function getQuarterlyTrend($db) {
    $sql = "
        SELECT 
            CONCAT(YEAR(reg_date), '-Q', QUARTER(reg_date)) as quarter,
            voc_type,
            COUNT(*) as count,
            ROUND(AVG(CHAR_LENGTH(voc)), 2) as avg_content_length
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
        GROUP BY YEAR(reg_date), QUARTER(reg_date), voc_type
        ORDER BY quarter DESC, count DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 실시간 VOC 현황
function getRealtimeVOCStatus($db) {
    $sql = "
        SELECT 
            COUNT(*) as total_today,
            COUNT(CASE WHEN reg_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as last_hour,
            COUNT(CASE WHEN reg_date >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 END) as last_15min,
            COUNT(CASE WHEN voc_type = 'K2K_SALES' THEN 1 END) as k2k_sales_today,
            COUNT(CASE WHEN voc_type = 'K2G_SALES' THEN 1 END) as k2g_sales_today,
            COUNT(CASE WHEN voc_type = 'RETENTION' THEN 1 END) as retention_today,
            MAX(reg_date) as last_voc_time
        FROM cafe24pro_sales_voc 
        WHERE DATE(reg_date) = CURDATE()
    ";
    return $db->query($sql)->fetch(PDO::FETCH_ASSOC);
}

// 고객 유형별 사유 분포 매트릭스
function getCustomerReasonMatrix($db) {
    $sql = "
        SELECT 
            mall_id,
            voc_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (PARTITION BY mall_id), 2) as percentage_within_mall
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY mall_id, voc_type
        HAVING COUNT(*) >= 2
        ORDER BY mall_id, count DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 서비스 개선 우선순위 고도화
function getAdvancedImprovementPriority($db) {
    $sql = "
        SELECT 
            voc_type,
            COUNT(*) as frequency,
            COUNT(DISTINCT mall_id) as affected_malls,
            ROUND(AVG(CHAR_LENGTH(voc)), 2) as avg_complexity,
            COUNT(CASE WHEN reg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_count,
            CASE 
                WHEN voc_type = 'K2K_SALES' THEN 5.0
                WHEN voc_type = 'K2G_SALES' THEN 4.8
                WHEN voc_type = 'RETENTION' THEN 4.5
                WHEN voc_type = 'K2K_MARKET' THEN 4.2
                WHEN voc_type = 'K2G_MARKET' THEN 4.0
                WHEN voc_type = 'TOP_SALES' THEN 3.5
                ELSE 3.0
            END as urgency_weight,
            (COUNT(*) * 0.4 + 
             COUNT(DISTINCT mall_id) * 0.3 + 
             COUNT(CASE WHEN reg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) * 0.3) *
            CASE 
                WHEN voc_type = 'K2K_SALES' THEN 5.0
                WHEN voc_type = 'K2G_SALES' THEN 4.8
                WHEN voc_type = 'RETENTION' THEN 4.5
                WHEN voc_type = 'K2K_MARKET' THEN 4.2
                WHEN voc_type = 'K2G_MARKET' THEN 4.0
                WHEN voc_type = 'TOP_SALES' THEN 3.5
                ELSE 3.0
            END as advanced_priority_score
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY voc_type
        ORDER BY advanced_priority_score DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
?>