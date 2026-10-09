import React from "react";
import { useForm } from "@inertiajs/react";
import {
    FaFileInvoiceDollar,
    FaLock,
    FaPaperPlane,
    FaCheck,
    FaFilePdf,
    FaFileCode,
} from "react-icons/fa6";

export default function AccountsVerificationPanel({
    project,
    submitAccountsRoute,
    isAccountsRole = true,
}) {
    const isLocked = project.is_locked;
    const latestRevision = project.drafting_jobs?.[0]?.drawing_revisions?.[0] || project.drawing_revisions?.[0] || {};

    const form = useForm({
        payment_status: project.payment_status || "pending",
        commercial_billing_amount: project.commercial_billing_amount || 0,
        commercial_advance_received: project.commercial_advance_received || 0,
        commercial_pending_amount: project.commercial_pending_amount || 0,
        commercial_expenses: project.commercial_expenses || 0,
        accounts_remarks: project.accounts_remarks || "",
        accounts_proof_document_id: project.accounts_proof_document_id || null,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route(submitAccountsRoute, project.id), { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                <div className="flex items-center gap-2.5">
                    <span className="p-2 rounded-xl bg-amber-600 text-white shadow-sm">
                        <FaFileInvoiceDollar size={18} />
                    </span>
                    <div>
                        <h3 className="text-base font-bold text-slate-900 dark:text-white">
                            Accounts Team – Commercial Verification
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Verify project commercial details, billing, advance received, expenses, payment status, and submit to Supervisor.
                        </p>
                    </div>
                </div>

                {!isAccountsRole && (
                    <span className="px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        Read-Only Mode (Accounts Role Only)
                    </span>
                )}
            </div>

            {/* Strict Isolation Notice */}
            <div className="rounded-xl border border-amber-200/80 bg-amber-50/50 p-3.5 dark:border-amber-900/60 dark:bg-amber-950/20 flex items-center justify-between">
                <p className="text-xs text-amber-800 dark:text-amber-300 font-medium">
                    🔒 <strong>Strict Audit Policy:</strong> Accounts Team does not have edit access to technical drawings or survey files.
                </p>
            </div>

            {/* Read-Only Verified Technical Drawing Summary */}
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-3">
                <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                    <FaLock className="text-indigo-500" /> Read-Only Technical Drawing Summary
                </h4>

                <div className="grid gap-3 sm:grid-cols-3 text-xs">
                    <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                        <span className="text-[10px] text-slate-400 font-semibold block">Drawing Type</span>
                        <span className="font-bold text-slate-800 dark:text-slate-200">
                            {latestRevision.drawing_type || "Architectural Floor Plan"}
                        </span>
                    </div>

                    <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                        <span className="text-[10px] text-slate-400 font-semibold block">Drawing No / Rev</span>
                        <span className="font-bold text-indigo-600 dark:text-indigo-400">
                            {latestRevision.drawing_no || `DWG-${project.project_code}`} (Rev {latestRevision.revision_no || 1})
                        </span>
                    </div>

                    <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                        <span className="text-[10px] text-slate-400 font-semibold block">Draft Verification</span>
                        <span className="font-bold text-emerald-600 dark:text-emerald-400">
                            ✓ Verified by Draft Team
                        </span>
                    </div>
                </div>
            </div>

            {/* Commercial Verification Form */}
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
                <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <FaFileInvoiceDollar className="text-amber-600" /> Commercial & Billing Details
                </h4>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Payment Status <span className="text-rose-500">*</span>
                            </label>
                            <select
                                required
                                disabled={!isAccountsRole || isLocked}
                                value={form.data.payment_status}
                                onChange={(e) => form.setData("payment_status", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            >
                                <option value="pending">Pending</option>
                                <option value="partial">Partial Payment</option>
                                <option value="paid">Paid (100% Cleared)</option>
                                <option value="not_applicable">Not Applicable</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Total Billing Amount (₹)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                disabled={!isAccountsRole || isLocked}
                                value={form.data.commercial_billing_amount}
                                onChange={(e) => form.setData("commercial_billing_amount", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Advance Received (₹)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                disabled={!isAccountsRole || isLocked}
                                value={form.data.commercial_advance_received}
                                onChange={(e) => form.setData("commercial_advance_received", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Pending Balance Amount (₹)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                disabled={!isAccountsRole || isLocked}
                                value={form.data.commercial_pending_amount}
                                onChange={(e) => form.setData("commercial_pending_amount", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Expenses Incurred (₹)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                disabled={!isAccountsRole || isLocked}
                                value={form.data.commercial_expenses}
                                onChange={(e) => form.setData("commercial_expenses", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Accounts Remarks & Payment Proof Notes
                        </label>
                        <textarea
                            rows={3}
                            disabled={!isAccountsRole || isLocked}
                            value={form.data.accounts_remarks}
                            onChange={(e) => form.setData("accounts_remarks", e.target.value)}
                            placeholder="Enter quotation/work order summary, payment reference number, invoice notes..."
                            className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                        />
                    </div>

                    <div className="pt-2 flex justify-end">
                        <button
                            type="submit"
                            disabled={!isAccountsRole || isLocked || form.processing}
                            className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-violet-700 disabled:opacity-50"
                        >
                            <FaPaperPlane size={13} /> Submit Commercials to Supervisor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
