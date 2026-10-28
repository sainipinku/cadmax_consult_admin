import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import Modal from "@/Components/Modal";
import DeleteActionButton from "@/Components/DeleteActionButton";
import { useForm, router } from "@inertiajs/react";
import { useState, useEffect } from "react";
import { MoreVertical } from "lucide-react";

// Keep all form fields in one place so create/edit forms stay in sync.
const emptyClientForm = {
    company_id: "",
    client_type: "individual",
    client_company_name: "",
    name: "",
    client_designation: "",
    email: "",
    phone: "",
    alternate_phone: "",
    gst_number: "",
    billing_address: "",
    site_address: "",
    notes: "",
    status: "active",
};

const clientTypeOptions = [
    { value: "individual", label: "Individual" },
    { value: "company", label: "Company" },
    { value: "government", label: "Government" },
];

// Company and designation details are applicable only to these client types.
const requiresCompanyDetails = (clientType) =>
    ["company", "government"].includes(clientType);



export default function ClientsIndex({ 
    clients = [], 
    companies = [], 
    is_project_owner = false, 
    user_company_id = null 
}) {
    const isProjectOwner = Boolean(is_project_owner);
    const effectiveCompanyId = user_company_id ? String(user_company_id) : null;
    const defaultCompanyId = 
    (!isProjectOwner && effectiveCompanyId)
        ? effectiveCompanyId
        : (companies[0]?.id ? String(companies[0].id) : "");

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

    // Edit form state.
    const editForm = useForm(emptyClientForm);

    // Keep a non-owner user's company selection locked to their assigned company.
    useEffect(() => {
        if (!isProjectOwner && effectiveCompanyId && String(form.data.company_id) !== String(effectiveCompanyId)) {
            form.setData("company_id", String(effectiveCompanyId));
        }
    }, [isProjectOwner, effectiveCompanyId]);

    // Clear company-only fields when switching to Individual, preventing stale values.
    const handleClientTypeChange = (targetForm, value) => {
        targetForm.setData((currentData) => ({
            ...currentData,
            client_type: value,
            ...(requiresCompanyDetails(value)
                ? {}
                : {
                      client_company_name: "",
                      client_designation: "",
                  }),
        }));

        targetForm.clearErrors("client_company_name", "client_designation");
    };

     // Populate the edit form with existing record values.
    const openEditModal = (client) => {
        editForm.clearErrors();
        editForm.setData({
            company_id: client.company_id ? String(client.company_id) : defaultCompanyId,
            client_type: client.client_type || "individual",
            client_company_name: client.client_company_name || "",
            name: client.name || "",
            client_designation: client.client_designation || "",
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

    // Submit changes to the Laravel update endpoint.
    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route("super.construction.clients.update", editingClient.id), {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
    };

    // Reset all editable fields after successful creation, retaining company mapping.
    const submitCreate = (event) => {
        event.preventDefault();
        form.post(route("super.construction.clients.store"), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset(
                    "client_type",
                    "client_company_name",
                    "name",
                    "client_designation",
                    "email",
                    "phone",
                    "alternate_phone",
                    "gst_number",
                    "billing_address",
                    "site_address",
                    "notes",
                );
                form.setData("client_type", "individual");
                form.setData("status", "active");
                form.clearErrors();
            },
        });
    };

    // Toggle active/inactive status after user confirmation.
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

    // Prevent deletion in the UI when projects are linked; server must enforce this too.
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

    const companyClientCount = clients.filter(
        (client) => client.client_type === "company",
    ).length;
    const governmentClientCount = clients.filter(
        (client) => client.client_type === "government",
    ).length;
    const activeClientCount = clients.filter(
        (client) => client.status === "active",
    ).length;

    const [openActionMenu, setOpenActionMenu] = useState(null);

    return (
        <ConstructionShell title="Clients" 
                description="Register every client under a company before project creation begins." 
                variant="super"
        >
            {/* Summary cards show current client totals. */}
            {/* <div className="grid gap-4 md:grid-cols-4">
                <StatCard label="Clients" value={clients.length} />
                <StatCard label="Active" value={clients.filter((client) => client.status === "active").length} />
                <StatCard label="Company Clients" value={clients.filter((client) => client.client_type === "company").length} />
                <StatCard label="Government Clients" value={clients.filter((client) => client.client_type === "government").length} />
            </div> */}

            {/* Summary cards show current client totals. */}
            <div className="grid gap-4 md:grid-cols-4">
                <StatCard label="Clients" value={clients.length} />
                <StatCard label="Active" value={activeClientCount} />
                <StatCard label="Company Clients" value={companyClientCount} />
                <StatCard
                    label="Government Clients"
                    value={governmentClientCount}
                />
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
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                Company 
                                <span class="text-xs text-slate-400 font-normal">(Locked)
                                </span>
                                </label>
                                <select disabled  defaultValue="1" class="w-full rounded-xl border border-slate-300 px-4 py-3 
                                dark:border-slate-700 dark:bg-slate-900 dark:text-white disabled:opacity-60 
                                disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-800">
                                    <option value="1">gentech</option>
                                    </select>
                        </div>
                        
                        <SelectField form={form} name="client_type" required label="Client Type" options={[
                            { value: "individual", label: "Individual" },
                            { value: "company", label: "Company" },
                            { value: "government", label: "Government" },
                        ]} 
                         onChange={(value) =>
                                handleClientTypeChange(form, value)
                            }
                        />

                        {/* Show and require these fields for Company/Government only. */}
                        {requiresCompanyDetails(form.data.client_type) && (
                            <div className="grid gap-4">
                                <InputField
                                    form={form}
                                    name="client_company_name"
                                    label="Client Company Name"
                                    required
                                    placeholder={
                                        form.data.client_type === "government"
                                            ? "Enter department / government organization"
                                            : "Enter registered company name"
                                    }
                                />
                                <InputField
                                    form={form}
                                    name="client_designation"
                                    label="Client Designation"
                                    required
                                    placeholder="Owner, Manager, Director, Officer, etc."
                                />
                            </div>
                        )}

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
                        <InputField form={form} name="gst_number" label="GST Number (Optional)" />
                        <TextAreaField form={form} name="billing_address" label="Permanent  Address" rows={3} />
                        <TextAreaField form={form} name="site_address" label="Temporary  Address" rows={3} />
                        {/* <TextAreaField form={form} name="notes" label="Notes" rows={3} /> */}
                        <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {form.processing ? "Saving..." : "Create Client"}
                        </button>
                    </form>
                </SectionCard>

               {/* client register table  */}

<SectionCard
    title="Client Register"
    description="All registered clients available for project creation."
>
    {clients.length ? (
        <div className="w-full rounded-lg border border-slate-200 dark:border-slate-800">
            <table className="w-full table-fixed text-xs">
                <thead className="bg-slate-100 dark:bg-slate-900/80">
                    <tr className="text-left text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th className="w-[15%] px-2 py-2 font-semibold">
                            Client
                        </th>
                        <th className="w-[18%] px-2 py-2 font-semibold">
                            Phone
                        </th>
                        <th className="w-[18%] px-1 py-2 text-center font-semibold">
                            Status
                        </th>
                        <th className="w-[15%] px-1 py-2 text-center font-semibold">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                    {clients.map((client) => (
                        <tr
                            key={client.id}
                            className="transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/60"
                        >
                            {/* Client details */}
                            <td className="px-2 py-2">
                                <div className="break-words text-sm font-medium text-slate-900 dark:text-white">
                                    {client.name}
                                </div>

                                <div className="mt-0.5 break-words text-[10px] text-slate-500 dark:text-slate-400">
                                    ID: {client.client_code || "-"}
                                </div>
                            </td>

                            {/* Phone */}
                            <td className="break-words px-2 py-2 text-[15px] text-slate-600 dark:text-slate-300">
                                {client.phone || (
                                    <span className="text-slate-400">
                                        Not provided
                                    </span>
                                )}
                            </td>

                            {/* Status */}
                            <td className="px-1 py-2 text-center">
                                <StatusBadge value={client.status} />
                            </td>

                            {/* Actions */}
                            <td className="px-1 py-2">
                                {client.can_manage ? (
                                    <div className="relative flex justify-center">
                                        {/* Three-dot button */}
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setOpenActionMenu(
                                                    openActionMenu === client.id
                                                        ? null
                                                        : client.id
                                                )
                                            }
                                            title="Actions"
                                            aria-label={`Actions for ${client.name}`}
                                            aria-expanded={openActionMenu === client.id}
                                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                        >
                                            <MoreVertical size={15} />
                                        </button>


                            {/* Dropdown menu */}


                        {openActionMenu === client.id && (
                            <>
                                {/* Close menu when clicking outside */}
                                <button
                                    type="button"
                                    aria-label="Close actions menu"
                                    className="fixed inset-0 z-40 cursor-default"
                                    onClick={() => setOpenActionMenu(null)}
                                />

                                <div className="absolute right-0 top-8 z-50 flex w-36 flex-col items-center gap-1 rounded-lg border border-slate-200 bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900">

                                    {/* Edit */}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setOpenActionMenu(null);
                                            openEditModal(client);
                                        }}
                                        className="inline-flex h-7 w-[90%] items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-900/60"
                                    >
                                        Edit
                                    </button>

                                    {/* Activate / Deactivate */}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setOpenActionMenu(null);
                                            handleToggleStatus(client);
                                        }}
                                        className={`inline-flex h-7 w-[90%] items-center justify-center rounded-md border px-1 text-xs font-semibold transition ${
                                            client.status === "active"
                                                ? "border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/60"
                                                : "border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/60"
                                        }`}
                                    >
                                        {client.status === "active" ? "Deactivate" : "Activate"}
                                    </button>

                                    {/* Delete */}
                                    <div className="flex w-[90%] [&>button]:!flex [&>button]:!h-7 [&>button]:!w-full [&>button]:!items-center [&>button]:!justify-center [&>button]:!rounded-md [&>button]:!px-1 [&>button]:!py-0 [&>button]:!text-xs">
                                        <DeleteActionButton
                                            resourceType="client"
                                            resourceId={client.id}
                                            resourceName={client.name}
                                            onDelete={() => handleDeleteClient(client)}
                                        />
                                    </div>
                                </div>
                            </>
                        )}



                                    </div>
                                ) : (
                                    <div className="text-center">
                                        <span className="inline-flex rounded-full bg-slate-100 px-1.5 py-1 text-[9px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                            View only
                                        </span>
                                    </div>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    ) : (
        <EmptyState
            title="No clients yet."
            description="Create the first client after company setup is complete."
        />
    )}
</SectionCard>



            </div>

            {/* Edit modal uses the same conditional fields and validation behavior. */}
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
                        {/* <SelectField form={editForm} name="client_type" label="Client Type" options={[
                            { value: "individual", label: "Individual" },
                            { value: "company", label: "Company" },
                            { value: "government", label: "Government" },
                        ]} /> */}
                        <SelectField
                        form={editForm}
                        name="client_type"
                        label="Client Type"
                        required
                        options={clientTypeOptions}
                        onChange={(value) =>
                            handleClientTypeChange(editForm, value)
                        }
                    />
                    </div>

                                        {requiresCompanyDetails(editForm.data.client_type) && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <InputField
                                form={editForm}
                                name="client_company_name"
                                label="Client Company Name"
                                required
                                placeholder={
                                    editForm.data.client_type === "government"
                                        ? "Enter department / government organization"
                                        : "Enter registered company name"
                                }
                            />
                            <InputField
                                form={editForm}
                                name="client_designation"
                                label="Client Designation"
                                required
                                placeholder="Owner, Manager, Director, Officer, etc."
                            />
                        </div>
                    )}

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
                    <TextAreaField form={editForm} name="billing_address" label="Permanent Address" rows={3} />
                    <TextAreaField form={editForm} name="site_address" label="Temporary Address" rows={3} />
                    {/* <TextAreaField form={editForm} name="notes" label="Notes" rows={3} /> */}
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


// Reusable text input: supports required validation and numeric-only phone fields.
function InputField({ form, name, label, type = "text", maxLength, isNumeric = false, placeholder = "",  required = false, }) {
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
            <label htmlFor={name} className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                  {label}
             {required && (
                    <span className="ml-1 text-rose-600">*</span>
                )}
            </label>
            <input
                id={name}
                type={type}
                maxLength={maxLength}
                inputMode={isNumeric ? "numeric" : undefined}
                placeholder={placeholder}
                value={form.data[name] ?? ""}
                onChange={handleChange}
                 aria-invalid={Boolean(form.errors[name])}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

// Reusable textarea with controlled state and inline server-side validation errors.
function TextAreaField({ form, name, label, rows = 4 }) {
    return (
        <div>
            <label  htmlFor={name} className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <textarea  id={name} rows={rows} value={form.data[name] ?? ""}  aria-invalid={Boolean(form.errors[name])} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

// Reusable select supports optional custom change behavior for conditional fields.
function SelectField({ form, name, label, options,onChange,required = false, }) {
    return (
        <div>
            <label  htmlFor={name} className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                {label}
                {required && (
                    <span className="ml-1 text-rose-600">*</span>
                )}
            </label>
            {/* <select value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} 
                    className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 
                    dark:bg-slate-900 dark:text-white"
            >
                {options.map((option) => <option key={option.value} value={option.value}>{option.label}
                </option>)}
            </select> */}
            <select
                id={name}
                name={name}
                required={required}
                value={form.data[name] ?? ""}
                onChange={(e) => {
                    const value = e.target.value;

                    if (onChange) {
                        onChange(value);
                    } else {
                        form.setData(name, value);
                    }
                }}
                aria-invalid={Boolean(form.errors[name])}
                className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                {options.map((option) => (
                    <option
                        key={option.value}
                        value={option.value}
                    >
                        {option.label}
                    </option>
                ))}
            </select>
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}