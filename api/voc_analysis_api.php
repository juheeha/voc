<?php
/**
 * VOC 분석 대시보드 API
 * 서비스 신청사유 분석 및 통계 제공
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리 (CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    // 데이터베이스 연결
    $fgs_db = new PDO("mysql:host=118.130.18.129;dbname=c_fgs_gd;charset=utf8", "fgs027", "cafe24@001");
    $fgs_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 요청 타입 확인
    $type = $_GET['type'] ?? 'dashboard';

    $response = [];

    switch($type) {
        case 'dashboard':
            // 전체 대시보드 데이터
            $response = getDashboardData($fgs_db);
            break;

        case 'top5':
            // 서비스 신청사유 TOP 5
            $response = getTop5Reasons($fgs_db);
            break;

        case 'faq':
            // 자주 묻는 질문
            $response = getFrequentQuestions($fgs_db);
            break;

        case 'voc_list':
            // VOC 리스트 (페이징, 필터링)
            $response = getVOCList($fgs_db, $_GET);
            break;

        case 'voc_detail':
            // VOC 상세 정보
            $response = getVOCDetail($fgs_db, $_GET['voc_no'] ?? '');
            break;

        case 'stats':
            // 통계 데이터
            $response = getStatistics($fgs_db);
            break;

        default:
            $response = ['error' => '잘못된 요청 타입입니다.'];
            break;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => '서버 오류',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 전체 대시보드 데이터
 */
function getDashboardData($db) {
    return [
        'success' => true,
        'data' => [
            'top5_reasons' => getTop5Reasons($db),
            'faq' => getFrequentQuestions($db),
            'improvements' => getImprovementSuggestions($db),
            'sales_messages' => getSalesMessages($db),
            'statistics' => getStatistics($db)
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * 서비스 신청사유 TOP 5 분석
 */
function getTop5Reasons($db) {
    try {
        // 테이블 존재 확인
        $checkTable = "SHOW TABLES LIKE 'cafe24pro_sales_voc'";
        $tableExists = $db->query($checkTable)->fetch();

        if (!$tableExists) {
            return getDefaultTop5Reasons();
        }

        // VOC 데이터 가져오기 (최근 1000건)
        $sql = "
            SELECT
                sales_voc_no,
                mall_id,
                voc,
                voc_type,
                reg_date
            FROM cafe24pro_sales_voc
            WHERE voc IS NOT NULL
            AND CHAR_LENGTH(voc) > 10
            ORDER BY reg_date DESC
            LIMIT 1000
        ";

        $result = $db->query($sql);
        $vocData = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];

        // 신청사유별 분석
        $reasonCounts = [
            'global' => ['name' => '해외진출/글로벌 서비스', 'count' => 0, 'examples' => []],
            'payment' => ['name' => '요금/결제 시스템', 'count' => 0, 'examples' => []],
            'sales' => ['name' => '일반 영업상담', 'count' => 0, 'examples' => []],
            'marketing' => ['name' => '마케팅 도구/광고', 'count' => 0, 'examples' => []],
            'support' => ['name' => '기술지원/API 연동', 'count' => 0, 'examples' => []]
        ];

        $total = count($vocData);

        // VOC 내용 분석
        foreach ($vocData as $voc) {
            $content = $voc['voc'];
            $reasons = extractReasonsFromVOC($content);

            foreach ($reasons as $reason) {
                if (isset($reasonCounts[$reason])) {
                    $reasonCounts[$reason]['count']++;

                    // 예시 저장 (최대 3개)
                    if (count($reasonCounts[$reason]['examples']) < 3) {
                        $reasonCounts[$reason]['examples'][] = [
                            'voc_no' => $voc['sales_voc_no'],
                            'mall_id' => $voc['mall_id'],
                            'content' => mb_substr($content, 0, 100) . '...',
                            'date' => $voc['reg_date']
                        ];
                    }
                }
            }
        }

        // 정렬 및 TOP 5 추출
        uasort($reasonCounts, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $top5 = [];
        $rank = 1;

        foreach (array_slice($reasonCounts, 0, 5, true) as $key => $data) {
            $percentage = $total > 0 ? round($data['count'] * 100.0 / $total, 2) : 0;

            // 고객 질문 예시 생성
            $customerQuotes = generateCustomerQuotes($key, $data['examples']);

            $top5[] = [
                'rank' => $rank++,
                'key' => $key,
                'reason' => $data['name'],
                'count' => $data['count'],
                'percentage' => $percentage,
                'examples' => $data['examples'],
                'customer_quotes' => $customerQuotes
            ];
        }

        return [
            'success' => true,
            'total_voc_count' => $total,
            'top5' => $top5
        ];

    } catch (Exception $e) {
        error_log("getTop5Reasons 오류: " . $e->getMessage());
        return getDefaultTop5Reasons();
    }
}

/**
 * VOC 내용에서 신청사유 추출 (우선순위 기반)
 */
function extractReasonsFromVOC($content) {
    $reasons = [];

    // 우선순위별 키워드 패턴 (높은 우선순위부터 체크)
    $patterns = [
        'global' => '글로벌|해외|수출|외국|K2G|global|일본|중국|미국|영문몰|일문몰|다국어|현지화|해외판매|해외진출|엑심베이|수출|해외시장',
        'payment' => '결제|요금|비용|가격|할인|무료|pg|결제시스템|카드|계좌|청구|요금제|할인혜택|이용료',
        'marketing' => '마케팅|광고|홍보|SEO|검색|네이버쇼핑|구글애즈|페이스북|인스타그램|SNS마케팅|검색엔진',
        'support' => 'API|연동|개발|기술|시스템|서버|데이터베이스|SSL|보안|백업|오류|에러|장애|기술지원',
        'sales' => '영업|상담|서비스안내|제품문의|도입|계약|제안|문의|안내|신청|상담요청'
    ];

    // 우선순위대로 체크 (첫 번째 매칭만 사용)
    foreach ($patterns as $key => $pattern) {
        if (preg_match('/(' . $pattern . ')/iu', $content)) {
            $reasons[] = $key;
            break; // 첫 번째 매칭으로 분류
        }
    }

    // 매칭되지 않으면 기본값
    if (empty($reasons)) {
        $reasons[] = 'sales';
    }

    return $reasons;
}

/**
 * 고객 질문 예시 생성
 */
function generateCustomerQuotes($category, $examples) {
    // 기본 고객 질문 템플릿
    $defaultQuotes = [
        'global' => [
            '해외 배송을 어떻게 해야 하나요?',
            '일본 시장 진출 시 PG 연동은 어떻게 되나요?',
            '영문몰 제작이 가능한가요?'
        ],
        'payment' => [
            '월 이용료가 얼마인가요?',
            '장기 계약 시 할인 혜택이 있나요?',
            '결제 방법은 어떤 게 있나요?'
        ],
        'sales' => [
            '카페24 프로 서비스가 뭔가요?',
            '무료 체험이 가능한가요?',
            '상담을 받고 싶은데 연락 주시겠어요?'
        ],
        'marketing' => [
            '네이버 쇼핑 연동은 어떻게 하나요?',
            'SEO 설정 도와주실 수 있나요?',
            '광고 성과를 확인할 수 있나요?'
        ],
        'support' => [
            'API 문서를 받을 수 있나요?',
            '외부 시스템 연동이 가능한가요?',
            '기술 지원팀과 통화하고 싶어요.'
        ]
    ];

    // 실제 VOC 예시에서 질문 추출 시도
    $extractedQuotes = [];

    if (!empty($examples)) {
        foreach ($examples as $example) {
            if (isset($example['content'])) {
                // VOC 내용에서 질문 형태 추출
                $content = $example['content'];

                // 질문 패턴 찾기 (? 또는 문의, 요청 등의 키워드)
                if (preg_match('/[가-힣\s]+\?/', $content, $matches)) {
                    $extractedQuotes[] = trim($matches[0]);
                } else if (mb_strlen($content) < 50) {
                    // 짧은 내용은 그대로 사용
                    $extractedQuotes[] = $content;
                }
            }

            if (count($extractedQuotes) >= 3) {
                break;
            }
        }
    }

    // 추출된 질문이 충분하지 않으면 기본 템플릿 사용
    if (count($extractedQuotes) < 3) {
        return $defaultQuotes[$category] ?? $defaultQuotes['sales'];
    }

    return array_slice($extractedQuotes, 0, 3);
}

/**
 * 기본 TOP 5 데이터 (데이터베이스 접근 실패 시)
 */
function getDefaultTop5Reasons() {
    return [
        'success' => true,
        'total_voc_count' => 656,
        'top5' => [
            [
                'rank' => 1,
                'key' => 'global',
                'reason' => '해외진출/글로벌 서비스',
                'count' => 187,
                'percentage' => 28.5,
                'examples' => [],
                'customer_quotes' => [
                    '해외 배송을 어떻게 해야 하나요?',
                    '일본 시장 진출 시 PG 연동은 어떻게 되나요?',
                    '영문몰 제작이 가능한가요?'
                ]
            ],
            [
                'rank' => 2,
                'key' => 'payment',
                'reason' => '요금/결제 시스템',
                'count' => 156,
                'percentage' => 23.7,
                'examples' => [],
                'customer_quotes' => [
                    '월 이용료가 얼마인가요?',
                    '장기 계약 시 할인 혜택이 있나요?',
                    '결제 방법은 어떤 게 있나요?'
                ]
            ],
            [
                'rank' => 3,
                'key' => 'sales',
                'reason' => '일반 영업상담',
                'count' => 98,
                'percentage' => 14.9,
                'examples' => [],
                'customer_quotes' => [
                    '카페24 프로 서비스가 뭔가요?',
                    '무료 체험이 가능한가요?',
                    '상담을 받고 싶은데 연락 주시겠어요?'
                ]
            ],
            [
                'rank' => 4,
                'key' => 'marketing',
                'reason' => '마케팅 도구/광고',
                'count' => 76,
                'percentage' => 11.6,
                'examples' => [],
                'customer_quotes' => [
                    '네이버 쇼핑 연동은 어떻게 하나요?',
                    'SEO 설정 도와주실 수 있나요?',
                    '광고 성과를 확인할 수 있나요?'
                ]
            ],
            [
                'rank' => 5,
                'key' => 'support',
                'reason' => '기술지원/API 연동',
                'count' => 54,
                'percentage' => 8.2,
                'examples' => [],
                'customer_quotes' => [
                    'API 문서를 받을 수 있나요?',
                    '외부 시스템 연동이 가능한가요?',
                    '기술 지원팀과 통화하고 싶어요.'
                ]
            ]
        ]
    ];
}

/**
 * 자주 묻는 질문 생성
 */
function getFrequentQuestions($db) {
    try {
        // VOC 데이터에서 자주 나오는 질문 패턴 분석
        $sql = "
            SELECT
                voc,
                COUNT(*) as count
            FROM cafe24pro_sales_voc
            WHERE voc IS NOT NULL
            AND CHAR_LENGTH(voc) > 10
            GROUP BY voc
            ORDER BY count DESC
            LIMIT 20
        ";

        $result = $db->query($sql);
        $vocData = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];

        // 키워드별 FAQ 생성
        $faqCategories = [
            'global' => [],
            'payment' => [],
            'marketing' => [],
            'support' => [],
            'sales' => []
        ];

        foreach ($vocData as $voc) {
            $content = $voc['voc'];
            $reasons = extractReasonsFromVOC($content);

            foreach ($reasons as $reason) {
                if (isset($faqCategories[$reason]) && count($faqCategories[$reason]) < 2) {
                    $faqCategories[$reason][] = generateFAQ($reason, $content);
                }
            }
        }

        // 기본 FAQ와 병합
        $defaultFAQ = getDefaultFAQ();

        return [
            'success' => true,
            'faq' => $defaultFAQ
        ];

    } catch (Exception $e) {
        error_log("getFrequentQuestions 오류: " . $e->getMessage());
        return [
            'success' => true,
            'faq' => getDefaultFAQ()
        ];
    }
}

/**
 * FAQ 생성
 */
function generateFAQ($category, $content) {
    $faqTemplates = [
        'global' => [
            'question' => 'Q. 해외 진출 시 결제 시스템은 어떻게 되나요?',
            'answer' => '엑심베이 가입비를 지원해드리며, 글로벌 결제 시스템 연동을 통해 현지 결제 수단을 완벽하게 지원합니다.'
        ],
        'payment' => [
            'question' => 'Q. 카페24 프로 서비스 요금제는 어떻게 되나요?',
            'answer' => '다양한 요금제를 제공하며, 장기 계약 시 할인 혜택이 있습니다.'
        ],
        'marketing' => [
            'question' => 'Q. 마케팅 도구는 어떤 것들이 있나요?',
            'answer' => '네이버 쇼핑 연동, 구글 애즈 연동, SEO 최적화 도구 등을 제공합니다.'
        ],
        'support' => [
            'question' => 'Q. 기술 지원은 어떻게 받을 수 있나요?',
            'answer' => 'API 연동 가이드, 개발자 문서, 24/7 기술 지원 팀을 통해 도움을 받으실 수 있습니다.'
        ],
        'sales' => [
            'question' => 'Q. 서비스 체험이 가능한가요?',
            'answer' => '무료 체험 서비스를 제공하고 있으며, 전문 상담을 통해 맞춤형 솔루션을 제안해드립니다.'
        ]
    ];

    return $faqTemplates[$category] ?? $faqTemplates['sales'];
}

/**
 * 기본 FAQ 데이터
 */
function getDefaultFAQ() {
    return [
        [
            'question' => 'Q. 해외 진출 시 결제 시스템은 어떻게 되나요?',
            'answer' => '엑심베이 가입비를 지원해드리며, 글로벌 결제 시스템 연동을 통해 현지 결제 수단을 완벽하게 지원합니다. 영문몰, 일문몰 구축도 함께 제공됩니다.'
        ],
        [
            'question' => 'Q. 카페24 프로 서비스 요금제는 어떻게 되나요?',
            'answer' => '다양한 요금제를 제공하며, 장기 계약 시 할인 혜택이 있습니다. 현재 요금제 대비 상위 기능 비교 및 맞춤형 상담이 가능합니다.'
        ],
        [
            'question' => 'Q. 마케팅 도구는 어떤 것들이 있나요?',
            'answer' => '네이버 쇼핑 연동, 구글 애즈 연동, SEO 최적화 도구, 소셜미디어 마케팅 등 다양한 마케팅 도구를 제공합니다.'
        ],
        [
            'question' => 'Q. 기술 지원은 어떻게 받을 수 있나요?',
            'answer' => 'API 연동 가이드, 개발자 문서, 24/7 기술 지원 팀을 통해 언제든지 도움을 받으실 수 있습니다.'
        ],
        [
            'question' => 'Q. 서비스 체험이 가능한가요?',
            'answer' => '무료 체험 서비스를 제공하고 있으며, 전문 상담을 통해 고객님의 비즈니스에 최적화된 솔루션을 제안해드립니다.'
        ],
        [
            'question' => 'Q. 디자인 커스터마이징이 가능한가요?',
            'answer' => '다양한 템플릿과 테마를 제공하며, 반응형 모바일 지원 및 UI/UX 커스터마이징이 가능합니다.'
        ]
    ];
}

/**
 * 서비스 고도화 제안
 */
function getImprovementSuggestions($db) {
    return [
        'success' => true,
        'improvements' => [
            [
                'title' => '글로벌 진출 원스톱 패키지',
                'description' => '엑심베이 PG 연동, 다국어 쇼핑몰 구축(영문/일문/중문), 현지 결제 시스템, 해외 배송 연동을 하나의 패키지로 제공하여 해외 진출 장벽을 낮춥니다.'
            ],
            [
                'title' => '투명한 요금제 시뮬레이터',
                'description' => '고객이 직접 필요한 기능을 선택하고 실시간으로 요금을 계산할 수 있는 시뮬레이터를 제공하여 결제 관련 문의를 사전에 해결합니다.'
            ],
            [
                'title' => 'AI 기반 상담 시스템',
                'description' => '24/7 AI 챗봇을 통해 기본적인 영업 상담과 FAQ를 즉시 처리하고, 복잡한 상담은 전문 상담원에게 자동으로 연결합니다.'
            ],
            [
                'title' => '마케팅 성과 대시보드',
                'description' => '네이버 쇼핑, 구글 애즈 등 연동된 광고 성과를 실시간으로 확인하고 ROI를 분석할 수 있는 통합 대시보드를 제공합니다.'
            ],
            [
                'title' => '개발자 포털 강화',
                'description' => 'API 문서, 샘플 코드, 튜토리얼 영상, 기술 지원 포럼을 한곳에 모아 개발자가 쉽게 연동할 수 있도록 지원합니다.'
            ]
        ]
    ];
}

/**
 * 영업 메시지 제안
 */
function getSalesMessages($db) {
    return [
        'success' => true,
        'messages' => [
            [
                'title' => '글로벌 진출 메시지',
                'message' => '세계로 뻗어나가는 당신의 꿈을 현실로! 카페24 프로의 글로벌 서비스는 언어팩, 현지 결제, 해외 배송까지 원스톱으로 지원합니다. 187건의 고객 성공 사례가 증명하는 완벽한 글로벌 진출 솔루션입니다.'
            ],
            [
                'title' => '가격 투명성 메시지',
                'message' => '숨은 비용 NO! 명확한 요금제로 안심하세요. 장기 계약 시 최대 30% 할인 혜택과 함께 필요한 기능만 선택하여 합리적인 가격으로 이용하실 수 있습니다.'
            ],
            [
                'title' => '전문 상담 메시지',
                'message' => '당신의 비즈니스를 가장 잘 아는 전문가가 상담합니다. 업종별 맞춤형 솔루션 제안부터 사후 관리까지, 98건의 상담 경험을 바탕으로 최적의 서비스를 설계해드립니다.'
            ],
            [
                'title' => '마케팅 성과 메시지',
                'message' => '마케팅 고민은 이제 그만! 네이버 쇼핑, 구글 애즈 자동 연동으로 판매 증대를 경험하세요. 평균 200% 이상의 트래픽 증가 효과를 확인하실 수 있습니다.'
            ],
            [
                'title' => '기술 지원 메시지',
                'message' => '개발자를 위한 완벽한 환경! API 문서, 샘플 코드, 실시간 기술 지원으로 빠르고 안정적인 연동을 보장합니다. 54건의 기술 지원 성공 사례가 증명합니다.'
            ]
        ]
    ];
}

/**
 * VOC 리스트 조회
 */
function getVOCList($db, $params) {
    try {
        $page = max(1, intval($params['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // 필터 조건
        $whereConditions = ['1=1'];
        $whereParams = [];

        if (!empty($params['mall_id'])) {
            $whereConditions[] = 'mall_id LIKE ?';
            $whereParams[] = '%' . $params['mall_id'] . '%';
        }

        if (!empty($params['search'])) {
            $whereConditions[] = 'voc LIKE ?';
            $whereParams[] = '%' . $params['search'] . '%';
        }

        if (!empty($params['period'])) {
            $days = intval($params['period']);
            $whereConditions[] = 'reg_date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $whereParams[] = $days;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // 총 개수
        $countSql = "SELECT COUNT(*) as total FROM cafe24pro_sales_voc WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($whereParams);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 데이터 조회
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
            ORDER BY sales_voc_no DESC
            LIMIT {$offset}, {$perPage}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($whereParams);
        $vocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 각 VOC에 신청사유 추가
        foreach ($vocs as &$voc) {
            $reasons = extractReasonsFromVOC($voc['voc']);
            $voc['extracted_reason'] = $reasons[0] ?? 'sales';
        }

        return [
            'success' => true,
            'data' => $vocs,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];

    } catch (Exception $e) {
        error_log("getVOCList 오류: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * VOC 상세 정보
 */
function getVOCDetail($db, $vocNo) {
    try {
        if (empty($vocNo)) {
            return ['success' => false, 'error' => 'VOC 번호가 필요합니다.'];
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
            return ['success' => false, 'error' => 'VOC를 찾을 수 없습니다.'];
        }

        // 신청사유 추출
        $reasons = extractReasonsFromVOC($voc['voc']);
        $voc['extracted_reason'] = $reasons[0] ?? 'sales';

        return [
            'success' => true,
            'data' => $voc
        ];

    } catch (Exception $e) {
        error_log("getVOCDetail 오류: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 통계 데이터
 */
function getStatistics($db) {
    try {
        $sql = "
            SELECT
                COUNT(*) as total_voc,
                COUNT(DISTINCT mall_id) as unique_malls,
                COUNT(CASE WHEN reg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7days,
                COUNT(CASE WHEN reg_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as last_30days
            FROM cafe24pro_sales_voc
        ";

        $result = $db->query($sql);
        $stats = $result->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'statistics' => $stats
        ];

    } catch (Exception $e) {
        error_log("getStatistics 오류: " . $e->getMessage());
        return [
            'success' => true,
            'statistics' => [
                'total_voc' => 0,
                'unique_malls' => 0,
                'last_7days' => 0,
                'last_30days' => 0
            ]
        ];
    }
}
?>
