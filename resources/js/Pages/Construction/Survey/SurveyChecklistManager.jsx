import { router } from "@inertiajs/react";
import {
    CheckSquare2,
    LoaderCircle,
    Plus,
} from "lucide-react";
import { useState } from "react";

export default function SurveyChecklistManager({
    plan,
    routePrefix,
    canManage = false,
}) {
    const [drafts, setDrafts] = useState({});
    const [errors, setErrors] = useState({});
    const [
        submittingAssignmentId,
        setSubmittingAssignmentId,
    ] = useState(null);

    const assignments = Array.isArray(
        plan?.plan_members,
    )
        ? plan.plan_members
        : [];

    const updateDraft = (
        assignmentId,
        value,
    ) => {
        setDrafts((current) => ({
            ...current,
            [assignmentId]: value,
        }));

        setErrors((current) => ({
            ...current,
            [assignmentId]: null,
        }));
    };

    const submitWork = (
        event,
        assignment,
    ) => {
        event.preventDefault();

        const assignmentId = assignment.id;

        const workTitle = String(
            drafts[assignmentId] || "",
        ).trim();

        if (!workTitle) {
            setErrors((current) => ({
                ...current,
                [assignmentId]:
                    "Enter the work to add.",
            }));

            return;
        }

        router.post(
            route(
                `${routePrefix}.survey.plans.members.checklist-works.store`,
                [
                    plan.id,
                    assignmentId,
                ],
            ),
            {
                works: [workTitle],
            },
            {
                preserveScroll: true,
                preserveState: true,

                onStart: () => {
                    setSubmittingAssignmentId(
                        assignmentId,
                    );
                },

                onSuccess: () => {
                    setDrafts((current) => ({
                        ...current,
                        [assignmentId]: "",
                    }));

                    setErrors((current) => ({
                        ...current,
                        [assignmentId]: null,
                    }));
                },

                onError: (responseErrors) => {
                    setErrors((current) => ({
                        ...current,
                        [assignmentId]:
                            responseErrors[
                                "works.0"
                            ]
                            || responseErrors.works
                            || "Unable to add this work.",
                    }));
                },

                onFinish: () => {
                    setSubmittingAssignmentId(
                        null,
                    );
                },
            },
        );
    };

    return (
        <section className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div className="flex items-center gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                    <CheckSquare2 size={19} />
                </span>

                <div>
                    <h4 className="text-sm font-semibold text-slate-900 dark:text-white">
                        Work Checklist
                    </h4>

                    <p className="text-xs text-slate-500 dark:text-slate-400">
                        Work is checked when the assigned
                        member completes it.
                    </p>
                </div>
            </div>

            {assignments.length ? (
                <div className="divide-y divide-slate-200 dark:divide-slate-800">
                    {assignments.map(
                        (assignment) => {
                            const works =
                                Array.isArray(
                                    assignment.work_checklists,
                                )
                                    ? assignment.work_checklists
                                    : [];

                            const completedCount =
                                works.filter(
                                    (work) =>
                                        Boolean(
                                            work.is_completed,
                                        )
                                        || Number(
                                            work.status,
                                        ) === 1,
                                ).length;

                            const isSubmitting =
                                submittingAssignmentId
                                === assignment.id;

                            return (
                                <div
                                    key={assignment.id}
                                    className="p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {assignment
                                                    .member
                                                    ?.name
                                                    || "Assigned member"}
                                            </p>

                                            <p className="mt-0.5 text-xs capitalize text-slate-500 dark:text-slate-400">
                                                {assignment
                                                    .role_in_survey
                                                    || "surveyor"}
                                            </p>
                                        </div>

                                        <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                                            {completedCount}
                                            {" / "}
                                            {works.length}
                                            {" completed"}
                                        </span>
                                    </div>

                                    {works.length ? (
                                        <ul className="mt-4 divide-y divide-slate-100 border-y border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                                            {works.map(
                                                (work) => {
                                                    const isCompleted =
                                                        Boolean(
                                                            work.is_completed,
                                                        )
                                                        || Number(
                                                            work.status,
                                                        ) === 1;

                                                    return (
                                                        <li
                                                            key={
                                                                work.id
                                                            }
                                                            className="flex items-start gap-3 py-3"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    isCompleted
                                                                }
                                                                readOnly
                                                                disabled
                                                                aria-label={
                                                                    work.work_title
                                                                }
                                                                className="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 accent-blue-600 disabled:cursor-default disabled:opacity-100 dark:border-slate-600"
                                                            />

                                                            <span
                                                                className={
                                                                    isCompleted
                                                                        ? "text-sm text-slate-400 line-through"
                                                                        : "text-sm text-slate-700 dark:text-slate-200"
                                                                }
                                                            >
                                                                {
                                                                    work.work_title
                                                                }
                                                            </span>
                                                        </li>
                                                    );
                                                },
                                            )}
                                        </ul>
                                    ) : (
                                        <p className="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-5 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                            No work added for
                                            this member yet.
                                        </p>
                                    )}

                                    {canManage ? (
                                        <form
                                            onSubmit={(
                                                event,
                                            ) =>
                                                submitWork(
                                                    event,
                                                    assignment,
                                                )
                                            }
                                            className="mt-4"
                                        >
                                            <div className="flex flex-col gap-2 sm:flex-row">
                                                <input
                                                    type="text"
                                                    maxLength={500}
                                                    value={
                                                        drafts[
                                                            assignment
                                                                .id
                                                        ]
                                                        || ""
                                                    }
                                                    onChange={(
                                                        event,
                                                    ) =>
                                                        updateDraft(
                                                            assignment.id,
                                                            event
                                                                .target
                                                                .value,
                                                        )
                                                    }
                                                    placeholder="Add work for this member"
                                                    disabled={
                                                        isSubmitting
                                                    }
                                                    className="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                                />

                                                <button
                                                    type="submit"
                                                    disabled={
                                                        isSubmitting
                                                    }
                                                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                                >
                                                    {isSubmitting ? (
                                                        <LoaderCircle
                                                            size={
                                                                17
                                                            }
                                                            className="animate-spin"
                                                        />
                                                    ) : (
                                                        <Plus
                                                            size={
                                                                17
                                                            }
                                                        />
                                                    )}

                                                    Add Work
                                                </button>
                                            </div>

                                            {errors[
                                                assignment.id
                                            ] ? (
                                                <p className="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                                                    {
                                                        errors[
                                                            assignment
                                                                .id
                                                        ]
                                                    }
                                                </p>
                                            ) : null}
                                        </form>
                                    ) : null}
                                </div>
                            );
                        },
                    )}
                </div>
            ) : (
                <p className="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    Assign a member to this survey before
                    adding checklist work.
                </p>
            )}
        </section>
    );
}