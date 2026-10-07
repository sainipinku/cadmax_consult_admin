import { useState, useRef, useEffect } from "react";
import Loading from "@/Components/Loading";
import NoData from "@/Components/NoData";

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
                                                            <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer">
                                                                <button onClick={() => { handleDelete(vehicle.uuid); handleCloseDropdown(); }} className="flex items-center gap-[8px]">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" className="w-[18px] text-red-600" viewBox="0 0 24 24" width="22" height="22" color="currentColor" fill="none">
                                                                        <path d="M19.5 5.5L18.8803 15.5251C18.7219 18.0864 18.6428 19.3671 18.0008 20.2879C17.6833 20.7431 17.2747 21.1273 16.8007 21.416C15.8421 22 14.559 22 11.9927 22C9.42312 22 8.1383 22 7.17905 21.4149C6.7048 21.1257 6.296 20.7408 5.97868 20.2848C5.33688 19.3626 5.25945 18.0801 5.10461 15.5152L4.5 5.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                        <path d="M3 5.5H21M16.0557 5.5L15.3731 4.09173C14.9196 3.15626 14.6928 2.68852 14.3017 2.39681C14.215 2.3321 14.1231 2.27454 14.027 2.2247C13.5939 2 13.0741 2 12.0345 2C10.9688 2 10.436 2 9.99568 2.23412C9.8981 2.28601 9.80498 2.3459 9.71728 2.41317C9.32164 2.7167 9.10063 3.20155 8.65861 4.17126L8.05292 5.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                        <path d="M9.5 16.5L9.5 10.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                        <path d="M14.5 16.5L14.5 10.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                    </svg>
                                                                    Delete
                                                                </button>
                                                            </li>
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
