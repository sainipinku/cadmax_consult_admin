import { Link, usePage } from '@inertiajs/react';
import { useTheme } from '@/Contexts/ThemeContext';
import { ArrowRight, Building2, Home, LayoutDashboard, Menu, Moon, ShieldCheck, Sun, X } from 'lucide-react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';
import LoginDropdown from '@/Components/LoginDropdown';

export default function HomepageLayout({ children }) {
    const { toggleTheme, isDark } = useTheme();
    const [isScrolled, setIsScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const { auth } = usePage().props;
    const isAuthenticated = !!auth?.user;

    useEffect(() => {
        const onScroll = () => setIsScrolled(window.scrollY > 20);
        window.addEventListener('scroll', onScroll);
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const navLinks = [
        { name: 'Home', href: route('homepage'), icon: Home },
        { name: 'Platform', href: '#platform', icon: Building2 },
        { name: 'Features', href: '#features', icon: ShieldCheck },
    ];

    const dashboardRouteName = isAuthenticated
        ? ({ superadmin: 'super.dashboard', admin: 'admin.dashboard', member: 'member.dashboard' })[auth?.guard] ?? 'home'
        : null;

    if (dashboardRouteName) {
        navLinks.push({ name: 'Dashboard', href: route(dashboardRouteName), icon: LayoutDashboard });
    }

    return (
        <div className={`min-h-screen transition-colors duration-300 ${isDark ? 'bg-[#03011C]' : 'bg-white'}`}>
            {/* Navbar */}
            <nav className={`fixed left-0 right-0 top-0 z-50 transition-all duration-300 ${
                isScrolled
                    ? isDark
                        ? 'border-b border-white/10 bg-[#03011C]/90 backdrop-blur-xl'
                        : 'border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur-xl'
                    : isDark
                        ? 'bg-transparent'
                        : 'border-b border-slate-200/60 bg-white/85 backdrop-blur-sm'
            }`}>
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-20 items-center justify-between">
                        {/* Logo */}
                        <Link href={route('homepage')} className="flex items-center gap-3">
                            <img
                                src="/images/cadmax_con_logo.jpeg"
                                alt="betaxtech Logo"
                                className="h-11 w-11 rounded-xl object-contain bg-slate-900/60 p-0.5 shadow-lg shadow-indigo-500/30 ring-2 ring-indigo-500/20"
                            />
                            <div className="leading-tight">
                                <div className={`text-xl font-extrabold tracking-tight ${isDark ? 'text-white' : 'text-slate-900'}`}>
                                    betaxtech
                                </div>
                                <div className="bg-gradient-to-r from-indigo-500 to-violet-500 bg-clip-text text-[9px] font-bold uppercase tracking-[0.3em] text-transparent">
                                    ERP Suite
                                </div>
                            </div>
                        </Link>

                        {/* Desktop Nav */}
                        <div className="hidden items-center gap-1 lg:flex">
                            {navLinks.map((link) => (
                                <a
                                    key={link.name}
                                    href={link.href}
                                    className={`relative px-4 py-2 text-sm font-medium transition-colors ${
                                        isDark
                                            ? 'text-slate-300 hover:text-white'
                                            : 'text-slate-700 hover:text-indigo-600'
                                    }`}
                                >
                                    {link.name}
                                </a>
                            ))}
                        </div>

                        {/* Right buttons */}
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={toggleTheme}
                                className={`flex h-10 w-10 items-center justify-center rounded-full transition-all duration-300 ${
                                    isDark ? 'bg-white/10 text-yellow-300 hover:bg-white/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }`}
                                aria-label="Toggle theme"
                            >
                                {isDark ? <Sun size={18} /> : <Moon size={18} />}
                            </button>

                            {dashboardRouteName ? (
                                <Link
                                    href={route(dashboardRouteName)}
                                    className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition-all hover:from-indigo-500 hover:to-violet-500"
                                >
                                    <LayoutDashboard size={16} />
                                    Dashboard
                                </Link>
                            ) : (
                                <div className="flex items-center gap-3">
                                    <LoginDropdown isDark={isDark} />
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition-all hover:from-indigo-500 hover:to-violet-500"
                                    >
                                        Get Started
                                        <ArrowRight size={15} />
                                    </Link>
                                </div>
                            )}

                            {/* Mobile toggle */}
                            <button
                                type="button"
                                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                className={`flex h-10 w-10 items-center justify-center rounded-xl lg:hidden ${
                                    isDark ? 'bg-white/10 text-white' : 'bg-slate-100 text-slate-700'
                                }`}
                                aria-label="Toggle menu"
                            >
                                {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile Menu */}
                <AnimatePresence>
                    {mobileMenuOpen && (
                        <motion.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            transition={{ duration: 0.3 }}
                            className={`border-t lg:hidden ${
                                isDark ? 'border-white/10 bg-[#03011C]/95 backdrop-blur-xl' : 'border-slate-200 bg-white/95 backdrop-blur-xl'
                            }`}
                        >
                            <div className="space-y-2 px-4 py-4">
                                {navLinks.map((link) => (
                                    <Link
                                        key={link.name}
                                        href={link.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all ${
                                            isDark ? 'text-slate-300 hover:bg-white/10 hover:text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                        }`}
                                    >
                                        <link.icon className="h-5 w-5" />
                                        {link.name}
                                    </Link>
                                ))}
                                <div className={`space-y-2 border-t pt-4 ${isDark ? 'border-white/10' : 'border-slate-200'}`}>
                                    {dashboardRouteName ? (
                                        <Link
                                            href={route(dashboardRouteName)}
                                            onClick={() => setMobileMenuOpen(false)}
                                            className="block w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3 text-center text-sm font-semibold text-white"
                                        >
                                            Dashboard
                                        </Link>
                                    ) : (
                                        <>
                                            <Link
                                                href={route('login')}
                                                onClick={() => setMobileMenuOpen(false)}
                                                className={`block w-full rounded-xl px-4 py-3 text-center text-sm font-medium ${
                                                    isDark ? 'text-slate-300 hover:text-white' : 'text-slate-600 hover:text-slate-900'
                                                }`}
                                            >
                                                Sign In
                                            </Link>
                                            <Link
                                                href={route('register')}
                                                onClick={() => setMobileMenuOpen(false)}
                                                className="block w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3 text-center text-sm font-semibold text-white"
                                            >
                                                Get Started
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </nav>

            {/* Main Content */}
            <main>{children}</main>
        </div>
    );
}