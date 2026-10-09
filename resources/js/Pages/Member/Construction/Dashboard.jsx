import { usePage } from "@inertiajs/react";
import { useEffect } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import RoleSwitcher from "./Components/RoleSwitcher";
import ProjectSwitcher from "./Components/ProjectSwitcher";

export default function Dashboard({
    member,
    available_roles,
    projects,
    permissions,
    active_role,
    active_project,
    dashboard,
}) {
    const { props } = usePage();
    const auth = props?.auth;

    useEffect(() => {
        console.log("=== [DEBUG Member Dashboard Props] ===");
        console.log("Inertia Props:", props);
        console.log("Passed permissions prop:", permissions);
        console.log("Auth User:", auth?.user);
        console.log("Auth Guard:", auth?.guard);
        console.log("Auth Permissions:", auth?.permissions);
        console.log("Active Role:", active_role);
    }, [props]);
    const hasMultipleProjects = projects?.length > 1;
    const hasMultipleRoles = available_roles?.length > 1;

    return (
        <ConstructionShell
            title="Site Member Dashboard"
            description="Your active construction project and role context."
            variant="member"
        >
            <div className="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <p className="font-semibold text-slate-900 dark:text-white">{member?.name}</p>
                    <p className="text-sm text-slate-500">
                        {active_project ? active_project.name : "No project selected"}
                    </p>
                    {active_role ? (
                        <p className="text-sm text-slate-500">
                            Working as: <span className="font-medium">{active_role.name}</span>
                        </p>
                    ) : null}
                </div>
                <div className="ml-auto flex flex-wrap items-center gap-4">
                    {hasMultipleProjects ? (
                        <ProjectSwitcher projects={projects} activeProject={active_project} />
                    ) : null}
                    {hasMultipleRoles ? (
                        <RoleSwitcher
                            available_roles={available_roles}
                            activeRole={active_role}
                            activeProject={active_project}
                        />
                    ) : null}
                </div>
            </div>

            {!active_project ? (
                <SectionCard
                    title="Select a project to continue"
                    description="Choose a project to load your work context."
                >
                    <EmptyState
                        title="No project selected."
                        description="Use the Project switcher above to continue."
                    />
                </SectionCard>
            ) : !active_role ? (
                <SectionCard
                    title="Select your role to continue"
                    description="Choose the role you are working as on this project."
                >
                    <EmptyState
                        title="No role selected."
                        description="Use the Working As switcher above to continue."
                    />
                </SectionCard>
            ) : (
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {dashboard?.assigned_surveys !== undefined && (
                            <StatCard
                                label="Assigned Surveys"
                                value={dashboard.assigned_surveys}
                                color="sky"
                            />
                        )}
                        {dashboard?.assigned_vehicles !== undefined && (
                            <StatCard
                                label="Assigned Vehicles"
                                value={dashboard.assigned_vehicles?.length ?? 0}
                                color="emerald"
                            />
                        )}
                        {dashboard?.active_tasks !== undefined && (
                            <StatCard
                                label="Active Tasks"
                                value={dashboard.active_tasks}
                                color="indigo"
                            />
                        )}
                        {dashboard?.open_attendance !== undefined && (
                            <StatCard
                                label="Open Attendance"
                                value={dashboard.open_attendance}
                                color="amber"
                            />
                        )}
                        {dashboard?.submitted_reports !== undefined && (
                            <StatCard
                                label="Submitted Reports"
                                value={dashboard.submitted_reports}
                                color="violet"
                            />
                        )}
                        {dashboard?.drafting_jobs !== undefined && (
                            <StatCard
                                label="Drafting Jobs"
                                value={dashboard.drafting_jobs}
                                color="fuchsia"
                            />
                        )}
                        {dashboard?.pending_approvals !== undefined && (
                            <StatCard
                                label="Pending Approvals"
                                value={dashboard.pending_approvals}
                                color="rose"
                            />
                        )}
                    </div>

                    {dashboard?.assigned_vehicles?.length > 0 && (
                        <SectionCard
                            title="My Vehicles"
                            description="Vehicles assigned to you on this project."
                        >
                            <div className="space-y-3">
                                {dashboard.assigned_vehicles.map((assignment) => (
                                    <div
                                        key={assignment.id}
                                        className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950"
                                    >
                                        <p className="font-semibold text-slate-900 dark:text-white">
                                            {assignment.vehicle?.registration_number ?? "Vehicle"}
                                        </p>
                                        <p className="text-sm text-slate-500">
                                            {assignment.vehicle?.vehicle_code ?? ""}
                                        </p>
                                        <StatusBadge value={assignment.status} />
                                    </div>
                                ))}
                            </div>
                        </SectionCard>
                    )}
                </div>
            )}
        </ConstructionShell>
    );
}
