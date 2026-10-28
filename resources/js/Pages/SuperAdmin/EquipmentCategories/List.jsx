import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import NoData from "@/Components/NoData";
import ConfirmDialog from "@/Components/ConfirmDialog";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/20/solid";
import Loading from "@/Components/Loading";

export default function List({ categories, filters }) {
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [currentCategory, setCurrentCategory] = useState(null);
    const [viewCategory, setViewCategory] = useState(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "");
    const [sortFilter, setSortFilter] = useState(filters.sort || "newest");
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [categoryToDelete, setCategoryToDelete] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [hasUserInteracted, setHasUserInteracted] = useState(false);

    const [formData, setFormData] = useState({
        category_name: "",
        description: "",
        status: 1,
    });

    const updateUrl = (newPage = 1) => {
        const params = {
            search: searchTerm,
            status: statusFilter,
            sort: sortFilter,
            per_page: perPage,
            page: newPage,
        };
        setIsLoading(true);
        router.get(route("super.equipment.categories.list"), params, {
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
    }, [searchTerm, statusFilter, sortFilter, perPage]);

    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
        setHasUserInteracted(true);
    };

    useEffect(() => {
        if (currentCategory) {
            setFormData({
                category_name: currentCategory.category_name || "",
                description: currentCategory.description || "",
                status: currentCategory.status ?? 1,
            });
        }
    }, [currentCategory]);

    const handleCreate = () => {
        setCurrentCategory(null);
        setFormData({ category_name: "", description: "", status: 1 });
        setErrors({});
        setIsOpen(true);
    };

    const handleEdit = (category) => {
        setCurrentCategory(category);
        setErrors({});
        setIsOpen(true);
    };

    const handleView = async (category) => {
        try {
            const response = await fetch(route("super.equipment.categories.show", category.id));
            const data = await response.json();
            if (data.success) {
                setViewCategory(data.category);
                setIsViewOpen(true);
            }
        } catch (error) {
            console.error("Error fetching category:", error);
        }
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => ({ ...prev, [name]: value }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        const endpoint = currentCategory
            ? route("super.equipment.categories.update", currentCategory.id)
            : route("super.equipment.categories.store");

        const method = currentCategory ? "PUT" : "POST";

        router[method.toLowerCase()](endpoint, formData, {
            onSuccess: () => {
                setErrors({});
                setIsSubmitting(false);
                handleClose();
                updateUrl(categories.current_page);
            },
            onError: (err) => {
                setErrors(err);
                setIsSubmitting(false);
            },
        });
    };

    const getStatusDisplay = (status) => {
        const statusMap = {
            1: { text: "Active", class: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400" },
            0: { text: "Inactive", class: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400" },
        };
        return statusMap[status] || { text: "Unknown", class: "bg-gray-100 text-gray-800" };
    };

    const handleClose = () => {
        setIsOpen(false);
        setCurrentCategory(null);
        setErrors({});
    };

    const handleCloseView = () => {
        setIsViewOpen(false);
        setViewCategory(null);
    };

    const handlePageChange = (page) => {
        setHasUserInteracted(true);
        updateUrl(page);
    };

    const handleDelete = async () => {
        try {
            await router.delete(route("super.equipment.categories.destroy", categoryToDelete), {
                preserveScroll: true,
                onSuccess: () => updateUrl(categories.current_page),
            });
        } catch (error) {
            console.error("Error deleting category:", error);
        } finally {
            setShowDeleteDialog(false);
            setCategoryToDelete(null);
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
            <Head title="Equipment Categories" />

            <div className="min-h-screen  memberbg">
                <div className="mt-[20px]">
                    {/* Filters Bar */}
                    <div className="px-[34px] mb-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="relative">
                                    <input type="text" value={searchTerm} onChange={handleSearchChange}
                                        placeholder="Search categories..." className="w-full md:w-64 text-sm border rounded-md px-4 py-2.5 focus:outline-none focus:ring-2 transition-all bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-blue-200 dark:bg-gray-900 dark:text-white dark:border-gray-700" />
                                </div>
                                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setHasUserInteracted(true); }} className={filterSelectClass}>
                                    <option value="">All Status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
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
                            <button onClick={handleCreate} className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-[rgb(82_70_230)] rounded-lg hover:bg-[rgb(82_70_230)/0.9] transition">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Category
                            </button>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="px-[34px]">
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            {isLoading && <Loading />}
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[600px]">
                                    <thead className="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Category ID</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Category Name</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {categories.data.length === 0 ? (
                                            <tr>
                                                <td colSpan="5" className="px-4 py-8">
                                                    <NoData message="No equipment categories found. Create your first category!" />
                                                </td>
                                            </tr>
                                        ) : (
                                            categories.data.map((category) => {
                                                const status = getStatusDisplay(category.status);
                                                return (
                                                    <tr key={category.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                                        <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                            {category.category_id}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                            {category.category_name}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 max-w-[250px] truncate">
                                                            {category.description || '-'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-medium ${status.class}`}>
                                                                {status.text}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="flex items-center justify-end gap-2">
                                                                <button onClick={() => handleView(category)} className="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition" title="View">
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                </button>
                                                                <button onClick={() => handleEdit(category)} className="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-md transition" title="Edit">
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                </button>
                                                                <button onClick={() => { setCategoryToDelete(category.id); setShowDeleteDialog(true); }} className="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition" title="Delete">
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
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
                    {categories.data.length > 0 && (
                        <div className="mt-4 flex justify-between items-center flex-wrap gap-4" style={{ padding: '0px 34px' }}>
                            <span className="dark:text-white text-black">
                                Showing {categories.from} to {categories.to} of {categories.total} entries
                            </span>
                            <nav aria-label="Pagination" className="flex items-center gap-2">
                                <button onClick={() => handlePageChange(categories.current_page - 1)}
                                    disabled={categories.current_page == 1}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${categories.current_page == 1 ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                    <ChevronLeftIcon className="size-4" /><span>BACK</span>
                                </button>
                                <div className="flex items-center gap-1">
                                    {Array.from({ length: categories.last_page }, (_, i) => i + 1).map((page) => {
                                        if (page == 1 || page == 2 || page == categories.last_page - 1 || page == categories.last_page ||
                                            (page >= categories.current_page - 1 && page <= categories.current_page + 1)) {
                                            return (
                                                <button key={page} onClick={() => handlePageChange(page)}
                                                    className={`flex items-center justify-center w-8 h-8 rounded-full text-sm text-white ${page == categories.current_page ? "bg-[rgb(82_70_230)]" : "bg-[rgb(74_91_127)] hover:bg-[rgb(74_91_127)/0.9]"}`}>
                                                    {page}
                                                </button>
                                            );
                                        }
                                        if ((page == 3 && categories.current_page > 4) || (page == categories.last_page - 2 && categories.current_page < categories.last_page - 3)) {
                                            return <span key={`ellipsis-${page}`} className="flex items-center justify-center w-8 h-8 rounded-full text-sm text-gray-500">...</span>;
                                        }
                                        return null;
                                    })}
                                </div>
                                <button onClick={() => handlePageChange(categories.current_page + 1)}
                                    disabled={categories.current_page == categories.last_page}
                                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${categories.current_page == categories.last_page ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]" : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"}`}>
                                    <span>NEXT</span><ChevronRightIcon className="size-4" />
                                </button>
                            </nav>
                        </div>
                    )}
                </div>
            </div>

            <ConfirmDialog isOpen={showDeleteDialog} onClose={() => { setShowDeleteDialog(false); setCategoryToDelete(null); }}
                onConfirm={handleDelete} message="Are you sure you want to delete this category? This action cannot be undone."
                confirmText="Yes, delete" cancelText="No, cancel" modalSpinnerMessage="Deleting category..." isDanger={true} />

            {/* View Modal */}
            <Modal show={isViewOpen} onClose={handleCloseView} maxWidth="lg" topCloseButton={true} handleTopClose={handleCloseView}>
                <div className="p-2 md:p-4 dark:bg-[#080626]">
                    <h2 className="text-xl font-bold mb-6 dark:text-white">Category Details</h2>
                    {viewCategory && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Category ID</label>
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">{viewCategory.category_id}</p>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Status</label>
                                    <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-medium ${getStatusDisplay(viewCategory.status).class}`}>
                                        {getStatusDisplay(viewCategory.status).text}
                                    </span>
                                </div>
                                <div className="col-span-2">
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Category Name</label>
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">{viewCategory.category_name}</p>
                                </div>
                                {viewCategory.description && (
                                    <div className="col-span-2">
                                        <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Description</label>
                                        <p className="text-sm text-gray-700 dark:text-gray-300">{viewCategory.description}</p>
                                    </div>
                                )}
                                <div className="col-span-2">
                                    <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Total Equipments</label>
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">{viewCategory.equipments_count || 0}</p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </Modal>

            {/* Add/Edit Modal */}
            <Modal show={isOpen} onClose={handleClose} maxWidth="lg" topCloseButton={true} handleTopClose={handleClose}>
                <div className="p-2 md:p-4 dark:bg-[#080626]">
                    <h2 className="text-xl font-bold mb-6 dark:text-white">
                        {currentCategory ? "Edit Equipment Category" : "Add Equipment Category"}
                    </h2>

                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Category ID</label>
                                <input type="text" value={currentCategory ? currentCategory.category_id : 'Auto-generated'} className={inputClass('category_id')} disabled />
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Category Name <em className="text-red-500">*</em></label>
                                <input type="text" name="category_name" value={formData.category_name} onChange={handleChange} className={inputClass('category_name')} placeholder="Enter category name" />
                                {errors.category_name && <p className="text-red-500 text-xs mt-1">{errors.category_name}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Description</label>
                                <textarea name="description" value={formData.description} onChange={handleChange} className={inputClass('description')} rows="3" placeholder="Enter description (optional)" />
                                {errors.description && <p className="text-red-500 text-xs mt-1">{errors.description}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Status <em className="text-red-500">*</em></label>
                                    <select name="status" value={formData.status} onChange={handleChange} className={selectClass('status')}>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                {errors.status && <p className="text-red-500 text-xs mt-1">{errors.status}</p>}
                            </div>
                        </div>

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
                                        {currentCategory ? "Updating..." : "Adding..."}
                                    </span>
                                ) : currentCategory ? "Update Category" : "Add Category"}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}