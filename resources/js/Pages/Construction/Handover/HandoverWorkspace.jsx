import { useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function HandoverWorkspace({ variant = "super", projects = [], handovers = [] }) {
    const routeBase =
        variant === "super"
            ? "super.construction.handover"
            : variant === "admin"
              ? "admin.construction.handover"
              : "member.construction.handover";
    const documentRouteBase =
        variant === "super"
            ? "super.construction.documents"
            : variant === "admin"
              ? "admin.construction.documents"
              : "member.construction.documents";

    const canCreate = variant !== "member";
    const canComplete = variant !== "member";
    const canClose = variant !== "member";
    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    const stats = useMemo(() => {
        const handedOver = handovers.filter((item) => item.status === "handed_over").length;
        const closed = handovers.filter((item) => item.status === "closed").length;
        return {
            projects: projects.length,
            handovers: handovers.length,
            handedOver,
            closed,
        };
    }, [projects, handovers]);

    const handoverForm = useForm({
        project_id: firstProjectId,
        handover_code: "",
        planned_handover_date: "",
        status: "draft",
        final_document: null,
        items: [],
    });

    const [items, setItems] = useState([{ title: "", category: "", status: "pending", notes: "" }]);

    return (
        <ConstructionShell
            title="Client Handover & Closure"
            description="Checklist-driven handover, final sign-off, and project closure workflow."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Projects" value={stats.projects} />
                <StatCard label="Handovers" value={stats.handovers} />
                <StatCard label="Handed Over" value={stats.handedOver} />
                <StatCard label="Closed" value={stats.closed} />
            </div>

            {projects.length === 0 ? (
                <EmptyState title="No projects available." description="Create and assign projects first to start handover and closure." />
            ) : null}

            {canCreate ? (
                <SectionCard title="Create Handover Checklist" description="Prepare final client handover checklist and document placeholder.">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            handoverForm.transform((data) => ({ ...data, items }));
                            handoverForm.post(route(`${routeBase}.store`), {
                                preserveScroll: true,
                                forceFormData: true,
                                onSuccess: () => {
                                    handoverForm.reset("handover_code", "planned_handover_date", "final_document");
                                    setItems([{ title: "", category: "", status: "pending", notes: "" }]);
                                },
                            });
                        }}
                        className="space-y-4"
                    >
                        <SelectInput
                            label="Project"
                            value={handoverForm.data.project_id}
                            onChange={(value) => handoverForm.setData("project_id", value)}
                            options={projects.map((project) => ({
                                value: String(project.id),
                                label: `${project.project_code} • ${project.name}`,
                            }))}
                            error={handoverForm.errors.project_id}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Planned Handover Date"
                                type="date"
                                value={handoverForm.data.planned_handover_date}
                                onChange={(value) => handoverForm.setData("planned_handover_date", value)}
                                error={handoverForm.errors.planned_handover_date}
                            />
                            <FileInput
                                label="Final Document"
                                onChange={(file) => handoverForm.setData("final_document", file)}
                                error={handoverForm.errors.final_document}
                            />
                        </div>
                        <ChecklistEditor items={items} onChange={setItems} errors={handoverForm.errors} />
                        <PrimaryButton processing={handoverForm.processing} label="Save Handover Checklist" />
                    </form>
                </SectionCard>
            ) : null}

            <SectionCard title="Handover Tracker" description="Manage checklist items, complete handover, and close projects.">
                {handovers.length === 0 ? (
                    <EmptyState title="No handovers yet." description="Create a handover checklist to start the final delivery process." />
                ) : (
                    <div className="space-y-6">
                        {handovers.map((handover) => (
                            <div key={handover.id} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h3 className="text-base font-semibold text-slate-900 dark:text-white">
                                            {handover.handover_code} • {handover.project?.project_code} • {handover.project?.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                            Planned: {handover.planned_handover_date || "-"} | Final document: {handover.final_document?.original_name || "-"}
                                        </p>
                                        {handover.final_document ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                <a
                                                    href={route(`${documentRouteBase}.view`, handover.final_document.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                >
                                                    View Document
                                                </a>
                                                <a
                                                    href={route(`${documentRouteBase}.download`, handover.final_document.id)}
                                                    className="rounded-full bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500"
                                                >
                                                    Download
                                                </a>
                                            </div>
                                        ) : null}
                                    </div>
                                    <StatusBadge value={handover.status} />
                                </div>
                                <div className="mt-4 space-y-3">
                                    {handover.items?.map((item) => (
                                        <ChecklistRow key={item.id} routeBase={routeBase} item={item} />
                                    ))}
                                </div>

                                {canComplete && handover.status !== "handed_over" && handover.status !== "closed" ? (
                                    <div className="mt-4 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                        <p className="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Complete Handover</p>
                                        <CompleteHandoverForm routeBase={routeBase} handoverId={handover.id} />
                                    </div>
                                ) : null}

                                {canClose && handover.status === "handed_over" ? (
                                    <div className="mt-4 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                        <p className="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Close Project</p>
                                        <CloseProjectForm routeBase={routeBase} handoverId={handover.id} />
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                )}
            </SectionCard>
        </ConstructionShell>
    );
}

function ChecklistRow({ routeBase, item }) {
    const form = useForm({
        status: item.status,
        notes: item.notes || "",
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(route(`${routeBase}.items.update`, item.id), { preserveScroll: true });
            }}
            className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
        >
            <div className="grid gap-4 md:grid-cols-[1.5fr,1fr,1.5fr,auto] md:items-end">
                <div>
                    <p className="text-sm font-semibold text-slate-900 dark:text-white">{item.title}</p>
                    <p className="text-xs text-slate-500 dark:text-slate-300">{item.category || "General"}</p>
                </div>
                <SelectInput
                    label="Status"
                    value={form.data.status}
                    onChange={(value) => form.setData("status", value)}
                    options={[
                        { value: "pending", label: "Pending" },
                        { value: "completed", label: "Completed" },
                        { value: "waived", label: "Waived" },
                    ]}
                    error={form.errors.status}
                />
                <TextAreaInput label="Notes" value={form.data.notes} onChange={(value) => form.setData("notes", value)} error={form.errors.notes} />
                <button type="submit" className="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500">
                    Update
                </button>
            </div>
        </form>
    );
}

function CompleteHandoverForm({ routeBase, handoverId }) {
    const form = useForm({
        actual_handover_at: "",
        client_signatory_name: "",
        client_signatory_role: "",
        signoff_notes: "",
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(route(`${routeBase}.complete`, handoverId), { preserveScroll: true });
            }}
            className="grid gap-4 md:grid-cols-2"
        >
            <TextInput label="Actual Handover At" type="datetime-local" value={form.data.actual_handover_at} onChange={(value) => form.setData("actual_handover_at", value)} error={form.errors.actual_handover_at} />
            <TextInput label="Client Signatory Name" value={form.data.client_signatory_name} onChange={(value) => form.setData("client_signatory_name", value)} error={form.errors.client_signatory_name} />
            <TextInput label="Client Signatory Role" value={form.data.client_signatory_role} onChange={(value) => form.setData("client_signatory_role", value)} error={form.errors.client_signatory_role} />
            <TextAreaInput label="Signoff Notes" value={form.data.signoff_notes} onChange={(value) => form.setData("signoff_notes", value)} error={form.errors.signoff_notes} />
            <div className="md:col-span-2">
                <PrimaryButton processing={form.processing} label="Complete Handover" />
            </div>
        </form>
    );
}

function CloseProjectForm({ routeBase, handoverId }) {
    const form = useForm({
        closure_date: "",
        signoff_notes: "",
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(route(`${routeBase}.close`, handoverId), { preserveScroll: true });
            }}
            className="grid gap-4 md:grid-cols-2"
        >
            <TextInput label="Closure Date" type="datetime-local" value={form.data.closure_date} onChange={(value) => form.setData("closure_date", value)} error={form.errors.closure_date} />
            <TextAreaInput label="Closure Notes" value={form.data.signoff_notes} onChange={(value) => form.setData("signoff_notes", value)} error={form.errors.signoff_notes} />
            <div className="md:col-span-2">
                <PrimaryButton processing={form.processing} label="Close Project" />
            </div>
        </form>
    );
}

function ChecklistEditor({ items, onChange, errors }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-semibold text-slate-900 dark:text-white">Checklist Items</p>
                <button
                    type="button"
                    className="rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    onClick={() => onChange([...items, { title: "", category: "", status: "pending", notes: "" }])}
                >
                    Add Item
                </button>
            </div>
            <div className="mt-4 space-y-4">
                {items.map((item, index) => (
                    <div key={index} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Title"
                                value={item.title}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], title: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.title`]}
                            />
                            <TextInput
                                label="Category"
                                value={item.category}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], category: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.category`]}
                            />
                            <SelectInput
                                label="Status"
                                value={item.status}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], status: value };
                                    onChange(next);
                                }}
                                options={[
                                    { value: "pending", label: "Pending" },
                                    { value: "completed", label: "Completed" },
                                    { value: "waived", label: "Waived" },
                                ]}
                                error={errors?.[`items.${index}.status`]}
                            />
                            <TextAreaInput
                                label="Notes"
                                value={item.notes}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], notes: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.notes`]}
                            />
                        </div>
                        <div className="mt-3 flex justify-end">
                            <button
                                type="button"
                                className="rounded-xl bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-500"
                                onClick={() => {
                                    const next = items.filter((_, i) => i !== index);
                                    onChange(next.length ? next : [{ title: "", category: "", status: "pending", notes: "" }]);
                                }}
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
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
