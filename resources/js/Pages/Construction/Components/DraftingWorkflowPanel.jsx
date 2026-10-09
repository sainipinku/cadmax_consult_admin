import React, { useState } from "react";
import { useForm } from "@inertiajs/react";
import {
    FaCompassDrafting,
    FaFileCode,
    FaFilePdf,
    FaPaperPlane,
    FaEye,
    FaLock,
} from "react-icons/fa6";
import { FaCheckCircle } from "react-icons/fa";

export default function DraftingWorkflowPanel({
    project,
    acceptRoute,
    submitDraftRoute,
    verifyRoute,
    isDraftRole = true,
}) {
    const isLocked = project.is_locked;
    const currentStage = project.current_stage;
    const isSurveySubmitted = currentStage === "survey_submitted";
    const isDraftingInProgress = currentStage === "drafting_in_progress";
    const isReadyForReview = currentStage === "draft_ready_for_review";

    const latestDraftingJob = project.drafting_jobs?.[0] || {};
    const latestRevision = latestDraftingJob.drawing_revisions?.[0] || project.drawing_revisions?.[0] || {};
    const existingChecklist = project.draft_verification_checklist || {};

    // Form 1: Drawing Preparation Form
    const draftForm = useForm({
        drawing_type: latestRevision.drawing_type || "2D Floor Layout Plan",
        drawing_no: latestRevision.drawing_no || `DWG-${project.project_code || "001"}`,
        revision_no: (latestRevision.revision_no || 0) + 1,
        changes_made: latestRevision.changes_made || "",
        draft_remarks: latestRevision.notes || "",
    });

    // Form 2: Verification Checklists Form
    const verifyForm = useForm({
        dimensions_checked: existingChecklist.dimensions_checked ?? true,
        survey_data_matched: existingChecklist.survey_data_matched ?? true,
        markup_updated: existingChecklist.markup_updated ?? true,
        drawing_checked: existingChecklist.drawing_checked ?? true,
        final_pdf_dwg_ready: existingChecklist.final_pdf_dwg_ready ?? true,
        verification_notes: existingChecklist.verification_notes || "",
    });

    const handleAcceptDrafting = (e) => {
        e.preventDefault();
        draftForm.post(route(acceptRoute, project.id), { preserveScroll: true });
    };

    const handleSaveDrawing = (e) => {
        e.preventDefault();
        draftForm.post(route(submitDraftRoute, project.id), { preserveScroll: true });
    };

    const handleVerifyAndSend = (e) => {
        e.preventDefault();
        verifyForm.post(route(verifyRoute, project.id), { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                <div className="flex items-center gap-2.5">
                    <span className="p-2 rounded-xl bg-indigo-600 text-white shadow-sm">
                        <FaCompassDrafting size={18} />
                    </span>
                    <div>
                        <h3 className="text-base font-bold text-slate-900 dark:text-white">
                            Draft Team – Map & Drawing Management
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Review survey data, accept for drafting, prepare map drawings, complete verification checks, and send to Accounts.
                        </p>
                    </div>
                </div>

                {!isDraftRole && (
                    <span className="px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <FaEye size={10} className="inline mr-1" /> Read-Only Mode (Drafter Role Only)
                    </span>
                )}
            </div>

            {/* Step A: Accept for Drafting Banner */}
            {isSurveySubmitted && (
                <div className="rounded-2xl border border-sky-300 bg-sky-50/80 p-5 dark:border-sky-800 dark:bg-sky-950/40 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h4 className="text-sm font-bold text-sky-900 dark:text-sky-200">
                            New Survey Submission Received
                        </h4>
                        <p className="text-xs text-sky-700 dark:text-sky-300 mt-0.5">
                            Survey team has completed site work and submitted survey files. Click accept to begin drafting.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={handleAcceptDrafting}
                        disabled={!isDraftRole || isLocked || draftForm.processing}
                        className="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-xs font-bold text-white shadow hover:bg-sky-700 disabled:opacity-50 shrink-0"
                    >
                        Accept for Drafting
                    </button>
                </div>
            )}

            {/* Read-Only Survey Data Section */}
            <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                    <FaEye className="text-indigo-500" /> Read-Only Survey Reference Data
                </h4>
                <div className="grid gap-3 sm:grid-cols-3 text-xs">
                    <div className="rounded-lg bg-white p-3 border border-slate-200/70 dark:bg-slate-800 dark:border-slate-700">
                        <span className="text-[10px] text-slate-400 font-semibold block">Site Address</span>
                        <span className="font-medium text-slate-800 dark:text-slate-200">
                            {project.location_name || project.project_address || "Location specified"}
                        </span>
                    </div>
                    <div className="rounded-lg bg-white p-3 border border-slate-200/70 dark:bg-slate-800 dark:border-slate-700">
                        <span className="text-[10px] text-slate-400 font-semibold block">Survey Status</span>
                        <span className="font-semibold text-emerald-600">Survey Completed & Approved</span>
                    </div>
                    <div className="rounded-lg bg-white p-3 border border-slate-200/70 dark:bg-slate-800 dark:border-slate-700">
                        <span className="text-[10px] text-slate-400 font-semibold block">Client Name</span>
                        <span className="font-medium text-slate-800 dark:text-slate-200">
                            {project.client?.name || "Client"}
                        </span>
                    </div>
                </div>
            </div>

            {/* Step B: Map Preparation Form */}
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
                <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <FaCompassDrafting className="text-indigo-600" /> Map / Drawing Preparation
                </h4>

                <form onSubmit={handleSaveDrawing} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Drawing Type <span className="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                disabled={!isDraftRole || isLocked}
                                value={draftForm.data.drawing_type}
                                onChange={(e) => draftForm.setData("drawing_type", e.target.value)}
                                placeholder="e.g. Architectural Floor Plan, Key Plan, Site Map"
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Drawing Number <span className="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                disabled={!isDraftRole || isLocked}
                                value={draftForm.data.drawing_no}
                                onChange={(e) => draftForm.setData("drawing_no", e.target.value)}
                                placeholder="e.g. DWG-2026-001"
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Revision Number
                            </label>
                            <input
                                type="number"
                                min="1"
                                disabled={!isDraftRole || isLocked}
                                value={draftForm.data.revision_no}
                                onChange={(e) => draftForm.setData("revision_no", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Changes Made (In this revision)
                            </label>
                            <input
                                type="text"
                                disabled={!isDraftRole || isLocked}
                                value={draftForm.data.changes_made}
                                onChange={(e) => draftForm.setData("changes_made", e.target.value)}
                                placeholder="Brief details of modifications made..."
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Draft Remarks
                        </label>
                        <textarea
                            rows={2}
                            disabled={!isDraftRole || isLocked}
                            value={draftForm.data.draft_remarks}
                            onChange={(e) => draftForm.setData("draft_remarks", e.target.value)}
                            placeholder="Drafting notes, scale, layers used, remarks..."
                            className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:bg-slate-100"
                        />
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={!isDraftRole || isLocked || draftForm.processing}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Save / Submit Draft Map
                        </button>
                    </div>
                </form>
            </div>

            {/* Step C: Draft Verification Checklists */}
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
                <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <FaCheckCircle className="text-emerald-500" /> Draft Verification Checklists
                    </h4>
                    <span className="text-[11px] font-semibold text-slate-500">
                        Required before sending to Accounts
                    </span>
                </div>

                <form onSubmit={handleVerifyAndSend} className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50 cursor-pointer">
                            <input
                                type="checkbox"
                                disabled={!isDraftRole || isLocked}
                                checked={verifyForm.data.dimensions_checked}
                                onChange={(e) => verifyForm.setData("dimensions_checked", e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span className="text-xs font-medium text-slate-800 dark:text-slate-200">
                                Dimensions checked
                            </span>
                        </label>

                        <label className="flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50 cursor-pointer">
                            <input
                                type="checkbox"
                                disabled={!isDraftRole || isLocked}
                                checked={verifyForm.data.survey_data_matched}
                                onChange={(e) => verifyForm.setData("survey_data_matched", e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span className="text-xs font-medium text-slate-800 dark:text-slate-200">
                                Survey data matched
                            </span>
                        </label>

                        <label className="flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50 cursor-pointer">
                            <input
                                type="checkbox"
                                disabled={!isDraftRole || isLocked}
                                checked={verifyForm.data.markup_updated}
                                onChange={(e) => verifyForm.setData("markup_updated", e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span className="text-xs font-medium text-slate-800 dark:text-slate-200">
                                Markup updated
                            </span>
                        </label>

                        <label className="flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50 cursor-pointer">
                            <input
                                type="checkbox"
                                disabled={!isDraftRole || isLocked}
                                checked={verifyForm.data.drawing_checked}
                                onChange={(e) => verifyForm.setData("drawing_checked", e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span className="text-xs font-medium text-slate-800 dark:text-slate-200">
                                Drawing checked
                            </span>
                        </label>

                        <label className="flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50 cursor-pointer sm:col-span-2">
                            <input
                                type="checkbox"
                                disabled={!isDraftRole || isLocked}
                                checked={verifyForm.data.final_pdf_dwg_ready}
                                onChange={(e) => verifyForm.setData("final_pdf_dwg_ready", e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span className="text-xs font-medium text-slate-800 dark:text-slate-200">
                                Final PDF/DWG ready
                            </span>
                        </label>
                    </div>

                    <div className="pt-2 flex justify-end">
                        <button
                            type="submit"
                            disabled={!isDraftRole || isLocked || verifyForm.processing}
                            className="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-amber-700 disabled:opacity-50"
                        >
                            <FaPaperPlane size={13} /> Verify & Send to Accounts Team
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
