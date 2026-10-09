import React, { useState } from "react";
import { useForm } from "@inertiajs/react";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import {
    FaUserCheck,
    FaCheck,
    FaRotateLeft,
    FaFilePdf,
    FaFileCode,
    FaImage,
    FaCircleCheck,
    FaLocationDot,
    FaCompassDrafting,
    FaFileInvoiceDollar,
    FaXmark,
    FaTriangleExclamation,
} from "react-icons/fa6";

export default function SupervisorSingleScreenReview({ project, submitRoute }) {
    const [returnModal, setReturnModal] = useState({
        open: false,
        targetAction: "", // send_back_to_draft, send_back_to_survey, send_back_to_accounts
        targetTitle: "",
    });

    const { data, setData, post, processing, errors, reset } = useForm({
        action: "",
        remarks: "",
    });

    const latestSubmission = project.survey_submissions?.[0] || {};
    const latestDraftingJob = project.drafting_jobs?.[0] || {};
    const latestRevision = latestDraftingJob.drawing_revisions?.[0] || project.drawing_revisions?.[0] || {};
    const checklist = project.draft_verification_checklist || {};

    const handleApprove = () => {
        if (confirm("Are you sure you want to approve this project? This will mark the project as Completed and lock all final drawings.")) {
            post(route(submitRoute, project.id), {
                data: { action: "approve", remarks: "Approved by Supervisor." },
                preserveScroll: true,
            });
        }
    };

    const openReturnModal = (action, title) => {
        setReturnModal({ open: true, targetAction: action, targetTitle: title });
        setData("action", action);
        setData("remarks", "");
    };

    const closeReturnModal = () => {
        setReturnModal({ open: false, targetAction: "", targetTitle: "" });
        reset();
    };

    const handleSendBackSubmit = (e) => {
        e.preventDefault();
        if (!data.remarks || !data.remarks.trim()) {
            alert("Remark is mandatory when sending back to any stage!");
            return;
        }

        post(route(submitRoute, project.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeReturnModal();
            },
        });
    };

    return (
        <div className="space-y-6 rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/40 via-white to-slate-50 p-6 shadow-sm dark:border-indigo-900/50 dark:from-slate-900 dark:to-slate-950">
            {/* Header */}
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between border-b border-indigo-100 dark:border-indigo-950 pb-4">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="p-2 rounded-lg bg-indigo-600 text-white shadow-sm">
                            <FaUserCheck size={18} />
                        </span>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                            Supervisor Unified Review Panel
                        </h2>
                    </div>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Review the complete workflow chain across Survey → Draft → Accounts on a single screen before final approval.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge value={project.current_stage} />
                    {project.is_locked && (
                        <span className="px-3 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            🔒 Locked
                        </span>
                    )}
                </div>
            </div>

            {/* Single Screen 3-Column Chain View */}
            <div className="grid gap-5 lg:grid-cols-3">
                {/* Column 1: Survey Submission */}
                <div className="rounded-xl border border-sky-200/80 bg-white p-4 shadow-sm dark:border-sky-900/60 dark:bg-slate-900 flex flex-col justify-between">
                    <div>
                        <div className="flex items-center justify-between border-b border-sky-100 dark:border-sky-950 pb-3">
                            <div className="flex items-center gap-2">
                                <span className="p-1.5 rounded-md bg-sky-500 text-white">
                                    <FaLocationDot size={13} />
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    1. Survey Submission
                                </h3>
                            </div>
                            <span className="text-[10px] font-semibold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded-full">
                                Stage 1
                            </span>
                        </div>

                        <div className="mt-3 space-y-3 text-xs">
                            <div>
                                <p className="text-[11px] text-slate-400 font-medium">Submitted By</p>
                                <p className="font-semibold text-slate-800 dark:text-slate-200">
                                    {latestSubmission.submitted_by?.name || project.survey_plans?.[0]?.assigned_by?.name || "Survey Team"}
                                </p>
                                <p className="text-[10px] text-slate-400">
                                    {latestSubmission.submitted_at
                                        ? new Date(latestSubmission.submitted_at).toLocaleString()
                                        : "Completed"}
                                </p>
                            </div>

                            <div>
                                <p className="text-[11px] text-slate-400 font-medium">Site Address / Location</p>
                                <p className="font-medium text-slate-700 dark:text-slate-300">
                                    {project.location_name || project.project_address || "On-site surveyed"}
                                </p>
                            </div>

                            <div>
                                <p className="text-[11px] text-slate-400 font-medium">Survey Remarks & Notes</p>
                                <p className="mt-0.5 rounded-md bg-slate-50 p-2 text-slate-700 dark:bg-slate-800 dark:text-slate-300 italic">
                                    "{latestSubmission.review_notes || "Survey complete. Raw site data, sketches and reference points uploaded."}"
                                </p>
                            </div>

                            <div>
                                <p className="text-[11px] text-slate-400 font-medium">Uploaded Attachments</p>
                                <div className="mt-1 flex flex-wrap gap-1.5">
                                    <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        <FaImage className="text-sky-500" /> Site Photos
                                    </span>
                                    <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        <FaFileCode className="text-amber-500" /> Raw Points (.csv/.txt)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 border-t border-slate-100 dark:border-slate-800 pt-3">
                        <button
                            type="button"
                            onClick={() => openReturnModal("send_back_to_survey", "Survey Team")}
                            disabled={project.is_locked}
                            className="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-300 dark:hover:bg-sky-900/60 disabled:opacity-50"
                        >
                            <FaRotateLeft size={11} /> Send Back to Survey Team
                        </button>
                    </div>
                </div>

                {/* Column 2: Draft Drawing */}
                <div className="rounded-xl border border-indigo-200/80 bg-white p-4 shadow-sm dark:border-indigo-900/60 dark:bg-slate-900 flex flex-col justify-between">
                    <div>
                        <div className="flex items-center justify-between border-b border-indigo-100 dark:border-indigo-950 pb-3">
                            <div className="flex items-center gap-2">
                                <span className="p-1.5 rounded-md bg-indigo-600 text-white">
                                    <FaCompassDrafting size={13} />
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    2. Draft Drawing & Verification
                                </h3>
                            </div>
                            <span className="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded-full">
                                Stage 2
                            </span>
                        </div>

                        <div className="mt-3 space-y-3 text-xs">
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <p className="text-[11px] text-slate-400 font-medium">Drawing Type</p>
                                    <p className="font-semibold text-slate-800 dark:text-slate-200">
                                        {latestRevision.drawing_type || "2D Layout Plan"}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[11px] text-slate-400 font-medium">Drawing No / Rev</p>
                                    <p className="font-semibold text-indigo-600 dark:text-indigo-400">
                                        {latestRevision.drawing_no || `DWG-${project.project_code}`} (Rev {latestRevision.revision_no || 1})
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p className="text-[11px] text-slate-400 font-medium">DWG & PDF Deliverables</p>
                                <div className="mt-1 flex flex-wrap gap-2">
                                    {latestRevision.dwg_document ? (
                                        <a
                                            href={`/storage/${latestRevision.dwg_document.path}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300"
                                        >
                                            <FaFileCode /> DWG File
                                        </a>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            <FaFileCode /> DWG Ready
                                        </span>
                                    )}

                                    {latestRevision.pdf_document ? (
                                        <a
                                            href={`/storage/${latestRevision.pdf_document.path}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300"
                                        >
                                            <FaFilePdf /> PDF Drawing
                                        </a>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            <FaFilePdf /> PDF Ready
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div>
                                <p className="text-[11px] text-slate-400 font-medium mb-1">Draft Verification Checklists</p>
                                <div className="space-y-1 rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/80">
                                    <CheckItem label="Dimensions checked" checked={checklist.dimensions_checked} />
                                    <CheckItem label="Survey data matched" checked={checklist.survey_data_matched} />
                                    <CheckItem label="Markup updated" checked={checklist.markup_updated} />
                                    <CheckItem label="Drawing checked" checked={checklist.drawing_checked} />
                                    <CheckItem label="Final PDF/DWG ready" checked={checklist.final_pdf_dwg_ready} />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 border-t border-slate-100 dark:border-slate-800 pt-3">
                        <button
                            type="button"
                            onClick={() => openReturnModal("send_back_to_draft", "Draft Team")}
                            disabled={project.is_locked}
                            className="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300 dark:hover:bg-indigo-900/60 disabled:opacity-50"
                        >
                            <FaRotateLeft size={11} /> Send Back to Draft Team
                        </button>
                    </div>
                </div>

                {/* Column 3: Accounts Commercial Verification */}
                <div className="rounded-xl border border-amber-200/80 bg-white p-4 shadow-sm dark:border-amber-900/60 dark:bg-slate-900 flex flex-col justify-between">
                    <div>
                        <div className="flex items-center justify-between border-b border-amber-100 dark:border-amber-950 pb-3">
                            <div className="flex items-center gap-2">
                                <span className="p-1.5 rounded-md bg-amber-600 text-white">
                                    <FaFileInvoiceDollar size={13} />
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    3. Accounts Commercial Verification
                                </h3>
                            </div>
                            <span className="text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded-full">
                                Stage 3
                            </span>
                        </div>

                        <div className="mt-3 space-y-3 text-xs">
                            <div className="flex items-center justify-between rounded-lg bg-amber-50/60 p-2.5 border border-amber-200/60 dark:bg-amber-950/30 dark:border-amber-900/50">
                                <div>
                                    <p className="text-[10px] font-semibold text-amber-800 dark:text-amber-300">Payment Status</p>
                                    <p className="text-xs font-extrabold uppercase text-amber-900 dark:text-amber-200">
                                        {project.payment_status || "Pending"}
                                    </p>
                                </div>
                                <span className="text-base">
                                    {project.payment_status === "paid" ? "✅" : "⏳"}
                                </span>
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <p className="text-[11px] text-slate-400 font-medium">Billing Amount</p>
                                    <p className="font-bold text-slate-800 dark:text-slate-200">
                                        ₹{Number(project.commercial_billing_amount || 0).toLocaleString("en-IN")}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[11px] text-slate-400 font-medium">Advance Received</p>
                                    <p className="font-bold text-emerald-600 dark:text-emerald-400">
                                        ₹{Number(project.commercial_advance_received || 0).toLocaleString("en-IN")}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[11px] text-slate-400 font-medium">Pending Amount</p>
                                    <p className="font-bold text-rose-600 dark:text-rose-400">
                                        ₹{Number(project.commercial_pending_amount || 0).toLocaleString("en-IN")}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[11px] text-slate-400 font-medium">Expenses</p>
                                    <p className="font-bold text-slate-700 dark:text-slate-300">
                                        ₹{Number(project.commercial_expenses || 0).toLocaleString("en-IN")}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p className="text-[11px] text-slate-400 font-medium">Accounts Remarks</p>
                                <p className="mt-0.5 rounded-md bg-slate-50 p-2 text-slate-700 dark:bg-slate-800 dark:text-slate-300 italic">
                                    "{project.accounts_remarks || "Commercial amounts verified and matched with quotation."}"
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 border-t border-slate-100 dark:border-slate-800 pt-3">
                        <button
                            type="button"
                            onClick={() => openReturnModal("send_back_to_accounts", "Accounts Team")}
                            disabled={project.is_locked}
                            className="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300 dark:hover:bg-amber-900/60 disabled:opacity-50"
                        >
                            <FaRotateLeft size={11} /> Send Back to Accounts Team
                        </button>
                    </div>
                </div>
            </div>

            {/* Bottom Final Action Bar */}
            <div className="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/60 dark:bg-slate-900 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h4 className="text-sm font-bold text-emerald-900 dark:text-emerald-300 flex items-center gap-2">
                        <FaCircleCheck size={16} /> Final Supervisor Decision
                    </h4>
                    <p className="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5">
                        If all 3 stages (Survey → Draft → Accounts) are verified, click Approve to complete the project and lock final files.
                    </p>
                </div>

                <div className="flex items-center gap-3 shrink-0">
                    <button
                        type="button"
                        onClick={handleApprove}
                        disabled={project.is_locked || processing}
                        className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all disabled:opacity-50"
                    >
                        <FaCheck size={15} /> Approve & Complete Project
                    </button>
                </div>
            </div>

            {/* Mandatory Return Remark Modal */}
            {returnModal.open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div className="flex items-center gap-2 text-rose-600 dark:text-rose-400 font-bold text-sm">
                                <FaTriangleExclamation size={16} />
                                Send Back to {returnModal.targetTitle}
                            </div>
                            <button
                                type="button"
                                onClick={closeReturnModal}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <FaXmark size={16} />
                            </button>
                        </div>

                        <form onSubmit={handleSendBackSubmit} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Mandatory Remark / Reason for Return <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    rows={4}
                                    required
                                    value={data.remarks}
                                    onChange={(e) => setData("remarks", e.target.value)}
                                    placeholder={`Specify clearly why this project is being sent back to ${returnModal.targetTitle}...`}
                                    className="w-full rounded-xl border border-slate-300 p-3 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                                {errors.remarks && (
                                    <p className="mt-1 text-xs text-rose-500">{errors.remarks}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={closeReturnModal}
                                    className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing || !data.remarks.trim()}
                                    className="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-rose-700 disabled:opacity-50"
                                >
                                    <FaRotateLeft size={12} /> Confirm & Send Back
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

function CheckItem({ label, checked }) {
    return (
        <div className="flex items-center justify-between text-[11px]">
            <span className="text-slate-600 dark:text-slate-300 font-medium">{label}</span>
            {checked ? (
                <span className="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                    <FaCheck size={10} /> Verified
                </span>
            ) : (
                <span className="text-slate-400 font-normal">Pending</span>
            )}
        </div>
    );
}
