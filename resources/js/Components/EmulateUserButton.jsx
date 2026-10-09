import React, { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { UserCheck } from 'lucide-react';
import ConfirmDialog from '@/Components/ConfirmDialog';

export default function EmulateUserButton({ targetType, targetId, userName, className = '' }) {
    const { auth } = usePage().props;
    const [showConfirm, setShowConfirm] = useState(false);

    const { post, processing } = useForm({
        target_type: targetType,
        target_id: targetId,
    });

    const isSuperAdmin = auth?.guard === 'superadmin';
    const isAdmin = auth?.guard === 'admin';

    // Permission check
    if (!isSuperAdmin && !isAdmin) return null;
    if (isAdmin && (targetType === 'superadmin' || targetType === 'admin')) return null;

    // Don't show emulate for current logged in user (only if same guard and same ID)
    const isSameGuardUser = (auth?.guard === targetType) && (
        String(auth?.user?.id) === String(targetId) ||
        (auth?.user?.uuid && String(auth?.user?.uuid) === String(targetId))
    );

    if (isSameGuardUser) return null;

    const handleConfirmEmulate = () => {
        return new Promise((resolve) => {
            post(route('emulate.start'), {
                onFinish: () => {
                    setShowConfirm(false);
                    resolve();
                },
            });
        });
    };

    const formattedUserName = userName
        ? userName
            .split(' ')
            .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
            .join(' ')
        : 'this user';

    return (
        <>
            <button
                type="button"
                onClick={() => setShowConfirm(true)}
                disabled={processing}
                title={`Log in as ${formattedUserName}`}
                className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md transition-colors ${
                    className || 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200'
                }`}
            >
                <UserCheck className="w-3.5 h-3.5 text-amber-600" />
                <span>{processing ? 'Switching...' : 'Emulate'}</span>
            </button>

            <ConfirmDialog
                isOpen={showConfirm}
                onClose={() => setShowConfirm(false)}
                onConfirm={handleConfirmEmulate}
                message={`Are you sure you want to log in as ${formattedUserName}? You can exit anytime from the top banner.`}
                confirmText="Yes, Emulate"
                cancelText="Cancel"
            />
        </>
    );
}
