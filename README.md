项目地址 https://github.com/MiaoluoYuanlina/AnAn_DrawingBoard_Production
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
| 层级 | 技术 |
|------|------|
| 后端 | PHP |
| 前端 | HTML + CSS + JavaScript |

---

根据排版系统重构后的最新流式排版设计（支持多子文本、自定义图片插图、彩色 Emoji 以及自动折行），我为你重新整理并更新了 API 接口文档。

原先根节点的文本控制参数（`text`、`size`、`color`、`align`、`font`）已全部整合并升华为 `elements` 嵌套 JSON 数组。

---

# 📡 API 接口文档

## 1. 获取初始化数据

用于拉取当前服务器上可用的背景、立绘、字体列表。

* **请求方法**：`GET`
* **URL**：`https://api.xiaomiaoica.wiki/anan/api.php?action=get_init_data`

### 示例响应：

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

## 2. 渲染合成图片

提交背景、立绘参数及排版元素（JSON 格式），由服务器进行流式排版、Emoji 智能解析渲染，并返回合成后的 Base64 图片数据。

* **请求方法**：`POST`
* **Content-Type**：`multipart/form-data`
* **URL**：`https://api.xiaomiaoica.wiki/anan/api.php?action=render`

### 请求参数

| 参数 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| **bg_type** | 文本 | 是 | 背景类型：`default`（使用服务器背景）或 `custom`（自定义上传） |
| **bg_name** | 文本 | 否 | 当 `bg_type=default` 时必填。背景文件名（如 `bg1.jpg`） |
| **bg_base64** | 文本 | 否 | 当 `bg_type=custom` 时必填。自定义背景图的 Base64 数据（需带 Data URL 前缀） |
| **portrait** | 文本 | 是 | 立绘文件名（如 `role1.png`），需存在于服务器 `img` 目录下 |
| **pt_scale** | 整数 | 否 | 立绘缩放百分比（如 `100` 表示 100%），默认 `100` |
| **pt_x** | 整数 | 否 | 立绘 X 轴偏移百分比（可为负数），默认 `0` |
| **pt_y** | 整数 | 否 | 立绘 Y 轴偏移百分比（可为负数），默认 `0` |
| **elements** | 文本 (JSON) | 是 | 核心排版元素数组，详见下方 **`elements` 结构说明** |
| **debug** | 文本 | 否 | 调试模式：`1`（在图片上绘制红色/绿色配字安全区边界和溢出警告）或 `0`（默认，不绘制） |

---

### `elements` JSON 结构说明

`elements` 参数接收一个 **JSON 格式的数组**。数组中的每一个对象代表一个排版图层。系统会按照数组的先后顺序，在立绘同名 `.txt` 规定的安全区域内，**自上而下自动流式排版并自动换行**。

#### A. 文本图层对象属性 (`type="text"`)

| 属性 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| **type** | 文本 | 是 | 固定为 `"text"` |
| **content** | 文本 | 是 | 文本内容。**支持原生彩色 Emoji**（如 `❤️❤️`），支持 `\n` 强制换行，超出安全宽度时会自动折行 |
| **font** | 文本 | 是 | 字体文件名（如 `simhei.ttf`），需存在于服务器 `ttf` 目录 |
| **size** | 整数 | 是 | 字体大小（像素值，如 `28`） |
| **color** | 文本 | 是 | 文字颜色 HEX 码（如 `"#333333"`） |
| **align** | 文本 | 是 | 段落对齐方式：`"left"`（左对齐）、`"center"`（居中对齐）、`"right"`（右对齐） |
| **marginBottom** | 整数 | 是 | 段后距（像素值，控制与下方元素的间距，如 `15`） |

#### B. 图片图层对象属性 (`type="image"`)

| 属性 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| **type** | 文本 | 是 | 固定为 `"image"` |
| **fileBase64** | 文本 | 是 | 插入图片的 Base64 数据（需带 `data:image/...;base64,` 前缀） |
| **scale** | 整数 | 是 | 宽度占比百分比（`10` 到 `100`），表示占安全排版区域宽度的比例 |
| **align** | 文本 | 是 | 图片对齐方式：`"left"`（靠左）、`"center"`（居中）、`"right"`（靠右） |
| **marginBottom** | 整数 | 是 | 图后距（像素值，控制与下方元素的间距，如 `15`） |

#### `elements` 参数 JSON 示例如下：

```json
[
  {
    "id": 0,
    "type": "text",
    "content": "夏目安安 ❤️ 自动换行测试 ✨",
    "font": "simhei.ttf",
    "size": 32,
    "color": "#ff0000",
    "align": "center",
    "marginBottom": 20
  },
  {
    "id": 1,
    "type": "image",
    "fileBase64": "data:image/png;base64,iVBORw0KGgoAAAANS...",
    "scale": 80,
    "align": "center",
    "marginBottom": 15
  }
]
```

---

### 示例响应：

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
2. 虽然要写在画板的文字
3. 调整字体立绘位置背景图片
4. 点击生成等待生成
5. 下载图片

---

💡 灵感来源

在某一天的夜晚，正在玩 gal 的我看到了吾辈举画板的样子，突然就想到了这个有趣的想法。

因为一个简单的瞬间，诞生了这个小工具，希望能让更多喜欢夏目安安的玩家自由创作，制作属于自己的表情包。

---

📜 开源许可

本项目采用 MIT License 开源，请遵循许可条款。游戏角色形象版权归属《魔法少女的魔女审判》官方所有，本项目仅为同人工具。

---
