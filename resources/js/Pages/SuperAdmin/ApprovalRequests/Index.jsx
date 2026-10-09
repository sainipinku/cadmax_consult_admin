import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';
import {
    CheckCircle2,
    XCircle,
    Clock,
    Search,
    ShieldAlert,
    User,
    Trash2,
    FileText,
    Filter,
    MessageSquare,
    AlertCircle,
    Shield,
    Key
} from 'lucide-react';

export default function ApprovalRequestsIndex({ requests, counts, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || 'all');
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [actionType, setActionType] = useState(null); // 'approve' or 'reject'

    const { data, setData, post, processing, reset, errors } = useForm({
        admin_remark: '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('super.approval_requests.index'),
            { search, status: statusFilter },
            { preserveState: true }
        );
    };

    const handleFilterChange = (status) => {
        setStatusFilter(status);
        router.get(
            route('super.approval_requests.index'),
            { search, status },
            { preserveState: true }
        );
    };

    const openActionModal = (req, type) => {
        setSelectedRequest(req);
        setActionType(type);
        setData('admin_remark', type === 'approve' ? 'Approved by Super Admin.' : 'Rejected by Super Admin.');
    };

    const closeModal = () => {
        setSelectedRequest(null);
        setActionType(null);
        reset();
    };

    const handleConfirmAction = (e) => {
        e.preventDefault();
        if (!selectedRequest || !actionType) return;

        const routeName = actionType === 'approve'
            ? route('super.approval_requests.approve', selectedRequest.id)
            : route('super.approval_requests.reject', selectedRequest.id);

        post(routeName, {
            onSuccess: () => closeModal(),
        });
    };

    const getStatusBadge = (status) => {
        switch (Number(status)) {
            case 0:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                        <Clock className="w-3.5 h-3.5" /> Pending Approval
                    </span>
                );
            case 1:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <CheckCircle2 className="w-3.5 h-3.5" /> Approved
                    </span>
                );
            case 2:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-200">
                        <XCircle className="w-3.5 h-3.5" /> Rejected
                    </span>
                );
            default:
                return null;
        }
    };

    return (
        <AuthenticatedLayout title="Permission & Access Approval Queue">
            <Head title="Permission & Access Approval Queue" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header Banner */}
                <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="bg-amber-100 p-2 rounded-xl text-amber-700">
                                <ShieldAlert className="w-6 h-6" />
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900">Permission & Access Approval Requests</h1>
                                <p className="text-sm text-slate-500 mt-0.5">
                                    Review and process permission access & deletion requests submitted by users and admins.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Quick Stats Tabs */}
                    <div className="flex items-center gap-2 bg-slate-100 p-1.5 rounded-xl border border-slate-200">
                        <button
                            onClick={() => handleFilterChange('all')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                statusFilter === 'all'
                                    ? 'bg-white text-slate-900 shadow-sm font-semibold'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            All ({counts?.all || 0})
                        </button>
                        <button
                            onClick={() => handleFilterChange('0')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                statusFilter === '0'
                                    ? 'bg-amber-500 text-white shadow-sm font-semibold'
                                    : 'text-amber-700 hover:bg-amber-50'
                            }`}
                        >
                            Pending ({counts?.pending || 0})
                        </button>
                        <button
                            onClick={() => handleFilterChange('1')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                statusFilter === '1'
                                    ? 'bg-emerald-600 text-white shadow-sm font-semibold'
                                    : 'text-emerald-700 hover:bg-emerald-50'
                            }`}
                        >
                            Approved ({counts?.approved || 0})
                        </button>
                        <button
                            onClick={() => handleFilterChange('2')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                statusFilter === '2'
                                    ? 'bg-rose-600 text-white shadow-sm font-semibold'
                                    : 'text-rose-700 hover:bg-rose-50'
                            }`}
                        >
                            Rejected ({counts?.rejected || 0})
                        </button>
                    </div>
                </div>

                {/* Filter & Search Bar */}
                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <form onSubmit={handleSearch} className="flex gap-3">
                        <div className="relative flex-1">
                            <Search className="absolute left-3.5 top-3 w-4 h-4 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by Requester, Permission, Resource Name, Type or Reason..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-10 pr-4 py-2 text-sm text-slate-900 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-medium"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 transition-colors"
                        >
                            Search
                        </button>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-600">
                            <thead className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Requester User</th>
                                    <th className="px-6 py-3.5">Request Type</th>
                                    <th className="px-6 py-3.5">Target Resource / Permission</th>
                                    <th className="px-6 py-3.5">Reason</th>
                                    <th className="px-6 py-3.5">Date Requested</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {requests?.data?.length > 0 ? (
                                    requests.data.map((req) => {
                                        const isPermissionType = req.action === 'permission';
                                        return (
                                            <tr key={req.id} className="hover:bg-slate-50/80 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div className="font-semibold text-slate-900">{req.requester_name}</div>
                                                    <div className="text-xs text-slate-400">{req.requester_email || 'User'}</div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    {isPermissionType ? (
                                                        <div className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                            <Key className="w-3 h-3" /> PERMISSION ACCESS
                                                        </div>
                                                    ) : (
                                                        <div className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                                            <Trash2 className="w-3 h-3" /> {req.action?.toUpperCase()}
                                                        </div>
                                                    )}
                                                    <div className="text-xs font-medium text-slate-700 mt-1">
                                                        {req.resource_type}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="font-medium text-slate-900">{req.resource_name || `#${req.resource_id}`}</div>
                                                    <div className="text-xs font-mono text-slate-400">{req.resource_id}</div>
                                                </td>
                                                <td className="px-6 py-4 max-w-xs truncate">
                                                    <span className="text-slate-600 text-xs" title={req.reason}>
                                                        {req.reason || 'No reason specified'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                                                    {req.formatted_created_at}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {getStatusBadge(req.status)}
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    {Number(req.status) === 0 ? (
                                                        <div className="flex items-center justify-end gap-2">
                                                            <button
                                                                onClick={() => openActionModal(req, 'approve')}
                                                                className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-colors flex items-center gap-1"
                                                            >
                                                                <CheckCircle2 className="w-3.5 h-3.5" /> Approve
                                                            </button>
                                                            <button
                                                                onClick={() => openActionModal(req, 'reject')}
                                                                className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-rose-100 text-slate-700 hover:text-rose-700 border border-slate-200 transition-colors flex items-center gap-1"
                                                            >
                                                                <XCircle className="w-3.5 h-3.5" /> Reject
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-slate-400 italic">
                                                            {req.admin_remark || 'Processed'}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan="7" className="px-6 py-12 text-center text-slate-400">
                                            <AlertCircle className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                            No permission approval requests found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Action Confirmation Modal */}
            {selectedRequest && actionType && (
                <div className="fixed inset-0 z-[999] bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-5 animate-in fade-in zoom-in duration-200">
                        <div className="flex items-center gap-3">
                            <div className={`p-2.5 rounded-xl ${actionType === 'approve' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>
                                {actionType === 'approve' ? <CheckCircle2 className="w-6 h-6" /> : <XCircle className="w-6 h-6" />}
                            </div>
                            <div>
                                <h3 className="text-lg font-bold text-slate-900">
                                    {actionType === 'approve' ? (selectedRequest.action === 'permission' ? 'Approve Permission Request' : 'Approve Delete Request') : 'Reject Request'}
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Requester: <span className="font-semibold text-slate-700">{selectedRequest.requester_name}</span>
                                </p>
                            </div>
                        </div>

                        <div className="bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs space-y-1">
                            <div className="font-medium text-slate-700">Type: {selectedRequest.action?.toUpperCase()} ({selectedRequest.resource_type})</div>
                            <div className="text-slate-900 font-bold text-sm">{selectedRequest.resource_name}</div>
                            {actionType === 'approve' && selectedRequest.action === 'permission' && (
                                <p className="text-indigo-600 font-medium pt-1">
                                    ✓ Approving will grant this permission to the user's role.
                                </p>
                            )}
                            {actionType === 'approve' && selectedRequest.action === 'delete' && (
                                <p className="text-rose-600 font-medium pt-1">
                                    ⚠️ Approving this request will allow deletion of this record.
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                Super Admin Remark / Feedback
                            </label>
                            <textarea
                                rows="3"
                                value={data.admin_remark}
                                onChange={(e) => setData('admin_remark', e.target.value)}
                                placeholder="Enter remark or feedback..."
                                className="w-full text-xs text-slate-900 bg-white p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-medium"
                            ></textarea>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-2">
                            <button
                                type="button"
                                onClick={closeModal}
                                className="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 bg-slate-100 rounded-xl"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleConfirmAction}
                                disabled={processing}
                                className={`px-5 py-2 text-xs font-bold text-white rounded-xl shadow-md transition-all ${
                                    actionType === 'approve'
                                        ? 'bg-emerald-600 hover:bg-emerald-700'
                                        : 'bg-rose-600 hover:bg-rose-700'
                                }`}
                            >
                                {processing ? 'Processing...' : actionType === 'approve' ? 'Confirm & Grant' : 'Confirm Rejection'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
