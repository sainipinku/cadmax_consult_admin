import Modal from "@/Components/Modal";
import { useState } from "react";
import { FiExternalLink, FiFileText, FiImage } from "react-icons/fi";

export default function VehicleViewModal({
    isOpen,
    onClose,
    viewVehicle,
    getStatusDisplay,
    getInsuranceStatusDisplay,
    getPucStatusDisplay,
    sectionCardClass,
    sectionTitleClass,
}) {
    const [selectedImage, setSelectedImage] = useState(null);

    const activeImage = selectedImage || (viewVehicle?.images && viewVehicle.images.length > 0 ? viewVehicle.images[0].image_url : viewVehicle?.vehicle_image_url);

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${day}/${month}/${year}`;
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="5xl" topCloseButton={true} handleTopClose={onClose}>
            <div className="p-2 md:p-4 dark:bg-[#080626]">
                <h2 className="text-xl font-bold mb-6 dark:text-white flex items-center justify-between">
                    <span>Vehicle Details</span>
                    {viewVehicle && (
                        <span className="text-sm font-normal text-blue-500 bg-blue-50 dark:bg-blue-900/30 px-3 py-1 rounded-full border border-blue-200 dark:border-blue-800">
                            {viewVehicle.vehicle_id}
                        </span>
                    )}
                </h2>

                {viewVehicle && (
                    <div className="space-y-6">
                        {/* Vehicle Photos Gallery */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>
                                <span className="flex items-center gap-2">
                                    <FiImage className="w-5 h-5 text-blue-500" />
                                    Vehicle Photos Gallery {viewVehicle.images && viewVehicle.images.length > 0 ? `(${viewVehicle.images.length})` : ''}
                                </span>
                            </h3>

                            <div className="flex flex-col md:flex-row gap-4 items-center md:items-start">
                                {/* Main Image Display */}
                                <div className="w-full md:w-64 h-48 rounded-xl overflow-hidden border-2 border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0 shadow-md">
                                    <img
                                        src={activeImage}
                                        alt={viewVehicle.vehicle_name || "Vehicle Photo"}
                                        className="w-full h-full object-cover"
                                        onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                    />
                                </div>

                                {/* Thumbnail Selector Grid */}
                                <div className="flex-1 w-full">
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-2 font-medium">Click thumbnail to view photo:</p>
                                    <div className="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-2 max-h-48 overflow-y-auto p-1">
                                        {viewVehicle.images && viewVehicle.images.length > 0 ? (
                                            viewVehicle.images.map((img) => (
                                                <button
                                                    key={img.id}
                                                    type="button"
                                                    onClick={() => setSelectedImage(img.image_url)}
                                                    className={`h-16 rounded-lg overflow-hidden border-2 transition ${
                                                        activeImage === img.image_url
                                                            ? 'border-blue-500 scale-95 shadow-md'
                                                            : 'border-gray-200 dark:border-gray-700 opacity-70 hover:opacity-100'
                                                    }`}
                                                >
                                                    <img
                                                        src={img.image_url}
                                                        alt="Thumbnail"
                                                        className="w-full h-full object-cover"
                                                        onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                    />
                                                </button>
                                            ))
                                        ) : (
                                            <button
                                                type="button"
                                                className="h-16 rounded-lg overflow-hidden border-2 border-blue-500"
                                            >
                                                <img
                                                    src={viewVehicle.vehicle_image_url}
                                                    alt="Vehicle Cover"
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Basic Information */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Basic Information</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle ID</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_id}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_number}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle Name</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_name || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Brand</label><p className="text-sm font-medium dark:text-white">{viewVehicle.brand || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Fuel Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.fuel_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Color</label><p className="text-sm font-medium dark:text-white">{viewVehicle.color || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Manufacturing Year</label><p className="text-sm font-medium dark:text-white">{viewVehicle.manufacturing_year || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Engine Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.engine_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Chassis Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.chassis_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Purchase Date</label><p className="text-sm font-medium dark:text-white">{formatDate(viewVehicle.purchase_date)}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Purchase Amount</label><p className="text-sm font-medium dark:text-white">{viewVehicle.purchase_amount ? '₹ ' + parseFloat(viewVehicle.purchase_amount).toLocaleString() : '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Current KM Reading</label><p className="text-sm font-medium dark:text-white">{viewVehicle.current_km_reading || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Status</label>
                                    <p className="text-sm font-medium">
                                        <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${getStatusDisplay(viewVehicle.status).class}`}>
                                            {getStatusDisplay(viewVehicle.status).text}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Insurance Details */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Insurance Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Provider</label><p className="text-sm font-medium dark:text-white">{viewVehicle.insurance_provider || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Policy Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.policy_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.insurance_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Start Date</label><p className="text-sm font-medium dark:text-white">{formatDate(viewVehicle.insurance_start_date)}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">End Date</label><p className="text-sm font-medium dark:text-white">{formatDate(viewVehicle.insurance_end_date)}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Status</label>
                                    <p className="text-sm font-medium">
                                        <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${getInsuranceStatusDisplay(viewVehicle.insurance_status).class}`}>
                                            {getInsuranceStatusDisplay(viewVehicle.insurance_status).text}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* PUC Details */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>PUC Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Certificate Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.puc_certificate_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Issue Date</label><p className="text-sm font-medium dark:text-white">{formatDate(viewVehicle.puc_issue_date)}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Expiry Date</label><p className="text-sm font-medium dark:text-white">{formatDate(viewVehicle.puc_expiry_date)}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Status</label>
                                    <p className="text-sm font-medium">
                                        <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${getPucStatusDisplay(viewVehicle.puc_status).class}`}>
                                            {getPucStatusDisplay(viewVehicle.puc_status).text}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Challan Details */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Challan Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Challan Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.challan_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Challan Date</label><p className="text-sm font-medium dark:text-white">{formatDate(viewVehicle.challan_date)}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Violation Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.violation_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Fine Amount</label><p className="text-sm font-medium dark:text-white">{viewVehicle.fine_amount ? '₹ ' + parseFloat(viewVehicle.fine_amount).toLocaleString() : '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Payment Status</label>
                                    <p className="text-sm font-medium">
                                        {viewVehicle.payment_status !== null && viewVehicle.payment_status !== undefined ? (
                                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${viewVehicle.payment_status == 1 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'}`}>
                                                {viewVehicle.payment_status == 1 ? 'Paid' : 'Unpaid'}
                                            </span>
                                        ) : '-'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Vehicle Document Photos / Attachments Section */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>
                                <span className="flex items-center gap-2">
                                    <FiFileText className="w-5 h-5 text-purple-500" />
                                    Attached Vehicle Documents
                                </span>
                            </h3>

                            {viewVehicle.documents && viewVehicle.documents.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    {viewVehicle.documents.map((doc) => (
                                        <div key={doc.id} className="border border-gray-200 dark:border-gray-700 rounded-xl p-3 bg-white dark:bg-gray-800 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                                            <div className="flex items-center gap-3 mb-3">
                                                {doc.is_image ? (
                                                    <img
                                                        src={doc.file_url}
                                                        alt={doc.document_name}
                                                        className="w-14 h-14 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0"
                                                        onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                    />
                                                ) : (
                                                    <div className="w-14 h-14 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center text-purple-600 font-bold text-xs shrink-0">
                                                        PDF
                                                    </div>
                                                )}
                                                <div className="overflow-hidden">
                                                    <span className="text-[10px] uppercase font-bold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 px-2 py-0.5 rounded">
                                                        {doc.document_type}
                                                    </span>
                                                    <p className="text-xs font-semibold text-gray-800 dark:text-gray-200 mt-1 truncate">
                                                        {doc.document_name || `${doc.document_type} Document`}
                                                    </p>
                                                </div>
                                            </div>

                                            <a
                                                href={doc.file_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="w-full text-center text-xs bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/40 py-1.5 rounded-lg transition font-medium flex items-center justify-center gap-1"
                                            >
                                                <FiExternalLink size={13} /> View / Open Document
                                            </a>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs text-gray-500 dark:text-gray-400 italic">No document attachments uploaded for this vehicle.</p>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}