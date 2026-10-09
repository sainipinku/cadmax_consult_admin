import { Link } from "@inertiajs/react";
import { Head } from "@inertiajs/react";
import { ThemeProvider } from "@/Contexts/ThemeContext";
import HomepageLayout from "@/Layouts/HomepageLayout";
import {
    ArrowRight,
    Building2,
    ClipboardList,
    Gauge,
    Home,
    MapPin,
    Phone,
    Rocket,
    Sparkles,
    Truck,
    Users,
    Wallet,
} from "lucide-react";

const features = [
    {
        icon: Building2,
        title: "Project Lifecycle",
        subtitle: "End-to-end project tracking from planning to handover",
        gradient: "from-indigo-500 to-violet-600",
    },
    {
        icon: ClipboardList,
        title: "Survey & Drafting",
        subtitle: "Digital field surveys, drawing approvals & revisions",
        gradient: "from-sky-500 to-blue-600",
    },
    {
        icon: Users,
        title: "Team Management",
        subtitle: "Role-based access with member & employee control",
        gradient: "from-emerald-500 to-teal-600",
    },
    {
        icon: Truck,
        title: "Fleet Tracking",
        subtitle: "GPS-verified vehicle tracking & live location pings",
        gradient: "from-amber-500 to-orange-600",
    },
    {
        icon: Wallet,
        title: "Billing & Finance",
        subtitle: "Invoices, payments & revenue analytics in one place",
        gradient: "from-rose-500 to-pink-600",
    },
    {
        icon: Gauge,
        title: "Real-Time Dashboard",
        subtitle: "Live KPIs, attendance & project health monitoring",
        gradient: "from-purple-500 to-fuchsia-600",
    },
];

const stats = [
    { value: "7", label: "Lifecycle Stages" },
    { value: "24/7", label: "Field Support" },
    { value: "100%", label: "Digital Workflow" },
    { value: "3×", label: "Faster Delivery" },
];

const workflowSteps = [
    {
        number: "01",
        title: "Plan & Budget",
        subtitle: "Create projects, define budgets and get approvals",
    },
    {
        number: "02",
        title: "Survey & Draft",
        subtitle: "Field survey, drawings and design approval",
    },
    {
        number: "03",
        title: "Execute & Track",
        subtitle: "Task execution, DPR, attendance and materials",
    },
    {
        number: "04",
        title: "Handover & Close",
        subtitle: "Client handover, billing and project closure",
    },
];

export default function Homepage() {
    return (
        <ThemeProvider>
            <Head title="betaxtech ERP | Construction Management Platform" />

            <HomepageLayout>
                {/* ═══════════════ HERO ═══════════════ */}
                <section id="hero" className="relative overflow-hidden">
                    {/* Background layers */}
                    <div className="absolute inset-0 bg-gradient-to-br from-[#0A0A2E] via-[#03011C] to-[#1a0b3e]" />
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(99,102,241,0.25),transparent_60%)]" />
                    <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.03)_1px,transparent_1px)] bg-[size:60px_60px]" />
                    <div className="absolute left-1/4 top-20 h-72 w-72 rounded-full bg-indigo-600/20 blur-[100px]" />
                    <div className="absolute right-1/4 bottom-10 h-72 w-72 rounded-full bg-violet-600/20 blur-[100px]" />

                    <div className="relative mx-auto max-w-7xl px-4 pb-20 pt-32 sm:px-6 lg:px-8 lg:pt-40 lg:pb-28">
                        <div className="mx-auto max-w-4xl text-center">
                            {/* Badge */}
                            <div className="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-300 backdrop-blur-sm">
                                <Sparkles size={14} />
                                Next-Gen Construction ERP
                            </div>

                            {/* Heading */}
                            <h1 className="mt-6 text-4xl font-extrabold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-7xl">
                                Build Smarter with{" "}
                                <span className="bg-gradient-to-r from-indigo-400 via-violet-400 to-purple-400 bg-clip-text text-transparent">
                                    betaxtech
                                </span>
                            </h1>

                            <p className="mx-auto mt-6 max-w-2xl text-base leading-7 text-slate-400 sm:text-lg">
                                The complete ERP suite for construction companies — manage
                                projects, teams, fleet, equipment, billing and everything
                                in between from a single powerful platform.
                            </p>

                            {/* CTA Buttons */}
                            <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                                <Link
                                    href={route("register")}
                                    className="group inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-8 py-4 text-sm font-semibold text-white shadow-2xl shadow-indigo-600/30 transition-all hover:from-indigo-500 hover:to-violet-500 sm:w-auto"
                                >
                                    Get Started Free
                                    <ArrowRight className="transition-transform group-hover:translate-x-1" size={16} />
                                </Link>
                                <Link
                                    href={route("login")}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-8 py-4 text-sm font-semibold text-white backdrop-blur-sm transition-all hover:bg-white/10 sm:w-auto"
                                >
                                    Explore the Platform
                                </Link>
                            </div>

                            {/* Stats */}
                            <div className="mx-auto mt-16 grid max-w-2xl grid-cols-2 gap-4 sm:grid-cols-4">
                                {stats.map((stat) => (
                                    <div key={stat.label} className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                                        <div className="bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-2xl font-extrabold text-transparent">
                                            {stat.value}
                                        </div>
                                        <div className="mt-1 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                                            {stat.label}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                {/* ═══════════════ PLATFORM FEATURES ═══════════════ */}
                <section id="platform" className="relative bg-white py-20 dark:bg-transparent">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mx-auto max-w-2xl text-center">
                            <p className="text-xs font-bold uppercase tracking-[0.3em] text-indigo-600 dark:text-indigo-400">
                                Platform Features
                            </p>
                            <h2 className="mt-3 text-3xl font-bold text-slate-900 dark:text-white sm:text-4xl">
                                Everything you need to run a construction business
                            </h2>
                            <p className="mt-4 text-sm text-slate-500 dark:text-slate-400">
                                Powerful tools designed specifically for the construction
                                industry — from first survey to final handover.
                            </p>
                        </div>

                        <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="group relative overflow-hidden rounded-3xl border border-slate-200 p-6 transition-all duration-300 hover:-translate-y-1 hover:border-indigo-300 hover:shadow-2xl hover:shadow-indigo-100 dark:border-white/10 dark:hover:border-indigo-500/40 dark:hover:shadow-indigo-950"
                                >
                                    <div className={`inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br ${feature.gradient} text-white shadow-lg`}>
                                        <feature.icon size={22} />
                                    </div>
                                    <h3 className="mt-5 text-lg font-bold text-slate-900 dark:text-white">
                                        {feature.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                        {feature.subtitle}
                                    </p>
                                    <div className="mt-4 flex items-center gap-1.5 text-[13px] font-semibold text-indigo-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-indigo-400">
                                        Learn more
                                        <ArrowRight size={14} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ═══════════════ WORKFLOW ═══════════════ */}
                <section id="features" className="relative overflow-hidden bg-[#03011C] py-20">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(139,92,246,0.15),transparent_50%)]" />
                    <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mx-auto max-w-2xl text-center">
                            <p className="text-xs font-bold uppercase tracking-[0.3em] text-violet-400">
                                How It Works
                            </p>
                            <h2 className="mt-3 text-3xl font-bold text-white sm:text-4xl">
                                Manage the full project lifecycle
                            </h2>
                            <p className="mt-4 text-sm text-slate-400">
                                From initial planning to final handover — track every stage
                                of your construction projects in real time.
                            </p>
                        </div>

                        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {workflowSteps.map((step, i) => (
                                <div key={step.number} className="relative">
                                    {i < workflowSteps.length - 1 && (
                                        <div className="absolute left-full top-6 hidden h-px w-full bg-gradient-to-r from-indigo-500/50 to-transparent lg:block" />
                                    )}
                                    <div className="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-sm transition-all duration-300 hover:border-indigo-500/40 hover:bg-white/10">
                                        <div className="bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-4xl font-extrabold text-transparent">
                                            {step.number}
                                        </div>
                                        <h3 className="mt-4 text-lg font-bold text-white">
                                            {step.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 text-slate-400">
                                            {step.subtitle}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ═══════════════ BOTTOM CTA ═══════════════ */}
                <section className="relative overflow-hidden bg-white py-20 dark:bg-transparent">
                    <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                        <div className="relative overflow-hidden rounded-[32px] bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 p-8 text-center shadow-2xl shadow-indigo-500/20 sm:p-12">
                            {/* Decorative */}
                            <div className="absolute -left-16 -top-16 h-48 w-48 rounded-full bg-white/10 blur-2xl" />
                            <div className="absolute -bottom-20 -right-10 h-56 w-56 rounded-full bg-white/10 blur-2xl" />

                            <div className="relative">
                                <div className="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-white backdrop-blur-sm">
                                    <Rocket size={26} />
                                </div>
                                <h2 className="text-3xl font-extrabold text-white sm:text-4xl">
                                    Ready to transform your construction business?
                                </h2>
                                <p className="mx-auto mt-4 max-w-xl text-sm leading-6 text-white/70 sm:text-base">
                                    Join the platform trusted by construction companies to
                                    streamline operations, improve accountability and deliver
                                    projects faster.
                                </p>
                                <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                                    <Link
                                        href={route("register")}
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-white px-8 py-4 text-sm font-bold text-indigo-700 shadow-xl transition-transform hover:scale-105 sm:w-auto"
                                    >
                                        Start Free Now
                                        <ArrowRight size={16} />
                                    </Link>
                                    <Link
                                        href={route("login")}
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-white/30 bg-white/10 px-8 py-4 text-sm font-semibold text-white backdrop-blur-sm transition-all hover:bg-white/20 sm:w-auto"
                                    >
                                        Sign In to Dashboard
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ═══════════════ FOOTER ═══════════════ */}
                <footer className="border-t border-white/10 bg-[#03011C] px-6 py-10 text-white">
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 md:flex-row">
                        <div className="flex items-center gap-3">
                            <img
                                src="/images/cadmax_con_logo.jpeg"
                                alt="betaxtech"
                                className="h-9 w-9 rounded-lg object-contain bg-slate-900/60 p-0.5 ring-1 ring-indigo-500/30"
                            />
                            <div>
                                <div className="text-sm font-bold">betaxtech</div>
                                <div className="text-[10px] uppercase tracking-[0.25em] text-slate-500">
                                    ERP Suite
                                </div>
                            </div>
                        </div>

                        <div className="text-center text-xs text-slate-500">
                            © {new Date().getFullYear()} betaxtech. All rights reserved.
                        </div>

                        <div className="flex items-center gap-4">
                            <a href="#" className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/5 text-slate-400 transition hover:bg-indigo-600 hover:text-white">
                                <Building2 size={16} />
                            </a>
                            <a href="#" className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/5 text-slate-400 transition hover:bg-indigo-600 hover:text-white">
                                <MapPin size={16} />
                            </a>
                            <a href="#" className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/5 text-slate-400 transition hover:bg-indigo-600 hover:text-white">
                                <Phone size={16} />
                            </a>
                        </div>
                    </div>
                </footer>
            </HomepageLayout>
        </ThemeProvider>
    );
}