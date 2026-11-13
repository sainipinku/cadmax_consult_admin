import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import Modal from "@/Components/Modal";
import DeleteActionButton from "@/Components/DeleteActionButton";
import { useForm, router } from "@inertiajs/react";
import { useState, useEffect } from "react";

const emptyClientForm = {
    company_id: "",
    client_type: "individual",
    name: "",
    email: "",
    phone: "",
    alternate_phone: "",
    gst_number: "",
    billing_address: "",
    site_address: "",
    notes: "",
    status: "active",
};

export default function ClientsIndex({ clients = [], companies = [], is_project_owner = false, user_company_id = null }) {
    const isProjectOwner = Boolean(is_project_owner);
    const queryCompanyId = typeof window !== "undefined"
        ? new URLSearchParams(window.location.search).get("company_id")
        : null;
    const effectiveCompanyId = user_company_id ? String(user_company_id) : null;
    const defaultCompanyId = queryCompanyId && companies.some((c) => String(c.id) === String(queryCompanyId))
        ? String(queryCompanyId)
        : ((!isProjectOwner && effectiveCompanyId)
            ? effectiveCompanyId
            : (companies[0]?.id ? String(companies[0].id) : ""));

    const [editingClient, setEditingClient] = useState(null);

    const form = useForm({
        company_id: defaultCompanyId,
        client_type: "individual",
        name: "",
        email: "",
        phone: "",
        alternate_phone: "",
        gst_number: "",
        billing_address: "",
        site_address: "",
        notes: "",
        status: "active",
    });

    const editForm = useForm(emptyClientForm);

    useEffect(() => {
        if (queryCompanyId && companies.some((c) => String(c.id) === String(queryCompanyId))) {
            form.setData("company_id", String(queryCompanyId));
        } else if (!isProjectOwner && effectiveCompanyId && String(form.data.company_id) !== String(effectiveCompanyId)) {
            form.setData("company_id", String(effectiveCompanyId));
        }
    }, [isProjectOwner, effectiveCompanyId, queryCompanyId]);

    const openEditModal = (client) => {
        editForm.clearErrors();
        editForm.setData({
            company_id: client.company_id ? String(client.company_id) : defaultCompanyId,
            client_type: client.client_type || "individual",
            name: client.name || "",
            email: client.email || "",
            phone: client.phone || "",
            alternate_phone: client.alternate_phone || "",
            gst_number: client.gst_number || "",
            billing_address: client.billing_address || "",
            site_address: client.site_address || "",
            notes: client.notes || "",
            status: client.status || "active",
        });
        setEditingClient(client);
    };

    const closeEditModal = () => {
        editForm.clearErrors();
        editForm.reset();
        setEditingClient(null);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route("super.construction.clients.update", editingClient.id), {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
    };

    const handleToggleStatus = (client) => {
        const nextStatus = client.status === "active" ? "inactive" : "active";
        const actionLabel = client.status === "active" ? "deactivate" : "activate";
        if (confirm(`Are you sure you want to ${actionLabel} client "${client.name}"?`)) {
            router.post(
                route("super.construction.clients.status", client.id),
                { status: nextStatus },
                { preserveScroll: true }
            );
        }
    };

    const handleDeleteClient = (client) => {
        if (client.projects_count && client.projects_count > 0) {
            alert(`Cannot delete client "${client.name}" because it is linked to ${client.projects_count} project(s). Please remove or reassign associated projects first.`);
            return;
        }
        if (confirm(`Are you sure you want to delete client "${client.name}"? This action cannot be undone.`)) {
            router.delete(route("super.construction.clients.destroy", client.id), {
                preserveScroll: true,
                onError: (errs) => {
                    if (typeof window !== "undefined" && typeof window.alert === "function") {
                        window.alert(Object.values(errs).find(Boolean) || "Failed to delete client.");
                    }
                },
            });
        }
    };

    return (
        <ConstructionShell title="Clients" description="Register every client under a company before project creation begins." variant="super">
            <div className="grid gap-4 md:grid-cols-4">
                <StatCard label="Clients" value={clients.length} />
                <StatCard label="Active" value={clients.filter((client) => client.status === "active").length} />
                <StatCard label="Company Clients" value={clients.filter((client) => client.client_type === "company").length} />
                <StatCard label="Government Clients" value={clients.filter((client) => client.client_type === "government").length} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[440px,1fr]">
                <SectionCard title="Add Client" description="Create client records with company mapping, contact information, and billing/site details.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route("super.construction.clients.store"), {
                                preserveScroll: true,
                                onSuccess: () => form.reset("name", "email", "phone", "alternate_phone", "gst_number", "billing_address", "site_address", "notes"),
                            });
                        }}
                        className="space-y-4"
                    >
                        {companies.length > 0 && (
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Company {!isProjectOwner && effectiveCompanyId && <span className="text-xs text-slate-400 font-normal">(Locked)</span>}
                                </label>
                                <select
                                    value={form.data.company_id}
                                    disabled={!isProjectOwner && Boolean(effectiveCompanyId)}
                                    onChange={(e) => form.setData("company_id", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-800"
                                >
                                    {companies.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select>
                                {form.errors.company_id ? <p className="mt-1 text-xs text-rose-600">{form.errors.company_id}</p> : null}
                            </div>
                        )}
                        <SelectField form={form} name="client_type" label="Client Type" options={[
                            { value: "individual", label: "Individual" },
                            { value: "company", label: "Company" },
                            { value: "government", label: "Government" },
                        ]} />
                        <InputField form={form} name="name" label="Client Name" />
                        <InputField form={form} name="email" label="Email" type="email" />
                        <InputField
                            form={form}
                            name="phone"
                            label="Phone (10 digits)"
                            isNumeric={true}
                            maxLength={10}
                            placeholder="Enter 10-digit phone number"
                        />
                        <InputField
                            form={form}
                            name="alternate_phone"
                            label="Alternate Phone (10 digits)"
                            isNumeric={true}
                            maxLength={10}
                            placeholder="Enter 10-digit alternate phone"
                        />
                        <InputField form={form} name="gst_number" label="GST Number" />
                        <TextAreaField form={form} name="billing_address" label="Billing Address" rows={3} />
                        <TextAreaField form={form} name="site_address" label="Site Address" rows={3} />
                        <TextAreaField form={form} name="notes" label="Notes" rows={3} />
                        <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {form.processing ? "Saving..." : "Create Client"}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Client Register" description="All registered clients available for project creation.">
                    {clients.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-slate-500">
                                    <tr>
                                        <th className="pb-3">Client</th>
                                        <th className="pb-3">Company</th>
                                        <th className="pb-3">Phone</th>
                                        <th className="pb-3">Status</th>
                                        <th className="pb-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {clients.map((client) => (
                                        <tr key={client.id}>
                                            <td className="py-3">
                                                <div className="font-medium text-slate-900 dark:text-white">{client.name}</div>
                                                <div className="text-xs text-slate-500">{client.client_code}</div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">{client.company?.name || "-"}</td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">
                                                <div>{client.phone || "-"}</div>
                                            </td>
                                            <td className="py-3"><StatusBadge value={client.status} /></td>
                                            <td className="py-3">
                                                {client.can_manage ? (
                                                    <div className="flex items-center gap-1.5 flex-wrap">
                                                        <button
                                                            type="button"
                                                            onClick={() => openEditModal(client)}
                                                            className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                                        >
                                                            Edit
                                                        </button>

                                                        <button
                                                            type="button"
                                                            onClick={() => handleToggleStatus(client)}
                                                            className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                                                client.status === "active"
                                                                    ? "bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800"
                                                                    : "bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                                                            }`}
                                                            title={client.status === "active" ? "Deactivate Client" : "Activate Client"}
                                                        >
                                                            {client.status === "active" ? "Deactivate" : "Activate"}
                                                        </button>

                                                        <DeleteActionButton
                                                            resourceType="client"
                                                            resourceId={client.id}
                                                            resourceName={client.name}
                                                            onDelete={() => handleDeleteClient(client)}
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400 dark:text-slate-500 italic">
                                                        View Only
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState title="No clients yet." description="Create the first client after company setup is complete." />
                    )}
                </SectionCard>
            </div>

            <Modal show={editingClient !== null} onClose={closeEditModal} maxWidth="2xl">
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Edit Client</h3>
                    <p className="mt-1 text-sm text-slate-500">Update the client master data.</p>
                </div>
                <form onSubmit={submitEdit} className="space-y-4 p-5">
                    {companies.length > 0 && (
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                Company {!isProjectOwner && <span className="text-xs text-slate-400 font-normal">(Locked)</span>}
                            </label>
                            <select
                                value={editForm.data.company_id}
                                disabled={!isProjectOwner}
                                onChange={(e) => editForm.setData("company_id", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-800"
                            >
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                            {editForm.errors.company_id ? <p className="mt-1 text-xs text-rose-600">{editForm.errors.company_id}</p> : null}
                        </div>
                    )}
                    <div>
                        <SelectField form={editForm} name="client_type" label="Client Type" options={[
                            { value: "individual", label: "Individual" },
                            { value: "company", label: "Company" },
                            { value: "government", label: "Government" },
                        ]} />
                    </div>
                    <InputField form={editForm} name="name" label="Client Name" />
                    <div className="grid gap-4 md:grid-cols-2">
                        <InputField form={editForm} name="email" label="Email" type="email" />
                        <InputField
                            form={editForm}
                            name="phone"
                            label="Phone (10 digits)"
                            isNumeric={true}
                            maxLength={10}
                            placeholder="Enter 10-digit phone number"
                        />
                        <InputField
                            form={editForm}
                            name="alternate_phone"
                            label="Alternate Phone (10 digits)"
                            isNumeric={true}
                            maxLength={10}
                            placeholder="Enter 10-digit alternate phone"
                        />
                        <InputField form={editForm} name="gst_number" label="GST Number" />
                    </div>
                    <TextAreaField form={editForm} name="billing_address" label="Billing Address" rows={3} />
                    <TextAreaField form={editForm} name="site_address" label="Site Address" rows={3} />
                    <TextAreaField form={editForm} name="notes" label="Notes" rows={3} />
                    <SelectField form={editForm} name="status" label="Status" options={[
                        { value: "active", label: "Active" },
                        { value: "inactive", label: "Inactive" },
                    ]} />
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            onClick={closeEditModal}
                            disabled={editForm.processing}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-60"
                        >
                            {editForm.processing ? "Updating..." : "Update Client"}
                        </button>
                    </div>
                </form>
            </Modal>
        </ConstructionShell>
    );
}

function InputField({ form, name, label, type = "text", maxLength, isNumeric = false, placeholder = "" }) {
    const handleChange = (e) => {
        let val = e.target.value;
        if (isNumeric) {
            val = val.replace(/\D/g, "");
            if (maxLength) {
                val = val.slice(0, maxLength);
            }
        } else if (maxLength) {
            val = val.slice(0, maxLength);
        }
        form.setData(name, val);
    };

    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input
                type={type}
                maxLength={maxLength}
                inputMode={isNumeric ? "numeric" : undefined}
                placeholder={placeholder}
                value={form.data[name] ?? ""}
                onChange={handleChange}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TextAreaField({ form, name, label, rows = 4 }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <textarea rows={rows} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function SelectField({ form, name, label, options }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <select value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
            </select>
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}