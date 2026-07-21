import { useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function EquipmentWorkspace({
    variant = "super",
    projects = [],
    equipments = [],
    allocations = [],
    usageLogs = [],
}) {
    const routeBase =
        variant === "super"
            ? "super.construction.equipment"
            : variant === "admin"
              ? "admin.construction.equipment"
              : "member.construction.equipment";

    const canManageEquipment = variant !== "member";
    const canAllocate = variant !== "member";

    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    const equipmentsByProject = useMemo(() => {
        return equipments.reduce((carry, equipment) => {
            const pid = String(equipment.project_id);
            if (!carry[pid]) carry[pid] = [];
            carry[pid].push(equipment);
            return carry;
        }, {});
    }, [equipments]);

    const activeAllocations = useMemo(() => allocations.filter((a) => a.status === "active"), [allocations]);

    const stats = useMemo(() => {
        return {
            projects: projects.length,
            equipments: equipments.length,
            allocations: allocations.length,
            activeAllocations: activeAllocations.length,
            usageLogs: usageLogs.length,
        };
    }, [projects, equipments, allocations, activeAllocations, usageLogs]);

    const equipmentForm = useForm({
        project_id: firstProjectId,
        equipment_code: "",
        name: "",
        equipment_type: "",
        serial_number: "",
        status: "active",
    });

    const [allocationCaptureStatus, setAllocationCaptureStatus] = useState(null);
    const allocationForm = useForm({
        project_id: firstProjectId,
        equipment_id: "",
        assigned_to_member_id: "",
        allocated_at: "",
        allocate_latitude: "",
        allocate_longitude: "",
        allocate_gps_accuracy_meters: "",
        notes: "",
    });

    const [returnCaptureStatus, setReturnCaptureStatus] = useState(null);
    const returnForm = useForm({
        project_id: firstProjectId,
        allocation_id: "",
        returned_at: "",
        return_latitude: "",
        return_longitude: "",
        return_gps_accuracy_meters: "",
    });

    const [usageCaptureStatus, setUsageCaptureStatus] = useState(null);
    const usageForm = useForm({
        project_id: firstProjectId,
        equipment_id: "",
        log_date: new Date().toISOString().slice(0, 10),
        hours_used: "",
        latitude: "",
        longitude: "",
        gps_accuracy_meters: "",
        notes: "",
    });

    const selectedEquipments = equipmentsByProject[String(allocationForm.data.project_id)] ?? [];

    const captureCurrentLocation = (form, setStatus, latKey, lonKey, accuracyKey) => {
        setStatus("Capturing location...");

        if (!navigator.geolocation) {
            setStatus("Geolocation is not supported by this browser.");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.setData((data) => ({
                    ...data,
                    [latKey]: String(position.coords.latitude),
                    [lonKey]: String(position.coords.longitude),
                    [accuracyKey]: position.coords.accuracy ? String(position.coords.accuracy) : "",
                }));
                setStatus("Location captured.");
            },
            () => {
                setStatus("Unable to capture current location.");
            },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    };

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
            title="Equipment Allocation"
            description="Register project equipment, allocate to team members, return with GPS verification, and log daily usage."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Projects" value={stats.projects} />
                <StatCard label="Equipments" value={stats.equipments} />
                <StatCard label="Allocations" value={stats.allocations} />
                <StatCard label="Active Allocations" value={stats.activeAllocations} />
                <StatCard label="Usage Logs" value={stats.usageLogs} />
            </div>

            {projects.length === 0 ? (
                <EmptyState title="No projects available." description="Create and assign projects first to start equipment allocation." />
            ) : null}

            <div className="grid gap-6 xl:grid-cols-2">
                {canManageEquipment ? (
                    <SectionCard title="Equipment Registry" description="Create equipment per project for allocations and usage tracking.">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                equipmentForm.post(route(`${routeBase}.store`), {
                                    preserveScroll: true,
                                    onSuccess: () => equipmentForm.reset("equipment_code", "name", "equipment_type", "serial_number"),
                                });
                            }}
                            className="space-y-4"
                        >
                            {renderProjectsSelect(
                                equipmentForm.data.project_id,
                                (value) => equipmentForm.setData("project_id", value),
                                equipmentForm.errors.project_id
                            )}
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextInput
                                    label="Name"
                                    value={equipmentForm.data.name}
                                    onChange={(value) => equipmentForm.setData("name", value)}
                                    error={equipmentForm.errors.name}
                                />
                                <TextInput
                                    label="Equipment Code (optional)"
                                    value={equipmentForm.data.equipment_code}
                                    onChange={(value) => equipmentForm.setData("equipment_code", value)}
                                    error={equipmentForm.errors.equipment_code}
                                />
                                <TextInput
                                    label="Type"
                                    value={equipmentForm.data.equipment_type}
                                    onChange={(value) => equipmentForm.setData("equipment_type", value)}
                                    error={equipmentForm.errors.equipment_type}
                                />
                                <TextInput
                                    label="Serial Number"
                                    value={equipmentForm.data.serial_number}
                                    onChange={(value) => equipmentForm.setData("serial_number", value)}
                                    error={equipmentForm.errors.serial_number}
                                />
                                <SelectInput
                                    label="Status"
                                    value={equipmentForm.data.status}
                                    onChange={(value) => equipmentForm.setData("status", value)}
                                    options={[
                                        { value: "active", label: "Active" },
                                        { value: "inactive", label: "Inactive" },
                                    ]}
                                    error={equipmentForm.errors.status}
                                />
                            </div>
                            <PrimaryButton processing={equipmentForm.processing} label="Save Equipment" />
                        </form>
                    </SectionCard>
                ) : null}

                {canAllocate ? (
                    <SectionCard title="Allocate Equipment" description="Allocate equipment to a member (optional) with GPS capture.">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                allocationForm.post(route(`${routeBase}.allocations.store`), {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        allocationForm.reset(
                                            "equipment_id",
                                            "assigned_to_member_id",
                                            "allocated_at",
                                            "allocate_latitude",
                                            "allocate_longitude",
                                            "allocate_gps_accuracy_meters",
                                            "notes"
                                        ),
                                });
                            }}
                            className="space-y-4"
                        >
                            {renderProjectsSelect(
                                allocationForm.data.project_id,
                                (value) => allocationForm.setData("project_id", value),
                                allocationForm.errors.project_id
                            )}
                            <SelectInput
                                label="Equipment"
                                value={allocationForm.data.equipment_id}
                                onChange={(value) => allocationForm.setData("equipment_id", value)}
                                options={[
                                    { value: "", label: "Select equipment" },
                                    ...selectedEquipments.map((equipment) => ({
                                        value: String(equipment.id),
                                        label: `${equipment.equipment_code} • ${equipment.name}`,
                                    })),
                                ]}
                                error={allocationForm.errors.equipment_id}
                            />
                            <TextInput
                                label="Assigned Member ID (optional)"
                                value={allocationForm.data.assigned_to_member_id}
                                onChange={(value) => allocationForm.setData("assigned_to_member_id", value)}
                                error={allocationForm.errors.assigned_to_member_id}
                            />
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextInput
                                    label="Allocated At (optional)"
                                    type="datetime-local"
                                    value={allocationForm.data.allocated_at}
                                    onChange={(value) => allocationForm.setData("allocated_at", value)}
                                    error={allocationForm.errors.allocated_at}
                                />
                                <TextInput
                                    label="GPS Accuracy (m)"
                                    value={allocationForm.data.allocate_gps_accuracy_meters}
                                    onChange={(value) => allocationForm.setData("allocate_gps_accuracy_meters", value)}
                                    error={allocationForm.errors.allocate_gps_accuracy_meters}
                                />
                                <TextInput
                                    label="Latitude"
                                    value={allocationForm.data.allocate_latitude}
                                    onChange={(value) => allocationForm.setData("allocate_latitude", value)}
                                    error={allocationForm.errors.allocate_latitude}
                                />
                                <TextInput
                                    label="Longitude"
                                    value={allocationForm.data.allocate_longitude}
                                    onChange={(value) => allocationForm.setData("allocate_longitude", value)}
                                    error={allocationForm.errors.allocate_longitude}
                                />
                            </div>
                            <TextAreaInput
                                label="Notes"
                                value={allocationForm.data.notes}
                                onChange={(value) => allocationForm.setData("notes", value)}
                                error={allocationForm.errors.notes}
                            />
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <button
                                    type="button"
                                    onClick={() =>
                                        captureCurrentLocation(
                                            allocationForm,
                                            setAllocationCaptureStatus,
                                            "allocate_latitude",
                                            "allocate_longitude",
                                            "allocate_gps_accuracy_meters"
                                        )
                                    }
                                    className="rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    Use Current Location
                                </button>
                                {allocationCaptureStatus ? (
                                    <p className="text-xs text-slate-600 dark:text-slate-300">{allocationCaptureStatus}</p>
                                ) : null}
                            </div>
                            <PrimaryButton processing={allocationForm.processing} label="Save Allocation" />
                        </form>
                    </SectionCard>
                ) : null}

                <SectionCard title="Return Equipment" description="Return an active allocation with GPS capture.">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            returnForm.post(route(`${routeBase}.allocations.return`), {
                                preserveScroll: true,
                                onSuccess: () =>
                                    returnForm.reset(
                                        "allocation_id",
                                        "returned_at",
                                        "return_latitude",
                                        "return_longitude",
                                        "return_gps_accuracy_meters"
                                    ),
                            });
                        }}
                        className="space-y-4"
                    >
                        {renderProjectsSelect(
                            returnForm.data.project_id,
                            (value) => returnForm.setData("project_id", value),
                            returnForm.errors.project_id
                        )}
                        <SelectInput
                            label="Active Allocation"
                            value={returnForm.data.allocation_id}
                            onChange={(value) => returnForm.setData("allocation_id", value)}
                            options={[
                                { value: "", label: "Select allocation" },
                                ...activeAllocations
                                    .filter((allocation) => String(allocation.project_id) === String(returnForm.data.project_id))
                                    .map((allocation) => ({
                                        value: String(allocation.id),
                                        label: `${allocation.equipment?.equipment_code || `#${allocation.equipment_id}`} • ${
                                            allocation.equipment?.name || "Equipment"
                                        }`,
                                    })),
                            ]}
                            error={returnForm.errors.allocation_id}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Returned At (optional)"
                                type="datetime-local"
                                value={returnForm.data.returned_at}
                                onChange={(value) => returnForm.setData("returned_at", value)}
                                error={returnForm.errors.returned_at}
                            />
                            <TextInput
                                label="GPS Accuracy (m)"
                                value={returnForm.data.return_gps_accuracy_meters}
                                onChange={(value) => returnForm.setData("return_gps_accuracy_meters", value)}
                                error={returnForm.errors.return_gps_accuracy_meters}
                            />
                            <TextInput
                                label="Latitude"
                                value={returnForm.data.return_latitude}
                                onChange={(value) => returnForm.setData("return_latitude", value)}
                                error={returnForm.errors.return_latitude}
                            />
                            <TextInput
                                label="Longitude"
                                value={returnForm.data.return_longitude}
                                onChange={(value) => returnForm.setData("return_longitude", value)}
                                error={returnForm.errors.return_longitude}
                            />
                        </div>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                type="button"
                                onClick={() =>
                                    captureCurrentLocation(
                                        returnForm,
                                        setReturnCaptureStatus,
                                        "return_latitude",
                                        "return_longitude",
                                        "return_gps_accuracy_meters"
                                    )
                                }
                                className="rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Use Current Location
                            </button>
                            {returnCaptureStatus ? (
                                <p className="text-xs text-slate-600 dark:text-slate-300">{returnCaptureStatus}</p>
                            ) : null}
                        </div>
                        <PrimaryButton processing={returnForm.processing} label="Return Equipment" />
                    </form>
                </SectionCard>

                <SectionCard title="Log Usage" description="Log daily equipment usage hours with GPS verification.">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            usageForm.post(route(`${routeBase}.usage.store`), {
                                preserveScroll: true,
                                onSuccess: () =>
                                    usageForm.reset(
                                        "equipment_id",
                                        "log_date",
                                        "hours_used",
                                        "latitude",
                                        "longitude",
                                        "gps_accuracy_meters",
                                        "notes"
                                    ),
                            });
                        }}
                        className="space-y-4"
                    >
                        {renderProjectsSelect(
                            usageForm.data.project_id,
                            (value) => usageForm.setData("project_id", value),
                            usageForm.errors.project_id
                        )}
                        <SelectInput
                            label="Equipment"
                            value={usageForm.data.equipment_id}
                            onChange={(value) => usageForm.setData("equipment_id", value)}
                            options={[
                                { value: "", label: "Select equipment" },
                                ...(equipmentsByProject[String(usageForm.data.project_id)] ?? []).map((equipment) => ({
                                    value: String(equipment.id),
                                    label: `${equipment.equipment_code} • ${equipment.name}`,
                                })),
                            ]}
                            error={usageForm.errors.equipment_id}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Log Date"
                                type="date"
                                value={usageForm.data.log_date}
                                onChange={(value) => usageForm.setData("log_date", value)}
                                error={usageForm.errors.log_date}
                            />
                            <TextInput
                                label="Hours Used"
                                value={usageForm.data.hours_used}
                                onChange={(value) => usageForm.setData("hours_used", value)}
                                error={usageForm.errors.hours_used}
                            />
                            <TextInput
                                label="GPS Accuracy (m)"
                                value={usageForm.data.gps_accuracy_meters}
                                onChange={(value) => usageForm.setData("gps_accuracy_meters", value)}
                                error={usageForm.errors.gps_accuracy_meters}
                            />
                            <TextInput
                                label="Latitude"
                                value={usageForm.data.latitude}
                                onChange={(value) => usageForm.setData("latitude", value)}
                                error={usageForm.errors.latitude}
                            />
                            <TextInput
                                label="Longitude"
                                value={usageForm.data.longitude}
                                onChange={(value) => usageForm.setData("longitude", value)}
                                error={usageForm.errors.longitude}
                            />
                        </div>
                        <TextAreaInput
                            label="Notes"
                            value={usageForm.data.notes}
                            onChange={(value) => usageForm.setData("notes", value)}
                            error={usageForm.errors.notes}
                        />
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                type="button"
                                onClick={() =>
                                    captureCurrentLocation(usageForm, setUsageCaptureStatus, "latitude", "longitude", "gps_accuracy_meters")
                                }
                                className="rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Use Current Location
                            </button>
                            {usageCaptureStatus ? (
                                <p className="text-xs text-slate-600 dark:text-slate-300">{usageCaptureStatus}</p>
                            ) : null}
                        </div>
                        <PrimaryButton processing={usageForm.processing} label="Save Usage Log" />
                    </form>
                </SectionCard>
            </div>

            <SectionCard title="Active Allocations" description="Track what is currently on-site / assigned.">
                {activeAllocations.length === 0 ? (
                    <EmptyState title="No active allocations." description="Allocate equipment to start tracking its usage lifecycle." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-slate-500 dark:text-slate-300">
                                    <th className="py-2 pr-4">Project</th>
                                    <th className="py-2 pr-4">Equipment</th>
                                    <th className="py-2 pr-4">Allocated</th>
                                    <th className="py-2 pr-4">Assigned</th>
                                    <th className="py-2 pr-4">GPS</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {activeAllocations.map((allocation) => (
                                    <tr key={allocation.id}>
                                        <td className="py-3 pr-4">
                                            <p className="font-medium text-slate-900 dark:text-white">
                                                {allocation.project?.project_code || `#${allocation.project_id}`}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <p className="font-medium text-slate-900 dark:text-white">
                                                {allocation.equipment?.equipment_code || `#${allocation.equipment_id}`}
                                            </p>
                                            <p className="text-xs text-slate-500 dark:text-slate-300">{allocation.equipment?.name}</p>
                                        </td>
                                        <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                            {allocation.allocated_at ? new Date(allocation.allocated_at).toLocaleString() : "-"}
                                        </td>
                                        <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                            {allocation.assigned_to_member_id ? `#${allocation.assigned_to_member_id}` : "-"}
                                        </td>
                                        <td className="py-3 pr-4">
                                            <StatusBadge
                                                status={allocation.allocate_gps_verified ? "gps_verified" : "unverified"}
                                                label={allocation.allocate_gps_verified ? "GPS Verified" : "Unverified"}
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

function TextAreaInput({ label, error, value, onChange }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <textarea
                value={value}
                onChange={(event) => onChange(event.target.value)}
                rows={3}
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

