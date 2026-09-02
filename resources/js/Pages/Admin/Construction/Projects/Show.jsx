import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import WorkflowTracker from "@/Pages/Construction/Components/WorkflowTracker";
import DynamicChecklistManager from "@/Pages/Construction/Components/DynamicChecklistManager";

export default function ProjectShow({ project, activityLog, workflowSummary }) {
    const checklistCounts = workflowSummary?.checklist_counts ?? { total: 0, completed: 0 };
    const checklistCompletion = checklistCounts.total > 0
        ? Math.round((checklistCounts.completed / checklistCounts.total) * 100)
        : 0;
    return (
        <ConstructionShell title={project.name} description={`${project.project_code} • ${project.company?.name || ""}`} variant="admin">
            <WorkflowTracker currentStage={project.current_stage} />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard label="Team Members" value={project.team_members?.length || 0} />
                <StatCard label="Budget Versions" value={project.budgets?.length || 0} />
                <StatCard label="Survey Plans" value={project.survey_plans?.length || 0} />
                <StatCard label="Submissions" value={project.survey_submissions?.length || 0} />
                <StatCard label="Drafting Jobs" value={project.drafting_jobs?.length || 0} />
                <StatCard label="Approvals" value={project.drawing_approvals?.length || 0} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                <SectionCard title="Project Summary" description="Current project context for assigned admin execution.">
                    <div className="flex flex-wrap gap-3">
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

                <SectionCard title="Current Team" description="Assigned roles for project execution and reviews.">
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
                        <EmptyState title="No team members assigned." description="Ask super admin to complete Phase 1 role assignment before execution." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Survey Execution" description="Plan status, field activity, and review outcomes.">
                    {project.survey_plans?.length ? (
                        <div className="space-y-4">
                            {project.survey_plans.map((plan) => (
                                <div key={plan.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{plan.title}</p>
                                            <p className="text-sm text-slate-500">{plan.survey_code} • {formatDate(plan.planned_date) || "No date"}</p>
                                            <p className="mt-1 text-sm text-slate-500">{plan.site_address || "No site address"}</p>
                                        </div>
                                        <StatusBadge value={plan.status_key} />
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {(plan.plan_members || []).map((item) => (
                                            <span key={item.id} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                {item.member?.name || "Unknown"} • {item.role_in_survey}
                                            </span>
                                        ))}
                                    </div>
                                    <div className="mt-4 space-y-2 text-sm text-slate-500">
                                        {(plan.visits || []).map((visit) => (
                                            <div key={visit.id} className="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">
                                                <div className="flex items-center justify-between gap-3">
                                                    <p className="font-medium text-slate-900 dark:text-white">Visit #{visit.id}</p>
                                                    <StatusBadge value={visit.status_key} />
                                                </div>
                                                <p className="mt-1">Entries: {visit.entries?.length || 0} • Measurements: {visit.measurements?.length || 0}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey plans yet." description="Create the first survey plan from the survey module." />
                    )}
                </SectionCard>

                <SectionCard title="Drafting & Approval" description="Downstream drafting workload and current approval decisions.">
                    {project.drafting_jobs?.length ? (
                        <div className="space-y-4">
                            {project.drafting_jobs.map((job) => (
                                <div key={job.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">Drafting Job #{job.id}</p>
                                            <p className="text-sm text-slate-500">Assigned to {job.assigned_to?.name || "Unassigned"} • Due {formatDate(job.due_date) || "Not set"}</p>
                                        </div>
                                        <StatusBadge value={job.status} />
                                    </div>
                                    <div className="mt-4 space-y-2">
                                        {(job.drawing_revisions || []).map((revision) => (
                                            <div key={revision.id} className="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">
                                                <div className="flex items-center justify-between gap-3">
                                                    <p className="font-medium text-slate-900 dark:text-white">Revision {revision.revision_no}</p>
                                                    <StatusBadge value={revision.status} />
                                                </div>
                                                <p className="mt-1 text-sm text-slate-500">{revision.pdf_document?.original_name || revision.dwg_document?.original_name || "No documents yet"}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drafting jobs yet." description="Approved survey submissions will be moved into drafting next." />
                    )}
                </SectionCard>
            </div>

            <DynamicChecklistManager
                project={project}
                namespace="admin"
                variant="project-show"
                enableDeltaPoll
                canManage={true}
                initialCounts={{ total: checklistCounts.total, completed: checklistCounts.completed, completion: checklistCompletion }}
            />

            <SectionCard title="Project Activity" description="Recent workflow actions for this assigned project.">
                {activityLog?.length ? (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {activityLog.map((item) => (
                            <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-medium text-slate-900 dark:text-white">{item.module}</p>
                                    <StatusBadge value={item.action} />
                                </div>
                                <p className="mt-2 text-sm text-slate-500">{item.actor?.name || item.actor?.email || "System"}</p>
                                <p className="mt-1 text-xs text-slate-500">{formatDate(item.created_at)}</p>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState title="No activity yet." description="Activity logs will appear here as the project advances through the workflow." />
                )}
            </SectionCard>
        </ConstructionShell>
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
