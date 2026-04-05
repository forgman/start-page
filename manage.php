<?php
/**
 * KAI.WORK Data Manager v8.3.1
 * 权限控制 + 数据读写 (增强健壮性版)
 */
// [核心修复] 延长服务器端 Session 有效期，防止自动回收
ini_set('session.gc_maxlifetime', 30 * 86400);
session_start();
header('Content-Type: application/json');
error_reporting(0); // [核心修复] 禁止 PHP 报错污染 JSON 输出

// 1. 鉴权：未登录直接驳回 401
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => '未登录']);
    exit;
}

$currentUser = $_SESSION['user'];
// 确保只读写该用户的文件，防止路径遍历
$safeUser = preg_replace('/[^a-zA-Z0-9_]/', '', $currentUser); 
$userFile = "../data/user_data/{$safeUser}.json";
$templateFile = "../data/templates.json";

// [核心修复] 确保目录存在
$dir = dirname($userFile);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// 2. 容错：如果用户文件丢失，尝试从模板重建
if (!file_exists($userFile)) {
    if(file_exists($templateFile)) {
        copy($templateFile, $userFile);
    } else {
        file_put_contents($userFile, '{"categories":[]}');
    }
}

// 3. GET: 读取数据
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $content = file_get_contents($userFile);
    // 确保返回的是有效 JSON，否则返回空结构
    $jsonCheck = json_decode($content);
    if ($jsonCheck === null) {
        echo '{"categories":[]}';
    } else {
        echo $content;
    }
    exit;
}

// 4. POST: 保存数据
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data) {
        if (file_put_contents($userFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => '写入失败，请检查目录权限 (777)']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => '数据格式错误']);
    }
    exit;
}
?>
