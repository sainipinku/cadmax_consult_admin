import React, { useState } from "react";
import { router } from "@inertiajs/react";
import {
    FaTimes,
    FaCalendarAlt,
    FaClock,
    FaMapMarkerAlt,
    FaUser,
    FaBuilding,
    FaCheckCircle,
    FaTimesCircle,
    FaHourglassHalf,
    FaRoute,
    FaExternalLinkAlt,
    FaShieldAlt,
} from "react-icons/fa";

export default function AttendanceDetailModal({
    isOpen,
    onClose,
    record,
    variant = "super",
    canReview = true,
}) {
    if (!isOpen || !record) return null;

    const [status, setStatus] = useState(record.status || "approved");
    const [reviewNotes, setReviewNotes] = useState(record.review_notes || "");
    const [isSubmitting, setIsSubmitting] = useState(false);

    const hasCheckInCoords =
        record.check_in_latitude !== null &&
        record.check_in_longitude !== null &&
        !isNaN(record.check_in_latitude) &&
        !isNaN(record.check_in_longitude);

    const hasCheckOutCoords =
        record.check_out_latitude !== null &&
        record.check_out_longitude !== null &&
        !isNaN(record.check_out_latitude) &&
        !isNaN(record.check_out_longitude);

    // Calculate map bounding box center or default
    const mapLat = record.check_in_latitude || record.check_out_latitude || 26.9124;
    const mapLng = record.check_in_longitude || record.check_out_longitude || 75.7873;
    const bboxMinLng = mapLng - 0.01;
    const bboxMaxLng = mapLng + 0.01;
    const bboxMinLat = mapLat - 0.01;
    const bboxMaxLat = mapLat + 0.01;

    const osmEmbedUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${bboxMinLng}%2C${bboxMinLat}%2C${bboxMaxLng}%2C${bboxMaxLat}&layer=mapnik&marker=${mapLat}%2C${mapLng}`;

    const handleReviewSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        const reviewRoute =
            variant === "admin"
                ? route("admin.construction.attendance.review", record.id)
                : route("super.construction.attendance.review", record.id);

        router.post(
            reviewRoute,
            {
                status,
                review_notes: reviewNotes,
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

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm overflow-y-auto">
            <div className="relative w-full max-w-4xl max-h-[92vh] flex flex-col bg-[#0e1322] border border-[#1e293b] rounded-2xl shadow-2xl overflow-hidden text-slate-100">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-[#1e293b] bg-[#111827]/80">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-purple-600/20 border border-purple-500/30 flex items-center justify-center text-purple-400">
                            <FaMapMarkerAlt className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-white">
                                Attendance & Shift Verification
                            </h3>
                            <p className="text-xs text-slate-400">
                                Detailed GPS coordinates, duration, and verification log
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800/60 transition"
                    >
                        <FaTimes className="w-5 h-5" />
                    </button>
                </div>

                {/* Modal Body */}
                <div className="p-6 overflow-y-auto space-y-6 flex-1">
                    {/* Top Row: Employee & Project Card */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Employee Card */}
                        <div className="p-4 rounded-xl bg-[#161f36]/70 border border-[#22304e] flex items-center gap-4">
                            <img
                                src={
                                    record.employee?.avatar ||
                                    "/images/profileimg.png"
                                }
                                alt={record.employee?.name || "Employee"}
                                className="w-14 h-14 rounded-full object-cover border-2 border-purple-500/40 bg-slate-800"
                                onError={(e) => {
                                    e.target.src = "/images/profileimg.png";
                                }}
                            />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <h4 className="text-sm font-semibold text-white truncate">
                                        {record.employee?.name || "Unknown"}
                                    </h4>
                                    <span className="px-2 py-0.5 text-[10px] rounded-full bg-purple-500/10 text-purple-300 border border-purple-500/20">
                                        {record.employee?.employee_code || "N/A"}
                                    </span>
                                </div>
                                <p className="text-xs text-slate-300 mt-0.5">
                                    {record.employee?.designation || "Site Team Member"}
                                </p>
                                <p className="text-[11px] text-slate-400 mt-1 truncate">
                                    {record.employee?.email || record.employee?.phone || "No contact info"}
                                </p>
                            </div>
                        </div>

                        {/* Project Card */}
                        <div className="p-4 rounded-xl bg-[#161f36]/70 border border-[#22304e] flex flex-col justify-between">
                            <div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[10px] uppercase font-bold tracking-wider text-purple-400 flex items-center gap-1.5">
                                        <FaBuilding className="w-3 h-3" /> Project
                                    </span>
                                    <span className="px-2 py-0.5 text-xs font-mono font-medium rounded bg-slate-800 text-slate-300 border border-slate-700">
                                        {record.project_code || "PRJ"}
                                    </span>
                                </div>
                                <h4 className="text-sm font-semibold text-white mt-1.5 line-clamp-1">
                                    {record.project_name || "Assigned Project"}
                                </h4>
                                {record.company_name && (
                                    <p className="text-xs text-slate-400 mt-0.5">
                                        {record.company_name}
                                    </p>
                                )}
                            </div>
                            <div className="mt-3 flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-800/80">
                                <span className="flex items-center gap-1.5">
                                    <FaCalendarAlt className="w-3.5 h-3.5 text-purple-400" />
                                    {record.attendance_date_formatted || record.attendance_date}
                                </span>
                                <span className="capitalize font-medium text-slate-300">
                                    Type: {record.attendance_type || "Present"}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Shift & Duration Stats Grid */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        {/* Check-In Card */}
                        <div className="p-3.5 rounded-xl bg-[#131c31] border border-emerald-500/20">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-emerald-400 flex items-center gap-1">
                                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Check-In
                            </span>
                            <p className="text-base font-bold text-white mt-1">
                                {record.check_in_formatted || "Not Logged"}
                            </p>
                            {hasCheckInCoords && (
                                <p className="text-[10px] text-slate-400 mt-1 font-mono truncate">
                                    {record.check_in_latitude?.toFixed(4)}, {record.check_in_longitude?.toFixed(4)}
                                </p>
                            )}
                        </div>

                        {/* Check-Out Card */}
                        <div className="p-3.5 rounded-xl bg-[#131c31] border border-rose-500/20">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-rose-400 flex items-center gap-1">
                                <span className="w-2 h-2 rounded-full bg-rose-400"></span>
                                Check-Out
                            </span>
                            <p className="text-base font-bold text-white mt-1">
                                {record.check_out_formatted || (record.is_in_progress ? "Active / On Duty" : "Pending")}
                            </p>
                            {hasCheckOutCoords && (
                                <p className="text-[10px] text-slate-400 mt-1 font-mono truncate">
                                    {record.check_out_latitude?.toFixed(4)}, {record.check_out_longitude?.toFixed(4)}
                                </p>
                            )}
                        </div>

                        {/* Working Hours Duration */}
                        <div className="p-3.5 rounded-xl bg-[#131c31] border border-purple-500/20">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-purple-400 flex items-center gap-1">
                                <FaClock className="w-3 h-3" /> Duration
                            </span>
                            <p className="text-base font-bold text-white mt-1">
                                {record.duration_formatted || "0h 0m"}
                            </p>
                            <p className="text-[10px] text-slate-400 mt-1">
                                {record.is_in_progress ? "Shift in progress" : "Total working time"}
                            </p>
                        </div>

                        {/* Distance / Accuracy */}
                        <div className="p-3.5 rounded-xl bg-[#131c31] border border-cyan-500/20">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-cyan-400 flex items-center gap-1">
                                <FaRoute className="w-3 h-3" /> Shift Distance
                            </span>
                            <p className="text-base font-bold text-white mt-1">
                                {record.distance_meters !== null && record.distance_meters !== undefined
                                    ? record.distance_meters > 1000
                                        ? `${(record.distance_meters / 1000).toFixed(2)} km`
                                        : `${Math.round(record.distance_meters)} m`
                                    : "Static Site"}
                            </p>
                            <p className="text-[10px] text-slate-400 mt-1">
                                {record.gps_accuracy_meters ? `Accuracy: ±${Math.round(record.gps_accuracy_meters)}m` : "GPS verified"}
                            </p>
                        </div>
                    </div>

                    {/* Interactive GPS Map Section */}
                    <div className="rounded-xl overflow-hidden border border-[#22304e] bg-[#0c101c]">
                        <div className="px-4 py-2.5 bg-[#141b2e] border-b border-[#22304e] flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <FaMapMarkerAlt className="w-4 h-4 text-purple-400" />
                                <span className="text-xs font-semibold text-white">
                                    GPS Location & Spatial Verification
                                </span>
                            </div>
                            <div className="flex items-center gap-3">
                                {hasCheckInCoords && (
                                    <a
                                        href={`https://www.google.com/maps?q=${record.check_in_latitude},${record.check_in_longitude}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-[11px] text-purple-400 hover:text-purple-300 flex items-center gap-1 transition"
                                    >
                                        Open in Maps <FaExternalLinkAlt className="w-2.5 h-2.5" />
                                    </a>
                                )}
                            </div>
                        </div>

                        {hasCheckInCoords || hasCheckOutCoords ? (
                            <div className="relative w-full h-72 bg-slate-900">
                                <iframe
                                    title="Attendance OpenStreetMap"
                                    width="100%"
                                    height="100%"
                                    frameBorder="0"
                                    scrolling="no"
                                    marginHeight="0"
                                    marginWidth="0"
                                    src={osmEmbedUrl}
                                    className="w-full h-full opacity-90 filter contrast-105"
                                />

                                {/* Coordinate Overlays */}
                                <div className="absolute bottom-3 left-3 right-3 flex flex-wrap gap-2 pointer-events-none">
                                    {hasCheckInCoords && (
                                        <div className="px-2.5 py-1.5 rounded-lg bg-black/85 backdrop-blur-md border border-emerald-500/40 text-[11px] text-emerald-300 flex items-center gap-1.5 pointer-events-auto">
                                            <span className="w-2 h-2 rounded-full bg-emerald-400"></span>
                                            <strong>Check-In:</strong> {record.check_in_latitude?.toFixed(5)}, {record.check_in_longitude?.toFixed(5)} ({record.check_in_formatted})
                                        </div>
                                    )}
                                    {hasCheckOutCoords && (
                                        <div className="px-2.5 py-1.5 rounded-lg bg-black/85 backdrop-blur-md border border-rose-500/40 text-[11px] text-rose-300 flex items-center gap-1.5 pointer-events-auto">
                                            <span className="w-2 h-2 rounded-full bg-rose-400"></span>
                                            <strong>Check-Out:</strong> {record.check_out_latitude?.toFixed(5)}, {record.check_out_longitude?.toFixed(5)} ({record.check_out_formatted})
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="p-8 text-center text-slate-400">
                                <FaMapMarkerAlt className="w-8 h-8 mx-auto text-slate-600 mb-2" />
                                <p className="text-sm">No GPS coordinates recorded for this shift entry.</p>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Attendance may have been marked manually or offline.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Notes & Verification Form */}
                    {canReview && (
                        <form onSubmit={handleReviewSubmit} className="p-4 rounded-xl bg-[#141b2e] border border-[#22304e] space-y-3">
                            <div className="flex items-center justify-between">
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-purple-300 flex items-center gap-1.5">
                                    <FaShieldAlt className="w-3.5 h-3.5 text-purple-400" />
                                    Supervisor Verification & Approval
                                </h4>
                                {record.reviewed_by && (
                                    <span className="text-[11px] text-slate-400">
                                        Last reviewed by <strong className="text-slate-300">{record.reviewed_by}</strong> on {record.reviewed_at}
                                    </span>
                                )}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-300 mb-1">
                                        Status Decision
                                    </label>
                                    <select
                                        value={status}
                                        onChange={(e) => setStatus(e.target.value)}
                                        className="w-full text-xs rounded-lg bg-[#0e1322] border border-[#273554] text-white py-2 px-2.5 focus:border-purple-500 focus:outline-none"
                                    >
                                        <option value="approved">Approved / Verified</option>
                                        <option value="pending">Pending Verification</option>
                                        <option value="rejected">Rejected / Invalid</option>
                                    </select>
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-xs font-medium text-slate-300 mb-1">
                                        Review Notes
                                    </label>
                                    <div className="flex gap-2">
                                        <input
                                            type="text"
                                            value={reviewNotes}
                                            onChange={(e) => setReviewNotes(e.target.value)}
                                            placeholder="Enter verification remarks (e.g. GPS confirmed on site)..."
                                            className="w-full text-xs rounded-lg bg-[#0e1322] border border-[#273554] text-white py-2 px-3 focus:border-purple-500 focus:outline-none placeholder-slate-500"
                                        />
                                        <button
                                            type="submit"
                                            disabled={isSubmitting}
                                            className="px-4 py-2 text-xs font-medium rounded-lg bg-purple-600 hover:bg-purple-700 text-white transition disabled:opacity-50 shrink-0"
                                        >
                                            {isSubmitting ? "Saving..." : "Save Review"}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end px-6 py-3.5 border-t border-[#1e293b] bg-[#111827]/80">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 text-xs font-medium text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 rounded-lg transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}

