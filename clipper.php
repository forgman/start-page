<?php
/**
 * KAI.WORK Clipper API (V8.6.1 UI Fixes)
 * 1. 修复收集箱名称重复显示图标的问题
 * 2. 将新建的收集箱放置在列表末尾
 */

// --- 配置区 ---
// [请修改] 设置一个您的专属密钥，必须与插件里的一致
$API_SECRET_KEY = "kai_vip_888"; 
// [请修改] 您的用户名 (根据 users.json 默认为 KAI)
$TARGET_USERNAME = "KAI"; 
// --------------

header('Content-Type: application/json');

// [核心修复] 彻底放开跨域限制
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 预检请求直接放行
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 接收数据
$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '';
$url = $input['url'] ?? '';
$key = $input['key'] ?? ''; 

// 1. 验证密钥
if ($key !== $API_SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'msg' => 'API 密钥错误']);
    exit;
}

// 2. 验证参数
if (!$title || !$url) {
    echo json_encode(['status' => 'error', 'msg' => '参数缺失']);
    exit;
}

// 3. 定位用户文件
$userFile = "../data/user_data/{$TARGET_USERNAME}.json";

if (!file_exists($userFile)) {
    echo json_encode(['status' => 'error', 'msg' => "用户文件({$TARGET_USERNAME})不存在"]);
    exit;
}

// 4. 读取数据
$data = json_decode(file_get_contents($userFile), true);
if (!$data) $data = ['categories' => []];

// 5. 寻找或创建 "收集箱"
$targetCatId = null;
$inboxName = "收集箱"; 

foreach ($data['categories'] as $index => $cat) {
    // 只要包含 "收集箱" 三个字就认为是目标
    if (strpos($cat['name'], $inboxName) !== false) {
        $targetCatId = $index;
        break;
    }
}

// 如果没找到收集箱，创建一个新的
if ($targetCatId === null) {
    $newCat = [
        "id" => "inbox_" . time(),
        "name" => $inboxName, // [修复1] 去掉前面的 emoji，防止双图标
        "icon" => "📥",       // 图标由这里控制
        "sections" => [
            [ "title" => "Quick Saves", "links" => [] ]
        ]
    ];
    
    // [修复2] 插入到数组末尾，而不是开头
    $data['categories'][] = $newCat;
    // 获取最后一个元素的索引
    $targetCatId = count($data['categories']) - 1;
}

// 确保该分类至少有一个 section
if (empty($data['categories'][$targetCatId]['sections'])) {
    $data['categories'][$targetCatId]['sections'][] = ["title" => "Quick Saves", "links" => []];
}

// 6. 插入链接 (去重)
$exists = false;
foreach ($data['categories'][$targetCatId]['sections'][0]['links'] as $link) {
    if ($link['url'] === $url) { $exists = true; break; }
}

if (!$exists) {
    // 链接依然插入到该板块的最前面，方便看到最新的
    array_unshift($data['categories'][$targetCatId]['sections'][0]['links'], [
        "name" => $title,
        "url" => $url
    ]);
} else {
    echo json_encode(['status' => 'success', 'msg' => '链接已存在']);
    exit;
}

// 7. 保存文件
if (file_put_contents($userFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'success', 'msg' => '✅ 已保存到 [收集箱]']);
} else {
    echo json_encode(['status' => 'error', 'msg' => '写入失败']);
}
?>