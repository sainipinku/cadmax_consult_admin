import { useForm } from "@inertiajs/react";
import { useMemo } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function VehiclesWorkspace({
    variant = "super",
    projects = [],
    vehicles = [],
    assignments = [],
    members = [],
}) {
    const routeBase =
        variant === "super"
            ? "super.construction.vehicles"
            : variant === "admin"
              ? "admin.construction.vehicles"
              : "member.construction.vehicles";

    const canManageAssignments = variant !== "member";
    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    const assignmentForm = useForm({
        project_id: firstProjectId,
        vehicle_id: "",
        driver_member_id: "",
        assigned_from: "",
        assigned_to: "",
        note: "",
        status: "active",
    });

    const availableVehiclesForProject = useMemo(() => {
        const selectedPid = String(assignmentForm.data.project_id || "");
        return vehicles.filter((v) => !v.project_id || String(v.project_id) === selectedPid);
    }, [vehicles, assignmentForm.data.project_id]);

    const stats = useMemo(() => {
        const activeAssignments = assignments.filter((a) => a.status === "active").length;
        return {
            projects: projects.length,
            vehicles: vehicles.length,
            assignments: assignments.length,
            activeAssignments,
        };
    }, [projects, vehicles, assignments]);

    const driverMembers = useMemo(() => {
        const drivers = members.filter((member) => !!member.is_driver);
        return drivers.length > 0 ? drivers : members;
    }, [members]);

    const renderProjectsSelect = (value, onChange, error) => (
        <SelectInput
            label="Project"
            value={value}
            onChange={onChange}
            options={projects.map((project) => ({
                value: String(project.id),
                label: `${project.project_code} • ${project.name}`,
            }))}
            error={error}
        />
    );

    return (
        <ConstructionShell
            title="Vehicle Assignment"
            description="Assign registered vehicles to drivers with assignment schedules and notes."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Projects" value={stats.projects} />
                <StatCard label="Vehicles" value={stats.vehicles} />
                <StatCard label="Assignments" value={stats.assignments} />
                <StatCard label="Active Assignments" value={stats.activeAssignments} />
            </div>

            {projects.length === 0 ? (
                <EmptyState title="No projects available." description="Create and assign projects first to start vehicle assignments." />
            ) : null}

            {canManageAssignments ? (
                <SectionCard title="Driver Assignment" description="Assign a vehicle to a driver (members with Driver role).">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            assignmentForm.post(route(`${routeBase}.assignments.store`), {
                                preserveScroll: true,
                                onSuccess: () => assignmentForm.reset("vehicle_id", "driver_member_id", "assigned_from", "assigned_to", "note"),
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            {renderProjectsSelect(
                                assignmentForm.data.project_id,
                                (value) => {
                                    assignmentForm.setData((data) => ({
                                        ...data,
                                        project_id: value,
                                        vehicle_id: "",
                                    }));
                                },
                                assignmentForm.errors.project_id
                            )}
                            <SelectInput
                                label="Vehicle"
                                value={assignmentForm.data.vehicle_id}
                                onChange={(value) => assignmentForm.setData("vehicle_id", value)}
                                options={[
                                    { value: "", label: "Select vehicle" },
                                    ...availableVehiclesForProject.map((vehicle) => ({
                                        value: vehicle.source ? `${vehicle.source}_${vehicle.id}` : String(vehicle.id),
                                        label: `${vehicle.vehicle_code || vehicle.vehicle_id} • ${vehicle.registration_number || vehicle.vehicle_number}${vehicle.vehicle_name ? ` (${vehicle.vehicle_name})` : ""}`,
                                    })),
                                ]}
                                error={assignmentForm.errors.vehicle_id}
                            />
                            <SelectInput
                                label="Driver (Member with Driver Role)"
                                value={assignmentForm.data.driver_member_id}
                                onChange={(value) => assignmentForm.setData("driver_member_id", value)}
                                options={[
                                    { value: "", label: "Select driver member" },
                                    ...driverMembers.map((member) => ({
                                        value: String(member.id),
                                        label: `${member.name}${member.designation_text ? ` (${member.designation_text})` : ""}${member.email ? ` • ${member.email}` : ""}`,
                                    })),
                                ]}
                                error={assignmentForm.errors.driver_member_id}
                            />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Assigned From (Date & Time)"
                                type="datetime-local"
                                value={assignmentForm.data.assigned_from}
                                onChange={(value) => assignmentForm.setData("assigned_from", value)}
                                error={assignmentForm.errors.assigned_from}
                            />
                            <TextInput
                                label="Assigned To (Date & Time)"
                                type="datetime-local"
                                value={assignmentForm.data.assigned_to}
                                onChange={(value) => assignmentForm.setData("assigned_to", value)}
                                error={assignmentForm.errors.assigned_to}
                            />
                        </div>
                        <TextAreaInput
                            label="Note / Remarks"
                            placeholder="Enter assignment details or notes..."
                            value={assignmentForm.data.note}
                            onChange={(value) => assignmentForm.setData("note", value)}
                            error={assignmentForm.errors.note}
                        />
                        <PrimaryButton processing={assignmentForm.processing} label="Save Assignment" />
                    </form>
                </SectionCard>
            ) : null}

            <SectionCard title="Vehicle Assignments" description="List of driver assignments across project vehicles.">
                {assignments.length === 0 ? (
                    <EmptyState title="No assignments recorded." description="Assign drivers to project vehicles using the form above." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-slate-500 dark:text-slate-300">
                                    <th className="py-2 pr-4">Project</th>
                                    <th className="py-2 pr-4">Vehicle</th>
                                    <th className="py-2 pr-4">Driver (Member)</th>
                                    <th className="py-2 pr-4">Assigned From</th>
                                    <th className="py-2 pr-4">Assigned To</th>
                                    <th className="py-2 pr-4">Note / Remarks</th>
                                    <th className="py-2 pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {assignments.map((assignment) => (
                                    <tr key={assignment.id}>
                                        <td className="py-3 pr-4">
                                            <p className="font-medium text-slate-900 dark:text-white">
                                                {assignment.project?.project_code || `#${assignment.project_id}`}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <p className="font-medium text-slate-900 dark:text-white">
                                                {assignment.vehicle?.vehicle_code || `#${assignment.vehicle_id}`}
                                            </p>
                                            <p className="text-xs text-slate-500 dark:text-slate-300">
                                                {assignment.vehicle?.registration_number}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <p className="font-medium text-slate-900 dark:text-white">
                                                {assignment.driver?.name || "Unassigned"}
                                            </p>
                                            <p className="text-xs text-slate-500 dark:text-slate-300">
                                                {assignment.driver?.email || ""}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                            {assignment.assigned_from ? new Date(assignment.assigned_from).toLocaleString() : "-"}
                                        </td>
                                        <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                            {assignment.assigned_to ? new Date(assignment.assigned_to).toLocaleString() : "-"}
                                        </td>
                                        <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                            {assignment.notes || assignment.note || "-"}
                                        </td>
                                        <td className="py-3 pr-4">
                                            <StatusBadge
                                                value={assignment.status}
                                                label={assignment.status === "active" ? "Active" : "Inactive"}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </SectionCard>
        </ConstructionShell>
    );
}

function TextInput({ label, error, value, onChange, type = "text" }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function TextAreaInput({ label, error, value, onChange, placeholder, rows = 3 }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <textarea
                rows={rows}
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function SelectInput({ label, error, value, onChange, options }) {
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

function PrimaryButton({ processing, label }) {
    return (
        <button
            type="submit"
            disabled={processing}
            className="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-indigo-400"
        >
            {processing ? "Saving..." : label}
        </button>
    );
}
