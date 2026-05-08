SET NAMES utf8mb4;

INSERT INTO `site_settings` (`id`, `base_info`, `header`, `footer`, `seo`, `other`, `created_at`, `updated_at`) VALUES
(1,
 JSON_OBJECT(
   'siteName', '在线学习平台',
   'siteSubtitle', '服务端渲染课程与视频学习平台',
   'siteUrl', '',
   'recordNumber', '',
   'contactPhone', '',
   'contactEmail', '',
   'logoObjectKey', '',
   'faviconObjectKey', ''
 ),
 JSON_OBJECT(
   'announcementEnabled', false,
   'announcementText', '',
   'contactPhone', '',
   'contactEmail', '',
   'socialLinks', JSON_ARRAY(),
   'customHtml', ''
 ),
 JSON_OBJECT(
   'copyrightText', '在线学习平台',
   'menuLinks', JSON_ARRAY(),
   'techSupportText', '',
   'customHtml', ''
 ),
 JSON_OBJECT(
   'homeTitle', '在线学习平台',
   'homeKeywords', JSON_ARRAY(),
   'homeDescription', '',
   'ogImageObjectKey', '',
   'shareImageObjectKey', '',
   'analyticsHtml', ''
 ),
 JSON_OBJECT(),
 NOW(),
 NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = `updated_at`;
