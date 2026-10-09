import React, { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import { LogOut, UserCheck } from 'lucide-react';

export default function EmulationBanner() {
    const pageProps = usePage().props;
    const auth = pageProps?.auth;
    const impersonator = auth?.impersonator || pageProps?.impersonator;
    const isImpersonating = Boolean(auth?.is_impersonating || impersonator);
    const { post, processing } = useForm();
    const [showTooltip, setShowTooltip] = useState(false);

    if (!isImpersonating || !impersonator) {
        return null;
    }

    const handleExitEmulation = (e) => {
        e.preventDefault();
        e.stopPropagation();
        document.body.style.overflow = '';
        document.body.style.pointerEvents = '';
        document.body.removeAttribute('data-scroll-locked');
        post(route('emulate.exit'), {
            onFinish: () => {
                document.body.style.overflow = '';
                document.body.style.pointerEvents = '';
                document.body.removeAttribute('data-scroll-locked');
            },
        });
    };

    const currentUser = auth?.user || pageProps?.user;
    const userName = currentUser?.name ? currentUser.name.split(' ')[0] : 'User';

    const availableRoles = auth?.available_roles || pageProps?.available_roles || [];
    const activeRole = auth?.active_role || pageProps?.active_role;

    const handleRoleChange = (e) => {
        const newRole = e.target.value;
        if (newRole) {
            router.post(route('emulate.switch_role'), { role: newRole });
        }
    };

    return (
        <div className="relative inline-flex items-center gap-2 shrink-0 font-sans z-50">
            {/* Emulating... Pill Badge */}
            <div className="flex items-center gap-2 bg-amber-500/10 dark:bg-amber-500/20 border border-amber-300 dark:border-amber-500/40 px-3.5 py-1 rounded-full shadow-sm backdrop-blur-sm">
                <span className="relative flex h-2.5 w-2.5 shrink-0">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                </span>
                <span className="text-xs font-semibold text-amber-800 dark:text-amber-300 tracking-tight whitespace-nowrap">
                    Emulating... ({userName})
                </span>
            </div>

            {/* Multi-Role Switcher Dropdown if roles exist */}
            {availableRoles.length > 0 && (
                <div className="flex items-center gap-1 bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700/60 rounded-full px-2.5 py-0.5 shadow-sm">
                    <UserCheck className="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                    <select
                        value={activeRole?.slug ?? ''}
                        onChange={handleRoleChange}
                        className="bg-transparent text-xs font-semibold text-slate-800 dark:text-slate-200 border-none focus:ring-0 py-0.5 pr-6 pl-1 cursor-pointer"
                    >
                        {availableRoles.map((r) => (
                            <option key={r.slug || r.id} value={r.slug || r.id} className="bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                Role: {r.name}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {/* Logout/Exit Icon Button with Tooltip */}
            <div 
                className="relative inline-flex items-center"
                onMouseEnter={() => setShowTooltip(true)}
                onMouseLeave={() => setShowTooltip(false)}
            >
                <button
                    type="button"
                    onClick={handleExitEmulation}
                    disabled={processing}
                    title="Logout Emulation"
                    className="flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 hover:bg-red-50 text-slate-700 hover:text-red-600 dark:bg-slate-800 dark:hover:bg-red-950/40 dark:text-slate-200 dark:hover:text-red-400 border border-slate-200 dark:border-slate-700 transition-all shadow-sm active:scale-95 disabled:opacity-50"
                >
                    <LogOut className="w-4 h-4 stroke-[2.2]" />
                </button>

                {/* Dark Blue Tooltip Card pointing UP */}
                {showTooltip && (
                    <div className="absolute right-0 top-full mt-1.5 z-[99999] flex flex-col items-end pointer-events-none animate-in fade-in zoom-in-95 duration-150">
                        {/* Caret Arrow pointing up */}
                        <div className="w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-b-[6px] border-b-[#1e3a8a] mr-2.5"></div>
                        {/* Tooltip box */}
                        <div className="bg-[#1e3a8a] text-white text-[11px] font-semibold px-3 py-1.5 rounded-lg shadow-xl whitespace-nowrap border border-blue-800/50">
                            Logout Emulation
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

