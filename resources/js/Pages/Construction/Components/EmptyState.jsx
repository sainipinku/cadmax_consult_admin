export default function EmptyState({ title = "No records yet.", description = "Create the first record to move this workflow forward." }) {
    return (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60">
            <p className="text-sm font-semibold text-slate-900 dark:text-white">{title}</p>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>
        </div>
    );
}
