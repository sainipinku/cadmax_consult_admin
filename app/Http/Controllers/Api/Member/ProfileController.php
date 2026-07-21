<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    private function getProfileCompletionData($member): array
    {
        $weights = [
            'name' => 15,
            'email' => 10,
            'phone' => 10,
            'username' => 5,
            'image' => 10,
            'dob' => 5,
            'gender' => 5,
            'skills' => 8,
            'overview' => 8,
            'education' => 8,
            'projects' => 6,
            'experience' => 10,
        ];

        $candidate = is_array($member->candidate_profile) ? $member->candidate_profile : [];

        $checks = [
            'name' => !empty($member->name),
            'email' => !empty($member->email),
            'phone' => !empty($member->phone),
            'username' => !empty($member->username),
            'image' => !empty($member->image),
            'dob' => !empty($member->dob),
            'gender' => !empty($member->gender),
            'skills' => !empty($candidate['skills']),
            'overview' => !empty($candidate['overview']),
            'education' => !empty($candidate['education']),
            'projects' => !empty($candidate['projects']),
            'experience' => array_key_exists('is_fresher', $candidate)
                ? ($candidate['is_fresher'] ? true : !empty($candidate['experience']))
                : false,
        ];

        $totalWeight = array_sum($weights);
        $score = 0;
        $missing = [];

        foreach ($weights as $key => $weight) {
            if (!empty($checks[$key])) {
                $score += $weight;
            } else {
                $missing[] = $key;
            }
        }

        $percentage = $totalWeight > 0 ? (int) floor(($score / $totalWeight) * 100) : 0;

        return [
            'percentage' => $percentage,
            'missing_fields' => $missing,
        ];
    }

    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'member' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('members', 'email')->ignore($member->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('members', 'phone')->ignore($member->id),
            ],
            'dob' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'image' => ['sometimes', 'nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'candidate_profile' => ['sometimes', 'array'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience' => ['sometimes', 'nullable', 'string', 'max:100'],
            'overview' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_fresher' => ['sometimes', 'boolean'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['string', 'max:50'],
            'latitude' => ['sometimes', 'nullable', 'decimal:8,6', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'decimal:9,6', 'between:-180,180'],
        ]);

        if ($request->hasFile('image')) {
            $this->deleteMemberImage($member->image);
            $path = Storage::disk('public')->putFile('members', $request->file('image'));
            $validated['image'] = $path;
        }

        $candidate = is_array($member->candidate_profile) ? $member->candidate_profile : [];

        if (array_key_exists('candidate_profile', $validated) && is_array($validated['candidate_profile'])) {
            $candidate = array_replace_recursive($candidate, $validated['candidate_profile']);
        }
        if (array_key_exists('skills', $validated)) {
            $candidate['skills'] = $validated['skills'];
        }
        if (array_key_exists('overview', $validated)) {
            $candidate['overview'] = $validated['overview'];
        }
        if (array_key_exists('is_fresher', $validated)) {
            $candidate['is_fresher'] = (bool) $validated['is_fresher'];
        }
        if (array_key_exists('job_title', $validated)) {
            $candidate['job_title'] = $validated['job_title'];
        }
        if (array_key_exists('location', $validated)) {
            $candidate['location'] = $validated['location'];
        }
        if (array_key_exists('experience', $validated)) {
            $candidate['experience_label'] = $validated['experience'];
        }

        $validated['candidate_profile'] = $candidate;

        if (array_key_exists('phone', $validated) && $validated['phone'] !== $member->phone) {
            $validated['phone_verify_at'] = null;
        }

        $member->fill($validated)->save();

        return response()->json([
            'success' => true,
            'member' => $member->fresh(),
        ]);
    }

    public function updatePhoto(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $this->deleteMemberImage($member->image);
        $path = Storage::disk('public')->putFile('members', $validated['photo']);

        $member->forceFill([
            'image' => $path,
        ])->save();

        return response()->json([
            'success' => true,
            'member' => $member->fresh(),
        ]);
    }

    public function removePhoto(Request $request)
    {
        $member = $request->user();

        $this->deleteMemberImage($member->image);

        $member->forceFill([
            'image' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'member' => $member->fresh(),
        ]);
    }

    public function resume(Request $request)
    {
        $member = $request->user();
        $hasResume = !empty($member->resume_path) && (
            Storage::disk('public')->exists($member->resume_path) || Storage::disk('local')->exists($member->resume_path)
        );

        return response()->json([
            'success' => true,
            'has_resume' => $hasResume,
            'resume' => $hasResume ? [
                'original_name' => $member->resume_original_name,
                'mime' => $member->resume_mime,
                'size' => $member->resume_size,
                'uploaded_at' => optional($member->resume_uploaded_at)->toISOString(),
                'view_url' => $this->buildResumeViewUrl($request),
                'path' => $member->resume_path,
                'url' => $member->resume_url,
            ] : null,
        ]);
    }

    public function uploadResume(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $this->deleteMemberResume($member->resume_path);

        $file = $validated['resume'];
        $extension = $file->getClientOriginalExtension() ?: 'pdf';
        $path = 'member-resumes/' . $member->uuid . '/' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        $member->forceFill([
            'resume_path' => $path,
            'resume_original_name' => $file->getClientOriginalName(),
            'resume_mime' => $file->getClientMimeType(),
            'resume_size' => $file->getSize(),
            'resume_uploaded_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Resume uploaded.',
            'resume' => [
                'original_name' => $member->resume_original_name,
                'mime' => $member->resume_mime,
                'size' => $member->resume_size,
                'uploaded_at' => optional($member->resume_uploaded_at)->toISOString(),
                'view_url' => $this->buildResumeViewUrl($request),
                'path' => $member->resume_path,
                'url' => $member->resume_url,
            ],
        ]);
    }

    public function deleteResume(Request $request)
    {
        $member = $request->user();

        $this->deleteMemberResume($member->resume_path);

        $member->forceFill([
            'resume_path' => null,
            'resume_original_name' => null,
            'resume_mime' => null,
            'resume_size' => null,
            'resume_uploaded_at' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Resume deleted.',
        ]);
    }

    public function viewResume(Request $request)
    {
        $member = $request->user();

        if (empty($member->resume_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found.',
            ], 404);
        }

        if (Storage::disk('public')->exists($member->resume_path)) {
            $fullPath = Storage::disk('public')->path($member->resume_path);
        } elseif (Storage::disk('local')->exists($member->resume_path)) {
            $fullPath = Storage::disk('local')->path($member->resume_path);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found.',
            ], 404);
        }

        $mime = $member->resume_mime ?: 'application/octet-stream';
        $filename = $member->resume_original_name ?: 'resume';
        $filename = str_replace(['"', "\n", "\r"], '', $filename);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function completion(Request $request)
    {
        $member = $request->user();
        $data = $this->getProfileCompletionData($member);

        return response()->json([
            'success' => true,
            'completion_percentage' => $data['percentage'],
            'missing_fields' => $data['missing_fields'],
            'min_required' => 35,
        ]);
    }

    private function deleteMemberImage(?string $image): void
    {
        if (empty($image)) {
            return;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return;
        }

        $path = ltrim($image, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function deleteMemberResume(?string $resumePath): void
    {
        if (empty($resumePath)) {
            return;
        }

        if (Storage::disk('public')->exists($resumePath)) {
            Storage::disk('public')->delete($resumePath);
        }

        if (Storage::disk('local')->exists($resumePath)) {
            Storage::disk('local')->delete($resumePath);
        }
    }

    private function buildResumeViewUrl(Request $request): string
    {
        $base = rtrim($request->getSchemeAndHttpHost(), '/');

        return $base . '/api/profile/resume/view';
    }
}
