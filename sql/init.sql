SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `play_url_caches`;
DROP TABLE IF EXISTS `import_tasks`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `video_likes`;
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `watch_histories`;
DROP TABLE IF EXISTS `grants`;
DROP TABLE IF EXISTS `video_assets`;
DROP TABLE IF EXISTS `video_tags`;
DROP TABLE IF EXISTS `videos`;
DROP TABLE IF EXISTS `lessons`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `account_verifications`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(191) NULL,
  `phone` VARCHAR(32) NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `avatar_url` VARCHAR(500) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('ADMIN', 'USER') NOT NULL DEFAULT 'USER',
  `level` ENUM('NORMAL', 'VIP', 'SVIP') NOT NULL DEFAULT 'NORMAL',
  `status` ENUM('ACTIVE', 'DISABLED', 'PENDING') NOT NULL DEFAULT 'ACTIVE',
  `valid_until` DATETIME NULL,
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_users_phone` (`phone`),
  KEY `idx_users_created_at` (`created_at`),
  KEY `idx_users_status_role` (`status`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `account_verifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `purpose` ENUM('register', 'bind') NOT NULL,
  `channel` ENUM('email', 'phone') NOT NULL,
  `recipient_hash` CHAR(64) NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `request_ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(500) NULL,
  `expires_at` DATETIME NOT NULL,
  `consumed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_account_verifications_lookup` (`purpose`, `channel`, `recipient_hash`),
  KEY `idx_account_verifications_user` (`user_id`),
  KEY `idx_account_verifications_expires_at` (`expires_at`),
  CONSTRAINT `fk_account_verifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `channel` ENUM('email', 'phone') NOT NULL,
  `selector` VARCHAR(64) NULL,
  `token_hash` VARCHAR(255) NULL,
  `code_hash` VARCHAR(255) NULL,
  `recipient_hash` CHAR(64) NOT NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `request_ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(500) NULL,
  `expires_at` DATETIME NOT NULL,
  `consumed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_resets_selector` (`selector`),
  KEY `idx_password_resets_user_channel` (`user_id`, `channel`),
  KEY `idx_password_resets_expires_at` (`expires_at`),
  KEY `idx_password_resets_recipient_hash` (`recipient_hash`),
  CONSTRAINT `fk_password_resets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `courses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `cover_url` VARCHAR(500) NULL,
  `required_level` ENUM('NORMAL', 'VIP', 'SVIP') NULL,
  `status` ENUM('DRAFT', 'PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_courses_status_created_at` (`status`, `created_at`),
  KEY `idx_courses_required_level` (`required_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_name` (`name`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  KEY `idx_categories_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tags_name` (`name`),
  UNIQUE KEY `uk_tags_slug` (`slug`),
  KEY `idx_tags_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lessons` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `summary` TEXT NULL,
  `video_object_key` VARCHAR(500) NULL,
  `duration_sec` INT UNSIGNED NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('DRAFT', 'PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lessons_course_id` (`course_id`),
  KEY `idx_lessons_course_status_sort` (`course_id`, `status`, `sort_order`),
  CONSTRAINT `fk_lessons_course_id` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `videos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `cover_url` VARCHAR(500) NULL,
  `category_id` BIGINT UNSIGNED NULL,
  `created_by_id` BIGINT UNSIGNED NOT NULL,
  `required_level` ENUM('NORMAL', 'VIP', 'SVIP') NOT NULL DEFAULT 'NORMAL',
  `valid_until` DATETIME NULL,
  `status` ENUM('DRAFT', 'PUBLISHED', 'OFFLINE') NOT NULL DEFAULT 'DRAFT',
  `allow_comments` TINYINT(1) NOT NULL DEFAULT 1,
  `play_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_videos_category_id` (`category_id`),
  KEY `idx_videos_created_by_id` (`created_by_id`),
  KEY `idx_videos_status` (`status`),
  KEY `idx_videos_status_created_at` (`status`, `created_at`),
  KEY `idx_videos_required_level` (`required_level`),
  CONSTRAINT `fk_videos_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_videos_created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `video_tags` (
  `video_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`video_id`, `tag_id`),
  KEY `idx_video_tags_tag_id` (`tag_id`),
  CONSTRAINT `fk_video_tags_video_id` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_video_tags_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `video_assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `video_id` BIGINT UNSIGNED NOT NULL,
  `kind` ENUM('VIDEO', 'COVER') NOT NULL DEFAULT 'VIDEO',
  `source_type` ENUM('LOCAL', 'DIRECT_URL', 'NETDISK') NOT NULL DEFAULT 'LOCAL',
  `netdisk_provider` ENUM('BAIDU', 'OTHER') NULL,
  `object_key` VARCHAR(500) NOT NULL,
  `share_url` VARCHAR(700) NULL,
  `share_code` VARCHAR(32) NULL,
  `share_file_name` VARCHAR(255) NULL,
  `share_raw_text` TEXT NULL,
  `resolver_meta` JSON NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NULL,
  `size_bytes` BIGINT UNSIGNED NULL,
  `duration_sec` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_video_assets_video_id` (`video_id`),
  KEY `idx_video_assets_kind` (`kind`),
  CONSTRAINT `fk_video_assets_video_id` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `grants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `course_id` BIGINT UNSIGNED NOT NULL,
  `granted_by_admin_id` BIGINT UNSIGNED NOT NULL,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grants_user_course` (`user_id`, `course_id`),
  KEY `idx_grants_course_id` (`course_id`),
  KEY `idx_grants_user_created_at` (`user_id`, `created_at`),
  KEY `idx_grants_created_at` (`created_at`),
  KEY `idx_grants_expires_at` (`expires_at`),
  KEY `idx_grants_admin_id` (`granted_by_admin_id`),
  CONSTRAINT `fk_grants_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_grants_course_id` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_grants_admin_id` FOREIGN KEY (`granted_by_admin_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `watch_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `video_id` BIGINT UNSIGNED NOT NULL,
  `watched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_position_sec` INT UNSIGNED NOT NULL DEFAULT 0,
  `progress_sec` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_watch_histories_user_video` (`user_id`, `video_id`),
  KEY `idx_watch_histories_video_id` (`video_id`),
  KEY `idx_watch_histories_watched_at` (`watched_at`),
  KEY `idx_watch_histories_user_watched_at` (`user_id`, `watched_at`),
  CONSTRAINT `fk_watch_histories_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_watch_histories_video_id` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `favorites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `video_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_favorites_user_video` (`user_id`, `video_id`),
  KEY `idx_favorites_video_id` (`video_id`),
  KEY `idx_favorites_user_created_at` (`user_id`, `created_at`),
  CONSTRAINT `fk_favorites_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favorites_video_id` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `video_likes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `video_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_video_likes_user_video` (`user_id`, `video_id`),
  KEY `idx_video_likes_video_id` (`video_id`),
  KEY `idx_video_likes_user_created_at` (`user_id`, `created_at`),
  CONSTRAINT `fk_video_likes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_video_likes_video_id` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `video_id` BIGINT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `status` ENUM('VISIBLE', 'HIDDEN') NOT NULL DEFAULT 'VISIBLE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_user_id` (`user_id`),
  KEY `idx_comments_video_status_created` (`video_id`, `status`, `created_at`),
  KEY `idx_comments_status_created_at` (`status`, `created_at`),
  CONSTRAINT `fk_comments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_video_id` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `import_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `video_id` BIGINT UNSIGNED NULL,
  `course_id` BIGINT UNSIGNED NULL,
  `lesson_id` BIGINT UNSIGNED NULL,
  `source_name` VARCHAR(255) NOT NULL,
  `source_type` ENUM('UPLOAD', 'NETDISK') NOT NULL DEFAULT 'UPLOAD',
  `source_url` VARCHAR(700) NULL,
  `source_code` VARCHAR(32) NULL,
  `source_raw_text` TEXT NULL,
  `kind` ENUM('VIDEO', 'COVER') NOT NULL DEFAULT 'VIDEO',
  `storage_key` VARCHAR(500) NULL,
  `status` ENUM('PENDING', 'PROCESSING', 'DONE', 'FAILED') NOT NULL DEFAULT 'PENDING',
  `error_message` TEXT NULL,
  `created_by_admin_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_import_tasks_video_id` (`video_id`),
  KEY `idx_import_tasks_course_id` (`course_id`),
  KEY `idx_import_tasks_lesson_id` (`lesson_id`),
  KEY `idx_import_tasks_admin_id` (`created_by_admin_id`),
  KEY `idx_import_tasks_created_at` (`created_at`),
  KEY `idx_import_tasks_source_type` (`source_type`),
  KEY `idx_import_tasks_status_created_at` (`status`, `created_at`),
  CONSTRAINT `fk_import_tasks_video_id` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_import_tasks_course_id` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_import_tasks_lesson_id` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_import_tasks_admin_id` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `play_url_caches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nonce` VARCHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `media_type` ENUM('VIDEO', 'LESSON') NOT NULL,
  `media_id` BIGINT UNSIGNED NOT NULL,
  `source_hash` CHAR(64) NOT NULL,
  `resolved_url` TEXT NOT NULL,
  `delivery_type` ENUM('LOCAL', 'REMOTE') NOT NULL DEFAULT 'LOCAL',
  `mime_type` VARCHAR(100) NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_play_url_caches_nonce` (`nonce`),
  KEY `idx_play_url_caches_user_media` (`user_id`, `media_type`, `media_id`),
  KEY `idx_play_url_caches_expires_at` (`expires_at`),
  CONSTRAINT `fk_play_url_caches_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `site_settings` (
  `id` BIGINT UNSIGNED NOT NULL,
  `base_info` JSON NOT NULL,
  `header` JSON NOT NULL,
  `footer` JSON NOT NULL,
  `seo` JSON NOT NULL,
  `other` JSON NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`id`, `base_info`, `header`, `footer`, `seo`, `other`, `created_at`, `updated_at`) VALUES
(1,
 '{"siteName":"在线学习平台","siteSubtitle":"服务端渲染课程与视频学习平台","siteUrl":"","recordNumber":"","contactPhone":"","contactEmail":"","logoObjectKey":"","faviconObjectKey":""}',
 '{"announcementEnabled":false,"announcementText":"","contactPhone":"","contactEmail":"","socialLinks":[],"customHtml":""}',
 '{"copyrightText":"在线学习平台","menuLinks":[],"techSupportText":"","customHtml":""}',
 '{"homeTitle":"在线学习平台","homeKeywords":[],"homeDescription":"","ogImageObjectKey":"","shareImageObjectKey":"","analyticsHtml":""}',
 '{"maintenanceEnabled":false,"maintenanceNotice":"","defaultLanguage":"zh-CN","timezone":"Asia/Shanghai","storage":{"driver":"local","uploadPath":"public/uploads","publicBaseUrl":"","netdiskLinkFormat":"https://网盘域名/分享路径?pwd=提取码"},"homepage":{"hero":{"kicker":"ONLINE LEARNING","title":"系统学习，高效进阶","description":"精选视频、专题课程、会员权限和学习记录全部服务端渲染，打开即学，跨设备延续你的学习进度。","primaryButtonText":"浏览课程","primaryButtonUrl":"/courses","secondaryButtonText":"观看视频","secondaryButtonUrl":"/videos","userButtonText":"进入我的学习","userButtonUrl":"/me","guestButtonText":"免费注册","guestButtonUrl":"/register"},"featureCards":[{"badge":"快速访问","title":"服务端渲染","description":"页面结构清晰，首屏打开即显示内容。"},{"badge":"权限体系","title":"会员与授权","description":"支持普通、会员、超级会员与课程单独授权。"},{"badge":"学习闭环","title":"记录与收藏","description":"保留观看历史和收藏内容，方便持续学习。"},{"badge":"安全播放","title":"签名播放","description":"按用户权限生成播放地址，减少资源被直接复制传播。"},{"badge":"移动适配","title":"多端访问","description":"兼容电脑、平板和手机页面，学习内容随时打开。"}]},"netdisk":{"baidu":{"enabled":false,"mode":"direct-or-external","resolverEndpoint":"","accessToken":"","cookie":"","directUrlTtlSec":900,"externalFallback":true}},"passwordRecovery":{"enabled":false,"expiresMinutes":30,"codeLength":6,"maxAttempts":5,"resendCooldownSeconds":60,"email":{"enabled":false,"driver":"smtp","fromEmail":"","fromName":"","smtp":{"host":"","port":587,"username":"","password":"","encryption":"tls","timeoutSeconds":10}},"phone":{"enabled":false,"provider":"none","endpoint":"","method":"POST","apiKey":"","secret":"","headersJson":"","template":"您的验证码是 {code}，{minutes} 分钟内有效。","signName":""}}}',
 NOW(),
 NOW()
);

SET FOREIGN_KEY_CHECKS = 1;
