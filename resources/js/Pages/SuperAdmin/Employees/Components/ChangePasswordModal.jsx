import { useState, useEffect } from "react";
import Modal from "@/Components/Modal";
import { router } from "@inertiajs/react";
import { FiKey } from "react-icons/fi";

export default function ChangePasswordModal({
    isOpen,
    onClose,
    employee,
    onSuccess,
}) {
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const member = employee?.member || {};

    useEffect(() => {
        if (employee) {
            setPassword("");
            setPasswordConfirmation("");
            setErrors({});
        }
    }, [employee]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!employee) return;

        if (!password || password.length < 6) {
            setErrors({ password: "Password must be at least 6 characters." });
            return;
        }

        if (password !== passwordConfirmation) {
            setErrors({ password_confirmation: "Passwords do not match." });
            return;
        }

        setIsSubmitting(true);
        setErrors({});

        router.post(
            route("super.employees.password", employee.uuid),
            {
                password,
                password_confirmation: passwordConfirmation,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSubmitting(false);
                    setPassword("");
                    setPasswordConfirmation("");
                    if (onSuccess) onSuccess();
                    onClose();
                },
                onError: (err) => {
                    setIsSubmitting(false);
                    setErrors(err);
                },
            }
        );
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="md" topCloseButton={true} handleTopClose={onClose}>
            <div className="p-4 md:p-6 dark:bg-[#080626]">
                <div className="flex items-center gap-3 mb-4 border-b border-gray-100 dark:border-gray-800 pb-3">
                    <div className="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 p-2.5 rounded-xl">
                        <FiKey size={22} />
                    </div>
                    <div>
                        <h2 className="text-lg font-bold dark:text-white text-gray-900">
                            Change Password
                        </h2>
                        {employee && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Set a new password for {member.name || "Employee"} ({employee.employee_id})
                            </p>
                        )}
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            New Password *
                        </label>
                        <input
                            type="password"
                            required
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Enter new password (min. 6 characters)"
                            className="w-full rounded-md border text-[13px] px-3.5 py-2.5 bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:bg-gray-800 dark:text-white dark:border-gray-600"
                        />
                        {errors.password && (
                            <p className="text-xs text-red-500 dark:text-red-400 mt-1">{errors.password}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Confirm New Password *
                        </label>
                        <input
                            type="password"
                            required
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            placeholder="Confirm new password"
                            className="w-full rounded-md border text-[13px] px-3.5 py-2.5 bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:bg-gray-800 dark:text-white dark:border-gray-600"
                        />
                        {errors.password_confirmation && (
                            <p className="text-xs text-red-500 dark:text-red-400 mt-1">{errors.password_confirmation}</p>
                        )}
                    </div>

                    <div className="flex justify-end space-x-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className={`flex items-center justify-center gap-2 px-5 py-2 text-xs font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 shadow-md transition ${
                                isSubmitting ? "opacity-75 cursor-not-allowed" : ""
                            }`}
                        >
                            {isSubmitting ? "Updating..." : "Update Password"}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
