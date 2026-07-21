import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

const steps = [
    { key: "budget_pending", label: "Budget Approval", phase: "Phase 1" },
    { key: "budget_approved", label: "Budget Approved", phase: "Phase 1" },
    { key: "team_assigned", label: "Team Assigned", phase: "Phase 1" },
    { key: "survey_planned", label: "Survey Planned", phase: "Phase 2" },
    { key: "survey_in_progress", label: "Survey In Progress", phase: "Phase 2" },
    { key: "drafting_in_progress", label: "Drafting", phase: "Phase 2" },
    { key: "drawing_approval_pending", label: "Drawing Approval", phase: "Phase 2" },
    { key: "ready_for_construction", label: "Ready for Construction", phase: "Phase 2" },
    { key: "execution_planned", label: "Execution Planned", phase: "Phase 3" },
    { key: "construction_in_progress", label: "Construction In Progress", phase: "Phase 3" },
];

export default function WorkflowTracker({ currentStage }) {
    const activeIndex = Math.max(steps.findIndex((step) => step.key === currentStage), 0);

    return (
        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-slate-900 dark:text-white">Project Workflow</p>
                    <p className="text-xs text-slate-500 dark:text-slate-400">Phase 1 foundation, Phase 2 planning, and Phase 3 execution milestones.</p>
                </div>
                <StatusBadge value={currentStage} />
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                {steps.map((step, index) => {
                    const isDone = index < activeIndex;
                    const isActive = index === activeIndex;

                    return (
                        <div
                            key={step.key}
                            className={`rounded-2xl border px-4 py-3 ${
                                isActive
                                    ? "border-indigo-200 bg-indigo-50 dark:border-indigo-500/40 dark:bg-indigo-500/10"
                                    : isDone
                                      ? "border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                                      : "border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-950"
                            }`}
                        >
                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                                {step.phase}
                            </p>
                            <p className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{step.label}</p>
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {isActive ? "Current stage" : isDone ? "Completed" : "Pending"}
                            </p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
