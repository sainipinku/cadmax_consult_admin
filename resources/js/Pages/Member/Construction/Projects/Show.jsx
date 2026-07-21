import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import WorkflowTracker from "@/Pages/Construction/Components/WorkflowTracker";

export default function ProjectShow({ project, activityLog }) {
    return (
        <ConstructionShell
            title={project.name}
            description={`${project.project_code} • ${project.company?.name || "-"}`}
            variant="member"
        >
            <WorkflowTracker currentStage={project.current_stage} />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Team Members" value={project.team_members?.length || 0} />
                <StatCard label="Execution Tasks" value={project.execution_tasks?.length || 0} />
                <StatCard label="DPR Records" value={project.daily_progress_reports?.length || 0} />
                <StatCard label="Attendance Records" value={project.attendance_records?.length || 0} />
                <StatCard label="Budget" value={project.latest_budget?.approved_amount || project.latest_budget?.estimated_amount || "-"} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.4fr,1fr]">
                <SectionCard title="Project Summary" description="Project-centric construction context for your site work.">
                    <div className="flex flex-wrap gap-3">
                        <StatusBadge value={project.status} />
                        <StatusBadge value={project.current_stage} />
                        <StatusBadge value={project.priority} />
                    </div>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <Field label="Client" value={project.client?.name} />
                        <Field label="Company" value={project.company?.name} />
                        <Field label="Address" value={project.project_address} span="sm:col-span-2" />
                        <Field label="Description" value={project.description} span="sm:col-span-2" />
                    </dl>
                </SectionCard>

                <SectionCard title="Assigned Team" description="People currently mapped to this project.">
                    {project.team_members?.length ? (
                        <div className="space-y-3">
                            {project.team_members.map((teamMember) => (
                                <div key={teamMember.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-white">{teamMember.member?.name || "Unknown member"}</p>
                                            <p className="text-sm text-slate-500">{teamMember.role?.name || "No role assigned"}</p>
                                        </div>
                                        <StatusBadge value={teamMember.status} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No team members listed." description="Project team assignment is still pending." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Execution Tasks" description="Current tasks linked to this project.">
                    {project.execution_tasks?.length ? (
                        <div className="space-y-4">
                            {project.execution_tasks.map((task) => (
                                <div key={task.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{task.title}</p>
                                            <p className="text-sm text-slate-500">{task.task_code} • {task.execution_plan?.title || "-"}</p>
                                            <p className="mt-1 text-sm text-slate-500">Supervisor: {task.supervisor?.name || "-"}</p>
                                        </div>
                                        <StatusBadge value={task.status} />
                                    </div>
                                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">{task.description || "No task description provided."}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No execution tasks yet." description="Execution tasks will appear after planning is completed." />
                    )}
                </SectionCard>

                <SectionCard title="Project Activity" description="Recent construction-only project activity.">
                    {activityLog?.length ? (
                        <div className="space-y-3">
                            {activityLog.map((item) => (
                                <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium text-slate-900 dark:text-white">{item.module}</p>
                                        <StatusBadge value={item.action} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">{item.actor?.name || item.actor?.email || "System"}</p>
                                    <p className="mt-1 text-xs text-slate-500">{item.created_at}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No activity yet." description="Activity logs will appear as the project moves through execution." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
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
