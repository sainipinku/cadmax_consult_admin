import { useState, useEffect, useRef, useCallback } from "react";
import { useMemo } from "react";
import gsap from "gsap";
import { useForm, router, usePage } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import WorkflowTracker from "@/Pages/Construction/Components/WorkflowTracker";
import Modal from "@/Components/Modal";
import DynamicChecklistManager from "@/Pages/Construction/Components/DynamicChecklistManager";
import FieldNotesDisplay from "@/Pages/Construction/Components/FieldNotesDisplay";
import ProjectActivityLog from "@/Pages/Construction/Components/ProjectActivityLog";
import { getMemberRoleBadges, formatMemberSelectOption, formatMemberOptionLabel } from "@/lib/memberBadges";
import { mergeProjectExecutionTasks } from "@/lib/taskMerge";
import AssignToDraftsmanButton from "@/Pages/Construction/Components/AssignToDraftsmanButton";
import AssignApprovalAdminButton from "@/Pages/Construction/Components/AssignApprovalAdminButton";
import PendingCadApprovalAlertBanner from "@/Pages/Construction/Components/PendingCadApprovalAlertBanner";

const TASKS_PER_PAGE = 10;
const EXECUTION_TASKS_TITLE_WORDS = ["Execution", "Tasks"];

const prefersReducedMotion = () =>
    typeof window !== "undefined" &&
    typeof window.matchMedia === "function" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const isActiveAssignment = (assignment) => {
    const status = assignment?.status?.value ?? assignment?.status;
    return status == null || String(status).toLowerCase() === "active";
};

const normalizeRoleStr = (val) => {
    if (!val || typeof val !== "string") return "";
    return val.toLowerCase().replace(/[\s\-_]+/g, "");
};

const getRoleIcon = (roleName) => {
    const norm = (roleName || "").toLowerCase();
    if (norm.includes("all")) return "📋";
    if (norm.includes("unassign")) return "⚪";
    if (norm.includes("driver")) return "🚚";
    if (norm.includes("draft") || norm.includes("cad")) return "📐";
    if (norm.includes("survey")) return "🧭";
    if (norm.includes("site manager")) return "👷‍♂️";
    if (norm.includes("site") || norm.includes("employee")) return "👷";
    if (norm.includes("approv")) return "✅";
    if (norm.includes("admin")) return "🛡️";
    if (norm.includes("manager")) return "💼";
    return "👤";
};

const resolveTaskAssignedMembers = (task, { members = [], projectTeamMembers = [] }) => {
    const assignedMembers = [];
    const seenIds = new Set();

    const addMember = (m) => {
        if (!m) return;
        const id = m.id ?? m.member_id;
        if (id && !seenIds.has(Number(id))) {
            seenIds.add(Number(id));
            const fullMember = members.find((mem) => Number(mem.id) === Number(id)) || m;
            assignedMembers.push(fullMember);
        }
    };

    if (task.supervisor) {
        addMember(task.supervisor);
    }
    if (task.assigned_supervisor_member) {
        addMember(task.assigned_supervisor_member);
    }
    const supId = task.supervisor_member_id || task.assigned_supervisor_member_id || task.member_id;
    if (supId) {
        const found = members.find((m) => Number(m.id) === Number(supId))
            || projectTeamMembers.find((m) => Number(m.member_id || m.id) === Number(supId));
        if (found) addMember(found);
    }

    const assignees = task.assignees || [];
    if (Array.isArray(assignees)) {
        assignees.forEach((a) => {
            if (isActiveAssignment(a)) {
                if (a.member) addMember(a.member);
                else if (a.member_id || a.assigned_to) {
                    const found = members.find((m) => Number(m.id) === Number(a.member_id || a.assigned_to));
                    if (found) addMember(found);
                }
            }
        });
    }

    const assignments = task.task_assignments || task.assignments || [];
    if (Array.isArray(assignments)) {
        assignments.forEach((a) => {
            if (isActiveAssignment(a)) {
                if (a.assignee) addMember(a.assignee);
                else if (a.assigned_to || a.member_id) {
                    const found = members.find((m) => Number(m.id) === Number(a.assigned_to || a.member_id));
                    if (found) addMember(found);
                }
            }
        });
    }

    return assignedMembers;
};

const isTaskUnassigned = (task, { members = [], projectTeamMembers = [] }) => {
    if (task.status === "drafting_in_progress") {
        return false;
    }
    const assignedMembers = resolveTaskAssignedMembers(task, { members, projectTeamMembers });
    if (assignedMembers.length > 0) {
        return false;
    }
    const supId = task.supervisor_member_id || task.assigned_supervisor_member_id || task.member_id;
    if (supId) {
        return false;
    }
    if (task.supervisor && task.supervisor.name && task.supervisor.name !== "Unassigned") {
        return false;
    }
    const assignees = task.assignees || [];
    if (Array.isArray(assignees) && assignees.some((a) => isActiveAssignment(a))) {
        return false;
    }
    const assignments = task.task_assignments || task.assignments || [];
    if (Array.isArray(assignments) && assignments.some((a) => isActiveAssignment(a))) {
        return false;
    }
    return true;
};

const getTaskRoles = (task, { members = [], projectTeamMembers = [] }) => {
    const rolesSet = new Set();
    const normalizedSet = new Set();

    const addRole = (r) => {
        if (!r || typeof r !== "string") return;
        const trimmed = r.trim();
        if (!trimmed) return;
        const norm = normalizeRoleStr(trimmed);
        if (!normalizedSet.has(norm)) {
            normalizedSet.add(norm);
            rolesSet.add(trimmed);
        }
    };

    if (task.status === "drafting_in_progress" || (task.assignees || []).some((a) => isActiveAssignment(a) && a.assignment_role === "draft_person")) {
        addRole("Draft Person");
        addRole("draft_person");
    }

    const assignees = task.assignees || [];
    if (Array.isArray(assignees)) {
        assignees.forEach((a) => {
            if (isActiveAssignment(a) && a.assignment_role) {
                addRole(a.assignment_role);
            }
        });
    }
    const assignments = task.task_assignments || task.assignments || [];
    if (Array.isArray(assignments)) {
        assignments.forEach((a) => {
            if (isActiveAssignment(a) && a.assignment_role) {
                addRole(a.assignment_role);
            }
        });
    }

    const assignedMembers = resolveTaskAssignedMembers(task, { members, projectTeamMembers });
    assignedMembers.forEach((member) => {
        const badges = getMemberRoleBadges(member);
        badges.forEach((b) => addRole(b));

        if (member.is_driver) {
            addRole("Vehicle Driver");
            addRole("vehicle_driver");
            addRole("driver");
        }
        if (member.role?.name) addRole(member.role.name);
        if (member.role?.slug) addRole(member.role.slug);
        if (member.role_name) addRole(member.role_name);
        if (member.designation) addRole(member.designation);

        if (Array.isArray(member.assigned_roles)) {
            member.assigned_roles.forEach((ar) => addRole(String(ar)));
        }
        if (Array.isArray(member.roles)) {
            member.roles.forEach((r) => {
                if (typeof r === "string") addRole(r);
                else if (r?.name) addRole(r.name);
                else if (r?.slug) addRole(r.slug);
            });
        }
    });

    return { roles: Array.from(rolesSet), normalized: normalizedSet };
};

const taskMatchesRole = (task, targetRoleKey, { members = [], projectTeamMembers = [] }) => {
    if (!targetRoleKey || targetRoleKey === "all") return true;
    if (targetRoleKey === "unassigned") {
        return isTaskUnassigned(task, { members, projectTeamMembers });
    }

    const taskRolesInfo = getTaskRoles(task, { members, projectTeamMembers });
    const targetNorm = normalizeRoleStr(String(targetRoleKey));

    if (taskRolesInfo.normalized.has(targetNorm)) {
        return true;
    }

    // Role-specific aliases and matching for official system roles:
    // 1. Vehicle Driver
    if (targetNorm === "vehicledriver" || targetNorm === "driver") {
        return taskRolesInfo.normalized.has("vehicledriver") || taskRolesInfo.normalized.has("driver");
    }

    // 2. Draft Person
    if (targetNorm === "draftperson" || targetNorm === "draftsman" || targetNorm === "drafter" || targetNorm === "draftman") {
        return taskRolesInfo.normalized.has("draftperson") ||
            taskRolesInfo.normalized.has("draftsman") ||
            taskRolesInfo.normalized.has("drafter") ||
            taskRolesInfo.normalized.has("draftman") ||
            taskRolesInfo.normalized.has("draftperson22") ||
            task.status === "drafting_in_progress";
    }

    // 3. Surveyor
    if (targetNorm === "surveyor" || targetNorm === "surveyman") {
        return taskRolesInfo.normalized.has("surveyor") || taskRolesInfo.normalized.has("surveyman");
    }

    // 4. Site Employee
    if (targetNorm === "siteemployee") {
        return taskRolesInfo.normalized.has("siteemployee") || taskRolesInfo.normalized.has("worker") || taskRolesInfo.normalized.has("executor");
    }

    // 5. Site Manager
    if (targetNorm === "sitemanager") {
        return taskRolesInfo.normalized.has("sitemanager");
    }

    // 6. Review Approver
    if (targetNorm === "reviewapprover") {
        return taskRolesInfo.normalized.has("reviewapprover");
    }

    // 7. Project Admin
    if (targetNorm === "projectadmin") {
        return taskRolesInfo.normalized.has("projectadmin");
    }

    // 8. Admin
    if (targetNorm === "admin") {
        return taskRolesInfo.normalized.has("admin");
    }

    // 9. Super Admin
    if (targetNorm === "superadmin") {
        return taskRolesInfo.normalized.has("superadmin");
    }

    // 10. Manager
    if (targetNorm === "manager") {
        return taskRolesInfo.normalized.has("manager");
    }

    // 11. Sub Admin
    if (targetNorm === "subadmin") {
        return taskRolesInfo.normalized.has("subadmin");
    }

    // 12. Member
    if (targetNorm === "member") {
        return taskRolesInfo.normalized.has("member");
    }

    return false;
};

export default function ProjectShow({ project, members, roles, activityLog, workflowSummary, companyApprovalAdmins = [], canAssignApprovalAdmin = false }) {
    const pageProps = usePage().props;
    const { auth } = pageProps;
    const currentUser = auth?.user;
    const guard = auth?.guard;
    const activeRoleSlug = String(auth?.active_role?.slug || auth?.active_role || "").toLowerCase();
    const isImpersonating = Boolean(auth?.is_impersonating);

    const isRestrictedRole = Boolean(
        activeRoleSlug &&
        !['super_admin', 'superadmin', 'crm_super_admin', 'admin', 'project_admin'].includes(activeRoleSlug)
    );

    const isProjectOwner = !isImpersonating && !isRestrictedRole && Boolean(
        currentUser?.is_project_owner == 1 ||
        currentUser?.is_project_owner === true ||
        currentUser?.id == 1 ||
        currentUser?.id === '1' ||
        (!currentUser?.company_id && guard === "superadmin")
    );

    const isSuperAdmin = !isImpersonating && !isRestrictedRole && (
        isProjectOwner ||
        guard === "superadmin" ||
        currentUser?.email === "superadmin@gmail.com" ||
        currentUser?.is_super_admin ||
        currentUser?.slug === "super-admin" ||
        currentUser?.slug === "super_admin" ||
        (Array.isArray(currentUser?.roles) && (currentUser.roles.includes("super_admin") || currentUser.roles.includes("superadmin"))) ||
        (Array.isArray(currentUser?.assigned_roles) && (currentUser.assigned_roles.includes("super_admin") || currentUser.assigned_roles.includes("superadmin")))
    );

    const isRootSuperAdmin = isProjectOwner || isSuperAdmin;

    const isDraftPersonUser = useMemo(() => {
        if (activeRoleSlug === "draft_person" || activeRoleSlug === "drafter" || activeRoleSlug === "draftsman") return true;
        if (Array.isArray(currentUser?.assigned_roles) && (currentUser.assigned_roles.includes("draft_person") || currentUser.assigned_roles.includes("drafter"))) return true;
        const desig = String(currentUser?.role_badge || currentUser?.designation_text || currentUser?.designation || "").toLowerCase();
        if (/draft|cad|drafter|draftman|draftsman/i.test(desig)) return true;
        const team = project?.team_members || project?.teamMembers || [];
        if (currentUser?.id && team.some((tm) => Number(tm.member_id) === Number(currentUser.id) && (/draft|cad|drafter/i.test(tm.role?.name || tm.assignment_scope || tm.project_role || "")))) return true;
        if (currentUser?.id && (project?.drafting_jobs || []).some((dj) => Number(dj.assigned_to_member_id) === Number(currentUser.id))) return true;
        return false;
    }, [activeRoleSlug, currentUser, project]);

    const userPermissions = auth?.permissions || auth?.construction_permissions || [];
    const hasPerm = (slug) => {
        if (isRootSuperAdmin) return true;
        if (!slug) return true;
        const slugs = Array.isArray(slug) ? slug : [slug];
        return slugs.some((s) => userPermissions.includes(s) || userPermissions.includes('*'));
    };

    const isAdminUser = isSuperAdmin || Boolean(
        currentUser?.is_admin ||
        currentUser?.slug === "admin" ||
        (Array.isArray(currentUser?.assigned_roles) && (currentUser.assigned_roles.includes("admin") || currentUser.assigned_roles.includes("project_admin"))) ||
        (Array.isArray(currentUser?.roles) && (currentUser.roles.includes("admin") || currentUser.roles.includes("project_admin") || currentUser.roles.includes(9) || currentUser.roles.includes("9") || currentUser.roles.includes(3) || currentUser.roles.includes("3")))
    );

    const isEmployeeView = (isImpersonating && !isAdminUser) || guard === "member" || (!isSuperAdmin && !isAdminUser);

    const canAddTeamMember = !isDraftPersonUser && hasPerm(["project_team.create", "project_team.manage"]);
    const canViewTeam = hasPerm(["project_team.view", "project_team.manage", "project.view", "project.manage"]);
    const canEditTeamMember = !isDraftPersonUser && hasPerm(["project_team.edit", "project_team.manage"]);
    const canDeleteTeamMember = !isDraftPersonUser && hasPerm(["project_team.delete", "project_team.manage"]);
    const canManageTeam = canAddTeamMember || canEditTeamMember || canDeleteTeamMember;

    const canCreateTask = hasPerm("execution.create");

    const canEditTask = hasPerm([
        "execution.edit",
        "execution.task.update",
    ]);

    const canDeleteTask = hasPerm("execution.delete");

    const canManageChecklist = hasPerm([
        "execution.create",
        "execution.edit",
    ]);

    const canTransferTask = hasPerm("execution.edit");
    const canEditScopeOfWork = !isDraftPersonUser && (isAdminUser || hasPerm(["project.edit", "project.manage"]));
    const [showScopeModal, setShowScopeModal] = useState(false);
    const scopeOfWorkForm = useForm({
        scope_of_work: project.scope_of_work || "",
    });
    const openScopeModal = () => {
        scopeOfWorkForm.setData("scope_of_work", project.scope_of_work || "");
        scopeOfWorkForm.clearErrors();
        setShowScopeModal(true);
    };
    const submitScopeOfWork = (e) => {
        e.preventDefault();
        scopeOfWorkForm.patch(route("super.construction.projects.scope-of-work.update", project.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowScopeModal(false);
                toast.success("Scope of work updated successfully.");
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };
    const metricsGridRef = useRef(null);

    useEffect(() => {
        if (!metricsGridRef.current) return;

        const ctx = gsap.context(() => {
            const cards = metricsGridRef.current.querySelectorAll(".stat-card-item");
            if (cards.length > 0) {
                gsap.fromTo(
                    cards,
                    { opacity: 0, y: 12, scale: 0.96 },
                    {
                        opacity: 1,
                        y: 0,
                        scale: 1,
                        duration: 0.34,
                        stagger: 0.03,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }
        }, metricsGridRef);

        return () => ctx.revert();
    }, [project.id, isDraftPersonUser]);

    const taskCounts = workflowSummary?.task_counts ?? { total: 0, completed: 0, in_progress: 0, pending: 0 };
    const checklistCounts = workflowSummary?.checklist_counts ?? { total: 0, completed: 0 };
    const checklistCompletion = checklistCounts.total > 0
        ? Math.round((checklistCounts.completed / checklistCounts.total) * 100)
        : 0;

    const budgetForm = useForm({
        estimated_amount: project.budgets?.[0]?.estimated_amount || "",
        approved_amount: project.budgets?.[0]?.approved_amount || "",
        currency: project.budgets?.[0]?.currency || "INR",
        notes: project.budgets?.[0]?.notes || "",
        status: project.budgets?.[0]?.status || "pending",
    });

    const teamForm = useForm({
        member_id: "",
        role_id: "",
        assigned_from: "",
        assigned_to: "",
        assignment_scope: "",
        is_primary: false,
        status: "active",
    });

    const taskForm = useForm({
        title: "",
        description: "",
        planned_start_date: "",
        planned_end_date: "",
        actual_start_date: "",
        actual_end_date: "",
        priority: "medium",
        planned_quantity: "",
        unit: "",
        requires_daily_update: false,
        requires_gps_verification: false,
        status: "draft",
    });

    const [editingTask, setEditingTask] = useState(null);
    const mergedTaskList = useMemo(() => {
        const allTasks = mergedTasksForExecutionList(project);
        if (!isEmployeeView || !currentUser?.id) {
            return allTasks;
        }
        const currentUid = Number(currentUser.id);
        return allTasks.filter((task) => {
            if (Number(task.member_id) === currentUid || Number(task.assigned_to) === currentUid) return true;
            if (Number(task.supervisor_member_id) === currentUid || Number(task.assigned_supervisor_member_id) === currentUid) return true;
            if (Array.isArray(task.assignees) && task.assignees.some((a) => isActiveAssignment(a) && (Number(a.member_id) === currentUid || Number(a.assigned_to) === currentUid))) return true;
            const assignments = task.task_assignments || task.assignments;
            if (Array.isArray(assignments) && assignments.some((a) => isActiveAssignment(a) && Number(a.assigned_to) === currentUid)) return true;
            return false;
        });
    }, [project, isEmployeeView, currentUser]);

    const [selectedRoleFilter, setSelectedRoleFilter] = useState("all");

    const projectTeamMembers = useMemo(() => {
        const list = project.team_members || project.teamMembers || [];
        return list.map((tm) => {
            const m = tm.member || {};
            const r = tm.role || {};
            const roleName = r.name || tm.assignment_scope || tm.project_role || "Team Member";
            const roleText = [r.slug, roleName, m.role_names, m.assigned_role_names, m.designation]
                .flat()
                .join(" ")
                .toLowerCase()
                .replace(/[\s-]+/g, "_");
            const isDraftPerson = /draft_person|draftsman|drafter|draft_man/.test(roleText);
            const memberBadges = getMemberRoleBadges(m);
            const rawBadges = [roleName, ...memberBadges].filter(Boolean);
            let combinedBadges = [];
            const seenKeys = new Set();
            for (const b of rawBadges) {
                const norm = b.trim();
                const key = norm.toLowerCase().replace(/[^a-z0-9]/g, "");
                if (!seenKeys.has(key) && norm.toLowerCase() !== "member" && norm.toLowerCase() !== "team member") {
                    seenKeys.add(key);
                    combinedBadges.push(norm);
                }
            }
            if (combinedBadges.some((b) => /vehicle driver/i.test(b))) {
                combinedBadges = combinedBadges.filter((b) => !/^driver$/i.test(b.trim()));
            }
            if (combinedBadges.some((b) => /^surveyor$/i.test(b.trim()))) {
                combinedBadges = combinedBadges.filter((b) => !/survey man/i.test(b));
            }
            const isDriver = Boolean(
                m.is_driver ||
                /driver|vehicle/i.test(roleName) ||
                combinedBadges.some((b) => /driver|vehicle/i.test(String(b)))
            );
            const status = tm.status == null ? "active" : String(tm.status).toLowerCase();
            return {
                id: tm.id,
                member_id: tm.member_id || m.id,
                name: m.name || `Member #${tm.member_id}`,
                email: m.email || "",
                phone: m.phone || "",
                role_name: roleName,
                badges: combinedBadges,
                is_draft_person: isDraftPerson,
                is_driver: isDriver,
                designation: m.designation || "",
                is_primary: tm.is_primary,
                status,
                member: m,
                role: r,
            };
        }).filter((tm) => tm.member_id && tm.status === "active");
    }, [project]);

    const roleFilterOptions = useMemo(() => {
        const allOption = {
            key: "all",
            label: "All Tasks",
            count: mergedTaskList.length,
            icon: "📋",
        };

        const unassignedCount = mergedTaskList.filter((t) =>
            isTaskUnassigned(t, { members, projectTeamMembers })
        ).length;
        const unassignedOption = {
            key: "unassigned",
            label: "Unassigned",
            count: unassignedCount,
            icon: "⚪",
        };

        const roleItems = [];
        const seenNormKeys = new Set(["all", "unassigned"]);

        // Strictly use the official roles from the Role Management listing (props.roles)
        (roles || []).forEach((r) => {
            const key = r.slug || r.name;
            const normKey = normalizeRoleStr(key);
            if (!seenNormKeys.has(normKey)) {
                seenNormKeys.add(normKey);
                const count = mergedTaskList.filter((t) =>
                    taskMatchesRole(t, key, { members, projectTeamMembers })
                ).length;
                roleItems.push({
                    key,
                    slug: r.slug,
                    label: r.name || r.slug,
                    count,
                    icon: getRoleIcon(r.name || r.slug),
                });
            }
        });

        // Roles with active tasks (count > 0) appear first, followed by zero-count roles
        roleItems.sort((a, b) => {
            if (b.count > 0 && a.count === 0) return 1;
            if (a.count > 0 && b.count === 0) return -1;
            if (b.count !== a.count) return b.count - a.count;
            return 0;
        });

        return [allOption, unassignedOption, ...roleItems];
    }, [mergedTaskList, roles, members, projectTeamMembers]);

    const filteredTaskList = useMemo(() => {
        if (!selectedRoleFilter || selectedRoleFilter === "all") {
            return mergedTaskList;
        }
        if (selectedRoleFilter === "unassigned") {
            return mergedTaskList.filter((t) => isTaskUnassigned(t, { members, projectTeamMembers }));
        }
        return mergedTaskList.filter((t) => taskMatchesRole(t, selectedRoleFilter, { members, projectTeamMembers }));
    }, [mergedTaskList, selectedRoleFilter, members, projectTeamMembers]);

    const activeFilterOption = useMemo(() => {
        return roleFilterOptions.find((o) =>
            o.key === selectedRoleFilter || normalizeRoleStr(o.key) === normalizeRoleStr(selectedRoleFilter)
        ) || { key: "all", label: "All Tasks", count: mergedTaskList.length, icon: "📋" };
    }, [roleFilterOptions, selectedRoleFilter, mergedTaskList.length]);

    const [showCreateTask, setShowCreateTask] = useState(false);
    const [showAdvancedTaskFields, setShowAdvancedTaskFields] = useState(false);

    // Execution Tasks pagination (10 per batch, 2 per row) + GSAP choreography
    const [visibleTaskCount, setVisibleTaskCount] = useState(TASKS_PER_PAGE);
    const [isLoadingMoreTasks, setIsLoadingMoreTasks] = useState(false);
    const [isExecutionInView, setIsExecutionInView] = useState(false);
    const executionTasksRef = useRef(null);
    const taskLoaderRef = useRef(null);
    const createTaskFormRef = useRef(null);
    const taskHeaderProgressRef = useRef(null);
    const prevVisibleTaskCountRef = useRef(0);
    const loadMoreTasksTimerRef = useRef(null);

    const visibleTaskList = useMemo(
        () => filteredTaskList.slice(0, visibleTaskCount),
        [filteredTaskList, visibleTaskCount]
    );
    const remainingTasks = Math.max(0, filteredTaskList.length - visibleTaskList.length);
    const hasMoreTasks = remainingTasks > 0;
    const taskCompletionPct = taskCounts.total > 0
        ? Math.round((taskCounts.completed / taskCounts.total) * 100)
        : 0;

    useEffect(() => {
        if (loadMoreTasksTimerRef.current) {
            clearTimeout(loadMoreTasksTimerRef.current);
            loadMoreTasksTimerRef.current = null;
        }
        setIsLoadingMoreTasks(false);
        prevVisibleTaskCountRef.current = 0;
        setVisibleTaskCount(TASKS_PER_PAGE);
    }, [selectedRoleFilter, project.id]);

    useEffect(() => {
        return () => {
            if (loadMoreTasksTimerRef.current) {
                clearTimeout(loadMoreTasksTimerRef.current);
                loadMoreTasksTimerRef.current = null;
            }
        };
    }, []);

    useEffect(() => {
        const el = executionTasksRef.current;
        if (!el || typeof IntersectionObserver === "undefined") {
            setIsExecutionInView(true);
            return undefined;
        }
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setIsExecutionInView(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.05 }
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    // Screen zoom-out entrance + kinetic title text effect for Execution Tasks
    useEffect(() => {
        if (!isExecutionInView || !executionTasksRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            const tl = gsap.timeline();

            tl.fromTo(
                ".et-header-card",
                { opacity: 0, scale: 1.045, y: 14 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.54,
                    ease: "power3.out",
                    clearProps: "transform,opacity",
                }
            );

            tl.fromTo(
                ".et-title-word",
                { y: 20, opacity: 0, rotateX: -24, scale: 1.08 },
                {
                    y: 0,
                    opacity: 1,
                    rotateX: 0,
                    scale: 1,
                    duration: 0.52,
                    stagger: 0.085,
                    ease: "back.out(1.7)",
                    clearProps: "transform,opacity",
                },
                "-=0.36"
            );

            tl.fromTo(
                ".et-filter-pill",
                { opacity: 0, scale: 1.05, y: 8 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.32,
                    stagger: 0.035,
                    ease: "power2.out",
                    clearProps: "transform,opacity",
                },
                "-=0.32"
            );

            if (taskHeaderProgressRef.current) {
                gsap.fromTo(
                    taskHeaderProgressRef.current,
                    { scaleX: 0, transformOrigin: "left center" },
                    { scaleX: 1, duration: 0.65, ease: "power3.out", clearProps: "transform" }
                );
            }
        }, executionTasksRef);

        return () => ctx.revert();
    }, [isExecutionInView]);

    // Incremental zoom-out + inner cascade for Execution Task cards
    useEffect(() => {
        if (!isExecutionInView || !executionTasksRef.current || prefersReducedMotion()) {
            prevVisibleTaskCountRef.current = visibleTaskList.length;
            return undefined;
        }

        const startIdx = prevVisibleTaskCountRef.current;
        const allCards = Array.from(executionTasksRef.current.querySelectorAll(".et-task-card"));
        const targetCards = allCards.filter((card) => Number(card.getAttribute("data-et-index")) >= startIdx);

        if (!targetCards.length) {
            prevVisibleTaskCountRef.current = visibleTaskList.length;
            return undefined;
        }

        const ctx = gsap.context(() => {
            gsap.fromTo(
                targetCards,
                { opacity: 0, scale: 1.04, y: 16 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.44,
                    stagger: 0.055,
                    ease: "power3.out",
                    clearProps: "transform,opacity",
                }
            );

            const titles = targetCards.flatMap((c) => Array.from(c.querySelectorAll(".et-task-title")));
            if (titles.length) {
                gsap.fromTo(
                    titles,
                    { opacity: 0, x: -8 },
                    {
                        opacity: 1,
                        x: 0,
                        duration: 0.34,
                        stagger: 0.04,
                        delay: 0.06,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }

            const bars = targetCards.flatMap((c) => Array.from(c.querySelectorAll(".et-task-progress-fill")));
            if (bars.length) {
                gsap.fromTo(
                    bars,
                    { scaleX: 0, transformOrigin: "left center" },
                    {
                        scaleX: 1,
                        duration: 0.52,
                        stagger: 0.045,
                        delay: 0.09,
                        ease: "power3.out",
                        clearProps: "transform",
                    }
                );
            }
        }, executionTasksRef);

        prevVisibleTaskCountRef.current = visibleTaskList.length;

        return () => ctx.revert();
    }, [isExecutionInView, selectedRoleFilter, visibleTaskList.length]);

    // Collapsible Create Task Form zoom-out reveal
    useEffect(() => {
        if (!showCreateTask || !createTaskFormRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            gsap.fromTo(
                createTaskFormRef.current,
                { opacity: 0, scale: 1.035, y: -10 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.4,
                    ease: "power3.out",
                    clearProps: "transform,opacity",
                }
            );
        }, createTaskFormRef);

        return () => ctx.revert();
    }, [showCreateTask]);

    // GSAP loading animation while fetching next 10 Execution Tasks
    useEffect(() => {
        if (!isLoadingMoreTasks || !taskLoaderRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            gsap.fromTo(
                ".et-load-more-loader",
                { opacity: 0, scale: 0.92 },
                { opacity: 1, scale: 1, duration: 0.22, ease: "back.out(2)" }
            );
            gsap.to(".et-loader-ring", {
                rotation: 360,
                duration: 0.72,
                repeat: -1,
                ease: "none",
                transformOrigin: "50% 50%",
            });
            gsap.to(".et-loader-dot", {
                y: -5,
                scale: 1.35,
                opacity: 1,
                duration: 0.34,
                stagger: 0.1,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut",
            });
        }, taskLoaderRef);

        return () => ctx.revert();
    }, [isLoadingMoreTasks]);

    const handleLoadMoreTasks = useCallback(() => {
        if (isLoadingMoreTasks || !hasMoreTasks) return;
        prevVisibleTaskCountRef.current = visibleTaskList.length;
        if (prefersReducedMotion()) {
            setVisibleTaskCount((prev) => Math.min(prev + TASKS_PER_PAGE, filteredTaskList.length));
            return;
        }
        setIsLoadingMoreTasks(true);
        loadMoreTasksTimerRef.current = setTimeout(() => {
            setVisibleTaskCount((prev) => Math.min(prev + TASKS_PER_PAGE, filteredTaskList.length));
            setIsLoadingMoreTasks(false);
            loadMoreTasksTimerRef.current = null;
        }, 460);
    }, [isLoadingMoreTasks, hasMoreTasks, visibleTaskList.length, filteredTaskList.length]);

    const handleShowLessTasks = useCallback(() => {
        if (loadMoreTasksTimerRef.current) {
            clearTimeout(loadMoreTasksTimerRef.current);
            loadMoreTasksTimerRef.current = null;
        }
        setIsLoadingMoreTasks(false);
        prevVisibleTaskCountRef.current = 0;
        setVisibleTaskCount(TASKS_PER_PAGE);
    }, []);

    const handleTaskPopEnter = useCallback((e, { scale = 1.015, y = -3 } = {}) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, {
            scale,
            y,
            duration: 0.24,
            ease: "back.out(2.2)",
            overwrite: "auto",
        });
    }, []);

    const handleTaskPopLeave = useCallback((e) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, {
            scale: 1,
            y: 0,
            duration: 0.24,
            ease: "power2.out",
            overwrite: "auto",
        });
    }, []);

    const editingTaskForm = useForm({
        title: "",
        description: "",
        planned_start_date: "",
        planned_end_date: "",
        actual_start_date: "",
        actual_end_date: "",
        priority: "medium",
        planned_quantity: "",
        completed_quantity: "",
        unit: "",
        progress_percent: "",
        requires_daily_update: false,
        requires_gps_verification: false,
        status: "draft",
    });

    useEffect(() => {
        if (editingTask) {
            editingTaskForm.setData({
                title: editingTask.title || "",
                description: editingTask.description || "",
                planned_start_date: formatDateForInput(editingTask.planned_start_date || editingTask.start_date || ""),
                planned_end_date: formatDateForInput(editingTask.planned_end_date || editingTask.end_date || ""),
                actual_start_date: formatDateForInput(editingTask.actual_start_date || ""),
                actual_end_date: formatDateForInput(editingTask.actual_end_date || ""),
                priority: editingTask.priority || "medium",
                planned_quantity: editingTask.planned_quantity ?? editingTask.planned_qty ?? "",
                completed_quantity: editingTask.completed_quantity ?? editingTask.completed_qty ?? "",
                unit: editingTask.unit || editingTask.qty_unit || "",
                progress_percent: editingTask.progress_percent ?? "",
                requires_daily_update: !!editingTask.requires_daily_update,
                requires_gps_verification: !!editingTask.requires_gps_verification,
                status: editingTask.status || "draft",
            });
        }
    }, [editingTask]);

    const handleRoleChange = (roleId) => {
        const roleObj = roles.find((r) => String(r.id) === String(roleId));
        let defaultScope = teamForm.data.assignment_scope;
        if (roleObj) {
            defaultScope = roleObj.description || defaultScope || "";
            if (!roleObj.description) {
                const slug = (roleObj.slug || roleObj.name || "").toLowerCase();
                if (slug.includes('survey')) defaultScope = "Field Survey & Site Data Collection";
                else if (slug.includes('draft')) defaultScope = "Drawing Revisions & CAD Drafting";
                else if (slug.includes('driver')) defaultScope = "Vehicle Transport & Site Movement";
                else if (slug.includes('admin')) defaultScope = "Project Oversight & Workflow Coordination";
                else if (slug.includes('approv')) defaultScope = "Drawing & Progress Approvals";
                else if (slug.includes('site')) defaultScope = "Site Work & Daily Execution";
            }
        }
        teamForm.setData((prev) => ({
            ...prev,
            role_id: roleId,
            assignment_scope: defaultScope,
        }));
    };

    const selectedRole = roles.find((r) => String(r.id) === String(teamForm.data.role_id));

    const sortedMembers = useMemo(() => {
        if (!selectedRole) return members;

        const normalizeRoleToken = (value) =>
            String(value || "")
                .toLowerCase()
                .replace(/[^a-z0-9]/g, "");

        const expandRoleAliases = (token) => {
            if (!token) return [];
            const aliasGroups = [
                ["surveyor", "surveyman"],
                ["draftperson", "draftsman", "draftman", "drafter"],
                ["vehicledriver", "driver"],
                ["projectadmin", "admin"],
            ];
            for (const group of aliasGroups) {
                if (group.includes(token)) {
                    return group;
                }
            }
            return [token];
        };

        const targetTokens = new Set([
            ...expandRoleAliases(normalizeRoleToken(selectedRole.slug)),
            ...expandRoleAliases(normalizeRoleToken(selectedRole.name)),
        ].filter(Boolean));

        if (targetTokens.size === 0) return members;

        const matching = members.filter((m) => {
            const memberTokens = new Set();
            if (Array.isArray(m.assigned_roles)) {
                m.assigned_roles.forEach((slug) => memberTokens.add(normalizeRoleToken(slug)));
            }
            getMemberRoleBadges(m).forEach((badge) => memberTokens.add(normalizeRoleToken(badge)));
            if (m.is_super_admin) memberTokens.add("superadmin");
            if (m.is_admin) memberTokens.add("admin");

            for (const token of targetTokens) {
                if (memberTokens.has(token)) {
                    return true;
                }
            }
            return false;
        });

        return matching.length > 0 ? matching : members;
    }, [members, selectedRole]);

    const metrics = useMemo(
        () => ({
            budgets: project.budgets?.length || 0,
            teamMembers: project.team_members?.length || 0,
            surveyPlans: project.survey_plans?.length || 0,
            submissions: project.survey_submissions?.length || 0,
            draftingJobs: project.drafting_jobs?.length || 0,
            approvals: project.drawing_approvals?.length || 0,
            executionTasks: taskCounts.total ?? mergedTaskList.length ?? 0,
            dailyProgressReports: project.daily_progress_reports?.length || 0,
            attendanceRecords: project.attendance_records?.length || 0,
            materialStocks: project.material_stocks?.length || 0,
            clientInvoices: project.client_invoices?.length || 0,
            handovers: project.handovers?.length || 0,
        }),
        [project, taskCounts]
    );

    const assignedCadAdminName = useMemo(() => {
        const direct = project?.drawing_approvals || project?.drawingApprovals || [];
        const jobApprovals = (project?.drafting_jobs || project?.draftingJobs || []).flatMap(
            (job) => (job?.drawing_revisions || job?.drawingRevisions || []).flatMap((rev) => rev?.approvals || [])
        );
        const pending = [...direct, ...jobApprovals]
            .reverse()
            .find((a) => String(a?.decision || "").toLowerCase() === "pending" && a?.assigned_to?.name);
        return pending?.assigned_to?.name || null;
    }, [project]);

    return (
        <ConstructionShell title={project.name} description={`${project.project_code} • ${project.company?.name || ""}`} variant="super">
            <WorkflowTracker currentStage={project.current_stage} isLocked={project.is_locked} assignedAdminName={assignedCadAdminName} />

            {/* Role-Specific Drafter Banner */}
            {isDraftPersonUser && (
                <div className="rounded-3xl border border-indigo-200/80 bg-gradient-to-r from-indigo-950/70 via-slate-900 to-indigo-950/60 p-6 shadow-xl shadow-indigo-950/20 backdrop-blur-md dark:border-indigo-800/60">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600/30 text-2xl text-indigo-400 border border-indigo-500/30 shadow-inner">
                                📐
                            </div>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-semibold text-indigo-300 border border-indigo-500/30">
                                        Drafting Person Workspace
                                    </span>
                                    <StatusBadge value={project.current_stage || "drafting_in_progress"} />
                                </div>
                                <h2 className="mt-1.5 text-xl font-bold text-white">
                                    {project.name} CAD & Map Drafting
                                </h2>
                                <p className="text-xs text-slate-300">
                                    {project.project_code} • Review survey field data, draft site layouts, upload revisions, and submit for drawing approval.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    const el = document.getElementById("survey-reference-section");
                                    el?.scrollIntoView({ behavior: "smooth" });
                                }}
                                className="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-xs font-semibold text-slate-200 border border-slate-700 hover:bg-slate-700 transition"
                            >
                                <span>🔍 Survey References</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Metric Stat Cards */}
            {isDraftPersonUser ? (
                <div ref={metricsGridRef} className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
                    <StatCard compact label="Drafting Jobs" value={metrics.draftingJobs} />
                    <StatCard compact label="Approvals" value={metrics.approvals} />
                    <StatCard compact label="Survey Submissions" value={metrics.submissions} />
                    <StatCard compact label="Survey Plans" value={metrics.surveyPlans} />
                    <StatCard compact label="Execution Tasks" value={metrics.executionTasks} />
                    <StatCard compact label="Team Members" value={metrics.teamMembers} />
                </div>
            ) : (
                <div ref={metricsGridRef} className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
                    <StatCard compact label="Budget Versions" value={metrics.budgets} />
                    <StatCard compact label="Team Members" value={metrics.teamMembers} />
                    <StatCard compact label="Survey Plans" value={metrics.surveyPlans} />
                    <StatCard compact label="Survey Submissions" value={metrics.submissions} />
                    <StatCard compact label="Drafting Jobs" value={metrics.draftingJobs} />
                    <StatCard compact label="Approvals" value={metrics.approvals} />
                    <StatCard compact label="Execution Tasks" value={metrics.executionTasks} />
                    <StatCard compact label="Daily Progress" value={metrics.dailyProgressReports} />
                    <StatCard compact label="Attendance" value={metrics.attendanceRecords} />
                    <StatCard compact label="Material Stock" value={metrics.materialStocks} />
                    <StatCard compact label="Invoices" value={metrics.clientInvoices} />
                    <StatCard compact label="Handovers" value={metrics.handovers} />
                </div>
            )}

            {/* Scope of Work & Survey Submission Queue */}
            <div id="survey-reference-section" className="grid gap-4 xl:grid-cols-3 items-stretch">
                <SectionCard
                    title="Scope of Work"
                    description="Project scope of work, deliverables, and linked field survey plans."
                    className="xl:col-span-2 flex flex-col"
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {project.survey_plans?.length > 0 && (
                                <span className="inline-flex items-center rounded-full border border-indigo-200/70 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    {project.survey_plans.length} {project.survey_plans.length === 1 ? "Plan" : "Plans"}
                                </span>
                            )}
                            {canEditScopeOfWork && (
                                <button
                                    type="button"
                                    onClick={openScopeModal}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-2xs transition hover:bg-indigo-100 hover:border-indigo-300 dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-200 dark:hover:bg-indigo-500/25"
                                >
                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    <span>Edit Scope of Work</span>
                                </button>
                            )}
                        </div>
                    }
                >
                    <div className="space-y-4">
                        {/* Project Scope of Work Content */}
                        <div className="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <div className="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-200/70 dark:border-slate-800">
                                <span className="text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Defined Project Scope
                                </span>
                                <div className="flex flex-wrap items-center gap-2">
                                    {project.khasra_number && (
                                        <span className="inline-flex items-center rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 ring-1 ring-slate-200/80 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-700">
                                            Khasra: {project.khasra_number}
                                        </span>
                                    )}
                                    {project.site_area && (
                                        <span className="inline-flex items-center rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 ring-1 ring-slate-200/80 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-700">
                                            Area: {project.site_area}
                                        </span>
                                    )}
                                </div>
                            </div>

                            {project.scope_of_work ? (
                                <p className="mt-3 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                                    {project.scope_of_work}
                                </p>
                            ) : (
                                <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-slate-300/80 bg-white/70 px-4 py-3.5 dark:border-slate-700 dark:bg-slate-950/40">
                                    <div>
                                        <p className="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                            No scope of work added yet.
                                        </p>
                                        <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            Fill scope of work when adding a project or click Edit Scope of Work to define deliverables and site instructions.
                                        </p>
                                    </div>
                                    {canEditScopeOfWork && (
                                        <button
                                            type="button"
                                            onClick={openScopeModal}
                                            className="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-indigo-500"
                                        >
                                            <span>+ Add Scope</span>
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Linked Survey Plans (when present) */}
                        {project.survey_plans?.length ? (
                            <div className="space-y-3">
                                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Linked Survey Plans ({project.survey_plans.length})
                                </p>
                                {project.survey_plans.map((plan) => (
                                    <SurveyPlanCard key={plan.id} plan={plan} />
                                ))}
                            </div>
                        ) : null}
                    </div>
                </SectionCard>

                {/* Edit Scope of Work Modal */}
                <Modal show={showScopeModal} onClose={() => setShowScopeModal(false)} maxWidth="2xl">
                    <form onSubmit={submitScopeOfWork} className="p-6">
                        <div className="flex items-start justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Edit Scope of Work
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {project.project_code} • {project.name}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowScopeModal(false)}
                                className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="mt-4">
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                Scope of Work Details
                            </label>
                            <textarea
                                rows={6}
                                maxLength={5000}
                                value={scopeOfWorkForm.data.scope_of_work}
                                onChange={(e) => scopeOfWorkForm.setData("scope_of_work", e.target.value)}
                                placeholder="Enter detailed project scope of work, surveying requirements, CAD deliverables, milestones, or special instructions..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                            <div className="mt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                                <span>{scopeOfWorkForm.errors.scope_of_work ? <span className="text-rose-500 font-medium">{scopeOfWorkForm.errors.scope_of_work}</span> : "Visible to the project team on Project Details."}</span>
                                <span>{(scopeOfWorkForm.data.scope_of_work || "").length} / 5000</span>
                            </div>
                        </div>

                        <div className="mt-5 flex items-center justify-end gap-2.5 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowScopeModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={scopeOfWorkForm.processing}
                                className="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {scopeOfWorkForm.processing ? "Saving..." : "Save Scope of Work"}
                            </button>
                        </div>
                    </form>
                </Modal>

                <SectionCard
                    title="Survey Submission Queue"
                    description="Review outcomes with full GPS/photos/report references before drafting starts."
                    className="flex flex-col"
                    actions={
                        <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {project.survey_submissions?.length || 0} {(project.survey_submissions?.length || 0) === 1 ? "Submission" : "Submissions"}
                        </span>
                    }
                >
                    {project.survey_submissions?.length ? (
                        <div className="space-y-3 max-h-[820px] overflow-y-auto pr-1">
                            {project.survey_submissions.map((submission) => (
                                <div key={submission.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-medium text-slate-900 dark:text-white">Visit #{submission.survey_visit_id}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Submitted by{" "}
                                                <span className="font-medium text-slate-700 dark:text-slate-200">{submission.submitted_by?.name || "Unknown"}</span>
                                                {" • "}
                                                {formatDateTime(submission.submitted_at) || "No timestamp"}
                                            </p>
                                        </div>
                                        <StatusBadge value={submission.status_key} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Reviewed by{" "}
                                        {submission.reviewed_by?.name
                                            ? submission.reviewed_by.name
                                            : submission.reviewed_at
                                                ? "authorized reviewer"
                                                : "Pending review"}
                                    </p>
                                    <p className="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600 dark:text-slate-300">
                                        {submission.review_notes || "No review notes yet."}
                                    </p>
                                    {submission.survey_visit ? (
                                        <div className="mt-4 grid gap-2 border-t border-slate-200 pt-3 text-xs sm:grid-cols-2 dark:border-slate-700">
                                            <SubMetric label="Points" value={submission.survey_visit.total_points_captured} />
                                            <SubMetric label="Distance (m)" value={formatNumber(submission.survey_visit.distance_covered_m)} />
                                            <SubMetric label="Elevation (m)" value={formatNumber(submission.survey_visit.elevation_m)} />
                                            <SubMetric label="Day" value={submission.survey_visit.day_number} />
                                            <SubMetric label="Latitude" value={submission.survey_visit.check_in_latitude ? Number(submission.survey_visit.check_in_latitude).toFixed(6) : undefined} />
                                            <SubMetric label="Longitude" value={submission.survey_visit.check_in_longitude ? Number(submission.survey_visit.check_in_longitude).toFixed(6) : undefined} />
                                            <FileAttachment path={submission.survey_visit.file_path} span="sm:col-span-2" />
                                        </div>
                                    ) : null}
                                    {submission.survey_visit?.photos?.length ? (
                                        <PhotoGrid photos={submission.survey_visit.photos} className="mt-3" />
                                    ) : null}
                                    {submission.survey_visit?.remarks || submission.survey_visit?.notes ? (
                                        <div className="mt-3 space-y-2">
                                            {submission.survey_visit.remarks ? (
                                                <div className="rounded-xl bg-amber-50/70 p-3 text-xs text-amber-900 dark:bg-amber-950/20 dark:text-amber-100">
                                                    <p className="font-semibold">Remarks</p>
                                                    <p className="mt-1 whitespace-pre-wrap break-words">{submission.survey_visit.remarks}</p>
                                                </div>
                                            ) : null}
                                            {submission.survey_visit.notes ? (
                                                <FieldNotesDisplay notes={submission.survey_visit.notes} />
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey submissions yet." description="Submitted field-duty reports render here with full GPS/photos/report payloads without manual frontend changes." />
                    )}
                </SectionCard>
            </div>

            {/* Team Assignment (Admin Only) & Assigned Team */}
            {canAddTeamMember ? (
                <div className="grid gap-6 xl:grid-cols-2">
                    <SectionCard title="Team Assignment" description="Assign project admin, surveyor, draft person, and review roles to project members.">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                teamForm.post(route("super.construction.projects.team.assign", project.id), {
                                    preserveScroll: true,
                                    onSuccess: () => teamForm.reset("member_id", "role_id", "assigned_from", "assigned_to", "assignment_scope", "is_primary"),
                                    onError: (errors) => {
                                        if (errors.member_id) {
                                            toast.error(errors.member_id);
                                        } else {
                                            const firstError = Object.values(errors)[0];
                                            if (firstError) toast.error(firstError);
                                        }
                                    },
                                });
                            }}
                            className="grid gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    form={teamForm}
                                    name="role_id"
                                    label="Construction Role"
                                    options={[
                                        { value: "", label: "-- Select Construction Role --" },
                                        ...roles.map((role) => ({ value: role.id, label: role.name })),
                                    ]}
                                    onChangeCustom={(val) => handleRoleChange(val)}
                                />
                                <SelectField
                                    form={teamForm}
                                    name="member_id"
                                    label="Member"
                                    options={[
                                        { value: "", label: "-- Select Member --", badges: [] },
                                        ...sortedMembers.map((member) =>
                                            formatMemberSelectOption(member, { extra: member.email || "" })
                                        ),
                                    ]}
                                />
                            </div>
                            <InputField form={teamForm} name="assignment_scope" label="Assignment Scope" placeholder="Survey, drafting, approvals, coordination..." />
                            <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" checked={teamForm.data.is_primary} onChange={(e) => teamForm.setData("is_primary", e.target.checked)} />
                                Mark this assignment as primary
                            </label>
                            <button type="submit" disabled={teamForm.processing} className="rounded-xl bg-slate-900 px-4 py-3 font-medium text-white hover:bg-slate-800 disabled:opacity-60 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                                {teamForm.processing ? "Assigning..." : "Assign Team Member"}
                            </button>
                        </form>
                    </SectionCard>

                    <SectionCard title="Assigned Team" description="Current team and role coverage for this project.">
                        {project.team_members?.length ? (
                            <div className="space-y-3">
                                {project.team_members.map((teamMember) => (
                                    <TeamMemberRow
                                        key={teamMember.id}
                                        teamMember={teamMember}
                                        project={project}
                                        roles={roles}
                                        canEditTeamMember={canEditTeamMember}
                                        canDeleteTeamMember={canDeleteTeamMember}
                                    />
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No team assigned yet." description="Assign the project admin and Phase 2 roles to unlock survey and drafting execution." />
                        )}
                    </SectionCard>
                </div>
            ) : (
                <SectionCard title="Assigned Team" description="Current team and role coverage for this project.">
                    {project.team_members?.length ? (
                        <div className="space-y-3">
                            {project.team_members.map((teamMember) => (
                                <TeamMemberRow
                                    key={teamMember.id}
                                    teamMember={teamMember}
                                    project={project}
                                    roles={roles}
                                    canEditTeamMember={canEditTeamMember}
                                    canDeleteTeamMember={canDeleteTeamMember}
                                />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No team assigned yet." description="Team members assigned to this project will appear here." />
                    )}
                </SectionCard>
            )}

            {/* Execution Tasks - Compact Mini-Space, 2 per row, 10 per batch + GSAP Choreography */}
            <div ref={executionTasksRef} className="et-stage w-full space-y-3.5">
                {/* Header & Role Filter Card */}
                <div className="et-header-card relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800/90 dark:bg-slate-900/95">
                    <div className="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-sky-500 opacity-85" />

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2.5">
                                <span
                                    onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.08, y: -2 })}
                                    onMouseLeave={handleTaskPopLeave}
                                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/15 to-sky-500/15 text-base text-indigo-700 ring-1 ring-indigo-500/20 dark:from-indigo-500/20 dark:to-sky-500/20 dark:text-indigo-300 dark:ring-indigo-400/25"
                                >
                                    ⚡
                                </span>
                                <div className="min-w-0">
                                    <h2
                                        aria-label="Execution Tasks"
                                        className="flex flex-wrap items-center gap-x-1.5 text-base font-extrabold tracking-tight text-slate-900 dark:text-white"
                                    >
                                        {EXECUTION_TASKS_TITLE_WORDS.map((word) => (
                                            <span key={word} className="inline-flex overflow-hidden py-0.5">
                                                <span className="et-title-word inline-block bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 bg-clip-text text-transparent dark:from-white dark:via-indigo-200 dark:to-white">
                                                    {word}
                                                </span>
                                            </span>
                                        ))}
                                    </h2>
                                    <div className="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                                        <span>
                                            {selectedRoleFilter !== "all"
                                                ? `Filtered by ${activeFilterOption.label}: Showing ${filteredTaskList.length} of ${mergedTaskList.length} tasks`
                                                : `${taskCounts.total} total tasks · ${taskCounts.completed} complete · ${taskCounts.in_progress} in-progress · ${taskCounts.pending} pending`}
                                        </span>
                                        <span className="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 ring-1 ring-indigo-200/70 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30">
                                            {taskCompletionPct}%
                                        </span>
                                        {filteredTaskList.length > 0 && (
                                            <span className="text-[10px] font-medium text-slate-400 dark:text-slate-500">
                                                • Showing {visibleTaskList.length} of {filteredTaskList.length}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 ring-1 ring-inset ring-slate-200/70 dark:bg-slate-800/90 dark:ring-slate-700/60">
                                <div
                                    ref={taskHeaderProgressRef}
                                    className="h-full rounded-full bg-gradient-to-r from-indigo-500 via-violet-500 to-emerald-500"
                                    style={{ width: `${taskCompletionPct}%` }}
                                />
                            </div>
                        </div>

                        {canCreateTask ? (
                            <button
                                type="button"
                                onClick={() => setShowCreateTask((prev) => !prev)}
                                onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.04, y: -1.5 })}
                                onMouseLeave={handleTaskPopLeave}
                                className={`inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold shadow-sm transition-colors ${showCreateTask
                                    ? "bg-slate-200 text-slate-800 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                    : "bg-indigo-600 text-white hover:bg-indigo-500 shadow-indigo-500/20"
                                    }`}
                            >
                                <span className="text-sm font-bold leading-none">{showCreateTask ? "✕" : "+"}</span>
                                <span>{showCreateTask ? "Close Task Form" : "New Task"}</span>
                            </button>
                        ) : null}
                    </div>

                    {/* Compact Role Filter Badges Bar */}
                    <div className="mt-3 rounded-xl border border-slate-200/80 bg-slate-50/70 p-2.5 dark:border-slate-800 dark:bg-slate-950/60">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Filter Tasks By Assigned Role
                                </span>
                                {selectedRoleFilter !== "all" && (
                                    <span className="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300">
                                        {activeFilterOption.label} ({filteredTaskList.length})
                                    </span>
                                )}
                            </div>
                            {selectedRoleFilter !== "all" && (
                                <button
                                    type="button"
                                    onClick={() => setSelectedRoleFilter("all")}
                                    className="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    <span>Clear filter (Show all {mergedTaskList.length})</span>
                                    <span className="text-[10px]">✕</span>
                                </button>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-1.5">
                            {roleFilterOptions.map((option) => {
                                const isSelected = selectedRoleFilter === option.key || (selectedRoleFilter && normalizeRoleStr(selectedRoleFilter) === normalizeRoleStr(option.key));
                                const isUnassignedPill = option.key === "unassigned";
                                return (
                                    <button
                                        key={option.key}
                                        type="button"
                                        onClick={() => setSelectedRoleFilter(option.key)}
                                        onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.04, y: -1 })}
                                        onMouseLeave={handleTaskPopLeave}
                                        className={`et-filter-pill inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors shadow-2xs ${
                                            isSelected
                                                ? isUnassignedPill
                                                    ? "bg-amber-500 text-white shadow-amber-500/20 ring-2 ring-amber-400 ring-offset-1 dark:ring-offset-slate-900"
                                                    : "bg-indigo-600 text-white shadow-indigo-600/20 ring-2 ring-indigo-500 ring-offset-1 dark:ring-offset-slate-900"
                                                : "bg-white text-slate-700 hover:bg-slate-100 hover:text-slate-900 border border-slate-200/90 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:border-slate-800"
                                        }`}
                                    >
                                        <span className="text-xs leading-none">{option.icon}</span>
                                        <span>{option.label}</span>
                                        <span
                                            className={`ml-0.5 rounded-full px-1.5 py-0.5 text-[9px] font-bold ${
                                                isSelected
                                                    ? "bg-white/25 text-white"
                                                    : option.count > 0
                                                        ? "bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                                                        : "bg-slate-100/60 text-slate-400 dark:bg-slate-800/60 dark:text-slate-500"
                                            }`}
                                        >
                                            {option.count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {canCreateTask && showCreateTask && (
                    <form
                        ref={createTaskFormRef}
                        onSubmit={(e) => {
                            e.preventDefault();
                            taskForm.post(route("super.construction.projects.tasks.store", project.id), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    taskForm.reset(
                                        "title",
                                        "description",
                                        "planned_start_date",
                                        "planned_end_date",
                                        "actual_start_date",
                                        "actual_end_date",
                                        "priority",
                                        "planned_quantity",
                                        "unit",
                                        "requires_daily_update",
                                        "requires_gps_verification",
                                        "status",
                                    );
                                    taskForm.setData("priority", "medium");
                                    taskForm.setData("status", "draft");
                                    setShowCreateTask(false);
                                    setShowAdvancedTaskFields(false);
                                    toast.success("Execution task created successfully.");
                                },
                                onError: (errors) => {
                                    const firstError = Object.values(errors)[0];
                                    if (firstError) toast.error(firstError);
                                },
                            });
                        }}
                        className="et-create-form-panel rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/50 via-white to-slate-50 p-4 shadow-sm dark:border-indigo-950 dark:from-slate-900 dark:to-slate-950"
                    >
                        <div className="flex items-center justify-between border-b border-indigo-100 pb-3 dark:border-slate-800">
                            <div>
                                <p className="text-sm font-bold text-slate-900 dark:text-white">Register New Execution Task</p>
                                <p className="text-xs text-slate-500">Provide the essential task details. You can attach checklists immediately after creating.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowCreateTask(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Core Fields */}
                        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="sm:col-span-2">
                                <InputField form={taskForm} name="title" label="Task Title *" placeholder="e.g. Survey site perimeter lines" required />
                            </div>
                            <SelectField
                                form={taskForm}
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

                        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <InputField form={taskForm} name="planned_start_date" label="Planned Start Date" type="date" />
                            <InputField form={taskForm} name="planned_end_date" label="Planned End Date" type="date" />
                            <div className="sm:col-span-2 flex items-center pt-5">
                                <button
                                    type="button"
                                    onClick={() => setShowAdvancedTaskFields((prev) => !prev)}
                                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    <span>{showAdvancedTaskFields ? "▾ Hide additional fields" : "▸ Add more details (Quantity, Status, GPS Verification, Description)"}</span>
                                </button>
                            </div>
                        </div>

                        {/* Collapsible Advanced Fields */}
                        {showAdvancedTaskFields && (
                            <div className="mt-4 space-y-4 rounded-xl border border-slate-200/80 bg-white/70 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <SelectField
                                        form={taskForm}
                                        name="status"
                                        label="Initial Status"
                                        options={[
                                            { value: "draft", label: "Draft" },
                                            { value: "planned", label: "Planned" },
                                            { value: "in_progress", label: "In Progress" },
                                            { value: "completed", label: "Completed" },
                                            { value: "blocked", label: "Blocked" },
                                        ]}
                                    />
                                    <InputField form={taskForm} name="planned_quantity" label="Planned Quantity" type="number" />
                                    <InputField form={taskForm} name="unit" label="Unit" placeholder="sqm / nos / m" />
                                    <div className="flex flex-col justify-center gap-2 pt-2">
                                        <label className="flex items-center gap-2.5 text-xs text-slate-700 dark:text-slate-200 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={taskForm.data.requires_daily_update}
                                                onChange={(e) => taskForm.setData("requires_daily_update", e.target.checked)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            Requires Daily Update
                                        </label>
                                        <label className="flex items-center gap-2.5 text-xs text-slate-700 dark:text-slate-200 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={taskForm.data.requires_gps_verification}
                                                onChange={(e) => taskForm.setData("requires_gps_verification", e.target.checked)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            GPS Verification
                                        </label>
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <InputField form={taskForm} name="actual_start_date" label="Actual Start (Optional)" type="date" />
                                    <InputField form={taskForm} name="actual_end_date" label="Actual End (Optional)" type="date" />
                                </div>
                                <TextAreaField form={taskForm} name="description" label="Task Description / Notes" rows={2} placeholder="Optional scope notes, equipment requirements..." />
                            </div>
                        )}

                        <div className="mt-4 flex items-center justify-end gap-3 border-t border-indigo-100/80 pt-3 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowCreateTask(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={taskForm.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {taskForm.processing ? "Creating Task..." : "Create Execution Task"}
                            </button>
                        </div>
                    </form>
                )}

                <div>
                    <PendingCadApprovalAlertBanner
                        approvals={(pageProps?.pending_assigned_cad_approvals || []).filter(
                            (a) => Number(a.project_id) === Number(project.id)
                        )}
                        className="cad-action-blink-border"
                    />
                    {visibleTaskList.length ? (
                        <div className="space-y-3">
                            <div className="grid gap-3 md:grid-cols-2">
                                {visibleTaskList.map((task, taskIndex) => (
                                    <TaskRow
                                        key={task._sourceKey}
                                        task={task}
                                        taskIndex={taskIndex}
                                        project={project}
                                        members={members}
                                        canEditTask={canEditTask}
                                        canDeleteTask={canDeleteTask}
                                        canManageChecklist={canManageChecklist}
                                        canTransferTask={canTransferTask}
                                        canAssignDraftsman={isAdminUser && canTransferTask}
                                        companyApprovalAdmins={companyApprovalAdmins}
                                        canAssignApprovalAdmin={Boolean(canAssignApprovalAdmin)}
                                        onEdit={() => setEditingTask(task)}
                                        onPopEnter={handleTaskPopEnter}
                                        onPopLeave={handleTaskPopLeave}
                                    />
                                ))}
                            </div>

                            {/* Load More / Show Less Footer with GSAP Loading Animation */}
                            {(hasMoreTasks || visibleTaskList.length > TASKS_PER_PAGE) && (
                                <div
                                    ref={taskLoaderRef}
                                    className="flex flex-wrap items-center justify-center gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-4 py-2.5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
                                >
                                    <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                        Showing <strong className="font-bold text-slate-800 dark:text-slate-200">{visibleTaskList.length}</strong> of{" "}
                                        <strong className="font-bold text-slate-800 dark:text-slate-200">{filteredTaskList.length}</strong> tasks
                                    </span>

                                    {hasMoreTasks && (
                                        <button
                                            type="button"
                                            onClick={handleLoadMoreTasks}
                                            disabled={isLoadingMoreTasks}
                                            onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.04, y: -1.5 })}
                                            onMouseLeave={handleTaskPopLeave}
                                            className="et-load-more-btn inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm shadow-indigo-500/20 transition hover:from-indigo-500 hover:to-violet-500 disabled:cursor-wait disabled:opacity-90"
                                        >
                                            {isLoadingMoreTasks ? (
                                                <span className="et-load-more-loader inline-flex items-center gap-2">
                                                    <svg
                                                        className="et-loader-ring h-3.5 w-3.5 text-white"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                    >
                                                        <circle
                                                            cx="12"
                                                            cy="12"
                                                            r="9"
                                                            stroke="currentColor"
                                                            strokeOpacity="0.3"
                                                            strokeWidth="3"
                                                        />
                                                        <path
                                                            d="M12 3a9 9 0 0 1 9 9"
                                                            stroke="currentColor"
                                                            strokeWidth="3"
                                                            strokeLinecap="round"
                                                        />
                                                    </svg>
                                                    <span>Loading next {Math.min(TASKS_PER_PAGE, remainingTasks)}</span>
                                                    <span className="inline-flex items-center gap-1">
                                                        <span className="et-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                        <span className="et-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                        <span className="et-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                    </span>
                                                </span>
                                            ) : (
                                                <>
                                                    <span>
                                                        Load More (+{Math.min(TASKS_PER_PAGE, remainingTasks)} of {remainingTasks} left)
                                                    </span>
                                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </>
                                            )}
                                        </button>
                                    )}

                                    {visibleTaskList.length > TASKS_PER_PAGE && !isLoadingMoreTasks && (
                                        <button
                                            type="button"
                                            onClick={handleShowLessTasks}
                                            onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.04, y: -1 })}
                                            onMouseLeave={handleTaskPopLeave}
                                            className="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                        >
                                            Show Less
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    ) : mergedTaskList.length > 0 ? (
                        <div className="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-6 text-center dark:border-slate-800 dark:bg-slate-900/80">
                            <span className="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-xl text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                🔍
                            </span>
                            <h3 className="mt-2.5 text-sm font-bold text-slate-900 dark:text-white">
                                No tasks found for &ldquo;{activeFilterOption.label}&rdquo;
                            </h3>
                            <p className="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                                There are currently no execution tasks matching this role filter.
                            </p>
                            <button
                                type="button"
                                onClick={() => setSelectedRoleFilter("all")}
                                className="mt-3.5 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500"
                            >
                                <span>Show All Tasks ({mergedTaskList.length})</span>
                            </button>
                        </div>
                    ) : (
                        <div className="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-6 text-center dark:border-slate-800 dark:bg-slate-900/80">
                            <span className="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-xl text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                🏗️
                            </span>
                            <h3 className="mt-2.5 text-sm font-bold text-slate-900 dark:text-white">No execution tasks yet</h3>
                            <p className="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                                {canCreateTask
                                    ? "Create your first task to assign supervisors, track dates, and attach checklists."
                                    : "No execution tasks have been created for this project yet."}
                            </p>
                            {canCreateTask && (
                                <button
                                    type="button"
                                    onClick={() => setShowCreateTask(true)}
                                    className="mt-3.5 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500"
                                >
                                    <span className="font-bold">+</span>
                                    <span>Create First Task</span>
                                </button>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <div id="project-checklist-workspace" className="scroll-mt-28">
                <DynamicChecklistManager
                    project={project}
                    namespace="super"
                    variant="project-show"
                    enableDeltaPoll
                    canManage={canManageChecklist}
                    initialCounts={{ total: checklistCounts.total, completed: checklistCounts.completed, completion: checklistCompletion }}
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <SectionCard title="Daily Progress & Attendance" description="Submissions, attendance and materials snapshot.">
                    <div className="space-y-3">
                        <SnapshotRow
                            label="Progress Reports Submitted"
                            value={project.daily_progress_reports?.length || 0}
                            badge={project.daily_progress_reports?.length ? "approved" : "planned"}
                        />
                        <SnapshotRow
                            label="Attendance Logged"
                            value={project.attendance_records?.length || 0}
                            badge={project.attendance_records?.length ? "approved" : "planned"}
                        />
                        <SnapshotRow
                            label="Material Stock Items"
                            value={project.material_stocks?.length || 0}
                            badge={project.material_stocks?.length ? "approved" : "planned"}
                        />
                        <SnapshotRow
                            label="Material Issues"
                            value={project.material_issues?.length || 0}
                            badge={project.material_issues?.length ? "approved" : "planned"}
                        />
                    </div>
                </SectionCard>
                <SectionCard title="Procurement Snapshot" description="Purchase flow across the project.">
                    <div className="space-y-3">
                        <SnapshotRow label="Purchase Requests" value={project.purchase_requests?.length || 0} />
                        <SnapshotRow label="Purchase Orders" value={project.purchase_orders?.length || 0} />
                        <SnapshotRow label="Material Receipts" value={project.material_receipts?.length || 0} />
                    </div>
                </SectionCard>
                <SectionCard title="Billing & Handover" description="Billed amounts and handover milestones.">
                    <div className="space-y-3">
                        <SnapshotRow label="Invoices Raised" value={project.client_invoices?.length || 0} />
                        <SnapshotRow label="Payments Received" value={project.client_payments?.length || 0} />
                        <SnapshotRow label="Handovers" value={project.handovers?.length || 0} />
                        <SnapshotRow
                            label="Handover Items"
                            value={
                                (project.handovers || []).reduce((acc, handover) => acc + (handover.items?.length || 0), 0)
                            }
                        />
                    </div>
                </SectionCard>
            </div>

            {/* Project Activity Log - Placed at Bottom */}
            <ProjectActivityLog activityLog={activityLog} />

            {canEditTask && editingTask ? (
                <Modal show={true} onClose={() => setEditingTask(null)}>
                    <div className="p-6">
                        <h3 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                            Edit Execution Task - {editingTask.task_code}
                        </h3>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                editingTaskForm.put(
                                    route("super.construction.projects.tasks-v2.update", [project.id, editingTask.id]),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setEditingTask(null),
                                        onError: (errors) => {
                                            const firstError = Object.values(errors)[0];
                                            if (firstError) toast.error(firstError);
                                        },
                                    },
                                );
                            }}
                            className="grid gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <InputField form={editingTaskForm} name="title" label="Title" />
                                <SelectField
                                    form={editingTaskForm}
                                    name="status"
                                    label="Status"
                                    options={[
                                        { value: "draft", label: "Draft" },
                                        { value: "planned", label: "Planned" },
                                        { value: "in_progress", label: "In Progress" },
                                        { value: "completed", label: "Completed" },
                                        { value: "blocked", label: "Blocked" },
                                    ]}
                                />
                                <SelectField
                                    form={editingTaskForm}
                                    name="priority"
                                    label="Priority"
                                    options={[
                                        { value: "low", label: "Low" },
                                        { value: "medium", label: "Medium" },
                                        { value: "high", label: "High" },
                                        { value: "critical", label: "Critical" },
                                    ]}
                                />
                                <InputField form={editingTaskForm} name="planned_start_date" label="Planned Start" type="date" />
                                <InputField form={editingTaskForm} name="planned_end_date" label="Planned End" type="date" />
                                <InputField form={editingTaskForm} name="actual_start_date" label="Actual Start" type="date" />
                                <InputField form={editingTaskForm} name="actual_end_date" label="Actual End" type="date" />
                                <InputField form={editingTaskForm} name="planned_quantity" label="Planned Qty" type="number" />
                                <InputField form={editingTaskForm} name="completed_quantity" label="Completed Qty" type="number" />
                                <InputField form={editingTaskForm} name="unit" label="Unit" />
                                <InputField form={editingTaskForm} name="progress_percent" label="Progress %" type="number" />
                                <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={editingTaskForm.data.requires_daily_update}
                                        onChange={(e) => editingTaskForm.setData("requires_daily_update", e.target.checked)}
                                    />
                                    Requires Daily Update
                                </label>
                                <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={editingTaskForm.data.requires_gps_verification}
                                        onChange={(e) => editingTaskForm.setData("requires_gps_verification", e.target.checked)}
                                    />
                                    GPS Verification
                                </label>
                            </div>
                            <TextAreaField form={editingTaskForm} name="description" label="Description" rows={3} />
                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setEditingTask(null)}
                                    className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editingTaskForm.processing}
                                    className="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                                >
                                    {editingTaskForm.processing ? "Saving..." : "Save Changes"}
                                </button>
                            </div>
                        </form>
                    </div>
                </Modal>
            ) : null}
        </ConstructionShell>
    );
}

function TaskRow({
    task,
    taskIndex = 0,
    project,
    members,
    onEdit,
    onPopEnter,
    onPopLeave,
    canEditTask: propCanEdit,
    canDeleteTask: propCanDelete,
    canManageChecklist: propCanManageChecklist,
    canTransferTask: propCanTransfer,
    canAssignDraftsman: propCanAssignDraftsman,
    companyApprovalAdmins = [],
    canAssignApprovalAdmin: propCanAssignApprovalAdmin = false,
}) {
    const pageProps = usePage().props;
    const { auth } = pageProps;
    const currentUser = auth?.user;
    const guard = auth?.guard;
    const isImpersonating = Boolean(auth?.is_impersonating);
    const activeRoleSlug = String(auth?.active_role?.slug || auth?.active_role || "").toLowerCase();

    const isRestrictedRoleModal = Boolean(
        activeRoleSlug &&
        !['super_admin', 'superadmin', 'crm_super_admin', 'admin', 'project_admin'].includes(activeRoleSlug)
    );

    const isProjectOwnerModal = !isImpersonating && !isRestrictedRoleModal && Boolean(
        currentUser?.is_project_owner == 1 ||
        currentUser?.is_project_owner === true ||
        currentUser?.id == 1 ||
        currentUser?.id === '1' ||
        (!currentUser?.company_id && guard === "superadmin")
    );

    const isSuperAdminModal = !isImpersonating && !isRestrictedRoleModal && (
        isProjectOwnerModal ||
        guard === "superadmin" ||
        (Array.isArray(currentUser?.roles) && (currentUser.roles.includes("super_admin") || currentUser.roles.includes("superadmin"))) ||
        (Array.isArray(currentUser?.assigned_roles) && (currentUser.assigned_roles.includes("super_admin") || currentUser.assigned_roles.includes("superadmin")))
    );

    const isRootSuperAdmin = isProjectOwnerModal || isSuperAdminModal;

    const userPermissions = auth?.permissions || auth?.construction_permissions || [];
    const hasPerm = (slug) => {
        if (isRootSuperAdmin) return true;
        if (!slug) return true;
        const slugs = Array.isArray(slug) ? slug : [slug];
        return slugs.some((s) => userPermissions.includes(s) || userPermissions.includes('*'));
    };

    const canEditTask = propCanEdit ?? hasPerm([
        "execution.edit",
        "execution.task.update",
    ]);

    const canDeleteTask = propCanDelete ?? hasPerm("execution.delete");

    const canManageChecklist = propCanManageChecklist ?? hasPerm([
        "execution.create",
        "execution.edit",
    ]);

    const canTransferTask = propCanTransfer ?? hasPerm("execution.edit");

    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [showChecklists, setShowChecklists] = useState(false);
    const [showAddChecklistModal, setShowAddChecklistModal] = useState(false);
    const [previewImage, setPreviewImage] = useState(null);
    const [showDetailsModal, setShowDetailsModal] = useState(false);
    const [showTransferModal, setShowTransferModal] = useState(false);
    const [showUnassignConfirm, setShowUnassignConfirm] = useState(false);
    const [isUnassigning, setIsUnassigning] = useState(false);
    const [showRevisionInput, setShowRevisionInput] = useState(false);
    const [revisionRemarks, setRevisionRemarks] = useState("");
    const [isSubmittingReview, setIsSubmittingReview] = useState(false);
    const [showDraftUploadSection, setShowDraftUploadSection] = useState(false);
    const [isSubmittingDraftReview, setIsSubmittingDraftReview] = useState(false);

    const taskVisits = useMemo(() => {
        const list = [...(task.survey_visits || task.surveyVisits || [])];
        if (Array.isArray(project.survey_visits)) {
            project.survey_visits.forEach((v) => {
                const matches =
                    Number(v.task_id) === Number(task.id) ||
                    Number(v.execution_task_id) === Number(task.id) ||
                    (task.execution_task_id && Number(v.execution_task_id) === Number(task.execution_task_id)) ||
                    (task.unified_task_id && Number(v.task_id) === Number(task.unified_task_id)) ||
                    (task.legacy_task_id && (Number(v.execution_task_id) === Number(task.legacy_task_id) || Number(v.task_id) === Number(task.legacy_task_id)));
                if (matches && !list.some((existing) => Number(existing.id) === Number(v.id))) {
                    list.push(v);
                }
            });
        }
        return list;
    }, [task, project]);

    const isDriverTask = useMemo(() => {
        const sup = task.supervisor;
        if (sup?.is_driver) return true;
        const roleText = [
            task.title,
            sup?.role_badge,
            sup?.designation,
            sup?.role_names,
            sup?.role_name,
            sup?.assigned_role_names,
        ].flat().filter(Boolean).join(" ").toLowerCase();
        if (/driver|vehicle/i.test(roleText)) return true;
        const assignees = task.assignees || task.task_assignments || task.assignments || [];
        return assignees.some((a) => {
            const aRole = a.assignment_role || a.project_role || a.role?.name || a.role?.slug || "";
            const m = a.member || {};
            return /driver|vehicle/i.test(aRole) || m.is_driver || /driver|vehicle/i.test(m.designation || "");
        });
    }, [task]);

    const isSurveyorTask = useMemo(() => {
        const sup = task.supervisor;
        const roleText = [
            task.title,
            sup?.role_badge,
            sup?.designation,
            sup?.role_names,
            sup?.role_name,
            sup?.assigned_role_names,
        ].flat().filter(Boolean).join(" ").toLowerCase();
        if (/survey/i.test(roleText)) return true;
        const assignees = task.assignees || task.task_assignments || task.assignments || [];
        return assignees.some((a) => {
            const aRole = a.assignment_role || a.project_role || a.role?.name || a.role?.slug || "";
            const m = a.member || {};
            return /survey/i.test(aRole) || /survey/i.test(m.designation || "");
        });
    }, [task]);

    const projectTeamMembers = useMemo(() => {
        const list = project.team_members || project.teamMembers || [];
        return list.map((tm) => {
            const m = tm.member || {};
            const r = tm.role || {};
            const roleName = r.name || tm.assignment_scope || tm.project_role || "Team Member";
            const roleText = [r.slug, roleName, m.role_names, m.assigned_role_names, m.designation]
                .flat()
                .join(" ")
                .toLowerCase()
                .replace(/[\s-]+/g, "_");
            const isDraftPerson = /draft_person|draftsman|drafter|draft_man/.test(roleText);
            const memberBadges = getMemberRoleBadges(m);
            const rawBadges = [roleName, ...memberBadges].filter(Boolean);
            let combinedBadges = [];
            const seenKeys = new Set();
            for (const b of rawBadges) {
                const norm = b.trim();
                const key = norm.toLowerCase().replace(/[^a-z0-9]/g, "");
                if (!seenKeys.has(key) && norm.toLowerCase() !== "member" && norm.toLowerCase() !== "team member") {
                    seenKeys.add(key);
                    combinedBadges.push(norm);
                }
            }
            if (combinedBadges.some((b) => /vehicle driver/i.test(b))) {
                combinedBadges = combinedBadges.filter((b) => !/^driver$/i.test(b.trim()));
            }
            if (combinedBadges.some((b) => /^surveyor$/i.test(b.trim()))) {
                combinedBadges = combinedBadges.filter((b) => !/survey man/i.test(b));
            }
            const isDriver = Boolean(
                m.is_driver ||
                /driver|vehicle/i.test(roleName) ||
                combinedBadges.some((b) => /driver|vehicle/i.test(String(b)))
            );
            const status = tm.status == null ? "active" : String(tm.status).toLowerCase();
            return {
                id: tm.id,
                member_id: tm.member_id || m.id,
                name: m.name || `Member #${tm.member_id}`,
                email: m.email || "",
                phone: m.phone || "",
                role_name: roleName,
                badges: combinedBadges,
                is_draft_person: isDraftPerson,
                is_driver: isDriver,
                designation: m.designation || "",
                is_primary: tm.is_primary,
                status,
            };
        }).filter((tm) => tm.member_id && tm.status === "active");
    }, [project]);
    const assignableTeamMembers = useMemo(
        () => projectTeamMembers.filter((member) => !member.is_draft_person),
        [projectTeamMembers]
    );

    const projectDraftsmen = useMemo(
        () => projectTeamMembers.filter((member) => member.is_draft_person),
        [projectTeamMembers]
    );

    const defaultDraftsmanId = useMemo(
        () => (projectDraftsmen[0]?.member_id ? String(projectDraftsmen[0].member_id) : ""),
        [projectDraftsmen]
    );

    const allCompanyDraftsmen = useMemo(() => {
        const map = new Map();

        for (const tm of projectDraftsmen) {
            const id = String(tm.member_id || tm.id);
            if (!id) continue;
            map.set(id, {
                id,
                member_id: tm.member_id || tm.id,
                name: tm.name,
                email: tm.email || "",
                role_name: tm.role_name || "Draft Person",
                is_draft_person: true,
                is_project_member: true,
            });
        }

        const employeeList = Array.isArray(members) ? members : [];
        for (const emp of employeeList) {
            const id = String(emp.id || emp.member_id || "");
            if (!id || map.has(id)) continue;

            const signal = [
                emp.role_badge,
                ...(Array.isArray(emp.role_badges) ? emp.role_badges : []),
                ...(Array.isArray(emp.roles_list) ? emp.roles_list : []),
                emp.designation_text,
                typeof emp.designation === "string" ? emp.designation : "",
                ...(Array.isArray(emp.roles) ? emp.roles : []),
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            const isDraftEmployee = Boolean(emp.is_draft_person)
                || /draft[\s_-]?person|draftsman|drafter|draft[\s_-]?man|\bdraft\b/i.test(signal);

            if (!isDraftEmployee) continue;

            map.set(id, {
                id,
                member_id: emp.id,
                name: emp.name || `Employee #${id}`,
                email: emp.email || "",
                role_name: emp.role_badge || "Draft Person",
                is_draft_person: true,
                is_project_member: false,
            });
        }

        return Array.from(map.values());
    }, [projectDraftsmen, members]);

    // Role and Drafting Job Resolution
    const isDraftPersonUser = useMemo(() => {
        if (activeRoleSlug === "draft_person" || activeRoleSlug === "drafter" || activeRoleSlug === "draftsman") return true;
        if (Array.isArray(currentUser?.assigned_roles) && currentUser.assigned_roles.includes("draft_person")) return true;
        const team = project?.team_members || project?.teamMembers || [];
        if (currentUser?.id && team.some((tm) => Number(tm.member_id) === Number(currentUser.id) && (/draft|cad|drafter/i.test(tm.role?.name || tm.assignment_scope || tm.project_role || tm.member?.designation || "")))) return true;
        if (currentUser?.id && (project?.drafting_jobs || []).some((dj) => Number(dj.assigned_to_member_id) === Number(currentUser.id))) return true;
        return false;
    }, [activeRoleSlug, currentUser, project]);

    const taskDraftingJob = useMemo(() => {
        const jobs = project.drafting_jobs || project.draftingJobs || [];
        if (task.drafting_job_id) {
            const byDirectId = jobs.find((j) => Number(j.id) === Number(task.drafting_job_id));
            if (byDirectId) return byDirectId;
        }
        const bySub = jobs.find((job) => {
            const sub = job.survey_submission || job.surveySubmission;
            if (!sub) return false;
            if (Number(sub.task_id) === Number(task.id) || Number(sub.execution_task_id) === Number(task.id)) {
                return true;
            }
            if (taskVisits.some((v) => Number(v.id) === Number(sub.survey_visit_id))) {
                return true;
            }
            return false;
        });
        if (bySub) return bySub;

        const isDraftStatus =
            task.status === "drafting_in_progress" ||
            task.status === "drawing_approval_pending" ||
            (task.assignees || []).some((a) => isActiveAssignment(a) && a.assignment_role === "draft_person") ||
            (task.assignments || []).some((a) => isActiveAssignment(a) && a.assignment_role === "draft_person");
        if (isDraftStatus && jobs.length > 0) {
            const draftAssignee =
                (task.assignees || []).find((a) => isActiveAssignment(a) && a.assignment_role === "draft_person") ||
                (task.assignments || []).find((a) => isActiveAssignment(a) && a.assignment_role === "draft_person");
            const targetDraftId = draftAssignee?.member_id || draftAssignee?.assignee_id || task.supervisor_member_id || task.supervisor?.id || task.member_id;
            const matchingJob = jobs.find((j) => Number(j.assigned_to_member_id) === Number(targetDraftId));
            return matchingJob || (jobs.length === 1 ? jobs[0] : null);
        }
        return null;
    }, [project.drafting_jobs, project.draftingJobs, task, taskVisits]);

    const isSurveyApproved = useMemo(() => {
        if (task.status === "survey_approved" || task.status === "drafting_in_progress") {
            return true;
        }
        if (taskDraftingJob) return true;
        const hasApprovedVisit = taskVisits.some((v) => {
            const sub = v.submission;
            return v.status === 2 || v.status === "approved" || (sub && (sub.status === 2 || sub.status_key === "approved"));
        });
        if (hasApprovedVisit && isSurveyorTask) return true;
        const submissions = project.survey_submissions || project.surveySubmissions || [];
        return submissions.some((s) => {
            const matches = Number(s.task_id) === Number(task.id) || Number(s.execution_task_id) === Number(task.id) || taskVisits.some((v) => Number(v.id) === Number(s.survey_visit_id));
            return matches && (s.status === 2 || s.status_key === "approved") && isSurveyorTask;
        });
    }, [task.status, taskDraftingJob, taskVisits, project.survey_submissions, project.surveySubmissions, task.id, isSurveyorTask]);

    const isDraftingAssigned = useMemo(() => {
        if (Boolean(taskDraftingJob)) return true;
        if (task.status === "drafting_in_progress") return true;
        return (task.assignees || []).some((a) => isActiveAssignment(a) && a.assignment_role === "draft_person")
            || (task.assignments || []).some((a) => isActiveAssignment(a) && a.assignment_role === "draft_person");
    }, [taskDraftingJob, task.status, task.assignees, task.assignments]);

    const assignedDraftPerson = useMemo(() => {
        if (taskDraftingJob?.assignedTo || taskDraftingJob?.assigned_to) {
            return taskDraftingJob.assignedTo || taskDraftingJob.assigned_to;
        }
        const draftAssignee = (task.assignees || []).find((a) => isActiveAssignment(a) && a.assignment_role === "draft_person");
        if (draftAssignee?.member) return draftAssignee.member;
        const draftAss = (task.assignments || []).find((a) => isActiveAssignment(a) && a.assignment_role === "draft_person");
        if (draftAss?.assignee) return draftAss.assignee;
        const supId = task.supervisor_member_id || task.supervisor?.id;
        if (supId) {
            const tm = projectTeamMembers.find((m) => Number(m.member_id) === Number(supId) && m.is_draft_person);
            if (tm) return tm;
        }
        return null;
    }, [taskDraftingJob, task, projectTeamMembers]);

    const isCurrentUserDraftPerson = useMemo(() => {
        if (!currentUser?.id) return false;
        if (taskDraftingJob && Number(taskDraftingJob.assigned_to_member_id) === Number(currentUser.id)) return true;
        if (assignedDraftPerson && Number(assignedDraftPerson.id || assignedDraftPerson.member_id) === Number(currentUser.id)) return true;
        if (isDraftPersonUser) return true;
        return false;
    }, [currentUser, taskDraftingJob, assignedDraftPerson, isDraftPersonUser]);

    const drawingRevisions = useMemo(() => {
        return taskDraftingJob?.drawing_revisions || taskDraftingJob?.drawingRevisions || [];
    }, [taskDraftingJob]);

    const latestRevision = drawingRevisions.length ? drawingRevisions[drawingRevisions.length - 1] : null;
    const isDrawingApproved = latestRevision?.status === "approved" || taskDraftingJob?.status === "approved";
    const isDrawingSubmitted = latestRevision?.status === "submitted" || taskDraftingJob?.status === "submitted";
    const isDrawingRevisionRequested = latestRevision?.status === "revision_requested" || taskDraftingJob?.status === "revision_requested";

    const latestPendingApproval = useMemo(() => {
        const revApprovals = latestRevision?.approvals || [];
        const projectApprovals = (project.drawing_approvals || project.drawingApprovals || []).filter(
            (a) => Number(a.drawing_revision_id) === Number(latestRevision?.id)
        );
        const pool = revApprovals.length ? revApprovals : projectApprovals;
        return pool.find((a) => String(a?.decision || "").toLowerCase() === "pending")
            || (pool.length ? pool[pool.length - 1] : null);
    }, [latestRevision, project.drawing_approvals, project.drawingApprovals]);

    const pendingAssignedApprovals = Array.isArray(pageProps?.pending_assigned_cad_approvals)
        ? pageProps.pending_assigned_cad_approvals
        : [];
    const currentGuard = String(pageProps?.auth?.guard || "").toLowerCase();
    const isAssignedToCurrentUserPendingApproval = Boolean(
        latestPendingApproval &&
        String(latestPendingApproval.decision || "").toLowerCase() === "pending" &&
        !isDrawingApproved &&
        (
            pendingAssignedApprovals.some((item) => Number(item.approval_id) === Number(latestPendingApproval.id)) ||
            (latestPendingApproval.assigned_to_id && currentUser && (
                (currentGuard === "superadmin" && String(latestPendingApproval.assigned_to_type || "").includes("SuperAdmin") && Number(latestPendingApproval.assigned_to_id) === Number(currentUser.id)) ||
                (currentGuard !== "superadmin" && String(latestPendingApproval.assigned_to_type || "").includes("Member") && Number(latestPendingApproval.assigned_to_id) === Number(currentUser.id))
            ))
        )
    );
    const hasAssignApprovalAdminPerm =
        Boolean(propCanAssignApprovalAdmin) ||
        (Array.isArray(pageProps?.auth?.permissions) && pageProps.auth.permissions.includes("drawing_approval.assign_admin") && Boolean(propCanAssignApprovalAdmin));

    const resolvedKhasraNumber = useMemo(() => {
        return taskDraftingJob?.khasra_number || project?.khasra_number || task?.khasra_number || "";
    }, [taskDraftingJob?.khasra_number, project?.khasra_number, task?.khasra_number]);

    const resolvedSiteArea = useMemo(() => {
        return taskDraftingJob?.site_area || project?.site_area || task?.site_area || "";
    }, [taskDraftingJob?.site_area, project?.site_area, task?.site_area]);

    const resolvedScopeOfWork = useMemo(() => {
        return taskDraftingJob?.scope_of_work || project?.scope_of_work || task?.scope_of_work || "";
    }, [taskDraftingJob?.scope_of_work, project?.scope_of_work, task?.scope_of_work]);

    // Form for CAD Drawing Submissions
    const draftUploadForm = useForm({
        drafting_job_id: taskDraftingJob?.id || "",
        drawing_title: `${task.task_code || "TSK"} - CAD Plan`,
        drawing_no: `DWG-${task.task_code || task.id}`,
        drawing_type: "topographic",
        revision_no: drawingRevisions.length + 1,
        khasra_number: resolvedKhasraNumber,
        site_area: resolvedSiteArea,
        scope_of_work: resolvedScopeOfWork,
        changes_made: "",
        notes: "",
        dwg_file: null,
        pdf_file: null,
    });

    useEffect(() => {
        draftUploadForm.setData((prev) => ({
            ...prev,
            drafting_job_id: taskDraftingJob?.id || prev.drafting_job_id || "",
            revision_no: drawingRevisions.length + 1,
            khasra_number: prev.khasra_number || resolvedKhasraNumber,
            site_area: prev.site_area || resolvedSiteArea,
            scope_of_work: prev.scope_of_work || resolvedScopeOfWork,
        }));
    }, [taskDraftingJob?.id, resolvedKhasraNumber, resolvedSiteArea, resolvedScopeOfWork, drawingRevisions.length]);

    const handleDraftSubmit = (e) => {
        e.preventDefault();
        if (!draftUploadForm.data.dwg_file && !draftUploadForm.data.pdf_file) {
            toast.error("Please upload at least a CAD (.DWG/.DXF) or PDF Drawing file.");
            return;
        }
        draftUploadForm.post(route("super.construction.projects.tasks-v2.submit-draft", project.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDraftUploadSection(false);
                toast.success("CAD Drawing revision submitted successfully! Awaiting review.");
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
                else toast.error("Failed to submit CAD drawing.");
            },
        });
    };

    const handleReviewDraftingJob = (decision) => {
        if (!taskDraftingJob) return;
        setIsSubmittingDraftReview(true);
        router.post(
            route("super.construction.projects.drafting-jobs.review", [project.id, taskDraftingJob.id]),
            {
                decision,
                remarks: revisionRemarks.trim() || undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSubmittingDraftReview(false),
                onSuccess: () => {
                    setShowDetailsModal(false);
                    setRevisionRemarks("");
                    toast.success(
                        decision === "approved"
                            ? "CAD Drawing approved! Project stage moved to Ready for Construction Execution."
                            : "Revision requested from Draft Person."
                    );
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to update drafting review.");
                },
            }
        );
    };

    const transferForm = useForm({
        member_id: "",
        due_date: task.planned_end_date ? String(task.planned_end_date).split("T")[0] : "",
        notes: "",
        create_drafting_job: false,
        task_source: task._source || "unified",
        legacy_task_id: task.legacy_task_id ?? null,
        unified_task_id: task.unified_task_id ?? null,
    });

    useEffect(() => {
        if (showTransferModal) {
            transferForm.setData({
                member_id: "",
                due_date: task.planned_end_date ? String(task.planned_end_date).split("T")[0] : "",
                notes: "",
                create_drafting_job: false,
                task_source: task._source || "unified",
                legacy_task_id: task.legacy_task_id ?? null,
                unified_task_id: task.unified_task_id ?? null,
            });
        }
    }, [showTransferModal]);

    const handleTransferSubmit = (e) => {
        e.preventDefault();
        if (!transferForm.data.member_id) {
            toast.error("Please select a project team member.");
            return;
        }
        transferForm.post(route("super.construction.projects.tasks-v2.transfer", [project.id, task.id]), {
            preserveScroll: true,
            onSuccess: () => {
                setShowTransferModal(false);
                toast.success("Task assigned / transferred successfully.");
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
                else toast.error("Failed to transfer task.");
            },
        });
    };

    const handleUnassign = () => {
        setIsUnassigning(true);
        router.post(
            route("super.construction.projects.tasks-v2.unassign", [project.id, task.id]),
            {
                task_source: task._source || "unified",
                legacy_task_id: task.legacy_task_id ?? null,
                unified_task_id: task.unified_task_id ?? null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsUnassigning(false);
                    setShowUnassignConfirm(false);
                    setShowTransferModal(false);
                    toast.success("Assignment removed. Task is now unassigned.");
                },
                onError: (errors) => {
                    setIsUnassigning(false);
                    const firstError = Object.values(errors)[0] || "Failed to remove assignment.";
                    toast.error(firstError);
                },
            }
        );
    };

    const handleReviewSurvey = (decision) => {
        if (decision === "revision_requested" && !revisionRemarks.trim()) {
            toast.error("Please provide revision remarks describing what needs to be updated.");
            return;
        }
        setIsSubmittingReview(true);
        router.post(
            route("super.construction.projects.tasks-v2.review-survey", [project.id, task.id]),
            {
                decision,
                remarks: revisionRemarks.trim() || undefined,
                task_source: task._source || "unified",
                legacy_task_id: task.legacy_task_id ?? null,
                unified_task_id: task.unified_task_id ?? null,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSubmittingReview(false),
                onSuccess: () => {
                    setShowDetailsModal(false);
                    setShowRevisionInput(false);
                    setRevisionRemarks("");
                    const successMsg = decision === "approved"
                        ? (isDriverTask || !isSurveyorTask ? "Task approved and marked as completed!" : "Survey task approved!")
                        : (isDriverTask ? "Revision requested from driver." : (isSurveyorTask ? "Revision requested from surveyor." : "Revision requested."));
                    toast.success(successMsg);
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to update task review.");
                },
            }
        );
    };

    // Quick inline add state
    const [quickTitle, setQuickTitle] = useState("");
    const [quickDay, setQuickDay] = useState(1);
    const [isSubmittingQuick, setIsSubmittingQuick] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    // Detailed add modal form
    const addChecklistForm = useForm({
        scope: task._source === "unified" ? "unified_task" : "execution_task",
        execution_task_id: task._source !== "unified" ? task.id : "",
        unified_task_id: task._source === "unified" ? task.id : "",
        survey_plan_id: "",
        day_number: 1,
        item_title: "",
        assign_hours: "",
        notes: "",
        status: "pending",
        is_completed: false,
        image_1: null,
        image_2: null,
    });
    const [img1Preview, setImg1Preview] = useState(null);
    const [img2Preview, setImg2Preview] = useState(null);

    const handleDelete = () => {
        router.delete(route("super.construction.projects.tasks-v2.destroy", [project.id, task.id]), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(false),
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    const taskChecklists = task.checklists || [];
    const completedChecklists = taskChecklists.filter((item) => item.is_completed).length;
    const progress = taskChecklists.length
        ? Math.round((completedChecklists / taskChecklists.length) * 100)
        : (Number(task.progress_percent) || 0);

    const handleToggleChecklist = (checklistItem) => {
        setTogglingId(checklistItem.id);
        router.patch(
            route("super.construction.projects.checklists.toggle", [project.id, checklistItem.id]),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to toggle checklist item.");
                },
            }
        );
    };

    const handleDeleteChecklist = (checklistItem) => {
        if (!confirm(`Are you sure you want to delete checklist item "${checklistItem.item_title}"?`)) return;
        router.delete(
            route("super.construction.projects.checklists.destroy", [project.id, checklistItem.id]),
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Checklist item removed."),
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to delete checklist item.");
                },
            }
        );
    };

    const handleQuickAdd = (e) => {
        e.preventDefault();
        if (!quickTitle.trim()) return;
        setIsSubmittingQuick(true);
        router.post(
            route("super.construction.projects.checklists.store", project.id),
            {
                scope: task._source === "unified" ? "unified_task" : "execution_task",
                execution_task_id: task._source !== "unified" ? task.id : "",
                unified_task_id: task._source === "unified" ? task.id : "",
                day_number: Number(quickDay) || 1,
                item_title: quickTitle.trim(),
                status: "pending",
                is_completed: false,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setQuickTitle("");
                    setIsSubmittingQuick(false);
                    setShowChecklists(true);
                    toast.success("Checklist item linked to task.");
                },
                onError: (errors) => {
                    setIsSubmittingQuick(false);
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to add checklist item.");
                },
            }
        );
    };

    const handleDetailedAdd = (e) => {
        e.preventDefault();
        if (!addChecklistForm.data.item_title.trim()) {
            toast.error("Please enter a checklist title.");
            return;
        }
        addChecklistForm.post(
            route("super.construction.projects.checklists.store", project.id),
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    addChecklistForm.reset("item_title", "assign_hours", "notes", "image_1", "image_2");
                    setImg1Preview(null);
                    setImg2Preview(null);
                    setShowAddChecklistModal(false);
                    setShowChecklists(true);
                    toast.success("Checklist item created and linked.");
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    if (firstError) toast.error(firstError);
                    else toast.error("Failed to add checklist item.");
                },
            }
        );
    };

    const checklistDrawerRef = useRef(null);

    useEffect(() => {
        if (!showChecklists || !checklistDrawerRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            gsap.fromTo(
                checklistDrawerRef.current,
                { opacity: 0, scale: 1.03, y: -6 },
                { opacity: 1, scale: 1, y: 0, duration: 0.34, ease: "power3.out", clearProps: "transform,opacity" }
            );
            const items = checklistDrawerRef.current.querySelectorAll(".et-checklist-item");
            if (items.length) {
                gsap.fromTo(
                    items,
                    { opacity: 0, scale: 1.025, y: 8 },
                    { opacity: 1, scale: 1, y: 0, duration: 0.3, stagger: 0.035, ease: "power2.out", clearProps: "transform,opacity" }
                );
            }
        }, checklistDrawerRef);
        return () => ctx.revert();
    }, [showChecklists]);

    return (
        <>
            <div
                data-et-index={taskIndex}
                onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.014, y: -3 })}
                onMouseLeave={(e) => onPopLeave?.(e)}
                className={`et-task-card flex flex-col justify-between rounded-xl border bg-white p-3 shadow-sm transition-colors hover:shadow-md dark:bg-slate-950/95 ${
                    isAssignedToCurrentUserPendingApproval
                        ? "cad-action-blink-border border-rose-500 bg-rose-50/30 dark:bg-rose-950/20"
                        : "border-slate-200/90 hover:border-indigo-300/80 hover:shadow-indigo-500/5 dark:border-slate-800 dark:hover:border-indigo-500/40"
                }`}
            >
                <div>
                    {/* 1. Compact Header: Task Code, Title, Badges & Administrative Actions */}
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <span className="inline-flex shrink-0 items-center rounded bg-indigo-50 px-1.5 py-0.5 font-mono text-[10px] font-bold text-indigo-700 border border-indigo-100 dark:bg-indigo-950/60 dark:border-indigo-900/60 dark:text-indigo-300">
                                    {task.task_code}
                                </span>
                                <div className="inline-flex items-center gap-1 scale-90 origin-left">
                                    <StatusBadge value={task.status} />
                                    <StatusBadge value={task.priority} />
                                </div>
                                {isAssignedToCurrentUserPendingApproval && (
                                    <span className="inline-flex items-center rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">
                                        Action Needed: CAD Approval
                                    </span>
                                )}
                            </div>
                            <h4 className="et-task-title mt-1 truncate text-xs sm:text-sm font-bold text-slate-900 dark:text-white tracking-tight" title={task.title}>
                                {task.title}
                            </h4>
                        </div>

                        {(canEditTask || canDeleteTask) && (
                            <div className="flex shrink-0 items-center gap-1">
                                {canEditTask && (
                                    <button
                                        type="button"
                                        onClick={onEdit}
                                        onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.07, y: -1 })}
                                        onMouseLeave={(e) => onPopLeave?.(e)}
                                        className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                        title="Edit Task Details"
                                    >
                                        <svg className="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                )}
                                {canDeleteTask && (
                                    <button
                                        type="button"
                                        onClick={() => setConfirmingDelete(true)}
                                        onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.07, y: -1 })}
                                        onMouseLeave={(e) => onPopLeave?.(e)}
                                        className="inline-flex items-center gap-1 rounded-md border border-rose-200/80 bg-rose-50/60 px-2 py-0.5 text-[11px] font-medium text-rose-600 shadow-2xs transition-colors hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-400"
                                        title="Delete Task"
                                    >
                                        <svg className="h-3 w-3 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <span>Delete</span>
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    {task.description ? (
                        <p className="mt-1.5 line-clamp-2 text-[11px] text-slate-600 dark:text-slate-300 leading-snug">
                            {task.description}
                        </p>
                    ) : null}

                    {/* 2. Compact Mini-Space 2x2 Metadata Strip */}
                    <div className="mt-2.5 grid grid-cols-2 gap-x-3 gap-y-2 rounded-lg border border-slate-100 bg-slate-50/70 p-2 dark:border-slate-800/80 dark:bg-slate-900/50">
                        <div className="flex flex-col min-w-0">
                            <span className="text-[9px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Planned Schedule
                            </span>
                            <span className="mt-0.5 flex items-center gap-1 text-[11px] font-medium text-slate-700 dark:text-slate-200">
                                <svg className="h-3 w-3 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span className="truncate">
                                    {formatDate(task.planned_start_date) || "TBD"} → {formatDate(task.planned_end_date) || "TBD"}
                                </span>
                            </span>
                        </div>

                        <div className="flex flex-col min-w-0">
                            <span className="text-[9px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                {isDraftingAssigned ? "Draft Person" : "Assigned Member"}
                            </span>
                            <span className="mt-0.5 flex items-center gap-1 text-[11px] font-semibold text-slate-800 dark:text-slate-200">
                                <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-bold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                    {((assignedDraftPerson?.name || task.supervisor?.name || "U")[0] || "U").toUpperCase()}
                                </span>
                                <span className="truncate">{assignedDraftPerson?.name || task.supervisor?.name || "Unassigned"}</span>
                                {isDraftingAssigned && (
                                    <span className="ml-0.5 rounded bg-violet-100 px-1 py-0.5 text-[8px] font-bold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                        CAD
                                    </span>
                                )}
                            </span>
                        </div>

                        <div className="flex flex-col min-w-0">
                            <span className="text-[9px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Checklists
                            </span>
                            <span className="mt-0.5 flex items-center gap-1 text-[11px] font-semibold text-slate-800 dark:text-slate-200">
                                <svg className="h-3 w-3 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{completedChecklists} of {taskChecklists.length} done</span>
                            </span>
                        </div>

                        <div className="flex flex-col justify-center min-w-0">
                            <div className="flex items-center justify-between text-[9px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                <span>Progress</span>
                                <span className="font-mono text-[11px] font-bold text-slate-700 dark:text-slate-200">{progress}%</span>
                            </div>
                            <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                <div
                                    className={`et-task-progress-fill h-full rounded-full ${progress === 100
                                        ? "bg-emerald-500"
                                        : "bg-gradient-to-r from-indigo-500 to-sky-500"
                                        }`}
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* 3. Compact Bottom Action Bar: Checklist actions & Primary workflow actions */}
                <div className="mt-2.5 flex flex-wrap items-center justify-between gap-1.5 border-t border-slate-100 pt-2 dark:border-slate-800/80">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => setShowChecklists((prev) => !prev)}
                            onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.04, y: -1 })}
                            onMouseLeave={(e) => onPopLeave?.(e)}
                            className={`inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-[11px] font-semibold shadow-2xs transition-colors ${showChecklists
                                ? "border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300"
                                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                }`}
                        >
                            <svg className="h-3 w-3 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <span>Checklists ({completedChecklists}/{taskChecklists.length})</span>
                            <svg
                                className={`h-2.5 w-2.5 text-slate-400 transition-transform duration-200 ${showChecklists ? "rotate-180" : ""}`}
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        {canManageChecklist && (
                            <button
                                type="button"
                                onClick={() => setShowAddChecklistModal(true)}
                                onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.04, y: -1 })}
                                onMouseLeave={(e) => onPopLeave?.(e)}
                                className="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 shadow-2xs transition-colors hover:bg-emerald-100 hover:border-emerald-300 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300"
                                title="Add a checklist item directly to this task"
                            >
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Add Checklist</span>
                            </button>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <AssignToDraftsmanButton
                            task={task}
                            projectId={project.id}
                            taskSource={task._source === "legacy" ? "execution" : "task"}
                            routeName="super.construction.projects.tasks.assign-draftsman"
                            draftsmen={allCompanyDraftsmen}
                            defaultDraftsmanId={defaultDraftsmanId}
                            hasSurveyVisits={taskVisits.length > 0}
                            authorized={Boolean(propCanAssignDraftsman)}
                        />
                        <AssignApprovalAdminButton
                            projectId={project.id}
                            draftingJob={taskDraftingJob}
                            latestRevision={latestRevision}
                            pendingApproval={latestPendingApproval}
                            companyAdmins={companyApprovalAdmins}
                            routeName="super.construction.projects.drawing-approvals.assign-admin"
                            authorized={hasAssignApprovalAdminPerm}
                        />
                        <button
                            type="button"
                            onClick={() => setShowDetailsModal(true)}
                            onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.04, y: -1 })}
                            onMouseLeave={(e) => onPopLeave?.(e)}
                            className="inline-flex items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-700 shadow-2xs transition-colors hover:bg-sky-100 hover:border-sky-300 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300 dark:hover:bg-sky-900/50"
                            title="View all submitted survey data, coordinates, photos, and attached files"
                        >
                            <svg className="h-3 w-3 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>View Details</span>
                            {taskVisits.length > 0 && (
                                <span className="rounded-full bg-sky-200/90 px-1.5 py-0.5 text-[9px] font-bold text-sky-800 dark:bg-sky-800 dark:text-sky-200">
                                    {taskVisits.length}
                                </span>
                            )}
                        </button>

                        {isCurrentUserDraftPerson ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setShowDetailsModal(true);
                                    setShowDraftUploadSection(true);
                                }}
                                onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.04, y: -1 })}
                                onMouseLeave={(e) => onPopLeave?.(e)}
                                className="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-xs transition-colors hover:from-emerald-500 hover:to-teal-500"
                                title="Upload and submit CAD drawing for this task"
                            >
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Submit CAD Drawing</span>
                            </button>
                        ) : canTransferTask ? (
                            <button
                                type="button"
                                onClick={() => setShowTransferModal(true)}
                                onMouseEnter={(e) => onPopEnter?.(e, { scale: 1.04, y: -1 })}
                                onMouseLeave={(e) => onPopLeave?.(e)}
                                className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-semibold shadow-xs transition-colors ${isDraftingAssigned
                                        ? "border border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/60 dark:text-violet-300"
                                        : "bg-gradient-to-r from-violet-600 to-indigo-600 text-white hover:from-violet-500 hover:to-indigo-500"
                                    }`}
                                title={isDraftingAssigned ? "Re-assign or transfer this task to another team member" : "Assign or transfer this task to a project team member"}
                            >
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                <span>{isDraftingAssigned ? "Re-assign / Transfer" : "Assign / Transfer"}</span>
                            </button>
                        ) : null}
                    </div>
                </div>

                {/* Inline Checklist Section */}
                {showChecklists && (
                    <div ref={checklistDrawerRef} className="mt-2.5 rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <span className="text-[11px] font-bold text-slate-800 dark:text-slate-200">
                                    Checklist Items ({completedChecklists}/{taskChecklists.length} completed)
                                </span>
                            </div>
                            {canManageChecklist && (
                                <button
                                    type="button"
                                    onClick={() => setShowAddChecklistModal(true)}
                                    className="text-[11px] font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                                >
                                    + Detailed Add with Photos
                                </button>
                            )}
                        </div>

                        {taskChecklists.length > 0 ? (
                            <div className="mt-2 space-y-1.5">
                                {taskChecklists.map((item) => (
                                    <div
                                        key={item.id}
                                        className={`et-checklist-item flex items-start justify-between gap-2 rounded-lg border p-2 text-[11px] transition ${item.is_completed
                                            ? "border-emerald-200 bg-emerald-50/40 dark:border-emerald-950 dark:bg-emerald-950/20"
                                            : "border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700/80 dark:bg-slate-950"
                                            }`}
                                    >
                                        <div className="flex items-start gap-2.5 min-w-0 flex-1">
                                            <button
                                                type="button"
                                                disabled={!canManageChecklist || togglingId === item.id}
                                                onClick={() => handleToggleChecklist(item)}
                                                className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border transition ${item.is_completed
                                                    ? "border-emerald-600 bg-emerald-600 text-white"
                                                    : "border-slate-300 bg-white hover:border-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                                    }`}
                                                title={item.is_completed ? "Click to reopen" : "Click to mark completed"}
                                            >
                                                {item.is_completed ? (
                                                    <svg className="h-3 w-3 stroke-current stroke-2" viewBox="0 0 24 24" fill="none">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                ) : null}
                                            </button>
                                            <div className="min-w-0 flex-1">
                                                <p
                                                    className={`font-medium ${item.is_completed
                                                        ? "text-slate-500 line-through dark:text-slate-400"
                                                        : "text-slate-800 dark:text-slate-100"
                                                        }`}
                                                >
                                                    {item.item_title}
                                                </p>
                                                <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 font-medium dark:bg-slate-800">
                                                        Day {item.day_number || 1}
                                                    </span>
                                                    {item.assign_hours != null && item.assign_hours !== "" && (
                                                        <span className="rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                                            ⏱ {item.assign_hours} hrs
                                                        </span>
                                                    )}
                                                    {item.notes && (
                                                        <span className="truncate max-w-xs text-slate-500 dark:text-slate-400">
                                                            • {item.notes}
                                                        </span>
                                                    )}
                                                </div>
                                                {(item.image_url_1 || item.image_url_2) && (
                                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                                        {item.image_url_1 && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setPreviewImage(item.image_url_1)}
                                                                className="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700"
                                                            >
                                                                <img
                                                                    src={item.image_url_1}
                                                                    alt="Proof 1"
                                                                    className="h-10 w-10 object-cover hover:opacity-80"
                                                                />
                                                            </button>
                                                        )}
                                                        {item.image_url_2 && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setPreviewImage(item.image_url_2)}
                                                                className="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700"
                                                            >
                                                                <img
                                                                    src={item.image_url_2}
                                                                    alt="Proof 2"
                                                                    className="h-10 w-10 object-cover hover:opacity-80"
                                                                />
                                                            </button>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        {canManageChecklist && (
                                            <button
                                                type="button"
                                                onClick={() => handleDeleteChecklist(item)}
                                                className="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 p-1"
                                                title="Delete checklist item"
                                            >
                                                ✕
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="my-3 rounded-lg border border-dashed border-slate-200 bg-white/50 p-3 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/30">
                                No checklists linked to this task yet. Use the quick-add bar below or click '+ Add Checklist' to get started.
                            </div>
                        )}

                        {/* Fast Quick Add Input */}
                        {canManageChecklist && (
                            <form onSubmit={handleQuickAdd} className="mt-3 flex flex-wrap sm:flex-nowrap items-center gap-2">
                                <input
                                    type="text"
                                    value={quickTitle}
                                    onChange={(e) => setQuickTitle(e.target.value)}
                                    placeholder="Type a checklist item & press Enter (e.g. Inspect boundary benchmarks)..."
                                    className="flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                                <div className="flex items-center gap-1.5 shrink-0">
                                    <span className="text-[11px] font-medium text-slate-500">Day</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="366"
                                        value={quickDay}
                                        onChange={(e) => setQuickDay(e.target.value)}
                                        className="w-14 rounded-xl border border-slate-300 bg-white px-2 py-2 text-center text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={!quickTitle.trim() || isSubmittingQuick}
                                    className="rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 shrink-0"
                                >
                                    {isSubmittingQuick ? "Adding..." : "+ Add Item"}
                                </button>
                            </form>
                        )}
                    </div>
                )}
            </div>

            {/* Detailed Add Checklist Modal */}
            {showAddChecklistModal && (
                <Modal show={true} onClose={() => setShowAddChecklistModal(false)}>
                    <form onSubmit={handleDetailedAdd} className="p-6">
                        <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Add Checklist to Task
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Linking directly to <span className="font-semibold text-indigo-600 dark:text-indigo-400">"{task.title}"</span> ({task.task_code})
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowAddChecklistModal(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    Checklist Title <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={addChecklistForm.data.item_title}
                                    onChange={(e) => addChecklistForm.setData("item_title", e.target.value)}
                                    placeholder="e.g. Conduct elevation survey and record boundary coordinates"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Survey Day #
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="366"
                                        value={addChecklistForm.data.day_number}
                                        onChange={(e) => addChecklistForm.setData("day_number", e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Estimated Hours
                                    </label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        value={addChecklistForm.data.assign_hours}
                                        onChange={(e) => addChecklistForm.setData("assign_hours", e.target.value)}
                                        placeholder="e.g. 2.5"
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Initial Status
                                    </label>
                                    <select
                                        value={addChecklistForm.data.status}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            addChecklistForm.setData({
                                                ...addChecklistForm.data,
                                                status: val,
                                                is_completed: val === "completed",
                                            });
                                        }}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    Notes / Remarks (Optional)
                                </label>
                                <textarea
                                    rows={2}
                                    value={addChecklistForm.data.notes}
                                    onChange={(e) => addChecklistForm.setData("notes", e.target.value)}
                                    placeholder="Add any specific instructions or requirements..."
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    Photo Attachments (Optional, max 2)
                                </label>
                                <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) {
                                                    addChecklistForm.setData("image_1", file);
                                                    setImg1Preview(URL.createObjectURL(file));
                                                }
                                            }}
                                            className="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200"
                                        />
                                        {img1Preview && (
                                            <div className="mt-2 relative inline-block">
                                                <img src={img1Preview} alt="Preview 1" className="h-16 w-16 rounded-lg object-cover border" />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        addChecklistForm.setData("image_1", null);
                                                        setImg1Preview(null);
                                                    }}
                                                    className="absolute -top-1 -right-1 rounded-full bg-rose-500 text-white h-4 w-4 text-[10px] flex items-center justify-center"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) {
                                                    addChecklistForm.setData("image_2", file);
                                                    setImg2Preview(URL.createObjectURL(file));
                                                }
                                            }}
                                            className="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200"
                                        />
                                        {img2Preview && (
                                            <div className="mt-2 relative inline-block">
                                                <img src={img2Preview} alt="Preview 2" className="h-16 w-16 rounded-lg object-cover border" />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        addChecklistForm.setData("image_2", null);
                                                        setImg2Preview(null);
                                                    }}
                                                    className="absolute -top-1 -right-1 rounded-full bg-rose-500 text-white h-4 w-4 text-[10px] flex items-center justify-center"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowAddChecklistModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={addChecklistForm.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {addChecklistForm.processing ? "Adding..." : "Add Checklist Item"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Photo Lightbox Modal */}
            {previewImage && (
                <Modal show={true} onClose={() => setPreviewImage(null)}>
                    <div className="p-4">
                        <div className="flex justify-end pb-2">
                            <button
                                type="button"
                                onClick={() => setPreviewImage(null)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>
                        <img
                            src={previewImage}
                            alt="Checklist Proof"
                            className="max-h-[75vh] w-full rounded-xl object-contain"
                        />
                    </div>
                </Modal>
            )}

            {/* Task Submission Details Modal */}
            {showDetailsModal && (
                <Modal show={true} onClose={() => setShowDetailsModal(false)} maxWidth="5xl">
                    <div className="flex max-h-[88vh] flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                            <div className="flex items-center gap-3">
                                <span className="rounded-lg bg-indigo-50 px-2.5 py-1 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                    {task.task_code}
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                            {task.title}
                                        </h3>
                                        <StatusBadge value={task.status} />
                                        <StatusBadge value={task.priority} />
                                    </div>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {isDriverTask ? "Driver Task Submission Details" : (isSurveyorTask ? "Survey Task Submission Details" : "Task Submission Details")} • Planned: {formatDate(task.planned_start_date) || "TBD"} → {formatDate(task.planned_end_date) || "TBD"}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowDetailsModal(false)}
                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Modal Body - Scrollable */}
                        <div className="flex-1 space-y-6 overflow-y-auto p-6 pb-14">
                            {/* Task Details Overview Card */}
                            <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900/60">
                                <div className="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                    <div className="rounded-xl border border-slate-200/60 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                        <p className="text-[11px] font-medium text-slate-400">Assigned Member</p>
                                        <p className="mt-1 font-semibold text-slate-800 dark:text-slate-200 truncate">
                                            {task.supervisor?.name || "Unassigned"}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-slate-200/60 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                        <p className="text-[11px] font-medium text-slate-400 flex items-center gap-1">
                                            <span>📅</span> Planned Schedule
                                        </p>
                                        <p className="mt-1 font-semibold text-slate-800 dark:text-slate-200">
                                            {formatDate(task.planned_start_date) || "TBD"} → {formatDate(task.planned_end_date) || "TBD"}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-slate-200/60 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                        <p className="text-[11px] font-medium text-slate-400 flex items-center gap-1">
                                            <span>📈</span> Progress
                                        </p>
                                        <p className="mt-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                            {progress}%
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-slate-200/60 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                        <p className="text-[11px] font-medium text-slate-400 flex items-center gap-1">
                                            <span>☑️</span> Checklists
                                        </p>
                                        <p className="mt-1 font-semibold text-indigo-600 dark:text-indigo-400">
                                            {completedChecklists} of {taskChecklists.length} done
                                        </p>
                                    </div>
                                </div>
                                {task.description && (
                                    <div className="mt-3 rounded-xl bg-white p-3 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300 border border-slate-200/60 dark:border-slate-800">
                                        <span className="font-semibold text-slate-900 dark:text-white">Description: </span>
                                        {task.description}
                                    </div>
                                )}
                            </div>

                            {/* Survey Visits Submissions */}
                            {taskVisits.length > 0 ? (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                            <span>📋</span>
                                            <span>{isDriverTask ? `Submitted Trip & Field Reports (${taskVisits.length})` : (isSurveyorTask ? `Submitted Survey Data & Field Reports (${taskVisits.length})` : `Submitted Day Data & Field Reports (${taskVisits.length})`)}</span>
                                        </h4>
                                        <span className="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                            Latest: Day {taskVisits[0]?.day_number || 1}
                                        </span>
                                    </div>

                                    {taskVisits.map((v, vIdx) => (
                                        <div
                                            key={v.id || vIdx}
                                            className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 shadow-xs dark:border-slate-800 dark:bg-slate-950"
                                        >
                                            {/* Submitter Info Card */}
                                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-3 dark:border-slate-800">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 font-bold text-sm">
                                                        {(v.checked_in_by?.name || (isDriverTask ? "D" : isSurveyorTask ? "S" : "M")).charAt(0).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-bold text-indigo-600 dark:text-indigo-400">
                                                                Day {v.day_number || 1} {isDriverTask ? "Driver Trip Update" : (isSurveyorTask ? "Survey Update" : "Task Update")}
                                                            </span>
                                                        </div>
                                                        <p className="text-xs text-slate-500">
                                                            Submitted by <span className="font-semibold text-slate-700 dark:text-slate-300">{v.checked_in_by?.name || (isDriverTask ? "Driver" : isSurveyorTask ? "Field Surveyor" : "Team Member")}</span> • {formatDateTime(v.updated_at || v.created_at) || "N/A"}
                                                            {v.checked_in_by?.phone ? ` • Tel: ${v.checked_in_by.phone}` : ""}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${Number(v.status) === 2 || v.status_key === 'approved'
                                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                        : Number(v.status) === 3 || v.status_key === 'rejected'
                                                            ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                                                            : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                                        }`}>
                                                        {v.status_label || (Number(v.status) === 2 ? 'Approved' : 'Submitted for Review')}
                                                    </span>
                                                    {v.gps_verified ? (
                                                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                            📍 GPS Verified
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </div>

                                            {/* Metrics Grid */}
                                            {(() => {
                                                const hasElevation = v.elevation_m != null;
                                                const hasDistance = v.distance_covered_m != null;
                                                const hasPoints = v.total_points_captured != null;
                                                const hasSurveyData = hasElevation || hasDistance || hasPoints;

                                                return (
                                                    <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                        <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                            <p className="text-[11px] font-medium text-slate-400">Day Scheduled</p>
                                                            <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                                                Day {v.day_number || 1}
                                                            </p>
                                                        </div>
                                                        <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                            <p className="text-[11px] font-medium text-slate-400">
                                                                {hasSurveyData ? "Elevation" : "Review Status"}
                                                            </p>
                                                            <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                                                {hasSurveyData
                                                                    ? (hasElevation ? `${v.elevation_m} m` : "—")
                                                                    : (v.status_label || (Number(v.status) === 2 ? "Approved" : "Submitted"))
                                                                }
                                                            </p>
                                                        </div>
                                                        <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                            <p className="text-[11px] font-medium text-slate-400">
                                                                {hasSurveyData ? "Distance Covered" : "GPS Status"}
                                                            </p>
                                                            <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                                                {hasSurveyData
                                                                    ? (hasDistance ? `${v.distance_covered_m} m` : "—")
                                                                    : (v.gps_verified ? "📍 GPS Verified" : (v.check_in_latitude ? "Recorded" : "Not Logged"))
                                                                }
                                                            </p>
                                                        </div>
                                                        <div className="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                            <p className="text-[11px] font-medium text-slate-400">
                                                                {hasSurveyData ? "Points Captured" : "Submission Time"}
                                                            </p>
                                                            <p className="mt-1 text-base font-bold text-indigo-600 dark:text-indigo-400 truncate">
                                                                {hasSurveyData
                                                                    ? (hasPoints ? v.total_points_captured : "—")
                                                                    : (v.updated_at || v.created_at ? formatDateTime(v.updated_at || v.created_at) : "Today")
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                );
                                            })()}

                                            {/* GPS Coordinates */}
                                            {(v.check_in_latitude || v.check_in_longitude) && (
                                                <div className="task-submission-coords mt-3.5 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 bg-white px-3.5 py-2.5 dark:border-slate-800 dark:bg-slate-900">
                                                    <div className="flex items-center gap-2 text-xs">
                                                        <span className="text-sm">📍</span>
                                                        <span className="font-semibold text-slate-700 dark:text-slate-300">GPS Coordinates:</span>
                                                        <span className="font-mono text-slate-800 dark:text-slate-200 font-medium">
                                                            {Number(v.check_in_latitude).toFixed(6)}, {Number(v.check_in_longitude).toFixed(6)}
                                                        </span>
                                                    </div>
                                                    <a
                                                        href={`https://www.google.com/maps?q=${v.check_in_latitude},${v.check_in_longitude}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300"
                                                    >
                                                        <span>Open in Google Maps</span>
                                                        <span>↗</span>
                                                    </a>
                                                </div>
                                            )}

                                            {/* Remarks & Field Notes */}
                                            {(v.remarks || v.notes) && (
                                                <div className="mt-4 space-y-2.5">
                                                    {v.remarks && (
                                                        <div className="rounded-xl bg-amber-50/70 p-3 text-xs dark:bg-amber-950/20">
                                                            <p className="font-semibold text-amber-900 dark:text-amber-200">
                                                                {isDriverTask ? "Driver / Trip Remarks" : (isSurveyorTask ? "Survey Remarks" : "Task Remarks")}
                                                            </p>
                                                            <p className="mt-1 text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{v.remarks}</p>
                                                        </div>
                                                    )}
                                                    {v.notes && (
                                                        <FieldNotesDisplay notes={v.notes} hideRemarks={Boolean(v.remarks)} />
                                                    )}
                                                </div>
                                            )}

                                            {/* Attached Files */}
                                            {v.file_path && (
                                                <div className="mt-4 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                    <p className="text-xs font-bold text-slate-700 dark:text-slate-300">
                                                        {isDriverTask ? "Attached Trip & Vehicle Documents" : (isSurveyorTask ? "Attached Survey Files & Reports" : "Attached Task Files & Reports")}
                                                    </p>
                                                    <div className="mt-2 flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-lg">📄</span>
                                                            <span className="text-xs font-medium text-slate-800 dark:text-slate-200 truncate max-w-sm">
                                                                {v.file_path.split("/").pop() || (isDriverTask ? "Driver Trip Report" : "Survey Data Report")}
                                                            </span>
                                                        </div>
                                                        <a
                                                            href={v.file_path}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300"
                                                        >
                                                            <span>Download / View File</span>
                                                            <span>↓</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Site Proof Photos */}
                                            {Array.isArray(v.photos) && v.photos.length > 0 && (
                                                <div className="mt-4">
                                                    <p className="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                                        <span>📸</span>
                                                        <span>{isDriverTask ? "Driver Trip & Delivery Proof Photos" : (isSurveyorTask ? "Site Survey Proof Photos" : "Task Proof Photos")} ({v.photos.length})</span>
                                                    </p>
                                                    <div className="proof-photos-container mt-2.5 flex flex-wrap gap-3">
                                                        {v.photos.map((photoUrl, pIdx) => (
                                                            <button
                                                                key={pIdx}
                                                                type="button"
                                                                onClick={() => setPreviewImage(photoUrl)}
                                                                className="group relative h-28 w-44 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-2xs transition hover:scale-[1.02] hover:border-indigo-400 dark:border-slate-700 dark:bg-slate-900"
                                                            >
                                                                <img
                                                                    src={photoUrl}
                                                                    alt={`Proof ${pIdx + 1}`}
                                                                    className="h-full w-full object-cover"
                                                                />
                                                                <span className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100 text-white text-xs font-semibold">
                                                                    🔍 Zoom
                                                                </span>
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-800">
                                    <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl dark:bg-amber-950/40">
                                        📋
                                    </span>
                                    <h4 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                                        {isDriverTask ? "No Driver Submissions Yet" : (isSurveyorTask ? "No Survey Submissions Yet" : "No Task Submissions Yet")}
                                    </h4>
                                    <p className="mt-1 max-w-md mx-auto text-xs text-slate-500">
                                        {isDriverTask
                                            ? "The driver has not submitted trip data or proof photos for this task yet. When they complete day work and submit from the mobile app, details and photos will appear here."
                                            : (isSurveyorTask
                                                ? "The survey team has not submitted field data for this task yet. When they complete day work and submit from the mobile app, full metrics, points, RAW/PDF files, and photos will appear here."
                                                : "The assigned member has not submitted updates or proof photos for this task yet. When they submit from the mobile app, details and photos will appear here.")}
                                    </p>
                                </div>
                            )}

                            {/* Task Checklist Items */}
                            {taskChecklists.length > 0 && (
                                <div className="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <div className="flex items-center justify-between border-b border-slate-200 pb-2.5 dark:border-slate-800">
                                        <h4 className="text-xs font-bold text-slate-800 dark:text-slate-200">
                                            Task Checklists ({completedChecklists} of {taskChecklists.length} done)
                                        </h4>
                                        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            {progress}% Complete
                                        </span>
                                    </div>
                                    <div className="mt-3 space-y-2">
                                        {taskChecklists.map((c) => (
                                            <div
                                                key={c.id}
                                                className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 bg-white p-3 text-xs dark:border-slate-800 dark:bg-slate-900"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span className={c.is_completed ? "text-emerald-500 font-bold" : "text-slate-300"}>
                                                        {c.is_completed ? "✓" : "○"}
                                                    </span>
                                                    <span className={`font-medium ${c.is_completed ? "line-through text-slate-400" : "text-slate-800 dark:text-slate-200"}`}>
                                                        {c.item_title}
                                                    </span>
                                                    {c.assign_hours && (
                                                        <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                            {c.assign_hours} hrs
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {c.image_url_1 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewImage(c.image_url_1)}
                                                            className="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                        >
                                                            📷 Proof 1
                                                        </button>
                                                    )}
                                                    {c.image_url_2 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewImage(c.image_url_2)}
                                                            className="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                        >
                                                            📷 Proof 2
                                                        </button>
                                                    )}
                                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${c.is_completed
                                                        ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                        : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                                        }`}>
                                                        {c.is_completed ? "Done" : "Pending"}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Drafting & CAD Drawing Phase Section */}
                            {((isSurveyorTask && isSurveyApproved) || isDraftingAssigned || isCurrentUserDraftPerson || drawingRevisions.length > 0) && (
                                <div id="drafting-cad-workspace-card" className="rounded-2xl border-2 border-indigo-200 bg-indigo-50/40 p-5 dark:border-indigo-900/60 dark:bg-indigo-950/20 mt-6 shadow-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-100 pb-3 dark:border-indigo-900/60">
                                        <div className="flex items-center gap-2">
                                            <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-base text-white shadow-xs">
                                                📐
                                            </span>
                                            <div>
                                                <h4 className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                                    Drafting & CAD Drawing Workspace
                                                </h4>
                                                <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {assignedDraftPerson ? (
                                                        <span>Assigned Draft Person: <strong className="text-indigo-700 dark:text-indigo-300">{assignedDraftPerson.name}</strong></span>
                                                    ) : (
                                                        <span>Drafting job queued for project assignment</span>
                                                    )}
                                                </p>
                                                {(taskDraftingJob?.khasra_number || project?.khasra_number || taskDraftingJob?.site_area || project?.site_area || taskDraftingJob?.scope_of_work || project?.scope_of_work) && (
                                                    <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                                        {(taskDraftingJob?.khasra_number || project?.khasra_number) && (
                                                            <span className="inline-flex items-center gap-1 rounded bg-indigo-100/80 px-2 py-0.5 text-[10px] font-medium text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200">
                                                                <span>📍 Khasra:</span> <strong>{taskDraftingJob?.khasra_number || project?.khasra_number}</strong>
                                                            </span>
                                                        )}
                                                        {(taskDraftingJob?.site_area || project?.site_area) && (
                                                            <span className="inline-flex items-center gap-1 rounded bg-amber-100/80 px-2 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                                                                <span>📏 Area:</span> <strong>{taskDraftingJob?.site_area || project?.site_area}</strong>
                                                            </span>
                                                        )}
                                                        {(taskDraftingJob?.scope_of_work || project?.scope_of_work) && (
                                                            <span className="inline-flex items-center gap-1 rounded bg-slate-200/80 px-2 py-0.5 text-[10px] font-medium text-slate-800 dark:bg-slate-800 dark:text-slate-200 max-w-xs truncate" title={taskDraftingJob?.scope_of_work || project?.scope_of_work}>
                                                                <span>📋 Scope:</span> <span>{taskDraftingJob?.scope_of_work || project?.scope_of_work}</span>
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            {isDrawingApproved ? (
                                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                                    ✓ CAD Drawing Approved
                                                </span>
                                            ) : isDrawingSubmitted ? (
                                                <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                                    ⏳ Awaiting Drawing Review
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                                                    Drafting In Progress
                                                </span>
                                            )}

                                            {isCurrentUserDraftPerson && !isDrawingApproved && (
                                                <button
                                                    type="button"
                                                    onClick={() => setShowDraftUploadSection((prev) => !prev)}
                                                    className="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500"
                                                >
                                                    {showDraftUploadSection ? "Close Form" : "+ Upload CAD Drawing"}
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Existing Revisions List */}
                                    {drawingRevisions.length > 0 ? (
                                        <div className="mt-3.5 space-y-2.5">
                                            <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Submitted Drawing Revisions ({drawingRevisions.length})
                                            </p>
                                            {drawingRevisions.map((rev) => {
                                                const dwgPath = rev.dwg_document?.file_path || rev.dwg_file_path || rev.dwg_url;
                                                const pdfPath = rev.pdf_document?.file_path || rev.pdf_file_path || rev.pdf_url;
                                                const dwgHref = dwgPath ? (String(dwgPath).startsWith("http") || String(dwgPath).startsWith("/") ? dwgPath : "/" + dwgPath) : null;
                                                const pdfHref = pdfPath ? (String(pdfPath).startsWith("http") || String(pdfPath).startsWith("/") ? pdfPath : "/" + pdfPath) : null;

                                                return (
                                                    <div
                                                        key={rev.id}
                                                        className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-white p-3 shadow-2xs dark:border-indigo-900/50 dark:bg-slate-900"
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                <span className="rounded-md bg-indigo-100 px-2 py-0.5 text-[11px] font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                                                                    Rev #{rev.revision_no || 1}
                                                                </span>
                                                                <span className="text-xs font-bold text-slate-900 dark:text-white">
                                                                    {rev.drawing_no || rev.drawing_type || "CAD Layout"}
                                                                </span>
                                                                <StatusBadge value={rev.status || "submitted"} />
                                                            </div>
                                                            {rev.changes_made && (
                                                                <p className="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                                                    Notes: {rev.changes_made}
                                                                </p>
                                                            )}
                                                            <p className="mt-0.5 text-[10px] text-slate-400">
                                                                Uploaded {rev.uploaded_at ? formatDate(rev.uploaded_at) : "recently"}
                                                                {rev.uploaded_by?.name ? ` by ${rev.uploaded_by.name}` : ""}
                                                            </p>
                                                        </div>

                                                        <div className="flex flex-wrap items-center gap-2">
                                                            {dwgHref && (
                                                                <a
                                                                    href={dwgHref}
                                                                    target="_blank"
                                                                    rel="noreferrer noopener"
                                                                    download
                                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300"
                                                                >
                                                                    📥 Download DWG
                                                                </a>
                                                            )}
                                                            {pdfHref && (
                                                                <a
                                                                    href={pdfHref}
                                                                    target="_blank"
                                                                    rel="noreferrer noopener"
                                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300"
                                                                >
                                                                    📄 View PDF Sheet
                                                                </a>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}

                                            {/* Admin Drawing Approval Action Banner */}
                                            {!isCurrentUserDraftPerson && taskDraftingJob && (isDrawingSubmitted || isDrawingRevisionRequested) && (
                                                <div className={`mt-3 rounded-xl border p-3.5 ${
                                                    isAssignedToCurrentUserPendingApproval
                                                        ? "cad-action-blink-border bg-rose-50/90 dark:bg-rose-950/35"
                                                        : "border-amber-300 bg-amber-50/90 dark:border-amber-700 dark:bg-amber-950/40"
                                                }`}>
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <h5 className="text-xs font-bold text-amber-900 dark:text-amber-200">
                                                            {isAssignedToCurrentUserPendingApproval
                                                                ? "Action Needed: CAD Approval Assigned to You"
                                                                : "Admin Review for CAD Drawing"}
                                                        </h5>
                                                        {latestPendingApproval?.assigned_to?.name && (
                                                            <span className="rounded-full bg-amber-200/80 px-2 py-0.5 text-[10px] font-bold text-amber-900 dark:bg-amber-900/60 dark:text-amber-100">
                                                                Assigned to: {latestPendingApproval.assigned_to.name}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-0.5 text-xs text-amber-800 dark:text-amber-300">
                                                        Approving this drawing will mark drafting as completed and advance the project stage to <strong>Construction Execution</strong>.
                                                    </p>
                                                    <div className="mt-2.5 flex flex-wrap items-center justify-end gap-2">
                                                        <AssignApprovalAdminButton
                                                            projectId={project.id}
                                                            draftingJob={taskDraftingJob}
                                                            latestRevision={latestRevision}
                                                            pendingApproval={latestPendingApproval}
                                                            companyAdmins={companyApprovalAdmins}
                                                            routeName="super.construction.projects.drawing-approvals.assign-admin"
                                                            authorized={hasAssignApprovalAdminPerm}
                                                        />
                                                        <button
                                                            type="button"
                                                            disabled={isSubmittingDraftReview}
                                                            onClick={() => handleReviewDraftingJob("revision_requested")}
                                                            className="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-slate-900 dark:text-amber-200"
                                                        >
                                                            Request Drawing Revision
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={isSubmittingDraftReview}
                                                            onClick={() => handleReviewDraftingJob("approved")}
                                                            className="rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500 disabled:opacity-60"
                                                        >
                                                            {isSubmittingDraftReview ? "Approving..." : "✓ Approve CAD Drawing & Advance Stage"}
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="mt-3 rounded-xl border border-dashed border-indigo-200 p-4 text-center dark:border-indigo-900/50">
                                            <p className="text-xs font-medium text-slate-600 dark:text-slate-300">
                                                No CAD drawing files submitted yet for this task.
                                            </p>
                                            <p className="mt-0.5 text-[11px] text-slate-400">
                                                The assigned Draft Person can use the survey data above to generate CAD drawings and submit them below.
                                            </p>
                                        </div>
                                    )}

                                    {/* Inline CAD Submission Form (for Draft Person or Admin upload) */}
                                    {(showDraftUploadSection || (isCurrentUserDraftPerson && drawingRevisions.length === 0)) && (
                                        <form onSubmit={handleDraftSubmit} className="mt-4 rounded-xl border border-indigo-200 bg-white p-4 shadow-sm dark:border-indigo-900/60 dark:bg-slate-900">
                                            <h5 className="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                                <span>📤</span>
                                                <span>Submit CAD Drawing Files (DWG / PDF)</span>
                                            </h5>
                                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                Upload your finished drawing files for admin approval. Once approved, the project will move to Construction Execution.
                                            </p>

                                            <div className="mt-3.5 grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Drawing Title / Sheet Name *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={draftUploadForm.data.drawing_title}
                                                        onChange={(e) => draftUploadForm.setData("drawing_title", e.target.value)}
                                                        required
                                                        placeholder="e.g. Site Boundary & Topographic CAD Plan"
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Drawing Number / Code *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={draftUploadForm.data.drawing_no}
                                                        onChange={(e) => draftUploadForm.setData("drawing_no", e.target.value)}
                                                        required
                                                        placeholder="e.g. DWG-TSK-00012-01"
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Khasra Number
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={draftUploadForm.data.khasra_number}
                                                        onChange={(e) => draftUploadForm.setData("khasra_number", e.target.value)}
                                                        placeholder="e.g. Khasra 142/3, 144/1"
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Site Area
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={draftUploadForm.data.site_area}
                                                        onChange={(e) => draftUploadForm.setData("site_area", e.target.value)}
                                                        placeholder="e.g. 2,500 sq ft / 1.5 Acres"
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Drawing Type
                                                    </label>
                                                    <select
                                                        value={draftUploadForm.data.drawing_type}
                                                        onChange={(e) => draftUploadForm.setData("drawing_type", e.target.value)}
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    >
                                                        <option value="topographic">Topographic Survey Plan</option>
                                                        <option value="architectural">Architectural Layout</option>
                                                        <option value="structural">Structural Drawing</option>
                                                        <option value="civil">Civil Engineering Plan</option>
                                                        <option value="as_built">As-Built Drawing</option>
                                                        <option value="site_boundary">Site Boundary & Coordinates</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        Revision No
                                                    </label>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={draftUploadForm.data.revision_no}
                                                        onChange={(e) => draftUploadForm.setData("revision_no", e.target.value)}
                                                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        CAD DWG / DXF File
                                                    </label>
                                                    <input
                                                        type="file"
                                                        accept=".dwg,.dxf,.zip"
                                                        onChange={(e) => draftUploadForm.setData("dwg_file", e.target.files[0])}
                                                        className="mt-1 w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                        PDF Drawing Sheet / Print Preview
                                                    </label>
                                                    <input
                                                        type="file"
                                                        accept=".pdf,image/*"
                                                        onChange={(e) => draftUploadForm.setData("pdf_file", e.target.files[0])}
                                                        className="mt-1 w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-sky-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-sky-700 hover:file:bg-sky-100"
                                                    />
                                                </div>
                                            </div>

                                            <div className="mt-3">
                                                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                    Scope of Work
                                                </label>
                                                <textarea
                                                    rows={2}
                                                    value={draftUploadForm.data.scope_of_work}
                                                    onChange={(e) => draftUploadForm.setData("scope_of_work", e.target.value)}
                                                    placeholder="Detailed scope of CAD drawing (boundary demarcation, contours, structural alignment...)"
                                                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                />
                                            </div>

                                            <div className="mt-3">
                                                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                                    Changes Made / Revision Notes
                                                </label>
                                                <textarea
                                                    rows={2}
                                                    value={draftUploadForm.data.changes_made}
                                                    onChange={(e) => draftUploadForm.setData("changes_made", e.target.value)}
                                                    placeholder="Specify layer standards, contours, adjustments made from field coordinates..."
                                                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                />
                                            </div>

                                            <div className="mt-3 flex items-center justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setShowDraftUploadSection(false)}
                                                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    type="submit"
                                                    disabled={draftUploadForm.processing}
                                                    className="rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:from-emerald-500 hover:to-teal-500 disabled:opacity-60"
                                                >
                                                    {draftUploadForm.processing ? "Uploading Drawing..." : "Submit CAD Drawing for Approval ➔"}
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            )}

                            {/* Inline Revision Input Form */}
                            {showRevisionInput && (
                                <div className="rounded-2xl border-2 border-amber-300 bg-amber-50/90 p-4 dark:border-amber-700 dark:bg-amber-950/40">
                                    <h4 className="text-xs font-bold text-amber-900 dark:text-amber-200">
                                        {isCurrentUserDraftPerson ? "Request Clarification / Changes from Surveyor" : "Request Survey Revision from Field Team"}
                                    </h4>
                                    <p className="mt-1 text-xs text-amber-800 dark:text-amber-300">
                                        {isCurrentUserDraftPerson
                                            ? "Specify missing survey points, elevation details, or boundary issues needed to complete CAD drawings:"
                                            : "Describe what is missing or needs correction (e.g. \"Missing elevation points on boundary corner B-4; please resubmit\"):"}
                                    </p>
                                    <textarea
                                        value={revisionRemarks}
                                        onChange={(e) => setRevisionRemarks(e.target.value)}
                                        rows={3}
                                        placeholder={isCurrentUserDraftPerson ? "Enter clarification request for field surveyor..." : "Enter revision notes and instructions..."}
                                        className="mt-2.5 w-full rounded-xl border border-amber-300 bg-white p-3 text-xs text-slate-900 focus:border-amber-500 focus:outline-none dark:border-amber-700 dark:bg-slate-900 dark:text-white"
                                    />
                                    <div className="mt-3 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setShowRevisionInput(false)}
                                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            disabled={isSubmittingReview}
                                            onClick={() => handleReviewSurvey("revision_requested")}
                                            className="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-500 disabled:opacity-60"
                                        >
                                            {isSubmittingReview ? "Sending..." : "Submit Revision Request"}
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Modal Footer Actions */}
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-950">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowDetailsModal(false);
                                    setShowDraftUploadSection(false);
                                }}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Close
                            </button>

                            <div className="flex flex-wrap items-center gap-2">
                                {isCurrentUserDraftPerson ? (
                                    <>
                                        {/* Draft Person Actions */}
                                        {!showRevisionInput && (
                                            <button
                                                type="button"
                                                onClick={() => setShowRevisionInput(true)}
                                                className="rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                                            >
                                                Request Clarification from Surveyor
                                            </button>
                                        )}
                                        {!isDrawingApproved && (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setShowDraftUploadSection((prev) => {
                                                        const next = !prev;
                                                        if (next) {
                                                            setTimeout(() => {
                                                                document.getElementById('drafting-cad-workspace-card')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                                            }, 100);
                                                        }
                                                        return next;
                                                    });
                                                }}
                                                className="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:from-emerald-500 hover:to-teal-500"
                                            >
                                                {showDraftUploadSection ? "Hide CAD Form" : "Upload & Submit CAD Drawing ➔"}
                                            </button>
                                        )}
                                        {isDrawingApproved && (
                                            <span className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                                                ✓ CAD Drawing Approved — Ready for Construction
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        {/* Admin / Owner / Supervisor Actions */}
                                        {task.status === "completed" ? (
                                            <>
                                                <span className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                    ✓ Task Completed
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowDetailsModal(false);
                                                        setShowTransferModal(true);
                                                    }}
                                                    className="rounded-xl border border-violet-300 bg-violet-50 px-3.5 py-2 text-xs font-semibold text-violet-800 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200"
                                                >
                                                    Re-assign / Transfer Task
                                                </button>
                                            </>
                                        ) : (isSurveyApproved || isDraftingAssigned) ? (
                                            <>
                                                <span className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                    ✓ Survey Approved
                                                </span>
                                                {assignedDraftPerson && (
                                                    <span className="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 dark:border-violet-900/40 dark:bg-violet-950/40 dark:text-violet-300">
                                                        ⭐ Drafter: {assignedDraftPerson.name}
                                                    </span>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowDetailsModal(false);
                                                        setShowTransferModal(true);
                                                    }}
                                                    className="rounded-xl border border-violet-300 bg-violet-50 px-3.5 py-2 text-xs font-semibold text-violet-800 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200"
                                                >
                                                    Re-assign / Transfer Task
                                                </button>
                                            </>
                                        ) : (
                                            <>
                                                {!showRevisionInput && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setShowRevisionInput(true)}
                                                        className="rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                                                    >
                                                        Request Revision
                                                    </button>
                                                )}
                                                <button
                                                    type="button"
                                                    disabled={isSubmittingReview}
                                                    onClick={() => handleReviewSurvey("approved")}
                                                    className="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500 disabled:opacity-60"
                                                >
                                                    {isDriverTask || !isSurveyorTask ? "Complete Task" : "Approve Survey"}
                                                </button>
                                            </>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Task Assign / Transfer Modal */}
            {showTransferModal && (
                <Modal show={true} onClose={() => setShowTransferModal(false)} maxWidth="2xl">
                    <form onSubmit={handleTransferSubmit} className="p-6">
                        <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Assign / Transfer Task
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Transfer <strong>{task.task_code}</strong>: "{task.title}" to a project team member.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowTransferModal(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Current Assignee Banner */}
                        {(() => {
                            const currentAssignedName = assignedDraftPerson?.name || task.supervisor?.name || null;
                            const hasAssignedMember = Boolean(
                                currentAssignedName ||
                                task.supervisor_member_id ||
                                task.assigned_supervisor_member_id ||
                                (task.assignees && task.assignees.some((a) => isActiveAssignment(a)))
                            );

                            return (
                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                    <div className="flex items-center gap-2">
                                        <span className="text-slate-400">Current Assigned Member:</span>{" "}
                                        <strong className="text-slate-800 dark:text-slate-200">
                                            {currentAssignedName || "Unassigned"}
                                        </strong>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge value={task.status} />
                                        {hasAssignedMember && canTransferTask && (
                                            <button
                                                type="button"
                                                onClick={() => setShowUnassignConfirm(true)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 hover:border-rose-300 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/60 transition shadow-2xs"
                                                title="Remove current member assignment from this task"
                                            >
                                                <svg className="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                <span>Remove Assignment</span>
                                            </button>
                                        )}
                                    </div>
                                </div>
                            );
                        })()}

                        <div className="mt-4 space-y-4">
                            {/* Member Dropdown from projectTeamMembers */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Assign To Project Team Member <span className="text-rose-500">*</span>
                                </label>
                                <p className="text-[11px] text-slate-400 mb-1.5">
                                    Select from members assigned to this project's team:
                                </p>
                                {assignableTeamMembers.length > 0 ? (
                                    <select
                                        value={transferForm.data.member_id}
                                        onChange={(e) => {
                                            const mid = e.target.value;
                                            transferForm.setData((prev) => ({
                                                ...prev,
                                                member_id: mid,
                                                create_drafting_job: false,
                                            }));
                                        }}
                                        required
                                        className="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="">-- Choose Project Team Member --</option>
                                        {assignableTeamMembers.map((tm) => {
                                            const badges = tm.badges && tm.badges.length ? tm.badges : (tm.role_name ? [tm.role_name] : []);
                                            const badgeStr = badges.length > 0 ? ` [${badges.join("] [")}]` : "";
                                            return (
                                                <option key={tm.id} value={tm.member_id}>
                                                    {tm.name}{badgeStr}
                                                </option>
                                            );
                                        })}
                                    </select>
                                ) : (
                                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
                                        No non-Draftsman team members are available for this transfer.
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Target Due Date
                                </label>
                                <input
                                    type="date"
                                    value={transferForm.data.due_date}
                                    onChange={(e) => transferForm.setData("due_date", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            {/* Instructions & Scope */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Instructions & Drafting Scope / Remarks
                                </label>
                                <textarea
                                    value={transferForm.data.notes}
                                    onChange={(e) => transferForm.setData("notes", e.target.value)}
                                    rows={3}
                                    placeholder="e.g. Generate 2D topographic DWG with contour lines from points captured. Follow municipality standard layers..."
                                    className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowTransferModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={transferForm.processing || assignableTeamMembers.length === 0}
                                className="rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:from-violet-500 hover:to-indigo-500 disabled:opacity-60"
                            >
                                {transferForm.processing ? "Assigning Task..." : "Confirm & Transfer Task"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Remove Assignment Confirmation Modal */}
            {showUnassignConfirm && (
                <Modal show={true} onClose={() => !isUnassigning && setShowUnassignConfirm(false)} maxWidth="md">
                    <div className="p-6">
                        <div className="flex items-center gap-3 mb-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Remove Task Assignment
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Task: <strong>{task.task_code}</strong>
                                </p>
                            </div>
                        </div>

                        <p className="mb-6 text-xs text-slate-600 dark:text-slate-300">
                            Are you sure you want to remove <strong>{assignedDraftPerson?.name || task.supervisor?.name || "the assigned member"}</strong> from this task? This task will become <strong>Unassigned</strong> and will no longer appear on the member's task list or mobile dashboard.
                        </p>

                        <div className="flex justify-end gap-3">
                            <button
                                type="button"
                                disabled={isUnassigning}
                                onClick={() => setShowUnassignConfirm(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={isUnassigning}
                                onClick={handleUnassign}
                                className="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 disabled:opacity-60"
                            >
                                {isUnassigning ? "Removing..." : "Yes, Remove Assignment"}
                            </button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Task Delete Confirmation Modal */}
            {confirmingDelete ? (
                <Modal show={true} onClose={() => setConfirmingDelete(false)}>
                    <div className="p-6">
                        <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-white">Delete Execution Task</h3>
                        <p className="mb-6 text-sm text-slate-600 dark:text-slate-400">
                            Removing <strong>{task.task_code}</strong> will also clean up its checklists, assignees, and DPR links. This cannot be undone.
                        </p>
                        <div className="flex gap-3">
                            <button
                                onClick={() => setConfirmingDelete(false)}
                                className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleDelete}
                                className="flex-1 rounded-xl bg-rose-600 px-4 py-2 font-medium text-white hover:bg-rose-500"
                            >
                                Delete Task
                            </button>
                        </div>
                    </div>
                </Modal>
            ) : null}
        </>
    );
}

function formatDate(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function formatDateForInput(dateLike) {
    if (!dateLike) return "";
    if (typeof dateLike === "string") {
        const cleanStr = dateLike.trim();
        if (!cleanStr) return "";
        const isoMatch = cleanStr.match(/^(\d{4})[-/](\d{2})[-/](\d{2})/);
        if (isoMatch) {
            return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
        }
        const dmyMatch = cleanStr.match(/^(\d{2})[-/](\d{2})[-/](\d{4})/);
        if (dmyMatch) {
            return `${dmyMatch[3]}-${dmyMatch[2]}-${dmyMatch[1]}`;
        }
    }
    if (dateLike instanceof Date) {
        if (isNaN(dateLike.getTime())) return "";
        const y = dateLike.getFullYear();
        const m = String(dateLike.getMonth() + 1).padStart(2, "0");
        const d = String(dateLike.getDate()).padStart(2, "0");
        return `${y}-${m}-${d}`;
    }
    const d = new Date(dateLike);
    if (!isNaN(d.getTime())) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        return `${y}-${m}-${day}`;
    }
    return "";
}

function formatDateTime(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function formatNumber(value) {
    if (value === null || value === undefined || value === "") return "-";
    const n = Number(value);
    if (!Number.isFinite(n)) return String(value);
    return new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 }).format(n);
}

function SubMetric({ label, value, span = "" }) {
    return (
        <div className={span}>
            <p className="text-[11px] uppercase tracking-wide text-slate-400">{label}</p>
            <p className="mt-0.5 font-semibold text-slate-800 dark:text-slate-100">{value ?? "-"}</p>
        </div>
    );
}

function FileAttachment({ path, span = "" }) {
    if (!path) return <div className={span} />;
    const name = String(path).split("/").pop() || "document";
    const href = /^https?:\/\//i.test(String(path)) ? path : String(path).startsWith("/") ? path : "/" + String(path).replace(/^\/+/, "");
    return (
        <div className={span}>
            <p className="text-[11px] uppercase tracking-wide text-slate-400">Report / File</p>
            <a
                href={href}
                target="_blank"
                rel="noreferrer noopener"
                className="mt-0.5 inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-[11px] font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-300 dark:hover:bg-indigo-950/60"
            >
                📄 <span className="truncate max-w-[220px]">{name}</span>
            </a>
        </div>
    );
}

function PhotoGrid({ photos = [], className = "" }) {
    const photoList = Array.isArray(photos) ? photos : [];
    if (!photoList.length) return null;
    return (
        <div className={`grid gap-2 sm:grid-cols-3 ${className}`}>
            {photoList.map((p, idx) => {
                const safe = typeof p === "string" ? p : p?.url || p?.path || "";
                if (!safe) return null;
                const href = /^https?:\/\//i.test(safe) ? safe : safe.startsWith("/") ? safe : "/" + safe.replace(/^\/+/, "");
                return (
                    <a
                        key={idx}
                        href={href}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="group relative block aspect-video w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <img
                            src={href}
                            alt={`Survey photo ${idx + 1}`}
                            loading="lazy"
                            className="h-full w-full object-cover transition group-hover:scale-[1.03]"
                        />
                    </a>
                );
            })}
        </div>
    );
}

function SurveyVisitCard({ visit }) {
    const hasSubmitted = Boolean(visit.submission) || String(visit.status || "").toLowerCase() === "submitted";
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-semibold text-slate-900 dark:text-white">Visit #{visit.id}</p>
                    <p className="mt-1 text-xs text-slate-500">
                        Check-in by{" "}
                        <span className="font-medium text-slate-700 dark:text-slate-200">
                            {visit.checked_in_by?.name || visit.checkedInBy?.name || "Unknown"}
                        </span>
                        {" • "}
                        {formatDateTime(visit.check_in_at) || "No timestamp"}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <StatusBadge value={visit.status_key || visit.status || (hasSubmitted ? "submitted" : "planned")} />
                    {visit.gps_verified ? (
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            GPS Verified
                        </span>
                    ) : (
                        <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            GPS Not Verified
                        </span>
                    )}
                    {visit.day_number ? (
                        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Day {visit.day_number}
                        </span>
                    ) : null}
                </div>
            </div>

            {visit.submission ? (
                <div className="mt-3 rounded-xl bg-indigo-50/60 p-3 text-xs dark:bg-indigo-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="font-medium text-indigo-900 dark:text-indigo-100">Submission Status</p>
                        <StatusBadge value={visit.submission.status_key || visit.submission.status} />
                    </div>
                    <p className="mt-1 text-indigo-700/80 dark:text-indigo-200/80">
                        Submitted by {visit.submission.submitted_by?.name || visit.submission.submittedBy?.name || "Unknown"}
                        {" • "}
                        {formatDateTime(visit.submission.submitted_at) || "—"}
                    </p>
                </div>
            ) : null}

            <div className="mt-4 grid gap-2 text-xs sm:grid-cols-2">
                <SubMetric label="Total Points" value={visit.total_points_captured} />
                <SubMetric label="Distance (m)" value={formatNumber(visit.distance_covered_m)} />
                <SubMetric label="Elevation (m)" value={formatNumber(visit.elevation_m)} />
                <SubMetric label="Entries / Meas." value={`${visit.entries?.length || 0} / ${visit.measurements?.length || 0}`} />
                <SubMetric label="GPS Latitude" value={visit.check_in_latitude ? Number(visit.check_in_latitude).toFixed(6) : undefined} />
                <SubMetric label="GPS Longitude" value={visit.check_in_longitude ? Number(visit.check_in_longitude).toFixed(6) : undefined} />
                <FileAttachment path={visit.file_path} span="sm:col-span-2" />
            </div>

            {visit.remarks || visit.notes ? (
                <div className="mt-3 space-y-2">
                    {visit.remarks ? (
                        <div className="rounded-xl bg-amber-50/70 p-3 text-xs text-amber-900 dark:bg-amber-950/20 dark:text-amber-100">
                            <p className="font-semibold">Remarks</p>
                            <p className="mt-1 whitespace-pre-wrap break-words">{visit.remarks}</p>
                        </div>
                    ) : null}
                    {visit.notes ? (
                        <FieldNotesDisplay notes={visit.notes} />
                    ) : null}
                </div>
            ) : null}

            <PhotoGrid photos={visit.photos} className="mt-4" />
        </div>
    );
}

function SurveyPlanCard({ plan }) {
    const [showModal, setShowModal] = useState(false);
    const visits = plan.visits || [];

    return (
        <div className="rounded-2xl border border-slate-200/90 bg-slate-50/70 p-5 shadow-xs transition-all duration-200 hover:border-indigo-300/80 hover:bg-white hover:shadow-md dark:border-slate-800 dark:bg-slate-900/90 dark:hover:border-indigo-500/40">
            {/* Card Header: Code Badge, Title & Status */}
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <span className="inline-flex items-center rounded-lg border border-indigo-200/70 bg-indigo-50 px-2.5 py-1 font-mono text-xs font-bold text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-300">
                            {plan.survey_code}
                        </span>
                        <h4 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white tracking-tight">
                            {plan.title}
                        </h4>
                    </div>

                    {/* Metadata Row: Planned Date & Site Address */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <span className="inline-flex items-center gap-1.5">
                            <span>📅</span>
                            <span>Planned Date:</span>
                            <strong className="font-semibold text-slate-700 dark:text-slate-200">
                                {formatDate(plan.planned_date) || "Not scheduled"}
                            </strong>
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span>📍</span>
                            <span className="truncate max-w-md text-slate-600 dark:text-slate-300">
                                {plan.site_address || "No site address recorded"}
                            </span>
                        </span>
                    </div>
                </div>

                <div className="shrink-0 flex items-center gap-2">
                    <StatusBadge value={plan.status_key || plan.status} />
                </div>
            </div>

            {/* Assigned Survey Team Members */}
            {(plan.plan_members || []).length > 0 && (
                <div className="mt-4 flex flex-wrap items-center gap-2">
                    {plan.plan_members.map((member) => (
                        <span
                            key={member.id}
                            className="inline-flex items-center gap-1.5 rounded-full border border-slate-200/90 bg-white px-3 py-1 text-xs font-medium text-slate-700 shadow-2xs dark:border-slate-700/80 dark:bg-slate-800/90 dark:text-slate-200"
                        >
                            <span className="text-[11px]">👤</span>
                            <span className="font-semibold">{member.member?.name || "Unknown"}</span>
                            <span className="text-slate-300 dark:text-slate-600">•</span>
                            <span className="capitalize text-slate-500 dark:text-slate-400">{member.role_in_survey}</span>
                        </span>
                    ))}
                </div>
            )}

            {/* Bottom bar with Visits Summary & View More button */}
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/70 pt-3.5 dark:border-slate-800">
                <div className="flex items-center gap-2.5 text-xs text-slate-500 dark:text-slate-400">
                    <span className="flex h-6 w-6 items-center justify-center rounded-full border border-indigo-200/60 bg-indigo-50 text-[11px] font-bold text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-300">
                        {visits.length}
                    </span>
                    <span className="font-medium">
                        {visits.length === 0
                            ? "No field check-in visits yet"
                            : `${visits.length} field visit${visits.length > 1 ? "s" : ""} recorded`}
                    </span>
                </div>

                <button
                    type="button"
                    onClick={() => setShowModal(true)}
                    className="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50/90 px-4 py-2 text-xs font-semibold text-indigo-700 shadow-xs transition hover:border-indigo-300 hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-200 dark:hover:border-indigo-400 dark:hover:bg-indigo-500/25"
                >
                    <span>👁️</span>
                    <span>View More Details</span>
                    {visits.length > 0 && (
                        <span className="inline-flex items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white dark:bg-indigo-500">
                            {visits.length}
                        </span>
                    )}
                    <span className="text-indigo-500 dark:text-indigo-300">↗</span>
                </button>
            </div>

            {/* Survey Plan Details Modal */}
            {showModal && (
                <Modal show={true} onClose={() => setShowModal(false)} maxWidth="5xl">
                    <div className="flex max-h-[88vh] flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {plan.survey_code}
                                    </span>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        {plan.title}
                                    </h3>
                                    <StatusBadge value={plan.status_key || plan.status} />
                                </div>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Planned Date: {formatDate(plan.planned_date) || "No planned date"}
                                    {plan.site_address ? ` • 📍 ${plan.site_address}` : ""}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Modal Body - Scrollable */}
                        <div className="flex-1 space-y-6 overflow-y-auto p-6">
                            {/* Plan Overview Card */}
                            <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-indigo-50/20 p-5 shadow-2xs dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
                                <h4 className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    Survey Plan Overview
                                </h4>
                                <div className="mt-3 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                                    <div>
                                        <p className="text-slate-400">Survey Code</p>
                                        <p className="font-mono font-bold text-indigo-600 dark:text-indigo-400">{plan.survey_code}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Planned Date</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{formatDate(plan.planned_date) || "N/A"}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Status</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{plan.status_key || plan.status || "In Progress"}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Field Visits</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{visits.length} recorded</p>
                                    </div>
                                </div>
                                {plan.site_address && (
                                    <div className="mt-3 rounded-xl bg-slate-100/70 p-3 text-xs text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                        <span className="font-semibold text-slate-900 dark:text-white">📍 Site Location: </span>
                                        {plan.site_address}
                                    </div>
                                )}
                                {(plan.plan_members || []).length > 0 && (
                                    <div className="mt-3 border-t border-slate-200/60 pt-3 dark:border-slate-800">
                                        <p className="text-[11px] font-medium text-slate-400 mb-1.5">Assigned Survey Team:</p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {plan.plan_members.map((m) => (
                                                <span key={m.id} className="rounded-lg bg-white px-2.5 py-1 text-xs border border-slate-200 font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                    👤 {m.member?.name || "Unknown"} ({m.role_in_survey})
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Captured Field Visits */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <span>📍</span>
                                        <span>Field Check-in Visits & Survey Reports ({visits.length})</span>
                                    </h4>
                                    {visits.length > 0 && (
                                        <span className="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                            Latest: Day {visits[0]?.day_number || 1}
                                        </span>
                                    )}
                                </div>

                                {visits.length > 0 ? (
                                    <div className="space-y-4">
                                        {visits.map((visit) => (
                                            <SurveyVisitCard key={visit.id} visit={visit} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-800">
                                        <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl dark:bg-amber-950/40">
                                            📋
                                        </span>
                                        <h4 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                                            No Field Visits Captured Yet
                                        </h4>
                                        <p className="mt-1 max-w-md mx-auto text-xs text-slate-500">
                                            Field surveyors have not submitted check-in visits for this survey plan yet.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="flex items-center justify-end border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-950">
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}

function LocationDisplay({ locationName, address, lat, lng, className = "" }) {
    const hasCoords =
        lat != null && lng != null && lat !== "" && lng !== "" && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng));
    const displayAddr = locationName || address;
    return (
        <div className={className}>
            <dt className="text-sm text-slate-500">📍 Location</dt>
            <dd className="mt-1 space-y-1 font-medium text-slate-900 dark:text-white">
                {displayAddr ? (
                    <div className="break-words leading-snug">{displayAddr}</div>
                ) : (
                    <div className="text-slate-400">Not set</div>
                )}
                {locationName && address && locationName !== address ? (
                    <div className="text-xs font-normal text-slate-500 break-words">
                        Raw address: {address}
                    </div>
                ) : null}
                {hasCoords ? (
                    <div className="flex flex-wrap items-center gap-3 text-xs font-normal text-slate-500">
                        <span>
                            Lat <span className="font-mono text-slate-700 dark:text-slate-300">{Number(lat).toFixed(6)}</span>
                            {" · "}
                            Lng <span className="font-mono text-slate-700 dark:text-slate-300">{Number(lng).toFixed(6)}</span>
                        </span>
                        <a
                            href={`https://www.google.com/maps?q=${encodeURIComponent(Number(lat))},${encodeURIComponent(Number(lng))}`}
                            target="_blank"
                            rel="noreferrer noopener"
                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-indigo-400 dark:hover:bg-slate-800"
                        >
                            Open in Maps
                        </a>
                    </div>
                ) : null}
            </dd>
        </div>
    );
}

function mergedTasksForExecutionList(project) {
    return mergeProjectExecutionTasks(project);
}

function Field({ label, value, span = "" }) {
    return (
        <div className={span}>
            <dt className="text-sm text-slate-500">{label}</dt>
            <dd className="mt-1 font-medium text-slate-900 dark:text-white">{value || "-"}</dd>
        </div>
    );
}

function SnapshotRow({ label, value, badge = null }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-slate-900 dark:text-white">{label}</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{value || "Not available yet"}</p>
                </div>
                {badge ? <StatusBadge value={badge} /> : null}
            </div>
        </div>
    );
}

function InputField({ form, name, label, type = "text", placeholder = "" }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input
                type={type}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                placeholder={placeholder}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TextAreaField({ form, name, label, rows = 4 }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <textarea
                rows={rows}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function SelectField({ form, name, label, options, onChangeCustom }) {
    const selectedVal = form.data[name];
    const selectedOption = options.find((opt) => String(opt.value) === String(selectedVal));
    const selectedBadges = selectedOption?.badges || [];

    return (
        <div>
            <div className="mb-1 flex flex-wrap items-center justify-between gap-1.5">
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
                {selectedBadges.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1">
                        {selectedBadges.map((badge, idx) => (
                            <span
                                key={idx}
                                className="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-950/60 dark:text-indigo-300 dark:ring-indigo-400/30"
                            >
                                {badge}
                            </span>
                        ))}
                    </div>
                )}
            </div>
            <select
                value={form.data[name]}
                onChange={(e) => {
                    form.setData(name, e.target.value);
                    if (onChangeCustom) onChangeCustom(e.target.value);
                }}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TeamMemberRow({ teamMember, project, roles, canEditTeamMember = true, canDeleteTeamMember = true }) {
    const [showEditModal, setShowEditModal] = useState(false);
    const [showRemoveModal, setShowRemoveModal] = useState(false);

    const memberId = teamMember.member_id;
    const allLogs = [];

    (project.attendance_records || []).forEach((r) => {
        if (Number(r.member_id) === Number(memberId)) {
            allLogs.push({
                check_in_at: r.check_in_at,
                check_out_at: r.check_out_at,
                duration_minutes: r.duration_minutes || null,
            });
        }
    });

    (project.survey_plans || []).forEach((plan) => {
        (plan.visits || []).forEach((v) => {
            const checkedInId = v.checked_in_by_member_id || v.checkedInBy?.id || v.checked_in_by?.id;
            if (Number(checkedInId) === Number(memberId)) {
                allLogs.push({
                    check_in_at: v.check_in_at,
                    check_out_at: v.check_out_at,
                    duration_minutes: v.duration_minutes || null,
                });
            }
        });
    });

    if (Array.isArray(project.survey_visits)) {
        project.survey_visits.forEach((v) => {
            const checkedInId = v.checked_in_by_member_id || v.checkedInBy?.id || v.checked_in_by?.id;
            if (Number(checkedInId) === Number(memberId)) {
                allLogs.push({
                    check_in_at: v.check_in_at,
                    check_out_at: v.check_out_at,
                    duration_minutes: v.duration_minutes || null,
                });
            }
        });
    }

    const map = new Map();
    allLogs.forEach((log) => {
        if (!log.check_in_at) return;
        const key = new Date(log.check_in_at).getTime();
        if (!map.has(key)) {
            map.set(key, { ...log });
        } else {
            const existing = map.get(key);
            if (!existing.check_out_at && log.check_out_at) {
                existing.check_out_at = log.check_out_at;
            }
            if (!existing.duration_minutes && log.duration_minutes) {
                existing.duration_minutes = log.duration_minutes;
            }
        }
    });

    const sortedLogs = [...map.values()].sort((a, b) => {
        const at = a.check_in_at ? new Date(a.check_in_at).getTime() : 0;
        const bt = b.check_in_at ? new Date(b.check_in_at).getTime() : 0;
        return bt - at;
    });

    const latestLog = sortedLogs[0];

    let totalMinutes = 0;
    sortedLogs.forEach((r) => {
        if (r.check_in_at && r.check_out_at) {
            const diff = Math.max(0, (new Date(r.check_out_at).getTime() - new Date(r.check_in_at).getTime()) / 60000);
            totalMinutes += diff;
        } else if (r.duration_minutes) {
            totalMinutes += Number(r.duration_minutes);
        } else if (r.check_in_at && !r.check_out_at) {
            const diff = Math.max(0, (new Date().getTime() - new Date(r.check_in_at).getTime()) / 60000);
            totalMinutes += diff;
        }
    });

    const hrs = Math.floor(totalMinutes / 60);
    const mins = Math.floor(totalMinutes % 60);
    const workingHoursStr = totalMinutes > 0 ? `${hrs}h ${mins}m` : "0h 0m";

    const formatTime = (ts) => {
        if (!ts) return "-";
        const d = new Date(ts);
        if (isNaN(d.getTime())) return "-";
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const checkInTimeStr = latestLog?.check_in_at ? formatTime(latestLog.check_in_at) : "-";
    const checkOutTimeStr = latestLog?.check_out_at ? formatTime(latestLog.check_out_at) : (latestLog?.check_in_at ? "Checked In (Active)" : "-");
    const isCheckedIn = !!(latestLog?.check_in_at && !latestLog?.check_out_at);

    const editForm = useForm({
        member_id: teamMember.member_id,
        role_id: teamMember.role_id || "",
        assignment_scope: teamMember.assignment_scope || "",
        is_primary: teamMember.is_primary,
        status: teamMember.status,
    });

    // Re-sync form data whenever modal opens or teamMember updates
    useEffect(() => {
        if (showEditModal) {
            editForm.setData({
                member_id: teamMember.member_id,
                role_id: teamMember.role_id || "",
                assignment_scope: teamMember.assignment_scope || "",
                is_primary: teamMember.is_primary,
                status: teamMember.status,
            });
        }
    }, [showEditModal, teamMember]);

    const openEditModal = () => {
        setShowEditModal(true);
    };

    const handleEditSubmit = (e) => {
        e.preventDefault();
        editForm.put(route("super.construction.projects.team.update", [project.id, teamMember.id]), {
            preserveScroll: true,
            onSuccess: () => {
                setShowEditModal(false);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    const handleRemove = () => {
        setShowRemoveModal(false);
        router.delete(route("super.construction.projects.team.destroy", [project.id, teamMember.id]), {
            preserveScroll: true,
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    const handleStatusToggle = () => {
        router.patch(route("super.construction.projects.team.status", [project.id, teamMember.id]), {}, {
            preserveScroll: true,
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    };

    return (
        <>
            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <p className="font-semibold text-slate-900 dark:text-white">{teamMember.member?.name || "Unknown member"}</p>
                            {teamMember.is_primary && (
                                <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">
                                    Primary
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-slate-500">{teamMember.role?.name || "No role assigned"}</p>
                        <p className="mt-1 text-xs text-slate-500">{teamMember.assignment_scope || "General project support"}</p>
                        <div className="mt-2">
                            <StatusBadge value={teamMember.status} />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={() => router.visit(route("super.construction.projects.team.show", [project.id, teamMember.id]))}
                            className="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/30"
                        >
                            View Details
                        </button>
                        {canEditTeamMember && (
                            <button
                                onClick={openEditModal}
                                className="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                            >
                                Edit
                            </button>
                        )}
                        {canEditTeamMember && (
                            <button
                                onClick={handleStatusToggle}
                                className={`rounded-lg px-3 py-1.5 text-xs font-medium ${teamMember.status === 'active'
                                    ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/30'
                                    : 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/20 dark:text-green-300 dark:hover:bg-green-900/30'
                                    }`}
                            >
                                {teamMember.status === 'active' ? 'Deactivate' : 'Activate'}
                            </button>
                        )}
                        {canDeleteTeamMember && (
                            <button
                                onClick={() => setShowRemoveModal(true)}
                                className="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/30"
                            >
                                Remove
                            </button>
                        )}
                    </div>
                </div>

                {/* Member Check-In, Check-Out & Working Hours Dynamic Section */}
                <div className="mt-3 grid gap-2 sm:grid-cols-3 rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-950">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-500">📥 Check-In:</span>
                        <span className="text-xs font-bold text-slate-800 dark:text-slate-200">{checkInTimeStr}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-500">📤 Check-Out:</span>
                        <span className={`text-xs font-bold ${isCheckedIn ? 'text-emerald-600 animate-pulse' : 'text-slate-800 dark:text-slate-200'}`}>
                            {checkOutTimeStr}
                        </span>
                    </div>
                    <div className="flex items-center gap-2 sm:justify-end">
                        <span className="text-xs font-semibold text-slate-500">⏱️ Working Hours:</span>
                        <span className="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                            {workingHoursStr}
                        </span>
                    </div>
                </div>
            </div>

            {/* Edit Modal */}
            <Modal show={showEditModal} onClose={() => setShowEditModal(false)}>
                <div className="p-6">
                    <h3 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Edit Team Member Assignment</h3>
                    <form onSubmit={handleEditSubmit} className="grid gap-4">
                        <SelectField
                            form={editForm}
                            name="role_id"
                            label="Construction Role"
                            options={[
                                { value: "", label: "-- Select Construction Role --" },
                                ...roles.map((role) => ({ value: role.id, label: role.name })),
                            ]}
                        />
                        <InputField
                            form={editForm}
                            name="assignment_scope"
                            label="Assignment Scope"
                            placeholder="Survey, drafting, approvals, coordination..."
                        />
                        <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                            <input
                                type="checkbox"
                                checked={editForm.data.is_primary}
                                onChange={(e) => editForm.setData("is_primary", e.target.checked)}
                            />
                            Mark this assignment as primary
                        </label>
                        <SelectField
                            form={editForm}
                            name="status"
                            label="Status"
                            options={[
                                { value: "active", label: "Active" },
                                { value: "inactive", label: "Inactive" },
                            ]}
                        />
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={() => setShowEditModal(false)}
                                className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="flex-1 rounded-xl bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {editForm.processing ? "Saving..." : "Save Changes"}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>

            {/* Remove Confirmation Modal */}
            <Modal show={showRemoveModal} onClose={() => setShowRemoveModal(false)}>
                <div className="p-6">
                    <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-white">Remove Team Member</h3>
                    <p className="mb-6 text-sm text-slate-600 dark:text-slate-400">
                        Are you sure you want to remove <strong>{teamMember.member?.name}</strong> from this project? This action cannot be undone.
                    </p>
                    <div className="flex gap-3">
                        <button
                            onClick={() => setShowRemoveModal(false)}
                            className="flex-1 rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={handleRemove}
                            disabled={editForm.processing}
                            className="flex-1 rounded-xl bg-rose-600 px-4 py-2 font-medium text-white hover:bg-rose-500 disabled:opacity-60"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
