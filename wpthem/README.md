# 樊老师数学课堂 - WordPress 主题包开发说明 (V1.0)

本项目主题 100% 严格遵循 **《Math Learning Platform 前端主题开发接口规范 V1.0》**、**《前端主题开发规则 V1.0》**、**《前端页面开发规范 V1.0》** 与 **《前端 UI 设计规范 V1.0》**。

---

## 1. 架构原则
- **插件负责业务**：课程数据、Tutor LMS 适配、课程授权、试看逻辑、学习进度计算、HLS 视频保护、安全 URL。
- **主题负责表现**：HTML 结构、CSS (Tailwind)、纯原生 JS 交互、自适应响应式与黑板播放器视觉。
- **杜绝直查数据库**：主题不直接执行 SQL 查询，不读取 `get_user_meta`，统一调用 `MathCourse\Course\Course_Service`。

---

## 2. 页面结构与 URL 规范
1. **首页** (`front-page.php` / `index.php`)：紧凑 Hero Banner（四大体系方框一字排开）、两大分类高亮 Tab 切换（「初中系统课」与「教辅配套课」）。
2. **课程中心** (`page-courses.php`)：展示全部公开课程，卡片点击直达 `/learning/?course_id={ID}`，无独立详情页。
3. **播放页面** (`page-learning.php`)：标准路径 `/learning/?course_id={ID}&lesson_id={ID}`，支持空视频防护与多层独立滚动折叠目录。
4. **学习中心** (`page-dashboard.php`)：仅登录学员可见，仅展示 `access === true` 授权课程、进度条与「继续学习」。

---

## 3. 标准 Shortcodes
- `[mathcourse_course_center]`：课程中心公开列表
- `[mathcourse_course_directory course_id="25"]`：单门课程目录
- `[mathcourse_learning_center]`：学员专属已授权课程与进度
