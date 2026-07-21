import { useTheme } from '@/Contexts/ThemeContext';
import { Link } from '@inertiajs/react';
import { Building2, Twitter, Linkedin, Github, Instagram } from 'lucide-react';

export default function Footer() {
    const { isDark } = useTheme();
    const currentYear = new Date().getFullYear();

    const bgColor = isDark ? 'bg-[#0f172a] border-gray-800' : 'bg-gray-50 border-gray-200';
    const textColor = isDark ? 'text-white' : 'text-gray-900';
    const subTextColor = isDark ? 'text-gray-400' : 'text-gray-600';

    const links = [
        { name: 'Browse Jobs', href: route('jobs.index') },
        { name: 'Companies', href: route('companies.index') },
        { name: 'About Us', href: route('about') },
        { name: 'Contact', href: route('contact.show') },
    ];

    const socialLinks = [
        { name: 'Twitter', icon: Twitter, href: '#' },
        { name: 'LinkedIn', icon: Linkedin, href: '#' },
        { name: 'GitHub', icon: Github, href: '#' },
        { name: 'Instagram', icon: Instagram, href: '#' },
    ];

    return (
        <footer className={`border-t ${bgColor}`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="grid md:grid-cols-4 gap-8">
                    {/* Brand */}
                    <div className="md:col-span-2">
                        <Link href={route('home')} className="flex items-center gap-2 mb-4">
                            <div className="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center">
                                <Building2 className="w-6 h-6 text-white" />
                            </div>
                            <span className={`text-xl font-bold ${textColor}`}>ATS</span>
                        </Link>
                        <p className={`text-sm max-w-xs mb-4 ${subTextColor}`}>
                            Connecting talented professionals with world-class employers.
                        </p>
                        <div className="flex items-center gap-3">
                            {socialLinks.map((social) => (
                                <a
                                    key={social.name}
                                    href={social.href}
                                    className={`p-2 rounded-lg transition-colors ${subTextColor} hover:text-blue-500`}
                                    aria-label={social.name}
                                >
                                    <social.icon className="w-4 h-4" />
                                </a>
                            ))}
                        </div>
                    </div>

                    {/* Links */}
                    <div>
                        <h3 className={`font-semibold text-sm mb-4 ${textColor}`}>Quick Links</h3>
                        <ul className="space-y-2">
                            {links.map((link) => (
                                <li key={link.name}>
                                    <Link href={link.href} className={`text-sm ${subTextColor} hover:text-blue-500 transition-colors`}>
                                        {link.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Contact */}
                    <div>
                        <h3 className={`font-semibold text-sm mb-4 ${textColor}`}>Contact</h3>
                        <ul className={`space-y-2 text-sm ${subTextColor}`}>
                            <li>support@ats.com</li>
                            <li>+1 (555) 123-4567</li>
                            <li>San Francisco, CA</li>
                        </ul>
                    </div>
                </div>

                {/* Bottom */}
                <div className={`mt-8 pt-8 border-t ${isDark ? 'border-gray-800' : 'border-gray-200'} flex flex-col md:flex-row items-center justify-between gap-4`}>
                    <p className={`text-sm ${subTextColor}`}>
                        {currentYear} ATS. All rights reserved.
                    </p>
                    <div className="flex items-center gap-4 text-sm">
                        <Link href="/privacy" className={`${subTextColor} hover:text-blue-500`}>Privacy</Link>
                        <Link href="/terms" className={`${subTextColor} hover:text-blue-500`}>Terms</Link>
                    </div>
                </div>
            </div>
        </footer>
    );
}
