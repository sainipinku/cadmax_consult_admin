import { Link, useForm, router, usePage } from "@inertiajs/react";
import PendingCadApprovalAlertBanner from "@/Pages/Construction/Components/PendingCadApprovalAlertBanner";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import Modal from "@/Components/Modal";
import GoogleMapLocationSection from "@/Components/GoogleMapLocationSection.jsx";
import DeleteActionButton from "@/Components/DeleteActionButton";
import { useEffect, useMemo, useRef, useState } from "react";
import gsap from "gsap";
import {
    FaProjectDiagram,
    FaPlayCircle,
    FaCheckCircle,
    FaHourglassHalf,
    FaLayerGroup,
    FaSearch,
    FaRulerCombined,
    FaHandshake,
    FaFlagCheckered,
    FaPlus,
    FaEye,
    FaEdit,
    FaTrashAlt,
    FaEllipsisV,
} from "react-icons/fa";

const projectStatusFlow = [
    { value: "planning", label: "Planning", color: "indigo", icon: FaLayerGroup, description: "Setup, budget, team" },
    { value: "survey", label: "Survey", color: "sky", icon: FaSearch, description: "Site survey & GPS" },
    { value: "finishing", label: "Finishing", color: "fuchsia", icon: FaRulerCombined, description: "Paint, floor, fixtures" },
    { value: "handover", label: "Handover", color: "rose", icon: FaHandshake, description: "Inspection & handover" },
    { value: "completed", label: "Completed", color: "emerald", icon: FaFlagCheckered, description: "Project closed" },
];

function formatDateForInput(value) {
    if (!value) return "";
    const str = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return str;
    if (str.includes("T")) return str.split("T")[0];
    if (str.includes(" ")) return str.split(" ")[0];
    try {
        const d = new Date(str);
        if (!Number.isNaN(d.getTime())) {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, "0");
            const dd = String(d.getDate()).padStart(2, "0");
            return `${yyyy}-${mm}-${dd}`;
        }
    } catch {
        // fallback
    }
    return str;
}

export default function ProjectsIndex({ projects, companies, clients, user_company_id, next_project_code }) {
    const pageProps = usePage().props;
    const { auth } = pageProps;
    const isImpersonating = Boolean(pageProps.is_impersonating || auth?.is_impersonating);
    const pendingCadApprovals = Array.isArray(pageProps?.pending_assigned_cad_approvals)
        ? pageProps.pending_assigned_cad_approvals
        : [];
    const pendingCadSet = new Set(pendingCadApprovals.map((item) => Number(item.project_id)));
    const activeRole = auth?.active_role;
    const activeRoleSlug = String(activeRole?.slug || activeRole || "").toLowerCase();
    const isProjectAdmin = activeRoleSlug === "project_admin";
    const isRestrictedRole = Boolean(
        activeRoleSlug && 
        !['super_admin', 'superadmin', 'crm_super_admin', 'admin', 'project_admin'].includes(activeRoleSlug)
    );

    const isProjectOwner = !isImpersonating && !isRestrictedRole && Boolean(
        auth?.user?.is_project_owner == 1 ||
        auth?.user?.is_project_owner === true ||
        auth?.user?.id == 1 ||
        auth?.user?.id === '1' ||
        auth?.user?.email === 'superadmin@gmail.com' ||
        auth?.user?.role === 'project_owner' ||
        auth?.user?.slug === 'project-owner' ||
        (!auth?.user?.company_id && auth?.guard === "superadmin")
    );

    const isSuperAdmin = !isImpersonating && !isRestrictedRole && (
        isProjectOwner ||
        auth?.guard === "superadmin" ||
        auth?.user?.is_super_admin == 1 ||
        Boolean(auth?.user?.is_super_admin) ||
        auth?.user?.slug === "super-admin" ||
        auth?.user?.email === "superadmin@gmail.com" ||
        (Array.isArray(auth?.user?.roles) && (auth?.user?.roles.includes("super_admin") || auth?.user?.roles.includes("superadmin")))
    );

    const isCompanyAdmin = !isProjectOwner && !isSuperAdmin && (
        auth?.guard === "admin" || isProjectAdmin || activeRoleSlug === "admin"
    );
    const effectiveCompanyId = user_company_id ?? auth?.user?.company_id ?? null;
    const isCompanyLocked = Boolean(effectiveCompanyId);

    const isRootSuperAdmin = isProjectOwner || isSuperAdmin;
    const userPermissions = auth?.permissions || auth?.construction_permissions || pageProps?.permissions || [];
    const hasPerm = (target) => {
        if (isProjectOwner || isSuperAdmin) return true;
        if (!target) return true;
        const targets = Array.isArray(target) ? target : [target];
        return targets.some((slug) => userPermissions.includes(slug) || userPermissions.includes('*'));
    };

    const canCreateProject = isProjectAdmin || hasPerm("project.create");
    const canViewProject = hasPerm(["project.view", "project.manage"]);

    // Scoped project permissions:
    // 1. Company Admin: Strictly CANNOT edit or delete projects. Only sees View Details.
    // 2. Company Super Admin: Can edit/delete ONLY their own company's projects.
    // 3. Project Owner: Can edit/delete ANY project across all companies.
    const canEditProjectFor = (project) => {
        if (isCompanyAdmin) return false;
        if (isProjectOwner) return true;
        if (isSuperAdmin) {
            if (!effectiveCompanyId) return true;
            return !project?.company_id || Number(project.company_id) === Number(effectiveCompanyId);
        }
        return false;
    };

    const canDeleteProjectFor = (project) => {
        if (isCompanyAdmin) return false;
        if (isProjectOwner) return true;
        if (isSuperAdmin) {
            if (!effectiveCompanyId) return true;
            return !project?.company_id || Number(project.company_id) === Number(effectiveCompanyId);
        }
        return false;
    };

    const [nextCode, setNextCode] = useState(next_project_code || "PRJ-00001");
    const [isCodeManuallyEdited, setIsCodeManuallyEdited] = useState(false);

    const initialCompanyId = effectiveCompanyId
        ? String(effectiveCompanyId)
        : (companies[0]?.id ? String(companies[0].id) : "");

    const initialClients = effectiveCompanyId
        ? clients.filter((c) => String(c.company_id) === String(effectiveCompanyId))
        : (companies[0]?.id ? clients.filter((c) => String(c.company_id) === String(companies[0].id)) : clients);

    const form = useForm({
        company_id: initialCompanyId,
        client_id: initialClients[0]?.id ? String(initialClients[0].id) : "",
        project_code: "",
        name: "",
        category: "",
        description: "",
        project_address: "",
        location_name: "",
        khasra_number: "",
        site_area: "",
        scope_of_work: "",
        latitude: "",
        longitude: "",
        start_date: "",
        expected_end_date: "",
        priority: "medium",
        status: "draft",
        current_stage: "budget_pending",
        client_review_status: "pending",
        client_revision_comment: "",
    });

    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editingProject, setEditingProject] = useState(null);

    const editForm = useForm({
        company_id: "",
        client_id: "",
        project_code: "",
        name: "",
        category: "",
        description: "",
        project_address: "",
        location_name: "",
        khasra_number: "",
        site_area: "",
        scope_of_work: "",
        latitude: "",
        longitude: "",
        start_date: "",
        expected_end_date: "",
        priority: "medium",
        status: "",
        current_stage: "",
        client_review_status: "",
        client_revision_comment: "",
    });

    const [stageFilter, setStageFilter] = useState("all");
    const [search, setSearch] = useState("");
    const [openDropdownId, setOpenDropdownId] = useState(null);
    const [dropdownPos, setDropdownPos] = useState({ top: 0, left: 0 });

    // Close dropdown on outside click
    useEffect(() => {
        function handleClickOutside(event) {
            if (!event.target.closest("[data-project-dropdown]")) {
                setOpenDropdownId(null);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    // Close dropdown on scroll / resize so it never stays in wrong position
    useEffect(() => {
        function handleScrollOrResize() {
            setOpenDropdownId(null);
        }
        window.addEventListener("scroll", handleScrollOrResize, true);
        window.addEventListener("resize", handleScrollOrResize);
        return () => {
            window.removeEventListener("scroll", handleScrollOrResize, true);
            window.removeEventListener("resize", handleScrollOrResize);
        };
    }, []);

    const openProjectDropdown = (e, projectId) => {
        if (openDropdownId === projectId) {
            setOpenDropdownId(null);
            return;
        }
        const rect = e.currentTarget.getBoundingClientRect();
        const dropdownWidth = 176; // w-44 = 176px
        let left = rect.right - dropdownWidth;
        if (left < 8) left = 8;
        if (left + dropdownWidth > window.innerWidth - 8) {
            left = window.innerWidth - dropdownWidth - 8;
        }
        setDropdownPos({ top: rect.bottom + 4, left });
        setOpenDropdownId(projectId);
    };

    const openCreateModal = () => {
        form.clearErrors();
        setIsCodeManuallyEdited(false);
        if (next_project_code) {
            setNextCode(next_project_code);
        }
        fetch(route("super.construction.projects.next-code"))
            .then((res) => res.json())
            .then((data) => {
                if (data?.code) {
                    setNextCode(data.code);
                }
            })
            .catch(() => {});
        if (effectiveCompanyId) {
            form.setData((prev) => ({
                ...prev,
                company_id: String(effectiveCompanyId),
                client_id: "",
                project_code: "",
            }));
        }
        setCreateModalOpen(true);
    };

    const closeCreateModal = () => {
        form.clearErrors();
        setIsCodeManuallyEdited(false);
        form.reset(
            "name",
            "project_code",
            "category",
            "description",
            "project_address",
            "location_name",
            "latitude",
            "longitude",
            "start_date",
            "expected_end_date",
            "client_review_status",
            "client_revision_comment",
        );
        if (effectiveCompanyId) {
            form.setData("company_id", String(effectiveCompanyId));
        }
        setCreateModalOpen(false);
    };

    const handleNameChange = (e) => {
        const enteredName = e.target.value;
        const autoCode = nextCode || "PRJ-00001";
        form.setData((prev) => {
            const nextData = { ...prev, name: enteredName };
            if (!isCodeManuallyEdited) {
                if (enteredName.trim().length > 0) {
                    nextData.project_code = autoCode;
                } else {
                    nextData.project_code = "";
                }
            }
            return nextData;
        });
    };

    const handleProjectCodeChange = (e) => {
        setIsCodeManuallyEdited(true);
        form.setData("project_code", e.target.value);
    };

    const handleResetAutoCode = () => {
        setIsCodeManuallyEdited(false);
        form.setData("project_code", form.data.name?.trim() ? (nextCode || "PRJ-00001") : "");
    };

    useEffect(() => {
        if (!createModalOpen) return;
        if (!isCodeManuallyEdited) {
            const hasName = Boolean(form.data.name && form.data.name.trim().length > 0);
            const currentAutoCode = nextCode || "PRJ-00001";
            if (hasName && form.data.project_code !== currentAutoCode) {
                form.setData("project_code", currentAutoCode);
            } else if (!hasName && form.data.project_code === currentAutoCode) {
                form.setData("project_code", "");
            }
        }
    }, [form.data.name, nextCode, isCodeManuallyEdited, createModalOpen]);

    const openEditModal = (project) => {
        if (!canEditProjectFor(project)) return;
        editForm.clearErrors();
        editForm.setData({
            company_id: isCompanyLocked ? String(effectiveCompanyId) : (project.company_id ? String(project.company_id) : ""),
            client_id: project.client_id ? String(project.client_id) : "",
            project_code: project.project_code || "",
            name: project.name || "",
            category: project.category || "",
            description: project.description || "",
            project_address: project.project_address || "",
            location_name: project.location_name || "",
            khasra_number: project.khasra_number || "",
            site_area: project.site_area || "",
            scope_of_work: project.scope_of_work || "",
            latitude: project.latitude ?? "",
            longitude: project.longitude ?? "",
            start_date: formatDateForInput(project.start_date),
            expected_end_date: formatDateForInput(project.expected_end_date),
            priority: project.priority || "medium",
            status: project.status || "",
            current_stage: project.current_stage || "",
            client_review_status: project.client_review_status || "pending",
            client_revision_comment: project.client_revision_comment || "",
        });
        setEditingProject(project);
    };

    const closeEditModal = () => {
        editForm.clearErrors();
        editForm.reset();
        setEditingProject(null);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route("super.construction.projects.update", editingProject.id), {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
    };

    const filteredClients = useMemo(
        () => clients.filter((client) => String(client.company_id) === String(form.data.company_id)),
        [clients, form.data.company_id]
    );

    const editFilteredClients = useMemo(
        () => clients.filter((client) => String(client.company_id) === String(editForm.data.company_id)),
        [clients, editForm.data.company_id]
    );

    useEffect(() => {
        if (effectiveCompanyId && String(form.data.company_id) !== String(effectiveCompanyId)) {
            form.setData("company_id", String(effectiveCompanyId));
        }
    }, [effectiveCompanyId, form.data.company_id]);

    useEffect(() => {
        if (filteredClients.length > 0) {
            const hasCurrentClient = filteredClients.some((c) => String(c.id) === String(form.data.client_id));
            if (!hasCurrentClient) {
                form.setData("client_id", String(filteredClients[0].id));
            }
        } else if (form.data.client_id) {
            form.setData("client_id", "");
        }
    }, [filteredClients]);

    const projectList = useMemo(() => projects || [], [projects]);

    const counts = useMemo(() => {
        const out = { total: 0, running: 0, completed: 0, pending: 0 };
        const c = {};
        projectStatusFlow.forEach((st) => (c[st.value] = 0));
        for (const p of projectList) {
            out.total += 1;
            if (projectStatusFlow.find((s) => s.value === p.current_stage)) c[p.current_stage] = (c[p.current_stage] || 0) + 1;
            if (p.current_stage === "completed" || p.current_stage === "closed") out.completed += 1;
            else if (p.current_stage === "budget_pending" || p.current_stage === "draft") out.pending += 1;
            else out.running += 1;
        }
        return { ...out, stages: c };
    }, [projectList]);

    const filteredProjects = useMemo(() => {
        let list = projectList;
        if (stageFilter !== "all") list = list.filter((p) => p.current_stage === stageFilter);
        if (search.trim()) {
            const q = search.trim().toLowerCase();
            list = list.filter(
                (p) =>
                    (p.name || "").toLowerCase().includes(q) ||
                    (p.project_code || "").toLowerCase().includes(q) ||
                    (p.client?.name || "").toLowerCase().includes(q) ||
                    (p.company?.name || "").toLowerCase().includes(q)
            );
        }
        return list;
    }, [projectList, stageFilter, search]);

    return (
        <ConstructionShell title="Projects" description="Every department works around project records. Phase 1 starts here and Phase 2 continues from here." variant="super">
            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 className="text-[22px] font-bold text-slate-900 dark:text-white tracking-tight">Projects & Budget</h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Create projects, approve budgets, assign teams, and advance through the 5-stage lifecycle.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={13} />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search projects…"
                            className="rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2 text-[13px] text-slate-700 placeholder:text-slate-400 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-indigo-700 dark:focus:ring-indigo-900 w-64"
                        />
                    </div>
                </div>
            </div>

            <PendingCadApprovalAlertBanner
                approvals={pendingCadApprovals}
                className="cad-action-blink-border"
            />
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
                <StatCard label="Total Projects" value={counts.total} hint="All-time registered" icon={FaProjectDiagram} color="indigo" />
                <StatCard label="Running Projects" value={counts.running} hint="In any active stage" icon={FaPlayCircle} color="emerald" />
                <StatCard label="Completed Projects" value={counts.completed} hint="Handed over & closed" icon={FaCheckCircle} color="sky" />
                <StatCard label="Pending Projects" value={counts.pending} hint="Draft / awaiting budget" icon={FaHourglassHalf} color="amber" />
            </div>

            <SectionCard
                title="Project Lifecycle Stages"
                description="Click any stage to filter the register below. Every project moves through this linear flow."
            >
                <div className="grid gap-2.5 sm:gap-3 grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
                    <button
                        onClick={() => setStageFilter("all")}
                        className={`text-left rounded-xl border p-2.5 sm:p-3 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md ${
                            stageFilter === "all"
                                ? "border-slate-900 bg-slate-900 text-white shadow-md dark:border-white dark:bg-white dark:text-slate-900"
                                : "border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <p className={`text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider ${stageFilter === "all" ? "opacity-80" : "text-slate-500 dark:text-slate-400"}`}>
                                View
                            </p>
                            <span className={`text-base sm:text-lg font-bold ${stageFilter === "all" ? "" : "text-slate-900 dark:text-white"}`}>{counts.total}</span>
                        </div>
                        <p className={`mt-1.5 text-xs sm:text-sm font-semibold ${stageFilter === "all" ? "" : "text-slate-900 dark:text-white"}`}>All Projects</p>
                        <p className={`mt-0.5 text-[10px] sm:text-[11px] ${stageFilter === "all" ? "opacity-80" : "text-slate-500 dark:text-slate-400"}`}>
                            No filter applied
                        </p>
                    </button>
                    {projectStatusFlow.map((st) => {
                        const Icon = st.icon;
                        const count = counts.stages[st.value] || 0;
                        return (
                            <button
                                key={st.value}
                                onClick={() => setStageFilter(stageFilter === st.value ? "all" : st.value)}
                                className={`text-left rounded-xl border p-2.5 sm:p-3 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md ${
                                    stageFilter === st.value
                                        ? "border-indigo-500 bg-indigo-50 shadow-md shadow-indigo-100 dark:border-indigo-500 dark:bg-indigo-950/60 dark:shadow-none"
                                        : "border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                                }`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div
                                        className={`w-7 h-7 rounded-lg flex items-center justify-center text-white shrink-0 ${
                                            {
                                                indigo: "bg-indigo-600",
                                                sky: "bg-sky-600",
                                                amber: "bg-amber-500",
                                                violet: "bg-violet-600",
                                                fuchsia: "bg-fuchsia-600",
                                                rose: "bg-rose-600",
                                                emerald: "bg-emerald-600",
                                            }[st.color]
                                        }`}
                                    >
                                        <Icon size={12} />
                                    </div>
                                    <span className="text-base sm:text-lg font-bold text-slate-900 dark:text-white">{count}</span>
                                </div>
                                <p className="mt-1.5 text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">{st.label}</p>
                                <p className="mt-0.5 text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 leading-snug truncate">{st.description}</p>
                            </button>
                        );
                    })}
                </div>
            </SectionCard>

            <ChecklistMonitoringDashboard projects={projects} />

            {canCreateProject && (
                <div className="mt-6">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white">Create Project</h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                Register the central project record and set the initial Phase 1 metadata.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:from-indigo-500 hover:to-violet-500"
                        >
                            <FaPlus size={13} />
                            New Project
                        </button>
                    </div>
                </div>
            )}

            <SectionCard
                title={`Project Register (${filteredProjects.length})`}
                description="Track where each project sits in the lifecycle. Click View / Edit / Delete actions to manage."
            >
                {filteredProjects.length ? (
                    <div className="overflow-x-auto -mx-1 sm:-mx-2">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="text-slate-500 dark:text-slate-400 bg-slate-50/60 dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Project</th>
                                    <th className="hidden lg:table-cell px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Company · Client</th>
                                    <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Stage</th>
                                    <th className="hidden md:table-cell px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Dates</th>
                                    <th className="hidden xl:table-cell px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Workflow</th>
                                    <th className="hidden xl:table-cell px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Checklists</th>
                                    <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Budget</th>
                                    <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {filteredProjects.map((project) => {
                                    const tasks = project.workflow_counts?.execution_tasks || { total: 0, completed: 0, in_progress: 0, pending: 0 };
                                    const checklists = project.workflow_counts?.checklists || { total: 0, completed: 0 };
                                    const survey = project.workflow_counts?.survey || { plans: 0, submissions: 0 };
                                    const checklistPct = checklists.total
                                        ? Math.round((checklists.completed / checklists.total) * 100)
                                        : 0;
                                    const clientReviewBadge = project.client_review_status
                                        ? project.client_review_status.replace(/_/g, " ")
                                        : null;
                                    const hasPendingCadAction = pendingCadSet.has(Number(project.id));
                                    return (
                                        <tr
                                            key={project.id}
                                            className={`transition-colors align-top ${
                                                hasPendingCadAction
                                                    ? "cad-action-blink-border bg-rose-50/50 dark:bg-rose-950/25"
                                                    : "hover:bg-slate-50/40 dark:hover:bg-slate-900/40"
                                            }`}
                                        >
                                            <td className="px-3 py-3 max-w-[220px] sm:max-w-none">
                                                <div className="font-semibold text-slate-900 dark:text-white truncate">{project.name}</div>
                                                {hasPendingCadAction && (
                                                    <span className="mt-1 inline-flex items-center rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                                                        Action Needed: CAD Approval
                                                    </span>
                                                )}
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {project.project_code || "—"} · {project.priority || "medium"} · {project.category || "Uncategorized"}
                                                </div>
                                                {project.status && project.status !== "draft" ? (
                                                    <div className="mt-1">
                                                        <StatusBadge value={project.status} />
                                                    </div>
                                                ) : null}
                                                {clientReviewBadge ? (
                                                    <div className="mt-1">
                                                        <StatusBadge value={clientReviewBadge} />
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="hidden lg:table-cell px-3 py-3 text-[13px] text-slate-600 dark:text-slate-300">
                                                <div className="font-medium text-slate-700 dark:text-slate-200">{project.company?.name || "-"}</div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">{project.client?.name || "-"}</div>
                                                {(project.location_name || project.project_address) ? (
                                                    <div
                                                        className="mt-1 text-[11px] text-slate-400 truncate"
                                                        title={project.location_name || project.project_address}
                                                    >
                                                        📍 {String(project.location_name || project.project_address).slice(0, 42)}
                                                        {String(project.location_name || project.project_address).length > 42 ? "…" : ""}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-3">
                                                <StatusBadge value={project.current_stage} />
                                            </td>
                                            <td className="hidden md:table-cell px-3 py-3 text-[13px] text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                                <div>{formatDate(project.start_date)} →</div>
                                                <div className="text-slate-500">{formatDate(project.expected_end_date)}</div>
                                            </td>
                                            <td className="hidden xl:table-cell px-3 py-3 text-[12px] text-slate-600 dark:text-slate-300">
                                                <div className="font-medium text-slate-700 dark:text-slate-200">
                                                    Tasks {tasks.completed}/{tasks.total} · Surveys {survey.submissions}/{survey.plans || survey.submissions || 0}
                                                </div>
                                                <div className="mt-1 text-[11px] text-slate-500">
                                                    {tasks.in_progress || 0} in-progress · {tasks.pending || 0} pending
                                                </div>
                                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                                    <div
                                                        className="h-full bg-indigo-500 transition-all"
                                                        style={{ width: `${tasks.total ? Math.round((tasks.completed / tasks.total) * 100) : 0}%` }}
                                                    />
                                                </div>
                                            </td>
                                            <td className="hidden xl:table-cell px-3 py-3 text-[12px] text-slate-600 dark:text-slate-300">
                                                <div className="font-medium text-slate-700 dark:text-slate-200">
                                                    {checklists.completed}/{checklists.total} complete
                                                </div>
                                                <div className="text-[11px] text-slate-500">{checklistPct}%</div>
                                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-emerald-100 dark:bg-emerald-950/40">
                                                    <div
                                                        className="h-full bg-emerald-500 transition-all"
                                                        style={{ width: `${checklistPct}%` }}
                                                    />
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-[13px] font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                {formatCurrency(
                                                    project.latest_budget?.approved_amount ||
                                                        project.latest_budget?.estimated_amount ||
                                                        0
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                <div className="inline-flex items-center justify-end gap-1.5 flex-nowrap whitespace-nowrap">
                                                    {canViewProject && (
                                                        <Link
                                                            href={route("super.construction.projects.show", project.id)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-300 dark:hover:bg-indigo-950"
                                                        >
                                                            <FaEye size={12} />
                                                            View Details
                                                        </Link>
                                                    )}
                                                    {(() => {
                                                        const rowCanEdit = canEditProjectFor(project);
                                                        const rowCanDelete = canDeleteProjectFor(project);
                                                        if (!rowCanEdit && !rowCanDelete) return null;

                                                        return (
                                                            <>
                                                                {rowCanEdit && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openEditModal(project)}
                                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-xs font-medium text-sky-700 transition hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-950"
                                                                        title="Edit Project"
                                                                    >
                                                                        <FaEdit size={12} />
                                                                        Edit
                                                                    </button>
                                                                )}
                                                                {rowCanDelete && (
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
                                                                )}
                                                            </>
                                                        );
                                                    })()}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <EmptyState
                        title="No projects match this filter."
                        description="Clear the stage filter or create the first project to start the ERP lifecycle."
                    />
                )}
            </SectionCard>

            <Modal show={createModalOpen} onClose={closeCreateModal} maxWidth="5xl" topCloseButton={true}>
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0">
                            <FaPlus size={13} />
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">New Project Registration</h3>
                            <p className="mt-1 text-sm text-slate-500">
                                Register the central project record and set the initial Phase 1 metadata.
                            </p>
                        </div>
                    </div>
                    {filteredClients.length === 0 && (
                        <div className="mr-8">
                            <Link
                                href={route("super.construction.clients.index", form.data.company_id ? { company_id: form.data.company_id } : {})}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition shadow-sm dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900"
                            >
                                <FaPlus size={10} /> Add Client
                            </Link>
                        </div>
                    )}
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route("super.construction.projects.store"), {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset(
                                    "name",
                                    "project_code",
                                    "category",
                                    "description",
                                    "project_address",
                                    "location_name",
                                    "khasra_number",
                                    "site_area",
                                    "scope_of_work",
                                    "latitude",
                                    "longitude",
                                    "start_date",
                                    "expected_end_date",
                                    "client_review_status",
                                    "client_revision_comment",
                                );
                                setCreateModalOpen(false);
                            },
                        });
                    }}
                    className="space-y-4 p-5 max-h-[82vh] overflow-y-auto"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={form}
                            name="company_id"
                            label="Company"
                            options={companies.map((company) => ({ value: company.id, label: company.name }))}
                            disabled={isCompanyLocked}
                            badge={isCompanyLocked ? "Auto-linked" : null}
                            helper={isCompanyLocked ? "Locked to your company" : null}
                        />
                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    Client <span className="text-[11px] font-normal lowercase text-slate-400">(optional)</span>
                                </label>
                                {filteredClients.length === 0 && (
                                    <Link
                                        href={route("super.construction.clients.index", form.data.company_id ? { company_id: form.data.company_id } : {})}
                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 hover:underline"
                                    >
                                        <FaPlus size={9} /> Add Client
                                    </Link>
                                )}
                            </div>
                            <SelectField
                                form={form}
                                name="client_id"
                                label=""
                                options={
                                    filteredClients.length > 0
                                        ? [
                                            { value: "", label: "-- Optional: Select Client --" },
                                            ...filteredClients.map((client) => ({ value: client.id, label: client.name })),
                                        ]
                                        : [{ value: "", label: "— No clients for this company —" }]
                                }
                            />
                            {filteredClients.length === 0 && (
                                <div className="mt-1.5 flex items-center justify-between rounded-lg bg-indigo-50/70 p-2 text-xs text-indigo-700 border border-indigo-100 dark:bg-indigo-950/40 dark:border-indigo-900/50 dark:text-indigo-300">
                                    <span>No clients for this company yet.</span>
                                    <Link
                                        href={route("super.construction.clients.index", form.data.company_id ? { company_id: form.data.company_id } : {})}
                                        className="inline-flex items-center gap-1 rounded bg-indigo-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow hover:bg-indigo-700 transition"
                                    >
                                        <FaPlus size={9} /> Add Client
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                    <div>
                        <InputField
                            form={form}
                            name="name"
                            label="Project Name"
                            placeholder="Enter project name"
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={form}
                            name="category"
                            label="Category"
                            options={[
                                { value: "", label: "-- Select category --" },
                                { value: "Residential", label: "Residential" },
                                { value: "Commercial", label: "Commercial" },
                                { value: "Road", label: "Road" },
                                { value: "Bridge", label: "Bridge" },
                                { value: "Institutional", label: "Institutional" },
                                { value: "Industrial", label: "Industrial" },
                                { value: "Renovation", label: "Renovation" },
                                { value: "Other", label: "Other" },
                            ]}
                        />
                        <SelectField
                            form={form}
                            name="priority"
                            label="Priority"
                            options={[
                                { value: "low", label: "Low" },
                                { value: "medium", label: "Medium" },
                                { value: "high", label: "High" },
                                { value: "critical", label: "Critical" },
                            ]}
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={form}
                            name="status"
                            label="Project Status"
                            options={[
                                { value: "draft", label: "Draft" },
                                { value: "active", label: "Active" },
                                { value: "on_hold", label: "On Hold" },
                                { value: "cancelled", label: "Cancelled" },
                                { value: "completed", label: "Completed" },
                            ]}
                        />
                        <SelectField
                            form={form}
                            name="current_stage"
                            label="Lifecycle Stage"
                            options={[
                                { value: "survey", label: "Survey" },
                                { value: "planning", label: "Planning" },
                                { value: "budget_pending", label: "Budget Pending" },
                                { value: "finishing", label: "Finishing" },
                                { value: "handover", label: "Handover" },
                                { value: "completed", label: "Completed" },
                            ]}
                        />
                    </div>
                    <TextAreaField form={form} name="description" label="Description" rows={3} />
                    <div className="grid gap-4 md:grid-cols-2">
                        <InputField form={form} name="khasra_number" label="Khasra Number / Land Parcel ID" placeholder="e.g. 142/3, 144/1" />
                        <InputField form={form} name="site_area" label="Site Area" placeholder="e.g. 2400 sq.ft / 2.5 Acres" />
                    </div>
                    <TextAreaField
                        form={form}
                        name="scope_of_work"
                        label="Scope of Work"
                        rows={3}
                        placeholder="Enter detailed scope of work, deliverables, surveying & drafting requirements (shown & editable on Project Details)..."
                    />
                    <TextAreaField form={form} name="project_address" label="Project Address" rows={2} />
                    <GoogleMapLocationSection
                        initialLat={form.data.latitude}
                        initialLng={form.data.longitude}
                        initialAddress={form.data.project_address}
                        initialLocationName={form.data.location_name}
                        onChange={(data) => {
                            form.setData("location_name", data.location_name);
                            form.setData("project_address", data.project_address || form.data.project_address);
                            form.setData("latitude", String(data.latitude));
                            form.setData("longitude", String(data.longitude));
                        }}
                    />
                    <div className="grid gap-4 md:grid-cols-2">
                        <InputField form={form} name="start_date" label="Start Date" type="date" />
                        <InputField form={form} name="expected_end_date" label="Expected End Date" type="date" />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={form}
                            name="client_review_status"
                            label="Client Review Status"
                            options={[
                                { value: "pending", label: "Pending" },
                                { value: "requested", label: "Requested" },
                                { value: "approved", label: "Approved" },
                                { value: "revision_requested", label: "Revision Requested" },
                                { value: "rejected", label: "Rejected" },
                            ]}
                        />
                        <div className="hidden md:block" />
                    </div>
                    {form.data.client_review_status ? (
                        <TextAreaField form={form} name="client_revision_comment" label="Client Review Notes / Revision Comment" rows={2} />
                    ) : null}
                    <div className="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800 -mx-5 px-5 mt-4 py-4 sticky bottom-0 bg-white dark:bg-slate-900/95 backdrop-blur-sm">
                        <button
                            type="button"
                            onClick={closeCreateModal}
                            disabled={form.processing}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:from-indigo-500 hover:to-violet-500 disabled:opacity-60"
                        >
                            {form.processing ? (
                                <>Saving…</>
                            ) : (
                                <>
                                    <FaPlus size={13} />
                                    Create Project
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal show={editingProject !== null} onClose={closeEditModal} maxWidth="5xl" topCloseButton={true}>
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Edit Project</h3>
                    <p className="mt-1 text-sm text-slate-500">Update project master data, lifecycle status, lifecycle stage and client review metadata.</p>
                </div>
                <form onSubmit={submitEdit} className="space-y-4 p-5 max-h-[80vh] overflow-y-auto">
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={editForm}
                            name="company_id"
                            label="Company"
                            options={companies.map((company) => ({ value: company.id, label: company.name }))}
                            disabled={isCompanyLocked}
                            badge={isCompanyLocked ? "Auto-linked" : null}
                            helper={isCompanyLocked ? "Locked to your company" : null}
                        />
                        <SelectField
                            form={editForm}
                            name="client_id"
                            label="Client (Optional)"
                            options={[
                                { value: "", label: "-- No Client (Optional) --" },
                                ...editFilteredClients.map((client) => ({ value: client.id, label: client.name }))
                            ]}
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <InputField form={editForm} name="name" label="Project Name" />
                        <InputField form={editForm} name="project_code" label="Project Code" />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={editForm}
                            name="category"
                            label="Category"
                            options={[
                                { value: "", label: "-- Select category --" },
                                { value: "Residential", label: "Residential" },
                                { value: "Commercial", label: "Commercial" },
                                { value: "Road", label: "Road" },
                                { value: "Bridge", label: "Bridge" },
                                { value: "Institutional", label: "Institutional" },
                                { value: "Industrial", label: "Industrial" },
                                { value: "Renovation", label: "Renovation" },
                                { value: "Other", label: "Other" },
                            ]}
                        />
                        <SelectField
                            form={editForm}
                            name="priority"
                            label="Priority"
                            options={[
                                { value: "low", label: "Low" },
                                { value: "medium", label: "Medium" },
                                { value: "high", label: "High" },
                                { value: "critical", label: "Critical" },
                            ]}
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={editForm}
                            name="status"
                            label="Project Status"
                            options={[
                                { value: "", label: "-- Unchanged --" },
                                { value: "draft", label: "Draft" },
                                { value: "active", label: "Active" },
                                { value: "on_hold", label: "On Hold" },
                                { value: "cancelled", label: "Cancelled" },
                                { value: "completed", label: "Completed" },
                            ]}
                        />
                        <SelectField
                            form={editForm}
                            name="current_stage"
                            label="Lifecycle Stage"
                            options={[
                                { value: "", label: "-- Unchanged --" },
                                { value: "survey", label: "Survey" },
                                { value: "planning", label: "Planning" },
                                { value: "budget_pending", label: "Budget Pending" },
                                { value: "finishing", label: "Finishing" },
                                { value: "handover", label: "Handover" },
                                { value: "completed", label: "Completed" },
                            ]}
                        />
                    </div>
                    <TextAreaField form={editForm} name="description" label="Description" rows={3} />
                    <div className="grid gap-4 md:grid-cols-2">
                        <InputField form={editForm} name="khasra_number" label="Khasra Number / Land Parcel ID" placeholder="e.g. 142/3, 144/1" />
                        <InputField form={editForm} name="site_area" label="Site Area" placeholder="e.g. 2400 sq.ft / 2.5 Acres" />
                    </div>
                    <TextAreaField form={editForm} name="scope_of_work" label="Scope of Work" rows={2} placeholder="Brief description of work scope, surveying & drafting requirements..." />
                    <TextAreaField form={editForm} name="project_address" label="Project Address" rows={2} />
                    <GoogleMapLocationSection
                        initialLat={editForm.data.latitude}
                        initialLng={editForm.data.longitude}
                        initialAddress={editForm.data.project_address}
                        initialLocationName={editForm.data.location_name}
                        onChange={(data) => {
                            editForm.setData("location_name", data.location_name);
                            editForm.setData("project_address", data.project_address || editForm.data.project_address);
                            editForm.setData("latitude", String(data.latitude));
                            editForm.setData("longitude", String(data.longitude));
                        }}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <InputField form={editForm} name="start_date" label="Start Date" type="date" />
                        <InputField form={editForm} name="expected_end_date" label="Expected End Date" type="date" />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            form={editForm}
                            name="client_review_status"
                            label="Client Review Status"
                            options={[
                                { value: "pending", label: "Pending" },
                                { value: "requested", label: "Requested" },
                                { value: "approved", label: "Approved" },
                                { value: "revision_requested", label: "Revision Requested" },
                                { value: "rejected", label: "Rejected" },
                            ]}
                        />
                        <div className="hidden sm:block" />
                    </div>
                    <TextAreaField form={editForm} name="client_revision_comment" label="Client Review Notes / Revision Comment" rows={2} />
                    <div className="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800 -mx-5 px-5 mt-4 py-4 sticky bottom-0 bg-white dark:bg-slate-900/95 backdrop-blur-sm">
                        <button
                            type="button"
                            onClick={closeEditModal}
                            disabled={editForm.processing}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-60"
                        >
                            {editForm.processing ? "Updating..." : "Update Project"}
                        </button>
                    </div>
                </form>
            </Modal>
        </ConstructionShell>
    );
}

function formatCurrency(value) {
    const num = Number(value) || 0;
    if (num >= 10000000) return "₹" + (num / 10000000).toFixed(2) + " Cr";
    if (num >= 100000) return "₹" + (num / 100000).toFixed(2) + " L";
    if (num >= 1000) return "₹" + (num / 1000).toFixed(1) + "K";
    return "₹" + num.toFixed(0);
}

function formatDate(value) {
    if (!value) return "—";
    // Handle full ISO timestamps like "2026-08-12T00:00:00.000000Z"
    const datePart = String(value).split("T")[0] || value;
    const parts = datePart.split("-");
    if (parts.length === 3) {
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        if (!Number.isNaN(date.getTime())) {
            return date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
        }
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
}

function InputField({ form, name, label, type = "text", step, placeholder = "", onChange, extraAction }) {
    const rawValue = form.data[name];
    const inputValue = type === "date"
        ? formatDateForInput(rawValue)
        : (rawValue ?? "");

    return (
        <div>
            <div className="mb-1 flex items-center justify-between">
                <label className="block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
                {extraAction}
            </div>
            <input
                type={type}
                step={step}
                placeholder={placeholder}
                value={inputValue}
                onChange={onChange || ((e) => form.setData(name, e.target.value))}
                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TextAreaField({ form, name, label, rows = 4 }) {
    return (
        <div>
            <label className="mb-1 block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <textarea
                rows={rows}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function SelectField({ form, name, label, options, disabled = false, badge = null, helper = null }) {
    return (
        <div>
            <div className="mb-1 flex items-center justify-between">
                <label className="block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
                {badge && (
                    <span className="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-800">
                        {badge}
                    </span>
                )}
            </div>
            <select
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                disabled={disabled}
                className={`w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60 ${
                    disabled
                        ? "bg-slate-100 text-slate-500 cursor-not-allowed dark:bg-slate-800/80 dark:text-slate-400 opacity-90"
                        : ""
                }`}
            >
                {options.map((option) => (
                    <option key={String(option.value)} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {helper ? <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{helper}</p> : null}
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function ChecklistMonitoringDashboard({ projects }) {
    const containerRef = useRef(null);

    const prefersReducedMotion = () =>
        typeof window !== "undefined" &&
        typeof window.matchMedia === "function" &&
        window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const withMeta = useMemo(() => {
        return (projects || []).map((p) => {
            const cl = p.workflow_counts?.checklists || { total: 0, completed: 0 };
            const pct = cl.total > 0 ? (cl.completed / cl.total) * 100 : -1;
            return { project: p, total: cl.total, completed: cl.completed, pct };
        });
    }, [projects]);

    const needingAttention = useMemo(
        () => withMeta.filter((m) => m.total > 0).sort((a, b) => a.pct - b.pct).slice(0, 3),
        [withMeta]
    );

    const zeroItemProjects = useMemo(
        () => withMeta.filter((m) => m.total === 0).slice(0, 2),
        [withMeta]
    );

    useEffect(() => {
        if (!containerRef.current || prefersReducedMotion()) return undefined;

        const ctx = gsap.context(() => {
            // Stage zoom-out reveal
            gsap.fromTo(
                containerRef.current,
                { opacity: 0, scale: 1.03, y: 14 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.6,
                    ease: "power3.out",
                    clearProps: "scale",
                }
            );

            // Kinetic word-by-word title reveal
            gsap.fromTo(
                ".cmd-title-word",
                { opacity: 0, y: 8, rotateX: -20 },
                {
                    opacity: 1,
                    y: 0,
                    rotateX: 0,
                    stagger: 0.04,
                    duration: 0.5,
                    ease: "back.out(1.7)",
                    clearProps: "all",
                }
            );

            // Staggered card entrance cascade
            gsap.fromTo(
                ".cmd-card",
                { opacity: 0, y: 12, scale: 0.97 },
                {
                    opacity: 1,
                    y: 0,
                    scale: 1,
                    stagger: 0.06,
                    duration: 0.45,
                    ease: "power3.out",
                    clearProps: "opacity,y",
                }
            );

            // Smooth animated progress bar fill
            gsap.fromTo(
                ".cmd-progress-bar",
                { width: "0%" },
                {
                    width: (i, el) => `${el.dataset.pct || 0}%`,
                    duration: 0.9,
                    ease: "power2.out",
                    delay: 0.15,
                }
            );

            // Pulsing live indicator dot
            gsap.to(".cmd-live-dot", {
                scale: 1.4,
                opacity: 0.35,
                repeat: -1,
                yoyo: true,
                duration: 1.1,
                ease: "sine.inOut",
            });
        }, containerRef);

        return () => ctx.revert();
    }, [needingAttention.length, zeroItemProjects.length]);

    const handleCardEnter = (e) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, {
            y: -3,
            scale: 1.02,
            boxShadow: "0 10px 25px -4px rgba(99, 102, 241, 0.18)",
            duration: 0.22,
            ease: "back.out(2)",
        });
    };

    const handleCardLeave = (e) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, {
            y: 0,
            scale: 1,
            boxShadow: "none",
            duration: 0.22,
            ease: "power2.out",
        });
    };

    if (!needingAttention.length && !zeroItemProjects.length) {
        return (
            <div ref={containerRef} className="cmd-stage mt-5 rounded-2xl border border-slate-200/90 bg-white p-3 sm:p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-center gap-2 border-b border-slate-100 pb-2.5 dark:border-slate-800/80">
                    <h3 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white flex items-center gap-1">
                        <span className="cmd-title-word inline-block mr-1">Checklist</span>
                        <span className="cmd-title-word inline-block mr-1">Monitoring</span>
                        <span className="cmd-title-word inline-block">Dashboard</span>
                    </h3>
                </div>
                <div className="py-6 text-center">
                    <EmptyState
                        title="No checklist data yet."
                        description="Open a project and seed defaults to begin monitoring completion progress here."
                    />
                </div>
            </div>
        );
    }

    const colorForPct = (pct) => {
        if (pct < 0 || pct === null) return "from-slate-400 to-slate-500";
        if (pct < 35) return "from-rose-500 to-amber-500";
        if (pct < 70) return "from-amber-500 to-sky-500";
        return "from-emerald-500 to-indigo-500";
    };

    const chipForPct = (pct) => {
        if (pct < 0) return { label: "No items", cls: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" };
        if (pct < 35) return { label: "Behind", cls: "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300" };
        if (pct < 70) return { label: "In Progress", cls: "bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300" };
        return { label: "On Track", cls: "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" };
    };

    return (
        <div ref={containerRef} className="cmd-stage mt-5 rounded-2xl border border-slate-200/90 bg-white p-3 sm:p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
            {/* Header: Compact title with kinetic reveal & Live pulse indicator */}
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5 dark:border-slate-800/80">
                <div className="flex items-center gap-2">
                    <h3 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white flex items-center">
                        <span className="cmd-title-word inline-block mr-1">Checklist</span>
                        <span className="cmd-title-word inline-block mr-1">Monitoring</span>
                        <span className="cmd-title-word inline-block">Dashboard</span>
                    </h3>
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <span className="relative flex h-1.5 w-1.5">
                            <span className="cmd-live-dot absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </span>
                        LIVE
                    </span>
                </div>
                <p className="text-[11px] text-slate-500 dark:text-slate-400 hidden sm:block">
                    Top lowest-completion projects · Deep-link to checklist workspace
                </p>
            </div>

            {/* Compact Mini Space Grid */}
            <div className="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                {needingAttention.map(({ project, total, completed, pct }) => {
                    const rounded = pct < 0 ? 0 : Math.round(pct);
                    const chip = chipForPct(pct);
                    const deepLink = `${route("super.construction.projects.show", project.id)}?focus=checklist#dynamic-checklist-manager`;

                    return (
                        <article
                            key={project.id}
                            onMouseEnter={handleCardEnter}
                            onMouseLeave={handleCardLeave}
                            className="cmd-card relative overflow-hidden rounded-xl border border-slate-200/90 bg-white p-2.5 sm:p-3 shadow-2xs transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div className={`pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r ${colorForPct(pct)} opacity-90`} />

                            <div className="flex items-start justify-between gap-1.5">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">{project.name}</p>
                                    <p className="mt-0.5 text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        {project.project_code || "—"} · {project.company?.name || project.client?.name || "Unassigned"}
                                    </p>
                                </div>
                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[9px] sm:text-[10px] font-semibold ${chip.cls}`}>
                                    {chip.label}
                                </span>
                            </div>

                            <div className="mt-2.5 flex items-baseline justify-between gap-2">
                                <div className="flex items-baseline gap-1.5">
                                    <span className="text-base sm:text-lg font-bold text-slate-900 dark:text-white">
                                        {rounded}%
                                    </span>
                                    <span className="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400">
                                        {completed}/{total} items complete
                                    </span>
                                </div>
                                <StatusBadge value={project.current_stage} />
                            </div>

                            <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    data-pct={rounded}
                                    className={`cmd-progress-bar h-full rounded-full bg-gradient-to-r ${colorForPct(pct)}`}
                                    style={{ width: `${rounded}%` }}
                                />
                            </div>

                            <div className="mt-2.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-2 dark:border-slate-800/80">
                                <span className="text-[10px] text-slate-400 capitalize">
                                    {String(project.status || "draft").replace(/_/g, " ")}
                                </span>
                                <Link
                                    href={deepLink}
                                    className="group inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-2xs transition hover:bg-indigo-500"
                                >
                                    <span>Open checklists</span>
                                    <span className="transition-transform group-hover:translate-x-0.5">→</span>
                                </Link>
                            </div>
                        </article>
                    );
                })}

                {zeroItemProjects.map(({ project }) => {
                    const deepLink = `${route("super.construction.projects.show", project.id)}?focus=checklist#dynamic-checklist-manager`;

                    return (
                        <article
                            key={`empty-${project.id}`}
                            onMouseEnter={handleCardEnter}
                            onMouseLeave={handleCardLeave}
                            className="cmd-card relative rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-2.5 sm:p-3 transition hover:shadow-2xs dark:border-slate-700 dark:bg-slate-900/40"
                        >
                            <div className="flex items-start justify-between gap-1.5">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">{project.name}</p>
                                    <p className="mt-0.5 text-[10px] sm:text-[11px] text-slate-500 truncate">
                                        {project.project_code || "—"} · {project.client?.name || "No client"}
                                    </p>
                                </div>
                                <span className="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[9px] sm:text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                    No checklists
                                </span>
                            </div>
                            <p className="mt-2 text-[10px] sm:text-[11px] leading-snug text-slate-500 dark:text-slate-400">
                                Seed default survey checklist to start tracking field progress.
                            </p>
                            <div className="mt-2.5 flex justify-end border-t border-slate-200/60 pt-2 dark:border-slate-800">
                                <Link
                                    href={deepLink}
                                    className="group inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-indigo-600 transition hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-900 dark:text-indigo-400 dark:hover:bg-slate-800"
                                >
                                    <span>Go to project</span>
                                    <span className="transition-transform group-hover:translate-x-0.5">→</span>
                                </Link>
                            </div>
                        </article>
                    );
                })}
            </div>
        </div>
    );
}
