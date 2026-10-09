import { useState, useRef, useEffect } from "react";
import Loading from "@/Components/Loading";
import NoData from "@/Components/NoData";
import DeleteActionButton from "@/Components/DeleteActionButton";

export default function VehicleTable({
    vehicles,
    isLoading,
    getStatusDisplay,
    getInsuranceStatusDisplay,
    getPucStatusDisplay,
    handleView,
    handleEdit,
    handleDelete,
}) {
    const [openDropdownId, setOpenDropdownId] = useState(null);
    const [position, setPosition] = useState({ top: 0, left: 0 });

    const handleToggleDropdown = (vehicleId, buttonElement) => {
        if (openDropdownId === vehicleId) {
            setOpenDropdownId(null);
        } else {
            const rect = buttonElement.getBoundingClientRect();
            const dropdownWidth = 120;
            setPosition({ 
                top: rect.bottom + 5, 
                left: Math.max(0, rect.left - dropdownWidth + 24) 
            });
            setOpenDropdownId(vehicleId);
        }
    };

    const handleCloseDropdown = () => {
        setOpenDropdownId(null);
    };

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (openDropdownId && !event.target.closest('.dropdown-container')) {
                setOpenDropdownId(null);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, [openDropdownId]);

    return (
        <div className="p-[15px]">
            <div className="overflow-x-auto tablebxbg p-[15px] rounded-[15px]">
                <table className="min-w-full text-black rounded-2xl dark:text-white">
                    <thead>
                        <tr className="whitespace-nowrap text-left">
                            <th className="p-3">SR No.</th>
                            <th className="p-3">Image</th>
                            <th className="p-3">Vehicle ID</th>
                            <th className="p-3">Vehicle Number</th>
                            <th className="p-3">Vehicle Name</th>
                            <th className="p-3">Type</th>
                            <th className="p-3">Brand</th>
                            <th className="p-3">Fuel</th>
                            <th className="p-3">Docs</th>
                            <th className="p-3">Insurance</th>
                            <th className="p-3">PUC</th>
                            <th className="p-3">Status</th>
                            <th className="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading ? (
                            <tr>
                                <td colSpan="13" className="text-center py-10"><Loading /></td>
                            </tr>
                        ) : vehicles && vehicles.data && vehicles.data.length > 0 ? (
                            vehicles.data.map((vehicle, index) => {
                                const isDropdownOpen = openDropdownId === vehicle.id;
                                const imgCount = vehicle.images ? vehicle.images.length : 0;
                                const docCount = vehicle.documents ? vehicle.documents.length : 0;

                                return (
                                    <tr key={vehicle.id} className="hover:bg-gray-100 dark:hover:bg-[#0a0e25]">
                                        <td className="p-3">{vehicles.from + index}</td>
                                        <td className="p-3">
                                            <div className="relative inline-block">
                                                <img
                                                    src={vehicle.vehicle_image_url}
                                                    alt={vehicle.vehicle_name || 'Vehicle'}
                                                    className="w-10 h-10 rounded-lg object-cover border border-gray-300 dark:border-gray-700"
                                                    onError={(e) => { e.target.onerror = null; e.target.src = '/images/common/data_not_found.png'; }}
                                                />
                                                {imgCount > 1 && (
                                                    <span className="absolute -top-1 -right-1 bg-blue-600 text-white text-[9px] font-bold px-1 rounded-full shadow">
                                                        +{imgCount}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="p-3 font-medium">{vehicle.vehicle_id}</td>
                                        <td className="p-3">{vehicle.vehicle_number || '-'}</td>
                                        <td className="p-3">{vehicle.vehicle_name || '-'}</td>
                                        <td className="p-3">{vehicle.vehicle_type || '-'}</td>
                                        <td className="p-3">{vehicle.brand || '-'}</td>
                                        <td className="p-3">{vehicle.fuel_type || '-'}</td>
                                        <td className="p-3">
                                            {docCount > 0 ? (
                                                <span className="inline-flex items-center gap-1 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 text-xs px-2 py-0.5 rounded-full font-medium border border-purple-200 dark:border-purple-800">
                                                    📄 {docCount} Doc{docCount > 1 ? 's' : ''}
                                                </span>
                                            ) : (
                                                <span className="text-gray-400 text-xs">-</span>
                                            )}
                                        </td>
                                        <td className="p-3">
                                            <span className={`px-2 py-1 rounded-full text-xs ${getInsuranceStatusDisplay(vehicle.insurance_status).class}`}>
                                                {getInsuranceStatusDisplay(vehicle.insurance_status).text}
                                            </span>
                                        </td>
                                        <td className="p-3">
                                            <span className={`px-2 py-1 rounded-full text-xs ${getPucStatusDisplay(vehicle.puc_status).class}`}>
                                                {getPucStatusDisplay(vehicle.puc_status).text}
                                            </span>
                                        </td>
                                        <td className="p-3">
                                            <span className={`px-2 py-1 rounded-full text-xs ${getStatusDisplay(vehicle.status).class}`}>
                                                {getStatusDisplay(vehicle.status).text}
                                            </span>
                                        </td>
                                        <td className="p-3">
                                            <div className="flex items-center justify-center gap-2 relative dropdown-container">
                                                <button onClick={(e) => handleToggleDropdown(vehicle.id, e.currentTarget)} className="text-sm bg-none text-white p-[0]">
                                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect x="0.5" y="0.5" width="23" height="23" rx="4.5" stroke="#727272" />
                                                        <path d="M5 13C5.55228 13 6 12.5523 6 12C6 11.4477 5.55228 11 5 11C4.44772 11 4 11.4477 4 12C4 12.5523 4.44772 13 5 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                        <path d="M11.9004 13C12.4527 13 12.9004 12.5523 12.9004 12C12.9004 11.4477 11.9004 11 10.9004 12C10.9004 12.5523 11.9004 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                        <path d="M18.8008 13C19.3531 13 19.8008 12.5523 19.8008 12C19.8008 11.4477 19.3531 11 18.8008 11C17.2485 11 17.8008 11.4477 17.8008 12C17.8008 12.5523 18.2485 13 18.8008 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                    </svg>
                                                </button>

                                                {isDropdownOpen && (
                                                    <div className="fixed min-w-[120px] z-50 px-[10px] py-[8px] dropDown rounded-[8px] shadow-md bg-white" style={{ top: `${position.top}px`, left: `${position.left}px` }}>
                                                        <ul>
                                                            <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                <button className="flex items-center gap-[8px]" onClick={() => { handleView(vehicle); handleCloseDropdown(); }}>
                                                                    <svg className="w-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                        <path d="M12 9a3.75 3.75 0 1 0 0 7.5A3.75 3.75 0 0 0 12 9Z" />
                                                                        <path d="M2.25 12c0-1.5 2.25-7.5 9.75-7.5s9.75 6 9.75 7.5-2.25 7.5-9.75 7.5S2.25 13.5 2.25 12Zm3 0a6.75 6.75 0 0 0 6.75 6.75A6.75 6.75 0 0 0 18.75 12a6.75 6.75 0 0 0-6.75-6.75A6.75 6.75 0 0 0 5.25 12Z" />
                                                                    </svg>
                                                                    View
                                                                </button>
                                                            </li>
                                                            <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                <button className="flex items-center gap-[8px]" onClick={() => { handleEdit(vehicle); handleCloseDropdown(); }}>
                                                                    <svg className="w-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                        <path d="M16.7574 2.99678L14.7574 4.99678H5V18.9968H19V9.23943L21 7.23943V19.9968C21 20.5491 20.5523 20.9968 20 20.9968H4C3.44772 20.9968 3 20.5491 3 19.9968V3.99678C3 3.4445 3.44772 2.99678 4 2.99678H16.7574ZM20.4853 2.09729L21.8995 3.5115L12.7071 12.7039L11.2954 12.7064L11.2929 11.2897L20.4853 2.09729Z" />
                                                                    </svg>
                                                                    Edit
                                                                </button>
                                                            </li>
                                                            <DeleteActionButton
                                                                resourceType="vehicle"
                                                                resourceId={vehicle.id || vehicle.uuid}
                                                                resourceName={`Vehicle: ${vehicle.vehicle_number || vehicle.vehicle_name || vehicle.id}`}
                                                                onDelete={() => { handleDelete(vehicle.uuid); handleCloseDropdown(); }}
                                                                asDropdownItem={true}
                                                            />
                                                        </ul>
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })
                        ) : (
                            <tr>
                                <td colSpan="13" className="p-0">
                                    <NoData message="No Vehicles found" iconSize={48} className="w-full" />
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
