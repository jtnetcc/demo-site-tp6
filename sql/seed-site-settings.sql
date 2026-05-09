SET NAMES utf8mb4;

INSERT INTO `site_settings` (`id`, `base_info`, `header`, `footer`, `seo`, `other`, `created_at`, `updated_at`) VALUES
(1,
 '{"siteName":"在线学习平台","siteSubtitle":"服务端渲染课程与视频学习平台","siteUrl":"","recordNumber":"","contactPhone":"","contactEmail":"","logoObjectKey":"","faviconObjectKey":""}',
 '{"announcementEnabled":false,"announcementText":"","contactPhone":"","contactEmail":"","socialLinks":[],"customHtml":""}',
 '{"copyrightText":"在线学习平台","menuLinks":[],"techSupportText":"","customHtml":""}',
 '{"homeTitle":"在线学习平台","homeKeywords":[],"homeDescription":"","ogImageObjectKey":"","shareImageObjectKey":"","analyticsHtml":""}',
 '{"maintenanceEnabled":false,"maintenanceNotice":"","defaultLanguage":"zh-CN","timezone":"Asia/Shanghai","storage":{"driver":"local","uploadPath":"public/uploads","publicBaseUrl":"","netdiskLinkFormat":"https://网盘域名/分享路径?pwd=提取码"},"homepage":{"hero":{"kicker":"ONLINE LEARNING","title":"系统学习，高效进阶","description":"精选视频、专题课程、会员权限和学习记录全部服务端渲染，打开即学，跨设备延续你的学习进度。","primaryButtonText":"浏览课程","primaryButtonUrl":"/courses","secondaryButtonText":"观看视频","secondaryButtonUrl":"/videos","userButtonText":"进入我的学习","userButtonUrl":"/me","guestButtonText":"免费注册","guestButtonUrl":"/register"},"featureCards":[{"badge":"快速访问","title":"服务端渲染","description":"页面结构清晰，首屏打开即显示内容。"},{"badge":"权限体系","title":"会员与授权","description":"支持普通、会员、超级会员与课程单独授权。"},{"badge":"学习闭环","title":"记录与收藏","description":"保留观看历史和收藏内容，方便持续学习。"},{"badge":"安全播放","title":"签名播放","description":"按用户权限生成播放地址，减少资源被直接复制传播。"},{"badge":"移动适配","title":"多端访问","description":"兼容电脑、平板和手机页面，学习内容随时打开。"}]},"netdisk":{"baidu":{"enabled":false,"mode":"direct-or-external","resolverEndpoint":"","accessToken":"","cookie":"","directUrlTtlSec":900,"externalFallback":true}},"passwordRecovery":{"enabled":false,"expiresMinutes":30,"codeLength":6,"maxAttempts":5,"resendCooldownSeconds":60,"email":{"enabled":false,"driver":"smtp","fromEmail":"","fromName":"","smtp":{"host":"","port":587,"username":"","password":"","encryption":"tls","timeoutSeconds":10}},"phone":{"enabled":false,"provider":"none","endpoint":"","method":"POST","apiKey":"","secret":"","headersJson":"","template":"您的验证码是 {code}，{minutes} 分钟内有效。","signName":""}}}',
 NOW(),
 NOW()
)
ON DUPLICATE KEY UPDATE
  `base_info` = IF(JSON_TYPE(`base_info`) = 'OBJECT' AND JSON_LENGTH(`base_info`) > 0, `base_info`, VALUES(`base_info`)),
  `header` = IF(JSON_TYPE(`header`) = 'OBJECT' AND JSON_LENGTH(`header`) > 0, `header`, VALUES(`header`)),
  `footer` = IF(JSON_TYPE(`footer`) = 'OBJECT' AND JSON_LENGTH(`footer`) > 0, `footer`, VALUES(`footer`)),
  `seo` = IF(JSON_TYPE(`seo`) = 'OBJECT' AND JSON_LENGTH(`seo`) > 0, `seo`, VALUES(`seo`)),
  `other` = IF(JSON_TYPE(`other`) = 'OBJECT' AND JSON_LENGTH(`other`) > 0, `other`, VALUES(`other`)),
  `updated_at` = NOW();
