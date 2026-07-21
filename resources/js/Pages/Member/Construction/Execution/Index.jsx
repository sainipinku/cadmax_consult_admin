import { useForm } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function ExecutionIndex({ projects, tasks, latestAttendance, latestReports, openAttendance }) {
    const documentRouteBase = "member.construction.documents";
    const attendanceProjectId = openAttendance?.project_id ? String(openAttendance.project_id) : String(projects[0]?.id || "");
    const attendanceTaskId = openAttendance?.execution_task_id ? String(openAttendance.execution_task_id) : String(tasks[0]?.id || "");

    const checkInForm = useForm({
        project_id: attendanceProjectId,
        execution_task_id: attendanceTaskId,
        attendance_type: "present",
        notes: "",
        check_in_latitude: "",
        check_in_longitude: "",
        gps_accuracy_meters: "",
    });

    const checkOutForm = useForm({
        check_out_latitude: "",
        check_out_longitude: "",
        gps_accuracy_meters: "",
        notes: "",
    });

    const progressForm = useForm({
        execution_task_id: String(tasks[0]?.id || ""),
        progress_percent: tasks[0]?.progress_percent || 0,
        completed_quantity: tasks[0]?.completed_quantity || "",
        status: tasks[0]?.status || "planned",
    });

    const reportForm = useForm({
        project_id: String(projects[0]?.id || ""),
        execution_task_id: String(tasks[0]?.id || ""),
        report_date: new Date().toISOString().slice(0, 10),
        summary: "",
        work_completed: "",
        blockers: "",
        workforce_count: 1,
        latitude: "",
        longitude: "",
        gps_accuracy_meters: "",
        weather_summary: "",
        supporting_document: null,
    });

    const selectedTask = tasks.find((task) => String(task.id) === String(progressForm.data.execution_task_id));

    return (
        <ConstructionShell
            title="Site Execution"
            description="Update attendance, task progress, and daily progress directly from the member construction panel."
            variant="member"
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Assigned Tasks" value={tasks.length} />
                <StatCard label="Attendance Records" value={latestAttendance.length} />
                <StatCard label="DPR Records" value={latestReports.length} />
                <StatCard label="Open Attendance" value={openAttendance ? 1 : 0} />
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Attendance" description="Check in and check out with GPS-linked execution context.">
                    {!openAttendance ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                checkInForm.post(route("member.construction.attendance.checkin"), { preserveScroll: true });
                            }}
                        >
                            <SelectField
                                label="Project"
                                value={checkInForm.data.project_id}
                                onChange={(value) => checkInForm.setData("project_id", value)}
                                options={projects.map((project) => ({
                                    value: String(project.id),
                                    label: `${project.project_code} • ${project.name}`,
                                }))}
                            />
                            <SelectField
                                label="Task"
                                value={checkInForm.data.execution_task_id}
                                onChange={(value) => checkInForm.setData("execution_task_id", value)}
                                options={[
                                    { value: "", label: "General site work" },
                                    ...tasks.map((task) => ({
                                        value: String(task.id),
                                        label: `${task.task_code} • ${task.title}`,
                                    })),
                                ]}
                            />
                            <SelectField
                                label="Attendance Type"
                                value={checkInForm.data.attendance_type}
                                onChange={(value) => checkInForm.setData("attendance_type", value)}
                                options={[
                                    { value: "present", label: "Present" },
                                    { value: "half_day", label: "Half Day" },
                                    { value: "overtime", label: "Overtime" },
                                    { value: "site_visit", label: "Site Visit" },
                                ]}
                            />
                            <TextInput label="Latitude" value={checkInForm.data.check_in_latitude} onChange={(value) => checkInForm.setData("check_in_latitude", value)} />
                            <TextInput label="Longitude" value={checkInForm.data.check_in_longitude} onChange={(value) => checkInForm.setData("check_in_longitude", value)} />
                            <TextInput label="GPS Accuracy (m)" value={checkInForm.data.gps_accuracy_meters} onChange={(value) => checkInForm.setData("gps_accuracy_meters", value)} />
                            <TextAreaInput label="Notes" value={checkInForm.data.notes} onChange={(value) => checkInForm.setData("notes", value)} rows={3} />
                            <PrimaryButton disabled={checkInForm.processing}>
                                {checkInForm.processing ? "Saving..." : "Check In"}
                            </PrimaryButton>
                        </form>
                    ) : (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                checkOutForm.post(route("member.construction.attendance.checkout", openAttendance.id), { preserveScroll: true });
                            }}
                        >
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                <p className="font-semibold text-slate-900 dark:text-white">{openAttendance.project?.name || "-"}</p>
                                <p className="mt-1 text-sm text-slate-500">{openAttendance.execution_task?.title || "General site work"}</p>
                                <div className="mt-3">
                                    <StatusBadge value={openAttendance.status} />
                                </div>
                            </div>
                            <TextInput label="Check-out Latitude" value={checkOutForm.data.check_out_latitude} onChange={(value) => checkOutForm.setData("check_out_latitude", value)} />
                            <TextInput label="Check-out Longitude" value={checkOutForm.data.check_out_longitude} onChange={(value) => checkOutForm.setData("check_out_longitude", value)} />
                            <TextInput label="GPS Accuracy (m)" value={checkOutForm.data.gps_accuracy_meters} onChange={(value) => checkOutForm.setData("gps_accuracy_meters", value)} />
                            <TextAreaInput label="Notes" value={checkOutForm.data.notes} onChange={(value) => checkOutForm.setData("notes", value)} rows={3} />
                            <PrimaryButton disabled={checkOutForm.processing}>
                                {checkOutForm.processing ? "Saving..." : "Check Out"}
                            </PrimaryButton>
                        </form>
                    )}
                </SectionCard>

                <SectionCard title="Task Progress Update" description="Keep execution tasks current from the member panel.">
                    {tasks.length ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                progressForm.post(route("member.construction.tasks.progress.update", progressForm.data.execution_task_id), { preserveScroll: true });
                            }}
                        >
                            <SelectField
                                label="Task"
                                value={progressForm.data.execution_task_id}
                                onChange={(value) => {
                                    const task = tasks.find((item) => String(item.id) === String(value));
                                    progressForm.setData({
                                        execution_task_id: value,
                                        progress_percent: task?.progress_percent || 0,
                                        completed_quantity: task?.completed_quantity || "",
                                        status: task?.status || "planned",
                                    });
                                    reportForm.setData("execution_task_id", value);
                                }}
                                options={tasks.map((task) => ({
                                    value: String(task.id),
                                    label: `${task.task_code} • ${task.title}`,
                                }))}
                            />
                            <TextInput label="Progress %" type="number" value={progressForm.data.progress_percent} onChange={(value) => progressForm.setData("progress_percent", value)} />
                            <TextInput label="Completed Quantity" type="number" value={progressForm.data.completed_quantity} onChange={(value) => progressForm.setData("completed_quantity", value)} />
                            <SelectField
                                label="Status"
                                value={progressForm.data.status}
                                onChange={(value) => progressForm.setData("status", value)}
                                options={[
                                    { value: "planned", label: "Planned" },
                                    { value: "in_progress", label: "In Progress" },
                                    { value: "completed", label: "Completed" },
                                    { value: "blocked", label: "Blocked" },
                                ]}
                            />
                            {selectedTask ? (
                                <div className="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                    <p className="font-medium text-slate-900 dark:text-white">{selectedTask.project?.name || "-"}</p>
                                    <p className="mt-1">{selectedTask.title}</p>
                                </div>
                            ) : null}
                            <PrimaryButton disabled={progressForm.processing}>
                                {progressForm.processing ? "Saving..." : "Update Task Progress"}
                            </PrimaryButton>
                        </form>
                    ) : (
                        <EmptyState title="No assigned tasks." description="Task updates become available after execution assignment." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.15fr,1fr]">
                <SectionCard title="Daily Progress Report" description="Submit one DPR entry for your daily site work.">
                    {projects.length ? (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const payload = {
                                    ...reportForm.data,
                                    items: [
                                        {
                                            execution_task_id: reportForm.data.execution_task_id || null,
                                            title: reportForm.data.summary || "Daily site update",
                                            description: reportForm.data.work_completed || null,
                                            completed_quantity: Number(reportForm.data.workforce_count || 0),
                                            percent_complete: Number(progressForm.data.progress_percent || 0),
                                            remarks: reportForm.data.blockers || null,
                                        },
                                    ],
                                };

                                reportForm.transform(() => payload).post(route("member.construction.reports.store"), {
                                    preserveScroll: true,
                                    forceFormData: true,
                                    onFinish: () => reportForm.transform((data) => data),
                                });
                            }}
                        >
                            <SelectField
                                label="Project"
                                value={reportForm.data.project_id}
                                onChange={(value) => reportForm.setData("project_id", value)}
                                options={projects.map((project) => ({
                                    value: String(project.id),
                                    label: `${project.project_code} • ${project.name}`,
                                }))}
                            />
                            <SelectField
                                label="Task"
                                value={reportForm.data.execution_task_id}
                                onChange={(value) => reportForm.setData("execution_task_id", value)}
                                options={[
                                    { value: "", label: "Project-level report" },
                                    ...tasks.map((task) => ({
                                        value: String(task.id),
                                        label: `${task.task_code} • ${task.title}`,
                                    })),
                                ]}
                            />
                            <TextInput label="Report Date" type="date" value={reportForm.data.report_date} onChange={(value) => reportForm.setData("report_date", value)} />
                            <TextAreaInput label="Summary" value={reportForm.data.summary} onChange={(value) => reportForm.setData("summary", value)} rows={3} />
                            <TextAreaInput label="Work Completed" value={reportForm.data.work_completed} onChange={(value) => reportForm.setData("work_completed", value)} rows={4} />
                            <TextAreaInput label="Blockers" value={reportForm.data.blockers} onChange={(value) => reportForm.setData("blockers", value)} rows={3} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Workforce Count" type="number" value={reportForm.data.workforce_count} onChange={(value) => reportForm.setData("workforce_count", value)} />
                                <TextInput label="Weather Summary" value={reportForm.data.weather_summary} onChange={(value) => reportForm.setData("weather_summary", value)} />
                                <TextInput label="Latitude" value={reportForm.data.latitude} onChange={(value) => reportForm.setData("latitude", value)} />
                                <TextInput label="Longitude" value={reportForm.data.longitude} onChange={(value) => reportForm.setData("longitude", value)} />
                            </div>
                            <TextInput label="GPS Accuracy (m)" value={reportForm.data.gps_accuracy_meters} onChange={(value) => reportForm.setData("gps_accuracy_meters", value)} />
                            <FileInput label="Supporting Document" onChange={(file) => reportForm.setData("supporting_document", file)} error={reportForm.errors.supporting_document} />
                            <PrimaryButton disabled={reportForm.processing}>
                                {reportForm.processing ? "Submitting..." : "Submit DPR"}
                            </PrimaryButton>
                        </form>
                    ) : (
                        <EmptyState title="No projects assigned." description="DPR submission starts after project assignment." />
                    )}
                </SectionCard>

                <SectionCard title="Recent Records" description="Your latest attendance and DPR history.">
                    <div className="space-y-5">
                        <div>
                            <p className="mb-3 text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Attendance</p>
                            {latestAttendance.length ? (
                                <div className="space-y-3">
                                    {latestAttendance.map((item) => (
                                        <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="font-medium text-slate-900 dark:text-white">{item.project?.name || "-"}</p>
                                                    <p className="text-sm text-slate-500">{item.attendance_date}</p>
                                                </div>
                                                <StatusBadge value={item.status} />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <EmptyState title="No attendance yet." description="Attendance history will appear after your first check-in." />
                            )}
                        </div>

                        <div>
                            <p className="mb-3 text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Daily Progress Reports</p>
                            {latestReports.length ? (
                                <div className="space-y-3">
                                    {latestReports.map((item) => (
                                        <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="font-medium text-slate-900 dark:text-white">{item.project?.name || "-"}</p>
                                                    <p className="text-sm text-slate-500">{item.report_date} • {item.execution_task?.title || "Project-level report"}</p>
                                                </div>
                                                <StatusBadge value={item.status} />
                                            </div>
                                            {item.supporting_document ? (
                                                <div className="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                                    <span>{item.supporting_document.original_name}</span>
                                                    <a
                                                        href={route(`${documentRouteBase}.view`, item.supporting_document.id)}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"
                                                    >
                                                        View
                                                    </a>
                                                    <a
                                                        href={route(`${documentRouteBase}.download`, item.supporting_document.id)}
                                                        className="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                                                    >
                                                        Download
                                                    </a>
                                                </div>
                                            ) : null}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <EmptyState title="No DPR records yet." description="Submitted daily progress reports will appear here." />
                            )}
                        </div>
                    </div>
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function PrimaryButton({ children, disabled = false }) {
    return (
        <button
            type="submit"
            disabled={disabled}
            className="rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
        >
            {children}
        </button>
    );
}

function TextInput({ label, error, onChange, ...props }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <input
                {...props}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function TextAreaInput({ label, error, onChange, ...props }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <textarea
                {...props}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function SelectField({ label, error, value, onChange, options }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
                {options.map((option) => (
                    <option key={`${option.value}-${option.label}`} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function FileInput({ label, error, onChange }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <input
                type="file"
                onChange={(event) => onChange(event.target.files?.[0] || null)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:file:bg-slate-900 dark:file:text-slate-200"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}
