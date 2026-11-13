import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import StatCard from "@/Pages/Construction/Components/StatCard";
import FuelDetailModal from "./Partials/FuelDetailModal";
import {
    FaGasPump,
    FaCar,
    FaBuilding,
    FaUsers,
    FaHourglassHalf,
    FaCheckCircle,
    FaTimesCircle,
    FaSearch,
    FaFilter,
    FaFileDownload,
    FaEye,
    FaRedoAlt,
    FaReceipt,
    FaTachometerAlt,
} from "react-icons/fa";

export default function Index({
    isProjectOwner = false,
    companies = [],
    projects = [],
    selectedProjectId = null,
    filters = {},
    kpis = {},
    fuelEntries = [],
    can = {},
    variant = "super",
}) {
    const [selectedEntry, setSelectedEntry] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [search, setSearch] = useState(filters.search || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "all");
    const [fuelTypeFilter, setFuelTypeFilter] = useState(filters.fuel_type || "all");
    const [dateFilter, setDateFilter] = useState(filters.date || "all");
    const [activeProjectId, setActiveProjectId] = useState(selectedProjectId || "");
    const [activeCompanyId, setActiveCompanyId] = useState(filters.company_id || "");

    const handleFilterChange = (updates = {}) => {
        const query = {
            ...filters,
            search,
            status: statusFilter,
            fuel_type: fuelTypeFilter,
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
                ? "admin.construction.fuel.index"
                : "super.construction.fuel.index";

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
        setActiveProjectId("");
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

    const handleFuelTypeSelect = (val) => {
        setFuelTypeFilter(val);
        handleFilterChange({ fuel_type: val });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        handleFilterChange({ search });
    };

    const handleExport = () => {
        const exportRoute =
            variant === "admin"
                ? route("admin.construction.fuel.export", {
                      project_id: activeProjectId,
                      company_id: activeCompanyId,
                      status: statusFilter,
                      fuel_type: fuelTypeFilter,
                  })
                : route("super.construction.fuel.export", {
                      project_id: activeProjectId,
                      company_id: activeCompanyId,
                      status: statusFilter,
                      fuel_type: fuelTypeFilter,
                  });

        window.open(exportRoute, "_blank");
    };

    const openDetailModal = (entry) => {
        setSelectedEntry(entry);
        setIsModalOpen(true);
    };

    const getStatusPill = (status) => {
        switch (status) {
            case "approved":
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/60">
                        <FaCheckCircle className="w-3 h-3" /> Approved
                    </span>
                );
            case "rejected":
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-800/60">
                        <FaTimesCircle className="w-3 h-3" /> Rejected
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/60">
                        <FaHourglassHalf className="w-3 h-3" /> Pending
                    </span>
                );
        }
    };

    const getFuelTypeBadge = (fuelType) => {
        const type = String(fuelType || "Diesel").toLowerCase();
        if (type.includes("diesel")) {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700/50">
                    <FaGasPump className="w-2.5 h-2.5" /> Diesel
                </span>
            );
        }
        if (type.includes("petrol")) {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700/50">
                    <FaGasPump className="w-2.5 h-2.5" /> Petrol
                </span>
            );
        }
        if (type.includes("cng")) {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700/50">
                    <FaGasPump className="w-2.5 h-2.5" /> CNG
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700/50">
                <FaGasPump className="w-2.5 h-2.5" /> {fuelType}
            </span>
        );
    };

    return (
        <ConstructionShell variant={variant}>
            <Head title="Vehicle Fuel History & Verification Logs" />

            <div className="space-y-6">
                {/* Header & Title */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-purple-50 text-purple-600 border border-purple-200 dark:bg-purple-600/20 dark:border-purple-500/30 dark:text-purple-400">
                                <FaGasPump className="w-5 h-5" />
                            </span>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                    Vehicle Fuel History & Verification Logs
                                </h1>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Monitor vehicle fuel consumption, odometer readings, station receipts, and approval statuses.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5">
                        {can.export !== false && (
                            <button
                                onClick={handleExport}
                                className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300/80 shadow-xs transition dark:bg-[#1e293b] dark:hover:bg-[#28374f] dark:text-slate-200 dark:border-slate-700/80"
                            >
                                <FaFileDownload className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                                Export CSV
                            </button>
                        )}
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
                        label="Total Fuel Entries"
                        value={kpis.total ?? fuelEntries.length}
                        icon={FaGasPump}
                    />
                    <StatCard
                        label="Pending Verification"
                        value={kpis.pending ?? 0}
                        icon={FaHourglassHalf}
                        badge="Action Needed"
                    />
                    <StatCard
                        label="Approved Volume"
                        value={`${Number(kpis.total_liters ?? 0).toFixed(0)} L`}
                        icon={FaCar}
                        badge={`₹${Number(kpis.approved_amount ?? 0).toLocaleString("en-IN")}`}
                    />
                    <StatCard
                        label="Declined / Rejected"
                        value={kpis.rejected ?? 0}
                        icon={FaTimesCircle}
                        badge="Declined"
                    />
                </div>

                {/* Dynamic Filters Bar */}
                <div className="p-4 rounded-2xl bg-white dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b] shadow-xs space-y-3.5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        {/* Company Filter (Only for Project Owner) */}
                        {isProjectOwner && companies.length > 0 && (
                            <div>
                                <label className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                    Select Company
                                </label>
                                <select
                                    value={activeCompanyId}
                                    onChange={handleCompanySelect}
                                    className="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-[#0e1322] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                                >
                                    <option value="">-- All Companies --</option>
                                    {companies.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {/* Project Dynamic Selector */}
                        <div className={!isProjectOwner ? "sm:col-span-2" : ""}>
                            <label className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                Select Project
                            </label>
                            <select
                                value={activeProjectId}
                                onChange={handleProjectSelect}
                                className="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-[#0e1322] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                            >
                                <option value="">
                                    {isProjectOwner
                                        ? "-- All Accessible Projects --"
                                        : "-- All Company Projects --"}
                                </option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.project_code ? `[${p.project_code}] ` : ""}
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Status Filter */}
                        <div>
                            <label className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                Status Filter
                            </label>
                            <select
                                value={statusFilter}
                                onChange={(e) => handleStatusSelect(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-[#0e1322] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                            >
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending Verification</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        {/* Fuel Type Filter */}
                        <div>
                            <label className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                Fuel Type
                            </label>
                            <select
                                value={fuelTypeFilter}
                                onChange={(e) => handleFuelTypeSelect(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-[#0e1322] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                            >
                                <option value="all">All Fuel Types</option>
                                <option value="Diesel">Diesel</option>
                                <option value="Petrol">Petrol</option>
                                <option value="CNG">CNG</option>
                                <option value="Electric">Electric</option>
                            </select>
                        </div>
                    </div>

                    {/* Search & Quick Date Filter Pills */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-slate-100 dark:border-[#1e293b]/70">
                        {/* Quick Date Buttons */}
                        <div className="flex items-center gap-1.5 flex-wrap">
                            <span className="text-[11px] font-semibold text-slate-400 mr-1 flex items-center gap-1">
                                <FaFilter className="w-2.5 h-2.5" /> Date:
                            </span>
                            {[
                                { key: "all", label: "All Dates" },
                                { key: "today", label: "Today" },
                                { key: "yesterday", label: "Yesterday" },
                            ].map((tab) => (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => handleDateQuickSelect(tab.key)}
                                    className={`px-3 py-1 rounded-lg text-xs font-semibold transition ${
                                        dateFilter === tab.key
                                            ? "bg-purple-600 text-white shadow-xs"
                                            : "bg-slate-100 dark:bg-[#0e1322] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        {/* Search Input */}
                        <form onSubmit={handleSearchSubmit} className="relative sm:w-80">
                            <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-3 h-3" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search driver, vehicle, project..."
                                className="w-full pl-9 pr-4 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-[#0e1322] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                            />
                        </form>
                    </div>
                </div>

                {/* Fuel Records Data Table */}
                <div className="overflow-hidden rounded-2xl bg-white dark:bg-[#151c33] border border-slate-200 dark:border-[#1e293b] shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 dark:bg-[#0e1322] border-b border-slate-200 dark:border-[#1e293b] text-slate-500 dark:text-slate-400 uppercase font-bold text-[10px] tracking-wider">
                                <tr>
                                    <th className="px-5 py-3.5">Driver / Employee</th>
                                    <th className="px-4 py-3.5">Project</th>
                                    <th className="px-4 py-3.5">Vehicle</th>
                                    <th className="px-4 py-3.5">Fuel Date</th>
                                    <th className="px-4 py-3.5">Fuel & Volume</th>
                                    <th className="px-4 py-3.5">Amount (₹)</th>
                                    <th className="px-4 py-3.5">Odometer</th>
                                    <th className="px-4 py-3.5">Receipt</th>
                                    <th className="px-4 py-3.5">Status</th>
                                    <th className="px-5 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-[#1e293b]/70">
                                {fuelEntries.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="px-6 py-12 text-center">
                                            <FaGasPump className="w-8 h-8 text-slate-300 dark:text-slate-600 mx-auto mb-2 opacity-50" />
                                            <p className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                                No vehicle fuel entries found
                                            </p>
                                            <p className="text-xs text-slate-400 mt-1">
                                                Try adjusting your project selection, date range, or search criteria.
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    fuelEntries.map((entry) => (
                                        <tr
                                            key={entry.id}
                                            className="hover:bg-slate-50/70 dark:hover:bg-[#1a223e] transition"
                                        >
                                            {/* Driver */}
                                            <td className="px-5 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    {entry.driver?.avatar ? (
                                                        <img
                                                            src={entry.driver.avatar}
                                                            alt={entry.driver.name}
                                                            className="w-8 h-8 rounded-full object-cover ring-1 ring-purple-500/20"
                                                        />
                                                    ) : (
                                                        <div className="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-300 font-bold flex items-center justify-center text-xs">
                                                            {entry.driver?.name?.charAt(0) || "D"}
                                                        </div>
                                                    )}
                                                    <div className="min-w-0">
                                                        <div className="font-bold text-slate-900 dark:text-white truncate">
                                                            {entry.driver?.name || "Unknown"}
                                                        </div>
                                                        <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                            {entry.driver?.employee_code}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Project */}
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-slate-800 dark:text-slate-200 truncate max-w-[150px]">
                                                    {entry.project?.name || "Unassigned"}
                                                </div>
                                                <div className="text-[11px] font-mono text-purple-600 dark:text-purple-400">
                                                    {entry.project?.project_code || "PRJ-GENERAL"}
                                                </div>
                                            </td>

                                            {/* Vehicle */}
                                            <td className="px-4 py-3.5">
                                                <div className="font-bold text-slate-900 dark:text-white">
                                                    {entry.vehicle_number}
                                                </div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {entry.vehicle?.vehicle_code || entry.vehicle?.vehicle_type || "Fleet"}
                                                </div>
                                            </td>

                                            {/* Date */}
                                            <td className="px-4 py-3.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                                {entry.fuel_date_formatted || entry.fuel_date}
                                            </td>

                                            {/* Fuel & Volume */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex flex-col gap-1 items-start">
                                                    {getFuelTypeBadge(entry.fuel_type)}
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {Number(entry.quantity_liters).toFixed(2)} L
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Amount */}
                                            <td className="px-4 py-3.5">
                                                <div className="font-bold text-slate-900 dark:text-white">
                                                    ₹{Number(entry.total_amount).toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                                                </div>
                                                {entry.rate_per_liter > 0 && (
                                                    <div className="text-[10px] text-slate-500 dark:text-slate-400">
                                                        ₹{Number(entry.rate_per_liter).toFixed(2)}/L
                                                    </div>
                                                )}
                                            </td>

                                            {/* Odometer */}
                                            <td className="px-4 py-3.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                                <span className="flex items-center gap-1 font-mono">
                                                    <FaTachometerAlt className="w-3 h-3 text-slate-400" />
                                                    {Number(entry.odometer_reading).toLocaleString()} KM
                                                </span>
                                            </td>

                                            {/* Receipt */}
                                            <td className="px-4 py-3.5">
                                                {entry.receipt_photo_url ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => openDetailModal(entry)}
                                                        className="relative group block w-10 h-10 rounded-lg overflow-hidden border border-purple-300 dark:border-purple-800"
                                                        title="Click to view full receipt"
                                                    >
                                                        <img
                                                            src={entry.receipt_photo_url}
                                                            alt="Receipt"
                                                            className="w-full h-full object-cover group-hover:scale-110 transition"
                                                        />
                                                        <span className="absolute inset-0 bg-slate-900/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition text-white">
                                                            <FaEye className="w-3 h-3" />
                                                        </span>
                                                    </button>
                                                ) : (
                                                    <span className="text-[11px] text-slate-400 italic">
                                                        No receipt
                                                    </span>
                                                )}
                                            </td>

                                            {/* Status */}
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {getStatusPill(entry.status)}
                                            </td>

                                            {/* Actions (Gated by can.details) */}
                                            <td className="px-5 py-3.5 text-right whitespace-nowrap">
                                                {can.details !== false && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openDetailModal(entry)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200/80 transition dark:bg-purple-900/30 dark:hover:bg-purple-900/50 dark:text-purple-300 dark:border-purple-800/60"
                                                    >
                                                        <FaEye className="w-3 h-3" />
                                                        <span>View Details</span>
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Verification & Detail Modal */}
            <FuelDetailModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                entry={selectedEntry}
                variant={variant}
                canVerify={can.verify !== false}
            />
        </ConstructionShell>
    );
}

