# VOC 분석 대시보드

cafe24pro_sales_voc 테이블의 VOC 내용을 분석하여 서비스 신청사유를 파악하고, 고도화 방안 및 영업 메시지를 제안하는 대시보드입니다.

## 생성된 파일

### 1. 프론트엔드
- **voc_analysis_dashboard.html** - 메인 대시보드 HTML 파일

### 2. 백엔드 API
- **api/voc_analysis_api.php** - VOC 데이터 분석 API

## 주요 기능

### 1. 자주 묻는 질문 (FAQ)
- VOC 데이터 분석을 통해 자주 나오는 질문 패턴 추출
- 카테고리별 FAQ 자동 생성

### 2. 서비스 신청사유 TOP 5
VOC 내용에서 키워드를 추출하여 다음 5가지 신청사유를 분석합니다:
1. **해외진출/글로벌 서비스** - 글로벌, 해외, PG, 엑심베이, 영문몰, 일문몰 등
2. **요금/결제 시스템** - 결제, 요금, 비용, 수수료, 성과 기반 요금제 등
3. **일반 영업상담** - 영업, 상담, 서비스안내, 계약 등
4. **운영 효율화/자동화** - 전문가 운영 대행, CRM, 프로모션, 이벤트 운영 등
5. **브랜드 확장/멀티채널 판매** - 멀티채널, 브랜드 확장, 마켓 연동, 자사몰+오픈마켓 등

#### 고객 질문 예시
각 신청사유 카드에는 고객들이 실제로 하는 질문 예시가 표시됩니다:
- **해외진출**: "해외 배송을 어떻게 해야 하나요?", "일본 시장 진출 시 PG 연동은 어떻게 되나요?" 등
- **요금/결제**: "초기 비용이 얼마나 드나요?", "매출이 없어도 요금을 내야 하나요?", "수수료율은 어떻게 되나요?" 등
- **일반 영업상담**: "카페24 프로 서비스가 뭔가요?", "표준 서비스에는 어떤 기능이 있나요?", "엔터프라이즈 마스터와 차이가 뭔가요?" 등
- **운영 효율화**: "카페24 전문가가 운영해주나요?", "제가 직접 할 일이 얼마나 되나요?", "CRM/프로모션을 대신 해주나요?" 등
- **브랜드 확장**: "자사몰과 마켓을 동시에 운영할 수 있나요?", "네이버/쿠팡 연동이 되나요?" 등

### 3. 서비스 구성 고도화 방안
신청사유 분석 결과를 바탕으로 다음과 같은 개선 방안을 제시합니다:
- 글로벌 진출 원스톱 패키지
- 성과 기반 요금제 계산기
- 자동화 상담 시스템
- 전문가 풀매니지드 운영 서비스
- 멀티채널 통합 관리 플랫폼

#### 카페24 PRO 서비스 정보

**서비스 특징**
- **표준 운영 서비스**: 쇼핑몰 운영에 필요한 검증된 표준 프로세스 제공
- **업그레이드**: 커스터마이징이 필요한 경우 엔터프라이즈 마스터로 업그레이드 가능

**요금제**
- **초기 비용**: 무료 (현재 프로모션 중)
- **자사몰 매출**: 2% 과금
- **마켓 매출**: 1% 과금
- **과금 방식**: 매출 발생 시에만 비용 지불 (성과 기반)

**마케팅**
- **CRM**: 고객 관리 및 세분화
- **프로모션**: 쿠폰, 할인, 이벤트 운영
- **목표**: 리텐션 강화 및 전환율 향상
- **특징**: 잘 팔릴 수 있는 환경 조성

### 4. 강화해야 할 영업 메시지
각 신청사유별 맞춤형 영업 메시지를 제안합니다:
- 글로벌 진출 메시지
- 가격 투명성 메시지
- 전문 상담 메시지
- 마케팅 성과 메시지
- 기술 지원 메시지

### 5. 고객별 VOC 리스트
- 페이징 기능 (20건씩)
- 필터링 기능 (쇼핑몰 ID, 신청사유, 기간, 검색어)
- VOC 상세 내용 모달
- 자동 신청사유 분류

## API 엔드포인트

### 1. 전체 대시보드 데이터
```
GET /api/voc_analysis_api.php?type=dashboard
```

응답 예시:
```json
{
  "success": true,
  "data": {
    "top5_reasons": [...],
    "faq": [...],
    "improvements": [...],
    "sales_messages": [...],
    "statistics": {...}
  },
  "timestamp": "2024-10-26 16:30:00"
}
```

### 2. 신청사유 TOP 5
```
GET /api/voc_analysis_api.php?type=top5
```

응답 예시:
```json
{
  "success": true,
  "total_voc_count": 656,
  "top5": [
    {
      "rank": 1,
      "key": "global",
      "reason": "해외진출/글로벌 서비스",
      "count": 187,
      "percentage": 28.5,
      "customer_quotes": [
        "해외 배송을 어떻게 해야 하나요?",
        "일본 시장 진출 시 PG 연동은 어떻게 되나요?",
        "영문몰 제작이 가능한가요?"
      ]
    }
  ]
}
```

### 3. FAQ 데이터
```
GET /api/voc_analysis_api.php?type=faq
```

### 4. VOC 리스트
```
GET /api/voc_analysis_api.php?type=voc_list&page=1&mall_id=mall66&search=글로벌&period=30
```

파라미터:
- `page`: 페이지 번호 (기본값: 1)
- `mall_id`: 쇼핑몰 ID 필터
- `search`: 검색어
- `period`: 기간 (7, 30, 90, 365일)

### 5. VOC 상세 정보
```
GET /api/voc_analysis_api.php?type=voc_detail&voc_no=27174
```

### 6. 통계 데이터
```
GET /api/voc_analysis_api.php?type=stats
```

## 설치 및 실행 방법

### 1. 파일 배치
```
C:\Users\win10_original\
├── voc_analysis_dashboard.html
└── api\
    └── voc_analysis_api.php
```

### 2. 웹 서버 설정
Apache, Nginx 등의 웹 서버에서 해당 디렉토리를 Document Root로 설정합니다.

### 3. 데이터베이스 연결 확인
`api/voc_analysis_api.php` 파일의 데이터베이스 연결 정보를 확인합니다:
```php
$fgs_db = new PDO(
    "mysql:host=118.130.18.129;dbname=c_fgs_gd;charset=utf8",
    "fgs027",
    "cafe24@001"
);
```

### 4. 브라우저에서 실행
```
http://localhost/voc_analysis_dashboard.html
```

## 키워드 분석 로직

VOC 내용에서 다음과 같은 우선순위로 키워드를 분석합니다:

1. **해외진출/글로벌** (최우선)
   - 키워드: 글로벌, 해외, 수출, K2G, PG, 엑심베이, 영문몰, 일문몰, 다국어, 일본, 중국 등

2. **요금/결제**
   - 키워드: 결제, 요금, 비용, 가격, 할인, 무료, 청구, 수수료, 요금제, 매출, 과금, 프로모션 등

3. **운영 효율화/자동화**
   - 키워드: 전문가, 대행, 운영대행, 위탁, 맡기기, CRM, 프로모션, 이벤트, 인력절감, 매출증대, 고객관리, 쿠폰, 리텐션, 전환율 등

4. **브랜드 확장/멀티채널**
   - 키워드: 멀티채널, 브랜드, 확장, 마켓, 네이버, 쿠팡, 11번가, 자사몰, 오픈마켓, 채널, 입점, 통합, 스마트스토어 등

5. **일반 영업상담** (기본값)
   - 키워드: 영업, 상담, 서비스안내, 계약, 문의, 신청 등

우선순위가 높은 키워드가 먼저 매칭되며, 첫 번째 매칭 결과로 신청사유를 분류합니다.

## 데이터베이스 테이블 스키마

```sql
cafe24pro_sales_voc
├── sales_voc_no (PK)
├── mall_id
├── shop_no
├── voc (TEXT) - VOC 내용 (분석 대상)
├── voc_type
└── reg_date
```

## 실시간 데이터 연동

현재 HTML 파일은 샘플 데이터를 사용하고 있습니다. 실제 데이터를 사용하려면 다음과 같이 수정하세요:

### HTML 파일 수정 (voc_analysis_dashboard.html)

JavaScript 함수에 API 호출 추가:

```javascript
// 데이터 로드 함수 수정
async function loadDashboardData() {
    try {
        showToast('데이터를 불러오는 중...', 'info');

        // API 호출
        const response = await fetch('/api/voc_analysis_api.php?type=dashboard');
        const data = await response.json();

        if (data.success) {
            // TOP 5 데이터 업데이트
            updateTop5Display(data.data.top5_reasons);

            // FAQ 업데이트
            updateFAQDisplay(data.data.faq);

            // 통계 업데이트
            updateStatistics(data.data.statistics);

            updateLastUpdate();
            showToast('데이터가 성공적으로 업데이트되었습니다!', 'success');
        } else {
            showToast('데이터 로드 실패: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('API 호출 오류:', error);
        showToast('데이터 로드 중 오류가 발생했습니다.', 'error');
    }
}

// VOC 리스트 로드
async function loadVOCList(filters) {
    try {
        const params = new URLSearchParams({
            type: 'voc_list',
            page: currentPage,
            ...filters
        });

        const response = await fetch(`/api/voc_analysis_api.php?${params}`);
        const data = await response.json();

        if (data.success) {
            updateVOCTable(data.data);
            updatePagination(data.pagination);
        }
    } catch (error) {
        console.error('VOC 리스트 로드 오류:', error);
    }
}
```

## 브라우저 호환성

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 라이센스

내부 사용 전용

## 문의

기술 지원이 필요한 경우 개발팀에 문의하세요.

---

## 추가 개선 사항

1. **실시간 데이터 연동**
   - 현재는 샘플 데이터 사용
   - JavaScript의 fetch API를 사용하여 실제 API 연동 필요

2. **캐싱 구현**
   - 빈번한 데이터 조회를 위한 Redis 캐싱
   - API 응답 속도 개선

3. **권한 관리**
   - 사용자 인증 및 권한 체크
   - 민감한 데이터 접근 제어

4. **로그 기능**
   - VOC 조회 이력 기록
   - 관리자 활동 로그

5. **엑셀 내보내기**
   - VOC 리스트 엑셀 다운로드 기능
   - 통계 데이터 리포트 생성

6. **알림 기능**
   - 긴급 VOC 발생시 알림
   - 일일/주간 리포트 이메일 발송
