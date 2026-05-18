<?php
// api.php
header("Access-Control-Allow-Origin: *");

// ================= 日志记录函数 =================
function writeLog($action, $status, $details = '') {
    $logDir = __DIR__ . '/logs';
    // 如果 logs 文件夹不存在，则自动创建
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    // 按天生成日志文件
    $logFile = $logDir . '/usage_' . date('Y-m-d') . '.log';
    
    $time = date('Y-m-d H:i:s');
    
    // 获取客户端真实 IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    // 拼接日志内容
    $logEntry = sprintf("[%s] [IP: %s] [Action: %s] [Status: %s] %s" . PHP_EOL, $time, $ip, $action, $status, $details);
    
    // 写入文件 
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}
// ================================================

$action = $_GET['action'] ?? '';

// 接口1：获取初始化数据
if ($action === 'get_init_data') {
    $data = ['bg' => [], 'img' => [], 'fonts' => []];
    
    if (is_dir('./bg/')) {
        foreach (scandir('./bg/') as $f) 
            if (preg_match("/\.(jpg|jpeg|png)$/i", $f)) $data['bg'][] = $f;
    }
    if (is_dir('./img/')) {
        foreach (scandir('./img/') as $f) 
            if (preg_match("/\.(jpg|jpeg|png)$/i", $f)) $data['img'][] = $f;
    }
    if (is_dir('./ttf/')) {
        foreach (scandir('./ttf/') as $f) 
            if (preg_match("/\.ttf$/i", $f)) $data['fonts'][] = $f;
    }

    // 记录日志
    writeLog('get_init_data', 'success', '获取了初始化数据');

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// 接口2：渲染合成图片
if ($action === 'render') {
    $bgType = $_POST['bg_type'] ?? 'default'; 
    $bgName = basename($_POST['bg_name'] ?? '');
    $bgBase64 = $_POST['bg_base64'] ?? '';
    
    $portraitName = basename($_POST['portrait'] ?? '');
    $fontName = basename($_POST['font'] ?? '');
    $text = $_POST['text'] ?? '';
    $colorHex = $_POST['color'] ?? '#000000';
    $align = $_POST['align'] ?? 'center';
    $fontSize = isset($_POST['size']) ? (int)$_POST['size'] : 24;

    $ptScale = isset($_POST['pt_scale']) ? (float)$_POST['pt_scale'] / 100 : 1; 
    $ptOffsetX = isset($_POST['pt_x']) ? (float)$_POST['pt_x'] : 0; 
    $ptOffsetY = isset($_POST['pt_y']) ? (float)$_POST['pt_y'] : 0; 

    $portraitPath = './img/' . $portraitName;
    $fontFile = './ttf/' . $fontName;

    // 错误处理与日志记录
    if (!file_exists($portraitPath)) {
        writeLog('render', 'error', "立绘不存在: {$portraitName}");
        die(json_encode(['error' => "立绘不存在"]));
    }
    if (!file_exists($fontFile)) {
        writeLog('render', 'error', "字体不存在: {$fontName}");
        die(json_encode(['error' => "字体不存在"]));
    }

    // 1. 处理背景图
    $bgImg = null;
    if ($bgType === 'custom' && !empty($bgBase64)) {
        $parts = explode(',', $bgBase64);
        if (isset($parts[1])) {
            $bgData = base64_decode($parts[1]);
            $bgImg = imagecreatefromstring($bgData);
        }
    } else {
        $bgPath = './bg/' . $bgName;
        if (file_exists($bgPath)) {
            $bgExt = strtolower(pathinfo($bgPath, PATHINFO_EXTENSION));
            $bgImg = ($bgExt === 'png') ? imagecreatefrompng($bgPath) : imagecreatefromjpeg($bgPath);
        }
    }

    // 2. 创建支持透明通道的主画布
    if ($bgImg) {
        $canvasW = imagesx($bgImg);
        $canvasH = imagesy($bgImg);
        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        
        // 开启透明通道保存
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        // 填充完全透明的底色 (防止黑底透出)
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
        
        // 将背景贴上去 (开启混色)
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $bgImg, 0, 0, 0, 0, $canvasW, $canvasH, $canvasW, $canvasH);
        imagedestroy($bgImg);
    } else {
        // 如果没有背景，创建一个全透明的画布
        $canvasW = 1080; $canvasH = 1920;
        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
    }

    // 3. 加载人物立绘
    $ext = strtolower(pathinfo($portraitPath, PATHINFO_EXTENSION));
    $portraitImg = ($ext === 'png') ? imagecreatefrompng($portraitPath) : imagecreatefromjpeg($portraitPath);
    
    // 保留其透明度
    imagealphablending($portraitImg, false);
    imagesavealpha($portraitImg, true);

    $origPtW = imagesx($portraitImg);
    $origPtH = imagesy($portraitImg);
    $newPtW = $origPtW * $ptScale;
    $newPtH = $origPtH * $ptScale;
    $dstX = ($canvasW * $ptOffsetX) / 100;
    $dstY = ($canvasH * $ptOffsetY) / 100;

    // 4. 将立绘渲染到主画布上 (必须开启 alphablending 才能透出下面的图层)
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $portraitImg, (int)$dstX, (int)$dstY, 0, 0, (int)$newPtW, (int)$newPtH, $origPtW, $origPtH);
    imagedestroy($portraitImg);

    // 5. 计算文字坐标
    $txtPath = './img/' . pathinfo($portraitName, PATHINFO_FILENAME) . '.txt';
    $pX1 = 10; $pY1 = 10; $pX2 = 90; $pY2 = 90; 
    if (file_exists($txtPath)) {
        $parts = preg_split('/\s+/', trim(file_get_contents($txtPath)));
        if (count($parts) >= 4) {
            $pX1 = (float)$parts[0]; $pY1 = (float)$parts[1];
            $pX2 = (float)$parts[2]; $pY2 = (float)$parts[3];
        }
    }

    $boxX_relative = $newPtW * ($pX1 / 100);
    $boxY_relative = $newPtH * ($pY1 / 100);
    $boxWidth = ($newPtW * ($pX2 / 100)) - $boxX_relative;
    $boxX = $dstX + $boxX_relative;
    $boxY = $dstY + $boxY_relative;

    // 6. 渲染文字
    list($r, $g, $b) = sscanf($colorHex, "#%02x%02x%02x");
    $textColor = imagecolorallocate($canvas, $r, $g, $b);
    $text = str_replace(['\r\n', '\n', "\r\n"], "\n", $text);
    $lines = explode("\n", $text);
    $lineHeight = $fontSize * 1.5; 
    $currentY = $boxY + $fontSize; 

    foreach ($lines as $line) {
        if (trim($line) === '') { $currentY += $lineHeight; continue; }
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $line);
        $textWidth = $bbox[2] - $bbox[0];
        
        $currentX = $boxX;
        if ($align === 'center') $currentX = $boxX + ($boxWidth - $textWidth) / 2;
        elseif ($align === 'right') $currentX = $boxX + $boxWidth - $textWidth;

        imagettftext($canvas, $fontSize, 0, $currentX, $currentY, $textColor, $fontFile, $line);
        $currentY += $lineHeight;
    }

    // 7. 输出 Base64
    ob_start();
    imagepng($canvas); // 使用 imagepng 保留透明度
    $imgData = ob_get_clean();
    imagedestroy($canvas);

    $base64Image = 'data:image/png;base64,' . base64_encode($imgData);
    
    // 记录成功生成的日志
    // 为了防止日志换行错乱，将文本中的换行符替换为空格，并截取前 50 个字符
    $logText = mb_substr(str_replace(["\r", "\n"], ' ', $text), 0, 50);
    $logBg = ($bgType === 'custom') ? '[自定义上传]' : $bgName;
    $details = sprintf("Bg: %s | Portrait: %s | Text: %s", $logBg, $portraitName, $logText);
    writeLog('render', 'success', $details);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'image' => $base64Image]);
    exit;
}
?>
