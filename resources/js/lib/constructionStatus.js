/**
 * Centralized UI display label mapping for construction project stages and statuses.
 */

export const READY_FOR_SUBMISSION_LABEL = "Ready for submission";

export const STATUS_DISPLAY_LABELS = {
    ready_for_construction: READY_FOR_SUBMISSION_LABEL,
};

export function resolveStatusDisplayLabel(rawValue, explicitLabel = null) {
    if (explicitLabel) {
        return {
            label: explicitLabel,
            hasCustomLabel: true,
        };
    }

    const normalizedKey = String(rawValue ?? "unknown")
        .toLowerCase()
        .trim()
        .replace(/[\s-]+/g, "_");

    if (Object.prototype.hasOwnProperty.call(STATUS_DISPLAY_LABELS, normalizedKey)) {
        return {
            label: STATUS_DISPLAY_LABELS[normalizedKey],
            hasCustomLabel: true,
        };
    }

    return {
        label: String(rawValue ?? "unknown").replaceAll("_", " "),
        hasCustomLabel: false,
    };
}
