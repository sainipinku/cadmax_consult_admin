import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import { useForm } from "@inertiajs/react";

export default function CompaniesIndex({ companies }) {
    const form = useForm({
        name: "",
        legal_name: "",
        email: "",
        phone: "",
        gst_number: "",
        address: "",
        status: "active",
    });

    return (
        <ConstructionShell title="Companies" description="Company master data comes first in Phase 1 and anchors clients, projects, and permissions." variant="super">
            <div className="grid gap-4 md:grid-cols-3">
                <StatCard label="Companies" value={companies.length} />
                <StatCard label="Active" value={companies.filter((company) => company.status === "active").length} />
                <StatCard label="Inactive" value={companies.filter((company) => company.status === "inactive").length} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[420px,1fr]">
                <SectionCard title="Add Company" description="Create the legal and operational company record before downstream setup.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route("super.construction.companies.store"), { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                        className="space-y-4"
                    >
                        <InputField form={form} name="name" label="Company Name" />
                        <InputField form={form} name="legal_name" label="Legal Name" />
                        <InputField form={form} name="email" label="Email" />
                        <InputField form={form} name="phone" label="Phone" />
                        <InputField form={form} name="gst_number" label="GST Number" />
                        <TextAreaField form={form} name="address" label="Address" rows={4} />
                        <SelectField form={form} name="status" label="Status" options={[
                            { value: "active", label: "Active" },
                            { value: "inactive", label: "Inactive" },
                        ]} />
                        <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {form.processing ? "Saving..." : "Create Company"}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Company Register" description="Current companies available for client and project onboarding.">
                    {companies.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-slate-500">
                                    <tr>
                                        <th className="pb-3">Company</th>
                                        <th className="pb-3">Contact</th>
                                        <th className="pb-3">GST</th>
                                        <th className="pb-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {companies.map((company) => (
                                        <tr key={company.id}>
                                            <td className="py-3">
                                                <div className="font-medium text-slate-900 dark:text-white">{company.name}</div>
                                                <div className="text-xs text-slate-500">{company.legal_name || "-"}</div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">
                                                <div>{company.email || "-"}</div>
                                                <div className="text-xs text-slate-500">{company.phone || "-"}</div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">{company.gst_number || "-"}</td>
                                            <td className="py-3"><StatusBadge value={company.status} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState title="No companies yet." description="Create the first company to start Phase 1 setup." />
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
        </div>
    );
}
