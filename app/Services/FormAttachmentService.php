<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FormAttachmentService
{
    /** @return array{attachment_id: string, path: string, name: string, mime: string, size: int, sha256: string} */
    public function store(UploadedFile $file, string $context, string $fieldKey): array
    {
        $sha256 = hash_file('sha256', $file->getRealPath()) ?: hash('sha256', $file->getClientOriginalName());
        $extension = $file->guessExtension() ?: 'bin';
        $contextHash = hash('sha256', $context);
        $attachmentId = hash('sha256', $context.'|'.$fieldKey.'|'.$sha256);
        $directory = 'flowcore-attachments/'.$contextHash;
        $filename = $fieldKey.'-'.$sha256.'.'.$extension;
        $path = $directory.'/'.$filename;

        if (! Storage::disk('local')->exists($path)) {
            $file->storeAs($directory, $filename, 'local');
        }

        return [
            'attachment_id' => $attachmentId,
            'path' => $path,
            'name' => basename($file->getClientOriginalName()),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'sha256' => $sha256,
        ];
    }
}
