import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import NoData from "@/Components/NoData";
import ConfirmDialog from "@/Components/ConfirmDialog";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/20/solid";
import Loading from "@/Components/Loading";
import VehicleFormModal from "./Components/VehicleFormModal";
import VehicleViewModal from "./Components/VehicleViewModal";
import VehicleFilters from "./Components/VehicleFilters";
import VehicleTable from "./Components/VehicleTable";

export default function List({ vehicles, filters }) {
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [currentVehicle, setCurrentVehicle] = useState(null);
    const [viewVehicle, setViewVehicle] = useState(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [vehicleTypeFilter, setVehicleTypeFilter] = useState(filters.vehicle_type || "");
    const [fuelTypeFilter, setFuelTypeFilter] = useState(filters.fuel_type || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "");
    const [insuranceStatusFilter, setInsuranceStatusFilter] = useState(filters.insurance_status || "");
    const [sortFilter, setSortFilter] = useState(filters.sort || "newest");
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [vehicleToDelete, setVehicleToDelete] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [hasUserInteracted, setHasUserInteracted] = useState(false);

    const statusOptions = [
        { value: 0, label: "Active" },
        { value: 1, label: "Inactive" },
        { value: 2, label: "Sold" },
    ];

    const insuranceStatusOptions = [
        { value: "", label: "All Insurance" },
        { value: 1, label: "Active" },
        { value: 0, label: "Expired" },
    ];

    const updateUrl = (newPage = 1) => {
        const params = {
            search: searchTerm,
            vehicle_type: vehicleTypeFilter,
            fuel_type: fuelTypeFilter,
            status: statusFilter,
            insurance_status: insuranceStatusFilter,
            sort: sortFilter,
            per_page: perPage,
            page: newPage,
        };
        setIsLoading(true);
        router.get(route("super.vehicles.list"), params, {
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
    }, [searchTerm, vehicleTypeFilter, fuelTypeFilter, statusFilter, insuranceStatusFilter, sortFilter, perPage]);

    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
        setHasUserInteracted(true);
    };

    const handleVehicleTypeFilterChange = (e) => {
        setVehicleTypeFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handleFuelTypeFilterChange = (e) => {
        setFuelTypeFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handleStatusFilterChange = (e) => {
        setStatusFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handleInsuranceStatusFilterChange = (e) => {
        setInsuranceStatusFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handleSortChange = (e) => {
        setSortFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handlePerPageChange = (e) => {
        setPerPage(e.target.value);
        setHasUserInteracted(true);
    };

    const [formData, setFormData] = useState({
        vehicle_type: "",
        vehicle_number: "",
        vehicle_name: "",
        brand: "",
        fuel_type: "",
        color: "",
        manufacturing_year: "",
        engine_number: "",
        chassis_number: "",
        purchase_date: "",
        purchase_amount: "",
        current_km_reading: "",
        status: 0,

        // Insurance
        insurance_provider: "",
        policy_number: "",
        insurance_type: "",
        insurance_start_date: "",
        insurance_end_date: "",
        insuranceDocFile: null,
        insuranceDocPreview: null,
        insuranceDocIsImage: false,
        existingInsuranceDoc: null,

        // PUC
        puc_certificate_number: "",
        puc_issue_date: "",
        puc_expiry_date: "",
        pucDocFile: null,
        pucDocPreview: null,
        pucDocIsImage: false,
        existingPucDoc: null,

        // Challan
        challan_number: "",
        challan_date: "",
        violation_type: "",
        fine_amount: "",
        payment_status: "",
        challanDocFile: null,
        challanDocPreview: null,
        challanDocIsImage: false,
        existingChallanDoc: null,

        // Multiple Vehicle Images
        existingImages: [],
        newImageFiles: [],
        newImagePreviews: [],
        deletedImageIds: [],

        // Supporting Documents
        existingOtherDocs: [],
        otherDocs: [],
        deletedDocumentIds: [],
    });

    const formatDateForInput = (dateStr) => {
        if (!dateStr) return "";
        const str = String(dateStr);
        if (str.includes("T")) return str.split("T")[0];
        if (str.includes(" ")) return str.split(" ")[0];
        return str;
    };

    useEffect(() => {
        if (currentVehicle) {
            const existingInsurance = currentVehicle.documents?.find((d) => d.document_type === 'Insurance');
            const existingPuc = currentVehicle.documents?.find((d) => d.document_type === 'PUC');
            const existingChallan = currentVehicle.documents?.find((d) => d.document_type === 'Challan');
            const existingOthers = currentVehicle.documents?.filter((d) => d.document_type === 'Other') || [];

            setFormData({
                vehicle_type: currentVehicle.vehicle_type || "",
                vehicle_number: currentVehicle.vehicle_number || "",
                vehicle_name: currentVehicle.vehicle_name || "",
                brand: currentVehicle.brand || "",
                fuel_type: currentVehicle.fuel_type || "",
                color: currentVehicle.color || "",
                manufacturing_year: currentVehicle.manufacturing_year || "",
                engine_number: currentVehicle.engine_number || "",
                chassis_number: currentVehicle.chassis_number || "",
                purchase_date: formatDateForInput(currentVehicle.purchase_date),
                purchase_amount: currentVehicle.purchase_amount || "",
                current_km_reading: currentVehicle.current_km_reading || "",
                status: currentVehicle.status ?? 0,

                insurance_provider: currentVehicle.insurance_provider || "",
                policy_number: currentVehicle.policy_number || "",
                insurance_type: currentVehicle.insurance_type || "",
                insurance_start_date: formatDateForInput(currentVehicle.insurance_start_date),
                insurance_end_date: formatDateForInput(currentVehicle.insurance_end_date),
                insuranceDocFile: null,
                insuranceDocPreview: null,
                insuranceDocIsImage: existingInsurance?.is_image || false,
                existingInsuranceDoc: existingInsurance || null,

                puc_certificate_number: currentVehicle.puc_certificate_number || "",
                puc_issue_date: formatDateForInput(currentVehicle.puc_issue_date),
                puc_expiry_date: formatDateForInput(currentVehicle.puc_expiry_date),
                pucDocFile: null,
                pucDocPreview: null,
                pucDocIsImage: existingPuc?.is_image || false,
                existingPucDoc: existingPuc || null,

                challan_number: currentVehicle.challan_number || "",
                challan_date: formatDateForInput(currentVehicle.challan_date),
                violation_type: currentVehicle.violation_type || "",
                fine_amount: currentVehicle.fine_amount || "",
                payment_status: currentVehicle.payment_status ?? "",
                challanDocFile: null,
                challanDocPreview: null,
                challanDocIsImage: existingChallan?.is_image || false,
                existingChallanDoc: existingChallan || null,

                existingImages: currentVehicle.images || [],
                newImageFiles: [],
                newImagePreviews: [],
                deletedImageIds: [],

                existingOtherDocs: existingOthers,
                otherDocs: [],
                deletedDocumentIds: [],
            });
        }
    }, [currentVehicle]);

    const handleCreate = () => {
        setCurrentVehicle(null);
        setFormData({
            vehicle_type: "", vehicle_number: "", vehicle_name: "", brand: "",
            fuel_type: "", color: "", manufacturing_year: "", engine_number: "",
            chassis_number: "", purchase_date: "", purchase_amount: "", current_km_reading: "",
            status: 0,
            insurance_provider: "", policy_number: "", insurance_type: "",
            insurance_start_date: "", insurance_end_date: "",
            insuranceDocFile: null, insuranceDocPreview: null, insuranceDocIsImage: false, existingInsuranceDoc: null,
            puc_certificate_number: "", puc_issue_date: "", puc_expiry_date: "",
            pucDocFile: null, pucDocPreview: null, pucDocIsImage: false, existingPucDoc: null,
            challan_number: "", challan_date: "", violation_type: "", fine_amount: "", payment_status: "",
            challanDocFile: null, challanDocPreview: null, challanDocIsImage: false, existingChallanDoc: null,
            existingImages: [], newImageFiles: [], newImagePreviews: [], deletedImageIds: [],
            existingOtherDocs: [], otherDocs: [], deletedDocumentIds: [],
        });
        setErrors({});
        setIsOpen(true);
    };

    const handleEdit = (vehicle) => {
        setCurrentVehicle(vehicle);
        setErrors({});
        setIsOpen(true);
    };

    const handleView = (vehicle) => {
        setViewVehicle(vehicle);
        setIsViewOpen(true);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => ({ ...prev, [name]: value }));
    };

    // Multi Image Handlers
    const handleMultiImageChange = (e) => {
        const files = Array.from(e.target.files);
        if (files.length > 0) {
            const previews = files.map((file) => URL.createObjectURL(file));
            setFormData((prev) => ({
                ...prev,
                newImageFiles: [...prev.newImageFiles, ...files],
                newImagePreviews: [...prev.newImagePreviews, ...previews],
            }));
        }
    };

    const handleRemoveNewImage = (index) => {
        setFormData((prev) => {
            const updatedFiles = [...prev.newImageFiles];
            const updatedPreviews = [...prev.newImagePreviews];
            updatedFiles.splice(index, 1);
            updatedPreviews.splice(index, 1);
            return {
                ...prev,
                newImageFiles: updatedFiles,
                newImagePreviews: updatedPreviews,
            };
        });
    };

    const handleRemoveExistingImage = (id, targetType = 'image') => {
        if (targetType === 'image') {
            setFormData((prev) => ({
                ...prev,
                existingImages: prev.existingImages.filter((img) => img.id !== id),
                deletedImageIds: [...prev.deletedImageIds, id],
            }));
        } else {
            setFormData((prev) => ({
                ...prev,
                existingOtherDocs: prev.existingOtherDocs.filter((doc) => doc.id !== id),
                deletedDocumentIds: [...prev.deletedDocumentIds, id],
            }));
        }
    };

    // Document Handlers
    const handleDocumentChange = (docType, file) => {
        if (!file) return;
        const isImg = file.type.startsWith('image/');
        const previewUrl = URL.createObjectURL(file);

        if (docType === 'insurance') {
            setFormData((prev) => ({
                ...prev,
                insuranceDocFile: file,
                insuranceDocPreview: previewUrl,
                insuranceDocIsImage: isImg,
            }));
        } else if (docType === 'puc') {
            setFormData((prev) => ({
                ...prev,
                pucDocFile: file,
                pucDocPreview: previewUrl,
                pucDocIsImage: isImg,
            }));
        } else if (docType === 'challan') {
            setFormData((prev) => ({
                ...prev,
                challanDocFile: file,
                challanDocPreview: previewUrl,
                challanDocIsImage: isImg,
            }));
        }
    };

    const handleRemoveDocument = (docType) => {
        if (docType === 'insurance') {
            if (formData.existingInsuranceDoc) {
                setFormData((prev) => ({
                    ...prev,
                    deletedDocumentIds: [...prev.deletedDocumentIds, prev.existingInsuranceDoc.id],
                    existingInsuranceDoc: null,
                    insuranceDocFile: null,
                    insuranceDocPreview: null,
                }));
            } else {
                setFormData((prev) => ({
                    ...prev,
                    insuranceDocFile: null,
                    insuranceDocPreview: null,
                }));
            }
        } else if (docType === 'puc') {
            if (formData.existingPucDoc) {
                setFormData((prev) => ({
                    ...prev,
                    deletedDocumentIds: [...prev.deletedDocumentIds, prev.existingPucDoc.id],
                    existingPucDoc: null,
                    pucDocFile: null,
                    pucDocPreview: null,
                }));
            } else {
                setFormData((prev) => ({
                    ...prev,
                    pucDocFile: null,
                    pucDocPreview: null,
                }));
            }
        } else if (docType === 'challan') {
            if (formData.existingChallanDoc) {
                setFormData((prev) => ({
                    ...prev,
                    deletedDocumentIds: [...prev.deletedDocumentIds, prev.existingChallanDoc.id],
                    existingChallanDoc: null,
                    challanDocFile: null,
                    challanDocPreview: null,
                }));
            } else {
                setFormData((prev) => ({
                    ...prev,
                    challanDocFile: null,
                    challanDocPreview: null,
                }));
            }
        }
    };

    // Supporting Documents Row Handlers
    const handleAddOtherDoc = () => {
        setFormData((prev) => ({
            ...prev,
            otherDocs: [...prev.otherDocs, { name: "", file: null }],
        }));
    };

    const handleOtherDocChange = (index, field, value) => {
        setFormData((prev) => {
            const updated = [...prev.otherDocs];
            updated[index][field] = value;
            return { ...prev, otherDocs: updated };
        });
    };

    const handleRemoveOtherDocRow = (index) => {
        setFormData((prev) => {
            const updated = [...prev.otherDocs];
            updated.splice(index, 1);
            return { ...prev, otherDocs: updated };
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        const formDataObj = new FormData();
        formDataObj.append("vehicle_type", formData.vehicle_type);
        formDataObj.append("vehicle_number", formData.vehicle_number);
        formDataObj.append("vehicle_name", formData.vehicle_name);
        formDataObj.append("brand", formData.brand);
        formDataObj.append("fuel_type", formData.fuel_type);
        formDataObj.append("color", formData.color);
        formDataObj.append("manufacturing_year", formData.manufacturing_year);
        formDataObj.append("engine_number", formData.engine_number);
        formDataObj.append("chassis_number", formData.chassis_number);
        formDataObj.append("purchase_date", formData.purchase_date);
        formDataObj.append("purchase_amount", formData.purchase_amount);
        formDataObj.append("current_km_reading", formData.current_km_reading);
        formDataObj.append("status", formData.status);

        formDataObj.append("insurance_provider", formData.insurance_provider);
        formDataObj.append("policy_number", formData.policy_number);
        formDataObj.append("insurance_type", formData.insurance_type);
        formDataObj.append("insurance_start_date", formData.insurance_start_date);
        formDataObj.append("insurance_end_date", formData.insurance_end_date);

        formDataObj.append("puc_certificate_number", formData.puc_certificate_number);
        formDataObj.append("puc_issue_date", formData.puc_issue_date);
        formDataObj.append("puc_expiry_date", formData.puc_expiry_date);

        formDataObj.append("challan_number", formData.challan_number);
        formDataObj.append("challan_date", formData.challan_date);
        formDataObj.append("violation_type", formData.violation_type);
        formDataObj.append("fine_amount", formData.fine_amount);
        formDataObj.append("payment_status", formData.payment_status);

        // Multiple Vehicle Images
        formData.newImageFiles.forEach((file) => {
            formDataObj.append("vehicle_images[]", file);
        });

        formData.deletedImageIds.forEach((id) => {
            formDataObj.append("deleted_image_ids[]", id);
        });

        // Document Files
        if (formData.insuranceDocFile) {
            formDataObj.append("insurance_document", formData.insuranceDocFile);
        }
        if (formData.pucDocFile) {
            formDataObj.append("puc_document", formData.pucDocFile);
        }
        if (formData.challanDocFile) {
            formDataObj.append("challan_document", formData.challanDocFile);
        }

        // Supporting Documents
        formData.otherDocs.forEach((doc) => {
            if (doc.file) {
                formDataObj.append("other_documents[]", doc.file);
                formDataObj.append("other_document_names[]", doc.name || "Supporting Document");
            }
        });

        formData.deletedDocumentIds.forEach((id) => {
            formDataObj.append("deleted_document_ids[]", id);
        });

        if (currentVehicle) {
            formDataObj.append("_method", "PUT");
        }

        const endpoint = currentVehicle
            ? route("super.vehicles.update", currentVehicle.uuid)
            : route("super.vehicles.store");

        router.post(endpoint, formDataObj, {
            onSuccess: () => {
                setErrors({});
                setIsSubmitting(false);
                handleClose();
                updateUrl(vehicles.current_page);
            },
            onError: (err) => {
                setErrors(err);
                setIsSubmitting(false);
            },
        });
    };

    const getStatusDisplay = (status) => {
        const statusMap = {
            0: { text: "Active", class: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400" },
            1: { text: "Inactive", class: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400" },
            2: { text: "Sold", class: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400" },
        };
        return statusMap[status] || { text: "Unknown", class: "bg-gray-100 text-gray-800" };
    };

    const getInsuranceStatusDisplay = (status) => {
        if (status === null || status === undefined || status === "") return { text: "N/A", class: "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400" };
        const isActive = status === 1;
        return {
            text: isActive ? "Active" : "Expired",
            class: isActive
                ? "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                : "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
        };
    };

    const getPucStatusDisplay = (status) => {
        if (status === null || status === undefined || status === "") return { text: "N/A", class: "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400" };
        const isValid = status === 1;
        return {
            text: isValid ? "Valid" : "Expired",
            class: isValid
                ? "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                : "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
        };
    };

    const handleClose = () => {
        setIsOpen(false);
        setCurrentVehicle(null);
        setErrors({});
    };

    const handleCloseView = () => {
        setIsViewOpen(false);
        setViewVehicle(null);
    };

    const handlePageChange = (page) => {
        setHasUserInteracted(true);
        updateUrl(page);
    };

    const handleDelete = async () => {
        try {
            await router.delete(route("super.vehicles.destroy", vehicleToDelete), {
                preserveScroll: true,
                onSuccess: () => updateUrl(vehicles.current_page),
            });
        } catch (error) {
            console.error("Error deleting vehicle:", error);
        } finally {
            setShowDeleteDialog(false);
            setVehicleToDelete(null);
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
            <Head title="Vehicle Management" />

            <div className="min-h-screen py-[40px] memberbg">
                <div className="mt-[64px]">
                    <VehicleFilters
                        vehicleTypeFilter={vehicleTypeFilter}
                        handleVehicleTypeFilterChange={handleVehicleTypeFilterChange}
                        fuelTypeFilter={fuelTypeFilter}
                        handleFuelTypeFilterChange={handleFuelTypeFilterChange}
                        statusFilter={statusFilter}
                        handleStatusFilterChange={handleStatusFilterChange}
                        insuranceStatusFilter={insuranceStatusFilter}
                        handleInsuranceStatusFilterChange={handleInsuranceStatusFilterChange}
                        sortFilter={sortFilter}
                        handleSortChange={handleSortChange}
                        perPage={perPage}
                        handlePerPageChange={handlePerPageChange}
                        searchTerm={searchTerm}
                        handleSearchChange={handleSearchChange}
                        filterSelectClass={filterSelectClass}
                        handleCreate={handleCreate}
                        statusOptions={statusOptions}
                        insuranceStatusOptions={insuranceStatusOptions}
                    />

                    <VehicleTable
                        vehicles={vehicles}
                        isLoading={isLoading}
                        getStatusDisplay={getStatusDisplay}
                        getInsuranceStatusDisplay={getInsuranceStatusDisplay}
                        getPucStatusDisplay={getPucStatusDisplay}
                        handleView={handleView}
                        handleEdit={handleEdit}
                        handleDelete={(uuid) => { setVehicleToDelete(uuid); setShowDeleteDialog(true); }}
                    />

                    {vehicles.data.length > 0 && (
                        <div className="mt-4 flex justify-between items-center flex-wrap gap-4" style={{ padding: '0px 34px' }}>
                            <span className="dark:text-white text-black">
                                Showing {vehicles.from} to {vehicles.to} of {vehicles.total} entries
                            </span>
                            <nav aria-label="Pagination" className="flex items-center gap-2">
                                <button onClick={() => handlePageChange(vehicles.current_page - 1)}
                                    disabled={vehicles.current_page == 1}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${vehicles.current_page == 1 ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                    <ChevronLeftIcon className="size-4" /><span>BACK</span>
                                </button>
                                <div className="flex items-center gap-1">
                                    {Array.from({ length: vehicles.last_page }, (_, i) => i + 1).map((page) => {
                                        if (page == 1 || page == 2 || page == vehicles.last_page - 1 || page == vehicles.last_page ||
                                            (page >= vehicles.current_page - 1 && page <= vehicles.current_page + 1)) {
                                            return (
                                                <button key={page} onClick={() => handlePageChange(page)}
                                                    className={`flex items-center justify-center w-8 h-8 rounded-full text-sm text-white ${page == vehicles.current_page ? "bg-[rgb(82_70_230)]" : "bg-[rgb(74_91_127)] hover:bg-[rgb(74_91_127)/0.9]"}`}>
                                                    {page}
                                                </button>
                                            );
                                        }
                                        if ((page == 3 && vehicles.current_page > 4) || (page == vehicles.last_page - 2 && vehicles.current_page < vehicles.last_page - 3)) {
                                            return <span key={`ellipsis-${page}`} className="flex items-center justify-center w-8 h-8 rounded-full text-sm text-gray-500">...</span>;
                                        }
                                        return null;
                                    })}
                                </div>
                                <button onClick={() => handlePageChange(vehicles.current_page + 1)}
                                    disabled={vehicles.current_page == vehicles.last_page}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${vehicles.current_page == vehicles.last_page ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                    <span>NEXT</span><ChevronRightIcon className="size-4" />
                                </button>
                            </nav>
                        </div>
                    )}
                </div>
            </div>

            <ConfirmDialog isOpen={showDeleteDialog} onClose={() => { setShowDeleteDialog(false); setVehicleToDelete(null); }}
                onConfirm={handleDelete} message="Are you sure you want to delete this vehicle? This action cannot be undone."
                confirmText="Yes, delete" cancelText="No, cancel" modalSpinnerMessage="Deleting vehicle..." isDanger={true} />

            <VehicleViewModal
                isOpen={isViewOpen}
                onClose={handleCloseView}
                viewVehicle={viewVehicle}
                getStatusDisplay={getStatusDisplay}
                getInsuranceStatusDisplay={getInsuranceStatusDisplay}
                getPucStatusDisplay={getPucStatusDisplay}
                sectionCardClass={sectionCardClass}
                sectionTitleClass={sectionTitleClass}
            />

            <VehicleFormModal
                isOpen={isOpen}
                onClose={handleClose}
                currentVehicle={currentVehicle}
                formData={formData}
                errors={errors}
                isSubmitting={isSubmitting}
                inputClass={inputClass}
                selectClass={selectClass}
                sectionCardClass={sectionCardClass}
                sectionTitleClass={sectionTitleClass}
                handleChange={handleChange}
                handleMultiImageChange={handleMultiImageChange}
                handleRemoveNewImage={handleRemoveNewImage}
                handleRemoveExistingImage={handleRemoveExistingImage}
                handleDocumentChange={handleDocumentChange}
                handleRemoveDocument={handleRemoveDocument}
                handleAddOtherDoc={handleAddOtherDoc}
                handleOtherDocChange={handleOtherDocChange}
                handleRemoveOtherDocRow={handleRemoveOtherDocRow}
                handleSubmit={handleSubmit}
                statusOptions={statusOptions}
            />
        </AuthenticatedLayout>
    );
}