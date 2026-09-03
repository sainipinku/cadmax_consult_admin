import React, { useState } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import EmptyState from "@/Pages/Construction/Components/EmptyState";

export default function TeamMemberShow({
    project,
    teamMember,
    surveySubmissions = [],
    surveyVisits = [],
    surveyPlans = [],
    supervisedTasks = [],
    assignedTasks = [],
    progressReports = [],
    attendanceRecords = [],
    activityLog = [],
}) {
    const member = teamMember?.member || {};
    const role = teamMember?.role || {};

    const [activeTab, setActiveTab] = useState("all");

    // Compute site working hours
    let siteTotalMinutes = 0;
    (surveyVisits || []).forEach((visit) => {
        if (visit.check_in_at && visit.check_out_at) {
            const diff = Math.max(0, (new Date(visit.check_out_at).getTime() - new Date(visit.check_in_at).getTime()) / 60000);
            siteTotalMinutes += diff;
        } else if (visit.duration_minutes) {
            siteTotalMinutes += Number(visit.duration_minutes);
        } else if (visit.check_in_at && !visit.check_out_at) {
            const diff = Math.max(0, (new Date().getTime() - new Date(visit.check_in_at).getTime()) / 60000);
            siteTotalMinutes += diff;
        }
    });

    // Compute general attendance working hours
    let attendanceTotalMinutes = 0;
    (attendanceRecords || []).forEach((record) => {
        if (record.check_in_at && record.check_out_at) {
            const diff = Math.max(0, (new Date(record.check_out_at).getTime() - new Date(record.check_in_at).getTime()) / 60000);
            attendanceTotalMinutes += diff;
        } else if (record.duration_minutes) {
            attendanceTotalMinutes += Number(record.duration_minutes);
        } else if (record.check_in_at && !record.check_out_at) {
            const diff = Math.max(0, (new Date().getTime() - new Date(record.check_in_at).getTime()) / 60000);
            attendanceTotalMinutes += diff;
        }
    });

    const formatHours = (mins) => {
        const hrs = Math.floor(mins / 60);
        const m = Math.floor(mins % 60);
        return `${hrs}h ${m}m`;
    };

    const siteHoursStr = formatHours(siteTotalMinutes);
    const attendanceHoursStr = formatHours(attendanceTotalMinutes);
    const totalWorkingHoursStr = formatHours(siteTotalMinutes + attendanceTotalMinutes);

    const formatDate = (ts) => {
        if (!ts) return "-";
        const d = new Date(ts);
        return isNaN(d.getTime()) ? ts : d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
    };

    const formatDateTime = (ts) => {
        if (!ts) return "-";
        const d = new Date(ts);
        return isNaN(d.getTime()) ? ts : d.toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
    };

    const formatTimeOnly = (ts) => {
        if (!ts) return "-";
        const d = new Date(ts);
        return isNaN(d.getTime()) ? "-" : d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    };

    const getVisitDurationStr = (v) => {
        let mins = 0;
        if (v.check_in_at && v.check_out_at) {
            mins = Math.max(0, (new Date(v.check_out_at).getTime() - new Date(v.check_in_at).getTime()) / 60000);
        } else if (v.duration_minutes) {
            mins = Number(v.duration_minutes);
        } else if (v.check_in_at && !v.check_out_at) {
            mins = Math.max(0, (new Date().getTime() - new Date(v.check_in_at).getTime()) / 60000);
            return `${formatHours(mins)} (Active)`;
        }
        return formatHours(mins);
    };

    const getAttendanceDurationStr = (r) => {
        let mins = 0;
        if (r.check_in_at && r.check_out_at) {
            mins = Math.max(0, (new Date(r.check_out_at).getTime() - new Date(r.check_in_at).getTime()) / 60000);
        } else if (r.duration_minutes) {
            mins = Number(r.duration_minutes);
        } else if (r.check_in_at && !r.check_out_at) {
            mins = Math.max(0, (new Date().getTime() - new Date(r.check_in_at).getTime()) / 60000);
            return `${formatHours(mins)} (Active)`;
        }
        return formatHours(mins);
    };

    const totalTasksCount = (supervisedTasks?.length || 0) + (assignedTasks?.length || 0);

    return (
        <ConstructionShell
            title={`Project Work Details - ${member?.name || "Team Member"}`}
            description={`${project.project_code} • Member Performance, Hours & Task Audit`}
            variant="super"
        >
            {/* Top Member Profile Summary Header */}
            <div className="mb-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white">{member?.name || "Unknown Member"}</h2>
                            {teamMember.is_primary && (
                                <span className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                    ⭐ Primary Assignment
                                </span>
                            )}
                            <StatusBadge value={teamMember.status || "active"} />
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            {role?.name || "No role specified"} • {member?.designation_text || "Construction Team"}
                            {member?.email ? ` • ${member.email}` : ""}
                        </p>
                        <p className="mt-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                            <span className="font-semibold text-slate-500">Scope of Work:</span> {teamMember.assignment_scope || "General project support"}
                        </p>
                    </div>
                    <div>
                        <button
                            onClick={() => window.history.back()}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            ← Back to Project Details
                        </button>
                    </div>
                </div>
            </div>

            {/* Dynamic Working Hours & Work Summary Bar */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-xs font-semibold text-slate-500">⏱️ Site Working Hours</p>
                    <p className="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">{siteHoursStr}</p>
                    <p className="text-[10px] text-slate-400">From site survey duty visits</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-xs font-semibold text-slate-500">📥 Attendance Hours</p>
                    <p className="mt-1 text-lg font-bold text-indigo-600 dark:text-indigo-400">{attendanceHoursStr}</p>
                    <p className="text-[10px] text-slate-400">From general attendance log</p>
                </div>
                <div className="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-4 shadow-sm dark:border-indigo-900/40 dark:from-indigo-950/20 dark:to-slate-900">
                    <p className="text-xs font-bold text-indigo-800 dark:text-indigo-300">⏳ Total Working Hours</p>
                    <p className="mt-1 text-xl font-black text-indigo-700 dark:text-indigo-300">{totalWorkingHoursStr}</p>
                    <p className="text-[10px] text-indigo-500">Combined Site & Attendance</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-xs font-semibold text-slate-500">📍 Site Duty Check-Ins</p>
                    <p className="mt-1 text-lg font-bold text-slate-900 dark:text-white">{surveyVisits?.length || 0}</p>
                    <p className="text-[10px] text-slate-400">Field survey visits</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-xs font-semibold text-slate-500">📋 Project Tasks</p>
                    <p className="mt-1 text-lg font-bold text-slate-900 dark:text-white">{totalTasksCount}</p>
                    <p className="text-[10px] text-slate-400">{supervisedTasks?.length || 0} sup • {assignedTasks?.length || 0} asgn</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-xs font-semibold text-slate-500">📝 Progress Reports</p>
                    <p className="mt-1 text-lg font-bold text-slate-900 dark:text-white">{progressReports?.length || 0}</p>
                    <p className="text-[10px] text-slate-400">Updates filed by member</p>
                </div>
            </div>

            {/* Filter Navigation Tabs */}
            <div className="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-3 dark:border-slate-800">
                {[
                    { id: "all", label: "Overview & All Activity" },
                    { id: "site_visits", label: `📍 Site Check-Ins (${surveyVisits?.length || 0})` },
                    { id: "attendance", label: `📥 Attendance Log (${attendanceRecords?.length || 0})` },
                    { id: "tasks", label: `📋 Tasks (${totalTasksCount})` },
                    { id: "task_updates", label: `📝 Progress Updates (${progressReports?.length || 0})` },
                    { id: "submissions", label: `📄 Submissions (${surveySubmissions?.length || 0})` },
                ].map((tab) => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        className={`rounded-xl px-4 py-2 text-xs font-semibold transition ${
                            activeTab === tab.id
                                ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
                                : "bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            <div className="grid gap-6">
                {/* 1. SITE CHECK-IN & CHECK-OUT HISTORY */}
                {(activeTab === "all" || activeTab === "site_visits") && (
                    <SectionCard
                        title="📍 Site Survey Duty Check-In & Check-Out History"
                        description={`Field site check-ins logged via mobile/API (/api/member/survey-duty/check-in). Total site hours: ${siteHoursStr}`}
                    >
                        {surveyVisits?.length ? (
                            <div className="space-y-4">
                                {surveyVisits.map((visit) => {
                                    const isCheckedInActive = visit.check_in_at && !visit.check_out_at;
                                    const durationText = getVisitDurationStr(visit);
                                    return (
                                        <div
                                            key={visit.id}
                                            className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950 hover:shadow-sm transition"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-slate-900 dark:text-white">Survey Site Visit #{visit.id}</span>
                                                        {visit.day_number && (
                                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                                Day {visit.day_number}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        Survey Plan: <span className="font-medium text-slate-700 dark:text-slate-300">{visit.survey_plan?.title || `Plan #${visit.survey_plan_id || "-"}`}</span>
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <StatusBadge value={visit.status_key || visit.status} />
                                                    <span className={`rounded-full px-2.5 py-0.5 text-[10px] font-bold ${
                                                        visit.gps_verified
                                                            ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                            : "bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                                    }`}>
                                                        {visit.gps_verified ? "GPS Verified" : "GPS Pending"}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Check-In / Check-Out Grid */}
                                            <div className="mt-3 grid gap-3 sm:grid-cols-3 rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 text-xs dark:border-slate-800 dark:bg-slate-900">
                                                <div>
                                                    <p className="text-[11px] font-semibold text-slate-500">📥 Check-In Time:</p>
                                                    <p className="font-bold text-slate-900 dark:text-white">
                                                        {visit.check_in_at ? formatDateTime(visit.check_in_at) : "-"}
                                                    </p>
                                                    {visit.check_in_latitude && visit.check_in_longitude && (
                                                        <p className="mt-0.5 text-[10px] text-slate-500 font-mono">
                                                            Lat: {Number(visit.check_in_latitude).toFixed(5)}, Lng: {Number(visit.check_in_longitude).toFixed(5)}
                                                        </p>
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="text-[11px] font-semibold text-slate-500">📤 Check-Out Time:</p>
                                                    <p className={`font-bold ${isCheckedInActive ? "text-emerald-600 animate-pulse" : "text-slate-900 dark:text-white"}`}>
                                                        {visit.check_out_at ? formatDateTime(visit.check_out_at) : (visit.check_in_at ? "Active On Site" : "-")}
                                                    </p>
                                                    {visit.check_out_latitude && visit.check_out_longitude && (
                                                        <p className="mt-0.5 text-[10px] text-slate-500 font-mono">
                                                            Lat: {Number(visit.check_out_latitude).toFixed(5)}, Lng: {Number(visit.check_out_longitude).toFixed(5)}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="sm:text-right">
                                                    <p className="text-[11px] font-semibold text-slate-500">⏱️ Duration On Site:</p>
                                                    <p className="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                        {durationText}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Field Duty Payload details */}
                                            <div className="mt-2 grid gap-2 text-xs sm:grid-cols-3">
                                                <div className="text-slate-600 dark:text-slate-400">
                                                    Points Captured: <span className="font-semibold text-slate-800 dark:text-slate-200">{visit.total_points_captured || 0}</span>
                                                </div>
                                                <div className="text-slate-600 dark:text-slate-400">
                                                    Distance Covered: <span className="font-semibold text-slate-800 dark:text-slate-200">{visit.distance_covered_m ? `${visit.distance_covered_m} m` : "-"}</span>
                                                </div>
                                                <div className="text-slate-600 dark:text-slate-400 sm:text-right">
                                                    Elevation: <span className="font-semibold text-slate-800 dark:text-slate-200">{visit.elevation_m ? `${visit.elevation_m} m` : "-"}</span>
                                                </div>
                                            </div>

                                            {visit.remarks && (
                                                <div className="mt-2 text-xs text-amber-900 bg-amber-50/80 p-2.5 rounded-xl dark:bg-amber-950/30 dark:text-amber-200">
                                                    <span className="font-semibold">Remarks:</span> {visit.remarks}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <EmptyState
                                title="No site check-in visits recorded."
                                description="This team member has not checked into any survey site duty visits for this project."
                            />
                        )}
                    </SectionCard>
                )}

                {/* 2. GENERAL ATTENDANCE CHECK-IN & CHECK-OUT HISTORY */}
                {(activeTab === "all" || activeTab === "attendance") && (
                    <SectionCard
                        title="📥 General Attendance Check-In & Check-Out History"
                        description={`Daily attendance logs and shift timestamps. Total attendance hours: ${attendanceHoursStr}`}
                    >
                        {attendanceRecords?.length ? (
                            <div className="space-y-3">
                                {attendanceRecords.map((record) => {
                                    const durationText = getAttendanceDurationStr(record);
                                    const isCheckedIn = record.check_in_at && !record.check_out_at;

                                    return (
                                        <div
                                            key={record.id}
                                            className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <p className="font-bold text-slate-900 dark:text-white">
                                                        {record.attendance_date ? formatDate(record.attendance_date) : "Attendance Entry"}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        Checked in by: {record.checked_in_by?.name || member?.name || "Member"}
                                                        {record.checked_out_by?.name ? ` • Checked out by: ${record.checked_out_by.name}` : ""}
                                                    </p>
                                                </div>
                                                <StatusBadge value={record.status || "present"} />
                                            </div>

                                            <div className="mt-3 grid gap-3 sm:grid-cols-3 rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 text-xs dark:border-slate-800 dark:bg-slate-900">
                                                <div>
                                                    <p className="text-[11px] font-semibold text-slate-500">📥 Check-In Time:</p>
                                                    <p className="font-bold text-slate-900 dark:text-white">
                                                        {record.check_in_at ? formatTimeOnly(record.check_in_at) : "-"}
                                                    </p>
                                                    {record.check_in_latitude && (
                                                        <p className="text-[10px] text-slate-500 font-mono">
                                                            Lat: {Number(record.check_in_latitude).toFixed(5)}, Lng: {Number(record.check_in_longitude).toFixed(5)}
                                                        </p>
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="text-[11px] font-semibold text-slate-500">📤 Check-Out Time:</p>
                                                    <p className={`font-bold ${isCheckedIn ? "text-emerald-600 animate-pulse" : "text-slate-900 dark:text-white"}`}>
                                                        {record.check_out_at ? formatTimeOnly(record.check_out_at) : (record.check_in_at ? "Checked In (Active)" : "-")}
                                                    </p>
                                                    {record.check_out_latitude && (
                                                        <p className="text-[10px] text-slate-500 font-mono">
                                                            Lat: {Number(record.check_out_latitude).toFixed(5)}, Lng: {Number(record.check_out_longitude).toFixed(5)}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="sm:text-right">
                                                    <p className="text-[11px] font-semibold text-slate-500">⏱️ Shift Hours:</p>
                                                    <p className="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                                        {durationText}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <EmptyState
                                title="No attendance records found."
                                description="This team member has no general attendance check-in logs for this project."
                            />
                        )}
                    </SectionCard>
                )}

                {/* 3. TASK HISTORY & PROGRESS UPDATES */}
                {(activeTab === "all" || activeTab === "tasks") && (
                    <div className="grid gap-6 xl:grid-cols-2">
                        <SectionCard title="📋 Supervised Tasks History" description="Tasks supervised by this team member">
                            {supervisedTasks?.length ? (
                                <div className="space-y-4">
                                    {supervisedTasks.map((task) => {
                                        const checklistsCount = task.checklists?.length || 0;
                                        const completedChecklists = (task.checklists || []).filter((c) => c.is_completed).length;

                                        return (
                                            <div key={task.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <p className="font-bold text-slate-900 dark:text-white">{task.title}</p>
                                                        <p className="text-xs text-slate-500">{task.task_code || `Task #${task.id}`} • Supervisor</p>
                                                        <p className="mt-1 text-xs text-slate-500">
                                                            Dates: {formatDate(task.planned_start_date)} → {formatDate(task.planned_end_date)}
                                                        </p>
                                                    </div>
                                                    <div className="flex gap-2">
                                                        <StatusBadge value={task.status} />
                                                        <StatusBadge value={task.priority} />
                                                    </div>
                                                </div>

                                                {task.progress_percent !== null && (
                                                    <div className="mt-3">
                                                        <div className="flex items-center justify-between text-xs">
                                                            <span className="text-slate-600 dark:text-slate-400">Progress</span>
                                                            <span className="font-bold text-slate-900 dark:text-white">{task.progress_percent}%</span>
                                                        </div>
                                                        <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                            <div className="h-full bg-indigo-600 transition-all" style={{ width: `${task.progress_percent}%` }} />
                                                        </div>
                                                    </div>
                                                )}

                                                <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                                                    <span>Checklists: {completedChecklists}/{checklistsCount} completed</span>
                                                    <span>Assignees: {task.assignees?.length || 0} members</span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <EmptyState title="No supervised tasks." description="This team member is not supervising any execution tasks for this project." />
                            )}
                        </SectionCard>

                        <SectionCard title="📋 Assigned Tasks History" description="Tasks assigned to this team member">
                            {assignedTasks?.length ? (
                                <div className="space-y-4">
                                    {assignedTasks.map((task) => {
                                        const checklistsCount = task.checklists?.length || 0;
                                        const completedChecklists = (task.checklists || []).filter((c) => c.is_completed).length;

                                        return (
                                            <div key={task.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <p className="font-bold text-slate-900 dark:text-white">{task.title}</p>
                                                        <p className="text-xs text-slate-500">{task.task_code || `Task #${task.id}`}</p>
                                                        {task.supervisor && (
                                                            <p className="mt-1 text-xs text-slate-500">Supervisor: <span className="font-medium text-slate-700 dark:text-slate-300">{task.supervisor.name}</span></p>
                                                        )}
                                                        <p className="mt-0.5 text-xs text-slate-500">
                                                            Dates: {formatDate(task.planned_start_date)} → {formatDate(task.planned_end_date)}
                                                        </p>
                                                    </div>
                                                    <div className="flex gap-2">
                                                        <StatusBadge value={task.status} />
                                                        <StatusBadge value={task.priority} />
                                                    </div>
                                                </div>

                                                {task.progress_percent !== null && (
                                                    <div className="mt-3">
                                                        <div className="flex items-center justify-between text-xs">
                                                            <span className="text-slate-600 dark:text-slate-400">Progress</span>
                                                            <span className="font-bold text-slate-900 dark:text-white">{task.progress_percent}%</span>
                                                        </div>
                                                        <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                            <div className="h-full bg-indigo-600 transition-all" style={{ width: `${task.progress_percent}%` }} />
                                                        </div>
                                                    </div>
                                                )}

                                                <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                                                    <span>Checklists: {completedChecklists}/{checklistsCount} completed</span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <EmptyState title="No assigned tasks." description="This team member is not directly assigned to any execution tasks." />
                            )}
                        </SectionCard>
                    </div>
                )}

                {/* 4. TASK UPDATE HISTORY / DAILY PROGRESS REPORTS */}
                {(activeTab === "all" || activeTab === "task_updates") && (
                    <SectionCard
                        title="📝 Task Update History & Daily Progress Reports"
                        description="Reports and daily execution progress updates filed by this member"
                    >
                        {progressReports?.length ? (
                            <div className="space-y-3">
                                {progressReports.map((report) => (
                                    <div key={report.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p className="font-bold text-slate-900 dark:text-white">
                                                    Report #{report.id} {report.task ? `• Task: ${report.task.title}` : ""}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    Date: {formatDate(report.report_date)} • Submitted by {report.submittedBy?.name || member?.name || "Member"}
                                                </p>
                                            </div>
                                            <StatusBadge value={report.status} />
                                        </div>
                                        {report.completed_qty !== undefined && report.completed_qty !== null && (
                                            <div className="mt-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                                Completed Quantity Update: {report.completed_qty} {report.unit || ""}
                                            </div>
                                        )}
                                        {report.remarks && (
                                            <div className="mt-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 p-3 rounded-xl dark:bg-slate-900">
                                                <span className="font-semibold text-slate-700 dark:text-slate-200">Member Update Remarks:</span> {report.remarks}
                                            </div>
                                        )}
                                        {report.supervisor_notes && (
                                            <div className="mt-2 text-xs text-indigo-900 bg-indigo-50/70 p-3 rounded-xl dark:bg-indigo-950/30 dark:text-indigo-200">
                                                <span className="font-semibold">Supervisor Feedback:</span> {report.supervisor_notes}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No task progress updates." description="No progress reports have been filed by this team member for this project." />
                        )}
                    </SectionCard>
                )}

                {/* 5. SURVEY SUBMISSIONS */}
                {(activeTab === "all" || activeTab === "submissions") && (
                    <SectionCard
                        title="📄 Field Survey Submissions"
                        description="Survey payload data submitted by this member"
                    >
                        {surveySubmissions?.length ? (
                            <div className="space-y-3">
                                {surveySubmissions.map((submission) => (
                                    <div key={submission.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p className="font-bold text-slate-900 dark:text-white">Survey Submission for Visit #{submission.survey_visit_id}</p>
                                                <p className="text-xs text-slate-500">
                                                    Submitted: {formatDateTime(submission.submitted_at)} by {submission.submitted_by?.name || "Member"}
                                                </p>
                                            </div>
                                            <StatusBadge value={submission.status_key} />
                                        </div>
                                        {submission.review_notes && (
                                            <div className="mt-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 p-3 rounded-xl dark:bg-slate-900">
                                                <span className="font-semibold">Review Notes:</span> {submission.review_notes}
                                            </div>
                                        )}
                                        {(submission.reviewed_by || submission.reviewed_at) && (
                                            <p className="mt-2 text-[11px] text-slate-500">
                                                Reviewed by: {submission.reviewed_by?.name || "Authorized reviewer"} {submission.reviewed_at ? `on ${formatDateTime(submission.reviewed_at)}` : ""}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No survey submissions." description="This team member has not submitted any survey payloads for this project." />
                        )}
                    </SectionCard>
                )}

                {/* 6. MEMBER ACTIVITY AUDIT LOG */}
                <SectionCard title="🔍 Member Activity Audit Log" description="Audit trail of actions in this project">
                    {activityLog?.length ? (
                        <div className="space-y-3">
                            {activityLog.map((item) => (
                                <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{item.module}</p>
                                            <p className="text-xs text-slate-500">
                                                {item.actor?.name || item.actor?.email || "System"} • {formatDateTime(item.created_at)}
                                            </p>
                                        </div>
                                        <StatusBadge value={item.action} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No audit activity logged." description="Actions and modifications performed by this member will be logged here." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}