import { Link } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function Dashboard({ stats, projects }) {
    return (
        <ConstructionShell
            title="Assigned Project Dashboard"
            description="Admin view focused only on assigned construction projects and their lifecycle from survey through execution."
            variant="admin"
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Assigned Projects" value={stats.assignedProjects} />
                <StatCard label="Survey Workflow" value={stats.surveyPlans} />
                <StatCard label="Survey Upload Review" value={stats.surveyApprovalsPending} />
                <StatCard label="Drawing Approval Queue" value={stats.draftingQueue} />
                <StatCard label="Ready For Construction" value={stats.readyForConstruction} />
                <StatCard label="Execution Tasks" value={stats.executionTasks} />
                <StatCard label="DPR Review" value={stats.dprPending} />
                <StatCard label="Attendance Review" value={stats.attendancePending} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1fr,1.2fr]">
                <SectionCard
                    title="Assigned Flow"
                    description="Only project-linked actions relevant to admin execution are kept here."
                    actions={
                        <Link
                            href={route("admin.construction.projects.index")}
                            className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            Open Assigned Projects
                        </Link>
                    }
                >
                    <div className="space-y-4">
                        <Row
                            label="Project Creation to Budget Approval"
                            value={stats.assignedProjects}
                            note="Projects already assigned to you for downstream work."
                            badge="active"
                        />
                        <Row
                            label="Survey Planning to Survey Upload"
                            value={stats.surveyApprovalsPending}
                            note="Pending survey review and upload decisions."
                            badge="submitted"
                        />
                        <Row
                            label="Drafting to Drawing Approval"
                            value={stats.draftingQueue}
                            note="Drawing work and approvals still in flight."
                            badge="drafting_in_progress"
                        />
                        <Row
                            label="Construction Execution to Daily Progress"
                            value={stats.dprPending}
                            note="Execution reports needing follow-up."
                            badge="in_progress"
                        />
                    </div>
                </SectionCard>

                <SectionCard title="Assigned Projects" description="Direct access to the projects currently under your supervision.">
                    {projects.length ? (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-2">
                            {projects.map((project) => (
                                <Link
                                    key={project.id}
                                    href={route("admin.construction.projects.show", project.id)}
                                    className="rounded-2xl border border-slate-200 p-5 transition hover:border-indigo-300 dark:border-slate-800"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-semibold text-slate-900 dark:text-white">
                                            {project.name}
                                        </p>
                                        <StatusBadge value={project.current_stage} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">
                                        {project.project_code}
                                    </p>
                                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                                        {project.client?.name || "-"}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Budget:{" "}
                                        {project.latest_budget?.approved_amount ||
                                            project.latest_budget?.estimated_amount ||
                                            "-"}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No assigned projects."
                            description="Once project team assignment is complete, your construction work will appear here."
                        />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function Row({ label, value, note, badge }) {
    return (
        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="font-medium text-slate-900 dark:text-white">{label}</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {note}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <p className="text-2xl font-semibold text-slate-900 dark:text-white">
                        {value}
                    </p>
                    <StatusBadge value={badge} />
                </div>
            </div>
        </div>
    );
}
