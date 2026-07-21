import { useState } from 'react';
import { motion } from 'framer-motion';
import { useTheme } from '@/Contexts/ThemeContext';
import { Search, MapPin, Building2, Target, Award, Star } from 'lucide-react';
import LocationInput from '@/Components/LocationInput';

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

export default function HeroSection() {
    const { isDark } = useTheme();
    const [searchQuery, setSearchQuery] = useState('');
    const [location, setLocation] = useState('');

    const floatingCards = [
        { icon: Building2, label: '10K+ Companies', color: 'bg-blue-500' },
        { icon: Target, label: 'Smart Matching', color: 'bg-purple-500' },
        { icon: Award, label: 'Verified Jobs', color: 'bg-orange-500' },
    ];

    // Theme-based colors
    const bgColor = isDark ? 'bg-[#0f172a]' : 'bg-gray-50';
    const textColor = isDark ? 'text-white' : 'text-gray-900';
    const subTextColor = isDark ? 'text-gray-400' : 'text-gray-600';
    const cardBg = isDark ? 'bg-gray-800/50 border-gray-700' : 'bg-white border-gray-200';
    const badgeBg = isDark ? 'bg-blue-500/20 text-blue-400 border-blue-500/30' : 'bg-blue-50 text-blue-700 border-blue-200';

    return (
        <section className={`relative min-h-screen flex items-center ${bgColor}`}>
            {/* Background Elements */}
            <div className="absolute inset-0 overflow-hidden">
                <div className={`absolute top-0 left-1/4 w-96 h-96 rounded-full blur-3xl ${isDark ? 'bg-blue-500/20' : 'bg-blue-400/20'}`} />
                <div className={`absolute bottom-0 right-1/4 w-80 h-80 rounded-full blur-3xl ${isDark ? 'bg-purple-500/20' : 'bg-purple-400/20'}`} />
            </div>

            <div className="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-20">
                <motion.div
                    variants={containerVariants}
                    initial="hidden"
                    animate="visible"
                    className="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center"
                >
                    {/* Left Content */}
                    <div className="text-center lg:text-left">
                        {/* Badge */}
                        <motion.div variants={itemVariants} className="mb-6">
                            <span className={`inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border ${badgeBg}`}>
                                <Star className="w-4 h-4 fill-current" />
                                Smart Job Matching • 50K+ Hired
                            </span>
                        </motion.div>

                        {/* Heading */}
                        <motion.h1
                            variants={itemVariants}
                            className={`text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight mb-6 ${textColor}`}
                        >
                            Find Your{' '}
                            <span className="text-blue-500">Dream Career</span>
                        </motion.h1>

                        {/* Subheading */}
                        <motion.p
                            variants={itemVariants}
                            className={`text-lg mb-8 max-w-xl mx-auto lg:mx-0 ${subTextColor}`}
                        >
                            Connect with top employers worldwide. Discover opportunities that match your skills and experience.
                        </motion.p>

                      <motion.div
  variants={itemVariants}
  className="w-full max-w-5xl mx-auto mb-8 px-3 sm:px-4"
>
  <div
    className={`relative flex flex-col md:flex-row items-stretch md:items-center
    gap-2 md:gap-0 px-3 sm:px-5 py-2 md:py-2.5
    ${isDark ? 'bg-gray-800/80' : 'bg-white/90'}
    backdrop-blur-sm rounded-2xl md:rounded-full shadow-lg hover:shadow-xl transition-all duration-300`}
  >

    {/* Job Title Input */}
    <div className="flex items-center gap-3 flex-1 w-full md:w-auto px-3 py-2 md:py-0 h-full
      [&_input]:border-0 [&_input]:outline-none [&_input]:ring-0
      [&_input]:focus:!outline-none [&_input]:focus:!ring-0">

      <Search className="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 flex-shrink-0" />

      <input
        type="text"
        placeholder="Job title, keywords, or company"
        value={searchQuery}
        onChange={(e) => setSearchQuery(e.target.value)}
        className="flex-1 w-full h-full bg-transparent text-sm sm:text-base text-slate-700 placeholder:text-slate-400"
      />
    </div>

    {/* Location Input */}
    <div className="flex items-center gap-3 flex-1 w-full md:w-auto px-3 py-2 md:py-0 h-full
      [&_*]:border-0 [&_*]:outline-none [&_*]:ring-0 [&_*]:shadow-none
      [&_input]:focus:!outline-none [&_input]:focus:!ring-0 [&_input]:focus-visible:!outline-none">

      <MapPin className="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 flex-shrink-0" />

      <div className="flex items-center gap-3 flex-1 w-full md:w-auto px-3 h-full
        [&_input]:!border-0
        [&_input]:!outline-none
        [&_input]:!ring-0
        [&_input]:focus:!outline-none
        [&_input]:focus:!ring-0
        [&_input]:focus-visible:!outline-none
        [&_input]:focus-visible:!ring-0
        [&_input]:!shadow-none">

        <LocationInput
          value={location}
          onChange={setLocation}
          placeholder="City, state"
          variant="pill"
          className="w-full h-full bg-transparent
          !border-0 !outline-none !ring-0 !shadow-none
          text-sm sm:text-base text-slate-600 placeholder:text-slate-400
          focus:!outline-none focus:!ring-0"
        />
      </div>
    </div>

    {/* Button */}
    <button
      onClick={() => {
        const params = new URLSearchParams();
        if (searchQuery) params.append('title', searchQuery);
        if (location) params.append('location', location);
        const jobsUrl = route('jobs.index');
        window.location.href = `${jobsUrl}${params.toString() ? '?' + params.toString() : ''}`;
      }}
      className="w-full md:w-auto mt-2 md:mt-0 md:ml-2
      min-h-[48px] md:min-h-[52px] px-6 md:px-8
      flex items-center justify-center
      bg-blue-600 hover:bg-blue-700 text-white font-medium
      rounded-full shadow-md hover:shadow-lg
      transition-all duration-200 whitespace-nowrap"
    >
      Find jobs
    </button>

  </div>

  {/* Quick Links */}
  <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mt-4 px-2">
    <div className="flex flex-wrap gap-2">
      <span className="text-xs sm:text-sm text-gray-500">Popular:</span>
      {['React Developer', 'Data Scientist', 'Remote', 'Entry Level'].map((tag) => (
        <button
          key={tag}
          onClick={() => setSearchQuery(tag)}
          className="text-xs sm:text-sm text-blue-600 hover:text-blue-700 hover:underline transition-colors"
        >
          {tag}
        </button>
      ))}
    </div>

    {/* <Link
      href="/jobs"
      className="text-xs sm:text-sm text-gray-600 hover:text-gray-900 hover:underline transition-colors"
    >
      Advanced search →
    </Link> */}
  </div>
</motion.div>


                        {/* Trust Indicators */}
                        {/* <motion.div variants={itemVariants} className="flex items-center justify-center lg:justify-start gap-4"> */}
                            {/* <div className="flex -space-x-2">
                                {[1, 2, 3, 4].map((i) => (
                                    <div
                                        key={i}
                                        className="w-10 h-10 rounded-full border-2 border-current flex items-center justify-center text-xs font-bold bg-gradient-to-br from-blue-500 to-purple-500 text-white"
                                        style={{ borderColor: isDark ? '#0f172a' : '#f9fafb' }}
                                    >
                                        {String.fromCharCode(64 + i)}
                                    </div>
                                ))}
                            </div> */}
                            {/* <div className="text-left">
                                <div className="flex items-center gap-1">
                                    {[1, 2, 3, 4, 5].map((i) => (
                                        <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                                    ))}
                                </div>
                                <p className={`text-sm ${subTextColor}`}>
                                    Trusted by <span className="font-semibold text-blue-500">50K+</span> job seekers
                                </p>
                            </div> */}
                        {/* </motion.div> */}
                    </div>

                    {/* Right Content - Floating Cards */}
                    <motion.div
                        variants={containerVariants}
                        initial="hidden"
                        animate="visible"
                        className="relative hidden lg:block"
                    >
                        <div className="relative h-[450px]">
                            {floatingCards.map((card, index) => (
                                <motion.div
                                    key={card.label}
                                    variants={itemVariants}
                                    animate={{ y: [0, -8, 0] }}
                                    transition={{ duration: 3 + index, repeat: Infinity, ease: "easeInOut", delay: index * 0.5 }}
                                    className={`absolute ${index === 0 ? 'top-8 left-8' :
                                        index === 1 ? 'top-1/3 right-4' :
                                            'bottom-16 left-16'
                                        }`}
                                >
                                    <div className={`p-4 rounded-xl border shadow-lg ${cardBg}`}>
                                        <div className={`w-10 h-10 rounded-lg ${card.color} flex items-center justify-center mb-2`}>
                                            <card.icon className="w-5 h-5 text-white" />
                                        </div>
                                        <p className={`text-sm font-medium ${textColor}`}>{card.label}</p>
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    </motion.div>
                </motion.div>
            </div>
        </section>
    );
}
