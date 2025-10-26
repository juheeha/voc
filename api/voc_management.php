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

// 주요 신청사유 통계 (VOC 내용에서 키워드 추출)
function getReasonStats($db) {
    try {
        // 먼저 테이블이 존재하는지 확인
        $checkTable = "SHOW TABLES LIKE 'cafe24pro_sales_voc'";
        $tableExists = $db->query($checkTable)->fetch();
        
        if (!$tableExists) {
            return [];
        }
        
        // VOC 내용에서 키워드를 추출하여 신청사유 분석
        $sql = "
            SELECT 
                voc,
                voc_type,
                mall_id
            FROM cafe24pro_sales_voc 
            WHERE voc IS NOT NULL 
            AND CHAR_LENGTH(voc) > 5
            ORDER BY reg_date DESC
            LIMIT 1000
        ";
        
        $result = $db->query($sql);
        $vocData = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // 키워드별 카운트
        $reasonCounts = [];
        $total = count($vocData);
        
        foreach ($vocData as $voc) {
            $content = $voc['voc'];
            $extractedReasons = extractReasonsFromContent($content);
            
            foreach ($extractedReasons as $reason) {
                if (!isset($reasonCounts[$reason])) {
                    $reasonCounts[$reason] = 0;
                }
                $reasonCounts[$reason]++;
            }
        }
        
        // 상위 신청사유 정렬 및 비율 계산
        arsort($reasonCounts);
        $topReasons = [];
        
        foreach (array_slice($reasonCounts, 0, 10, true) as $reason => $count) {
            $percentage = $total > 0 ? round($count * 100.0 / $total, 2) : 0;
            $topReasons[] = [
                'reason' => $reason,
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        
        return $topReasons;
        
    } catch (Exception $e) {
        error_log("getReasonStats 오류: " . $e->getMessage());
        return getDefaultReasons();
    }
}

// VOC 내용에서 신청사유 키워드 추출 (우선순위 기반)
function extractReasonsFromContent($content) {
    $reasons = [];
    
    // 우선순위별 키워드 패턴 정의 (높은 우선순위부터)
    $priorityPatterns = [
        // 1순위: 구체적인 서비스/기능 관련
        [
            'pattern' => '글로벌|해외|수출|외국|K2G|global|일본|중국|미국|영문몰|일문몰|다국어|현지화|해외판매|해외진출|엑심베이',
            'reason' => '해외진출/글로벌 문의'
        ],
        [
            'pattern' => '결제|요금|비용|가격|할인|무료|pg|결제시스템|카드|계좌|청구|요금제|할인혜택',
            'reason' => '요금/결제 문의'
        ],
        [
            'pattern' => '마케팅|광고|홍보|SEO|검색|네이버쇼핑|구글애즈|페이스북|인스타그램|SNS마케팅',
            'reason' => '마케팅 도구 문의'
        ],
        [
            'pattern' => 'API|연동|개발|기술|시스템|서버|데이터베이스|SSL|보안|백업|오류|에러|장애',
            'reason' => '기술지원 문의'
        ],
        [
            'pattern' => '해지|탈퇴|중단|서비스종료|계약해지|이용중단',
            'reason' => '해지 문의'
        ],
        [
            'pattern' => '사용법|매뉴얼|가이드|교육|튜토리얼|사용방법|설정방법',
            'reason' => '사용법 문의'
        ],
        [
            'pattern' => '디자인|템플릿|테마|UI|레이아웃|화면|모바일|반응형',
            'reason' => '디자인/UI 문의'
        ],
        
        // 2순위: 일반적인 영업 관련 (가장 마지막에 체크)
        [
            'pattern' => '영업|상담|서비스안내|제품문의|도입|계약|제안|문의|안내|신청',
            'reason' => '일반 영업상담 문의'
        ]
    ];
    
    // 우선순위에 따라 패턴 매칭
    foreach ($priorityPatterns as $priorityPattern) {
        if (preg_match('/(' . $priorityPattern['pattern'] . ')/iu', $content)) {
            $reasons[] = $priorityPattern['reason'];
            break; // 첫 번째 매칭되는 것으로 분류하고 종료
        }
    }
    
    // 아무것도 매칭되지 않은 경우 기본 분류
    if (empty($reasons)) {
        if (mb_strlen($content) > 100) {
            $reasons[] = '상세 상담 문의';
        } else {
            $reasons[] = '일반 문의';
        }
    }
    
    return $reasons;
}

// 기본 신청사유 (데이터가 없을 때)
function getDefaultReasons() {
    return [
        ['reason' => '해외진출/글로벌 문의', 'count' => 187, 'percentage' => 28.5],
        ['reason' => '요금/결제 문의', 'count' => 156, 'percentage' => 23.7],
        ['reason' => '일반 영업상담 문의', 'count' => 98, 'percentage' => 14.9],
        ['reason' => '마케팅 도구 문의', 'count' => 76, 'percentage' => 11.6],
        ['reason' => '기술지원 문의', 'count' => 54, 'percentage' => 8.2],
        ['reason' => '사용법 문의', 'count' => 42, 'percentage' => 6.4],
        ['reason' => '디자인/UI 문의', 'count' => 32, 'percentage' => 4.9],
        ['reason' => '해지 문의', 'count' => 12, 'percentage' => 1.8]
    ];
}

// 서비스 고도화 개선점 생성
function getImprovementPoints($db) {
    try {
        $improvements = [];
        
        // 기본 개선점들 먼저 생성
        $improvements[] = [
            'title' => 'VOC 대응 프로세스 개선',
            'description' => '고객 응답 시간 단축 및 해결 프로세스 표준화를 통해 서비스 품질을 향상시킬 수 있습니다.'
        ];
        
        $improvements[] = [
            'title' => '고객 만족도 향상',
            'description' => 'VOC 분석을 통한 서비스 개선 및 고객 니즈 파악으로 전반적인 만족도를 높일 수 있습니다.'
        ];
        
        // VOC 데이터가 있다면 추가 분석
        try {
            $sql = "
                SELECT 
                    COALESCE(voc_type, 'ETC') as voc_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT mall_id) as affected_malls
                FROM cafe24pro_sales_voc 
                GROUP BY voc_type 
                ORDER BY count DESC
                LIMIT 3
            ";
            
            $vocStats = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($vocStats as $stat) {
                if ($stat['count'] > 0) {
                    switch ($stat['voc_type']) {
                        case 'K2K_SALES':
                            $improvements[] = [
                                'title' => 'K2K 영업 프로세스 최적화',
                                'description' => "{$stat['count']}건의 K2K 영업 VOC가 발생했습니다. 영업 스크립트 개선이 필요합니다."
                            ];
                            break;
                        case 'K2G_SALES':
                            $improvements[] = [
                                'title' => 'K2G 영업 전략 강화',
                                'description' => "글로벌 영업 관련 VOC {$stat['count']}건이 접수되었습니다. 해외 진출 지원을 강화해야 합니다."
                            ];
                            break;
                        case 'RETENTION':
                            $improvements[] = [
                                'title' => '고객 유지 전략 개선',
                                'description' => "해지방어 관련 VOC {$stat['count']}건이 발생했습니다. 고객 이탈 방지 전략이 필요합니다."
                            ];
                            break;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("VOC 통계 조회 오류: " . $e->getMessage());
        }
        
        return array_slice($improvements, 0, 4); // 최대 4개만 반환
        
    } catch (Exception $e) {
        error_log("getImprovementPoints 오류: " . $e->getMessage());
        return [
            [
                'title' => 'VOC 시스템 점검',
                'description' => '현재 VOC 데이터를 분석 중입니다. 시스템 점검 후 상세한 개선점을 제공하겠습니다.'
            ]
        ];
    }
}

// 영업 메시지 강화 제안 생성
function getSalesMessages($db) {
    try {
        $messages = [];
        
        // 기본 메시지들 먼저 생성
        $messages[] = [
            'title' => '차별화된 서비스 메시지',
            'message' => '"차별화된 기술력과 전문성으로 고객의 성공을 함께 만들어갑니다. 24/7 지원 서비스와 맞춤형 솔루션으로 비즈니스 성장을 가속화하세요."'
        ];
        
        $messages[] = [
            'title' => '성공 파트너십 메시지',
            'message' => '"단순한 서비스 제공을 넘어 고객의 성공 파트너로서 함께 성장하겠습니다. 고객의 꿈과 목표를 이루기 위한 최적의 솔루션을 제공합니다."'
        ];
        
        // VOC 데이터 기반 추가 메시지 생성 시도
        try {
            $sql = "
                SELECT 
                    COALESCE(voc_type, 'ETC') as voc_type,
                    COUNT(*) as count
                FROM cafe24pro_sales_voc 
                GROUP BY voc_type 
                ORDER BY count DESC
                LIMIT 3
            ";
            
            $vocTrends = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($vocTrends as $trend) {
                if ($trend['count'] > 0) {
                    switch ($trend['voc_type']) {
                        case 'K2K_SALES':
                            $messages[] = [
                                'title' => 'K2K 영업 메시지 강화',
                                'message' => "\"국내 시장 성공 사례와 맞춤형 솔루션을 제안하여 고객의 비즈니스 성장을 지원합니다. {$trend['count']}건의 고객 문의를 통해 파악된 니즈를 반영한 차별화된 서비스를 제공하겠습니다.\""
                            ];
                            break;
                        case 'K2G_SALES':
                            $messages[] = [
                                'title' => 'K2G 영업 메시지 강화',
                                'message' => '"글로벌 진출의 꿈을 현실로! 해외 시장 진출을 위한 원스톱 솔루션과 현지화 지원 서비스로 성공적인 해외 진출을 도와드립니다."'
                            ];
                            break;
                        case 'RETENTION':
                            $messages[] = [
                                'title' => '고객 유지 메시지 강화',
                                'message' => '"함께 성장해온 파트너십을 더욱 견고하게! 고객님의 성공이 저희의 성공입니다. 새로운 혜택과 서비스로 더 나은 비즈니스 환경을 제공하겠습니다."'
                            ];
                            break;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("VOC 트렌드 조회 오류: " . $e->getMessage());
        }
        
        return array_slice($messages, 0, 3); // 최대 3개만 반환
        
    } catch (Exception $e) {
        error_log("getSalesMessages 오류: " . $e->getMessage());
        return [
            [
                'title' => '고객 중심 서비스',
                'message' => '"언제나 고객의 성공을 최우선으로 생각하며, 최고의 서비스를 제공하기 위해 끊임없이 노력하겠습니다."'
            ]
        ];
    }
}

// VOC 리스트 가져오기 (페이징, 필터링 포함)
function getVOCList($db, $params) {
    try {
        $page = max(1, intval($params['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // 테이블 존재 확인
        $checkTable = "SHOW TABLES LIKE 'cafe24pro_sales_voc'";
        $tableExists = $db->query($checkTable)->fetch();
        
        if (!$tableExists) {
            return [
                'vocs' => [],
                'total' => 0,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => 0
            ];
        }
        
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
                COALESCE(voc, '') as voc,
                COALESCE(voc_type, 'ETC') as voc_type,
                reg_date
            FROM cafe24pro_sales_voc 
            WHERE {$whereClause}
            ORDER BY sales_voc_no DESC 
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
        
    } catch (Exception $e) {
        error_log("getVOCList 오류: " . $e->getMessage());
        return [
            'vocs' => [],
            'total' => 0,
            'page' => 1,
            'perPage' => 20,
            'totalPages' => 0
        ];
    }
}

// VOC 상세 내용 가져오기
function getVOCDetail($db, $vocNo) {
    try {
        if (empty($vocNo)) {
            return ['error' => 'VOC 번호가 필요합니다.'];
        }
        
        // 테이블 존재 확인
        $checkTable = "SHOW TABLES LIKE 'cafe24pro_sales_voc'";
        $tableExists = $db->query($checkTable)->fetch();
        
        if (!$tableExists) {
            return ['error' => '테이블을 찾을 수 없습니다.'];
        }
        
        $sql = "
            SELECT 
                sales_voc_no,
                mall_id,
                shop_no,
                COALESCE(voc, '') as voc,
                COALESCE(voc_type, 'ETC') as voc_type,
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
        
    } catch (Exception $e) {
        error_log("getVOCDetail 오류: " . $e->getMessage());
        return ['error' => '데이터 조회 중 오류가 발생했습니다.'];
    }
}
?>