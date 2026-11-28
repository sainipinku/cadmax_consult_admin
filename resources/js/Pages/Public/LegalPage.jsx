import { Head, Link } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { ThemeProvider, useTheme } from "@/Contexts/ThemeContext";
import HomepageLayout from "@/Layouts/HomepageLayout";
import Footer from "@/Components/Homepage/Footer";
import {
    ArrowLeft,
    CalendarClock,
    FileText,
    ShieldCheck,
    UserCheck,
} from "lucide-react";

function formatLastUpdated(value) {
    if (!value) {
        return null;
    }

    const parsed = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return parsed.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

function LegalPageContent({ page }) {
    const { isDark } = useTheme();
    const iframeRef = useRef(null);
    const resizeObserverRef = useRef(null);
    const [frameHeight, setFrameHeight] = useState(520);

    const pageKey = page?.key || "privacy_policy";
    const pageTitle =
        page?.title ||
        (pageKey === "terms_and_conditions"
            ? "Terms & Conditions"
            : "Privacy Policy");
    const documentHtml = page?.document_html || page?.content_html || "";
    const formattedDate = formatLastUpdated(page?.last_updated);
    const isPrivacy = pageKey === "privacy_policy";

    const syncIframeHeight = () => {
        const iframe = iframeRef.current;
        if (!iframe) {
            return;
        }

        try {
            const doc = iframe.contentDocument;
            if (!doc) {
                return;
            }

            const bodyHeight = doc.body?.scrollHeight || 0;
            const rootHeight = doc.documentElement?.scrollHeight || 0;
            const measured = Math.max(bodyHeight, rootHeight, 420);
            setFrameHeight(measured + 16);
        } catch {
            setFrameHeight(680);
        }
    };

    const handleIframeLoad = () => {
        syncIframeHeight();

        if (resizeObserverRef.current) {
            resizeObserverRef.current.disconnect();
            resizeObserverRef.current = null;
        }

        const doc = iframeRef.current?.contentDocument;
        if (doc?.body && typeof ResizeObserver !== "undefined") {
            const observer = new ResizeObserver(() => syncIframeHeight());
            observer.observe(doc.body);
            resizeObserverRef.current = observer;
        }
    };

    useEffect(() => {
        setFrameHeight(520);
        const onWindowResize = () => syncIframeHeight();
        window.addEventListener("resize", onWindowResize);

        return () => {
            window.removeEventListener("resize", onWindowResize);
            if (resizeObserverRef.current) {
                resizeObserverRef.current.disconnect();
                resizeObserverRef.current = null;
            }
        };
    }, [pageKey, documentHtml]);

    const HeroIcon = isPrivacy ? ShieldCheck : FileText;

    return (
        <>
            <Head title={pageTitle} />

            <HomepageLayout>
                {/* Hero Banner */}
                <section className="relative overflow-hidden bg-gradient-to-br from-[#0A0A2E] via-[#03011C] to-[#1a0b3e] pt-32 pb-14">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(99,102,241,0.22),transparent_65%)]" />
                    <div className="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <Link
                            href={route("homepage")}
                            className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-indigo-300 transition hover:text-white"
                        >
                            <ArrowLeft size={14} />
                            Back to Home
                        </Link>

                        <div className="mt-5 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                            <div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3.5 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-300">
                                    <HeroIcon size={14} />
                                    Legal &amp; Compliance
                                </div>

                                <h1 className="mt-4 text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                                    {pageTitle}
                                </h1>

                                <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-300">
                                    {formattedDate && (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5">
                                            <CalendarClock size={14} className="text-indigo-400" />
                                            Last updated: {formattedDate}
                                        </span>
                                    )}
                                    {page?.updated_by && (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5">
                                            <UserCheck size={14} className="text-violet-400" />
                                            Updated by: {page.updated_by}
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Legal Page Switcher */}
                            <div className="inline-flex rounded-2xl border border-white/15 bg-white/5 p-1.5 backdrop-blur-sm self-start">
                                <Link
                                    href={route("privacy.policy")}
                                    className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition ${
                                        isPrivacy
                                            ? "bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-600/25"
                                            : "text-slate-300 hover:bg-white/10 hover:text-white"
                                    }`}
                                >
                                    <ShieldCheck size={14} />
                                    Privacy Policy
                                </Link>
                                <Link
                                    href={route("terms.conditions")}
                                    className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition ${
                                        !isPrivacy
                                            ? "bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-600/25"
                                            : "text-slate-300 hover:bg-white/10 hover:text-white"
                                    }`}
                                >
                                    <FileText size={14} />
                                    Terms &amp; Conditions
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Dynamic Database Content Body */}
                <section
                    className={`py-12 transition-colors ${
                        isDark ? "bg-[#03011C]" : "bg-slate-50"
                    }`}
                >
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <div
                            className={`overflow-hidden rounded-3xl border shadow-2xl ${
                                isDark
                                    ? "border-white/10 bg-white shadow-indigo-950/30"
                                    : "border-slate-200 bg-white shadow-slate-200/70"
                            }`}
                        >
                            {documentHtml ? (
                                <iframe
                                    key={pageKey}
                                    ref={iframeRef}
                                    title={pageTitle}
                                    srcDoc={documentHtml}
                                    sandbox="allow-same-origin"
                                    onLoad={handleIframeLoad}
                                    style={{ height: `${frameHeight}px` }}
                                    className="block w-full border-0 bg-white"
                                />
                            ) : (
                                <div className="p-8 text-center text-slate-600">
                                    No content available for {pageTitle}.
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <Footer />
            </HomepageLayout>
        </>
    );
}

export default function LegalPage({ page }) {
    return (
        <ThemeProvider>
            <LegalPageContent page={page} />
        </ThemeProvider>
    );
}
