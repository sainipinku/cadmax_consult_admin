import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import { FiCamera } from "react-icons/fi";

export default function EmployeeFormModal({
    isOpen,
    onClose,
    currentEmployee,
    formData,
    errors,
    isSubmitting,
    inputClass,
    selectClass,
    handleChange,
    handleFileChange,
    handleSubmit,
    fileInputRef,
    profilePreview,
    departmentOptions,
    designationOptions,
    departmentDesignationMap,
    roleOptions,
}) {
    const DEFAULT_DEPT_DESIG_MAP = {
        'Administration': ['CEO', 'Director', 'General Manager', 'Manager (Admin)', 'Admin Executive', 'Receptionist', 'Office Assistant', 'Assistant', 'Office Boy'],
        'Architecture': ['Architect', 'Senior Architect', 'AutoCAD Designer'],
        'Planning': ['Manager (Planning)', 'Planning Engineer'],
        'Engineering': ['Manager (Engineering)', 'Civil Engineer', 'Junior Civil Engineer', 'Site Engineer', 'CAD Engineer', 'Supervisor', 'Site Supervisor'],
        'Survey': ['Manager (Survey)', 'Surveyor', 'Senior Surveyor', 'Assistant Surveyor', 'Quantity Surveyor'],
        'Drafting': ['Manager (Drawings)', 'Draftsman', 'Senior Draftsman', 'Junior Draftsman'],
        'Accounts': ['Manager (Accounts)', 'Accountant', 'Senior Accountant'],
        'HR': ['HR Manager', 'HR Executive'],
        'GIS & Mapping': ['GIS Engineer', 'GIS Analyst'],
        'Data Collection': ['Manager (Data Collection)'],
        'Development': ['Senior Consultant', 'Team Leader', 'Project Manager'],
        'Project Management': ['Project Manager', 'Team Leader'],
        'Operations': ['Store Keeper', 'Driver']
    };

    const deptMap = departmentDesignationMap || DEFAULT_DEPT_DESIG_MAP;
    const currentDesignationOptions = formData.department
        ? (deptMap[formData.department] || designationOptions)
        : [];

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="4xl" topCloseButton={true} handleTopClose={onClose}>
            <div className="p-2 md:p-4 dark:bg-[#080626]">
                <h2 className="text-xl font-bold mb-6 dark:text-white">
                    {currentEmployee ? "Edit Employee" : "Add Employee"}
                </h2>

                <form onSubmit={handleSubmit} encType="multipart/form-data">
                    {/* Profile Photo */}
                    <div className="mb-6 flex justify-center">
                        <div className="relative">
                            <div className="w-24 h-24 rounded-full overflow-hidden border-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                {profilePreview ? (
                                    <img
                                        src={profilePreview}
                                        alt="Preview"
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
                            <button type="button" onClick={() => fileInputRef.current?.click()}
                                className="absolute bottom-0 right-0 bg-blue-600 text-white p-1.5 rounded-full hover:bg-blue-700 transition">
                                <FiCamera size={14} />
                            </button>
                            <input type="file" ref={fileInputRef} onChange={handleFileChange} accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" className="hidden" />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Full Name */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Full Name <em className="text-red-500">*</em></label>
                            <input type="text" name="full_name" value={formData.full_name} onChange={handleChange} className={inputClass('full_name')} required />
                            {errors.full_name && <p className="text-red-500 text-xs mt-1">{errors.full_name}</p>}
                        </div>

                        {/* Employee ID */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Employee ID</label>
                            <input type="text" value={currentEmployee ? currentEmployee.employee_id : 'Auto-generated'} className={inputClass('employee_id')} disabled />
                        </div>

                        {/* Email */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Email <em className="text-red-500">*</em></label>
                            <input type="email" name="email" value={formData.email} onChange={handleChange} className={inputClass('email')} required />
                            {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
                        </div>

                        {/* Phone */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Phone <em className="text-red-500">*</em></label>
                            <input
                                type="tel"
                                name="phone"
                                value={formData.phone}
                                onChange={(e) => {
                                    const val = e.target.value.replace(/\D/g, '').slice(0, 10);
                                    handleChange({ target: { name: 'phone', value: val } });
                                }}
                                className={inputClass('phone')}
                                required
                                inputMode="numeric"
                                pattern="[6-9][0-9]{9}"
                                maxLength={10}
                                title="Enter a 10-digit Indian mobile number starting with 6, 7, 8, or 9"
                            />
                            {errors.phone && <p className="text-red-500 text-xs mt-1">{errors.phone}</p>}
                        </div>

                        {/* Password */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">
                                Password {!currentEmployee && <em className="text-red-500">*</em>}
                            </label>
                            <input type="password" name="password" value={formData.password} onChange={handleChange} className={inputClass('password')} placeholder={currentEmployee ? "Leave blank to keep current" : ""} minLength={6} required={!currentEmployee} />
                            {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                        </div>

                        {/* Confirm Password */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Confirm Password</label>
                            <input
                                type="password"
                                name="confirm_password"
                                value={formData.confirm_password}
                                onChange={handleChange}
                                className={inputClass('confirm_password')}
                                minLength={6}
                                required={!currentEmployee || Boolean(formData.password)}
                            />
                            {errors.confirm_password && <p className="text-red-500 text-xs mt-1">{errors.confirm_password}</p>}
                        </div>

                        {/* Gender */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Gender</label>
                            <select name="gender" value={formData.gender} onChange={handleChange} className={selectClass('gender')}>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            {errors.gender && <p className="text-red-500 text-xs mt-1">{errors.gender}</p>}
                        </div>

                        {/* Date of Birth */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Date of Birth</label>
                            <input type="date" name="dob" value={formData.dob} onChange={handleChange} className={inputClass('dob')} />
                            {errors.dob && <p className="text-red-500 text-xs mt-1">{errors.dob}</p>}
                        </div>

                        {/* Department */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Department <em className="text-red-500">*</em></label>
                            <select name="department" value={formData.department} onChange={handleChange} className={selectClass('department')}>
                                <option value="">Select Department</option>
                                {departmentOptions.map((dept) => (
                                    <option key={dept} value={dept}>{dept}</option>
                                ))}
                            </select>
                            {errors.department && <p className="text-red-500 text-xs mt-1">{errors.department}</p>}
                        </div>

                        {/* Designation */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Designation <em className="text-red-500">*</em></label>
                            <select
                                name="designation"
                                value={formData.designation}
                                onChange={handleChange}
                                className={`${selectClass('designation')} ${!formData.department ? 'opacity-60 cursor-not-allowed bg-gray-100 dark:bg-gray-700' : ''}`}
                                disabled={!formData.department}
                            >
                                <option value="">
                                    {!formData.department ? "Select Department First" : "Select Designation"}
                                </option>
                                {currentDesignationOptions.map((desig) => (
                                    <option key={desig} value={desig}>{desig}</option>
                                ))}
                            </select>
                            {errors.designation && <p className="text-red-500 text-xs mt-1">{errors.designation}</p>}
                        </div>

                        {/* Alternate Number */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Alternate Number</label>
                            <input
                                type="tel"
                                name="alternate_number"
                                value={formData.alternate_number}
                                onChange={(e) => handleChange({
                                    target: {
                                        name: "alternate_number",
                                        value: e.target.value.replace(/\D/g, "").slice(0, 10),
                                    },
                                })}
                                className={inputClass('alternate_number')}
                                inputMode="numeric"
                                pattern="[6-9][0-9]{9}"
                                maxLength={10}
                                title="Enter a 10-digit Indian mobile number starting with 6, 7, 8, or 9"
                            />
                            {errors.alternate_number && <p className="text-red-500 text-xs mt-1">{errors.alternate_number}</p>}
                        </div>

                        {/* Aadhaar Number */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Aadhaar Number</label>
                            <input
                                type="text"
                                name="aadhaar_number"
                                value={formData.aadhaar_number}
                                onChange={(e) => handleChange({
                                    target: {
                                        name: "aadhaar_number",
                                        value: e.target.value.replace(/\D/g, "").slice(0, 12),
                                    },
                                })}
                                className={inputClass('aadhaar_number')}
                                inputMode="numeric"
                                pattern="[2-9][0-9]{11}"
                                maxLength={12}
                                title="Enter a valid 12-digit Aadhaar number"
                            />
                            {errors.aadhaar_number && <p className="text-red-500 text-xs mt-1">{errors.aadhaar_number}</p>}
                        </div>

                        {/* PAN Number */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">PAN Number</label>
                            <input
                                type="text"
                                name="pan_number"
                                value={formData.pan_number}
                                onChange={(e) => handleChange({
                                    target: {
                                        name: "pan_number",
                                        value: e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 10),
                                    },
                                })}
                                className={inputClass('pan_number')}
                                pattern="[A-Z]{5}[0-9]{4}[A-Z]"
                                maxLength={10}
                                title="Enter a valid PAN number (for example, ABCDE1234F)"
                            />
                            {errors.pan_number && <p className="text-red-500 text-xs mt-1">{errors.pan_number}</p>}
                        </div>

                        {/* Status */}
                        <div>
                            <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Status</label>
                            <select name="status" value={formData.status} onChange={handleChange} className={selectClass('status')}>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            {errors.status && <p className="text-red-500 text-xs mt-1">{errors.status}</p>}
                        </div>

                        {/* Member Roles */}
                        <div className="md:col-span-2 bg-gray-50 dark:bg-gray-800/40 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">
                                Member Roles (Select all that apply)
                            </label>
                            <div className="flex flex-wrap gap-4">
                                {[
                                    { slug: "surveyor", label: "Survey Man" },
                                    { slug: "vehicle_driver", label: "Driver" },
                                    { slug: "draft_person", label: "Draft Man" },
                                ].map((roleItem) => {
                                    const currentRoles = Array.isArray(formData.roles) ? formData.roles : [];
                                    const isChecked = currentRoles.includes(roleItem.slug);

                                    return (
                                        <label key={roleItem.slug} className="flex items-center gap-2 cursor-pointer bg-white dark:bg-gray-800 px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700 hover:border-blue-500 transition">
                                            <input
                                                type="checkbox"
                                                checked={isChecked}
                                                onChange={(e) => {
                                                    let updated = [...currentRoles];
                                                    if (e.target.checked) {
                                                        if (!updated.includes(roleItem.slug)) updated.push(roleItem.slug);
                                                    } else {
                                                        updated = updated.filter((r) => r !== roleItem.slug);
                                                    }
                                                    handleChange({ target: { name: "roles", value: updated } });
                                                }}
                                                className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <span className="text-sm font-medium text-gray-700 dark:text-gray-200">{roleItem.label}</span>
                                        </label>
                                    );
                                })}
                            </div>
                            {errors.roles && <p className="text-red-500 text-xs mt-1">{errors.roles}</p>}
                        </div>
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
                                    {currentEmployee ? "Updating..." : "Creating..."}
                                </span>
                            ) : currentEmployee ? "Update" : "Create"}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
