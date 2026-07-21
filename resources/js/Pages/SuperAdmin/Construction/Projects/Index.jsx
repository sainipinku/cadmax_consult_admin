import { Link, useForm } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function ProjectsIndex({ projects, companies, clients }) {
    const form = useForm({
        company_id: companies[0]?.id || "",
        client_id: clients[0]?.id || "",
        name: "",
        category: "",
        description: "",
        project_address: "",
        latitude: "",
        longitude: "",
        start_date: "",
        expected_end_date: "",
        priority: "medium",
    });

    const filteredClients = clients.filter((client) => String(client.company_id) === String(form.data.company_id));

    const stats = {
        total: projects.length,
        budgetPending: projects.filter((project) => project.current_stage === "budget_pending").length,
        teamAssigned: projects.filter((project) => project.current_stage === "team_assigned").length,
        surveyInFlow: projects.filter((project) => ["survey_planned", "survey_in_progress", "drafting_in_progress", "drawing_approval_pending"].includes(project.current_stage)).length,
        ready: projects.filter((project) => project.current_stage === "ready_for_construction").length,
    };

    return (
        <ConstructionShell title="Projects" description="Every department works around project records. Phase 1 starts here and Phase 2 continues from here." variant="super">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Projects" value={stats.total} />
                <StatCard label="Budget Pending" value={stats.budgetPending} />
                <StatCard label="Team Assigned" value={stats.teamAssigned} />
                <StatCard label="Survey / Drafting Flow" value={stats.surveyInFlow} />
                <StatCard label="Ready for Construction" value={stats.ready} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[440px,1fr]">
                <SectionCard title="Create Project" description="Register the central project record and set the initial Phase 1 metadata.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route("super.construction.projects.store"), {
                                preserveScroll: true,
                                onSuccess: () => form.reset("name", "category", "description", "project_address", "latitude", "longitude", "start_date", "expected_end_date"),
                            });
                        }}
                        className="space-y-4"
                    >
                        <SelectField form={form} name="company_id" label="Company" options={companies.map((company) => ({ value: company.id, label: company.name }))} />
                        <SelectField form={form} name="client_id" label="Client" options={filteredClients.map((client) => ({ value: client.id, label: client.name }))} />
                        <InputField form={form} name="name" label="Project Name" />
                        <InputField form={form} name="category" label="Category" />
                        <TextAreaField form={form} name="description" label="Description" rows={4} />
                        <TextAreaField form={form} name="project_address" label="Project Address" rows={3} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <InputField form={form} name="latitude" label="Latitude" />
                            <InputField form={form} name="longitude" label="Longitude" />
                            <InputField form={form} name="start_date" label="Start Date" type="date" />
                            <InputField form={form} name="expected_end_date" label="Expected End Date" type="date" />
                        </div>
                        <SelectField form={form} name="priority" label="Priority" options={[
                            { value: "low", label: "Low" },
                            { value: "medium", label: "Medium" },
                            { value: "high", label: "High" },
                            { value: "critical", label: "Critical" },
                        ]} />
                        <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {form.processing ? "Saving..." : "Create Project"}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Project Register" description="Track where each project sits in the Phase 1 and Phase 2 flow.">
                    {projects.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-slate-500">
                                    <tr>
                                        <th className="pb-3">Project</th>
                                        <th className="pb-3">Company</th>
                                        <th className="pb-3">Client</th>
                                        <th className="pb-3">Stage</th>
                                        <th className="pb-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {projects.map((project) => (
                                        <tr key={project.id}>
                                            <td className="py-3">
                                                <div className="font-medium text-slate-900 dark:text-white">{project.name}</div>
                                                <div className="text-xs text-slate-500">{project.project_code}</div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">{project.company?.name || "-"}</td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">{project.client?.name || "-"}</td>
                                            <td className="py-3"><StatusBadge value={project.current_stage} /></td>
                                            <td className="py-3">
                                                <Link href={route("super.construction.projects.show", project.id)} className="text-sm font-medium text-indigo-600">
                                                    Open workflow
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState title="No projects yet." description="Create the first project to start the ERP lifecycle." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function InputField({ form, name, label, type = "text" }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input type={type} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
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
