<?php

namespace Tests\Feature\Member;

use Tests\TestCase;

class MemberTasksDeduplicationFeatureTest extends TestCase
{
    public function test_controller_prioritizes_unified_tasks_in_mytasks(): void
    {
        $filePath = app_path('Http/Controllers/Api/MemberDashboardController.php');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        // Find myTasks method
        $pos = strpos($content, 'public function myTasks(');
        $this->assertNotFalse($pos);
        $methodSlice = substr($content, $pos, 10000);

        // Ensure unifiedRows loop occurs before legacyRows loop in myTasks
        $unifiedPos = strpos($methodSlice, 'foreach ($unifiedRows as $t)');
        $legacyPos = strpos($methodSlice, 'foreach ($legacyRows as $t)');

        $this->assertNotFalse($unifiedPos, 'myTasks must iterate unifiedRows');
        $this->assertNotFalse($legacyPos, 'myTasks must iterate legacyRows');
        $this->assertLessThan($legacyPos, $unifiedPos, 'unifiedRows must be processed before legacyRows to prioritize unified tasks over mirrors');

        // Ensure title+project deduplication key is used in myTasks
        $this->assertStringContainsString('$seenTitleProject', $methodSlice, 'myTasks must check seenTitleProject to eliminate mirror duplicates');
    }

    public function test_controller_prioritizes_unified_tasks_in_todays_tasks(): void
    {
        $filePath = app_path('Http/Controllers/Api/MemberDashboardController.php');
        $content = file_get_contents($filePath);

        $pos = strpos($content, 'public function todaysTasks(');
        $this->assertNotFalse($pos);
        $methodSlice = substr($content, $pos, 10000);

        $unifiedPos = strpos($methodSlice, 'foreach ($unified as $t)');
        $legacyPos = strpos($methodSlice, 'foreach ($legacy as $t)');

        $this->assertNotFalse($unifiedPos, 'todaysTasks must iterate unified');
        $this->assertNotFalse($legacyPos, 'todaysTasks must iterate legacy');
        $this->assertLessThan($legacyPos, $unifiedPos, 'unified must be processed before legacy in todaysTasks');
    }
}
