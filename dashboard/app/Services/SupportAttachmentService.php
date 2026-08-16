<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SupportAttachmentService
{
    public function transaction(callable $operation): mixed
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($operation, &$stored) {
                return $operation($stored);
            });
        } catch (Throwable $exception) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    public function attach(SupportMessage $message, SupportTicket $ticket, User $uploader, array $files, array &$stored): void
    {
        foreach ($files as $file) {
            $mime = $file->getMimeType();
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            };
            $disk = 'local';
            $path = 'support/'.$ticket->id.'/'.Str::uuid().'.'.$extension;
            Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
            $stored[] = [$disk, $path];
            $message->attachments()->create([
                'uploaded_by' => $uploader->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $this->safeName($file),
                'mime_type' => $mime,
                'size' => $file->getSize(),
            ]);
        }
    }

    private function safeName(UploadedFile $file): string
    {
        $name = str_replace(['/', '\\'], '', basename($file->getClientOriginalName()));
        $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?: 'image';

        return Str::limit($name, 255, '');
    }
}
