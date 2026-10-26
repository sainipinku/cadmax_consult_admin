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
    FaArrowRight,
    FaUndo,
    FaPlus,
    FaTools,
    FaUser,
    FaTimes,
} from "react-icons/fa";

export default function EquipmentWorkspace({
    variant = "super",
    projects = [],
    equipments = [],
    allocations = [],
    usageLogs = [],
    members = [],
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
            ? "super.construction.equipment"
            : variant === "admin"
              ? "admin.construction.equipment"
              : "member.construction.equipment";

    const canAllocate =
        isSuperAdmin || hasPerm("equipment_allocation.manage") || hasPerm("equipment.manage");

    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    // Tab state
    const [activeTab, setActiveTab] = useState("occupied"); // 'occupied' | 'free' | 'assign' | 'history' | 'usage'
    const [searchQuery, setSearchQuery] = useState("");
    const [filterProjectId, setFilterProjectId] = useState("all");

    // Modal state for Return Equipment
    const [returningAllocation, setReturningAllocation] = useState(null);

    // Active allocations
    const activeAllocations = useMemo(
        () => allocations.filter((a) => a.status === "active"),
        [allocations]
    );

    // Returned allocations (history)
    const returnedAllocations = useMemo(
        () => allocations.filter((a) => a.status === "returned"),
        [allocations]
    );

    // Track which equipments are occupied
    const occupiedKeys = useMemo(() => {
        const keys = new Set();
        activeAllocations.forEach((alloc) => {
            if (alloc.equipment?.equipment_code) {
                keys.add(String(alloc.equipment.equipment_code).trim().toLowerCase());
            }
            if (alloc.equipment_id) {
                keys.add(String(alloc.equipment_id));
            }
        });
        return keys;
    }, [activeAllocations]);

    // Free / Available equipments
    const freeEquipments = useMemo(() => {
        return equipments.filter((eq) => {
            const eqStatus = String(eq.status || "").toLowerCase();
            if (["maintenance", "damaged", "lost", "disposed", "inactive"].includes(eqStatus)) {
                return false;
            }
            const code = String(eq.equipment_code || eq.equipment_id || "").trim().toLowerCase();
            const idStr = String(eq.id);
            if (code && occupiedKeys.has(code)) return false;
            if (idStr && occupiedKeys.has(idStr)) return false;
            if (eq.status === "allocated" || eq.status === 1) return false;
            return true;
        });
    }, [equipments, occupiedKeys]);

    // Overdue allocations
    const overdueAllocations = useMemo(() => {
        const now = new Date();
        return activeAllocations.filter((alloc) => {
            if (!alloc.assigned_to) return false;
            return new Date(alloc.assigned_to) < now;
        });
    }, [activeAllocations]);

    // Stats
    const stats = useMemo(() => {
        return {
            totalEquipments: equipments.length,
            freeEquipments: freeEquipments.length,
            occupiedEquipments: activeAllocations.length,
            overdueCount: overdueAllocations.length,
            projects: projects.length,
        };
    }, [equipments, freeEquipments, activeAllocations, overdueAllocations, projects]);

    // Assignment form (simplified: Project, Equipment, Member, Date From, Date To, Hours [optional], Notes)
    const nowLocal = new Date().toISOString().slice(0, 16);
    const allocationForm = useForm({
        project_id: firstProjectId,
        equipment_id: "",
        assigned_to_member_id: "",
        assigned_from: nowLocal,
        assigned_to: "",
        hours: "",
        notes: "",
    });

    // Return form
    const returnForm = useForm({
        project_id: "",
        allocation_id: "",
        returned_at: nowLocal,
        notes: "",
    });

    // Usage log form
    const usageForm = useForm({
        project_id: firstProjectId,
        equipment_id: "",
        member_id: "",
        log_date: new Date().toISOString().slice(0, 10),
        hours_used: "",
        notes: "",
    });

    // Equipments available for the currently selected project in assignment form
    const availableEquipmentsForProject = useMemo(() => {
        const selectedPid = String(allocationForm.data.project_id || "");
        return freeEquipments.filter((eq) => {
            if (!eq.project_id) return true; // central fleet available to all
            return String(eq.project_id) === selectedPid;
        });
    }, [freeEquipments, allocationForm.data.project_id]);

    // Helper: trigger assignment of a free equipment
    const handleQuickAssign = (equipment) => {
        const eqValue = equipment.source ? `${equipment.source}_${equipment.id}` : String(equipment.id);
        const targetPid = equipment.project_id ? String(equipment.project_id) : firstProjectId;
        allocationForm.setData((data) => ({
            ...data,
            project_id: targetPid,
            equipment_id: eqValue,
            assigned_from: new Date().toISOString().slice(0, 16),
        }));
        setActiveTab("assign");
    };

    // Helper: open return modal
    const handleOpenReturnModal = (allocation) => {
        setReturningAllocation(allocation);
        returnForm.setData({
            project_id: String(allocation.project_id),
            allocation_id: String(allocation.id),
            returned_at: new Date().toISOString().slice(0, 16),
            notes: "",
        });
    };

    const handleConfirmReturn = (event) => {
        event.preventDefault();
        returnForm.post(route(`${routeBase}.allocations.return`), {
            preserveScroll: true,
            onSuccess: () => {
                setReturningAllocation(null);
                returnForm.reset();
            },
        });
    };

    // Filtered occupied allocations
    const filteredOccupiedAllocations = useMemo(() => {
        return activeAllocations.filter((alloc) => {
            if (filterProjectId !== "all" && String(alloc.project_id) !== String(filterProjectId)) {
                return false;
            }
            if (!searchQuery.trim()) return true;
            const q = searchQuery.toLowerCase();
            const code = String(alloc.equipment?.equipment_code || alloc.equipment_id || "").toLowerCase();
            const name = String(alloc.equipment?.name || "").toLowerCase();
            const memberName = String(alloc.assigned_to?.name || "").toLowerCase();
            const projName = String(alloc.project?.name || alloc.project?.project_code || "").toLowerCase();
            return code.includes(q) || name.includes(q) || memberName.includes(q) || projName.includes(q);
        });
    }, [activeAllocations, filterProjectId, searchQuery]);

    // Filtered free equipments
    const filteredFreeEquipments = useMemo(() => {
        return freeEquipments.filter((eq) => {
            if (filterProjectId !== "all" && eq.project_id && String(eq.project_id) !== String(filterProjectId)) {
                return false;
            }
            if (!searchQuery.trim()) return true;
            const q = searchQuery.toLowerCase();
            const code = String(eq.equipment_code || eq.equipment_id || "").toLowerCase();
            const name = String(eq.name || eq.equipment_name || "").toLowerCase();
            const type = String(eq.equipment_type || "").toLowerCase();
            const brand = String(eq.brand || "").toLowerCase();
            const model = String(eq.model || "").toLowerCase();
            return code.includes(q) || name.includes(q) || type.includes(q) || brand.includes(q) || model.includes(q);
        });
    }, [freeEquipments, filterProjectId, searchQuery]);

    return (
        <ConstructionShell
            title="Equipment Tracking & Allocation"
            description="Track real-time equipment availability, monitor occupied tools with schedules & hours, and allocate equipment seamlessly."
            variant={variant}
        >
            {/* Top Stat Summary Cards */}
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Total Equipments" value={stats.totalEquipments} icon={FaTools} />
                <div className="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm transition hover:shadow-md dark:border-emerald-800/40 dark:bg-emerald-950/20">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            Free / Available
                        </p>
                        <p className="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                            {stats.freeEquipments}
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
                            {stats.occupiedEquipments}
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
                            Overdue Return
                        </p>
                        <p className="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-300">
                            {stats.overdueCount}
                        </p>
                        <p className="mt-0.5 text-[11px] text-rose-600/80 dark:text-rose-400/80">
                            Past scheduled date
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
                    description="Create and assign projects first to start equipment allocation."
                />
            ) : null}

            {/* Main Interactive Tracking Navigation Tabs */}
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
                            Occupied Equipments
                            <span
                                className={`ml-1 rounded-full px-2 py-0.5 text-xs font-bold ${
                                    activeTab === "occupied"
                                        ? "bg-white/25 text-white"
                                        : "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                }`}
                            >
                                {stats.occupiedEquipments}
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
                                {stats.freeEquipments}
                            </span>
                        </button>

                        {canAllocate ? (
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
                                Assign Equipment
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
                            Allocation History
                            <span className="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                {returnedAllocations.length}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => setActiveTab("usage")}
                            className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                activeTab === "usage"
                                    ? "bg-indigo-700 text-white"
                                    : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            Usage Logs ({usageLogs.length})
                        </button>
                    </div>

                    {/* Quick Filters */}
                    {["occupied", "free", "history"].includes(activeTab) && (
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative">
                                <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
                                <input
                                    type="text"
                                    placeholder="Search code, name..."
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

                {/* TAB 1: OCCUPIED EQUIPMENTS */}
                {activeTab === "occupied" && (
                    <SectionCard
                        title="Occupied / Allocated Equipments"
                        description="Currently deployed equipment, member assignments, scheduled return dates, and allocated hours."
                    >
                        {filteredOccupiedAllocations.length === 0 ? (
                            <EmptyState
                                title={
                                    searchQuery || filterProjectId !== "all"
                                        ? "No matching occupied equipments."
                                        : "No equipment is currently occupied."
                                }
                                description={
                                    searchQuery || filterProjectId !== "all"
                                        ? "Try adjusting your search query or project filter."
                                        : "All available tools are currently free in the yard. Assign an equipment to get started."
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                            <th className="py-3 pr-4">Equipment</th>
                                            <th className="py-3 pr-4">Project</th>
                                            <th className="py-3 pr-4">Assigned To</th>
                                            <th className="py-3 pr-4">Date From</th>
                                            <th className="py-3 pr-4">Date To</th>
                                            <th className="py-3 pr-4">Hours</th>
                                            <th className="py-3 pr-4">Status</th>
                                            <th className="py-3 pr-4">Notes</th>
                                            <th className="py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {filteredOccupiedAllocations.map((allocation) => {
                                            const isOverdue =
                                                allocation.assigned_to &&
                                                new Date(allocation.assigned_to) < new Date();

                                            return (
                                                <tr
                                                    key={allocation.id}
                                                    className={`transition hover:bg-slate-50/70 dark:hover:bg-slate-900/40 ${
                                                        isOverdue
                                                            ? "bg-rose-50/40 dark:bg-rose-950/10"
                                                            : ""
                                                    }`}
                                                >
                                                    <td className="py-3.5 pr-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-mono text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                                <FaTools className="h-4 w-4 text-amber-500" />
                                                            </div>
                                                            <div>
                                                                <p className="font-semibold text-slate-900 dark:text-white">
                                                                    {allocation.equipment?.name || "Equipment"}
                                                                </p>
                                                                <p className="font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                                                    {allocation.equipment?.equipment_code ||
                                                                        `#${allocation.equipment_id}`}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="py-3.5 pr-4">
                                                        <p className="font-semibold text-slate-900 dark:text-white">
                                                            {allocation.project?.project_code ||
                                                                `#${allocation.project_id}`}
                                                        </p>
                                                        <p className="text-xs text-slate-500 dark:text-slate-400">
                                                            {allocation.project?.name}
                                                        </p>
                                                    </td>

                                                    <td className="py-3.5 pr-4">
                                                        {allocation.assigned_to ? (
                                                            <div>
                                                                <p className="font-medium text-slate-900 dark:text-white flex items-center gap-1.5">
                                                                    <FaUser className="h-3 w-3 text-slate-400" />
                                                                    {allocation.assigned_to.name}
                                                                </p>
                                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                                    {allocation.assigned_to.email}
                                                                </p>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs italic text-slate-400">
                                                                Project Yard / Site Team
                                                            </span>
                                                        )}
                                                    </td>

                                                    <td className="py-3.5 pr-4 text-xs font-medium text-slate-700 dark:text-slate-300">
                                                        {allocation.assigned_from || allocation.allocated_at
                                                            ? new Date(
                                                                  allocation.assigned_from ||
                                                                      allocation.allocated_at
                                                              ).toLocaleString()
                                                            : "—"}
                                                    </td>

                                                    <td className="py-3.5 pr-4 text-xs font-medium">
                                                        {allocation.assigned_to ? (
                                                            <span
                                                                className={
                                                                    isOverdue
                                                                        ? "font-bold text-rose-600 dark:text-rose-400 flex items-center gap-1"
                                                                        : "text-slate-700 dark:text-slate-300"
                                                                }
                                                            >
                                                                {isOverdue && (
                                                                    <FaExclamationTriangle className="h-3 w-3 shrink-0" />
                                                                )}
                                                                {new Date(
                                                                    allocation.assigned_to
                                                                ).toLocaleString()}
                                                            </span>
                                                        ) : (
                                                            <span className="text-slate-400">Open-ended</span>
                                                        )}
                                                    </td>

                                                    <td className="py-3.5 pr-4 text-xs font-semibold text-slate-800 dark:text-slate-200">
                                                        {allocation.hours !== null &&
                                                        allocation.hours !== undefined
                                                            ? `${allocation.hours} hrs`
                                                            : "—"}
                                                    </td>

                                                    <td className="py-3.5 pr-4">
                                                        {isOverdue ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                                <FaExclamationTriangle className="h-2.5 w-2.5" />
                                                                Overdue
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                                <FaClock className="h-2.5 w-2.5" />
                                                                Occupied
                                                            </span>
                                                        )}
                                                    </td>

                                                    <td className="py-3.5 pr-4 text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                                        {allocation.notes || "—"}
                                                    </td>

                                                    <td className="py-3.5 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleOpenReturnModal(allocation)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                                                        >
                                                            <FaUndo className="h-3 w-3" />
                                                            Return / Free Up
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                )}

                {/* TAB 2: FREE / AVAILABLE EQUIPMENTS */}
                {activeTab === "free" && (
                    <SectionCard
                        title="Free & Available Equipments"
                        description="Equipment currently available in company and project fleets ready to be allocated."
                    >
                        {filteredFreeEquipments.length === 0 ? (
                            <EmptyState
                                title="No free equipments found."
                                description="All equipment is currently allocated or no equipment matches your search."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                            <th className="py-3 pr-4">Equipment Code</th>
                                            <th className="py-3 pr-4">Name</th>
                                            <th className="py-3 pr-4">Type / Category</th>
                                            <th className="py-3 pr-4">Brand & Model</th>
                                            <th className="py-3 pr-4">Serial Number</th>
                                            <th className="py-3 pr-4">Status</th>
                                            <th className="py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {filteredFreeEquipments.map((eq, idx) => (
                                            <tr
                                                key={eq.id ? `${eq.source || "eq"}_${eq.id}` : idx}
                                                className="transition hover:bg-slate-50/70 dark:hover:bg-slate-900/40"
                                            >
                                                <td className="py-3.5 pr-4 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                                    {eq.equipment_code || eq.equipment_id || `#${eq.id}`}
                                                </td>

                                                <td className="py-3.5 pr-4 font-semibold text-slate-900 dark:text-white">
                                                    {eq.name || eq.equipment_name || "Equipment"}
                                                </td>

                                                <td className="py-3.5 pr-4">
                                                    <span className="inline-flex rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        {eq.equipment_type || "Standard"}
                                                    </span>
                                                </td>

                                                <td className="py-3.5 pr-4 text-xs text-slate-600 dark:text-slate-300">
                                                    {[eq.brand, eq.model].filter(Boolean).join(" ") || "—"}
                                                </td>

                                                <td className="py-3.5 pr-4 font-mono text-xs text-slate-500 dark:text-slate-400">
                                                    {eq.serial_number || "—"}
                                                </td>

                                                <td className="py-3.5 pr-4">
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                        <FaCheckCircle className="h-2.5 w-2.5 text-emerald-600" />
                                                        Available / Free
                                                    </span>
                                                </td>

                                                <td className="py-3.5 text-right">
                                                    {canAllocate ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleQuickAssign(eq)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                                        >
                                                            <FaPlus className="h-3 w-3" />
                                                            Assign Equipment
                                                        </button>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                )}

                {/* TAB 3: ASSIGN EQUIPMENT FORM */}
                {activeTab === "assign" && canAllocate && (
                    <SectionCard
                        title="Simple Equipment Assignment"
                        description="Allocate free equipment to a project team member with schedule dates and optional hours."
                    >
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                allocationForm.post(route(`${routeBase}.allocations.store`), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        allocationForm.reset(
                                            "equipment_id",
                                            "assigned_to_member_id",
                                            "assigned_to",
                                            "hours",
                                            "notes"
                                        );
                                        setActiveTab("occupied");
                                    },
                                });
                            }}
                            className="space-y-6 max-w-4xl"
                        >
                            <div className="grid gap-5 md:grid-cols-3">
                                {/* Project Select */}
                                <SelectInput
                                    label="Project"
                                    value={allocationForm.data.project_id}
                                    onChange={(value) => {
                                        allocationForm.setData((data) => ({
                                            ...data,
                                            project_id: value,
                                            equipment_id: "",
                                        }));
                                    }}
                                    options={projects.map((project) => ({
                                        value: String(project.id),
                                        label: `${project.project_code} • ${project.name}`,
                                    }))}
                                    error={allocationForm.errors.project_id}
                                />

                                {/* Equipment Select */}
                                <SelectInput
                                    label="Equipment (Free / Available)"
                                    value={allocationForm.data.equipment_id}
                                    onChange={(value) => allocationForm.setData("equipment_id", value)}
                                    options={[
                                        { value: "", label: "Select available equipment" },
                                        ...availableEquipmentsForProject.map((eq) => ({
                                            value: eq.source ? `${eq.source}_${eq.id}` : String(eq.id),
                                            label: `${eq.equipment_code || eq.equipment_id || `#${eq.id}`} • ${
                                                eq.name || eq.equipment_name
                                            } (${eq.equipment_type || "Equipment"})`,
                                        })),
                                    ]}
                                    error={allocationForm.errors.equipment_id}
                                />

                                {/* Assigned To Member */}
                                <SelectInput
                                    label="Assigned To (Member / Team)"
                                    value={allocationForm.data.assigned_to_member_id}
                                    onChange={(value) =>
                                        allocationForm.setData("assigned_to_member_id", value)
                                    }
                                    options={[
                                        { value: "", label: "Select team member (optional)", badges: [] },
                                        ...members.map((member) =>
                                            formatMemberSelectOption(member, {
                                                extra: member.email || "",
                                            })
                                        ),
                                    ]}
                                    error={allocationForm.errors.assigned_to_member_id}
                                />
                            </div>

                            <div className="grid gap-5 md:grid-cols-3">
                                {/* Date From */}
                                <TextInput
                                    label="Date From (Assignment Start)"
                                    type="datetime-local"
                                    value={allocationForm.data.assigned_from}
                                    onChange={(value) => allocationForm.setData("assigned_from", value)}
                                    error={allocationForm.errors.assigned_from}
                                />

                                {/* Date To */}
                                <TextInput
                                    label="Date To (Scheduled Return)"
                                    type="datetime-local"
                                    value={allocationForm.data.assigned_to}
                                    onChange={(value) => allocationForm.setData("assigned_to", value)}
                                    error={allocationForm.errors.assigned_to}
                                />

                                {/* Hours (Optional) */}
                                <TextInput
                                    label="Hours (optional)"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    placeholder="e.g. 8.0"
                                    value={allocationForm.data.hours}
                                    onChange={(value) => allocationForm.setData("hours", value)}
                                    error={allocationForm.errors.hours}
                                />
                            </div>

                            {/* Notes / Remarks */}
                            <TextAreaInput
                                label="Notes / Work Instructions (optional)"
                                placeholder="Enter specific instructions, work area, or notes for this equipment allocation..."
                                value={allocationForm.data.notes}
                                onChange={(value) => allocationForm.setData("notes", value)}
                                error={allocationForm.errors.notes}
                            />

                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab("occupied")}
                                    className="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={allocationForm.processing}
                                    className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-500 disabled:opacity-50"
                                >
                                    <FaPlus className="h-3.5 w-3.5" />
                                    {allocationForm.processing ? "Assigning..." : "Assign Equipment"}
                                </button>
                            </div>
                        </form>
                    </SectionCard>
                )}

                {/* TAB 4: ALLOCATION HISTORY */}
                {activeTab === "history" && (
                    <SectionCard
                        title="Allocation History"
                        description="Past completed and returned equipment allocations."
                    >
                        {returnedAllocations.length === 0 ? (
                            <EmptyState
                                title="No past allocations recorded."
                                description="Returned equipment allocations will be listed here with completed usage details."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                            <th className="py-3 pr-4">Equipment</th>
                                            <th className="py-3 pr-4">Project</th>
                                            <th className="py-3 pr-4">Used By</th>
                                            <th className="py-3 pr-4">Assigned From</th>
                                            <th className="py-3 pr-4">Returned At</th>
                                            <th className="py-3 pr-4">Hours</th>
                                            <th className="py-3 pr-4">Notes</th>
                                            <th className="py-3 text-right">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {returnedAllocations.map((alloc) => (
                                            <tr
                                                key={alloc.id}
                                                className="transition hover:bg-slate-50/70 dark:hover:bg-slate-900/40"
                                            >
                                                <td className="py-3.5 pr-4">
                                                    <p className="font-semibold text-slate-900 dark:text-white">
                                                        {alloc.equipment?.name || "Equipment"}
                                                    </p>
                                                    <p className="font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                                        {alloc.equipment?.equipment_code ||
                                                            `#${alloc.equipment_id}`}
                                                    </p>
                                                </td>

                                                <td className="py-3.5 pr-4">
                                                    <p className="font-medium text-slate-900 dark:text-white">
                                                        {alloc.project?.project_code || `#${alloc.project_id}`}
                                                    </p>
                                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                                        {alloc.project?.name}
                                                    </p>
                                                </td>

                                                <td className="py-3.5 pr-4 text-xs text-slate-700 dark:text-slate-300">
                                                    {alloc.assigned_to?.name || "Yard / Team"}
                                                </td>

                                                <td className="py-3.5 pr-4 text-xs text-slate-600 dark:text-slate-300">
                                                    {alloc.assigned_from || alloc.allocated_at
                                                        ? new Date(
                                                              alloc.assigned_from || alloc.allocated_at
                                                          ).toLocaleString()
                                                        : "—"}
                                                </td>

                                                <td className="py-3.5 pr-4 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                    {alloc.returned_at
                                                        ? new Date(alloc.returned_at).toLocaleString()
                                                        : "Returned"}
                                                </td>

                                                <td className="py-3.5 pr-4 text-xs font-semibold text-slate-800 dark:text-slate-200">
                                                    {alloc.hours !== null && alloc.hours !== undefined
                                                        ? `${alloc.hours} hrs`
                                                        : "—"}
                                                </td>

                                                <td className="py-3.5 pr-4 text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                                    {alloc.notes || "—"}
                                                </td>

                                                <td className="py-3.5 text-right">
                                                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        Returned
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

                {/* TAB 5: USAGE LOGS */}
                {activeTab === "usage" && (
                    <div className="grid gap-6 xl:grid-cols-3">
                        <div className="xl:col-span-1">
                            <SectionCard
                                title="Log Equipment Usage"
                                description="Record daily operational hours used on-site."
                            >
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        usageForm.post(route(`${routeBase}.usage.store`), {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                usageForm.reset("equipment_id", "hours_used", "notes"),
                                        });
                                    }}
                                    className="space-y-4"
                                >
                                    <SelectInput
                                        label="Project"
                                        value={usageForm.data.project_id}
                                        onChange={(value) => usageForm.setData("project_id", value)}
                                        options={projects.map((project) => ({
                                            value: String(project.id),
                                            label: `${project.project_code} • ${project.name}`,
                                        }))}
                                        error={usageForm.errors.project_id}
                                    />

                                    <SelectInput
                                        label="Equipment"
                                        value={usageForm.data.equipment_id}
                                        onChange={(value) => usageForm.setData("equipment_id", value)}
                                        options={[
                                            { value: "", label: "Select equipment" },
                                            ...equipments.map((eq) => ({
                                                value: String(eq.id),
                                                label: `${eq.equipment_code || `#${eq.id}`} • ${
                                                    eq.name || eq.equipment_name
                                                }`,
                                            })),
                                        ]}
                                        error={usageForm.errors.equipment_id}
                                    />

                                    <SelectInput
                                        label="Operator / Member (optional)"
                                        value={usageForm.data.member_id}
                                        onChange={(value) => usageForm.setData("member_id", value)}
                                        options={[
                                            { value: "", label: "Select operator member" },
                                            ...members.map((member) =>
                                                formatMemberSelectOption(member, {
                                                    extra: member.email || "",
                                                })
                                            ),
                                        ]}
                                        error={usageForm.errors.member_id}
                                    />

                                    <TextInput
                                        label="Log Date"
                                        type="date"
                                        value={usageForm.data.log_date}
                                        onChange={(value) => usageForm.setData("log_date", value)}
                                        error={usageForm.errors.log_date}
                                    />

                                    <TextInput
                                        label="Hours Used"
                                        type="number"
                                        step="0.25"
                                        min="0.1"
                                        placeholder="e.g. 6.5"
                                        value={usageForm.data.hours_used}
                                        onChange={(value) => usageForm.setData("hours_used", value)}
                                        error={usageForm.errors.hours_used}
                                    />

                                    <TextAreaInput
                                        label="Work Notes"
                                        placeholder="Enter work performed or task description..."
                                        value={usageForm.data.notes}
                                        onChange={(value) => usageForm.setData("notes", value)}
                                        error={usageForm.errors.notes}
                                    />

                                    <PrimaryButton processing={usageForm.processing} label="Save Usage Log" />
                                </form>
                            </SectionCard>
                        </div>

                        <div className="xl:col-span-2">
                            <SectionCard
                                title="Equipment Daily Usage Logs"
                                description="Daily recorded working hours across equipment."
                            >
                                {usageLogs.length === 0 ? (
                                    <EmptyState
                                        title="No usage logs recorded."
                                        description="Log daily running hours of equipment using the form."
                                    />
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                                    <th className="py-3 pr-4">Date</th>
                                                    <th className="py-3 pr-4">Equipment</th>
                                                    <th className="py-3 pr-4">Project</th>
                                                    <th className="py-3 pr-4">Operator</th>
                                                    <th className="py-3 pr-4">Hours</th>
                                                    <th className="py-3 pr-4">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                {usageLogs.map((log) => (
                                                    <tr key={log.id}>
                                                        <td className="py-3 pr-4 font-mono text-xs font-semibold text-slate-900 dark:text-white">
                                                            {log.log_date}
                                                        </td>
                                                        <td className="py-3 pr-4">
                                                            <p className="font-semibold text-slate-900 dark:text-white">
                                                                {log.equipment?.name || "Equipment"}
                                                            </p>
                                                            <p className="font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                                                {log.equipment?.equipment_code || `#${log.equipment_id}`}
                                                            </p>
                                                        </td>
                                                        <td className="py-3 pr-4 text-xs font-medium text-slate-700 dark:text-slate-300">
                                                            {log.project?.project_code || `#${log.project_id}`}
                                                        </td>
                                                        <td className="py-3 pr-4 text-xs text-slate-600 dark:text-slate-300">
                                                            {log.member?.name || "—"}
                                                        </td>
                                                        <td className="py-3 pr-4 font-semibold text-indigo-600 dark:text-indigo-400">
                                                            {log.hours_used} hrs
                                                        </td>
                                                        <td className="py-3 pr-4 text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                                            {log.notes || "—"}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </SectionCard>
                        </div>
                    </div>
                )}
            </div>

            {/* Return / Free Up Equipment Modal */}
            {returningAllocation && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                            <div className="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
                                <FaUndo className="h-4 w-4" />
                                <h3 className="font-bold text-slate-900 dark:text-white">
                                    Return & Free Up Equipment
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setReturningAllocation(null)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                            >
                                <FaTimes className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="my-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60 text-xs space-y-1.5">
                            <p className="text-slate-500 dark:text-slate-400">
                                Equipment:{" "}
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    {returningAllocation.equipment?.name || "Equipment"} (
                                    {returningAllocation.equipment?.equipment_code || `#${returningAllocation.equipment_id}`})
                                </span>
                            </p>
                            <p className="text-slate-500 dark:text-slate-400">
                                Project:{" "}
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    {returningAllocation.project?.project_code} • {returningAllocation.project?.name}
                                </span>
                            </p>
                            {returningAllocation.assigned_to && (
                                <p className="text-slate-500 dark:text-slate-400">
                                    Currently with:{" "}
                                    <span className="font-semibold text-slate-900 dark:text-white">
                                        {returningAllocation.assigned_to.name}
                                    </span>
                                </p>
                            )}
                        </div>

                        <form onSubmit={handleConfirmReturn} className="space-y-4">
                            <TextInput
                                label="Return Date & Time"
                                type="datetime-local"
                                value={returnForm.data.returned_at}
                                onChange={(value) => returnForm.setData("returned_at", value)}
                                error={returnForm.errors.returned_at}
                            />

                            <TextAreaInput
                                label="Return Remarks / Equipment Condition"
                                placeholder="Condition upon return, clean status, or handover notes..."
                                value={returnForm.data.notes}
                                onChange={(value) => returnForm.setData("notes", value)}
                                error={returnForm.errors.notes}
                            />

                            <div className="flex items-center justify-end gap-3 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setReturningAllocation(null)}
                                    className="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={returnForm.processing}
                                    className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-semibold text-white shadow-md transition hover:bg-slate-800 disabled:opacity-50 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                                >
                                    <FaUndo className="h-3 w-3" />
                                    {returnForm.processing ? "Returning..." : "Confirm Return & Free Up"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ConstructionShell>
    );
}

function TextInput({ label, error, value, onChange, type = "text", placeholder = "", step, min }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">
                {label}
            </span>
            <input
                type={type}
                step={step}
                min={min}
                placeholder={placeholder}
                value={value ?? ""}
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
            <span className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">
                {label}
            </span>
            <textarea
                rows={rows}
                placeholder={placeholder}
                value={value ?? ""}
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
                <span className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                    {label}
                </span>
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
