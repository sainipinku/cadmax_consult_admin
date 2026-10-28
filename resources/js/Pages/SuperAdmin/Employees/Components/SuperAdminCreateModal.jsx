import React, { useState, useEffect } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { UserCheck, Key, Mail, Phone, User, Shield, Building, ShieldCheck, MapPin, FileText, Eye, EyeOff } from 'lucide-react';

export default function SuperAdminCreateModal({ isOpen, onClose, constructionRoles = [], isProjectOwner: isProjectOwnerProp }) {
    const { auth } = usePage().props;
    const currentUser = auth?.user;
    const isProjectOwner = isProjectOwnerProp ?? (currentUser?.is_project_owner == 1 || (currentUser && (currentUser.id == 1 || currentUser.id === '1')));

    const [accountType, setAccountType] = useState(isProjectOwner ? 'superadmin' : 'admin'); // 'superadmin' or 'admin'
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
    const [localErrors, setLocalErrors] = useState({});

    useEffect(() => {
        if (!isProjectOwner) {
            setAccountType('admin');
        }
    }, [isProjectOwner]);

    useEffect(() => {
        if (!isOpen) {
            setShowPassword(false);
            setShowPasswordConfirmation(false);
            setLocalErrors({});
        }
    }, [isOpen]);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        // User Details
        name: '',
        email: '',
        username: '',
        phone: '',
        role_slug: 'admin',
        password: '',
        password_confirmation: '',
        
        // Merged Company Details (for Super Admin)
        company_name: '',
        company_legal_name: '',
        company_email: '',
        company_phone: '',
        company_gst_number: '',
        company_address: '',
    });

    if (!isOpen) return null;

    const handleAccountTypeChange = (type) => {
        setAccountType(type);
        setShowPassword(false);
        setShowPasswordConfirmation(false);
        setLocalErrors({});
        clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const newErrors = {};
        const cleanedPhone = data.phone ? data.phone.replace(/\D/g, '') : '';
        const cleanedCompanyPhone = data.company_phone ? data.company_phone.replace(/\D/g, '') : '';

        if (cleanedPhone && cleanedPhone.length !== 10) {
            newErrors.phone = 'Phone number must be exactly 10 digits (min and max 10 digits).';
        }

        if (accountType === 'superadmin' && cleanedCompanyPhone && cleanedCompanyPhone.length !== 10) {
            newErrors.company_phone = 'Company phone must be exactly 10 digits (min and max 10 digits).';
        }

        if (Object.keys(newErrors).length > 0) {
            setLocalErrors(newErrors);
            return;
        }

        setLocalErrors({});
        const routeName = accountType === 'superadmin'
            ? route('super.super_admins.store_superadmin')
            : route('super.super_admins.store_admin');

        post(routeName, {
            onSuccess: () => {
                reset();
                setLocalErrors({});
                onClose();
            },
        });
    };

    const inputClasses = "w-full pl-9 pr-3 py-2.5 text-xs text-slate-900 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600 placeholder:text-slate-400 font-medium";

    const defaultRoleOptions = [
        { slug: 'admin', name: 'Admin' },
        { slug: 'project_admin', name: 'Project Admin' },
        { slug: 'surveyor', name: 'Surveyor / Survey Man' },
        { slug: 'draft_person', name: 'Draft Person / Draft Man' },
        { slug: 'vehicle_driver', name: 'Vehicle Driver / Driver' },
        { slug: 'site_employee', name: 'Site Employee' },
    ];

    const availableRoles = constructionRoles.length > 0
        ? constructionRoles
            .filter(r => r.slug !== 'super_admin' && r.slug !== 'superadmin')
            .map(r => ({ slug: r.slug, name: r.name }))
        : defaultRoleOptions;

    return (
        <div className="fixed inset-0 z-[9999] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
            <div className="bg-white text-slate-900 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8 max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in duration-200">
                {/* Modal Header */}
                <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div className="flex items-center gap-3">
                        <div className={`p-2.5 rounded-xl ${accountType === 'superadmin' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'}`}>
                            {accountType === 'superadmin' ? <ShieldCheck className="w-6 h-6" /> : <UserCheck className="w-6 h-6" />}
                        </div>
                        <div>
                            <h3 className="text-lg font-bold text-slate-900">
                                {accountType === 'superadmin' ? 'Create Super Admin & Company' : 'Create Admin Account'}
                            </h3>
                            <p className="text-xs text-slate-500">
                                {accountType === 'superadmin'
                                    ? 'Creates a new Super Admin account along with their dedicated company in one flow.'
                                    : 'Create new Admin account credentials and assign role.'}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-slate-400 hover:text-slate-600 text-sm font-bold p-1 rounded-lg hover:bg-slate-100"
                    >
                        ✕
                    </button>
                </div>

                {/* Account Type Selector */}
                {isProjectOwner && (
                    <div className="grid grid-cols-2 gap-3 p-1 bg-slate-100 rounded-xl">
                        <button
                            type="button"
                            onClick={() => handleAccountTypeChange('superadmin')}
                            className={`flex items-center justify-center gap-2 py-2 text-xs font-bold rounded-lg transition-all ${
                                accountType === 'superadmin'
                                    ? 'bg-amber-600 text-white shadow-md'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            <ShieldCheck className="w-4 h-4" /> Super Admin + Company
                        </button>
                        <button
                            type="button"
                            onClick={() => handleAccountTypeChange('admin')}
                            className={`flex items-center justify-center gap-2 py-2 text-xs font-bold rounded-lg transition-all ${
                                accountType === 'admin'
                                    ? 'bg-indigo-600 text-white shadow-md'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            <UserCheck className="w-4 h-4" /> Standard Admin
                        </button>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* User Information Section */}
                    <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                        <div className="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                            <User className="w-4 h-4 text-slate-500" /> Account Credentials
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            {/* Name */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">Full Name *</label>
                                <div className="relative">
                                    <User className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                    <input
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Enter full name"
                                        className={inputClasses}
                                    />
                                </div>
                                {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                            </div>

                            {/* Email */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">Email Address *</label>
                                <div className="relative">
                                    <Mail className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                    <input
                                        type="email"
                                        required
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="superadmin@example.com"
                                        className={inputClasses}
                                    />
                                </div>
                                {errors.email && <p className="text-xs text-rose-500 mt-1">{errors.email}</p>}
                            </div>

                            {/* Phone */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">Phone Number (10 digits)</label>
                                <div className="relative">
                                    <Phone className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                    <input
                                        type="tel"
                                        maxLength={10}
                                        minLength={10}
                                        pattern="[0-9]{10}"
                                        inputMode="numeric"
                                        value={data.phone}
                                        onChange={(e) => {
                                            const val = e.target.value.replace(/\D/g, '').slice(0, 10);
                                            setData('phone', val);
                                            if (errors.phone) clearErrors('phone');
                                            if (localErrors.phone) {
                                                setLocalErrors((prev) => {
                                                    const next = { ...prev };
                                                    delete next.phone;
                                                    return next;
                                                });
                                            }
                                        }}
                                        placeholder="Enter 10-digit phone number"
                                        className={inputClasses}
                                    />
                                </div>
                                {(localErrors.phone || errors.phone) && (
                                    <p className="text-xs text-rose-500 mt-1">{localErrors.phone || errors.phone}</p>
                                )}
                            </div>

                            {/* Username or Role */}
                            {accountType === 'superadmin' ? (
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Username</label>
                                    <div className="relative">
                                        <ShieldCheck className="absolute left-3 top-3 w-4 h-4 text-amber-500" />
                                        <input
                                            type="text"
                                            value={data.username}
                                            onChange={(e) => setData('username', e.target.value)}
                                            placeholder="superadmin_username"
                                            className={inputClasses}
                                        />
                                    </div>
                                    {errors.username && <p className="text-xs text-rose-500 mt-1">{errors.username}</p>}
                                </div>
                            ) : 

                            (
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Assign Role
                                    </label>

                                    <div className="relative">
                                        <Shield className="absolute left-3 top-3 w-4 h-4 text-indigo-500" />

                                        <input
                                            type="text"
                                            value="Project Admin"
                                            readOnly
                                            className={`${inputClasses} cursor-not-allowed`}
                                        />
                                    </div>
                                </div>
                            )
                            
                            // (
                            //     <div>
                            //         <label className="block text-xs font-semibold text-slate-700 mb-1">Assign Role *</label>
                            //         <div className="relative">
                            //             <Shield className="absolute left-3 top-3 w-4 h-4 text-indigo-500" />
                            //             <select
                            //                 value={data.role_slug}
                            //                 onChange={(e) => setData('role_slug', e.target.value)}
                            //                 className={inputClasses}
                            //             >
                            //                 {availableRoles.map((role) => (
                            //                     <option key={role.slug} value={role.slug}>
                            //                         {role.name}
                            //                     </option>
                            //                 ))}
                            //             </select>
                            //         </div>
                            //         {errors.role_slug && <p className="text-xs text-rose-500 mt-1">{errors.role_slug}</p>}
                            //     </div>
                            // )
                            
                            }
                        </div>

                        {/* Passwords */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">Password *</label>
                                <div className="relative">
                                    <Key className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        required
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="••••••••"
                                        className={`${inputClasses} pr-10`}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((visible) => !visible)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        title={showPassword ? 'Hide password' : 'Show password'}
                                    >
                                        {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                                {errors.password && <p className="text-xs text-rose-500 mt-1">{errors.password}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">Confirm Password *</label>
                                <div className="relative">
                                    <Key className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                    <input
                                        type={showPasswordConfirmation ? 'text' : 'password'}
                                        required
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        placeholder="••••••••"
                                        className={`${inputClasses} pr-10`}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPasswordConfirmation((visible) => !visible)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                                        aria-label={showPasswordConfirmation ? 'Hide confirm password' : 'Show confirm password'}
                                        title={showPasswordConfirmation ? 'Hide confirm password' : 'Show confirm password'}
                                    >
                                        {showPasswordConfirmation ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Merged Company Section for Super Admin */}
                    {accountType === 'superadmin' && (
                        <div className="bg-amber-50/60 p-4 rounded-xl border border-amber-200 space-y-3">
                            <div className="text-xs font-bold text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                                <Building className="w-4 h-4 text-amber-600" /> Company Details (Merged Flow)
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                {/* Company Name */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Company Name *</label>
                                    <div className="relative">
                                        <Building className="absolute left-3 top-3 w-4 h-4 text-amber-600" />
                                        <input
                                            type="text"
                                            required
                                            value={data.company_name}
                                            onChange={(e) => setData('company_name', e.target.value)}
                                            placeholder="e.g. CadMax Constructions"
                                            className={inputClasses}
                                        />
                                    </div>
                                    {errors.company_name && <p className="text-xs text-rose-500 mt-1">{errors.company_name}</p>}
                                </div>

                                {/* Legal Name */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Legal Name</label>
                                    <div className="relative">
                                        <FileText className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                        <input
                                            type="text"
                                            value={data.company_legal_name}
                                            onChange={(e) => setData('company_legal_name', e.target.value)}
                                            placeholder="e.g. CadMax Infra Private Limited"
                                            className={inputClasses}
                                        />
                                    </div>
                                    {errors.company_legal_name && <p className="text-xs text-rose-500 mt-1">{errors.company_legal_name}</p>}
                                </div>

                                {/* Company Email */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Company Email</label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                        <input
                                            type="email"
                                            value={data.company_email}
                                            onChange={(e) => setData('company_email', e.target.value)}
                                            placeholder="info@company.com"
                                            className={inputClasses}
                                        />
                                    </div>
                                    {errors.company_email && <p className="text-xs text-rose-500 mt-1">{errors.company_email}</p>}
                                </div>

                                {/* Company Phone */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Company Phone (10 digits)</label>
                                    <div className="relative">
                                        <Phone className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                        <input
                                            type="tel"
                                            maxLength={10}
                                            minLength={10}
                                            pattern="[0-9]{10}"
                                            inputMode="numeric"
                                            value={data.company_phone}
                                            onChange={(e) => {
                                                const val = e.target.value.replace(/\D/g, '').slice(0, 10);
                                                setData('company_phone', val);
                                                if (errors.company_phone) clearErrors('company_phone');
                                                if (localErrors.company_phone) {
                                                    setLocalErrors((prev) => {
                                                        const next = { ...prev };
                                                        delete next.company_phone;
                                                        return next;
                                                    });
                                                }
                                            }}
                                            placeholder="Enter 10-digit company phone"
                                            className={inputClasses}
                                        />
                                    </div>
                                    {(localErrors.company_phone || errors.company_phone) && (
                                        <p className="text-xs text-rose-500 mt-1">{localErrors.company_phone || errors.company_phone}</p>
                                    )}
                                </div>

                                {/* GST Number */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">GST Number</label>
                                    <div className="relative">
                                        <FileText className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                        <input
                                            type="text"
                                            value={data.company_gst_number}
                                            onChange={(e) => setData('company_gst_number', e.target.value)}
                                            placeholder="22AAAAA0000A1Z5"
                                            className={inputClasses}
                                        />
                                    </div>
                                    {errors.company_gst_number && <p className="text-xs text-rose-500 mt-1">{errors.company_gst_number}</p>}
                                </div>

                                {/* Address */}
                                <div className="md:col-span-2">
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Company Address</label>
                                    <div className="relative">
                                        <MapPin className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                        <input
                                            type="text"
                                            value={data.company_address}
                                            onChange={(e) => setData('company_address', e.target.value)}
                                            placeholder="Corporate address"
                                            className={inputClasses}
                                        />
                                    </div>
                                    {errors.company_address && <p className="text-xs text-rose-500 mt-1">{errors.company_address}</p>}
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 bg-slate-100 rounded-xl"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className={`px-5 py-2 text-xs font-bold text-white rounded-xl shadow-md transition-all ${
                                accountType === 'superadmin'
                                    ? 'bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800'
                                    : 'bg-indigo-600 hover:bg-indigo-700'
                            }`}
                        >
                            {processing
                                ? 'Creating...'
                                : accountType === 'superadmin'
                                ? 'Create Super Admin & Company'
                                : 'Create Admin Account'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
