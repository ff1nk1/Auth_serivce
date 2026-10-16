<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetUploadUrlRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function getUploadUrl(GetUploadUrlRequest $request)
    {
        $validated = $request->validated();

        $extension = pathinfo($validated['filename'], PATHINFO_EXTENSION);
        $fileName = Str::uuid().'.'.$extension;
        $path = 'uploads/'.$fileName;

        /** @var \Illuminate\Filesystem\AwsS3V3Adapter $s3Disk */
        $s3Disk = Storage::disk('s3');
        $internalUrl = $s3Disk->temporaryUploadUrl(
            $path,
            now()->addMinutes(5),
            ['ContentType' => $validated['content_type']]
        );

        if (is_array($internalUrl)) {
            $internalUrl = $internalUrl['url'] ?? $internalUrl[0] ?? (string) json_encode($internalUrl);
        }
        $internalUrl = (string) $internalUrl;

        $bucket = env('AWS_BUCKET', 'media');
        $publicBase = rtrim((string) env('MEDIA_PUBLIC_BASE', '/storage'), '/');

        return response()->json([
            // Internal MinIO URL for server-side PUT (admin Docker network)
            'upload_url' => $internalUrl,
            'file_path' => $path,
            'public_url' => "{$publicBase}/{$bucket}/{$path}",
        ]);
    }
}
