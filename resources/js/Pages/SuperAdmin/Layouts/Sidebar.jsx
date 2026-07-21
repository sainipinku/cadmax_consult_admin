import React from "react";
import { Link, usePage } from "@inertiajs/react";

const navigation = [
    {
        label: "ERP Dashboard",
        href: route("super.dashboard"),
        active: ["super.dashboard", "super.construction.dashboard"],
    },
    {
        label: "Company Setup",
        href: route("super.construction.companies.index"),
        active: ["super.construction.companies.*"],
    },
    {
        label: "Roles & Access",
        href: route("super.role.list"),
        active: ["super.role.*"],
    },
    {
        label: "Departments",
        href: route("super.departments"),
        active: ["super.departments"],
    },
    {
        label: "Employee Assignment",
        href: route("super.construction.projects.index"),
        active: ["super.construction.projects.*"],
    },
    {
        label: "Employee Management",
        href: route("super.employees.list"),
        active: ["super.employees.*"],
    },
    {
        label: "Client Registration",
        href: route("super.construction.clients.index"),
        active: ["super.construction.clients.*"],
    },
    {
        label: "Projects & Budget",
        href: route("super.construction.projects.index"),
        active: ["super.construction.projects.*"],
    },
    {
        label: "Survey Planning",
        href: route("super.construction.survey.index"),
        active: ["super.construction.survey.*"],
    },
    {
        label: "Drawing Approval",
        href: route("super.construction.drafting.index"),
        active: ["super.construction.drafting.*"],
    },
    {
        label: "Construction Execution",
        href: route("super.construction.execution.index"),
        active: ["super.construction.execution.*"],
    },
    {
        label: "Material Management",
        href: route("super.construction.materials.index"),
        active: ["super.construction.materials.*"],
    },
    {
        label: "Vehicle Tracking",
        href: route("super.construction.vehicles.index"),
        active: ["super.construction.vehicles.*"],
    },
    {
        label: "Equipment Allocation",
        href: route("super.construction.equipment.index"),
        active: ["super.construction.equipment.*"],
    },
    {
        label: "Accounts & Billing",
        href: route("super.construction.billing.index"),
        active: ["super.construction.billing.*"],
    },
    {
        label: "Handover & Closure",
        href: route("super.construction.handover.index"),
        active: ["super.construction.handover.*"],
    },
];

export default function Sidebar({ isOpen, onClose }) {
    const { props } = usePage();

    return (
        <>
            {/* Backdrop overlay - below navbar */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-30 "
                    onClick={onClose}
                />
            )}

            <aside
                className={`fixed top-[62px] left-0 z-40 h-[calc(100vh-62px)] w-[288px] bg-white text-slate-900 shadow-md transition-transform duration-300 dark:bg-[#03011C] dark:text-white ${
                    isOpen ? "translate-x-0" : "-translate-x-full"
                }`}
            >
                <div className="border-b border-gray-200 p-4 dark:border-b-[#5146e64a]">
                    {/* Logo commented out
                    /<div className="block dark:hidden">
                        <img
                            className="max-w-[100px] sm:max-w-[120px]"
                            src="/images/logo.png"
                            alt="Logo"
                        />
                    </div>
                    <div className="hidden dark:block">
                        <img
                            className="max-w-[100px] sm:max-w-[120px]"
                            src={props?.auth?.user?.profile_photo_url}
                            alt="Logo"
                        />
                    </div>
                    */}

                    {/* <button
                        onClick={onClose}
                        className="absolute right-[5px] top-[16px] flex h-[38px] w-[48px] items-center justify-center rounded-[8px] border border-[#0000001A] bg-white text-[#000] transition focus:outline-none dark:border-[#61CC681A] dark:bg-[#61CC681A] dark:text-[#fff]"
                    >
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button> */}
                </div>

                <div className="border-b border-gray-200 px-4 py-4 dark:border-b-[#5146e64a]">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600 dark:text-indigo-300">
                        Construction ERP
                    </p>
                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Super Admin controls aligned to the project lifecycle only.
                    </p>
                </div>

                <nav className="px-[5px] py-[8px] overflow-y-auto max-h-[calc(100vh-220px)]">
                    {navigation.map((item) => {
                        const isActive = item.active.some((pattern) => route().current(pattern));

                        return (
                            <div
                                key={item.label}
                                className="border-b border-b-gray-200 dark:border-b-[#5146e64a]"
                            >
                                <Link
                                    href={item.href}
                                    className={`flex items-center gap-3 rounded px-[10px] py-[10px] text-[15px] transition ${
                                        isActive
                                            ? "bg-[#5146E6] text-white"
                                            : "text-[#727272] hover:bg-[#5146E6] hover:text-white"
                                    }`}
                                >
                                    <span
                                        className={`h-2.5 w-2.5 rounded-full ${
                                            isActive ? "bg-white" : "bg-indigo-400"
                                        }`}
                                    />
                                    {item.label}
                                </Link>
                            </div>
                        );
                    })}
                </nav>
            </aside>
        </>
    );
}