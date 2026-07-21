import React from "react";
import { Link, usePage } from "@inertiajs/react";

export default function Sidebar({ isOpen, onClose }) {
    const user = usePage().props.auth?.user;
    const permissions = usePage().props.auth?.permissions ?? [];

    const items = [
        {
            label: "Project Dashboard",
            href: route("member.dashboard"),
            active: ["member.dashboard", "member.construction.dashboard"],
            permissions: ["dashboard.view"],
        },
        {
            label: "Assigned Projects",
            href: route("member.construction.projects.index"),
            active: ["member.construction.projects.*"],
            permissions: ["dashboard.view"],
        },
        {
            label: "Site Execution",
            href: route("member.construction.execution.index"),
            active: ["member.construction.execution.*"],
            permissions: ["execution_task.manage", "dpr.manage", "attendance.manage"],
        },
        {
            label: "Material Management",
            href: route("member.construction.materials.index"),
            active: ["member.construction.materials.*"],
            permissions: ["material_issue.manage", "material_stock.manage"],
        },
        {
            label: "Vehicle Tracking",
            href: route("member.construction.vehicles.index"),
            active: ["member.construction.vehicles.*"],
            permissions: ["vehicle_tracking.manage"],
        },
        {
            label: "Equipment Allocation",
            href: route("member.construction.equipment.index"),
            active: ["member.construction.equipment.*"],
            permissions: ["equipment_allocation.manage", "equipment_usage.manage"],
        },
        {
            label: "Handover & Closure",
            href: route("member.construction.handover.index"),
            active: ["member.construction.handover.*"],
            permissions: ["handover.manage"],
        },
        {
            label: "My Profile",
            href: route("member.profile"),
            active: ["member.profile"],
            permissions: [],
        },
    ].filter((item) => item.permissions.length === 0 || item.permissions.some((perm) => permissions.includes(perm)));

    return (
        <div
            className={`fixed top-0 left-0 h-full w-[288px] bg-white dark:bg-[#03011C] text-white transition-transform duration-300 z-[99] shadow-md ${
                isOpen ? "translate-x-0" : "-translate-x-full"
            }`}
        >
            <div className="border-b border-gray-200 p-4 text-lg font-bold dark:border-b-[#5146e64a]">
                <div className="block dark:hidden">
                    <img className="max-w-[100px] sm:max-w-[120px]" src="/images/logo.png" alt="Logo" />
                </div>
                <div className="hidden dark:block">
                    <img className="max-w-[100px] sm:max-w-[120px]" src="/images/logo-dark.png" alt="Logo" />
                </div>

                <button
                    onClick={onClose}
                    className="absolute right-[5px] top-[16px] flex xl:hidden items-center justify-center bg-white dark:bg-[#61CC681A] w-[48px] h-[38px] border border-[#0000001A] dark:border-[#61CC681A] rounded-[8px] transition md:flex text-[#000] dark:text-[#fff] focus:outline-none"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div className="border-b border-gray-200 px-4 py-4 dark:border-b-[#5146e64a]">
                <p className="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600 dark:text-indigo-300">
                    Construction ERP
                </p>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Site member workspace for assigned construction projects only.
                </p>
                {user?.name ? (
                    <p className="mt-3 text-sm font-medium text-slate-900 dark:text-white">{user.name}</p>
                ) : null}
            </div>

            <ul className="px-[5px] py-[8px]">
                {items.map((item) => {
                    const isActive = item.active.some((pattern) => route().current(pattern));

                    return (
                        <li key={item.label} className="border-b border-b-gray-200 dark:border-b-[#5146e64a]">
                            <Link
                                href={item.href}
                                className={`flex items-center gap-[8px] px-[10px] py-[10px] text-[15px] rounded ${
                                    isActive
                                        ? "text-[#4F46E5] dark:text-[#4F46E5] bg-[#4F46E5]/10"
                                        : "text-[#727272] hover:text-[#4F46E5] dark:hover:text-[#4F46E5]"
                                }`}
                            >
                                <span className={`h-2.5 w-2.5 rounded-full ${isActive ? "bg-[#4F46E5]" : "bg-indigo-400"}`} />
                                {item.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
