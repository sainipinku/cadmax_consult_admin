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
                            {project.team_members.map((teamMember) => {
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

                                return (
                                    <div key={teamMember.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-slate-900 dark:text-white">{teamMember.member?.name || "Unknown member"}</p>
                                                <p className="text-sm text-slate-500">{teamMember.role?.name || "No role assigned"}</p>
                                            </div>
                                            <StatusBadge value={teamMember.status} />
                                        </div>

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
                                );
                            })}
                        </div>
                    ) : (
                        <EmptyState title="No team members assigned." description="Ask super admin to complete Phase 1 role assignment before execution." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Survey Execution" description="Plan status, submitted GPS/photos/reports, and field activity.">
                    {project.survey_plans?.length ? (
                        <div className="space-y-4">
                            {project.survey_plans.map((plan) => (
                                <div key={plan.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-slate-900 dark:text-white truncate">{plan.title}</p>
                                            <p className="text-sm text-slate-500">{plan.survey_code} • {formatDate(plan.planned_date) || "No date"}</p>
                                            <p className="mt-1 text-sm text-slate-500 break-words">{plan.site_address || "No site address"}</p>
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
                                    <div className="mt-4 space-y-3">
                                        {(plan.visits || []).length ? plan.visits.map((visit) => (
                                            <SurveyVisitCard key={visit.id} visit={visit} />
                                        )) : (
                                            <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-3 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-950">
                                                No check-in visits captured yet for this plan.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey plans yet." description="Create the first survey plan from the survey module." />
                    )}
                </SectionCard>

                <SectionCard title="Survey Submission Queue" description="Submitted field-duty reports pending/approved before drafting.">
                    {project.survey_submissions?.length ? (
                        <div className="space-y-3">
                            {project.survey_submissions.map((submission) => (
                                <div key={submission.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-medium text-slate-900 dark:text-white">Visit #{submission.survey_visit_id}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Submitted by <span className="font-medium text-slate-700 dark:text-slate-200">{submission.submitted_by?.name || "Unknown"}</span>{" "}
                                                • {formatDateTime(submission.submitted_at) || "No timestamp"}
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
                                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                        {submission.review_notes || "No review notes yet."}
                                    </p>
                                    {submission.survey_visit ? (
                                        <div className="mt-4 grid gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500 sm:grid-cols-2 dark:border-slate-700">
                                            <Metric label="Points" value={submission.survey_visit.total_points_captured} />
                                            <Metric label="Distance (m)" value={formatNumber(submission.survey_visit.distance_covered_m)} />
                                            <Metric label="Elevation (m)" value={formatNumber(submission.survey_visit.elevation_m)} />
                                            <Metric label="Day" value={submission.survey_visit.day_number} />
                                            <Metric label="Checked in" value={formatDateTime(submission.survey_visit.check_in_at)} span="sm:col-span-2" />
                                            <FileAttachment path={submission.survey_visit.file_path} span="sm:col-span-2" />
                                        </div>
                                    ) : null}
                                    {submission.survey_visit?.photos?.length ? (
                                        <PhotoGrid photos={submission.survey_visit.photos} className="mt-3" />
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey submissions yet." description="Submitted field-duty reports (from POST /survey-duty/submit-data) appear here with their GPS, photos, remarks, and captured file." />
                    )}
                </SectionCard>

                <div className="xl:col-span-2">
                    <SectionCard title="Drafting & Approval" description="Downstream drafting workload and current approval decisions.">
                        {project.drafting_jobs?.length ? (
                            <div className="space-y-4">
                                {project.drafting_jobs.map((job) => (
                                    <div key={job.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
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

function formatDateTime(value) {
    if (!value) return "";
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleString("en-GB", {
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

function Metric({ label, value, span = "" }) {
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
                        Check-in by <span className="font-medium text-slate-700 dark:text-slate-200">{visit.checked_in_by?.name || visit.checkedInBy?.name || "Unknown"}</span>
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
                    ) : null}
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
                <Metric label="Total Points" value={visit.total_points_captured} />
                <Metric label="Distance (m)" value={formatNumber(visit.distance_covered_m)} />
                <Metric label="Elevation (m)" value={formatNumber(visit.elevation_m)} />
                <Metric label="Entries / Meas." value={`${visit.entries?.length || 0} / ${visit.measurements?.length || 0}`} />
                <Metric label="GPS Latitude" value={visit.check_in_latitude ? Number(visit.check_in_latitude).toFixed(6) : undefined} />
                <Metric label="GPS Longitude" value={visit.check_in_longitude ? Number(visit.check_in_longitude).toFixed(6) : undefined} />
                <FileAttachment path={visit.file_path} span="sm:col-span-2" />
            </div>

            {visit.remarks || visit.notes ? (
                <div className="mt-3 rounded-xl bg-amber-50/70 p-3 text-xs text-amber-900 dark:bg-amber-950/20 dark:text-amber-100">
                    {visit.remarks ? (
                        <div>
                            <p className="font-semibold">Remarks</p>
                            <p className="mt-1 whitespace-pre-wrap break-words">{visit.remarks}</p>
                        </div>
                    ) : null}
                    {visit.notes ? (
                        <div className="mt-2">
                            <p className="font-semibold">Field Notes</p>
                            <p className="mt-1 whitespace-pre-wrap break-words">{visit.notes}</p>
                        </div>
                    ) : null}
                </div>
            ) : null}

            <PhotoGrid photos={visit.photos} className="mt-4" />
        </div>
    );
}
