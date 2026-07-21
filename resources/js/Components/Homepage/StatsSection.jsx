import { motion } from 'framer-motion';
import { useTheme } from '@/Contexts/ThemeContext';
import { Briefcase, Building2, Users, TrendingUp } from 'lucide-react';
import { useEffect, useState } from 'react';

const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: { staggerChildren: 0.15, delayChildren: 0.1 }
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

function AnimatedCounter({ target, suffix = '', duration = 2 }) {
    const [count, setCount] = useState(0);

    useEffect(() => {
        let startTime;
        let animationFrame;

        const animate = (timestamp) => {
            if (!startTime) startTime = timestamp;
            const progress = Math.min((timestamp - startTime) / (duration * 1000), 1);
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            setCount(Math.floor(easeOutQuart * target));

            if (progress < 1) {
                animationFrame = requestAnimationFrame(animate);
            }
        };

        animationFrame = requestAnimationFrame(animate);
        return () => cancelAnimationFrame(animationFrame);
    }, [target, duration]);

    return <span>{count.toLocaleString()}{suffix}</span>;
}

export default function StatsSection({ stats = {} }) {
    const { isDark } = useTheme();

    const bgColor = isDark ? 'bg-[#0f172a]' : 'bg-white';
    const cardBg = isDark ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200';
    const textColor = isDark ? 'text-white' : 'text-gray-900';
    const subTextColor = isDark ? 'text-gray-400' : 'text-gray-600';

    const defaultStats = [
        { icon: Briefcase, value: stats.activeJobs || 12500, suffix: '+', label: 'Active Jobs', color: 'bg-blue-500' },
        { icon: Building2, value: stats.companies || 8500, suffix: '+', label: 'Companies', color: 'bg-purple-500' },
        { icon: Users, value: stats.jobSeekers || 50000, suffix: '+', label: 'Job Seekers', color: 'bg-orange-500' },
        { icon: TrendingUp, value: stats.successRate || 94, suffix: '%', label: 'Success Rate', color: 'bg-green-500' },
    ];

    return (
        <section className={`py-16 lg:py-20 ${bgColor}`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <motion.div
                    variants={containerVariants}
                    initial="hidden"
                    whileInView="visible"
                    viewport={{ once: true, margin: "-50px" }}
                    className="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6"
                >
                    {defaultStats.map((stat) => (
                        <motion.div
                            key={stat.label}
                            variants={itemVariants}
                            whileHover={{ y: -4, transition: { duration: 0.2 } }}
                            className={`p-4 rounded-xl border ${cardBg}`}
                        >
                            <div className={`w-12 h-12 rounded-lg ${stat.color} flex items-center justify-center mb-4`}>
                                <stat.icon className="w-6 h-6 text-white" />
                            </div>
                            <div className={`text-3xl font-bold mb-1 ${textColor}`}>
                                <AnimatedCounter target={stat.value} suffix={stat.suffix} />
                            </div>
                            <p className={`text-sm ${subTextColor}`}>{stat.label}</p>
                        </motion.div>
                    ))}
                </motion.div>
            </div>
        </section>
    );
}
