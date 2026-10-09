import React, { useState } from 'react';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';
import SuperAdminCreateModal from '../Employees/Components/SuperAdminCreateModal';
import EmulateUserButton from '@/Components/EmulateUserButton';
import {
    ShieldCheck,
    UserCheck,
    UserPlus,
    Search,
    Mail,
    Phone,
    UserX,
    Check,
    AlertCircle,
    Shield,
    Key,
    Edit3,
    Building,
} from 'lucide-react';

export default function SuperAdminsIndex({ superAdmins, admins, constructionRoles = [], filters }) {
    const { auth } = usePage().props;
    const currentUser = auth?.user;
    const isProjectOwner = auth?.guard === 'superadmin' && (currentUser?.is_project_owner == 1 || (currentUser && (currentUser.id == 1 || currentUser.id === '1')));

    const [search, setSearch] = useState(filters?.search || '');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState(isProjectOwner ? 'superadmin' : 'admin'); // 'superadmin' or 'admin'

    // Change Password Modal State
    const [passwordModalUser, setPasswordModalUser] = useState(null);
    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    // Change Role Modal State
    const [roleModalUser, setRoleModalUser] = useState(null);
    const roleForm = useForm({
        role_slug: 'admin',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('super.super_admins.index'),
            { search },
            { preserveState: true }
        );
    };

    const handleToggleSuperAdminStatus = (id) => {
        router.post(route('super.super_admins.status_superadmin', id));
    };

    const handleToggleAdminStatus = (id) => {
        router.post(route('super.super_admins.status_admin', id));
    };

    const openPasswordModal = (user, targetType) => {
        setPasswordModalUser({ user, targetType });
        passwordForm.reset();
    };

    const handlePasswordSubmit = (e) => {
        e.preventDefault();
        if (!passwordModalUser) return;
        const { user, targetType } = passwordModalUser;
        const routeName = targetType === 'superadmin'
            ? route('super.super_admins.password_superadmin', user.id)
            : route('super.super_admins.password_admin', user.id);

        passwordForm.post(routeName, {
            onSuccess: () => setPasswordModalUser(null),
        });
    };

    const openRoleModal = (user, targetType) => {
        const currentSlug = user.assigned_roles?.[0] || (targetType === 'superadmin' ? 'super_admin' : 'admin');
        setRoleModalUser({ user, targetType });
        roleForm.setData('role_slug', currentSlug);
    };

    const handleRoleSubmit = (e) => {
        e.preventDefault();
        if (!roleModalUser) return;
        const { user, targetType } = roleModalUser;
        const routeName = targetType === 'superadmin'
            ? route('super.super_admins.role_superadmin', user.id)
            : route('super.super_admins.role_admin', user.id);

        roleForm.post(routeName, {
            onSuccess: () => setRoleModalUser(null),
        });
    };

    const defaultRoleOptions = [
        { slug: 'admin', name: 'Admin / Project Admin' },
        { slug: 'super_admin', name: 'Super Admin' },
        { slug: 'surveyor', name: 'Surveyor / Survey Man' },
        { slug: 'draft_person', name: 'Draft Person / Draft Man' },
        { slug: 'vehicle_driver', name: 'Vehicle Driver / Driver' },
        { slug: 'site_employee', name: 'Site Employee' },
    ];

    const availableRoles = constructionRoles.length > 0
        ? constructionRoles.map(r => ({ slug: r.slug, name: r.name }))
        : defaultRoleOptions;

    const pageTitle = isProjectOwner ? "Super Admin & Admin Accounts" : "Admin Accounts";

    return (
        <AuthenticatedLayout title={pageTitle}>
            <Head title={pageTitle} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-amber-100 p-2.5 rounded-xl text-amber-800">
                            <ShieldCheck className="w-6 h-6" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900">
                                {isProjectOwner ? "Admin Account Management" : "Admin Management"}
                            </h1>
                            <p className="text-sm text-slate-500 mt-0.5">
                                {isProjectOwner
                                    ? "Manage Super Admin & Admin users, permissions and emulation."
                                    : "Manage Admin users, permissions and roles."}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('super.role.list')}
                            className="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold px-4 py-2.5 rounded-xl border border-slate-300 transition-all text-xs"
                        >
                            <Shield className="w-4 h-4 text-indigo-600" /> Role Management
                        </Link>
                        <button
                            onClick={() => setIsCreateModalOpen(true)}
                            className="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-bold px-4 py-2.5 rounded-xl shadow-md transition-all text-xs"
                        >
                            <UserPlus className="w-4 h-4" /> Create Admin Account
                        </button>
                    </div>
                </div>

                {/* Search & Tabs */}
                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div className="flex items-center gap-2 bg-slate-100 p-1 rounded-xl w-full md:w-auto">
                        {isProjectOwner && (
                            <button
                                onClick={() => setActiveTab('superadmin')}
                                className={`px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-2 ${
                                    activeTab === 'superadmin' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <ShieldCheck className="w-4 h-4" /> Super Admins ({superAdmins?.length || 0})
                            </button>
                        )}
                        <button
                            onClick={() => setActiveTab('admin')}
                            className={`px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-2 ${
                                activeTab === 'admin' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            <UserCheck className="w-4 h-4" /> Admins ({admins?.length || 0})
                        </button>
                    </div>

                    <form onSubmit={handleSearch} className="flex gap-2 w-full md:w-80">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by name, email, username..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-1.5 text-xs text-slate-900 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                            />
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-600">
                            <thead className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">User</th>
                                    <th className="px-6 py-3.5">Company</th>
                                    <th className="px-6 py-3.5">Role</th>
                                    <th className="px-6 py-3.5">Contact</th>
                                    <th className="px-6 py-3.5">Created Date</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {activeTab === 'superadmin' && isProjectOwner ? (
                                    superAdmins?.length > 0 ? (
                                        superAdmins.map((user) => (
                                            <tr key={user.id} className="hover:bg-slate-50/80 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <img
                                                            src={user.profile_photo_url || '/images/profileimg.png'}
                                                            alt={user.name}
                                                            className="w-9 h-9 rounded-full object-cover border border-amber-200"
                                                        />
                                                        <div>
                                                            <div className="font-semibold text-slate-900">{user.name}</div>
                                                            <div className="text-xs text-slate-400">@{user.username || 'superadmin'}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    {user.company?.name ? (
                                                        <div>
                                                            <div className="font-semibold text-slate-900 flex items-center gap-1.5 text-xs">
                                                                <Building className="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                                                <span>{user.company.name}</span>
                                                            </div>
                                                            {user.company.legal_name && (
                                                                <div className="text-[11px] text-slate-500">{user.company.legal_name}</div>
                                                            )}
                                                        </div>
                                                    ) : user.company_name ? (
                                                        <div className="font-semibold text-slate-900 flex items-center gap-1.5 text-xs">
                                                            <Building className="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                                            <span>{user.company_name}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-slate-400 italic">No Company</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <button
                                                        type="button"
                                                        onClick={() => openRoleModal(user, 'superadmin')}
                                                        title="Click to Change Role"
                                                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200 transition-all cursor-pointer group"
                                                    >
                                                        <ShieldCheck className="w-3.5 h-3.5 text-amber-700" />
                                                        <span>Super Admin</span>
                                                        <Edit3 className="w-3 h-3 text-amber-600 opacity-60 group-hover:opacity-100" />
                                                    </button>
                                                </td>
                                                <td className="px-6 py-4 text-xs">
                                                    <div className="flex items-center gap-1.5 text-slate-700">
                                                        <Mail className="w-3.5 h-3.5 text-slate-400" /> {user.email}
                                                    </div>
                                                    {user.phone && (
                                                        <div className="flex items-center gap-1.5 text-slate-500 mt-0.5">
                                                            <Phone className="w-3.5 h-3.5 text-slate-400" /> {user.phone}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-xs text-slate-500">
                                                    {user.received_at || '--'}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <button
                                                        onClick={() => handleToggleSuperAdminStatus(user.id)}
                                                        className={`inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full ${
                                                            Number(user.status) === 1
                                                                ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                                                : 'bg-rose-100 text-rose-800 border border-rose-200'
                                                        }`}
                                                    >
                                                        {Number(user.status) === 1 ? <Check className="w-3 h-3" /> : <UserX className="w-3 h-3" />}
                                                        {Number(user.status) === 1 ? 'Active' : 'Inactive'}
                                                    </button>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => openPasswordModal(user, 'superadmin')}
                                                            title="Change Password"
                                                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 border border-slate-200 transition-colors"
                                                        >
                                                            <Key className="w-3.5 h-3.5" />
                                                        </button>

                                                        <button
                                                            type="button"
                                                            onClick={() => openRoleModal(user, 'superadmin')}
                                                            title="Change Role"
                                                            className="p-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200 transition-colors"
                                                        >
                                                            <Shield className="w-3.5 h-3.5" />
                                                        </button>

                                                        <EmulateUserButton targetType="superadmin" targetId={user.id} userName={user.name} />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                         <tr>
                                            <td colSpan="7" className="px-6 py-10 text-center text-slate-400">
                                                No Super Admin accounts found.
                                            </td>
                                        </tr>
                                    )
                                ) : (
                                    admins?.length > 0 ? (
                                        admins.map((user) => {
                                            const roleName = user.assigned_role_names?.[0] || 'Admin';
                                            return (
                                                <tr key={user.id} className="hover:bg-slate-50/80 transition-colors">
                                                    <td className="px-6 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <img
                                                                src={user.profile_photo_url || '/images/profileimg.png'}
                                                                alt={user.name}
                                                                className="w-9 h-9 rounded-full object-cover border border-indigo-200"
                                                            />
                                                            <div>
                                                                <div className="font-semibold text-slate-900">{user.name}</div>
                                                                <div className="text-xs text-slate-400">@{user.username || 'admin'}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        {user.company?.name ? (
                                                            <div>
                                                                <div className="font-semibold text-slate-900 flex items-center gap-1.5 text-xs">
                                                                    <Building className="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                                                                    <span>{user.company.name}</span>
                                                                </div>
                                                                {user.company.legal_name && (
                                                                    <div className="text-[11px] text-slate-500">{user.company.legal_name}</div>
                                                                )}
                                                            </div>
                                                        ) : user.company_name ? (
                                                            <div className="font-semibold text-slate-900 flex items-center gap-1.5 text-xs">
                                                                <Building className="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                                                                <span>{user.company_name}</span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-slate-400 italic">
                                                                No Company
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <button
                                                            type="button"
                                                            onClick={() => openRoleModal(user, 'admin')}
                                                            title="Click to Change Role"
                                                            className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-300 hover:bg-indigo-200 transition-all cursor-pointer group"
                                                        >
                                                            <UserCheck className="w-3.5 h-3.5 text-indigo-700" />
                                                            <span>{roleName}</span>
                                                            <Edit3 className="w-3 h-3 text-indigo-600 opacity-60 group-hover:opacity-100" />
                                                        </button>
                                                    </td>
                                                    <td className="px-6 py-4 text-xs">
                                                        <div className="flex items-center gap-1.5 text-slate-700">
                                                            <Mail className="w-3.5 h-3.5 text-slate-400" /> {user.email}
                                                        </div>
                                                        {user.phone && (
                                                            <div className="flex items-center gap-1.5 text-slate-500 mt-0.5">
                                                                <Phone className="w-3.5 h-3.5 text-slate-400" /> {user.phone}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-xs text-slate-500">
                                                        {user.created_at ? new Date(user.created_at).toLocaleDateString() : '--'}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <button
                                                            onClick={() => handleToggleAdminStatus(user.id)}
                                                            className={`inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full ${
                                                                Number(user.status) === 1
                                                                    ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                                                    : 'bg-rose-100 text-rose-800 border border-rose-200'
                                                            }`}
                                                        >
                                                            {Number(user.status) === 1 ? <Check className="w-3 h-3" /> : <UserX className="w-3 h-3" />}
                                                            {Number(user.status) === 1 ? 'Active' : 'Inactive'}
                                                        </button>
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() => openPasswordModal(user, 'admin')}
                                                                title="Change Password"
                                                                className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 border border-slate-200 transition-colors"
                                                            >
                                                                <Key className="w-3.5 h-3.5" />
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => openRoleModal(user, 'admin')}
                                                                title="Change Role"
                                                                className="p-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200 transition-colors"
                                                            >
                                                                <Shield className="w-3.5 h-3.5" />
                                                            </button>

                                                            <EmulateUserButton targetType="admin" targetId={user.id} userName={user.name} />
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="7" className="px-6 py-10 text-center text-slate-400">
                                                No Admin accounts found.
                                            </td>
                                        </tr>
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Create Admin Modal */}
            <SuperAdminCreateModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                constructionRoles={constructionRoles}
                isProjectOwner={isProjectOwner}
            />

            {/* Change Password Modal */}
            {passwordModalUser && (
                <div className="fixed inset-0 z-[9999] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white text-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5 animate-in fade-in zoom-in duration-200">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-3">
                                <div className="bg-amber-100 p-2.5 rounded-xl text-amber-800">
                                    <Key className="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold text-slate-900">Change Password</h3>
                                    <p className="text-xs text-slate-500">Update password for {passwordModalUser.user.name}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setPasswordModalUser(null)}
                                className="text-slate-400 hover:text-slate-600 text-sm font-bold p-1 rounded-lg hover:bg-slate-100"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handlePasswordSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">New Password *</label>
                                <input
                                    type="password"
                                    required
                                    value={passwordForm.data.password}
                                    onChange={(e) => passwordForm.setData('password', e.target.value)}
                                    placeholder="Enter new password"
                                    className="w-full px-3 py-2 text-xs text-slate-900 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                                />
                                {passwordForm.errors.password && <p className="text-xs text-rose-500 mt-1">{passwordForm.errors.password}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">Confirm New Password *</label>
                                <input
                                    type="password"
                                    required
                                    value={passwordForm.data.password_confirmation}
                                    onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                    placeholder="Confirm new password"
                                    className="w-full px-3 py-2 text-xs text-slate-900 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setPasswordModalUser(null)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 bg-slate-100 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={passwordForm.processing}
                                    className="px-5 py-2 text-xs font-bold text-white rounded-xl shadow-md bg-indigo-600 hover:bg-indigo-700 transition-all"
                                >
                                    {passwordForm.processing ? 'Updating...' : 'Update Password'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Change Role Modal */}
            {roleModalUser && (
                <div className="fixed inset-0 z-[9999] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white text-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5 animate-in fade-in zoom-in duration-200">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-3">
                                <div className="bg-indigo-100 p-2.5 rounded-xl text-indigo-800">
                                    <Shield className="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold text-slate-900">Change Role</h3>
                                    <p className="text-xs text-slate-500">Assign new role to {roleModalUser.user.name}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setRoleModalUser(null)}
                                className="text-slate-400 hover:text-slate-600 text-sm font-bold p-1 rounded-lg hover:bg-slate-100"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleRoleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">Select Role *</label>
                                <select
                                    value={roleForm.data.role_slug}
                                    onChange={(e) => roleForm.setData('role_slug', e.target.value)}
                                    className="w-full px-3 py-2 text-xs text-slate-900 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 font-medium"
                                >
                                    {availableRoles.map((role) => (
                                        <option key={role.slug} value={role.slug}>
                                            {role.name}
                                        </option>
                                    ))}
                                </select>
                                {roleForm.errors.role_slug && <p className="text-xs text-rose-500 mt-1">{roleForm.errors.role_slug}</p>}
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setRoleModalUser(null)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 bg-slate-100 rounded-xl"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={roleForm.processing}
                                    className="px-5 py-2 text-xs font-bold text-white rounded-xl shadow-md bg-indigo-600 hover:bg-indigo-700 transition-all"
                                >
                                    {roleForm.processing ? 'Updating...' : 'Assign Role'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

