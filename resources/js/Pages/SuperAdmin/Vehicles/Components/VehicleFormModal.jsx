import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import { FiCamera, FiTrash2, FiPlus, FiFileText, FiUpload, FiX } from "react-icons/fi";

const vehicleTypes = ["Motorcycle", "Car", "SUV", "Pickup", "Truck", "JCB", "Tractor", "Other"];
const fuelTypes = ["Petrol", "Diesel", "CNG", "Electric", "Hybrid"];
const insuranceTypes = ["Third Party", "Comprehensive"];

const currentYear = new Date().getFullYear();
const yearOptions = Array.from({ length: currentYear - 1979 }, (_, i) => currentYear + 1 - i);

export default function VehicleFormModal({
    isOpen,
    onClose,
    currentVehicle,
    formData,
    errors,
    isSubmitting,
    inputClass,
    selectClass,
    sectionCardClass,
    sectionTitleClass,
    handleChange,
    handleMultiImageChange,
    handleRemoveNewImage,
    handleRemoveExistingImage,
    handleDocumentChange,
    handleRemoveDocument,
    handleAddOtherDoc,
    handleOtherDocChange,
    handleRemoveOtherDocRow,
    handleSubmit,
    statusOptions,
}) {
    const multiImageInputRef = useRef(null);
    const insuranceDocRef = useRef(null);
    const pucDocRef = useRef(null);
    const challanDocRef = useRef(null);

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="6xl" topCloseButton={true} handleTopClose={onClose}>
            <div className="p-2 md:p-4 dark:bg-[#080626]">
                <h2 className="text-xl font-bold mb-6 dark:text-white flex items-center justify-between">
                    <span>{currentVehicle ? "Edit Vehicle" : "Add Vehicle"}</span>
                    {currentVehicle && (
                        <span className="text-xs font-normal text-blue-500 bg-blue-50 dark:bg-blue-900/30 px-3 py-1 rounded-full border border-blue-200 dark:border-blue-800">
                            ID: {currentVehicle.vehicle_id}
                        </span>
                    )}
                </h2>

                <form onSubmit={handleSubmit} encType="multipart/form-data">
                    {/* Basic Information */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Basic Information
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vehicle Type <em className="text-red-500">*</em></label>
                                <select name="vehicle_type" value={formData.vehicle_type} onChange={handleChange} className={selectClass('vehicle_type')}>
                                    <option value="">Select Vehicle Type</option>
                                    {vehicleTypes.map((type) => (
                                        <option key={type} value={type}>{type}</option>
                                    ))}
                                </select>
                                {errors.vehicle_type && <p className="text-red-500 text-xs mt-1">{errors.vehicle_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vehicle Number <em className="text-red-500">*</em></label>
                                <input type="text" name="vehicle_number" value={formData.vehicle_number} onChange={handleChange} className={inputClass('vehicle_number')} placeholder="e.g. MH-01-AB-1234" />
                                {errors.vehicle_number && <p className="text-red-500 text-xs mt-1">{errors.vehicle_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vehicle Name / Model</label>
                                <input type="text" name="vehicle_name" value={formData.vehicle_name} onChange={handleChange} className={inputClass('vehicle_name')} placeholder="e.g. Honda City" />
                                {errors.vehicle_name && <p className="text-red-500 text-xs mt-1">{errors.vehicle_name}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Brand</label>
                                <input type="text" name="brand" value={formData.brand} onChange={handleChange} className={inputClass('brand')} placeholder="e.g. Honda, Toyota" />
                                {errors.brand && <p className="text-red-500 text-xs mt-1">{errors.brand}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Fuel Type <em className="text-red-500">*</em></label>
                                <select name="fuel_type" value={formData.fuel_type} onChange={handleChange} className={selectClass('fuel_type')}>
                                    <option value="">Select Fuel Type</option>
                                    {fuelTypes.map((fuel) => (
                                        <option key={fuel} value={fuel}>{fuel}</option>
                                    ))}
                                </select>
                                {errors.fuel_type && <p className="text-red-500 text-xs mt-1">{errors.fuel_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Color</label>
                                <input type="text" name="color" value={formData.color} onChange={handleChange} className={inputClass('color')} placeholder="e.g. White, Red" />
                                {errors.color && <p className="text-red-500 text-xs mt-1">{errors.color}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Manufacturing Year</label>
                                <select name="manufacturing_year" value={formData.manufacturing_year} onChange={handleChange} className={selectClass('manufacturing_year')}>
                                    <option value="">Select Manufacturing Year</option>
                                    {yearOptions.map((year) => (
                                        <option key={year} value={year}>{year}</option>
                                    ))}
                                </select>
                                {errors.manufacturing_year && <p className="text-red-500 text-xs mt-1">{errors.manufacturing_year}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Engine Number</label>
                                <input type="text" name="engine_number" value={formData.engine_number} onChange={handleChange} className={inputClass('engine_number')} />
                                {errors.engine_number && <p className="text-red-500 text-xs mt-1">{errors.engine_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Chassis Number</label>
                                <input type="text" name="chassis_number" value={formData.chassis_number} onChange={handleChange} className={inputClass('chassis_number')} />
                                {errors.chassis_number && <p className="text-red-500 text-xs mt-1">{errors.chassis_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Purchase Date</label>
                                <input type="date" name="purchase_date" value={formData.purchase_date} onChange={handleChange} className={inputClass('purchase_date')} />
                                {errors.purchase_date && <p className="text-red-500 text-xs mt-1">{errors.purchase_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Purchase Amount (₹)</label>
                                <input type="number" name="purchase_amount" value={formData.purchase_amount} onChange={handleChange} className={inputClass('purchase_amount')} placeholder="0.00" min="0" step="0.01" />
                                {errors.purchase_amount && <p className="text-red-500 text-xs mt-1">{errors.purchase_amount}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Current KM Reading</label>
                                <input type="text" name="current_km_reading" value={formData.current_km_reading} onChange={handleChange} className={inputClass('current_km_reading')} placeholder="e.g. 15000 km" />
                                {errors.current_km_reading && <p className="text-red-500 text-xs mt-1">{errors.current_km_reading}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Status <em className="text-red-500">*</em></label>
                                <select name="status" value={formData.status} onChange={handleChange} className={selectClass('status')}>
                                    {statusOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                                {errors.status && <p className="text-red-500 text-xs mt-1">{errors.status}</p>}
                            </div>

                            {/* Multi-Image Upload Section */}
                            <div className="md:col-span-2 mt-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div className="flex items-center justify-between mb-3">
                                    <label className="block text-gray-800 dark:text-gray-200 font-semibold text-sm">
                                        Vehicle Photos (Multiple Upload)
                                    </label>
                                    <span className="text-xs text-gray-500 dark:text-gray-400">
                                        Click <FiX className="inline text-red-500 font-bold" /> on upper-right corner of thumbnail to remove photo
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                                    {/* Existing Saved Vehicle Images */}
                                    {formData.existingImages && formData.existingImages.map((img) => (
                                        <div key={`existing-${img.id}`} className="relative group rounded-lg overflow-visible border-2 border-blue-400 dark:border-blue-600 bg-gray-100 dark:bg-gray-700 h-28 flex items-center justify-center shadow-sm">
                                            <img
                                                src={img.image_url}
                                                alt="Saved Vehicle Image"
                                                className="w-full h-full object-cover rounded-lg"
                                                onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                            />
                                            {/* Upper-right Close button overlay */}
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveExistingImage(img.id)}
                                                className="absolute -top-2 -right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow-md transition-transform hover:scale-110 focus:outline-none z-10"
                                                title="Remove photo"
                                            >
                                                <FiX size={14} />
                                            </button>
                                            <span className="absolute bottom-1 left-1 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded backdrop-blur-sm">
                                                Saved
                                            </span>
                                        </div>
                                    ))}

                                    {/* Newly Picked Image Previews */}
                                    {formData.newImagePreviews && formData.newImagePreviews.map((preview, index) => (
                                        <div key={`new-${index}`} className="relative group rounded-lg overflow-visible border-2 border-emerald-400 dark:border-emerald-600 bg-gray-100 dark:bg-gray-700 h-28 flex items-center justify-center shadow-sm">
                                            <img
                                                src={preview}
                                                alt={`Upload preview ${index + 1}`}
                                                className="w-full h-full object-cover rounded-lg"
                                                onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                            />
                                            {/* Upper-right Close button overlay */}
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveNewImage(index)}
                                                className="absolute -top-2 -right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow-md transition-transform hover:scale-110 focus:outline-none z-10"
                                                title="Remove photo"
                                            >
                                                <FiX size={14} />
                                            </button>
                                            <span className="absolute bottom-1 left-1 bg-emerald-700/80 text-white text-[10px] px-1.5 py-0.5 rounded backdrop-blur-sm">
                                                New
                                            </span>
                                        </div>
                                    ))}

                                    {/* Upload Trigger Button */}
                                    <div
                                        onClick={() => multiImageInputRef.current?.click()}
                                        className="h-28 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-500 dark:hover:border-blue-400 bg-gray-50 dark:bg-gray-800/40 hover:bg-blue-50/50 dark:hover:bg-blue-900/20 flex flex-col items-center justify-center cursor-pointer transition p-2 text-center"
                                    >
                                        <FiCamera className="w-6 h-6 text-gray-400 dark:text-gray-500 mb-1" />
                                        <span className="text-xs font-medium text-blue-600 dark:text-blue-400">+ Add Photos</span>
                                        <span className="text-[10px] text-gray-400 mt-0.5">JPG, PNG, WEBP</span>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    ref={multiImageInputRef}
                                    onChange={handleMultiImageChange}
                                    multiple
                                    accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                    className="hidden"
                                />
                                {errors.vehicle_images && <p className="text-red-500 text-xs mt-1">{errors.vehicle_images}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Insurance Details */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Insurance Details
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Provider</label>
                                <input type="text" name="insurance_provider" value={formData.insurance_provider} onChange={handleChange} className={inputClass('insurance_provider')} placeholder="e.g. ICICI Lombard" />
                                {errors.insurance_provider && <p className="text-red-500 text-xs mt-1">{errors.insurance_provider}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Policy Number</label>
                                <input type="text" name="policy_number" value={formData.policy_number} onChange={handleChange} className={inputClass('policy_number')} />
                                {errors.policy_number && <p className="text-red-500 text-xs mt-1">{errors.policy_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Type</label>
                                <select name="insurance_type" value={formData.insurance_type} onChange={handleChange} className={selectClass('insurance_type')}>
                                    <option value="">Select Type</option>
                                    {insuranceTypes.map((type) => (
                                        <option key={type} value={type}>{type}</option>
                                    ))}
                                </select>
                                {errors.insurance_type && <p className="text-red-500 text-xs mt-1">{errors.insurance_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Start Date</label>
                                <input type="date" name="insurance_start_date" value={formData.insurance_start_date} onChange={handleChange} className={inputClass('insurance_start_date')} />
                                {errors.insurance_start_date && <p className="text-red-500 text-xs mt-1">{errors.insurance_start_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance End Date</label>
                                <input type="date" name="insurance_end_date" value={formData.insurance_end_date} onChange={handleChange} className={inputClass('insurance_end_date')} />
                                {errors.insurance_end_date && <p className="text-red-500 text-xs mt-1">{errors.insurance_end_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Status (Auto-calculated)</label>
                                <input type="text" value={
                                    formData.insurance_end_date
                                        ? (new Date(formData.insurance_end_date) >= new Date(new Date().toDateString()) ? 'Active' : 'Expired')
                                        : 'N/A'
                                } className={inputClass('insurance_status')} disabled />
                            </div>

                            {/* Insurance Document Photo Upload */}
                            <div className="md:col-span-2 mt-2">
                                <label className="block text-gray-700 dark:text-gray-300 mb-2 text-sm font-medium">
                                    Insurance Document Photo / File
                                </label>
                                {formData.insuranceDocPreview || formData.existingInsuranceDoc ? (
                                    <div className="relative inline-block border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-800 shadow-sm pr-8">
                                        <div className="flex items-center gap-3">
                                            {formData.insuranceDocIsImage ? (
                                                <img
                                                    src={formData.insuranceDocPreview || formData.existingInsuranceDoc?.file_url}
                                                    alt="Insurance Doc"
                                                    className="w-16 h-16 object-cover rounded"
                                                    onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                />
                                            ) : (
                                                <div className="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-xs">
                                                    PDF
                                                </div>
                                            )}
                                            <div>
                                                <p className="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                                    {formData.insuranceDocFile ? formData.insuranceDocFile.name : (formData.existingInsuranceDoc?.document_name || 'Insurance Document')}
                                                </p>
                                                <span className="text-[10px] text-green-600 dark:text-green-400 font-medium">
                                                    {formData.insuranceDocFile ? 'New Document Attached' : 'Saved Document'}
                                                </span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleRemoveDocument('insurance')}
                                            className="absolute -top-2 -right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow transition"
                                            title="Remove document"
                                        >
                                            <FiX size={14} />
                                        </button>
                                    </div>
                                ) : (
                                    <div>
                                        <input
                                            type="file"
                                            ref={insuranceDocRef}
                                            onChange={(e) => handleDocumentChange('insurance', e.target.files[0])}
                                            accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf"
                                            className="hidden"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => insuranceDocRef.current?.click()}
                                            className="px-4 py-2 border border-dashed border-gray-400 dark:border-gray-600 hover:border-blue-500 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex items-center gap-2"
                                        >
                                            <FiUpload className="w-4 h-4 text-blue-500" />
                                            <span>Upload Insurance Photo / Document</span>
                                        </button>
                                    </div>
                                )}
                                {errors.insurance_document && <p className="text-red-500 text-xs mt-1">{errors.insurance_document}</p>}
                            </div>
                        </div>
                    </div>

                    {/* PUC Details */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                PUC Details
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">PUC Certificate Number</label>
                                <input type="text" name="puc_certificate_number" value={formData.puc_certificate_number} onChange={handleChange} className={inputClass('puc_certificate_number')} />
                                {errors.puc_certificate_number && <p className="text-red-500 text-xs mt-1">{errors.puc_certificate_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Issue Date</label>
                                <input type="date" name="puc_issue_date" value={formData.puc_issue_date} onChange={handleChange} className={inputClass('puc_issue_date')} />
                                {errors.puc_issue_date && <p className="text-red-500 text-xs mt-1">{errors.puc_issue_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Expiry Date</label>
                                <input type="date" name="puc_expiry_date" value={formData.puc_expiry_date} onChange={handleChange} className={inputClass('puc_expiry_date')} />
                                {errors.puc_expiry_date && <p className="text-red-500 text-xs mt-1">{errors.puc_expiry_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">PUC Status (Auto-calculated)</label>
                                <input type="text" value={
                                    formData.puc_expiry_date
                                        ? (new Date(formData.puc_expiry_date) >= new Date(new Date().toDateString()) ? 'Valid' : 'Expired')
                                        : 'N/A'
                                } className={inputClass('puc_status')} disabled />
                            </div>

                            {/* PUC Document Photo Upload */}
                            <div className="md:col-span-2 mt-2">
                                <label className="block text-gray-700 dark:text-gray-300 mb-2 text-sm font-medium">
                                    PUC Certificate Photo / File
                                </label>
                                {formData.pucDocPreview || formData.existingPucDoc ? (
                                    <div className="relative inline-block border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-800 shadow-sm pr-8">
                                        <div className="flex items-center gap-3">
                                            {formData.pucDocIsImage ? (
                                                <img
                                                    src={formData.pucDocPreview || formData.existingPucDoc?.file_url}
                                                    alt="PUC Doc"
                                                    className="w-16 h-16 object-cover rounded"
                                                    onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                />
                                            ) : (
                                                <div className="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/30 rounded flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold text-xs">
                                                    PDF
                                                </div>
                                            )}
                                            <div>
                                                <p className="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                                    {formData.pucDocFile ? formData.pucDocFile.name : (formData.existingPucDoc?.document_name || 'PUC Document')}
                                                </p>
                                                <span className="text-[10px] text-green-600 dark:text-green-400 font-medium">
                                                    {formData.pucDocFile ? 'New Document Attached' : 'Saved Document'}
                                                </span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleRemoveDocument('puc')}
                                            className="absolute -top-2 -right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow transition"
                                            title="Remove document"
                                        >
                                            <FiX size={14} />
                                        </button>
                                    </div>
                                ) : (
                                    <div>
                                        <input
                                            type="file"
                                            ref={pucDocRef}
                                            onChange={(e) => handleDocumentChange('puc', e.target.files[0])}
                                            accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf"
                                            className="hidden"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => pucDocRef.current?.click()}
                                            className="px-4 py-2 border border-dashed border-gray-400 dark:border-gray-600 hover:border-emerald-500 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex items-center gap-2"
                                        >
                                            <FiUpload className="w-4 h-4 text-emerald-500" />
                                            <span>Upload PUC Photo / Document</span>
                                        </button>
                                    </div>
                                )}
                                {errors.puc_document && <p className="text-red-500 text-xs mt-1">{errors.puc_document}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Challan Details */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Challan Details
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Challan Number</label>
                                <input type="text" name="challan_number" value={formData.challan_number} onChange={handleChange} className={inputClass('challan_number')} />
                                {errors.challan_number && <p className="text-red-500 text-xs mt-1">{errors.challan_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Challan Date</label>
                                <input type="date" name="challan_date" value={formData.challan_date} onChange={handleChange} className={inputClass('challan_date')} />
                                {errors.challan_date && <p className="text-red-500 text-xs mt-1">{errors.challan_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Violation Type</label>
                                <input type="text" name="violation_type" value={formData.violation_type} onChange={handleChange} className={inputClass('violation_type')} placeholder="e.g. Over Speeding" />
                                {errors.violation_type && <p className="text-red-500 text-xs mt-1">{errors.violation_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Fine Amount (₹)</label>
                                <input type="number" name="fine_amount" value={formData.fine_amount} onChange={handleChange} className={inputClass('fine_amount')} placeholder="0.00" min="0" step="0.01" />
                                {errors.fine_amount && <p className="text-red-500 text-xs mt-1">{errors.fine_amount}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Payment Status</label>
                                <select name="payment_status" value={formData.payment_status} onChange={handleChange} className={selectClass('payment_status')}>
                                    <option value="">Select Payment Status</option>
                                    <option value={1}>Paid</option>
                                    <option value={0}>Unpaid</option>
                                </select>
                                {errors.payment_status && <p className="text-red-500 text-xs mt-1">{errors.payment_status}</p>}
                            </div>

                            {/* Challan Document Photo Upload */}
                            <div className="md:col-span-2 mt-2">
                                <label className="block text-gray-700 dark:text-gray-300 mb-2 text-sm font-medium">
                                    Challan Receipt Photo / File
                                </label>
                                {formData.challanDocPreview || formData.existingChallanDoc ? (
                                    <div className="relative inline-block border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-800 shadow-sm pr-8">
                                        <div className="flex items-center gap-3">
                                            {formData.challanDocIsImage ? (
                                                <img
                                                    src={formData.challanDocPreview || formData.existingChallanDoc?.file_url}
                                                    alt="Challan Doc"
                                                    className="w-16 h-16 object-cover rounded"
                                                    onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                />
                                            ) : (
                                                <div className="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded flex items-center justify-center text-amber-600 dark:text-amber-400 font-bold text-xs">
                                                    PDF
                                                </div>
                                            )}
                                            <div>
                                                <p className="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                                    {formData.challanDocFile ? formData.challanDocFile.name : (formData.existingChallanDoc?.document_name || 'Challan Receipt')}
                                                </p>
                                                <span className="text-[10px] text-green-600 dark:text-green-400 font-medium">
                                                    {formData.challanDocFile ? 'New Document Attached' : 'Saved Document'}
                                                </span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleRemoveDocument('challan')}
                                            className="absolute -top-2 -right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow transition"
                                            title="Remove document"
                                        >
                                            <FiX size={14} />
                                        </button>
                                    </div>
                                ) : (
                                    <div>
                                        <input
                                            type="file"
                                            ref={challanDocRef}
                                            onChange={(e) => handleDocumentChange('challan', e.target.files[0])}
                                            accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf"
                                            className="hidden"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => challanDocRef.current?.click()}
                                            className="px-4 py-2 border border-dashed border-gray-400 dark:border-gray-600 hover:border-amber-500 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex items-center gap-2"
                                        >
                                            <FiUpload className="w-4 h-4 text-amber-500" />
                                            <span>Upload Challan Receipt / Photo</span>
                                        </button>
                                    </div>
                                )}
                                {errors.challan_document && <p className="text-red-500 text-xs mt-1">{errors.challan_document}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Additional Vehicle Documents Section */}
                    <div className={sectionCardClass}>
                        <div className="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                            <h3 className="text-base font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <FiFileText className="w-5 h-5 text-purple-500" />
                                Additional Supporting Documents (RC, Permit, Fitness, etc.)
                            </h3>
                            <button
                                type="button"
                                onClick={handleAddOtherDoc}
                                className="px-3 py-1.5 text-xs bg-purple-600 hover:bg-purple-700 text-white rounded-md transition flex items-center gap-1"
                            >
                                <FiPlus size={14} /> Add Document
                            </button>
                        </div>

                        {/* Existing Saved Other Documents */}
                        {formData.existingOtherDocs && formData.existingOtherDocs.length > 0 && (
                            <div className="mb-4">
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Saved Supporting Documents:</p>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    {formData.existingOtherDocs.map((doc) => (
                                        <div key={`existing-doc-${doc.id}`} className="relative border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 bg-white dark:bg-gray-800 flex items-center justify-between shadow-sm">
                                            <div className="flex items-center gap-3">
                                                {doc.is_image ? (
                                                    <img
                                                        src={doc.file_url}
                                                        alt={doc.document_name}
                                                        className="w-12 h-12 object-cover rounded"
                                                        onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                    />
                                                ) : (
                                                    <div className="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded flex items-center justify-center text-purple-600 font-bold text-xs">
                                                        DOC
                                                    </div>
                                                )}
                                                <div>
                                                    <p className="text-xs font-semibold dark:text-white">{doc.document_name || 'Document'}</p>
                                                    <a href={doc.file_url} target="_blank" rel="noreferrer" className="text-[10px] text-blue-500 underline">
                                                        View Saved File
                                                    </a>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveExistingImage(doc.id, 'doc')}
                                                className="bg-red-100 dark:bg-red-900/40 text-red-600 p-1.5 rounded-full hover:bg-red-200 transition"
                                                title="Delete Document"
                                            >
                                                <FiX size={14} />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Dynamic New Supporting Documents Rows */}
                        {formData.otherDocs && formData.otherDocs.length > 0 ? (
                            <div className="space-y-3">
                                {formData.otherDocs.map((docRow, idx) => (
                                    <div key={idx} className="flex flex-col md:flex-row items-center gap-3 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                                        <div className="flex-1 w-full">
                                            <input
                                                type="text"
                                                placeholder="Document Title (e.g. RC Certificate, Fitness)"
                                                value={docRow.name}
                                                onChange={(e) => handleOtherDocChange(idx, 'name', e.target.value)}
                                                className={inputClass(`other_doc_name_${idx}`)}
                                            />
                                        </div>
                                        <div className="w-full md:w-auto flex items-center gap-2">
                                            <input
                                                type="file"
                                                id={`other_doc_file_${idx}`}
                                                onChange={(e) => handleOtherDocChange(idx, 'file', e.target.files[0])}
                                                accept="image/*,.pdf"
                                                className="hidden"
                                            />
                                            <label
                                                htmlFor={`other_doc_file_${idx}`}
                                                className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer flex items-center gap-1.5"
                                            >
                                                <FiUpload size={13} />
                                                <span className="truncate max-w-[140px]">
                                                    {docRow.file ? docRow.file.name : "Choose File"}
                                                </span>
                                            </label>
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveOtherDocRow(idx)}
                                                className="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-md transition"
                                                title="Remove Row"
                                            >
                                                <FiTrash2 size={16} />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            (!formData.existingOtherDocs || formData.existingOtherDocs.length === 0) && (
                                <p className="text-xs text-gray-500 dark:text-gray-400 italic">No additional supporting documents added yet.</p>
                            )
                        )}
                    </div>

                    {/* Submit Buttons */}
                    <div className="flex justify-end space-x-3 mt-6">
                        <button type="button" onClick={onClose} className="px-4 py-2 canclebtn rounded-[7px]">Cancel</button>
                        <button type="submit"
                            className={`flex items-center gap-[5px] px-[20px] py-[12px] text-[15px] text-white rounded-[10px] bluebtbg ${isSubmitting ? "opacity-75 cursor-not-allowed" : ""}`}
                            disabled={isSubmitting}>
                            {isSubmitting ? (
                                <span className="flex items-center justify-center">
                                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    {currentVehicle ? "Updating..." : "Adding..."}
                                </span>
                            ) : currentVehicle ? "Update Vehicle" : "Add Vehicle"}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}