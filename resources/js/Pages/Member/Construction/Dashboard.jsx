import { Link } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function Dashboard({ stats, projects, tasks }) {
    return (
        <ConstructionShell
            title="Site Member Dashboard"
            description="Your assigned construction projects, field tasks, and daily execution flow."
            variant="member"
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Assigned Projects" value={stats.assignedProjects} />
                <StatCard label="Active Tasks" value={stats.activeTasks} />
                <StatCard label="Completed Tasks" value={stats.completedTasks} />
                <StatCard label="Open Attendance" value={stats.openAttendance} />
                <StatCard label="Submitted DPR" value={stats.submittedReports} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.25fr,1fr]">
                <SectionCard
                    title="Assigned Projects"
                    description="Projects connected to your current team assignment."
                    actions={
                        <Link
                            href={route("member.construction.projects.index")}
                            className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            Open Projects
                        </Link>
                    }
                >
                    {projects.length ? (
                        <div className="space-y-4">
                            {projects.map((project) => (
                                <Link
                                    key={project.id}
                                    href={route("member.construction.projects.show", project.id)}
                                    className="block rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-300 dark:border-slate-800"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{project.name}</p>
                                            <p className="text-sm text-slate-500">{project.project_code}</p>
                                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                                {project.client?.name || "-"} • {project.company?.name || "-"}
                                            </p>
                                        </div>
                                        <StatusBadge value={project.current_stage} />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No assigned projects."
                            description="Once you are added to a construction project team, it will appear here."
                        />
                    )}
                </SectionCard>

                <SectionCard
                    title="My Site Tasks"
                    description="Daily execution items assigned to you."
                    actions={
                        <Link
                            href={route("member.construction.execution.index")}
                            className="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            Open Execution
                        </Link>
                    }
                >
                    {tasks.length ? (
                        <div className="space-y-4">
                            {tasks.map((task) => (
                                <div key={task.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{task.title}</p>
                                            <p className="text-sm text-slate-500">{task.task_code} • {task.project?.name || "-"}</p>
                                            <p className="mt-1 text-sm text-slate-500">{task.execution_plan?.title || "No plan linked"}</p>
                                        </div>
                                        <StatusBadge value={task.status} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No site tasks assigned."
                            description="Task assignments will appear here after project execution planning."
                        />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}
