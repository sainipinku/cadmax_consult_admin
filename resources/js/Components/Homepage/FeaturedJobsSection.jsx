import { motion, AnimatePresence } from 'framer-motion';
import { useTheme } from '@/Contexts/ThemeContext';
import { Link } from '@inertiajs/react';
import { Clock, ArrowRight, Briefcase, X, CheckCircle2, Calendar, Users, MapPin, DollarSign, Bookmark, BookmarkCheck, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { formatDistanceToNow } from 'date-fns';
import { usePage } from '@inertiajs/react';

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

// Job Card Component (Copy from Member/JobListings)
const CandidateJobCard = ({ job, hasApplied, onViewDetails, onApply, isDark, isAuthenticated, onSaveJob, savedJobs }) => {
    const getJobTypeBadge = (type) => {
        const badges = {
            'full-time': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'part-time': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'contract': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
            'internship': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            'remote': 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400',
        };
        return badges[type?.toLowerCase()] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'No deadline';
        const date = new Date(dateString);
        const today = new Date();
        const diffTime = date - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays < 0) return 'Expired';
        if (diffDays === 0) return 'Closing today';
        if (diffDays === 1) return 'Closes tomorrow';
        if (diffDays <= 7) return `Closes in ${diffDays} days`;
        return `Closes: ${date.toLocaleDateString('en-IN')}`;
    };

    const cardBg = isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-slate-200';
    const hoverBorder = isDark ? 'hover:border-blue-500' : 'hover:border-blue-300';
    const footerBg = isDark ? 'bg-gray-700/50 border-gray-700' : 'bg-slate-50 border-slate-100';
    const textPrimary = isDark ? 'text-white' : 'text-slate-900';
    const textSecondary = isDark ? 'text-gray-300' : 'text-slate-600';
    const textMuted = isDark ? 'text-gray-400' : 'text-slate-500';

    const isSaved = savedJobs?.includes(job.id);
    const handleSaveJob = () => {
        if (!isAuthenticated) {
            // Redirect to login when trying to save without authentication
            window.location.href = route('login');
            return;
        }
        onSaveJob(job.id);
    };

    return (
        <div className={`${cardBg} rounded-2xl shadow-sm border ${hoverBorder} transition-all duration-300 overflow-hidden flex flex-col h-full`}>
            {/* Header with Company Logo */}
            <div className="p-5 flex-grow">
                <div className="flex items-start gap-4">
                    <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold flex-shrink-0 overflow-hidden">
                        {job.company_image ? (
                            <img
                                src={job.company_image}
                                alt={job.company}
                                className="w-full h-full object-cover"
                                onError={(e) => {
                                    e.target.style.display = 'none';
                                    e.target.parentElement.innerHTML = job.company?.charAt(0)?.toUpperCase() || 'J';
                                }}
                            />
                        ) : (
                            job.company?.charAt(0)?.toUpperCase() || 'J'
                        )}
                    </div>
                    <div className="flex-1 min-w-0">
                        <h3 className={`text-lg font-semibold ${textPrimary} line-clamp-1 mb-1`}>
                            {job.title}
                        </h3>
                        <p className={`${textSecondary} text-sm font-medium`}>{job.company}</p>
                    </div>

                    {/* Save/Bookmark Button - Top Right */}
                    <button
                        onClick={handleSaveJob}
                        className={`p-2 rounded-lg transition-all duration-300 flex-shrink-0 ${
                            isSaved
                                ? 'bg-emerald-100 text-emerald-600 border border-emerald-200 hover:bg-emerald-200'
                                : isDark
                                    ? 'bg-gray-700 text-gray-300 border border-gray-600 hover:bg-gray-600 hover:text-white'
                                    : 'bg-white text-gray-600 border border-slate-300 hover:bg-slate-50 hover:text-slate-700'
                        }`}
                        title={isSaved ? 'Remove from saved jobs' : isAuthenticated ? 'Save job' : 'Sign in to save jobs'}
                    >
                        {isSaved ? <BookmarkCheck className="w-4 h-4" /> : <Bookmark className="w-4 h-4" />}
                    </button>
                </div>

                {/* Job Meta */}
                <div className="mt-4 flex flex-wrap gap-2">
                    <span className={`px-3 py-1 rounded-full text-xs font-medium ${getJobTypeBadge(job.job_type)}`}>
                        {job.job_type?.replace('-', ' ')?.toUpperCase()}
                    </span>
                    <span className={`px-3 py-1 rounded-full text-xs font-medium ${isDark ? 'bg-gray-700 text-gray-300' : 'bg-slate-100 text-slate-700'}`}>
                        {job.experience}
                    </span>
                </div>

                {/* Details */}
                <div className="mt-4 space-y-2">
                    <div className={`flex items-center gap-2 ${textSecondary} text-sm`}>
                        <MapPin className="w-4 h-4 text-slate-400" />
                        <span>{job.location}</span>
                    </div>
                    <div className={`flex items-center gap-2 ${textSecondary} text-sm`}>
                        <DollarSign className="w-4 h-4 text-slate-400" />
                        <span className="font-semibold text-emerald-600">{job.salary}</span>
                    </div>
                </div>

                {/* Skills Preview */}
                {job.skills && job.skills.length > 0 && (
                    <div className="mt-4">
                        <div className="flex flex-wrap gap-1.5">
                            {job.skills.slice(0, 3).map((skill, idx) => (
                                <span key={idx} className={`px-2 py-0.5 text-xs rounded-md border ${isDark ? 'bg-gray-700 text-gray-300 border-gray-600' : 'bg-slate-50 text-slate-600 border-slate-200'}`}>
                                    {skill}
                                </span>
                            ))}
                            {job.skills.length > 3 && (
                                <span className={`px-2 py-0.5 text-xs rounded-md ${isDark ? 'bg-gray-700 text-gray-400' : 'bg-slate-50 text-slate-500'}`}>
                                    +{job.skills.length - 3} more
                                </span>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Footer with Actions - Always at Bottom */}
            <div className={`px-5 py-4 ${footerBg} border-t mt-auto`}>
                <div className="flex items-center justify-between">
                    <div className={`text-xs ${textMuted}`}>
                        <div className="flex items-center gap-1">
                            <Clock className="w-3.5 h-3.5" />
                            {formatDate(job.last_date)}
                        </div>
                        <div className="mt-1">
                            {job.applicants || 0} applicants
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <button
                            onClick={() => onViewDetails(job)}
                            className={`px-4 py-2 text-sm font-medium ${isDark ? 'text-gray-300 bg-gray-700 border-gray-600 hover:bg-gray-600' : 'text-slate-700 bg-white border-slate-300 hover:bg-slate-50'} rounded-lg transition-colors`}
                        >
                            View Details
                        </button>
                        <button
                            onClick={() => onApply(job)}
                            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Apply Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

function SkeletonCard({ isDark }) {
    const bg = isDark ? 'bg-gray-800 border-gray-700' : 'bg-gray-100 border-gray-200';
    const pulse = isDark ? 'bg-gray-700' : 'bg-gray-200';

    return (
        <div className={`${bg} rounded-2xl border animate-pulse`}>
            <div className="p-5">
                <div className="flex items-start gap-4 mb-4">
                    <div className={`w-14 h-14 rounded-xl ${pulse}`} />
                    <div className="flex-1 space-y-2">
                        <div className={`h-5 rounded w-3/4 ${pulse}`} />
                        <div className={`h-4 rounded w-1/2 ${pulse}`} />
                    </div>
                </div>
                <div className="space-y-2 mb-4">
                    <div className={`h-4 rounded w-full ${pulse}`} />
                    <div className={`h-4 rounded w-2/3 ${pulse}`} />
                </div>
                <div className="flex gap-2 mb-4">
                    <div className={`h-6 rounded-full w-20 ${pulse}`} />
                    <div className={`h-6 rounded-full w-24 ${pulse}`} />
                </div>
                <div className="flex gap-3">
                    <div className={`h-10 rounded-lg flex-1 ${pulse}`} />
                    <div className={`h-10 rounded-lg flex-1 ${pulse}`} />
                </div>
            </div>
        </div>
    );
}

// Job Details Modal (Exact Copy from Member/JobListings)
const JobDetailsModal = ({ job, isOpen, onClose, hasApplied, onApply }) => {
    const [activeTab, setActiveTab] = useState('overview');

    if (!isOpen || !job) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {/* Backdrop */}
                <div className="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50" onClick={onClose}></div>

                {/* Modal Panel */}
                <div className="inline-block w-full max-w-4xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl sm:align-middle">
                    {/* Header */}
                    <div className="relative bg-gradient-to-r from-blue-600 to-purple-700 px-8 py-6 rounded-t-2xl">
                        <div className="flex items-start gap-4">
                            <div className="w-16 h-16 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center text-white text-2xl font-bold overflow-hidden">
                                {job.company_image ? (
                                    <img
                                        src={job.company_image}
                                        alt={job.company}
                                        className="w-full h-full object-cover"
                                        onError={(e) => {
                                            e.target.style.display = 'none';
                                            e.target.parentElement.innerHTML = job.company?.charAt(0)?.toUpperCase() || 'J';
                                        }}
                                    />
                                ) : (
                                    job.company?.charAt(0)?.toUpperCase() || 'J'
                                )}
                            </div>
                            <div className="flex-1">
                                <h2 className="text-2xl font-bold text-white mb-1">{job.title}</h2>
                                <p className="text-white/90 text-lg">{job.company}</p>
                                <div className="flex flex-wrap gap-2 mt-3">
                                    <span className="px-3 py-1 bg-white/20 backdrop-blur rounded-full text-white text-sm">
                                        {job.job_type?.replace('-', ' ')?.toUpperCase()}
                                    </span>
                                    <span className="px-3 py-1 bg-white/20 backdrop-blur rounded-full text-white text-sm">
                                        {job.location}
                                    </span>
                                    <span className="px-3 py-1 bg-white/20 backdrop-blur rounded-full text-white text-sm">
                                        {job.experience}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Tabs */}
                    <div className="border-b border-slate-200 px-8">
                        <div className="flex gap-6">
                            {['overview', 'requirements', 'company'].map((tab) => (
                                <button
                                    key={tab}
                                    onClick={() => setActiveTab(tab)}
                                    className={`py-4 text-sm font-medium border-b-2 transition-colors capitalize ${
                                        activeTab === tab
                                            ? 'border-blue-600 text-blue-600'
                                            : 'border-transparent text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    {tab}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Content */}
                    <div className="p-8 max-h-[60vh] overflow-y-auto">
                        {activeTab === 'overview' && (
                            <div className="space-y-6">
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="p-4 bg-slate-50 rounded-xl">
                                        <p className="text-slate-500 text-sm mb-1">Salary</p>
                                        <p className="text-lg font-semibold text-emerald-600">{job.salary}</p>
                                    </div>
                                    <div className="p-4 bg-slate-50 rounded-xl">
                                        <p className="text-slate-500 text-sm mb-1">Experience</p>
                                        <p className="text-lg font-semibold text-slate-900">{job.experience}</p>
                                    </div>
                                    <div className="p-4 bg-slate-50 rounded-xl">
                                        <p className="text-slate-500 text-sm mb-1">Applicants</p>
                                        <p className="text-lg font-semibold text-slate-900">{job.applicants || 0}</p>
                                    </div>
                                </div>

                                <div>
                                    <h4 className="text-lg font-semibold text-slate-900 mb-3">About the Role</h4>
                                    <p className="text-slate-600 leading-relaxed whitespace-pre-line">{job.description}</p>
                                </div>

                                {job.skills && job.skills.length > 0 && (
                                    <div>
                                        <h4 className="text-lg font-semibold text-slate-900 mb-3">Required Skills</h4>
                                        <div className="flex flex-wrap gap-2">
                                            {job.skills.map((skill, idx) => (
                                                <span key={idx} className="px-3 py-1.5 bg-blue-50 text-blue-700 text-sm rounded-lg border border-blue-100">
                                                    {skill}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {job.perks && job.perks.length > 0 && (
                                    <div>
                                        <h4 className="text-lg font-semibold text-slate-900 mb-3">Perks & Benefits</h4>
                                        <div className="flex flex-wrap gap-2">
                                            {job.perks.map((perk, idx) => (
                                                <span
                                                    key={idx}
                                                    className="px-3 py-2 bg-emerald-50 text-emerald-700 rounded-lg text-sm font-medium border border-emerald-100 flex items-center gap-1.5"
                                                >
                                                    <svg className="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    {perk}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {activeTab === 'requirements' && (
                            <div className="space-y-6">
                                {(job.qualifications) && (
                                    <div>
                                        <h4 className="text-lg font-semibold text-slate-900 mb-3">Requirements</h4>
                                        <div className="p-4 bg-slate-50 rounded-xl border border-slate-200">
                                            <p className="text-slate-700 leading-relaxed whitespace-pre-line">
                                                {job.qualifications || 'No qualifications specified.'}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div>
                                    <h4 className="text-lg font-semibold text-slate-900 mb-3">Key Responsibilities</h4>
                                    <div className="p-4 bg-slate-50 rounded-xl border border-slate-200">
                                        <p className="text-slate-700 leading-relaxed whitespace-pre-line">
                                            {job.key_responsibilities || job.keyResponsibilities || 'No key responsibilities specified.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'company' && (
                            <div className="space-y-6">
                                <div className="p-4 bg-slate-50 rounded-xl">
                                    <p className="text-slate-500 text-sm mb-1">Posted by</p>
                                    <p className="text-lg font-semibold text-slate-900">{job.creator?.name || 'Admin'}</p>
                                </div>
                                <div className="p-4 bg-slate-50 rounded-xl">
                                    <p className="text-slate-500 text-sm mb-1">Posted on</p>
                                    <p className="text-lg font-semibold text-slate-900">
                                        {new Date(job.created_at).toLocaleDateString('en-IN', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric'
                                        })}
                                    </p>
                                </div>
                                {job.last_date && (
                                    <div className="p-4 bg-slate-50 rounded-xl">
                                        <p className="text-slate-500 text-sm mb-1">Application Deadline</p>
                                        <p className="text-lg font-semibold text-slate-900">
                                            {new Date(job.last_date).toLocaleDateString('en-IN', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric'
                                            })}
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="px-8 py-5 bg-slate-50 rounded-b-2xl border-t border-slate-200 flex justify-end gap-3">
                        <button
                            onClick={onClose}
                            className="px-6 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                        >
                            Close
                        </button>
                        {hasApplied ? (
                            <button
                                disabled
                                className="px-6 py-2.5 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg cursor-not-allowed flex items-center gap-1"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Applied
                            </button>
                        ) : (
                            <button
                                onClick={() => onApply(job)}
                                className="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Apply Now
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default function FeaturedJobsSection({ jobs: featuredJobs }) {
    const { isDark } = useTheme();
    const [selectedJob, setSelectedJob] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const { auth } = usePage().props;
    const isAuthenticated = !!auth?.user;
    const [savedJobs, setSavedJobs] = useState([]);

    const bgColor = isDark ? 'bg-[#0f172a]' : 'bg-gray-50';
    const cardBg = isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200';
    const textColor = isDark ? 'text-white' : 'text-gray-900';
    const subTextColor = isDark ? 'text-gray-400' : 'text-gray-600';
    const badgeBg = isDark ? 'bg-blue-500/20 text-blue-400 border-blue-500/30' : 'bg-blue-50 text-blue-700 border-blue-200';

    const formatSalary = (salary) => {
        if (!salary) return 'Salary not disclosed';
        if (typeof salary === 'string') return salary;
        return `$${salary.toLocaleString()}`;
    };

    const handleViewDetails = (job) => {
        setSelectedJob(job);
        setIsModalOpen(true);
    };

    const handleApply = (job) => {
        if (auth?.guard === 'member') {
            window.location.href = route('member.jobs.index');
            return;
        }

        window.location.href = route('login');
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelectedJob(null);
    };

    const handleSaveJob = (jobId) => {
        if (savedJobs.includes(jobId)) {
            setSavedJobs(savedJobs.filter(id => id !== jobId));
        } else {
            setSavedJobs([...savedJobs, jobId]);
        }
    };

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
                        <Briefcase className="w-4 h-4" />
                        Latest Opportunities
                    </span>
                    <h2 className={`text-3xl lg:text-4xl font-bold mt-4 mb-2 ${textColor}`}>Featured Jobs</h2>
                    <p className={`text-lg max-w-2xl mx-auto ${subTextColor}`}>
                        Discover the latest job openings from top companies.
                    </p>
                </motion.div>

                {/* Jobs Grid */}
                {!featuredJobs || featuredJobs.length === 0 ? (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        className={`text-center py-16 rounded-xl border ${cardBg}`}
                    >
                        <div className={`w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4 ${isDark ? 'bg-gray-700' : 'bg-gray-200'}`}>
                            <Briefcase className={`w-10 h-10 ${subTextColor}`} />
                        </div>
                        <h3 className={`text-xl font-semibold mb-2 ${textColor}`}>No jobs available</h3>
                        <p className={`mb-6 ${subTextColor}`}>Check back later for new opportunities</p>
                        <Link
                            href={route('jobs.index')}
                            className={`inline-flex items-center gap-2 px-6 py-3 rounded-lg font-medium ${
                                isDark ? 'bg-gray-700 text-white hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            }`}
                        >
                            View All Jobs
                            <ArrowRight className="w-4 h-4" />
                        </Link>
                    </motion.div>
                ) : (
                    <motion.div
                        variants={containerVariants}
                        initial="hidden"
                        whileInView="visible"
                        viewport={{ once: true, margin: "-50px" }}
                        className="grid md:grid-cols-2 lg:grid-cols-3 gap-6"
                    >
                        {featuredJobs.map((job, index) => (
                            <motion.div
                                key={job.id}
                                variants={itemVariants}
                                whileHover={{ y: -8, transition: { duration: 0.2 } }}
                            >
                                <CandidateJobCard
                                    job={job}
                                    hasApplied={false}
                                    onViewDetails={handleViewDetails}
                                    onApply={handleApply}
                                    isDark={isDark}
                                    isAuthenticated={isAuthenticated}
                                    onSaveJob={handleSaveJob}
                                    savedJobs={savedJobs}
                                />
                            </motion.div>
                        ))}
                    </motion.div>
                )}

                {/* CTA Button */}
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.5, delay: 0.3 }}
                    className="text-center mt-12"
                >
                    <Link
                        href={route('jobs.index')}
                        className="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-semibold text-white bg-blue-500 hover:bg-blue-600 transition-colors shadow-lg shadow-blue-500/25"
                    >
                        Explore All Opportunities
                        <ArrowRight className="w-5 h-5" />
                    </Link>
                </motion.div>
            </div>

            {/* Job Details Modal */}
            <JobDetailsModal
                job={selectedJob}
                isOpen={isModalOpen}
                onClose={handleCloseModal}
                hasApplied={false}
                onApply={handleApply}
            />
        </section>
    );
}
