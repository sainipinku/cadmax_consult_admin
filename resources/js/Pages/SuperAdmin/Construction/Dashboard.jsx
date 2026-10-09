import { Link, router, usePage } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import DeleteActionButton from "@/Components/DeleteActionButton";
import {
    FaProjectDiagram,
    FaPlayCircle,
    FaCheckCircle,
    FaHourglassHalf,
    FaUsers,
    FaUserCheck,
    FaUsersCog,
    FaTruck,
    FaCogs,
    FaCity,
    FaHandHoldingUsd,
    FaFileInvoiceDollar,
    FaCalendarCheck,
    FaSatelliteDish,
    FaMapMarkedAlt,
    FaPlus,
    FaEye,
    FaEdit,
    FaTrashAlt,
    FaLayerGroup,
    FaSearch,
    FaMoneyBillWave,
    FaChartLine,
    FaBuilding,
    FaIndustry,
    FaToolbox,
    FaClipboardCheck,
    FaRulerCombined,
    FaHandshake,
    FaFlagCheckered,
} from "react-icons/fa";
import { useState, useMemo, useEffect } from "react";

const projectStatusFlow = [
    { value: "planning", label: "Planning", color: "indigo", icon: FaLayerGroup, description: "Project setup, budgeting, team assignment" },
    { value: "survey", label: "Survey", color: "sky", icon: FaSearch, description: "Site survey, measurement & GPS verification" },
    { value: "foundation", label: "Foundation", color: "amber", icon: FaToolbox, description: "Excavation, footing & foundation work" },
    { value: "structure", label: "Structure", color: "violet", icon: FaBuilding, description: "Columns, beams, slabs & masonry" },
    { value: "finishing", label: "Finishing", color: "fuchsia", icon: FaRulerCombined, description: "Plaster, paint, flooring, fixtures" },
    { value: "handover", label: "Handover", color: "rose", icon: FaHandshake, description: "Final inspection, documents & handover" },
    { value: "completed", label: "Completed", color: "emerald", icon: FaFlagCheckered, description: "Project closed, handover complete" },
];

const formatCurrency = (value) => {
    const num = Number(value) || 0;
    if (num >= 10000000) return "₹" + (num / 10000000).toFixed(2) + " Cr";
    if (num >= 100000) return "₹" + (num / 100000).toFixed(2) + " L";
    if (num >= 1000) return "₹" + (num / 1000).toFixed(1) + "K";
    return "₹" + num.toFixed(0);
};

export default function Dashboard({ stats, recentProjects, recentActivity }) {
    const { props } = usePage();
    const auth = props?.auth;
    const isImpersonating = Boolean(props?.is_impersonating || auth?.is_impersonating);
    const isSuperAdmin = !isImpersonating && (
        auth?.guard === "superadmin" ||
        auth?.user?.email === "superadmin@gmail.com" ||
        Boolean(auth?.user?.is_super_admin) || 
        auth?.user?.slug === "super-admin" || 
        auth?.user?.slug === "super_admin" || 
        (Array.isArray(auth?.user?.assigned_roles) && (auth?.user?.assigned_roles.includes("super_admin") || auth?.user?.assigned_roles.includes("superadmin"))) ||
        (Array.isArray(auth?.user?.roles) && (auth?.user?.roles.includes("super_admin") || auth?.user?.roles.includes("superadmin")))
    );
    const userPermissions = auth?.permissions || auth?.construction_permissions || [];

    useEffect(() => {
        console.log("=== [DEBUG SuperAdmin ERP Dashboard Props] ===");
        console.log("Inertia Props:", props);
        console.log("Auth User:", auth?.user);
        console.log("Auth Guard:", auth?.guard);
        console.log("Is Impersonating:", isImpersonating);
        console.log("Impersonator Data:", props?.impersonator || auth?.impersonator);
        console.log("Permissions Count:", userPermissions.length);
        console.log("Permissions List:", userPermissions);
    }, [props]);

    const hasPerm = (slug) => {
        if (isSuperAdmin) return true;
        const viewSlug = slug.endsWith(".manage") ? slug.replace(".manage", ".view") : slug;
        const createSlug = slug.endsWith(".manage") ? slug.replace(".manage", ".create") : slug;
        return (
            userPermissions.includes(slug) ||
            userPermissions.includes(viewSlug) ||
            userPermissions.includes(createSlug)
        );
    };

    const s = stats || {};
    const projects = s.projects || { total: 0, running: 0, completed: 0, pending: 0 };
    const employees = s.employees || { total: 0, active: 0 };
    const survey = s.survey || { teams: 0, members: 0 };
    const vehicles = s.vehicles || { total: 0, active: 0 };
    const equipment = s.equipment || { total: 0, allocated: 0 };
    const clients = s.clients || { total: 0, company: 0, government: 0 };
    const finance = s.finance || { monthlyRevenue: 0, monthlyExpenses: 0, totalRevenue: 0, monthlyInvoiced: 0 };
    const attendance = s.attendance || { today: 0, presentToday: 0, pending: 0, thisMonth: 0 };
    const gps = s.gps || { activeVehicles24h: 0, pingsToday: 0 };
    const stageDistribution = s.stageDistribution || {};

    const [projectFilter, setProjectFilter] = useState("all");
    const [statusQuery, setStatusQuery] = useState("");

    const filteredRecentProjects = useMemo(() => {
        const list = recentProjects || [];
        let out = list;
        if (projectFilter !== "all") {
            out = out.filter((p) => p.current_stage === projectFilter);
        }
        if (statusQuery.trim()) {
            const q = statusQuery.trim().toLowerCase();
            out = out.filter(
                (p) =>
                    (p.name || "").toLowerCase().includes(q) ||
                    (p.project_code || "").toLowerCase().includes(q) ||
                    (p.client?.name || "").toLowerCase().includes(q)
            );
        }
        return out;
    }, [recentProjects, projectFilter, statusQuery]);

    const attendaceRateToday =
        attendance.today > 0 && employees.active > 0
            ? Math.min(100, Math.round((attendance.presentToday / Math.max(employees.active, 1)) * 100))
            : 0;

    return (
        <ConstructionShell
            title="ERP Dashboard"
            description="Real-time overview of your construction business — projects, workforce, fleet, finance and field execution."
            variant="super"
        >
            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 className="text-[22px] font-bold text-slate-900 dark:text-white tracking-tight">
                        Control Tower
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Complete visibility across every project lifecycle stage.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {hasPerm("client.manage") && (
                        <Link
                            href={route("super.construction.clients.index")}
                            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-[13px] font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            <FaPlus size={13} />
                            Register Client
                        </Link>
                    )}
                    {hasPerm("project.manage") && (
                        <Link
                            href={route("super.construction.projects.index")}
                            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-[13px] font-medium text-white shadow-sm hover:from-indigo-500 hover:to-violet-500"
                        >
                            <FaPlus size={13} />
                            Create Project
                        </Link>
                    )}
                </div>
            </div>

            <div className="space-y-8">
                <SectionGroupHeader title="Projects" subtitle="Live portfolio health across every construction stage" href={route("super.construction.projects.index")} hrefLabel="View Projects" />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Total Projects"
                        value={projects.total}
                        hint="All-time registered projects"
                        icon={FaProjectDiagram}
                        color="indigo"
                        onClick={() => (window.location.href = route("super.construction.projects.index"))}
                    />
                    <StatCard
                        label="Running Projects"
                        value={projects.running}
                        hint="In-progress across all 7 lifecycle stages"
                        icon={FaPlayCircle}
                        color="emerald"
                    />
                    <StatCard
                        label="Completed Projects"
                        value={projects.completed}
                        hint="Handed over & closed projects"
                        icon={FaCheckCircle}
                        color="sky"
                    />
                    <StatCard
                        label="Pending Projects"
                        value={projects.pending}
                        hint="Draft or awaiting budget approval"
                        icon={FaHourglassHalf}
                        color="amber"
                    />
                </div>

                <SectionGroupHeader title="Workforce & Field Team" subtitle="Employees, survey crews and on-ground manpower" />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <StatCard
                        label="Total Employees"
                        value={employees.total}
                        hint="Registered in employee master"
                        icon={FaUsers}
                        color="violet"
                    />
                    <StatCard
                        label="Active Employees"
                        value={employees.active}
                        hint={employees.total > 0 ? Math.round((employees.active / employees.total) * 100) + "% of total workforce active" : "No active records"}
                        icon={FaUserCheck}
                        color="emerald"
                        trend={employees.total > 0 ? Math.round((employees.active / employees.total) * 100) : 0}
                        trendLabel="active ratio"
                    />
                    <StatCard
                        label="Survey Teams"
                        value={survey.teams}
                        hint={`${survey.members} surveyors assigned across plans`}
                        icon={FaUsersCog}
                        color="sky"
                    />
                </div>

                <SectionGroupHeader title="Fleet, Equipment & Clients" subtitle="Physical assets and customer base overview" />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Vehicles"
                        value={vehicles.total}
                        hint={`${vehicles.active} active on site or in transit`}
                        icon={FaTruck}
                        color="amber"
                    />
                    <StatCard
                        label="Equipment"
                        value={equipment.total}
                        hint={`${equipment.allocated} currently allocated to projects`}
                        icon={FaCogs}
                        color="rose"
                    />
                    <StatCard
                        label="Total Clients"
                        value={clients.total}
                        hint={`${clients.company} Company · ${clients.government} Govt`}
                        icon={FaCity}
                        color="fuchsia"
                    />
                    <StatCard
                        label="Company Clients"
                        value={clients.company}
                        hint="Registered corporate client entities"
                        icon={FaIndustry}
                        color="slate"
                    />
                </div>

                <SectionGroupHeader title="Finance Overview" subtitle="This month's revenue, billing and collections" />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Monthly Revenue"
                        value={formatCurrency(finance.monthlyRevenue)}
                        hint="Payments received this month"
                        icon={FaMoneyBillWave}
                        color="emerald"
                        trend={finance.monthlyInvoiced > 0 ? Math.round((finance.monthlyRevenue / Math.max(finance.monthlyInvoiced, 1)) * 100) : 0}
                        trendLabel="of invoiced"
                    />
                    <StatCard
                        label="Revenue (All Time)"
                        value={formatCurrency(finance.totalRevenue)}
                        hint="Total client payments collected"
                        icon={FaChartLine}
                        color="indigo"
                    />
                    <StatCard
                        label="Monthly Invoiced"
                        value={formatCurrency(finance.monthlyInvoiced)}
                        hint="Client invoices raised this month"
                        icon={FaFileInvoiceDollar}
                        color="violet"
                    />
                    <StatCard
                        label="Monthly Expenses"
                        value={formatCurrency(finance.monthlyExpenses)}
                        hint="Operational expenses this month"
                        icon={FaHandHoldingUsd}
                        color="rose"
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <SectionCard
                        title="Attendance Summary"
                        description="Field workforce presence and GPS-verified attendance."
                        actions={
                            <Link
                                href={route("super.construction.execution.index")}
                                className="text-[13px] font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Review Attendance →
                            </Link>
                        }
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-emerald-50 to-white p-5 dark:border-slate-800 dark:from-emerald-950/40 dark:to-slate-950">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                                        <FaCalendarCheck size={16} />
                                        <p className="text-[13px] font-semibold uppercase tracking-wider">Today's Attendance</p>
                                    </div>
                                    <span className="text-xs rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200">
                                        Live
                                    </span>
                                </div>
                                <div className="mt-4 flex items-end justify-between gap-4">
                                    <div>
                                        <p className="text-3xl font-bold text-slate-900 dark:text-white">
                                            {attendance.presentToday}
                                            <span className="text-base font-normal text-slate-400 ml-1">
                                                / {employees.active || "—"}
                                            </span>
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            Field employees marked present today
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                            {attendaceRateToday}%
                                        </p>
                                        <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            Present rate
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-200/60 dark:bg-slate-800">
                                    <div
                                        className="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-500"
                                        style={{ width: `${attendaceRateToday}%` }}
                                    />
                                </div>
                            </div>

                            <div className="space-y-3">
                                <MiniRow
                                    icon={FaClipboardCheck}
                                    color="sky"
                                    label="Marked Today"
                                    value={attendance.today}
                                    subtitle="Check-ins recorded today"
                                />
                                <MiniRow
                                    icon={FaHourglassHalf}
                                    color="amber"
                                    label="Pending Review"
                                    value={attendance.pending}
                                    subtitle="Attendance requests pending"
                                />
                                <MiniRow
                                    icon={FaCalendarCheck}
                                    color="indigo"
                                    label="This Month"
                                    value={attendance.thisMonth}
                                    subtitle="Total attendance entries"
                                />
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        title="GPS Tracking Overview"
                        description="24-hour vehicle location pings and active site tracking."
                        actions={
                            <Link
                                href={route("super.construction.vehicles.index")}
                                className="text-[13px] font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Open Fleet →
                            </Link>
                        }
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-sky-50 to-white p-5 dark:border-slate-800 dark:from-sky-950/40 dark:to-slate-950">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-sky-700 dark:text-sky-300">
                                        <FaSatelliteDish size={16} />
                                        <p className="text-[13px] font-semibold uppercase tracking-wider">Active Vehicles</p>
                                    </div>
                                    <span className="text-xs rounded-full bg-sky-100 px-2 py-0.5 text-sky-700 dark:bg-sky-900 dark:text-sky-200">
                                        24h window
                                    </span>
                                </div>
                                <div className="mt-4 flex items-end justify-between gap-4">
                                    <div>
                                        <p className="text-3xl font-bold text-slate-900 dark:text-white">
                                            {gps.activeVehicles24h}
                                            <span className="text-base font-normal text-slate-400 ml-1">
                                                / {vehicles.total || "—"}
                                            </span>
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            Vehicles with GPS pings in last 24h
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-2xl font-bold text-sky-600 dark:text-sky-400">
                                            {vehicles.total > 0 ? Math.round((gps.activeVehicles24h / Math.max(vehicles.total, 1)) * 100) : 0}%
                                        </p>
                                        <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            fleet connected
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-4 grid grid-cols-3 gap-3">
                                    <div className="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 text-center">
                                        <FaMapMarkedAlt className="mx-auto text-sky-600 dark:text-sky-400 mb-1" size={16} />
                                        <p className="text-base font-bold text-slate-900 dark:text-white">{gps.pingsToday}</p>
                                        <p className="text-[10px] text-slate-500 dark:text-slate-400">Pings Today</p>
                                    </div>
                                    <div className="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 text-center">
                                        <FaTruck className="mx-auto text-emerald-600 dark:text-emerald-400 mb-1" size={16} />
                                        <p className="text-base font-bold text-slate-900 dark:text-white">{vehicles.active}</p>
                                        <p className="text-[10px] text-slate-500 dark:text-slate-400">In Use</p>
                                    </div>
                                    <div className="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 text-center">
                                        <FaCogs className="mx-auto text-violet-600 dark:text-violet-400 mb-1" size={16} />
                                        <p className="text-base font-bold text-slate-900 dark:text-white">{equipment.allocated}</p>
                                        <p className="text-[10px] text-slate-500 dark:text-slate-400">Equip. Alloc</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p className="text-[13px] font-semibold text-slate-700 dark:text-slate-200 mb-3">
                                    Recent GPS Activity
                                </p>
                                <div className="space-y-2">
                                    {recentProjects && recentProjects.slice(0, 4).length ? (
                                        recentProjects.slice(0, 4).map((p) => (
                                            <div
                                                key={p.id}
                                                className="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                                            >
                                                <div className="w-9 h-9 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center shrink-0 dark:bg-sky-950 dark:text-sky-300">
                                                    <FaMapMarkedAlt size={15} />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[13px] font-semibold text-slate-900 dark:text-white truncate">
                                                        {p.name}
                                                    </p>
                                                    <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                        {p.project_address ? `${p.project_address.slice(0, 40)}${p.project_address.length > 40 ? "…" : ""}` : (p.latitude && p.longitude ? `${Number(p.latitude).toFixed(3)}, ${Number(p.longitude).toFixed(3)}` : "Location pending")}
                                                    </p>
                                                </div>
                                                <span className="text-[11px] rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                    Tracked
                                                </span>
                                            </div>
                                        ))
                                    ) : (
                                        <EmptyState title="No GPS data yet" description="Vehicle and attendance GPS pings will appear here once field activity starts." />
                                    )}
                                </div>
                            </div>
                        </div>
                    </SectionCard>
                </div>

                <SectionCard
                    title="Project Management · Status Flow"
                    description="Every project moves through the 7-stage lifecycle. Create, view, edit, assign, or change project status."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative">
                                <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={13} />
                                <input
                                    type="text"
                                    value={statusQuery}
                                    onChange={(e) => setStatusQuery(e.target.value)}
                                    placeholder="Search projects…"
                                    className="rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2 text-[13px] text-slate-700 placeholder:text-slate-400 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-indigo-700 dark:focus:ring-indigo-900 w-56"
                                />
                            </div>
                            <select
                                value={projectFilter}
                                onChange={(e) => setProjectFilter(e.target.value)}
                                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[13px] text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-indigo-700 dark:focus:ring-indigo-900"
                            >
                                <option value="all">All Stages</option>
                                {projectStatusFlow.map((st) => (
                                    <option key={st.value} value={st.value}>
                                        {st.label}
                                    </option>
                                ))}
                            </select>
                            <Link
                                href={route("super.construction.projects.index")}
                                className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-[13px] font-medium text-white hover:bg-indigo-500"
                            >
                                <FaPlus size={12} />
                                New Project
                            </Link>
                        </div>
                    }
                >
                    <div className="grid gap-3 mb-5 md:grid-cols-7">
                        {projectStatusFlow.map((st) => {
                            const count =
                                stageDistribution && stageDistribution[st.value]
                                    ? stageDistribution[st.value]
                                    : 0;
                            const Icon = st.icon;
                            return (
                                <button
                                    key={st.value}
                                    onClick={() => setProjectFilter(projectFilter === st.value ? "all" : st.value)}
                                    className={`text-left rounded-2xl border p-4 transition-all duration-200 ${
                                        projectFilter === st.value
                                            ? "border-indigo-500 bg-indigo-50 shadow-md shadow-indigo-100 dark:border-indigo-500 dark:bg-indigo-950/60 dark:shadow-none"
                                            : "border-slate-200 bg-white hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div
                                            className={`w-9 h-9 rounded-lg flex items-center justify-center text-white ${
                                                {
                                                    indigo: "bg-indigo-600",
                                                    sky: "bg-sky-600",
                                                    amber: "bg-amber-500",
                                                    violet: "bg-violet-600",
                                                    fuchsia: "bg-fuchsia-600",
                                                    rose: "bg-rose-600",
                                                    emerald: "bg-emerald-600",
                                                }[st.color] || "bg-indigo-600"
                                            }`}
                                        >
                                            <Icon size={16} />
                                        </div>
                                        <span className="text-lg font-bold text-slate-900 dark:text-white">{count}</span>
                                    </div>
                                    <p className="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{st.label}</p>
                                    <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed line-clamp-2">
                                        {st.description}
                                    </p>
                                </button>
                            );
                        })}
                    </div>

                    <div className="rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div className="flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50">
                            <p className="text-[13px] font-semibold text-slate-700 dark:text-slate-200">
                                Project Register ({filteredRecentProjects.length})
                            </p>
                        </div>
                        {filteredRecentProjects && filteredRecentProjects.length ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead className="text-slate-500 bg-slate-50/50 dark:bg-slate-900/30 dark:text-slate-400">
                                        <tr>
                                            <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider">Project</th>
                                            <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider">Client</th>
                                            <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider">Stage</th>
                                            <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider">Budget</th>
                                            <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {filteredRecentProjects.map((project) => (
                                            <tr key={project.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={route("super.construction.projects.show", project.id)}
                                                        className="font-semibold text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                                    >
                                                        {project.name}
                                                    </Link>
                                                    <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                        {project.project_code || "—"}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 dark:text-slate-300 text-[13px]">
                                                    {project.client?.name || "—"}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge value={project.current_stage} />
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 dark:text-slate-300 text-[13px] font-medium">
                                                    {formatCurrency(
                                                        project.latest_budget?.approved_amount ||
                                                        project.latest_budget?.estimated_amount ||
                                                        0
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <ActionButton
                                                            label="View"
                                                            icon={FaEye}
                                                            href={route("super.construction.projects.show", project.id)}
                                                            color="indigo"
                                                        />
                                                        <ActionButton
                                                            label="Edit"
                                                            icon={FaEdit}
                                                            href={route("super.construction.projects.show", project.id)}
                                                            color="sky"
                                                        />
                                                        <DeleteActionButton
                                                            resourceType="project"
                                                            resourceId={project.id}
                                                            resourceName={project.name}
                                                            onDelete={() => {
                                                                if (confirm(`Delete project "${project.name}"? All survey, execution, material, billing and handover data tied to this project will be removed and cannot be restored.`)) {
                                                                    router.delete(route("super.construction.projects.destroy", project.id), {
                                                                        preserveScroll: true,
                                                                        onError: (errs) => {
                                                                            if (typeof window !== "undefined" && typeof window.alert === "function") {
                                                                                window.alert(Object.values(errs).find(Boolean) || "Failed to delete project.");
                                                                            }
                                                                        },
                                                                    });
                                                                }
                                                            }}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="p-8">
                                <EmptyState
                                    title="No projects match this view."
                                    description="Try clearing the search or stage filter, or create the first project to start the ERP lifecycle."
                                />
                            </div>
                        )}
                    </div>
                </SectionCard>

                <div className="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                    <SectionCard
                        title="Project Flow Snapshot"
                        description="Current projects moving through the approved lifecycle."
                    >
                        {recentProjects && recentProjects.length ? (
                            <div className="space-y-3">
                                {recentProjects.slice(0, 6).map((project) => {
                                    const st = projectStatusFlow.find((s) => s.value === project.current_stage);
                                    const StageIcon = st ? st.icon : FaProjectDiagram;
                                    const colorMap = {
                                        indigo: "#4f46e5",
                                        sky: "#0284c7",
                                        amber: "#d97706",
                                        violet: "#7c3aed",
                                        fuchsia: "#c026d3",
                                        rose: "#e11d48",
                                        emerald: "#059669",
                                    };
                                    const dotColor = st ? colorMap[st.color] : colorMap.indigo;
                                    const pillClass = st
                                        ? {
                                              indigo: "bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300",
                                              sky: "bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300",
                                              amber: "bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
                                              violet: "bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300",
                                              fuchsia: "bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950 dark:text-fuchsia-300",
                                              rose: "bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
                                              emerald: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
                                          }[st.color]
                                        : "bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300";
                                    return (
                                        <div
                                            key={project.id}
                                            className="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                                        >
                                            <div
                                                className="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 text-white shadow-sm"
                                                style={{ backgroundColor: dotColor }}
                                            >
                                                <StageIcon size={18} />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <Link
                                                        href={route("super.construction.projects.show", project.id)}
                                                        className="font-semibold text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400 truncate"
                                                    >
                                                        {project.name}
                                                    </Link>
                                                    {st && (
                                                        <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${pillClass}`}>
                                                            {st.label}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="mt-0.5 text-[12px] text-slate-500 dark:text-slate-400 truncate">
                                                    Client: {project.client?.name || "—"} · {project.company?.name || "—"}
                                                </div>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <p className="text-sm font-bold text-slate-900 dark:text-white">
                                                    {formatCurrency(
                                                        project.latest_budget?.approved_amount ||
                                                            project.latest_budget?.estimated_amount ||
                                                            0
                                                    )}
                                                </p>
                                                <p className="text-[11px] text-slate-500 dark:text-slate-400">Budget</p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <EmptyState
                                title="No construction projects yet."
                                description="Start with client registration and project creation."
                            />
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Recent Lifecycle Activity"
                        description="Recent construction-only actions across all projects."
                    >
                        {recentActivity && recentActivity.length ? (
                            <div className="space-y-2.5 max-h-[440px] overflow-y-auto pr-1">
                                {recentActivity.map((item) => (
                                    <div
                                        key={item.id}
                                        className="rounded-xl bg-slate-50 p-3.5 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/60"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-[13px] font-semibold text-slate-900 capitalize dark:text-white">
                                                {item.module}
                                            </p>
                                            <StatusBadge value={item.action} />
                                        </div>
                                        <p className="mt-1.5 text-[12px] text-slate-600 dark:text-slate-300">
                                            Project: <span className="font-medium">{item.project?.name || "Global"}</span>
                                        </p>
                                        <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            {item.created_at}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                title="No lifecycle activity yet."
                                description="Construction ERP activity will appear here as the flow starts moving."
                            />
                        )}
                    </SectionCard>
                </div>
            </div>
        </ConstructionShell>
    );
}

function SectionGroupHeader({ title, subtitle, href, hrefLabel }) {
    return (
        <div className="-mb-3 flex items-end justify-between gap-4 pt-2">
            <div>
                <h2 className="text-[15px] font-bold text-slate-900 dark:text-white tracking-tight">
                    {title}
                </h2>
                {subtitle ? (
                    <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{subtitle}</p>
                ) : null}
            </div>
            {href && hrefLabel ? (
                <Link href={href} className="text-[12px] font-semibold text-indigo-600 hover:text-indigo-500 whitespace-nowrap">
                    {hrefLabel} →
                </Link>
            ) : null}
        </div>
    );
}

function MiniRow({ icon, color, label, value, subtitle }) {
    const Icon = icon;
    return (
        <div className="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 dark:border-slate-800">
            <div
                className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 text-white ${
                    {
                        indigo: "bg-indigo-600",
                        sky: "bg-sky-600",
                        amber: "bg-amber-500",
                        violet: "bg-violet-600",
                        fuchsia: "bg-fuchsia-600",
                        rose: "bg-rose-600",
                        emerald: "bg-emerald-600",
                    }[color] || "bg-indigo-600"
                }`}
            >
                <Icon size={15} />
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-[12px] font-medium text-slate-600 dark:text-slate-300">{label}</p>
                    <p className="text-base font-bold text-slate-900 dark:text-white">{value}</p>
                </div>
                <p className="text-[11px] text-slate-500 dark:text-slate-400 truncate">{subtitle}</p>
            </div>
        </div>
    );
}

function ActionButton({ label, icon, color = "indigo", href, onClick }) {
    const Icon = icon;
    const colors = {
        indigo: "text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/60",
        sky: "text-sky-600 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-950/60",
        rose: "text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/60",
        emerald: "text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/60",
        amber: "text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/60",
    };
    const cls = `inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors ${colors[color] || colors.indigo}`;
    if (href) {
        return (
            <Link href={href} title={label} className={cls}>
                <Icon size={14} />
            </Link>
        );
    }
    return (
        <button type="button" onClick={onClick} title={label} className={cls}>
            <Icon size={14} />
        </button>
    );
}
