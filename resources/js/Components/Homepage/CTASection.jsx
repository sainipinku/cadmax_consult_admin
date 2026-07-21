import { motion } from 'framer-motion';
import { useTheme } from '@/Contexts/ThemeContext';
import { Link } from '@inertiajs/react';
import { ArrowRight, Sparkles } from 'lucide-react';

export default function CTASection() {
    const { isDark } = useTheme();

    const bgColor = isDark ? 'bg-[#0f172a]' : 'bg-white';
    const cardBg = isDark ? 'bg-gray-800 border-gray-700' : 'bg-blue-50 border-blue-200';
    const textColor = isDark ? 'text-white' : 'text-gray-900';
    const subTextColor = isDark ? 'text-gray-400' : 'text-gray-600';
    const badgeBg = isDark ? 'bg-blue-500/20 text-blue-400 border-blue-500/30' : 'bg-white text-blue-700 border-blue-300';

    return (
        <section className={`py-16 lg:py-20 ${bgColor}`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.5 }}
                    className={`p-8 lg:p-12 rounded-2xl border text-center ${cardBg}`}
                >
                    <span className={`inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border ${badgeBg}`}>
                        <Sparkles className="w-4 h-4" />
                        Join 50,000+ Professionals
                    </span>

                    <h2 className={`text-3xl lg:text-4xl font-bold mt-4 mb-2 ${textColor}`}>
                        Ready to Transform <span className="text-blue-500">Your Career?</span>
                    </h2>

                    <p className={`text-lg max-w-2xl mx-auto mb-8 ${subTextColor}`}>
                        Join thousands who found their dream jobs through our platform.
                    </p>

                    <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link
                            href={route('register')}
                            className="px-8 py-3 rounded-lg font-semibold text-white bg-blue-500 hover:bg-blue-600 transition-colors flex items-center gap-2"
                        >
                            Sign Up
                            <ArrowRight className="w-5 h-5" />
                        </Link>
                        <Link
                            href={route('jobs.index')}
                            className={`px-8 py-3 rounded-lg font-semibold flex items-center gap-2 ${
                                isDark ? 'bg-gray-700 text-white hover:bg-gray-600' : 'bg-white text-gray-700 hover:bg-gray-100'
                            }`}
                        >
                            Browse Jobs
                            <ArrowRight className="w-5 h-5" />
                        </Link>
                    </div>
                </motion.div>
            </div>
        </section>
    );
}
