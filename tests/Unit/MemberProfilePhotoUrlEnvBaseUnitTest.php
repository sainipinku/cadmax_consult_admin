<?php

namespace Tests\Unit;

use App\Models\Member;
use Tests\TestCase;

class MemberProfilePhotoUrlEnvBaseUnitTest extends TestCase
{
    public function test_resolve_public_base_url_method_exists(): void
    {
        $this->assertTrue(method_exists(Member::class, 'resolvePublicBaseUrl'));
    }

    public function test_resolve_public_base_url_trims_trailing_slash_from_app_url(): void
    {
        putenv('APP_URL=http://betaxtech.com/');
        $_ENV['APP_URL'] = 'http://betaxtech.com/';
        config(['app.url' => 'http://betaxtech.com/']);

        $base = Member::resolvePublicBaseUrl();
        $this->assertEquals('http://betaxtech.com', $base);
    }

    public function test_member_profile_photo_url_is_based_on_app_url(): void
    {
        putenv('APP_URL=http://betaxtech.com/');
        $_ENV['APP_URL'] = 'http://betaxtech.com/';
        config(['app.url' => 'http://betaxtech.com/']);

        $member = new Member();
        $member->image = 'members/BZN6QsvERpm2svN07BTFQPinzVbVgUicSycYr125.png';

        $this->assertEquals(
            'http://betaxtech.com/storage/members/BZN6QsvERpm2svN07BTFQPinzVbVgUicSycYr125.png',
            $member->profile_photo_url
        );
    }

    public function test_member_profile_photo_url_strips_redundant_storage_prefix(): void
    {
        putenv('APP_URL=http://betaxtech.com/');
        $_ENV['APP_URL'] = 'http://betaxtech.com/';
        config(['app.url' => 'http://betaxtech.com/']);

        $member = new Member();
        $member->image = 'storage/members/sample_pic.png';

        $this->assertEquals(
            'http://betaxtech.com/storage/members/sample_pic.png',
            $member->profile_photo_url
        );
    }

    public function test_member_resume_url_is_based_on_app_url(): void
    {
        putenv('APP_URL=http://betaxtech.com/');
        $_ENV['APP_URL'] = 'http://betaxtech.com/';
        config(['app.url' => 'http://betaxtech.com/']);

        $member = new Member();
        $member->resume_path = 'resumes/my_resume.pdf';

        $this->assertEquals(
            'http://betaxtech.com/storage/resumes/my_resume.pdf',
            $member->resume_url
        );
    }

    public function test_default_avatar_is_based_on_app_url_when_image_is_empty(): void
    {
        putenv('APP_URL=http://betaxtech.com/');
        $_ENV['APP_URL'] = 'http://betaxtech.com/';
        config(['app.url' => 'http://betaxtech.com/']);

        $member = new Member();
        $member->image = null;

        $this->assertEquals(
            'http://betaxtech.com/images/profileimg.png',
            $member->profile_photo_url
        );
    }
}

