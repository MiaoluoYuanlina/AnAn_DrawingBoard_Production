├── anan/
│   ├── api.php             # 后端核心逻辑入口
│   ├── bg/                 # 存放预设背景图片的目录
│   ├── img/                # 存放夏目安安各个姿势立绘的目录
│   └── ttf/                # 存放可用字体的目录
└── index.html              # 前端 UI 交互界面
这是一份为你量身定制的 `README.md` 文档。整体风格既保持了开源项目的清晰与专业，又在“项目背景”中融入了你提及的灵感来源，非常适合直接放入你的 GitHub 仓库中。

***
```markdown
# 魔法少女魔女审判 - 夏目安安画板生成器

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892bf.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

一个基于 **PHP 后端 + HTML5 前端** 制作的趣味图片生成器。

用户可以通过在线界面自定义《魔法少女魔女审判》中角色**夏目安安 (Natsume An-An)** 手中画板的内容，自由调整文字样式、立绘位置以及背景图片，最终合成一张完成度极高的角色立绘图片。

**🔗 在线演示：** [https://anan.ica.wiki/](https://anan.ica.wiki/)

---

## 📖 项目背景

在某一天的夜晚，正闲着无聊推着 galgame 的我，突然看到了吾辈（安安）在游戏里举着画板的可爱样子。灵光一闪，就诞生了这个把画板内容“迫害/自定义”成任意梗图的有趣想法。于是就有了这个小工具。

---

## ✨ 功能特性

- **数据动态初始化**：前端自动从后端接口拉取可用的背景图、角色立绘以及字体列表。
- **丰富的自定义文字**：支持多行输入（`\n`）、字体大小、自定义颜色（HEX 码）以及对齐方式（左/中/右）。
- **灵活的立绘微调**：支持对夏目安安的立绘进行百分比缩放（`pt_scale`）以及 X/Y 轴的偏移调整。
- **双背景模式**：既可以使用服务器预设的背景，也支持用户自行上传自定义背景图片。
- **无缝 Base64 输出**：合成后的图片直接以 Base64 格式返回，前端无需刷新即可实时预览与保存。

---

## 🛠️ API 接口说明

项目采用前后端分离的思想，后端 `api.php` 提供了两个核心接口。

### 1. 获取初始化数据
用于前端加载时初始化背景、立绘和字体选择框。

- **请求方法**：`GET`
- **请求 URL**：`[https://api.xiaomiaoica.wiki/anan/api.php?action=get_init_data](https://api.xiaomiaoica.wiki/anan/api.php?action=get_init_data)`
- **返回示例**：
  ```json
  {
      "status": "success",
      "data": {
          "bg": ["bg1.jpg", "bg2.png"],
          "img": ["role1.png", "role2.png"],
          "fonts": ["simhei.ttf"]
      }
  }
