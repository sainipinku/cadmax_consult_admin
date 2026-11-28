import Modal from "@/Components/Modal";

/**
 * Shared modal for reviewing and approving/requesting revisions on a submitted CAD drawing.
 */
export default function CadReviewModal({
    reviewTarget,
    form,
    onClose,
    onSubmit,
}) {
    if (!reviewTarget) {
        return null;
    }

    const latestRev = reviewTarget.latestRev || {};
    const pdfPath =
        latestRev?.pdf_document?.file_path ||
        latestRev?.pdfDocument?.file_path ||
        reviewTarget?.file_path ||
        null;

    return (
        <Modal show={Boolean(reviewTarget)} maxWidth="lg" onClose={onClose}>
            <div className="p-6">
                <div className="flex items-start justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-800">
                    <div>
                        <h3 className="text-base font-bold text-slate-900 dark:text-white">
                            Review Submitted CAD Drawing
                        </h3>
                        <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {latestRev?.drawing_no || "CAD Revision"}{" "}
                            {latestRev?.revision_no ? `(Rev ${latestRev.revision_no})` : ""}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                    >
                        Close
                    </button>
                </div>

                <div className="mt-4 space-y-3">
                    <div className="rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <div>
                            Drawing Type: <strong>{latestRev?.drawing_type || "-"}</strong>
                        </div>
                        <div className="mt-1">
                            Scope / Area:{" "}
                            <strong>
                                {latestRev?.site_area ||
                                    latestRev?.khasra_number ||
                                    reviewTarget?.job?.site_area ||
                                    reviewTarget?.job?.khasra_number ||
                                    "-"}
                            </strong>
                        </div>
                        {pdfPath && (
                            <a
                                href={
                                    String(pdfPath).startsWith("/storage/")
                                        ? pdfPath
                                        : `/storage/${String(pdfPath).replace(/^\/+/, "")}`
                                }
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-block font-semibold text-indigo-600 underline"
                            >
                                Open CAD PDF
                            </a>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Review Remarks
                        </label>
                        <textarea
                            rows={3}
                            value={form.data.remarks}
                            onChange={(e) => form.setData("remarks", e.target.value)}
                            placeholder="Enter approval remarks or revision notes..."
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                        {form.errors?.remarks && (
                            <p className="mt-1 text-xs text-rose-600">{form.errors.remarks}</p>
                        )}
                    </div>
                </div>

                <div className="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() => onSubmit("revision_requested")}
                        className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-60"
                    >
                        Request Revision
                    </button>
                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() => onSubmit("approved")}
                        className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-60"
                    >
                        {form.processing ? "Submitting..." : "Approve CAD"}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
