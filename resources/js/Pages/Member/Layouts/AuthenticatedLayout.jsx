import { useAlerts } from "@/Components/Alerts";
import ApplicationLogo from "@/Components/ApplicationLogo";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import { Link, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Toaster } from "react-hot-toast";
import { IoMoon } from "react-icons/io5";
import { FaSun, FaBell, FaProjectDiagram, FaHardHat, FaTruck, FaBoxes } from "react-icons/fa";
import { IoSettings } from "react-icons/io5";
import { GiHamburgerMenu } from "react-icons/gi";
import { route } from "ziggy-js";
import Sidebar from "./Sidebar";
import EmulationBanner from "@/Components/EmulationBanner";

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props;
    const auth = user.auth;
    const authUser = auth?.user;
    const guard = auth?.guard;
    const isSuperAdmin = guard === 'superadmin' || authUser?.slug === 'super-admin' || authUser?.is_super_admin;
    const userPermissions = auth?.permissions || auth?.construction_permissions || user.permissions || [];

    const hasPerm = (itemPerms) => {
        if (isSuperAdmin) return true;
        if (!itemPerms || itemPerms.length === 0) return true;
        return itemPerms.some((permSlug) => {
            if (userPermissions.includes(permSlug)) return true;
            const prefix = permSlug.split('.')[0];
            return userPermissions.some((p) => typeof p === 'string' && (p === permSlug || p.startsWith(prefix + '.')));
        });
    };

    const quickPills = [
        {
            href: route("member.construction.projects.index"),
            active: route().current("member.construction.projects.*"),
            icon: <FaProjectDiagram size={14} />,
            label: "Projects",
            permissions: ["project.manage", "project.view"],
        },
        {
            href: route("member.construction.execution.index"),
            active: route().current("member.construction.execution.*"),
            icon: <FaHardHat size={14} />,
            label: "Execution",
            permissions: ["execution.manage", "execution.view", "execution_task.manage", "execution.task.view", "dpr.manage", "dpr.view", "attendance.manage"],
        },
        {
            href: route("member.construction.materials.index"),
            active: route().current("member.construction.materials.*"),
            icon: <FaBoxes size={14} />,
            label: "Materials",
            permissions: ["material.manage", "material.view", "material_requisition.manage", "material_requisition.view", "material_issue.manage", "material_stock.manage"],
        },
        {
            href: route("member.construction.vehicles.index"),
            active: route().current("member.construction.vehicles.*"),
            icon: <FaTruck size={14} />,
            label: "Vehicles",
            permissions: ["vehicle.manage", "vehicle.view", "vehicle_tracking.manage", "vehicle_tracking.view"],
        },
    ].filter((item) => hasPerm(item.permissions));

    const { successAlert, errorAlert, warningAlert, infoAlert } = useAlerts();
    const { flash, errors, messages } = usePage().props;

    useEffect(() => {
        if (errors) {
            Object.entries(errors).forEach(([, value]) => errorAlert(value));
        }
        if (flash?.success) successAlert(flash.success);
        if (flash?.error) errorAlert(flash.error);
        if (flash?.warning) warningAlert(flash.warning);
        if (flash?.info) infoAlert(flash.info);
        if (messages?.envelopes?.length > 0) {
            messages.envelopes.forEach(({ type, message }) => {
                switch (type) {
                    case "success":
                        successAlert(message);
                        break;
                    case "error":
                        errorAlert(message);
                        break;
                    case "warning":
                        warningAlert(message);
                        break;
                    case "info":
                        infoAlert(message);
                        break;
                }
            });
        }
    }, [messages, flash, errors]);

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [darkMode, setDarkMode] = useState(
        () =>
            localStorage.theme == "dark" ||
            (!("theme" in localStorage) &&
                window.matchMedia("(prefers-color-scheme: dark)").matches)
    );

    useEffect(() => {
        const root = window.document.documentElement;
        if (darkMode) {
            root.classList.add("dark");
            localStorage.setItem("theme", "dark");
        } else {
            root.classList.remove("dark");
            localStorage.setItem("theme", "light");
        }
    }, [darkMode]);

    const [sidebarOpen, setSidebarOpen] = useState(false);
    useEffect(() => {
        if (typeof window === "undefined") return;
        const setFromWidth = () => setSidebarOpen(window.innerWidth >= 1280);
        setFromWidth();
        window.addEventListener("resize", setFromWidth);
        return () => window.removeEventListener("resize", setFromWidth);
    }, []);

    const toggleSidebar = () => setSidebarOpen((prev) => !prev);
    const closeSidebar = () => setSidebarOpen(false);

    const [dropdownOpen, setDropdownOpen] = useState(false);
    const dropdownRef = useRef(null);
    useEffect(() => {
        function handleClickOutside(event) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setDropdownOpen(false);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const logoutCsrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

    return (
        <>
            <Sidebar isOpen={sidebarOpen} onClose={closeSidebar} />
            <div
                className={`min-h-screen mainbg transition-[padding] duration-300 ease-in-out ${
                    sidebarOpen ? "xl:pl-[288px]" : "xl:pl-0"
                }`}
            >
                <nav
                    className={`headerbg fixed top-0 z-50 py-[10px] px-[12px] print:hidden transition-[left,right] duration-300 ease-in-out ${
                        sidebarOpen
                            ? "left-0 right-0 xl:left-[288px] xl:right-0"
                            : "left-0 right-0"
                    }`}
                >
                    <div className="navbg rounded-[12px] border border-[1px] borderbx shadow-sm">
                        <div className="mx-auto max-w-full px-[12px] py-0">
                            <div className="flex h-14 items-center justify-between gap-4">
                                {/* LEFT SECTION: Hamburger + Logo + Quick Nav */}
                                <div className="flex items-center gap-4 min-w-0 flex-1">
                                    <button
                                        onClick={toggleSidebar}
                                        className="flex shrink-0 items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[10px] transition text-[currentColor] dark:text-[currentColor] focus:outline-none hover:bg-gray-50 dark:hover:bg-[#61CC6820]"
                                        aria-label="Toggle sidebar"
                                    >
                                        <GiHamburgerMenu size={20} />
                                    </button>

                                    <div className="shrink-0 items-center hidden sm:flex">
                                        <Link href={route("member.dashboard")}>
                                            <ApplicationLogo props={null} className="block w-auto h-8 fill-current" />
                                        </Link>
                                    </div>

                                    {quickPills.length > 0 && (
                                        <div className="hidden lg:flex items-center gap-2 ml-2 border-l border-gray-200 dark:border-gray-700 pl-4 min-w-0">
                                            {quickPills.map((pill, idx) => (
                                                <QuickPill
                                                    key={idx}
                                                    href={pill.href}
                                                    active={pill.active}
                                                    icon={pill.icon}
                                                >
                                                    {pill.label}
                                                </QuickPill>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {/* RIGHT SECTION: Actions + Avatar */}
                                <div className="flex items-center gap-2 shrink-0">
                                    <EmulationBanner />
                                    {hasPerm(["dpr.manage", "dpr.view", "execution.manage", "execution.view"]) && (
                                        <div className="hidden sm:flex items-center gap-2 mr-2">
                                            <Link
                                                href={route("member.construction.execution.index")}
                                                className="inline-flex items-center gap-2 rounded-[10px] bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-[13px] font-medium text-white shadow-sm hover:from-emerald-500 hover:to-teal-500 transition"
                                            >
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                                    <path d="M9 11l3 3L22 4" />
                                                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                                                </svg>
                                                Today's DPR
                                            </Link>
                                        </div>
                                    )}

                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button
                                                type="button"
                                                className="relative flex shrink-0 items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[10px] transition text-[currentColor] dark:text-[currentColor] hover:bg-gray-50 dark:hover:bg-[#61CC6820]"
                                            >
                                                <FaBell size={17} />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="w-[320px] p-0">
                                            <DropdownMenuLabel>Notifications</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <div className="p-4 text-sm text-gray-500">No new notifications</div>
                                        </DropdownMenuContent>
                                    </DropdownMenu>

                                    <button
                                        onClick={() => setDarkMode(!darkMode)}
                                        className="flex shrink-0 items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[10px] transition text-[currentColor] dark:text-[currentColor] hover:bg-gray-50 dark:hover:bg-[#61CC6820]"
                                        aria-label="Toggle theme"
                                    >
                                        {darkMode ? (
                                            <FaSun size={17} className="text-yellow-300" />
                                        ) : (
                                            <IoMoon size={17} className="text-slate-600" />
                                        )}
                                    </button>

                                    <Link
                                        href={route("member.profile")}
                                        className="flex shrink-0 items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[10px] transition text-slate-700 dark:text-[currentColor] hover:bg-gray-50 dark:hover:bg-[#61CC6820]"
                                        aria-label="Settings"
                                    >
                                        <IoSettings size={18} />
                                    </Link>

                                    <div className="relative" ref={dropdownRef}>
                                        <button
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setDropdownOpen(!dropdownOpen);
                                            }}
                                            className="w-[40px] h-[40px] rounded-full bg-none p-0 focus:outline-none overflow-hidden border-2 border-emerald-100 dark:border-emerald-900 hover:border-emerald-300 dark:hover:border-emerald-700 transition shrink-0"
                                        >
                                            <img
                                                src={
                                                    user?.auth?.user?.profile_photo_url ||
                                                    user?.auth?.profile_photo_url ||
                                                    "/images/profileimg.png"
                                                }
                                                alt="User"
                                                className="w-full h-full object-cover rounded-full"
                                                onError={(e) => {
                                                    e.currentTarget.src = "/images/profileimg.png";
                                                }}
                                            />
                                        </button>

                                        {dropdownOpen && (
                                            <div
                                                className="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-50 overflow-hidden"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-slate-800 dark:to-slate-800">
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                        {user?.auth?.user?.name || "Site Member"}
                                                    </p>
                                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                        {user?.auth?.user?.email || user?.auth?.email || "member@cadmax.in"}
                                                    </p>
                                                </div>
                                                <ul className="py-1">
                                                    <li>
                                                        <Link
                                                            href={route("member.profile")}
                                                            className="block w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                                        >
                                                            Profile Settings
                                                        </Link>
                                                    </li>
                                                    <li className="border-t border-gray-100 dark:border-gray-700 mt-1 pt-1">
                                                        <Link
                                                            href={route("member.logout")}
                                                            method="post"
                                                            as="button"
                                                            className="block w-full text-left px-4 py-2.5 text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20"
                                                        >
                                                            Sign Out
                                                        </Link>
                                                    </li>
                                                </ul>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Mobile Nav */}
                        <div
                            className={
                                (showingNavigationDropdown ? "block" : "hidden") +
                                " sm:hidden border-t border-gray-200 dark:border-gray-700"
                            }
                        >
                            <div className="space-y-1 pb-4 pt-3 px-3">
                                <ResponsiveNavLink href={route("member.dashboard")} active={route().current("member.dashboard") || route().current("member.construction.dashboard")}>
                                    Project Dashboard
                                </ResponsiveNavLink>
                                <ResponsiveNavLink href={route("member.construction.projects.index")} active={route().current("member.construction.projects.*")}>
                                    Assigned Projects
                                </ResponsiveNavLink>
                                <ResponsiveNavLink href={route("member.construction.execution.index")} active={route().current("member.construction.execution.*")}>
                                    Site Execution
                                </ResponsiveNavLink>
                                {/* <ResponsiveNavLink href={route("member.construction.materials.index")} active={route().current("member.construction.materials.*")}>
                                    Material Management
                                </ResponsiveNavLink> */}
                                <ResponsiveNavLink href={route("member.construction.vehicles.index")} active={route().current("member.construction.vehicles.*")}>
                                    Vehicle Tracking
                                </ResponsiveNavLink>
                                <ResponsiveNavLink href={route("member.construction.equipment.index")} active={route().current("member.construction.equipment.*")}>
                                    Equipment Allocation
                                </ResponsiveNavLink>
                                <ResponsiveNavLink href={route("member.construction.handover.index")} active={route().current("member.construction.handover.*")}>
                                    Handover &amp; Closure
                                </ResponsiveNavLink>
                                <ResponsiveNavLink href={route("member.profile")} active={route().current("member.profile")}>
                                    My Profile
                                </ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </nav>

                {header && (
                    <header className="bg-white shadow dark:bg-gray-800">
                        <div className="mx-auto max-w-full px-4 py-6 sm:px-6 lg:px-8">{header}</div>
                    </header>
                )}

                <main className="pt-[72px] px-3 sm:px-6 lg:px-8 pb-8">{children}</main>
                <Toaster position="top-right" reverseOrder={false} gutter={8} />
            </div>
        </>
    );
}

function QuickPill({ href, active, icon, children }) {
    return (
        <Link
            href={href}
            className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-[13px] font-medium transition-all duration-200 ${
                active
                    ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800"
                    : "text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
            }`}
        >
            {icon}
            <span>{children}</span>
        </Link>
    );
}
