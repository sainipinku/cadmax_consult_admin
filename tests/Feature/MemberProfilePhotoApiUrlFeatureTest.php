<?php

namespace Tests\Feature;

use App\Models\Member;
use Tests\TestCase;

class MemberProfilePhotoApiUrlFeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_URL=http://localhost:8000/');
        $_ENV['APP_URL'] = 'http://localhost:8000/';
        config(['app.url' => 'http://localhost:8000/']);
        parent::tearDown();
    }

    public function test_member_json_serialization_contains_env_based_profile_photo_url(): void
    {
        putenv('APP_URL=http://betaxtech.com/');
        $_ENV['APP_URL'] = 'http://betaxtech.com/';
        config(['app.url' => 'http://betaxtech.com/']);

        $member = new Member();
        $member->id = 125;
        $member->name = 'Test Member';
        $member->email = 'test@betaxtech.com';
        $member->image = 'members/BZN6QsvERpm2svN07BTFQPinzVbVgUicSycYr125.png';

        $jsonArray = $member->toArray();

        $this->assertArrayHasKey('profile_photo_url', $jsonArray);
        $this->assertEquals(
            'http://betaxtech.com/storage/members/BZN6QsvERpm2svN07BTFQPinzVbVgUicSycYr125.png',
            $jsonArray['profile_photo_url']
        );
    }

    public function test_member_json_serialization_contains_env_based_resume_url(): void
    {
        putenv('APP_URL=http://betaxtech.com/');
        $_ENV['APP_URL'] = 'http://betaxtech.com/';
        config(['app.url' => 'http://betaxtech.com/']);

        $member = new Member();
        $member->id = 125;
        $member->resume_path = 'resumes/sample_cv.pdf';

        $jsonArray = $member->toArray();

        $this->assertArrayHasKey('resume_url', $jsonArray);
        $this->assertEquals(
            'http://betaxtech.com/storage/resumes/sample_cv.pdf',
            $jsonArray['resume_url']
        );
    }

    public function test_profile_photo_url_without_trailing_slash_in_env(): void
    {
        putenv('APP_URL=http://betaxtech.com');
        $_ENV['APP_URL'] = 'http://betaxtech.com';
        config(['app.url' => 'http://betaxtech.com']);

        $member = new Member();
        $member->image = 'members/avatar.jpg';

        $this->assertEquals('http://betaxtech.com/storage/members/avatar.jpg', $member->profile_photo_url);
    }

    public function test_incoming_request_host_used_when_env_is_localhost(): void
    {
        putenv('APP_URL=http://localhost');
        $_ENV['APP_URL'] = 'http://localhost';
        config(['app.url' => 'http://localhost']);

        // Mock an incoming HTTP request with betaxtech.com
        $request = \Illuminate\Http\Request::create('http://betaxtech.com/api/profile/photo', 'POST');
        app()->instance('request', $request);

        $member = new Member();
        $member->image = 'members/test_photo.png';

        $this->assertEquals('http://betaxtech.com/storage/members/test_photo.png', $member->profile_photo_url);
    }
}

