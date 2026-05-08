<?php

namespace app\service;

use app\model\Category;
use app\model\Tag;
use app\model\User;
use app\model\Video;
use app\model\VideoAsset;
use app\model\VideoTag;
use RuntimeException;

class AdminVideoService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Video::with(['category', 'tags'])->order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('title', '%' . $q . '%')->whereOr('description', 'like', '%' . $q . '%');
            });
        }

        foreach (['status', 'required_level'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function find(int $id): ?Video
    {
        return Video::with(['category', 'tags', 'assets'])->find($id);
    }

    public function create(array $data, User $admin, $coverFile = null, $videoFile = null): Video
    {
        $data = $this->prepareInput($data, $coverFile, $videoFile);
        $this->validateRequired($data, null);
        $video = Video::create($this->payload($data, $admin, null));
        $this->syncAsset($video, $data, false);
        $this->syncTags((int) $video->id, $data['tag_ids'] ?? [], (string) ($data['custom_tags'] ?? ''));

        return $video;
    }

    public function update(int $id, array $data, User $admin, $coverFile = null, $videoFile = null): Video
    {
        $video = Video::find($id);

        if (!$video) {
            throw new RuntimeException('视频不存在', 404);
        }

        $data = $this->prepareInput($data, $coverFile, $videoFile);
        $this->validateRequired($data, $video);
        $video->save($this->payload($data, $admin, $video));
        $this->syncAsset($video, $data, true);

        if (array_key_exists('tag_ids', $data) || trim((string) ($data['custom_tags'] ?? '')) !== '') {
            $this->syncTags((int) $video->id, $data['tag_ids'] ?? [], (string) ($data['custom_tags'] ?? ''));
        }

        return $video;
    }

    public function delete(int $id): bool
    {
        $video = Video::find($id);

        if (!$video) {
            throw new RuntimeException('视频不存在', 404);
        }

        return (bool) $video->delete();
    }

    public function categoryOptions()
    {
        return Category::order('name', 'asc')->select();
    }

    public function tagOptions()
    {
        return Tag::order('name', 'asc')->select();
    }

    public function selectedTagIds(?Video $video): array
    {
        if (!$video) {
            return [];
        }

        return array_map('intval', VideoTag::where('video_id', (int) $video->id)->column('tag_id'));
    }

    public function videoAsset(?Video $video): ?VideoAsset
    {
        if (!$video) {
            return null;
        }

        return VideoAsset::where('video_id', (int) $video->id)->where('kind', 'VIDEO')->find();
    }

    public function assetMap($videos): array
    {
        $ids = $this->ids($videos);

        if (!$ids) {
            return [];
        }

        $assets = VideoAsset::where('kind', 'VIDEO')->where('video_id', 'in', $ids)->select();
        $map = [];

        foreach ($assets as $asset) {
            $map[(int) $asset->video_id] = [
                'source_type' => (string) ($asset->source_type ?: 'LOCAL'),
                'netdisk_provider' => (string) $asset->netdisk_provider,
                'object_key' => (string) $asset->object_key,
                'share_url' => (string) $asset->share_url,
                'share_code' => (string) $asset->share_code,
                'share_file_name' => (string) $asset->share_file_name,
                'share_raw_text' => (string) $asset->share_raw_text,
                'original_name' => (string) $asset->original_name,
                'mime_type' => (string) $asset->mime_type,
                'size_bytes' => (int) $asset->size_bytes,
                'duration_sec' => (int) $asset->duration_sec,
            ];
        }

        return $map;
    }

    public function selectedTagMap($videos): array
    {
        $ids = $this->ids($videos);

        if (!$ids) {
            return [];
        }

        $rows = VideoTag::where('video_id', 'in', $ids)->select();
        $map = [];

        foreach ($rows as $row) {
            $videoId = (int) $row->video_id;
            $map[$videoId] = $map[$videoId] ?? [];
            $map[$videoId][] = (int) $row->tag_id;
        }

        foreach ($map as $videoId => $tagIds) {
            $map[$videoId] = implode(',', $tagIds);
        }

        return $map;
    }

    private function prepareInput(array $data, $coverFile, $videoFile): array
    {
        if ($this->hasUploadedFile($coverFile)) {
            $cover = (new AdminUploadService())->store($coverFile, 'COVER');
            $data['cover_url'] = '/' . $cover['storage_key'];
        }

        if ($this->hasUploadedFile($videoFile)) {
            $video = (new AdminUploadService())->store($videoFile, 'VIDEO');
            $data['source_type'] = 'LOCAL';
            $data['object_key'] = $video['storage_key'];
            $data['original_name'] = $video['original_name'];
            $data['mime_type'] = $video['mime_type'] ?? 'video/mp4';
            $data['size_bytes'] = $video['size_bytes'] ?? null;
            $data['duration_sec'] = $video['duration_sec'] ?? null;
            return $data;
        }

        $data = $this->normalizeAssetInput($data);
        $source = trim((string) ($data['object_key'] ?? ''));

        if ($source !== '') {
            $metadata = (new MediaMetadataService())->source($source);

            foreach (['original_name', 'mime_type', 'size_bytes', 'duration_sec'] as $field) {
                if (($data[$field] ?? '') === '' && isset($metadata[$field])) {
                    $data[$field] = $metadata[$field];
                }
            }
        }

        return $data;
    }

    private function validateRequired(array $data, ?Video $video): void
    {
        if (trim((string) ($data['title'] ?? '')) === '') {
            throw new RuntimeException('标题不能为空', 400);
        }

        if (trim((string) ($data['description'] ?? '')) === '') {
            throw new RuntimeException('简介不能为空', 400);
        }

        $categoryId = (int) ($data['category_id'] ?? 0);

        if ($categoryId <= 0 || !Category::find($categoryId)) {
            throw new RuntimeException('请选择有效分类', 400);
        }

        if (!in_array(($data['required_level'] ?? 'NORMAL'), ['NORMAL', 'VIP', 'SVIP'], true)) {
            throw new RuntimeException('请选择有效等级', 400);
        }

        $hasCover = trim((string) ($data['cover_url'] ?? '')) !== '' || ($video && trim((string) $video->cover_url) !== '');

        if (!$hasCover) {
            throw new RuntimeException('封面请上传本地文件或填写封面链接，任选一种即可', 400);
        }

        $hasSource = trim((string) ($data['object_key'] ?? '')) !== '' || trim((string) ($data['baidu_share_text'] ?? '')) !== '';

        if (!$hasSource && (!$video || !$this->videoAsset($video))) {
            throw new RuntimeException('视频资源请上传本地视频、填写视频地址或粘贴网盘分享文本，任选一种即可', 400);
        }
    }

    private function hasUploadedFile($file): bool
    {
        if (!$file) {
            return false;
        }

        if (method_exists($file, 'getError') && (int) $file->getError() === UPLOAD_ERR_NO_FILE) {
            return false;
        }

        foreach (['getOriginalName', 'getOriginalFilename'] as $method) {
            if (method_exists($file, $method) && trim((string) $file->$method()) !== '') {
                return true;
            }
        }

        if (method_exists($file, 'getPathname')) {
            $path = (string) $file->getPathname();
            return $path !== '' && is_file($path);
        }

        return true;
    }

    private function payload(array $data, User $admin, ?Video $video): array
    {
        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => $this->nullable($data['description'] ?? null),
            'cover_url' => $this->nullable($data['cover_url'] ?? null) ?: ($video ? (string) $video->cover_url : null),
            'category_id' => (int) $data['category_id'],
            'created_by_id' => $video ? (int) $video->created_by_id : (int) $admin->id,
            'required_level' => in_array(($data['required_level'] ?? 'NORMAL'), ['NORMAL', 'VIP', 'SVIP'], true) ? $data['required_level'] : 'NORMAL',
            'status' => in_array(($data['status'] ?? 'DRAFT'), ['DRAFT', 'PUBLISHED', 'OFFLINE'], true) ? $data['status'] : 'DRAFT',
            'allow_comments' => !empty($data['allow_comments']) ? 1 : 0,
        ];
    }

    private function syncAsset(Video $video, array $data, bool $preserveExisting): void
    {
        $objectKey = $this->nullable($data['object_key'] ?? null);
        $asset = VideoAsset::where('video_id', (int) $video->id)->where('kind', 'VIDEO')->find();

        if ($objectKey === null) {
            if (!$preserveExisting && $asset) {
                $asset->delete();
            }
            return;
        }

        $sourceType = $this->sourceType($data, $objectKey);
        $payload = [
            'video_id' => (int) $video->id,
            'kind' => 'VIDEO',
            'source_type' => $sourceType,
            'netdisk_provider' => $sourceType === 'NETDISK' ? ($this->nullable($data['netdisk_provider'] ?? null) ?: 'BAIDU') : null,
            'object_key' => $objectKey,
            'share_url' => $sourceType === 'NETDISK' ? $this->nullable($data['share_url'] ?? null) : null,
            'share_code' => $sourceType === 'NETDISK' ? $this->nullable($data['share_code'] ?? null) : null,
            'share_file_name' => $sourceType === 'NETDISK' ? $this->nullable($data['share_file_name'] ?? null) : null,
            'share_raw_text' => $sourceType === 'NETDISK' ? $this->nullable($data['share_raw_text'] ?? $data['baidu_share_text'] ?? null) : null,
            'resolver_meta' => $sourceType === 'NETDISK' ? ($data['resolver_meta'] ?? null) : null,
            'original_name' => $this->nullable($data['original_name'] ?? null) ?: ($this->nullable($data['share_file_name'] ?? null) ?: basename(parse_url($objectKey, PHP_URL_PATH) ?: $objectKey)),
            'mime_type' => $this->nullable($data['mime_type'] ?? null),
            'size_bytes' => max(0, (int) ($data['size_bytes'] ?? 0)) ?: null,
            'duration_sec' => max(0, (int) ($data['duration_sec'] ?? 0)) ?: null,
        ];

        if ($asset) {
            $asset->save($payload);
        } else {
            VideoAsset::create($payload);
        }
    }

    private function normalizeAssetInput(array $data): array
    {
        $parser = new NetdiskShareParserService();
        $text = trim((string) ($data['baidu_share_text'] ?? ''));
        $objectKey = trim((string) ($data['object_key'] ?? ''));
        $parsed = $text !== '' ? $parser->parse($text) : null;

        if (!$parsed && $objectKey !== '') {
            $parsed = $parser->parse($objectKey);
        }

        if (!$parsed) {
            return $data;
        }

        foreach ($parsed as $key => $value) {
            if ($value !== '' && ($key !== 'original_name' || empty($data['original_name']))) {
                $data[$key] = $value;
            }
        }

        if (empty($data['mime_type'])) {
            $data['mime_type'] = $parsed['mime_type'];
        }

        return $data;
    }

    private function sourceType(array $data, string $objectKey): string
    {
        $sourceType = strtoupper((string) ($data['source_type'] ?? ''));

        if (in_array($sourceType, ['LOCAL', 'DIRECT_URL', 'NETDISK'], true)) {
            return $sourceType;
        }

        if (!empty($data['share_url']) || (new NetdiskShareParserService())->parse($objectKey)) {
            return 'NETDISK';
        }

        if (preg_match('#^https?://#i', $objectKey)) {
            return 'DIRECT_URL';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $objectKey)) {
            throw new RuntimeException('该资源协议已不支持，请改用本地上传、本地路径、HTTP 直链或网盘链接', 400);
        }

        return 'LOCAL';
    }

    private function syncTags(int $videoId, $tagIds, string $customTags = ''): void
    {
        $tagIds = is_array($tagIds) ? $tagIds : [$tagIds];
        $tagIds = array_values(array_filter(array_map('intval', $tagIds)));

        foreach ($this->customTagNames($customTags) as $name) {
            $tagIds[] = (int) $this->findOrCreateTag($name)->id;
        }

        $tagIds = array_values(array_unique($tagIds));
        VideoTag::where('video_id', $videoId)->delete();

        foreach ($tagIds as $tagId) {
            if (Tag::find($tagId)) {
                VideoTag::create(['video_id' => $videoId, 'tag_id' => $tagId]);
            }
        }
    }

    private function customTagNames(string $text): array
    {
        $parts = preg_split('/[,，\r\n]+/u', $text) ?: [];
        $names = [];

        foreach ($parts as $part) {
            $name = trim((string) $part);

            if ($name !== '') {
                $names[] = mb_substr($name, 0, 100);
            }
        }

        return array_values(array_unique($names));
    }

    private function findOrCreateTag(string $name): Tag
    {
        $tag = Tag::where('name', $name)->find();

        if ($tag) {
            return $tag;
        }

        return Tag::create([
            'name' => $name,
            'slug' => $this->uniqueTagSlug($name),
        ]);
    }

    private function uniqueTagSlug(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));

        if ($slug === '') {
            $slug = 'tag-' . substr(sha1($name), 0, 10);
        }

        $base = mb_substr($slug, 0, 100);
        $slug = $base;
        $index = 2;

        while (Tag::where('slug', $slug)->find()) {
            $suffix = '-' . $index++;
            $slug = mb_substr($base, 0, 120 - strlen($suffix)) . $suffix;
        }

        return $slug;
    }

    private function ids($videos): array
    {
        $ids = [];

        foreach ($videos as $video) {
            $ids[] = (int) $video->id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function nullable($value): ?string
    {
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value === '' ? null : $value;
    }
}
