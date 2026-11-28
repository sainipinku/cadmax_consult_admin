import { Link } from "@inertiajs/react";

export default function PendingCadApprovalAlertBanner({
    approvals = [],
    className = "cad-action-blink-border",
    onReview = null,
}) {
    const items = Array.isArray(approvals) ? approvals : [];
    if (items.length === 0) {
        return null;
    }

    const buildReviewHref = (item) => {
        const base = item.project_url || route("admin.construction.projects.show", item.project_id);
        if (!item?.approval_id) {
            return base;
        }
        const sep = String(base).includes("?") ? "&" : "?";
        return `${base}${sep}review_approval=${encodeURIComponent(item.approval_id)}`;
    };

    return (
        <div
            className={`${className} mb-6 rounded-2xl border-2 bg-rose-50/95 p-4 shadow-md dark:bg-rose-950/35`}
            role="alert"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                    <span className="inline-flex h-2.5 w-2.5 animate-ping rounded-full bg-rose-500" />
                    <div>
                        <h3 className="text-sm font-bold text-rose-900 dark:text-rose-100">
                            Action Needed: CAD Approval Assigned to You ({items.length})
                        </h3>
                        <p className="text-xs text-rose-700 dark:text-rose-300">
                            Review and approve or request revision on the assigned CAD drawing submissions below.
                        </p>
                    </div>
                </div>
            </div>

            <div className="mt-3 grid gap-2.5 md:grid-cols-2">
                {items.map((item) => (
                    <div
                        key={item.approval_id}
                        className="cad-action-blink-border flex items-center justify-between gap-3 rounded-xl bg-white p-3 dark:bg-slate-900"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-xs font-bold text-slate-900 dark:text-white">
                                {item.project_name} {item.project_code ? `(${item.project_code})` : ""}
                            </p>
                            <p className="mt-0.5 text-[11px] text-slate-600 dark:text-slate-300">
                                Drawing:{" "}
                                <span className="font-semibold text-indigo-700 dark:text-indigo-300">
                                    {item.drawing_no || `Rev ${item.revision_no || 1}`}
                                </span>
                                {item.assigned_by_name ? ` · Assigned by ${item.assigned_by_name}` : ""}
                            </p>
                        </div>
                        {typeof onReview === "function" ? (
                            <button
                                type="button"
                                onClick={() => onReview(item)}
                                className="shrink-0 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-500"
                            >
                                Review &amp; Approve
                            </button>
                        ) : (
                            <Link
                                href={buildReviewHref(item)}
                                className="shrink-0 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-500"
                            >
                                Review &amp; Approve
                            </Link>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
