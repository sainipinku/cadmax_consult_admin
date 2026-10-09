import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import {
    FaLocationDot,
    FaCompassDrafting,
    FaFileInvoiceDollar,
    FaUserCheck,
    FaCircleCheck,
    FaCheck,
} from "react-icons/fa6";

const steps = [
    { key: "survey", label: "Survey Team", icon: FaLocationDot, color: "sky", subtext: "Site Data & Photos" },
    { key: "drafting", label: "Draft Team", icon: FaCompassDrafting, color: "indigo", subtext: "Map & DWG/PDF" },
    { key: "accounts", label: "Accounts Team", icon: FaFileInvoiceDollar, color: "amber", subtext: "Commercial Verify" },
    { key: "supervisor", label: "Supervisor", icon: FaUserCheck, color: "violet", subtext: "Final Chain Review" },
    { key: "completed", label: "Completed", icon: FaCircleCheck, color: "emerald", subtext: "Approved & Locked" },
];

const internalStageMap = {
    // Survey stage variants
    survey: "survey",
    survey_submitted: "survey",
    survey_planned: "survey",
    survey_in_progress: "survey",
    survey_complete: "survey",
    budget_pending: "survey",
    budget_approved: "survey",
    draft: "survey",
    planning: "survey",
    team_assigned: "survey",

    // Drafting stage variants
    drafting: "drafting",
    drafting_in_progress: "drafting",
    draft_ready_for_review: "drafting",
    drawing_approval_pending: "drafting",
    revision_requested: "drafting",
    drawing_approved: "drafting",

    // Accounts stage variants
    accounts: "accounts",
    accounts_pending: "accounts",
    billing_pending: "accounts",

    // Supervisor stage variants
    supervisor: "supervisor",
    awaiting_supervisor_review: "supervisor",
    supervisor_review: "supervisor",

    // Completed stage variants
    completed: "completed",
    closed: "completed",
    approved: "completed",
};

export default function WorkflowTracker({ currentStage, isLocked = false }) {
    const mapped = internalStageMap[currentStage] || "survey";
    const activeIndex = Math.max(steps.findIndex((step) => step.key === mapped), 0);

    const colorRing = {
        sky: "border-sky-500 bg-sky-50 text-sky-700 dark:border-sky-400 dark:bg-sky-500/10 dark:text-sky-300",
        indigo: "border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/10 dark:text-indigo-300",
        amber: "border-amber-500 bg-amber-50 text-amber-700 dark:border-amber-400 dark:bg-amber-500/10 dark:text-amber-300",
        violet: "border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-400 dark:bg-violet-500/10 dark:text-violet-300",
        emerald: "border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-400 dark:bg-emerald-500/10 dark:text-emerald-300",
    };

    const iconBg = {
        sky: "bg-sky-600",
        indigo: "bg-indigo-600",
        amber: "bg-amber-600",
        violet: "bg-violet-600",
        emerald: "bg-emerald-600",
    };

    return (
        <div className="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/50 to-slate-100/30 p-5 shadow-sm dark:border-slate-800 dark:from-slate-900 dark:via-slate-900/90 dark:to-slate-950">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <p className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                            Complete Workflow Progress
                        </p>
                        {isLocked && (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                🔒 Locked & Completed
                            </span>
                        )}
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Survey Team → Draft Team → Accounts Team → Supervisor → Completed
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <StatusBadge value={currentStage} />
                </div>
            </div>

            <div className="mt-6 relative px-2">
                <div className="absolute left-6 right-6 top-6 h-[2px] bg-slate-200 dark:bg-slate-800" />
                <div
                    className="absolute left-6 top-6 h-[2px] bg-gradient-to-r from-sky-500 via-indigo-500 via-amber-500 via-violet-500 to-emerald-500 transition-all duration-700"
                    style={{
                        width: `calc(${(activeIndex / (steps.length - 1)) * 100}% - 48px * ${(activeIndex / (steps.length - 1))})`,
                    }}
                />

                <div className="relative grid grid-cols-5 gap-2">
                    {steps.map((step, index) => {
                        const Icon = step.icon;
                        const isDone = index < activeIndex;
                        const isActive = index === activeIndex;

                        return (
                            <div key={step.key} className="flex flex-col items-center text-center group">
                                <div
                                    className={`relative z-10 w-12 h-12 rounded-full flex items-center justify-center border-2 transition-all duration-300 ${
                                        isActive
                                            ? `${colorRing[step.color]} shadow-lg scale-105 ring-4 ring-offset-2 ring-indigo-500/20`
                                            : isDone
                                              ? "border-emerald-500 bg-emerald-500 text-white dark:border-emerald-400 shadow-sm"
                                              : "border-slate-200 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500"
                                    }`}
                                >
                                    {isDone ? (
                                        <FaCheck size={16} className="text-white" />
                                    ) : (
                                        <Icon size={isActive ? 19 : 16} />
                                    )}
                                </div>
                                <p
                                    className={`mt-2.5 text-[11px] font-bold uppercase tracking-wider ${
                                        isActive
                                            ? "text-slate-900 dark:text-white"
                                            : isDone
                                              ? "text-emerald-600 dark:text-emerald-400"
                                              : "text-slate-400 dark:text-slate-500"
                                    }`}
                                >
                                    {step.label} {isDone ? "✓" : isActive ? "○" : "○"}
                                </p>
                                <p className="mt-0.5 text-[10px] text-slate-500 dark:text-slate-500 truncate max-w-[110px]">
                                    {step.subtext}
                                </p>
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="mt-6 hidden md:flex items-center justify-between gap-2 border-t border-slate-200/60 dark:border-slate-800/60 pt-4">
                {steps.map((step, index) => {
                    const Icon = step.icon;
                    const isDone = index < activeIndex;
                    const isActive = index === activeIndex;

                    return (
                        <div
                            key={step.key}
                            className={`flex-1 rounded-xl border px-3 py-2.5 transition-all ${
                                isActive
                                    ? `${colorRing[step.color]} shadow-sm`
                                    : isDone
                                      ? "border-emerald-200 bg-emerald-50/50 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                                      : "border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900/60"
                            }`}
                        >
                            <div className="flex items-center gap-2.5">
                                <span
                                    className={`w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs shrink-0 ${
                                        isDone ? "bg-emerald-500" : iconBg[step.color]
                                    }`}
                                >
                                    {isDone ? <FaCheck size={12} /> : <Icon size={12} />}
                                </span>
                                <div className="min-w-0">
                                    <p
                                        className={`text-xs font-bold truncate ${
                                            isActive || isDone
                                                ? "text-slate-900 dark:text-white"
                                                : "text-slate-500 dark:text-slate-400"
                                        }`}
                                    >
                                        {step.label}
                                    </p>
                                    <p className="text-[10px] text-slate-500 dark:text-slate-500 truncate">
                                        {isActive ? "In Progress" : isDone ? "Completed ✓" : "Pending"}
                                    </p>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
