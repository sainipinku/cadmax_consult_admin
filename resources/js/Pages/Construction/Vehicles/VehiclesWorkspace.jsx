import { useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
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
    pings = [],
}) {
    const routeBase =
        variant === "super"
            ? "super.construction.vehicles"
            : variant === "admin"
              ? "admin.construction.vehicles"
              : "member.construction.vehicles";

    const canManageVehicles = variant !== "member";
    const canManageAssignments = variant !== "member";

    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    const vehiclesByProject = useMemo(() => {
        return vehicles.reduce((carry, vehicle) => {
            const pid = String(vehicle.project_id);
            if (!carry[pid]) carry[pid] = [];
            carry[pid].push(vehicle);
            return carry;
        }, {});
    }, [vehicles]);

    const stats = useMemo(() => {
        const verified = pings.filter((ping) => !!ping.gps_verified).length;
        const activeAssignments = assignments.filter((a) => a.status === "active").length;
        return {
            projects: projects.length,
            vehicles: vehicles.length,
            assignments: assignments.length,
            activeAssignments,
            pings: pings.length,
            verified,
        };
    }, [projects, vehicles, assignments, pings]);

    const vehicleForm = useForm({
        project_id: firstProjectId,
        vehicle_code: "",
        registration_number: "",
        vehicle_type: "",
        make: "",
        model: "",
        status: "active",
    });

    const assignmentForm = useForm({
        project_id: firstProjectId,
        vehicle_id: "",
        driver_member_id: "",
        assigned_from: "",
        assigned_to: "",
        status: "active",
    });

    const [pingCaptureStatus, setPingCaptureStatus] = useState(null);
    const pingForm = useForm({
        project_id: firstProjectId,
        vehicle_id: "",
        recorded_at: "",
        latitude: "",
        longitude: "",
        gps_accuracy_meters: "",
        speed_kmph: "",
        heading_degrees: "",
        odometer_km: "",
    });

    const selectedVehicles = vehiclesByProject[String(pingForm.data.project_id)] ?? [];

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

    const captureCurrentLocation = () => {
        setPingCaptureStatus("Capturing location...");

        if (!navigator.geolocation) {
            setPingCaptureStatus("Geolocation is not supported by this browser.");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                pingForm.setData((data) => ({
                    ...data,
                    latitude: String(position.coords.latitude),
                    longitude: String(position.coords.longitude),
                    gps_accuracy_meters: position.coords.accuracy ? String(position.coords.accuracy) : "",
                }));
                setPingCaptureStatus("Location captured.");
            },
            () => {
                setPingCaptureStatus("Unable to capture current location.");
            },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    };

    return (
        <ConstructionShell
            title="Vehicle Tracking"
            description="Register project vehicles, assign drivers, and record GPS pings with verification."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard label="Projects" value={stats.projects} />
                <StatCard label="Vehicles" value={stats.vehicles} />
                <StatCard label="Assignments" value={stats.assignments} />
                <StatCard label="Active Assignments" value={stats.activeAssignments} />
                <StatCard label="Location Pings" value={stats.pings} />
                <StatCard label="GPS Verified" value={stats.verified} />
            </div>

            {projects.length === 0 ? (
                <EmptyState title="No projects available." description="Create and assign projects first to start vehicle tracking." />
            ) : null}

            <div className="grid gap-6 xl:grid-cols-2">
                {canManageVehicles ? (
                    <SectionCard title="Vehicle Registry" description="Create vehicles per project for tracking and assignment.">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                vehicleForm.post(route(`${routeBase}.store`), {
                                    preserveScroll: true,
                                    onSuccess: () => vehicleForm.reset("vehicle_code", "registration_number", "vehicle_type", "make", "model"),
                                });
                            }}
                            className="space-y-4"
                        >
                            {renderProjectsSelect(
                                vehicleForm.data.project_id,
                                (value) => vehicleForm.setData("project_id", value),
                                vehicleForm.errors.project_id
                            )}
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextInput
                                    label="Registration Number"
                                    value={vehicleForm.data.registration_number}
                                    onChange={(value) => vehicleForm.setData("registration_number", value)}
                                    error={vehicleForm.errors.registration_number}
                                />
                                <TextInput
                                    label="Vehicle Code (optional)"
                                    value={vehicleForm.data.vehicle_code}
                                    onChange={(value) => vehicleForm.setData("vehicle_code", value)}
                                    error={vehicleForm.errors.vehicle_code}
                                />
                                <TextInput
                                    label="Type"
                                    value={vehicleForm.data.vehicle_type}
                                    onChange={(value) => vehicleForm.setData("vehicle_type", value)}
                                    error={vehicleForm.errors.vehicle_type}
                                />
                                <TextInput
                                    label="Make"
                                    value={vehicleForm.data.make}
                                    onChange={(value) => vehicleForm.setData("make", value)}
                                    error={vehicleForm.errors.make}
                                />
                                <TextInput
                                    label="Model"
                                    value={vehicleForm.data.model}
                                    onChange={(value) => vehicleForm.setData("model", value)}
                                    error={vehicleForm.errors.model}
                                />
                                <SelectInput
                                    label="Status"
                                    value={vehicleForm.data.status}
                                    onChange={(value) => vehicleForm.setData("status", value)}
                                    options={[
                                        { value: "active", label: "Active" },
                                        { value: "inactive", label: "Inactive" },
                                    ]}
                                    error={vehicleForm.errors.status}
                                />
                            </div>
                            <PrimaryButton processing={vehicleForm.processing} label="Save Vehicle" />
                        </form>
                    </SectionCard>
                ) : null}

                {canManageAssignments ? (
                    <SectionCard title="Driver Assignment" description="Assign a driver (member) to a vehicle. Driver must be part of the project team.">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                assignmentForm.post(route(`${routeBase}.assignments.store`), {
                                    preserveScroll: true,
                                    onSuccess: () => assignmentForm.reset("vehicle_id", "driver_member_id", "assigned_from", "assigned_to"),
                                });
                            }}
                            className="space-y-4"
                        >
                            {renderProjectsSelect(
                                assignmentForm.data.project_id,
                                (value) => assignmentForm.setData("project_id", value),
                                assignmentForm.errors.project_id
                            )}
                            <SelectInput
                                label="Vehicle"
                                value={assignmentForm.data.vehicle_id}
                                onChange={(value) => assignmentForm.setData("vehicle_id", value)}
                                options={[
                                    { value: "", label: "Select vehicle" },
                                    ...(vehiclesByProject[String(assignmentForm.data.project_id)] ?? []).map((vehicle) => ({
                                        value: String(vehicle.id),
                                        label: `${vehicle.vehicle_code} • ${vehicle.registration_number}`,
                                    })),
                                ]}
                                error={assignmentForm.errors.vehicle_id}
                            />
                            <TextInput
                                label="Driver Member ID (optional)"
                                value={assignmentForm.data.driver_member_id}
                                onChange={(value) => assignmentForm.setData("driver_member_id", value)}
                                error={assignmentForm.errors.driver_member_id}
                            />
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextInput
                                    label="Assigned From (optional)"
                                    type="datetime-local"
                                    value={assignmentForm.data.assigned_from}
                                    onChange={(value) => assignmentForm.setData("assigned_from", value)}
                                    error={assignmentForm.errors.assigned_from}
                                />
                                <TextInput
                                    label="Assigned To (optional)"
                                    type="datetime-local"
                                    value={assignmentForm.data.assigned_to}
                                    onChange={(value) => assignmentForm.setData("assigned_to", value)}
                                    error={assignmentForm.errors.assigned_to}
                                />
                            </div>
                            <PrimaryButton processing={assignmentForm.processing} label="Save Assignment" />
                        </form>
                    </SectionCard>
                ) : null}

                <SectionCard title="Record Location Ping" description="Capture GPS location for a vehicle. GPS verified when accuracy ≤ 50m.">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            pingForm.post(route(`${routeBase}.pings.store`), {
                                preserveScroll: true,
                                onSuccess: () =>
                                    pingForm.reset(
                                        "vehicle_id",
                                        "recorded_at",
                                        "latitude",
                                        "longitude",
                                        "gps_accuracy_meters",
                                        "speed_kmph",
                                        "heading_degrees",
                                        "odometer_km"
                                    ),
                            });
                        }}
                        className="space-y-4"
                    >
                        {renderProjectsSelect(
                            pingForm.data.project_id,
                            (value) => pingForm.setData("project_id", value),
                            pingForm.errors.project_id
                        )}
                        <SelectInput
                            label="Vehicle"
                            value={pingForm.data.vehicle_id}
                            onChange={(value) => pingForm.setData("vehicle_id", value)}
                            options={[
                                { value: "", label: "Select vehicle" },
                                ...selectedVehicles.map((vehicle) => ({
                                    value: String(vehicle.id),
                                    label: `${vehicle.vehicle_code} • ${vehicle.registration_number}`,
                                })),
                            ]}
                            error={pingForm.errors.vehicle_id}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Latitude"
                                value={pingForm.data.latitude}
                                onChange={(value) => pingForm.setData("latitude", value)}
                                error={pingForm.errors.latitude}
                            />
                            <TextInput
                                label="Longitude"
                                value={pingForm.data.longitude}
                                onChange={(value) => pingForm.setData("longitude", value)}
                                error={pingForm.errors.longitude}
                            />
                            <TextInput
                                label="GPS Accuracy (m)"
                                value={pingForm.data.gps_accuracy_meters}
                                onChange={(value) => pingForm.setData("gps_accuracy_meters", value)}
                                error={pingForm.errors.gps_accuracy_meters}
                            />
                            <TextInput
                                label="Recorded At (optional)"
                                type="datetime-local"
                                value={pingForm.data.recorded_at}
                                onChange={(value) => pingForm.setData("recorded_at", value)}
                                error={pingForm.errors.recorded_at}
                            />
                            <TextInput
                                label="Speed (km/h)"
                                value={pingForm.data.speed_kmph}
                                onChange={(value) => pingForm.setData("speed_kmph", value)}
                                error={pingForm.errors.speed_kmph}
                            />
                            <TextInput
                                label="Heading (deg)"
                                value={pingForm.data.heading_degrees}
                                onChange={(value) => pingForm.setData("heading_degrees", value)}
                                error={pingForm.errors.heading_degrees}
                            />
                            <TextInput
                                label="Odometer (km)"
                                value={pingForm.data.odometer_km}
                                onChange={(value) => pingForm.setData("odometer_km", value)}
                                error={pingForm.errors.odometer_km}
                            />
                        </div>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                type="button"
                                onClick={captureCurrentLocation}
                                className="rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Use Current Location
                            </button>
                            {pingCaptureStatus ? (
                                <p className="text-xs text-slate-600 dark:text-slate-300">{pingCaptureStatus}</p>
                            ) : null}
                        </div>
                        <PrimaryButton processing={pingForm.processing} label="Save Location Ping" />
                    </form>
                </SectionCard>
            </div>

            <SectionCard title="Recent Pings" description="Latest vehicle locations across your accessible projects.">
                {pings.length === 0 ? (
                    <EmptyState title="No pings yet." description="Submit at least one GPS ping to start tracking movement." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-slate-500 dark:text-slate-300">
                                    <th className="py-2 pr-4">Project</th>
                                    <th className="py-2 pr-4">Vehicle</th>
                                    <th className="py-2 pr-4">Recorded</th>
                                    <th className="py-2 pr-4">Location</th>
                                    <th className="py-2 pr-4">Accuracy</th>
                                    <th className="py-2 pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {pings.map((ping) => (
                                    <tr key={ping.id}>
                                        <td className="py-3 pr-4">
                                            <p className="font-medium text-slate-900 dark:text-white">
                                                {ping.project?.project_code || `#${ping.project_id}`}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <p className="font-medium text-slate-900 dark:text-white">
                                                {ping.vehicle?.vehicle_code || `#${ping.vehicle_id}`}
                                            </p>
                                            <p className="text-xs text-slate-500 dark:text-slate-300">
                                                {ping.vehicle?.registration_number}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                            {ping.recorded_at ? new Date(ping.recorded_at).toLocaleString() : "-"}
                                        </td>
                                        <td className="py-3 pr-4">
                                            <a
                                                href={`https://www.google.com/maps?q=${ping.latitude},${ping.longitude}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-indigo-600 hover:underline"
                                            >
                                                {Number(ping.latitude).toFixed(5)}, {Number(ping.longitude).toFixed(5)}
                                            </a>
                                        </td>
                                        <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                            {ping.gps_accuracy_meters ? `${Number(ping.gps_accuracy_meters).toFixed(0)}m` : "-"}
                                        </td>
                                        <td className="py-3 pr-4">
                                            <StatusBadge
                                                status={ping.gps_verified ? "gps_verified" : "unverified"}
                                                label={ping.gps_verified ? "GPS Verified" : "Unverified"}
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
