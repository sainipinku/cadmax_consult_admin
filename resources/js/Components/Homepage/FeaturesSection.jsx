import { motion } from 'framer-motion';
import { useTheme } from '@/Contexts/ThemeContext';
import { Zap, MousePointerClick, Activity, Shield, FileText, Globe } from 'lucide-react';

const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: { staggerChildren: 0.1, delayChildren: 0.2 }
    }
};

const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.5 }
    }
};

export default function FeaturesSection() {
    const { isDark } = useTheme();

    const bgColor = isDark ? 'bg-[#0f172a]' : 'bg-white';
    const cardBg = isDark ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200';
    const textColor = isDark ? 'text-white' : 'text-gray-900';
    const subTextColor = isDark ? 'text-gray-400' : 'text-gray-600';
    const badgeBg = isDark ? 'bg-purple-500/20 text-purple-400 border-purple-500/30' : 'bg-purple-50 text-purple-700 border-purple-200';

    const features = [
        { icon: Zap, title: 'Smart Matching', description: 'Advanced algorithms match you with relevant jobs.', color: 'bg-blue-500' },
        { icon: MousePointerClick, title: 'One-Click Apply', description: 'Apply instantly to multiple positions.', color: 'bg-purple-500' },
        { icon: Activity, title: 'Real-time Tracking', description: 'Track your applications in real-time.', color: 'bg-green-500' },
        { icon: Shield, title: 'Verified Employers', description: 'All companies are thoroughly vetted.', color: 'bg-orange-500' },
        { icon: FileText, title: 'Resume Builder', description: 'Create professional resumes easily.', color: 'bg-pink-500' },
        { icon: Globe, title: 'Global Opportunities', description: 'Access jobs from companies worldwide.', color: 'bg-indigo-500' },
    ];

    return (
        <section className={`py-16 lg:py-20 ${bgColor}`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.5 }}
                    className="text-center mb-12"
                >
                    <span className={`inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border ${badgeBg}`}>
                        <Zap className="w-4 h-4" />
                        Powerful Features
                    </span>
                    <h2 className={`text-3xl lg:text-4xl font-bold mt-4 mb-2 ${textColor}`}>
                        Everything You Need to <span className="text-blue-500">Succeed</span>
                    </h2>
                    <p className={`text-lg max-w-2xl mx-auto ${subTextColor}`}>
                        Our platform makes your job search efficient and enjoyable.
                    </p>
                </motion.div>

                {/* Features Grid */}
                <motion.div
                    variants={containerVariants}
                    initial="hidden"
                    whileInView="visible"
                    viewport={{ once: true, margin: "-50px" }}
                    className="grid md:grid-cols-2 lg:grid-cols-3 gap-6"
                >
                    {features.map((feature) => (
                        <motion.div
                            key={feature.title}
                            variants={itemVariants}
                            whileHover={{ y: -4, transition: { duration: 0.2 } }}
                            className={`p-6 rounded-xl border ${cardBg}`}
                        >
                            <div className={`w-12 h-12 rounded-lg ${feature.color} flex items-center justify-center mb-4`}>
                                <feature.icon className="w-6 h-6 text-white" />
                            </div>
                            <h3 className={`text-lg font-semibold mb-2 ${textColor}`}>{feature.title}</h3>
                            <p className={`text-sm ${subTextColor}`}>{feature.description}</p>
                        </motion.div>
                    ))}
                </motion.div>
            </div>
        </section>
    );
}
