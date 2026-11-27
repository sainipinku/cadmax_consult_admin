<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class WorkflowTrackerMotionPathFeatureTest extends TestCase
{
    public function test_project_details_pages_mount_workflow_tracker_with_isolated_motion_path(): void
    {
        $superShow = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminShow = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('<WorkflowTracker', $superShow);
        $this->assertStringContainsString('<WorkflowTracker', $adminShow);

        // MotionPath plugin must stay encapsulated inside WorkflowTracker (Complete Workflow Progress)
        $this->assertStringNotContainsString('MotionPathPlugin', $superShow);
        $this->assertStringNotContainsString('MotionPathPlugin', $adminShow);
    }

    public function test_workflow_tracker_cleans_up_gsap_context_and_motion_path_helper(): void
    {
        $trackerCode = file_get_contents(resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx'));

        $this->assertStringContainsString('gsap.context(', $trackerCode);
        $this->assertStringContainsString('ctx.revert()', $trackerCode);
        $this->assertStringContainsString('.kill()', $trackerCode);
        $this->assertStringContainsString('Complete Workflow Progress', $trackerCode);
    }

    public function test_workflow_tracker_blinks_active_stage_after_file_arrival_and_respects_reduced_motion(): void
    {
        $trackerCode = file_get_contents(resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx'));

        $this->assertStringContainsString('hasArrived', $trackerCode);
        $this->assertStringContainsString('setHasArrived(true)', $trackerCode);
        $this->assertStringContainsString('prefers-reduced-motion', $trackerCode);
        $this->assertStringContainsString('startActiveStepBling', $trackerCode);
    }

    public function test_edit_path_mode_registers_motion_path_helper_with_live_tween_and_cleans_up(): void
    {
        $trackerCode = file_get_contents(resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx'));

        $this->assertStringContainsString('gsap.registerPlugin(MotionPathPlugin, MotionPathHelper)', $trackerCode);
        $this->assertStringContainsString('editTweenRef', $trackerCode);
        $this->assertStringContainsString('gsap,', $trackerCode);
    }

    public function test_workflow_tracker_uses_four_stages_without_accounts_team_across_project_pages(): void
    {
        $trackerCode = file_get_contents(resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx'));
        $memberShow = file_get_contents(resource_path('js/Pages/Member/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('<WorkflowTracker', $memberShow);
        $this->assertStringContainsString('Survey → Draft → Admin → Completed', $trackerCode);
        $this->assertStringNotContainsString('Accounts Team', $trackerCode);
        $this->assertStringNotContainsString('Commercial Verify', $trackerCode);
    }
}
