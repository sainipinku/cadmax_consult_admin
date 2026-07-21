export default function SectionCard({ title, description, actions = null, children, className = "" }) {
    return (
        <section className={`rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950 ${className}`.trim()}>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900 dark:text-white">{title}</h2>
                    {description ? (
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>
                    ) : null}
                </div>
                {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
            </div>
            <div className="mt-5">{children}</div>
        </section>
    );
}
