import { useState, useEffect } from "react";
import { useMemo } from "react";
import { useForm, router } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import WorkflowTracker from "@/Pages/Construction/Components/WorkflowTracker";
import Modal from "@/Components/Modal";
import DynamicChecklistManager from "@/Pages/Construction/Components/DynamicChecklistManager";

export default function ProjectShow({ project, members, roles, activityLog, workflowSummary }) {
    const latestSubmission = project.survey_submissions?.[0] ?? null;
    const latestDraftingJob = project.drafting_jobs?.[0] ?? null;

    const taskCounts = workflowSummary?.task_counts ?? { total: 0, completed: 0, in_progress: 0, pending: 0 };
    const checklistCounts = workflowSummary?.checklist_counts ?? { total: 0, completed: 0 };
    const checklistCompletion = checklistCounts.total > 0
        ? Math.round((checklistCounts.completed / checklistCounts.total) * 100)
        : 0;

    const budgetForm = useForm({
        estimated_amount: project.budgets?.[0]?.estimated_amount || "",
        approved_amount: project.budgets?.[0]?.approved_amount || "",
        currency: project.budgets?.[0]?.currency || "INR",
        notes: project.budgets?.[0]?.notes || "",
        status: project.budgets?.[0]?.status || "pending",
    });

    const teamForm = useForm({
        member_id: "",
        role_id: "",
        assigned_from: "",
        assigned_to: "",
        assignment_scope: "",
        is_primary: false,
        status: "active",
    });

    const taskForm = useForm({
        title: "",
        description: "",
        planned_start_date: "",
        planned_end_date: "",
        actual_start_date: "",
        actual_end_date: "",
        priority: "medium",
        planned_quantity: "",
        unit: "",
        supervisor_member_id: "",
        requires_daily_update: false,
        requires_gps_verification: false,
        status: "draft",
    });

    const [editingTask, setEditingTask] = useState(null);

    const editingTaskForm = useForm({
        title: "",
        description: "",
        planned_start_date: "",
        planned_end_date: "",
        actual_start_date: "",
        actual_end_date: "",
        priority: "medium",
        planned_quantity: "",
        completed_quantity: "",
        unit: "",
        progress_percent: "",
        supervisor_member_id: "",
        requires_daily_update: false,
        requires_gps_verification: false,
        status: "draft",
    });

    useEffect(() => {
        if (editingTask) {
            editingTaskForm.setData({
                title: editingTask.title || "",
                description: editingTask.description || "",
                planned_start_date: editingTask.planned_start_date || "",
                planned_end_date: editingTask.planned_end_date || "",
                actual_start_date: editingTask.actual_start_date || "",
                actual_end_date: editingTask.actual_end_date || "",
                priority: editingTask.priority || "medium",
                planned_quantity: editingTask.planned_quantity ?? "",
                completed_quantity: editingTask.completed_quantity ?? "",
                unit: editingTask.unit || "",
                progress_percent: editingTask.progress_percent ?? "",
                supervisor_member_id: editingTask.supervisor_member_id || "",
                requires_daily_update: !!editingTask.requires_daily_update,
                requires_gps_verification: !!editingTask.requires_gps_verification,
                status: editingTask.status || "draft",
            });
        }
    }, [editingTask]);

    const handleRoleChange = (roleId) => {
        const roleObj = roles.find((r) => String(r.id) === String(roleId));
        let defaultScope = teamForm.data.assignment_scope;
        if (roleObj) {
            const slug = (roleObj.slug || roleObj.name || "").toLowerCase();
            if (slug.includes('survey')) defaultScope = "Field Survey & Site Data Collection";
            else if (slug.includes('draft')) defaultScope = "Drawing Revisions & CAD Drafting";
            else if (slug.includes('driver')) defaultScope = "Vehicle Transport & Site Movement";
            else if (slug.includes('admin')) defaultScope = "Project Oversight & Workflow Coordination";
            else if (slug.includes('approv')) defaultScope = "Drawing & Progress Approvals";
            else if (slug.includes('site')) defaultScope = "Site Work & Daily Execution";
        }
        teamForm.setData((prev) => ({
            ...prev,
            role_id: roleId,
            assignment_scope: defaultScope,
        }));
    };

    const selectedRole = roles.find((r) => String(r.id) === String(teamForm.data.role_id));
    const roleSlug = selectedRole ? (selectedRole.slug || selectedRole.name || "").toLowerCase() : "";

    const sortedMembers = useMemo(() => {
        if (!roleSlug) return members;
        let targetKeyword = "";
        if (roleSlug.includes("survey")) targetKeyword = "survey";
        else if (roleSlug.includes("draft")) targetKeyword = "draft";
        else if (roleSlug.includes("driver")) targetKeyword = "driver";
        else if (roleSlug.includes("admin")) targetKeyword = "admin";
        else if (roleSlug.includes("approv")) targetKeyword = "review";

        if (!targetKeyword) return members;

        const matching = [];
        const others = [];
        members.forEach((m) => {
            const desig = (m.designation_text || "").toLowerCase();
            if (desig.includes(targetKeyword)) {
                matching.push(m);
            } else {
                others.push(m);
            }
        });
        return [...matching, ...others];
    }, [members, roleSlug]);

    const metrics = useMemo(
        () => ({
            budgets: project.budgets?.length || 0,
            teamMembers: project.team_members?.length || 0,
            surveyPlans: project.survey_plans?.length || 0,
            submissions: project.survey_submissions?.length || 0,
            draftingJobs: project.drafting_jobs?.length || 0,
            approvals: project.drawing_approvals?.length || 0,
            executionTasks: taskCounts.total || project.execution_tasks?.length || 0,
            dailyProgressReports: project.daily_progress_reports?.length || 0,
            attendanceRecords: project.attendance_records?.length || 0,
            materialStocks: project.material_stocks?.length || 0,
            clientInvoices: project.client_invoices?.length || 0,
            handovers: project.handovers?.length || 0,
        }),
        [project, taskCounts]
    );

    return (
        <ConstructionShell title={project.name} description={`${project.project_code} • ${project.company?.name || ""}`} variant="super">
            <WorkflowTracker currentStage={project.current_stage} />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard label="Budget Versions" value={metrics.budgets} />
                <StatCard label="Team Members" value={metrics.teamMembers} />
                <StatCard label="Survey Plans" value={metrics.surveyPlans} />
                <StatCard label="Survey Submissions" value={metrics.submissions} />
                <StatCard label="Drafting Jobs" value={metrics.draftingJobs} />
                <StatCard label="Approvals" value={metrics.approvals} />
                <StatCard label="Execution Tasks" value={metrics.executionTasks} />
                <StatCard label="Daily Progress" value={metrics.dailyProgressReports} />
                <StatCard label="Attendance" value={metrics.attendanceRecords} />
                <StatCard label="Material Stock" value={metrics.materialStocks} />
                <StatCard label="Invoices" value={metrics.clientInvoices} />
                <StatCard label="Handovers" value={metrics.handovers} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                <SectionCard title="Project Summary" description="This project record is the anchor for all Phase 1 foundation and Phase 2 survey workflows.">
                    <div className="flex flex-wrap items-center gap-3">
                        <StatusBadge value={project.status} />
                        <StatusBadge value={project.current_stage} />
                        <StatusBadge value={project.priority} />
                    </div>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <Field label="Client" value={project.client?.name} />
                        <Field label="Company" value={project.company?.name} />
                        <Field label="Start Date" value={formatDate(project.start_date)} />
                        <Field label="Expected End" value={formatDate(project.expected_end_date)} />
                        <LocationDisplay
                            className="sm:col-span-2"
                            locationName={project.location_name}
                            address={project.project_address}
                            lat={project.latitude}
                            lng={project.longitude}
                        />
                        <Field label="Description" value={project.description} span="sm:col-span-2" />
                    </dl>
                </SectionCard>

                <SectionCard title="Workflow Snapshot" description="Quick read of where this project currently stands.">
                    <div className="space-y-4">
                        <SnapshotRow label="Latest Budget" value={project.budgets?.[0] ? `${project.budgets[0].currency} ${project.budgets[0].approved_amount || project.budgets[0].estimated_amount}` : null} badge={project.budgets?.[0]?.status} />
                        <SnapshotRow label="Latest Survey Submission" value={latestSubmission ? `Visit #${latestSubmission.survey_visit_id}` : null} badge={latestSubmission?.status_key} />
                        <SnapshotRow label="Latest Drafting Job" value={latestDraftingJob ? latestDraftingJob.assigned_to?.name || "Unassigned" : null} badge={latestDraftingJob?.status} />
                        <SnapshotRow label="Primary Team Size" value={`${project.team_members?.filter((item) => item.is_primary).length || 0} primary assignments`} />
                    </div>
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Budget Approval" description="Approve, reject, or revise the project budget before downstream work continues.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            budgetForm.post(route("super.construction.projects.budget.store", project.id), { preserveScroll: true });
                        }}
                        className="grid gap-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <InputField form={budgetForm} name="estimated_amount" label="Estimated Amount" />
                            <InputField form={budgetForm} name="approved_amount" label="Approved Amount" />
                            <InputField form={budgetForm} name="currency" label="Currency" />
                            <SelectField form={budgetForm} name="status" label="Budget Decision" options={[
                                { value: "pending", label: "Pending" },
                                { value: "approved", label: "Approved" },
                                { value: "rejected", label: "Rejected" },
                            ]} />
                        </div>
                        <TextAreaField form={budgetForm} name="notes" label="Budget Notes" rows={4} />
                        <button type="submit" disabled={budgetForm.processing} className="rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {budgetForm.processing ? "Saving..." : "Save Budget Decision"}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Team Assignment" description="Assign project admin, surveyor, draft person, and review roles to project members.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            teamForm.post(route("super.construction.projects.team.assign", project.id), {
                                preserveScroll: true,
                                onSuccess: () => teamForm.reset("member_id", "role_id", "assigned_from", "assigned_to", "assignment_scope", "is_primary"),
                                onError: (errors) => {
                                    if (errors.member_id) {
                                        toast.error(errors.member_id);
                                    } else {
                                        const firstError = Object.values(errors)[0];
                                        if (firstError) toast.error(firstError);
                                    }
                                },
                            });
                        }}
                        className="grid gap-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                form={teamForm}
                                name="role_id"
                                label="Construction Role"
                                options={[
                                    { value: "", label: "-- Select Construction Role --" },
                                    ...roles.map((role) => ({ value: role.id, label: role.name })),
                                ]}
                                onChangeCustom={(val) => handleRoleChange(val)}
                            />
                            <SelectField
                                form={teamForm}
                                name="member_id"
                                label="Member"
                                options={[
                                    { value: "", label: "-- Select Member --" },
                                    ...sortedMembers.map((member) => ({
                                        value: member.id,
                                        label: `${member.name}${member.designation_text ? ` (${member.designation_text})` : ""}${member.email ? ` • ${member.email}` : ""}`,
                                    })),
                                ]}
                            />
                            {/* <InputField form={teamForm} name="assigned_from" label="Assigned From" type="date" />
                            <InputField form={teamForm} name="assigned_to" label="Assigned To" type="date" placeholder="dd-mm-yyyy" /> */}
                        </div>
                        <InputField form={teamForm} name="assignment_scope" label="Assignment Scope" placeholder="Survey, drafting, approvals, coordination..." />
                        <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                            <input type="checkbox" checked={teamForm.data.is_primary} onChange={(e) => teamForm.setData("is_primary", e.target.checked)} />
                            Mark this assignment as primary
                        </label>
                        <button type="submit" disabled={teamForm.processing} className="rounded-xl bg-slate-900 px-4 py-3 font-medium text-white hover:bg-slate-800 disabled:opacity-60 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                            {teamForm.processing ? "Assigning..." : "Assign Team Member"}
                        </button>
                    </form>
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Assigned Team" description="Current team and role coverage for this project.">
                    {project.team_members?.length ? (
                        <div className="space-y-3">
                            {project.team_members.map((teamMember) => (
                                <TeamMemberRow
                                    key={teamMember.id}
                                    teamMember={teamMember}
                                    project={project}
                                    roles={roles}
                                />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No team assigned yet." description="Assign the project admin and Phase 2 roles to unlock survey and drafting execution." />
                    )}
                </SectionCard>

                <SectionCard title="Activity Log" description="Every major action in the project workflow should show up here.">
                    {activityLog?.length ? (
                        <div className="space-y-3">
                            {activityLog.map((item) => (
                                <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{item.module}</p>
                                            <p className="text-sm text-slate-500">
                                                {item.actor?.name || item.actor?.email || "System"} • {formatDate(item.created_at)}
                                            </p>
                                        </div>
                                        <StatusBadge value={item.action} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No project activity yet." description="Once actions are performed, the workflow audit trail will appear here." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <SectionCard title="Survey Plans" description="Survey planning, assigned members, and field execution details." className="xl:col-span-2">
                    {project.survey_plans?.length ? (
                        <div className="space-y-4">
                            {project.survey_plans.map((plan) => (
                                <div key={plan.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{plan.title}</p>
                                            <p className="text-sm text-slate-500">{plan.survey_code} • {formatDate(plan.planned_date) || "No planned date"}</p>
                                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{plan.site_address || "No site address recorded."}</p>
                                        </div>
                                        <StatusBadge value={plan.status_key} />
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {(plan.plan_members || []).map((member) => (
                                            <span key={member.id} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                {member.member?.name || "Unknown"} • {member.role_in_survey}
                                            </span>
                                        ))}
                                    </div>
                                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                                        {(plan.visits || []).map((visit) => (
                                            <div key={visit.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                                <div className="flex flex-wrap items-center justify-between gap-3">
                                                    <p className="font-medium text-slate-900 dark:text-white">Visit #{visit.id}</p>
                                                    <div className="flex gap-2">
                                                        <StatusBadge value={visit.status_key} />
                                                        <StatusBadge value={visit.gps_verified ? "approved" : "revision_requested"} />
                                                    </div>
                                                </div>
                                                <p className="mt-2 text-sm text-slate-500">Check-in by {visit.checked_in_by?.name || "Unknown"} • {formatDateTime(visit.check_in_at) || "No timestamp"}</p>
                                                <p className="mt-1 text-sm text-slate-500">Entries: {visit.entries?.length || 0} • Measurements: {visit.measurements?.length || 0}</p>
                                                {visit.submission ? (
                                                    <div className="mt-3 rounded-2xl bg-slate-50 p-3 dark:bg-slate-900">
                                                        <p className="text-sm font-medium text-slate-900 dark:text-white">Submission Status</p>
                                                        <div className="mt-2 flex items-center justify-between gap-3">
                                                            <p className="text-sm text-slate-500">{visit.submission.submitted_by?.name || "Unknown"}</p>
                                                           <StatusBadge value={visit.submission.status_key}/>
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey plans yet." description="Create a survey plan once budget approval and team assignment are complete." />
                    )}
                </SectionCard>

                <SectionCard title="Survey Submission Queue" description="Review outcomes before drafting starts.">
                    {project.survey_submissions?.length ? (
                        <div className="space-y-3">
                            {project.survey_submissions.map((submission) => (
                                <div key={submission.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium text-slate-900 dark:text-white">Visit #{submission.survey_visit_id}</p>
                                       <StatusBadge value={submission.status_key}/>
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">Submitted by {submission.submitted_by?.name || "Unknown"}</p>
                                    <p className="mt-1 text-sm text-slate-500">Reviewed by{" "}{submission.reviewed_by?.name|| (submission.reviewed_at? "authorized reviewer (see activity log)": "Pending review")}</p>
                                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{submission.review_notes || "No review notes yet."}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey submissions yet." description="Field teams need to check in, capture data, and submit their visit from mobile first." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.4fr,1fr]">
                <SectionCard title="Drafting Jobs" description="Approved survey data moves here for CAD work, revisions, and final drawing handoff.">
                    {project.drafting_jobs?.length ? (
                        <div className="space-y-4">
                            {project.drafting_jobs.map((job) => (
                                <div key={job.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">Drafting Job #{job.id}</p>
                                            <p className="text-sm text-slate-500">
                                                Assigned to {job.assigned_to?.name || "Unassigned"} • Due {formatDate(job.due_date) || "Not set"}
                                            </p>
                                        </div>
                                        <StatusBadge value={job.status} />
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        {(job.drawing_revisions || []).map((revision) => (
                                            <div key={revision.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                                <div className="flex flex-wrap items-center justify-between gap-3">
                                                    <div>
                                                        <p className="font-medium text-slate-900 dark:text-white">Revision {revision.revision_no}</p>
                                                        <p className="text-sm text-slate-500">Uploaded by {revision.uploaded_by?.name || "Unknown"} • {formatDateTime(revision.uploaded_at) || "No upload time"}</p>
                                                    </div>
                                                    <StatusBadge value={revision.status} />
                                                </div>
                                                <div className="mt-3 grid gap-2 text-sm text-slate-600 dark:text-slate-300">
                                                    <p>DWG: {revision.dwg_document?.original_name || "Not uploaded"}</p>
                                                    <p>PDF: {revision.pdf_document?.original_name || "Not uploaded"}</p>
                                                    <p>Notes: {revision.notes || "No notes added."}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drafting jobs yet." description="Once a survey submission is approved, create a drafting job from the drafting screen." />
                    )}
                </SectionCard>

                <SectionCard title="Drawing Approvals" description="Final Phase 2 gate before the project becomes ready for construction.">
                    {project.drawing_approvals?.length ? (
                        <div className="space-y-3">
                            {project.drawing_approvals.map((approval) => (
                                <div key={approval.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium text-slate-900 dark:text-white">Approval #{approval.id}</p>
                                        <StatusBadge value={approval.decision} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">Revision #{approval.drawing_revision_id}</p>
                                    <p className="mt-1 text-sm text-slate-500">{approval.remarks || "No remarks added."}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drawing approvals yet." description="A submitted drawing revision will automatically generate an approval record here." />
                    )}
                </SectionCard>
            </div>

            <SectionCard
                title="Execution Tasks"
                description={`${taskCounts.total} total tasks · ${taskCounts.completed} complete · ${taskCounts.in_progress} in-progress · ${taskCounts.pending} pending`}
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        taskForm.post(route("super.construction.projects.tasks.store", project.id), {
                            preserveScroll: true,
                            onSuccess: () => {
                                taskForm.reset(
                                    "title",
                                    "description",
                                    "planned_start_date",
                                    "planned_end_date",
                                    "actual_start_date",
                                    "actual_end_date",
                                    "priority",
                                    "planned_quantity",
                                    "unit",
                                    "supervisor_member_id",
                                    "requires_daily_update",
                                    "requires_gps_verification",
                                    "status",
                                );
                                taskForm.setData("priority", "medium");
                                taskForm.setData("status", "draft");
                            },
                            onError: (errors) => {
                                const firstError = Object.values(errors)[0];
                                if (firstError) toast.error(firstError);
                            },
                        });
                    }}
                    className="grid gap-4 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
                >
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-200">Register New Execution Task</p>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <InputField form={taskForm} name="title" label="Task Title" placeholder="Survey site perimeter lines" />
                        <SelectField
                            form={taskForm}
                            name="priority"
                            label="Priority"
                            options={[
                                { value: "low", label: "Low" },
                                { value: "medium", label: "Medium" },
                                { value: "high", label: "High" },
                                { value: "critical", label: "Critical" },
                            ]}
                        />
                        <SelectField
                            form={taskForm}
                            name="status"
                            label="Status"
                            options={[
                                { value: "draft", label: "Draft" },
                                { value: "planned", label: "Planned" },
                                { value: "in_progress", label: "In Progress" },
                                { value: "completed", label: "Completed" },
                                { value: "blocked", label: "Blocked" },
                            ]}
                        />
                        <SelectField
                            form={taskForm}
                            name="supervisor_member_id"
                            label="Supervisor"
                            options={[
                                { value: "", label: "-- Optional supervisor --" },
                                ...members.map((m) => ({ value: m.id, label: m.name })),
                            ]}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <InputField form={taskForm} name="planned_start_date" label="Planned Start" type="date" />
                        <InputField form={taskForm} name="planned_end_date" label="Planned End" type="date" />
                        <InputField form={taskForm} name="actual_start_date" label="Actual Start" type="date" />
                        <InputField form={taskForm} name="actual_end_date" label="Actual End" type="date" />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <InputField form={taskForm} name="planned_quantity" label="Planned Quantity" type="number" />
                        <InputField form={taskForm} name="unit" label="Unit" placeholder="sqm / nos / m" />
                        <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                            <input
                                type="checkbox"
                                checked={taskForm.data.requires_daily_update}
                                onChange={(e) => taskForm.setData("requires_daily_update", e.target.checked)}
                            />
                            Requires Daily Update
                        </label>
                        <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                            <input
                                type="checkbox"
                                checked={taskForm.data.requires_gps_verification}
                                onChange={(e) => taskForm.setData("requires_gps_verification", e.target.checked)}
                            />
                            GPS Verification
                        </label>
                    </div>
                    <TextAreaField form={taskForm} name="description" label="Task Description" rows={2} />
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={taskForm.processing}
                            className="rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                        >
                            {taskForm.processing ? "Saving..." : "Create Execution Task"}
                        </button>
                    </div>
                </form>

                <div className="mt-6">
                    {project.execution_tasks?.length ? (
                        <div className="space-y-3">
                            {project.execution_tasks.map((task) => (
                                <TaskRow
                                    key={task.id}
                                    task={task}
                                    project={project}
                                    members={members}
                                    onEdit={() => setEditingTask(task)}
                                />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No execution tasks yet." description="Create a task from the form above to track daily execution progress." />
                    )}
                </div>
            </SectionCard>

            <DynamicChecklistManager
                project={project}
                namespace="super"
                variant="project-show"
                enableDeltaPoll
                canManage={true}
                initialCounts={{ total: checklistCounts.total, completed: checklistCounts.completed, completion: checklistCompletion }}
            />

            <div className="grid gap-6 xl:grid-cols-3">
                <SectionCard title="Daily Progress & Attendance" description="Submissions, attendance and materials snapshot.">
                    <div className="space-y-3">
                        <SnapshotRow
                            label="Progress Reports Submitted"
                            value={project.daily_progress_reports?.length || 0}
                            badge={project.daily_progress_reports?.length ? "approved" : "planned"}
                        />
                        <SnapshotRow
                            label="Attendance Logged"
                            value={project.attendance_records?.length || 0}
                            badge={project.attendance_records?.length ? "approved" : "planned"}
                        />
                        <SnapshotRow
                            label="Material Stock Items"
                            value={project.material_stocks?.length || 0}
                            badge={project.material_stocks?.length ? "approved" : "planned"}
                        />
                        <SnapshotRow
                            label="Material Issues"
                            value={project.material_issues?.length || 0}
                            badge={project.material_issues?.length ? "approved" : "planned"}
                        />
                    </div>
                </SectionCard>
                <SectionCard title="Procurement Snapshot" description="Purchase flow across the project.">
                    <div className="space-y-3">
                        <SnapshotRow label="Purchase Requests" value={project.purchase_requests?.length || 0} />
                        <SnapshotRow label="Purchase Orders" value={project.purchase_orders?.length || 0} />
                        <SnapshotRow label="Material Receipts" value={project.material_receipts?.length || 0} />
                    </div>
                </SectionCard>
                <SectionCard title="Billing & Handover" description="Billed amounts and handover milestones.">
                    <div className="space-y-3">
                        <SnapshotRow label="Invoices Raised" value={project.client_invoices?.length || 0} />
                        <SnapshotRow label="Payments Received" value={project.client_payments?.length || 0} />
                        <SnapshotRow label="Handovers" value={project.handovers?.length || 0} />
                        <SnapshotRow
                            label="Handover Items"
                            value={
                                (project.handovers || []).reduce((acc, handover) => acc + (handover.items?.length || 0), 0)
                            }
                        />
                    </div>
                </SectionCard>
            </div>

            {editingTask ? (
                <Modal show={true} onClose={() => setEditingTask(null)}>
                    <div className="p-6">
                        <h3 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                            Edit Execution Task - {editingTask.task_code}
                        </h3>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                editingTaskForm.put(
                                    route("super.construction.projects.tasks.update", [project.id, editingTask.id]),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setEditingTask(null),
                                        onError: (errors) => {
                                            const firstError = Object.values(errors)[0];
                                            if (firstError) toast.error(firstError);
                                        },
                                    },
                                );
                            }}
                            className="grid gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <InputField form={editingTaskForm} name="title" label="Title" />
                                <SelectField
                                    form={editingTaskForm}
                                    name="status"
                                    label="Status"
                                    options={[
                                        { value: "draft", label: "Draft" },
                                        { value: "planned", label: "Planned" },
                                        { value: "in_progress", label: "In Progress" },
                                        { value: "completed", label: "Completed" },
                                        { value: "blocked", label: "Blocked" },
                                    ]}
                                />
                                <SelectField
                                    form={editingTaskForm}
                                    name="priority"
                                    label="Priority"
                                    options={[
                                        { value: "low", label: "Low" },
                                        { value: "medium", label: "Medium" },
                                        { value: "high", label: "High" },
                                        { value: "critical", label: "Critical" },
                                    ]}
                                />
                                <SelectField
                                    form={editingTaskForm}
                                    name="supervisor_member_id"
                                    label="Supervisor"
                                    options={[
                                        { value: "", label: "-- No supervisor --" },
                                        ...members.map((m) => ({ value: m.id, label: m.name })),
                                    ]}
                                />
                                <InputField form={editingTaskForm} name="planned_start_date" label="Planned Start" type="date" />
                                <InputField form={editingTaskForm} name="planned_end_date" label="Planned End" type="date" />
                                <InputField form={editingTaskForm} name="actual_start_date" label="Actual Start" type="date" />
                                <InputField form={editingTaskForm} name="actual_end_date" label="Actual End" type="date" />
                                <InputField form={editingTaskForm} name="planned_quantity" label="Planned Qty" type="number" />
                                <InputField form={editingTaskForm} name="completed_quantity" label="Completed Qty" type="number" />
                                <InputField form={editingTaskForm} name="unit" label="Unit" />
                                <InputField form={editingTaskForm} name="progress_percent" label="Progress %" type="number" />
                                <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={editingTaskForm.data.requires_daily_update}
                                        onChange={(e) => editingTaskForm.setData("requires_daily_update", e.target.checked)}
                                    />
                                    Requires Daily Update
                                </label>
                                <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={editingTaskForm.data.requires_gps_verification}
                                        onChange={(e) => editingTaskForm.setData("requires_gps_verification", e.target.checked)}
                                    />
                                    GPS Verification
                                </label>
                            </div>
                            <TextAreaField form={editingTaskForm} name="description" label="Description" rows={3} />
                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setEditingTask(null)}
                                    className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editingTaskForm.processing}
                                    className="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                                >
                                    {editingTaskForm.processing ? "Saving..." : "Save Changes"}
                                </button>
                            </div>
                        </form>
                    </div>
                </Modal>
            ) : null}
        </ConstructionShell>
    );
}

function TaskRow({ task, project, onEdit }) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const handleDelete = () => {
        router.delete(route("super.construction.projects.tasks.destroy", [project.id, task.id]), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(false),
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    const taskChecklists = task.checklists || [];
    const completedChecklists = taskChecklists.filter((item) => item.is_completed).length;
    const progress = taskChecklists.length
        ? Math.round((completedChecklists / taskChecklists.length) * 100)
        : (Number(task.progress_percent) || 0);

    return (
        <>
            <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-semibold text-slate-900 dark:text-white">{task.title}</p>
                            <StatusBadge value={task.status} />
                            <StatusBadge value={task.priority} />
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            {task.task_code} • {formatDate(task.planned_start_date) || "TBD"} → {formatDate(task.planned_end_date) || "TBD"}
                        </p>
                        {task.description ? (
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{task.description}</p>
                        ) : null}
                        <div className="mt-3 grid gap-3 text-xs sm:grid-cols-3">
                            <div>
                                <p className="text-slate-500">Supervisor</p>
                                <p className="font-medium text-slate-900 dark:text-white">{task.supervisor?.name || "Unassigned"}</p>
                            </div>
                            <div>
                                <p className="text-slate-500">Progress</p>
                                <p className="font-medium text-slate-900 dark:text-white">{progress}%</p>
                            </div>
                            <div>
                                <p className="text-slate-500">Checklists</p>
                                <p className="font-medium text-slate-900 dark:text-white">
                                    {completedChecklists}/{taskChecklists.length}
                                </p>
                            </div>
                        </div>
                        <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                className="h-full bg-gradient-to-r from-emerald-400 to-indigo-500 transition-all"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={onEdit}
                            className="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                        >
                            Edit Task
                        </button>
                        <button
                            onClick={() => setConfirmingDelete(true)}
                            className="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/30"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
            {confirmingDelete ? (
                <Modal show={true} onClose={() => setConfirmingDelete(false)}>
                    <div className="p-6">
                        <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-white">Delete Execution Task</h3>
                        <p className="mb-6 text-sm text-slate-600 dark:text-slate-400">
                            Removing <strong>{task.task_code}</strong> will also clean up its checklists, assignees, and DPR links. This cannot be undone.
                        </p>
                        <div className="flex gap-3">
                            <button
                                onClick={() => setConfirmingDelete(false)}
                                className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleDelete}
                                className="flex-1 rounded-xl bg-rose-600 px-4 py-2 font-medium text-white hover:bg-rose-500"
                            >
                                Delete Task
                            </button>
                        </div>
                    </div>
                </Modal>
            ) : null}
        </>
    );
}

function formatDate(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function formatDateTime(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function LocationDisplay({ locationName, address, lat, lng, className = "" }) {
    const hasCoords =
        lat != null && lng != null && lat !== "" && lng !== "" && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng));
    const displayAddr = locationName || address;
    return (
        <div className={className}>
            <dt className="text-sm text-slate-500">📍 Location</dt>
            <dd className="mt-1 space-y-1 font-medium text-slate-900 dark:text-white">
                {displayAddr ? (
                    <div className="break-words leading-snug">{displayAddr}</div>
                ) : (
                    <div className="text-slate-400">Not set</div>
                )}
                {locationName && address && locationName !== address ? (
                    <div className="text-xs font-normal text-slate-500 break-words">
                        Raw address: {address}
                    </div>
                ) : null}
                {hasCoords ? (
                    <div className="flex flex-wrap items-center gap-3 text-xs font-normal text-slate-500">
                        <span>
                            Lat <span className="font-mono text-slate-700 dark:text-slate-300">{Number(lat).toFixed(6)}</span>
                            {" · "}
                            Lng <span className="font-mono text-slate-700 dark:text-slate-300">{Number(lng).toFixed(6)}</span>
                        </span>
                        <a
                            href={`https://www.google.com/maps?q=${encodeURIComponent(Number(lat))},${encodeURIComponent(Number(lng))}`}
                            target="_blank"
                            rel="noreferrer noopener"
                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-indigo-400 dark:hover:bg-slate-800"
                        >
                            Open in Maps
                        </a>
                    </div>
                ) : null}
            </dd>
        </div>
    );
}

function Field({ label, value, span = "" }) {
    return (
        <div className={span}>
            <dt className="text-sm text-slate-500">{label}</dt>
            <dd className="mt-1 font-medium text-slate-900 dark:text-white">{value || "-"}</dd>
        </div>
    );
}

function SnapshotRow({ label, value, badge = null }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-slate-900 dark:text-white">{label}</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{value || "Not available yet"}</p>
                </div>
                {badge ? <StatusBadge value={badge} /> : null}
            </div>
        </div>
    );
}

function InputField({ form, name, label, type = "text", placeholder = "" }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input
                type={type}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                placeholder={placeholder}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TextAreaField({ form, name, label, rows = 4 }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <textarea
                rows={rows}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function SelectField({ form, name, label, options, onChangeCustom }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <select
                value={form.data[name]}
                onChange={(e) => {
                    form.setData(name, e.target.value);
                    if (onChangeCustom) onChangeCustom(e.target.value);
                }}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TeamMemberRow({ teamMember, project, roles }) {
    const [showEditModal, setShowEditModal] = useState(false);
    const [showRemoveModal, setShowRemoveModal] = useState(false);

    const editForm = useForm({
        member_id: teamMember.member_id,
        role_id: teamMember.role_id || "",
        assignment_scope: teamMember.assignment_scope || "",
        is_primary: teamMember.is_primary,
        status: teamMember.status,
    });

    // Re-sync form data whenever modal opens or teamMember updates
    useEffect(() => {
        if (showEditModal) {
            editForm.setData({
                member_id: teamMember.member_id,
                role_id: teamMember.role_id || "",
                assignment_scope: teamMember.assignment_scope || "",
                is_primary: teamMember.is_primary,
                status: teamMember.status,
            });
        }
    }, [showEditModal, teamMember]);

    const openEditModal = () => {
        setShowEditModal(true);
    };

    const handleEditSubmit = (e) => {
        e.preventDefault();
        editForm.put(route("super.construction.projects.team.update", [project.id, teamMember.id]), {
            preserveScroll: true,
            onSuccess: () => {
                setShowEditModal(false);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    const handleRemove = () => {
        setShowRemoveModal(false);
        router.delete(route("super.construction.projects.team.destroy", [project.id, teamMember.id]), {
            preserveScroll: true,
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    const handleStatusToggle = () => {
        router.patch(route("super.construction.projects.team.status", [project.id, teamMember.id]), {}, {
            preserveScroll: true,
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    return (
        <>
            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <p className="font-semibold text-slate-900 dark:text-white">{teamMember.member?.name || "Unknown member"}</p>
                            {teamMember.is_primary && (
                                <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">
                                    Primary
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-slate-500">{teamMember.role?.name || "No role assigned"}</p>
                        <p className="mt-1 text-xs text-slate-500">{teamMember.assignment_scope || "General project support"}</p>
                        <div className="mt-2">
                            <StatusBadge value={teamMember.status} />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={() => router.visit(route("super.construction.projects.team.show", [project.id, teamMember.id]))}
                            className="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/30"
                        >
                            View Details
                        </button>
                        <button
                            onClick={openEditModal}
                            className="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                        >
                            Edit
                        </button>
                        <button
                            onClick={handleStatusToggle}
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium ${
                                teamMember.status === 'active'
                                    ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/30'
                                    : 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/20 dark:text-green-300 dark:hover:bg-green-900/30'
                            }`}
                        >
                            {teamMember.status === 'active' ? 'Deactivate' : 'Activate'}
                        </button>
                        <button
                            onClick={() => setShowRemoveModal(true)}
                            className="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/30"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            {/* Edit Modal */}
            <Modal show={showEditModal} onClose={() => setShowEditModal(false)}>
                <div className="p-6">
                    <h3 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Edit Team Member Assignment</h3>
                    <form onSubmit={handleEditSubmit} className="grid gap-4">
                        <SelectField
                            form={editForm}
                            name="role_id"
                            label="Construction Role"
                            options={[
                                { value: "", label: "-- Select Construction Role --" },
                                ...roles.map((role) => ({ value: role.id, label: role.name })),
                            ]}
                        />
                        <InputField
                            form={editForm}
                            name="assignment_scope"
                            label="Assignment Scope"
                            placeholder="Survey, drafting, approvals, coordination..."
                        />
                        <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                            <input
                                type="checkbox"
                                checked={editForm.data.is_primary}
                                onChange={(e) => editForm.setData("is_primary", e.target.checked)}
                            />
                            Mark this assignment as primary
                        </label>
                        <SelectField
                            form={editForm}
                            name="status"
                            label="Status"
                            options={[
                                { value: "active", label: "Active" },
                                { value: "inactive", label: "Inactive" },
                            ]}
                        />
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={() => setShowEditModal(false)}
                                className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {editForm.processing ? "Saving..." : "Save Changes"}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>

            {/* Remove Confirmation Modal */}
            <Modal show={showRemoveModal} onClose={() => setShowRemoveModal(false)}>
                <div className="p-6">
                    <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-white">Remove Team Member</h3>
                    <p className="mb-6 text-sm text-slate-600 dark:text-slate-400">
                        Are you sure you want to remove <strong>{teamMember.member?.name}</strong> from this project? This action cannot be undone.
                    </p>
                    <div className="flex gap-3">
                        <button
                            onClick={() => setShowRemoveModal(false)}
                            className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={handleRemove}
                            disabled={editForm.processing}
                            className="flex-1 rounded-xl bg-rose-600 px-4 py-2 font-medium text-white hover:bg-rose-500 disabled:opacity-60"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
