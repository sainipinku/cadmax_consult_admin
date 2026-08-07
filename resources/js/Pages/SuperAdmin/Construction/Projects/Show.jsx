import { useState, useEffect } from "react";
import { useMemo } from "react";
import { useForm, router } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import WorkflowTracker from "@/Pages/Construction/Components/WorkflowTracker";
import Modal from "@/Components/Modal";

export default function ProjectShow({ project, members, roles, activityLog }) {
    const latestSubmission = project.survey_submissions?.[0] ?? null;
    const latestDraftingJob = project.drafting_jobs?.[0] ?? null;

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

    const handleRoleChange = (roleId) => {
        const roleObj = roles.find((r) => String(r.id) === String(roleId));
        let defaultScope = teamForm.data.assignment_scope;
        if (roleObj) {
            const slug = (roleObj.slug || roleObj.name || "").toLowerCase();
            if (slug.includes('survey')) defaultScope = "Field Survey & Site Data Collection";
            else if (slug.includes('draft')) defaultScope = "Drawing Revisions & CAD Drafting";
            else if (slug.includes('driver')) defaultScope = "Vehicle Transport & Site Movement";
            else if (slug.includes('admin')) defaultScope = "Project Oversight & Workflow Coordination";
            else if (slug.includes('approv')) defaultScope = "Drawing & Progress Approvals";
            else if (slug.includes('site')) defaultScope = "Site Work & Daily Execution";
        }
        teamForm.setData((prev) => ({
            ...prev,
            role_id: roleId,
            assignment_scope: defaultScope,
        }));
    };

    const selectedRole = roles.find((r) => String(r.id) === String(teamForm.data.role_id));
    const roleSlug = selectedRole ? (selectedRole.slug || selectedRole.name || "").toLowerCase() : "";

    const sortedMembers = useMemo(() => {
        if (!roleSlug) return members;
        let targetKeyword = "";
        if (roleSlug.includes("survey")) targetKeyword = "survey";
        else if (roleSlug.includes("draft")) targetKeyword = "draft";
        else if (roleSlug.includes("driver")) targetKeyword = "driver";
        else if (roleSlug.includes("admin")) targetKeyword = "admin";
        else if (roleSlug.includes("approv")) targetKeyword = "review";

        if (!targetKeyword) return members;

        const matching = [];
        const others = [];
        members.forEach((m) => {
            const desig = (m.designation_text || "").toLowerCase();
            if (desig.includes(targetKeyword)) {
                matching.push(m);
            } else {
                others.push(m);
            }
        });
        return [...matching, ...others];
    }, [members, roleSlug]);

    const metrics = useMemo(
        () => ({
            budgets: project.budgets?.length || 0,
            teamMembers: project.team_members?.length || 0,
            surveyPlans: project.survey_plans?.length || 0,
            submissions: project.survey_submissions?.length || 0,
            draftingJobs: project.drafting_jobs?.length || 0,
            approvals: project.drawing_approvals?.length || 0,
        }),
        [project]
    );

    return (
        <ConstructionShell title={project.name} description={`${project.project_code} • ${project.company?.name || ""}`} variant="super">
            <WorkflowTracker currentStage={project.current_stage} />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard label="Budget Versions" value={metrics.budgets} />
                <StatCard label="Team Members" value={metrics.teamMembers} />
                <StatCard label="Survey Plans" value={metrics.surveyPlans} />
                <StatCard label="Survey Submissions" value={metrics.submissions} />
                <StatCard label="Drafting Jobs" value={metrics.draftingJobs} />
                <StatCard label="Approvals" value={metrics.approvals} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                <SectionCard title="Project Summary" description="This project record is the anchor for all Phase 1 foundation and Phase 2 survey workflows.">
                    <div className="flex flex-wrap items-center gap-3">
                        <StatusBadge value={project.status} />
                        <StatusBadge value={project.current_stage} />
                        <StatusBadge value={project.priority} />
                    </div>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <Field label="Client" value={project.client?.name} />
                        <Field label="Company" value={project.company?.name} />
                        <Field label="Start Date" value={formatDate(project.start_date)} />
                        <Field label="Expected End" value={formatDate(project.expected_end_date)} />
                        <Field label="Address" value={project.project_address} span="sm:col-span-2" />
                        <Field label="Description" value={project.description} span="sm:col-span-2" />
                    </dl>
                </SectionCard>

                <SectionCard title="Workflow Snapshot" description="Quick read of where this project currently stands.">
                    <div className="space-y-4">
                        <SnapshotRow label="Latest Budget" value={project.budgets?.[0] ? `${project.budgets[0].currency} ${project.budgets[0].approved_amount || project.budgets[0].estimated_amount}` : null} badge={project.budgets?.[0]?.status} />
                        <SnapshotRow label="Latest Survey Submission" value={latestSubmission ? `Visit #${latestSubmission.survey_visit_id}` : null} badge={latestSubmission?.status} />
                        <SnapshotRow label="Latest Drafting Job" value={latestDraftingJob ? latestDraftingJob.assigned_to?.name || "Unassigned" : null} badge={latestDraftingJob?.status} />
                        <SnapshotRow label="Primary Team Size" value={`${project.team_members?.filter((item) => item.is_primary).length || 0} primary assignments`} />
                    </div>
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Budget Approval" description="Approve, reject, or revise the project budget before downstream work continues.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            budgetForm.post(route("super.construction.projects.budget.store", project.id), { preserveScroll: true });
                        }}
                        className="grid gap-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <InputField form={budgetForm} name="estimated_amount" label="Estimated Amount" />
                            <InputField form={budgetForm} name="approved_amount" label="Approved Amount" />
                            <InputField form={budgetForm} name="currency" label="Currency" />
                            <SelectField form={budgetForm} name="status" label="Budget Decision" options={[
                                { value: "pending", label: "Pending" },
                                { value: "approved", label: "Approved" },
                                { value: "rejected", label: "Rejected" },
                            ]} />
                        </div>
                        <TextAreaField form={budgetForm} name="notes" label="Budget Notes" rows={4} />
                        <button type="submit" disabled={budgetForm.processing} className="rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {budgetForm.processing ? "Saving..." : "Save Budget Decision"}
                        </button>
                    </form>
                </SectionCard>

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
                                    { value: "", label: "-- Select Member --" },
                                    ...sortedMembers.map((member) => ({
                                        value: member.id,
                                        label: `${member.name}${member.designation_text ? ` (${member.designation_text})` : ""}${member.email ? ` • ${member.email}` : ""}`,
                                    })),
                                ]}
                            />
                            {/* <InputField form={teamForm} name="assigned_from" label="Assigned From" type="date" />
                            <InputField form={teamForm} name="assigned_to" label="Assigned To" type="date" placeholder="dd-mm-yyyy" /> */}
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
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Assigned Team" description="Current team and role coverage for this project.">
                    {project.team_members?.length ? (
                        <div className="space-y-3">
                            {project.team_members.map((teamMember) => (
                                <TeamMemberRow 
                                    key={teamMember.id} 
                                    teamMember={teamMember} 
                                    project={project}
                                    roles={roles}
                                />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No team assigned yet." description="Assign the project admin and Phase 2 roles to unlock survey and drafting execution." />
                    )}
                </SectionCard>

                <SectionCard title="Activity Log" description="Every major action in the project workflow should show up here.">
                    {activityLog?.length ? (
                        <div className="space-y-3">
                            {activityLog.map((item) => (
                                <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{item.module}</p>
                                            <p className="text-sm text-slate-500">
                                                {item.actor?.name || item.actor?.email || "System"} • {formatDate(item.created_at)}
                                            </p>
                                        </div>
                                        <StatusBadge value={item.action} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No project activity yet." description="Once actions are performed, the workflow audit trail will appear here." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <SectionCard title="Survey Plans" description="Survey planning, assigned members, and field execution details." className="xl:col-span-2">
                    {project.survey_plans?.length ? (
                        <div className="space-y-4">
                            {project.survey_plans.map((plan) => (
                                <div key={plan.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{plan.title}</p>
                                            <p className="text-sm text-slate-500">{plan.survey_code} • {formatDate(plan.planned_date) || "No planned date"}</p>
                                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{plan.site_address || "No site address recorded."}</p>
                                        </div>
                                        <StatusBadge value={plan.status} />
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {(plan.plan_members || []).map((member) => (
                                            <span key={member.id} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                {member.member?.name || "Unknown"} • {member.role_in_survey}
                                            </span>
                                        ))}
                                    </div>
                                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                                        {(plan.visits || []).map((visit) => (
                                            <div key={visit.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                                <div className="flex flex-wrap items-center justify-between gap-3">
                                                    <p className="font-medium text-slate-900 dark:text-white">Visit #{visit.id}</p>
                                                    <div className="flex gap-2">
                                                        <StatusBadge value={visit.status} />
                                                        <StatusBadge value={visit.gps_verified ? "approved" : "revision_requested"} />
                                                    </div>
                                                </div>
                                                <p className="mt-2 text-sm text-slate-500">Check-in by {visit.checked_in_by?.name || "Unknown"} • {formatDateTime(visit.check_in_at) || "No timestamp"}</p>
                                                <p className="mt-1 text-sm text-slate-500">Entries: {visit.entries?.length || 0} • Measurements: {visit.measurements?.length || 0}</p>
                                                {visit.submission ? (
                                                    <div className="mt-3 rounded-2xl bg-slate-50 p-3 dark:bg-slate-900">
                                                        <p className="text-sm font-medium text-slate-900 dark:text-white">Submission Status</p>
                                                        <div className="mt-2 flex items-center justify-between gap-3">
                                                            <p className="text-sm text-slate-500">{visit.submission.submitted_by?.name || "Unknown"}</p>
                                                            <StatusBadge value={visit.submission.status} />
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey plans yet." description="Create a survey plan once budget approval and team assignment are complete." />
                    )}
                </SectionCard>

                <SectionCard title="Survey Submission Queue" description="Review outcomes before drafting starts.">
                    {project.survey_submissions?.length ? (
                        <div className="space-y-3">
                            {project.survey_submissions.map((submission) => (
                                <div key={submission.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium text-slate-900 dark:text-white">Visit #{submission.survey_visit_id}</p>
                                        <StatusBadge value={submission.status} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">Submitted by {submission.submitted_by?.name || "Unknown"}</p>
                                    <p className="mt-1 text-sm text-slate-500">Reviewed by {submission.reviewed_by?.name || "Pending review"}</p>
                                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{submission.review_notes || "No review notes yet."}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey submissions yet." description="Field teams need to check in, capture data, and submit their visit from mobile first." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.4fr,1fr]">
                <SectionCard title="Drafting Jobs" description="Approved survey data moves here for CAD work, revisions, and final drawing handoff.">
                    {project.drafting_jobs?.length ? (
                        <div className="space-y-4">
                            {project.drafting_jobs.map((job) => (
                                <div key={job.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">Drafting Job #{job.id}</p>
                                            <p className="text-sm text-slate-500">
                                                Assigned to {job.assigned_to?.name || "Unassigned"} • Due {formatDate(job.due_date) || "Not set"}
                                            </p>
                                        </div>
                                        <StatusBadge value={job.status} />
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        {(job.drawing_revisions || []).map((revision) => (
                                            <div key={revision.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                                <div className="flex flex-wrap items-center justify-between gap-3">
                                                    <div>
                                                        <p className="font-medium text-slate-900 dark:text-white">Revision {revision.revision_no}</p>
                                                        <p className="text-sm text-slate-500">Uploaded by {revision.uploaded_by?.name || "Unknown"} • {formatDateTime(revision.uploaded_at) || "No upload time"}</p>
                                                    </div>
                                                    <StatusBadge value={revision.status} />
                                                </div>
                                                <div className="mt-3 grid gap-2 text-sm text-slate-600 dark:text-slate-300">
                                                    <p>DWG: {revision.dwg_document?.original_name || "Not uploaded"}</p>
                                                    <p>PDF: {revision.pdf_document?.original_name || "Not uploaded"}</p>
                                                    <p>Notes: {revision.notes || "No notes added."}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drafting jobs yet." description="Once a survey submission is approved, create a drafting job from the drafting screen." />
                    )}
                </SectionCard>

                <SectionCard title="Drawing Approvals" description="Final Phase 2 gate before the project becomes ready for construction.">
                    {project.drawing_approvals?.length ? (
                        <div className="space-y-3">
                            {project.drawing_approvals.map((approval) => (
                                <div key={approval.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium text-slate-900 dark:text-white">Approval #{approval.id}</p>
                                        <StatusBadge value={approval.decision} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">Revision #{approval.drawing_revision_id}</p>
                                    <p className="mt-1 text-sm text-slate-500">{approval.remarks || "No remarks added."}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No drawing approvals yet." description="A submitted drawing revision will automatically generate an approval record here." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
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
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
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

function TeamMemberRow({ teamMember, project, roles }) {
    const [showEditModal, setShowEditModal] = useState(false);
    const [showRemoveModal, setShowRemoveModal] = useState(false);
    
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
            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
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
                        <button
                            onClick={openEditModal}
                            className="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                        >
                            Edit
                        </button>
                        <button
                            onClick={handleStatusToggle}
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium ${
                                teamMember.status === 'active'
                                    ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/30'
                                    : 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/20 dark:text-green-300 dark:hover:bg-green-900/30'
                            }`}
                        >
                            {teamMember.status === 'active' ? 'Deactivate' : 'Activate'}
                        </button>
                        <button
                            onClick={() => setShowRemoveModal(true)}
                            className="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/30"
                        >
                            Remove
                        </button>
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
