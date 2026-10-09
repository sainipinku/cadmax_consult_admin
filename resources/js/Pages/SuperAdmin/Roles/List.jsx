import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import { Head, usePage, router } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import NoData from "@/Components/NoData";
import { toast } from "react-hot-toast";
import { FaEdit, FaShieldAlt, FaCheckSquare, FaSquare, FaSearch } from "react-icons/fa";
import ConfirmDialog from "@/Components/ConfirmDialog";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/20/solid";

export default function List({ roles, auth, filters, all_permissions = [], grouped_permissions = {} }) {
    const [isOpen, setIsOpen] = useState(false);
    const [currentRole, setCurrentRole] = useState(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "");
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);
    const [roleToUpdate, setRoleToUpdate] = useState(null);
    const [newStatus, setNewStatus] = useState(null);
    const [memberToDelete, setMemberToDelete] = useState(null);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [formData, setFormData] = useState({
        name: "",
        status: "active",
    });
    const [hasUserInteracted, setHasUserInteracted] = useState(false);

    // Permission Modal States
    const [permissionModalOpen, setPermissionModalOpen] = useState(false);
    const [selectedRoleForPerms, setSelectedRoleForPerms] = useState(null);
    const [selectedPermissionIds, setSelectedPermissionIds] = useState([]);
    const [permSearchTerm, setPermSearchTerm] = useState("");
    const [isSavingPermissions, setIsSavingPermissions] = useState(false);

    const updateUrl = (newPage = 1) => {
        const params = {
            search: searchTerm,
            status: statusFilter,
            per_page: perPage,
            page: newPage,
        };
        router.get(route("super.role.list"), params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    useEffect(() => {
        if (hasUserInteracted) {
            updateUrl();
        }
    }, [searchTerm, statusFilter, perPage]);

    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
        setHasUserInteracted(true);
    };

    const handleStatusFilterChange = (e) => {
        setStatusFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handlePerPageChange = (e) => {
        setPerPage(e.target.value);
        setHasUserInteracted(true);
    };

    useEffect(() => {
        if (currentRole) {
            setFormData({
                name: currentRole.name,
                status: currentRole.status == 1 ? "active" : "inactive",
            });
        }
    }, [currentRole]);

    const handleCreate = () => {
        setCurrentRole(null);
        setIsOpen(true);
    };

    const handleEdit = (role) => {
        setCurrentRole(role);
        setIsOpen(true);
    };

    const handleOpenPermissionModal = (role) => {
        setSelectedRoleForPerms(role);
        setSelectedPermissionIds(role.permissions || []);
        setPermSearchTerm("");
        setPermissionModalOpen(true);
    };

    const handleTogglePermission = (permId) => {
        setSelectedPermissionIds((prev) =>
            prev.includes(permId)
                ? prev.filter((id) => id !== permId)
                : [...prev, permId]
        );
    };

    const handleToggleModulePermissions = (modulePermissions, isAllSelected) => {
        const moduleIds = modulePermissions.map((p) => p.id);
        if (isAllSelected) {
            setSelectedPermissionIds((prev) => prev.filter((id) => !moduleIds.includes(id)));
        } else {
            setSelectedPermissionIds((prev) => Array.from(new Set([...prev, ...moduleIds])));
        }
    };

    const handleSavePermissions = (e) => {
        e.preventDefault();
        if (!selectedRoleForPerms) return;
        setIsSavingPermissions(true);

        router.post(
            route("super.role.permissions.update", selectedRoleForPerms.uuid),
            { permission_ids: selectedPermissionIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Role permissions updated successfully!");
                    setPermissionModalOpen(false);
                    setIsSavingPermissions(false);
                    updateUrl(roles.current_page);
                },
                onError: (err) => {
                    toast.error("Failed to update role permissions");
                    setIsSavingPermissions(false);
                },
            }
        );
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: value,
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        const endpoint = currentRole
            ? route("super.role.update", currentRole.uuid)
            : route("super.role.add");

        const method = currentRole ? "put" : "post";

        router[method](
            endpoint,
            {
                ...formData,
                created_by: auth.user.id,
            },
            {
                onSuccess: () => {
                    setErrors({});
                    setFormData({
                        name: "",
                        status: "active",
                    });
                    setIsSubmitting(false);
                    handleClose();
                    setHasUserInteracted(true);
                    updateUrl(roles.current_page);
                },
                onError: (err) => {
                    setErrors(err);
                    setIsSubmitting(false);
                },
            }
        );
    };

    const getStatusDisplay = (status) => {
        const statusMap = {
            1: { text: "Active", class: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300" },
            0: { text: "Inactive", class: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300" },
        };
        return statusMap[status] || statusMap[0];
    };

    const handleClose = () => {
        setIsOpen(false);
        setCurrentRole(null);
        setFormData({
            name: "",
            status: "active",
        });
        setErrors({});
    };

    const handlePageChange = (page) => {
        setHasUserInteracted(true);
        updateUrl(page);
    };

    const toggleStatus = (uuid, currentStatus) => {
        const updatedStatus = currentStatus == 1 ? 0 : 1;
        setRoleToUpdate(uuid);
        setNewStatus(updatedStatus);
        setShowConfirmDialog(true);
    };

    const handleStatusUpdate = async () => {
        try {
            await router.post(
                route("super.role.status", roleToUpdate),
                { status: newStatus },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        updateUrl(roles.current_page);
                    },
                    onError: () => {},
                }
            );
        } catch (error) {
            console.error("Error updating status:", error);
        } finally {
            setShowConfirmDialog(false);
        }
    };

    const handleDelete = async () => {
        try {
            await router.delete(
                route("super.role.destroy", memberToDelete),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        updateUrl(roles.current_page);
                    },
                    onError: () => {},
                }
            );
        } catch (error) {
            console.error("Error deleting member:", error);
        } finally {
            setShowDeleteDialog(false);
            setMemberToDelete(null);
        }
    };

    const userPermissions = auth?.permissions || [];
    const isSuperAdmin = auth?.guard === 'superadmin' || auth?.user?.is_super_admin || userPermissions.includes('*');

    const canCreate = isSuperAdmin || userPermissions.includes('role.create');
    const canEdit = isSuperAdmin || userPermissions.includes('role.edit');
    const canDelete = isSuperAdmin || userPermissions.includes('role.delete');
    const canAssignPermissions = isSuperAdmin || userPermissions.includes('role.assign_permissions') || userPermissions.includes('role.manage');

    const DownMenuItem = ({
        taskItem,
        handleEdit,
        handleOpenPermissionModal,
        toggleStatus,
        setMemberToDelete,
        setShowDeleteDialog,
        canAssignPermissions,
        canEdit,
        canDelete,
    }) => {
        const [isDropdownOpen, setIsDropdownOpen] = useState(false);
        const [position, setPosition] = useState({ top: 0, left: 0 });

        if (!canAssignPermissions && !canEdit && !canDelete) {
            return null;
        }

        const toggleDropdown = (e) => {
            e.stopPropagation();
            const button = e.currentTarget;
            const rect = button.getBoundingClientRect();
            const newTop = rect.bottom + window.scrollY;
            const newLeft = rect.right - 165;

            setPosition({ top: newTop, left: newLeft });
            setIsDropdownOpen(!isDropdownOpen);
        };
        const dropdownRef = useRef(null);

        useEffect(() => {
            const handleClickOutside = (event) => {
                if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                    setIsDropdownOpen(false);
                }
            };

            document.addEventListener("mousedown", handleClickOutside);
            return () => {
                document.removeEventListener("mousedown", handleClickOutside);
            };
        }, []);

        return (
            <>
                <button
                    onClick={toggleDropdown}
                    className="text-sm bg-none text-white p-[0]"
                >
                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <rect
                            x="0.5"
                            y="0.5"
                            width="23"
                            height="23"
                            rx="4.5"
                            stroke="#727272"
                        />
                        <path
                            d="M5 13C5.55228 13 6 12.5523 6 12C6 11.4477 5.55228 11 5 11C4.44772 11 4 11.4477 4 12C4 12.5523 4.44772 13 5 13Z"
                            stroke="#727272"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                        <path
                            d="M11.9004 13C12.4527 13 12.9004 12.5523 12.9004 12C12.9004 11.4477 12.4527 11 11.9004 11C11.3481 11 10.9004 11.4477 10.9004 12C10.9004 12.5523 11.3481 13 11.9004 13Z"
                            stroke="#727272"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                        <path
                            d="M18.8008 13C19.3531 13 19.8008 12.5523 19.8008 12C19.8008 11.4477 19.3531 11 18.8008 11C18.2485 11 17.8008 11.4477 17.8008 12C17.8008 12.5523 18.2485 13 18.8008 13Z"
                            stroke="#727272"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </button>

                {isDropdownOpen && (
                    <div
                        ref={dropdownRef}
                        className="absolute min-w-[160px] z-50 px-[10px] py-[8px] dropDown rounded-[8px] mt-[5px] shadow-md bg-white dark:bg-[#080626]"
                        style={{
                            top: `${position.top}px`,
                            left: `${position.left}px`,
                        }}
                    >
                        <ul>
                            {canAssignPermissions && (
                                <li className="flex items-center gap-[5px] p-2 text-[12px] text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-[#5146E622] cursor-pointer border-b border-b-[#f2f2f2] dark:border-b-gray-800">
                                    <button
                                        className="flex items-center gap-[8px] font-semibold"
                                        onClick={() => {
                                            setIsDropdownOpen(false);
                                            handleOpenPermissionModal(taskItem);
                                        }}
                                    >
                                        <FaShieldAlt className="w-4 h-4" />
                                        Manage Permissions
                                    </button>
                                </li>
                            )}

                            {canEdit && (
                                <>
                                    <li className="flex items-center gap-[5px] p-2 text-[12px] text-black dark:text-white hover:bg-gray-100 dark:hover:bg-[#0a0e25] cursor-pointer border-b border-b-[#f2f2f2] dark:border-b-gray-800">
                                        <button
                                            className="flex items-center gap-[8px]"
                                            onClick={() => {
                                                setIsDropdownOpen(false);
                                                handleEdit(taskItem);
                                            }}
                                        >
                                            <svg
                                                className="w-[18px]"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                            >
                                                <path d="M16.7574 2.99678L14.7574 4.99678H5V18.9968H19V9.23943L21 7.23943V19.9968C21 20.5491 20.5523 20.9968 20 20.9968H4C3.44772 20.9968 3 20.5491 3 19.9968V3.99678C3 3.4445 3.44772 2.99678 4 2.99678H16.7574ZM20.4853 2.09729L21.8995 3.5115L12.7071 12.7039L11.2954 12.7064L11.2929 11.2897L20.4853 2.09729Z"></path>
                                            </svg>
                                            Edit Role
                                        </button>
                                    </li>

                                    <li className="flex items-center gap-[5px] p-2 text-[12px] text-black dark:text-white hover:bg-gray-100 dark:hover:bg-[#0a0e25] cursor-pointer border-b border-b-[#f2f2f2] dark:border-b-gray-800">
                                        <button
                                            onClick={() => {
                                                setIsDropdownOpen(false);
                                                toggleStatus(taskItem.uuid, taskItem.status);
                                            }}
                                            className="flex items-center gap-[8px]"
                                        >
                                            {taskItem.status == 0 ? "Activate Role" : "Deactivate Role"}
                                        </button>
                                    </li>
                                </>
                            )}

                            {canDelete && (
                                <li className="flex items-center gap-[5px] p-2 text-[12px] text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 cursor-pointer">
                                    <button
                                        onClick={() => {
                                            setIsDropdownOpen(false);
                                            setMemberToDelete(taskItem.uuid);
                                            setShowDeleteDialog(true);
                                        }}
                                        className="flex items-center gap-[8px]"
                                    >
                                        Delete Role
                                    </button>
                                </li>
                            )}
                        </ul>
                    </div>
                )}
            </>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Roles & Permissions" />

            <div className="min-h-screen py-[40px] memberbg">
                <div className="mt-[64px]">
                    <div className="flex justify-between items-center flex-wrap md:flex-nowrap px-[15px] pt-[5px] pb-[15px] gap-3">
                        <div className="flex items-center flex-col md:flex-row gap-[15px] w-full md:w-auto">
                            <select
                                value={statusFilter}
                                onChange={handleStatusFilterChange}
                                className="w-full md:w-auto min-w-[120px] text-sm border rounded-md px-4 py-2.5 bg-white text-gray-800 border-gray-300 dark:bg-gray-900 dark:text-white dark:border-gray-700"
                            >
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>

                            <select
                                value={perPage}
                                onChange={handlePerPageChange}
                                className="w-full md:w-auto min-w-[120px] text-sm border rounded-md px-4 py-2.5 bg-white text-gray-800 border-gray-300 dark:bg-gray-900 dark:text-white dark:border-gray-700"
                            >
                                <option value="10">10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>

                            <input
                                type="text"
                                className="w-full md:w-auto sm:min-w-[200px] text-sm rounded-md px-4 py-2.5 bg-white text-gray-800 placeholder-gray-500 border border-gray-300 dark:bg-gray-900 dark:text-white dark:placeholder-gray-400 dark:border-gray-700"
                                placeholder="Search Roles..."
                                value={searchTerm}
                                onChange={handleSearchChange}
                            />
                        </div>

                        {canCreate && (
                            <button
                                onClick={handleCreate}
                                className="flex items-center gap-[8px] px-[20px] py-[10px] text-[14px] font-semibold text-white rounded-[10px] bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 transition shadow-md"
                            >
                                + Create Role
                            </button>
                        )}
                    </div>

                    <div className="p-[15px]">
                        <div className="overflow-x-auto tablebxbg p-[15px] rounded-[15px]">
                            <table className="min-w-full text-black rounded-2xl dark:text-white">
                                <thead>
                                    <tr className="whitespace-nowrap text-left">
                                        <th className="p-3">SR No.</th>
                                        <th className="p-3">Role Name</th>
                                        <th className="p-3">Assigned Permissions</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Created By</th>
                                        <th className="p-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roles.data.length > 0 ? (
                                        roles.data.map((role, index) => {
                                            const permCount = (role.permissions || []).length;
                                            return (
                                                <tr
                                                    key={role.id}
                                                    className="hover:bg-gray-100 dark:hover:bg-[#0a0e25]"
                                                >
                                                    <td className="p-3">{index + 1}</td>
                                                    <td className="p-3 font-medium">{role.name}</td>
                                                    <td className="p-3">
                                                        {canAssignPermissions ? (
                                                            <button
                                                                onClick={() => handleOpenPermissionModal(role)}
                                                                className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition"
                                                            >
                                                                <FaShieldAlt size={12} />
                                                                {permCount} Permissions
                                                            </button>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                                <FaShieldAlt size={12} />
                                                                {permCount} Permissions
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="p-3">
                                                        <span
                                                            className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                                                                getStatusDisplay(role.status).class
                                                            }`}
                                                        >
                                                            {getStatusDisplay(role.status).text}
                                                        </span>
                                                    </td>
                                                    <td className="p-3">
                                                        {role.creator?.name || "System"}
                                                    </td>
                                                    <td className="p-3">
                                                        <div className="flex items-center justify-center gap-2">
                                                            {canAssignPermissions && (
                                                                <button
                                                                    onClick={() => handleOpenPermissionModal(role)}
                                                                    className="px-2.5 py-1 text-xs font-medium rounded-lg bg-violet-600 text-white hover:bg-violet-700 transition"
                                                                >
                                                                    Permissions
                                                                </button>
                                                            )}
                                                            <DownMenuItem
                                                                taskItem={role}
                                                                handleEdit={handleEdit}
                                                                handleOpenPermissionModal={handleOpenPermissionModal}
                                                                toggleStatus={toggleStatus}
                                                                setMemberToDelete={setMemberToDelete}
                                                                setShowDeleteDialog={setShowDeleteDialog}
                                                                canAssignPermissions={canAssignPermissions}
                                                                canEdit={canEdit}
                                                                canDelete={canDelete}
                                                            />
                                                            {!canAssignPermissions && !canEdit && !canDelete && (
                                                                <span className="text-xs text-gray-400 font-medium">Read Only</span>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="6" className="p-4">
                                                <NoData message="No Roles found" iconSize={48} />
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {roles.data.length > 0 && (
                        <div className="mt-4 flex justify-between items-center flex-wrap gap-4 px-[34px]">
                            <span className="dark:text-white text-black text-sm">
                                Showing {roles.from} to {roles.to} of {roles.total} entries
                            </span>
                            <nav aria-label="Pagination" className="flex items-center gap-2">
                                <button
                                    onClick={() => handlePageChange(roles.current_page - 1)}
                                    disabled={roles.current_page == 1}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${
                                        roles.current_page == 1
                                            ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]"
                                            : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"
                                    }`}
                                >
                                    <ChevronLeftIcon className="size-4" />
                                    <span>BACK</span>
                                </button>
                                <div className="flex items-center gap-1">
                                    {Array.from({ length: roles.last_page }, (_, i) => i + 1).map((page) => {
                                        if (
                                            page == 1 ||
                                            page == 2 ||
                                            page == roles.last_page - 1 ||
                                            page == roles.last_page ||
                                            (page >= roles.current_page - 1 &&
                                                page <= roles.current_page + 1)
                                        ) {
                                            return (
                                                <button
                                                    key={page}
                                                    onClick={() => handlePageChange(page)}
                                                    className={`flex items-center justify-center w-8 h-8 rounded-full text-sm text-white ${
                                                        page == roles.current_page
                                                            ? "bg-[rgb(82_70_230)]"
                                                            : "bg-[rgb(74_91_127)] hover:bg-[rgb(74_91_127)/0.9]"
                                                    }`}
                                                >
                                                    {page}
                                                </button>
                                            );
                                        }
                                        return null;
                                    })}
                                </div>
                                <button
                                    onClick={() => handlePageChange(roles.current_page + 1)}
                                    disabled={roles.current_page == roles.last_page}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${
                                        roles.current_page == roles.last_page
                                            ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]"
                                            : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"
                                    }`}
                                >
                                    <span>NEXT</span>
                                    <ChevronRightIcon className="size-4" />
                                </button>
                            </nav>
                        </div>
                    )}
                </div>
            </div>

            {/* Status Confirmation Modal */}
            <ConfirmDialog
                isOpen={showConfirmDialog}
                onClose={() => setShowConfirmDialog(false)}
                onConfirm={handleStatusUpdate}
                message={`Do you want to ${newStatus == 1 ? "activate" : "deactivate"} this role?`}
                confirmText={`Yes, ${newStatus == 1 ? "activate" : "deactivate"}`}
                cancelText="No, cancel"
                modalSpinnerMessage="Updating role status..."
            />

            {/* Delete Confirmation Modal */}
            <ConfirmDialog
                isOpen={showDeleteDialog}
                onClose={() => {
                    setShowDeleteDialog(false);
                    setMemberToDelete(null);
                }}
                onConfirm={handleDelete}
                message="Are you sure you want to delete this Role?"
                confirmText="Yes, delete"
                cancelText="No, cancel"
                modalSpinnerMessage="Deleting Role..."
                isDanger={true}
            />

            {/* Edit / Create Role Modal */}
            <Modal
                show={isOpen}
                onClose={handleClose}
                maxWidth="md"
                topCloseButton={true}
                handleTopClose={handleClose}
            >
                <h2 className="text-xl font-bold mb-4 dark:text-white">
                    {currentRole ? "Edit Role" : "Create Role"}
                </h2>

                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label className="block text-gray-700 dark:text-gray-300 mb-2">
                            Name <em className="text-red-500">*</em>
                        </label>
                        <input
                            type="text"
                            name="name"
                            value={formData.name}
                            onChange={handleChange}
                            className="w-full px-3 py-2 text-sm rounded-md bg-white text-gray-800 placeholder-gray-500 border border-gray-300 focus:outline-none focus:ring-2 focus:border-blue-500 dark:bg-gray-900 dark:text-white dark:border-gray-700"
                            required
                        />
                        {errors.name && (
                            <p className="text-red-500 text-sm mt-1">{errors.name}</p>
                        )}
                    </div>

                    <div className="flex justify-end space-x-3">
                        <button
                            type="button"
                            onClick={handleClose}
                            className="px-4 py-2 canclebtn rounded-[7px]"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="flex items-center gap-[5px] px-[20px] py-[12px] text-[15px] text-white rounded-[10px] bluebtbg"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? "Saving..." : currentRole ? "Update" : "Create"}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Manage Role Permissions Modal */}
            <Modal
                show={permissionModalOpen}
                onClose={() => setPermissionModalOpen(false)}
                maxWidth="4xl"
                topCloseButton={true}
                handleTopClose={() => setPermissionModalOpen(false)}
            >
                <div className="p-2 md:p-4">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b pb-3 mb-4 dark:border-gray-700">
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <FaShieldAlt className="text-indigo-600 dark:text-indigo-400" />
                                Manage Permissions: <span className="text-indigo-600 dark:text-indigo-400">{selectedRoleForPerms?.name}</span>
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Check the permissions that users assigned to this role will be granted.
                            </p>
                        </div>
                        <div className="text-right">
                            <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                                {selectedPermissionIds.length} / {all_permissions.length} Selected
                            </span>
                        </div>
                    </div>

                    {/* Filter & Quick Actions */}
                    <div className="flex flex-wrap items-center justify-between gap-3 mb-4 bg-slate-50 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div className="relative flex-1 min-w-[200px]">
                            <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
                            <input
                                type="text"
                                value={permSearchTerm}
                                onChange={(e) => setPermSearchTerm(e.target.value)}
                                placeholder="Filter permissions..."
                                className="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setSelectedPermissionIds(all_permissions.map((p) => p.id))}
                                className="px-3 py-1 text-xs font-medium rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition"
                            >
                                Select All
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedPermissionIds([])}
                                className="px-3 py-1 text-xs font-medium rounded-lg bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 transition"
                            >
                                Clear All
                            </button>
                        </div>
                    </div>

                    {/* Permissions Grouped by Module */}
                    <form onSubmit={handleSavePermissions}>
                        <div className="max-h-[50vh] overflow-y-auto pr-2 space-y-4 sidebar-scroll">
                            {Object.entries(grouped_permissions).map(([moduleName, perms]) => {
                                const filteredPerms = perms.filter(
                                    (p) =>
                                        p.name.toLowerCase().includes(permSearchTerm.toLowerCase()) ||
                                        p.slug.toLowerCase().includes(permSearchTerm.toLowerCase()) ||
                                        (p.description && p.description.toLowerCase().includes(permSearchTerm.toLowerCase()))
                                );

                                if (filteredPerms.length === 0) return null;

                                const moduleIds = filteredPerms.map((p) => p.id);
                                const isAllModuleSelected = moduleIds.every((id) => selectedPermissionIds.includes(id));
                                const selectedInModuleCount = moduleIds.filter((id) => selectedPermissionIds.includes(id)).length;

                                return (
                                    <div
                                        key={moduleName}
                                        className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-[#03011C] overflow-hidden shadow-sm"
                                    >
                                        <div className="flex items-center justify-between bg-slate-100 dark:bg-slate-900 px-4 py-2.5 border-b border-slate-200 dark:border-slate-800">
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                                                    {moduleName}
                                                </h3>
                                                <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                    {selectedInModuleCount} / {filteredPerms.length}
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => handleToggleModulePermissions(filteredPerms, isAllModuleSelected)}
                                                className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                            >
                                                {isAllModuleSelected ? "Deselect Module" : "Select Module"}
                                            </button>
                                        </div>

                                        <div className="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                            {filteredPerms.map((perm) => {
                                                const isChecked = selectedPermissionIds.includes(perm.id);
                                                return (
                                                    <div
                                                        key={perm.id}
                                                        onClick={() => handleTogglePermission(perm.id)}
                                                        className={`flex items-start gap-3 p-2.5 rounded-lg border text-left cursor-pointer select-none transition-all ${
                                                            isChecked
                                                                ? "bg-indigo-50/70 border-indigo-400 dark:bg-indigo-950/40 dark:border-indigo-600 text-indigo-900 dark:text-indigo-200 shadow-sm"
                                                                : "bg-slate-50/40 border-slate-200 hover:border-indigo-300 hover:bg-slate-50 dark:bg-slate-900/30 dark:border-slate-800 text-slate-700 dark:text-slate-300"
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onChange={() => {}}
                                                            className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 pointer-events-none"
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-xs font-semibold truncate">{perm.name}</p>
                                                            <p className="text-[10px] font-mono text-slate-400 dark:text-slate-500 truncate">{perm.slug}</p>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Footer Actions */}
                        <div className="flex items-center justify-between border-t pt-4 mt-4 dark:border-gray-700">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Total permissions assigned: <strong className="text-indigo-600 dark:text-indigo-400">{selectedPermissionIds.length}</strong>
                            </span>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setPermissionModalOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSavingPermissions}
                                    className="px-5 py-2 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-md transition disabled:opacity-50"
                                >
                                    {isSavingPermissions ? "Saving Permissions..." : "Save Role Permissions"}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
