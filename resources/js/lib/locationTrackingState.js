/**
 * Pure state and map bounds helpers for SuperAdmin LocationTracking.
 */

export function createClosedHistoryPatch() {
    return {
        selectedEmployee: null,
        activeProfile: null,
        historyPoints: [],
        routePath: [],
        routeMetrics: null,
        selectedCheckpoint: null,
        isPlaying: false,
        playbackIndex: 0,
        historyLoading: false,
        historyError: "",
    };
}

export function isLatestHistoryRequest(activeRequestId, expectedRequestId) {
    return activeRequestId === expectedRequestId;
}

export function fitMapToCoordinates(map, coords, googleMaps = null) {
    if (!map || !Array.isArray(coords) || coords.length === 0) return;

    if (coords.length === 1) {
        map.panTo({
            lat: Number(coords[0].lat || coords[0].latitude),
            lng: Number(coords[0].lng || coords[0].longitude),
        });
        map.setZoom(16);
        return;
    }

    if (!googleMaps?.LatLngBounds) return;

    const bounds = new googleMaps.LatLngBounds();
    coords.forEach((c) =>
        bounds.extend({
            lat: Number(c.lat || c.latitude),
            lng: Number(c.lng || c.longitude),
        })
    );
    map.fitBounds(bounds, { top: 80, right: 80, bottom: 80, left: 340 });
}
