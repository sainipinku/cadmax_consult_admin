import { useState, useEffect } from "react";
import { useMemo } from "react";
import { useForm, router, usePage } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import WorkflowTracker from "@/Pages/Construction/Components/WorkflowTracker";
import Modal from "@/Components/Modal";
import DynamicChecklistManager from "@/Pages/Construction/Components/DynamicChecklistManager";
import SupervisorSingleScreenReview from "@/Pages/Construction/Components/SupervisorSingleScreenReview";
import DraftingWorkflowPanel from "@/Pages/Construction/Components/DraftingWorkflowPanel";
import AccountsVerificationPanel from "@/Pages/Construction/Components/AccountsVerificationPanel";
import SurveySubmissionPanel from "@/Pages/Construction/Components/SurveySubmissionPanel";
import WorkflowAuditTrail from "@/Pages/Construction/Components/WorkflowAuditTrail";
import FieldNotesDisplay from "@/Pages/Construction/Components/FieldNotesDisplay";
import ProjectActivityLog from "@/Pages/Construction/Components/ProjectActivityLog";

export default function ProjectShow({ project, members, roles, activityLog, workflowSummary }) {
    const { auth } = usePage().props;
    const currentUser = auth?.user;
    const guard = auth?.guard;
    const activeRoleSlug = String(auth?.active_role?.slug || auth?.active_role || "").toLowerCase();
    const isImpersonating = Boolean(auth?.is_impersonating);

    const isSuperAdmin = !isImpersonating && Boolean(
        guard === "superadmin" ||
        currentUser?.email === "superadmin@gmail.com" ||
        currentUser?.is_super_admin ||
        currentUser?.slug === "super-admin" ||
        currentUser?.slug === "super_admin" ||
        (Array.isArray(currentUser?.assigned_roles) && (currentUser.assigned_roles.includes("super_admin") || currentUser.assigned_roles.includes("superadmin")))
    );

    const isDraftPersonUser = useMemo(() => {
        if (activeRoleSlug === "draft_person" || activeRoleSlug === "drafter" || activeRoleSlug === "draftsman") return true;
        if (Array.isArray(currentUser?.assigned_roles) && (currentUser.assigned_roles.includes("draft_person") || currentUser.assigned_roles.includes("drafter"))) return true;
        const desig = String(currentUser?.role_badge || currentUser?.designation_text || currentUser?.designation || "").toLowerCase();
        if (/draft|cad|drafter|draftman|draftsman/i.test(desig)) return true;
        const team = project?.team_members || project?.teamMembers || [];
        if (currentUser?.id && team.some((tm) => Number(tm.member_id) === Number(currentUser.id) && (/draft|cad|drafter/i.test(tm.role?.name || tm.assignment_scope || tm.project_role || "")))) return true;
        if (currentUser?.id && (project?.drafting_jobs || []).some((dj) => Number(dj.assigned_to_member_id) === Number(currentUser.id))) return true;
        return false;
    }, [activeRoleSlug, currentUser, project]);

    const userPermissions = auth?.permissions || auth?.construction_permissions || [];
    const hasPerm = (slug) => {
        if (isSuperAdmin) return true;
        if (!slug) return true;
        const slugs = Array.isArray(slug) ? slug : [slug];
        return slugs.some((s) => userPermissions.includes(s));
    };

    const canManageTeam = !isDraftPersonUser && (isSuperAdmin || hasPerm(["project_team.manage", "project.manage"]));

    const [activeWorkflowTab, setActiveWorkflowTab] = useState(() => isDraftPersonUser ? "drafting" : "supervisor");

    useEffect(() => {
        if (isDraftPersonUser) {
            setActiveWorkflowTab("drafting");
        }
    }, [isDraftPersonUser]);

    const latestSubmission = project.survey_submissions?.[0] ?? null;
    const latestDraftingJob = project.drafting_jobs?.[0] ?? null;

    const [showDirectDraftUpload, setShowDirectDraftUpload] = useState(false);
    const directDraftForm = useForm({
        drafting_job_id: latestDraftingJob?.id || "",
        drawing_type: "2D Floor Layout Plan",
        drawing_no: `DWG-${project.project_code || "001"}`,
        revision_no: (latestDraftingJob?.drawing_revisions?.[0]?.revision_no || 0) + 1,
        changes_made: "",
        notes: "",
        dwg_file: null,
        pdf_file: null,
    });

    const handleDirectDraftSubmit = (e) => {
        e.preventDefault();
        directDraftForm.post(route("super.construction.projects.tasks-v2.submit-draft", project.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                directDraftForm.reset("changes_made", "notes", "dwg_file", "pdf_file");
                setShowDirectDraftUpload(false);
                toast.success("CAD Drawing revision submitted successfully!");
            },
            onError: (errs) => {
                const first = Object.values(errs)[0];
                if (first) toast.error(first);
            },
        });
    };

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
    const mergedTaskList = useMemo(() => mergedTasksForExecutionList(project), [project]);
    const [showCreateTask, setShowCreateTask] = useState(false);
    const [showAdvancedTaskFields, setShowAdvancedTaskFields] = useState(false);

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
                planned_start_date: formatDateForInput(editingTask.planned_start_date || editingTask.start_date || ""),
                planned_end_date: formatDateForInput(editingTask.planned_end_date || editingTask.end_date || ""),
                actual_start_date: formatDateForInput(editingTask.actual_start_date || ""),
                actual_end_date: formatDateForInput(editingTask.actual_end_date || ""),
                priority: editingTask.priority || "medium",
                planned_quantity: editingTask.planned_quantity ?? editingTask.planned_qty ?? "",
                completed_quantity: editingTask.completed_quantity ?? editingTask.completed_qty ?? "",
                unit: editingTask.unit || editingTask.qty_unit || "",
                progress_percent: editingTask.progress_percent ?? "",
                supervisor_member_id: editingTask.supervisor_member_id || editingTask.assigned_supervisor_member_id || "",
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
            const desig = `${m.role_badge || ""} ${m.designation_text || ""}`.toLowerCase();
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
            <WorkflowTracker currentStage={project.current_stage} isLocked={project.is_locked} />

            {/* Role-Specific Drafter Banner */}
            {isDraftPersonUser && (
                <div className="rounded-3xl border border-indigo-200/80 bg-gradient-to-r from-indigo-950/70 via-slate-900 to-indigo-950/60 p-6 shadow-xl shadow-indigo-950/20 backdrop-blur-md dark:border-indigo-800/60">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600/30 text-2xl text-indigo-400 border border-indigo-500/30 shadow-inner">
                                📐
                            </div>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-semibold text-indigo-300 border border-indigo-500/30">
                                        Drafting Person Workspace
                                    </span>
                                    <StatusBadge value={project.current_stage || "drafting_in_progress"} />
                                </div>
                                <h2 className="mt-1.5 text-xl font-bold text-white">
                                    {project.name} CAD & Map Drafting
                                </h2>
                                <p className="text-xs text-slate-300">
                                    {project.project_code} • Review survey field data, draft site layouts, upload revisions, and submit for drawing approval.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowDirectDraftUpload(true);
                                    const el = document.getElementById("drafting-jobs-section");
                                    el?.scrollIntoView({ behavior: "smooth" });
                                }}
                                className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-indigo-500 transition"
                            >
                                <span>⬆ Upload CAD Revision</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    const el = document.getElementById("survey-reference-section");
                                    el?.scrollIntoView({ behavior: "smooth" });
                                }}
                                className="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-xs font-semibold text-slate-200 border border-slate-700 hover:bg-slate-700 transition"
                            >
                                <span>🔍 Survey References</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Metric Stat Cards */}
            {isDraftPersonUser ? (
                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <StatCard label="Drafting Jobs" value={metrics.draftingJobs} />
                    <StatCard label="Approvals" value={metrics.approvals} />
                    <StatCard label="Survey Submissions" value={metrics.submissions} />
                    <StatCard label="Survey Plans" value={metrics.surveyPlans} />
                    <StatCard label="Execution Tasks" value={metrics.executionTasks} />
                    <StatCard label="Team Members" value={metrics.teamMembers} />
                </div>
            ) : (
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
            )}

            {/* Workflow Stage Management Panels */}
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse" />
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                            {isDraftPersonUser ? "Drafting Stage Workflow" : "5-Stage Complete Workflow Management"}
                        </h3>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-semibold">
                        {!isDraftPersonUser && (
                            <button
                                type="button"
                                onClick={() => setActiveWorkflowTab("supervisor")}
                                className={`px-3 py-1.5 rounded-lg transition-all ${activeWorkflowTab === "supervisor"
                                    ? "bg-indigo-600 text-white shadow-sm"
                                    : "text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
                                    }`}
                            >
                                Supervisor Review
                            </button>
                        )}
                        {!isDraftPersonUser && (
                            <button
                                type="button"
                                onClick={() => setActiveWorkflowTab("survey")}
                                className={`px-3 py-1.5 rounded-lg transition-all ${activeWorkflowTab === "survey"
                                    ? "bg-sky-600 text-white shadow-sm"
                                    : "text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
                                    }`}
                            >
                                Stage 1: Survey
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setActiveWorkflowTab("drafting")}
                            className={`px-3 py-1.5 rounded-lg transition-all ${activeWorkflowTab === "drafting"
                                ? "bg-indigo-600 text-white shadow-sm"
                                : "text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
                                }`}
                        >
                            Stage 2: Drafting
                        </button>
                        {!isDraftPersonUser && (
                            <button
                                type="button"
                                onClick={() => setActiveWorkflowTab("accounts")}
                                className={`px-3 py-1.5 rounded-lg transition-all ${activeWorkflowTab === "accounts"
                                    ? "bg-amber-600 text-white shadow-sm"
                                    : "text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
                                    }`}
                            >
                                Stage 3: Accounts
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setActiveWorkflowTab("audit")}
                            className={`px-3 py-1.5 rounded-lg transition-all ${activeWorkflowTab === "audit"
                                ? "bg-slate-700 text-white shadow-sm"
                                : "text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
                                }`}
                        >
                            Audit Trail Logs
                        </button>
                    </div>
                </div>

                {activeWorkflowTab === "supervisor" && !isDraftPersonUser && (
                    <SupervisorSingleScreenReview
                        project={project}
                        submitRoute="super.construction.projects.workflow.supervisor-review"
                    />
                )}

                {activeWorkflowTab === "survey" && !isDraftPersonUser && (
                    <SurveySubmissionPanel
                        project={project}
                        submitSurveyRoute="super.construction.projects.workflow.submit-survey"
                        isSurveyRole={true}
                    />
                )}

                {activeWorkflowTab === "drafting" && (
                    <DraftingWorkflowPanel
                        project={project}
                        acceptRoute="super.construction.projects.workflow.accept-drafting"
                        submitDraftRoute="super.construction.projects.workflow.submit-draft"
                        verifyRoute="super.construction.projects.workflow.verify-draft"
                        isDraftRole={true}
                    />
                )}

                {activeWorkflowTab === "accounts" && !isDraftPersonUser && (
                    <AccountsVerificationPanel
                        project={project}
                        submitAccountsRoute="super.construction.projects.workflow.submit-accounts"
                        isAccountsRole={true}
                    />
                )}

                {activeWorkflowTab === "audit" && (
                    <WorkflowAuditTrail logs={project.workflow_logs} />
                )}
            </div>

            {/* Drafting Jobs & Drawing Approvals (Prioritized for Draft Persons) */}
            <div id="drafting-jobs-section" className="grid gap-6 xl:grid-cols-[1.4fr,1fr]">
                <SectionCard
                    title="Drafting Jobs & CAD Submissions"
                    description="Approved survey data moves here for CAD work, revisions, and final drawing handoff."
                    actions={
                        <button
                            type="button"
                            onClick={() => setShowDirectDraftUpload((prev) => !prev)}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 transition"
                        >
                            <span>{showDirectDraftUpload ? "✕ Close Form" : "+ Upload CAD Revision"}</span>
                        </button>
                    }
                >
                    {/* Direct CAD Upload Form */}
                    {showDirectDraftUpload && (
                        <form
                            onSubmit={handleDirectDraftSubmit}
                            className="mb-6 rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/60 via-white to-slate-50 p-5 shadow-sm dark:border-indigo-900/60 dark:from-slate-900 dark:to-slate-950 space-y-4"
                        >
                            <div className="flex items-center justify-between border-b border-indigo-100 dark:border-slate-800 pb-3">
                                <div>
                                    <h4 className="text-sm font-bold text-slate-900 dark:text-white">
                                        Upload CAD Drawing Revision
                                    </h4>
                                    <p className="text-xs text-slate-500">
                                        Upload DWG drawing file and PDF sheet for supervisor approval.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowDirectDraftUpload(false)}
                                    className="rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                >
                                    ✕
                                </button>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Drawing Type *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={directDraftForm.data.drawing_type}
                                        onChange={(e) => directDraftForm.setData("drawing_type", e.target.value)}
                                        placeholder="e.g. 2D Floor Layout Plan, Contour Map"
                                        className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Drawing Number *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={directDraftForm.data.drawing_no}
                                        onChange={(e) => directDraftForm.setData("drawing_no", e.target.value)}
                                        placeholder="e.g. DWG-PRJ-00003-01"
                                        className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Revision Number
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={directDraftForm.data.revision_no}
                                        onChange={(e) => directDraftForm.setData("revision_no", e.target.value)}
                                        className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Changes Made in this Revision
                                    </label>
                                    <input
                                        type="text"
                                        value={directDraftForm.data.changes_made}
                                        onChange={(e) => directDraftForm.setData("changes_made", e.target.value)}
                                        placeholder="Summary of modifications or feedback addressed..."
                                        className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        CAD DWG / DXF File
                                    </label>
                                    <input
                                        type="file"
                                        accept=".dwg,.dxf,.zip"
                                        onChange={(e) => directDraftForm.setData("dwg_file", e.target.files[0])}
                                        className="w-full rounded-xl border border-slate-300 p-1.5 text-xs text-slate-700 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        PDF Drawing / Sheet
                                    </label>
                                    <input
                                        type="file"
                                        accept=".pdf"
                                        onChange={(e) => directDraftForm.setData("pdf_file", e.target.files[0])}
                                        className="w-full rounded-xl border border-slate-300 p-1.5 text-xs text-slate-700 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Notes & Scale Details
                                </label>
                                <textarea
                                    rows={2}
                                    value={directDraftForm.data.notes}
                                    onChange={(e) => directDraftForm.setData("notes", e.target.value)}
                                    placeholder="Scale ratio, coordinate system, layers, draftsman remarks..."
                                    className="w-full rounded-xl border border-slate-300 p-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowDirectDraftUpload(false)}
                                    className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={directDraftForm.processing}
                                    className="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
                                >
                                    {directDraftForm.processing ? "Uploading Drawing..." : "Submit Drawing for Approval"}
                                </button>
                            </div>
                        </form>
                    )}

                    {project.drafting_jobs?.length ? (
                        <div className="space-y-4">
                            {project.drafting_jobs.map((job) => (
                                <div key={job.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">Drafting Job #{job.id}</p>
                                            <p className="text-sm text-slate-500">
                                                Assigned to {job.assigned_to?.name || "Unassigned"} • Due {formatDate(job.due_date) || "Not set"}
                                            </p>
                                        </div>
                                        <StatusBadge value={job.status} />
                                    </div>
                                    {(job.khasra_number || project.khasra_number || job.site_area || project.site_area || job.scope_of_work || project.scope_of_work) && (
                                        <div className="mt-2.5 flex flex-wrap items-center gap-2 text-xs">
                                            {(job.khasra_number || project.khasra_number) && (
                                                <span className="rounded-md bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                                    <strong>Khasra:</strong> {job.khasra_number || project.khasra_number}
                                                </span>
                                            )}
                                            {(job.site_area || project.site_area) && (
                                                <span className="rounded-md bg-amber-50 px-2.5 py-1 font-medium text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                                    <strong>Area:</strong> {job.site_area || project.site_area}
                                                </span>
                                            )}
                                            {(job.scope_of_work || project.scope_of_work) && (
                                                <span className="rounded-md bg-slate-100 px-2.5 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                    <strong>Scope:</strong> {job.scope_of_work || project.scope_of_work}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                    <div className="mt-4 space-y-3">
                                        {(job.drawing_revisions || []).length ? (
                                            job.drawing_revisions.map((revision) => (
                                                <div key={revision.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-bold text-slate-900 dark:text-white">
                                                                    Revision {revision.revision_no}
                                                                </span>
                                                                {revision.drawing_type && (
                                                                    <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                                        {revision.drawing_type}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <p className="text-xs text-slate-500 mt-1">
                                                                Uploaded by {revision.uploaded_by?.name || "Unknown"} • {formatDateTime(revision.uploaded_at) || "No upload time"}
                                                            </p>
                                                        </div>
                                                        <StatusBadge value={revision.status} />
                                                    </div>

                                                    <div className="mt-3 flex flex-wrap items-center gap-3 text-xs">
                                                        {revision.dwg_document ? (
                                                            <a
                                                                href={route("super.construction.documents.download", revision.dwg_document.id)}
                                                                className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-3 py-1.5 font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900 transition"
                                                            >
                                                                <span>💾 DWG: {revision.dwg_document.original_name}</span>
                                                            </a>
                                                        ) : (
                                                            <span className="text-slate-400">DWG: Not uploaded</span>
                                                        )}
                                                        {revision.pdf_document ? (
                                                            <a
                                                                href={route("super.construction.documents.download", revision.pdf_document.id)}
                                                                className="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-1.5 font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300 dark:hover:bg-rose-900 transition"
                                                            >
                                                                <span>📄 PDF: {revision.pdf_document.original_name}</span>
                                                            </a>
                                                        ) : (
                                                            <span className="text-slate-400">PDF: Not uploaded</span>
                                                        )}
                                                    </div>

                                                    {revision.changes_made && (
                                                        <p className="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                                            <span className="font-semibold text-slate-700 dark:text-slate-200">Changes:</span> {revision.changes_made}
                                                        </p>
                                                    )}
                                                    {revision.notes && (
                                                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                            <span className="font-semibold">Notes:</span> {revision.notes}
                                                        </p>
                                                    )}
                                                </div>
                                            ))
                                        ) : (
                                            <EmptyState title="No revisions uploaded yet." description="Click '+ Upload CAD Revision' above to upload your first drawing." />
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drafting jobs yet." description="Click '+ Upload CAD Revision' above to create and upload the initial drawing revision." />
                    )}
                </SectionCard>

                <SectionCard title="Drawing Approvals" description="Final Phase 2 gate before the project becomes ready for construction.">
                    {project.drawing_approvals?.length ? (
                        <div className="space-y-3">
                            {project.drawing_approvals.map((approval) => (
                                <div key={approval.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">Approval #{approval.id}</p>
                                            <p className="text-xs text-slate-500">Revision #{approval.drawing_revision_id}</p>
                                        </div>
                                        <StatusBadge value={approval.decision} />
                                    </div>
                                    {approval.remarks && (
                                        <div className="mt-2 rounded-xl bg-amber-50/70 p-3 text-xs text-amber-900 dark:bg-amber-950/20 dark:text-amber-200">
                                            <p className="font-semibold">Reviewer Feedback:</p>
                                            <p className="mt-0.5 whitespace-pre-wrap">{approval.remarks}</p>
                                        </div>
                                    )}
                                    <p className="mt-2 text-[11px] text-slate-400">
                                        Requested {formatDateTime(approval.requested_at) || formatDate(approval.created_at)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drawing approvals yet." description="A submitted drawing revision will automatically generate an approval record here." />
                    )}
                </SectionCard>
            </div>

            {/* Survey References */}
            <div id="survey-reference-section" className="grid gap-6 xl:grid-cols-3">
                <SectionCard title="Survey Plans" description="Survey planning, assigned members, and submitted field-duty payloads." className="xl:col-span-2">
                    {project.survey_plans?.length ? (
                        <div className="space-y-4">
                            {project.survey_plans.map((plan) => (
                                <SurveyPlanCard key={plan.id} plan={plan} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey plans yet." description="Create a survey plan once budget approval and team assignment are complete." />
                    )}
                </SectionCard>

                <SectionCard title="Survey Submission Queue" description="Review outcomes with full GPS/photos/report references before drafting starts.">
                    {project.survey_submissions?.length ? (
                        <div className="space-y-3 max-h-[820px] overflow-y-auto pr-1">
                            {project.survey_submissions.map((submission) => (
                                <div key={submission.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-medium text-slate-900 dark:text-white">Visit #{submission.survey_visit_id}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Submitted by{" "}
                                                <span className="font-medium text-slate-700 dark:text-slate-200">{submission.submitted_by?.name || "Unknown"}</span>
                                                {" • "}
                                                {formatDateTime(submission.submitted_at) || "No timestamp"}
                                            </p>
                                        </div>
                                        <StatusBadge value={submission.status_key} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Reviewed by{" "}
                                        {submission.reviewed_by?.name
                                            ? submission.reviewed_by.name
                                            : submission.reviewed_at
                                                ? "authorized reviewer"
                                                : "Pending review"}
                                    </p>
                                    <p className="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600 dark:text-slate-300">
                                        {submission.review_notes || "No review notes yet."}
                                    </p>
                                    {submission.survey_visit ? (
                                        <div className="mt-4 grid gap-2 border-t border-slate-200 pt-3 text-xs sm:grid-cols-2 dark:border-slate-700">
                                            <SubMetric label="Points" value={submission.survey_visit.total_points_captured} />
                                            <SubMetric label="Distance (m)" value={formatNumber(submission.survey_visit.distance_covered_m)} />
                                            <SubMetric label="Elevation (m)" value={formatNumber(submission.survey_visit.elevation_m)} />
                                            <SubMetric label="Day" value={submission.survey_visit.day_number} />
                                            <SubMetric label="Latitude" value={submission.survey_visit.check_in_latitude ? Number(submission.survey_visit.check_in_latitude).toFixed(6) : undefined} />
                                            <SubMetric label="Longitude" value={submission.survey_visit.check_in_longitude ? Number(submission.survey_visit.check_in_longitude).toFixed(6) : undefined} />
                                            <FileAttachment path={submission.survey_visit.file_path} span="sm:col-span-2" />
                                        </div>
                                    ) : null}
                                    {submission.survey_visit?.photos?.length ? (
                                        <PhotoGrid photos={submission.survey_visit.photos} className="mt-3" />
                                    ) : null}
                                    {submission.survey_visit?.remarks || submission.survey_visit?.notes ? (
                                        <div className="mt-3 space-y-2">
                                            {submission.survey_visit.remarks ? (
                                                <div className="rounded-xl bg-amber-50/70 p-3 text-xs text-amber-900 dark:bg-amber-950/20 dark:text-amber-100">
                                                    <p className="font-semibold">Remarks</p>
                                                    <p className="mt-1 whitespace-pre-wrap break-words">{submission.survey_visit.remarks}</p>
                                                </div>
                                            ) : null}
                                            {submission.survey_visit.notes ? (
                                                <FieldNotesDisplay notes={submission.survey_visit.notes} />
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey submissions yet." description="Submitted field-duty reports render here with full GPS/photos/report payloads without manual frontend changes." />
                    )}
                </SectionCard>
            </div>

            {/* Team Assignment (Admin Only) & Assigned Team */}
            {canManageTeam ? (
                <div className="grid gap-6 xl:grid-cols-2">
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
                                        ...sortedMembers.map((member) => {
                                            const badge = member.role_badge ? ` (${member.role_badge})` : (member.designation_text ? ` (${member.designation_text})` : "");
                                            return {
                                                value: member.id,
                                                label: `${member.name}${badge}${member.email ? ` • ${member.email}` : ""}`,
                                            };
                                        }),
                                    ]}
                                />
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
                </div>
            ) : (
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
                        <EmptyState title="No team assigned yet." description="Team members assigned to this project will appear here." />
                    )}
                </SectionCard>
            )}

            {/* Activity Log - Responsive Full Width */}
            <ProjectActivityLog activityLog={activityLog} />

            <SectionCard
                title="Execution Tasks"
                description={`${taskCounts.total} total tasks · ${taskCounts.completed} complete · ${taskCounts.in_progress} in-progress · ${taskCounts.pending} pending`}
                actions={
                    <button
                        type="button"
                        onClick={() => setShowCreateTask((prev) => !prev)}
                        className={`inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs sm:text-sm font-semibold shadow-xs transition ${showCreateTask
                            ? "bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200"
                            : "bg-indigo-600 text-white hover:bg-indigo-500"
                            }`}
                    >
                        <span className="text-base font-bold leading-none">{showCreateTask ? "✕" : "+"}</span>
                        <span>{showCreateTask ? "Close Task Form" : "New Task"}</span>
                    </button>
                }
            >
                {showCreateTask && (
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
                                    setShowCreateTask(false);
                                    setShowAdvancedTaskFields(false);
                                    toast.success("Execution task created successfully.");
                                },
                                onError: (errors) => {
                                    const firstError = Object.values(errors)[0];
                                    if (firstError) toast.error(firstError);
                                },
                            });
                        }}
                        className="mb-6 rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50/50 via-white to-slate-50 p-5 shadow-xs dark:border-indigo-950 dark:from-slate-900 dark:to-slate-950"
                    >
                        <div className="flex items-center justify-between border-b border-indigo-100 pb-3 dark:border-slate-800">
                            <div>
                                <p className="text-sm font-bold text-slate-900 dark:text-white">Register New Execution Task</p>
                                <p className="text-xs text-slate-500">Provide the essential task details. You can attach checklists immediately after creating.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowCreateTask(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Core Fields */}
                        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="sm:col-span-2">
                                <InputField form={taskForm} name="title" label="Task Title *" placeholder="e.g. Survey site perimeter lines" required />
                            </div>
                            <SelectField
                                form={taskForm}
                                name="supervisor_member_id"
                                label="Supervisor"
                                options={[
                                    { value: "", label: "-- Optional supervisor --" },
                                    ...members.map((m) => {
                                        const badge = m.role_badge ? ` (${m.role_badge})` : (m.designation_text ? ` (${m.designation_text})` : "");
                                        return { value: m.id, label: `${m.name}${badge}` };
                                    }),
                                ]}
                            />
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
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <InputField form={taskForm} name="planned_start_date" label="Planned Start Date" type="date" />
                            <InputField form={taskForm} name="planned_end_date" label="Planned End Date" type="date" />
                            <div className="sm:col-span-2 flex items-center pt-5">
                                <button
                                    type="button"
                                    onClick={() => setShowAdvancedTaskFields((prev) => !prev)}
                                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    <span>{showAdvancedTaskFields ? "▾ Hide additional fields" : "▸ Add more details (Quantity, Status, GPS Verification, Description)"}</span>
                                </button>
                            </div>
                        </div>

                        {/* Collapsible Advanced Fields */}
                        {showAdvancedTaskFields && (
                            <div className="mt-4 space-y-4 rounded-xl border border-slate-200/80 bg-white/70 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <SelectField
                                        form={taskForm}
                                        name="status"
                                        label="Initial Status"
                                        options={[
                                            { value: "draft", label: "Draft" },
                                            { value: "planned", label: "Planned" },
                                            { value: "in_progress", label: "In Progress" },
                                            { value: "completed", label: "Completed" },
                                            { value: "blocked", label: "Blocked" },
                                        ]}
                                    />
                                    <InputField form={taskForm} name="planned_quantity" label="Planned Quantity" type="number" />
                                    <InputField form={taskForm} name="unit" label="Unit" placeholder="sqm / nos / m" />
                                    <div className="flex flex-col justify-center gap-2 pt-2">
                                        <label className="flex items-center gap-2.5 text-xs text-slate-700 dark:text-slate-200 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={taskForm.data.requires_daily_update}
                                                onChange={(e) => taskForm.setData("requires_daily_update", e.target.checked)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            Requires Daily Update
                                        </label>
                                        <label className="flex items-center gap-2.5 text-xs text-slate-700 dark:text-slate-200 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={taskForm.data.requires_gps_verification}
                                                onChange={(e) => taskForm.setData("requires_gps_verification", e.target.checked)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            GPS Verification
                                        </label>
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <InputField form={taskForm} name="actual_start_date" label="Actual Start (Optional)" type="date" />
                                    <InputField form={taskForm} name="actual_end_date" label="Actual End (Optional)" type="date" />
                                </div>
                                <TextAreaField form={taskForm} name="description" label="Task Description / Notes" rows={2} placeholder="Optional scope notes, equipment requirements..." />
                            </div>
                        )}

                        <div className="mt-4 flex items-center justify-end gap-3 border-t border-indigo-100/80 pt-3 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowCreateTask(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={taskForm.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {taskForm.processing ? "Creating Task..." : "Create Execution Task"}
                            </button>
                        </div>
                    </form>
                )}

                <div>
                    {mergedTaskList.length ? (
                        <div className="space-y-3">
                            {mergedTaskList.map((task) => (
                                <TaskRow
                                    key={task._sourceKey}
                                    task={task}
                                    project={project}
                                    members={members}
                                    onEdit={() => setEditingTask(task)}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-800">
                            <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-2xl text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                🏗️
                            </span>
                            <h3 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">No execution tasks yet</h3>
                            <p className="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                                Create your first task to assign supervisors, track dates, and attach checklists.
                            </p>
                            <button
                                type="button"
                                onClick={() => setShowCreateTask(true)}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500"
                            >
                                <span className="font-bold">+</span>
                                <span>Create First Task</span>
                            </button>
                        </div>
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
                                    route("super.construction.projects.tasks-v2.update", [project.id, editingTask.id]),
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
                                        ...members.map((m) => {
                                            const badge = m.role_badge ? ` (${m.role_badge})` : (m.designation_text ? ` (${m.designation_text})` : "");
                                            return { value: m.id, label: `${m.name}${badge}` };
                                        }),
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

function TaskRow({ task, project, members, onEdit }) {
    const { auth } = usePage().props;
    const currentUser = auth?.user;
    const activeRoleSlug = String(auth?.active_role?.slug || auth?.active_role || "").toLowerCase();

    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [showChecklists, setShowChecklists] = useState(false);
    const [showAddChecklistModal, setShowAddChecklistModal] = useState(false);
    const [previewImage, setPreviewImage] = useState(null);
    const [showDetailsModal, setShowDetailsModal] = useState(false);
    const [showTransferModal, setShowTransferModal] = useState(false);
    const [showRevisionInput, setShowRevisionInput] = useState(false);
    const [revisionRemarks, setRevisionRemarks] = useState("");
    const [isSubmittingReview, setIsSubmittingReview] = useState(false);
    const [showDraftUploadSection, setShowDraftUploadSection] = useState(false);
    const [isSubmittingDraftReview, setIsSubmittingDraftReview] = useState(false);

    const taskVisits = useMemo(() => {
        const list = [...(task.survey_visits || task.surveyVisits || [])];
        if (list.length === 0 && Array.isArray(project.survey_visits)) {
            project.survey_visits.forEach((v) => {
                if (Number(v.task_id) === Number(task.id)) {
                    list.push(v);
                }
            });
        }
        return list;
    }, [task, project]);

    const projectTeamMembers = useMemo(() => {
        const list = project.team_members || project.teamMembers || [];
        return list.map((tm) => {
            const m = tm.member || {};
            const r = tm.role || {};
            const roleName = r.name || tm.assignment_scope || tm.project_role || "Team Member";
            const isDraftPerson = /draft|cad|drafter|draftsman/i.test(roleName) || /draft|cad|drafter|draftsman/i.test(m.designation || "");
            return {
                id: tm.id,
                member_id: tm.member_id || m.id,
                name: m.name || `Member #${tm.member_id}`,
                email: m.email || "",
                phone: m.phone || "",
                role_name: roleName,
                is_draft_person: isDraftPerson,
                designation: m.designation || "",
                is_primary: tm.is_primary,
            };
        }).filter((tm) => tm.member_id);
    }, [project]);

    // Role and Drafting Job Resolution
    const isDraftPersonUser = useMemo(() => {
        if (activeRoleSlug === "draft_person" || activeRoleSlug === "drafter" || activeRoleSlug === "draftsman") return true;
        if (Array.isArray(currentUser?.assigned_roles) && currentUser.assigned_roles.includes("draft_person")) return true;
        const team = project?.team_members || project?.teamMembers || [];
        if (currentUser?.id && team.some((tm) => Number(tm.member_id) === Number(currentUser.id) && (/draft|cad|drafter/i.test(tm.role?.name || tm.assignment_scope || tm.project_role || tm.member?.designation || "")))) return true;
        if (currentUser?.id && (project?.drafting_jobs || []).some((dj) => Number(dj.assigned_to_member_id) === Number(currentUser.id))) return true;
        return false;
    }, [activeRoleSlug, currentUser, project]);

    const taskDraftingJob = useMemo(() => {
        const jobs = project.drafting_jobs || project.draftingJobs || [];
        const bySub = jobs.find((job) => {
            const sub = job.survey_submission || job.surveySubmission;
            if (!sub) return false;
            if (Number(sub.task_id) === Number(task.id) || Number(sub.execution_task_id) === Number(task.id)) {
                return true;
            }
            if (taskVisits.some((v) => Number(v.id) === Number(sub.survey_visit_id))) {
                return true;
            }
            return false;
        });
        if (bySub) return bySub;

        const isDraftStatus = task.status === "drafting_in_progress" || (task.assignees || []).some((a) => a.assignment_role === "draft_person");
        if (isDraftStatus && jobs.length > 0) {
            const taskSupervisorId = task.supervisor_member_id || task.supervisor?.id || task.member_id;
            const matchingJob = jobs.find((j) => Number(j.assigned_to_member_id) === Number(taskSupervisorId));
            return matchingJob || jobs[0];
        }
        return null;
    }, [project.drafting_jobs, project.draftingJobs, task, taskVisits]);

    const isSurveyApproved = useMemo(() => {
        if (task.status === "survey_approved" || task.status === "drafting_in_progress" || task.status === "completed") {
            return true;
        }
        if (taskDraftingJob) return true;
        const hasApprovedVisit = taskVisits.some((v) => {
            const sub = v.submission;
            return v.status === 2 || v.status === "approved" || (sub && (sub.status === 2 || sub.status_key === "approved"));
        });
        if (hasApprovedVisit) return true;
        const submissions = project.survey_submissions || project.surveySubmissions || [];
        return submissions.some((s) => {
            const matches = Number(s.task_id) === Number(task.id) || Number(s.execution_task_id) === Number(task.id) || taskVisits.some((v) => Number(v.id) === Number(s.survey_visit_id));
            return matches && (s.status === 2 || s.status_key === "approved");
        });
    }, [task.status, taskDraftingJob, taskVisits, project.survey_submissions, project.surveySubmissions, task.id]);

    const isDraftingAssigned = useMemo(() => {
        if (Boolean(taskDraftingJob)) return true;
        if (task.status === "drafting_in_progress") return true;
        return (task.assignees || []).some((a) => a.assignment_role === "draft_person")
            || (task.assignments || []).some((a) => a.assignment_role === "draft_person");
    }, [taskDraftingJob, task.status, task.assignees, task.assignments]);

    const assignedDraftPerson = useMemo(() => {
        if (taskDraftingJob?.assignedTo || taskDraftingJob?.assigned_to) {
            return taskDraftingJob.assignedTo || taskDraftingJob.assigned_to;
        }
        const draftAssignee = (task.assignees || []).find((a) => a.assignment_role === "draft_person");
        if (draftAssignee?.member) return draftAssignee.member;
        const draftAss = (task.assignments || []).find((a) => a.assignment_role === "draft_person");
        if (draftAss?.assignee) return draftAss.assignee;
        const supId = task.supervisor_member_id || task.supervisor?.id;
        if (supId) {
            const tm = projectTeamMembers.find((m) => Number(m.member_id) === Number(supId) && m.is_draft_person);
            if (tm) return tm;
        }
        return null;
    }, [taskDraftingJob, task, projectTeamMembers]);

    const isCurrentUserDraftPerson = useMemo(() => {
        if (!currentUser?.id) return false;
        if (taskDraftingJob && Number(taskDraftingJob.assigned_to_member_id) === Number(currentUser.id)) return true;
        if (assignedDraftPerson && Number(assignedDraftPerson.id || assignedDraftPerson.member_id) === Number(currentUser.id)) return true;
        if (isDraftPersonUser) return true;
        return false;
    }, [currentUser, taskDraftingJob, assignedDraftPerson, isDraftPersonUser]);

    const drawingRevisions = useMemo(() => {
        return taskDraftingJob?.drawing_revisions || taskDraftingJob?.drawingRevisions || [];
    }, [taskDraftingJob]);

    const latestRevision = drawingRevisions.length ? drawingRevisions[drawingRevisions.length - 1] : null;
    const isDrawingApproved = latestRevision?.status === "approved" || taskDraftingJob?.status === "approved";
    const isDrawingSubmitted = latestRevision?.status === "submitted" || taskDraftingJob?.status === "submitted";
    const isDrawingRevisionRequested = latestRevision?.status === "revision_requested" || taskDraftingJob?.status === "revision_requested";

    // Form for CAD Drawing Submissions
    const draftUploadForm = useForm({
        drafting_job_id: taskDraftingJob?.id || "",
        drawing_title: `${task.task_code || "TSK"} - CAD Plan`,
        drawing_no: `DWG-${task.task_code || task.id}`,
        drawing_type: "topographic",
        revision_no: drawingRevisions.length + 1,
        changes_made: "",
        notes: "",
        dwg_file: null,
        pdf_file: null,
    });

    useEffect(() => {
        if (taskDraftingJob?.id) {
            draftUploadForm.setData({
                ...draftUploadForm.data,
                drafting_job_id: taskDraftingJob.id,
                revision_no: drawingRevisions.length + 1,
            });
        }
    }, [taskDraftingJob?.id, drawingRevisions.length]);

    const handleDraftSubmit = (e) => {
        e.preventDefault();
        if (!draftUploadForm.data.dwg_file && !draftUploadForm.data.pdf_file) {
            toast.error("Please upload at least a CAD (.DWG/.DXF) or PDF Drawing file.");
            return;
        }
        draftUploadForm.post(route("super.construction.projects.tasks-v2.submit-draft", project.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDraftUploadSection(false);
                toast.success("CAD Drawing revision submitted successfully! Awaiting review.");
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
                else toast.error("Failed to submit CAD drawing.");
            },
        });
    };

    const handleReviewDraftingJob = (decision) => {
        if (!taskDraftingJob) return;
        setIsSubmittingDraftReview(true);
        router.post(
            route("super.construction.projects.drafting-jobs.review", [project.id, taskDraftingJob.id]),
            {
                decision,
                remarks: revisionRemarks.trim() || undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSubmittingDraftReview(false),
                onSuccess: () => {
                    setShowDetailsModal(false);
                    setRevisionRemarks("");
                    toast.success(
                        decision === "approved"
                            ? "CAD Drawing approved! Project stage moved to Ready for Construction Execution."
                            : "Revision requested from Draft Person."
                    );
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to update drafting review.");
                },
            }
        );
    };

    const defaultDraftMember = projectTeamMembers.find((tm) => tm.is_draft_person) || projectTeamMembers[0];
    const transferForm = useForm({
        member_id: defaultDraftMember?.member_id || "",
        assignment_role: defaultDraftMember?.is_draft_person ? "draft_person" : "executor",
        due_date: task.planned_end_date ? String(task.planned_end_date).split("T")[0] : "",
        notes: "",
        create_drafting_job: defaultDraftMember?.is_draft_person ? true : false,
    });

    useEffect(() => {
        if (showTransferModal) {
            const draftMem = projectTeamMembers.find((tm) => tm.is_draft_person) || projectTeamMembers[0];
            transferForm.setData({
                member_id: draftMem?.member_id || "",
                assignment_role: draftMem?.is_draft_person ? "draft_person" : "executor",
                due_date: task.planned_end_date ? String(task.planned_end_date).split("T")[0] : "",
                notes: "",
                create_drafting_job: draftMem?.is_draft_person ? true : false,
            });
        }
    }, [showTransferModal]);

    const handleTransferSubmit = (e) => {
        e.preventDefault();
        if (!transferForm.data.member_id) {
            toast.error("Please select a project team member.");
            return;
        }
        transferForm.post(route("super.construction.projects.tasks-v2.transfer", [project.id, task.id]), {
            preserveScroll: true,
            onSuccess: () => {
                setShowTransferModal(false);
                toast.success("Task assigned / transferred successfully.");
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
                else toast.error("Failed to transfer task.");
            },
        });
    };

    const handleReviewSurvey = (decision) => {
        if (decision === "revision_requested" && !revisionRemarks.trim()) {
            toast.error("Please provide revision remarks describing what needs to be updated.");
            return;
        }
        setIsSubmittingReview(true);
        router.post(
            route("super.construction.projects.tasks-v2.review-survey", [project.id, task.id]),
            {
                decision,
                remarks: revisionRemarks.trim() || undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSubmittingReview(false),
                onSuccess: () => {
                    setShowDetailsModal(false);
                    setShowRevisionInput(false);
                    setRevisionRemarks("");
                    toast.success(decision === "approved" ? "Survey task approved!" : "Revision requested from surveyor.");
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to update survey review.");
                },
            }
        );
    };

    // Quick inline add state
    const [quickTitle, setQuickTitle] = useState("");
    const [quickDay, setQuickDay] = useState(1);
    const [isSubmittingQuick, setIsSubmittingQuick] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    // Detailed add modal form
    const addChecklistForm = useForm({
        scope: task._source === "unified" ? "unified_task" : "execution_task",
        execution_task_id: task._source !== "unified" ? task.id : "",
        unified_task_id: task._source === "unified" ? task.id : "",
        survey_plan_id: "",
        day_number: 1,
        item_title: "",
        assign_hours: "",
        notes: "",
        status: "pending",
        is_completed: false,
        image_1: null,
        image_2: null,
    });
    const [img1Preview, setImg1Preview] = useState(null);
    const [img2Preview, setImg2Preview] = useState(null);

    const handleDelete = () => {
        router.delete(route("super.construction.projects.tasks-v2.destroy", [project.id, task.id]), {
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

    const handleToggleChecklist = (checklistItem) => {
        setTogglingId(checklistItem.id);
        router.patch(
            route("super.construction.projects.checklists.toggle", [project.id, checklistItem.id]),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to toggle checklist item.");
                },
            }
        );
    };

    const handleDeleteChecklist = (checklistItem) => {
        if (!confirm(`Are you sure you want to delete checklist item "${checklistItem.item_title}"?`)) return;
        router.delete(
            route("super.construction.projects.checklists.destroy", [project.id, checklistItem.id]),
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Checklist item removed."),
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to delete checklist item.");
                },
            }
        );
    };

    const handleQuickAdd = (e) => {
        e.preventDefault();
        if (!quickTitle.trim()) return;
        setIsSubmittingQuick(true);
        router.post(
            route("super.construction.projects.checklists.store", project.id),
            {
                scope: task._source === "unified" ? "unified_task" : "execution_task",
                execution_task_id: task._source !== "unified" ? task.id : "",
                unified_task_id: task._source === "unified" ? task.id : "",
                day_number: Number(quickDay) || 1,
                item_title: quickTitle.trim(),
                status: "pending",
                is_completed: false,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setQuickTitle("");
                    setIsSubmittingQuick(false);
                    setShowChecklists(true);
                    toast.success("Checklist item linked to task.");
                },
                onError: (errors) => {
                    setIsSubmittingQuick(false);
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to add checklist item.");
                },
            }
        );
    };

    const handleDetailedAdd = (e) => {
        e.preventDefault();
        if (!addChecklistForm.data.item_title.trim()) {
            toast.error("Please enter a checklist title.");
            return;
        }
        addChecklistForm.post(
            route("super.construction.projects.checklists.store", project.id),
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    addChecklistForm.reset("item_title", "assign_hours", "notes", "image_1", "image_2");
                    setImg1Preview(null);
                    setImg2Preview(null);
                    setShowAddChecklistModal(false);
                    setShowChecklists(true);
                    toast.success("Checklist item created and linked.");
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to add checklist item.");
                },
            }
        );
    };

    return (
        <>
            <div className="rounded-2xl border border-slate-200/90 bg-white p-4.5 sm:p-5 shadow-xs transition hover:border-slate-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-700">
                {/* 1. Header: Task Code, Title, Badges & Administrative Actions */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-2 min-w-0">
                        <span className="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 font-mono text-[11px] font-bold text-indigo-700 border border-indigo-100 dark:bg-indigo-950/60 dark:border-indigo-900/60 dark:text-indigo-300">
                            {task.task_code}
                        </span>
                        <h4 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white tracking-tight">
                            {task.title}
                        </h4>
                        <StatusBadge value={task.status} />
                        <StatusBadge value={task.priority} />
                    </div>

                    <div className="flex items-center gap-1.5 shrink-0">
                        <button
                            type="button"
                            onClick={onEdit}
                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            title="Edit Task Details"
                        >
                            <svg className="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            <span>Edit</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setConfirmingDelete(true)}
                            className="inline-flex items-center gap-1 rounded-lg border border-rose-200/80 bg-rose-50/60 px-2.5 py-1 text-xs font-medium text-rose-600 shadow-2xs transition hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-400"
                            title="Delete Task"
                        >
                            <svg className="h-3.5 w-3.5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Delete</span>
                        </button>
                    </div>
                </div>

                {task.description ? (
                    <p className="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        {task.description}
                    </p>
                ) : null}

                {/* 2. Middle Grid: Key Metadata & Status Spanning Full Width */}
                <div className="mt-3.5 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800/80 dark:bg-slate-900/40">
                    <div className="flex flex-col min-w-0">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            Planned Schedule
                        </span>
                        <span className="mt-1 flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-200">
                            <svg className="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span className="truncate">
                                {formatDate(task.planned_start_date) || "TBD"} → {formatDate(task.planned_end_date) || "TBD"}
                            </span>
                        </span>
                    </div>

                    <div className="flex flex-col min-w-0">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {isDraftingAssigned ? "Draft Person" : "Supervisor"}
                        </span>
                        <span className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                {((assignedDraftPerson?.name || task.supervisor?.name || "U")[0] || "U").toUpperCase()}
                            </span>
                            <span className="truncate">{assignedDraftPerson?.name || task.supervisor?.name || "Unassigned"}</span>
                            {isDraftingAssigned && (
                                <span className="ml-1 rounded bg-violet-100 px-1 py-0.2 text-[9px] font-bold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                    CAD
                                </span>
                            )}
                        </span>
                    </div>

                    <div className="flex flex-col min-w-0">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            Checklists
                        </span>
                        <span className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <svg className="h-3.5 w-3.5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{completedChecklists} of {taskChecklists.length} done</span>
                        </span>
                    </div>

                    <div className="flex flex-col justify-center min-w-0">
                        <div className="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            <span>Progress</span>
                            <span className="font-mono text-xs font-bold text-slate-700 dark:text-slate-200">{progress}%</span>
                        </div>
                        <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                            <div
                                className={`h-full rounded-full transition-all duration-500 ${progress === 100
                                    ? "bg-emerald-500"
                                    : "bg-gradient-to-r from-indigo-500 to-sky-500"
                                    }`}
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* 3. Bottom Action Bar: Checklist actions & Primary workflow actions */}
                <div className="mt-3.5 flex flex-wrap items-center justify-between gap-2.5 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setShowChecklists((prev) => !prev)}
                            className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-2xs transition ${showChecklists
                                ? "border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300"
                                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                }`}
                        >
                            <svg className="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <span>Checklists ({completedChecklists}/{taskChecklists.length})</span>
                            <svg
                                className={`h-3 w-3 text-slate-400 transition-transform duration-200 ${showChecklists ? "rotate-180" : ""}`}
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowAddChecklistModal(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-2xs transition hover:bg-emerald-100 hover:border-emerald-300 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300"
                            title="Add a checklist item directly to this task"
                        >
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Add Checklist</span>
                        </button>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setShowDetailsModal(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 shadow-2xs transition hover:bg-sky-100 hover:border-sky-300 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300 dark:hover:bg-sky-900/50"
                            title="View all submitted survey data, coordinates, photos, and attached files"
                        >
                            <svg className="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>View Details</span>
                            {taskVisits.length > 0 && (
                                <span className="rounded-full bg-sky-200/90 px-1.5 py-0.2 text-[10px] font-bold text-sky-800 dark:bg-sky-800 dark:text-sky-200">
                                    {taskVisits.length}
                                </span>
                            )}
                        </button>

                        {isCurrentUserDraftPerson ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setShowDetailsModal(true);
                                    setShowDraftUploadSection(true);
                                }}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:from-emerald-500 hover:to-teal-500"
                                title="Upload and submit CAD drawing for this task"
                            >
                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Submit CAD Drawing</span>
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setShowTransferModal(true)}
                                className={`inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-semibold shadow-xs transition ${
                                    isDraftingAssigned
                                        ? "border border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/60 dark:text-violet-300"
                                        : "bg-gradient-to-r from-violet-600 to-indigo-600 text-white hover:from-violet-500 hover:to-indigo-500"
                                }`}
                                title={isDraftingAssigned ? "Re-assign or transfer this task to another team member" : "Assign or transfer this task to a project team member"}
                            >
                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                <span>{isDraftingAssigned ? "Re-assign / Transfer" : "Assign / Transfer"}</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Inline Checklist Section */}
                {showChecklists && (
                    <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-900/60">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2.5 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    Checklist Items ({completedChecklists}/{taskChecklists.length} completed)
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowAddChecklistModal(true)}
                                className="text-xs font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                            >
                                + Detailed Add with Photos
                            </button>
                        </div>

                        {taskChecklists.length > 0 ? (
                            <div className="mt-3 space-y-2">
                                {taskChecklists.map((item) => (
                                    <div
                                        key={item.id}
                                        className={`flex items-start justify-between gap-3 rounded-lg border p-2.5 text-xs transition ${item.is_completed
                                            ? "border-emerald-200 bg-emerald-50/40 dark:border-emerald-950 dark:bg-emerald-950/20"
                                            : "border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700/80 dark:bg-slate-950"
                                            }`}
                                    >
                                        <div className="flex items-start gap-2.5 min-w-0 flex-1">
                                            <button
                                                type="button"
                                                disabled={togglingId === item.id}
                                                onClick={() => handleToggleChecklist(item)}
                                                className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border transition ${item.is_completed
                                                    ? "border-emerald-600 bg-emerald-600 text-white"
                                                    : "border-slate-300 bg-white hover:border-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                                    }`}
                                                title={item.is_completed ? "Click to reopen" : "Click to mark completed"}
                                            >
                                                {item.is_completed ? (
                                                    <svg className="h-3 w-3 stroke-current stroke-2" viewBox="0 0 24 24" fill="none">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                ) : null}
                                            </button>
                                            <div className="min-w-0 flex-1">
                                                <p
                                                    className={`font-medium ${item.is_completed
                                                        ? "text-slate-500 line-through dark:text-slate-400"
                                                        : "text-slate-800 dark:text-slate-100"
                                                        }`}
                                                >
                                                    {item.item_title}
                                                </p>
                                                <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 font-medium dark:bg-slate-800">
                                                        Day {item.day_number || 1}
                                                    </span>
                                                    {item.assign_hours != null && item.assign_hours !== "" && (
                                                        <span className="rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                                            ⏱ {item.assign_hours} hrs
                                                        </span>
                                                    )}
                                                    {item.notes && (
                                                        <span className="truncate max-w-xs text-slate-500 dark:text-slate-400">
                                                            • {item.notes}
                                                        </span>
                                                    )}
                                                </div>
                                                {(item.image_url_1 || item.image_url_2) && (
                                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                                        {item.image_url_1 && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setPreviewImage(item.image_url_1)}
                                                                className="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700"
                                                            >
                                                                <img
                                                                    src={item.image_url_1}
                                                                    alt="Proof 1"
                                                                    className="h-10 w-10 object-cover hover:opacity-80"
                                                                />
                                                            </button>
                                                        )}
                                                        {item.image_url_2 && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setPreviewImage(item.image_url_2)}
                                                                className="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700"
                                                            >
                                                                <img
                                                                    src={item.image_url_2}
                                                                    alt="Proof 2"
                                                                    className="h-10 w-10 object-cover hover:opacity-80"
                                                                />
                                                            </button>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleDeleteChecklist(item)}
                                            className="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 p-1"
                                            title="Delete checklist item"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="my-3 rounded-lg border border-dashed border-slate-200 bg-white/50 p-3 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/30">
                                No checklists linked to this task yet. Use the quick-add bar below or click '+ Add Checklist' to get started.
                            </div>
                        )}

                        {/* Fast Quick Add Input */}
                        <form onSubmit={handleQuickAdd} className="mt-3 flex flex-wrap sm:flex-nowrap items-center gap-2">
                            <input
                                type="text"
                                value={quickTitle}
                                onChange={(e) => setQuickTitle(e.target.value)}
                                placeholder="Type a checklist item & press Enter (e.g. Inspect boundary benchmarks)..."
                                className="flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                            <div className="flex items-center gap-1.5 shrink-0">
                                <span className="text-[11px] font-medium text-slate-500">Day</span>
                                <input
                                    type="number"
                                    min="1"
                                    max="366"
                                    value={quickDay}
                                    onChange={(e) => setQuickDay(e.target.value)}
                                    className="w-14 rounded-xl border border-slate-300 bg-white px-2 py-2 text-center text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={!quickTitle.trim() || isSubmittingQuick}
                                className="rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 shrink-0"
                            >
                                {isSubmittingQuick ? "Adding..." : "+ Add Item"}
                            </button>
                        </form>
                    </div>
                )}
            </div>

            {/* Detailed Add Checklist Modal */}
            {showAddChecklistModal && (
                <Modal show={true} onClose={() => setShowAddChecklistModal(false)}>
                    <form onSubmit={handleDetailedAdd} className="p-6">
                        <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Add Checklist to Task
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Linking directly to <span className="font-semibold text-indigo-600 dark:text-indigo-400">"{task.title}"</span> ({task.task_code})
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowAddChecklistModal(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    Checklist Title <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={addChecklistForm.data.item_title}
                                    onChange={(e) => addChecklistForm.setData("item_title", e.target.value)}
                                    placeholder="e.g. Conduct elevation survey and record boundary coordinates"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Survey Day #
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="366"
                                        value={addChecklistForm.data.day_number}
                                        onChange={(e) => addChecklistForm.setData("day_number", e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Estimated Hours
                                    </label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        value={addChecklistForm.data.assign_hours}
                                        onChange={(e) => addChecklistForm.setData("assign_hours", e.target.value)}
                                        placeholder="e.g. 2.5"
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Initial Status
                                    </label>
                                    <select
                                        value={addChecklistForm.data.status}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            addChecklistForm.setData({
                                                ...addChecklistForm.data,
                                                status: val,
                                                is_completed: val === "completed",
                                            });
                                        }}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    Notes / Remarks (Optional)
                                </label>
                                <textarea
                                    rows={2}
                                    value={addChecklistForm.data.notes}
                                    onChange={(e) => addChecklistForm.setData("notes", e.target.value)}
                                    placeholder="Add any specific instructions or requirements..."
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    Photo Attachments (Optional, max 2)
                                </label>
                                <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) {
                                                    addChecklistForm.setData("image_1", file);
                                                    setImg1Preview(URL.createObjectURL(file));
                                                }
                                            }}
                                            className="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200"
                                        />
                                        {img1Preview && (
                                            <div className="mt-2 relative inline-block">
                                                <img src={img1Preview} alt="Preview 1" className="h-16 w-16 rounded-lg object-cover border" />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        addChecklistForm.setData("image_1", null);
                                                        setImg1Preview(null);
                                                    }}
                                                    className="absolute -top-1 -right-1 rounded-full bg-rose-500 text-white h-4 w-4 text-[10px] flex items-center justify-center"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) {
                                                    addChecklistForm.setData("image_2", file);
                                                    setImg2Preview(URL.createObjectURL(file));
                                                }
                                            }}
                                            className="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200"
                                        />
                                        {img2Preview && (
                                            <div className="mt-2 relative inline-block">
                                                <img src={img2Preview} alt="Preview 2" className="h-16 w-16 rounded-lg object-cover border" />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        addChecklistForm.setData("image_2", null);
                                                        setImg2Preview(null);
                                                    }}
                                                    className="absolute -top-1 -right-1 rounded-full bg-rose-500 text-white h-4 w-4 text-[10px] flex items-center justify-center"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowAddChecklistModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={addChecklistForm.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {addChecklistForm.processing ? "Adding..." : "Add Checklist Item"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Photo Lightbox Modal */}
            {previewImage && (
                <Modal show={true} onClose={() => setPreviewImage(null)}>
                    <div className="p-4">
                        <div className="flex justify-end pb-2">
                            <button
                                type="button"
                                onClick={() => setPreviewImage(null)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>
                        <img
                            src={previewImage}
                            alt="Checklist Proof"
                            className="max-h-[75vh] w-full rounded-xl object-contain"
                        />
                    </div>
                </Modal>
            )}

            {/* Task Submission Details Modal */}
            {showDetailsModal && (
                <Modal show={true} onClose={() => setShowDetailsModal(false)} maxWidth="5xl">
                    <div className="flex max-h-[88vh] flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {task.task_code}
                                    </span>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        Survey Task Submission Details
                                    </h3>
                                    <StatusBadge value={task.status} />
                                    <StatusBadge value={task.priority} />
                                </div>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {task.title} • Planned: {formatDate(task.planned_start_date) || "TBD"} → {formatDate(task.planned_end_date) || "TBD"}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowDetailsModal(false)}
                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Modal Body - Scrollable */}
                        <div className="flex-1 space-y-6 overflow-y-auto p-6">
                            {/* Task Details Overview Card */}
                            <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-indigo-50/20 p-4.5 shadow-2xs dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/70 pb-3 dark:border-slate-800">
                                    <div>
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Task Overview</span>
                                        <h4 className="text-base font-bold text-slate-900 dark:text-white">{task.title}</h4>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge value={task.status} />
                                        <StatusBadge value={task.priority} />
                                    </div>
                                </div>
                                <div className="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                    <div>
                                        <p className="text-slate-400">Supervisor</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{task.supervisor?.name || "Unassigned"}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Planned Schedule</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">
                                            {formatDate(task.planned_start_date) || "TBD"} → {formatDate(task.planned_end_date) || "TBD"}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Progress</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{progress}%</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Checklists</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{completedChecklists} of {taskChecklists.length} done</p>
                                    </div>
                                </div>
                                {task.description && (
                                    <div className="mt-3 rounded-xl bg-slate-100/70 p-3 text-xs text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                        <span className="font-semibold text-slate-900 dark:text-white">Description: </span>
                                        {task.description}
                                    </div>
                                )}
                            </div>

                            {/* Survey Visits Submissions */}
                            {taskVisits.length > 0 ? (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                            <span>📋</span>
                                            <span>Submitted Day Data & Field Reports ({taskVisits.length})</span>
                                        </h4>
                                        <span className="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                            Latest: Day {taskVisits[0]?.day_number || 1}
                                        </span>
                                    </div>

                                    {taskVisits.map((v, vIdx) => (
                                        <div
                                            key={v.id || vIdx}
                                            className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4.5 shadow-xs dark:border-slate-800 dark:bg-slate-950"
                                        >
                                            {/* Submitter Info Card */}
                                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-3 dark:border-slate-800">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 font-bold text-sm">
                                                        {(v.checked_in_by?.name || "S").charAt(0).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-bold text-indigo-600 dark:text-indigo-400">
                                                                Day {v.day_number || 1} Survey Update
                                                            </span>
                                                        </div>
                                                        <p className="text-xs text-slate-500">
                                                            Submitted by <span className="font-semibold text-slate-700 dark:text-slate-300">{v.checked_in_by?.name || "Field Surveyor"}</span> • {formatDateTime(v.updated_at || v.created_at) || "N/A"}
                                                            {v.checked_in_by?.phone ? ` • Tel: ${v.checked_in_by.phone}` : ""}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${Number(v.status) === 2 || v.status_key === 'approved'
                                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                        : Number(v.status) === 3 || v.status_key === 'rejected'
                                                            ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                                                            : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                                        }`}>
                                                        {v.status_label || (Number(v.status) === 2 ? 'Approved' : 'Submitted for Review')}
                                                    </span>
                                                    {v.gps_verified ? (
                                                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                            📍 GPS Verified
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </div>

                                            {/* Metrics Grid */}
                                            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                    <p className="text-[11px] font-medium text-slate-400">Elevation</p>
                                                    <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                                        {v.elevation_m != null ? `${v.elevation_m} m` : "—"}
                                                    </p>
                                                </div>
                                                <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                    <p className="text-[11px] font-medium text-slate-400">Distance Covered</p>
                                                    <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                                        {v.distance_covered_m != null ? `${v.distance_covered_m} m` : "—"}
                                                    </p>
                                                </div>
                                                <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                    <p className="text-[11px] font-medium text-slate-400">Points Captured</p>
                                                    <p className="mt-1 text-base font-bold text-indigo-600 dark:text-indigo-400">
                                                        {v.total_points_captured != null ? v.total_points_captured : "—"}
                                                    </p>
                                                </div>
                                                <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                    <p className="text-[11px] font-medium text-slate-400">Day Scheduled</p>
                                                    <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                                        Day {v.day_number || 1}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* GPS Coordinates */}
                                            {(v.check_in_latitude || v.check_in_longitude) && (
                                                <div className="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                                    <span>📍 Coords:</span>
                                                    <span className="font-mono text-slate-700 dark:text-slate-300">
                                                        {Number(v.check_in_latitude).toFixed(6)}, {Number(v.check_in_longitude).toFixed(6)}
                                                    </span>
                                                    <a
                                                        href={`https://www.google.com/maps?q=${v.check_in_latitude},${v.check_in_longitude}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-indigo-600 hover:underline dark:text-indigo-400"
                                                    >
                                                        (Open in Google Maps ↗)
                                                    </a>
                                                </div>
                                            )}

                                            {/* Remarks & Field Notes */}
                                            {(v.remarks || v.notes) && (
                                                <div className="mt-4 space-y-2.5">
                                                    {v.remarks && (
                                                        <div className="rounded-xl bg-amber-50/70 p-3 text-xs dark:bg-amber-950/20">
                                                            <p className="font-semibold text-amber-900 dark:text-amber-200">Survey Remarks</p>
                                                            <p className="mt-1 text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{v.remarks}</p>
                                                        </div>
                                                    )}
                                                    {v.notes && (
                                                        <FieldNotesDisplay notes={v.notes} hideRemarks={Boolean(v.remarks)} />
                                                    )}
                                                </div>
                                            )}

                                            {/* Attached Files */}
                                            {v.file_path && (
                                                <div className="mt-4 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                    <p className="text-xs font-bold text-slate-700 dark:text-slate-300">Attached Survey Files & Reports</p>
                                                    <div className="mt-2 flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-lg">📄</span>
                                                            <span className="text-xs font-medium text-slate-800 dark:text-slate-200 truncate max-w-sm">
                                                                {v.file_path.split("/").pop() || "Survey Data Report"}
                                                            </span>
                                                        </div>
                                                        <a
                                                            href={v.file_path}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300"
                                                        >
                                                            <span>Download / View File</span>
                                                            <span>↓</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Site Proof Photos */}
                                            {Array.isArray(v.photos) && v.photos.length > 0 && (
                                                <div className="mt-4">
                                                    <p className="text-xs font-bold text-slate-700 dark:text-slate-300">
                                                        Site Proof Photos ({v.photos.length})
                                                    </p>
                                                    <div className="mt-2 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                                        {v.photos.map((photoUrl, pIdx) => (
                                                            <button
                                                                key={pIdx}
                                                                type="button"
                                                                onClick={() => setPreviewImage(photoUrl)}
                                                                className="group relative aspect-video w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-2xs transition hover:scale-[1.02] hover:border-indigo-400 dark:border-slate-700 dark:bg-slate-900"
                                                            >
                                                                <img
                                                                    src={photoUrl}
                                                                    alt={`Proof ${pIdx + 1}`}
                                                                    className="h-full w-full object-cover"
                                                                />
                                                                <span className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100 text-white text-xs font-semibold">
                                                                    🔍 Zoom
                                                                </span>
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-800">
                                    <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl dark:bg-amber-950/40">
                                        📋
                                    </span>
                                    <h4 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                                        No Survey Submissions Yet
                                    </h4>
                                    <p className="mt-1 max-w-md mx-auto text-xs text-slate-500">
                                        The survey team has not submitted field data for this task yet. When they complete day work and submit from the mobile app, full metrics, points, RAW/PDF files, and photos will appear here.
                                    </p>
                                </div>
                            )}

                            {/* Task Checklist Items */}
                            {taskChecklists.length > 0 && (
                                <div className="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <div className="flex items-center justify-between border-b border-slate-200 pb-2.5 dark:border-slate-800">
                                        <h4 className="text-xs font-bold text-slate-800 dark:text-slate-200">
                                            Task Checklists ({completedChecklists} of {taskChecklists.length} done)
                                        </h4>
                                        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            {progress}% Complete
                                        </span>
                                    </div>
                                    <div className="mt-3 space-y-2">
                                        {taskChecklists.map((c) => (
                                            <div
                                                key={c.id}
                                                className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 bg-white p-3 text-xs dark:border-slate-800 dark:bg-slate-900"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span className={c.is_completed ? "text-emerald-500 font-bold" : "text-slate-300"}>
                                                        {c.is_completed ? "✓" : "○"}
                                                    </span>
                                                    <span className={`font-medium ${c.is_completed ? "line-through text-slate-400" : "text-slate-800 dark:text-slate-200"}`}>
                                                        {c.item_title}
                                                    </span>
                                                    {c.assign_hours && (
                                                        <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                            {c.assign_hours} hrs
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {c.image_url_1 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewImage(c.image_url_1)}
                                                            className="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                        >
                                                            📷 Proof 1
                                                        </button>
                                                    )}
                                                    {c.image_url_2 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewImage(c.image_url_2)}
                                                            className="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                        >
                                                            📷 Proof 2
                                                        </button>
                                                    )}
                                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${c.is_completed
                                                        ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                        : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                                        }`}>
                                                        {c.is_completed ? "Done" : "Pending"}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Drafting & CAD Drawing Phase Section */}
                            {(isSurveyApproved || isDraftingAssigned || isCurrentUserDraftPerson || drawingRevisions.length > 0) && (
                                <div className="rounded-2xl border-2 border-indigo-200 bg-indigo-50/40 p-4.5 dark:border-indigo-900/60 dark:bg-indigo-950/20">
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-100 pb-3 dark:border-indigo-900/60">
                                        <div className="flex items-center gap-2">
                                            <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-base text-white shadow-xs">
                                                📐
                                            </span>
                                            <div>
                                                <h4 className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                                    Drafting & CAD Drawing Workspace
                                                </h4>
                                                <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {assignedDraftPerson ? (
                                                        <span>Assigned Draft Person: <strong className="text-indigo-700 dark:text-indigo-300">{assignedDraftPerson.name}</strong></span>
                                                    ) : (
                                                        <span>Drafting job queued for project assignment</span>
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            {isDrawingApproved ? (
                                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                                    ✓ CAD Drawing Approved
                                                </span>
                                            ) : isDrawingSubmitted ? (
                                                <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                                    ⏳ Awaiting Drawing Review
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                                                    Drafting In Progress
                                                </span>
                                            )}

                                            {isCurrentUserDraftPerson && !isDrawingApproved && (
                                                <button
                                                    type="button"
                                                    onClick={() => setShowDraftUploadSection((prev) => !prev)}
                                                    className="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500"
                                                >
                                                    {showDraftUploadSection ? "Close Form" : "+ Upload CAD Drawing"}
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Existing Revisions List */}
                                    {drawingRevisions.length > 0 ? (
                                        <div className="mt-3.5 space-y-2.5">
                                            <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Submitted Drawing Revisions ({drawingRevisions.length})
                                            </p>
                                            {drawingRevisions.map((rev) => {
                                                const dwgPath = rev.dwg_document?.file_path || rev.dwg_file_path || rev.dwg_url;
                                                const pdfPath = rev.pdf_document?.file_path || rev.pdf_file_path || rev.pdf_url;
                                                const dwgHref = dwgPath ? (String(dwgPath).startsWith("http") || String(dwgPath).startsWith("/") ? dwgPath : "/" + dwgPath) : null;
                                                const pdfHref = pdfPath ? (String(pdfPath).startsWith("http") || String(pdfPath).startsWith("/") ? pdfPath : "/" + pdfPath) : null;

                                                return (
                                                    <div
                                                        key={rev.id}
                                                        className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-white p-3 shadow-2xs dark:border-indigo-900/50 dark:bg-slate-900"
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                <span className="rounded-md bg-indigo-100 px-2 py-0.5 text-[11px] font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                                                                    Rev #{rev.revision_no || 1}
                                                                </span>
                                                                <span className="text-xs font-bold text-slate-900 dark:text-white">
                                                                    {rev.drawing_no || rev.drawing_type || "CAD Layout"}
                                                                </span>
                                                                <StatusBadge value={rev.status || "submitted"} />
                                                            </div>
                                                            {rev.changes_made && (
                                                                <p className="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                                                    Notes: {rev.changes_made}
                                                                </p>
                                                            )}
                                                            <p className="mt-0.5 text-[10px] text-slate-400">
                                                                Uploaded {rev.uploaded_at ? formatDate(rev.uploaded_at) : "recently"}
                                                                {rev.uploaded_by?.name ? ` by ${rev.uploaded_by.name}` : ""}
                                                            </p>
                                                        </div>

                                                        <div className="flex flex-wrap items-center gap-2">
                                                            {dwgHref && (
                                                                <a
                                                                    href={dwgHref}
                                                                    target="_blank"
                                                                    rel="noreferrer noopener"
                                                                    download
                                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300"
                                                                >
                                                                    📥 Download DWG
                                                                </a>
                                                            )}
                                                            {pdfHref && (
                                                                <a
                                                                    href={pdfHref}
                                                                    target="_blank"
                                                                    rel="noreferrer noopener"
                                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300"
                                                                >
                                                                    📄 View PDF Sheet
                                                                </a>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}

                                            {/* Admin Drawing Approval Action Banner */}
                                            {!isCurrentUserDraftPerson && taskDraftingJob && (isDrawingSubmitted || isDrawingRevisionRequested) && (
                                                <div className="mt-3 rounded-xl border border-amber-300 bg-amber-50/90 p-3.5 dark:border-amber-700 dark:bg-amber-950/40">
                                                    <h5 className="text-xs font-bold text-amber-900 dark:text-amber-200">
                                                        Admin Review for CAD Drawing
                                                    </h5>
                                                    <p className="mt-0.5 text-xs text-amber-800 dark:text-amber-300">
                                                        Approving this drawing will mark drafting as completed and advance the project stage to <strong>Construction Execution</strong>.
                                                    </p>
                                                    <div className="mt-2.5 flex flex-wrap items-center justify-end gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={isSubmittingDraftReview}
                                                            onClick={() => handleReviewDraftingJob("revision_requested")}
                                                            className="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-slate-900 dark:text-amber-200"
                                                        >
                                                            Request Drawing Revision
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={isSubmittingDraftReview}
                                                            onClick={() => handleReviewDraftingJob("approved")}
                                                            className="rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500 disabled:opacity-60"
                                                        >
                                                            {isSubmittingDraftReview ? "Approving..." : "✓ Approve CAD Drawing & Advance Stage"}
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="mt-3 rounded-xl border border-dashed border-indigo-200 p-4 text-center dark:border-indigo-900/50">
                                            <p className="text-xs font-medium text-slate-600 dark:text-slate-300">
                                                No CAD drawing files submitted yet for this task.
                                            </p>
                                            <p className="mt-0.5 text-[11px] text-slate-400">
                                                The assigned Draft Person can use the survey data above to generate CAD drawings and submit them below.
                                            </p>
                                        </div>
                                    )}

                                    {/* Inline CAD Submission Form (for Draft Person or Admin upload) */}
                                    {(showDraftUploadSection || (isCurrentUserDraftPerson && drawingRevisions.length === 0)) && (
                                        <form onSubmit={handleDraftSubmit} className="mt-4 rounded-xl border border-indigo-200 bg-white p-4 shadow-sm dark:border-indigo-900/60 dark:bg-slate-900">
                                            <h5 className="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                                <span>📤</span>
                                                <span>Submit CAD Drawing Files (DWG / PDF)</span>
                                            </h5>
                                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                Upload your finished drawing files for admin approval. Once approved, the project will move to Construction Execution.
                                            </p>

                                            <div className="mt-3.5 grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Drawing Title / Sheet Name *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={draftUploadForm.data.drawing_title}
                                                        onChange={(e) => draftUploadForm.setData("drawing_title", e.target.value)}
                                                        required
                                                        placeholder="e.g. Site Boundary & Topographic CAD Plan"
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Drawing Number / Code *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={draftUploadForm.data.drawing_no}
                                                        onChange={(e) => draftUploadForm.setData("drawing_no", e.target.value)}
                                                        required
                                                        placeholder="e.g. DWG-TSK-00012-01"
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Drawing Type
                                                    </label>
                                                    <select
                                                        value={draftUploadForm.data.drawing_type}
                                                        onChange={(e) => draftUploadForm.setData("drawing_type", e.target.value)}
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    >
                                                        <option value="topographic">Topographic Survey Plan</option>
                                                        <option value="architectural">Architectural Layout</option>
                                                        <option value="structural">Structural Drawing</option>
                                                        <option value="civil">Civil Engineering Plan</option>
                                                        <option value="as_built">As-Built Drawing</option>
                                                        <option value="site_boundary">Site Boundary & Coordinates</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Revision No
                                                    </label>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={draftUploadForm.data.revision_no}
                                                        onChange={(e) => draftUploadForm.setData("revision_no", e.target.value)}
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        CAD DWG / DXF File
                                                    </label>
                                                    <input
                                                        type="file"
                                                        accept=".dwg,.dxf,.zip"
                                                        onChange={(e) => draftUploadForm.setData("dwg_file", e.target.files[0])}
                                                        className="mt-1 w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        PDF Drawing Sheet / Print Preview
                                                    </label>
                                                    <input
                                                        type="file"
                                                        accept=".pdf,image/*"
                                                        onChange={(e) => draftUploadForm.setData("pdf_file", e.target.files[0])}
                                                        className="mt-1 w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-sky-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-sky-700 hover:file:bg-sky-100"
                                                    />
                                                </div>
                                            </div>

                                            <div className="mt-3">
                                                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                    Scope / Changes Made / Drawing Notes
                                                </label>
                                                <textarea
                                                    rows={2}
                                                    value={draftUploadForm.data.changes_made}
                                                    onChange={(e) => draftUploadForm.setData("changes_made", e.target.value)}
                                                    placeholder="Specify layer standards, contours, adjustments made from field coordinates..."
                                                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                />
                                            </div>

                                            <div className="mt-3 flex items-center justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setShowDraftUploadSection(false)}
                                                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    type="submit"
                                                    disabled={draftUploadForm.processing}
                                                    className="rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:from-emerald-500 hover:to-teal-500 disabled:opacity-60"
                                                >
                                                    {draftUploadForm.processing ? "Uploading Drawing..." : "Submit CAD Drawing for Approval ➔"}
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            )}

                            {/* Inline Revision Input Form */}
                            {showRevisionInput && (
                                <div className="rounded-2xl border-2 border-amber-300 bg-amber-50/90 p-4 dark:border-amber-700 dark:bg-amber-950/40">
                                    <h4 className="text-xs font-bold text-amber-900 dark:text-amber-200">
                                        {isCurrentUserDraftPerson ? "Request Clarification / Changes from Surveyor" : "Request Survey Revision from Field Team"}
                                    </h4>
                                    <p className="mt-1 text-xs text-amber-800 dark:text-amber-300">
                                        {isCurrentUserDraftPerson
                                            ? "Specify missing survey points, elevation details, or boundary issues needed to complete CAD drawings:"
                                            : "Describe what is missing or needs correction (e.g. \"Missing elevation points on boundary corner B-4; please resubmit\"):"}
                                    </p>
                                    <textarea
                                        value={revisionRemarks}
                                        onChange={(e) => setRevisionRemarks(e.target.value)}
                                        rows={3}
                                        placeholder={isCurrentUserDraftPerson ? "Enter clarification request for field surveyor..." : "Enter revision notes and instructions..."}
                                        className="mt-2.5 w-full rounded-xl border border-amber-300 bg-white p-3 text-xs text-slate-900 focus:border-amber-500 focus:outline-none dark:border-amber-700 dark:bg-slate-900 dark:text-white"
                                    />
                                    <div className="mt-3 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setShowRevisionInput(false)}
                                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            disabled={isSubmittingReview}
                                            onClick={() => handleReviewSurvey("revision_requested")}
                                            className="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-500 disabled:opacity-60"
                                        >
                                            {isSubmittingReview ? "Sending..." : "Submit Revision Request"}
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Modal Footer Actions */}
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-950">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowDetailsModal(false);
                                    setShowDraftUploadSection(false);
                                }}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Close
                            </button>

                            <div className="flex flex-wrap items-center gap-2">
                                {isCurrentUserDraftPerson ? (
                                    <>
                                        {/* Draft Person Actions */}
                                        {!showRevisionInput && (
                                            <button
                                                type="button"
                                                onClick={() => setShowRevisionInput(true)}
                                                className="rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                                            >
                                                Request Clarification from Surveyor
                                            </button>
                                        )}
                                        {!isDrawingApproved && (
                                            <button
                                                type="button"
                                                onClick={() => setShowDraftUploadSection((prev) => !prev)}
                                                className="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:from-emerald-500 hover:to-teal-500"
                                            >
                                                {showDraftUploadSection ? "Hide CAD Form" : "Upload & Submit CAD Drawing ➔"}
                                            </button>
                                        )}
                                        {isDrawingApproved && (
                                            <span className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                                                ✓ CAD Drawing Approved — Ready for Construction
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        {/* Admin / Owner / Supervisor Actions */}
                                        {isSurveyApproved || isDraftingAssigned ? (
                                            <>
                                                <span className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                    ✓ Survey Approved
                                                </span>
                                                {assignedDraftPerson && (
                                                    <span className="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 dark:border-violet-900/40 dark:bg-violet-950/40 dark:text-violet-300">
                                                        ⭐ Drafter: {assignedDraftPerson.name}
                                                    </span>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowDetailsModal(false);
                                                        setShowTransferModal(true);
                                                    }}
                                                    className="rounded-xl border border-violet-300 bg-violet-50 px-3.5 py-2 text-xs font-semibold text-violet-800 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200"
                                                >
                                                    Re-assign / Transfer Task
                                                </button>
                                            </>
                                        ) : (
                                            <>
                                                {!showRevisionInput && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setShowRevisionInput(true)}
                                                        className="rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                                                    >
                                                        Request Revision
                                                    </button>
                                                )}
                                                <button
                                                    type="button"
                                                    disabled={isSubmittingReview}
                                                    onClick={() => handleReviewSurvey("approved")}
                                                    className="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500 disabled:opacity-60"
                                                >
                                                    Approve Survey
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowDetailsModal(false);
                                                        setShowTransferModal(true);
                                                    }}
                                                    className="rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:from-violet-500 hover:to-indigo-500"
                                                >
                                                    Approve & Transfer to Draft Person ➔
                                                </button>
                                            </>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Task Assign / Transfer Modal */}
            {showTransferModal && (
                <Modal show={true} onClose={() => setShowTransferModal(false)} maxWidth="2xl">
                    <form onSubmit={handleTransferSubmit} className="p-6">
                        <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Assign / Transfer Task
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Transfer <strong>{task.task_code}</strong>: "{task.title}" to a project team member.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowTransferModal(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Current Assignee Banner */}
                        <div className="mt-4 flex items-center justify-between rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                            <div>
                                <span className="text-slate-400">Current Supervisor / Assignee:</span>{" "}
                                <strong className="text-slate-800 dark:text-slate-200">
                                    {task.supervisor?.name || "Unassigned"}
                                </strong>
                            </div>
                            <StatusBadge value={task.status} />
                        </div>

                        <div className="mt-4 space-y-4">
                            {/* Member Dropdown from projectTeamMembers */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Assign To Project Team Member <span className="text-rose-500">*</span>
                                </label>
                                <p className="text-[11px] text-slate-400 mb-1.5">
                                    Select from members assigned to this project's team:
                                </p>
                                {projectTeamMembers.length > 0 ? (
                                    <select
                                        value={transferForm.data.member_id}
                                        onChange={(e) => {
                                            const mid = e.target.value;
                                            const selected = projectTeamMembers.find((tm) => Number(tm.member_id) === Number(mid));
                                            transferForm.setData((prev) => ({
                                                ...prev,
                                                member_id: mid,
                                                assignment_role: selected?.is_draft_person ? "draft_person" : prev.assignment_role,
                                                create_drafting_job: selected?.is_draft_person ? true : prev.create_drafting_job,
                                            }));
                                        }}
                                        required
                                        className="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="">-- Choose Project Team Member --</option>
                                        {projectTeamMembers.map((tm) => (
                                            <option key={tm.id} value={tm.member_id}>
                                                {tm.name} — {tm.role_name} {tm.is_draft_person ? "⭐ (Draft Person)" : ""}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
                                        ⚠️ No team members currently assigned to this project. Please assign team members in the <strong>Assigned Team</strong> section first.
                                    </div>
                                )}
                            </div>

                            {/* Assignment Role */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Assignment Role
                                    </label>
                                    <select
                                        value={transferForm.data.assignment_role}
                                        onChange={(e) => transferForm.setData("assignment_role", e.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="draft_person">Draft Person (Prepares CAD DWG / Revisions)</option>
                                        <option value="executor">Field Executor (Site Work / Operations)</option>
                                        <option value="supervisor">Supervisor (Site In-charge)</option>
                                        <option value="reviewer">Reviewer / Quality Checker</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Target Due Date
                                    </label>
                                    <input
                                        type="date"
                                        value={transferForm.data.due_date}
                                        onChange={(e) => transferForm.setData("due_date", e.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            {/* Drafting Job Toggle */}
                            <div className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                                <label className="flex items-start gap-2.5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={transferForm.data.create_drafting_job}
                                        onChange={(e) => transferForm.setData("create_drafting_job", e.target.checked)}
                                        className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <div>
                                        <span className="text-xs font-bold text-slate-900 dark:text-white">
                                            Create Drafting Job for CAD Drawing & Revision Submissions
                                        </span>
                                        <p className="text-[11px] text-slate-500">
                                            Unlocks CAD drawing uploads (DWG/PDF), revision tracking, and drafting dashboard for the assigned Draft Person.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            {/* Instructions & Scope */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Instructions & Drafting Scope / Remarks
                                </label>
                                <textarea
                                    value={transferForm.data.notes}
                                    onChange={(e) => transferForm.setData("notes", e.target.value)}
                                    rows={3}
                                    placeholder="e.g. Generate 2D topographic DWG with contour lines from points captured. Follow municipality standard layers..."
                                    className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowTransferModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={transferForm.processing || projectTeamMembers.length === 0}
                                className="rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:from-violet-500 hover:to-indigo-500 disabled:opacity-60"
                            >
                                {transferForm.processing ? "Assigning Task..." : "Confirm & Transfer Task"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Task Delete Confirmation Modal */}
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

function formatDateForInput(dateLike) {
    if (!dateLike) return "";
    if (typeof dateLike === "string") {
        const cleanStr = dateLike.trim();
        if (!cleanStr) return "";
        const isoMatch = cleanStr.match(/^(\d{4})[-/](\d{2})[-/](\d{2})/);
        if (isoMatch) {
            return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
        }
        const dmyMatch = cleanStr.match(/^(\d{2})[-/](\d{2})[-/](\d{4})/);
        if (dmyMatch) {
            return `${dmyMatch[3]}-${dmyMatch[2]}-${dmyMatch[1]}`;
        }
    }
    if (dateLike instanceof Date) {
        if (isNaN(dateLike.getTime())) return "";
        const y = dateLike.getFullYear();
        const m = String(dateLike.getMonth() + 1).padStart(2, "0");
        const d = String(dateLike.getDate()).padStart(2, "0");
        return `${y}-${m}-${d}`;
    }
    const d = new Date(dateLike);
    if (!isNaN(d.getTime())) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        return `${y}-${m}-${day}`;
    }
    return "";
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

function formatNumber(value) {
    if (value === null || value === undefined || value === "") return "-";
    const n = Number(value);
    if (!Number.isFinite(n)) return String(value);
    return new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 }).format(n);
}

function SubMetric({ label, value, span = "" }) {
    return (
        <div className={span}>
            <p className="text-[11px] uppercase tracking-wide text-slate-400">{label}</p>
            <p className="mt-0.5 font-semibold text-slate-800 dark:text-slate-100">{value ?? "-"}</p>
        </div>
    );
}

function FileAttachment({ path, span = "" }) {
    if (!path) return <div className={span} />;
    const name = String(path).split("/").pop() || "document";
    const href = /^https?:\/\//i.test(String(path)) ? path : String(path).startsWith("/") ? path : "/" + String(path).replace(/^\/+/, "");
    return (
        <div className={span}>
            <p className="text-[11px] uppercase tracking-wide text-slate-400">Report / File</p>
            <a
                href={href}
                target="_blank"
                rel="noreferrer noopener"
                className="mt-0.5 inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-[11px] font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-300 dark:hover:bg-indigo-950/60"
            >
                📄 <span className="truncate max-w-[220px]">{name}</span>
            </a>
        </div>
    );
}

function PhotoGrid({ photos = [], className = "" }) {
    const photoList = Array.isArray(photos) ? photos : [];
    if (!photoList.length) return null;
    return (
        <div className={`grid gap-2 sm:grid-cols-3 ${className}`}>
            {photoList.map((p, idx) => {
                const safe = typeof p === "string" ? p : p?.url || p?.path || "";
                if (!safe) return null;
                const href = /^https?:\/\//i.test(safe) ? safe : safe.startsWith("/") ? safe : "/" + safe.replace(/^\/+/, "");
                return (
                    <a
                        key={idx}
                        href={href}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="group relative block aspect-video w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <img
                            src={href}
                            alt={`Survey photo ${idx + 1}`}
                            loading="lazy"
                            className="h-full w-full object-cover transition group-hover:scale-[1.03]"
                        />
                    </a>
                );
            })}
        </div>
    );
}

function SurveyVisitCard({ visit }) {
    const hasSubmitted = Boolean(visit.submission) || String(visit.status || "").toLowerCase() === "submitted";
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-semibold text-slate-900 dark:text-white">Visit #{visit.id}</p>
                    <p className="mt-1 text-xs text-slate-500">
                        Check-in by{" "}
                        <span className="font-medium text-slate-700 dark:text-slate-200">
                            {visit.checked_in_by?.name || visit.checkedInBy?.name || "Unknown"}
                        </span>
                        {" • "}
                        {formatDateTime(visit.check_in_at) || "No timestamp"}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <StatusBadge value={visit.status_key || visit.status || (hasSubmitted ? "submitted" : "planned")} />
                    {visit.gps_verified ? (
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            GPS Verified
                        </span>
                    ) : (
                        <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            GPS Not Verified
                        </span>
                    )}
                    {visit.day_number ? (
                        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Day {visit.day_number}
                        </span>
                    ) : null}
                </div>
            </div>

            {visit.submission ? (
                <div className="mt-3 rounded-xl bg-indigo-50/60 p-3 text-xs dark:bg-indigo-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="font-medium text-indigo-900 dark:text-indigo-100">Submission Status</p>
                        <StatusBadge value={visit.submission.status_key || visit.submission.status} />
                    </div>
                    <p className="mt-1 text-indigo-700/80 dark:text-indigo-200/80">
                        Submitted by {visit.submission.submitted_by?.name || visit.submission.submittedBy?.name || "Unknown"}
                        {" • "}
                        {formatDateTime(visit.submission.submitted_at) || "—"}
                    </p>
                </div>
            ) : null}

            <div className="mt-4 grid gap-2 text-xs sm:grid-cols-2">
                <SubMetric label="Total Points" value={visit.total_points_captured} />
                <SubMetric label="Distance (m)" value={formatNumber(visit.distance_covered_m)} />
                <SubMetric label="Elevation (m)" value={formatNumber(visit.elevation_m)} />
                <SubMetric label="Entries / Meas." value={`${visit.entries?.length || 0} / ${visit.measurements?.length || 0}`} />
                <SubMetric label="GPS Latitude" value={visit.check_in_latitude ? Number(visit.check_in_latitude).toFixed(6) : undefined} />
                <SubMetric label="GPS Longitude" value={visit.check_in_longitude ? Number(visit.check_in_longitude).toFixed(6) : undefined} />
                <FileAttachment path={visit.file_path} span="sm:col-span-2" />
            </div>

            {visit.remarks || visit.notes ? (
                <div className="mt-3 space-y-2">
                    {visit.remarks ? (
                        <div className="rounded-xl bg-amber-50/70 p-3 text-xs text-amber-900 dark:bg-amber-950/20 dark:text-amber-100">
                            <p className="font-semibold">Remarks</p>
                            <p className="mt-1 whitespace-pre-wrap break-words">{visit.remarks}</p>
                        </div>
                    ) : null}
                    {visit.notes ? (
                        <FieldNotesDisplay notes={visit.notes} />
                    ) : null}
                </div>
            ) : null}

            <PhotoGrid photos={visit.photos} className="mt-4" />
        </div>
    );
}

function SurveyPlanCard({ plan }) {
    const [showModal, setShowModal] = useState(false);
    const visits = plan.visits || [];

    return (
        <div className="rounded-2xl border border-slate-200/90 bg-white p-4.5 shadow-2xs transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                            {plan.survey_code}
                        </span>
                        <h4 className="font-bold text-slate-900 dark:text-white">
                            {plan.title}
                        </h4>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                        Planned Date: {formatDate(plan.planned_date) || "No planned date"}
                        {plan.site_address ? ` • 📍 ${plan.site_address}` : " • No site address recorded."}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <StatusBadge value={plan.status_key || plan.status} />
                </div>
            </div>

            {/* Assigned Survey Team Members */}
            {(plan.plan_members || []).length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                    {plan.plan_members.map((member) => (
                        <span
                            key={member.id}
                            className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <span>👤</span>
                            <span>{member.member?.name || "Unknown"}</span>
                            <span className="text-slate-400">•</span>
                            <span className="text-slate-500 dark:text-slate-400">{member.role_in_survey}</span>
                        </span>
                    ))}
                </div>
            )}

            {/* Bottom bar with Visits Summary & View More button */}
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-[11px] font-bold">
                        {visits.length}
                    </span>
                    <span>
                        {visits.length === 0
                            ? "No field check-in visits yet"
                            : `${visits.length} field visit${visits.length > 1 ? "s" : ""} recorded`}
                    </span>
                </div>

                <button
                    type="button"
                    onClick={() => setShowModal(true)}
                    className="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50/80 px-3.5 py-1.5 text-xs font-semibold text-indigo-700 shadow-2xs transition hover:bg-indigo-100 hover:border-indigo-300 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300 dark:hover:bg-indigo-900/60"
                >
                    <span>👁️</span>
                    <span>View More Details</span>
                    {visits.length > 0 && (
                        <span className="rounded-full bg-indigo-200/90 px-1.5 py-0.2 text-[10px] font-bold text-indigo-800 dark:bg-indigo-800 dark:text-indigo-200">
                            {visits.length}
                        </span>
                    )}
                    <span>↗</span>
                </button>
            </div>

            {/* Survey Plan Details Modal */}
            {showModal && (
                <Modal show={true} onClose={() => setShowModal(false)} maxWidth="5xl">
                    <div className="flex max-h-[88vh] flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {plan.survey_code}
                                    </span>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        {plan.title}
                                    </h3>
                                    <StatusBadge value={plan.status_key || plan.status} />
                                </div>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Planned Date: {formatDate(plan.planned_date) || "No planned date"}
                                    {plan.site_address ? ` • 📍 ${plan.site_address}` : ""}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Modal Body - Scrollable */}
                        <div className="flex-1 space-y-6 overflow-y-auto p-6">
                            {/* Plan Overview Card */}
                            <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-indigo-50/20 p-4.5 shadow-2xs dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
                                <h4 className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    Survey Plan Overview
                                </h4>
                                <div className="mt-3 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                                    <div>
                                        <p className="text-slate-400">Survey Code</p>
                                        <p className="font-mono font-bold text-indigo-600 dark:text-indigo-400">{plan.survey_code}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Planned Date</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{formatDate(plan.planned_date) || "N/A"}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Status</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{plan.status_key || plan.status || "In Progress"}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Field Visits</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{visits.length} recorded</p>
                                    </div>
                                </div>
                                {plan.site_address && (
                                    <div className="mt-3 rounded-xl bg-slate-100/70 p-3 text-xs text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                        <span className="font-semibold text-slate-900 dark:text-white">📍 Site Location: </span>
                                        {plan.site_address}
                                    </div>
                                )}
                                {(plan.plan_members || []).length > 0 && (
                                    <div className="mt-3 border-t border-slate-200/60 pt-3 dark:border-slate-800">
                                        <p className="text-[11px] font-medium text-slate-400 mb-1.5">Assigned Survey Team:</p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {plan.plan_members.map((m) => (
                                                <span key={m.id} className="rounded-lg bg-white px-2.5 py-1 text-xs border border-slate-200 font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                    👤 {m.member?.name || "Unknown"} ({m.role_in_survey})
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Captured Field Visits */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <span>📍</span>
                                        <span>Field Check-in Visits & Survey Reports ({visits.length})</span>
                                    </h4>
                                    {visits.length > 0 && (
                                        <span className="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                            Latest: Day {visits[0]?.day_number || 1}
                                        </span>
                                    )}
                                </div>

                                {visits.length > 0 ? (
                                    <div className="space-y-4">
                                        {visits.map((visit) => (
                                            <SurveyVisitCard key={visit.id} visit={visit} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-800">
                                        <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl dark:bg-amber-950/40">
                                            📋
                                        </span>
                                        <h4 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                                            No Field Visits Captured Yet
                                        </h4>
                                        <p className="mt-1 max-w-md mx-auto text-xs text-slate-500">
                                            Field surveyors have not submitted check-in visits for this survey plan yet.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="flex items-center justify-end border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-950">
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
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

function mergedTasksForExecutionList(project) {
    const legacyTasks = Array.isArray(project?.execution_tasks) ? project.execution_tasks : [];
    const unifiedTasks = Array.isArray(project?.tasks) ? project.tasks : [];

    const normalize = (task, sourceTag) => {
        const legacyChecklists = Array.isArray(task.checklists) ? task.checklists : [];
        const rawUnified = Array.isArray(task.checklist_items)
            ? task.checklist_items
            : (Array.isArray(task.checklistItems) ? task.checklistItems : []);
        const unifiedChecklists = rawUnified.map((item) => ({
            ...item,
            is_completed: item.is_completed ?? item.completed_at ?? false,
            item_title: item.item_title ?? item.title ?? "",
        }));

        // Cross-match if checklists were attached to the other table representation
        let fallbackChecklists = [];
        if (sourceTag === "unified" && legacyChecklists.length === 0 && unifiedChecklists.length === 0) {
            const match = legacyTasks.find((et) =>
                et.id === task.id ||
                (et.task_code && task.task_code && et.task_code === task.task_code) ||
                (et.title && task.title && et.title.trim().toLowerCase() === task.title.trim().toLowerCase())
            );
            if (match && Array.isArray(match.checklists)) {
                fallbackChecklists = match.checklists;
            }
        } else if (sourceTag === "legacy" && legacyChecklists.length === 0 && unifiedChecklists.length === 0) {
            const match = unifiedTasks.find((ut) =>
                ut.id === task.id ||
                (ut.task_code && task.task_code && ut.task_code === task.task_code) ||
                (ut.title && task.title && ut.title.trim().toLowerCase() === task.title.trim().toLowerCase())
            );
            if (match) {
                const rawUt = Array.isArray(match.checklist_items)
                    ? match.checklist_items
                    : (Array.isArray(match.checklistItems) ? match.checklistItems : []);
                fallbackChecklists = rawUt.map((item) => ({
                    ...item,
                    is_completed: item.is_completed ?? item.completed_at ?? false,
                    item_title: item.item_title ?? item.title ?? "",
                }));
            }
        }

        const combinedChecklists = [...legacyChecklists, ...unifiedChecklists, ...fallbackChecklists];
        const seenChecklist = new Set();
        const mergedChecklists = [];
        for (const it of combinedChecklists) {
            const key = `${it.item_title || it.title || ""}-${it.day_number || 1}`;
            if (!seenChecklist.has(key)) {
                seenChecklist.add(key);
                mergedChecklists.push(it);
            }
        }
        const fallbackCode = `${sourceTag.toUpperCase()}-${task.id}`;
        const has = (k) => Object.prototype.hasOwnProperty.call(task, k);
        const pick = (keys) => {
            const out = {};
            keys.forEach((k) => {
                if (has(k)) out[k] = task[k];
            });
            return out;
        };
        const isLegacy = sourceTag === "legacy";
        const plannedStart = isLegacy
            ? (task.planned_start_date ?? "")
            : (task.start_date ?? task.planned_start_date ?? "");
        const plannedEnd = isLegacy
            ? (task.planned_end_date ?? "")
            : (task.end_date ?? task.planned_end_date ?? "");
        const actualStart = isLegacy
            ? (task.actual_start_date ?? "")
            : (task.actual_start_date ?? task.start_date ?? "");
        const actualEnd = isLegacy
            ? (task.actual_end_date ?? "")
            : (task.actual_end_date ?? task.end_date ?? "");
        const plannedQty = isLegacy
            ? (task.planned_quantity ?? "")
            : (task.planned_qty ?? task.planned_quantity ?? "");
        const completedQty = isLegacy
            ? (task.completed_quantity ?? "")
            : (task.completed_qty ?? task.completed_quantity ?? "");
        const unitVal = isLegacy
            ? (task.unit ?? "")
            : (task.qty_unit ?? task.unit ?? "");
        const supervisorId = isLegacy
            ? (task.supervisor_member_id ?? "")
            : (task.assigned_supervisor_member_id ?? task.supervisor_member_id ?? "");
        return {
            ...pick(Object.keys(task)),
            checklists: mergedChecklists,
            task_code: task.task_code || fallbackCode,
            status: task.status || task.status_key || "pending",
            priority: task.priority || "medium",
            supervisor: task.supervisor || task.assigned_supervisor_member || null,
            sort_order: Number.isFinite(task.sort_order) ? task.sort_order : 0,
            _sourceKey: `${sourceTag}-${task.id}`,
            _source: sourceTag,
            planned_start_date: formatDateForInput(plannedStart),
            planned_end_date: formatDateForInput(plannedEnd),
            actual_start_date: formatDateForInput(actualStart),
            actual_end_date: formatDateForInput(actualEnd),
            planned_quantity: plannedQty,
            completed_quantity: completedQty,
            unit: unitVal,
            progress_percent: has("progress_percent") && task.progress_percent !== null && task.progress_percent !== undefined
                ? task.progress_percent
                : "",
            supervisor_member_id: supervisorId,
            requires_daily_update: !!(task.requires_daily_update ?? false),
            requires_gps_verification: !!(task.requires_gps_verification ?? false),
        };
    };

    const rows = [
        ...legacyTasks.map((t) => normalize(t, "legacy")),
        ...unifiedTasks.map((t) => normalize(t, "unified")),
    ];

    rows.sort((a, b) => {
        const diff = (a.sort_order || 0) - (b.sort_order || 0);
        if (diff !== 0) return diff;
        const at = a.created_at ? new Date(a.created_at).getTime() : 0;
        const bt = b.created_at ? new Date(b.created_at).getTime() : 0;
        return bt - at;
    });

    return rows;
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

    const memberId = teamMember.member_id;
    const allLogs = [];

    (project.attendance_records || []).forEach((r) => {
        if (Number(r.member_id) === Number(memberId)) {
            allLogs.push({
                check_in_at: r.check_in_at,
                check_out_at: r.check_out_at,
                duration_minutes: r.duration_minutes || null,
            });
        }
    });

    (project.survey_plans || []).forEach((plan) => {
        (plan.visits || []).forEach((v) => {
            const checkedInId = v.checked_in_by_member_id || v.checkedInBy?.id || v.checked_in_by?.id;
            if (Number(checkedInId) === Number(memberId)) {
                allLogs.push({
                    check_in_at: v.check_in_at,
                    check_out_at: v.check_out_at,
                    duration_minutes: v.duration_minutes || null,
                });
            }
        });
    });

    if (Array.isArray(project.survey_visits)) {
        project.survey_visits.forEach((v) => {
            const checkedInId = v.checked_in_by_member_id || v.checkedInBy?.id || v.checked_in_by?.id;
            if (Number(checkedInId) === Number(memberId)) {
                allLogs.push({
                    check_in_at: v.check_in_at,
                    check_out_at: v.check_out_at,
                    duration_minutes: v.duration_minutes || null,
                });
            }
        });
    }

    const map = new Map();
    allLogs.forEach((log) => {
        if (!log.check_in_at) return;
        const key = new Date(log.check_in_at).getTime();
        if (!map.has(key)) {
            map.set(key, { ...log });
        } else {
            const existing = map.get(key);
            if (!existing.check_out_at && log.check_out_at) {
                existing.check_out_at = log.check_out_at;
            }
            if (!existing.duration_minutes && log.duration_minutes) {
                existing.duration_minutes = log.duration_minutes;
            }
        }
    });

    const sortedLogs = [...map.values()].sort((a, b) => {
        const at = a.check_in_at ? new Date(a.check_in_at).getTime() : 0;
        const bt = b.check_in_at ? new Date(b.check_in_at).getTime() : 0;
        return bt - at;
    });

    const latestLog = sortedLogs[0];

    let totalMinutes = 0;
    sortedLogs.forEach((r) => {
        if (r.check_in_at && r.check_out_at) {
            const diff = Math.max(0, (new Date(r.check_out_at).getTime() - new Date(r.check_in_at).getTime()) / 60000);
            totalMinutes += diff;
        } else if (r.duration_minutes) {
            totalMinutes += Number(r.duration_minutes);
        } else if (r.check_in_at && !r.check_out_at) {
            const diff = Math.max(0, (new Date().getTime() - new Date(r.check_in_at).getTime()) / 60000);
            totalMinutes += diff;
        }
    });

    const hrs = Math.floor(totalMinutes / 60);
    const mins = Math.floor(totalMinutes % 60);
    const workingHoursStr = totalMinutes > 0 ? `${hrs}h ${mins}m` : "0h 0m";

    const formatTime = (ts) => {
        if (!ts) return "-";
        const d = new Date(ts);
        if (isNaN(d.getTime())) return "-";
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const checkInTimeStr = latestLog?.check_in_at ? formatTime(latestLog.check_in_at) : "-";
    const checkOutTimeStr = latestLog?.check_out_at ? formatTime(latestLog.check_out_at) : (latestLog?.check_in_at ? "Checked In (Active)" : "-");
    const isCheckedIn = !!(latestLog?.check_in_at && !latestLog?.check_out_at);

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
            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
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
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium ${teamMember.status === 'active'
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

                {/* Member Check-In, Check-Out & Working Hours Dynamic Section */}
                <div className="mt-3 grid gap-2 sm:grid-cols-3 rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-950">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-500">📥 Check-In:</span>
                        <span className="text-xs font-bold text-slate-800 dark:text-slate-200">{checkInTimeStr}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-500">📤 Check-Out:</span>
                        <span className={`text-xs font-bold ${isCheckedIn ? 'text-emerald-600 animate-pulse' : 'text-slate-800 dark:text-slate-200'}`}>
                            {checkOutTimeStr}
                        </span>
                    </div>
                    <div className="flex items-center gap-2 sm:justify-end">
                        <span className="text-xs font-semibold text-slate-500">⏱️ Working Hours:</span>
                        <span className="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                            {workingHoursStr}
                        </span>
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
