SET NAMES utf8mb4;

SET @admin_id := (SELECT id FROM users WHERE role='ADMIN' ORDER BY id LIMIT 1);

INSERT IGNORE INTO categories (name, slug, description, created_at, updated_at) VALUES
('[演示] AI 入门', 'demo-home-ai', '人工智能、提示词和自动化工具入门内容', NOW(), NOW()),
('[演示] 前端开发', 'demo-home-frontend', 'HTML、CSS、JavaScript 与响应式页面实战', NOW(), NOW()),
('[演示] 后端架构', 'demo-home-backend', '接口设计、权限、安全和性能优化', NOW(), NOW()),
('[演示] 移动适配', 'demo-home-mobile', '移动端布局、触控交互和页面体验优化', NOW(), NOW());

INSERT IGNORE INTO tags (name, slug, created_at, updated_at) VALUES
('[演示] 实战案例', 'demo-home-practice', NOW(), NOW()),
('[演示] 新手友好', 'demo-home-beginner', NOW(), NOW());

INSERT INTO videos (title, description, cover_url, category_id, created_by_id, required_level, status, allow_comments, play_count, created_at, updated_at)
SELECT '[演示] 10 分钟搭建学习首页', '从导航、英雄区、分类入口到课程卡片，快速了解在线学习平台首页结构。', NULL, c.id, @admin_id, 'NORMAL', 'PUBLISHED', 1, 986, NOW(), NOW()
FROM categories c WHERE c.slug='demo-home-frontend' AND @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM videos WHERE title='[演示] 10 分钟搭建学习首页');

INSERT INTO videos (title, description, cover_url, category_id, created_by_id, required_level, status, allow_comments, play_count, created_at, updated_at)
SELECT '[演示] AI 工具效率工作流', '用 AI 辅助整理需求、生成提纲、检查页面文案，适合刚开始使用智能工具的学习者。', NULL, c.id, @admin_id, 'NORMAL', 'PUBLISHED', 1, 1250, NOW(), NOW()
FROM categories c WHERE c.slug='demo-home-ai' AND @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM videos WHERE title='[演示] AI 工具效率工作流');

INSERT INTO videos (title, description, cover_url, category_id, created_by_id, required_level, status, allow_comments, play_count, created_at, updated_at)
SELECT '[演示] 视频平台权限设计', '讲解普通用户、VIP、SVIP 与课程单独授权的组合方式。', NULL, c.id, @admin_id, 'VIP', 'PUBLISHED', 1, 732, NOW(), NOW()
FROM categories c WHERE c.slug='demo-home-backend' AND @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM videos WHERE title='[演示] 视频平台权限设计');

INSERT INTO courses (title, description, cover_url, required_level, status, created_at, updated_at)
SELECT '[演示] 在线学习平台从 0 到 1', '覆盖首页、视频详情、课程目录、账号中心和后台管理的完整演示课程。', NULL, NULL, 'PUBLISHED', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM courses WHERE title='[演示] 在线学习平台从 0 到 1');

INSERT INTO courses (title, description, cover_url, required_level, status, created_at, updated_at)
SELECT '[演示] ThinkPHP 项目实战', '围绕路由、控制器、服务层、模板和数据库完成一个可运行的内容管理平台。', NULL, 'NORMAL', 'PUBLISHED', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM courses WHERE title='[演示] ThinkPHP 项目实战');

INSERT INTO lessons (course_id, title, summary, sort_order, status, created_at, updated_at)
SELECT c.id, '第 1 课：课程介绍与学习路径', '了解本课程目标、适合人群和最终效果。', 1, 'PUBLISHED', NOW(), NOW()
FROM courses c WHERE c.title LIKE '[演示]%' AND NOT EXISTS (SELECT 1 FROM lessons l WHERE l.course_id=c.id AND l.title='第 1 课：课程介绍与学习路径');
