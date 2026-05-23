<?php
// api.php
header("Access-Control-Allow-Origin: *");

// ================= 日志记录函数 =================
function writeLog($action, $status, $details = '') {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/usage_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    $logEntry = sprintf("[%s] [IP: %s] [Action: %s] [Status: %s] %s" . PHP_EOL, $time, $ip, $action, $status, $details);
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}
// ================================================


// ================= Emoji 辅助处理函数 =================

// 将 UTF-8 字符转换为 Unicode 码点
function utf8ToUnicode($char) {
    $ord = ord($char[0]);
    if ($ord < 128) return $ord;
    if ($ord < 224) return (($ord & 31) << 6) + (ord($char[1]) & 63);
    if ($ord < 240) return (($ord & 15) << 12) + ((ord($char[1]) & 63) << 6) + (ord($char[2]) & 63);
    return (($ord & 7) << 18) + ((ord($char[1]) & 63) << 12) + ((ord($char[2]) & 63) << 6) + (ord($char[3]) & 63);
}

// 将 Emoji 字符转换为 Twemoji 标准文件名（16进制文件名）
function emojiToHex($emoji) {
    $emoji = preg_replace('/\x{FE0F}/u', '', $emoji); // 移除变体选择符-16 (防止匹配失败)
    $hex = [];
    $len = mb_strlen($emoji, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($emoji, $i, 1, 'UTF-8');
        $hex[] = dechex(utf8ToUnicode($char));
    }
    return implode('-', $hex);
}

// 获取并缓存 Emoji PNG 图片，返回 GD 图像资源
function getEmojiImage($emoji, $fontSize) {
    $hex = emojiToHex($emoji);
    $cacheDir = __DIR__ . '/emoji_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    
    $localFile = $cacheDir . '/' . $hex . '.png';
    // 如果本地没有缓存，则从 Twemoji CDN 下载高质量 72x72 PNG 
    if (!file_exists($localFile)) {
        $url = "https://cdnjs.cloudflare.com/ajax/libs/twemoji/14.0.2/72x72/{$hex}.png";
        $ctx = stream_context_create(['http' => ['timeout' => 3]]); // 设置3秒超时
        $imgData = @file_get_contents($url, false, $ctx);
        if ($imgData) {
            @file_put_contents($localFile, $imgData);
        }
    }
    
    if (file_exists($localFile)) {
        return @imagecreatefrompng($localFile);
    }
    return null;
}

// 将含有 Emoji 的单行文本切分为普通文字与 Emoji 的 Token 数组
function tokenizeLine($line) {
    // 覆盖绝大多数标准 Emoji、表情、符号、心形等 Unicode 正则
    $pattern = '/(
        \x{2764}\x{FE0F}? | # 红心
        [\x{1F600}-\x{1F64F}] | # 表情
        [\x{1F300}-\x{1F5FF}] | # 杂项符号
        [\x{1F680}-\x{1F6FF}] | # 交通与地图
        [\x{2600}-\x{27BF}][\x{FE0F}]? | # 符号与杂项
        [\x{1F900}-\x{1F9FF}] | # 补充符号
        [\x{1FA00}-\x{1FAFF}]   # 扩展符号A
    )/ux';
    
    $parts = preg_split($pattern, $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $tokens = [];
    foreach ($parts as $part) {
        $isEmoji = preg_match($pattern, $part);
        $tokens[] = [
            'type' => $isEmoji ? 'emoji' : 'text',
            'content' => $part
        ];
    }
    return $tokens;
}

// 支持 Emoji 混合测算宽度的智能自动折行算法
function wrapText($fontSize, $fontFile, $text, $maxWidth) {
    $wrappedLines = [];
    $rawLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    
    foreach ($rawLines as $rawLine) {
        if (trim($rawLine) === '') {
            $wrappedLines[] = []; // 空行
            continue;
        }
        
        $tokens = tokenizeLine($rawLine);
        $currentLineTokens = [];
        
        foreach ($tokens as $token) {
            if ($token['type'] === 'text') {
                $chars = preg_split('//u', $token['content'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($chars as $char) {
                    $testLine = getTokensTextWidth($currentLineTokens) . $char;
                    $bbox = imagettfbbox($fontSize, 0, $fontFile, $testLine);
                    $testWidth = $bbox[2] - $bbox[0];
                    
                    if ($testWidth > $maxWidth && !empty($currentLineTokens)) {
                        $wrappedLines[] = $currentLineTokens;
                        $currentLineTokens = [['type' => 'text', 'content' => $char]];
                    } else {
                        $lastIdx = count($currentLineTokens) - 1;
                        if ($lastIdx >= 0 && $currentLineTokens[$lastIdx]['type'] === 'text') {
                            $currentLineTokens[$lastIdx]['content'] .= $char;
                        } else {
                            $currentLineTokens[] = ['type' => 'text', 'content' => $char];
                        }
                    }
                }
            } else {
                // 遇到 Emoji，估算其占位宽度（约等于 1.1 倍字号）
                $emojiWidth = $fontSize * 1.1;
                $testLine = getTokensTextWidth($currentLineTokens);
                $bbox = imagettfbbox($fontSize, 0, $fontFile, $testLine);
                $testWidth = ($bbox[2] - $bbox[0]) + $emojiWidth;
                
                if ($testWidth > $maxWidth && !empty($currentLineTokens)) {
                    $wrappedLines[] = $currentLineTokens;
                    $currentLineTokens = [$token];
                } else {
                    $currentLineTokens[] = $token;
                }
            }
        }
        if (!empty($currentLineTokens)) {
            $wrappedLines[] = $currentLineTokens;
        }
    }
    return $wrappedLines;
}

// 辅助估算 Token 序列在折行计算中的等效文本占位
function getTokensTextWidth($tokens) {
    $str = '';
    foreach ($tokens as $t) {
        if ($t['type'] === 'text') {
            $str .= $t['content'];
        } else {
            $str .= '  '; // 用两个空格来大概模拟一个 Emoji 的宽度
        }
    }
    return $str;
}
// =====================================================================


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
    writeLog('get_init_data', 'success', '获取了初始化数据');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// 接口2：流式排版渲染合成图片 (支持彩色 Emoji)
if ($action === 'render') {
    $bgType = $_POST['bg_type'] ?? 'default'; 
    $bgName = basename($_POST['bg_name'] ?? '');
    $bgBase64 = $_POST['bg_base64'] ?? '';
    $portraitName = basename($_POST['portrait'] ?? '');
    
    $ptScale = isset($_POST['pt_scale']) ? (float)$_POST['pt_scale'] / 100 : 1; 
    $ptOffsetX = isset($_POST['pt_x']) ? (float)$_POST['pt_x'] : 0; 
    $ptOffsetY = isset($_POST['pt_y']) ? (float)$_POST['pt_y'] : 0; 

    $elementsJson = $_POST['elements'] ?? '[]';
    $elements = json_decode($elementsJson, true) ?? [];
    $debugMode = isset($_POST['debug']) && $_POST['debug'] === '1';

    $portraitPath = './img/' . $portraitName;
    if (!file_exists($portraitPath)) {
        writeLog('render', 'error', "立绘不存在: {$portraitName}");
        die(json_encode(['error' => "立绘不存在"]));
    }

    // 1. 处理背景
    $bgImg = null;
    if ($bgType === 'custom' && !empty($bgBase64)) {
        $parts = explode(',', $bgBase64);
        if (isset($parts[1])) {
            $bgImg = imagecreatefromstring(base64_decode($parts[1]));
        }
    } else {
        $bgPath = './bg/' . $bgName;
        if (file_exists($bgPath)) {
            $bgExt = strtolower(pathinfo($bgPath, PATHINFO_EXTENSION));
            $bgImg = ($bgExt === 'png') ? imagecreatefrompng($bgPath) : imagecreatefromjpeg($bgPath);
        }
    }

    // 2. 创建主画布
    if ($bgImg) {
        $canvasW = imagesx($bgImg);
        $canvasH = imagesy($bgImg);
        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $bgImg, 0, 0, 0, 0, $canvasW, $canvasH, $canvasW, $canvasH);
        imagedestroy($bgImg);
    } else {
        $canvasW = 1080; $canvasH = 1920;
        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
    }

    // 3. 渲染人物立绘
    $ext = strtolower(pathinfo($portraitPath, PATHINFO_EXTENSION));
    $portraitImg = ($ext === 'png') ? @imagecreatefrompng($portraitPath) : @imagecreatefromjpeg($portraitPath);
    
    $newPtW = 0; $newPtH = 0;
    $dstX = 0; $dstY = 0;

    if($portraitImg) {
        imagealphablending($portraitImg, false);
        imagesavealpha($portraitImg, true);
        $origPtW = imagesx($portraitImg);
        $origPtH = imagesy($portraitImg);
        $newPtW = $origPtW * $ptScale;
        $newPtH = $origPtH * $ptScale;
        $dstX = ($canvasW * $ptOffsetX) / 100;
        $dstY = ($canvasH * $ptOffsetY) / 100;

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $portraitImg, (int)$dstX, (int)$dstY, 0, 0, (int)$newPtW, (int)$newPtH, $origPtW, $origPtH);
        imagedestroy($portraitImg);
    }

    // 4. 读取同名 .txt 框定自动排版的安全区域
    $pX1 = 10; $pY1 = 10; $pX2 = 90; $pY2 = 90; 
    $txtPath = './img/' . pathinfo($portraitName, PATHINFO_FILENAME) . '.txt';
    if (file_exists($txtPath)) {
        $parts = preg_split('/\s+/', trim(file_get_contents($txtPath)));
        if (count($parts) >= 4) {
            $pX1 = (float)$parts[0]; $pY1 = (float)$parts[1];
            $pX2 = (float)$parts[2]; $pY2 = (float)$parts[3];
        }
    }

    // 计算排版区域绝对像素坐标
    $boxX_relative = $newPtW * ($pX1 / 100);
    $boxY_relative = $newPtH * ($pY1 / 100);
    $boxWidth = ($newPtW * ($pX2 / 100)) - $boxX_relative;
    $boxHeight = ($newPtH * ($pY2 / 100)) - $boxY_relative;

    $boxX = $dstX + $boxX_relative;
    $boxY = $dstY + $boxY_relative;

    // 5. 【流式排版流引擎】自动堆叠渲染
    $currentY = $boxY; 
    imagealphablending($canvas, true);

    foreach ($elements as $el) {
        $marginBottom = isset($el['marginBottom']) ? (int)$el['marginBottom'] : 15;
        $align = isset($el['align']) ? $el['align'] : 'center';

        if ($el['type'] === 'text') {
            $fontName = basename($el['font']);
            $fontFile = './ttf/' . $fontName;
            if (!file_exists($fontFile)) continue;

            $fontSize = (int)$el['size'];
            list($r, $g, $b) = sscanf($el['color'], "#%02x%02x%02x");
            $textColor = imagecolorallocate($canvas, $r, $g, $b);

            // 获取支持 Emoji 的智能折行 Token 嵌套数组
            $lines = wrapText($fontSize, $fontFile, $el['content'], $boxWidth);
            $lineHeight = $fontSize * 1.4;

            // 测算首行基线，以便顶部精确对齐
            $firstLineText = 'Top';
            if (isset($lines[0])) {
                foreach ($lines[0] as $t) {
                    if ($t['type'] === 'text') { $firstLineText = $t['content']; break; }
                }
            }
            $bboxFirst = imagettfbbox($fontSize, 0, $fontFile, $firstLineText);
            $ascent = abs($bboxFirst[7]);

            $drawY = $currentY + $ascent; 

            foreach ($lines as $lineTokens) {
                if (empty($lineTokens)) { $drawY += $lineHeight; continue; }

                // 1. 测算当前混合行（文字+Emoji）的总宽度
                $lineWidth = 0;
                foreach ($lineTokens as $t) {
                    if ($t['type'] === 'text') {
                        $bbox = imagettfbbox($fontSize, 0, $fontFile, $t['content']);
                        $lineWidth += ($bbox[2] - $bbox[0]);
                    } else {
                        $lineWidth += $fontSize * 1.1; // 加上 Emoji 占位宽
                    }
                }

                // 2. 根据对齐方式，计算当前行起始 X 轴坐标
                $curX = $boxX;
                if ($align === 'center') {
                    $curX = $boxX + ($boxWidth - $lineWidth) / 2;
                } elseif ($align === 'right') {
                    $curX = $boxX + $boxWidth - $lineWidth;
                }

                // 3. 逐个渲染 Token（文字或 Emoji）
                foreach ($lineTokens as $t) {
                    if ($t['type'] === 'text') {
                        imagettftext($canvas, $fontSize, 0, (int)$curX, (int)$drawY, $textColor, $fontFile, $t['content']);
                        $bbox = imagettfbbox($fontSize, 0, $fontFile, $t['content']);
                        $curX += ($bbox[2] - $bbox[0]);
                    } else {
                        // 渲染本地缓存或在线下载的彩色 Emoji PNG 
                        $emojiImg = getEmojiImage($t['content'], $fontSize);
                        if ($emojiImg) {
                            $emojiSize = $fontSize * 1.1;
                            // 高精度垂直居中对齐：将 Emoji 顶部对齐到文字 Ascent 顶端
                            $emojiY = $drawY - ($fontSize * 0.9); 
                            
                            // 开启混色并贴图
                            imagealphablending($canvas, true);
                            imagecopyresampled($canvas, $emojiImg, (int)$curX, (int)$emojiY, 0, 0, (int)$emojiSize, (int)$emojiSize, imagesx($emojiImg), imagesy($emojiImg));
                            imagedestroy($emojiImg);
                        }
                        $curX += $fontSize * 1.1;
                    }
                }
                $drawY += $lineHeight;
            }

            // 累加计算排版水位的 Y 轴增量
            $currentY += $ascent + (count($lines) - 1) * $lineHeight + $marginBottom;
        } 
        elseif ($el['type'] === 'image' && !empty($el['fileBase64'])) {
            $parts = explode(',', $el['fileBase64']);
            if (isset($parts[1])) {
                $layerImg = @imagecreatefromstring(base64_decode($parts[1]));
                if ($layerImg) {
                    $layerW = imagesx($layerImg);
                    $layerH = imagesy($layerImg);

                    $userScale = (float)$el['scale'] / 100;
                    $newLayerW = $boxWidth * $userScale;
                    $newLayerH = $layerH * ($newLayerW / $layerW);

                    $drawX = $boxX;
                    if ($align === 'center') {
                        $drawX = $boxX + ($boxWidth - $newLayerW) / 2;
                    } elseif ($align === 'right') {
                        $drawX = $boxX + $boxWidth - $newLayerW;
                    }

                    imagecopyresampled($canvas, $layerImg, (int)$drawX, (int)$currentY, 0, 0, (int)$newLayerW, (int)$newLayerH, $layerW, $layerH);
                    imagedestroy($layerImg);

                    $currentY += $newLayerH + $marginBottom;
                }
            }
        }
    }

    // 6. 【调试模式】绘制排版辅助框
    if ($debugMode) {
        $debugFillColor = imagecolorallocatealpha($canvas, 0, 255, 0, 110); 
        $debugBorderColor = imagecolorallocate($canvas, 0, 255, 0);
        
        $isOverflow = $currentY > ($boxY + $boxHeight);
        if ($isOverflow) {
            $debugFillColor = imagecolorallocatealpha($canvas, 255, 0, 0, 110); 
            $debugBorderColor = imagecolorallocate($canvas, 255, 0, 0);
        }

        imagesetthickness($canvas, 4);
        imagerectangle($canvas, (int)$boxX, (int)$boxY, (int)($boxX + $boxWidth), (int)($boxY + $boxHeight), $debugBorderColor);
        imagefilledrectangle($canvas, (int)$boxX, (int)$boxY, (int)($boxX + $boxWidth), (int)($boxY + $boxHeight), $debugFillColor);

        imagesetthickness($canvas, 2);
        $dashColor = imagecolorallocate($canvas, 0, 0, 255);
        imagedashedline($canvas, (int)$boxX, (int)$currentY, (int)($boxX + $boxWidth), (int)$currentY, $dashColor);

        $statusText = $isOverflow ? "OVERFLOW ALERT! (Out of safe area)" : "WORD FLOW AUTO-LAYOUT SAFE";
        imagestring($canvas, 5, (int)$boxX + 10, (int)$boxY + 10, $statusText, $debugBorderColor);
    }

    // 7. 输出 Base64
    ob_start();
    imagepng($canvas); 
    $imgData = ob_get_clean();
    imagedestroy($canvas);

    $base64Image = 'data:image/png;base64,' . base64_encode($imgData);

    // ================= 日志记录逻辑 =================
    $allTexts = [];
    foreach ($elements as $el) {
        if ($el['type'] === 'text') {
            $allTexts[] = $el['content'];
        }
    }
    $textCombined = implode(' | ', $allTexts);
    
    $logText = mb_substr(str_replace(["\r", "\n"], ' ', $textCombined), 0, 50);
    $logBg = ($bgType === 'custom') ? '[自定义上传]' : $bgName;
    
    $details = sprintf("Bg: %s | Portrait: %s | Text: %s", $logBg, $portraitName, $logText);
    writeLog('render', 'success', $details . ($debugMode ? " [调试模式]" : ""));
    // =====================================================

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'image' => $base64Image]);
    exit;
}
?>
