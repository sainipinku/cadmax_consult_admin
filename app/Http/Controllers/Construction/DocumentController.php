<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Document;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use ResolvesConstructionActor;

    public function view(Document $document, Request $request): BinaryFileResponse
    {
        $this->ensureDocumentAccess($document, $request);

        $fullPath = $this->resolveDocumentPath($document);
        $mime = $document->mime_type ?: 'application/octet-stream';
        $filename = $this->sanitizeFilename($document->original_name ?: $document->file_name ?: 'document');

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function download(Document $document, Request $request): StreamedResponse
    {
        $this->ensureDocumentAccess($document, $request);

        $disk = $document->disk ?: 'public';
        abort_unless(Storage::disk($disk)->exists($document->path), 404, 'Document not found.');

        $filename = $this->sanitizeFilename($document->original_name ?: $document->file_name ?: 'document');

        return Storage::disk($disk)->download($document->path, $filename);
    }

    private function ensureDocumentAccess(Document $document, Request $request): void
    {
        if (Auth::guard('superadmin')->check()) {
            return;
        }

        $actor = $request->user() ?: $this->constructionActor();
        abort_unless($actor, 403, 'Unauthorized.');

        abort_unless($document->project_id, 403, 'Document is not linked to a project.');

        if ($actor instanceof Member) {
            $hasProjectAccess = ProjectTeamMember::query()
                ->where('project_id', $document->project_id)
                ->where('member_id', $actor->getKey())
                ->where('status', 'active')
                ->exists();

            abort_unless($hasProjectAccess, 403, 'You are not assigned to this project.');
            return;
        }

        abort(403, 'Unauthorized.');
    }

    private function resolveDocumentPath(Document $document): string
    {
        $disk = $document->disk ?: 'public';
        abort_unless(Storage::disk($disk)->exists($document->path), 404, 'Document not found.');

        return Storage::disk($disk)->path($document->path);
    }

    private function sanitizeFilename(string $filename): string
    {
        return str_replace(['"', "\n", "\r"], '', $filename);
    }
}

