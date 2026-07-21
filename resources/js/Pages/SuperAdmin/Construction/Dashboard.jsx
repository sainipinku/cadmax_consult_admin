import { Link } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

const lifecycleSteps = [
    "Client Registration",
    "Project Creation",
    "Budget Approval",
    "Employee Assignment",
    "Survey Planning",
    "Survey Execution",
    "GPS Verification",
    "Survey Upload",
    "Drafting",
    "Drawing Approval",
    "Construction Execution",
    "Daily Progress",
    "Material Management",
    "Vehicle Tracking",
    "Equipment Allocation",
    "Accounts",
    "Billing",
    "Client Handover",
    "Project Closure",
];

export default function Dashboard({ stats, recentProjects, recentActivity }) {
    return (
        <ConstructionShell
            title="Construction ERP Control Tower"
            description="Super Admin view limited to the construction project lifecycle, from client registration through site execution readiness."
            variant="super"
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Client Registration" value={stats.clients} />
                <StatCard label="Project Creation" value={stats.projects} />
                <StatCard label="Budget Approval Pending" value={stats.budgetPending} />
                <StatCard label="Employee Assignment Ready" value={stats.teamAssigned} />
                <StatCard label="Survey Workflow Active" value={stats.surveyPlanned} />
                <StatCard label="Survey Upload Review" value={stats.surveyApprovalsPending} />
                <StatCard label="Drawing Approval Queue" value={stats.draftingQueue} />
                <StatCard label="Ready For Construction" value={stats.readyForConstruction} />
                <StatCard label="Construction Tasks" value={stats.executionTasks} />
                <StatCard label="Daily Progress Review" value={stats.dprPending} />
                <StatCard label="Attendance Review" value={stats.attendancePending} />
                <StatCard label="Company Setup" value={stats.companies} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.6fr,1fr]">
                <SectionCard
                    title="Lifecycle Flow"
                    description="Only the construction ERP journey is shown in this portal."
                >
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {lifecycleSteps.map((step) => (
                            <div
                                key={step}
                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <p className="text-sm font-medium text-slate-900 dark:text-white">
                                    {step}
                                </p>
                            </div>
                        ))}
                    </div>
                </SectionCard>

                <SectionCard
                    title="Super Admin Controls"
                    description="Navigation grouped by the live ERP flow."
                    actions={
                        <Link
                            href={route("super.construction.projects.index")}
                            className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            Open Projects
                        </Link>
                    }
                >
                    <div className="space-y-3">
                        <QuickLink
                            label="Company Setup"
                            description="Create company records and base ERP ownership."
                            href={route("super.construction.companies.index")}
                        />
                        <QuickLink
                            label="Client Registration"
                            description="Register clients before project creation starts."
                            href={route("super.construction.clients.index")}
                        />
                        <QuickLink
                            label="Projects & Budget"
                            description="Create projects, approve budgets, and assign project teams."
                            href={route("super.construction.projects.index")}
                        />
                        <QuickLink
                            label="Survey Planning"
                            description="Schedule and review survey workflow."
                            href={route("super.construction.survey.index")}
                        />
                        <QuickLink
                            label="Drawing Approval"
                            description="Track drafting revisions and drawing approvals."
                            href={route("super.construction.drafting.index")}
                        />
                        <QuickLink
                            label="Construction Execution"
                            description="Monitor execution tasks, DPR, and attendance review."
                            href={route("super.construction.execution.index")}
                        />
                    </div>
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                <SectionCard title="Project Flow Snapshot" description="Current projects moving through the approved lifecycle.">
                    {recentProjects.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-slate-500">
                                    <tr>
                                        <th className="pb-3">Project</th>
                                        <th className="pb-3">Client</th>
                                        <th className="pb-3">Current Stage</th>
                                        <th className="pb-3">Budget</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {recentProjects.map((project) => (
                                        <tr key={project.id}>
                                            <td className="py-3">
                                                <Link
                                                    href={route("super.construction.projects.show", project.id)}
                                                    className="font-medium text-slate-900 hover:text-indigo-600 dark:text-white"
                                                >
                                                    {project.name}
                                                </Link>
                                                <div className="text-xs text-slate-500">
                                                    {project.project_code}
                                                </div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">
                                                {project.client?.name || "-"}
                                            </td>
                                            <td className="py-3">
                                                <StatusBadge value={project.current_stage} />
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">
                                                {project.latest_budget?.approved_amount ||
                                                    project.latest_budget?.estimated_amount ||
                                                    "-"}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState
                            title="No construction projects yet."
                            description="Start with client registration and project creation."
                        />
                    )}
                </SectionCard>

                <SectionCard title="Recent Lifecycle Activity" description="Recent construction-only actions across all projects.">
                    {recentActivity.length ? (
                        <div className="space-y-3">
                            {recentActivity.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium text-slate-900 dark:text-white">
                                            {item.module}
                                        </p>
                                        <StatusBadge value={item.action} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Project: {item.project?.name || "Global"}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {item.created_at}
                                    </p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No lifecycle activity yet."
                            description="Construction ERP activity will appear here as the flow starts moving."
                        />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function QuickLink({ label, description, href }) {
    return (
        <Link
            href={href}
            className="block rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-indigo-300 hover:bg-white dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-400"
        >
            <p className="font-medium text-slate-900 dark:text-white">{label}</p>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {description}
            </p>
        </Link>
    );
}
