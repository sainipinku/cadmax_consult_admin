import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import NoData from "@/Components/NoData";
import ConfirmDialog from "@/Components/ConfirmDialog";
import DeleteActionButton from "@/Components/DeleteActionButton";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/20/solid";
import Loading from "@/Components/Loading";
import { FiCamera, FiTrash2 } from "react-icons/fi";

export default function List({ equipments, categories, employees, projects, filters, isProductSuperAdmin, canManageEquipment = !isProductSuperAdmin }) {
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [currentEquipment, setCurrentEquipment] = useState(null);
    const [viewEquipment, setViewEquipment] = useState(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [categoryFilter, setCategoryFilter] = useState(filters.category_id || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "");
    const [sortFilter, setSortFilter] = useState(filters.sort || "newest");
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [equipmentToDelete, setEquipmentToDelete] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [photoPreview, setPhotoPreview] = useState(null);
    const fileInputRef = useRef(null);
    const [hasUserInteracted, setHasUserInteracted] = useState(false);

    const statusOptions = [
        { value: 0, label: "Available" },
        { value: 1, label: "Assigned" },
        { value: 2, label: "Under Maintenance" },
        { value: 3, label: "Damaged" },
        { value: 4, label: "Lost" },
        { value: 5, label: "Disposed" },
    ];

    const [formData, setFormData] = useState({
        category_id: "",
        equipment_name: "",
        company: "",
        brand: "",
        model: "",
        serial_number: "",
        asset_tag: "",
        quantity: 1,
        ownership_type: "owned",
        location_site: "",
        purchase_date: "",
        purchase_cost: "",
        vendor: "",
        warranty_till: "",
        notes: "",
        photo: null,
        status: 0,
    });

    // Assign Modal state
    const [isAssignOpen, setIsAssignOpen] = useState(false);
    const [assignEquipment, setAssignEquipment] = useState(null);
    const [assignFormData, setAssignFormData] = useState({
        assigned_employee_id: "",
        assigned_project_id: "",
        assigned_date: "",
    });
    const [assignErrors, setAssignErrors] = useState({});
    const [isAssignSubmitting, setIsAssignSubmitting] = useState(false);

    const updateUrl = (newPage = 1) => {
        const params = {
            search: searchTerm,
            category_id: categoryFilter,
            status: statusFilter,
            sort: sortFilter,
            per_page: perPage,
            page: newPage,
        };
        setIsLoading(true);
        router.get(route("super.equipment.list"), params, {
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
    }, [searchTerm, categoryFilter, statusFilter, sortFilter, perPage]);

    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
        setHasUserInteracted(true);
    };

    useEffect(() => {
        if (currentEquipment) {
            setFormData({
                category_id: currentEquipment.category_id || "",
                equipment_name: currentEquipment.equipment_name || "",
                company: currentEquipment.company || "",
                brand: currentEquipment.brand || "",
                model: currentEquipment.model || "",
                serial_number: currentEquipment.serial_number || "",
                asset_tag: currentEquipment.asset_tag || "",
                quantity: currentEquipment.quantity ?? 1,
                ownership_type: currentEquipment.ownership_type || "owned",
                location_site: currentEquipment.location_site || "",
                purchase_date: currentEquipment.purchase_date ? String(currentEquipment.purchase_date).substring(0, 10) : "",
                purchase_cost: currentEquipment.purchase_cost || "",
                vendor: currentEquipment.vendor || "",
                warranty_till: currentEquipment.warranty_till ? String(currentEquipment.warranty_till).substring(0, 10) : "",
                notes: currentEquipment.notes || "",
                photo: null,
                status: currentEquipment.status ?? 0,
            });
            setPhotoPreview(currentEquipment.photo_url || null);
        }
    }, [currentEquipment]);

    const handleCreate = () => {
        setCurrentEquipment(null);
        setFormData({
            category_id: "", equipment_name: "", company: "", brand: "", model: "",
            serial_number: "", asset_tag: "", quantity: 1, ownership_type: "owned",
            location_site: "", purchase_date: "", purchase_cost: "",
            vendor: "", warranty_till: "", notes: "", photo: null, status: 0,
        });
        setPhotoPreview(null);
        setErrors({});
        setIsOpen(true);
    };

    const handleEdit = (equipment) => {
        setCurrentEquipment(equipment);
        setErrors({});
        setIsOpen(true);
    };

    const handleView = async (equipment) => {
        try {
            const response = await fetch(route("super.equipment.show", equipment.id));
            const data = await response.json();
            if (data.success) {
                setViewEquipment(data.equipment);
                setIsViewOpen(true);
            }
        } catch (error) {
            console.error("Error fetching equipment:", error);
        }
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => ({ ...prev, [name]: value }));
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setFormData((prev) => ({ ...prev, photo: file }));
            setPhotoPreview(URL.createObjectURL(file));
        }
    };

    const handleRemoveImage = () => {
        setFormData((prev) => ({ ...prev, photo: null }));
        setPhotoPreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    };

    const handleOpenAssignModal = (equipment) => {
        setAssignEquipment(equipment);
        setAssignFormData({
            assigned_employee_id: equipment.assigned_employee_id || "",
            assigned_project_id: equipment.assigned_project_id || "",
            assigned_date: equipment.assigned_date
                ? String(equipment.assigned_date).substring(0, 10)
                : new Date().toISOString().split("T")[0],
        });
        setAssignErrors({});
        setIsAssignOpen(true);
    };

    const handleCloseAssign = () => {
        setIsAssignOpen(false);
        setAssignEquipment(null);
        setAssignErrors({});
        setIsAssignSubmitting(false);
    };

    const handleAssignSubmit = (e) => {
        e.preventDefault();
        if (!assignEquipment) return;
        setIsAssignSubmitting(true);

        router.post(route("super.equipment.assign", assignEquipment.id), assignFormData, {
            preserveScroll: true,
            onSuccess: () => {
                setIsAssignSubmitting(false);
                handleCloseAssign();
                updateUrl(equipments.current_page);
            },
            onError: (err) => {
                setAssignErrors(err);
                setIsAssignSubmitting(false);
            },
        });
    };

    const handleUnassign = (equipment) => {
        if (confirm(`Are you sure you want to unassign ${equipment.equipment_name || "this equipment"}? It will be marked as Available.`)) {
            router.post(route("super.equipment.unassign", equipment.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    updateUrl(equipments.current_page);
                },
            });
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        const formDataObj = new FormData();
        formDataObj.append("category_id", formData.category_id);
        formDataObj.append("equipment_name", formData.equipment_name);
        formDataObj.append("company", formData.company);
        formDataObj.append("brand", formData.brand);
        formDataObj.append("model", formData.model);
        formDataObj.append("serial_number", formData.serial_number);
        formDataObj.append("asset_tag", formData.asset_tag);
        formDataObj.append("quantity", formData.quantity);
        formDataObj.append("ownership_type", formData.ownership_type);
        formDataObj.append("location_site", formData.location_site);
        formDataObj.append("purchase_date", formData.purchase_date);
        formDataObj.append("purchase_cost", formData.purchase_cost);
        formDataObj.append("vendor", formData.vendor);
        formDataObj.append("warranty_till", formData.warranty_till);
        formDataObj.append("notes", formData.notes);
        formDataObj.append("status", formData.status);

        if (currentEquipment) {
            formDataObj.append("assigned_employee_id", currentEquipment.assigned_employee_id || "");
            formDataObj.append("assigned_project_id", currentEquipment.assigned_project_id || "");
            formDataObj.append("assigned_date", currentEquipment.assigned_date ? String(currentEquipment.assigned_date).substring(0, 10) : "");
            formDataObj.append("_method", "PUT");
        }

        if (formData.photo instanceof File) {
            formDataObj.append("photo", formData.photo);
        }

        const endpoint = currentEquipment
            ? route("super.equipment.update", currentEquipment.id)
            : route("super.equipment.store");

        router.post(endpoint, formDataObj, {
            onSuccess: () => {
                setErrors({});
                setPhotoPreview(null);
                setIsSubmitting(false);
                handleClose();
                updateUrl(equipments.current_page);
            },
            onError: (err) => {
                setErrors(err);
                setIsSubmitting(false);
            },
        });
    };

    const getStatusDisplay = (status) => {
        const statusMap = {
            0: { text: "Available", class: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400" },
            1: { text: "Assigned", class: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400" },
            2: { text: "Under Maintenance", class: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400" },
            3: { text: "Damaged", class: "bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400" },
            4: { text: "Lost", class: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400" },
            5: { text: "Disposed", class: "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400" },
        };
        return statusMap[status] || { text: "Unknown", class: "bg-gray-100 text-gray-800" };
    };

    const handleClose = () => {
        setIsOpen(false);
        setCurrentEquipment(null);
        setErrors({});
        setPhotoPreview(null);
    };

    const handleCloseView = () => {
        setIsViewOpen(false);
        setViewEquipment(null);
    };

    const handlePageChange = (page) => {
        setHasUserInteracted(true);
        updateUrl(page);
    };

    const handleDelete = async () => {
        try {
            await router.delete(route("super.equipment.destroy", equipmentToDelete), {
                preserveScroll: true,
                onSuccess: () => updateUrl(equipments.current_page),
            });
        } catch (error) {
            console.error("Error deleting equipment:", error);
        } finally {
            setShowDeleteDialog(false);
            setEquipmentToDelete(null);
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

    const sectionCardClass = "bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 md:p-5 border border-gray-200 dark:border-gray-700 mb-5";
    const sectionTitleClass = "text-base font-semibold text-gray-800 dark:text-gray-200 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700";

    return (
        <AuthenticatedLayout>
            <Head title="Equipment Management" />

            <div className="min-h-screen py-[40px] memberbg">
                <div className="mt-[64px]">
                    {/* Filters Bar */}
                    <div className="px-[34px] mb-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="relative">
                                    <input type="text" value={searchTerm} onChange={handleSearchChange}
                                        placeholder="Search equipment..." className="w-full md:w-64 text-sm border rounded-md px-4 py-2.5 focus:outline-none focus:ring-2 transition-all bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-blue-200 dark:bg-gray-900 dark:text-white dark:border-gray-700" />
                                </div>
                                <select value={categoryFilter} onChange={(e) => { setCategoryFilter(e.target.value); setHasUserInteracted(true); }} className={filterSelectClass}>
                                    <option value="">All Categories</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>{cat.category_name}</option>
                                    ))}
                                </select>
                                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setHasUserInteracted(true); }} className={filterSelectClass}>
                                    <option value="">All Status</option>
                                    {statusOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                                <select value={sortFilter} onChange={(e) => { setSortFilter(e.target.value); setHasUserInteracted(true); }} className={filterSelectClass}>
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="name">By Name</option>
                                </select>
                                <select value={perPage} onChange={(e) => { setPerPage(e.target.value); setHasUserInteracted(true); }} className={filterSelectClass}>
                                    <option value={10}>10 per page</option>
                                    <option value={25}>25 per page</option>
                                    <option value={50}>50 per page</option>
                                    <option value={100}>100 per page</option>
                                </select>
                            </div>
                            {canManageEquipment && (
                                <button onClick={handleCreate} className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-[rgb(82_70_230)] rounded-lg hover:bg-[rgb(82_70_230)/0.9] transition">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Equipment
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Table */}
                    <div className="px-[34px]">
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            {isLoading && <Loading />}
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[900px]">
                                    <thead className="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Equipment ID</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Photo</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                            {isProductSuperAdmin && <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Company</th>}
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Brand/Model</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Serial No.</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Ownership</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Location / Site</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Assignment Details</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {equipments.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={isProductSuperAdmin ? 13 : 12} className="px-4 py-8">
                                                    <NoData message={canManageEquipment ? "No equipment found. Add your first equipment!" : "No equipment found."} />
                                                </td>
                                            </tr>
                                        ) : (
                                            equipments.data.map((equipment) => {
                                                const status = getStatusDisplay(equipment.status);
                                                const assignedEmployee = equipment.assigned_employee || equipment.assignedEmployee;
                                                const assignedProject = equipment.assigned_project || equipment.assignedProject;
                                                const employeeName = assignedEmployee?.member?.name || assignedEmployee?.name;
                                                const hasAssignment = Boolean(assignedEmployee || assignedProject || equipment.assigned_date);

                                                return (
                                                    <tr key={equipment.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                                        <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                            {equipment.equipment_id}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="w-10 h-10 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                                                <img src={equipment.photo_url} alt="" className="w-full h-full object-cover"
                                                                    onError={(e) => { e.target.src = '/images/common/data_not_found.png'; }} />
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-medium">
                                                            {equipment.equipment_name}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            {equipment.category?.category_name || '-'}
                                                        </td>
                                                        {isProductSuperAdmin && (
                                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                                {equipment.owning_company?.name || '-'}
                                                            </td>
                                                        )}
                                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            {[equipment.brand, equipment.model].filter(Boolean).join(' / ') || '-'}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            {equipment.serial_number || '-'}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            {equipment.quantity ?? 1}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 capitalize">
                                                            {equipment.ownership_type || 'owned'}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            {equipment.location_site || '-'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-medium ${status.class}`}>
                                                                {status.text}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                            {hasAssignment ? (
                                                                <div className="flex flex-col gap-1 max-w-[220px]">
                                                                    {assignedEmployee && (
                                                                        <div className="flex items-center gap-1.5 text-xs text-gray-900 dark:text-white font-medium">
                                                                            <svg className="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                            </svg>
                                                                            <span className="truncate" title={employeeName ? `${employeeName} (${assignedEmployee.employee_id})` : assignedEmployee.employee_id}>
                                                                                {employeeName || assignedEmployee.employee_id}
                                                                                {assignedEmployee.employee_id && employeeName && (
                                                                                    <span className="text-[11px] text-gray-400 dark:text-gray-400 font-normal ml-1">
                                                                                        ({assignedEmployee.employee_id})
                                                                                    </span>
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {assignedProject && (
                                                                        <div className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                                                            <svg className="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                                            </svg>
                                                                            <span className="truncate" title={`${assignedProject.project_code} - ${assignedProject.name}`}>
                                                                                {assignedProject.name || assignedProject.project_code}
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {equipment.assigned_date && (
                                                                        <div className="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                                                            <svg className="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                            </svg>
                                                                            <span>{String(equipment.assigned_date).substring(0, 10)}</span>
                                                                        </div>
                                                                    )}
                                                                    {canManageEquipment && <div className="pt-1 flex items-center gap-2">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleOpenAssignModal(equipment)}
                                                                            className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 inline-flex items-center gap-1 transition"
                                                                            title="Change assignment"
                                                                        >
                                                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                            </svg>
                                                                            Reassign
                                                                        </button>
                                                                        <span className="text-gray-300 dark:text-gray-600 text-xs">•</span>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleUnassign(equipment)}
                                                                            className="text-[11px] font-semibold text-rose-500 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 transition"
                                                                            title="Remove assignment"
                                                                        >
                                                                            Unassign
                                                                        </button>
                                                                    </div>}
                                                                </div>
                                                            ) : (
                                                                canManageEquipment ? <button
                                                                    type="button"
                                                                    onClick={() => handleOpenAssignModal(equipment)}
                                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500 shadow-sm transition-all focus:ring-2 focus:ring-indigo-500/20 active:scale-95"
                                                                >
                                                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                                                    </svg>
                                                                    Assign
                                                                </button> : <span className="text-xs text-gray-400">Not assigned</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="flex items-center justify-end gap-2">
                                                                <button onClick={() => handleView(equipment)} className="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition" title="View">
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                </button>
                                                                {canManageEquipment && <button onClick={() => handleEdit(equipment)} className="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-md transition" title="Edit">
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                </button>}
                                                                {canManageEquipment && <DeleteActionButton
                                                                    resourceType="equipment"
                                                                    resourceId={equipment.id}
                                                                    resourceName={equipment.equipment_name || `Equipment #${equipment.id}`}
                                                                    onDelete={() => { setEquipmentToDelete(equipment.id); setShowDeleteDialog(true); }}
                                                                />}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* Pagination */}
                    {equipments.data.length > 0 && (
                        <div className="mt-4 flex justify-between items-center flex-wrap gap-4" style={{ padding: '0px 34px' }}>
                            <span className="dark:text-white text-black">
                                Showing {equipments.from} to {equipments.to} of {equipments.total} entries
                            </span>
                            <nav aria-label="Pagination" className="flex items-center gap-2">
                                <button onClick={() => handlePageChange(equipments.current_page - 1)}
                                    disabled={equipments.current_page == 1}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${equipments.current_page == 1 ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                    <ChevronLeftIcon className="size-4" /><span>BACK</span>
                                </button>
                                <div className="flex items-center gap-1">
                                    {Array.from({ length: equipments.last_page }, (_, i) => i + 1).map((page) => {
                                        if (page == 1 || page == 2 || page == equipments.last_page - 1 || page == equipments.last_page ||
                                            (page >= equipments.current_page - 1 && page <= equipments.current_page + 1)) {
                                            return (
                                                <button key={page} onClick={() => handlePageChange(page)}
                                                    className={`flex items-center justify-center w-8 h-8 rounded-full text-sm text-white ${page == equipments.current_page ? "bg-[rgb(82_70_230)]" : "bg-[rgb(74_91_127)] hover:bg-[rgb(74_91_127)/0.9]"}`}>
                                                    {page}
                                                </button>
                                            );
                                        }
                                        if ((page == 3 && equipments.current_page > 4) || (page == equipments.last_page - 2 && equipments.current_page < equipments.last_page - 3)) {
                                            return <span key={`ellipsis-${page}`} className="flex items-center justify-center w-8 h-8 rounded-full text-sm text-gray-500">...</span>;
                                        }
                                        return null;
                                    })}
                                </div>
                                <button onClick={() => handlePageChange(equipments.current_page + 1)}
                                    disabled={equipments.current_page == equipments.last_page}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${equipments.current_page == equipments.last_page ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                    <span>NEXT</span><ChevronRightIcon className="size-4" />
                                </button>
                            </nav>
                        </div>
                    )}
                </div>
            </div>

            <ConfirmDialog isOpen={showDeleteDialog} onClose={() => { setShowDeleteDialog(false); setEquipmentToDelete(null); }}
                onConfirm={handleDelete} message="Are you sure you want to delete this equipment? This action cannot be undone."
                confirmText="Yes, delete" cancelText="No, cancel" modalSpinnerMessage="Deleting equipment..." isDanger={true} />

            {/* View Modal */}
            <Modal show={isViewOpen} onClose={handleCloseView} maxWidth="2xl" topCloseButton={true} handleTopClose={handleCloseView}>
                <div className="p-2 md:p-4 dark:bg-[#080626]">
                    <h2 className="text-xl font-bold mb-6 dark:text-white">Equipment Details</h2>
                    {viewEquipment && (
                        <div className="space-y-4">
                            <div className="flex items-center gap-4 mb-4">
                                <div className="w-20 h-20 rounded-lg overflow-hidden border-2 border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    <img src={viewEquipment.photo_url} alt="" className="w-full h-full object-cover"
                                        onError={(e) => { e.target.src = '/images/common/data_not_found.png'; }} />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{viewEquipment.equipment_name}</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">{viewEquipment.equipment_id}</p>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Category</label>
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">{viewEquipment.category?.category_name || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Status</label>
                                    <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-medium ${getStatusDisplay(viewEquipment.status).class}`}>
                                        {getStatusDisplay(viewEquipment.status).text}
                                    </span>
                                </div>
                                {isProductSuperAdmin && (
                                    <div>
                                        <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Owning Company</label>
                                        <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.owning_company?.name || '-'}</p>
                                    </div>
                                )}
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Manufacturer</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.company || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Brand</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.brand || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Model</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.model || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Serial Number</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.serial_number || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Asset Tag</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.asset_tag || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Quantity</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.quantity ?? 1}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Ownership Type</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300 capitalize">{viewEquipment.ownership_type || 'owned'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Location / Site</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.location_site || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Purchase Date</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.purchase_date || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Purchase Cost</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.purchase_cost ? '₹' + Number(viewEquipment.purchase_cost).toLocaleString() : '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Vendor</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.vendor || '-'}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Warranty Till</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300">{viewEquipment.warranty_till || '-'}</p>
                                </div>
                                <div className="col-span-2">
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Notes / Description</label>
                                    <p className="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{viewEquipment.notes || '-'}</p>
                                </div>
                                {(viewEquipment.assigned_employee || viewEquipment.assignedEmployee) && (() => {
                                    const assignedEmp = viewEquipment.assigned_employee || viewEquipment.assignedEmployee;
                                    const empName = assignedEmp?.member?.name || assignedEmp?.name;
                                    return (
                                        <div>
                                            <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Assigned To</label>
                                            <p className="text-sm text-gray-700 dark:text-gray-300">
                                                {empName || 'Employee'} ({assignedEmp.employee_id})
                                            </p>
                                        </div>
                                    );
                                })()}
                                {(viewEquipment.assigned_project || viewEquipment.assignedProject) && (() => {
                                    const assignedProj = viewEquipment.assigned_project || viewEquipment.assignedProject;
                                    return (
                                        <div>
                                            <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Assigned Project</label>
                                            <p className="text-sm text-gray-700 dark:text-gray-300">{assignedProj.name} ({assignedProj.project_code})</p>
                                        </div>
                                    );
                                })()}
                                {viewEquipment.assigned_date && (
                                    <div>
                                        <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Assigned Date</label>
                                        <p className="text-sm text-gray-700 dark:text-gray-300">{String(viewEquipment.assigned_date).substring(0, 10)}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </Modal>

            {/* Add/Edit Modal */}
            {canManageEquipment && <Modal show={isOpen} onClose={handleClose} maxWidth="6xl" topCloseButton={true} handleTopClose={handleClose}>
                <div className="p-2 md:p-4 dark:bg-[#080626]">
                    <h2 className="text-xl font-bold mb-6 dark:text-white">
                        {currentEquipment ? "Edit Equipment" : "Add Equipment"}
                    </h2>

                    <form onSubmit={handleSubmit} encType="multipart/form-data">
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Basic Information</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Equipment ID</label>
                                    <input type="text" value={currentEquipment ? currentEquipment.equipment_id : "Auto-generated"} className={inputClass("equipment_id")} disabled />
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Equipment Name <em className="text-red-500">*</em></label>
                                    <input type="text" name="equipment_name" value={formData.equipment_name} onChange={handleChange} className={inputClass("equipment_name")} placeholder="Enter equipment name" />
                                    {errors.equipment_name && <p className="text-red-500 text-xs mt-1">{errors.equipment_name}</p>}
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Category <em className="text-red-500">*</em></label>
                                    <select name="category_id" value={formData.category_id} onChange={handleChange} className={selectClass("category_id")}>
                                        <option value="">Select Category</option>
                                        {categories.map((cat) => <option key={cat.id} value={cat.id}>{cat.category_name}</option>)}
                                    </select>
                                    {errors.category_id && <p className="text-red-500 text-xs mt-1">{errors.category_id}</p>}
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Brand</label>
                                    <input type="text" name="brand" value={formData.brand} onChange={handleChange} className={inputClass("brand")} placeholder="e.g. Caterpillar" />
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Model</label>
                                    <input type="text" name="model" value={formData.model} onChange={handleChange} className={inputClass("model")} placeholder="e.g. 320D" />
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Serial Number</label>
                                    <input type="text" name="serial_number" value={formData.serial_number} onChange={handleChange} className={inputClass("serial_number")} placeholder="Enter serial number" />
                                    {errors.serial_number && <p className="text-red-500 text-xs mt-1">{errors.serial_number}</p>}
                                </div>
                                <div className="md:col-span-2">
                                    <label className="block text-gray-700 dark:text-gray-300 mb-2 text-sm">Equipment Photo</label>
                                    <div className="flex items-center gap-4">
                                        <div className="relative">
                                            <div className="w-24 h-24 rounded-lg overflow-hidden border-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                                {photoPreview ? <img src={photoPreview} alt="Preview" className="w-full h-full object-cover" /> : <FiCamera className="w-8 h-8 text-gray-400" />}
                                            </div>
                                            <button type="button" onClick={() => fileInputRef.current?.click()} className="absolute bottom-0 right-0 bg-blue-600 text-white p-1.5 rounded-full hover:bg-blue-700 transition">
                                                <FiCamera size={14} />
                                            </button>
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <input type="file" ref={fileInputRef} onChange={handleFileChange} accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" className="hidden" />
                                            <button type="button" onClick={() => fileInputRef.current?.click()} className="px-3 py-1.5 text-xs bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md">Choose Image</button>
                                            {photoPreview && <button type="button" onClick={handleRemoveImage} className="px-3 py-1.5 text-xs bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-md flex items-center gap-1"><FiTrash2 size={12} /> Remove</button>}
                                        </div>
                                    </div>
                                    {errors.photo && <p className="text-red-500 text-xs mt-1">{errors.photo}</p>}
                                </div>
                            </div>
                        </div>

                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Inventory</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Quantity <em className="text-red-500">*</em></label>
                                    <input type="number" name="quantity" value={formData.quantity} onChange={handleChange} className={inputClass("quantity")} min="1" step="1" />
                                    {errors.quantity && <p className="text-red-500 text-xs mt-1">{errors.quantity}</p>}
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Ownership Type <em className="text-red-500">*</em></label>
                                    <select name="ownership_type" value={formData.ownership_type} onChange={handleChange} className={selectClass("ownership_type")}>
                                        <option value="owned">Owned</option>
                                        <option value="leased">Leased</option>
                                        <option value="rented">Rented</option>
                                    </select>
                                    {errors.ownership_type && <p className="text-red-500 text-xs mt-1">{errors.ownership_type}</p>}
                                </div>
                                <div className="md:col-span-2">
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Location / Site</label>
                                    <input type="text" name="location_site" value={formData.location_site} onChange={handleChange} className={inputClass("location_site")} placeholder="Enter location or site" />
                                    {errors.location_site && <p className="text-red-500 text-xs mt-1">{errors.location_site}</p>}
                                </div>
                            </div>
                        </div>

                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Purchase Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Purchase Date</label>
                                    <input type="date" name="purchase_date" value={formData.purchase_date} onChange={handleChange} className={inputClass("purchase_date")} />
                                    {errors.purchase_date && <p className="text-red-500 text-xs mt-1">{errors.purchase_date}</p>}
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Purchase Cost (₹)</label>
                                    <input type="number" name="purchase_cost" value={formData.purchase_cost} onChange={handleChange} className={inputClass("purchase_cost")} min="0" step="0.01" />
                                    {errors.purchase_cost && <p className="text-red-500 text-xs mt-1">{errors.purchase_cost}</p>}
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vendor</label>
                                    <input type="text" name="vendor" value={formData.vendor} onChange={handleChange} className={inputClass("vendor")} placeholder="Vendor name" />
                                </div>
                                <div>
                                    <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Warranty Till</label>
                                    <input type="date" name="warranty_till" value={formData.warranty_till} onChange={handleChange} className={inputClass("warranty_till")} />
                                    {errors.warranty_till && <p className="text-red-500 text-xs mt-1">{errors.warranty_till}</p>}
                                </div>
                            </div>
                        </div>

                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Operations</h3>
                            <div className="max-w-md">
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Initial Status <em className="text-red-500">*</em></label>
                                <select name="status" value={formData.status} onChange={handleChange} className={selectClass("status")}>
                                    {statusOptions.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                                </select>
                                {errors.status && <p className="text-red-500 text-xs mt-1">{errors.status}</p>}
                            </div>
                        </div>

                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Additional</h3>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Notes / Description</label>
                            <textarea name="notes" value={formData.notes} onChange={handleChange} className={inputClass("notes")} rows="4" placeholder="Additional equipment notes" />
                            {errors.notes && <p className="text-red-500 text-xs mt-1">{errors.notes}</p>}
                        </div>

                        {/* Submit Buttons */}
                        <div className="flex justify-end space-x-3 mt-6">
                            <button type="button" onClick={handleClose} className="px-4 py-2 canclebtn rounded-[7px]">Cancel</button>
                            <button type="submit"
                                className={`flex items-center gap-[5px] px-[20px] py-[12px] text-[15px] text-white rounded-[10px] bluebtbg ${isSubmitting ? "opacity-75 cursor-not-allowed" : ""}`}
                                disabled={isSubmitting}>
                                {isSubmitting ? (
                                    <span className="flex items-center justify-center">
                                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        {currentEquipment ? "Updating..." : "Adding..."}
                                    </span>
                                ) : currentEquipment ? "Update Equipment" : "Add Equipment"}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>}

            {/* Assign / Reassign Modal */}
            <Modal show={isAssignOpen} onClose={handleCloseAssign} maxWidth="lg" topCloseButton={true} handleTopClose={handleCloseAssign}>
                <div className="p-4 md:p-6 dark:bg-[#080626]">
                    <div className="flex items-center gap-3 pb-4 border-b border-gray-100 dark:border-gray-800">
                        <div className="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shadow-sm">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                        <div>
                            <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                                {assignEquipment?.assigned_employee_id || assignEquipment?.assigned_project_id ? "Reassign Equipment" : "Assign Equipment"}
                            </h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                {assignEquipment?.equipment_name} {assignEquipment?.equipment_id ? `(${assignEquipment.equipment_id})` : ""}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleAssignSubmit} className="mt-5 space-y-4">
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm font-medium">
                                Assigned To (Employee)
                            </label>
                            <select
                                name="assigned_employee_id"
                                value={assignFormData.assigned_employee_id}
                                onChange={(e) => setAssignFormData({ ...assignFormData, assigned_employee_id: e.target.value })}
                                className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#0b0838] text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            >
                                <option value="">Not Assigned / Select Employee</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.display_name}
                                    </option>
                                ))}
                            </select>
                            {assignErrors.assigned_employee_id && (
                                <p className="text-red-500 text-xs mt-1">{assignErrors.assigned_employee_id}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm font-medium">
                                Assigned Project
                            </label>
                            <select
                                name="assigned_project_id"
                                value={assignFormData.assigned_project_id}
                                onChange={(e) => setAssignFormData({ ...assignFormData, assigned_project_id: e.target.value })}
                                className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#0b0838] text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            >
                                <option value="">Not Assigned / Select Project</option>
                                {projects.map((proj) => (
                                    <option key={proj.id} value={proj.id}>
                                        {proj.project_code} - {proj.name}
                                    </option>
                                ))}
                            </select>
                            {assignErrors.assigned_project_id && (
                                <p className="text-red-500 text-xs mt-1">{assignErrors.assigned_project_id}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm font-medium">
                                Assigned Date
                            </label>
                            <input
                                type="date"
                                name="assigned_date"
                                value={assignFormData.assigned_date}
                                onChange={(e) => setAssignFormData({ ...assignFormData, assigned_date: e.target.value })}
                                className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#0b0838] text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            {assignErrors.assigned_date && (
                                <p className="text-red-500 text-xs mt-1">{assignErrors.assigned_date}</p>
                            )}
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                            <button
                                type="button"
                                onClick={handleCloseAssign}
                                className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={isAssignSubmitting}
                                className={`inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white rounded-lg bg-indigo-600 hover:bg-indigo-700 transition ${isAssignSubmitting ? "opacity-75 cursor-not-allowed" : ""}`}
                            >
                                {isAssignSubmitting ? "Saving..." : "Confirm Assignment"}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}