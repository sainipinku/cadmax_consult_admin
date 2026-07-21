import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import { useForm } from "@inertiajs/react";

export default function ClientsIndex({ clients, companies }) {
    const form = useForm({
        company_id: companies[0]?.id || "",
        client_type: "individual",
        name: "",
        contact_person: "",
        email: "",
        phone: "",
        alternate_phone: "",
        gst_number: "",
        billing_address: "",
        site_address: "",
        notes: "",
        status: "active",
    });

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
                                onSuccess: () => form.reset("name", "contact_person", "email", "phone", "alternate_phone", "gst_number", "billing_address", "site_address", "notes"),
                            });
                        }}
                        className="space-y-4"
                    >
                        <SelectField form={form} name="company_id" label="Company" options={companies.map((company) => ({ value: company.id, label: company.name }))} />
                        <SelectField form={form} name="client_type" label="Client Type" options={[
                            { value: "individual", label: "Individual" },
                            { value: "company", label: "Company" },
                            { value: "government", label: "Government" },
                        ]} />
                        <InputField form={form} name="name" label="Client Name" />
                        <InputField form={form} name="contact_person" label="Contact Person" />
                        <InputField form={form} name="email" label="Email" />
                        <InputField form={form} name="phone" label="Phone" />
                        <InputField form={form} name="alternate_phone" label="Alternate Phone" />
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
                                        <th className="pb-3">Contact</th>
                                        <th className="pb-3">Status</th>
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
                                                <div>{client.contact_person || "-"}</div>
                                                <div className="text-xs text-slate-500">{client.phone || "-"}</div>
                                            </td>
                                            <td className="py-3"><StatusBadge value={client.status} /></td>
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
        </ConstructionShell>
    );
}

function InputField({ form, name, label }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
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
