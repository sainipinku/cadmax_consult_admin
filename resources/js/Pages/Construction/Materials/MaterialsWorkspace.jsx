import { router, useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function MaterialsWorkspace({
    variant = "super",
    projects = [],
    vendors = [],
    materials = [],
    purchaseRequests = [],
    purchaseOrders = [],
    receipts = [],
    issues = [],
    stocks = [],
    myIssues = [],
}) {
    const routeBase =
        variant === "super"
            ? "super.construction.materials"
            : variant === "admin"
              ? "admin.construction.materials"
              : "member.construction.materials";
    const documentRouteBase =
        variant === "super"
            ? "super.construction.documents"
            : variant === "admin"
              ? "admin.construction.documents"
              : "member.construction.documents";

    const isSuper = variant === "super";
    const isAdmin = variant === "admin";
    const isMember = variant === "member";

    const firstProjectId = projects[0]?.id ? String(projects[0].id) : "";
    const materialsByProject = useMemo(() => {
        return materials.reduce((carry, material) => {
            const pid = String(material.project_id);
            if (!carry[pid]) carry[pid] = [];
            carry[pid].push(material);
            return carry;
        }, {});
    }, [materials]);

    const vendorsByProject = useMemo(() => {
        return vendors.reduce((carry, vendor) => {
            const pid = String(vendor.project_id);
            if (!carry[pid]) carry[pid] = [];
            carry[pid].push(vendor);
            return carry;
        }, {});
    }, [vendors]);

    const stats = useMemo(() => {
        const stockPositive = stocks.filter((s) => Number(s.on_hand_quantity || 0) > 0).length;
        return {
            projects: projects.length,
            vendors: vendors.length,
            materials: materials.length,
            purchaseRequests: purchaseRequests.length,
            purchaseOrders: purchaseOrders.length,
            receipts: receipts.length,
            issues: issues.length,
            stockRows: stockPositive,
        };
    }, [projects, vendors, materials, purchaseRequests, purchaseOrders, receipts, issues, stocks]);

    const vendorForm = useForm({
        project_id: firstProjectId,
        vendor_code: "",
        name: "",
        phone: "",
        email: "",
        gstin: "",
        address: "",
        status: "active",
    });

    const materialForm = useForm({
        project_id: firstProjectId,
        material_code: "",
        name: "",
        unit: "nos",
        default_rate: "",
        status: "active",
    });

    const [purchaseRequestItems, setPurchaseRequestItems] = useState([
        { material_id: "", quantity: "", unit: "", estimated_rate: "", notes: "" },
    ]);

    const purchaseRequestForm = useForm({
        project_id: firstProjectId,
        request_date: new Date().toISOString().slice(0, 10),
        notes: "",
        status: "submitted",
        items: [],
    });

    const [purchaseOrderItems, setPurchaseOrderItems] = useState([
        { material_id: "", quantity: "", unit: "", rate: "", tax_percent: "0" },
    ]);

    const purchaseOrderForm = useForm({
        project_id: firstProjectId,
        purchase_request_id: "",
        vendor_id: "",
        po_date: new Date().toISOString().slice(0, 10),
        expected_delivery_date: "",
        status: "issued",
        invoice_document: null,
        items: [],
    });

    const [receiptItems, setReceiptItems] = useState([
        { material_id: "", quantity: "", unit: "", rate: "" },
    ]);

    const receiptForm = useForm({
        project_id: firstProjectId,
        purchase_order_id: "",
        received_at: "",
        latitude: "",
        longitude: "",
        gps_accuracy_meters: "",
        receipt_document: null,
        notes: "",
        items: [],
    });

    const [issueItems, setIssueItems] = useState([
        { material_id: "", quantity: "", unit: "", execution_task_id: "", remarks: "" },
    ]);

    const issueForm = useForm({
        project_id: firstProjectId,
        issue_date: new Date().toISOString().slice(0, 10),
        latitude: "",
        longitude: "",
        gps_accuracy_meters: "",
        notes: "",
        items: [],
    });

    const selectedProjectMaterials = materialsByProject[String(purchaseRequestForm.data.project_id)] ?? [];
    const selectedProjectVendors = vendorsByProject[String(purchaseOrderForm.data.project_id)] ?? [];

    const selectedProjectStocks = useMemo(() => {
        const pid = String(firstProjectId);
        return stocks.filter((row) => String(row.project_id) === pid);
    }, [stocks, firstProjectId]);

    const purchaseOrderTotals = useMemo(() => {
        let subtotal = 0;
        let tax = 0;
        purchaseOrderItems.forEach((item) => {
            const quantity = Number(item.quantity || 0);
            const rate = Number(item.rate || 0);
            const percent = Number(item.tax_percent || 0);
            const base = quantity * rate;
            const lineTax = base * (percent / 100);
            subtotal += base;
            tax += lineTax;
        });
        return { subtotal, tax, total: subtotal + tax };
    }, [purchaseOrderItems]);

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
            title="Material Management"
            description="Vendors, purchase requests, purchase orders, receipts, issues, and live stock for the project lifecycle."
            variant={variant}
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Vendors" value={stats.vendors} />
                <StatCard label="Materials" value={stats.materials} />
                <StatCard label="Purchase Requests" value={stats.purchaseRequests} />
                <StatCard label="Purchase Orders" value={stats.purchaseOrders} />
                <StatCard label="Receipts" value={stats.receipts} />
                <StatCard label="Issues" value={stats.issues} />
                <StatCard label="Stock Rows" value={stats.stockRows} />
                <StatCard label="Projects" value={stats.projects} />
            </div>

            {projects.length === 0 ? (
                <EmptyState title="No projects available." description="Create and assign projects first to start material management." />
            ) : null}

            {isSuper ? (
                <div className="grid gap-6 xl:grid-cols-2">
                    <SectionCard title="Vendor Setup" description="Register vendors project-wise for purchase orders.">
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                vendorForm.post(route(`${routeBase}.vendors.store`), { preserveScroll: true });
                            }}
                        >
                            {renderProjectsSelect(vendorForm.data.project_id, (v) => vendorForm.setData("project_id", v), vendorForm.errors.project_id)}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Vendor Code (optional)" value={vendorForm.data.vendor_code} onChange={(v) => vendorForm.setData("vendor_code", v)} error={vendorForm.errors.vendor_code} />
                                <SelectInput
                                    label="Status"
                                    value={vendorForm.data.status}
                                    onChange={(v) => vendorForm.setData("status", v)}
                                    options={[
                                        { value: "active", label: "Active" },
                                        { value: "inactive", label: "Inactive" },
                                    ]}
                                    error={vendorForm.errors.status}
                                />
                            </div>
                            <TextInput label="Vendor Name" value={vendorForm.data.name} onChange={(v) => vendorForm.setData("name", v)} error={vendorForm.errors.name} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Phone" value={vendorForm.data.phone} onChange={(v) => vendorForm.setData("phone", v)} error={vendorForm.errors.phone} />
                                <TextInput label="Email" value={vendorForm.data.email} onChange={(v) => vendorForm.setData("email", v)} error={vendorForm.errors.email} />
                            </div>
                            <TextInput label="GSTIN" value={vendorForm.data.gstin} onChange={(v) => vendorForm.setData("gstin", v)} error={vendorForm.errors.gstin} />
                            <TextAreaInput label="Address" value={vendorForm.data.address} onChange={(v) => vendorForm.setData("address", v)} error={vendorForm.errors.address} rows={3} />
                            <PrimaryButton disabled={vendorForm.processing}>
                                {vendorForm.processing ? "Saving..." : "Save Vendor"}
                            </PrimaryButton>
                        </form>
                    </SectionCard>

                    <SectionCard title="Material Master" description="Register project-specific materials and default units.">
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                materialForm.post(route(`${routeBase}.materials.store`), { preserveScroll: true });
                            }}
                        >
                            {renderProjectsSelect(materialForm.data.project_id, (v) => materialForm.setData("project_id", v), materialForm.errors.project_id)}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Material Code (optional)" value={materialForm.data.material_code} onChange={(v) => materialForm.setData("material_code", v)} error={materialForm.errors.material_code} />
                                <SelectInput
                                    label="Status"
                                    value={materialForm.data.status}
                                    onChange={(v) => materialForm.setData("status", v)}
                                    options={[
                                        { value: "active", label: "Active" },
                                        { value: "inactive", label: "Inactive" },
                                    ]}
                                    error={materialForm.errors.status}
                                />
                            </div>
                            <TextInput label="Material Name" value={materialForm.data.name} onChange={(v) => materialForm.setData("name", v)} error={materialForm.errors.name} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Unit (nos/kg/bag)" value={materialForm.data.unit} onChange={(v) => materialForm.setData("unit", v)} error={materialForm.errors.unit} />
                                <TextInput label="Default Rate" type="number" value={materialForm.data.default_rate} onChange={(v) => materialForm.setData("default_rate", v)} error={materialForm.errors.default_rate} />
                            </div>
                            <PrimaryButton disabled={materialForm.processing}>
                                {materialForm.processing ? "Saving..." : "Save Material"}
                            </PrimaryButton>
                        </form>
                    </SectionCard>
                </div>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Purchase Request" description="Create a request for materials with multi-item support.">
                    {!selectedProjectMaterials.length ? (
                        <EmptyState title="No materials for this project." description="Create materials first (Super Admin) or switch project." />
                    ) : (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                purchaseRequestForm.transform(() => ({
                                    ...purchaseRequestForm.data,
                                    items: purchaseRequestItems.filter((i) => i.material_id && i.quantity),
                                })).post(route(`${routeBase}.purchase_requests.store`), {
                                    preserveScroll: true,
                                    onFinish: () => purchaseRequestForm.transform((d) => d),
                                });
                            }}
                        >
                            {renderProjectsSelect(
                                purchaseRequestForm.data.project_id,
                                (v) => {
                                    purchaseRequestForm.setData("project_id", v);
                                    setPurchaseRequestItems([{ material_id: "", quantity: "", unit: "", estimated_rate: "", notes: "" }]);
                                },
                                purchaseRequestForm.errors.project_id
                            )}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="Request Date" type="date" value={purchaseRequestForm.data.request_date} onChange={(v) => purchaseRequestForm.setData("request_date", v)} error={purchaseRequestForm.errors.request_date} />
                                <SelectInput
                                    label="Status"
                                    value={purchaseRequestForm.data.status}
                                    onChange={(v) => purchaseRequestForm.setData("status", v)}
                                    options={[
                                        { value: "draft", label: "Draft" },
                                        { value: "submitted", label: "Submitted" },
                                    ]}
                                    error={purchaseRequestForm.errors.status}
                                />
                            </div>
                            <TextAreaInput label="Notes" value={purchaseRequestForm.data.notes} onChange={(v) => purchaseRequestForm.setData("notes", v)} error={purchaseRequestForm.errors.notes} rows={3} />
                            <LineItemsEditor
                                title="Request Items"
                                items={purchaseRequestItems}
                                materials={selectedProjectMaterials}
                                onChange={setPurchaseRequestItems}
                                fields={[
                                    { key: "quantity", label: "Quantity", type: "number" },
                                    { key: "unit", label: "Unit", type: "text" },
                                    { key: "estimated_rate", label: "Estimated Rate", type: "number" },
                                    { key: "notes", label: "Notes", type: "text" },
                                ]}
                            />
                            <PrimaryButton disabled={purchaseRequestForm.processing}>
                                {purchaseRequestForm.processing ? "Saving..." : "Save Purchase Request"}
                            </PrimaryButton>
                        </form>
                    )}
                </SectionCard>

                <SectionCard title="Purchase Order" description="Create a PO against a vendor and items.">
                    {!selectedProjectVendors.length ? (
                        <EmptyState title="No vendors for this project." description="Add vendors first (Super Admin) or switch project." />
                    ) : (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                purchaseOrderForm.transform(() => ({
                                    ...purchaseOrderForm.data,
                                    items: purchaseOrderItems.filter((i) => i.material_id && i.quantity),
                                })).post(route(`${routeBase}.purchase_orders.store`), {
                                    preserveScroll: true,
                                    forceFormData: true,
                                    onFinish: () => purchaseOrderForm.transform((d) => d),
                                });
                            }}
                        >
                            {renderProjectsSelect(
                                purchaseOrderForm.data.project_id,
                                (v) => {
                                    purchaseOrderForm.setData("project_id", v);
                                    setPurchaseOrderItems([{ material_id: "", quantity: "", unit: "", rate: "", tax_percent: "0" }]);
                                },
                                purchaseOrderForm.errors.project_id
                            )}
                            <SelectInput
                                label="Vendor"
                                value={purchaseOrderForm.data.vendor_id}
                                onChange={(v) => purchaseOrderForm.setData("vendor_id", v)}
                                options={selectedProjectVendors.map((vendor) => ({
                                    value: String(vendor.id),
                                    label: `${vendor.vendor_code} • ${vendor.name}`,
                                }))}
                                error={purchaseOrderForm.errors.vendor_id}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextInput label="PO Date" type="date" value={purchaseOrderForm.data.po_date} onChange={(v) => purchaseOrderForm.setData("po_date", v)} error={purchaseOrderForm.errors.po_date} />
                                <TextInput label="Expected Delivery" type="date" value={purchaseOrderForm.data.expected_delivery_date} onChange={(v) => purchaseOrderForm.setData("expected_delivery_date", v)} error={purchaseOrderForm.errors.expected_delivery_date} />
                            </div>
                            {!isMember ? (
                                <FileInput
                                    label="Invoice Document"
                                    onChange={(file) => purchaseOrderForm.setData("invoice_document", file)}
                                    error={purchaseOrderForm.errors.invoice_document}
                                />
                            ) : null}
                            <LineItemsEditor
                                title="PO Items"
                                items={purchaseOrderItems}
                                materials={materialsByProject[String(purchaseOrderForm.data.project_id)] ?? []}
                                onChange={setPurchaseOrderItems}
                                fields={[
                                    { key: "quantity", label: "Quantity", type: "number" },
                                    { key: "unit", label: "Unit", type: "text" },
                                    { key: "rate", label: "Rate", type: "number" },
                                    { key: "tax_percent", label: "Tax %", type: "number" },
                                ]}
                            />
                            <div className="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                <div className="flex items-center justify-between">
                                    <span>Subtotal</span>
                                    <span>{purchaseOrderTotals.subtotal.toFixed(2)}</span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span>Tax</span>
                                    <span>{purchaseOrderTotals.tax.toFixed(2)}</span>
                                </div>
                                <div className="mt-3 flex items-center justify-between font-semibold text-slate-900 dark:text-white">
                                    <span>Total</span>
                                    <span>{purchaseOrderTotals.total.toFixed(2)}</span>
                                </div>
                            </div>
                            <PrimaryButton disabled={purchaseOrderForm.processing}>
                                {purchaseOrderForm.processing ? "Saving..." : "Save Purchase Order"}
                            </PrimaryButton>
                        </form>
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Material Receipt" description="Receive materials and increase stock (GPS optional).">
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            receiptForm.transform(() => ({
                                ...receiptForm.data,
                                items: receiptItems.filter((i) => i.material_id && i.quantity),
                            })).post(route(`${routeBase}.receipts.store`), {
                                preserveScroll: true,
                                forceFormData: true,
                                onFinish: () => receiptForm.transform((d) => d),
                            });
                        }}
                    >
                        {renderProjectsSelect(
                            receiptForm.data.project_id,
                            (v) => {
                                receiptForm.setData("project_id", v);
                                setReceiptItems([{ material_id: "", quantity: "", unit: "", rate: "" }]);
                            },
                            receiptForm.errors.project_id
                        )}
                        <SelectInput
                            label="Purchase Order (optional)"
                            value={receiptForm.data.purchase_order_id}
                            onChange={(v) => receiptForm.setData("purchase_order_id", v)}
                            options={[
                                { value: "", label: "Not linked to PO" },
                                ...purchaseOrders
                                    .filter((po) => String(po.project_id) === String(receiptForm.data.project_id))
                                    .map((po) => ({
                                        value: String(po.id),
                                        label: `${po.po_code} • ${po.vendor?.name || "Vendor"}`,
                                    })),
                            ]}
                            error={receiptForm.errors.purchase_order_id}
                        />
                        <div className="grid gap-4 sm:grid-cols-3">
                            <TextInput label="Latitude" value={receiptForm.data.latitude} onChange={(v) => receiptForm.setData("latitude", v)} error={receiptForm.errors.latitude} />
                            <TextInput label="Longitude" value={receiptForm.data.longitude} onChange={(v) => receiptForm.setData("longitude", v)} error={receiptForm.errors.longitude} />
                            <TextInput label="GPS Accuracy (m)" value={receiptForm.data.gps_accuracy_meters} onChange={(v) => receiptForm.setData("gps_accuracy_meters", v)} error={receiptForm.errors.gps_accuracy_meters} />
                        </div>
                        {!isMember ? (
                            <FileInput
                                label="Receipt Document"
                                onChange={(file) => receiptForm.setData("receipt_document", file)}
                                error={receiptForm.errors.receipt_document}
                            />
                        ) : null}
                        <TextAreaInput label="Notes" value={receiptForm.data.notes} onChange={(v) => receiptForm.setData("notes", v)} error={receiptForm.errors.notes} rows={3} />
                        <LineItemsEditor
                            title="Receipt Items"
                            items={receiptItems}
                            materials={materialsByProject[String(receiptForm.data.project_id)] ?? []}
                            onChange={setReceiptItems}
                            fields={[
                                { key: "quantity", label: "Quantity", type: "number" },
                                { key: "unit", label: "Unit", type: "text" },
                                { key: "rate", label: "Rate", type: "number" },
                            ]}
                        />
                        <PrimaryButton disabled={receiptForm.processing}>
                            {receiptForm.processing ? "Saving..." : "Save Material Receipt"}
                        </PrimaryButton>
                    </form>
                </SectionCard>

                <SectionCard title="Material Issue" description="Issue materials to site work and reduce stock (GPS optional).">
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const action = isMember ? "issues.store" : "issues.store";
                            issueForm.transform(() => ({
                                ...issueForm.data,
                                items: issueItems.filter((i) => i.material_id && i.quantity),
                            })).post(route(`${routeBase}.${action}`), {
                                preserveScroll: true,
                                onFinish: () => issueForm.transform((d) => d),
                            });
                        }}
                    >
                        {renderProjectsSelect(
                            issueForm.data.project_id,
                            (v) => {
                                issueForm.setData("project_id", v);
                                setIssueItems([{ material_id: "", quantity: "", unit: "", execution_task_id: "", remarks: "" }]);
                            },
                            issueForm.errors.project_id
                        )}
                        <TextInput label="Issue Date" type="date" value={issueForm.data.issue_date} onChange={(v) => issueForm.setData("issue_date", v)} error={issueForm.errors.issue_date} />
                        <div className="grid gap-4 sm:grid-cols-3">
                            <TextInput label="Latitude" value={issueForm.data.latitude} onChange={(v) => issueForm.setData("latitude", v)} error={issueForm.errors.latitude} />
                            <TextInput label="Longitude" value={issueForm.data.longitude} onChange={(v) => issueForm.setData("longitude", v)} error={issueForm.errors.longitude} />
                            <TextInput label="GPS Accuracy (m)" value={issueForm.data.gps_accuracy_meters} onChange={(v) => issueForm.setData("gps_accuracy_meters", v)} error={issueForm.errors.gps_accuracy_meters} />
                        </div>
                        <TextAreaInput label="Notes" value={issueForm.data.notes} onChange={(v) => issueForm.setData("notes", v)} error={issueForm.errors.notes} rows={3} />
                        <LineItemsEditor
                            title="Issue Items"
                            items={issueItems}
                            materials={materialsByProject[String(issueForm.data.project_id)] ?? []}
                            onChange={setIssueItems}
                            fields={[
                                { key: "quantity", label: "Quantity", type: "number" },
                                { key: "unit", label: "Unit", type: "text" },
                                { key: "remarks", label: "Remarks", type: "text" },
                            ]}
                        />
                        <PrimaryButton disabled={issueForm.processing}>
                            {issueForm.processing ? "Saving..." : "Save Material Issue"}
                        </PrimaryButton>
                    </form>
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Stock Snapshot" description="Live on-hand quantity per material and project.">
                    {stocks.length ? (
                        <div className="space-y-3">
                            {stocks.slice(0, 50).map((row) => (
                                <div key={row.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{row.material?.name || "-"}</p>
                                            <p className="text-sm text-slate-500">{row.project?.name || "-"} • {row.material?.unit || "nos"}</p>
                                        </div>
                                        <p className="text-lg font-semibold text-slate-900 dark:text-white">
                                            {Number(row.on_hand_quantity || 0).toFixed(2)}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No stock records yet." description="Stock will appear after receipts are recorded." />
                    )}
                </SectionCard>

                <SectionCard title={isMember ? "My Material Issues" : "Recent Material Issues"} description="Latest issue entries from site.">
                    {(isMember ? myIssues : issues).length ? (
                        <div className="space-y-3">
                            {(isMember ? myIssues : issues).slice(0, 30).map((issue) => (
                                <div key={issue.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{issue.project?.name || "-"}</p>
                                            <p className="text-sm text-slate-500">{issue.issue_code} • {issue.issue_date}</p>
                                        </div>
                                        <StatusBadge value={issue.status} />
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {(issue.items || []).slice(0, 5).map((item) => (
                                            <span key={item.id} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                {item.material?.name || "-"} • {Number(item.quantity || 0).toFixed(2)} {item.unit || item.material?.unit || ""}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No issues yet." description="Issued materials will appear here once recorded." />
                    )}
                </SectionCard>
            </div>

            {isSuper ? (
                <SectionCard title="Purchase Request Review" description="Approve/reject submitted requests (Super Admin).">
                    {purchaseRequests.length ? (
                        <div className="space-y-3">
                            {purchaseRequests.slice(0, 20).map((pr) => (
                                <div key={pr.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{pr.project?.name || "-"}</p>
                                            <p className="text-sm text-slate-500">{pr.request_code} • {pr.request_date}</p>
                                        </div>
                                        <StatusBadge value={pr.status} />
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                                            onClick={() => {
                                                router.post(route(`${routeBase}.purchase_requests.review`, pr.id), {
                                                    status: "approved",
                                                    review_notes: pr.review_notes || "",
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            className="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500"
                                            onClick={() => {
                                                router.post(route(`${routeBase}.purchase_requests.review`, pr.id), {
                                                    status: "rejected",
                                                    review_notes: "Rejected during purchase review.",
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No purchase requests yet." description="Requests will appear here after admin/site teams submit them." />
                    )}
                </SectionCard>
            ) : null}

            {!isMember ? (
                <SectionCard title="Recent Purchase Orders" description="Latest purchase orders with linked invoice documents, if attached.">
                    {purchaseOrders.length ? (
                        <div className="space-y-3">
                            {purchaseOrders.slice(0, 20).map((po) => (
                                <div key={po.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{po.po_code}</p>
                                            <p className="text-sm text-slate-500">{po.project?.name || "-"} • {po.vendor?.name || "Vendor"}</p>
                                        </div>
                                        <StatusBadge value={po.status} />
                                    </div>
                                    <div className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                                        <p>Total: {Number(po.total_amount || 0).toFixed(2)}</p>
                                        {po.invoice_document ? (
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                <span>Invoice: {po.invoice_document.original_name}</span>
                                                <a
                                                    href={route(`${documentRouteBase}.view`, po.invoice_document.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"
                                                >
                                                    View
                                                </a>
                                                <a
                                                    href={route(`${documentRouteBase}.download`, po.invoice_document.id)}
                                                    className="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                                                >
                                                    Download
                                                </a>
                                            </div>
                                        ) : (
                                            <p className="mt-2">Invoice: Not attached</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No purchase orders yet." description="Purchase orders will appear here after they are created." />
                    )}
                </SectionCard>
            ) : null}

            {!isMember ? (
                <SectionCard title="Recent Material Receipts" description="Latest receipts with uploaded receipt documents, if attached.">
                    {receipts.length ? (
                        <div className="space-y-3">
                            {receipts.slice(0, 20).map((receipt) => (
                                <div key={receipt.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900 dark:text-white">{receipt.receipt_code}</p>
                                            <p className="text-sm text-slate-500">{receipt.project?.name || "-"} • {receipt.purchase_order?.po_code || "No PO linked"}</p>
                                        </div>
                                        <StatusBadge value={receipt.status} />
                                    </div>
                                    <div className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                                        <p>Received At: {receipt.received_at || "-"}</p>
                                        {receipt.receipt_document ? (
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                <span>Document: {receipt.receipt_document.original_name}</span>
                                                <a
                                                    href={route(`${documentRouteBase}.view`, receipt.receipt_document.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"
                                                >
                                                    View
                                                </a>
                                                <a
                                                    href={route(`${documentRouteBase}.download`, receipt.receipt_document.id)}
                                                    className="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                                                >
                                                    Download
                                                </a>
                                            </div>
                                        ) : (
                                            <p className="mt-2">Document: Not attached</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No receipts yet." description="Material receipts will appear here after they are recorded." />
                    )}
                </SectionCard>
            ) : null}
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

function LineItemsEditor({ title, items, materials, onChange, fields }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-semibold text-slate-900 dark:text-white">{title}</p>
                <button
                    type="button"
                    className="rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    onClick={() => onChange([...items, { material_id: "", quantity: "", unit: "", rate: "", estimated_rate: "", tax_percent: "0", notes: "", remarks: "" }])}
                >
                    Add Item
                </button>
            </div>
            <div className="mt-4 space-y-4">
                {items.map((item, index) => (
                    <div key={index} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div className="grid gap-4 md:grid-cols-2">
                            <SelectInput
                                label="Material"
                                value={item.material_id}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], material_id: value };
                                    onChange(next);
                                }}
                                options={[
                                    { value: "", label: "Select material" },
                                    ...materials.map((material) => ({
                                        value: String(material.id),
                                        label: `${material.material_code} • ${material.name}`,
                                    })),
                                ]}
                            />
                            {fields.map((field) => (
                                <TextInput
                                    key={field.key}
                                    label={field.label}
                                    type={field.type}
                                    value={item[field.key] ?? ""}
                                    onChange={(value) => {
                                        const next = [...items];
                                        next[index] = { ...next[index], [field.key]: value };
                                        onChange(next);
                                    }}
                                />
                            ))}
                        </div>
                        <div className="mt-3 flex justify-end">
                            <button
                                type="button"
                                className="rounded-xl bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-500"
                                onClick={() => {
                                    const next = items.filter((_, i) => i !== index);
                                    onChange(next.length ? next : [{ material_id: "", quantity: "", unit: "" }]);
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
