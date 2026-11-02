import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import {
    FaHistory,
    FaSearch,
    FaFilter,
    FaCalendarAlt,
    FaBuilding,
    FaProjectDiagram,
    FaUser,
    FaMobileAlt,
    FaLaptop,
    FaTimes,
    FaEye,
    FaMapMarkerAlt,
    FaGlobe,
} from "react-icons/fa";

export default function AuditLogsIndex({
    logs = { data: [], links: [] },
    filters = {},
    projects = [],
    companies = [],
    modules = [],
    actions = [],
    channels = [],
    isProjectOwner = false,
}) {
    const [selectedLog, setSelectedLog] = useState(null);

    const [formFilters, setFormFilters] = useState({
        search: filters.search || "",
        project_id: filters.project_id || "all",
        company_id: filters.company_id || "all",
        module: filters.module || "all",
        action: filters.action || "all",
        channel: filters.channel || "all",
        actor_type: filters.actor_type || "all",
        from_date: filters.from_date || "",
        to_date: filters.to_date || "",
        per_page: filters.per_page || 25,
    });

    const handleFilterChange = (key, value) => {
        const updated = { ...formFilters, [key]: value };
        setFormFilters(updated);
        router.get(route("super.audit_logs.index"), updated, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        router.get(route("super.audit_logs.index"), formFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        const cleared = {
            search: "",
            project_id: "all",
            company_id: "all",
            module: "all",
            action: "all",
            channel: "all",
            actor_type: "all",
            from_date: "",
            to_date: "",
            per_page: 25,
        };
        setFormFilters(cleared);
        router.get(route("super.audit_logs.index"), cleared);
    };

    const formatChannelBadge = (ch) => {
        const val = String(ch || "").toLowerCase();
        if (val.includes("mobile")) {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-semibold text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-950/60 dark:text-purple-300">
                    <FaMobileAlt className="text-[10px]" /> Mobile App
                </span>
            );
        }
        if (val.includes("superadmin")) {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-950/60 dark:text-indigo-300">
                    <FaLaptop className="text-[10px]" /> SuperAdmin Web
                </span>
            );
        }
        if (val.includes("admin")) {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-950/60 dark:text-blue-300">
                    <FaLaptop className="text-[10px]" /> Admin Web
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 ring-1 ring-inset ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300">
                <FaGlobe className="text-[10px]" /> {ch || "API / Web"}
            </span>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Activity & Audit Logs" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                <FaHistory className="h-5 w-5" />
                            </div>
                            <div>
                                <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                                    Activity & Audit Logs
                                </h1>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Comprehensive real-time audit trail of actions across Web Panels and Mobile APIs.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filter Toolbar */}
                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <form onSubmit={handleSearchSubmit} className="space-y-3">
                        <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">
                            {/* Search */}
                            <div className="relative col-span-1 sm:col-span-2">
                                <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Search by actor, description, reference, IP..."
                                    value={formFilters.search}
                                    onChange={(e) => setFormFilters({ ...formFilters, search: e.target.value })}
                                    className="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            {/* Project Filter */}
                            <div>
                                <select
                                    value={formFilters.project_id}
                                    onChange={(e) => handleFilterChange("project_id", e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                >
                                    <option value="all">All Projects</option>
                                    {projects.map((p) => (
                                        <option key={p.id} value={String(p.id)}>
                                            {p.project_code} • {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Company Filter (Project Owner only) */}
                            {isProjectOwner && (
                                <div>
                                    <select
                                        value={formFilters.company_id}
                                        onChange={(e) => handleFilterChange("company_id", e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    >
                                        <option value="all">All Companies</option>
                                        {companies.map((c) => (
                                            <option key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Module Filter */}
                            <div>
                                <select
                                    value={formFilters.module}
                                    onChange={(e) => handleFilterChange("module", e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                >
                                    <option value="all">All Modules</option>
                                    {modules.map((m) => (
                                        <option key={m} value={m}>
                                            {String(m).replace(/_/g, " ").toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Channel Filter */}
                            <div>
                                <select
                                    value={formFilters.channel}
                                    onChange={(e) => handleFilterChange("channel", e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                >
                                    <option value="all">All Channels</option>
                                    {channels.map((ch) => (
                                        <option key={ch} value={ch}>
                                            {String(ch).replace(/_/g, " ").toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Actor Type */}
                            <div>
                                <select
                                    value={formFilters.actor_type}
                                    onChange={(e) => handleFilterChange("actor_type", e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                >
                                    <option value="all">All Actor Types</option>
                                    <option value="superadmin">Super Admins</option>
                                    <option value="member">Members / Employees</option>
                                </select>
                            </div>

                            {/* Date From */}
                            <div>
                                <input
                                    type="date"
                                    value={formFilters.from_date}
                                    onChange={(e) => handleFilterChange("from_date", e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            {/* Date To */}
                            <div>
                                <input
                                    type="date"
                                    value={formFilters.to_date}
                                    onChange={(e) => handleFilterChange("to_date", e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            {/* Actions Buttons */}
                            <div className="flex items-center gap-2">
                                <button
                                    type="submit"
                                    className="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                >
                                    <FaSearch className="text-[10px]" /> Filter
                                </button>
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    <FaTimes className="text-[10px]" /> Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {/* Logs Table */}
                <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    {logs.data.length === 0 ? (
                        <div className="p-8 text-center">
                            <FaHistory className="mx-auto h-8 w-8 text-slate-400" />
                            <h3 className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                                No activity logs recorded
                            </h3>
                            <p className="mt-1 text-xs text-slate-500">
                                Activity records will appear as actions are performed on the platform.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 text-xs dark:divide-slate-800">
                                <thead className="bg-slate-50/70 text-left font-semibold text-slate-600 dark:bg-slate-950 dark:text-slate-300">
                                    <tr>
                                        <th className="px-4 py-3">Timestamp</th>
                                        <th className="px-4 py-3">Actor</th>
                                        <th className="px-4 py-3">Channel</th>
                                        <th className="px-4 py-3">Module & Action</th>
                                        <th className="px-4 py-3">Project / Reference</th>
                                        <th className="px-4 py-3">Description</th>
                                        <th className="px-4 py-3 text-right">Details</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {logs.data.map((log) => {
                                        const actorName = log.actor?.name || `Actor #${log.actor_id}`;
                                        const actorEmail = log.actor?.email || "";
                                        const isSuper = log.actor_type?.includes("SuperAdmin");

                                        return (
                                            <tr
                                                key={log.id}
                                                className="transition hover:bg-slate-50/70 dark:hover:bg-slate-950/40"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-400">
                                                    <span className="font-medium text-slate-900 dark:text-white">
                                                        {new Date(log.created_at).toLocaleDateString("en-GB", {
                                                            day: "2-digit",
                                                            month: "short",
                                                            year: "numeric",
                                                        })}
                                                    </span>
                                                    <p className="text-[10px] text-slate-400">
                                                        {new Date(log.created_at).toLocaleTimeString("en-GB", {
                                                            hour: "2-digit",
                                                            minute: "2-digit",
                                                        })}
                                                    </p>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300 text-[10px]">
                                                            {actorName.slice(0, 2).toUpperCase()}
                                                        </div>
                                                        <div>
                                                            <div className="flex items-center gap-1.5">
                                                                <span className="font-semibold text-slate-900 dark:text-white">
                                                                    {actorName}
                                                                </span>
                                                                <span
                                                                    className={`rounded px-1.5 py-0.2 text-[9px] font-semibold ${
                                                                        isSuper
                                                                            ? "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                                                            : "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                                                    }`}
                                                                >
                                                                    {isSuper ? "SuperAdmin" : "Member"}
                                                                </span>
                                                            </div>
                                                            {actorEmail && (
                                                                <p className="text-[10px] text-slate-400">
                                                                    {actorEmail}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {formatChannelBadge(log.channel)}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <span className="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        {log.module}
                                                    </span>
                                                    <p className="mt-0.5 font-semibold text-slate-900 dark:text-white capitalize">
                                                        {log.action?.replace(/_/g, " ")}
                                                    </p>
                                                </td>

                                                <td className="px-4 py-3">
                                                    {log.project ? (
                                                        <div>
                                                            <span className="font-semibold text-indigo-600 dark:text-indigo-400">
                                                                {log.project.project_code}
                                                            </span>
                                                            <p className="text-[10px] text-slate-500">
                                                                {log.project.name}
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <span className="text-slate-400">-</span>
                                                    )}
                                                    {log.reference_code && (
                                                        <p className="text-[10px] font-mono text-slate-400">
                                                            Ref: {log.reference_code}
                                                        </p>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 max-w-xs truncate text-slate-700 dark:text-slate-300">
                                                    {log.description || "-"}
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedLog(log)}
                                                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"
                                                    >
                                                        <FaEye className="text-[10px]" /> Inspect
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination Links */}
                    {logs.links && logs.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 p-4 text-xs dark:border-slate-800">
                            <span className="text-slate-500">
                                Showing {logs.from} to {logs.to} of {logs.total} logs
                            </span>
                            <div className="flex flex-wrap items-center gap-1">
                                {logs.links.map((link, idx) => (
                                    <button
                                        key={idx}
                                        disabled={!link.url || link.active}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                        className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                            link.active
                                                ? "bg-indigo-600 text-white"
                                                : link.url
                                                  ? "bg-slate-50 text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300"
                                                  : "opacity-40"
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Inspect Modal Drawer */}
            {selectedLog && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                            <div className="flex items-center gap-2.5">
                                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    <FaHistory className="h-4 w-4" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                        Log Inspection: #{selectedLog.id}
                                    </h3>
                                    <p className="text-xs text-slate-500">
                                        {selectedLog.module} • {selectedLog.action}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedLog(null)}
                                className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                <FaTimes className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="mt-4 space-y-4 text-xs">
                            <div className="grid gap-3 sm:grid-cols-2 rounded-xl bg-slate-50 p-4 dark:bg-slate-950">
                                <div>
                                    <span className="text-slate-400">Timestamp:</span>
                                    <p className="font-semibold text-slate-900 dark:text-white">
                                        {new Date(selectedLog.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-slate-400">Channel / IP:</span>
                                    <p className="font-semibold text-slate-900 dark:text-white">
                                        {selectedLog.channel} • {selectedLog.ip_address || "N/A"}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-slate-400">Actor:</span>
                                    <p className="font-semibold text-slate-900 dark:text-white">
                                        {selectedLog.actor?.name || "System"} ({selectedLog.actor_type})
                                    </p>
                                </div>
                                <div>
                                    <span className="text-slate-400">Reference:</span>
                                    <p className="font-semibold text-slate-900 dark:text-white">
                                        {selectedLog.reference_code || selectedLog.reference_id || "N/A"} ({selectedLog.reference_type || "N/A"})
                                    </p>
                                </div>
                            </div>

                            {selectedLog.description && (
                                <div>
                                    <span className="font-semibold text-slate-700 dark:text-slate-300">
                                        Summary Description:
                                    </span>
                                    <p className="mt-1 rounded-xl border border-slate-200 bg-white p-3 text-slate-800 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                                        {selectedLog.description}
                                    </p>
                                </div>
                            )}

                            <div>
                                <span className="font-semibold text-slate-700 dark:text-slate-300">
                                    Metadata & Field Payload:
                                </span>
                                <pre className="mt-1 max-h-60 overflow-auto rounded-xl border border-slate-200 bg-slate-950 p-3 font-mono text-[11px] text-emerald-400 dark:border-slate-800">
                                    {JSON.stringify(selectedLog.meta || {}, null, 2)}
                                </pre>
                            </div>

                            {selectedLog.user_agent && (
                                <div>
                                    <span className="text-slate-400">User Agent:</span>
                                    <p className="font-mono text-[10px] text-slate-500 break-all">
                                        {selectedLog.user_agent}
                                    </p>
                                </div>
                            )}

                            <div className="flex justify-end pt-2">
                                <button
                                    type="button"
                                    onClick={() => setSelectedLog(null)}
                                    className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

