import React from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    FaBuilding,
    FaUsers,
    FaProjectDiagram,
    FaClipboardList,
    FaDraftingCompass,
    FaHardHat,
    FaBoxes,
    FaTruck,
    FaCogs,
    FaHandshake,
    FaTachometerAlt,
    FaCity,
    FaLayerGroup,
    FaRoute,
    FaTools,
    FaMoneyBillWave,
    FaChevronRight,
    FaShieldAlt,
} from "react-icons/fa";

const navigation = [
    {
        section: "Overview",
        items: [
            {
                label: "ERP Dashboard",
                permission: "dashboard.view",
                href: route("super.dashboard"),
                active: ["super.dashboard", "super.construction.dashboard"],
                icon: FaTachometerAlt,
            },
            {
                label: "Permission Requests",
                permission: "approval_request.manage",
                adminOnly: true,
                href: route("super.approval_requests.index"),
                active: ["super.approval_requests.*"],
                icon: FaClipboardList,
            },
            {
                label: "Admin & Super Admins",
                permission: "super_admin.manage",
                adminOnly: true,
                href: route("super.super_admins.index"),
                active: ["super.super_admins.*"],
                icon: FaUsers,
            },
            {
                label: "Role Management",
                permission: "role.manage",
                adminOnly: true,
                href: route("super.role.list"),
                active: ["super.role.*"],
                icon: FaShieldAlt,
            },
        ],
    },
    {
        section: "Foundation",
        items: [
            {
                label: "Employee Management",
                permission: "employee.manage",
                adminOnly: true,
                href: route("super.employees.list"),
                active: ["super.employees.*"],
                icon: FaUsers,
            },
            {
                label: "Client Registration",
                permission: "client.manage",
                adminOnly: true,
                href: route("super.construction.clients.index"),
                active: ["super.construction.clients.*"],
                icon: FaCity,
            },
        ],
    },
    {
        section: "Project Lifecycle",
        items: [
            {
                label: "Projects & Budget",
                permission: "project.manage",
                href: route("super.construction.projects.index"),
                active: ["super.construction.projects.*"],
                icon: FaProjectDiagram,
            },
            {
                label: "Survey Planning",
                permission: "survey_plan.manage",
                href: route("super.construction.survey.index"),
                active: ["super.construction.survey.*"],
                icon: FaClipboardList,
            },
            {
                label: "Drawing Approval",
                permission: "drawing_approval.manage",
                href: route("super.construction.drafting.index"),
                active: ["super.construction.drafting.*"],
                icon: FaDraftingCompass,
            },
        ],
    },
    {
        section: "Fleet & Equipment",
        items: [
            {
                label: "Vehicle Management",
                permission: "vehicle.manage",
                href: route("super.vehicles.list"),
                active: ["super.vehicles.*"],
                icon: FaTruck,
            },
            {
                label: "Vehicle Tracking",
                permission: "vehicle_tracking.manage",
                href: route("super.construction.vehicles.index"),
                active: ["super.construction.vehicles.*"],
                icon: FaRoute,
            },
            {
                label: "Equipment Categories",
                permission: "equipment_category.manage",
                href: route("super.equipment.categories.list"),
                active: ["super.equipment.categories.*"],
                icon: FaLayerGroup,
            },
            {
                label: "Equipment Management",
                permission: "equipment.manage",
                href: route("super.equipment.list"),
                active: ["super.equipment.*"],
                exclude: ["super.equipment.categories.*"],
                icon: FaTools,
            },
        ],
    },
    {
        section: "Finance & Closure",
        items: [
            {
                label: "Accounts & Billing",
                permission: "billing.manage",
                adminOnly: true,
                href: route("super.construction.billing.index"),
                active: ["super.construction.billing.*"],
                icon: FaMoneyBillWave,
            },
            {
                label: "Handover & Closure",
                permission: "handover.manage",
                href: route("super.construction.handover.index"),
                active: ["super.construction.handover.*"],
                icon: FaHandshake,
            },
        ],
    },
];

const stageLabels = {
    budget_pending: "Budget Pending",
    budget_approved: "Budget Approved",
    team_assigned: "Team Assigned",
    planning: "Planning",
    survey: "Survey",
    foundation: "Foundation",
    structure: "Structure",
    finishing: "Finishing",
    handover: "Handover",
    completed: "Completed",
};

const statusColors = {
    draft: "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300",
    active: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
    completed: "bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300",
    on_hold: "bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
    cancelled: "bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
};

export default function Sidebar({ isOpen, onClose }) {
    const { props } = usePage();
    const auth = props?.auth;
    const user = auth?.user;
    const guard = auth?.guard;
    const isImpersonating = Boolean(props?.is_impersonating || auth?.is_impersonating);
    const currentProject = props?.current_project;
    const userPermissions = auth?.permissions || auth?.construction_permissions || [];

    const isSuperAdminUnimpersonated = !isImpersonating && (
        guard === "superadmin" ||
        user?.email === "superadmin@gmail.com" ||
        Boolean(user?.is_super_admin) || 
        user?.slug === "super-admin" || 
        user?.slug === "super_admin" ||
        (Array.isArray(user?.assigned_roles) && (user.assigned_roles.includes("super_admin") || user.assigned_roles.includes("superadmin"))) ||
        (Array.isArray(user?.roles) && (user.roles.includes("super_admin") || user.roles.includes("superadmin")))
    );
    const isAdminUser = 
        isSuperAdminUnimpersonated ||
        Boolean(user?.is_admin) || 
        user?.slug === "admin" || 
        (Array.isArray(user?.assigned_roles) && (user.assigned_roles.includes("admin") || user.assigned_roles.includes("project_admin"))) ||
        (Array.isArray(user?.roles) && (user.roles.includes("admin") || user.roles.includes("project_admin")));
    const isSuperAdmin = isSuperAdminUnimpersonated;

    const isEmployeeView = isImpersonating && !isAdminUser;

    const hasPerm = (permSlug) => {
        if (!permSlug) return true;
        if (isSuperAdmin) return true;
        if (permSlug === "dashboard.view") return true;

        const viewSlug = permSlug.endsWith(".manage") ? permSlug.replace(".manage", ".view") : permSlug;
        const createSlug = permSlug.endsWith(".manage") ? permSlug.replace(".manage", ".create") : permSlug;
        const editSlug = permSlug.endsWith(".manage") ? permSlug.replace(".manage", ".edit") : permSlug;
        const deleteSlug = permSlug.endsWith(".manage") ? permSlug.replace(".manage", ".delete") : permSlug;
        const manageSlug = permSlug.endsWith(".view") ? permSlug.replace(".view", ".manage") : permSlug;

        return (
            userPermissions.includes(permSlug) ||
            userPermissions.includes(viewSlug) ||
            userPermissions.includes(createSlug) ||
            userPermissions.includes(editSlug) ||
            userPermissions.includes(deleteSlug) ||
            userPermissions.includes(manageSlug)
        );
    };

    const isProjectOwner = user?.is_project_owner == 1 || (user && (user.id == 1 || user.id === '1'));

    const visibleNavigation = navigation
        .map((group) => {
            const items = group.items
                .filter((item) => {
                    if (isSuperAdmin) return true;
                    if (item.adminOnly && !isAdminUser) return false;
                    return hasPerm(item.permission);
                })
                .map((item) => {
                    if (item.label === "Admin & Super Admins" && !isProjectOwner) {
                        return { ...item, label: "Admin" };
                    }
                    return item;
                });
            return { ...group, items };
        })
        .filter((group) => group.items.length > 0);

    return (
        <>
            {/* Backdrop overlay - closes sidebar when clicking outside */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm xl:hidden"
                    onClick={onClose}
                />
            )}

            <aside
                className={`fixed left-0 top-0 z-40 flex h-screen w-[288px] flex-col bg-white text-slate-900 shadow-2xl shadow-slate-900/10 transition-transform duration-300 ease-in-out dark:bg-[#03011C] dark:text-white dark:shadow-black/40 ${
                    isOpen ? "translate-x-0" : "-translate-x-full"
                }`}
            >
                {/* ── Brand Header ─────────────────────────────── */}
                <div className="relative flex shrink-0 items-center gap-3 border-b border-gray-200/80 px-5 py-4 dark:border-[#5146e64a]">
                    <img
                        src="/images/cadmax_con_logo.jpeg"
                        alt="betaxtech Logo"
                        className="h-10 w-10 shrink-0 rounded-xl object-contain bg-slate-900/60 p-0.5 border border-indigo-500/20 shadow-lg shadow-indigo-500/20"
                        onError={(e) => {
                            e.currentTarget.onerror = null;
                            e.currentTarget.src = "/images/logo.png";
                        }}
                    />
                    <div className="min-w-0">
                        <p className="truncate text-[15px] uppercase font-bold tracking-tight text-slate-900 dark:text-white">
                            betaxtech
                        </p>
                        <p className="truncate text-[11px] font-medium uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">
                            {isEmployeeView ? "Employee Workspace" : "Super Admin"}
                        </p>
                    </div>
                </div>

                {/* ── Current Project ──────────────────────────── */}
                {currentProject && (
                    <div className="border-b border-gray-200/80 px-5 py-3 dark:border-[#5146e64a]">
                        <div className="rounded-xl bg-gradient-to-r from-indigo-50 to-violet-50 p-3 dark:from-[#5146E61A] dark:to-[#5146E60D]">
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">
                                Active Project
                            </p>
                            <p className="mt-1 truncate text-[13px] font-semibold text-slate-900 dark:text-white">
                                {currentProject.name}
                            </p>
                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                    {currentProject.project_code}
                                </span>
                                {currentProject.current_stage ? (
                                    <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                        {stageLabels[currentProject.current_stage] || currentProject.current_stage}
                                    </span>
                                ) : null}
                                {currentProject.status ? (
                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                        statusColors[currentProject.status] || statusColors.draft
                                    }`}>
                                        {currentProject.status}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </div>
                )}

                {/* ── Scrollable Navigation ────────────────────── */}
                <nav className="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 sidebar-scroll">
                    {visibleNavigation.map((group) => (
                        <div key={group.section} className="mb-5 last:mb-0">
                            <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                                {group.section}
                            </p>
                            <div className="space-y-1">
                                {group.items.map((item) => {
                                    const isActive =
                                        item.active.some((pattern) => route().current(pattern)) &&
                                        (!item.exclude || !item.exclude.some((pattern) => route().current(pattern)));
                                    const Icon = item.icon;

                                    return (
                                        <Link
                                            key={item.label}
                                            href={item.href}
                                            className={`group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-medium transition-all duration-200 ${
                                                isActive
                                                    ? "bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-600/25"
                                                    : "text-slate-600 hover:bg-indigo-50 hover:text-indigo-700 dark:text-slate-300 dark:hover:bg-[#5146E61A] dark:hover:text-indigo-300"
                                            }`}
                                        >
                                            {/* Active indicator bar */}
                                            {isActive && (
                                                <span className="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-white/90" />
                                            )}

                                            <span
                                                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors ${
                                                    isActive
                                                        ? "bg-white/15 text-white"
                                                        : "bg-slate-100 text-slate-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 dark:bg-[#5146E61A] dark:text-slate-400 dark:group-hover:bg-[#5146E633] dark:group-hover:text-indigo-300"
                                                }`}
                                            >
                                                <Icon size={14} />
                                            </span>

                                            <span className="flex-1 truncate">
                                                {item.label}
                                            </span>

                                            <FaChevronRight
                                                size={10}
                                                className={`shrink-0 transition-all duration-200 ${
                                                    isActive
                                                        ? "translate-x-0 opacity-100"
                                                        : "-translate-x-1 opacity-0 group-hover:translate-x-0 group-hover:opacity-100"
                                                }`}
                                            />
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                {/* ── Footer / User Card ───────────────────────── */}
                <div className="shrink-0 border-t border-gray-200/80 p-3 dark:border-[#5146e64a]">
                    <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-indigo-50 to-violet-50 p-3 dark:from-[#5146E61A] dark:to-[#5146E60D]">
                        <div className="relative shrink-0">
                            <img
                                src={
                                    user?.profile_photo_url ||
                                    "/images/profileimg.png"
                                }
                                alt="User"
                                className="h-9 w-9 rounded-full border-2 border-white object-cover shadow-sm dark:border-[#5146E6]"
                                onError={(e) => {
                                    e.currentTarget.src = "/images/profileimg.png";
                                }}
                            />
                            <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-500 dark:border-[#03011C]" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[13px] font-semibold text-slate-900 dark:text-white">
                                {user?.name || "Super Admin"}
                            </p>
                            <p className="truncate text-[11px] text-slate-500 dark:text-slate-400">
                                {user?.email || "admin@cadmax.in"}
                            </p>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Custom scrollbar styles */}
            <style>{`
                .sidebar-scroll::-webkit-scrollbar {
                    width: 4px;
                }
                .sidebar-scroll::-webkit-scrollbar-track {
                    background: transparent;
                }
                .sidebar-scroll::-webkit-scrollbar-thumb {
                    background: rgba(81, 70, 230, 0.3);
                    border-radius: 9999px;
                }
                .sidebar-scroll::-webkit-scrollbar-thumb:hover {
                    background: rgba(81, 70, 230, 0.5);
                }
                .sidebar-scroll {
                    scrollbar-width: thin;
                    scrollbar-color: rgba(81, 70, 230, 0.3) transparent;
                }
            `}</style>
        </>
    );
}