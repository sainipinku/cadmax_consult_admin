import { router, useForm } from "@inertiajs/react";
import {
    FileText,
    Pencil,
    RefreshCw,
    Trash2,
    Upload,
} from "lucide-react";
import { useMemo, useState } from "react";
import Modal from "@/Components/Modal";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import SurveyChecklistManager from "./SurveyChecklistManager";

const DEFAULT_SURVEY_STATUS_CODES = Object.freeze({
    draft: 0,
    planned: 1,
    in_progress: 2,
    submitted: 3,
    approved: 4,
    revision_requested: 5,
    rejected: 6,
});

export default function SurveyWorkspace({
    surveyPlans = [],
    surveySubmissions = [],
    projects = [],
    members = [],
    projectPermissions = {},
    surveyPlanStatuses = [1, 2],
    surveyStatusCodes = DEFAULT_SURVEY_STATUS_CODES,
    documentLimits = {},
    routePrefix = "admin.construction",
    variant = "admin",
}) {
    const documentRouteBase = `${routePrefix}.documents`;

    const manageableProjects = useMemo(
        () =>
            projects.filter((project) =>
                hasProjectPermission(
                    projectPermissions,
                    project.id,
                    "survey_plan.manage",
                ),
            ),
        [projectPermissions, projects],
    );

    const planForm = useForm({
        project_id: manageableProjects[0]?.id || "",
        title: "",
        description: "",
        site_address: "",
        site_latitude: "",
        site_longitude: "",
        planned_date: "",
        planned_start_time: "",
        planned_end_time: "",
        member_ids: [],
    });

    const [editingPlan, setEditingPlan] = useState(null);
    const [statusPlan, setStatusPlan] = useState(null);
    const [uploadPlan, setUploadPlan] = useState(null);
    const [deletingDocumentId, setDeletingDocumentId] =
        useState(null);

    const editForm = useForm(emptyPlanForm());
    const statusForm = useForm({
        status: "",
    });
    const uploadForm = useForm({
        documents: [],
    });

    const createMembers = useMemo(
        () =>
            membersForProject(
                members,
                planForm.data.project_id,
            ),
        [members, planForm.data.project_id],
    );

    const editMembers = useMemo(
        () =>
            membersForProject(
                members,
                editingPlan?.project_id,
            ),
        [members, editingPlan?.project_id],
    );

    const stats = {
        totalPlans: surveyPlans.length,

        planned: surveyPlans.filter(
            (item) =>
                Number(item.status)
                === surveyStatusCodes.planned,
        ).length,

        inProgress: surveyPlans.filter(
            (item) =>
                Number(item.status)
                === surveyStatusCodes.in_progress,
        ).length,

        pendingReview: surveySubmissions.filter(
            (item) =>
                Number(item.status)
                === surveyStatusCodes.submitted,
        ).length,

        revisionRequested: surveySubmissions.filter(
            (item) =>
                Number(item.status)
                === surveyStatusCodes.revision_requested,
        ).length,

        approved: surveySubmissions.filter(
            (item) =>
                Number(item.status)
                === surveyStatusCodes.approved,
        ).length,
    };

    const submitPlan = (event) => {
        event.preventDefault();

        planForm.post(
            route(
                `${routePrefix}.survey.plans.store`,
            ),
            {
                preserveScroll: true,

                onSuccess: () => {
                    planForm.reset(
                        "title",
                        "description",
                        "site_address",
                        "site_latitude",
                        "site_longitude",
                        "planned_date",
                        "planned_start_time",
                        "planned_end_time",
                        "member_ids",
                    );
                },
            },
        );
    };

    const openEditModal = (plan) => {
        editForm.clearErrors();

        editForm.setData({
            title: plan.title || "",
            description: plan.description || "",
            site_address: plan.site_address || "",
            site_latitude:
                plan.site_latitude ?? "",
            site_longitude:
                plan.site_longitude ?? "",
            planned_date:
                dateInputValue(plan.planned_date),
            planned_start_time:
                timeInputValue(
                    plan.planned_start_time,
                ),
            planned_end_time:
                timeInputValue(
                    plan.planned_end_time,
                ),
            member_ids:
                (plan.plan_members || []).map(
                    (item) =>
                        String(item.member_id),
                ),
        });

        setEditingPlan(plan);
    };

    const closeEditModal = () => {
        editForm.clearErrors();
        editForm.reset();
        setEditingPlan(null);
    };

    const submitEdit = (event) => {
        event.preventDefault();

        if (!editingPlan) {
            return;
        }

        editForm.put(
            route(
                `${routePrefix}.survey.plans.update`,
                editingPlan.id,
            ),
            {
                preserveScroll: true,
                onSuccess: closeEditModal,
            },
        );
    };

    const openStatusModal = (plan) => {
        statusForm.clearErrors();

        const nextStatus =
            availablePlanStatuses(
                plan,
                surveyPlanStatuses,
                surveyStatusCodes.planned,
            ).find(
                (status) =>
                    Number(status)
                    !== Number(plan.status),
            )
            ?? Number(plan.status);

        statusForm.setData(
            "status",
            nextStatus,
        );

        setStatusPlan(plan);
    };

    const closeStatusModal = () => {
        statusForm.clearErrors();
        statusForm.reset();
        setStatusPlan(null);
    };

    const submitStatus = (event) => {
        event.preventDefault();

        if (!statusPlan) {
            return;
        }

        statusForm.patch(
            route(
                `${routePrefix}.survey.plans.status.update`,
                statusPlan.id,
            ),
            {
                preserveScroll: true,
                onSuccess: closeStatusModal,
            },
        );
    };

    const openUploadModal = (plan) => {
        uploadForm.clearErrors();

        uploadForm.setData(
            "documents",
            [],
        );

        setUploadPlan(plan);
    };

    const closeUploadModal = () => {
        uploadForm.clearErrors();
        uploadForm.reset();
        setUploadPlan(null);
    };

    const submitDocuments = (event) => {
        event.preventDefault();

        if (!uploadPlan) {
            return;
        }

        uploadForm.post(
            route(
                `${routePrefix}.survey.plans.documents.store`,
                uploadPlan.id,
            ),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: closeUploadModal,
            },
        );
    };

    const deleteDocument = (
        plan,
        document,
    ) => {
        const confirmed = window.confirm(
            `Delete “${document.original_name}”? This cannot be undone.`,
        );

        if (!confirmed) {
            return;
        }

        setDeletingDocumentId(
            document.id,
        );

        router.delete(
            route(
                `${routePrefix}.survey.plans.documents.destroy`,
                [
                    plan.id,
                    document.id,
                ],
            ),
            {
                preserveScroll: true,

                onFinish: () =>
                    setDeletingDocumentId(null),
            },
        );
    };

    return (
        <ConstructionShell
            title="Survey Control"
            description="Plan assigned survey work, manage documents, monitor field execution, and complete submission reviews."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard
                    label="Survey Plans"
                    value={stats.totalPlans}
                />

                <StatCard
                    label="Planned"
                    value={stats.planned}
                />

                <StatCard
                    label="In Progress"
                    value={stats.inProgress}
                />

                <StatCard
                    label="Pending Review"
                    value={stats.pendingReview}
                />

                <StatCard
                    label="Revision Asked"
                    value={stats.revisionRequested}
                />

                <StatCard
                    label="Approved"
                    value={stats.approved}
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-[430px,1fr]">
                <SectionCard
                    title="Create Survey Plan"
                    description="Schedule an assigned project and select only active members from that project team."
                >
                    {manageableProjects.length ? (
                        <form
                            onSubmit={submitPlan}
                            className="space-y-4"
                        >
                            <SelectField
                                form={planForm}
                                name="project_id"
                                label="Project"
                                placeholder="Select a project"
                                options={manageableProjects.map(
                                    (project) => ({
                                        value:
                                            project.id,

                                        label:
                                            `${project.project_code} • ${project.name}`,
                                    }),
                                )}
                                onChange={(value) => {
                                    planForm.setData(
                                        (data) => ({
                                            ...data,
                                            project_id:
                                                value,
                                            member_ids:
                                                [],
                                        }),
                                    );
                                }}
                            />

                            <InputField
                                form={planForm}
                                name="title"
                                label="Survey Title"
                            />

                            <TextAreaField
                                form={planForm}
                                name="description"
                                label="Survey Scope"
                                rows={4}
                            />

                            <TextAreaField
                                form={planForm}
                                name="site_address"
                                label="Site Address"
                                rows={3}
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                <InputField
                                    form={planForm}
                                    name="site_latitude"
                                    label="Latitude"
                                    inputMode="decimal"
                                />

                                <InputField
                                    form={planForm}
                                    name="site_longitude"
                                    label="Longitude"
                                    inputMode="decimal"
                                />

                                <InputField
                                    form={planForm}
                                    name="planned_date"
                                    label="Planned Date"
                                    type="date"
                                />

                                <InputField
                                    form={planForm}
                                    name="planned_start_time"
                                    label="Start Time"
                                    type="time"
                                />
                            </div>

                            <InputField
                                form={planForm}
                                name="planned_end_time"
                                label="End Time"
                                type="time"
                            />

                            <MemberSelect
                                form={planForm}
                                members={createMembers}
                            />

                            <button
                                type="submit"
                                disabled={
                                    planForm.processing
                                    || !planForm.data
                                        .project_id
                                }
                                className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {planForm.processing
                                    ? "Creating..."
                                    : "Create Survey Plan"}
                            </button>
                        </form>
                    ) : (
                        <EmptyState
                            title={
                                projects.length
                                    ? "Plan management is unavailable."
                                    : "No assigned projects."
                            }
                            description={
                                projects.length
                                    ? "Your current role can review submissions but cannot create survey plans."
                                    : "You need an active project-team assignment before creating a survey plan."
                            }
                        />
                    )}
                </SectionCard>

                <div className="space-y-6">
                    <SectionCard
                        title="Survey Plans"
                        description="Edit assignments, control pre-submission status, and manage survey plan documents."
                    >
                        {surveyPlans.length ? (
                            <div className="space-y-4">
                                {surveyPlans.map(
                                    (plan) => {
                                        const canManagePlan =
                                            hasProjectPermission(
                                                projectPermissions,
                                                plan.project_id,
                                                "survey_plan.manage",
                                            );

                                        const canManagePlanDocuments =
                                            hasProjectPermission(
                                                projectPermissions,
                                                plan.project_id,
                                                "document.manage",
                                            );

                                        const fieldWorkStarted =
                                            Number(
                                                plan.visits_count
                                                || 0,
                                            ) > 0;

                                        const canEditPlan =
                                            canManagePlan
                                            && surveyPlanStatuses.some(
                                                (
                                                    status,
                                                ) =>
                                                    Number(
                                                        status,
                                                    )
                                                    === Number(
                                                        plan.status,
                                                    ),
                                            )
                                            && !fieldWorkStarted;

                                        const canChangePlanStatus =
                                            canManagePlan
                                            && availablePlanStatuses(
                                                plan,
                                                surveyPlanStatuses,
                                                surveyStatusCodes.planned,
                                            ).some(
                                                (
                                                    status,
                                                ) =>
                                                    Number(
                                                        status,
                                                    )
                                                    !== Number(
                                                        plan.status,
                                                    ),
                                            );

                                        return (
                                            <article
                                                key={
                                                    plan.id
                                                }
                                                className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="font-semibold text-slate-900 dark:text-white">
                                                            {
                                                                plan.title
                                                            }
                                                        </p>

                                                        <p className="text-sm text-slate-500">
                                                            {plan
                                                                .project
                                                                ?.name
                                                                || "Unknown project"}
                                                            {" • "}
                                                            {
                                                                plan.survey_code
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-sm text-slate-500">
                                                            {plan.site_address
                                                                || "No site address set."}
                                                        </p>
                                                    </div>

                                                    <StatusBadge
                                                        value={
                                                            plan.status_key
                                                        }
                                                    />
                                                </div>

                                                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                                    <MetaPill
                                                        label="Planned Date"
                                                        value={formatDate(
                                                            plan.planned_date,
                                                        )}
                                                    />

                                                    <MetaPill
                                                        label="Time Window"
                                                        value={
                                                            [
                                                                formatTime(
                                                                    plan.planned_start_time,
                                                                ),

                                                                formatTime(
                                                                    plan.planned_end_time,
                                                                ),
                                                            ]
                                                                .filter(
                                                                    Boolean,
                                                                )
                                                                .join(
                                                                    " - ",
                                                                )
                                                            || "Not set"
                                                        }
                                                    />

                                                    <MetaPill
                                                        label="Documents"
                                                        value={String(
                                                            plan.documents
                                                                ?.length
                                                            || 0,
                                                        )}
                                                    />
                                                </div>

                                                <div className="mt-4 flex flex-wrap gap-2">
                                                    {(
                                                        plan.plan_members
                                                        || []
                                                    ).map(
                                                        (
                                                            item,
                                                        ) => (
                                                            <span
                                                                key={
                                                                    item.id
                                                                }
                                                                className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                            >
                                                                {item
                                                                    .member
                                                                    ?.name
                                                                    || "Unknown"}
                                                                {" • "}
                                                                {item.role_in_survey
                                                                    || "surveyor"}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>

                                                <SurveyChecklistManager
                                                    plan={plan}
                                                    routePrefix={routePrefix}
                                                    canManage={canEditPlan}
                                                />

                                                <PlanDocuments
                                                    plan={
                                                        plan
                                                    }
                                                    documentRouteBase={
                                                        documentRouteBase
                                                    }
                                                    canViewDocuments={
                                                        canManagePlanDocuments
                                                    }
                                                    canManageDocuments={
                                                        canManagePlanDocuments
                                                    }
                                                    deletingDocumentId={
                                                        deletingDocumentId
                                                    }
                                                    onDelete={
                                                        deleteDocument
                                                    }
                                                />

                                                <div className="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                                                    {canEditPlan ? (
                                                        <>
                                                            <ActionButton
                                                                icon={
                                                                    Pencil
                                                                }
                                                                onClick={() =>
                                                                    openEditModal(
                                                                        plan,
                                                                    )
                                                                }
                                                            >
                                                                Edit
                                                                Plan
                                                            </ActionButton>

                                                            {canChangePlanStatus ? (
                                                                <ActionButton
                                                                    icon={
                                                                        RefreshCw
                                                                    }
                                                                    onClick={() =>
                                                                        openStatusModal(
                                                                            plan,
                                                                        )
                                                                    }
                                                                >
                                                                    Change
                                                                    Status
                                                                </ActionButton>
                                                            ) : null}
                                                        </>
                                                    ) : null}

                                                    {canManagePlanDocuments ? (
                                                        <ActionButton
                                                            icon={
                                                                Upload
                                                            }
                                                            primary
                                                            onClick={() =>
                                                                openUploadModal(
                                                                    plan,
                                                                )
                                                            }
                                                        >
                                                            Upload
                                                            Documents
                                                        </ActionButton>
                                                    ) : null}
                                                </div>

                                                {!canEditPlan
                                                    && canManagePlan ? (
                                                    <p className="mt-3 text-right text-xs text-slate-500">
                                                        {fieldWorkStarted
                                                            ? "Plan details are locked because field work has started."
                                                            : "Submitted or reviewed plans are locked against manual edits."}
                                                    </p>
                                                ) : null}
                                            </article>
                                        );
                                    },
                                )}
                            </div>
                        ) : (
                            <EmptyState
                                title="No survey plans yet."
                                description="Create a plan for one of your assigned projects to start the survey workflow."
                            />
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Submission Reviews"
                        description="Verify GPS-backed field work and issue one final review decision per submission."
                    >
                        {surveySubmissions.length ? (
                            <div className="space-y-4">
                                {surveySubmissions.map(
                                    (submission) => {
                                        const canReviewSubmission =
                                            hasProjectPermission(
                                                projectPermissions,
                                                submission.project_id,
                                                "survey_submission.review",
                                            );

                                        const canViewSubmissionDocuments =
                                            hasProjectPermission(
                                                projectPermissions,
                                                submission.project_id,
                                                "document.manage",
                                            );

                                        return (
                                            <SubmissionReviewCard
                                                key={
                                                    submission.id
                                                }
                                                submission={
                                                    submission
                                                }
                                                documentRouteBase={
                                                    documentRouteBase
                                                }
                                                canReview={
                                                    canReviewSubmission
                                                }
                                                canViewDocuments={
                                                    canViewSubmissionDocuments
                                                }
                                                routePrefix={
                                                    routePrefix
                                                }
                                                surveyStatusCodes={
                                                    surveyStatusCodes
                                                }
                                                members={members}
                                            />
                                        );
                                    },
                                )}
                            </div>
                        ) : (
                            <EmptyState
                                title="No submissions yet."
                                description="Field submissions appear here after an assigned surveyor completes a visit."
                            />
                        )}
                    </SectionCard>
                </div>
            </div>

            <Modal
                show={editingPlan !== null}
                onClose={closeEditModal}
                maxWidth="2xl"
                closeable={!editForm.processing}
            >
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                        Edit Survey Plan
                    </h3>

                    <p className="mt-1 text-sm text-slate-500">
                        Update plan details and active
                        project-team assignments before
                        submission.
                    </p>
                </div>

                <form
                    onSubmit={submitEdit}
                    className="space-y-4 p-5"
                >
                    <InputField
                        form={editForm}
                        name="title"
                        label="Survey Title"
                    />

                    <TextAreaField
                        form={editForm}
                        name="description"
                        label="Survey Scope"
                        rows={4}
                    />

                    <TextAreaField
                        form={editForm}
                        name="site_address"
                        label="Site Address"
                        rows={3}
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <InputField
                            form={editForm}
                            name="site_latitude"
                            label="Latitude"
                            inputMode="decimal"
                        />

                        <InputField
                            form={editForm}
                            name="site_longitude"
                            label="Longitude"
                            inputMode="decimal"
                        />

                        <InputField
                            form={editForm}
                            name="planned_date"
                            label="Planned Date"
                            type="date"
                        />

                        <InputField
                            form={editForm}
                            name="planned_start_time"
                            label="Start Time"
                            type="time"
                        />
                    </div>

                    <InputField
                        form={editForm}
                        name="planned_end_time"
                        label="End Time"
                        type="time"
                    />

                    <MemberSelect
                        form={editForm}
                        members={editMembers}
                    />

                    <ModalActions
                        processing={
                            editForm.processing
                        }
                        onCancel={closeEditModal}
                        submitLabel="Update Survey Plan"
                        processingLabel="Updating..."
                    />
                </form>
            </Modal>

            <Modal
                show={statusPlan !== null}
                onClose={closeStatusModal}
                maxWidth="md"
                closeable={!statusForm.processing}
            >
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                        Change Survey Status
                    </h3>

                    <p className="mt-1 text-sm text-slate-500">
                        {statusPlan?.title} is currently{" "}
                        <span className="font-medium">
                            {statusPlan?.status_label
                                || statusLabel(
                                    statusPlan?.status,
                                    surveyStatusCodes,
                                )}
                        </span>
                        .
                    </p>
                </div>

                <form
                    onSubmit={submitStatus}
                    className="space-y-4 p-5"
                >
                    <SelectField
                        form={statusForm}
                        name="status"
                        label="New Status"
                        options={availablePlanStatuses(
                            statusPlan,
                            surveyPlanStatuses,
                            surveyStatusCodes.planned,
                        ).map((status) => ({
                            value: status,
                            label: statusLabel(
                                status,
                                surveyStatusCodes,
                            ),
                        }))}
                        onChange={(value) =>
                            statusForm.setData(
                                "status",
                                Number(value),
                            )
                        }
                    />

                    <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Submission and review statuses
                        are controlled automatically by
                        the field workflow.
                    </p>

                    <ModalActions
                        processing={
                            statusForm.processing
                        }
                        onCancel={closeStatusModal}
                        submitLabel="Update Status"
                        processingLabel="Updating..."
                    />
                </form>
            </Modal>

            <Modal
                show={uploadPlan !== null}
                onClose={closeUploadModal}
                maxWidth="lg"
                closeable={!uploadForm.processing}
            >
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                        Upload Survey Documents
                    </h3>

                    <p className="mt-1 text-sm text-slate-500">
                        Add controlled plan documents to{" "}
                        {uploadPlan?.title}.
                    </p>
                </div>

                <form
                    onSubmit={submitDocuments}
                    className="space-y-4 p-5"
                >
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                            Documents
                        </label>

                        <input
                            key={
                                uploadPlan?.id
                                || "document-input"
                            }
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt,.dwg,.dxf"
                            onChange={(event) =>
                                uploadForm.setData(
                                    "documents",
                                    Array.from(
                                        event.target.files
                                        || [],
                                    ),
                                )
                            }
                            className="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-medium file:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:file:bg-indigo-950 dark:file:text-indigo-200"
                        />

                        <p className="mt-2 text-xs text-slate-500">
                            Up to{" "}
                            {documentLimits
                                .max_files_per_upload
                                || 5}{" "}
                            files per upload,{" "}
                            {documentLimits
                                .max_file_size_mb
                                || 20}{" "}
                            MB each, and{" "}
                            {documentLimits
                                .max_files_per_plan
                                || 25}{" "}
                            files per plan. PDF, images,
                            Office, text, DWG and DXF are
                            allowed.
                        </p>

                        {uploadForm.data.documents
                            .length ? (
                            <ul className="mt-3 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                                {uploadForm.data.documents.map(
                                    (file) => (
                                        <li
                                            key={`${file.name}-${file.lastModified}`}
                                            className="flex justify-between gap-3"
                                        >
                                            <span className="truncate">
                                                {
                                                    file.name
                                                }
                                            </span>

                                            <span className="shrink-0 text-xs text-slate-500">
                                                {formatBytes(
                                                    file.size,
                                                )}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ul>
                        ) : null}

                        {firstError(
                            uploadForm.errors,
                            "documents",
                        ) ? (
                            <p className="mt-2 text-xs text-rose-600">
                                {firstError(
                                    uploadForm.errors,
                                    "documents",
                                )}
                            </p>
                        ) : null}
                    </div>

                    <ModalActions
                        processing={
                            uploadForm.processing
                        }
                        disabled={
                            !uploadForm.data.documents
                                .length
                        }
                        onCancel={closeUploadModal}
                        submitLabel="Upload Documents"
                        processingLabel="Uploading..."
                    />
                </form>
            </Modal>
        </ConstructionShell>
    );
}

function PlanDocuments({
    plan,
    documentRouteBase,
    canViewDocuments,
    canManageDocuments,
    deletingDocumentId,
    onDelete,
}) {
    const documents = plan.documents || [];

    if (
        !canViewDocuments
        && !canManageDocuments
    ) {
        return (
            <p className="mt-4 text-xs text-slate-400">
                Document access is unavailable for
                this role.
            </p>
        );
    }

    if (!documents.length) {
        return (
            <p className="mt-4 text-xs text-slate-400">
                No plan documents uploaded.
            </p>
        );
    }

    return (
        <div className="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">
            {documents.map((document) => (
                <div
                    key={document.id}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl px-2 py-2 hover:bg-slate-50 dark:hover:bg-slate-900"
                >
                    <div className="flex min-w-0 items-center gap-3">
                        <FileText
                            className="h-4 w-4 shrink-0 text-indigo-500"
                            aria-hidden="true"
                        />

                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                {document.original_name}
                            </p>

                            <p className="text-xs text-slate-500">
                                {formatBytes(
                                    document.file_size,
                                )}
                                {" • "}
                                {formatDateTime(
                                    document.created_at,
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {canViewDocuments ? (
                            <>
                                <a
                                    href={route(
                                        `${documentRouteBase}.view`,
                                        document.id,
                                    )}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    View
                                </a>

                                <a
                                    href={route(
                                        `${documentRouteBase}.download`,
                                        document.id,
                                    )}
                                    className="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-500"
                                >
                                    Download
                                </a>
                            </>
                        ) : null}

                        {canManageDocuments ? (
                            <button
                                type="button"
                                disabled={
                                    deletingDocumentId
                                    === document.id
                                }
                                onClick={() =>
                                    onDelete(
                                        plan,
                                        document,
                                    )
                                }
                                className="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 disabled:opacity-50 dark:hover:bg-rose-950/40"
                                aria-label={`Delete ${document.original_name}`}
                            >
                                <Trash2
                                    className="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </button>
                        ) : null}
                    </div>
                </div>
            ))}
        </div>
    );
}

function SubmissionReviewCard({
    submission,
    documentRouteBase,
    canReview,
    canViewDocuments,
    routePrefix,
    surveyStatusCodes,
    members = [],
}) {
    const form = useForm({
        status: surveyStatusCodes.approved,
        review_notes: "",
        assigned_to_member_id: "",
        due_date: "",
        khasra_number: submission.project?.khasra_number || "",
        site_area: submission.project?.site_area || "",
        scope_of_work: submission.project?.scope_of_work || "",
    });

    const visit = submission.survey_visit;
    const details = submission.submission_details || {};
    const checklists = details.checklists || [];
    const photos = details.photos || visit?.photos || [];
    const categorizedFiles = details.categorized_files || {};
    const workStatus = details.work_status || (Number(submission.status) === surveyStatusCodes.approved ? "completed" : "partial");
    const hasObstruction = Boolean(details.has_obstruction);
    const obstructionDetails = details.obstruction_details;
    const pendingWork = details.pending_work;
    const totalHours = details.total_hours || details.work_summary?.total_hours;
    const checklistDone = details.checklist_completed_count ?? checklists.filter((c) => c.is_completed).length;
    const checklistTotal = details.checklist_total_count ?? checklists.length;

    const planDocuments = visit?.survey_plan?.documents || [];

    const isReviewable =
        Number(submission.status) === surveyStatusCodes.submitted;

    const noteRequired = [
        surveyStatusCodes.revision_requested,
        surveyStatusCodes.rejected,
    ].includes(Number(form.data.status));

    const submitReview = (event) => {
        event.preventDefault();

        form.post(
            route(
                `${routePrefix}.survey.submissions.review`,
                submission.id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    const statusBadgeColors = {
        completed: "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300",
        partial: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300",
        not_done: "bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300",
    };

    return (
        <article className="rounded-2xl border border-slate-200/80 bg-slate-50 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {/* Header */}
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/70 pb-4 dark:border-slate-800">
                <div>
                    <div className="flex items-center gap-2">
                        <p className="text-base font-semibold text-slate-900 dark:text-white">
                            {submission.project?.name || "Survey Task Submission"}
                        </p>
                        <span className="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                            Day {visit?.day_number || 1}
                        </span>
                    </div>

                    <p className="mt-1 text-sm text-slate-500">
                        Submitted by{" "}
                        <span className="font-medium text-slate-700 dark:text-slate-300">
                            {submission.submitted_by?.name || "Field Surveyor"}
                        </span>
                        {" • "}
                        Visit #{submission.survey_visit_id}
                        {details.task_id ? ` • Task #${details.task_id}` : ""}
                    </p>

                    <p className="mt-0.5 text-xs text-slate-400">
                        {formatDateTime(submission.submitted_at)}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${
                            statusBadgeColors[workStatus] || statusBadgeColors.completed
                        }`}
                    >
                        Work: {workStatus.replace("_", " ")}
                    </span>

                    <StatusBadge value={submission.status_key} />

                    <StatusBadge
                        value={visit?.gps_verified ? "gps_verified" : "unverified"}
                    />
                </div>
            </div>

            {/* Meta statistics */}
            <div className="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                <MetaPill
                    label="Checked In By"
                    value={visit?.checked_in_by?.name || "Unknown"}
                />

                <MetaPill
                    label="Checklist Progress"
                    value={`${checklistDone} / ${checklistTotal} done`}
                />

                <MetaPill
                    label="Total Hours"
                    value={totalHours ? String(totalHours) : (visit?.duration_minutes ? `${Math.round(visit.duration_minutes / 60 * 10) / 10}h` : "—")}
                />

                <MetaPill
                    label="Uploaded Media"
                    value={`${photos.length} photos · ${(categorizedFiles.survey_data?.length || 0) + (categorizedFiles.sketch_markup?.length || 0) + (categorizedFiles.reference_documents?.length || 0)} files`}
                />
            </div>

            {/* Obstruction Alert Box */}
            {hasObstruction && (
                <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 dark:border-amber-900/60 dark:bg-amber-950/30">
                    <div className="flex items-start gap-2.5">
                        <span className="text-base leading-none">⚠️</span>
                        <div>
                            <p className="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                                Site Obstruction Reported
                            </p>
                            <p className="mt-1 text-xs text-amber-900 dark:text-amber-200">
                                {obstructionDetails || "Obstruction was flagged at the survey site."}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Pending Work Box */}
            {pendingWork && (
                <div className="mt-3 rounded-xl border border-blue-200 bg-blue-50/70 p-3.5 dark:border-blue-900/60 dark:bg-blue-950/30">
                    <div className="flex items-start gap-2.5">
                        <span className="text-base leading-none">📌</span>
                        <div>
                            <p className="text-xs font-bold uppercase tracking-wider text-blue-800 dark:text-blue-300">
                                Pending Work Remaining
                            </p>
                            <p className="mt-1 text-xs text-blue-900 dark:text-blue-200">
                                {pendingWork}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Work Checklist Section */}
            {checklists.length > 0 && (
                <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                    <div className="mb-2.5 flex items-center justify-between">
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Work Checklist
                        </p>
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {checklistDone} / {checklistTotal} done
                        </span>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        {checklists.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-start gap-2 text-xs text-slate-700 dark:text-slate-300"
                            >
                                <span
                                    className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded text-[10px] font-bold ${
                                        item.is_completed
                                            ? "bg-emerald-500 text-white"
                                            : "border border-slate-300 bg-slate-100 text-transparent dark:border-slate-700 dark:bg-slate-900"
                                    }`}
                                >
                                    ✓
                                </span>
                                <span
                                    className={
                                        item.is_completed
                                            ? "font-medium text-slate-900 dark:text-slate-100"
                                            : "text-slate-400 dark:text-slate-500"
                                    }
                                >
                                    {item.item_title}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Site Photos Gallery */}
            {photos.length > 0 && (
                <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                    <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Site Photos ({photos.length})
                    </p>

                    <div className="grid grid-cols-3 gap-2.5 sm:grid-cols-6">
                        {photos.map((photoUrl, idx) => (
                            <a
                                key={idx}
                                href={photoUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="group relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                                title="Click to view full photo"
                            >
                                <img
                                    src={photoUrl}
                                    alt={`Site photo #${idx + 1}`}
                                    className="h-full w-full object-cover transition duration-200 group-hover:scale-105"
                                />
                                <span className="absolute bottom-1 right-1 rounded bg-black/60 px-1 py-0.5 text-[9px] font-medium text-white">
                                    #{idx + 1}
                                </span>
                            </a>
                        ))}
                    </div>
                </div>
            )}

            {/* Categorized Files */}
            {((categorizedFiles.survey_data?.length || 0) > 0 ||
                (categorizedFiles.sketch_markup?.length || 0) > 0 ||
                (categorizedFiles.reference_documents?.length || 0) > 0) && (
                <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                    <p className="mb-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Submitted Files & Documents
                    </p>

                    <div className="grid gap-3 sm:grid-cols-3">
                        {/* Survey Data */}
                        <div className="rounded-xl border border-slate-100 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/50">
                            <p className="mb-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                📊 Survey Data Files
                            </p>
                            <div className="space-y-1.5">
                                {categorizedFiles.survey_data?.map((file, i) => (
                                    <a
                                        key={i}
                                        href={file.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center justify-between rounded-lg bg-white p-2 text-xs font-medium text-indigo-600 shadow-xs hover:underline dark:bg-slate-950 dark:text-indigo-400"
                                    >
                                        <span className="truncate max-w-[130px]">{file.name}</span>
                                        <span className="text-[10px] text-slate-400">{file.size}</span>
                                    </a>
                                ))}
                                {(!categorizedFiles.survey_data || categorizedFiles.survey_data.length === 0) && (
                                    <span className="text-xs text-slate-400">None uploaded</span>
                                )}
                            </div>
                        </div>

                        {/* Sketch & Markup */}
                        <div className="rounded-xl border border-slate-100 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/50">
                            <p className="mb-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                📐 Sketch & Markup
                            </p>
                            <div className="space-y-1.5">
                                {categorizedFiles.sketch_markup?.map((file, i) => (
                                    <a
                                        key={i}
                                        href={file.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center justify-between rounded-lg bg-white p-2 text-xs font-medium text-indigo-600 shadow-xs hover:underline dark:bg-slate-950 dark:text-indigo-400"
                                    >
                                        <span className="truncate max-w-[130px]">{file.name}</span>
                                        <span className="text-[10px] text-slate-400">{file.size}</span>
                                    </a>
                                ))}
                                {(!categorizedFiles.sketch_markup || categorizedFiles.sketch_markup.length === 0) && (
                                    <span className="text-xs text-slate-400">None uploaded</span>
                                )}
                            </div>
                        </div>

                        {/* Reference Documents */}
                        <div className="rounded-xl border border-slate-100 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/50">
                            <p className="mb-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                📑 Reference Documents
                            </p>
                            <div className="space-y-1.5">
                                {categorizedFiles.reference_documents?.map((file, i) => (
                                    <a
                                        key={i}
                                        href={file.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center justify-between rounded-lg bg-white p-2 text-xs font-medium text-indigo-600 shadow-xs hover:underline dark:bg-slate-950 dark:text-indigo-400"
                                    >
                                        <span className="truncate max-w-[130px]">{file.name}</span>
                                        <span className="text-[10px] text-slate-400">{file.size}</span>
                                    </a>
                                ))}
                                {(!categorizedFiles.reference_documents || categorizedFiles.reference_documents.length === 0) && (
                                    <span className="text-xs text-slate-400">None uploaded</span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Plan Documents (existing) */}
            {planDocuments.length ? (
                <div className="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 dark:border-indigo-900 dark:bg-indigo-950/20">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">
                        Survey Plan Attached Documents
                    </p>

                    <div className="flex flex-wrap gap-2">
                        {planDocuments.map((document) => (
                            <DocumentLinks
                                key={document.id}
                                document={document}
                                documentRouteBase={documentRouteBase}
                                canView={canViewDocuments}
                            />
                        ))}
                    </div>
                </div>
            ) : null}

            {/* Entries (existing) */}
            {visit?.entries?.length ? (
                <div className="mt-4 space-y-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                    {visit.entries.map((entry) => (
                        <div
                            key={entry.id}
                            className="flex flex-wrap items-start justify-between gap-3 text-sm text-slate-600 dark:text-slate-300"
                        >
                            <div>
                                <p className="font-medium text-slate-800 dark:text-slate-100">
                                    {entry.entry_type} • {entry.title}
                                </p>
                                {entry.description ? (
                                    <p className="mt-1 text-xs text-slate-500">
                                        {entry.description}
                                    </p>
                                ) : null}
                            </div>

                            {entry.supporting_document ? (
                                <DocumentLinks
                                    document={entry.supporting_document}
                                    documentRouteBase={documentRouteBase}
                                    canView={canViewDocuments}
                                />
                            ) : (
                                <span className="text-xs text-slate-400">
                                    No attachment
                                </span>
                            )}
                        </div>
                    ))}
                </div>
            ) : null}

            {/* Review Decision Form */}
            {isReviewable && canReview ? (
                <form
                    onSubmit={submitReview}
                    className="mt-5 space-y-4 border-t border-slate-200 pt-4 dark:border-slate-800"
                >
                    <div className="grid gap-3 md:grid-cols-[220px,1fr]">
                        <div>
                            <label className="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Review Action
                            </label>

                            <select
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData(
                                        "status",
                                        Number(event.target.value),
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-2.5 font-medium text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            >
                                <option value={surveyStatusCodes.approved}>
                                    ✓ Approve
                                </option>
                                <option value={surveyStatusCodes.revision_requested}>
                                    🔄 Request Changes / Revision
                                </option>
                                <option value={surveyStatusCodes.rejected}>
                                    ✕ Reject
                                </option>
                            </select>

                            {form.errors.status ? (
                                <p className="mt-1 text-xs text-rose-600">
                                    {form.errors.status}
                                </p>
                            ) : null}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {Number(form.data.status) === surveyStatusCodes.revision_requested
                                    ? "Action Description / Required Changes"
                                    : "Review Notes"}
                                {noteRequired ? (
                                    <span className="text-rose-500"> *</span>
                                ) : null}
                            </label>

                            <textarea
                                rows={3}
                                required={noteRequired}
                                value={form.data.review_notes}
                                onChange={(event) =>
                                    form.setData("review_notes", event.target.value)
                                }
                                placeholder={
                                    Number(form.data.status) === surveyStatusCodes.revision_requested
                                        ? "Give detailed action description explaining what modifications or re-survey work is needed..."
                                        : Number(form.data.status) === surveyStatusCodes.rejected
                                        ? "State the reason for rejecting this survey submission..."
                                        : "Optional notes for the approval..."
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />

                            {form.errors.review_notes ? (
                                <p className="mt-1 text-xs text-rose-600">
                                    {form.errors.review_notes}
                                </p>
                            ) : null}
                        </div>
                    </div>

                    {/* When Approved: Assign Next Teammate (Draftsperson) */}
                    {Number(form.data.status) === surveyStatusCodes.approved && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                            <div className="mb-2">
                                <p className="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                                    Next Workflow Step: Assign to Draft Person / Teammate
                                </p>
                                <p className="text-xs text-emerald-700/80 dark:text-emerald-400/80">
                                    Approving will transition project to <strong>Drafting in Progress</strong>. Assign the teammate responsible for the drafting job.
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        Assign Teammate (Draft Person)
                                    </label>
                                    <select
                                        value={form.data.assigned_to_member_id}
                                        onChange={(e) =>
                                            form.setData("assigned_to_member_id", e.target.value)
                                        }
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="">Select draft person / teammate...</option>
                                        {members.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name} {m.designation_text ? `(${m.designation_text})` : ""}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        Target Due Date (Optional)
                                    </label>
                                    <input
                                        type="date"
                                        value={form.data.due_date}
                                        onChange={(e) =>
                                            form.setData("due_date", e.target.value)
                                        }
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        Khasra Number (Optional)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="e.g. 142/3 or Plot No. 24"
                                        value={form.data.khasra_number}
                                        onChange={(e) =>
                                            form.setData("khasra_number", e.target.value)
                                        }
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        Site Area (Optional)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="e.g. 1500 sq ft / 2 Acres"
                                        value={form.data.site_area}
                                        onChange={(e) =>
                                            form.setData("site_area", e.target.value)
                                        }
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        Scope of Work (Optional)
                                    </label>
                                    <textarea
                                        rows={2}
                                        placeholder="Enter scope of work and drawing instructions for the draft person..."
                                        value={form.data.scope_of_work}
                                        onChange={(e) =>
                                            form.setData("scope_of_work", e.target.value)
                                        }
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* When Request Revision: Alert */}
                    {Number(form.data.status) === surveyStatusCodes.revision_requested && (
                        <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">
                            <strong>Note:</strong> Requesting changes will move the project stage back to <strong>Survey In Progress</strong> and set the task status to <strong>In Progress</strong> with your action description so the surveyor can make corrections and resubmit.
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className={`rounded-xl px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition disabled:opacity-60 ${
                                Number(form.data.status) === surveyStatusCodes.approved
                                    ? "bg-emerald-600 hover:bg-emerald-700"
                                    : Number(form.data.status) === surveyStatusCodes.revision_requested
                                    ? "bg-amber-600 hover:bg-amber-700"
                                    : "bg-rose-600 hover:bg-rose-700"
                            }`}
                        >
                            {form.processing
                                ? "Processing..."
                                : Number(form.data.status) === surveyStatusCodes.approved
                                ? "Approve & Advance to Drafting"
                                : Number(form.data.status) === surveyStatusCodes.revision_requested
                                ? "Send Revision Request"
                                : "Reject Submission"}
                        </button>
                    </div>
                </form>
            ) : (
                <div className="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        {isReviewable
                            ? "Awaiting Authorized Reviewer"
                            : "Review Completed"}
                    </p>

                    {!isReviewable ? (
                        <>
                            <p className="mt-2 text-sm text-slate-700 dark:text-slate-200">
                                {submission.review_notes || "No review notes were provided."}
                            </p>

                            <p className="mt-1 text-xs text-slate-500">
                                {submission.reviewed_by?.name
                                    ? `Reviewed by ${submission.reviewed_by.name}`
                                    : "Reviewed by an authorized reviewer"}

                                {submission.reviewed_at
                                    ? ` • ${formatDateTime(submission.reviewed_at)}`
                                    : ""}
                            </p>
                        </>
                    ) : null}
                </div>
            )}
        </article>
    );
}

function DocumentLinks({
    document,
    documentRouteBase,
    canView,
}) {
    if (!canView) {
        return (
            <span className="text-xs text-slate-400">
                Attachment access unavailable
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-2">
            <a
                href={route(
                    `${documentRouteBase}.view`,
                    document.id,
                )}
                target="_blank"
                rel="noreferrer"
                className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                View
            </a>

            <a
                href={route(
                    `${documentRouteBase}.download`,
                    document.id,
                )}
                className="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500"
            >
                Download
            </a>
        </span>
    );
}

function MemberSelect({
    form,
    members,
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                Survey Team
            </label>

            <select
                multiple
                value={form.data.member_ids}
                onChange={(event) =>
                    form.setData(
                        "member_ids",
                        Array.from(
                            event.target
                                .selectedOptions,
                            (option) =>
                                option.value,
                        ),
                    )
                }
                className="min-h-40 w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                {members.map((member) => (
                    <option
                        key={member.id}
                        value={String(member.id)}
                    >
                        {member.name}

                        {member.designation_text
                            ? ` (${member.designation_text})`
                            : ""}

                        {member.email
                            ? ` • ${member.email}`
                            : ""}
                    </option>
                ))}
            </select>

            <p className="mt-1 text-xs text-slate-500">
                Use Ctrl/Command to select multiple
                active members from the selected project.
            </p>

            {!members.length ? (
                <p className="mt-1 text-xs text-amber-600">
                    No active project-team members are
                    available.
                </p>
            ) : null}

            {firstError(
                form.errors,
                "member_ids",
            ) ? (
                <p className="mt-1 text-xs text-rose-600">
                    {firstError(
                        form.errors,
                        "member_ids",
                    )}
                </p>
            ) : null}
        </div>
    );
}

function InputField({
    form,
    name,
    label,
    type = "text",
    inputMode,
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                {label}
            </label>

            <input
                type={type}
                inputMode={inputMode}
                value={form.data[name]}
                onChange={(event) =>
                    form.setData(
                        name,
                        event.target.value,
                    )
                }
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />

            {form.errors[name] ? (
                <p className="mt-1 text-xs text-rose-600">
                    {form.errors[name]}
                </p>
            ) : null}
        </div>
    );
}

function TextAreaField({
    form,
    name,
    label,
    rows = 4,
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                {label}
            </label>

            <textarea
                rows={rows}
                value={form.data[name]}
                onChange={(event) =>
                    form.setData(
                        name,
                        event.target.value,
                    )
                }
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />

            {form.errors[name] ? (
                <p className="mt-1 text-xs text-rose-600">
                    {form.errors[name]}
                </p>
            ) : null}
        </div>
    );
}

function SelectField({
    form,
    name,
    label,
    options,
    placeholder,
    onChange,
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                {label}
            </label>

            <select
                value={form.data[name]}
                onChange={(event) =>
                    onChange
                        ? onChange(
                            event.target.value,
                        )
                        : form.setData(
                            name,
                            event.target.value,
                        )
                }
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                {placeholder ? (
                    <option value="">
                        {placeholder}
                    </option>
                ) : null}

                {options.map((option) => (
                    <option
                        key={option.value}
                        value={option.value}
                    >
                        {option.label}
                    </option>
                ))}
            </select>

            {form.errors[name] ? (
                <p className="mt-1 text-xs text-rose-600">
                    {form.errors[name]}
                </p>
            ) : null}
        </div>
    );
}

function ActionButton({
    icon: Icon,
    children,
    onClick,
    primary = false,
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={
                primary
                    ? "inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-indigo-500"
                    : "inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            }
        >
            <Icon
                className="h-3.5 w-3.5"
                aria-hidden="true"
            />

            {children}
        </button>
    );
}

function ModalActions({
    processing,
    disabled = false,
    onCancel,
    submitLabel,
    processingLabel,
}) {
    return (
        <div className="flex items-center justify-end gap-3 pt-2">
            <button
                type="button"
                onClick={onCancel}
                disabled={processing}
                className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                Cancel
            </button>

            <button
                type="submit"
                disabled={
                    processing || disabled
                }
                className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {processing
                    ? processingLabel
                    : submitLabel}
            </button>
        </div>
    );
}

function MetaPill({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                {label}
            </p>

            <p className="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                {value || "-"}
            </p>
        </div>
    );
}

function emptyPlanForm() {
    return {
        title: "",
        description: "",
        site_address: "",
        site_latitude: "",
        site_longitude: "",
        planned_date: "",
        planned_start_time: "",
        planned_end_time: "",
        member_ids: [],
    };
}

function membersForProject(
    members,
    projectId,
) {
    if (!projectId) {
        return [];
    }

    const targetId =
        Number(projectId);

    return members.filter(
        (member) =>
            (member.project_ids || []).some(
                (id) =>
                    Number(id) === targetId,
            ),
    );
}

function hasProjectPermission(
    projectPermissions,
    projectId,
    permission,
) {
    return (
        projectPermissions?.[
        String(projectId)
        ]
        || projectPermissions?.[projectId]
        || []
    ).includes(permission);
}

function availablePlanStatuses(
    plan,
    statuses,
    plannedStatus,
) {
    if (!plan) {
        return statuses;
    }

    return statuses.filter(
        (status) =>
            !(
                Number(status)
                === Number(plannedStatus)
                && Number(
                    plan.visits_count || 0,
                ) > 0
            ),
    );
}

function dateInputValue(value) {
    if (!value) {
        return "";
    }

    return String(value).slice(0, 10);
}

function timeInputValue(value) {
    if (!value) {
        return "";
    }

    return String(value).slice(0, 5);
}

function formatDate(value) {
    if (!value) {
        return "Not set";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString(
        "en-IN",
        {
            day: "2-digit",
            month: "short",
            year: "numeric",
        },
    );
}

function formatDateTime(value) {
    if (!value) {
        return "Date not available";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString(
        "en-IN",
        {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        },
    );
}

function formatTime(value) {
    if (!value) {
        return null;
    }

    const date = new Date(
        `1970-01-01T${value}`,
    );

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleTimeString(
        "en-IN",
        {
            hour: "2-digit",
            minute: "2-digit",
            hour12: true,
        },
    );
}

function formatBytes(value) {
    const bytes =
        Number(value || 0);

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 ** 2) {
        return `${(
            bytes / 1024
        ).toFixed(1)} KB`;
    }

    return `${(
        bytes / (1024 ** 2)
    ).toFixed(1)} MB`;
}

function statusLabel(
    value,
    statusCodes,
) {
    const key = Object.entries(
        statusCodes,
    ).find(
        ([, code]) =>
            Number(code) === Number(value),
    )?.[0];

    return String(key || "unknown")
        .replaceAll("_", " ")
        .replace(
            /\b\w/g,
            (letter) =>
                letter.toUpperCase(),
        );
}

function firstError(
    errors,
    prefix,
) {
    if (errors[prefix]) {
        return errors[prefix];
    }

    const matchingKey =
        Object.keys(errors).find(
            (key) =>
                key.startsWith(
                    `${prefix}.`,
                ),
        );

    return matchingKey
        ? errors[matchingKey]
        : null;
}