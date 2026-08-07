import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import EmptyState from "@/Pages/Construction/Components/EmptyState";

export default function TeamMemberShow({ project, teamMember, surveySubmissions, surveyVisits, surveyPlans, supervisedTasks, assignedTasks, progressReports, attendanceRecords, activityLog }) {
    const member = teamMember.member;
    const role = teamMember.role;

    return (
        <ConstructionShell
            title={`Project Work - ${member?.name || 'Team Member'}`}
            description={`${project.project_code} • Field Submissions & Project Work`}
            variant="super"
        >
            {/* Submitted By Reference */}
            <div className="mb-6 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                <p className="text-sm text-slate-600 dark:text-slate-400">
                    <span className="font-medium">Submitted By:</span> {member?.name || 'Unknown'}
                    {role && <span className="ml-2">• {role.name}</span>}
                    {member?.designation_text && <span className="ml-2">• {member.designation_text}</span>}
                </p>
            </div>

            <div className="grid gap-6">
                {/* Survey Submissions */}
                <SectionCard title="Survey Submissions" description="Survey data submitted by this team member">
                    {surveySubmissions?.length ? (
                        <div className="space-y-3">
                            {surveySubmissions.map((submission) => (
                                <div key={submission.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-white">Survey Visit #{submission.survey_visit_id}</p>
                                            <p className="text-sm text-slate-500">
                                                Submitted by {submission.submittedBy?.name || "Unknown"} • {submission.submitted_at ? new Date(submission.submitted_at).toLocaleString('en-GB') : 'N/A'}
                                            </p>
                                        </div>
                                        <StatusBadge value={submission.status} />
                                    </div>
                                    {submission.review_notes && (
                                        <div className="mt-3">
                                            <p className="text-sm font-medium text-slate-700 dark:text-slate-300">Review Notes:</p>
                                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">{submission.review_notes}</p>
                                        </div>
                                    )}
                                    {submission.reviewedBy && (
                                        <p className="mt-2 text-xs text-slate-500">
                                            Reviewed by: {submission.reviewedBy.name} {submission.reviewed_at ? `• ${new Date(submission.reviewed_at).toLocaleString('en-GB')}` : ''}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey submissions yet." description="This team member has not submitted any survey data for this project." />
                    )}
                </SectionCard>

                {/* Survey Visits */}
                <SectionCard title="Survey Visits" description="Site visits and field work">
                    {surveyVisits?.length ? (
                        <div className="space-y-3">
                            {surveyVisits.map((visit) => (
                                <div key={visit.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-white">Visit #{visit.id}</p>
                                            <p className="text-sm text-slate-500">
                                                Check-in by {visit.checkedInBy?.name || "Unknown"} • {visit.check_in_at ? new Date(visit.check_in_at).toLocaleString('en-GB') : 'N/A'}
                                            </p>
                                            {visit.check_in_latitude && visit.check_in_longitude && (
                                                <p className="text-xs text-slate-500 mt-1">
                                                    GPS: {visit.check_in_latitude}, {visit.check_in_longitude}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex gap-2">
                                            <StatusBadge value={visit.status} />
                                            <StatusBadge value={visit.gps_verified ? "GPS Verified" : "Pending GPS"} />
                                        </div>
                                    </div>
                                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                        <div>
                                            <p className="text-xs font-medium text-slate-700 dark:text-slate-300">Entries: {visit.entries?.length || 0}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs font-medium text-slate-700 dark:text-slate-300">Measurements: {visit.measurements?.length || 0}</p>
                                        </div>
                                    </div>
                                    {visit.submission && (
                                        <div className="mt-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-900">
                                            <p className="text-sm font-medium text-slate-900 dark:text-white">Submission Status</p>
                                            <div className="mt-2 flex items-center justify-between gap-3">
                                                <p className="text-sm text-slate-500">
                                                    {visit.submission.submittedBy?.name || "Unknown"}
                                                </p>
                                                <StatusBadge value={visit.submission.status} />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey visits yet." description="This team member has not performed any survey visits for this project." />
                    )}
                </SectionCard>

                {/* Survey Plans */}
                <SectionCard title="Survey Plans" description="Survey plans assigned to this team member">
                    {surveyPlans?.length ? (
                        <div className="space-y-3">
                            {surveyPlans.map((plan) => (
                                <div key={plan.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-white">{plan.title}</p>
                                            <p className="text-sm text-slate-500">{plan.survey_code} • Planned: {plan.planned_date ? new Date(plan.planned_date).toLocaleDateString('en-GB') : 'N/A'}</p>
                                            {plan.site_address && (
                                                <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{plan.site_address}</p>
                                            )}
                                        </div>
                                        <StatusBadge value={plan.status} />
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {plan.plan_members?.map((planMember) => (
                                            <span key={planMember.id} className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">
                                                {planMember.member?.name || "Unknown"} • {planMember.role_in_survey}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey plans assigned." description="This team member is not assigned to any survey plans for this project." />
                    )}
                </SectionCard>

                {/* Execution Tasks */}
                <div className="grid gap-6 xl:grid-cols-2">
                    <SectionCard title="Supervised Tasks" description="Tasks supervised by this team member">
                        {supervisedTasks?.length ? (
                            <div className="space-y-3">
                                {supervisedTasks.map((task) => (
                                    <div key={task.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-slate-900 dark:text-white">{task.title}</p>
                                                <p className="text-sm text-slate-500">{task.task_code || `Task #${task.id}`}</p>
                                                {task.planned_start_date && (
                                                    <p className="text-xs text-slate-500 mt-1">
                                                        Planned: {new Date(task.planned_start_date).toLocaleDateString('en-GB')} - {task.planned_end_date ? new Date(task.planned_end_date).toLocaleDateString('en-GB') : 'N/A'}
                                                    </p>
                                                )}
                                            </div>
                                            <StatusBadge value={task.status} />
                                        </div>
                                        {task.progress_percent !== null && (
                                            <div className="mt-3">
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-slate-600 dark:text-slate-400">Progress</span>
                                                    <span className="font-medium text-slate-900 dark:text-white">{task.progress_percent}%</span>
                                                </div>
                                                <div className="mt-1 h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                    <div className="h-2 rounded-full bg-indigo-600" style={{ width: `${task.progress_percent}%` }}></div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No supervised tasks." description="This team member is not supervising any tasks for this project." />
                        )}
                    </SectionCard>

                    <SectionCard title="Assigned Tasks" description="Tasks assigned to this team member">
                        {assignedTasks?.length ? (
                            <div className="space-y-3">
                                {assignedTasks.map((task) => (
                                    <div key={task.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-slate-900 dark:text-white">{task.title}</p>
                                                <p className="text-sm text-slate-500">{task.task_code || `Task #${task.id}`}</p>
                                                {task.supervisor && (
                                                    <p className="text-xs text-slate-500 mt-1">
                                                        Supervisor: {task.supervisor.name}
                                                    </p>
                                                )}
                                            </div>
                                            <StatusBadge value={task.status} />
                                        </div>
                                        {task.progress_percent !== null && (
                                            <div className="mt-3">
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-slate-600 dark:text-slate-400">Progress</span>
                                                    <span className="font-medium text-slate-900 dark:text-white">{task.progress_percent}%</span>
                                                </div>
                                                <div className="mt-1 h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                    <div className="h-2 rounded-full bg-indigo-600" style={{ width: `${task.progress_percent}%` }}></div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No assigned tasks." description="This team member is not assigned to any tasks for this project." />
                        )}
                    </SectionCard>
                </div>

                {/* Progress Reports */}
                <SectionCard title="Progress Reports" description="Daily progress reports submitted">
                    {progressReports?.length ? (
                        <div className="space-y-3">
                            {progressReports.map((report) => (
                                <div key={report.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-white">Report #{report.id}</p>
                                            <p className="text-sm text-slate-500">
                                                {report.report_date ? new Date(report.report_date).toLocaleDateString('en-GB') : 'N/A'} • 
                                                Submitted by {report.submittedBy?.name || "Unknown"}
                                            </p>
                                        </div>
                                        <StatusBadge value={report.status} />
                                    </div>
                                    {report.remarks && (
                                        <div className="mt-3">
                                            <p className="text-sm font-medium text-slate-700 dark:text-slate-300">Remarks:</p>
                                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">{report.remarks}</p>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No progress reports yet." description="This team member has not submitted any progress reports for this project." />
                    )}
                </SectionCard>

                {/* Attendance Records */}
                <SectionCard title="Attendance Records" description="Check-in and check-out records">
                    {attendanceRecords?.length ? (
                        <div className="space-y-3">
                            {attendanceRecords.map((record) => (
                                <div key={record.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-white">{record.attendance_date ? new Date(record.attendance_date).toLocaleDateString('en-GB') : 'N/A'}</p>
                                            <p className="text-sm text-slate-500">
                                                Check-in: {record.check_in_at ? new Date(record.check_in_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : 'N/A'} • Check-out: {record.check_out_at ? new Date(record.check_out_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : 'N/A'}
                                            </p>
                                            {record.check_in_latitude && record.check_in_longitude && (
                                                <p className="text-xs text-slate-500 mt-1">
                                                    GPS: {record.check_in_latitude}, {record.check_in_longitude}
                                                </p>
                                            )}
                                        </div>
                                        <StatusBadge value={record.status} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No attendance records yet." description="This team member has no attendance records for this project." />
                    )}
                </SectionCard>

                {/* Activity Log */}
                <SectionCard title="Activity Log" description="Recent activities and updates">
                    {activityLog?.length ? (
                        <div className="space-y-3">
                            {activityLog.map((item) => (
                                <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{item.module}</p>
                                            <p className="text-sm text-slate-500">
                                                {item.actor?.name || item.actor?.email || "System"} • {new Date(item.created_at).toLocaleString('en-GB')}
                                            </p>
                                            {item.meta && Object.keys(item.meta).length > 0 && (
                                                <div className="mt-2 text-xs text-slate-600 dark:text-slate-400">
                                                    {Object.entries(item.meta).map(([key, value]) => (
                                                        <span key={key} className="mr-3">
                                                            {key}: {JSON.stringify(value)}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                        <StatusBadge value={item.action} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No activity yet." description="Activities will appear here as actions are performed." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}