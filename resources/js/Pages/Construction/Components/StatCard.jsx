export default function StatCard({ label, value, hint }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{label}</p>
            <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{value}</p>
            {hint ? (
                <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            ) : null}
        </div>
    );
}
