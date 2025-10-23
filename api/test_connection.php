<?php
// 테스트용 연결 확인 페이지
header('Content-Type: text/html; charset=utf-8');

echo "<h2>VOC 데이터베이스 연결 테스트</h2>";

try {
    // 데이터베이스 연결 테스트
    $fgs_db = new PDO("mysql:host=118.130.18.129;dbname=c_fgs_gd;charset=utf8", "fgs027", "cafe24@001");
    $fgs_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ 데이터베이스 연결 성공!</p>";
    
    // 테이블 존재 확인
    $tables = $fgs_db->query("SHOW TABLES LIKE 'cafe24pro_sales_voc'")->fetchAll();
    
    if (count($tables) > 0) {
        echo "<p style='color: green;'>✅ cafe24pro_sales_voc 테이블 존재</p>";
        
        // 테이블 구조 확인
        echo "<h3>테이블 구조:</h3>";
        $columns = $fgs_db->query("DESCRIBE cafe24pro_sales_voc")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>컬럼명</th><th>타입</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 데이터 개수 확인
        $count = $fgs_db->query("SELECT COUNT(*) as cnt FROM cafe24pro_sales_voc")->fetch();
        echo "<p><strong>총 데이터 개수:</strong> {$count['cnt']}건</p>";
        
        // 샘플 데이터 확인
        echo "<h3>샘플 데이터 (최근 5건):</h3>";
        $samples = $fgs_db->query("
            SELECT 
                sales_voc_no, 
                mall_id, 
                COALESCE(voc_type, 'NULL') as voc_type, 
                LEFT(COALESCE(voc, ''), 50) as voc_preview,
                reg_date 
            FROM cafe24pro_sales_voc 
            ORDER BY sales_voc_no DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>번호</th><th>쇼핑몰</th><th>유형</th><th>내용(50자)</th><th>등록일</th></tr>";
        foreach ($samples as $sample) {
            echo "<tr>";
            echo "<td>{$sample['sales_voc_no']}</td>";
            echo "<td>{$sample['mall_id']}</td>";
            echo "<td>{$sample['voc_type']}</td>";
            echo "<td>{$sample['voc_preview']}</td>";
            echo "<td>{$sample['reg_date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // VOC 유형별 통계
        echo "<h3>VOC 유형별 통계:</h3>";
        $stats = $fgs_db->query("
            SELECT 
                COALESCE(voc_type, 'NULL') as voc_type, 
                COUNT(*) as count
            FROM cafe24pro_sales_voc 
            GROUP BY voc_type 
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>VOC 유형</th><th>건수</th></tr>";
        foreach ($stats as $stat) {
            echo "<tr><td>{$stat['voc_type']}</td><td>{$stat['count']}</td></tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: red;'>❌ cafe24pro_sales_voc 테이블이 존재하지 않습니다.</p>";
        
        // 존재하는 테이블 목록 표시
        echo "<h3>존재하는 테이블들:</h3>";
        $allTables = $fgs_db->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
        foreach ($allTables as $table) {
            echo "<p>- {$table[0]}</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 오류 발생: " . $e->getMessage() . "</p>";
    echo "<p><strong>파일:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>라인:</strong> " . $e->getLine() . "</p>";
}

echo "<hr>";
echo "<p><a href='../voc_management_dashboard.html'>대시보드로 돌아가기</a></p>";
?>