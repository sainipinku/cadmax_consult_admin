import { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { ThemeProvider } from '@/Contexts/ThemeContext';
import HomepageLayout from '@/Layouts/HomepageLayout';

export default function Homepage() {
    const countdownTarget = useMemo(
        () => Date.now() + (10 * 24 * 60 * 60 * 1000),
        []
    );
    const [timeLeft, setTimeLeft] = useState(() => getTimeLeft(countdownTarget));

    useEffect(() => {
        const timer = setInterval(() => {
            setTimeLeft(getTimeLeft(countdownTarget));
        }, 1000);

        return () => clearInterval(timer);
    }, [countdownTarget]);

    return (
        <ThemeProvider>
            <Head title="Find Your Dream Career | ATS" />
            <HomepageLayout>
                <section className="min-h-[calc(100vh-5rem)] flex items-center justify-center px-4 py-16">
                    <div className="w-full max-w-4xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl dark:border-slate-800 dark:bg-slate-900">
                        <p className="text-sm font-semibold uppercase tracking-[0.3em] text-blue-500">
                            Coming Soon
                        </p>
                        <h1 className="mt-4 text-4xl font-bold text-slate-900 dark:text-white sm:text-5xl">
                            Our new homepage is on the way
                        </h1>
                        <p className="mx-auto mt-4 max-w-2xl text-base text-slate-600 dark:text-slate-300 sm:text-lg">
                            Launch countdown for the next 10 days.
                        </p>

                        <div className="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            {[
                                { label: 'Days', value: timeLeft.days },
                                { label: 'Hours', value: timeLeft.hours },
                                { label: 'Minutes', value: timeLeft.minutes },
                                { label: 'Seconds', value: timeLeft.seconds },
                            ].map((item) => (
                                <div
                                    key={item.label}
                                    className="rounded-2xl bg-slate-100 px-4 py-6 dark:bg-slate-800"
                                >
                                    <div className="text-3xl font-bold text-slate-900 dark:text-white sm:text-4xl">
                                        {formatTime(item.value)}
                                    </div>
                                    <div className="mt-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                                        {item.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </HomepageLayout>
        </ThemeProvider>
    );
}

function getTimeLeft(targetTime) {
    const difference = Math.max(targetTime - Date.now(), 0);

    return {
        days: Math.floor(difference / (1000 * 60 * 60 * 24)),
        hours: Math.floor((difference / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((difference / (1000 * 60)) % 60),
        seconds: Math.floor((difference / 1000) % 60),
    };
}

function formatTime(value) {
    return String(value).padStart(2, '0');
}
