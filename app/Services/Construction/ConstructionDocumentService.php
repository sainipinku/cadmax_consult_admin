<?php

namespace App\Services\Construction;

use App\Models\ConstructionDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ConstructionDocumentService
{
    public function storeDocument(
        Model $documentable,
        ?Model $actor,
        string $folder,
        UploadedFile $file,
        ?int $companyId = null,
        ?int $projectId = null,
        ?string $disk = null,
        ?string $mimeType = null
    ): ConstructionDocument {
        $targetDisk = $disk ?: 'public';
        $extension = strtolower(
            $file->getClientOriginalExtension()
        );
        $baseName = pathinfo(
            $file->getClientOriginalName(),
            PATHINFO_FILENAME
        );
        $sanitizedBaseName = Str::slug($baseName)
            ?: Str::uuid()->toString();
        $suffix = Str::lower(Str::random(8));

        $fileName = $extension !== ''
            ? "{$sanitizedBaseName}-{$suffix}.{$extension}"
            : "{$sanitizedBaseName}-{$suffix}";

        $normalizedFolder = trim($folder, '/');

        $storedPath = $file->storeAs(
            $normalizedFolder,
            $fileName,
            $targetDisk
        );

        if (!is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException(
                'The document could not be stored.'
            );
        }

        try {
            return ConstructionDocument::create([
                'company_id' => $companyId,
                'project_id' => $projectId,
                'documentable_type' => $documentable::class,
                'documentable_id' => $documentable->getKey(),
                'folder' => $normalizedFolder,
                'file_name' => basename($storedPath),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType ?: $file->getMimeType(),
                'file_size' => (int) ($file->getSize() ?? 0),
                'disk' => $targetDisk,
                'path' => $storedPath,
                'uploaded_by_type' => $actor
                    ? $actor::class
                    : null,
                'uploaded_by_id' => $actor?->getKey(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($targetDisk)->delete($storedPath);

            throw $exception;
        }
    }

    public function createPlaceholderDocument(
        Model $documentable,
        ?Model $actor,
        string $folder,
        string $originalName,
        ?int $companyId = null,
        ?int $projectId = null,
        ?string $disk = null,
        ?string $mimeType = null
    ): ConstructionDocument {
        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );
        $baseName = pathinfo(
            $originalName,
            PATHINFO_FILENAME
        );
        $sanitizedBaseName = Str::slug($baseName)
            ?: Str::uuid()->toString();
        $suffix = Str::lower(Str::random(8));

        $fileName = $extension !== ''
            ? "{$sanitizedBaseName}-{$suffix}.{$extension}"
            : "{$sanitizedBaseName}-{$suffix}";

        $targetDisk = $disk ?: 'public';
        $normalizedFolder = trim($folder, '/');
        $path = $normalizedFolder . '/' . $fileName;
        $createdPlaceholder = false;

        if (!Storage::disk($targetDisk)->exists($path)) {
            $stored = Storage::disk($targetDisk)->put(
                $path,
                ''
            );

            if (!$stored) {
                throw new RuntimeException(
                    'The placeholder document could not be stored.'
                );
            }

            $createdPlaceholder = true;
        }

        try {
            return ConstructionDocument::create([
                'company_id' => $companyId,
                'project_id' => $projectId,
                'documentable_type' => $documentable::class,
                'documentable_id' => $documentable->getKey(),
                'folder' => $normalizedFolder,
                'file_name' => $fileName,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'file_size' => 0,
                'disk' => $targetDisk,
                'path' => $path,
                'uploaded_by_type' => $actor
                    ? $actor::class
                    : null,
                'uploaded_by_id' => $actor?->getKey(),
            ]);
        } catch (Throwable $exception) {
            if ($createdPlaceholder) {
                Storage::disk($targetDisk)->delete($path);
            }

            throw $exception;
        }
    }

    public function deleteDocument(
        ConstructionDocument $document
    ): void {
        $disk = $document->disk ?: 'public';
        $path = $document->path;

        if (
            is_string($path)
            && $path !== ''
            && Storage::disk($disk)->exists($path)
        ) {
            $deleted = Storage::disk($disk)->delete($path);

            if (!$deleted) {
                throw new RuntimeException(
                    'The document file could not be deleted.'
                );
            }
        }

        $document->delete();
    }
}