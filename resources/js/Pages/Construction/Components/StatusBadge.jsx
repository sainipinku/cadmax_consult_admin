const colors = {
    active: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    approved: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    ready_for_construction: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    pending: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300",
    submitted: "bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300",
    issued: "bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300",
    planned: "bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300",
    queued: "bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300",
    in_progress: "bg-cyan-100 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300",
    draft: "bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300",
    partially_paid: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300",
    paid: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    handed_over: "bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300",
    closed: "bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300",
    completed: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    waived: "bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300",
    gps_verified: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    unverified: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300",
    budget_pending: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300",
    budget_approved: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    team_assigned: "bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300",
    survey_planned: "bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300",
    survey_in_progress: "bg-cyan-100 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300",
    drafting_in_progress: "bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300",
    drawing_approval_pending: "bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300",
    revision_requested: "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300",
    rejected: "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300",
    inactive: "bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300",
    low: "bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300",
    medium: "bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300",
    high: "bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300",
    critical: "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300",
    planning: "bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300",
    survey: "bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300",
    foundation: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300",
    structure: "bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300",
    finishing: "bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950/60 dark:text-fuchsia-300",
    handover: "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300",
};

export default function StatusBadge({ value, status, label }) {
    const rawVal = value ?? status ?? "unknown";
    const displayLabel = label || String(rawVal).replaceAll("_", " ");
    const color = colors[rawVal] || "bg-slate-100 text-slate-700";

    return (
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${color}`}>
            {displayLabel}
        </span>
    );
}
