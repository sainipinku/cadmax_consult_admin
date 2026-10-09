import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import {
    ShieldCheck,
    UserCheck,
    Users,
    Compass,
    Truck,
    PenTool,
    PhoneCall,
    ChevronDown,
    LogIn,
} from 'lucide-react';

export default function LoginDropdown({ isDark = true }) {
    const [isOpen, setIsOpen] = useState(false);

    const loginRoles = [
        {
            name: 'Super Admin Login',
            desc: 'System Owner Portal',
            href: route('login'),
            icon: ShieldCheck,
            color: 'from-amber-500 to-orange-600',
        },
        {
            name: 'Admin Login',
            desc: 'Manager Portal',
            href: route('admin.login'),
            icon: UserCheck,
            color: 'from-indigo-500 to-violet-600',
        },
        {
            name: 'Field Member Login',
            desc: 'General Staff Portal',
            href: route('doer.login'),
            icon: Users,
            color: 'from-emerald-500 to-teal-600',
        },
        {
            name: 'Survey Man (Surveyor)',
            desc: 'Survey & Visit Portal',
            href: route('surveyor.login'),
            icon: Compass,
            color: 'from-sky-500 to-blue-600',
        },
        {
            name: 'Driver Portal',
            desc: 'Vehicle & Route Portal',
            href: route('driver.login'),
            icon: Truck,
            color: 'from-orange-500 to-amber-600',
        },
        {
            name: 'Draft Man Portal',
            desc: 'Drawing & CAD Portal',
            href: route('draftman.login'),
            icon: PenTool,
            color: 'from-purple-500 to-pink-600',
        },
        {
            name: 'Calling Team Portal',
            desc: 'Hiring & Candidate Portal',
            href: route('callingteam.login'),
            icon: PhoneCall,
            color: 'from-rose-500 to-red-600',
        },
    ];

    return (
        <div
            className="relative group inline-block"
            onMouseEnter={() => setIsOpen(true)}
            onMouseLeave={() => setIsOpen(false)}
        >
            {/* Trigger Button */}
            <button
                type="button"
                className={`inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl transition-all duration-200 ${
                    isDark
                        ? 'text-slate-200 hover:text-white hover:bg-white/10'
                        : 'text-slate-700 hover:text-indigo-600 hover:bg-slate-100'
                }`}
            >
                <LogIn className="w-4 h-4 text-indigo-400" />
                <span>Sign In</span>
                <ChevronDown className="w-3.5 h-3.5 opacity-70 transition-transform duration-200 group-hover:rotate-180" />
            </button>

            {/* Dropdown Menu */}
            <div
                className={`absolute right-0 top-full pt-2 w-72 transition-all duration-200 z-[9999] ${
                    isOpen
                        ? 'opacity-100 visible translate-y-0'
                        : 'opacity-0 invisible -translate-y-2 pointer-events-none'
                }`}
            >
                <div
                    className={`rounded-2xl p-2.5 shadow-2xl backdrop-blur-xl border font-sans ${
                        isDark
                            ? 'bg-[#060424]/95 border-indigo-500/30 text-white shadow-indigo-950/50'
                            : 'bg-white/95 border-slate-200 text-slate-900 shadow-slate-300/50'
                    }`}
                >
                    <div className="px-3 py-2 border-b border-white/10 mb-1">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-indigo-400">
                            Select Login Portal
                        </p>
                    </div>

                    <div className="space-y-1 max-h-[380px] overflow-y-auto custom-scrollbar">
                        {loginRoles.map((role, idx) => {
                            const IconComponent = role.icon;
                            return (
                                <Link
                                    key={idx}
                                    href={role.href}
                                    className={`flex items-center gap-3 p-2.5 rounded-xl transition-all group/item ${
                                        isDark
                                            ? 'hover:bg-white/10 text-slate-200 hover:text-white'
                                            : 'hover:bg-slate-100 text-slate-700 hover:text-slate-900'
                                    }`}
                                >
                                    <div
                                        className={`w-8 h-8 rounded-lg bg-gradient-to-br ${role.color} flex items-center justify-center text-white shadow-md group-hover/item:scale-110 transition-transform`}
                                    >
                                        <IconComponent className="w-4 h-4" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="text-xs font-bold leading-tight flex items-center justify-between">
                                            <span>{role.name}</span>
                                        </div>
                                        <p className="text-[10px] text-slate-400 truncate mt-0.5">
                                            {role.desc}
                                        </p>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}
