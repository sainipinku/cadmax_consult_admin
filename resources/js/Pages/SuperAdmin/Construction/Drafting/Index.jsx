import { useForm } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function DraftingIndex({ draftingJobs, approvedSurveySubmissions, members }) {
    const jobForm = useForm({
        survey_submission_id: approvedSurveySubmissions[0]?.id || "",
        assigned_to_member_id: members[0]?.id || "",
        due_date: "",
    });

    const stats = {
        queue: draftingJobs.filter((job) => ["queued", "in_progress", "submitted"].includes(job.status)).length,
        revisions: draftingJobs.reduce((count, job) => count + (job.drawing_revisions?.length || 0), 0),
        pendingApprovals: draftingJobs.reduce(
            (count, job) => count + (job.drawing_revisions || []).flatMap((revision) => revision.approvals || []).filter((approval) => approval.decision === "pending").length,
            0
        ),
        readyForConstruction: draftingJobs.filter((job) => job.project?.current_stage === "ready_for_construction").length,
    };

    const documentRouteBase = "super.construction.documents";

    return (
        <ConstructionShell title="Drafting & Drawing Approval" description="Move approved survey data into drafting, upload revisions, and close drawing approvals." variant="super">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Drafting Queue" value={stats.queue} />
                <StatCard label="Revisions" value={stats.revisions} />
                <StatCard label="Pending Approvals" value={stats.pendingApprovals} />
                <StatCard label="Ready for Construction" value={stats.readyForConstruction} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[430px,1fr]">
                <SectionCard title="Create Drafting Job" description="Pick an approved survey submission and assign the drafting owner.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            jobForm.post(route("super.construction.drafting.jobs.store"), { preserveScroll: true });
                        }}
                        className="space-y-4"
                    >
                        <SelectField form={jobForm} name="survey_submission_id" label="Approved Survey Submission" options={approvedSurveySubmissions.map((submission) => ({
                            value: submission.id,
                            label: `${submission.project?.name || "Unknown"} • Submission #${submission.id}`,
                        }))} />
                        <SelectField form={jobForm} name="assigned_to_member_id" label="Assign Draft Person" options={members.map((member) => ({
                            value: member.id,
                            label: `${member.name}${member.email ? ` • ${member.email}` : ""}`,
                        }))} />
                        <InputField form={jobForm} name="due_date" label="Due Date" type="date" />
                        <button type="submit" disabled={jobForm.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {jobForm.processing ? "Creating..." : "Create Drafting Job"}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Drafting Queue" description="Revision uploads, approval requests, and decision history for all active jobs.">
                    {draftingJobs.length ? (
                        <div className="space-y-5">
                            {draftingJobs.map((job) => (
                                <DraftingJobCard key={job.id} job={job} members={members} variant="super" documentRouteBase={documentRouteBase} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drafting jobs yet." description="Create the first drafting job from an approved survey submission." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function DraftingJobCard({ job, variant, documentRouteBase }) {
    return (
        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-lg font-semibold text-slate-900 dark:text-white">{job.project?.name || "Unknown project"}</p>
                    <p className="text-sm text-slate-500">Assigned to {job.assigned_to?.name || "Unassigned"} • Due {job.due_date || "Not set"}</p>
                    <p className="mt-1 text-sm text-slate-500">Source submission #{job.survey_submission_id} • Submitted by {job.survey_submission?.submitted_by?.name || "Unknown"}</p>
                </div>
                <StatusBadge value={job.status} />
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-[360px,1fr]">
                <RevisionFormCard job={job} routeName={`${variant}.construction.drafting.revisions.store`} />
                <div className="space-y-4">
                    {(job.drawing_revisions || []).length ? (
                        job.drawing_revisions.map((revision) => (
                            <div key={revision.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-slate-900 dark:text-white">Revision {revision.revision_no}</p>
                                        <p className="text-sm text-slate-500">Uploaded by {revision.uploaded_by?.name || "Unknown"} • {revision.uploaded_at || "No upload time"}</p>
                                    </div>
                                    <StatusBadge value={revision.status} />
                                </div>
                                <div className="mt-3 grid gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <DocumentLinkRow label="DWG" document={revision.dwg_document} routeBase={documentRouteBase} />
                                    <DocumentLinkRow label="PDF" document={revision.pdf_document} routeBase={documentRouteBase} />
                                    <p>Notes: {revision.notes || "No notes added."}</p>
                                </div>
                                <div className="mt-4 space-y-3">
                                    {(revision.approvals || []).length ? (
                                        revision.approvals.map((approval) => (
                                            <ApprovalFormCard key={approval.id} approval={approval} routeName={`${variant}.construction.drafting.approvals.update`} />
                                        ))
                                    ) : (
                                        <EmptyState title="No approval record yet." description="Submitting a revision for approval creates the next decision step automatically." />
                                    )}
                                </div>
                            </div>
                        ))
                    ) : (
                        <EmptyState title="No revisions uploaded." description="Upload the first DWG/PDF revision from this job card." />
                    )}
                </div>
            </div>
        </div>
    );
}

function RevisionFormCard({ job, routeName }) {
    const form = useForm({
        notes: "",
        dwg_file: null,
        pdf_file: null,
        status: "submitted",
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route(routeName, job.id), { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset() });
            }}
            className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950"
        >
            <h3 className="font-semibold text-slate-900 dark:text-white">Upload Revision</h3>
            <div className="mt-3 space-y-3">
                <FileField form={form} name="dwg_file" label="DWG File" />
                <FileField form={form} name="pdf_file" label="PDF File" />
                <TextAreaField form={form} name="notes" label="Revision Notes" rows={4} />
                <SelectField form={form} name="status" label="Revision Status" options={[
                    { value: "draft", label: "Save as Draft" },
                    { value: "submitted", label: "Submit for Approval" },
                ]} />
                <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-slate-900 px-4 py-3 font-medium text-white hover:bg-slate-800 disabled:opacity-60 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                    {form.processing ? "Saving..." : "Save Revision"}
                </button>
            </div>
        </form>
    );
}

function ApprovalFormCard({ approval, routeName }) {
    const form = useForm({
        decision: approval.decision === "pending" ? "approved" : approval.decision,
        remarks: approval.remarks || "",
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route(routeName, approval.id), { preserveScroll: true });
            }}
            className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
        >
            <div className="flex items-center justify-between gap-3">
                <p className="font-medium text-slate-900 dark:text-white">Approval #{approval.id}</p>
                <StatusBadge value={approval.decision} />
            </div>
            <p className="mt-1 text-sm text-slate-500">Decision by {approval.approved_by?.name || "Pending approver"}</p>
            <div className="mt-3 grid gap-3 sm:grid-cols-[180px,1fr,160px]">
                <select
                    value={form.data.decision}
                    onChange={(e) => form.setData("decision", e.target.value)}
                    className="rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                >
                    <option value="approved">Approve</option>
                    <option value="revision_requested">Request Revision</option>
                    <option value="rejected">Reject</option>
                </select>
                <input
                    value={form.data.remarks}
                    onChange={(e) => form.setData("remarks", e.target.value)}
                    placeholder="Remarks"
                    className="rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                />
                <button type="submit" disabled={form.processing} className="rounded-xl bg-emerald-600 px-4 py-3 font-medium text-white hover:bg-emerald-500 disabled:opacity-60">
                    {form.processing ? "Updating..." : "Update Approval"}
                </button>
            </div>
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

function FileField({ form, name, label }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input
                type="file"
                onChange={(e) => form.setData(name, e.target.files?.[0] || null)}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function DocumentLinkRow({ label, document, routeBase }) {
    if (!document) {
        return <p>{label}: Not uploaded</p>;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span>{label}: {document.original_name}</span>
            <a
                href={route(`${routeBase}.view`, document.id)}
                target="_blank"
                rel="noreferrer"
                className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                View
            </a>
            <a
                href={route(`${routeBase}.download`, document.id)}
                className="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500"
            >
                Download
            </a>
        </div>
    );
}
