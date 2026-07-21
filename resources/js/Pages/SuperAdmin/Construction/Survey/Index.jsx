import { useForm } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function SurveyIndex({ surveyPlans, surveySubmissions, projects, members }) {
    const documentRouteBase = "super.construction.documents";
    const planForm = useForm({
        project_id: projects[0]?.id || "",
        title: "",
        description: "",
        site_address: "",
        site_latitude: "",
        site_longitude: "",
        planned_date: "",
        planned_start_time: "",
        planned_end_time: "",
        member_ids: [],
    });

    const stats = {
        totalPlans: surveyPlans.length,
        planned: surveyPlans.filter((item) => item.status === "planned").length,
        inProgress: surveyPlans.filter((item) => item.status === "in_progress").length,
        pendingReview: surveySubmissions.filter((item) => item.status === "submitted").length,
        revisionRequested: surveySubmissions.filter((item) => item.status === "revision_requested").length,
        approved: surveySubmissions.filter((item) => item.status === "approved").length,
    };

    return (
        <ConstructionShell title="Survey Workflow" description="Plan survey assignments, monitor field execution, verify GPS-backed visits, and clear data for drafting." variant="super">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard label="Survey Plans" value={stats.totalPlans} />
                <StatCard label="Planned" value={stats.planned} />
                <StatCard label="In Progress" value={stats.inProgress} />
                <StatCard label="Pending Review" value={stats.pendingReview} />
                <StatCard label="Revision Asked" value={stats.revisionRequested} />
                <StatCard label="Approved" value={stats.approved} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[430px,1fr]">
                <SectionCard title="Create Survey Plan" description="Assign the project, site details, schedule, and survey team in one step.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            planForm.post(route("super.construction.survey.plans.store"), { preserveScroll: true });
                        }}
                        className="space-y-4"
                    >
                        <SelectField form={planForm} name="project_id" label="Project" options={projects.map((project) => ({
                            value: project.id,
                            label: `${project.project_code} • ${project.name}`,
                        }))} />
                        <InputField form={planForm} name="title" label="Survey Title" />
                        <TextAreaField form={planForm} name="description" label="Survey Scope" rows={4} />
                        <TextAreaField form={planForm} name="site_address" label="Site Address" rows={3} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <InputField form={planForm} name="site_latitude" label="Latitude" />
                            <InputField form={planForm} name="site_longitude" label="Longitude" />
                            <InputField form={planForm} name="planned_date" label="Planned Date" type="date" />
                            <InputField form={planForm} name="planned_start_time" label="Start Time" type="time" />
                        </div>
                        <InputField form={planForm} name="planned_end_time" label="End Time" type="time" />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Survey Team</label>
                            <select
                                multiple
                                value={planForm.data.member_ids}
                                onChange={(e) => planForm.setData("member_ids", Array.from(e.target.selectedOptions, (option) => option.value))}
                                className="min-h-40 w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                {members.map((member) => (
                                    <option key={member.id} value={member.id}>{member.name}{member.email ? ` • ${member.email}` : ""}</option>
                                ))}
                            </select>
                            {planForm.errors.member_ids ? <p className="mt-1 text-xs text-rose-600">{planForm.errors.member_ids}</p> : null}
                        </div>
                        <button type="submit" disabled={planForm.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {planForm.processing ? "Creating..." : "Create Survey Plan"}
                        </button>
                    </form>
                </SectionCard>

                <div className="space-y-6">
                    <SectionCard title="Survey Plans" description="Plan register with assigned members and current field status.">
                        {surveyPlans.length ? (
                            <div className="space-y-4">
                                {surveyPlans.map((plan) => (
                                    <div key={plan.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold text-slate-900 dark:text-white">{plan.title}</p>
                                                <p className="text-sm text-slate-500">{plan.project?.name} • {plan.survey_code}</p>
                                                <p className="mt-1 text-sm text-slate-500">{plan.site_address || "No site address set."}</p>
                                            </div>
                                            <StatusBadge value={plan.status} />
                                        </div>
                                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                                            <MetaPill label="Planned Date" value={plan.planned_date} />
                                            <MetaPill label="Window" value={[plan.planned_start_time, plan.planned_end_time].filter(Boolean).join(" - ") || "Not set"} />
                                        </div>
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {(plan.plan_members || []).map((item) => (
                                                <span key={item.id} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                    {item.member?.name || "Unknown"} • {item.role_in_survey}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No survey plans yet." description="Create the first plan to start the Phase 2 survey workflow." />
                        )}
                    </SectionCard>

                    <SectionCard title="Submission Review Queue" description="Review field submissions, GPS compliance, captured data, and revision requests.">
                        {surveySubmissions.length ? (
                            <div className="space-y-4">
                                {surveySubmissions.map((submission) => (
                                    <SubmissionReviewCard key={submission.id} submission={submission} routeName="super.construction.survey.submissions.review" documentRouteBase={documentRouteBase} />
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No survey submissions yet." description="Surveyors need to check in and submit a survey visit from mobile first." />
                        )}
                    </SectionCard>
                </div>
            </div>
        </ConstructionShell>
    );
}

function SubmissionReviewCard({ submission, routeName, documentRouteBase }) {
    const form = useForm({
        status: submission.status === "submitted" ? "approved" : submission.status,
        review_notes: submission.review_notes || "",
    });

    const visit = submission.survey_visit;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route(routeName, submission.id), { preserveScroll: true });
            }}
            className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold text-slate-900 dark:text-white">{submission.project?.name || "Unknown project"}</p>
                    <p className="text-sm text-slate-500">Submitted by {submission.submitted_by?.name || "Unknown"} • Visit #{submission.survey_visit_id}</p>
                </div>
                <div className="flex gap-2">
                    <StatusBadge value={submission.status} />
                    <StatusBadge value={visit?.gps_verified ? "approved" : "revision_requested"} />
                </div>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-3">
                <MetaPill label="Checked In By" value={visit?.checked_in_by?.name || "Unknown"} />
                <MetaPill label="Entries" value={String(visit?.entries?.length || 0)} />
                <MetaPill label="Measurements" value={String(visit?.measurements?.length || 0)} />
            </div>
            {visit?.entries?.length ? (
                <div className="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                    {visit.entries.map((entry) => (
                        <div key={entry.id} className="flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <span>{entry.entry_type} • {entry.title}</span>
                            {entry.supporting_document ? (
                                <>
                                    <a
                                        href={route(`${documentRouteBase}.view`, entry.supporting_document.id)}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                    >
                                        View
                                    </a>
                                    <a
                                        href={route(`${documentRouteBase}.download`, entry.supporting_document.id)}
                                        className="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                                    >
                                        Download
                                    </a>
                                </>
                            ) : (
                                <span className="text-xs text-slate-400">No attachment</span>
                            )}
                        </div>
                    ))}
                </div>
            ) : null}

            <div className="mt-4 grid gap-3 sm:grid-cols-[180px,1fr,160px]">
                <select
                    value={form.data.status}
                    onChange={(e) => form.setData("status", e.target.value)}
                    className="rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                >
                    <option value="approved">Approve</option>
                    <option value="revision_requested">Request Revision</option>
                    <option value="rejected">Reject</option>
                </select>
                <input
                    value={form.data.review_notes}
                    onChange={(e) => form.setData("review_notes", e.target.value)}
                    placeholder="Review notes"
                    className="rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                />
                <button type="submit" disabled={form.processing} className="rounded-xl bg-slate-900 px-4 py-3 font-medium text-white hover:bg-slate-800 disabled:opacity-60 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                    {form.processing ? "Updating..." : "Update Review"}
                </button>
            </div>
            {form.errors.status ? <p className="mt-2 text-xs text-rose-600">{form.errors.status}</p> : null}
            {form.errors.review_notes ? <p className="mt-1 text-xs text-rose-600">{form.errors.review_notes}</p> : null}
        </form>
    );
}

function InputField({ form, name, label, type = "text" }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input
                type={type}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
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

function SelectField({ form, name, label, options }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <select
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                ))}
            </select>
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function MetaPill({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-medium text-slate-900 dark:text-white">{value || "-"}</p>
        </div>
    );
}
