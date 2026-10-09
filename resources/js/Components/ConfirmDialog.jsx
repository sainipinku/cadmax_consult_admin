import { useEffect, useState } from "react";
import LoadingSpinner from "./LoadingSpinner"; // adjust path

export default function ConfirmDialog({
    isOpen,
    onClose,
    onConfirm,
    message = "Are you sure you want to proceed?",
    confirmText = "Yes, I'm sure",
    cancelText = "No, cancel",
    modalSpinnerMessage = "Processing, please wait..."
}) {
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = "hidden";
        } else {
            document.body.style.overflow = "";
            setIsSubmitting(false);
        }

        return () => {
            document.body.style.overflow = "";
            document.body.style.pointerEvents = "";
            document.body.removeAttribute('data-scroll-locked');
        };
    }, [isOpen]);

    if (!isOpen) return null;

    const handleConfirm = async () => {
        try {
            setIsSubmitting(true);
            await onConfirm(); // make sure parent returns a promise
        } catch (err) {
            console.error("Error during confirm:", err);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto">
            <div className="relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-700 transition-all transform overflow-hidden">
                <div className="flex justify-end p-3 pb-0">
                    <button
                        onClick={onClose}
                        type="button"
                        className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-lg text-sm p-1.5 inline-flex items-center transition-colors"
                        disabled={isSubmitting}
                    >
                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                fillRule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clipRule="evenodd"
                            />
                        </svg>
                    </button>
                </div>

                <div className="px-6 pb-6 text-center">
                    {isSubmitting ? (
                        <LoadingSpinner message={modalSpinnerMessage} />
                    ) : (
                        <>
                            <div className="w-16 h-16 bg-red-50 dark:bg-red-950/40 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-100 dark:border-red-900/40">
                                <svg className="w-8 h-8 text-red-600 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <h3 className="text-base md:text-lg font-medium text-slate-800 dark:text-slate-100 mb-6 leading-relaxed whitespace-normal break-words max-w-full">
                                {message}
                            </h3>

                            <div className="flex items-center justify-center gap-3">
                                <button
                                    onClick={handleConfirm}
                                    disabled={isSubmitting}
                                    className="text-white bg-red-600 hover:bg-red-700 active:bg-red-800 focus:ring-4 focus:ring-red-200 dark:focus:ring-red-900/50 font-medium rounded-xl text-sm px-5 py-2.5 transition-all shadow-sm disabled:opacity-50"
                                >
                                    {confirmText}
                                </button>

                                <button
                                    onClick={onClose}
                                    disabled={isSubmitting}
                                    className="text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-700 font-medium rounded-xl text-sm px-5 py-2.5 transition-all disabled:opacity-50"
                                >
                                    {cancelText}
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
