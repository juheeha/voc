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
    $type = $_GET['type'] ?? 'summary';

    $response = [];

    switch($type) {
        case 'summary':
            $response = getDashboardSummary($fgs_db);
            break;
        case 'voc_list':
            $response = getVOCList($fgs_db, $_GET);
            break;
        case 'voc_detail':
            $response = getVOCDetail($fgs_db, $_GET['voc_no'] ?? '');
            break;
        default:
            $response = ['error' => '잘못된 요청 타입입니다.'];
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

// 대시보드 요약 데이터 가져오기
function getDashboardSummary($db) {
    return [
        'reasonStats' => getReasonStats($db),
        'improvements' => getImprovementPoints($db),
        'salesMessages' => getSalesMessages($db)
    ];
}

// 주요 신청사유 통계
function getReasonStats($db) {
    $sql = "
        SELECT 
            voc_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM cafe24pro_sales_voc WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 2) as percentage
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY voc_type 
        ORDER BY count DESC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// 서비스 고도화 개선점 생성
function getImprovementPoints($db) {
    $improvements = [];
    
    // VOC 유형별 분석을 기반으로 개선점 생성
    $sql = "
        SELECT 
            voc_type,
            COUNT(*) as count,
            COUNT(DISTINCT mall_id) as affected_malls,
            ROUND(AVG(CHAR_LENGTH(voc)), 2) as avg_content_length
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY voc_type 
        ORDER BY count DESC
        LIMIT 5
    ";
    
    $vocStats = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($vocStats as $stat) {
        switch ($stat['voc_type']) {
            case 'K2K_SALES':
                $improvements[] = [
                    'title' => 'K2K 영업 프로세스 최적화',
                    'description' => "최근 30일간 {$stat['count']}건의 K2K 영업 VOC가 발생했습니다. 영업 스크립트 개선 및 FAQ 구축을 통해 고객 만족도를 높일 수 있습니다."
                ];
                break;
            case 'K2G_SALES':
                $improvements[] = [
                    'title' => 'K2G 영업 전략 강화',
                    'description' => "글로벌 영업 관련 VOC {$stat['count']}건이 접수되었습니다. 해외 진출 가이드 제작 및 다국어 지원 서비스 확대가 필요합니다."
                ];
                break;
            case 'RETENTION':
                $improvements[] = [
                    'title' => '고객 유지 전략 개선',
                    'description' => "해지방어 관련 VOC {$stat['count']}건 중 {$stat['affected_malls']}개 쇼핑몰에서 발생했습니다. 맞춤형 혜택 및 서비스 제안으로 이탈률을 줄일 수 있습니다."
                ];
                break;
            case 'K2K_MARKET':
                $improvements[] = [
                    'title' => '국내 마케팅 지원 강화',
                    'description' => "국내 마케팅 관련 VOC {$stat['count']}건이 접수되었습니다. 마케팅 툴 개선 및 교육 프로그램 확대가 필요합니다."
                ];
                break;
            case 'K2G_MARKET':
                $improvements[] = [
                    'title' => '글로벌 마케팅 지원 확대',
                    'description' => "글로벌 마케팅 VOC {$stat['count']}건이 발생했습니다. 현지화 마케팅 전략 및 글로벌 플랫폼 연동 지원이 필요합니다."
                ];
                break;
        }
    }
    
    // 기본 개선점들 추가
    if (empty($improvements)) {
        $improvements[] = [
            'title' => 'VOC 대응 프로세스 개선',
            'description' => '고객 응답 시간 단축 및 해결 프로세스 표준화를 통해 서비스 품질을 향상시킬 수 있습니다.'
        ];
    }
    
    return $improvements;
}

// 영업 메시지 강화 제안 생성
function getSalesMessages($db) {
    $messages = [];
    
    // 최근 VOC 트렌드 분석
    $sql = "
        SELECT 
            voc_type,
            COUNT(*) as count,
            GROUP_CONCAT(DISTINCT SUBSTRING(voc, 1, 50) SEPARATOR '|') as sample_contents
        FROM cafe24pro_sales_voc 
        WHERE reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND voc IS NOT NULL 
        AND CHAR_LENGTH(voc) > 10
        GROUP BY voc_type 
        ORDER BY count DESC
        LIMIT 3
    ";
    
    $vocTrends = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($vocTrends as $trend) {
        switch ($trend['voc_type']) {
            case 'K2K_SALES':
                $messages[] = [
                    'title' => 'K2K 영업 메시지 강화',
                    'message' => "\"국내 시장 성공 사례와 맞춤형 솔루션을 제안하여 고객의 비즈니스 성장을 지원합니다. 최근 {$trend['count']}건의 문의를 통해 파악된 고객 니즈를 반영한 차별화된 서비스를 제공하겠습니다.\""
                ];
                break;
            case 'K2G_SALES':
                $messages[] = [
                    'title' => 'K2G 영업 메시지 강화',
                    'message' => "\"글로벌 진출의 꿈을 현실로! 해외 시장 진출을 위한 원스톱 솔루션과 현지화 지원 서비스로 성공적인 해외 진출을 도와드립니다. 전문 컨설팅부터 기술 지원까지 모든 것을 제공합니다.\""
                ];
                break;
            case 'RETENTION':
                $messages[] = [
                    'title' => '고객 유지 메시지 강화',
                    'message' => "\"함께 성장해온 파트너십을 더욱 견고하게! 고객님의 성공이 저희의 성공입니다. 새로운 혜택과 서비스로 더 나은 비즈니스 환경을 제공하겠습니다.\""
                ];
                break;
        }
    }
    
    // 기본 메시지들 추가
    if (count($messages) < 3) {
        $messages[] = [
            'title' => '종합 서비스 메시지',
            'message' => '"차별화된 기술력과 전문성으로 고객의 성공을 함께 만들어갑니다. 24/7 지원 서비스와 맞춤형 솔루션으로 비즈니스 성장을 가속화하세요."'
        ];
    }
    
    return $messages;
}

// VOC 리스트 가져오기 (페이징, 필터링 포함)
function getVOCList($db, $params) {
    $page = max(1, intval($params['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    // 필터 조건 구성
    $whereConditions = ['1=1'];
    $whereParams = [];
    
    // 쇼핑몰 ID 필터
    if (!empty($params['mall_id'])) {
        $whereConditions[] = 'mall_id LIKE ?';
        $whereParams[] = '%' . $params['mall_id'] . '%';
    }
    
    // VOC 유형 필터
    if (!empty($params['voc_type'])) {
        $whereConditions[] = 'voc_type = ?';
        $whereParams[] = $params['voc_type'];
    }
    
    // 기간 필터
    if (!empty($params['period'])) {
        $days = intval($params['period']);
        $whereConditions[] = 'reg_date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $whereParams[] = $days;
    }
    
    // 검색어 필터
    if (!empty($params['search'])) {
        $whereConditions[] = 'voc LIKE ?';
        $whereParams[] = '%' . $params['search'] . '%';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 총 개수 조회
    $countSql = "SELECT COUNT(*) as total FROM cafe24pro_sales_voc WHERE {$whereClause}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($whereParams);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // VOC 리스트 조회
    $sql = "
        SELECT 
            sales_voc_no,
            mall_id,
            shop_no,
            voc,
            voc_type,
            reg_date
        FROM cafe24pro_sales_voc 
        WHERE {$whereClause}
        ORDER BY reg_date DESC 
        LIMIT {$offset}, {$perPage}
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($whereParams);
    $vocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'vocs' => $vocs,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ];
}

// VOC 상세 내용 가져오기
function getVOCDetail($db, $vocNo) {
    if (empty($vocNo)) {
        return ['error' => 'VOC 번호가 필요합니다.'];
    }
    
    $sql = "
        SELECT 
            sales_voc_no,
            mall_id,
            shop_no,
            voc,
            voc_type,
            reg_date
        FROM cafe24pro_sales_voc 
        WHERE sales_voc_no = ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$vocNo]);
    $voc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voc) {
        return ['error' => 'VOC를 찾을 수 없습니다.'];
    }
    
    return ['voc' => $voc];
}
?>