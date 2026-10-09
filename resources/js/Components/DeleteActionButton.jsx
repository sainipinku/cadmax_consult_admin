import React, { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { Trash2, ShieldAlert, Clock } from 'lucide-react';

export default function DeleteActionButton({
    resourceType,
    resourceId,
    resourceName,
    onDelete,
    className = "",
    asDropdownItem = false,
    onSuccess,
}) {
    const pageProps = usePage().props;
    const auth = pageProps?.auth;
    const user = auth?.user;
    const guard = auth?.guard;
    const isSuperAdmin = 
        guard === "superadmin" ||
        user?.email === "superadmin@gmail.com" ||
        Boolean(user?.is_super_admin) || 
        user?.slug === 'super-admin' || 
        user?.slug === 'super_admin' || 
        (Array.isArray(user?.assigned_roles) && (user.assigned_roles.includes("super_admin") || user.assigned_roles.includes("superadmin"))) || 
        (Array.isArray(user?.roles) && (user.roles.includes("super_admin") || user.roles.includes("superadmin")));
    const permissions = auth?.permissions || auth?.construction_permissions || pageProps?.permissions || [];
    const deleteRequests = pageProps?.delete_approval_requests || {};

    const hasDeletePerm = () => {
        if (isSuperAdmin) return true;
        const resType = String(resourceType).toLowerCase();
        return (
            permissions.includes(`${resType}.delete`) ||
            permissions.includes(`${resType}.manage`) ||
            permissions.includes("approval_request.create") ||
            permissions.includes("approval_request.manage")
        );
    };

    if (!isSuperAdmin && !hasDeletePerm()) {
        return null;
    }

    const keyWithUuid = resourceId ? `${String(resourceType).toLowerCase()}_${String(resourceId)}` : '';
    const requestInfo = deleteRequests[keyWithUuid];

    const status = requestInfo ? Number(requestInfo.status) : null;
    // status: null = No request, 0 = Pending, 1 = Approved, 2 = Rejected

    const [showModal, setShowModal] = useState(false);
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleRequestSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        router.post(route('super.approval_requests.submit_delete'), {
            resource_type: resourceType,
            resource_id: resourceId,
            resource_name: resourceName || `${resourceType} #${resourceId}`,
            reason: reason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowModal(false);
                setReason('');
                setIsSubmitting(false);
                if (onSuccess) onSuccess();
            },
            onError: () => setIsSubmitting(false),
        });
    };

    // If Super Admin OR request is APPROVED (status === 1):
    // SHOW the DELETE button! HIDE the Request Delete button!
    if (isSuperAdmin || status === 1) {
        if (asDropdownItem) {
            return (
                <li className="flex items-center gap-[5px] p-2 text-[12px] text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 cursor-pointer border-b border-b-[#f2f2f2] dark:border-b-gray-700">
                    <button
                        type="button"
                        onClick={() => onDelete && onDelete()}
                        className="flex items-center gap-[8px] w-full text-red-600 font-semibold"
                    >
                        <Trash2 className="w-[16px] h-[16px]" />
                        Delete
                    </button>
                </li>
            );
        }

        return (
            <button
                type="button"
                onClick={() => onDelete && onDelete()}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition ${className}`}
            >
                <Trash2 className="w-3.5 h-3.5" /> Delete
            </button>
        );
    }

    // If Admin AND status === 0 (PENDING):
    // HIDE Delete button, HIDE Request Delete button, SHOW "Delete Request Pending"
    if (status === 0) {
        if (asDropdownItem) {
            return (
                <li className="p-2 text-[12px] text-amber-700 bg-amber-50 dark:bg-amber-950/40 rounded border border-amber-200 dark:border-amber-800">
                    <div className="flex items-center gap-1.5 font-medium">
                        <Clock className="w-[14px] h-[14px] shrink-0 text-amber-600 animate-pulse" />
                        Delete Request Pending
                    </div>
                </li>
            );
        }

        return (
            <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200 ${className}`}>
                <Clock className="w-3.5 h-3.5 text-amber-600 animate-pulse" /> Delete Request Pending
            </span>
        );
    }

    // If Admin AND no request or REJECTED:
    // SHOW "Request Delete" button! HIDE Delete button!
    return (
        <>
            {asDropdownItem ? (
                <li className="flex items-center gap-[5px] p-2 text-[12px] text-amber-800 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/30 cursor-pointer border-b border-b-[#f2f2f2] dark:border-b-gray-700">
                    <button
                        type="button"
                        onClick={() => setShowModal(true)}
                        className="flex items-center gap-[8px] w-full font-medium"
                    >
                        <ShieldAlert className="w-[16px] h-[16px] text-amber-600" />
                        {status === 2 ? 'Re-request Delete' : 'Request Delete'}
                    </button>
                </li>
            ) : (
                <button
                    type="button"
                    onClick={() => setShowModal(true)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 transition ${className}`}
                >
                    <ShieldAlert className="w-3.5 h-3.5 text-amber-600" />
                    {status === 2 ? 'Re-request Delete' : 'Request Delete'}
                </button>
            )}

            {showModal && (
                <div className="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#080626] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 text-slate-900 dark:text-white animate-in fade-in zoom-in duration-150">
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                                <ShieldAlert className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold">Request Delete Permission</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Target: <span className="font-semibold">{resourceName || `${resourceType} #${resourceId}`}</span>
                                </p>
                            </div>
                        </div>

                        <p className="text-xs text-slate-600 dark:text-slate-300">
                            As an Admin, deleting this item requires Super Admin approval. Submit a request, and Super Admin will review it. Once approved, the <strong>Delete</strong> button will be unlocked for you.
                        </p>

                        <form onSubmit={handleRequestSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Reason for Deletion (Optional)
                                </label>
                                <textarea
                                    rows="3"
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    placeholder="Enter reason for deleting this item..."
                                    className="w-full text-xs p-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-medium"
                                ></textarea>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-1">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-md transition-all disabled:opacity-50"
                                >
                                    {isSubmitting ? 'Submitting...' : 'Submit Request'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}
