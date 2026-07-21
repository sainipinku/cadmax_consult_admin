import { Link, usePage } from '@inertiajs/react';
import { useTheme } from '@/Contexts/ThemeContext';
import {
    Sun,
    Moon,
    Menu,
    X,
    Building2,
    LayoutDashboard,
    Home,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

export default function HomepageLayout({ children }) {
    const { toggleTheme, isDark } = useTheme();
    const [isScrolled, setIsScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const { auth } = usePage().props;
    const isAuthenticated = !!auth?.user;

    useEffect(() => {
        const handleScroll = () => {
            setIsScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const navLinks = [
        { name: 'Home', href: route('homepage'), icon: Home },
        // { name: 'Browse Jobs', href: route('jobs.index'), icon: Briefcase },
        // { name: 'Companies', href: route('companies.index'), icon: Building2 },
        // { name: 'About', href: route('about'), icon: Sparkles },
        // { name: 'Contact', href: route('contact.show'), icon: Mail },
    ];

    if (isAuthenticated) {
        const dashboardRouteByGuard = {
            superadmin: 'super.dashboard',
            admin: 'admin.dashboard',
            member: 'member.dashboard',
        };
        const dashboardRouteName = dashboardRouteByGuard[auth?.guard] ?? 'home';
        navLinks.push({ name: 'Dashboard', href: route(dashboardRouteName), icon: LayoutDashboard });
    }

    return (
        <div className={`min-h-screen transition-colors duration-300 ${isDark ? 'bg-[#0f172a]' : 'bg-gray-50'}`}>
            {/* Navbar */}
            <nav
                className={`fixed top-0 left-0 right-0 z-50 transition-colors duration-300 ${
                    isScrolled
                        ? isDark
                            ? 'bg-gray-900/95 border-b border-gray-800'
                            : 'bg-white border-b border-gray-200 shadow-sm'
                        : isDark ? 'bg-[#0f172a]' : 'bg-gray-50'
                }`}
            >
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16 lg:h-20">
                        {/* Logo */}
                        <Link href={route('homepage')} className="flex items-center gap-2 group">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-500`}>
                                <Building2 className="w-6 h-6 text-white" />
                            </div>
                            <span className={`text-xl font-bold transition-colors duration-300 ${
                                isDark ? 'text-white' : 'text-slate-900'
                            }`}>
                                ATS
                            </span>
                        </Link>

                        {/* Desktop Navigation */}
                        <div className="hidden lg:flex items-center gap-1">
                            {navLinks.map((link) => (
                                <Link
                                    key={link.name}
                                    href={link.href}
                                    className={`px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 ${
                                        isDark
                                            ? 'text-slate-300 hover:text-white hover:bg-white/10'
                                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                                    }`}
                                >
                                    {link.name}
                                </Link>
                            ))}
                        </div>

                        {/* Right Side Actions */}
                        <div className="flex items-center gap-2 sm:gap-4">
                            {/* Theme Toggle */}
                            <button
                                onClick={toggleTheme}
                                className={`p-2 rounded-lg transition-colors ${
                                    isDark
                                        ? 'text-gray-400 hover:text-white hover:bg-gray-800'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                }`}
                                aria-label="Toggle theme"
                            >
                                {isDark ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                            </button>

                            {/* Auth Buttons - Desktop */}
                            <div className="hidden sm:flex items-center gap-3">
                                {isAuthenticated ? (
                                    <Link
                                        href={route(({
                                            superadmin: 'super.dashboard',
                                            admin: 'admin.dashboard',
                                            member: 'member.dashboard',
                                        })[auth?.guard] ?? 'home')}
                                        className="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-500 hover:bg-blue-600 transition-colors"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={route('login')}
                                            className={`px-4 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                                                isDark ? 'text-gray-300 hover:text-white' : 'text-gray-600 hover:text-gray-900'
                                            }`}
                                        >
                                            Sign In
                                        </Link>
                                        <Link
                                            href={route('register')}
                                            className="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-500 hover:bg-blue-600 transition-colors"
                                        >
                                            Sign Up
                                        </Link>
                                    </>
                                )}
                            </div>

                            {/* Mobile Menu Button */}
                            <button
                                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                className={`lg:hidden p-2.5 rounded-xl transition-all duration-300 ${
                                    isDark
                                        ? 'text-slate-300 hover:text-white hover:bg-white/10'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                                }`}
                            >
                                {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
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
                            className={`lg:hidden border-t ${
                                isDark
                                    ? 'bg-slate-900/95 border-slate-800 backdrop-blur-xl'
                                    : 'bg-white/95 border-slate-200 backdrop-blur-xl'
                            }`}
                        >
                            <div className="px-4 py-4 space-y-2">
                                {navLinks.map((link) => (
                                    <Link
                                        key={link.name}
                                        href={link.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 ${
                                            isDark
                                                ? 'text-slate-300 hover:text-white hover:bg-white/10'
                                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                                        }`}
                                    >
                                        <link.icon className="w-5 h-5" />
                                        {link.name}
                                    </Link>
                                ))}
                                <div className="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-2">
                                    {isAuthenticated ? (
                                        <Link
                                            href={route(({
                                                superadmin: 'super.dashboard',
                                                admin: 'admin.dashboard',
                                                member: 'member.dashboard',
                                            })[auth?.guard] ?? 'home')}
                                            className={`block w-full text-center px-4 py-3 rounded-xl text-sm font-semibold ${
                                                isDark
                                                    ? 'bg-gradient-to-r from-brand-500 to-accent-purple text-white'
                                                    : 'bg-gradient-to-r from-brand-600 to-brand-700 text-white'
                                            }`}
                                        >
                                            Dashboard
                                        </Link>
                                    ) : (
                                        <>
                                            <Link
                                                href={route('login')}
                                                className={`block w-full text-center px-4 py-3 rounded-xl text-sm font-medium ${
                                                    isDark
                                                        ? 'text-slate-300 hover:text-white'
                                                        : 'text-slate-600 hover:text-slate-900'
                                                }`}
                                            >
                                                Sign In
                                            </Link>
                                            <Link
                                                href={route('register')}
                                                className={`block w-full text-center px-4 py-3 rounded-xl text-sm font-semibold ${
                                                    isDark
                                                        ? 'bg-gradient-to-r from-brand-500 to-accent-purple text-white'
                                                        : 'bg-gradient-to-r from-brand-600 to-brand-700 text-white'
                                                }`}
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
            <main className="pt-16 lg:pt-20">
                {children}
            </main>
        </div>
    );
}
