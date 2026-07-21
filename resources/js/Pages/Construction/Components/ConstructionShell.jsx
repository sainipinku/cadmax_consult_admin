import { Head, Link, usePage } from "@inertiajs/react";
import SuperAdminLayout from "@/Pages/SuperAdmin/Layouts/AuthenticatedLayout";
import AdminLayout from "@/Pages/Admin/Layouts/AuthenticatedLayout";
import MemberLayout from "@/Pages/Member/Layouts/AuthenticatedLayout";

const variantConfig = {
    super: {
        layout: SuperAdminLayout,
        items: [
            { label: "Control Tower", href: route("super.construction.dashboard"), active: "super.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Company Setup", href: route("super.construction.companies.index"), active: "super.construction.companies.*", permissions: ["company.manage"] },
            { label: "Client Registration", href: route("super.construction.clients.index"), active: "super.construction.clients.*", permissions: ["client.manage"] },
            { label: "Projects & Budget", href: route("super.construction.projects.index"), active: "super.construction.projects.*", permissions: ["project.manage"] },
            { label: "Survey Planning", href: route("super.construction.survey.index"), active: "super.construction.survey.*", permissions: ["survey_plan.manage", "survey_submission.review"] },
            { label: "Drawing Approval", href: route("super.construction.drafting.index"), active: "super.construction.drafting.*", permissions: ["drafting.manage", "drawing_approval.manage"] },
            { label: "Site Execution", href: route("super.construction.execution.index"), active: "super.construction.execution.*", permissions: ["execution.manage", "execution_task.manage", "dpr.review", "attendance.review"] },
            { label: "Material Management", href: route("super.construction.materials.index"), active: "super.construction.materials.*", permissions: ["material.manage", "purchase_request.manage", "purchase_order.manage", "material_receipt.manage", "material_issue.manage", "material_stock.manage"] },
            { label: "Vehicle Tracking", href: route("super.construction.vehicles.index"), active: "super.construction.vehicles.*", permissions: ["vehicle.manage", "vehicle_assignment.manage", "vehicle_tracking.manage"] },
            { label: "Equipment Allocation", href: route("super.construction.equipment.index"), active: "super.construction.equipment.*", permissions: ["equipment.manage", "equipment_allocation.manage", "equipment_usage.manage"] },
            { label: "Accounts & Billing", href: route("super.construction.billing.index"), active: "super.construction.billing.*", permissions: ["billing_invoice.manage", "billing_payment.manage"] },
            { label: "Handover & Closure", href: route("super.construction.handover.index"), active: "super.construction.handover.*", permissions: ["handover.manage", "project_closure.manage"] },
        ],
    },
    admin: {
        layout: AdminLayout,
        items: [
            { label: "Project Dashboard", href: route("admin.construction.dashboard"), active: "admin.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Assigned Projects", href: route("admin.construction.projects.index"), active: "admin.construction.projects.*", permissions: ["project.manage"] },
            { label: "Survey Workflow", href: route("admin.construction.survey.index"), active: "admin.construction.survey.*", permissions: ["survey_plan.manage", "survey_submission.review"] },
            { label: "Drawing Approval", href: route("admin.construction.drafting.index"), active: "admin.construction.drafting.*", permissions: ["drafting.manage", "drawing_approval.manage"] },
            { label: "Site Execution", href: route("admin.construction.execution.index"), active: "admin.construction.execution.*", permissions: ["execution.manage", "execution_task.manage", "dpr.review", "attendance.review"] },
            { label: "Material Management", href: route("admin.construction.materials.index"), active: "admin.construction.materials.*", permissions: ["material.manage", "purchase_request.manage", "purchase_order.manage", "material_receipt.manage", "material_issue.manage", "material_stock.manage"] },
            { label: "Vehicle Tracking", href: route("admin.construction.vehicles.index"), active: "admin.construction.vehicles.*", permissions: ["vehicle.manage", "vehicle_assignment.manage", "vehicle_tracking.manage"] },
            { label: "Equipment Allocation", href: route("admin.construction.equipment.index"), active: "admin.construction.equipment.*", permissions: ["equipment.manage", "equipment_allocation.manage", "equipment_usage.manage"] },
            { label: "Accounts & Billing", href: route("admin.construction.billing.index"), active: "admin.construction.billing.*", permissions: ["billing_invoice.manage", "billing_payment.manage"] },
            { label: "Handover & Closure", href: route("admin.construction.handover.index"), active: "admin.construction.handover.*", permissions: ["handover.manage", "project_closure.manage"] },
        ],
    },
    member: {
        layout: MemberLayout,
        items: [
            { label: "Project Dashboard", href: route("member.dashboard"), active: "member.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Assigned Projects", href: route("member.construction.projects.index"), active: "member.construction.projects.*", permissions: ["dashboard.view"] },
            { label: "Site Execution", href: route("member.construction.execution.index"), active: "member.construction.execution.*", permissions: ["execution_task.manage", "dpr.manage", "attendance.manage"] },
            { label: "Material Management", href: route("member.construction.materials.index"), active: "member.construction.materials.*", permissions: ["material_issue.manage", "material_stock.manage"] },
            { label: "Vehicle Tracking", href: route("member.construction.vehicles.index"), active: "member.construction.vehicles.*", permissions: ["vehicle_tracking.manage"] },
            { label: "Equipment Allocation", href: route("member.construction.equipment.index"), active: "member.construction.equipment.*", permissions: ["equipment_allocation.manage", "equipment_usage.manage"] },
            { label: "Handover & Closure", href: route("member.construction.handover.index"), active: "member.construction.handover.*", permissions: ["handover.manage"] },
        ],
    },
};

export default function ConstructionShell({
    title,
    description,
    variant = "super",
    children,
}) {
    const config = variantConfig[variant];
    const Layout = config.layout;
    const permissions = usePage().props.auth?.permissions ?? [];
    const navItems = config.items.filter((item) =>
        !item.permissions || item.permissions.some((permission) => permissions.includes(permission))
    );

    return (
        <Layout>
            <Head title={title} />
            <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">
                                Construction ERP
                            </p>
                            <h1 className="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
                                {title}
                            </h1>
                            {description ? (
                                <p className="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                                    {description}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {navItems.map((item) => {
                                const active = route().current(item.active);

                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={`rounded-full px-4 py-2 text-sm font-medium transition ${
                                            active
                                                ? "bg-indigo-600 text-white"
                                                : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </div>
                {children}
            </div>
        </Layout>
    );
}
