import { useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function BillingWorkspace({ variant = "super", projects = [], invoices = [], payments = [] }) {
    const routeBase = variant === "super" ? "super.construction.billing" : "admin.construction.billing";

    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";

    const stats = useMemo(() => {
        const totalBilled = invoices.reduce((sum, inv) => sum + Number(inv.total_amount || 0), 0);
        const totalReceived = invoices.reduce((sum, inv) => sum + Number(inv.paid_amount || 0), 0);
        const outstanding = invoices.reduce((sum, inv) => sum + Number(inv.balance_amount || 0), 0);
        return {
            projects: projects.length,
            invoices: invoices.length,
            payments: payments.length,
            totalBilled,
            totalReceived,
            outstanding,
        };
    }, [projects, invoices, payments]);

    const invoiceForm = useForm({
        project_id: firstProjectId,
        invoice_code: "",
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: "",
        tax_type: "intra",
        status: "issued",
        notes: "",
        items: [],
    });

    const [invoiceItems, setInvoiceItems] = useState([{ description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }]);

    const paymentForm = useForm({
        project_id: firstProjectId,
        invoice_id: "",
        payment_code: "",
        received_at: "",
        amount: "",
        method: "bank_transfer",
        reference_no: "",
        notes: "",
    });

    const invoicesByProject = useMemo(() => {
        return invoices.reduce((carry, invoice) => {
            const pid = String(invoice.project_id);
            if (!carry[pid]) carry[pid] = [];
            carry[pid].push(invoice);
            return carry;
        }, {});
    }, [invoices]);

    const selectedProjectInvoices = invoicesByProject[String(paymentForm.data.project_id)] ?? [];

    const invoiceTotals = useMemo(() => {
        let subtotal = 0;
        let tax = 0;
        invoiceItems.forEach((item) => {
            const q = Number(item.quantity || 0);
            const r = Number(item.rate || 0);
            const p = Number(item.gst_percent || 0);
            const base = q * r;
            subtotal += base;
            tax += base * (p / 100);
        });
        return { subtotal, tax, total: subtotal + tax };
    }, [invoiceItems]);

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
            title="Accounts & Billing"
            description="GST-aware client invoices and payment receipts at project level."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard label="Projects" value={stats.projects} />
                <StatCard label="Invoices" value={stats.invoices} />
                <StatCard label="Payments" value={stats.payments} />
                <StatCard label="Total Billed" value={`₹${stats.totalBilled.toFixed(2)}`} />
                <StatCard label="Total Received" value={`₹${stats.totalReceived.toFixed(2)}`} />
                <StatCard label="Outstanding" value={`₹${stats.outstanding.toFixed(2)}`} />
            </div>

            {projects.length === 0 ? (
                <EmptyState title="No projects available." description="Create and assign projects first to start billing." />
            ) : null}

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Create Invoice" description="Create a GST invoice with line items and tax type (intra/inter).">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            invoiceForm.transform((data) => ({
                                ...data,
                                items: invoiceItems.map((item) => ({
                                    description: item.description,
                                    quantity: item.quantity,
                                    unit: item.unit,
                                    rate: item.rate,
                                    gst_percent: item.gst_percent,
                                })),
                            }));
                            invoiceForm.post(route(`${routeBase}.invoices.store`), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    invoiceForm.reset("invoice_code", "due_date", "notes");
                                    setInvoiceItems([{ description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }]);
                                },
                            });
                        }}
                        className="space-y-4"
                    >
                        {renderProjectsSelect(
                            invoiceForm.data.project_id,
                            (value) => invoiceForm.setData("project_id", value),
                            invoiceForm.errors.project_id
                        )}
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Invoice Date"
                                type="date"
                                value={invoiceForm.data.invoice_date}
                                onChange={(value) => invoiceForm.setData("invoice_date", value)}
                                error={invoiceForm.errors.invoice_date}
                            />
                            <TextInput
                                label="Due Date"
                                type="date"
                                value={invoiceForm.data.due_date}
                                onChange={(value) => invoiceForm.setData("due_date", value)}
                                error={invoiceForm.errors.due_date}
                            />
                            <SelectInput
                                label="Tax Type"
                                value={invoiceForm.data.tax_type}
                                onChange={(value) => invoiceForm.setData("tax_type", value)}
                                options={[
                                    { value: "intra", label: "Intra-state (CGST+SGST)" },
                                    { value: "inter", label: "Inter-state (IGST)" },
                                ]}
                                error={invoiceForm.errors.tax_type}
                            />
                            <SelectInput
                                label="Status"
                                value={invoiceForm.data.status}
                                onChange={(value) => invoiceForm.setData("status", value)}
                                options={[
                                    { value: "issued", label: "Issued" },
                                    { value: "draft", label: "Draft" },
                                ]}
                                error={invoiceForm.errors.status}
                            />
                        </div>

                        <LineItemsEditor items={invoiceItems} onChange={setInvoiceItems} errors={invoiceForm.errors} />

                        <div className="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <div className="flex items-center justify-between">
                                <span>Subtotal</span>
                                <span className="font-semibold">₹{invoiceTotals.subtotal.toFixed(2)}</span>
                            </div>
                            <div className="mt-2 flex items-center justify-between">
                                <span>GST</span>
                                <span className="font-semibold">₹{invoiceTotals.tax.toFixed(2)}</span>
                            </div>
                            <div className="mt-2 flex items-center justify-between text-base font-semibold text-slate-900 dark:text-white">
                                <span>Total</span>
                                <span>₹{invoiceTotals.total.toFixed(2)}</span>
                            </div>
                        </div>

                        <TextAreaInput
                            label="Notes"
                            value={invoiceForm.data.notes}
                            onChange={(value) => invoiceForm.setData("notes", value)}
                            error={invoiceForm.errors.notes}
                        />

                        <PrimaryButton processing={invoiceForm.processing} label="Save Invoice" />
                    </form>
                </SectionCard>

                <SectionCard title="Record Payment" description="Record incoming payment against an issued invoice (no overpayments allowed).">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            paymentForm.post(route(`${routeBase}.payments.store`), {
                                preserveScroll: true,
                                onSuccess: () => paymentForm.reset("invoice_id", "payment_code", "received_at", "amount", "reference_no", "notes"),
                            });
                        }}
                        className="space-y-4"
                    >
                        {renderProjectsSelect(
                            paymentForm.data.project_id,
                            (value) => paymentForm.setData("project_id", value),
                            paymentForm.errors.project_id
                        )}
                        <SelectInput
                            label="Invoice"
                            value={paymentForm.data.invoice_id}
                            onChange={(value) => paymentForm.setData("invoice_id", value)}
                            options={[
                                { value: "", label: "Select invoice" },
                                ...selectedProjectInvoices.map((invoice) => ({
                                    value: String(invoice.id),
                                    label: `${invoice.invoice_code} • ₹${Number(invoice.balance_amount || 0).toFixed(2)} due`,
                                })),
                            ]}
                            error={paymentForm.errors.invoice_id}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Amount"
                                value={paymentForm.data.amount}
                                onChange={(value) => paymentForm.setData("amount", value)}
                                error={paymentForm.errors.amount}
                            />
                            <SelectInput
                                label="Method"
                                value={paymentForm.data.method}
                                onChange={(value) => paymentForm.setData("method", value)}
                                options={[
                                    { value: "bank_transfer", label: "Bank Transfer" },
                                    { value: "upi", label: "UPI" },
                                    { value: "cash", label: "Cash" },
                                    { value: "cheque", label: "Cheque" },
                                    { value: "card", label: "Card" },
                                    { value: "other", label: "Other" },
                                ]}
                                error={paymentForm.errors.method}
                            />
                            <TextInput
                                label="Received At"
                                type="datetime-local"
                                value={paymentForm.data.received_at}
                                onChange={(value) => paymentForm.setData("received_at", value)}
                                error={paymentForm.errors.received_at}
                            />
                            <TextInput
                                label="Reference No"
                                value={paymentForm.data.reference_no}
                                onChange={(value) => paymentForm.setData("reference_no", value)}
                                error={paymentForm.errors.reference_no}
                            />
                        </div>
                        <TextAreaInput
                            label="Notes"
                            value={paymentForm.data.notes}
                            onChange={(value) => paymentForm.setData("notes", value)}
                            error={paymentForm.errors.notes}
                        />
                        <PrimaryButton processing={paymentForm.processing} label="Record Payment" />
                    </form>
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Recent Invoices" description="Latest invoices created for your accessible projects.">
                    {invoices.length === 0 ? (
                        <EmptyState title="No invoices yet." description="Create an invoice to start tracking billing." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-slate-500 dark:text-slate-300">
                                        <th className="py-2 pr-4">Project</th>
                                        <th className="py-2 pr-4">Invoice</th>
                                        <th className="py-2 pr-4">Date</th>
                                        <th className="py-2 pr-4">Total</th>
                                        <th className="py-2 pr-4">Paid</th>
                                        <th className="py-2 pr-4">Balance</th>
                                        <th className="py-2 pr-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {invoices.map((invoice) => (
                                        <tr key={invoice.id}>
                                            <td className="py-3 pr-4">
                                                <p className="font-medium text-slate-900 dark:text-white">
                                                    {invoice.project?.project_code || `#${invoice.project_id}`}
                                                </p>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <p className="font-medium text-slate-900 dark:text-white">{invoice.invoice_code}</p>
                                                <p className="text-xs text-slate-500 dark:text-slate-300">
                                                    {invoice.tax_type === "inter" ? "IGST" : "CGST+SGST"}
                                                </p>
                                            </td>
                                            <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                {formatDate(invoice.invoice_date)}
                                            </td>
                                            <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                ₹{Number(invoice.total_amount || 0).toFixed(2)}
                                            </td>
                                            <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                ₹{Number(invoice.paid_amount || 0).toFixed(2)}
                                            </td>
                                            <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                ₹{Number(invoice.balance_amount || 0).toFixed(2)}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <StatusBadge value={invoice.status} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </SectionCard>

                <SectionCard title="Recent Payments" description="Latest payment receipts booked against invoices.">
                    {payments.length === 0 ? (
                        <EmptyState title="No payments yet." description="Record a payment to reduce invoice outstanding." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-slate-500 dark:text-slate-300">
                                        <th className="py-2 pr-4">Project</th>
                                        <th className="py-2 pr-4">Payment</th>
                                        <th className="py-2 pr-4">Invoice</th>
                                        <th className="py-2 pr-4">Amount</th>
                                        <th className="py-2 pr-4">Method</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {payments.map((payment) => (
                                        <tr key={payment.id}>
                                            <td className="py-3 pr-4">
                                                <p className="font-medium text-slate-900 dark:text-white">
                                                    {payment.project?.project_code || `#${payment.project_id}`}
                                                </p>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <p className="font-medium text-slate-900 dark:text-white">{payment.payment_code}</p>
                                                {payment.reference_no ? (
                                                    <p className="text-xs text-slate-500 dark:text-slate-300">{payment.reference_no}</p>
                                                ) : null}
                                            </td>
                                            <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                {payment.invoice?.invoice_code || `#${payment.invoice_id}`}
                                            </td>
                                            <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                ₹{Number(payment.amount || 0).toFixed(2)}
                                            </td>
                                            <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                {String(payment.method || "").replaceAll("_", " ")}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function formatDate(dateString) {
    if (!dateString) return null;
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString("en-IN", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
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

function LineItemsEditor({ items, onChange, errors }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-semibold text-slate-900 dark:text-white">Invoice Items</p>
                <button
                    type="button"
                    className="rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    onClick={() => onChange([...items, { description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }])}
                >
                    Add Item
                </button>
            </div>
            <div className="mt-4 space-y-4">
                {items.map((item, index) => (
                    <div key={index} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Description"
                                value={item.description}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], description: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.description`]}
                            />
                            <TextInput
                                label="Quantity"
                                value={item.quantity}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], quantity: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.quantity`]}
                            />
                            <TextInput
                                label="Unit"
                                value={item.unit}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], unit: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.unit`]}
                            />
                            <TextInput
                                label="Rate"
                                value={item.rate}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], rate: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.rate`]}
                            />
                            <TextInput
                                label="GST %"
                                value={item.gst_percent}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], gst_percent: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.gst_percent`]}
                            />
                        </div>
                        <div className="mt-3 flex justify-end">
                            <button
                                type="button"
                                className="rounded-xl bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-500"
                                onClick={() => {
                                    const next = items.filter((_, i) => i !== index);
                                    onChange(next.length ? next : [{ description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }]);
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

