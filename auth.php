<?php
/**
 * KAI.WORK Auth System (V8.5.3 Legacy Compat)
 * 修复：使用原始 HTTP 头强制写入 SameSite=None，兼容旧版 PHP
 */
header('Content-Type: application/json');

// [核心修复] 设置合理的 Session 生命周期
ini_set('session.gc_maxlifetime', 30 * 86400);
ini_set('session.cookie_lifetime', 0);

session_start();

// [核心修复] 定义统一的 Cookie 刷新函数，支持 30 天持久化
function syncSessionCookie($forceSafe = null) {
    if (!session_id()) return;
    
    $name = session_name();
    $id = session_id();
    
    // 优先使用传入的状态，否则从 Session 读取
    $isSafe = $forceSafe !== null ? $forceSafe : (isset($_SESSION['remember_me']) && $_SESSION['remember_me']);
    $expireTime = $isSafe ? time() + 30 * 86400 : 0;
    
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    // 兼容性处理：非 HTTPS 环境下，SameSite 必须为 Lax 才能在现代浏览器正常工作
    $samesite = $is_https ? 'None' : 'Lax';
    
    $cookie = "$name=$id; path=/; HttpOnly; SameSite=$samesite";
    if ($is_https) $cookie .= "; Secure";
    if ($expireTime > 0) {
        $headerExpire = gmdate('D, d M Y H:i:s T', $expireTime);
        $cookie .= "; expires=$headerExpire";
    }
    
    header("Set-Cookie: $cookie", false);
}

// 自动根据当前状态同步 Cookie (注销除外)
if (($_POST['action'] ?? '') !== 'logout') {
    syncSessionCookie();
}

$usersFile = '../data/users.json';
$userDataDir = '../data/user_data/';
$templateFile = '../data/templates.json';

$action = $_POST['action'] ?? '';

// 获取用户列表
function getUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) file_put_contents($usersFile, '[]');
    return json_decode(file_get_contents($usersFile), true);
}

// 保存用户列表
function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 1. 注册
if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$username || !$password) die(json_encode(['status' => 'error', 'msg' => '请输入账号密码']));
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) die(json_encode(['status' => 'error', 'msg' => '用户名只能包含字母、数字和下划线']));

    $users = getUsers();
    foreach ($users as $u) {
        if ($u['username'] === $username) die(json_encode(['status' => 'error', 'msg' => '用户已存在']));
    }

    if (!file_exists($userDataDir)) mkdir($userDataDir, 0777, true);
    $initData = file_exists($templateFile) ? file_get_contents($templateFile) : '{"categories":[]}';
    file_put_contents($userDataDir . $username . '.json', $initData);

    $users[] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT)
    ];
    saveUsers($users);
    
    $_SESSION['user'] = $username;
    echo json_encode(['status' => 'success', 'msg' => '注册成功']);
    exit;
}

// 2. 登录
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $isSafe = ($_POST['safe_login'] ?? 'false') === 'true';

    $users = getUsers();
    foreach ($users as $u) {
        if ($u['username'] === $username && password_verify($password, $u['password'])) {
            
            $_SESSION['user'] = $username;

            if ($isSafe) {
                $_SESSION['remember_me'] = true;
                syncSessionCookie(true); 
            }
            
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'msg' => '账号或密码错误']);
    exit;
}

// 3. 检查状态
if ($action === 'check') {
    if (isset($_SESSION['user'])) {
        echo json_encode(['status' => 'logged_in', 'user' => $_SESSION['user']]);
    } else {
        echo json_encode(['status' => 'guest']);
    }
    exit;
}

// 4. 注销
if ($action === 'logout') {
    session_destroy();
    
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $samesite = $is_https ? 'None' : 'Lax';
    $cookie = session_name() . "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; HttpOnly; SameSite=$samesite";
    if ($is_https) $cookie .= "; Secure";
    header("Set-Cookie: $cookie");
    
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => '无效请求']);
?>
