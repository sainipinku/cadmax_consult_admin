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
}) {
    const form = useForm({
        status: surveyStatusCodes.approved,
        review_notes: "",
    });

    const visit =
        submission.survey_visit;

    const planDocuments =
        visit?.survey_plan?.documents || [];

    const isReviewable =
        Number(submission.status)
        === surveyStatusCodes.submitted;

    const noteRequired = [
        surveyStatusCodes.revision_requested,
        surveyStatusCodes.rejected,
    ].includes(
        Number(form.data.status),
    );

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

    return (
        <article className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold text-slate-900 dark:text-white">
                        {submission.project?.name
                            || "Unknown project"}
                    </p>

                    <p className="text-sm text-slate-500">
                        Submitted by{" "}
                        {submission.submitted_by?.name
                            || "Unknown"}
                        {" • "}
                        Visit #
                        {submission.survey_visit_id}
                    </p>

                    <p className="mt-1 text-xs text-slate-400">
                        {formatDateTime(
                            submission.submitted_at,
                        )}
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <StatusBadge
                        value={
                            submission.status_key
                        }
                    />

                    <StatusBadge
                        value={
                            visit?.gps_verified
                                ? "gps_verified"
                                : "unverified"
                        }
                    />
                </div>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-3">
                <MetaPill
                    label="Checked In By"
                    value={
                        visit?.checked_in_by?.name
                        || "Unknown"
                    }
                />

                <MetaPill
                    label="Entries"
                    value={String(
                        visit?.entries?.length || 0,
                    )}
                />

                <MetaPill
                    label="Measurements"
                    value={String(
                        visit?.measurements?.length
                        || 0,
                    )}
                />
            </div>

            {planDocuments.length ? (
                <div className="mt-4 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 dark:border-indigo-900 dark:bg-indigo-950/20">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600 dark:text-indigo-300">
                        Plan Documents
                    </p>

                    <div className="flex flex-wrap gap-2">
                        {planDocuments.map(
                            (document) => (
                                <DocumentLinks
                                    key={
                                        document.id
                                    }
                                    document={
                                        document
                                    }
                                    documentRouteBase={
                                        documentRouteBase
                                    }
                                    canView={
                                        canViewDocuments
                                    }
                                />
                            ),
                        )}
                    </div>
                </div>
            ) : null}

            {visit?.entries?.length ? (
                <div className="mt-4 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                    {visit.entries.map(
                        (entry) => (
                            <div
                                key={entry.id}
                                className="flex flex-wrap items-start justify-between gap-3 text-sm text-slate-600 dark:text-slate-300"
                            >
                                <div>
                                    <p className="font-medium text-slate-800 dark:text-slate-100">
                                        {entry.entry_type}
                                        {" • "}
                                        {entry.title}
                                    </p>

                                    {entry.description ? (
                                        <p className="mt-1 text-xs text-slate-500">
                                            {
                                                entry.description
                                            }
                                        </p>
                                    ) : null}
                                </div>

                                {entry.supporting_document ? (
                                    <DocumentLinks
                                        document={
                                            entry.supporting_document
                                        }
                                        documentRouteBase={
                                            documentRouteBase
                                        }
                                        canView={
                                            canViewDocuments
                                        }
                                    />
                                ) : (
                                    <span className="text-xs text-slate-400">
                                        No attachment
                                    </span>
                                )}
                            </div>
                        ),
                    )}
                </div>
            ) : null}

            {isReviewable && canReview ? (
                <form
                    onSubmit={submitReview}
                    className="mt-4 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800"
                >
                    <div className="grid gap-3 md:grid-cols-[190px,1fr]">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                Decision
                            </label>

                            <select
                                value={
                                    form.data.status
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "status",
                                        Number(
                                            event.target
                                                .value,
                                        ),
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            >
                                <option
                                    value={
                                        surveyStatusCodes
                                            .approved
                                    }
                                >
                                    Approve
                                </option>

                                <option
                                    value={
                                        surveyStatusCodes
                                            .revision_requested
                                    }
                                >
                                    Request Revision
                                </option>

                                <option
                                    value={
                                        surveyStatusCodes
                                            .rejected
                                    }
                                >
                                    Reject
                                </option>
                            </select>

                            {form.errors.status ? (
                                <p className="mt-1 text-xs text-rose-600">
                                    {
                                        form.errors
                                            .status
                                    }
                                </p>
                            ) : null}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                Review Notes{" "}

                                {noteRequired ? (
                                    <span className="text-rose-500">
                                        *
                                    </span>
                                ) : null}
                            </label>

                            <textarea
                                rows={3}
                                required={
                                    noteRequired
                                }
                                value={
                                    form.data
                                        .review_notes
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "review_notes",
                                        event.target.value,
                                    )
                                }
                                placeholder={
                                    noteRequired
                                        ? "Explain the required changes or rejection reason"
                                        : "Optional approval notes"
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />

                            {form.errors
                                .review_notes ? (
                                <p className="mt-1 text-xs text-rose-600">
                                    {
                                        form.errors
                                            .review_notes
                                    }
                                </p>
                            ) : null}
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={
                                form.processing
                            }
                            className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            {form.processing
                                ? "Saving Review..."
                                : "Submit Review"}
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
                                {submission.review_notes
                                    || "No review notes were provided."}
                            </p>

                            <p className="mt-1 text-xs text-slate-500">
                                {submission.reviewed_by
                                    ?.name
                                    ? `Reviewed by ${submission.reviewed_by.name}`
                                    : "Reviewed by an authorized reviewer (identity is in the activity log)"}

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