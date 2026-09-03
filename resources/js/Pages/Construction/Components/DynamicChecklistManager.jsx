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
    const isDone = !!raw.is_completed || raw.status === 1 || raw.status === "completed";
    return {
        id: String(raw.id),
        source: sourceKey,
        groupHeading: groupHeading || raw.groupHeading || "",
        item_title: raw.item_title || raw.work_title || raw.title || "",
        assign_hours: raw.assign_hours != null && raw.assign_hours !== "" ? Number(raw.assign_hours) : null,
        notes: raw.notes || raw.note || raw.remarks || "",
        status: raw.status || (isDone ? "completed" : "pending"),
        image_url_1: raw.image_url_1 || null,
        image_url_2: raw.image_url_2 || null,
        is_completed: isDone,
        day_number: raw.day_number ?? 1,
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
                { ...it, item_title: it.work_title, is_completed: it.status === 1, day_number: it.day_number ?? 1 },
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
        const day = Number(item.day_number) > 0 ? Number(item.day_number) : 1;
        const key = `Survey Day ${day}`;
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
    const [selectedTaskKey, setSelectedTaskKey] = useState("all");
    const [editing, setEditing] = useState(null);
    const [previewImage, setPreviewImage] = useState(null);

    // Form for creating new checklist item under selected task
    const createForm = useForm({
        scope: "execution_task",
        execution_task_id: "",
        survey_plan_id: "",
        unified_task_id: "",
        day_number: 1,
        item_title: "",
        assign_hours: "",
        notes: "",
        status: "pending",
        is_completed: false,
        image_1: null,
        image_2: null,
    });

    const editForm = useForm({
        day_number: 1,
        item_title: "",
        assign_hours: "",
        notes: "",
        status: "pending",
        is_completed: false,
        image_1: null,
        image_2: null,
        remove_image_1: false,
        remove_image_2: false,
    });

    const [img1Preview, setImg1Preview] = useState(null);
    const [img2Preview, setImg2Preview] = useState(null);
    const [editImg1Preview, setEditImg1Preview] = useState(null);
    const [editImg2Preview, setEditImg2Preview] = useState(null);

    // Extract all project tasks (execution tasks, survey plans, unified tasks)
    const taskOptions = useMemo(() => {
        const list = [];

        (project?.execution_tasks || []).forEach((t) => {
            list.push({
                key: `exec-${t.id}`,
                scope: "execution_task",
                id: t.id,
                code: t.task_code || `TASK#${t.id}`,
                title: t.title,
                label: `[Execution Task] ${t.task_code || "TASK#" + t.id} - ${t.title}`,
                supervisor: t.supervisor?.name,
                status: t.status,
            });
        });

        (project?.tasks || []).forEach((t) => {
            list.push({
                key: `unified-${t.id}`,
                scope: "unified_task",
                id: t.id,
                code: t.task_code || `UTASK#${t.id}`,
                title: t.title,
                label: `[Dynamic Task] ${t.task_code || "UTASK#" + t.id} - ${t.title}`,
                supervisor: t.supervisor?.name,
                status: t.status,
            });
        });

        (project?.survey_plans || []).forEach((sp) => {
            list.push({
                key: `survey-${sp.id}`,
                scope: "survey_plan",
                id: sp.id,
                code: sp.survey_code || `SP#${sp.id}`,
                title: sp.title,
                label: `[Survey Plan] ${sp.survey_code || "SP#" + sp.id} - ${sp.title}`,
                supervisor: null,
                status: sp.status,
            });
        });

        return list;
    }, [project]);

    // Preselect first task if available
    useEffect(() => {
        if (taskOptions.length > 0 && selectedTaskKey === "all") {
            const first = taskOptions[0];
            setSelectedTaskKey(first.key);
            updateCreateFormScope(first);
        }
    }, [taskOptions]);

    const updateCreateFormScope = (taskOpt) => {
        if (!taskOpt) return;
        if (taskOpt.scope === "execution_task") {
            createForm.setData((prev) => ({
                ...prev,
                scope: "execution_task",
                execution_task_id: taskOpt.id,
                survey_plan_id: "",
                unified_task_id: "",
            }));
        } else if (taskOpt.scope === "unified_task") {
            createForm.setData((prev) => ({
                ...prev,
                scope: "unified_task",
                unified_task_id: taskOpt.id,
                execution_task_id: "",
                survey_plan_id: "",
            }));
        } else if (taskOpt.scope === "survey_plan") {
            createForm.setData((prev) => ({
                ...prev,
                scope: "survey_plan",
                survey_plan_id: taskOpt.id,
                execution_task_id: "",
                unified_task_id: "",
            }));
        }
    };

    const handleTaskChange = (e) => {
        const val = e.target.value;
        setSelectedTaskKey(val);
        const opt = taskOptions.find((t) => t.key === val);
        if (opt) {
            updateCreateFormScope(opt);
        }
    };

    useEffect(() => {
        if (editing) {
            editForm.setData({
                day_number: editing.item?.day_number || 1,
                item_title: editing.item?.item_title || "",
                assign_hours: editing.item?.assign_hours ?? "",
                notes: editing.item?.notes || "",
                status: editing.item?.status || (editing.item?.is_completed ? "completed" : "pending"),
                is_completed: !!editing.item?.is_completed,
                image_1: null,
                image_2: null,
                remove_image_1: false,
                remove_image_2: false,
            });
            setEditImg1Preview(editing.item?.image_url_1 || null);
            setEditImg2Preview(editing.item?.image_url_2 || null);
        }
    }, [editing]);

    const built = useMemo(() => buildGroupsFromProject(project), [project]);
    const activeGroups = groupingMode === "day" ? built.dayGroups : built.groups;
    const filteredGroups = useMemo(() => {
        if (selectedTaskKey === "all") return activeGroups;
        const opt = taskOptions.find((t) => t.key === selectedTaskKey);
        if (!opt) return activeGroups;
        return activeGroups.filter((g) =>
            g.heading.toLowerCase().includes(opt.title.toLowerCase()) ||
            g.heading.toLowerCase().includes(opt.code.toLowerCase())
        );
    }, [activeGroups, selectedTaskKey, taskOptions]);

    const slicedGroups = typeof limitGroups === "number" ? filteredGroups.slice(0, limitGroups) : filteredGroups;
    const completionPct = built.totals.total
        ? Math.round((built.totals.completed / built.totals.total) * 100)
        : 0;

    const handleImage1Change = (e, setForm, setPreview) => {
        const file = e.target.files?.[0];
        if (file) {
            setForm("image_1", file);
            setPreview(URL.createObjectURL(file));
        }
    };

    const handleImage2Change = (e, setForm, setPreview) => {
        const file = e.target.files?.[0];
        if (file) {
            setForm("image_2", file);
            setPreview(URL.createObjectURL(file));
        }
    };

    const removeImage1 = (setForm, setPreview, isEdit = false) => {
        setForm("image_1", null);
        if (isEdit) setForm("remove_image_1", true);
        setPreview(null);
    };

    const removeImage2 = (setForm, setPreview, isEdit = false) => {
        setForm("image_2", null);
        if (isEdit) setForm("remove_image_2", true);
        setPreview(null);
    };

    const submitCreate = (e) => {
        e.preventDefault();
        if (!createForm.data.item_title.trim()) {
            toast.error("Please enter a title for the checklist item.");
            return;
        }

        router.post(route(`${routePrefix}.checklists.store`, project.id), createForm.data, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                createForm.reset("item_title", "assign_hours", "notes", "image_1", "image_2");
                createForm.setData("status", "pending");
                setImg1Preview(null);
                setImg2Preview(null);
                toast.success("Checklist item created successfully.");
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
                else toast.error("Failed to add checklist item.");
            },
        });
    };

    const submitEdit = (e) => {
        e.preventDefault();
        if (!editing?.item?.id) return;

        router.post(
            route(`${routePrefix}.checklists.update`, [project.id, editing.item.id]),
            {
                _method: "PUT",
                ...editForm.data,
            },
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    setEditing(null);
                    toast.success("Checklist item updated.");
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    if (first) toast.error(first);
                    else toast.error("Failed to update checklist item.");
                },
            }
        );
    };

    const runToggle = (group, item) => {
        if (!canManage) return;
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
            }
        );
    };

    const runDelete = (group, item) => {
        if (!canManage) return;
        if (!confirm("Are you sure you want to delete this checklist item?")) return;
        router.delete(route(`${routePrefix}.checklists.destroy`, [project.id, item.id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (editing?.item?.id === item.id) setEditing(null);
                toast.success("Checklist item deleted.");
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
                else toast.error("Failed to delete checklist item.");
            },
        });
    };

    const selectedTask = useMemo(
        () => taskOptions.find((t) => t.key === selectedTaskKey),
        [taskOptions, selectedTaskKey]
    );

    return (
        <div className="w-full space-y-6">
            {/* Header & Metrics */}
            <div className="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                            📋
                        </span>
                        <div>
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                                Dynamic Checklist Manager
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Total {built.totals.completed} of {built.totals.total} tasks completed ({completionPct}%)
                            </p>
                        </div>
                    </div>
                    <div className="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            className="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-500 transition-all duration-500"
                            style={{ width: `${completionPct}%` }}
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <div className="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-950">
                        <button
                            type="button"
                            onClick={() => setGroupingMode("group")}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                groupingMode === "group"
                                    ? "bg-indigo-600 text-white shadow-sm"
                                    : "text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            Group by Task
                        </button>
                        <button
                            type="button"
                            onClick={() => setGroupingMode("day")}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                groupingMode === "day"
                                    ? "bg-indigo-600 text-white shadow-sm"
                                    : "text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-800"
                            }`}
                        >
                            Group by Day
                        </button>
                    </div>
                    {headerExtra || null}
                </div>
            </div>

            {/* Task Selection & Creation Flow */}
            {variant === "project-show" && canManage ? (
                <div className="rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50/40 via-white to-purple-50/30 p-5 shadow-sm dark:border-indigo-950 dark:from-slate-900 dark:to-indigo-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-100/80 pb-4 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-indigo-950 dark:text-indigo-200">
                                Step 1: Select Task & Add Checklist Items
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Select any assigned task below to attach dynamic checklists with title, hours, status, notes & images.
                            </p>
                        </div>

                        {/* Task Select Dropdown */}
                        <div className="min-w-[260px]">
                            <select
                                value={selectedTaskKey}
                                onChange={handleTaskChange}
                                className="w-full rounded-xl border border-indigo-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-indigo-800 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="all">-- All Project Tasks ({taskOptions.length}) --</option>
                                {taskOptions.map((opt) => (
                                    <option key={opt.key} value={opt.key}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Active Selected Task Badge Card */}
                    {selectedTask ? (
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-200/70 bg-indigo-50/70 px-4 py-2.5 text-xs dark:border-indigo-900/40 dark:bg-indigo-950/40">
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-indigo-900 dark:text-indigo-200">Selected Task:</span>
                                <span className="rounded-md bg-indigo-600 px-2 py-0.5 font-mono text-[11px] text-white">
                                    {selectedTask.code}
                                </span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">{selectedTask.title}</span>
                            </div>
                            {selectedTask.status ? (
                                <StatusBadge value={selectedTask.status} />
                            ) : null}
                        </div>
                    ) : null}

                    {/* Form to Add Checklist Item */}
                    <form onSubmit={submitCreate} className="mt-4 space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="lg:col-span-2">
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Checklist Title <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={createForm.data.item_title}
                                    onChange={(e) => createForm.setData("item_title", e.target.value)}
                                    placeholder="e.g. Conduct elevation survey and record boundary coordinates"
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Assign Hours <span className="text-slate-400 font-normal">(Optional)</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={createForm.data.assign_hours}
                                    onChange={(e) => createForm.setData("assign_hours", e.target.value)}
                                    placeholder="e.g. 2.5"
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Status
                                </label>
                                <select
                                    value={createForm.data.status}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        createForm.setData("status", val);
                                        createForm.setData("is_completed", val === "completed");
                                    }}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Survey Day #
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={createForm.data.day_number}
                                    onChange={(e) => createForm.setData("day_number", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Notes <span className="text-slate-400 font-normal">(Optional)</span>
                                </label>
                                <input
                                    type="text"
                                    value={createForm.data.notes}
                                    onChange={(e) => createForm.setData("notes", e.target.value)}
                                    placeholder="Add optional notes or field remarks..."
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>
                        </div>

                        {/* Image Attachments with Remove Image Button */}
                        <div className="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <span className="mb-2 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Photo Attachments (Max 2 images)
                            </span>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* Image 1 */}
                                <div className="space-y-2">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 1</label>
                                    {img1Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                                            <img src={img1Preview} alt="Preview 1" className="h-20 w-32 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage1(createForm.setData, setImg1Preview)}
                                                className="absolute right-1 top-1 rounded-full bg-rose-600 p-1 text-[10px] text-white shadow hover:bg-rose-700"
                                                title="Remove Image 1"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage1Change(e, createForm.setData, setImg1Preview)}
                                            className="block w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                        />
                                    )}
                                </div>

                                {/* Image 2 */}
                                <div className="space-y-2">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 2</label>
                                    {img2Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                                            <img src={img2Preview} alt="Preview 2" className="h-20 w-32 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage2(createForm.setData, setImg2Preview)}
                                                className="absolute right-1 top-1 rounded-full bg-rose-600 p-1 text-[10px] text-white shadow hover:bg-rose-700"
                                                title="Remove Image 2"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage2Change(e, createForm.setData, setImg2Preview)}
                                            className="block w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                        />
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={createForm.processing}
                                className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {createForm.processing ? "Saving Checklist..." : "➕ Add Checklist Item"}
                            </button>
                        </div>
                    </form>
                </div>
            ) : null}

            {/* Checklist Items Display List */}
            {!slicedGroups.length ? (
                <EmptyState
                    title="No checklist items found."
                    description="Select a task above and create checklist items to track execution details."
                />
            ) : (
                <div className="grid gap-5 md:grid-cols-2">
                    {slicedGroups.map((group) => {
                        const pct = group.total ? Math.round((group.completed / group.total) * 100) : 0;
                        const anchorGroup = `chk-group-${group.slug || SLUG_SAFE(group.key)}`;
                        return (
                            <section
                                key={group.key}
                                id={anchorGroup}
                                className="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-slate-800">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate text-sm font-bold text-slate-900 dark:text-white">
                                            {group.heading}
                                        </h3>
                                        <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                            {group.completed}/{group.total} completed • {pct}%
                                        </p>
                                    </div>
                                    <StatusBadge
                                        value={
                                            group.total === 0
                                                ? "planned"
                                                : group.completed === group.total
                                                ? "completed"
                                                : "in_progress"
                                        }
                                    />
                                </div>

                                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        className="h-full bg-emerald-500 transition-all duration-500"
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>

                                <ul className="mt-4 space-y-3">
                                    {group.items.map((item) => (
                                        <li
                                            key={`${item.source}-${item.id}`}
                                            className="rounded-xl border border-slate-200/90 bg-slate-50/50 p-3.5 transition hover:border-indigo-200 dark:border-slate-800 dark:bg-slate-950/60"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <label className="flex flex-1 items-start gap-3 min-w-0 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        disabled={!canManage}
                                                        checked={!!item.is_completed}
                                                        onChange={() => runToggle(group, item)}
                                                        className="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    />
                                                    <div className="min-w-0 flex-1">
                                                        <p
                                                            className={`break-words text-xs font-semibold ${
                                                                item.is_completed
                                                                    ? "line-through text-slate-400 dark:text-slate-500"
                                                                    : "text-slate-900 dark:text-white"
                                                            }`}
                                                        >
                                                            {item.item_title || "(Untitled Item)"}
                                                        </p>

                                                        {/* Badges for Assign Hours, Status & Day */}
                                                        <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                                            {item.assign_hours ? (
                                                                <span className="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                                                    ⏱️ {item.assign_hours} hrs
                                                                </span>
                                                            ) : null}

                                                            <span className={`inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase ${
                                                                item.is_completed
                                                                    ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300"
                                                                    : item.status === "in_progress"
                                                                    ? "bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300"
                                                                    : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                                            }`}>
                                                                {item.status || (item.is_completed ? "Completed" : "Pending")}
                                                            </span>

                                                            {item.day_number ? (
                                                                <span className="rounded-md bg-purple-50 px-2 py-0.5 text-[10px] font-medium text-purple-700 dark:bg-purple-900/20 dark:text-purple-300">
                                                                    Day {item.day_number}
                                                                </span>
                                                            ) : null}
                                                        </div>

                                                        {/* Notes snippet */}
                                                        {item.notes ? (
                                                            <p className="mt-2 rounded-lg bg-white p-2 text-[11px] text-slate-600 italic border border-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                                                💬 {item.notes}
                                                            </p>
                                                        ) : null}

                                                        {/* Images preview thumbnails */}
                                                        {(item.image_url_1 || item.image_url_2) ? (
                                                            <div className="mt-2.5 flex items-center gap-2">
                                                                {item.image_url_1 ? (
                                                                    <img
                                                                        src={item.image_url_1}
                                                                        alt="Img 1"
                                                                        onClick={() => setPreviewImage(item.image_url_1)}
                                                                        className="h-12 w-16 cursor-pointer rounded-lg border border-slate-200 object-cover shadow-sm hover:opacity-90"
                                                                    />
                                                                ) : null}
                                                                {item.image_url_2 ? (
                                                                    <img
                                                                        src={item.image_url_2}
                                                                        alt="Img 2"
                                                                        onClick={() => setPreviewImage(item.image_url_2)}
                                                                        className="h-12 w-16 cursor-pointer rounded-lg border border-slate-200 object-cover shadow-sm hover:opacity-90"
                                                                    />
                                                                ) : null}
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </label>

                                                {/* Action Buttons */}
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        type="button"
                                                        disabled={!canManage}
                                                        onClick={() => setEditing({ group, item })}
                                                        className="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={!canManage}
                                                        onClick={() => runDelete(group, item)}
                                                        className="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        );
                    })}
                </div>
            )}

            {/* Edit Modal */}
            {editing ? (
                <Modal open={true} onClose={() => setEditing(null)}>
                    <form onSubmit={submitEdit} className="grid gap-4 p-6">
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Edit Checklist Item
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {editing.group?.heading}
                            </p>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Item Title
                            </label>
                            <input
                                type="text"
                                required
                                value={editForm.data.item_title}
                                onChange={(e) => editForm.setData("item_title", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Assign Hours
                                </label>
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={editForm.data.assign_hours}
                                    onChange={(e) => editForm.setData("assign_hours", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Status
                                </label>
                                <select
                                    value={editForm.data.status}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        editForm.setData("status", val);
                                        editForm.setData("is_completed", val === "completed");
                                    }}
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Notes
                            </label>
                            <textarea
                                rows={2}
                                value={editForm.data.notes}
                                onChange={(e) => editForm.setData("notes", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        {/* Image Attachments with Remove Option */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
                            <span className="mb-2 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Update Photos & Attachments
                            </span>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 1</label>
                                    {editImg1Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200">
                                            <img src={editImg1Preview} alt="Edit Img 1" className="h-16 w-24 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage1(editForm.setData, setEditImg1Preview, true)}
                                                className="absolute right-1 top-1 rounded bg-rose-600 px-1.5 py-0.5 text-[9px] text-white shadow"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage1Change(e, editForm.setData, setEditImg1Preview)}
                                            className="block w-full text-xs text-slate-500"
                                        />
                                    )}
                                </div>

                                <div className="space-y-1">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 2</label>
                                    {editImg2Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200">
                                            <img src={editImg2Preview} alt="Edit Img 2" className="h-16 w-24 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage2(editForm.setData, setEditImg2Preview, true)}
                                                className="absolute right-1 top-1 rounded bg-rose-600 px-1.5 py-0.5 text-[9px] text-white shadow"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage2Change(e, editForm.setData, setEditImg2Preview)}
                                            className="block w-full text-xs text-slate-500"
                                        />
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                onClick={() => setEditing(null)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {editForm.processing ? "Saving..." : "Save Changes"}
                            </button>
                        </div>
                    </form>
                </Modal>
            ) : null}

            {/* Lightbox Modal for Image Zoom Preview */}
            {previewImage ? (
                <Modal open={true} onClose={() => setPreviewImage(null)}>
                    <div className="p-4 text-center">
                        <img src={previewImage} alt="Attachment Full View" className="max-h-[80vh] w-auto mx-auto rounded-xl object-contain" />
                        <button
                            type="button"
                            onClick={() => setPreviewImage(null)}
                            className="mt-3 rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white"
                        >
                            Close Image
                        </button>
                    </div>
                </Modal>
            ) : null}
        </div>
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
                className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
                onClick={() => onClose && onClose()}
            />
            <div className="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                <div className="flex justify-end p-2">
                    <button
                        type="button"
                        onClick={() => onClose && onClose()}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                    >
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
