import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import { FiCamera } from "react-icons/fi";
import { router } from "@inertiajs/react";
import CameraUploadModal from "./CameraUploadModal";

export default function AssignRoleModal({
    isOpen,
    onClose,
    employee,
    memberRoleOptions = [],
    onSuccess,
}) {
    const [selectedRoles, setSelectedRoles] = useState([]);
    const [profilePhoto, setProfilePhoto] = useState(null);
    const [profilePreview, setProfilePreview] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isCameraModalOpen, setIsCameraModalOpen] = useState(false);
    const fileInputRef = useRef(null);

    const member = employee?.member || {};

    const DEFAULT_ROLE_OPTIONS = [
        { slug: "surveyor", name: "Survey Man" },
        { slug: "vehicle_driver", name: "Driver" },
        { slug: "draft_person", name: "Draft Man" },
    ];

    const rolesToDisplay = memberRoleOptions.length > 0 ? memberRoleOptions : DEFAULT_ROLE_OPTIONS;

    useEffect(() => {
        if (employee) {
            const currentRoles = member.assigned_roles || [];
            setSelectedRoles(currentRoles);
            setProfilePreview(member.profile_photo_url || null);
            setProfilePhoto(null);
        }
    }, [employee]);

    const handleRoleToggle = (slug) => {
        setSelectedRoles((prev) => {
            if (prev.includes(slug)) {
                return prev.filter((item) => item !== slug);
            } else {
                return [...prev, slug];
            }
        });
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setProfilePhoto(file);
            setProfilePreview(URL.createObjectURL(file));
        }
    };

    const handleImageCapturedFromCamera = async (capturedSrc) => {
        try {
            setProfilePreview(capturedSrc);
            const res = await fetch(capturedSrc);
            const blob = await res.blob();
            const file = new File([blob], "captured_photo.jpg", { type: blob.type || "image/jpeg" });
            setProfilePhoto(file);
        } catch (err) {
            console.error("Error converting captured camera image:", err);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!employee) return;

        setIsSubmitting(true);
        const formData = new FormData();

        selectedRoles.forEach((role) => {
            formData.append("roles[]", role);
        });

        if (profilePhoto instanceof File) {
            formData.append("profile_photo", profilePhoto);
        }

        router.post(route("super.employees.assign_role", employee.uuid), formData, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmitting(false);
                if (onSuccess) onSuccess();
                onClose();
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    return (
        <>
            <Modal show={isOpen} onClose={onClose} maxWidth="lg" topCloseButton={true} handleTopClose={onClose}>
                <div className="p-4 md:p-6 dark:bg-[#080626]">
                    <div className="mb-4">
                        <h2 className="text-xl font-bold dark:text-white text-gray-900">
                            Assign Roles & Update Photo
                        </h2>
                        {employee && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {member.name || "Employee"} ({employee.employee_id})
                            </p>
                        )}
                    </div>

                    <form onSubmit={handleSubmit}>
                        {/* Profile Photo */}
                        <div className="mb-6 flex justify-center">
                            <div className="relative group cursor-pointer" onClick={() => setIsCameraModalOpen(true)}>
                                <div className="w-24 h-24 rounded-full overflow-hidden border-2 border-blue-500 dark:border-blue-400 bg-gray-100 dark:bg-gray-700 flex items-center justify-center shadow-md">
                                    {profilePreview ? (
                                        <img
                                            src={profilePreview}
                                            alt="Profile Preview"
                                            className="w-full h-full object-cover"
                                            onError={(e) => {
                                                e.target.onerror = null;
                                                e.target.src = "/images/profileimg.png";
                                            }}
                                        />
                                    ) : (
                                        <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    )}
                                </div>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        setIsCameraModalOpen(true);
                                    }}
                                    className="absolute bottom-0 right-0 bg-blue-600 text-white p-2 rounded-full hover:bg-blue-700 transition shadow-lg border-2 border-white dark:border-gray-800"
                                    title="Capture / Change Photo"
                                >
                                    <FiCamera size={14} />
                                </button>
                            </div>
                        </div>

                    {/* Member Roles Checkboxes */}
                    <div className="mb-6 bg-gray-50 dark:bg-[#0a0e25] p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                        <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">
                            Member Roles (Select all that apply):
                        </label>

                        <div className="space-y-3">
                            {rolesToDisplay.map((role) => (
                                <label
                                    key={role.slug}
                                    className="flex items-center gap-3 p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-all"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedRoles.includes(role.slug)}
                                        onChange={() => handleRoleToggle(role.slug)}
                                        className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <div className="flex flex-col">
                                        <span className="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {role.name}
                                        </span>
                                    </div>
                                </label>
                            ))}
                        </div>
                    </div>

                    {/* Modal Action Buttons */}
                    <div className="flex justify-end space-x-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className={`flex items-center justify-center gap-2 px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-md transition ${
                                isSubmitting ? "opacity-75 cursor-not-allowed" : ""
                            }`}
                        >
                            {isSubmitting ? (
                                <>
                                    <svg className="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    Updating...
                                </>
                            ) : (
                                "Update Roles"
                            )}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <CameraUploadModal
            isOpen={isCameraModalOpen}
            onClose={() => setIsCameraModalOpen(false)}
            onImageCaptured={(capturedSrc) => {
                handleImageCapturedFromCamera(capturedSrc);
                setIsCameraModalOpen(false);
            }}
        />
    </>
);
}
