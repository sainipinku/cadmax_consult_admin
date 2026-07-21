const colors = {
    active: "bg-emerald-100 text-emerald-700",
    approved: "bg-emerald-100 text-emerald-700",
    ready_for_construction: "bg-emerald-100 text-emerald-700",
    pending: "bg-amber-100 text-amber-700",
    submitted: "bg-blue-100 text-blue-700",
    issued: "bg-blue-100 text-blue-700",
    planned: "bg-sky-100 text-sky-700",
    queued: "bg-violet-100 text-violet-700",
    in_progress: "bg-cyan-100 text-cyan-700",
    draft: "bg-slate-100 text-slate-700",
    partially_paid: "bg-amber-100 text-amber-700",
    paid: "bg-emerald-100 text-emerald-700",
    handed_over: "bg-indigo-100 text-indigo-700",
    closed: "bg-slate-200 text-slate-700",
    completed: "bg-emerald-100 text-emerald-700",
    waived: "bg-violet-100 text-violet-700",
    gps_verified: "bg-emerald-100 text-emerald-700",
    unverified: "bg-amber-100 text-amber-700",
    budget_pending: "bg-amber-100 text-amber-700",
    budget_approved: "bg-emerald-100 text-emerald-700",
    team_assigned: "bg-indigo-100 text-indigo-700",
    survey_planned: "bg-sky-100 text-sky-700",
    survey_in_progress: "bg-cyan-100 text-cyan-700",
    drafting_in_progress: "bg-violet-100 text-violet-700",
    drawing_approval_pending: "bg-orange-100 text-orange-700",
    revision_requested: "bg-rose-100 text-rose-700",
    rejected: "bg-rose-100 text-rose-700",
    inactive: "bg-slate-200 text-slate-700",
    low: "bg-slate-100 text-slate-700",
    medium: "bg-sky-100 text-sky-700",
    high: "bg-orange-100 text-orange-700",
    critical: "bg-rose-100 text-rose-700",
};

export default function StatusBadge({ value }) {
    const label = String(value || "unknown").replaceAll("_", " ");
    const color = colors[value] || "bg-slate-100 text-slate-700";

    return (
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${color}`}>
            {label}
        </span>
    );
}
