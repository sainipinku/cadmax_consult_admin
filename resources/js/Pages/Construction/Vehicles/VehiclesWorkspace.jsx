import { router, useForm, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import { formatMemberSelectOption } from "@/lib/memberBadges";
import {
    FaCheckCircle,
    FaExclamationTriangle,
    FaClock,
    FaCalendarAlt,
    FaSearch,
    FaUndo,
    FaPlus,
    FaTruck,
    FaRoute,
    FaUser,
    FaTimes,
} from "react-icons/fa";

export default function VehiclesWorkspace({
    variant = "super",
    projects = [],
    vehicles = [],
    assignments = [],
    members = [],
    pings = [],
}) {
    const pageProps = usePage().props;
    const auth = pageProps?.auth;
    const isSuperAdmin =
        auth?.guard === "superadmin" ||
        auth?.user?.slug === "super-admin" ||
        Boolean(auth?.user?.is_super_admin);
    const userPermissions =
        auth?.permissions || auth?.construction_permissions || pageProps?.permissions || [];

    const hasPerm = (permSlug) => {
        if (isSuperAdmin) return true;
        if (userPermissions.includes(permSlug)) return true;
        const prefix = permSlug.split(".")[0];
        return userPermissions.some(
            (p) => typeof p === "string" && (p === permSlug || p.startsWith(prefix + "."))
        );
    };

    const routeBase =
        variant === "super"
            ? "super.construction.vehicles"
            : variant === "admin"
              ? "admin.construction.vehicles"
              : "member.construction.vehicles";

    const canManageAssignments =
        isSuperAdmin || hasPerm("vehicle_assignment.manage") || hasPerm("vehicle.manage");
    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    // Tab state
    const [activeTab, setActiveTab] = useState("occupied"); // 'occupied' | 'free' | 'assign' | 'history' | 'pings'
    const [searchQuery, setSearchQuery] = useState("");
    const [filterProjectId, setFilterProjectId] = useState("all");

    // Modal state for Return Vehicle
    const [returningAssignment, setReturningAssignment] = useState(null);

    // Normalize helper
    const normalize = (str) =>
        str ? String(str).trim().toLowerCase().replace(/[\s\-_.]/g, "") : "";

    // Active assignments
    const activeAssignments = useMemo(
        () => assignments.filter((a) => a.status === "active"),
        [assignments]
    );

    // Inactive / History assignments
    const historyAssignments = useMemo(
        () => assignments.filter((a) => a.status !== "active"),
        [assignments]
    );

    // Track occupied vehicles
    const occupiedKeys = useMemo(() => {
        const keys = new Set();
        activeAssignments.forEach((a) => {
            if (a.vehicle?.registration_number) {
                keys.add(normalize(a.vehicle.registration_number));
            }
            if (a.vehicle?.vehicle_code) {
                keys.add(normalize(a.vehicle.vehicle_code));
            }
            if (a.vehicle_id) {
                keys.add(String(a.vehicle_id));
            }
        });
        return keys;
    }, [activeAssignments]);

    // Free / Available vehicles
    const freeVehicles = useMemo(() => {
        return vehicles.filter((v) => {
            const vStatus = String(v.status || "").toLowerCase();
            if (["maintenance", "damaged", "scrapped", "inactive"].includes(vStatus)) {
                return false;
            }
            const reg = normalize(v.registration_number || v.vehicle_number);
            const code = normalize(v.vehicle_code || v.vehicle_id);
            const idStr = String(v.id);
            if (reg && occupiedKeys.has(reg)) return false;
            if (code && occupiedKeys.has(code)) return false;
            if (idStr && occupiedKeys.has(idStr)) return false;
            return true;
        });
    }, [vehicles, occupiedKeys]);

    // Overdue assignments
    const overdueAssignments = useMemo(() => {
        const now = new Date();
        return activeAssignments.filter((a) => {
            if (!a.assigned_to) return false;
            return new Date(a.assigned_to) < now;
        });
    }, [activeAssignments]);

    // Stats
    const stats = useMemo(() => {
        return {
            totalVehicles: vehicles.length,
            freeVehicles: freeVehicles.length,
            occupiedVehicles: activeAssignments.length,
            overdueCount: overdueAssignments.length,
            projects: projects.length,
        };
    }, [vehicles, freeVehicles, activeAssignments, overdueAssignments, projects]);

    const nowLocal = new Date().toISOString().slice(0, 16);

    // Assignment Form
    const assignmentForm = useForm({
        project_id: firstProjectId,
        vehicle_id: "",
        driver_member_id: "",
        assigned_from: nowLocal,
        assigned_to: "",
        note: "",
        status: "active",
    });

    // Return Form
    const returnForm = useForm({
        assignment_id: "",
    });

    const driverMembers = useMemo(() => {
        const drivers = members.filter((member) => !!member.is_driver);
        return drivers.length > 0 ? drivers : members;
    }, [members]);

    // Quick assign free vehicle
    const handleQuickAssign = (vehicle) => {
        const vValue = vehicle.source ? `${vehicle.source}_${vehicle.id}` : String(vehicle.id);
        const targetPid = vehicle.project_id ? String(vehicle.project_id) : firstProjectId;
        assignmentForm.setData((data) => ({
            ...data,
            project_id: targetPid,
            vehicle_id: vValue,
            assigned_from: new Date().toISOString().slice(0, 16),
        }));
        setActiveTab("assign");
    };

    // Open return vehicle modal
    const handleOpenReturnModal = (assignment) => {
        setReturningAssignment(assignment);
        returnForm.setData({
            assignment_id: String(assignment.id),
        });
    };

    const handleConfirmReturn = (event) => {
        event.preventDefault();
        returnForm.post(route(`${routeBase}.assignments.return`), {
            preserveScroll: true,
            onSuccess: () => {
                setReturningAssignment(null);
                returnForm.reset();
            },
        });
    };

    // Filtered occupied assignments
    const filteredOccupiedAssignments = useMemo(() => {
        return activeAssignments.filter((a) => {
            if (filterProjectId !== "all" && String(a.project_id) !== String(filterProjectId)) {
                return false;
            }
            if (!searchQuery.trim()) return true;
            const q = searchQuery.toLowerCase();
            const reg = String(a.vehicle?.registration_number || "").toLowerCase();
            const code = String(a.vehicle?.vehicle_code || a.vehicle_id || "").toLowerCase();
            const driver = String(a.driver?.name || a.driver?.email || "").toLowerCase();
            const proj = String(a.project?.name || a.project?.project_code || "").toLowerCase();
            return reg.includes(q) || code.includes(q) || driver.includes(q) || proj.includes(q);
        });
    }, [activeAssignments, filterProjectId, searchQuery]);

    // Filtered free vehicles
    const filteredFreeVehicles = useMemo(() => {
        return freeVehicles.filter((v) => {
            if (filterProjectId !== "all" && v.project_id && String(v.project_id) !== String(filterProjectId)) {
                return false;
            }
            if (!searchQuery.trim()) return true;
            const q = searchQuery.toLowerCase();
            const reg = String(v.registration_number || v.vehicle_number || "").toLowerCase();
            const code = String(v.vehicle_code || v.vehicle_id || "").toLowerCase();
            const name = String(v.vehicle_name || "").toLowerCase();
            const type = String(v.vehicle_type || "").toLowerCase();
            return reg.includes(q) || code.includes(q) || name.includes(q) || type.includes(q);
        });
    }, [freeVehicles, filterProjectId, searchQuery]);

    // Filtered history
    const filteredHistoryAssignments = useMemo(() => {
        return historyAssignments.filter((a) => {
            if (filterProjectId !== "all" && String(a.project_id) !== String(filterProjectId)) {
                return false;
            }
            if (!searchQuery.trim()) return true;
            const q = searchQuery.toLowerCase();
            const reg = String(a.vehicle?.registration_number || "").toLowerCase();
            const code = String(a.vehicle?.vehicle_code || a.vehicle_id || "").toLowerCase();
            const driver = String(a.driver?.name || a.driver?.email || "").toLowerCase();
            const proj = String(a.project?.name || a.project?.project_code || "").toLowerCase();
            return reg.includes(q) || code.includes(q) || driver.includes(q) || proj.includes(q);
        });
    }, [historyAssignments, filterProjectId, searchQuery]);

    // Available vehicles for assignment form (prioritize free ones)
    const availableVehiclesForAssignment = useMemo(() => {
        const selectedPid = String(assignmentForm.data.project_id || "");
        if (!selectedPid) return [];

        const projectVehicles = vehicles.filter(
            (v) => String(v.project_id) === selectedPid
        );

        const existingKeys = new Set();
        projectVehicles.forEach((pv) => {
            const reg = normalize(pv.registration_number || pv.vehicle_number);
            const code = normalize(pv.vehicle_code || pv.vehicle_id);
            if (reg) existingKeys.add(reg);
            if (code) existingKeys.add(code);
        });

        const unassignedMainVehicles = vehicles.filter((v) => {
            if (v.project_id) return false;
            const reg = normalize(v.registration_number || v.vehicle_number);
            const code = normalize(v.vehicle_code || v.vehicle_id);
            if (reg && existingKeys.has(reg)) return false;
            if (code && existingKeys.has(code)) return false;
            return true;
        });

        return [...projectVehicles, ...unassignedMainVehicles];
    }, [vehicles, assignmentForm.data.project_id]);

    const renderProjectsSelect = (value, onChange, error) => (
        <SelectInput
            label="Project"
            value={value}
            onChange={onChange}
            options={projects.map((project) => ({
                value: String(project.id),
                label: `${project.project_code} • ${project.name}`,
            }))}
            error={error}
        />
    );

    return (
        <ConstructionShell
            title="Vehicle Tracking & Assignment"
            description="Track real-time vehicle assignments, monitor occupied vehicles with date ranges, and allocate fleet seamlessly."
            variant={variant}
        >
            {/* Top Stat Summary Cards */}
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Total Vehicles" value={stats.totalVehicles} icon={FaTruck} />

                <div className="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm transition hover:shadow-md dark:border-emerald-800/40 dark:bg-emerald-950/20">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            Free / Available
                        </p>
                        <p className="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                            {stats.freeVehicles}
                        </p>
                        <p className="mt-0.5 text-[11px] text-emerald-600/80 dark:text-emerald-400/80">
                            Ready for assignment
                        </p>
                    </div>
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/60 dark:text-emerald-300">
                        <FaCheckCircle className="h-6 w-6" />
                    </div>
                </div>

                <div className="flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm transition hover:shadow-md dark:border-amber-800/40 dark:bg-amber-950/20">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                            Occupied / Active
                        </p>
                        <p className="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-300">
                            {stats.occupiedVehicles}
                        </p>
                        <p className="mt-0.5 text-[11px] text-amber-600/80 dark:text-amber-400/80">
                            Currently deployed
                        </p>
                    </div>
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/60 dark:text-amber-300">
                        <FaClock className="h-6 w-6" />
                    </div>
                </div>

                <div className="flex items-center justify-between rounded-2xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm transition hover:shadow-md dark:border-rose-800/40 dark:bg-rose-950/20">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                            Overdue / Expired
                        </p>
                        <p className="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-300">
                            {stats.overdueCount}
                        </p>
                        <p className="mt-0.5 text-[11px] text-rose-600/80 dark:text-rose-400/80">
                            Past scheduled end date
                        </p>
                    </div>
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/60 dark:text-rose-300">
                        <FaExclamationTriangle className="h-6 w-6" />
                    </div>
                </div>

                <StatCard label="Projects" value={stats.projects} />
            </div>

            {projects.length === 0 ? (
                <EmptyState
                    title="No projects available."
                    description="Create and assign projects first to start vehicle assignment and tracking."
                />
            ) : null}

            {/* Navigation Tabs */}
            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setActiveTab("occupied")}
                            className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                activeTab === "occupied"
                                    ? "bg-amber-500 text-white shadow-md shadow-amber-500/20"
                                    : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            <FaClock className="h-4 w-4" />
                            Occupied Vehicles
                            <span
                                className={`ml-1 rounded-full px-2 py-0.5 text-xs font-bold ${
                                    activeTab === "occupied"
                                        ? "bg-white/25 text-white"
                                        : "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                }`}
                            >
                                {stats.occupiedVehicles}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => setActiveTab("free")}
                            className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                activeTab === "free"
                                    ? "bg-emerald-600 text-white shadow-md shadow-emerald-600/20"
                                    : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            <FaCheckCircle className="h-4 w-4" />
                            Free / Available
                            <span
                                className={`ml-1 rounded-full px-2 py-0.5 text-xs font-bold ${
                                    activeTab === "free"
                                        ? "bg-white/25 text-white"
                                        : "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                }`}
                            >
                                {stats.freeVehicles}
                            </span>
                        </button>

                        {canManageAssignments ? (
                            <button
                                type="button"
                                onClick={() => setActiveTab("assign")}
                                className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                    activeTab === "assign"
                                        ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
                                        : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                }`}
                            >
                                <FaPlus className="h-3.5 w-3.5" />
                                Assign Vehicle
                            </button>
                        ) : null}

                        <button
                            type="button"
                            onClick={() => setActiveTab("history")}
                            className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                activeTab === "history"
                                    ? "bg-slate-800 text-white dark:bg-slate-700"
                                    : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            <FaUndo className="h-3.5 w-3.5" />
                            Assignment History
                            <span className="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                {historyAssignments.length}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => setActiveTab("pings")}
                            className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                activeTab === "pings"
                                    ? "bg-indigo-700 text-white shadow-md shadow-indigo-700/20"
                                    : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            <FaRoute className="h-3.5 w-3.5" />
                            Tracking Logs ({pings.length})
                        </button>
                    </div>

                    {/* Quick Filters */}
                    {["occupied", "free", "history"].includes(activeTab) && (
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative">
                                <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
                                <input
                                    type="text"
                                    placeholder="Search vehicle, driver..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="h-9 w-44 sm:w-52 rounded-xl border border-slate-200 bg-white pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <select
                                value={filterProjectId}
                                onChange={(e) => setFilterProjectId(e.target.value)}
                                className="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                            >
                                <option value="all">All Projects</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={String(p.id)}>
                                        {p.project_code} • {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {/* TAB 1: OCCUPIED VEHICLES */}
                {activeTab === "occupied" && (
                    <SectionCard
                        title="Occupied / Active Vehicle Assignments"
                        description="Currently deployed vehicles, assigned drivers, and date ranges. Click Return to release a vehicle back to the available fleet."
                    >
                        {filteredOccupiedAssignments.length === 0 ? (
                            <EmptyState
                                title={
                                    searchQuery || filterProjectId !== "all"
                                        ? "No matching occupied vehicles."
                                        : "No vehicles are currently occupied."
                                }
                                description={
                                    searchQuery || filterProjectId !== "all"
                                        ? "Try adjusting your search query or project filter."
                                        : "All registered vehicles are currently free and available for assignment."
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left font-semibold text-slate-600 dark:border-slate-800 dark:text-slate-300">
                                            <th className="py-3 pr-4">Project</th>
                                            <th className="py-3 pr-4">Vehicle</th>
                                            <th className="py-3 pr-4">Driver (Member)</th>
                                            <th className="py-3 pr-4">Assigned From</th>
                                            <th className="py-3 pr-4">Assigned To</th>
                                            <th className="py-3 pr-4">Note / Remarks</th>
                                            <th className="py-3 pr-4">Status</th>
                                            {canManageAssignments && <th className="py-3 pr-4 text-right">Action</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {filteredOccupiedAssignments.map((assignment) => {
                                            const isOverdue =
                                                assignment.assigned_to &&
                                                new Date(assignment.assigned_to) < new Date();
                                            return (
                                                <tr
                                                    key={assignment.id}
                                                    className={`transition hover:bg-slate-50/70 dark:hover:bg-slate-900/50 ${
                                                        isOverdue
                                                            ? "bg-rose-50/30 dark:bg-rose-950/10"
                                                            : ""
                                                    }`}
                                                >
                                                    <td className="py-3 pr-4">
                                                        <span className="font-semibold text-slate-900 dark:text-white">
                                                            {assignment.project?.project_code || `#${assignment.project_id}`}
                                                        </span>
                                                        <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                            {assignment.project?.name}
                                                        </p>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <span className="font-semibold text-slate-900 dark:text-white">
                                                            {assignment.vehicle?.vehicle_code || `#${assignment.vehicle_id}`}
                                                        </span>
                                                        <p className="text-[11px] font-medium text-indigo-600 dark:text-indigo-400">
                                                            {assignment.vehicle?.registration_number}
                                                        </p>
                                                        {assignment.vehicle?.model && (
                                                            <p className="text-[10px] text-slate-400">
                                                                {assignment.vehicle.make} {assignment.vehicle.model}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <div className="flex items-center gap-1.5">
                                                            <FaUser className="text-slate-400 text-[10px]" />
                                                            <span className="font-medium text-slate-900 dark:text-white">
                                                                {assignment.driver?.name || "Unassigned"}
                                                            </span>
                                                        </div>
                                                        <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                            {assignment.driver?.email || ""}
                                                        </p>
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                        {assignment.assigned_from
                                                            ? new Date(assignment.assigned_from).toLocaleString()
                                                            : "-"}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <span
                                                            className={
                                                                isOverdue
                                                                    ? "font-semibold text-rose-600 dark:text-rose-400"
                                                                    : "text-slate-700 dark:text-slate-300"
                                                            }
                                                        >
                                                            {assignment.assigned_to
                                                                ? new Date(assignment.assigned_to).toLocaleString()
                                                                : "-"}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-600 dark:text-slate-300 max-w-xs truncate">
                                                        {assignment.notes || assignment.note || "-"}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        {isOverdue ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                                                <FaExclamationTriangle className="h-2.5 w-2.5" />
                                                                Overdue
                                                            </span>
                                                        ) : (
                                                            <StatusBadge
                                                                value="active"
                                                                label="Active"
                                                            />
                                                        )}
                                                    </td>
                                                    {canManageAssignments && (
                                                        <td className="py-3 pr-4 text-right">
                                                            <button
                                                                type="button"
                                                                onClick={() => handleOpenReturnModal(assignment)}
                                                                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                            >
                                                                <FaUndo className="h-2.5 w-2.5 text-amber-500" />
                                                                Return / Release
                                                            </button>
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                )}

                {/* TAB 2: FREE / AVAILABLE VEHICLES */}
                {activeTab === "free" && (
                    <SectionCard
                        title="Free & Available Fleet"
                        description="Vehicles ready for assignment without active or overlapping bookings."
                    >
                        {filteredFreeVehicles.length === 0 ? (
                            <EmptyState
                                title={
                                    searchQuery || filterProjectId !== "all"
                                        ? "No matching free vehicles."
                                        : "No vehicles are currently available."
                                }
                                description={
                                    searchQuery || filterProjectId !== "all"
                                        ? "Try adjusting your search filters."
                                        : "All registered vehicles are currently occupied on projects."
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left font-semibold text-slate-600 dark:border-slate-800 dark:text-slate-300">
                                            <th className="py-3 pr-4">Vehicle Code</th>
                                            <th className="py-3 pr-4">Registration Number</th>
                                            <th className="py-3 pr-4">Vehicle Details</th>
                                            <th className="py-3 pr-4">Type</th>
                                            <th className="py-3 pr-4">Status</th>
                                            {canManageAssignments && <th className="py-3 pr-4 text-right">Action</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {filteredFreeVehicles.map((vehicle) => (
                                            <tr
                                                key={`${vehicle.source || "v"}-${vehicle.id}`}
                                                className="transition hover:bg-slate-50/70 dark:hover:bg-slate-900/50"
                                            >
                                                <td className="py-3 pr-4 font-semibold text-slate-900 dark:text-white">
                                                    {vehicle.vehicle_code || vehicle.vehicle_id || `#${vehicle.id}`}
                                                </td>
                                                <td className="py-3 pr-4 font-medium text-indigo-600 dark:text-indigo-400">
                                                    {vehicle.registration_number || vehicle.vehicle_number}
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                    {vehicle.vehicle_name || vehicle.model || "-"}
                                                </td>
                                                <td className="py-3 pr-4 text-slate-600 dark:text-slate-400">
                                                    {vehicle.vehicle_type || "Standard"}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                        <FaCheckCircle className="h-2.5 w-2.5" />
                                                        Available
                                                    </span>
                                                </td>
                                                {canManageAssignments && (
                                                    <td className="py-3 pr-4 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleQuickAssign(vehicle)}
                                                            className="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                                        >
                                                            <FaPlus className="h-2.5 w-2.5" />
                                                            Assign
                                                        </button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                )}

                {/* TAB 3: ASSIGN VEHICLE */}
                {activeTab === "assign" && canManageAssignments && (
                    <SectionCard
                        title="Driver Assignment"
                        description="Assign an available vehicle to a project driver for a specific date range."
                    >
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                assignmentForm.post(route(`${routeBase}.assignments.store`), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        assignmentForm.reset(
                                            "vehicle_id",
                                            "driver_member_id",
                                            "assigned_from",
                                            "assigned_to",
                                            "note"
                                        );
                                        setActiveTab("occupied");
                                    },
                                });
                            }}
                            className="space-y-4"
                        >
                            <div className="grid gap-4 md:grid-cols-3">
                                {renderProjectsSelect(
                                    assignmentForm.data.project_id,
                                    (value) => {
                                        assignmentForm.setData((data) => ({
                                            ...data,
                                            project_id: value,
                                            vehicle_id: "",
                                        }));
                                    },
                                    assignmentForm.errors.project_id
                                )}
                                <SelectInput
                                    label="Vehicle"
                                    value={assignmentForm.data.vehicle_id}
                                    onChange={(value) => assignmentForm.setData("vehicle_id", value)}
                                    options={[
                                        { value: "", label: "Select vehicle" },
                                        ...availableVehiclesForAssignment.map((vehicle) => {
                                            const isOccupied =
                                                occupiedKeys.has(normalize(vehicle.registration_number || vehicle.vehicle_number)) ||
                                                occupiedKeys.has(normalize(vehicle.vehicle_code || vehicle.vehicle_id)) ||
                                                occupiedKeys.has(String(vehicle.id));
                                            return {
                                                value: vehicle.source ? `${vehicle.source}_${vehicle.id}` : String(vehicle.id),
                                                label: `${vehicle.vehicle_code || vehicle.vehicle_id} • ${vehicle.registration_number || vehicle.vehicle_number}${
                                                    vehicle.vehicle_name ? ` (${vehicle.vehicle_name})` : ""
                                                } ${isOccupied ? "[Currently Occupied]" : "[Free / Available]"}`,
                                            };
                                        }),
                                    ]}
                                    error={assignmentForm.errors.vehicle_id}
                                />
                                <SelectInput
                                    label="Driver (Member with Driver Role)"
                                    value={assignmentForm.data.driver_member_id}
                                    onChange={(value) => assignmentForm.setData("driver_member_id", value)}
                                    options={[
                                        { value: "", label: "Select driver member", badges: [] },
                                        ...driverMembers.map((member) =>
                                            formatMemberSelectOption(member, { extra: member.email || "" })
                                        ),
                                    ]}
                                    error={assignmentForm.errors.driver_member_id}
                                />
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextInput
                                    label="Assigned From (Date & Time) *"
                                    type="datetime-local"
                                    value={assignmentForm.data.assigned_from}
                                    onChange={(value) => assignmentForm.setData("assigned_from", value)}
                                    error={assignmentForm.errors.assigned_from}
                                    required={true}
                                />
                                <TextInput
                                    label="Assigned To (Date & Time) *"
                                    type="datetime-local"
                                    value={assignmentForm.data.assigned_to}
                                    onChange={(value) => assignmentForm.setData("assigned_to", value)}
                                    error={assignmentForm.errors.assigned_to}
                                    required={true}
                                />
                            </div>
                            <TextAreaInput
                                label="Note / Remarks"
                                placeholder="Enter assignment details or notes..."
                                value={assignmentForm.data.note}
                                onChange={(value) => assignmentForm.setData("note", value)}
                                error={assignmentForm.errors.note}
                            />
                            <div className="flex items-center gap-3">
                                <PrimaryButton processing={assignmentForm.processing} label="Save Assignment" />
                                <button
                                    type="button"
                                    onClick={() => setActiveTab("occupied")}
                                    className="rounded-xl border border-slate-200 bg-white px-5 py-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </SectionCard>
                )}

                {/* TAB 4: ASSIGNMENT HISTORY */}
                {activeTab === "history" && (
                    <SectionCard
                        title="Vehicle Assignment History"
                        description="Historical record of completed, returned, and inactive vehicle assignments."
                    >
                        {filteredHistoryAssignments.length === 0 ? (
                            <EmptyState
                                title="No historical assignments."
                                description="Past returned assignments will be recorded and displayed here."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left font-semibold text-slate-600 dark:border-slate-800 dark:text-slate-300">
                                            <th className="py-3 pr-4">Project</th>
                                            <th className="py-3 pr-4">Vehicle</th>
                                            <th className="py-3 pr-4">Driver (Member)</th>
                                            <th className="py-3 pr-4">Assigned From</th>
                                            <th className="py-3 pr-4">Assigned To</th>
                                            <th className="py-3 pr-4">Note / Remarks</th>
                                            <th className="py-3 pr-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {filteredHistoryAssignments.map((assignment) => (
                                            <tr
                                                key={assignment.id}
                                                className="transition hover:bg-slate-50/70 dark:hover:bg-slate-900/50"
                                            >
                                                <td className="py-3 pr-4">
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {assignment.project?.project_code || `#${assignment.project_id}`}
                                                    </span>
                                                    <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                        {assignment.project?.name}
                                                    </p>
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {assignment.vehicle?.vehicle_code || `#${assignment.vehicle_id}`}
                                                    </span>
                                                    <p className="text-[11px] font-medium text-slate-600 dark:text-slate-300">
                                                        {assignment.vehicle?.registration_number}
                                                    </p>
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <span className="font-medium text-slate-900 dark:text-white">
                                                        {assignment.driver?.name || "Unassigned"}
                                                    </span>
                                                    <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                        {assignment.driver?.email || ""}
                                                    </p>
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                    {assignment.assigned_from
                                                        ? new Date(assignment.assigned_from).toLocaleString()
                                                        : "-"}
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                    {assignment.assigned_to
                                                        ? new Date(assignment.assigned_to).toLocaleString()
                                                        : "-"}
                                                </td>
                                                <td className="py-3 pr-4 text-slate-600 dark:text-slate-300 max-w-xs truncate">
                                                    {assignment.notes || assignment.note || "-"}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                        Inactive / Completed
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                )}

                {/* TAB 5: LOCATION PINGS */}
                {activeTab === "pings" && (
                    <SectionCard
                        title="Vehicle Location Pings & Tracking Logs"
                        description="Real-time GPS telemetry and tracking pings recorded for fleet vehicles."
                    >
                        {pings.length === 0 ? (
                            <EmptyState
                                title="No location pings recorded."
                                description="Real-time vehicle pings from mobile or GPS devices will appear here."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left font-semibold text-slate-600 dark:border-slate-800 dark:text-slate-300">
                                            <th className="py-3 pr-4">Vehicle</th>
                                            <th className="py-3 pr-4">Project</th>
                                            <th className="py-3 pr-4">Reported By</th>
                                            <th className="py-3 pr-4">Recorded At</th>
                                            <th className="py-3 pr-4">Coordinates (Lat, Lng)</th>
                                            <th className="py-3 pr-4">Speed</th>
                                            <th className="py-3 pr-4">GPS Accuracy</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {pings.map((ping) => (
                                            <tr
                                                key={ping.id}
                                                className="transition hover:bg-slate-50/70 dark:hover:bg-slate-900/50"
                                            >
                                                <td className="py-3 pr-4">
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {ping.vehicle?.vehicle_code || `#${ping.vehicle_id}`}
                                                    </span>
                                                    <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                        {ping.vehicle?.registration_number}
                                                    </p>
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                    {ping.project?.project_code || `#${ping.project_id}`}
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                    {ping.reported_by?.name || "Automated Ping"}
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                    {ping.recorded_at
                                                        ? new Date(ping.recorded_at).toLocaleString()
                                                        : "-"}
                                                </td>
                                                <td className="py-3 pr-4 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                                    {ping.latitude}, {ping.longitude}
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                                    {ping.speed_kmph != null ? `${ping.speed_kmph} km/h` : "-"}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {ping.gps_verified ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                            Verified ({ping.gps_accuracy_meters}m)
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 text-[11px]">
                                                            {ping.gps_accuracy_meters != null
                                                                ? `${ping.gps_accuracy_meters}m`
                                                                : "-"}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                )}
            </div>

            {/* Return Vehicle Modal */}
            {returningAssignment && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/60 dark:text-amber-300">
                                    <FaTruck className="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                        Return / Release Vehicle
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {returningAssignment.vehicle?.vehicle_code} •{" "}
                                        {returningAssignment.vehicle?.registration_number}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setReturningAssignment(null)}
                                className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                <FaTimes className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleConfirmReturn} className="mt-4 space-y-4">
                            <div className="rounded-xl bg-slate-50 p-4 text-xs dark:bg-slate-800/40 space-y-2">
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Project:</span>
                                    <span className="font-semibold text-slate-800 dark:text-slate-200">
                                        {returningAssignment.project?.project_code} •{" "}
                                        {returningAssignment.project?.name}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Driver:</span>
                                    <span className="font-semibold text-slate-800 dark:text-slate-200">
                                        {returningAssignment.driver?.name || "Unassigned"}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Scheduled End:</span>
                                    <span className="font-semibold text-slate-800 dark:text-slate-200">
                                        {returningAssignment.assigned_to
                                            ? new Date(returningAssignment.assigned_to).toLocaleString()
                                            : "-"}
                                    </span>
                                </div>
                            </div>

                            <p className="text-xs text-slate-600 dark:text-slate-400">
                                Releasing this vehicle will end the driver assignment immediately and return the vehicle to the <strong>Free / Available</strong> fleet for new bookings.
                            </p>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setReturningAssignment(null)}
                                    className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={returnForm.processing}
                                    className="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-semibold text-white shadow-md transition hover:bg-amber-500 disabled:opacity-50"
                                >
                                    <FaUndo className="h-3 w-3" />
                                    {returnForm.processing ? "Releasing..." : "Confirm Return & Free Vehicle"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ConstructionShell>
    );
}

function TextInput({ label, error, value, onChange, type = "text", required = false }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">{label}</span>
            <input
                type={type}
                value={value ?? ""}
                required={required}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function TextAreaInput({ label, error, value, onChange, placeholder = "", rows = 3 }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">{label}</span>
            <textarea
                rows={rows}
                value={value ?? ""}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function SelectInput({ label, error, value, onChange, options }) {
    const selectedOption = options.find((opt) => String(opt.value) === String(value));
    const selectedBadges = selectedOption?.badges || [];

    return (
        <label className="block">
            <div className="mb-1.5 flex flex-wrap items-center justify-between gap-1">
                <span className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{label}</span>
                {selectedBadges.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1">
                        {selectedBadges.map((badge, idx) => (
                            <span
                                key={idx}
                                className="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-950/60 dark:text-indigo-300 dark:ring-indigo-400/30"
                            >
                                {badge}
                            </span>
                        ))}
                    </div>
                )}
            </div>
            <select
                value={value ?? ""}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
                {options.map((option) => (
                    <option key={`${option.value}-${option.label}`} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function PrimaryButton({ processing, label }) {
    return (
        <button
            type="submit"
            disabled={processing}
            className="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-indigo-400"
        >
            {processing ? "Saving..." : label}
        </button>
    );
}
