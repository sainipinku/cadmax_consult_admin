/**
 * Shared client-side task counterpart matching, normalization, and deduplication
 * for merging legacy execution_tasks and unified tasks across construction views.
 */

const normalizeCode = (code) => {
    if (code == null) return "";
    return String(code).trim().toLowerCase();
};

const stripMirrorSuffix = (code) => {
    const norm = normalizeCode(code);
    if (!norm) return "";
    return norm.replace(/(?:-m\d+|-\d+)$/i, "") || norm;
};

const normalizeTitle = (title) => String(title || "").trim().toLowerCase();

const expectedFallbackCode = (id) => `tsk-${String(id).padStart(5, "0")}`;

const isFallbackTagCode = (code) => /^(legacy|unified)-\d+$/i.test(String(code || "").trim());

const isValidDate = (d) => d instanceof Date && !Number.isNaN(d.getTime());

export function formatTaskDateInput(value) {
    if (!value) return "";
    if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}/.test(value)) {
        return value.slice(0, 10);
    }
    const parsed = new Date(value);
    return isValidDate(parsed) ? parsed.toISOString().slice(0, 10) : "";
}

function shareSurveyVisit(a, b) {
    const visitsA = Array.isArray(a?.survey_visits) ? a.survey_visits : (Array.isArray(a?.surveyVisits) ? a.surveyVisits : []);
    const visitsB = Array.isArray(b?.survey_visits) ? b.survey_visits : (Array.isArray(b?.surveyVisits) ? b.surveyVisits : []);
    if (!visitsA.length || !visitsB.length) return false;
    const idsA = new Set(visitsA.map((v) => v?.id).filter(Boolean).map(Number));
    return visitsB.some((v) => v?.id && idsA.has(Number(v.id)));
}

/**
 * Determine whether a legacy ExecutionTask and a unified Task represent the same logical task.
 */
export function areTasksCounterparts(legacy, unified) {
    if (!legacy || !unified) return false;
    if (legacy.project_id != null && unified.project_id != null && Number(legacy.project_id) !== Number(unified.project_id)) {
        return false;
    }

    const legCode = normalizeCode(legacy.task_code);
    const uniCode = normalizeCode(unified.task_code);

    if (legCode && uniCode) {
        if (legCode === uniCode || stripMirrorSuffix(legCode) === stripMirrorSuffix(uniCode)) {
            return true;
        }
    }

    const expectedFromUnified = expectedFallbackCode(unified.id);
    if (legCode && (legCode === expectedFromUnified || stripMirrorSuffix(legCode) === expectedFromUnified)) {
        return true;
    }

    if (shareSurveyVisit(legacy, unified)) {
        return true;
    }

    const legTitle = normalizeTitle(legacy.title);
    const uniTitle = normalizeTitle(unified.title);
    if (legTitle && legTitle === uniTitle) {
        if (legCode && uniCode && legCode !== uniCode) {
            return false;
        }
        return true;
    }

    return false;
}

/**
 * Find the matching counterpart task for a given source record inside the other array.
 */
export function findPartnerTask(task, sourceTag, legacyTasks = [], unifiedTasks = []) {
    if (sourceTag === "unified") {
        return legacyTasks.find((et) => areTasksCounterparts(et, task)) || null;
    }
    return unifiedTasks.find((ut) => areTasksCounterparts(task, ut)) || null;
}

function normalizeUnifiedChecklists(task) {
    if (!task) return [];
    const rawUnified = Array.isArray(task.checklist_items)
        ? task.checklist_items
        : (Array.isArray(task.checklistItems) ? task.checklistItems : []);
    return rawUnified.map((item) => ({
        ...item,
        is_completed: Boolean(item.is_completed ?? item.completed_at ?? false),
        item_title: item.item_title ?? item.title ?? "",
    }));
}

function mergeChecklists(primaryChecklists = [], partnerChecklists = []) {
    const merged = [];
    const seen = new Map();

    for (const it of [...primaryChecklists, ...partnerChecklists]) {
        const key = `${normalizeTitle(it.item_title || it.title || "")}-${it.day_number || 1}`;
        if (!seen.has(key)) {
            seen.set(key, merged.length);
            merged.push(it);
        } else if (it.is_completed) {
            const idx = seen.get(key);
            merged[idx] = { ...merged[idx], is_completed: true };
        }
    }
    return merged;
}

function mergeSurveyVisits(primary, partnerMatch) {
    const selfVisits = Array.isArray(primary?.survey_visits) ? primary.survey_visits : (Array.isArray(primary?.surveyVisits) ? primary.surveyVisits : []);
    const partnerVisits = partnerMatch ? (Array.isArray(partnerMatch.survey_visits) ? partnerMatch.survey_visits : (Array.isArray(partnerMatch.surveyVisits) ? partnerMatch.surveyVisits : [])) : [];
    const combinedVisits = [...selfVisits, ...partnerVisits];
    const seen = new Set();
    const mergedVisits = [];
    for (const v of combinedVisits) {
        if (v && v.id && !seen.has(v.id)) {
            seen.add(v.id);
            mergedVisits.push(v);
        }
    }
    return mergedVisits;
}

function hasActiveDraftsman(task) {
    if (!task) return false;
    if (String(task.status || "").toLowerCase() === "drafting_in_progress") return true;
    const rows = [
        ...(Array.isArray(task.assignees) ? task.assignees : []),
        ...(Array.isArray(task.assignments) ? task.assignments : []),
    ];
    return rows.some((a) => {
        const st = String(a?.status?.value ?? a?.status ?? "active").toLowerCase();
        const role = String(a?.assignment_role ?? "").toLowerCase();
        return st === "active" && /draft_person|draftsman|drafter|draft_man/.test(role);
    });
}

function timestampMs(record) {
    const ms = record?.updated_at ? new Date(record.updated_at).getTime() : 0;
    return Number.isFinite(ms) ? ms : 0;
}

/**
 * Pick whichever counterpart holds the fresher / more progressed workflow state
 * so legacy and unified drift cannot surface stale data after deduplicating.
 */
function pickFresherStateSource(unified, legacy) {
    if (!unified) return { preferred: legacy, secondary: null, preferredTag: "legacy" };
    if (!legacy) return { preferred: unified, secondary: null, preferredTag: "unified" };

    const uniCompleted = String(unified.status || "").toLowerCase() === "completed";
    const legCompleted = String(legacy.status || "").toLowerCase() === "completed";
    if (uniCompleted && !legCompleted) return { preferred: unified, secondary: legacy, preferredTag: "unified" };
    if (legCompleted && !uniCompleted) return { preferred: legacy, secondary: unified, preferredTag: "legacy" };

    const uniDraft = hasActiveDraftsman(unified);
    const legDraft = hasActiveDraftsman(legacy);
    if (legDraft && !uniDraft) return { preferred: legacy, secondary: unified, preferredTag: "legacy" };
    if (uniDraft && !legDraft) return { preferred: unified, secondary: legacy, preferredTag: "unified" };

    const uniTs = timestampMs(unified);
    const legTs = timestampMs(legacy);
    if (legTs > uniTs) {
        return { preferred: legacy, secondary: unified, preferredTag: "legacy" };
    }
    return { preferred: unified, secondary: legacy, preferredTag: "unified" };
}

function normalizeMergedPair(unified, legacy) {
    const primaryRecord = unified || legacy;
    const primarySource = unified ? "unified" : "legacy";
    const { preferred, secondary } = pickFresherStateSource(unified, legacy);

    const unifiedChecklists = unified ? normalizeUnifiedChecklists(unified) : [];
    const legacyChecklists = Array.isArray(legacy?.checklists) ? legacy.checklists : [];
    const mergedChecklists = mergeChecklists(unifiedChecklists, legacyChecklists);
    const mergedVisits = mergeSurveyVisits(unified || legacy, unified ? legacy : null);

    // Prefer a real task_code from either side over generated UNIFIED-N / LEGACY-N fallback
    const candidates = [
        unified?.task_code,
        legacy?.task_code,
    ].filter((c) => c && !isFallbackTagCode(c));
    const resolvedTaskCode = candidates[0] || `${primarySource.toUpperCase()}-${primaryRecord.id}`;

    const has = (obj, k) => Boolean(obj && Object.prototype.hasOwnProperty.call(obj, k));

    const plannedStart = preferred?.planned_start_date ?? preferred?.start_date ?? secondary?.planned_start_date ?? secondary?.start_date ?? "";
    const plannedEnd = preferred?.planned_end_date ?? preferred?.end_date ?? secondary?.planned_end_date ?? secondary?.end_date ?? "";
    const actualStart = preferred?.actual_start_date ?? preferred?.start_date ?? secondary?.actual_start_date ?? secondary?.start_date ?? "";
    const actualEnd = preferred?.actual_end_date ?? preferred?.end_date ?? secondary?.actual_end_date ?? secondary?.end_date ?? "";
    const plannedQty = preferred?.planned_quantity ?? preferred?.planned_qty ?? secondary?.planned_quantity ?? secondary?.planned_qty ?? "";
    const completedQty = preferred?.completed_quantity ?? preferred?.completed_qty ?? secondary?.completed_quantity ?? secondary?.completed_qty ?? "";
    const unitVal = preferred?.unit ?? preferred?.qty_unit ?? secondary?.unit ?? secondary?.qty_unit ?? "";
    const supervisorId = preferred?.supervisor_member_id ?? preferred?.assigned_supervisor_member_id ?? secondary?.supervisor_member_id ?? secondary?.assigned_supervisor_member_id ?? "";

    const rawAssignees = Array.isArray(preferred?.assignees) && preferred.assignees.length
        ? preferred.assignees
        : (Array.isArray(legacy?.assignees) ? legacy.assignees : []);
    const rawAssignments = Array.isArray(preferred?.assignments) && preferred.assignments.length
        ? preferred.assignments
        : (Array.isArray(unified?.assignments) ? unified.assignments : []);

    const progressPercent = has(preferred, "progress_percent") && preferred.progress_percent != null
        ? preferred.progress_percent
        : (has(secondary, "progress_percent") && secondary.progress_percent != null ? secondary.progress_percent : "");

    return {
        ...(secondary || {}),
        ...(preferred || {}),
        id: primaryRecord.id,
        legacy_task_id: legacy?.id ?? primaryRecord.legacy_task_id ?? null,
        unified_task_id: unified?.id ?? primaryRecord.unified_task_id ?? null,
        checklists: mergedChecklists,
        survey_visits: mergedVisits,
        assignees: rawAssignees,
        assignments: rawAssignments,
        task_code: resolvedTaskCode,
        status: preferred?.status || preferred?.status_key || secondary?.status || "pending",
        priority: preferred?.priority || secondary?.priority || "medium",
        supervisor: preferred?.supervisor || preferred?.assigned_supervisor_member || secondary?.supervisor || secondary?.assigned_supervisor_member || null,
        sort_order: Number.isFinite(primaryRecord.sort_order) ? primaryRecord.sort_order : 0,
        _sourceKey: `${primarySource}-${primaryRecord.id}`,
        _source: primarySource,
        planned_start_date: formatTaskDateInput(plannedStart),
        planned_end_date: formatTaskDateInput(plannedEnd),
        actual_start_date: formatTaskDateInput(actualStart),
        actual_end_date: formatTaskDateInput(actualEnd),
        planned_quantity: plannedQty,
        completed_quantity: completedQty,
        unit: unitVal,
        progress_percent: progressPercent,
        supervisor_member_id: supervisorId,
        requires_daily_update: Boolean(preferred?.requires_daily_update ?? secondary?.requires_daily_update ?? false),
        requires_gps_verification: Boolean(preferred?.requires_gps_verification ?? secondary?.requires_gps_verification ?? false),
    };
}

/**
 * Merge unified tasks and legacy execution_tasks into a single deduplicated list.
 */
export function mergeProjectExecutionTasks(project) {
    const legacyTasks = Array.isArray(project?.execution_tasks) ? project.execution_tasks : [];
    const unifiedTasks = Array.isArray(project?.tasks) ? project.tasks : [];

    const matchedLegacyIds = new Set();
    const rows = [];

    for (const ut of unifiedTasks) {
        const partner = legacyTasks.find((et) => !matchedLegacyIds.has(et.id) && areTasksCounterparts(et, ut)) || null;
        if (partner) {
            matchedLegacyIds.add(partner.id);
        }
        rows.push(normalizeMergedPair(ut, partner));
    }

    for (const et of legacyTasks) {
        if (matchedLegacyIds.has(et.id)) {
            continue;
        }
        rows.push(normalizeMergedPair(null, et));
    }

    rows.sort((a, b) => {
        const diff = (a.sort_order || 0) - (b.sort_order || 0);
        if (diff !== 0) return diff;
        const at = a.created_at ? new Date(a.created_at).getTime() : 0;
        const bt = b.created_at ? new Date(b.created_at).getTime() : 0;
        return bt - at;
    });

    return rows;
}
