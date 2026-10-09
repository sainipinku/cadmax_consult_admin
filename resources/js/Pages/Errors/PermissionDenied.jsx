import React, { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { FaShieldAlt, FaLock, FaPaperPlane, FaArrowLeft } from "react-icons/fa";
import Modal from "@/Components/Modal";
import { toast } from "react-hot-toast";

export default function PermissionDenied({
    required_permission,
    permission_name,
    module_name,
    requested_url,
    message,
}) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const [requestModalOpen, setRequestModalOpen] = useState(false);
    const [reason, setReason] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleOpenModal = () => {
        setReason("");
        setRequestModalOpen(true);
    };

    const getDashboardUrl = () => {
        try {
            const guard = auth?.guard;
            if (guard === "superadmin") return route("super.dashboard");
            if (guard === "admin") return route("admin.dashboard");
            if (guard === "member") return route("member.dashboard");
            return "/";
        } catch (err) {
            return "/";
        }
    };

    const handleSubmitRequest = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        router.post(
            route("super.permission.request.submit"),
            {
                permission_slug: required_permission,
                permission_name: permission_name,
                module_name: module_name,
                requested_url: requested_url,
                reason: reason,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Permission request submitted to Super Admin!");
                    setRequestModalOpen(false);
                    setIsSubmitting(false);
                },
                onError: (err) => {
                    toast.error("Failed to submit permission request.");
                    setIsSubmitting(false);
                },
            }
        );
    };

    return (
        <div className="min-h-screen bg-slate-900 text-white flex items-center justify-center p-4">
            <Head title="Access Denied - Permission Required" />

            <div className="max-w-md w-full bg-slate-800/90 border border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-2xl backdrop-blur-md text-center relative overflow-hidden">
                {/* Background ambient glow */}
                <div className="absolute -top-24 -left-24 w-48 h-48 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-24 -right-24 w-48 h-48 bg-violet-500/20 rounded-full blur-3xl pointer-events-none" />

                {/* Shield Icon */}
                <div className="relative inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-red-500/20 border border-amber-500/30 text-amber-400 mb-6 shadow-lg shadow-amber-500/10">
                    <FaLock size={32} />
                    <span className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-slate-950 font-bold text-xs shadow">
                        !
                    </span>
                </div>

                <h1 className="text-2xl font-bold tracking-tight text-white mb-2">
                    Access Denied
                </h1>

                <p className="text-sm text-slate-300 mb-6 leading-relaxed">
                    You don't have the required permission to access <strong className="text-amber-300 font-semibold">{permission_name || required_permission}</strong> under the <span className="text-indigo-300 font-semibold">{module_name}</span> module.
                </p>

                {/* Info Card */}
                <div className="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3.5 mb-6 text-left space-y-1.5 text-xs font-mono">
                    <div className="flex justify-between">
                        <span className="text-slate-400">Module:</span>
                        <span className="text-indigo-300 font-semibold">{module_name}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-slate-400">Permission:</span>
                        <span className="text-amber-400 font-bold">{required_permission}</span>
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        onClick={handleOpenModal}
                        className="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white shadow-lg shadow-indigo-600/30 transition transform active:scale-98"
                    >
                        <FaPaperPlane size={14} />
                        Request Permission from Super Admin
                    </button>

                    <Link
                        href={getDashboardUrl()}
                        className="w-full flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-semibold bg-slate-700/60 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-600/60 transition"
                    >
                        <FaArrowLeft size={12} />
                        Return to Dashboard
                    </Link>
                </div>
            </div>

            {/* Request Permission Modal */}
            <Modal
                show={requestModalOpen}
                onClose={() => setRequestModalOpen(false)}
                maxWidth="md"
                topCloseButton={true}
                handleTopClose={() => setRequestModalOpen(false)}
            >
                <div className="p-2 md:p-4 text-left">
                    <div className="flex items-center gap-3 border-b pb-3 mb-4 dark:border-gray-700">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                            <FaShieldAlt size={20} />
                        </div>
                        <div>
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                Request Permission Access
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Send an access request directly to Super Admin
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleSubmitRequest} className="space-y-4">
                        <div className="bg-slate-50 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-xs space-y-1">
                            <p className="text-slate-600 dark:text-slate-400">
                                <strong>Target Permission:</strong> {permission_name} ({required_permission})
                            </p>
                            <p className="text-slate-600 dark:text-slate-400">
                                <strong>Module:</strong> {module_name}
                            </p>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Reason for Request <span className="text-red-500">*</span>
                            </label>
                            <textarea
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                rows={3}
                                required
                                placeholder="Explain why you need access to this feature..."
                                className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white p-3 focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>

                        <div className="flex items-center justify-end gap-3 border-t pt-3 dark:border-gray-700">
                            <button
                                type="button"
                                onClick={() => setRequestModalOpen(false)}
                                className="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="flex items-center gap-2 px-5 py-2 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-md transition disabled:opacity-50"
                            >
                                <FaPaperPlane size={12} />
                                {isSubmitting ? "Sending Request..." : "Submit Request"}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    );
}
