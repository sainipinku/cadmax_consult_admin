const vehicleTypes = ["Motorcycle", "Car", "SUV", "Pickup", "Truck", "JCB", "Tractor", "Other"];
const fuelTypes = ["Petrol", "Diesel", "CNG", "Electric", "Hybrid"];

export default function VehicleFilters({
    isProjectOwner = false,
    companies = [],
    companyFilter = "",
    handleCompanyFilterChange,
    vehicleTypeFilter,
    handleVehicleTypeFilterChange,
    fuelTypeFilter,
    handleFuelTypeFilterChange,
    statusFilter,
    handleStatusFilterChange,
    insuranceStatusFilter,
    handleInsuranceStatusFilterChange,
    sortFilter,
    handleSortChange,
    perPage,
    handlePerPageChange,
    searchTerm,
    handleSearchChange,
    filterSelectClass,
    handleCreate,
    statusOptions,
    insuranceStatusOptions,
}) {
    return (
        <div className="flex justify-between flex-wrap md:flex-nowrap px-[15px] pt-[5px] pb-[15px]">
            <div className="flex items-center flex-col md:flex-row gap-[15px] w-full md:w-auto flex-wrap">
                {isProjectOwner && companies && companies.length > 0 && (
                    <select value={companyFilter} onChange={handleCompanyFilterChange} className={filterSelectClass}>
                        <option value="">All Companies</option>
                        {companies.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                )}

                <select value={vehicleTypeFilter} onChange={handleVehicleTypeFilterChange} className={filterSelectClass}>
                    <option value="">All Types</option>
                    {vehicleTypes.map((type) => (
                        <option key={type} value={type}>{type}</option>
                    ))}
                </select>

                <select value={fuelTypeFilter} onChange={handleFuelTypeFilterChange} className={filterSelectClass}>
                    <option value="">All Fuel</option>
                    {fuelTypes.map((fuel) => (
                        <option key={fuel} value={fuel}>{fuel}</option>
                    ))}
                </select>

                <select value={statusFilter} onChange={handleStatusFilterChange} className={filterSelectClass}>
                    <option value="">All Status</option>
                    {statusOptions && statusOptions.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>

                <select value={insuranceStatusFilter} onChange={handleInsuranceStatusFilterChange} className={filterSelectClass}>
                    {insuranceStatusOptions && insuranceStatusOptions.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>

                <select value={sortFilter} onChange={handleSortChange} className={filterSelectClass}>
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="vehicle_number">Vehicle Number</option>
                </select>

                <select value={perPage} onChange={handlePerPageChange} className={filterSelectClass}>
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>

                <input type="text"
                    className="w-full md:w-auto sm:min-w-[180px] text-sm rounded-md px-4 py-3 focus:outline-none focus:ring-2 transition-all bg-white text-gray-800 placeholder-gray-500 border border-gray-300 hover:border-gray-400 focus:border-blue-500 focus:ring-blue-200 dark:bg-gray-900 dark:text-white dark:placeholder-gray-400 dark:border-gray-700 dark:hover:border-gray-600 dark:focus:border-blue-600 dark:focus:ring-blue-900/30"
                    placeholder="Search Vehicle Number, Name, Brand..."
                    value={searchTerm}
                    onChange={handleSearchChange}
                />
            </div>

            <div className="flex items-center space-x-1 mt-[10px] md:mt-[0]">
                <button onClick={handleCreate} className="flex items-center gap-[5px] px-[20px] py-[12px] text-[15px] text-white rounded-[10px] bluebtbg">
                    Add Vehicle
                </button>
            </div>
        </div>
    );
}