import React from "react";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import { FaClock, FaUser, FaCommentDots, FaCheckCircle, FaExclamationCircle } from "react-icons/fa";

export default function WorkflowAuditTrail({ logs = [] }) {
    if (!logs || logs.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500 dark:border-slate-800">
                No workflow activity logged yet.
            </div>
        );
    }

    const stageTitles = {
        survey: "Survey Team Stage",
        drafting: "Draft Team Stage",
        accounts: "Accounts Commercial Stage",
        supervisor: "Supervisor Review Stage",
        completed: "Final Project Completion",
    };

    return (
        <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <FaClock className="text-indigo-500" />
                Workflow Audit Trail & History
            </h3>
            <div className="relative border-l-2 border-slate-200 dark:border-slate-800 ml-3 space-y-6 pl-5">
                {logs.map((log, index) => {
                    const isSupervisorSendBack = log.action && log.action.startsWith("sent_back");
                    const isApproved = log.action === "supervisor_approved" || log.stage === "completed";

                    return (
                        <div key={log.id || index} className="relative group">
                            {/* Dot icon on line */}
                            <span
                                className={`absolute -left-[27px] top-1 flex h-6 w-6 items-center justify-center rounded-full text-[11px] text-white ring-4 ring-white dark:ring-slate-900 ${
                                    isApproved
                                        ? "bg-emerald-500"
                                        : isSupervisorSendBack
                                          ? "bg-rose-500"
                                          : "bg-indigo-600"
                                }`}
                            >
                                {isApproved ? (
                                    <FaCheckCircle size={12} />
                                ) : isSupervisorSendBack ? (
                                    <FaExclamationCircle size={12} />
                                ) : (
                                    index + 1
                                )}
                            </span>

                            <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition-all hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-2.5">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-bold text-slate-900 dark:text-white">
                                            {stageTitles[log.stage] || log.stage}
                                        </span>
                                        <StatusBadge value={log.status || log.stage} />
                                    </div>
                                    <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                        <FaClock size={10} className="text-slate-400" />
                                        {new Date(log.created_at).toLocaleString()}
                                    </span>
                                </div>

                                <div className="mt-3 flex flex-col gap-2 text-xs">
                                    <div className="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                        <FaUser size={11} className="text-indigo-500" />
                                        <span className="font-semibold">Action By:</span>
                                        <span className="font-medium text-slate-800 dark:text-slate-200">
                                            {log.action_by?.name || "System Admin"}
                                        </span>
                                        {log.action_by?.designation && (
                                            <span className="text-[10px] text-slate-400">
                                                ({log.action_by.designation})
                                            </span>
                                        )}
                                    </div>

                                    {log.remarks && (
                                        <div className={`mt-1.5 rounded-lg p-2.5 text-xs ${
                                            isSupervisorSendBack
                                                ? "bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:border-rose-900"
                                                : "bg-slate-50 text-slate-700 dark:bg-slate-800/60 dark:text-slate-300"
                                        }`}>
                                            <div className="flex items-center gap-1.5 font-bold mb-1">
                                                <FaCommentDots size={11} />
                                                {isSupervisorSendBack ? "Mandatory Supervisor Return Remark:" : "Stage Remarks:"}
                                            </div>
                                            <p className="whitespace-pre-wrap">{log.remarks}</p>
                                        </div>
                                    )}

                                    {log.metadata && (
                                        <div className="mt-1 text-[11px] text-slate-500">
                                            {log.metadata.drawing_no && (
                                                <span className="mr-3">
                                                    Drawing No: <strong>{log.metadata.drawing_no}</strong>
                                                </span>
                                            )}
                                            {log.metadata.payment_status && (
                                                <span>
                                                    Payment Status: <strong>{log.metadata.payment_status}</strong>
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
