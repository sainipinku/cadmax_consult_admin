import { router, useForm } from "@inertiajs/react";
import { useMemo } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function ExecutionWorkspace({
    variant = "super",
    stats,
    projects,
    executionPlans,
    executionTasks,
    dailyProgressReports,
    attendanceRecords,
    members,
    projectTeamMembers,
}) {
    const routePrefix = variant === "super" ? "super.construction.execution" : "admin.construction.execution";
    const documentRouteBase = variant === "super" ? "super.construction.documents" : "admin.construction.documents";
    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    const planOptionsByProject = useMemo(() => {
        return executionPlans.reduce((carry, plan) => {
            const projectId = String(plan.project_id);
            if (!carry[projectId]) {
                carry[projectId] = [];
            }
            carry[projectId].push(plan);
            return carry;
        }, {});
    }, [executionPlans]);

    const teamOptionsByProject = useMemo(() => {
        return projectTeamMembers.reduce((carry, teamMember) => {
            const projectId = String(teamMember.project_id);
            if (!carry[projectId]) {
                carry[projectId] = [];
            }
            carry[projectId].push(teamMember);
            return carry;
        }, {});
    }, [projectTeamMembers]);

    const taskOptionsByProject = useMemo(() => {
        return executionTasks.reduce((carry, task) => {
            const projectId = String(task.project_id);
            if (!carry[projectId]) {
                carry[projectId] = [];
            }
            carry[projectId].push(task);
            return carry;
        }, {});
    }, [executionTasks]);

    const initialPlanId = planOptionsByProject[firstProjectId]?.[0]?.id ? String(planOptionsByProject[firstProjectId][0].id) : "";
    const initialTeamMemberId = teamOptionsByProject[firstProjectId]?.[0]?.member_id
        ? String(teamOptionsByProject[firstProjectId][0].member_id)
        : "";
    const initialTaskId = taskOptionsByProject[firstProjectId]?.[0]?.id ? String(taskOptionsByProject[firstProjectId][0].id) : "";

    const planForm = useForm({
        project_id: firstProjectId,
        title: "",
        description: "",
        planned_start_date: "",
        planned_end_date: "",
        status: "planned",
    });

    const taskForm = useForm({
        project_id: firstProjectId,
        execution_plan_id: initialPlanId,
        parent_task_id: "",
        title: "",
        description: "",
        planned_start_date: "",
        planned_end_date: "",
        priority: "medium",
        planned_quantity: "",
        unit: "",
        requires_daily_update: true,
        requires_gps_verification: true,
        supervisor_member_id: initialTeamMemberId,
        assignee_member_ids: initialTeamMemberId ? [initialTeamMemberId] : [],
        primary_assignment_role: "worker",
    });

    const assignmentForm = useForm({
        execution_task_id: initialTaskId,
        member_id: initialTeamMemberId,
        assignment_role: "worker",
        assigned_from: "",
        assigned_to: "",
        is_primary: false,
    });

    const progressForm = useForm({
        execution_task_id: initialTaskId,
        progress_percent: executionTasks[0]?.progress_percent ?? 0,
        completed_quantity: executionTasks[0]?.completed_quantity ?? "",
        status: executionTasks[0]?.status ?? "planned",
    });

    const reportForm = useForm({
        project_id: firstProjectId,
        execution_task_id: initialTaskId,
        report_date: new Date().toISOString().slice(0, 10),
        summary: "",
        work_completed: "",
        blockers: "",
        workforce_count: "",
        latitude: "",
        longitude: "",
        gps_accuracy_meters: "",
        weather_summary: "",
        supporting_document: null,
        items: [],
    });

    const selectedTask = executionTasks.find((task) => String(task.id) === String(progressForm.data.execution_task_id));

    const updateProjectScopedForms = (projectId) => {
        const normalizedProjectId = String(projectId);
        const firstProjectPlan = planOptionsByProject[normalizedProjectId]?.[0];
        const firstProjectTeamMember = teamOptionsByProject[normalizedProjectId]?.[0];
        const firstProjectTask = taskOptionsByProject[normalizedProjectId]?.[0];

        taskForm.setData((data) => ({
            ...data,
            project_id: normalizedProjectId,
            execution_plan_id: firstProjectPlan ? String(firstProjectPlan.id) : "",
            supervisor_member_id: firstProjectTeamMember ? String(firstProjectTeamMember.member_id) : "",
            assignee_member_ids: firstProjectTeamMember ? [String(firstProjectTeamMember.member_id)] : [],
        }));

        reportForm.setData((data) => ({
            ...data,
            project_id: normalizedProjectId,
            execution_task_id: firstProjectTask ? String(firstProjectTask.id) : "",
        }));

        if (firstProjectTask) {
            progressForm.setData((data) => ({
                ...data,
                execution_task_id: String(firstProjectTask.id),
                progress_percent: firstProjectTask.progress_percent ?? 0,
                completed_quantity: firstProjectTask.completed_quantity ?? "",
                status: firstProjectTask.status ?? "planned",
            }));

            assignmentForm.setData((data) => ({
                ...data,
                execution_task_id: String(firstProjectTask.id),
                member_id: firstProjectTeamMember ? String(firstProjectTeamMember.member_id) : "",
            }));
        } else {
            progressForm.setData((data) => ({
                ...data,
                execution_task_id: "",
                progress_percent: 0,
                completed_quantity: "",
                status: "planned",
            }));

            assignmentForm.setData((data) => ({
                ...data,
                execution_task_id: "",
                member_id: firstProjectTeamMember ? String(firstProjectTeamMember.member_id) : "",
            }));
        }
    };

    const teamOptionsForSelectedProject = teamOptionsByProject[String(taskForm.data.project_id)] ?? [];
    const planOptionsForSelectedProject = planOptionsByProject[String(taskForm.data.project_id)] ?? [];
    const taskOptionsForSelectedProject = taskOptionsByProject[String(reportForm.data.project_id)] ?? [];
    const assignmentTask = executionTasks.find((task) => String(task.id) === String(assignmentForm.data.execution_task_id));
    const assignmentTeamOptions = assignmentTask
        ? (teamOptionsByProject[String(assignmentTask.project_id)] ?? [])
        : [];

    return (
        <ConstructionShell
            title="Construction Execution"
            description="Batch 1 execution planning, task allocation, DPR capture, and attendance review in one project-linked workspace."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Execution Plans" value={stats.plans} />
                <StatCard label="Execution Tasks" value={stats.tasks} />
                <StatCard label="Active Tasks" value={stats.activeTasks} hint="Planned, in progress, or blocked" />
                <StatCard label="Pending DPR Reviews" value={stats.pendingReports} />
                <StatCard label="Pending Attendance" value={stats.pendingAttendance} />
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Execution Plan Setup" description="Create the top-level execution plan once a project is ready for site work.">
                    {projects.length ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                planForm.post(route(`${routePrefix}.plans.store`), { preserveScroll: true });
                            }}
                        >
                            <SelectInput
                                label="Project"
                                value={planForm.data.project_id}
                                onChange={(value) => {
                                    planForm.setData("project_id", value);
                                    updateProjectScopedForms(value);
                                }}
                                options={projects.map((project) => ({
                                    value: String(project.id),
                                    label: `${project.project_code} • ${project.name}`,
                                }))}
                                error={planForm.errors.project_id}
                            />
                            <TextInput label="Plan Title" value={planForm.data.title} onChange={(value) => planForm.setData("title", value)} error={planForm.errors.title} />
                            <TextAreaInput label="Description" value={planForm.data.description} onChange={(value) => planForm.setData("description", value)} error={planForm.errors.description} rows={4} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Planned Start" type="date" value={planForm.data.planned_start_date} onChange={(value) => planForm.setData("planned_start_date", value)} error={planForm.errors.planned_start_date} />
                                <TextInput label="Planned End" type="date" value={planForm.data.planned_end_date} onChange={(value) => planForm.setData("planned_end_date", value)} error={planForm.errors.planned_end_date} />
                            </div>
                            <SelectInput
                                label="Initial Status"
                                value={planForm.data.status}
                                onChange={(value) => planForm.setData("status", value)}
                                options={[
                                    { value: "planned", label: "Planned" },
                                    { value: "active", label: "Active" },
                                ]}
                                error={planForm.errors.status}
                            />
                            <PrimaryButton disabled={planForm.processing}>
                                {planForm.processing ? "Saving..." : "Create Execution Plan"}
                            </PrimaryButton>
                        </form>
                    ) : (
                        <EmptyState title="No construction projects available." description="Create and prepare a project through Phase 1 and Phase 2 before starting execution." />
                    )}
                </SectionCard>

                <SectionCard title="Task Planning" description="Break the plan into task-level execution units with supervisor and worker assignments.">
                    {projects.length ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                taskForm.post(route(`${routePrefix}.tasks.store`), { preserveScroll: true });
                            }}
                        >
                            <SelectInput
                                label="Project"
                                value={taskForm.data.project_id}
                                onChange={(value) => {
                                    updateProjectScopedForms(value);
                                }}
                                options={projects.map((project) => ({
                                    value: String(project.id),
                                    label: `${project.project_code} • ${project.name}`,
                                }))}
                                error={taskForm.errors.project_id}
                            />
                            <SelectInput
                                label="Execution Plan"
                                value={taskForm.data.execution_plan_id}
                                onChange={(value) => taskForm.setData("execution_plan_id", value)}
                                options={planOptionsForSelectedProject.map((plan) => ({
                                    value: String(plan.id),
                                    label: `${plan.plan_code} • ${plan.title}`,
                                }))}
                                error={taskForm.errors.execution_plan_id}
                            />
                            <TextInput label="Task Title" value={taskForm.data.title} onChange={(value) => taskForm.setData("title", value)} error={taskForm.errors.title} />
                            <TextAreaInput label="Description" value={taskForm.data.description} onChange={(value) => taskForm.setData("description", value)} error={taskForm.errors.description} rows={3} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Planned Start" type="date" value={taskForm.data.planned_start_date} onChange={(value) => taskForm.setData("planned_start_date", value)} error={taskForm.errors.planned_start_date} />
                                <TextInput label="Planned End" type="date" value={taskForm.data.planned_end_date} onChange={(value) => taskForm.setData("planned_end_date", value)} error={taskForm.errors.planned_end_date} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectInput
                                    label="Priority"
                                    value={taskForm.data.priority}
                                    onChange={(value) => taskForm.setData("priority", value)}
                                    options={[
                                        { value: "low", label: "Low" },
                                        { value: "medium", label: "Medium" },
                                        { value: "high", label: "High" },
                                        { value: "critical", label: "Critical" },
                                    ]}
                                    error={taskForm.errors.priority}
                                />
                                <SelectInput
                                    label="Supervisor"
                                    value={taskForm.data.supervisor_member_id}
                                    onChange={(value) => taskForm.setData("supervisor_member_id", value)}
                                    options={teamOptionsForSelectedProject.map((item) => ({
                                        value: String(item.member_id),
                                        label: item.member?.name || `Member #${item.member_id}`,
                                    }))}
                                    error={taskForm.errors.supervisor_member_id}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Planned Quantity" type="number" value={taskForm.data.planned_quantity} onChange={(value) => taskForm.setData("planned_quantity", value)} error={taskForm.errors.planned_quantity} />
                                <TextInput label="Unit" value={taskForm.data.unit} onChange={(value) => taskForm.setData("unit", value)} error={taskForm.errors.unit} placeholder="sqm, nos, ft..." />
                            </div>
                            <MultiSelectInput
                                label="Assignees"
                                value={taskForm.data.assignee_member_ids}
                                onChange={(value) => taskForm.setData("assignee_member_ids", value)}
                                options={teamOptionsForSelectedProject.map((item) => ({
                                    value: String(item.member_id),
                                    label: item.member?.name || `Member #${item.member_id}`,
                                }))}
                                error={taskForm.errors.assignee_member_ids}
                            />
                            <TextInput label="Assignment Role" value={taskForm.data.primary_assignment_role} onChange={(value) => taskForm.setData("primary_assignment_role", value)} error={taskForm.errors.primary_assignment_role} />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <CheckboxInput
                                    label="Require daily updates"
                                    checked={taskForm.data.requires_daily_update}
                                    onChange={(checked) => taskForm.setData("requires_daily_update", checked)}
                                />
                                <CheckboxInput
                                    label="Require GPS verification"
                                    checked={taskForm.data.requires_gps_verification}
                                    onChange={(checked) => taskForm.setData("requires_gps_verification", checked)}
                                />
                            </div>
                            <PrimaryButton disabled={taskForm.processing || !planOptionsForSelectedProject.length}>
                                {taskForm.processing ? "Saving..." : "Create Execution Task"}
                            </PrimaryButton>
                        </form>
                    ) : (
                        <EmptyState title="No projects ready for task planning." description="Create an execution plan first, then use this form to create site tasks." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Task Assignment" description="Add or update worker-level assignments after the task is created.">
                    {executionTasks.length ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                assignmentForm.post(
                                    route(`${routePrefix}.tasks.assign`, assignmentForm.data.execution_task_id),
                                    { preserveScroll: true }
                                );
                            }}
                        >
                            <SelectInput
                                label="Task"
                                value={assignmentForm.data.execution_task_id}
                                onChange={(value) => {
                                    const nextTask = executionTasks.find((task) => String(task.id) === String(value));
                                    assignmentForm.setData((data) => ({
                                        ...data,
                                        execution_task_id: value,
                                        member_id: nextTask
                                            ? String((teamOptionsByProject[String(nextTask.project_id)] ?? [])[0]?.member_id ?? "")
                                            : "",
                                    }));
                                }}
                                options={executionTasks.map((task) => ({
                                    value: String(task.id),
                                    label: `${task.task_code} • ${task.title}`,
                                }))}
                                error={assignmentForm.errors.execution_task_id}
                            />
                            <SelectInput
                                label="Member"
                                value={assignmentForm.data.member_id}
                                onChange={(value) => assignmentForm.setData("member_id", value)}
                                options={assignmentTeamOptions.map((item) => ({
                                    value: String(item.member_id),
                                    label: item.member?.name || `Member #${item.member_id}`,
                                }))}
                                error={assignmentForm.errors.member_id}
                            />
                            <TextInput label="Assignment Role" value={assignmentForm.data.assignment_role} onChange={(value) => assignmentForm.setData("assignment_role", value)} error={assignmentForm.errors.assignment_role} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Assigned From" type="date" value={assignmentForm.data.assigned_from} onChange={(value) => assignmentForm.setData("assigned_from", value)} error={assignmentForm.errors.assigned_from} />
                                <TextInput label="Assigned To" type="date" value={assignmentForm.data.assigned_to} onChange={(value) => assignmentForm.setData("assigned_to", value)} error={assignmentForm.errors.assigned_to} />
                            </div>
                            <CheckboxInput
                                label="Mark as primary assignee"
                                checked={assignmentForm.data.is_primary}
                                onChange={(checked) => assignmentForm.setData("is_primary", checked)}
                            />
                            <PrimaryButton disabled={assignmentForm.processing}>
                                {assignmentForm.processing ? "Saving..." : "Save Assignment"}
                            </PrimaryButton>
                        </form>
                    ) : (
                        <EmptyState title="No execution tasks available." description="Create at least one task before assigning site members." />
                    )}
                </SectionCard>

                <SectionCard title="Task Progress Update" description="Use this for quick supervisor updates and to keep plan-level progress current.">
                    {executionTasks.length ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                progressForm.post(
                                    route(`${routePrefix}.tasks.progress.update`, progressForm.data.execution_task_id),
                                    { preserveScroll: true }
                                );
                            }}
                        >
                            <SelectInput
                                label="Task"
                                value={progressForm.data.execution_task_id}
                                onChange={(value) => {
                                    const task = executionTasks.find((item) => String(item.id) === String(value));
                                    progressForm.setData({
                                        execution_task_id: value,
                                        progress_percent: task?.progress_percent ?? 0,
                                        completed_quantity: task?.completed_quantity ?? "",
                                        status: task?.status ?? "planned",
                                    });
                                }}
                                options={executionTasks.map((task) => ({
                                    value: String(task.id),
                                    label: `${task.task_code} • ${task.title}`,
                                }))}
                                error={progressForm.errors.execution_task_id}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput
                                    label="Progress %"
                                    type="number"
                                    value={progressForm.data.progress_percent}
                                    onChange={(value) => progressForm.setData("progress_percent", value)}
                                    error={progressForm.errors.progress_percent}
                                />
                                <TextInput
                                    label="Completed Quantity"
                                    type="number"
                                    value={progressForm.data.completed_quantity}
                                    onChange={(value) => progressForm.setData("completed_quantity", value)}
                                    error={progressForm.errors.completed_quantity}
                                />
                            </div>
                            <SelectInput
                                label="Status"
                                value={progressForm.data.status}
                                onChange={(value) => progressForm.setData("status", value)}
                                options={[
                                    { value: "planned", label: "Planned" },
                                    { value: "in_progress", label: "In Progress" },
                                    { value: "completed", label: "Completed" },
                                    { value: "blocked", label: "Blocked" },
                                ]}
                                error={progressForm.errors.status}
                            />
                            {selectedTask ? (
                                <div className="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                    <p className="font-medium text-slate-900 dark:text-white">{selectedTask.title}</p>
                                    <p className="mt-1">Plan: {selectedTask.execution_plan?.title || "Not linked"}</p>
                                    <p className="mt-1">Supervisor: {selectedTask.supervisor?.name || "Not assigned"}</p>
                                </div>
                            ) : null}
                            <PrimaryButton disabled={progressForm.processing}>
                                {progressForm.processing ? "Saving..." : "Update Task Progress"}
                            </PrimaryButton>
                        </form>
                    ) : (
                        <EmptyState title="No progress targets yet." description="Once tasks exist, supervisors and site staff can keep progress updated from here and from mobile." />
                    )}
                </SectionCard>
            </div>

            <SectionCard title="Daily Progress Report" description="Capture project-linked DPR entries with optional GPS and line-item details.">
                {projects.length ? (
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const payload = {
                                ...reportForm.data,
                                items: [
                                    {
                                        execution_task_id: reportForm.data.execution_task_id || null,
                                        title: reportForm.data.summary || "Site progress update",
                                        description: reportForm.data.work_completed || null,
                                        completed_quantity: reportForm.data.workforce_count || 0,
                                        percent_complete: reportForm.data.execution_task_id
                                            ? progressForm.data.progress_percent || 0
                                            : 0,
                                        remarks: reportForm.data.blockers || null,
                                    },
                                ],
                            };

                            reportForm.transform(() => payload).post(route(`${routePrefix}.reports.store`), {
                                preserveScroll: true,
                                forceFormData: true,
                                onFinish: () => reportForm.transform((data) => data),
                            });
                        }}
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <SelectInput
                                label="Project"
                                value={reportForm.data.project_id}
                                onChange={(value) => {
                                    const firstTask = taskOptionsByProject[String(value)]?.[0];
                                    reportForm.setData((data) => ({
                                        ...data,
                                        project_id: value,
                                        execution_task_id: firstTask ? String(firstTask.id) : "",
                                    }));
                                }}
                                options={projects.map((project) => ({
                                    value: String(project.id),
                                    label: `${project.project_code} • ${project.name}`,
                                }))}
                                error={reportForm.errors.project_id}
                            />
                            <SelectInput
                                label="Execution Task"
                                value={reportForm.data.execution_task_id}
                                onChange={(value) => reportForm.setData("execution_task_id", value)}
                                options={[
                                    { value: "", label: "Project-level report" },
                                    ...taskOptionsForSelectedProject.map((task) => ({
                                        value: String(task.id),
                                        label: `${task.task_code} • ${task.title}`,
                                    })),
                                ]}
                                error={reportForm.errors.execution_task_id}
                            />
                            <TextInput label="Report Date" type="date" value={reportForm.data.report_date} onChange={(value) => reportForm.setData("report_date", value)} error={reportForm.errors.report_date} />
                        </div>
                        <TextAreaInput label="Summary" value={reportForm.data.summary} onChange={(value) => reportForm.setData("summary", value)} error={reportForm.errors.summary} rows={3} />
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextAreaInput label="Work Completed" value={reportForm.data.work_completed} onChange={(value) => reportForm.setData("work_completed", value)} error={reportForm.errors.work_completed} rows={4} />
                            <TextAreaInput label="Blockers" value={reportForm.data.blockers} onChange={(value) => reportForm.setData("blockers", value)} error={reportForm.errors.blockers} rows={4} />
                        </div>
                        <div className="grid gap-4 md:grid-cols-4">
                            <TextInput label="Workforce Count" type="number" value={reportForm.data.workforce_count} onChange={(value) => reportForm.setData("workforce_count", value)} error={reportForm.errors.workforce_count} />
                            <TextInput label="Latitude" value={reportForm.data.latitude} onChange={(value) => reportForm.setData("latitude", value)} error={reportForm.errors.latitude} />
                            <TextInput label="Longitude" value={reportForm.data.longitude} onChange={(value) => reportForm.setData("longitude", value)} error={reportForm.errors.longitude} />
                            <TextInput label="GPS Accuracy (m)" type="number" value={reportForm.data.gps_accuracy_meters} onChange={(value) => reportForm.setData("gps_accuracy_meters", value)} error={reportForm.errors.gps_accuracy_meters} />
                        </div>
                        <TextInput label="Weather Summary" value={reportForm.data.weather_summary} onChange={(value) => reportForm.setData("weather_summary", value)} error={reportForm.errors.weather_summary} />
                        <FileInput label="Supporting Document" onChange={(file) => reportForm.setData("supporting_document", file)} error={reportForm.errors.supporting_document} />
                        <PrimaryButton disabled={reportForm.processing}>
                            {reportForm.processing ? "Submitting..." : "Submit Daily Progress Report"}
                        </PrimaryButton>
                    </form>
                ) : (
                    <EmptyState title="No projects available for DPR." description="Projects and tasks must exist before DPR tracking can start." />
                )}
            </SectionCard>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Execution Plans" description="All plans currently active or queued for execution.">
                    {executionPlans.length ? (
                        <div className="space-y-4">
                            {executionPlans.map((plan) => (
                                <div key={plan.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{plan.title}</p>
                                            <p className="text-sm text-slate-500">{plan.plan_code} • {plan.project?.name || "Unknown project"}</p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <StatusBadge value={plan.status} />
                                            <StatusBadge value={plan.actual_progress_percent >= 100 ? "approved" : "in_progress"} />
                                        </div>
                                    </div>
                                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">{plan.description || "No plan description provided."}</p>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                        <MiniMetric label="Planned Start" value={formatDate(plan.planned_start_date) || "-"} />
                                        <MiniMetric label="Planned End" value={formatDate(plan.planned_end_date) || "-"} />
                                        <MiniMetric label="Progress" value={`${plan.actual_progress_percent || 0}%`} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No execution plans yet." description="Create the first execution plan to move the project into Phase 3." />
                    )}
                </SectionCard>

                <SectionCard title="Task Board" description="Live view of task progress, supervision, and assignee coverage.">
                    {executionTasks.length ? (
                        <div className="space-y-4">
                            {executionTasks.map((task) => (
                                <div key={task.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{task.title}</p>
                                            <p className="text-sm text-slate-500">{task.task_code} • {task.project?.name || "Unknown project"}</p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <StatusBadge value={task.priority} />
                                            <StatusBadge value={task.status} />
                                        </div>
                                    </div>
                                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">{task.description || "No task description provided."}</p>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                        <MiniMetric label="Progress" value={`${task.progress_percent || 0}%`} />
                                        <MiniMetric label="Supervisor" value={task.supervisor?.name || "-"} />
                                        <MiniMetric label="Assignees" value={task.assignees?.length || 0} />
                                    </div>
                                    {task.assignees?.length ? (
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {task.assignees.map((assignee) => (
                                                <span key={assignee.id} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                    {assignee.member?.name || "Unknown"} • {assignee.assignment_role}
                                                </span>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No execution tasks yet." description="Tasks will appear here after you break the execution plan into site work packages." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Daily Progress Queue" description="Review submitted DPR items and keep the audit trail moving.">
                    {dailyProgressReports.length ? (
                        <div className="space-y-4">
                            {dailyProgressReports.map((report) => (
                                <div key={report.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{report.project?.name || "Unknown project"}</p>
                                            <p className="text-sm text-slate-500">{formatDate(report.report_date)} • {report.execution_task?.title || "Project-level report"}</p>
                                        </div>
                                        <StatusBadge value={report.status} />
                                    </div>
                                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">{report.summary || report.work_completed || "No summary provided."}</p>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                        <MiniMetric label="Submitted By" value={report.submitted_by?.name || "-"} />
                                        <MiniMetric label="Items" value={report.items?.length || 0} />
                                    </div>
                                    {report.supporting_document ? (
                                        <div className="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                            <span>{report.supporting_document.original_name}</span>
                                            <a
                                                href={route(`${documentRouteBase}.view`, report.supporting_document.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"
                                            >
                                                View
                                            </a>
                                            <a
                                                href={route(`${documentRouteBase}.download`, report.supporting_document.id)}
                                                className="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                                            >
                                                Download
                                            </a>
                                        </div>
                                    ) : null}
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                                            onClick={() => {
                                                router.post(route(`${routePrefix}.reports.review`, report.id), {
                                                    status: "approved",
                                                    review_notes: report.review_notes || "",
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            className="rounded-xl bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-400"
                                            onClick={() => {
                                                router.post(route(`${routePrefix}.reports.review`, report.id), {
                                                    status: "revision_requested",
                                                    review_notes: "Please update details and resubmit.",
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            Request Revision
                                        </button>
                                        <button
                                            type="button"
                                            className="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500"
                                            onClick={() => {
                                                router.post(route(`${routePrefix}.reports.review`, report.id), {
                                                    status: "rejected",
                                                    review_notes: "Rejected during execution review.",
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No DPR submissions yet." description="Once site teams submit DPRs, they will be listed here for review." />
                    )}
                </SectionCard>

                <SectionCard title="Attendance Review Queue" description="Approve or reject GPS-based attendance records tied to execution work.">
                    {attendanceRecords.length ? (
                        <div className="space-y-4">
                            {attendanceRecords.map((attendance) => (
                                <div key={attendance.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{attendance.member?.name || "Unknown member"}</p>
                                            <p className="text-sm text-slate-500">{attendance.project?.name || "Unknown project"} • {formatDate(attendance.attendance_date)}</p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <StatusBadge value={attendance.attendance_type} />
                                            <StatusBadge value={attendance.status} />
                                        </div>
                                    </div>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                        <MiniMetric label="Task" value={attendance.execution_task?.title || "-"} />
                                        <MiniMetric label="GPS Accuracy" value={attendance.gps_accuracy_meters ? `${attendance.gps_accuracy_meters} m` : "-"} />
                                        <MiniMetric label="Check In" value={formatDateTime(attendance.check_in_at) || "-"} />
                                        <MiniMetric label="Check Out" value={formatDateTime(attendance.check_out_at) || "-"} />
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                                            onClick={() => {
                                                router.post(route(`${routePrefix}.attendance.review`, attendance.id), {
                                                    status: "approved",
                                                    review_notes: attendance.review_notes || "",
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            className="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500"
                                            onClick={() => {
                                                router.post(route(`${routePrefix}.attendance.review`, attendance.id), {
                                                    status: "rejected",
                                                    review_notes: "Rejected during attendance review.",
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No attendance records yet." description="Execution attendance records will show up here after site check-ins start." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function formatDate(dateString) {
    if (!dateString) return null;
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString("en-IN", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function formatDateTime(dateString) {
    if (!dateString) return null;
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return dateString;
    return date.toLocaleString("en-IN", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function PrimaryButton({ children, disabled = false }) {
    return (
        <button
            type="submit"
            disabled={disabled}
            className="rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
        >
            {children}
        </button>
    );
}

function FileInput({ label, error, onChange }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <input
                type="file"
                onChange={(event) => onChange(event.target.files?.[0] || null)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:file:bg-slate-900 dark:file:text-slate-200"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function MiniMetric({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">
            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{label}</p>
            <p className="mt-2 text-sm font-medium text-slate-900 dark:text-white">{value || "-"}</p>
        </div>
    );
}

function TextInput({ label, error, onChange, ...props }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <input
                {...props}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function TextAreaInput({ label, error, onChange, ...props }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <textarea
                {...props}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function SelectInput({ label, error, value, onChange, options }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
                {options.length ? null : <option value="">No options available</option>}
                {options.map((option) => (
                    <option key={`${option.value}-${option.label}`} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function MultiSelectInput({ label, error, value, onChange, options }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <select
                multiple
                value={value}
                onChange={(event) => {
                    const selectedValues = Array.from(event.target.selectedOptions).map((option) => option.value);
                    onChange(selectedValues);
                }}
                className="min-h-32 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
                {options.map((option) => (
                    <option key={`${option.value}-${option.label}`} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function CheckboxInput({ label, checked, onChange }) {
    return (
        <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            <span>{label}</span>
        </label>
    );
}
