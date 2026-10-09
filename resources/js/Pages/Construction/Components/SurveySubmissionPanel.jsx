import React from "react";
import { useForm } from "@inertiajs/react";
import {
    FaLocationDot,
    FaPaperPlane,
    FaImage,
    FaFileCode,
    FaRulerCombined,
} from "react-icons/fa6";

export default function SurveySubmissionPanel({
    project,
    submitSurveyRoute,
    isSurveyRole = true,
}) {
    const isLocked = project.is_locked;
    const latestSubmission = project.survey_submissions?.[0] || {};

    const form = useForm({
        remarks: latestSubmission.review_notes || "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route(submitSurveyRoute, project.id), { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                <div className="flex items-center gap-2.5">
                    <span className="p-2 rounded-xl bg-sky-600 text-white shadow-sm">
                        <FaLocationDot size={18} />
                    </span>
                    <div>
                        <h3 className="text-base font-bold text-slate-900 dark:text-white">
                            Survey Team – Site Work & Data Submission
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Upload site photos, raw GPS files, reference points, sketches, reports, and submit survey data to Draft Team.
                        </p>
                    </div>
                </div>

                {!isSurveyRole && (
                    <span className="px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        Read-Only Mode (Survey Role Only)
                    </span>
                )}
            </div>

            {/* Strict Audit Notice */}
            <div className="rounded-xl border border-sky-200/80 bg-sky-50/50 p-3.5 dark:border-sky-900/60 dark:bg-sky-950/20">
                <p className="text-xs text-sky-800 dark:text-sky-300 font-medium">
                    🔒 <strong>Strict Audit Policy:</strong> Survey Team members cannot edit Draft files or Accounts/Payment data.
                </p>
            </div>

            {/* Site Summary Card */}
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-3">
                <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                    Site & Location Context
                </h4>
                <div className="grid gap-3 sm:grid-cols-2 text-xs">
                    <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                        <span className="text-[10px] text-slate-400 font-semibold block">Project Code & Name</span>
                        <span className="font-bold text-slate-800 dark:text-slate-200">
                            {project.project_code} • {project.name}
                        </span>
                    </div>

                    <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                        <span className="text-[10px] text-slate-400 font-semibold block">Location</span>
                        <span className="font-medium text-slate-700 dark:text-slate-300">
                            {project.location_name || project.project_address || "Location specified"}
                        </span>
                    </div>
                </div>
            </div>

            {/* Upload & Submission Form */}
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
                <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <FaLocationDot className="text-sky-600" /> Final Survey Submission
                </h4>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4 text-center dark:border-slate-700 dark:bg-slate-800/50">
                            <FaImage className="mx-auto text-sky-500 mb-1" size={20} />
                            <span className="text-xs font-bold text-slate-700 dark:text-slate-300 block">Site Photos</span>
                            <span className="text-[10px] text-slate-400">Captured on-site</span>
                        </div>

                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4 text-center dark:border-slate-700 dark:bg-slate-800/50">
                            <FaFileCode className="mx-auto text-amber-500 mb-1" size={20} />
                            <span className="text-xs font-bold text-slate-700 dark:text-slate-300 block">Raw Files & Coordinates</span>
                            <span className="text-[10px] text-slate-400">.csv, .txt, .dwg raw data</span>
                        </div>

                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4 text-center dark:border-slate-700 dark:bg-slate-800/50">
                            <FaRulerCombined className="mx-auto text-indigo-500 mb-1" size={20} />
                            <span className="text-xs font-bold text-slate-700 dark:text-slate-300 block">Sketches & Reference</span>
                            <span className="text-[10px] text-slate-400">Hand markups & notes</span>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Survey Remarks & Notes <span className="text-rose-500">*</span>
                        </label>
                        <textarea
                            rows={3}
                            required
                            disabled={!isSurveyRole || isLocked}
                            value={form.data.remarks}
                            onChange={(e) => form.setData("remarks", e.target.value)}
                            placeholder="Enter site survey completion notes, benchmark reference details, remarks..."
                            className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-sky-500 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                        />
                    </div>

                    <div className="pt-2 flex justify-end">
                        <button
                            type="submit"
                            disabled={!isSurveyRole || isLocked || form.processing}
                            className="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-sky-700 disabled:opacity-50"
                        >
                            <FaPaperPlane size={13} /> Submit Final Survey to Draft Team
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
