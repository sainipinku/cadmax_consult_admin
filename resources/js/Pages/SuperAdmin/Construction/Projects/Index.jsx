import { Link, useForm, router } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import Modal from "@/Components/Modal";
import GoogleMapLocationSection from "@/Components/GoogleMapLocationSection.jsx";
import { useEffect, useMemo, useState } from "react";
import {
    FaProjectDiagram,
    FaPlayCircle,
    FaCheckCircle,
    FaHourglassHalf,
    FaLayerGroup,
    FaSearch,
    FaToolbox,
    FaBuilding,
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
    { value: "foundation", label: "Foundation", color: "amber", icon: FaToolbox, description: "Footing & excavation" },
    { value: "structure", label: "Structure", color: "violet", icon: FaBuilding, description: "Columns, beams, slabs" },
    { value: "finishing", label: "Finishing", color: "fuchsia", icon: FaRulerCombined, description: "Paint, floor, fixtures" },
    { value: "handover", label: "Handover", color: "rose", icon: FaHandshake, description: "Inspection & handover" },
    { value: "completed", label: "Completed", color: "emerald", icon: FaFlagCheckered, description: "Project closed" },
];

export default function ProjectsIndex({ projects, companies, clients }) {
    const form = useForm({
        company_id: companies[0]?.id || "",
        client_id: clients[0]?.id || "",
        project_code: "",
        name: "",
        category: "",
        description: "",
        project_address: "",
        location_name: "",
        latitude: "",
        longitude: "",
        start_date: "",
        expected_end_date: "",
        priority: "medium",
        status: "draft",
        current_stage: "budget_pending",
        client_review_status: "",
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

    const closeCreateModal = () => {
        form.clearErrors();
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
        setCreateModalOpen(false);
    };

    const openEditModal = (project) => {
        editForm.clearErrors();
        editForm.setData({
            company_id: project.company_id || "",
            client_id: project.client_id || "",
            project_code: project.project_code || "",
            name: project.name || "",
            category: project.category || "",
            description: project.description || "",
            project_address: project.project_address || "",
            location_name: project.location_name || "",
            latitude: project.latitude ?? "",
            longitude: project.longitude ?? "",
            start_date: project.start_date || "",
            expected_end_date: project.expected_end_date || "",
            priority: project.priority || "medium",
            status: project.status || "",
            current_stage: project.current_stage || "",
            client_review_status: project.client_review_status || "",
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
                        Create projects, approve budgets, assign teams, and advance through the 7-stage lifecycle.
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
                <div className="grid gap-3 md:grid-cols-4 xl:grid-cols-7">
                    <button
                        onClick={() => setStageFilter("all")}
                        className={`text-left rounded-2xl border p-3.5 transition-all duration-200 ${
                            stageFilter === "all"
                                ? "border-slate-900 bg-slate-900 text-white shadow-md dark:border-white dark:bg-white dark:text-slate-900"
                                : "border-slate-200 bg-white hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <p className={`text-[11px] font-semibold uppercase tracking-wider ${stageFilter === "all" ? "opacity-80" : "text-slate-500 dark:text-slate-400"}`}>
                                View
                            </p>
                            <span className={`text-lg font-bold ${stageFilter === "all" ? "" : "text-slate-900 dark:text-white"}`}>{counts.total}</span>
                        </div>
                        <p className={`mt-2 text-sm font-semibold ${stageFilter === "all" ? "" : "text-slate-900 dark:text-white"}`}>All Projects</p>
                        <p className={`mt-0.5 text-[11px] ${stageFilter === "all" ? "opacity-80" : "text-slate-500 dark:text-slate-400"}`}>
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
                                className={`text-left rounded-2xl border p-3.5 transition-all duration-200 ${
                                    stageFilter === st.value
                                        ? "border-indigo-500 bg-indigo-50 shadow-md shadow-indigo-100 dark:border-indigo-500 dark:bg-indigo-950/60 dark:shadow-none"
                                        : "border-slate-200 bg-white hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                                }`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div
                                        className={`w-8 h-8 rounded-lg flex items-center justify-center text-white ${
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
                                        <Icon size={14} />
                                    </div>
                                    <span className="text-lg font-bold text-slate-900 dark:text-white">{count}</span>
                                </div>
                                <p className="mt-2.5 text-sm font-semibold text-slate-900 dark:text-white">{st.label}</p>
                                <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">{st.description}</p>
                            </button>
                        );
                    })}
                </div>
            </SectionCard>

            <SectionCard
                title="Checklist Monitoring Dashboard"
                description="Top 3 lowest-completion projects. Deep-link directly to the checklist workspace on the project details page."
                className="mt-6"
            >
                {(() => {
                    const SLUG_SAFE = (s) =>
                        String(s ?? "")
                            .toLowerCase()
                            .replace(/[^a-z0-9]+/g, "-")
                            .replace(/(^-|-$)/g, "");

                    const withMeta = (projects || []).map((p) => {
                        const cl = p.workflow_counts?.checklists || { total: 0, completed: 0 };
                        const pct = cl.total > 0 ? (cl.completed / cl.total) * 100 : -1;
                        return { project: p, total: cl.total, completed: cl.completed, pct };
                    });

                    const needingAttention = withMeta
                        .filter((m) => m.total > 0)
                        .sort((a, b) => a.pct - b.pct)
                        .slice(0, 3);

                    const zeroItemProjects = withMeta
                        .filter((m) => m.total === 0)
                        .slice(0, 2);

                    if (!needingAttention.length && !zeroItemProjects.length) {
                        return (
                            <EmptyState
                                title="No checklist data yet."
                                description="Open a project and seed defaults to begin monitoring completion progress here."
                            />
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
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {needingAttention.map(({ project, total, completed, pct }) => {
                                const rounded = pct < 0 ? 0 : Math.round(pct);
                                const chip = chipForPct(pct);
                                return (
                                    <article
                                        key={project.id}
                                        className="rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold text-slate-900 dark:text-white">{project.name}</p>
                                                <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                                    {project.project_code || "—"} · {project.company?.name || project.client?.name || "Unassigned"}
                                                </p>
                                            </div>
                                            <span className={`rounded-full px-2.5 py-0.5 text-[10px] font-semibold ${chip.cls}`}>
                                                {chip.label}
                                            </span>
                                        </div>
                                        <div className="mt-4 flex items-baseline justify-between">
                                            <div>
                                                <p className="text-2xl font-bold text-slate-900 dark:text-white">
                                                    {rounded}
                                                    <span className="text-sm text-slate-400">%</span>
                                                </p>
                                                <p className="mt-0.5 text-[11px] text-slate-500">
                                                    {completed}/{total} items complete
                                                </p>
                                            </div>
                                            <StatusBadge value={project.current_stage} />
                                        </div>
                                        <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                className={`h-full bg-gradient-to-r transition-all ${colorForPct(pct)}`}
                                                style={{ width: `${rounded}%` }}
                                            />
                                        </div>
                                        <div className="mt-4 flex items-center justify-between gap-2">
                                            <span className="text-[11px] text-slate-400">
                                                {String(project.status || "draft").replace(/_/g, " ")}
                                            </span>
                                            <Link
                                                href={route("super.construction.projects.show", project.id) + `#chk-group-${SLUG_SAFE(project.project_code || project.id)}`}
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                            >
                                                Open checklists →
                                            </Link>
                                        </div>
                                    </article>
                                );
                            })}

                            {zeroItemProjects.map(({ project }) => (
                                <article
                                    key={`empty-${project.id}`}
                                    className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-slate-900 dark:text-white">{project.name}</p>
                                            <p className="mt-0.5 text-[11px] text-slate-500 truncate">
                                                {project.project_code || "—"} · {project.client?.name || "No client"}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                            No checklists
                                        </span>
                                    </div>
                                    <p className="mt-4 text-[11px] leading-snug text-slate-500">
                                        Seed the default survey checklist or add custom items to start tracking this project's field progress.
                                    </p>
                                    <div className="mt-4 flex justify-end">
                                        <Link
                                            href={route("super.construction.projects.show", project.id) + `#chk-group-default`}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-indigo-600 transition hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-900 dark:text-indigo-400 dark:hover:bg-slate-800"
                                        >
                                            Go to project →
                                        </Link>
                                    </div>
                                </article>
                            ))}
                        </div>
                    );
                })()}
            </SectionCard>

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
                        onClick={() => setCreateModalOpen(true)}
                        className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:from-indigo-500 hover:to-violet-500"
                    >
                        <FaPlus size={13} />
                        New Project
                    </button>
                </div>
            </div>

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
                                    return (
                                        <tr key={project.id} className="hover:bg-slate-50/40 dark:hover:bg-slate-900/40 transition-colors align-top">
                                            <td className="px-3 py-3 max-w-[220px] sm:max-w-none">
                                                <div className="font-semibold text-slate-900 dark:text-white truncate">{project.name}</div>
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
                                                <div className="relative inline-block text-left" data-project-dropdown>
                                                    <button
                                                        type="button"
                                                        onClick={(e) => openProjectDropdown(e, project.id)}
                                                        className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition-colors"
                                                        title="Actions"
                                                    >
                                                        <FaEllipsisV size={14} />
                                                    </button>
                                                    {openDropdownId === project.id && (
                                                        <div
                                                            className="fixed w-44 rounded-xl border border-slate-200 bg-white py-1 shadow-xl z-50 dark:border-slate-700 dark:bg-slate-900"
                                                            style={{ top: dropdownPos.top, left: dropdownPos.left }}
                                                        >
                                                            <Link
                                                                href={route("super.construction.projects.show", project.id)}
                                                                className="flex items-center gap-2.5 px-4 py-2 text-[13px] font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800 transition-colors"
                                                            >
                                                                <FaEye size={13} className="text-indigo-500" />
                                                                View Details
                                                            </Link>
                                                            <button
                                                                type="button"
                                                                onClick={() => { setOpenDropdownId(null); openEditModal(project); }}
                                                                className="w-full flex items-center gap-2.5 px-4 py-2 text-[13px] font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800 transition-colors text-left"
                                                            >
                                                                <FaEdit size={13} className="text-sky-500" />
                                                                Edit
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setOpenDropdownId(null);
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
                                                                className="w-full flex items-center gap-2.5 px-4 py-2 text-[13px] font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/60 transition-colors text-left"
                                                            >
                                                                <FaTrashAlt size={13} />
                                                                Delete
                                                            </button>
                                                        </div>
                                                    )}
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
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
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
                        <SelectField form={form} name="company_id" label="Company" options={companies.map((company) => ({ value: company.id, label: company.name }))} />
                        <SelectField
                            form={form}
                            name="client_id"
                            label="Client"
                            options={filteredClients.length > 0 ? filteredClients.map((client) => ({ value: client.id, label: client.name })) : [{ value: "", label: "— No clients for this company —" }]}
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <InputField form={form} name="name" label="Project Name" />
                        <InputField form={form} name="project_code" label="Project Code (optional, auto-generated)" />
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
                                { value: "budget_pending", label: "Budget Pending" },
                                { value: "planning", label: "Planning" },
                                { value: "survey", label: "Survey" },
                                { value: "foundation", label: "Foundation" },
                                { value: "structure", label: "Structure" },
                                { value: "finishing", label: "Finishing" },
                                { value: "handover", label: "Handover" },
                                { value: "completed", label: "Completed" },
                            ]}
                        />
                    </div>
                    <TextAreaField form={form} name="description" label="Description" rows={3} />
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
                            label="Client Review (optional)"
                            options={[
                                { value: "", label: "-- Not reviewed --" },
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
                        <SelectField form={editForm} name="company_id" label="Company" options={companies.map((company) => ({ value: company.id, label: company.name }))} />
                        <SelectField
                            form={editForm}
                            name="client_id"
                            label="Client"
                            options={editFilteredClients.length > 0 ? editFilteredClients.map((client) => ({ value: client.id, label: client.name })) : [{ value: "", label: "— No clients for this company —" }]}
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
                                { value: "budget_pending", label: "Budget Pending" },
                                { value: "planning", label: "Planning" },
                                { value: "survey", label: "Survey" },
                                { value: "foundation", label: "Foundation" },
                                { value: "structure", label: "Structure" },
                                { value: "finishing", label: "Finishing" },
                                { value: "handover", label: "Handover" },
                                { value: "completed", label: "Completed" },
                            ]}
                        />
                    </div>
                    <TextAreaField form={editForm} name="description" label="Description" rows={3} />
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
                                { value: "", label: "-- Not reviewed --" },
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

function InputField({ form, name, label, type = "text", step }) {
    return (
        <div>
            <label className="mb-1 block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input
                type={type}
                step={step}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
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

function SelectField({ form, name, label, options }) {
    return (
        <div>
            <label className="mb-1 block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <select
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            >
                {options.map((option) => (
                    <option key={String(option.value)} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

