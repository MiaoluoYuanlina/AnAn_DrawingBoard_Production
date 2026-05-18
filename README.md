---

夏目安安 举画板立绘生成器

一个基于 PHP 后端 + HTML 前端的立绘合成工具，可以为《魔法少女的魔女审判》中的角色 夏目安安 (Natsume An-An) 手中所持的画板自定义文字或背景，快速生成专属立绘图片。

在线体验：https://anan.ica.wiki

---

✨ 功能特性

· 🎨 自由选择背景（服务器预设 / 自定义上传 Base64 图片）

· 👧 多款夏目安安立绘可选

· ✍️ 画板文字完全自定义，支持多行、换行、字号、颜色、对齐方式

· 📐 立绘位置与缩放可微调，轻松适配不同构图

· ⚡ 前端即时预览 + 一键生成 PNG 图片

· 🌐 纯浏览器端操作，无需安装任何软件

---

🛠 技术栈

层级 技术
后端 PHP（提供 API 接口）
前端 HTML + CSS + JavaScript
字体 服务器存储的 TrueType 字体
图像 GD 库 / Imagick 合成（视服务器环境）

---

📡 API 接口文档

1. 获取初始化数据

用于拉取当前可用的背景、立绘、字体列表。

· 请求方法：GET
· URL：https://api.xiaomiaoica.wiki/anan/api.php?action=get_init_data

示例响应：

```json
{
    "status": "success",
    "data": {
        "bg": ["bg1.jpg", "bg2.png"],
        "img": ["role1.png", "role2.png"],
        "fonts": ["simhei.ttf"]
    }
}
```

---

2. 渲染合成图片

提交所有参数，由服务器合成最终立绘图片并返回 Base64 数据。

· 请求方法：POST
· Content-Type：multipart/form-data
· URL：https://api.xiaomiaoica.wiki/anan/api.php?action=render

请求参数

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| bg_type | 文本 | 是 | 背景类型：default（使用服务器背景）或 custom（自定义上传） |
| bg_name | 文本 | 否 | 当 bg_type=default 时必填，背景文件名（如 bg1.jpg） |
| bg_base64 | 文本 | 否 | 当 bg_type=custom 时必填，自定义背景图的 Base64 数据 |
| portrait | 文本 | 是 | 立绘文件名（如 role1.png），需存在于服务器 img 目录 |
| font | 文本 | 是 | 字体文件名（如 simhei.ttf），需存在于服务器 ttf 目录 |
| text | 文本 | 是 | 渲染的文字，支持 \n 换行 |
| size | 整数 | 否 | 字体大小，默认 24 |
| color | 文本 | 否 | 文字颜色 HEX 码，例如 #ff0000，默认 #000000 |
| align | 文本 | 否 | 对齐方式：left，center，right，默认 center |
| pt_scale | 整数 | 否 | 立绘缩放百分比（如 100 表示 100%），默认 100 |
| pt_x | 整数 | 否 | 立绘 X 轴偏移百分比（可负），默认 0 |
| pt_y | 整数 | 否 | 立绘 Y 轴偏移百分比（可负），默认 0 |

示例响应：

```json
{
    "status": "success",
    "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
}
```

---

📁 项目结构与部署

```
/your-web-root/
├── index.html          # 前端页面
├── api.php             # API 接口文件
├── bg/                 # 服务器背景图片存放目录
├── img/                # 立绘图片存放目录
└── ttf/                # 字体文件存放目录
```

部署步骤

1. 将上述文件及目录上传至支持 PHP 的服务器（PHP 5.6+，建议开启 GD 或 Imagick 扩展）。
2. 在 bg/ 文件夹中放入背景图片（可选 支持jpg/png）。
3. 在 img/ 文件夹中放入夏目安安的立绘素材添加立绘布局配置文件（可选 PNG透明背景）。
4. 在 ttf/ 文件夹中放入需要使用的 TrueType 字体（可选 .ttf 文件）。
5. 修改 index.html 的api地址（可选）
6. 访问 index.html 即可使用。

⚠️ 安全提示：请确保 api.php 具备基本的路径校验，防止恶意读取服务器文件。

---

🖱 使用方法（前端）

1. 打开网页，页面会自动请求 初始化接口 加载可用背景、立绘、字体列表。
2. 选择背景来源：
   · 默认背景：从下拉菜单选择预设背景图。
   · 自定义背景：粘贴或上传一张图片的 Base64 数据。
3. 选择一个夏目安安立绘。
4. 在文本框中输入想要显示在画板上的文字，可用 \n 换行。
5. 调整字体、字号、颜色、对齐方式。
6. （可选）微调立绘的缩放比例和位置偏移。
7. 点击 生成图片 按钮，页面将 POST 请求发送至渲染 API，并直接展示返回的立绘图片。
8. 右键保存或长按下载生成的 PNG 图片。

---

💡 灵感来源

在某一天的夜晚，正在玩 gal 的我看到了吾辈举画板的样子，突然就想到了这个有趣的想法。

因为一个简单的瞬间，诞生了这个小工具，希望能让更多喜欢夏目安安的朋友自由创作，玩出属于自己的画面。

---

📜 开源许可

本项目采用 MIT License 开源，请遵循许可条款。游戏角色形象版权归属《魔法少女的魔女审判》官方所有，本项目仅为同人工具。

---
