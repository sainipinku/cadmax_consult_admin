import { useAlerts } from "@/Components/Alerts";
import ApplicationLogo from "@/Components/ApplicationLogo";
import NavLink from "@/Components/NavLink";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import { Link, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Toaster } from "react-hot-toast";
import { IoMoon } from "react-icons/io5";
import { FaSun } from "react-icons/fa6";
import { FaBell, FaProjectDiagram, FaCity, FaHardHat, FaFileInvoiceDollar } from "react-icons/fa";
import { IoSettings } from "react-icons/io5";
import { SettingsProvider } from "@/Components/SettingsProvider";
import Sidebar from "./Sidebar";
import EmulationBanner from "@/Components/EmulationBanner";
import { route } from "ziggy-js";

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

function isValidationError(error) {
    return typeof error == "object" && error !== null;
}

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props;
    const authUser = user.auth?.user;
    const guard = user.auth?.guard;
    const isImpersonating = Boolean(user.is_impersonating || user.auth?.is_impersonating);
    const isSuperAdmin = 
        guard === "superadmin" ||
        authUser?.email === "superadmin@gmail.com" ||
        Boolean(authUser?.is_super_admin) || 
        authUser?.slug === "super-admin" || 
        authUser?.slug === "super_admin" ||
        (Array.isArray(authUser?.assigned_roles) && (authUser.assigned_roles.includes("super_admin") || authUser.assigned_roles.includes("superadmin"))) ||
        (Array.isArray(authUser?.roles) && (authUser.roles.includes("super_admin") || authUser.roles.includes("superadmin")));

    const isAdminUser = 
        isSuperAdmin ||
        Boolean(authUser?.is_admin) || 
        authUser?.slug === "admin" || 
        (Array.isArray(authUser?.assigned_roles) && (authUser.assigned_roles.includes("admin") || authUser.assigned_roles.includes("project_admin"))) ||
        (Array.isArray(authUser?.roles) && (authUser.roles.includes("admin") || authUser.roles.includes("project_admin") || authUser.roles.includes(1) || authUser.roles.includes("1")));

    const userPermissions = user.auth?.permissions || user.auth?.construction_permissions || user.permissions || [];

    const hasPerm = (itemPerms) => {
        if (isSuperAdmin || isAdminUser) return true;
        if (!itemPerms || itemPerms.length === 0) return true;
        if (userPermissions.length === 0) return true;
        return itemPerms.some((permSlug) => {
            if (userPermissions.includes(permSlug)) return true;
            const prefix = permSlug.split('.')[0];
            return userPermissions.some((p) => typeof p === 'string' && (p === permSlug || p.startsWith(prefix + '.')));
        });
    };

    const quickPills = [
        {
            href: route("super.construction.projects.index"),
            active: route().current("super.construction.projects.*"),
            icon: <FaProjectDiagram size={14} />,
            label: "Projects",
            permissions: ["project.manage", "project.view"],
        },
        {
            href: route("super.construction.clients.index"),
            active: route().current("super.construction.clients.*"),
            icon: <FaCity size={14} />,
            label: "Clients",
            permissions: ["client.manage", "client.view"],
        },
        {
            href: route("super.construction.execution.index"),
            active: route().current("super.construction.execution.*"),
            icon: <FaHardHat size={14} />,
            label: "Execution",
            permissions: ["execution.manage", "execution.view", "execution_task.manage", "execution.task.view", "dpr.review", "attendance.review"],
        },
        {
            href: route("super.construction.billing.index"),
            active: route().current("super.construction.billing.*"),
            icon: <FaFileInvoiceDollar size={14} />,
            label: "Billing",
            permissions: ["billing.manage", "billing.view", "billing_invoice.manage", "billing_payment.manage"],
        },
    ].filter((item) => hasPerm(item.permissions));

    const { successAlert, errorAlert, warningAlert, infoAlert } = useAlerts();

    const { flash, errors, messages } = usePage().props;

    useEffect(() => {
        if (flash?.success) successAlert(flash.success);
        if (flash?.error && !isValidationError(flash.error))
            errorAlert(flash.error);
        if (flash?.warning) warningAlert(flash.warning);
        if (flash?.info) infoAlert(flash.info);
        if (messages?.envelopes?.length > 0) {
            messages.envelopes.forEach(({ type, message }) => {
                switch (type) {
                    case "success":
                        successAlert(message);
                        break;
                    case "error":
                        if (!isValidationError(message)) errorAlert(message);
                        break;
                    case "warning":
                        warningAlert(message);
                        break;
                    case "info":
                        infoAlert(message);
                        break;
                    default:
                        console.warn("Unknown message type:", type);
                }
            });
        }
    }, [messages, flash, errors]);

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

    // Dropdown
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const dropdownRef = useRef(null);
    // Close dropdown on outside click
    useEffect(() => {
        function handleClickOutside(event) {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(event.target)
            ) {
                setDropdownOpen(false);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () =>
            document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    // Sidebar
    const [sidebarOpen, setSidebarOpen] = useState(false);

    // Open sidebar by default on large screens and keep it responsive to resizes
    useEffect(() => {
        if (typeof window === "undefined") return;
        const setFromWidth = () => setSidebarOpen(window.innerWidth >= 1280);
        setFromWidth();
        window.addEventListener("resize", setFromWidth);
        return () => window.removeEventListener("resize", setFromWidth);
    }, []);

    const toggleSidebar = () => setSidebarOpen((prev) => !prev);
    const closeSidebar = () => setSidebarOpen(false);
    const [settings] = useState(null);

    const [bellOpen, setBellOpen] = useState(false);
    const [notificationsLoading, setNotificationsLoading] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const [notifications, setNotifications] = useState([]);

    const formatRelativeTime = (value) => {
        if (!value) return "";

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        const diffInSeconds = Math.floor((date.getTime() - Date.now()) / 1000);
        const rtf = new Intl.RelativeTimeFormat("en", { numeric: "auto" });

        const units = [
            { unit: "year", seconds: 60 * 60 * 24 * 365 },
            { unit: "month", seconds: 60 * 60 * 24 * 30 },
            { unit: "week", seconds: 60 * 60 * 24 * 7 },
            { unit: "day", seconds: 60 * 60 * 24 },
            { unit: "hour", seconds: 60 * 60 },
            { unit: "minute", seconds: 60 },
        ];

        for (const { unit, seconds } of units) {
            if (Math.abs(diffInSeconds) >= seconds) {
                return rtf.format(
                    Math.round(diffInSeconds / seconds),
                    unit
                );
            }
        }

        return rtf.format(diffInSeconds, "second");
    };

    const superPrefix = (() => {
        if (typeof window === "undefined") return "";
        const path = window.location.pathname || "";
        const idx = path.indexOf("/super/");
        return idx >= 0 ? path.slice(0, idx) : "";
    })();

    const withSuperPrefix = (path) => `${superPrefix}${path}`;

    const getCsrfToken = () => {
        const token = document
            ?.querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        return token || "";
    };

    const fetchUnreadCount = async () => {
        try {
            const res = await fetch(withSuperPrefix("/super/notifications/api/unread-count"), {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            const payload = await res.json();
            if (payload?.success) {
                setUnreadCount(Number(payload.unread ?? 0));
            }
        } catch (e) {}
    };

    const fetchNotifications = async () => {
        setNotificationsLoading(true);
        try {
            const res = await fetch(
                withSuperPrefix("/super/notifications/api/list") + "?per_page=10",
                {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                }
            );
            const payload = await res.json();
            if (payload?.success) {
                const page = payload.data;
                const items = Array.isArray(page?.data) ? page.data : [];
                setNotifications(items);
                setUnreadCount(Number(payload.unread ?? 0));
            }
        } catch (e) {
            setNotifications([]);
        } finally {
            setNotificationsLoading(false);
        }
    };

    const markNotificationRead = async (notificationUuid) => {
        try {
            await fetch(withSuperPrefix(`/super/notifications/api/${notificationUuid}/read`), {
                method: "PATCH",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
            });
        } catch (e) {}
    };

    const markAllRead = async () => {
        try {
            const res = await fetch(withSuperPrefix("/super/notifications/api/read-all"), {
                method: "PATCH",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
            });
            const payload = await res.json();
            if (payload?.success) {
                setUnreadCount(Number(payload.unread ?? 0));
                setNotifications((prev) =>
                    prev.map((n) => ({
                        ...n,
                        status: "read",
                        viewed_at: n.viewed_at ?? new Date().toISOString(),
                    }))
                );
            }
        } catch (e) {}
    };

    const notificationText = (n) => {
        const title = n?.job?.title || n?.data?.title || n?.data?.job_title || "Job";
        switch (n?.type) {
            case "job_created":
                return `New job created: ${title}`;
            case "job_resubmitted":
                return `Job resubmitted: ${title}`;
            case "job_pending":
                return `Job pending: ${title}`;
            default:
                return title;
        }
    };

    useEffect(() => {
        fetchUnreadCount();
        const id = setInterval(() => fetchUnreadCount(), 30000);
        return () => clearInterval(id);
    }, []);

    useEffect(() => {
        if (bellOpen) {
            fetchNotifications();
        }
    }, [bellOpen]);

    return (
        <>
            <Sidebar isOpen={sidebarOpen} onClose={closeSidebar} />
            <SettingsProvider>
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
                                            className="flex items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[8px] transition text-[currentColor] dark:text-[currentColor] focus:outline-none hover:bg-gray-100 dark:hover:bg-[#61CC6820]"
                                            aria-label="Toggle sidebar"
                                        >
                                            {sidebarOpen ? (
                                                <svg
                                                    className="w-6 h-6"
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
                                            ) : (
                                                <svg
                                                    className="w-6 h-6"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={2}
                                                        d="M4 6h16M4 12h16M4 18h16"
                                                    />
                                                </svg>
                                            )}
                                        </button>

                                        {/* <div className="shrink-0 items-center hidden sm:flex">
                                            <Link href={route("super.dashboard")}>
                                                <ApplicationLogo
                                                    props={settings}
                                                    className="block w-auto h-8 fill-current"
                                                />
                                            </Link>
                                        </div> */}
                                    </div>

                                    {/* MIDDLE SECTION: QuickPills */}
                                    {quickPills.length > 0 && (
                                        <div className="hidden lg:flex items-center gap-2 mx-2 border-l border-gray-200 dark:border-gray-700 pl-4 min-w-0">
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

                                    {/* RIGHT SECTION: Actions + EmulationBanner + Avatar */}
                                    <div className="flex items-center gap-2 shrink-0">
                                        <EmulationBanner />
                                        {hasPerm(["project.manage", "project.create"]) && (
                                            <div className="hidden sm:flex items-center gap-2 mr-2">
                                                <Link
                                                    href={route("super.construction.projects.index")}
                                                    className="inline-flex items-center gap-2 rounded-[10px] bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-[13px] font-medium text-white shadow-sm hover:from-indigo-500 hover:to-violet-500 transition"
                                                >
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                                    New Project
                                                </Link>
                                            </div>
                                        )}

                                        <DropdownMenu open={bellOpen} onOpenChange={setBellOpen}>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="relative flex shrink-0 items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[10px] transition text-[currentColor] dark:text-[currentColor] hover:bg-gray-50 dark:hover:bg-[#61CC6820]"
                                                >
                                                    <FaBell size={17} />
                                                    {unreadCount > 0 && (
                                                        <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] leading-[18px] text-center font-semibold shadow-sm">
                                                            {unreadCount > 99 ? "99+" : unreadCount}
                                                        </span>
                                                    )}
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-[360px] p-0">
                                                <DropdownMenuLabel className="flex items-center justify-between">
                                                    <span>Notifications</span>
                                                    <button
                                                        type="button"
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            e.stopPropagation();
                                                            markAllRead();
                                                        }}
                                                        className="text-xs text-blue-600 hover:underline"
                                                    >
                                                        Mark all read
                                                    </button>
                                                </DropdownMenuLabel>
                                                <DropdownMenuSeparator />

                                                {notificationsLoading ? (
                                                    <div className="p-4 text-sm text-gray-500">Loading...</div>
                                                ) : notifications.length === 0 ? (
                                                    <div className="p-4 text-sm text-gray-500">
                                                        No notifications
                                                    </div>
                                                ) : (
                                                    <div className="max-h-[420px] overflow-auto">
                                                        {notifications.map((n) => (
                                                            <DropdownMenuItem
                                                                key={n.uuid}
                                                                className="flex flex-col items-start gap-1 py-3 cursor-pointer"
                                                                onSelect={async (e) => {
                                                                    e.preventDefault();
                                                                    await markNotificationRead(n.uuid);
                                                                    setUnreadCount((prev) =>
                                                                        Math.max(
                                                                            0,
                                                                            prev -
                                                                                (n.status === "unread" &&
                                                                                !n.viewed_at
                                                                                    ? 1
                                                                                    : 0)
                                                                        )
                                                                    );
                                                                    setNotifications((prev) =>
                                                                        prev.map((x) =>
                                                                            x.uuid === n.uuid
                                                                                ? {
                                                                                      ...x,
                                                                                      status: "read",
                                                                                      viewed_at:
                                                                                          x.viewed_at ??
                                                                                          new Date().toISOString(),
                                                                                  }
                                                                                : x
                                                                        )
                                                                    );
                                                                     window.location.href = withSuperPrefix(
                                                                         "/super/job-requests"
                                                                     );
                                                                }}
                                                            >
                                                                <div className="w-full flex items-start justify-between gap-2">
                                                                    <div
                                                                        className={
                                                                            "text-sm " +
                                                                            (n.status === "unread" &&
                                                                            !n.viewed_at
                                                                                ? "font-semibold"
                                                                                : "font-normal")
                                                                        }
                                                                    >
                                                                        {notificationText(n)}
                                                                    </div>
                                                                    {n.status === "unread" && !n.viewed_at && (
                                                                        <span className="mt-[6px] w-2 h-2 rounded-full bg-blue-600" />
                                                                    )}
                                                                </div>
                                                                <div className="text-xs text-gray-500">
                                                                    {formatRelativeTime(n.created_at)}
                                                                </div>
                                                            </DropdownMenuItem>
                                                        ))}
                                                    </div>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>

                                        <button
                                            onClick={() =>
                                                setDarkMode(!darkMode)
                                            }
                                            className="flex shrink-0 items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[10px] transition text-[currentColor] dark:text-[currentColor] hover:bg-gray-50 dark:hover:bg-[#61CC6820]"
                                            aria-label="Toggle theme"
                                        >
                                            {darkMode ? (
                                                <FaSun
                                                    size={17}
                                                    className="text-yellow-300"
                                                />
                                            ) : (
                                                <IoMoon size={17} className="text-slate-600" />
                                            )}
                                        </button>

                                        <Link
                                            href={route("super.settings.index")}
                                            className="flex shrink-0 items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[10px] transition text-slate-700 dark:text-[currentColor] hover:bg-gray-50 dark:hover:bg-[#61CC6820]"
                                            aria-label="Settings"
                                        >
                                            <IoSettings size={18} />
                                        </Link>

                                        <div
                                            className="relative"
                                            ref={dropdownRef}
                                        >
                                            <button
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setDropdownOpen(
                                                        !dropdownOpen
                                                    );
                                                }}
                                                className="w-[40px] h-[40px] rounded-full bg-none p-0 focus:outline-none overflow-hidden border-2 border-indigo-100 dark:border-indigo-900 hover:border-indigo-300 dark:hover:border-indigo-700 transition shrink-0"
                                            >
                                                <img
                                                    src={
                                                        user?.auth?.user
                                                            ?.profile_photo_url ||
                                                        user?.auth
                                                            ?.profile_photo_url ||
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
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-slate-800 dark:to-slate-800">
                                                        <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                            {user?.auth?.user?.name || "Super Admin"}
                                                        </p>
                                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                            {user?.auth?.user?.email || user?.email || "admin@cadmax.in"}
                                                        </p>
                                                    </div>
                                                    <ul className="py-1">
                                                        <li>
                                                            <NavLink
                                                                href={route(
                                                                    "super.profile"
                                                                )}
                                                                method="get"
                                                                as="button"
                                                                className="block w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                                            >
                                                                Profile Settings
                                                            </NavLink>
                                                        </li>
                                                        <li>
                                                            <NavLink
                                                                href={route(
                                                                    "super.settings.index"
                                                                )}
                                                                method="get"
                                                                as="button"
                                                                className="block w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                                            >
                                                                System Settings
                                                            </NavLink>
                                                        </li>
                                                        <li className="border-t border-gray-100 dark:border-gray-700 mt-1 pt-1">
                                                            <NavLink
                                                                href={route(
                                                                    "super.logout"
                                                                )}
                                                                method="post"
                                                                as="button"
                                                                className="block w-full text-left px-4 py-2.5 text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20"
                                                            >
                                                                Sign Out
                                                            </NavLink>
                                                        </li>
                                                    </ul>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Mobile menu */}
                            <div className="hidden sm:hidden border-t border-gray-200 dark:border-gray-700">
                                <div className="space-y-1 pb-4 pt-3 px-3">
                                    <ResponsiveNavLink href={route("super.dashboard")} active={route().current("super.dashboard") || route().current("super.construction.dashboard")}>
                                        Control Tower
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route("super.construction.clients.index")} active={route().current("super.construction.clients.*")}>
                                        Client Registration
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route("super.construction.projects.index")} active={route().current("super.construction.projects.*")}>
                                        Projects & Budget
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route("super.construction.survey.index")} active={route().current("super.construction.survey.*")}>
                                        Survey Planning
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route("super.construction.drafting.index")} active={route().current("super.construction.drafting.*")}>
                                        Drawing Approval
                                    </ResponsiveNavLink>
                                    {/* <ResponsiveNavLink href={route("super.construction.execution.index")} active={route().current("super.construction.execution.*")}>
                                        Construction Execution
                                    </ResponsiveNavLink> */}
                                    {/* <ResponsiveNavLink href={route("super.construction.materials.index")} active={route().current("super.construction.materials.*")}>
                                        Material Management
                                    </ResponsiveNavLink> */}
                                    <ResponsiveNavLink href={route("super.construction.vehicles.index")} active={route().current("super.construction.vehicles.*")}>
                                        Vehicle Tracking
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route("super.construction.equipment.index")} active={route().current("super.construction.equipment.*")}>
                                        Equipment Allocation
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route("super.construction.billing.index")} active={route().current("super.construction.billing.*")}>
                                        Accounts & Billing
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route("super.construction.handover.index")} active={route().current("super.construction.handover.*")}>
                                        Handover & Closure
                                    </ResponsiveNavLink>
                                </div>
                            </div>
                        </div>
                    </nav>

                    {header && (
                        <header className="bg-white shadow dark:bg-gray-800">
                            <div className="mx-auto max-w-full px-4 py-6 sm:px-6 lg:px-8">
                                {header}
                            </div>
                        </header>
                    )}

                    <main className="pt-[72px] px-1 sm:px-2 lg:px-3 pb-8">{children}</main>

                    <Toaster
                        position="top-right"
                        reverseOrder={false}
                        gutter={8}
                    />
                </div>
            </SettingsProvider>
        </>
    );
}

function FlowNavLink({ href, active, children }) {
    return (
        <NavLink
            className={`px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 ${
                active
                    ? "bg-blue-600 text-white shadow-md"
                    : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
            }`}
            href={href}
            active={active}
        >
            {children}
        </NavLink>
    );
}

function QuickPill({ href, active, icon, children }) {
    return (
        <Link
            href={href}
            className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-[13px] font-medium transition-all duration-200 ${
                active
                    ? "bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800"
                    : "text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
            }`}
        >
            {icon}
            <span>{children}</span>
        </Link>
    );
}
