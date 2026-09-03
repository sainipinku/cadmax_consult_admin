import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import NoData from "@/Components/NoData";
import ConfirmDialog from "@/Components/ConfirmDialog";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/20/solid";
import Loading from "@/Components/Loading";
import EmployeeFormModal from "./Components/EmployeeFormModal";
import EmployeeFilters from "./Components/EmployeeFilters";
import EmployeeTable from "./Components/EmployeeTable";

export default function List({ employees, departmentOptions, designationOptions, departmentDesignationMap, roleOptions, filters }) {
    const [isOpen, setIsOpen] = useState(false);
    const [currentEmployee, setCurrentEmployee] = useState(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [departmentFilter, setDepartmentFilter] = useState(filters.department || "");
    const [designationFilter, setDesignationFilter] = useState(filters.designation || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "");
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);
    const [employeeToUpdate, setEmployeeToUpdate] = useState(null);
    const [newStatus, setNewStatus] = useState(null);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [employeeToDelete, setEmployeeToDelete] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [profilePreview, setProfilePreview] = useState(null);
    const fileInputRef = useRef(null);
    const [hasUserInteracted, setHasUserInteracted] = useState(false);

    const [formData, setFormData] = useState({
        full_name: "",
        email: "",
        phone: "",
        password: "",
        confirm_password: "",
        role: "member",
        department: "",
        designation: "",
        gender: "",
        dob: "",
        status: "active",
        profile_photo: null,
        alternate_number: "",
        aadhaar_number: "",
        pan_number: "",
    });

    const updateUrl = (newPage = 1) => {
        const params = {
            search: searchTerm,
            department: departmentFilter,
            designation: designationFilter,
            status: statusFilter,
            per_page: perPage,
            page: newPage,
        };
        setIsLoading(true);
        router.get(route("super.employees.list"), params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            onFinish: () => setIsLoading(false),
        });
    };

    useEffect(() => {
        if (hasUserInteracted) {
            updateUrl();
        }
    }, [searchTerm, departmentFilter, designationFilter, statusFilter, perPage]);

    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
        setHasUserInteracted(true);
    };

    const handleDepartmentFilterChange = (e) => {
        setDepartmentFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handleDesignationFilterChange = (e) => {
        setDesignationFilter(e.target.value);
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
        if (currentEmployee) {
            const member = currentEmployee.member || {};
            const memberDept = member.departments ? (Array.isArray(member.departments) ? member.departments[0] : member.departments) : "";
            const memberDesig = member.designation ? (Array.isArray(member.designation) ? member.designation[0] : member.designation) : "";
            setFormData({
                full_name: member.name || "",
                email: member.email || "",
                phone: member.phone || "",
                password: "",
                confirm_password: "",
                role: member.role_slug || "",
                department: memberDept || "",
                designation: memberDesig || "",
                gender: member.gender || "",
                dob: member.dob || "",
                status: member.status == 1 ? "active" : "inactive",
                profile_photo: null,
                alternate_number: currentEmployee.alternate_number || "",
                aadhaar_number: currentEmployee.aadhaar_number || "",
                pan_number: currentEmployee.pan_number || "",
            });
            setProfilePreview(member.profile_photo_url || null);
        }
    }, [currentEmployee]);

    const handleCreate = () => {
        setCurrentEmployee(null);
        setFormData({
            full_name: "", email: "", phone: "", password: "", confirm_password: "",
            role: "member", department: "", designation: "",
            gender: "", dob: "", status: "active",
            profile_photo: null, alternate_number: "", aadhaar_number: "", pan_number: "",
        });
        setProfilePreview(null);
        setIsOpen(true);
    };

    const handleEdit = (employee) => {
        setCurrentEmployee(employee);
        setIsOpen(true);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => {
            const updated = { ...prev, [name]: value };
            if (name === "department") {
                const available = departmentDesignationMap && departmentDesignationMap[value] ? departmentDesignationMap[value] : [];
                if (!available.includes(prev.designation)) {
                    updated.designation = "";
                }
            }
            return updated;
        });
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setFormData((prev) => ({ ...prev, profile_photo: file }));
            setProfilePreview(URL.createObjectURL(file));
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        const formDataObj = new FormData();
        formDataObj.append("full_name", formData.full_name);
        formDataObj.append("email", formData.email);
        formDataObj.append("phone", formData.phone);
        formDataObj.append("gender", formData.gender);
        formDataObj.append("dob", formData.dob);
        formDataObj.append("alternate_number", formData.alternate_number);
        formDataObj.append("aadhaar_number", formData.aadhaar_number);
        formDataObj.append("pan_number", formData.pan_number);
        formDataObj.append("status", formData.status === "active" ? 1 : 0);
        formDataObj.append("role", formData.role);
        formDataObj.append("department", formData.department);
        formDataObj.append("designation", formData.designation);

        if (formData.password) {
            formDataObj.append("password", formData.password);
            formDataObj.append("confirm_password", formData.confirm_password || formData.password);
        }

        if (formData.profile_photo instanceof File) {
            formDataObj.append("profile_photo", formData.profile_photo);
        }

        if (currentEmployee) {
            formDataObj.append("_method", "PUT");
        }

        const endpoint = currentEmployee
            ? route("super.employees.update", currentEmployee.uuid)
            : route("super.employees.store");

        router.post(endpoint, formDataObj, {
            onSuccess: () => {
                setErrors({});
                setProfilePreview(null);
                setIsSubmitting(false);
                handleClose();
                updateUrl(employees.current_page);
            },
            onError: (err) => {
                setErrors(err);
                setIsSubmitting(false);
            },
        });
    };

    const getStatusDisplay = (status) => {
        const statusMap = {
            1: { text: "Active", class: "bg-green-100 text-green-800" },
            0: { text: "Inactive", class: "bg-red-100 text-red-800" },
        };
        return statusMap[status] || statusMap[0];
    };

    const handleClose = () => {
        setIsOpen(false);
        setCurrentEmployee(null);
        setErrors({});
        setProfilePreview(null);
    };

    const handlePageChange = (page) => {
        setHasUserInteracted(true);
        updateUrl(page);
    };

    const toggleStatus = (uuid, targetStatus) => {
        setEmployeeToUpdate(uuid);
        setNewStatus(targetStatus);
        setShowConfirmDialog(true);
    };

    const handleDelete = async () => {
        try {
            await router.delete(route("super.employees.destroy", employeeToDelete), {
                preserveScroll: true,
                onSuccess: () => updateUrl(employees.current_page),
            });
        } catch (error) {
            console.error("Error deleting employee:", error);
        } finally {
            setShowDeleteDialog(false);
            setEmployeeToDelete(null);
        }
    };

    const handleStatusUpdate = async () => {
        try {
            await router.post(
                route("super.employees.status", employeeToUpdate),
                { status: newStatus },
                { preserveScroll: true }
            );
        } catch (error) {
            console.error("Error updating status:", error);
        } finally {
            setShowConfirmDialog(false);
        }
    };

    const inputClass = (fieldName) => `
        w-full rounded-md border text-[12px] md:text-[13px]
        px-[15px] py-[12px] focus:outline-none transition-all
        bg-white text-gray-800 placeholder-gray-500
        border-gray-300 hover:border-gray-400
        focus:border-blue-500 focus:ring-2 focus:ring-blue-200
        dark:bg-gray-800 dark:text-white dark:placeholder-gray-400
        dark:border-gray-600 dark:hover:border-gray-500
        dark:focus:border-blue-500 dark:focus:ring-blue-500/30
        ${errors[fieldName] ? "border-red-500 focus:border-red-500 focus:ring-red-200 dark:border-red-400 dark:focus:border-red-400" : ""}
    `;

    const selectClass = (fieldName) => `
        w-full rounded-md border text-[12px] md:text-[13px]
        px-[15px] py-[12px] focus:outline-none transition-all appearance-none
        bg-white text-gray-800 border-gray-300
        hover:bg-gray-100
        focus:border-blue-500 focus:ring-2 focus:ring-blue-200
        dark:bg-gray-800 dark:text-white dark:border-gray-600
        dark:hover:bg-[#0a0e25]
        dark:focus:border-blue-500 dark:focus:ring-blue-500/30
        ${errors[fieldName] ? "border-red-500 focus:border-red-500 focus:ring-red-200 dark:border-red-400 dark:focus:border-red-400" : ""}
    `;

    const filterSelectClass = `
        w-full md:w-auto min-w-[120px] text-sm border rounded-md px-4 py-2.5
        focus:outline-none focus:ring-2 transition-all cursor-pointer appearance-none
        bg-white text-gray-800 border-gray-300
        hover:bg-gray-100 focus:border-blue-500 focus:ring-blue-200
        dark:bg-gray-900 dark:text-white dark:border-gray-700
        dark:hover:bg-[#0a0e25]
    `;

    return (
        <AuthenticatedLayout>
            <Head title="Employees" />

            <div className="min-h-screen py-[20px] memberbg">
                <EmployeeFilters
                    departmentFilter={departmentFilter}
                    handleDepartmentFilterChange={handleDepartmentFilterChange}
                    designationFilter={designationFilter}
                    handleDesignationFilterChange={handleDesignationFilterChange}
                    statusFilter={statusFilter}
                    handleStatusFilterChange={handleStatusFilterChange}
                    perPage={perPage}
                    handlePerPageChange={handlePerPageChange}
                    searchTerm={searchTerm}
                    handleSearchChange={handleSearchChange}
                    filterSelectClass={filterSelectClass}
                    handleCreate={handleCreate}
                    departmentOptions={departmentOptions}
                    designationOptions={designationOptions}
                    departmentDesignationMap={departmentDesignationMap}
                />

                <EmployeeTable
                    employees={employees}
                    isLoading={isLoading}
                    getStatusDisplay={getStatusDisplay}
                    handleEdit={handleEdit}
                    handleDelete={(uuid) => { setEmployeeToDelete(uuid); setShowDeleteDialog(true); }}
                    toggleStatus={toggleStatus}
                />

                {employees.data.length > 0 && (
                    <div className="mt-4 flex justify-between items-center flex-wrap gap-4" style={{ padding: '0px 34px' }}>
                        <span className="dark:text-white text-black">
                            Showing {employees.from} to {employees.to} of {employees.total} entries
                        </span>
                        <nav aria-label="Pagination" className="flex items-center gap-2">
                            <button onClick={() => handlePageChange(employees.current_page - 1)}
                                disabled={employees.current_page == 1}
                                className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${employees.current_page == 1 ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                <ChevronLeftIcon className="size-4" /><span>BACK</span>
                            </button>
                            <div className="flex items-center gap-1">
                                {Array.from({ length: employees.last_page }, (_, i) => i + 1).map((page) => {
                                    if (page == 1 || page == 2 || page == employees.last_page - 1 || page == employees.last_page ||
                                        (page >= employees.current_page - 1 && page <= employees.current_page + 1)) {
                                        return (
                                            <button key={page} onClick={() => handlePageChange(page)}
                                                className={`flex items-center justify-center w-8 h-8 rounded-full text-sm text-white ${page == employees.current_page ? "bg-[rgb(82_70_230)]" : "bg-[rgb(74_91_127)] hover:bg-[rgb(74_91_127)/0.9]"}`}>
                                                {page}
                                            </button>
                                        );
                                    }
                                    if ((page == 3 && employees.current_page > 4) || (page == employees.last_page - 2 && employees.current_page < employees.last_page - 3)) {
                                        return <span key={`ellipsis-${page}`} className="flex items-center justify-center w-8 h-8 rounded-full text-sm text-gray-500">...</span>;
                                    }
                                    return null;
                                })}
                            </div>
                            <button onClick={() => handlePageChange(employees.current_page + 1)}
                                disabled={employees.current_page == employees.last_page}
                                className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${employees.current_page == employees.last_page ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                <span>NEXT</span><ChevronRightIcon className="size-4" />
                            </button>
                        </nav>
                    </div>
                )}
            </div>

            <ConfirmDialog isOpen={showDeleteDialog} onClose={() => { setShowDeleteDialog(false); setEmployeeToDelete(null); }}
                onConfirm={handleDelete} message="Are you sure you want to delete this employee? This will also remove their login access."
                confirmText="Yes, delete" cancelText="No, cancel" modalSpinnerMessage="Deleting employee..." isDanger={true} />

            <ConfirmDialog isOpen={showConfirmDialog} onClose={() => setShowConfirmDialog(false)}
                onConfirm={handleStatusUpdate}
                message={`Are you sure you want to ${newStatus == 1 ? "approve & activate" : (newStatus == 2 ? "reject registration for" : "deactivate")} this employee?`}
                confirmText={`Yes, ${newStatus == 1 ? "approve" : (newStatus == 2 ? "reject" : "deactivate")}`} cancelText="No, cancel"
                modalSpinnerMessage="Updating employee status..." />

            <EmployeeFormModal
                isOpen={isOpen}
                onClose={handleClose}
                currentEmployee={currentEmployee}
                formData={formData}
                errors={errors}
                isSubmitting={isSubmitting}
                inputClass={inputClass}
                selectClass={selectClass}
                handleChange={handleChange}
                handleFileChange={handleFileChange}
                handleSubmit={handleSubmit}
                fileInputRef={fileInputRef}
                profilePreview={profilePreview}
                departmentOptions={departmentOptions}
                designationOptions={designationOptions}
                departmentDesignationMap={departmentDesignationMap}
                roleOptions={roleOptions}
            />
        </AuthenticatedLayout>
    );
}