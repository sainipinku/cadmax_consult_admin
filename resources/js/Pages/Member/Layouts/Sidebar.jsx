import React from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    FaTachometerAlt,
    FaProjectDiagram,
    FaHardHat,
    FaBoxes,
    FaTruck,
    FaCogs,
    FaHandshake,
    FaUserCircle,
} from "react-icons/fa";

const navigation = [
    {
        label: "Project Dashboard",
        href: route("member.dashboard"),
        active: ["member.dashboard", "member.construction.dashboard"],
        icon: FaTachometerAlt,
        permissions: ["dashboard.view"],
    },
    {
        label: "Assigned Projects",
        href: route("member.construction.projects.index"),
        active: ["member.construction.projects.*"],
        icon: FaProjectDiagram,
        permissions: ["project.manage", "project.view"],
    },
    {
        label: "Site Execution",
        href: route("member.construction.execution.index"),
        active: ["member.construction.execution.*"],
        icon: FaHardHat,
        permissions: ["execution_task.manage", "execution.task.view", "execution.manage", "execution.view", "dpr.manage", "dpr.view", "attendance.manage"],
    },
    {
        label: "Vehicle Tracking",
        href: route("member.construction.vehicles.index"),
        active: ["member.construction.vehicles.*"],
        icon: FaTruck,
        permissions: ["vehicle_tracking.manage", "vehicle_tracking.view", "vehicle.manage", "vehicle.view"],
    },
    {
        label: "Equipment Allocation",
        href: route("member.construction.equipment.index"),
        active: ["member.construction.equipment.*"],
        icon: FaCogs,
        permissions: ["equipment_allocation.manage", "equipment_allocation.view", "equipment_usage.manage", "equipment.manage", "equipment.view"],
    },
    {
        label: "Handover & Closure",
        href: route("member.construction.handover.index"),
        active: ["member.construction.handover.*"],
        icon: FaHandshake,
        permissions: ["handover.manage", "handover.view", "project_closure.manage"],
    },
    {
        label: "My Profile",
        href: route("member.profile"),
        active: ["member.profile"],
        icon: FaUserCircle,
    },
];

export default function Sidebar({ isOpen, onClose }) {
    const page = usePage();
    const auth = page.props?.auth;
    const user = auth?.user;
    const guard = auth?.guard;
    const isSuperAdmin = guard === 'superadmin' || user?.slug === 'super-admin' || user?.is_super_admin;
    const userPermissions = auth?.permissions || auth?.construction_permissions || page.props?.permissions || [];

    const hasPerm = (itemPerms) => {
        if (isSuperAdmin) return true;
        if (!itemPerms || itemPerms.length === 0) return true;

        return itemPerms.some((permSlug) => {
            if (userPermissions.includes(permSlug)) return true;
            const prefix = permSlug.split('.')[0];
            return userPermissions.some((p) => typeof p === 'string' && (p === permSlug || p.startsWith(prefix + '.')));
        });
    };

    const visibleItems = navigation.filter((item) => hasPerm(item.permissions));

    return (
        <>
            {/* Backdrop overlay - closes sidebar when clicking outside on mobile */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm xl:hidden"
                    onClick={onClose}
                />
            )}

            <aside
            className={`fixed left-0 z-40 h-[calc(100vh-62px)] w-[288px] bg-white text-slate-900 shadow-md transition-transform duration-300 dark:bg-[#03011C] dark:text-white overflow-y-auto ${
                isOpen ? "translate-x-0" : "-translate-x-full"
            }`}
        >
            <div className="border-b border-gray-200 px-4 py-3 dark:border-b-[#5146e64a]">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="block dark:hidden">
                            <img className="max-w-[90px] sm:max-w-[110px]" src="/images/logo.png" alt="Logo" />
                        </div>
                        <div className="hidden dark:block">
                            <img className="max-w-[90px] sm:max-w-[110px]" src="/images/logo-dark.png" alt="Logo" />
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="flex h-[38px] w-[48px] items-center justify-center rounded-[8px] border border-[#0000001A] bg-white text-[#000] transition focus:outline-none dark:border-[#61CC681A] dark:bg-[#61CC681A] dark:text-[#fff] xl:hidden"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div className="border-b border-gray-200 px-4 py-3 dark:border-b-[#5146e64a]">
                <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-emerald-600 dark:text-emerald-400">
                    Site Member Workspace
                </p>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Assigned construction tasks, DPR, attendance and field execution.
                </p>
                {user?.name ? (
                    <div className="mt-3 flex items-center gap-2">
                    <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 flex items-center justify-center font-bold text-sm overflow-hidden border border-emerald-200 dark:border-emerald-800">
                        {user.profile_photo_url ? (
                            <img src={user.profile_photo_url} alt={user.name} className="w-full h-full object-cover" />
                        ) : (
                            (user.name || "U").charAt(0).toUpperCase()
                        )}
                    </span>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-slate-900 dark:text-white truncate">{user.name}</p>
                        {user.email ? (
                            <p className="text-[11px] text-slate-500 dark:text-slate-400 truncate">{user.email}</p>
                        ) : null}
                    </div>
                </div>
                ) : null}
            </div>

            <nav className="px-[10px] py-[12px] space-y-1">
                {visibleItems.map((item) => {
                    const isActive = item.active.some((pattern) => route().current(pattern));
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.label}
                            href={item.href}
                            className={`flex items-center gap-3 rounded-xl px-[12px] py-[11px] text-[14px] font-medium transition-all duration-200 ${
                                isActive
                                    ? "bg-emerald-600 text-white shadow-md shadow-emerald-500/20"
                                    : "text-[#52525B] hover:bg-emerald-50 hover:text-emerald-700 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-emerald-300"
                            }`}
                        >
                            <Icon className="w-[17px] h-[17px] shrink-0" />
                            <span className="flex-1 truncate">{item.label}</span>
                            {isActive && <span className="ml-auto w-1.5 h-6 rounded-full bg-white/80" />}
                        </Link>
                    );
                })}
            </nav>

            <div className="mt-4 px-4 pb-6">
                <div className="rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 p-4 text-white shadow-md">
                    <p className="text-[10px] uppercase tracking-wider opacity-80">Field Duty</p>
                    <p className="mt-1 text-sm font-semibold">Execution &amp; Daily Progress</p>
                    <p className="mt-2 text-[11px] opacity-80 leading-relaxed">
                        Projects → Execution → Materials → Equipment → Handover
                    </p>
                </div>
            </div>
        </aside>
    </>);
}
