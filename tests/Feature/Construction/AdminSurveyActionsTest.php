<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionDocument;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\SurveyWorkChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSurveyActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_super_admin_can_assign_predefined_checklist_work(): void
{
    [$admin, $project, $role] =
        $this->createAdminContext([
            'survey_plan.manage',
        ]);

    $surveyor = $this->createMember(
        'Checklist Surveyor'
    );

    $this->assignProjectMember(
        $surveyor,
        $project,
        $role
    );

    $plan = SurveyPlan::create([
        'project_id' => $project->getKey(),
        'survey_code' => 'SUR-CHECKLIST',
        'title' => 'Topography Survey',
        'status' => SurveyPlan::STATUS_PLANNED,
    ]);

    $assignment = SurveyPlanMember::create([
        'survey_plan_id' => $plan->getKey(),
        'member_id' => $surveyor->getKey(),
        'role_in_survey' => 'surveyor',
        'status' => 'assigned',
    ]);

    $adminResponse = $this
        ->actingAs($admin, 'admin')
        ->post(
            route(
                'admin.construction.survey.plans'
                . '.members.checklist-works.store',
                [
                    'surveyPlan' => $plan,
                    'surveyPlanMember' =>
                        $assignment,
                ]
            ),
            [
                'works' => [
                    ' Check instrument and battery ',
                    'Calibrate total station',
                ],
            ]
        );

 
$adminResponse
    ->assertRedirect()
    ->assertSessionHasNoErrors();
    $this->assertDatabaseHas(
        'construction_survey_work_checklists',
        [
            'survey_plan_member_id' =>
                $assignment->getKey(),
            'work_title' =>
                'Check instrument and battery',
            'source' =>
                SurveyWorkChecklist::SOURCE_ADMIN,
            'status' =>
                SurveyWorkChecklist::STATUS_PENDING,
            'added_by_type' =>
                $admin->getMorphClass(),
            'added_by_id' => $admin->getKey(),
            'sort_order' => 0,
        ]
    );

    $this->assertDatabaseHas(
        'construction_survey_work_checklists',
        [
            'survey_plan_member_id' =>
                $assignment->getKey(),
            'work_title' =>
                'Calibrate total station',
            'source' =>
                SurveyWorkChecklist::SOURCE_ADMIN,
            'status' =>
                SurveyWorkChecklist::STATUS_PENDING,
            'sort_order' => 1,
        ]
    );

    $superAdmin = SuperAdmin::query()
        ->firstOrFail();

    $superAdminResponse = $this
        ->actingAs($superAdmin, 'superadmin')
        ->post(
            route(
                'super.construction.survey.plans'
                . '.members.checklist-works.store',
                [
                    'surveyPlan' => $plan,
                    'surveyPlanMember' =>
                        $assignment,
                ]
            ),
            [
                'works' => [
                    'Record existing benchmarks',
                ],
            ]
        );

   $superAdminResponse
    ->assertRedirect()
    ->assertSessionHasNoErrors();

    $this->assertDatabaseHas(
        'construction_survey_work_checklists',
        [
            'survey_plan_member_id' =>
                $assignment->getKey(),
            'work_title' =>
                'Record existing benchmarks',
            'source' =>
                SurveyWorkChecklist::SOURCE_SUPER_ADMIN,
            'status' =>
                SurveyWorkChecklist::STATUS_PENDING,
            'added_by_type' =>
                $superAdmin->getMorphClass(),
            'added_by_id' =>
                $superAdmin->getKey(),
            'sort_order' => 2,
        ]
    );

    $this->assertDatabaseCount(
        'construction_survey_work_checklists',
        3
    );
}

public function test_admin_cannot_use_another_plans_checklist_assignment(): void
{
    [$admin, $project, $role] =
        $this->createAdminContext([
            'survey_plan.manage',
        ]);

    $surveyor = $this->createMember(
        'Cross Plan Surveyor'
    );

    $this->assignProjectMember(
        $surveyor,
        $project,
        $role
    );

    $firstPlan = SurveyPlan::create([
        'project_id' => $project->getKey(),
        'survey_code' => 'SUR-CHECK-A',
        'title' => 'First Survey',
        'status' => SurveyPlan::STATUS_PLANNED,
    ]);

    $secondPlan = SurveyPlan::create([
        'project_id' => $project->getKey(),
        'survey_code' => 'SUR-CHECK-B',
        'title' => 'Second Survey',
        'status' => SurveyPlan::STATUS_PLANNED,
    ]);

    $secondAssignment = SurveyPlanMember::create([
        'survey_plan_id' =>
            $secondPlan->getKey(),
        'member_id' => $surveyor->getKey(),
        'role_in_survey' => 'surveyor',
        'status' => 'assigned',
    ]);

    $response = $this
        ->actingAs($admin, 'admin')
        ->post(
            route(
                'admin.construction.survey.plans'
                . '.members.checklist-works.store',
                [
                    'surveyPlan' => $firstPlan,
                    'surveyPlanMember' =>
                        $secondAssignment,
                ]
            ),
            [
                'works' => [
                    'Unauthorized cross-plan work',
                ],
            ]
        );

    $response->assertNotFound();

    $this->assertDatabaseCount(
        'construction_survey_work_checklists',
        0
    );
}

    public function test_admin_can_create_and_update_a_plan_with_active_project_members_only(): void
    {
        [$admin, $project, $role] =
            $this->createAdminContext([
                'survey_plan.manage',
            ]);

        $firstSurveyor =
            $this->createMember(
                'First Surveyor'
            );

        $secondSurveyor =
            $this->createMember(
                'Second Surveyor'
            );

        $otherProjectSurveyor =
            $this->createMember(
                'Other Project Surveyor'
            );

        $this->assignProjectMember(
            $firstSurveyor,
            $project,
            $role
        );

        $this->assignProjectMember(
            $secondSurveyor,
            $project,
            $role
        );

        $otherProject =
            $this->createProject(
                'PRJ-OTHER',
                'other-project',
                'Other Project'
            );

        $this->assignProjectMember(
            $otherProjectSurveyor,
            $otherProject,
            $role
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.construction.survey.plans.store'
                ),
                [
                    'project_id' =>
                        $project->id,

                    'title' =>
                        'Initial Site Survey',

                    'planned_start_time' =>
                        '09:00',

                    'planned_end_time' =>
                        '11:00',

                    'member_ids' => [
                        $otherProjectSurveyor
                            ->id,
                    ],
                ]
            )
            ->assertSessionHasErrors(
                'member_ids.0'
            );

        $response =
            $this->actingAs(
                $admin,
                'admin'
            )->post(
                route(
                    'admin.construction.survey.plans.store'
                ),
                [
                    'project_id' =>
                        $project->id,

                    'title' =>
                        'Initial Site Survey',

                    'planned_start_time' =>
                        '09:00',

                    'planned_end_time' =>
                        '11:00',

                    'member_ids' => [
                        $firstSurveyor->id,
                    ],
                ]
            );

        $response->assertRedirect();

        $plan =
            SurveyPlan::query()->sole();

        $this->assertStringStartsWith(
            'SUR-',
            $plan->survey_code
        );

        $this->assertSame(
            SurveyPlan::STATUS_PLANNED,
            $plan->status
        );

        $this->assertIsInt(
            $plan->status
        );

        $this->assertSame(
            'planned',
            $plan->status_key
        );

        $this->assertSame(
            'Planned',
            $plan->status_label
        );

        $this->assertDatabaseHas(
            'construction_survey_plan_members',
            [
                'survey_plan_id' =>
                    $plan->id,

                'member_id' =>
                    $firstSurveyor->id,
            ]
        );

        $this->actingAs($admin, 'admin')
            ->put(
                route(
                    'admin.construction.survey.plans.update',
                    $plan
                ),
                [
                    'title' =>
                        'Updated Site Survey',

                    'description' =>
                        'Updated scope',

                    'planned_start_time' =>
                        '10:00',

                    'planned_end_time' =>
                        '12:00',

                    'member_ids' => [
                        $secondSurveyor->id,
                    ],
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            'Updated Site Survey',
            $plan->fresh()->title
        );

        $this->assertDatabaseMissing(
            'construction_survey_plan_members',
            [
                'survey_plan_id' =>
                    $plan->id,

                'member_id' =>
                    $firstSurveyor->id,
            ]
        );

        $this->assertDatabaseHas(
            'construction_survey_plan_members',
            [
                'survey_plan_id' =>
                    $plan->id,

                'member_id' =>
                    $secondSurveyor->id,
            ]
        );
    }

    public function test_admin_can_change_only_pre_submission_plan_statuses(): void
    {
        [$admin, $project] =
            $this->createAdminContext([
                'survey_plan.manage',
            ]);

        $plan = $this->createSurveyPlan(
            $project,
            SurveyPlan::STATUS_PLANNED
        );

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.construction.survey.plans.status.update',
                    $plan
                ),
                [
                    'status' =>
                        SurveyPlan::STATUS_IN_PROGRESS,
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            SurveyPlan::STATUS_IN_PROGRESS,
            $plan->fresh()->status
        );

        $this->assertSame(
            'survey_in_progress',
            $project->fresh()->current_stage
        );

        $this->assertDatabaseHas(
            'construction_activity_logs',
            [
                'project_id' =>
                    $project->id,

                'module' =>
                    'survey_plan',

                'action' =>
                    'status_changed',
            ]
        );

        SurveyVisit::create([
            'project_id' =>
                $project->id,

            'survey_plan_id' =>
                $plan->id,

            'checked_in_by_member_id' =>
                $admin->id,

            'check_in_at' =>
                now(),

            'status' =>
                SurveyVisit::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.construction.survey.plans.status.update',
                    $plan
                ),
                [
                    'status' =>
                        SurveyPlan::STATUS_PLANNED,
                ]
            )
            ->assertSessionHasErrors(
                'status'
            );

        $this->assertSame(
            SurveyPlan::STATUS_IN_PROGRESS,
            $plan->fresh()->status
        );

        $plan->update([
            'status' =>
                SurveyPlan::STATUS_SUBMITTED,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.construction.survey.plans.status.update',
                    $plan
                ),
                [
                    'status' =>
                        SurveyPlan::STATUS_PLANNED,
                ]
            )
            ->assertSessionHasErrors(
                'status'
            );

        $this->assertSame(
            SurveyPlan::STATUS_SUBMITTED,
            $plan->fresh()->status
        );
    }

    public function test_admin_can_upload_and_delete_private_plan_documents(): void
    {
        Storage::fake('local');

        [$admin, $project] =
            $this->createAdminContext([
                'document.manage',
            ]);

        $plan = $this->createSurveyPlan(
            $project,
            SurveyPlan::STATUS_PLANNED
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.construction.survey.plans.documents.store',
                    $plan
                ),
                [
                    'documents' => [
                        UploadedFile::fake()
                            ->create(
                                'site-layout.pdf',
                                64,
                                'application/pdf'
                            ),
                    ],
                ]
            )
            ->assertRedirect();

        $document =
            ConstructionDocument::query()
                ->sole();

        $this->assertSame(
            SurveyPlan::class,
            $document->documentable_type
        );

        $this->assertSame(
            $plan->id,
            (int) $document
                ->documentable_id
        );

        $this->assertSame(
            'local',
            $document->disk
        );

        Storage::disk('local')
            ->assertExists(
                $document->path
            );

        $this->assertDatabaseHas(
            'construction_activity_logs',
            [
                'project_id' =>
                    $project->id,

                'module' =>
                    'survey_plan',

                'action' =>
                    'documents_uploaded',
            ]
        );

        $storedPath =
            $document->path;

        $this->actingAs($admin, 'admin')
            ->delete(
                route(
                    'admin.construction.survey.plans.documents.destroy',
                    [
                        $plan,
                        $document,
                    ]
                )
            )
            ->assertRedirect();

        $this->assertModelMissing(
            $document
        );

        Storage::disk('local')
            ->assertMissing(
                $storedPath
            );

        $this->assertDatabaseHas(
            'construction_activity_logs',
            [
                'project_id' =>
                    $project->id,

                'module' =>
                    'survey_plan',

                'action' =>
                    'document_deleted',
            ]
        );
    }

    public function test_approval_is_one_time_and_synchronizes_the_survey_workflow(): void
    {
        [$admin, $project] =
            $this->createAdminContext([
                'survey_plan.manage',
                'survey_submission.review',
            ]);

        [
            $plan,
            $visit,
            $submission,
        ] = $this->createSubmittedSurvey(
            $project,
            $admin
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.construction.survey.submissions.review',
                    $submission
                ),
                [
                    'status' =>
                        SurveySubmission::STATUS_APPROVED,

                    'review_notes' =>
                        'Survey data verified.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            SurveySubmission::STATUS_APPROVED,
            $submission->fresh()->status
        );

        $this->assertSame(
            SurveyVisit::STATUS_APPROVED,
            $visit->fresh()->status
        );

        $this->assertSame(
            SurveyPlan::STATUS_APPROVED,
            $plan->fresh()->status
        );

        $this->assertSame(
            'drafting_in_progress',
            $project->fresh()->current_stage
        );

        $this->assertSame(
            $admin->id,
            (int) $submission
                ->fresh()
                ->reviewed_by_member_id
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.construction.survey.submissions.review',
                    $submission
                ),
                [
                    'status' =>
                        SurveySubmission::STATUS_REJECTED,

                    'review_notes' =>
                        'Second review should not be allowed.',
                ]
            )
            ->assertSessionHasErrors(
                'status'
            );

        $this->assertSame(
            SurveySubmission::STATUS_APPROVED,
            $submission->fresh()->status
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                '/api/construction/mobile/construction/survey-visits/'
                . $visit->id
                . '/submit'
            )
            ->assertStatus(409);
    }

    public function test_revision_requires_notes_and_reopens_the_visit(): void
    {
        [$admin, $project] =
            $this->createAdminContext([
                'survey_submission.review',
            ]);

        [
            $plan,
            $visit,
            $submission,
        ] = $this->createSubmittedSurvey(
            $project,
            $admin
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.construction.survey.submissions.review',
                    $submission
                ),
                [
                    'status' =>
                        SurveySubmission::STATUS_REVISION_REQUESTED,

                    'review_notes' => '',
                ]
            )
            ->assertSessionHasErrors(
                'review_notes'
            );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.construction.survey.submissions.review',
                    $submission
                ),
                [
                    'status' =>
                        SurveySubmission::STATUS_REVISION_REQUESTED,

                    'review_notes' =>
                        'Re-capture the north elevation.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            SurveySubmission::STATUS_REVISION_REQUESTED,
            $submission->fresh()->status
        );

        $this->assertSame(
            SurveyVisit::STATUS_IN_PROGRESS,
            $visit->fresh()->status
        );

        $this->assertSame(
            SurveyPlan::STATUS_IN_PROGRESS,
            $plan->fresh()->status
        );
    }

    public function test_revision_can_be_resubmitted_and_clears_old_review_metadata(): void
    {
        [$admin, $project] =
            $this->createAdminContext([
                'survey_plan.manage',
                'survey_submission.review',
            ]);

        [
            $plan,
            $visit,
            $submission,
        ] = $this->createSubmittedSurvey(
            $project,
            $admin
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.construction.survey.submissions.review',
                    $submission
                ),
                [
                    'status' =>
                        SurveySubmission::STATUS_REVISION_REQUESTED,

                    'review_notes' =>
                        'Please re-check the site levels.',
                ]
            )
            ->assertRedirect();

        $this->assertNotNull(
            $submission
                ->fresh()
                ->reviewed_at
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->postJson(
                '/api/construction/mobile/construction/survey-visits/'
                . $visit->id
                . '/submit',
                [
                    'review_notes' =>
                        'Levels re-checked.',
                ]
            )
            ->assertOk();

        $submission->refresh();

        $this->assertSame(
            SurveySubmission::STATUS_SUBMITTED,
            $submission->status
        );

        $this->assertNull(
            $submission
                ->reviewed_by_member_id
        );

        $this->assertNull(
            $submission->reviewed_at
        );

        $this->assertSame(
            SurveyVisit::STATUS_SUBMITTED,
            $visit->fresh()->status
        );

        $this->assertSame(
            SurveyPlan::STATUS_SUBMITTED,
            $plan->fresh()->status
        );
    }

    public function test_super_admin_has_the_same_status_document_and_review_actions(): void
    {
        Storage::fake('local');

        $project =
            $this->createProject();

        $surveyor =
            $this->createMember(
                'Super Admin Surveyor'
            );

        $role =
            ConstructionRole::create([
                'name' =>
                    'Super Admin Surveyor',

                'slug' =>
                    'super-admin-surveyor-'
                    . Str::lower(
                        Str::random(6)
                    ),

                'description' =>
                    'Surveyor used by the SuperAdmin workflow test',

                'is_system_role' =>
                    true,

                'status' =>
                    'active',
            ]);

        $this->assignProjectMember(
            $surveyor,
            $project,
            $role
        );

        $superAdmin =
            SuperAdmin::query()
                ->firstOrFail();

        $this->actingAs(
            $superAdmin,
            'superadmin'
        )
            ->post(
                route(
                    'super.construction.survey.plans.store'
                ),
                [
                    'project_id' =>
                        $project->id,

                    'title' =>
                        'Super Admin Site Survey',

                    'planned_start_time' =>
                        '09:00',

                    'planned_end_time' =>
                        '11:00',

                    'member_ids' => [
                        $surveyor->id,
                    ],
                ]
            )
            ->assertRedirect();

        $plan =
            SurveyPlan::query()->sole();

        $this->assertSame(
            SurveyPlan::STATUS_PLANNED,
            $plan->status
        );

        $this->actingAs(
            $superAdmin,
            'superadmin'
        )
            ->put(
                route(
                    'super.construction.survey.plans.update',
                    $plan
                ),
                [
                    'title' =>
                        'Updated Super Admin Site Survey',

                    'planned_start_time' =>
                        '10:00',

                    'planned_end_time' =>
                        '12:00',

                    'member_ids' => [
                        $surveyor->id,
                    ],
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            'Updated Super Admin Site Survey',
            $plan->fresh()->title
        );

        $this->actingAs(
            $superAdmin,
            'superadmin'
        )
            ->patch(
                route(
                    'super.construction.survey.plans.status.update',
                    $plan
                ),
                [
                    'status' =>
                        SurveyPlan::STATUS_IN_PROGRESS,
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            SurveyPlan::STATUS_IN_PROGRESS,
            $plan->fresh()->status
        );

        $this->actingAs(
            $superAdmin,
            'superadmin'
        )
            ->post(
                route(
                    'super.construction.survey.plans.documents.store',
                    $plan
                ),
                [
                    'documents' => [
                        UploadedFile::fake()
                            ->create(
                                'super-admin-layout.pdf',
                                64,
                                'application/pdf'
                            ),
                    ],
                ]
            )
            ->assertRedirect();

        $document =
            ConstructionDocument::query()
                ->sole();

        Storage::disk('local')
            ->assertExists(
                $document->path
            );

        $this->actingAs(
            $superAdmin,
            'superadmin'
        )
            ->delete(
                route(
                    'super.construction.survey.plans.documents.destroy',
                    [
                        $plan,
                        $document,
                    ]
                )
            )
            ->assertRedirect();

        $this->assertModelMissing(
            $document
        );

        [
            $submittedPlan,
            $visit,
            $submission,
        ] = $this->createSubmittedSurvey(
            $project,
            $surveyor
        );

        $this->actingAs(
            $superAdmin,
            'superadmin'
        )
            ->post(
                route(
                    'super.construction.survey.submissions.review',
                    $submission
                ),
                [
                    'status' =>
                        SurveySubmission::STATUS_APPROVED,

                    'review_notes' =>
                        'Verified by SuperAdmin.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            SurveySubmission::STATUS_APPROVED,
            $submission->fresh()->status
        );

        $this->assertSame(
            SurveyVisit::STATUS_APPROVED,
            $visit->fresh()->status
        );

        $this->assertSame(
            SurveyPlan::STATUS_APPROVED,
            $submittedPlan->fresh()->status
        );

        $this->assertNull(
            $submission
                ->fresh()
                ->reviewed_by_member_id
        );

        $this->assertDatabaseHas(
            'construction_activity_logs',
            [
                'project_id' =>
                    $project->id,

                'actor_type' =>
                    SuperAdmin::class,

                'actor_id' =>
                    $superAdmin->id,

                'module' =>
                    'survey_submission',

                'action' =>
                    'approved',
            ]
        );
    }

        public function test_admin_cannot_manage_a_survey_from_another_project(): void
    {
        [$admin] =
            $this->createAdminContext([
                'survey_plan.manage',
            ]);

        $otherProject =
            $this->createProject(
                'PRJ-DENIED',
                'denied-project',
                'Denied Project'
            );

        $plan = $this->createSurveyPlan(
            $otherProject,
            SurveyPlan::STATUS_PLANNED
        );

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.construction.survey.plans.status.update',
                    $plan
                ),
                [
                    'status' =>
                        SurveyPlan::STATUS_IN_PROGRESS,
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            SurveyPlan::STATUS_PLANNED,
            $plan->fresh()->status
        );
    }

    public function test_index_exposes_only_projects_with_project_scoped_survey_permission(): void
    {
        [$admin, $allowedProject] =
            $this->createAdminContext([
                'survey_plan.manage',
            ]);

        $allowedPlan =
            $this->createSurveyPlan(
                $allowedProject,
                SurveyPlan::STATUS_PLANNED
            );

        $hiddenProject =
            $this->createProject(
                'PRJ-HIDDEN',
                'hidden-project',
                'Hidden Project'
            );

        $unrelatedRole =
            ConstructionRole::create([
                'name' =>
                    'Unrelated Project Role',

                'slug' =>
                    'unrelated-project-role-'
                    . Str::lower(
                        Str::random(6)
                    ),

                'description' =>
                    'Has no survey permissions',

                'is_system_role' =>
                    false,

                'status' =>
                    'active',
            ]);

        MemberRoleAssignment::create([
            'member_id' =>
                $admin->id,

            'role_id' =>
                $unrelatedRole->id,

            'project_id' =>
                $hiddenProject->id,

            'status' => 1,
        ]);

        $this->assignProjectMember(
            $admin,
            $hiddenProject,
            $unrelatedRole
        );

        $this->createSurveyPlan(
            $hiddenProject,
            SurveyPlan::STATUS_PLANNED
        );

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.construction.survey.index'
                )
            )
            ->assertOk()
            ->assertInertia(
                fn ($page) =>
                    $page
                        ->component(
                            'Admin/Construction/Survey/Index'
                        )
                        ->has('projects', 1)
                        ->where(
                            'projects.0.id',
                            $allowedProject->id
                        )
                        ->has(
                            'surveyPlans',
                            1
                        )
                        ->where(
                            'surveyPlans.0.id',
                            $allowedPlan->id
                        )
            );
    }

    /**
     * @param array<int, string> $permissionSlugs
     * @return array{
     *     0: Member,
     *     1: Project,
     *     2: ConstructionRole
     * }
     */
    private function createAdminContext(
        array $permissionSlugs
    ): array {
        $admin =
            $this->createMember(
                'Project Admin'
            );

        $project =
            $this->createProject();

        $role =
            ConstructionRole::create([
                'name' =>
                    'Project Admin',

                'slug' =>
                    'project-admin-'
                    . Str::lower(
                        Str::random(6)
                    ),

                'description' =>
                    'Project scoped test administrator',

                'is_system_role' =>
                    true,

                'status' =>
                    'active',
            ]);

        $permissionIds =
            collect($permissionSlugs)
                ->map(
                    fn (string $slug) =>
                        Permission::firstOrCreate(
                            [
                                'slug' =>
                                    $slug,
                            ],
                            [
                                'name' =>
                                    $slug,

                                'module' =>
                                    'test',
                            ]
                        )->id
                )
                ->all();

        $role->permissions()->sync(
            $permissionIds
        );

        MemberRoleAssignment::create([
            'member_id' =>
                $admin->id,

            'role_id' =>
                $role->id,

            'project_id' =>
                $project->id,

            'status' => 1,
        ]);

        $this->assignProjectMember(
            $admin,
            $project,
            $role
        );

        return [
            $admin,
            $project,
            $role,
        ];
    }

    private function assignProjectMember(
        Member $member,
        Project $project,
        ConstructionRole $role
    ): void {
        ProjectTeamMember::create([
            'project_id' =>
                $project->id,

            'member_id' =>
                $member->id,

            'role_id' =>
                $role->id,

            'status' =>
                'active',
        ]);
    }

    private function createSurveyPlan(
        Project $project,
        int $status
    ): SurveyPlan {
        return SurveyPlan::create([
            'project_id' =>
                $project->id,

            'survey_code' =>
                'SUR-'
                . Str::upper(
                    Str::random(10)
                ),

            'title' =>
                'Initial Survey',

            'status' =>
                $status,
        ]);
    }

    /**
     * @return array{
     *     0: SurveyPlan,
     *     1: SurveyVisit,
     *     2: SurveySubmission
     * }
     */
    private function createSubmittedSurvey(
        Project $project,
        Member $member
    ): array {
        $plan =
            $this->createSurveyPlan(
                $project,
                SurveyPlan::STATUS_SUBMITTED
            );

        SurveyPlanMember::create([
            'survey_plan_id' =>
                $plan->id,

            'member_id' =>
                $member->id,

            'role_in_survey' =>
                'surveyor',

            'status' =>
                'assigned',
        ]);

        $visit =
            SurveyVisit::create([
                'project_id' =>
                    $project->id,

                'survey_plan_id' =>
                    $plan->id,

                'checked_in_by_member_id' =>
                    $member->id,

                'check_in_at' =>
                    now(),

                'gps_verified' =>
                    true,

                'status' =>
                    SurveyVisit::STATUS_SUBMITTED,
            ]);

        $submission =
            SurveySubmission::create([
                'project_id' =>
                    $project->id,

                'survey_visit_id' =>
                    $visit->id,

                'submitted_by_member_id' =>
                    $member->id,

                'submitted_at' =>
                    now(),

                'status' =>
                    SurveySubmission::STATUS_SUBMITTED,
            ]);

        return [
            $plan,
            $visit,
            $submission,
        ];
    }

    private function createMember(
        string $name
    ): Member {
        $superAdmin =
            SuperAdmin::query()->first()
            ?? SuperAdmin::create([
                'uuid' =>
                    (string) Str::uuid(),

                'roles' =>
                    json_encode([
                        'super_admin',
                    ]),

                'name' =>
                    'Construction Super Admin',

                'email' =>
                    'super-admin@example.com',

                'phone' =>
                    '9999999999',

                'whatsapp_phone' =>
                    '9999999999',

                'password' =>
                    'password',
            ]);

        $slug =
            Str::slug($name)
            . '-'
            . Str::lower(
                Str::random(6)
            );

        return Member::create([
            'uuid' =>
                (string) Str::uuid(),

            'created_by' =>
                $superAdmin->id,

            'name' =>
                $name,

            'username' =>
                $slug,

            'email' =>
                $slug . '@example.com',

            'phone' =>
                (string) random_int(
                    7000000000,
                    9999999999
                ),

            'password' =>
                'password',

            'slug' =>
                $slug,

            'status' =>
                Member::STATUS_ACTIVE,
        ]);
    }

    private function createProject(
        string $projectCode =
            'PRJ-001',
        string $slug =
            'project-001',
        string $name =
            'Construction Project'
    ): Project {
        $company =
            Company::create([
                'name' =>
                    $name . ' Company',

                'status' =>
                    'active',
            ]);

        $client =
            Client::create([
                'company_id' =>
                    $company->id,

                'client_code' =>
                    $projectCode
                    . '-CLIENT',

                'name' =>
                    $name . ' Client',

                'status' =>
                    'active',
            ]);

        return Project::create([
            'company_id' =>
                $company->id,

            'client_id' =>
                $client->id,

            'project_code' =>
                $projectCode,

            'name' =>
                $name,

            'slug' =>
                $slug,

            'status' =>
                'active',

            'current_stage' =>
                'survey_in_progress',
        ]);
    }
}