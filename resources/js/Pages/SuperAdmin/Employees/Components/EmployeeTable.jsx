import { useState, useRef, useEffect } from "react";
import Loading from "@/Components/Loading";
import NoData from "@/Components/NoData";

export default function EmployeeTable({
    employees,
    isLoading,
    getStatusDisplay,
    handleEdit,
    handleDelete,
    toggleStatus,
}) {
    const [openDropdownId, setOpenDropdownId] = useState(null);
    const [position, setPosition] = useState({ top: 0, left: 0 });

    const handleToggleDropdown = (employeeId, buttonElement) => {
        if (openDropdownId === employeeId) {
            setOpenDropdownId(null);
        } else {
            const rect = buttonElement.getBoundingClientRect();
            const dropdownWidth = 120;
            setPosition({ 
                top: rect.bottom + 5, 
                left: Math.max(0, rect.left - dropdownWidth + 24) 
            });
            setOpenDropdownId(employeeId);
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

    const getStatusBadge = (status, uuid) => {
        const statusCode = parseInt(status, 10);
        if (statusCode === 1) {
            return (
                <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 border border-green-300 dark:border-green-700">
                    Active
                </span>
            );
        } else if (statusCode === 2) {
            return (
                <div className="flex items-center gap-2">
                    <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border border-red-300 dark:border-red-700">
                        Rejected
                    </span>
                    <button
                        onClick={() => toggleStatus(uuid, 1)}
                        className="px-2 py-1 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md shadow-sm transition-all flex items-center gap-1"
                        title="Approve Employee"
                    >
                        Approve
                    </button>
                </div>
            );
        } else {
            return (
                <div className="flex items-center gap-2">
                    <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 border border-yellow-300 dark:border-yellow-700">
                        Pending Approval
                    </span>
                    <button
                        onClick={() => toggleStatus(uuid, 1)}
                        className="px-2.5 py-1 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md shadow-sm transition-all flex items-center gap-1"
                        title="Approve Employee"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Approve
                    </button>
                </div>
            );
        }
    };

    return (
        <div className="p-[15px]">
            <div className="overflow-x-auto tablebxbg p-[15px] rounded-[15px]">
                <table className="min-w-full text-black rounded-2xl dark:text-white">
                    <thead>
                        <tr className="whitespace-nowrap text-left">
                            <th className="p-3">SR No.</th>
                            <th className="p-3">Photo</th>
                            <th className="p-3">Employee ID</th>
                            <th className="p-3">Full Name</th>
                            <th className="p-3">Email</th>
                            <th className="p-3">Phone</th>
                            <th className="p-3">Role</th>
                            <th className="p-3">Department</th>
                            <th className="p-3">Designation</th>
                            <th className="p-3">Status</th>
                            <th className="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading ? (
                            <tr>
                                <td colSpan="11" className="text-center py-10"><Loading /></td>
                            </tr>
                        ) : employees.data.length > 0 ? (
                            employees.data.map((employee, index) => {
                                const member = employee.member || {};
                                const isDropdownOpen = openDropdownId === employee.id;

                                return (
                                    <tr key={employee.id} className="hover:bg-gray-100 dark:hover:bg-[#0a0e25]">
                                        <td className="p-3">{index + 1}</td>
                                        <td className="p-3">
                                            <img src={member.profile_photo_url} alt={member.name} className="w-10 h-10 rounded-full object-cover" />
                                        </td>
                                        <td className="p-3 font-medium">{employee.employee_id}</td>
                                        <td className="p-3">{member.name || '-'}</td>
                                        <td className="p-3">{member.email || '-'}</td>
                                        <td className="p-3">{member.phone || '-'}</td>
                                        <td className="p-3">{member.role_name || '-'}</td>
                                        <td className="p-3">{member.single_department || '-'}</td>
                                        <td className="p-3">{member.single_designation || '-'}</td>
                                        <td className="p-3">
                                            {getStatusBadge(member.status, employee.uuid)}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex items-center justify-center gap-2 relative dropdown-container">
                                                <button onClick={(e) => handleToggleDropdown(employee.id, e.currentTarget)} className="text-sm bg-none text-white p-[0]">
                                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect x="0.5" y="0.5" width="23" height="23" rx="4.5" stroke="#727272" />
                                                        <path d="M5 13C5.55228 13 6 12.5523 6 12C6 11.4477 5.55228 11 5 11C4.44772 11 4 11.4477 4 12C4 12.5523 4.44772 13 5 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                        <path d="M11.9004 13C12.4527 13 12.9004 12.5523 12.9004 12C12.9004 11.4477 11.9004 11 10.9004 12C10.9004 12.5523 11.9004 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                        <path d="M18.8008 13C19.3531 13 19.8008 12.5523 19.8008 12C19.8008 11.4477 19.3531 11 18.8008 11C17.2485 11 17.8008 11.4477 17.8008 12C17.8008 12.5523 18.2485 13 18.8008 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                    </svg>
                                                </button>

                                                {isDropdownOpen && (
                                                    <div className="fixed min-w-[150px] z-50 px-[10px] py-[8px] dropDown rounded-[8px] shadow-md bg-white border border-gray-200" style={{ top: `${position.top}px`, left: `${position.left}px` }}>
                                                        <ul>
                                                            <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                <button className="flex items-center gap-[8px] w-full" onClick={() => { handleEdit(employee); handleCloseDropdown(); }}>
                                                                    <svg className="w-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                        <path d="M16.7574 2.99678L14.7574 4.99678H5V18.9968H19V9.23943L21 7.23943V19.9968C21 20.5491 20.5523 20.9968 20 20.9968H4C3.44772 20.9968 3 20.5491 3 19.9968V3.99678C3 3.4445 3.44772 2.99678 4 2.99678H16.7574ZM20.4853 2.09729L21.8995 3.5115L12.7071 12.7039L11.2954 12.7064L11.2929 11.2897L20.4853 2.09729Z" />
                                                                    </svg>
                                                                    Edit
                                                                </button>
                                                            </li>

                                                            {member.status != 1 && (
                                                                <li className="flex items-center gap-[5px] p-2 text-[12px] text-green-700 hover:bg-green-50 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                    <button onClick={() => { toggleStatus(employee.uuid, 1); handleCloseDropdown(); }} className="flex items-center gap-[8px] w-full">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" className="w-[18px]" fill="none" stroke="currentColor" strokeWidth="2">
                                                                            <path d="M5 13l4 4L19 7" strokeLinecap="round" strokeLinejoin="round" />
                                                                        </svg>
                                                                        Approve / Activate
                                                                    </button>
                                                                </li>
                                                            )}

                                                            {member.status == 0 && (
                                                                <li className="flex items-center gap-[5px] p-2 text-[12px] text-amber-700 hover:bg-amber-50 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                    <button onClick={() => { toggleStatus(employee.uuid, 2); handleCloseDropdown(); }} className="flex items-center gap-[8px] w-full">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" className="w-[18px]" fill="none" stroke="currentColor" strokeWidth="2">
                                                                            <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
                                                                        </svg>
                                                                        Reject Registration
                                                                    </button>
                                                                </li>
                                                            )}

                                                            {member.status == 1 && (
                                                                <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                    <button onClick={() => { toggleStatus(employee.uuid, 0); handleCloseDropdown(); }} className="flex items-center gap-[8px] w-full">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" className="w-[18px]" fill="none" stroke="currentColor" strokeWidth="1.5">
                                                                            <path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" strokeLinecap="round" strokeLinejoin="round" />
                                                                        </svg>
                                                                        Deactivate
                                                                    </button>
                                                                </li>
                                                            )}

                                                            <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer">
                                                                <button onClick={() => { handleDelete(employee.uuid); handleCloseDropdown(); }} className="flex items-center gap-[8px] w-full">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" className="w-[18px] text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                                                        <path d="M19.5 5.5L18.8803 15.5251C18.7219 18.0864 18.6428 19.3671 18.0008 20.2879C17.6833 20.7431 17.2747 21.1273 16.8007 21.416C15.8421 22 14.559 22 11.9927 22C9.42312 22 8.1383 22 7.17905 21.4149C6.7048 21.1257 6.296 20.7408 5.97868 20.2848C5.33688 19.3626 5.25945 18.0801 5.10461 15.5152L4.5 5.5" strokeLinecap="round" />
                                                                        <path d="M3 5.5H21M16.0557 5.5L15.3731 4.09173C14.9196 3.15626 14.6928 2.68852 14.3017 2.39681C14.215 2.3321 14.1231 2.27454 14.027 2.2247C13.5939 2 13.0741 2 12.0345 2C10.9688 2 10.436 2 9.99568 2.23412C9.8981 2.28601 9.80498 2.3459 9.71728 2.41317C9.32164 2.7167 9.10063 3.20155 8.65861 4.17126L8.05292 5.5" strokeLinecap="round" />
                                                                        <path d="M9.5 16.5L9.5 10.5" strokeLinecap="round" />
                                                                        <path d="M14.5 16.5L14.5 10.5" strokeLinecap="round" />
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
                                <td colSpan="11" className="p-0">
                                    <NoData message="No Employees found" iconSize={48} className="w-full" />
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
