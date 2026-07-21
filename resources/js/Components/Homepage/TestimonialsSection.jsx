import { motion } from 'framer-motion';
import { useTheme } from '@/Contexts/ThemeContext';
import { Star } from 'lucide-react';

const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: { staggerChildren: 0.15, delayChildren: 0.2 }
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

export default function TestimonialsSection() {
    const { isDark } = useTheme();

    const bgColor = isDark ? 'bg-[#0f172a]' : 'bg-gray-50';
    const cardBg = isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200';
    const textColor = isDark ? 'text-white' : 'text-gray-900';
    const subTextColor = isDark ? 'text-gray-400' : 'text-gray-600';
    const badgeBg = isDark ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-amber-50 text-amber-700 border-amber-200';

    const testimonials = [
        { name: 'Sarah Johnson', role: 'Software Engineer at Google', quote: 'This platform transformed my job search. Got multiple offers in two weeks!', initials: 'SJ', color: 'bg-blue-500' },
        { name: 'Michael Chen', role: 'Product Manager at Microsoft', quote: 'The resume builder helped me create a CV that stood out. Highly recommend!', initials: 'MC', color: 'bg-purple-500' },
        { name: 'Emily Rodriguez', role: 'UX Designer at Airbnb', quote: 'Real-time tracking kept me informed. Found my dream job!', initials: 'ER', color: 'bg-orange-500' },
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
                        <Star className="w-4 h-4 fill-current" />
                        Success Stories
                    </span>
                    <h2 className={`text-3xl lg:text-4xl font-bold mt-4 mb-2 ${textColor}`}>
                        Loved by <span className="text-blue-500">Job Seekers</span>
                    </h2>
                    <p className={`text-lg max-w-2xl mx-auto ${subTextColor}`}>
                        See what our users have to say about their experience.
                    </p>
                </motion.div>

                {/* Testimonials Grid */}
                <motion.div
                    variants={containerVariants}
                    initial="hidden"
                    whileInView="visible"
                    viewport={{ once: true, margin: "-50px" }}
                    className="grid md:grid-cols-3 gap-6"
                >
                    {testimonials.map((testimonial) => (
                        <motion.div
                            key={testimonial.name}
                            variants={itemVariants}
                            whileHover={{ y: -4, transition: { duration: 0.2 } }}
                            className={`p-6 rounded-xl border ${cardBg}`}
                        >
                            <div className="flex items-center gap-1 mb-4">
                                {[1, 2, 3, 4, 5].map((i) => (
                                    <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                                ))}
                            </div>
                            <p className={`text-sm mb-6 ${subTextColor}`}>"{testimonial.quote}"</p>
                            <div className="flex items-center gap-3">
                                <div className={`w-10 h-10 rounded-lg ${testimonial.color} flex items-center justify-center text-white font-bold text-sm`}>
                                    {testimonial.initials}
                                </div>
                                <div>
                                    <h4 className={`font-semibold text-sm ${textColor}`}>{testimonial.name}</h4>
                                    <p className={`text-xs ${subTextColor}`}>{testimonial.role}</p>
                                </div>
                            </div>
                        </motion.div>
                    ))}
                </motion.div>
            </div>
        </section>
    );
}
