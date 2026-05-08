<?php

namespace app\service;

use app\model\Course;
use app\model\ImportTask;
use app\model\Lesson;
use app\model\User;
use app\model\Video;
use app\model\VideoAsset;
use RuntimeException;

class AdminImportTaskService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = ImportTask::with(['video', 'course', 'lesson', 'createdByAdmin'])->order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $videoIds = Video::whereLike('title', '%' . $q . '%')->column('id');
            $courseIds = Course::whereLike('title', '%' . $q . '%')->column('id');
            $lessonIds = Lesson::whereLike('title', '%' . $q . '%')->column('id');
            $query->where(function ($subQuery) use ($q, $videoIds, $courseIds, $lessonIds) {
                $subQuery->whereLike('source_name', '%' . $q . '%')->whereOr('storage_key', 'like', '%' . $q . '%')->whereOr('source_url', 'like', '%' . $q . '%');

                if ($videoIds) {
                    $subQuery->whereOr('video_id', 'in', $videoIds);
                }

                if ($courseIds) {
                    $subQuery->whereOr('course_id', 'in', $courseIds);
                }

                if ($lessonIds) {
                    $subQuery->whereOr('lesson_id', 'in', $lessonIds);
                }
            });
        }

        foreach (['status', 'kind', 'source_type'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }

        foreach (['video_id', 'course_id', 'lesson_id'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function find(int $id): ?ImportTask
    {
        return ImportTask::with(['video', 'course', 'lesson', 'createdByAdmin'])->find($id);
    }

    public function create(array $data, User $admin, $file): ImportTask
    {
        $sourceType = strtoupper((string) ($data['source_type'] ?? 'UPLOAD'));

        if ($sourceType === 'NETDISK' || trim((string) ($data['baidu_share_text'] ?? '')) !== '') {
            return $this->createNetdisk($data, $admin, false);
        }

        return $this->createUpload($data, $admin, $file);
    }

    public function createNetdiskForVideo(Video $video, array $data, User $admin): ?ImportTask
    {
        $text = trim((string) ($data['baidu_share_text'] ?? $data['share_raw_text'] ?? ''));

        if ($text === '' && empty($data['share_url'])) {
            return null;
        }

        return $this->createNetdisk(array_merge($data, [
            'kind' => 'VIDEO',
            'video_id' => (int) $video->id,
            'source_type' => 'NETDISK',
            'baidu_share_text' => $text !== '' ? $text : (string) $data['share_url'],
        ]), $admin, false);
    }

    public function process(int $id): ImportTask
    {
        $task = ImportTask::find($id);

        if (!$task) {
            throw new RuntimeException('导入任务不存在', 404);
        }

        if ((string) $task->source_type !== 'NETDISK') {
            throw new RuntimeException('只有网盘导入任务需要执行转存', 400);
        }

        $task->save(['status' => 'PROCESSING', 'error_message' => null]);

        try {
            $stored = (new NetdiskImportService())->store($task);
            $task->save([
                'source_name' => (string) $task->source_name === 'netdisk' ? $stored['original_name'] : (string) $task->source_name,
                'storage_key' => $stored['storage_key'],
                'status' => 'DONE',
                'error_message' => null,
            ]);
            $this->syncBusinessData($task, $stored);
        } catch (\Throwable $e) {
            $task->save([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $task;
    }

    public function update(int $id, array $data): ImportTask
    {
        $task = ImportTask::find($id);

        if (!$task) {
            throw new RuntimeException('导入任务不存在', 404);
        }

        [$kind, $videoId, $courseId, $lessonId] = $this->validatedBase($data);
        $sourceType = in_array(($data['source_type'] ?? 'UPLOAD'), ['UPLOAD', 'NETDISK'], true) ? $data['source_type'] : 'UPLOAD';
        $status = in_array(($data['status'] ?? $task->status), ['PENDING', 'PROCESSING', 'DONE', 'FAILED'], true) ? $data['status'] : $task->status;

        $task->save([
            'video_id' => $videoId,
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'source_name' => trim((string) ($data['source_name'] ?? '')) ?: (string) $task->source_name,
            'source_type' => $sourceType,
            'source_url' => $this->nullable($data['source_url'] ?? null),
            'source_code' => $this->nullable($data['source_code'] ?? null),
            'source_raw_text' => $this->nullable($data['source_raw_text'] ?? null),
            'kind' => $kind,
            'storage_key' => $this->nullable($data['storage_key'] ?? null),
            'status' => $status,
            'error_message' => $this->nullable($data['error_message'] ?? null),
        ]);

        return $this->find($id) ?: $task;
    }

    public function delete(int $id): bool
    {
        $task = ImportTask::find($id);

        if (!$task) {
            throw new RuntimeException('导入任务不存在', 404);
        }

        return (bool) $task->delete();
    }

    private function createUpload(array $data, User $admin, $file): ImportTask
    {
        [$kind, $videoId, $courseId, $lessonId] = $this->validatedBase($data);
        $sourceName = trim((string) ($data['source_name'] ?? '')) ?: 'upload';
        $task = ImportTask::create([
            'video_id' => $videoId,
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'source_name' => $sourceName,
            'source_type' => 'UPLOAD',
            'kind' => $kind,
            'status' => 'PROCESSING',
            'created_by_admin_id' => (int) $admin->id,
        ]);

        try {
            $stored = (new AdminUploadService())->store($file, $kind);
            $task->save([
                'source_name' => $sourceName === 'upload' ? $stored['original_name'] : $sourceName,
                'storage_key' => $stored['storage_key'],
                'status' => 'DONE',
                'error_message' => null,
            ]);
            $this->syncBusinessData($task, $stored);
        } catch (\Throwable $e) {
            $task->save([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $task;
    }

    private function createNetdisk(array $data, User $admin, bool $autoProcess = true): ImportTask
    {
        [$kind, $videoId, $courseId, $lessonId] = $this->validatedBase(array_merge($data, ['kind' => 'VIDEO']));
        $text = trim((string) ($data['baidu_share_text'] ?? $data['source_raw_text'] ?? $data['source_url'] ?? ''));
        $parsed = (new NetdiskShareParserService())->parse($text);

        if (!$parsed) {
            throw new RuntimeException('请粘贴有效的百度网盘分享文本', 400);
        }

        $sourceName = trim((string) ($data['source_name'] ?? '')) ?: ((string) ($parsed['share_file_name'] ?? '') ?: 'netdisk');
        $task = ImportTask::create([
            'video_id' => $videoId,
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'source_name' => $sourceName,
            'source_type' => 'NETDISK',
            'source_url' => (string) $parsed['share_url'],
            'source_code' => (string) $parsed['share_code'],
            'source_raw_text' => (string) $parsed['share_raw_text'],
            'kind' => $kind,
            'status' => 'PENDING',
            'created_by_admin_id' => (int) $admin->id,
        ]);

        if ($autoProcess) {
            return $this->process((int) $task->id);
        }

        return $task;
    }

    private function validatedBase(array $data): array
    {
        $kind = in_array(($data['kind'] ?? 'VIDEO'), ['VIDEO', 'COVER'], true) ? $data['kind'] : 'VIDEO';
        $videoId = $this->optionalId($data['video_id'] ?? null);
        $courseId = $this->optionalId($data['course_id'] ?? null);
        $lessonId = $this->optionalId($data['lesson_id'] ?? null);

        if ($videoId !== null && !Video::find($videoId)) {
            throw new RuntimeException('视频不存在', 404);
        }

        if ($courseId !== null && !Course::find($courseId)) {
            throw new RuntimeException('课程不存在', 404);
        }

        if ($lessonId !== null && !Lesson::find($lessonId)) {
            throw new RuntimeException('课时不存在', 404);
        }

        if ($videoId === null && $courseId === null && $lessonId === null) {
            throw new RuntimeException('请至少选择一个关联对象', 400);
        }

        return [$kind, $videoId, $courseId, $lessonId];
    }

    private function syncBusinessData(ImportTask $task, array $stored): void
    {
        $storageKey = (string) $stored['storage_key'];

        if ((int) $task->video_id > 0) {
            $video = Video::find((int) $task->video_id);

            if ($video && $task->kind === 'COVER') {
                $video->save(['cover_url' => '/' . $storageKey]);
                $this->syncAsset((int) $video->id, 'COVER', $stored);
            }

            if ($video && $task->kind === 'VIDEO') {
                $this->syncAsset((int) $video->id, 'VIDEO', $stored);
            }
        }

        if ((int) $task->lesson_id > 0 && $task->kind === 'VIDEO') {
            $lesson = Lesson::find((int) $task->lesson_id);

            if ($lesson) {
                $lesson->save(['video_object_key' => $storageKey]);
            }
        }
    }

    private function syncAsset(int $videoId, string $kind, array $stored): void
    {
        $asset = VideoAsset::where('video_id', $videoId)->where('kind', $kind)->find();
        $payload = [
            'video_id' => $videoId,
            'kind' => $kind,
            'source_type' => 'LOCAL',
            'netdisk_provider' => null,
            'object_key' => (string) $stored['storage_key'],
            'share_url' => null,
            'share_code' => null,
            'share_file_name' => null,
            'share_raw_text' => null,
            'resolver_meta' => null,
            'original_name' => (string) $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'duration_sec' => null,
        ];

        if ($asset) {
            $asset->save($payload);
        } else {
            VideoAsset::create($payload);
        }
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function optionalId($value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
