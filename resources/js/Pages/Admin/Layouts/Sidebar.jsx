import React from "react";
import { Link, usePage } from "@inertiajs/react";

const adminFlowItems = [
    {
        label: "Project Dashboard",
        href: route("admin.dashboard"),
        active: ["admin.dashboard", "admin.construction.dashboard"],
        permissions: ["dashboard.view"],
    },
    {
        label: "Assigned Projects",
        href: route("admin.construction.projects.index"),
        active: ["admin.construction.projects.*"],
        permissions: ["project.manage"],
    },
    {
        label: "Survey Workflow",
        href: route("admin.construction.survey.index"),
        active: ["admin.construction.survey.*"],
        permissions: ["survey_plan.manage", "survey_submission.review"],
    },
    {
        label: "Drawing Approval",
        href: route("admin.construction.drafting.index"),
        active: ["admin.construction.drafting.*"],
        permissions: ["drafting.manage", "drawing_approval.manage"],
    },
    {
        label: "Construction Execution",
        href: route("admin.construction.execution.index"),
        active: ["admin.construction.execution.*"],
        permissions: ["execution.manage", "execution_task.manage", "dpr.manage", "dpr.review", "attendance.manage", "attendance.review"],
    },
    {
        label: "Material Management",
        href: route("admin.construction.materials.index"),
        active: ["admin.construction.materials.*"],
        permissions: ["material.manage", "purchase_request.manage", "purchase_order.manage", "material_receipt.manage", "material_issue.manage", "material_stock.manage"],
    },
    {
        label: "Vehicle Tracking",
        href: route("admin.construction.vehicles.index"),
        active: ["admin.construction.vehicles.*"],
        permissions: ["vehicle.manage", "vehicle_assignment.manage", "vehicle_tracking.manage"],
    },
    {
        label: "Equipment Allocation",
        href: route("admin.construction.equipment.index"),
        active: ["admin.construction.equipment.*"],
        permissions: ["equipment.manage", "equipment_allocation.manage", "equipment_usage.manage"],
    },
    {
        label: "Accounts & Billing",
        href: route("admin.construction.billing.index"),
        active: ["admin.construction.billing.*"],
        permissions: ["billing_invoice.manage", "billing_payment.manage"],
    },
    {
        label: "Handover & Closure",
        href: route("admin.construction.handover.index"),
        active: ["admin.construction.handover.*"],
        permissions: ["handover.manage", "project_closure.manage"],
    },
];

export default function Sidebar({ isOpen, onClose }) {
    const permissions = usePage().props.auth?.permissions ?? [];
    const items = adminFlowItems.filter((item) =>
        item.permissions.some((permission) => permissions.includes(permission))
    );

    return (
        <aside
            className={`fixed top-0 left-0 z-[99] h-full w-[288px] bg-white text-slate-900 shadow-md transition-transform duration-300 dark:bg-[#03011C] dark:text-white ${
                isOpen ? "translate-x-0" : "-translate-x-full"
            }`}
        >
            <div className="border-b border-gray-200 p-4 dark:border-b-[#5146e64a]">
                <div className="block dark:hidden">
                    <img
                        className="max-w-[100px] sm:max-w-[120px]"
                        src="/images/logo.png"
                        alt="Logo"
                    />
                </div>
                <div className="hidden dark:block">
                    <img
                        className="max-w-[100px] sm:max-w-[120px]"
                        src="/images/logo-dark.png"
                        alt="Logo"
                    />
                </div>

                <button
                    onClick={onClose}
                    className="absolute right-[5px] top-[16px] flex h-[38px] w-[48px] items-center justify-center rounded-[8px] border border-[#0000001A] bg-white text-[#000] transition focus:outline-none dark:border-[#61CC681A] dark:bg-[#61CC681A] dark:text-[#fff] md:flex xl:hidden"
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
                </button>
            </div>

            <div className="border-b border-gray-200 px-4 py-4 dark:border-b-[#5146e64a]">
                <p className="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600 dark:text-indigo-300">
                    Construction ERP
                </p>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Admin workspace focused only on assigned project flow.
                </p>
            </div>

            <nav className="px-[5px] py-[8px]">
                {items.map((item) => {
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
                                        ? "bg-[#4F46E5]/10 text-[#4F46E5]"
                                        : "text-[#727272] hover:bg-[#4F46E5]/10 hover:text-[#4F46E5]"
                                }`}
                            >
                                <span
                                    className={`h-2.5 w-2.5 rounded-full ${
                                        isActive ? "bg-[#4F46E5]" : "bg-indigo-400"
                                    }`}
                                />
                                {item.label}
                            </Link>
                        </div>
                    );
                })}
            </nav>
        </aside>
    );
}
