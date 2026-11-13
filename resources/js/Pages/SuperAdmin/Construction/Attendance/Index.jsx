import React, { useState, useMemo } from "react";
import { Head, router } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import StatCard from "@/Pages/Construction/Components/StatCard";
import AttendanceDetailModal from "./Partials/AttendanceDetailModal";
import {
    FaCalendarCheck,
    FaBuilding,
    FaUsers,
    FaClock,
    FaMapMarkerAlt,
    FaSearch,
    FaFilter,
    FaFileDownload,
    FaEye,
    FaCheckCircle,
    FaTimesCircle,
    FaHourglassHalf,
    FaRedoAlt,
} from "react-icons/fa";

export default function Index({
    isProjectOwner = false,
    companies = [],
    projects = [],
    selectedProjectId = null,
    filters = {},
    kpis = {},
    attendanceRecords = [],
    variant = "super",
}) {
    const [selectedRecord, setSelectedRecord] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [search, setSearch] = useState(filters.search || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "all");
    const [dateFilter, setDateFilter] = useState(filters.date || "all");
    const [activeProjectId, setActiveProjectId] = useState(selectedProjectId || "");
    const [activeCompanyId, setActiveCompanyId] = useState(filters.company_id || "");

    const handleFilterChange = (updates = {}) => {
        const query = {
            ...filters,
            search,
            status: statusFilter,
            date: dateFilter,
            project_id: activeProjectId,
            company_id: activeCompanyId,
            ...updates,
        };

        // Remove empty keys
        Object.keys(query).forEach((k) => {
            if (query[k] === "" || query[k] === null || query[k] === "all") {
                delete query[k];
            }
        });

        const routeName =
            variant === "admin"
                ? "admin.construction.attendance.index"
                : "super.construction.attendance.index";

        router.get(route(routeName), query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleProjectSelect = (e) => {
        const pId = e.target.value;
        setActiveProjectId(pId);
        handleFilterChange({ project_id: pId });
    };

    const handleCompanySelect = (e) => {
        const cId = e.target.value;
        setActiveCompanyId(cId);
        setActiveProjectId(""); // Reset project when company changes
        handleFilterChange({ company_id: cId, project_id: "" });
    };

    const handleDateQuickSelect = (val) => {
        setDateFilter(val);
        handleFilterChange({ date: val });
    };

    const handleStatusSelect = (val) => {
        setStatusFilter(val);
        handleFilterChange({ status: val });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        handleFilterChange({ search });
    };

    const handleExport = () => {
        const exportRoute =
            variant === "admin"
                ? route("admin.construction.attendance.export", {
                      project_id: activeProjectId,
                      date: dateFilter,
                  })
                : route("super.construction.attendance.export", {
                      project_id: activeProjectId,
                      date: dateFilter,
                  });

        window.open(exportRoute, "_blank");
    };

    const openDetailModal = (record) => {
        setSelectedRecord(record);
        setIsModalOpen(true);
    };

    return (
        <ConstructionShell variant={variant}>
            <Head title="Workforce & Project Attendance Logs" />

            <div className="space-y-6">
                {/* Header & Title */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-purple-50 text-purple-600 border border-purple-200 dark:bg-purple-600/20 dark:border-purple-500/30 dark:text-purple-400">
                                <FaCalendarCheck className="w-5 h-5" />
                            </span>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                    Project Attendance & Workforce Logs
                                </h1>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Track on-site check-in/out, GPS spatial coordinates, and shift durations.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <button
                            onClick={handleExport}
                            className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300/80 shadow-xs transition dark:bg-[#1e293b] dark:hover:bg-[#28374f] dark:text-slate-200 dark:border-slate-700/80"
                        >
                            <FaFileDownload className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                            Export CSV
                        </button>
                        <button
                            onClick={() => handleFilterChange()}
                            className="p-2 text-slate-600 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-300/80 rounded-xl shadow-xs transition dark:bg-[#1e293b] dark:hover:bg-[#28374f] dark:border-slate-700/80 dark:text-slate-300 dark:hover:text-white"
                            title="Refresh logs"
                        >
                            <FaRedoAlt className="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                {/* KPI Cards Row */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <StatCard
                        label="Total Attendance Logs"
                        value={kpis.totalRecords ?? attendanceRecords.length}
                        icon={FaCalendarCheck}
                    />
                    <StatCard
                        label="Present Today"
                        value={kpis.presentToday ?? 0}
                        icon={FaUsers}
                        badge="Today"
                    />
                    <StatCard
                        label="Checked Out Today"
                        value={kpis.checkedOutToday ?? 0}
                        icon={FaClock}
                        badge="Completed"
                    />
                    <StatCard
                        label="Pending Approval"
                        value={kpis.pendingApproval ?? 0}
                        icon={FaHourglassHalf}
                        badge={kpis.pendingApproval > 0 ? "Review Required" : "All Clear"}
                    />
                </div>

                {/* Filters & Project Selection Bar */}
                <div className="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-3.5 dark:bg-[#0e1322] dark:border-[#1e293b] dark:shadow-lg">
                    <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                        {/* Company Filter (Only for Project Owner) */}
                        {isProjectOwner && companies.length > 0 && (
                            <div className="md:col-span-3">
                                <label className="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">
                                    Company Filter
                                </label>
                                <select
                                    value={activeCompanyId}
                                    onChange={handleCompanySelect}
                                    className="w-full text-xs rounded-xl bg-white border border-slate-300 text-slate-800 py-2 px-3 focus:border-purple-500 focus:ring-1 focus:ring-purple-400 focus:outline-none dark:bg-[#141b2e] dark:border-[#273554] dark:text-white"
                                >
                                    <option value="">All Companies (Global View)</option>
                                    {companies.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {/* Project Selector */}
                        <div className={isProjectOwner ? "md:col-span-4" : "md:col-span-6"}>
                            <label className="block text-[11px] font-semibold uppercase tracking-wider text-purple-700 dark:text-purple-400 mb-1">
                                Select Project
                            </label>
                            <select
                                value={activeProjectId}
                                onChange={handleProjectSelect}
                                className="w-full text-xs font-medium rounded-xl bg-white border border-purple-300 text-slate-900 py-2 px-3 focus:border-purple-500 focus:ring-1 focus:ring-purple-400 focus:outline-none dark:bg-[#141b2e] dark:border-purple-500/40 dark:text-white"
                            >
                                <option value="">-- All Accessible Projects --</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.project_code} • {p.name} {p.company?.name ? `(${p.company.name})` : ""}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Status Filter */}
                        <div className={isProjectOwner ? "md:col-span-2" : "md:col-span-3"}>
                            <label className="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">
                                Status Filter
                            </label>
                            <select
                                value={statusFilter}
                                onChange={(e) => handleStatusSelect(e.target.value)}
                                className="w-full text-xs rounded-xl bg-white border border-slate-300 text-slate-800 py-2 px-3 focus:border-purple-500 focus:ring-1 focus:ring-purple-400 focus:outline-none dark:bg-[#141b2e] dark:border-[#273554] dark:text-white"
                            >
                                <option value="all">All Statuses</option>
                                <option value="approved">Approved</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress (Active)</option>
                                <option value="completed">Completed (Checked Out)</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        {/* Search Input */}
                        <div className={isProjectOwner ? "md:col-span-3" : "md:col-span-3"}>
                            <label className="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">
                                Search Employee / Code
                            </label>
                            <form onSubmit={handleSearchSubmit} className="relative">
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Name, email, code..."
                                    className="w-full text-xs rounded-xl bg-white border border-slate-300 text-slate-800 pl-8 pr-3 py-2 focus:border-purple-500 focus:ring-1 focus:ring-purple-400 focus:outline-none placeholder-slate-400 dark:bg-[#141b2e] dark:border-[#273554] dark:text-white dark:placeholder-slate-500"
                                />
                                <FaSearch className="w-3 h-3 text-slate-400 absolute left-2.5 top-3" />
                            </form>
                        </div>
                    </div>

                    {/* Quick Date Pills */}
                    <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-200/80 dark:border-[#1c263c]">
                        <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 mr-1 flex items-center gap-1">
                            <FaFilter className="w-2.5 h-2.5" /> Date:
                        </span>
                        {[
                            { id: "all", label: "All Dates" },
                            { id: "today", label: "Today" },
                            { id: "yesterday", label: "Yesterday" },
                        ].map((d) => (
                            <button
                                key={d.id}
                                type="button"
                                onClick={() => handleDateQuickSelect(d.id)}
                                className={`px-2.5 py-1 text-xs font-medium rounded-lg transition ${
                                    dateFilter === d.id
                                        ? "bg-purple-600 text-white shadow-xs"
                                        : "bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-[#141b2e] dark:text-slate-300 dark:hover:bg-[#1a233a] dark:border-slate-700/60"
                                }`}
                            >
                                {d.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Attendance Data Table */}
                <div className="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden dark:border-[#1e293b] dark:bg-[#0e1322] dark:shadow-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                            <thead className="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200 dark:bg-[#141b2e] dark:text-slate-400 dark:border-[#1e293b]">
                                <tr>
                                    <th className="px-4 py-3.5">Employee</th>
                                    <th className="px-4 py-3.5">Project</th>
                                    <th className="px-4 py-3.5">Date</th>
                                    <th className="px-4 py-3.5">Check-In</th>
                                    <th className="px-4 py-3.5">Check-Out</th>
                                    <th className="px-4 py-3.5">Duration</th>
                                    <th className="px-4 py-3.5">Status</th>
                                    <th className="px-4 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-[#18233b]">
                                {attendanceRecords.length > 0 ? (
                                    attendanceRecords.map((record) => (
                                        <tr
                                            key={record.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-[#131b2e]/60 transition"
                                        >
                                            {/* Employee */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <img
                                                        src={
                                                            record.employee?.avatar ||
                                                            "/images/profileimg.png"
                                                        }
                                                        alt={record.employee?.name}
                                                        className="w-9 h-9 rounded-full object-cover border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
                                                        onError={(e) => {
                                                            e.target.src = "/images/profileimg.png";
                                                        }}
                                                    />
                                                    <div>
                                                        <div className="font-semibold text-slate-900 dark:text-white">
                                                            {record.employee?.name}
                                                        </div>
                                                        <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                            {record.employee?.employee_code} • {record.employee?.designation}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Project */}
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-slate-800 dark:text-slate-200">
                                                    {record.project_name || "N/A"}
                                                </div>
                                                <div className="text-[10px] text-purple-700 dark:text-purple-400 font-mono">
                                                    {record.project_code}
                                                </div>
                                            </td>

                                            {/* Date */}
                                            <td className="px-4 py-3.5 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                                {record.attendance_date_formatted || record.attendance_date}
                                            </td>

                                            {/* Check-In */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {record.check_in_formatted || "—"}
                                                </span>
                                                {record.check_in_latitude && (
                                                    <div className="text-[10px] text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                                        <FaMapMarkerAlt className="w-2.5 h-2.5 text-emerald-500 dark:text-emerald-400 shrink-0" />
                                                        {record.check_in_latitude.toFixed(3)}, {record.check_in_longitude?.toFixed(3)}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Check-Out */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {record.check_out_formatted ? (
                                                    <span className="font-semibold text-rose-600 dark:text-rose-400">
                                                        {record.check_out_formatted}
                                                    </span>
                                                ) : record.is_in_progress ? (
                                                    <span className="px-2 py-0.5 text-[10px] rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-medium dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20">
                                                        In Progress
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 dark:text-slate-500">—</span>
                                                )}
                                                {record.check_out_latitude && (
                                                    <div className="text-[10px] text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                                        <FaMapMarkerAlt className="w-2.5 h-2.5 text-rose-500 dark:text-rose-400 shrink-0" />
                                                        {record.check_out_latitude.toFixed(3)}, {record.check_out_longitude?.toFixed(3)}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Duration */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                <span className="px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 font-semibold text-xs border border-purple-200/80 dark:bg-[#19233c] dark:text-purple-300 dark:border-purple-500/20">
                                                    {record.duration_formatted}
                                                </span>
                                            </td>

                                            {/* Status */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {record.status === "approved" ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/30">
                                                        <FaCheckCircle className="w-3 h-3 text-emerald-600 dark:text-emerald-400" /> Approved
                                                    </span>
                                                ) : record.status === "rejected" ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/30">
                                                        <FaTimesCircle className="w-3 h-3 text-rose-600 dark:text-rose-400" /> Rejected
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30">
                                                        <FaHourglassHalf className="w-3 h-3 text-amber-600 dark:text-amber-400" /> Pending
                                                    </span>
                                                )}
                                            </td>

                                            {/* Actions */}
                                            <td className="px-4 py-3.5 whitespace-nowrap text-right">
                                                <button
                                                    onClick={() => openDetailModal(record)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200/80 transition text-xs font-semibold shadow-2xs dark:bg-purple-600/15 dark:hover:bg-purple-600/30 dark:text-purple-300 dark:border-purple-500/30"
                                                >
                                                    <FaEye className="w-3 h-3" /> View Details & Map
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-4 py-12 text-center text-slate-500 dark:text-slate-400"
                                        >
                                            <FaCalendarCheck className="w-8 h-8 mx-auto text-slate-400 dark:text-slate-600 mb-2" />
                                            <p className="text-sm font-semibold text-slate-700 dark:text-slate-300">No attendance records found</p>
                                            <p className="text-xs text-slate-500 mt-0.5">
                                                Try selecting a different project or adjusting the date/status filter.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Attendance Detail & GPS Map Modal */}
            <AttendanceDetailModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                record={selectedRecord}
                variant={variant}
                canReview={true}
            />
        </ConstructionShell>
    );
}
