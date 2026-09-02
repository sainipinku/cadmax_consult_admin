import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useForm, router } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

const SLUG_SAFE = (s) =>
    String(s ?? "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, "");

const formatDateTime = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return String(dateString);
    return date.toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const normalizeItem = (raw, sourceKey, groupHeading) => {
    if (!raw) return null;
    return {
        id: String(raw.id),
        source: sourceKey,
        groupHeading: groupHeading || raw.groupHeading || "",
        item_title: raw.item_title || raw.work_title || raw.title || "",
        is_completed: !!raw.is_completed || raw.status === 1 || raw.status === "completed",
        day_number: raw.day_number ?? 0,
        completed_at: raw.completed_at || null,
        completed_by: raw.completed_by
            ? {
                  name:
                      raw.completed_by.name ||
                      raw.completed_by.full_name ||
                      raw.completed_by.member?.name ||
                      "Unknown",
              }
            : null,
        sort_order: raw.sort_order ?? 0,
        scope: raw.scope || sourceKey,
        updated_at: raw.updated_at || raw.created_at || null,
        _raw: raw,
    };
};

const buildGroupsFromProject = (project) => {
    const entries = [];

    (project?.survey_plans || []).forEach((plan) => {
        const heading = `${plan.survey_code || "SP#" + plan.id} ${plan.title || ""}`.trim();
        (plan.checklists || []).forEach((it) => {
            const n = normalizeItem(it, "survey_plan", heading);
            if (n) entries.push({ groupKind: "Survey Plan", groupLabel: heading, item: n });
        });
        (plan.workChecklists || plan.checklist_works || []).forEach((it) => {
            const n = normalizeItem(
                { ...it, item_title: it.work_title, is_completed: it.status === 1, day_number: it.day_number ?? 0 },
                "survey_work",
                heading,
            );
            if (n) entries.push({ groupKind: "Survey Work", groupLabel: heading, item: n });
        });
    });

    (project?.execution_tasks || []).forEach((task) => {
        const heading = `${task.task_code || "TASK#" + task.id} ${task.title || ""}`.trim();
        (task.checklists || []).forEach((it) => {
            const n = normalizeItem(it, "execution_task", heading);
            if (n) entries.push({ groupKind: "Execution Task", groupLabel: heading, item: n });
        });
    });

    (project?.tasks || []).forEach((task) => {
        const heading = `${task.task_code || "UTASK#" + task.id} ${task.title || ""}`.trim();
        (task.checklist_items || task.checklistItems || []).forEach((it) => {
            const n = normalizeItem(it, "unified_task", heading);
            if (n) entries.push({ groupKind: "Dynamic Task", groupLabel: heading, item: n });
        });
    });

    const byGroup = new Map();
    entries.forEach(({ groupKind, groupLabel, item }) => {
        const key = `${groupKind} · ${groupLabel}`;
        if (!byGroup.has(key)) {
            byGroup.set(key, {
                key,
                slug: SLUG_SAFE(key),
                kind: groupKind,
                heading: `${groupKind} · ${groupLabel}`,
                label: groupLabel,
                items: [],
                completed: 0,
                total: 0,
            });
        }
        const grp = byGroup.get(key);
        grp.items.push(item);
        grp.total += 1;
        if (item.is_completed) grp.completed += 1;
    });

    const groups = [...byGroup.values()].map((g) => ({
        ...g,
        items: [...g.items].sort((a, b) => {
            if ((a.day_number || 0) !== (b.day_number || 0))
                return (a.day_number || 0) - (b.day_number || 0);
            if ((a.sort_order || 0) !== (b.sort_order || 0))
                return (a.sort_order || 0) - (b.sort_order || 0);
            return Number(a.id) - Number(b.id);
        }),
    }));

    const byDay = new Map();
    entries.forEach(({ item }) => {
        const day = Number(item.day_number) > 0 ? Number(item.day_number) : 0;
        const key = day > 0 ? `Survey Day ${day}` : "General";
        if (!byDay.has(key)) {
            byDay.set(key, {
                key,
                slug: SLUG_SAFE(key),
                kind: "By Day",
                heading: key,
                label: key,
                items: [],
                completed: 0,
                total: 0,
            });
        }
        const grp = byDay.get(key);
        grp.items.push(item);
        grp.total += 1;
        if (item.is_completed) grp.completed += 1;
    });
    const dayGroups = [...byDay.values()].sort((a, b) => {
        const da = a.label.startsWith("Survey Day ") ? Number(a.label.slice(11)) : 999999;
        const db = b.label.startsWith("Survey Day ") ? Number(b.label.slice(11)) : 999999;
        return da - db;
    });

    const totals = entries.reduce(
        (acc, { item }) => {
            acc.total += 1;
            if (item.is_completed) acc.completed += 1;
            return acc;
        },
        { total: 0, completed: 0 },
    );

    return { groups, dayGroups, totals, itemCount: entries.length };
};

const DELTA_INTERVAL_MS = 15000;

export default function DynamicChecklistManager({
    project,
    namespace = "super",
    variant = "project-show",
    canManage = true,
    enableDeltaPoll = false,
    initialActiveGroup = null,
    limitGroups = null,
    headerExtra = null,
}) {
    const routePrefix =
        namespace === "admin"
            ? "admin.construction.projects"
            : "super.construction.projects";

    const [groupingMode, setGroupingMode] = useState("group");
    const [activeGroup, setActiveGroup] = useState(initialActiveGroup || null);
    const [realtimeError, setRealtimeError] = useState(null);
    const [realtimeConnected, setRealtimeConnected] = useState(false);
    const [editing, setEditing] = useState(null);
    const deltaRef = useRef({ lastAt: null, stopped: false });

    const initialSeed = useForm({
        day_number: 1,
        target_scope: "all",
        survey_plan_id: project?.survey_plans?.[0]?.id || "",
        execution_task_id: "",
    });

    const initialCustom = useForm({
        scope: "survey_plan",
        survey_plan_id: project?.survey_plans?.[0]?.id || "",
        execution_task_id: project?.execution_tasks?.[0]?.id || "",
        unified_task_id: project?.tasks?.[0]?.id || "",
        day_number: 1,
        item_title: "",
    });

    const editForm = useForm({
        item_title: "",
        day_number: 1,
        is_completed: false,
    });

    useEffect(() => {
        if (editing) {
            editForm.setData({
                item_title: editing.item?.item_title || "",
                day_number: editing.item?.day_number || 1,
                is_completed: !!editing.item?.is_completed,
            });
        }
    }, [editing]);

    const built = useMemo(() => buildGroupsFromProject(project), [project]);
    const activeGroups = groupingMode === "day" ? built.dayGroups : built.groups;
    const slicedGroups = typeof limitGroups === "number" ? activeGroups.slice(0, limitGroups) : activeGroups;

    const completionPct = built.totals.total
        ? Math.round((built.totals.completed / built.totals.total) * 100)
        : 0;

    const scrollToItem = useCallback((hash) => {
        if (typeof window === "undefined" || !hash || hash.length < 2) return;
        setTimeout(() => {
            const el = document.querySelector(hash);
            if (el && typeof el.scrollIntoView === "function") {
                el.scrollIntoView({ behavior: "smooth", block: "start" });
                el.classList.add("ring-2", "ring-indigo-400", "ring-offset-2");
                setTimeout(() => {
                    el.classList.remove("ring-2", "ring-indigo-400", "ring-offset-2");
                }, 2200);
            }
        }, 120);
    }, []);

    useEffect(() => {
        const onHash = () => scrollToItem(window.location.hash || "");
        onHash();
        window.addEventListener("hashchange", onHash);
        return () => window.removeEventListener("hashchange", onHash);
    }, [scrollToItem, built.totals.itemCount]);

    useEffect(() => {
        if (!enableDeltaPoll || variant === "dashboard-index") return undefined;
        deltaRef.current.lastAt = project?.updated_at
            ? new Date(project.updated_at)
            : new Date();
        setRealtimeConnected(true);
        const poll = async () => {
            if (deltaRef.current.stopped) return;
            try {
                const since = deltaRef.current.lastAt
                    ? new Date(deltaRef.current.lastAt).toISOString()
                    : new Date().toISOString();
                const url =
                    "/api/construction/projects/" +
                    encodeURIComponent(project.id) +
                    "/tasks/delta?since=" +
                    encodeURIComponent(since);
                const res = await fetch(url, {
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                });
                if (!res.ok) {
                    throw new Error("HTTP " + res.status);
                }
                const payload = await res.json();
                if (payload?.success && payload?.data?.has_updates) {
                    router.reload({ only: ["project"], preserveScroll: true, preserveState: false });
                }
                deltaRef.current.lastAt = new Date();
                setRealtimeError(null);
                setRealtimeConnected(true);
            } catch (err) {
                setRealtimeError(err.message || "Realtime sync unavailable");
                setRealtimeConnected(false);
            }
        };
        const interval = setInterval(poll, DELTA_INTERVAL_MS);
        return () => {
            deltaRef.current.stopped = true;
            clearInterval(interval);
        };
    }, [enableDeltaPoll, project?.id, variant]);

    const runToggle = (group, item) => {
        if (!canManage) return;
        if (![204, 200, 401, 403]) { /* noop */ }
        router.patch(
            route(`${routePrefix}.checklists.toggle`, [project.id, item.id]),
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    if (first) toast.error(first);
                    else toast.error("Failed to toggle checklist item.");
                },
            },
        );
    };

    const runDelete = (group, item) => {
        if (!canManage) return;
        router.delete(route(`${routePrefix}.checklists.destroy`, [project.id, item.id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (editing?.item?.id === item.id) setEditing(null);
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
                else toast.error("Failed to delete checklist item.");
            },
        });
    };

    const submitEdit = (e) => {
        e.preventDefault();
        if (!editing?.item?.id) return;
        router.put(
            route(`${routePrefix}.checklists.update`, [project.id, editing.item.id]),
            { ...editForm.data },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    if (first) toast.error(first);
                    else toast.error("Failed to update checklist item.");
                },
            },
        );
    };

    const submitCustom = (e) => {
        e.preventDefault();
        router.post(route(`${routePrefix}.checklists.store`, project.id), initialCustom.data, {
            preserveScroll: true,
            onSuccess: () => initialCustom.reset("item_title"),
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
                else toast.error("Failed to add checklist item.");
            },
        });
    };

    const submitSeed = (e) => {
        e.preventDefault();
        initialSeed.post(route(`${routePrefix}.checklists.seed-defaults`, project.id), {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
                else toast.error("Failed to seed default checklists.");
            },
        });
    };

    return (
        <div className="w-full space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                                Dynamic Checklist Manager
                            </h2>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {built.totals.completed}/{built.totals.total} items complete • {completionPct}%
                                {enableDeltaPoll && variant !== "dashboard-index" ? (
                                    realtimeConnected ? (
                                        <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                                            Live delta sync
                                        </span>
                                    ) : (
                                        <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                            Sync offline
                                        </span>
                                    )
                                ) : null}
                            </p>
                        </div>
                    </div>
                    <div className="mt-3 h-3 w-full max-w-xl overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            className="h-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-500"
                            style={{ width: `${completionPct}%` }}
                        />
                    </div>
                    {realtimeError ? (
                        <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                            Realtime background sync encountered an error: {realtimeError}. Page reload preserves data.
                        </div>
                    ) : null}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="inline-flex rounded-xl border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-900">
                        <button
                            type="button"
                            onClick={() => setGroupingMode("group")}
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                                groupingMode === "group"
                                    ? "bg-indigo-600 text-white shadow"
                                    : "text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            Group by Task
                        </button>
                        <button
                            type="button"
                            onClick={() => setGroupingMode("day")}
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                                groupingMode === "day"
                                    ? "bg-indigo-600 text-white shadow"
                                    : "text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            Group by Day
                        </button>
                    </div>
                    {headerExtra || null}
                </div>
            </div>

            {variant === "project-show" && canManage ? (
                <div className="grid gap-4 lg:grid-cols-2">
                    <form
                        onSubmit={submitSeed}
                        className="grid gap-3 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900/50 dark:bg-indigo-950/20"
                    >
                        <div>
                            <p className="text-sm font-semibold text-indigo-900 dark:text-indigo-100">
                                Seed Default Checklist Items
                            </p>
                            <p className="mt-1 text-xs text-indigo-700/80 dark:text-indigo-200/70">
                                Idempotent deployment of survey default checklists. Safe to re-run.
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <MiniInput label="Survey Day" type="number">
                                <input
                                    type="number"
                                    value={initialSeed.data.day_number}
                                    onChange={(e) => initialSeed.setData("day_number", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </MiniInput>
                            <MiniSelect
                                label="Target Scope"
                                value={initialSeed.data.target_scope}
                                onChange={(v) => initialSeed.setData("target_scope", v)}
                                options={[
                                    { value: "default", label: "Default (first plan + task)" },
                                    { value: "survey_plan", label: "All Survey Plans" },
                                    { value: "execution_task", label: "All Execution Tasks" },
                                    { value: "all", label: "All of the above" },
                                ]}
                            />
                            <MiniSelect
                                label="Survey Plan (optional)"
                                value={initialSeed.data.survey_plan_id}
                                onChange={(v) => initialSeed.setData("survey_plan_id", v)}
                                options={[
                                    { value: "", label: "-- First plan by default --" },
                                    ...(project?.survey_plans || []).map((p) => ({
                                        value: p.id,
                                        label: `${p.survey_code || "SP#" + p.id} - ${p.title}`,
                                    })),
                                ]}
                            />
                            <MiniSelect
                                label="Execution Task (optional)"
                                value={initialSeed.data.execution_task_id}
                                onChange={(v) => initialSeed.setData("execution_task_id", v)}
                                options={[
                                    { value: "", label: "-- First task by default --" },
                                    ...(project?.execution_tasks || []).map((t) => ({
                                        value: t.id,
                                        label: `${t.task_code || "TASK#" + t.id} - ${t.title}`,
                                    })),
                                ]}
                            />
                        </div>
                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={initialSeed.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {initialSeed.processing ? "Seeding..." : "Seed Default Checklist"}
                            </button>
                        </div>
                    </form>

                    <form
                        onSubmit={submitCustom}
                        className="grid gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
                    >
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Add Custom Checklist Item
                            </p>
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Attaches to a survey plan / execution task / dynamic task immediately.
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <MiniSelect
                                label="Attach to"
                                value={initialCustom.data.scope}
                                onChange={(v) => initialCustom.setData("scope", v)}
                                options={[
                                    { value: "survey_plan", label: "Survey Plan" },
                                    { value: "execution_task", label: "Execution Task" },
                                    { value: "unified_task", label: "Dynamic Task (unified)" },
                                    { value: "project", label: "Project-wide" },
                                ]}
                            />
                            {initialCustom.data.scope === "survey_plan" ? (
                                <MiniSelect
                                    label="Survey Plan"
                                    value={initialCustom.data.survey_plan_id}
                                    onChange={(v) => initialCustom.setData("survey_plan_id", v)}
                                    options={[
                                        { value: "", label: "-- Select plan --" },
                                        ...(project?.survey_plans || []).map((p) => ({
                                            value: p.id,
                                            label: `${p.survey_code || "SP#" + p.id} - ${p.title}`,
                                        })),
                                    ]}
                                />
                            ) : initialCustom.data.scope === "execution_task" ? (
                                <MiniSelect
                                    label="Execution Task"
                                    value={initialCustom.data.execution_task_id}
                                    onChange={(v) => initialCustom.setData("execution_task_id", v)}
                                    options={[
                                        { value: "", label: "-- Select task --" },
                                        ...(project?.execution_tasks || []).map((t) => ({
                                            value: t.id,
                                            label: `${t.task_code || "TASK#" + t.id} - ${t.title}`,
                                        })),
                                    ]}
                                />
                            ) : initialCustom.data.scope === "unified_task" ? (
                                <MiniSelect
                                    label="Dynamic Task"
                                    value={initialCustom.data.unified_task_id}
                                    onChange={(v) => initialCustom.setData("unified_task_id", v)}
                                    options={[
                                        { value: "", label: "-- Select task --" },
                                        ...(project?.tasks || []).map((t) => ({
                                            value: t.id,
                                            label: `${t.task_code || "UTASK#" + t.id} - ${t.title}`,
                                        })),
                                    ]}
                                />
                            ) : (
                                <div />
                            )}
                            <MiniInput label="Day Number" type="number">
                                <input
                                    type="number"
                                    value={initialCustom.data.day_number}
                                    onChange={(e) => initialCustom.setData("day_number", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </MiniInput>
                            <MiniInput label="Item Title" type="text">
                                <input
                                    type="text"
                                    value={initialCustom.data.item_title}
                                    onChange={(e) => initialCustom.setData("item_title", e.target.value)}
                                    placeholder="e.g. Upload survey field notes"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </MiniInput>
                        </div>
                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={initialCustom.processing}
                                className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                            >
                                {initialCustom.processing ? "Adding..." : "Add Checklist Item"}
                            </button>
                        </div>
                    </form>
                </div>
            ) : null}

            {!slicedGroups.length ? (
                <EmptyState
                    title="No checklist items yet."
                    description="Use the forms above (or seed defaults) to deploy the survey checklist structure. New items added via API will appear here automatically."
                />
            ) : (
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-2">
                    {slicedGroups.map((group) => {
                        const pct = group.total ? Math.round((group.completed / group.total) * 100) : 0;
                        const anchorGroup = `chk-group-${group.slug || SLUG_SAFE(group.key)}`;
                        return (
                            <section
                                key={group.key}
                                id={anchorGroup}
                                className="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/40"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                                <a
                                                    href={`#${anchorGroup}`}
                                                    className="hover:text-indigo-600 dark:hover:text-indigo-300"
                                                >
                                                    {group.heading}
                                                </a>
                                            </h3>
                                            {typeof limitGroups === "number" ? (
                                                <a
                                                    href={`#${anchorGroup}`}
                                                    onClick={(e) => {
                                                        if (variant === "dashboard-index") {
                                                            e.preventDefault();
                                                            router.visit(
                                                                route(`${routePrefix}.show`, project.id) +
                                                                    `#${anchorGroup}`,
                                                            );
                                                        }
                                                    }}
                                                    className="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300"
                                                >
                                                    Open details →
                                                </a>
                                            ) : null}
                                        </div>
                                        <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                            {group.completed}/{group.total} items • {pct}%
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge
                                            value={
                                                group.total === 0
                                                    ? "planned"
                                                    : group.completed === group.total
                                                    ? "completed"
                                                    : "in_progress"
                                            }
                                        />
                                        {activeGroup === group.key ? (
                                            <button
                                                type="button"
                                                onClick={() => setActiveGroup(null)}
                                                className="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                            >
                                                Collapse
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() => setActiveGroup(group.key)}
                                                className="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                            >
                                                Focus
                                            </button>
                                        )}
                                    </div>
                                </div>
                                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                    <div
                                        className="h-full bg-emerald-500 transition-all duration-500"
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                                {activeGroup === null || activeGroup === group.key ? (
                                    <ul className="mt-4 space-y-2">
                                        {group.items.map((item) => {
                                            const itemAnchor = `chk-item-${group.slug || SLUG_SAFE(group.key)}-${item.id}`;
                                            return (
                                                <li
                                                    key={`${item.source}-${item.id}`}
                                                    id={itemAnchor}
                                                    className="scroll-mt-24 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                                                >
                                                    <label className="flex flex-1 items-start gap-3 min-w-0">
                                                        <input
                                                            type="checkbox"
                                                            disabled={!canManage}
                                                            checked={!!item.is_completed}
                                                            onChange={() => runToggle(group, item)}
                                                            className="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600"
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <p
                                                                className={`break-words text-sm font-medium ${
                                                                    item.is_completed
                                                                        ? "line-through text-slate-400"
                                                                        : "text-slate-900 dark:text-white"
                                                                }`}
                                                            >
                                                                <a
                                                                    href={`#${itemAnchor}`}
                                                                    className="hover:text-indigo-600 dark:hover:text-indigo-300"
                                                                >
                                                                    {item.item_title || "(untitled item)"}
                                                                </a>
                                                            </p>
                                                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                                {Number(item.day_number) > 0
                                                                    ? `Day ${item.day_number} • `
                                                                    : ""}
                                                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] uppercase tracking-wide dark:bg-slate-800">
                                                                    {item.source}
                                                                </span>
                                                                {item.completed_at
                                                                    ? ` • completed ${formatDateTime(item.completed_at)}`
                                                                    : ""}
                                                                {item.completed_by?.name
                                                                    ? ` • by ${item.completed_by.name}`
                                                                    : ""}
                                                            </p>
                                                        </div>
                                                    </label>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={!canManage}
                                                            onClick={() =>
                                                                setEditing({
                                                                    group,
                                                                    item,
                                                                })
                                                            }
                                                            className="rounded-lg bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={!canManage}
                                                            onClick={() => runDelete(group, item)}
                                                            className="rounded-lg bg-rose-50 px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100 disabled:opacity-50 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/30"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                ) : null}
                            </section>
                        );
                    })}
                </div>
            )}

            {editing ? (
                <Modal open={true} onClose={() => setEditing(null)}>
                    <form onSubmit={submitEdit} className="grid gap-4 p-6">
                        <div>
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                Edit Checklist Item
                            </h3>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {editing.group?.heading || ""}
                            </p>
                        </div>
                        <MiniInput label="Item Title" type="text">
                            <input
                                type="text"
                                value={editForm.data.item_title}
                                onChange={(e) => editForm.setData("item_title", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </MiniInput>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <MiniInput label="Day Number" type="number">
                                <input
                                    type="number"
                                    value={editForm.data.day_number}
                                    onChange={(e) => editForm.setData("day_number", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </MiniInput>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={!!editForm.data.is_completed}
                                    onChange={(e) => editForm.setData("is_completed", e.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                />
                                <span className="text-sm text-slate-700 dark:text-slate-200">Marked complete</span>
                            </label>
                        </div>
                        <div className="flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setEditing(null)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {editForm.processing ? "Saving..." : "Save Changes"}
                            </button>
                        </div>
                    </form>
                </Modal>
            ) : null}
        </div>
    );
}

function MiniInput({ label, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{label}</span>
            {children}
        </label>
    );
}

function MiniSelect({ label, value, onChange, options }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{label}</span>
            <select
                value={value}
                onChange={(e) => onChange && onChange(e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

function Modal({ open, onClose, children }) {
    useEffect(() => {
        if (!open) return undefined;
        const handler = (e) => {
            if (e.key === "Escape") onClose && onClose();
        };
        document.addEventListener("keydown", handler);
        return () => document.removeEventListener("keydown", handler);
    }, [open, onClose]);
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
                onClick={() => onClose && onClose()}
            />
            <div className="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                <div className="flex justify-end p-2">
                    <button
                        type="button"
                        onClick={() => onClose && onClose()}
                        aria-label="Close"
                        className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
