import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import Modal from "@/Components/Modal";

const surveyorRoles = ["surveyor", "survey", "field_surveyor"];

function roleMatches(value, roles) {
    const values = Array.isArray(value) ? value : [value];
    return values.some((item) => {
        const role = String(item || "").toLowerCase().replaceAll("-", "_").trim();
        return roles.includes(role) || roles.some((candidate) => role.includes(candidate));
    });
}

function hasSurveyorAssignment(task) {
    const assignees = [
        ...(Array.isArray(task.assignees) ? task.assignees : []),
        ...(Array.isArray(task.assignments) ? task.assignments : []),
        ...(Array.isArray(task.task_assignments) ? task.task_assignments : []),
    ];

    return assignees.some((assignment) => {
        if (assignment.status && assignment.status !== "active") return false;
        const member = assignment.member || assignment.assignee || {};
        return roleMatches(assignment.assignment_role, surveyorRoles)
            || roleMatches(member.assigned_roles, surveyorRoles)
            || roleMatches(member.role_names, surveyorRoles)
            || roleMatches(member.assigned_role_names, surveyorRoles)
            || roleMatches(member.designation, surveyorRoles);
    });
}

export default function AssignToDraftsmanButton({
    task,
    projectId,
    taskSource,
    routeName,
    draftsmen = [],
    authorized = false,
}) {
    const [showModal, setShowModal] = useState(false);
    const form = useForm({
        task_source: taskSource,
        task_id: task.id,
        member_id: "",
        due_date: "",
    });

    if (!authorized || String(task.status || "").toLowerCase() !== "completed" || !hasSurveyorAssignment(task)) {
        return null;
    }

    const submit = (event) => {
        event.preventDefault();
        if (!form.data.member_id) {
            toast.error("Select a Draftsman to assign this task.");
            return;
        }

        form.post(route(routeName, projectId), {
            preserveScroll: true,
            onSuccess: () => {
                setShowModal(false);
                form.reset();
                toast.success("Task assigned to Draftsman.");
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError || "Failed to assign task.");
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setShowModal(true)}
                className="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:from-violet-500 hover:to-indigo-500"
                title="Assign this completed Surveyor task to a Draftsman"
            >
                Assign to Draftsman
            </button>

            {showModal && (
                <Modal show={true} onClose={() => setShowModal(false)} maxWidth="md">
                    <form onSubmit={submit} className="p-6">
                        <div className="mb-5">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">Assign to Draftsman</h3>
                            <p className="mt-1 text-xs text-slate-500">
                                {task.task_code || `Task #${task.id}`} • {task.title}
                            </p>
                        </div>

                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Draftsman <span className="text-rose-500">*</span>
                            <select
                                value={form.data.member_id}
                                onChange={(event) => form.setData("member_id", event.target.value)}
                                required
                                className="mt-1.5 w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="">-- Select a Draftsman --</option>
                                {draftsmen.map((member) => (
                                    <option key={member.member_id || member.id} value={member.member_id || member.id}>
                                        {member.name}{member.email ? ` • ${member.email}` : ""}
                                    </option>
                                ))}
                            </select>
                        </label>

                        {draftsmen.length === 0 && (
                            <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                No active Draftsman is assigned to this project.
                            </p>
                        )}

                        <label className="mt-4 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Due Date
                            <input
                                type="date"
                                value={form.data.due_date}
                                onChange={(event) => form.setData("due_date", event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </label>

                        <div className="mt-6 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing || draftsmen.length === 0}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {form.processing ? "Assigning..." : "Assign Task"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}
