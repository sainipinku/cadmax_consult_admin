import { useEffect, useRef } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import gsap from "gsap";
import SuperAdminLayout from "@/Pages/SuperAdmin/Layouts/AuthenticatedLayout";
import AdminLayout from "@/Pages/Admin/Layouts/AuthenticatedLayout";
import MemberLayout from "@/Pages/Member/Layouts/AuthenticatedLayout";

const variantConfig = {
    super: {
        layout: SuperAdminLayout,
        items: [
            { label: "Control Tower", href: route("super.construction.dashboard"), active: "super.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Client Registration", href: route("super.construction.clients.index"), active: "super.construction.clients.*", permissions: ["client.manage", "client.view"] },
            { label: "Projects & Budget", href: route("super.construction.projects.index"), active: "super.construction.projects.*", permissions: ["project.manage", "project.view"] },
            { label: "Attendance Logs", href: route("super.construction.attendance.index"), active: "super.construction.attendance.*", permissions: ["attendance.view", "attendance.review", "attendance.details"] },
            { label: "Vehicle Tracking", href: route("super.construction.vehicles.index"), active: "super.construction.vehicles.*", permissions: ["vehicle.manage", "vehicle_assignment.manage", "vehicle_tracking.manage", "vehicle.view", "vehicle_tracking.view"] },
            { label: "Fuel History", href: route("super.construction.fuel.index"), active: "super.construction.fuel.*", permissions: ["fuel_entry.view", "fuel_entry.verify", "fuel_entry.details"] },
            { label: "Equipment Tracking", href: route("super.construction.equipment.index"), active: "super.construction.equipment.*", permissions: ["equipment_tracking.manage", "equipment_tracking.view", "equipment.manage", "equipment_allocation.manage", "equipment_usage.manage", "equipment.view"] },
            { label: "Accounts & Billing", href: route("super.construction.billing.index"), active: "super.construction.billing.*", permissions: ["billing.manage", "billing.view", "billing_invoice.manage", "billing_payment.manage"] },
            { label: "Handover & Closure", href: route("super.construction.handover.index"), active: "super.construction.handover.*", permissions: ["handover.manage", "project_closure.manage", "handover.view"] },
        ],
    },
    admin: {
        layout: AdminLayout,
        items: [
            { label: "Project Dashboard", href: route("admin.construction.dashboard"), active: "admin.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Assigned Projects", href: route("admin.construction.projects.index"), active: "admin.construction.projects.*", permissions: ["project.manage", "project.view"] },
            { label: "Attendance Logs", href: route("admin.construction.attendance.index"), active: "admin.construction.attendance.*", permissions: ["attendance.view", "attendance.review", "attendance.details"] },
            { label: "Vehicle Tracking", href: route("admin.construction.vehicles.index"), active: "admin.construction.vehicles.*", permissions: ["vehicle.manage", "vehicle_assignment.manage", "vehicle_tracking.manage", "vehicle.view", "vehicle_tracking.view"] },
            { label: "Fuel History", href: route("admin.construction.fuel.index"), active: "admin.construction.fuel.*", permissions: ["fuel_entry.view", "fuel_entry.verify", "fuel_entry.details"] },
            { label: "Equipment Tracking", href: route("admin.construction.equipment.index"), active: "admin.construction.equipment.*", permissions: ["equipment_tracking.manage", "equipment_tracking.view", "equipment.manage", "equipment_allocation.manage", "equipment_usage.manage", "equipment.view"] },
            { label: "Accounts & Billing", href: route("admin.construction.billing.index"), active: "admin.construction.billing.*", permissions: ["billing.manage", "billing.view", "billing_invoice.manage", "billing_payment.manage"] },
            { label: "Handover & Closure", href: route("admin.construction.handover.index"), active: "admin.construction.handover.*", permissions: ["handover.manage", "project_closure.manage", "handover.view"] },
        ],
    },
    member: {
        layout: MemberLayout,
        items: [
            { label: "Project Dashboard", href: route("member.dashboard"), active: "member.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Assigned Projects", href: route("member.construction.projects.index"), active: "member.construction.projects.*", permissions: ["dashboard.view", "project.manage", "project.view"] },
            { label: "Site Execution", href: route("member.construction.execution.index"), active: "member.construction.execution.*", permissions: ["execution_task.manage", "execution.task.view", "execution.manage", "execution.view", "dpr.manage", "attendance.manage"] },
            { label: "Vehicle Tracking", href: route("member.construction.vehicles.index"), active: "member.construction.vehicles.*", permissions: ["vehicle_tracking.manage", "vehicle.manage"] },
            { label: "Equipment Tracking", href: route("member.construction.equipment.index"), active: "member.construction.equipment.*", permissions: ["equipment_tracking.manage", "equipment_tracking.view", "equipment_allocation.manage", "equipment_usage.manage", "equipment.manage"] },
            { label: "Handover & Closure", href: route("member.construction.handover.index"), active: "member.construction.handover.*", permissions: ["handover.manage", "project_closure.manage"] },
        ],
    },
};

const permissionAliases = {
    "drawing_approval.manage": ["drafting.manage", "drafting.view", "document.manage", "drawing_approval.view"],
    "drafting.manage": ["drawing_approval.manage", "drawing_approval.view", "document.manage", "drafting.view"],
    "survey_plan.manage": ["survey.manage", "survey_submission.review", "survey_plan.view", "survey.view"],
    "survey.manage": ["survey_plan.manage", "survey.view", "survey_plan.view"],
    "vehicle_tracking.manage": ["vehicle.manage", "vehicle_assignment.manage", "vehicle_tracking.view"],
    "vehicle.manage": ["vehicle_tracking.manage", "vehicle.view"],
    "equipment_category.manage": ["equipment.manage", "equipment_category.view"],
    "equipment.manage": ["equipment_category.manage", "equipment_usage.manage", "equipment_allocation.manage", "equipment_tracking.manage", "equipment_tracking.view", "equipment.view"],
    "equipment_tracking.manage": ["equipment.manage", "equipment_allocation.manage", "equipment_usage.manage", "equipment_tracking.view"],
    "billing.manage": ["billing_invoice.manage", "billing_payment.manage", "billing.view"],
    "handover.manage": ["project_closure.manage", "handover.view"],
    "execution.manage": ["execution_task.manage", "execution.view", "execution.task.view"],
    "execution_task.manage": ["execution.manage", "execution.task.view", "execution.view"],
};

export default function ConstructionShell({
    title,
    description,
    variant = "super",
    children,
}) {
    const config = variantConfig[variant];
    const Layout = config.layout;
    const page = usePage();

    const user = page.props.auth?.user;
    const guard = page.props.auth?.guard;
    const isImpersonating = Boolean(page.props?.is_impersonating || page.props.auth?.is_impersonating);
    const isSuperAdmin = !isImpersonating && (
        guard === "superadmin" ||
        user?.email === "superadmin@gmail.com" ||
        Boolean(user?.is_super_admin) ||
        user?.slug === "super-admin" ||
        user?.slug === "super_admin" ||
        (Array.isArray(user?.assigned_roles) && (user.assigned_roles.includes("super_admin") || user.assigned_roles.includes("superadmin"))) ||
        (Array.isArray(user?.roles) && (user.roles.includes("super_admin") || user.roles.includes("superadmin")))
    );

    const permissions =
        variant === "member"
            ? page.props.permissions ?? page.props.auth?.permissions ?? page.props.auth?.construction_permissions ?? []
            : page.props.auth?.permissions ?? page.props.auth?.construction_permissions ?? page.props.permissions ?? [];

    const hasPerm = (itemPerms) => {
        if (isSuperAdmin) return true;
        if (!itemPerms || itemPerms.length === 0) return true;
        if (!Array.isArray(permissions) || permissions.length === 0) return false;

        return itemPerms.some((permSlug) => {
            const permsToCheck = [permSlug, ...(permissionAliases[permSlug] || [])];

            return permsToCheck.some((slug) => {
                if (permissions.includes(slug)) return true;
                const viewSlug = slug.endsWith(".manage") ? slug.replace(".manage", ".view") : slug;
                const createSlug = slug.endsWith(".manage") ? slug.replace(".manage", ".create") : slug;
                const editSlug = slug.endsWith(".manage") ? slug.replace(".manage", ".edit") : slug;
                const deleteSlug = slug.endsWith(".manage") ? slug.replace(".manage", ".delete") : slug;
                const manageSlug = slug.endsWith(".view") ? slug.replace(".view", ".manage") : slug;

                return (
                    permissions.includes(viewSlug) ||
                    permissions.includes(createSlug) ||
                    permissions.includes(editSlug) ||
                    permissions.includes(deleteSlug) ||
                    permissions.includes(manageSlug)
                );
            });
        });
    };

    const navItems = config.items.filter((item) => hasPerm(item.permissions));
    const shellHeaderRef = useRef(null);

    useEffect(() => {
        if (!shellHeaderRef.current) return;

        const ctx = gsap.context(() => {
            gsap.fromTo(
                shellHeaderRef.current,
                { opacity: 0, y: -8 },
                { opacity: 1, y: 0, duration: 0.35, ease: "power2.out", clearProps: "transform,opacity" }
            );

            const pills = shellHeaderRef.current.querySelectorAll(".shell-nav-pill");
            if (pills.length > 0) {
                gsap.fromTo(
                    pills,
                    { opacity: 0, y: 6, scale: 0.96 },
                    {
                        opacity: 1,
                        y: 0,
                        scale: 1,
                        duration: 0.28,
                        stagger: 0.022,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }
        }, shellHeaderRef);

        return () => ctx.revert();
    }, [title, variant]);

    const handlePillEnter = (e) => {
        gsap.to(e.currentTarget, { y: -1.5, duration: 0.16, ease: "power1.out", overwrite: "auto" });
    };

    const handlePillLeave = (e) => {
        gsap.to(e.currentTarget, { y: 0, duration: 0.16, ease: "power1.out", overwrite: "auto" });
    };

    return (
        <Layout>
            <Head title={title} />
            <div className="space-y-3.5 px-2 py-3 sm:px-3 lg:px-4">
                <div
                    ref={shellHeaderRef}
                    className="rounded-2xl border border-slate-200/90 bg-white/95 px-4 py-3 shadow-xs dark:border-slate-800 dark:bg-slate-950/95"
                >
                    <div className="flex flex-col gap-2.5 lg:flex-row lg:items-center lg:justify-between">
                        <div className="min-w-0 shrink-0">
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                                Construction ERP
                            </p>
                            <div className="mt-0.5 flex flex-wrap items-baseline gap-x-2.5 gap-y-0.5">
                                <h1 className="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                                    {title}
                                </h1>
                                {description ? (
                                    <p className="truncate max-w-xl text-xs text-slate-500 dark:text-slate-400">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                        {navItems.length > 0 && (
                            <div className="flex flex-wrap items-center gap-1.5">
                                {navItems.map((item) => {
                                    const active = route().current(item.active);

                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            onMouseEnter={handlePillEnter}
                                            onMouseLeave={handlePillLeave}
                                            className={`shell-nav-pill rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                                active
                                                    ? "bg-indigo-600 text-white shadow-xs shadow-indigo-600/25"
                                                    : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                            }`}
                                        >
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
                {children}
            </div>
        </Layout>
    );
}
