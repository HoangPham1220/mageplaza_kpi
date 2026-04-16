<?php
// include class của bạn
require_once 'SurveyTicket.php';

// khởi tạo object
$surveyTicket = new SurveyTicket();

// nhận dữ liệu (hỗ trợ cả GET và POST)
$agent = isset($_REQUEST['agent']) ? $_REQUEST['agent'] : null;
$target = isset($_REQUEST['target']) ? $_REQUEST['target'] : null;

// validate
if (!$agent || !$target) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing agent or target'
    ]);
    exit;
}

// ép kiểu
$target = (int)$target;

// update (insert record mới)
$surveyTicket->updateTargetByAgent($agent, $target);

// trả kết quả
echo json_encode([
    'status' => 'success',
    'agent' => $agent,
    'target' => $target
]);