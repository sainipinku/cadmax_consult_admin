import React, { useState } from "react";
import { router } from "@inertiajs/react";
import {
    FaTimes,
    FaGasPump,
    FaCar,
    FaTachometerAlt,
    FaReceipt,
    FaCalendarAlt,
    FaCheckCircle,
    FaTimesCircle,
    FaHourglassHalf,
    FaUser,
    FaBuilding,
    FaExternalLinkAlt,
    FaShieldAlt,
} from "react-icons/fa";

export default function FuelDetailModal({
    isOpen,
    onClose,
    entry,
    variant = "super",
    canVerify = true,
}) {
    if (!isOpen || !entry) return null;

    const [status, setStatus] = useState(entry.status === "rejected" ? "rejected" : "approved");
    const [adminRemarks, setAdminRemarks] = useState(entry.admin_remarks || "");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isImageZoomed, setIsImageZoomed] = useState(false);

    const handleVerifySubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        const verifyRoute =
            variant === "admin"
                ? route("admin.construction.fuel.verify", entry.id)
                : route("super.construction.fuel.verify", entry.id);

        router.post(
            verifyRoute,
            {
                status,
                admin_remarks: adminRemarks,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSubmitting(false);
                    onClose();
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    const getStatusBadge = (s) => {
        switch (s) {
            case "approved":
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/60">
                        <FaCheckCircle className="w-3.5 h-3.5" /> Approved
                    </span>
                );
            case "rejected":
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-800/60">
                        <FaTimesCircle className="w-3.5 h-3.5" /> Rejected
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/60">
                        <FaHourglassHalf className="w-3.5 h-3.5" /> Pending Verification
                    </span>
                );
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div className="relative w-full max-w-4xl bg-white dark:bg-[#0e1322] rounded-2xl shadow-2xl border border-slate-200 dark:border-[#1e293b] overflow-hidden my-8 transition-all">
                {/* Modal Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-[#1e293b] bg-slate-50/70 dark:bg-[#151c33]/70">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 rounded-xl bg-purple-50 text-purple-600 border border-purple-200 dark:bg-purple-600/20 dark:border-purple-500/30 dark:text-purple-400">
                            <FaGasPump className="w-5 h-5" />
                        </div>
                        <div>
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                                Fuel Entry Details & Verification
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Vehicle: <span className="font-semibold text-slate-700 dark:text-slate-200">{entry.vehicle_number}</span> &bull; Log #{entry.id}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        {getStatusBadge(entry.status)}
                        <button
                            onClick={onClose}
                            className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-[#1e293b] transition"
                        >
                            <FaTimes className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {/* Modal Body */}
                <div className="p-6 space-y-6 max-h-[calc(85vh-120px)] overflow-y-auto">
                    {/* Top Info Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Driver Card */}
                        <div className="p-4 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b]">
                            <div className="flex items-center gap-2 mb-2 text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
                                <FaUser className="w-3.5 h-3.5" /> Driver / Employee
                            </div>
                            <div className="flex items-center gap-3">
                                {entry.driver?.avatar ? (
                                    <img
                                        src={entry.driver.avatar}
                                        alt={entry.driver.name}
                                        className="w-10 h-10 rounded-full object-cover ring-2 ring-purple-500/30"
                                    />
                                ) : (
                                    <div className="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-300 font-bold flex items-center justify-center text-sm">
                                        {entry.driver?.name?.charAt(0) || "D"}
                                    </div>
                                )}
                                <div className="min-w-0">
                                    <div className="text-sm font-bold text-slate-900 dark:text-white truncate">
                                        {entry.driver?.name || "Unknown Driver"}
                                    </div>
                                    <div className="text-xs text-slate-500 dark:text-slate-400">
                                        {entry.driver?.employee_code} &bull; {entry.driver?.designation}
                                    </div>
                                    {entry.driver?.phone && (
                                        <div className="text-xs text-slate-400">
                                            {entry.driver.phone}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Vehicle Card */}
                        <div className="p-4 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b]">
                            <div className="flex items-center gap-2 mb-2 text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
                                <FaCar className="w-3.5 h-3.5" /> Vehicle Fleet
                            </div>
                            <div>
                                <div className="text-sm font-bold text-slate-900 dark:text-white">
                                    {entry.vehicle_number}
                                </div>
                                <div className="text-xs text-slate-500 dark:text-slate-400">
                                    Code: {entry.vehicle?.vehicle_code || "N/A"}
                                </div>
                                <div className="text-xs text-slate-500 dark:text-slate-400">
                                    {entry.vehicle?.vehicle_name || entry.vehicle?.vehicle_type || "Commercial Fleet"}
                                </div>
                            </div>
                        </div>

                        {/* Project Card */}
                        <div className="p-4 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b]">
                            <div className="flex items-center gap-2 mb-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                                <FaBuilding className="w-3.5 h-3.5" /> Project & Company
                            </div>
                            <div>
                                <div className="text-sm font-bold text-slate-900 dark:text-white truncate">
                                    {entry.project?.name || "Unassigned"}
                                </div>
                                <div className="text-xs font-mono text-purple-600 dark:text-purple-400 font-semibold">
                                    {entry.project?.project_code || "PRJ-GENERAL"}
                                </div>
                                <div className="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {entry.project?.company_name || "Platform Company"}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Fuel & Expense Metrics Row */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200/60 dark:border-purple-800/40">
                        <div>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Fuel Type</span>
                            <div className="text-base font-bold text-purple-700 dark:text-purple-300">
                                {entry.fuel_type}
                            </div>
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Quantity</span>
                            <div className="text-base font-bold text-slate-900 dark:text-white">
                                {Number(entry.quantity_liters).toFixed(2)} Liters
                            </div>
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Total Amount</span>
                            <div className="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                ₹{Number(entry.total_amount).toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                            </div>
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Rate / Liter</span>
                            <div className="text-base font-bold text-slate-900 dark:text-white">
                                ₹{entry.rate_per_liter ? Number(entry.rate_per_liter).toFixed(2) : "0.00"}
                            </div>
                        </div>
                    </div>

                    {/* Odometer & Date Specs */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="flex items-center gap-3 p-3.5 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b]">
                            <div className="p-2.5 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-600/20 dark:text-blue-400">
                                <FaTachometerAlt className="w-4 h-4" />
                            </div>
                            <div>
                                <div className="text-xs text-slate-500 dark:text-slate-400">Odometer Reading</div>
                                <div className="text-sm font-bold text-slate-900 dark:text-white">
                                    {Number(entry.odometer_reading).toLocaleString()} KM
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-3 p-3.5 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b]">
                            <div className="p-2.5 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-600/20 dark:text-emerald-400">
                                <FaCalendarAlt className="w-4 h-4" />
                            </div>
                            <div>
                                <div className="text-xs text-slate-500 dark:text-slate-400">Fuel Log Date</div>
                                <div className="text-sm font-bold text-slate-900 dark:text-white">
                                    {entry.fuel_date_formatted || entry.fuel_date}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Driver Notes (if present) */}
                    {entry.notes && (
                        <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b]">
                            <div className="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                Driver Remarks / Station Location:
                            </div>
                            <p className="text-xs text-slate-700 dark:text-slate-300 italic">
                                "{entry.notes}"
                            </p>
                        </div>
                    )}

                    {/* Receipt Photo Lightbox & Preview */}
                    <div className="p-4 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b]">
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                <FaReceipt className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                                Fuel Station Receipt Photo
                            </div>
                            {entry.receipt_photo_url && (
                                <a
                                    href={entry.receipt_photo_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1.5 text-xs text-purple-600 hover:text-purple-700 dark:text-purple-400 font-semibold transition"
                                >
                                    Open Full Size <FaExternalLinkAlt className="w-2.5 h-2.5" />
                                </a>
                            )}
                        </div>

                        {entry.receipt_photo_url ? (
                            <div className="relative group rounded-xl overflow-hidden bg-slate-900 border border-slate-200 dark:border-[#1e293b] flex items-center justify-center max-h-72">
                                <img
                                    src={entry.receipt_photo_url}
                                    alt="Fuel Receipt"
                                    className="w-full max-h-72 object-contain cursor-pointer transition transform hover:scale-105"
                                    onClick={() => setIsImageZoomed(!isImageZoomed)}
                                />
                            </div>
                        ) : (
                            <div className="py-8 text-center rounded-xl bg-white dark:bg-[#0e1322] border border-dashed border-slate-300 dark:border-slate-700">
                                <FaReceipt className="w-8 h-8 text-slate-400 mx-auto mb-2 opacity-50" />
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    No receipt photo was uploaded with this fuel entry.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Verification Form (Gated by canVerify permission) */}
                    {canVerify && (
                        <form
                            onSubmit={handleVerifySubmit}
                            className="p-5 rounded-xl bg-purple-50/60 dark:bg-purple-950/20 border border-purple-200/80 dark:border-purple-800/60 space-y-4"
                        >
                            <div className="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                                <FaShieldAlt className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                Review & Verify Fuel Consumption
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onClick={() => setStatus("approved")}
                                    className={`flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-xs font-bold transition border ${
                                        status === "approved"
                                            ? "bg-emerald-600 text-white border-emerald-600 shadow-sm"
                                            : "bg-white text-slate-700 border-slate-200 hover:bg-slate-50 dark:bg-[#151c33] dark:text-slate-300 dark:border-[#1e293b]"
                                    }`}
                                >
                                    <FaCheckCircle className="w-3.5 h-3.5" /> Approve Entry
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setStatus("rejected")}
                                    className={`flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-xs font-bold transition border ${
                                        status === "rejected"
                                            ? "bg-rose-600 text-white border-rose-600 shadow-sm"
                                            : "bg-white text-slate-700 border-slate-200 hover:bg-slate-50 dark:bg-[#151c33] dark:text-slate-300 dark:border-[#1e293b]"
                                    }`}
                                >
                                    <FaTimesCircle className="w-3.5 h-3.5" /> Reject Entry
                                </button>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                    Admin Remarks / Verification Notes
                                </label>
                                <textarea
                                    value={adminRemarks}
                                    onChange={(e) => setAdminRemarks(e.target.value)}
                                    rows={2}
                                    placeholder="Add any validation remarks, pump discrepancy notes, or reason for approval/rejection..."
                                    className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-white dark:bg-[#0e1322] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-1">
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="px-4 py-2 text-xs font-semibold rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 dark:bg-[#151c33] dark:hover:bg-[#1e293b] dark:text-slate-300 dark:border-slate-700 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="px-5 py-2 text-xs font-bold rounded-xl bg-purple-600 hover:bg-purple-700 text-white shadow-xs transition disabled:opacity-50"
                                >
                                    {isSubmitting ? "Saving Verification..." : "Save Verification Status"}
                                </button>
                            </div>
                        </form>
                    )}

                    {/* Previous Review Log (If Already Verified) */}
                    {entry.reviewed_by && (
                        <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b] flex items-center justify-between text-xs">
                            <div>
                                <span className="text-slate-500 dark:text-slate-400">Last Reviewed By: </span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">
                                    {entry.reviewed_by}
                                </span>
                                {entry.reviewed_at && (
                                    <span className="text-slate-400"> on {entry.reviewed_at}</span>
                                )}
                            </div>
                            {entry.admin_remarks && (
                                <div className="text-slate-600 dark:text-slate-400 italic">
                                    "{entry.admin_remarks}"
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

