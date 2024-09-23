<?php
// 데이터베이스 연결
$conn = new mysqli("hostname", "username", "password", "database");

#backup240729.sql에서 conn을 정의
$conn = @mysql_connect($host , $port , $socket , $user , $pass);

// 오류 처리
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 평가경향을 고려한 점수를 계산하는 함수
function calculate_scaled_scores($conn) {
    // 평가자(uploader)별로 원본 점수를 가져옴
    $sql = "SELECT uploader, task, score FROM evalution";
    $result = $conn->query($sql);

    $scores_by_uploader = [];

    // 각 평가자(uploader)별로 점수를 분류
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $uploader = $row['uploader'];
            $task = $row['task'];
            $score = $row['score'];

            // 평가자별로 점수를 배열에 저장
            $scores_by_uploader[$uploader][] = ['task' => $task, 'score' => $score];
        }
    }

    // 평가 경향을 고려한 점수 계산 및 저장
    foreach ($scores_by_uploader as $uploader => $scores) {
        $original_scores = array_column($scores, 'score');
        
        // 최대값, 최소값, 범위 계산
        $max_score = max($original_scores);
        $min_score = min($original_scores);
        $range_score = $max_score - $min_score;

        // 범위가 0인 경우 오류 방지를 위해 1로 설정
        if ($range_score == 0) {
            $range_score = 1;
        }

        // 각 점수를 스케일링하고 결과를 evalution_log 테이블에 저장
        foreach ($scores as $score_data) {
            $task = $score_data['task'];
            $original_score = $score_data['score'];

            // 스케일링된 점수 계산
            $scaled_score = round((($original_score - $min_score) / $range_score) * 10);

            // 결과를 evalution_log 테이블에 삽입
            $insert_sql = "INSERT INTO evalution_log (task, uploader, score, type, isActive, updatedAt) 
                           VALUES ($task, $uploader, $scaled_score, 1, 1, NOW())";
            $conn->query($insert_sql);
        }
    }

    echo "Scaled scores have been calculated and saved!";
}

// "Bias" 버튼 클릭 시 평가 경향 점수 계산을 트리거
if (isset($_POST['apply_bias'])) {
    calculate_scaled_scores($conn);
}
// 연결 종료
$conn->close();
?>

<!-- Bias 버튼 -->
<form method="post">
    <button type="submit" name="apply_bias">Apply Bias</button>
</form>