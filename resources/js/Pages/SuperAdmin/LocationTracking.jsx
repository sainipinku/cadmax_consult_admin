import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { usePage } from "@inertiajs/react";
import {
    GoogleMap,
    InfoWindowF,
    MarkerF,
    OverlayView,
    OverlayViewF,
    PolylineF,
    useJsApiLoader,
} from "@react-google-maps/api";
import {
    FaCalendarAlt,
    FaCalendarDay,
    FaCheck,
    FaChevronLeft,
    FaChevronRight,
    FaClock,
    FaCrosshairs,
    FaEnvelope,
    FaFilter,
    FaGlobe,
    FaLayerGroup,
    FaLocationArrow,
    FaMapMarkerAlt,
    FaMinus,
    FaPause,
    FaPhoneAlt,
    FaPlay,
    FaPlus,
    FaRedoAlt,
    FaRoute,
    FaSearch,
    FaShieldAlt,
    FaTimes,
    FaUser,
    FaUserTie,
} from "react-icons/fa";
import AuthenticatedLayout from "./Layouts/AuthenticatedLayout";
import {
    GOOGLE_MAPS_LOADER_ID,
    GOOGLE_MAPS_LIBRARIES,
    resolveGoogleMapsApiKey,
} from "@/lib/googleMaps";

const mapContainerStyle = { width: "100%", height: "100%" };
const defaultCenter = { lat: 26.9124, lng: 75.7873 };

// Snapchat Snap Map pastel aesthetic styles for Google Maps
const snapMapStyles = [
    {
        elementType: "geometry",
        stylers: [{ color: "#f1f5f9" }],
    },
    {
        elementType: "labels.text.fill",
        stylers: [{ color: "#334155" }],
    },
    {
        elementType: "labels.text.stroke",
        stylers: [{ color: "#ffffff" }, { weight: 3 }],
    },
    {
        featureType: "administrative.locality",
        elementType: "labels.text.fill",
        stylers: [{ color: "#0f172a" }, { weight: 600 }],
    },
    {
        featureType: "landscape.natural",
        elementType: "geometry",
        stylers: [{ color: "#dcfce7" }], // Snap Map mint green
    },
    {
        featureType: "landscape.man_made",
        elementType: "geometry",
        stylers: [{ color: "#f8fafc" }],
    },
    {
        featureType: "poi",
        elementType: "geometry",
        stylers: [{ color: "#d1fae5" }],
    },
    {
        featureType: "poi.park",
        elementType: "geometry",
        stylers: [{ color: "#bbf7d0" }], // Snap Map lush park grass
    },
    {
        featureType: "poi.park",
        elementType: "labels.text.fill",
        stylers: [{ color: "#166534" }],
    },
    {
        featureType: "road",
        elementType: "geometry",
        stylers: [{ color: "#ffffff" }],
    },
    {
        featureType: "road",
        elementType: "geometry.stroke",
        stylers: [{ color: "#e2e8f0" }, { weight: 1.5 }],
    },
    {
        featureType: "road.highway",
        elementType: "geometry",
        stylers: [{ color: "#fef08a" }], // Snap Map soft golden highway
    },
    {
        featureType: "road.highway",
        elementType: "geometry.stroke",
        stylers: [{ color: "#fde047" }, { weight: 1.5 }],
    },
    {
        featureType: "transit.line",
        elementType: "geometry",
        stylers: [{ color: "#cbd5e1" }],
    },
    {
        featureType: "water",
        elementType: "geometry",
        stylers: [{ color: "#93c5fd" }], // Snap Map powder cyan water
    },
    {
        featureType: "water",
        elementType: "labels.text.fill",
        stylers: [{ color: "#1d4ed8" }],
    },
];

function toDateInputValue(date) {
    const d = new Date(date);
    const month = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${d.getFullYear()}-${month}-${day}`;
}

function relativeTimeSnap(value) {
    if (!value) return "offline";
    const ts = new Date(value).getTime();
    if (Number.isNaN(ts)) return "offline";

    const seconds = Math.max(0, Math.floor((Date.now() - ts) / 1000));
    if (seconds < 90) return "now";
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
    return `${Math.floor(seconds / 86400)}d`;
}

function formatTimeString(isoString) {
    if (!isoString) return "";
    try {
        const d = new Date(isoString);
        return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    } catch {
        return "";
    }
}

function formatDateDisplay(dateStr) {
    if (!dateStr) return "";
    const todayStr = toDateInputValue(new Date());
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const yesterdayStr = toDateInputValue(yesterday);

    if (dateStr === todayStr) return "Today";
    if (dateStr === yesterdayStr) return "Yesterday";

    try {
        const [year, month, day] = dateStr.split("-").map(Number);
        const d = new Date(year, month - 1, day);
        return d.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
    } catch {
        return dateStr;
    }
}

function getAvatarInitials(name = "") {
    return (
        name
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((p) => p[0] || "")
            .join("")
            .toUpperCase() || "E"
    );
}

const AVATAR_BG_GRADIENTS = [
    "from-indigo-500 to-purple-600",
    "from-emerald-400 to-teal-600",
    "from-rose-500 to-pink-600",
    "from-amber-400 to-orange-500",
    "from-cyan-400 to-blue-600",
];

function getAvatarGradient(name = "") {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_BG_GRADIENTS[Math.abs(hash) % AVATAR_BG_GRADIENTS.length];
}

function getAvatarSrc(employee) {
    if (!employee) return "";
    const img = employee.profileImage;
    if (img && typeof img === "string" && !img.includes("profileimg.png") && img.length > 5) {
        return img;
    }
    // High-quality personalized Bitmoji avatar via Dicebear Avataaars
    const seed = encodeURIComponent(employee.employeeId || employee.name || "cadmax-user");
    return `https://api.dicebear.com/7.x/avataaars/svg?seed=${seed}&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf`;
}

function separateCollidingLocations(employees) {
    if (!Array.isArray(employees) || employees.length === 0) return [];

    const result = [];
    const visited = new Set();
    const threshold = 0.00025; // ~25-30m

    for (let i = 0; i < employees.length; i++) {
        const current = employees[i];
        if (visited.has(current.employeeId)) continue;

        const cluster = [current];
        visited.add(current.employeeId);

        for (let j = i + 1; j < employees.length; j++) {
            const other = employees[j];
            if (visited.has(other.employeeId)) continue;
            const dLat = Math.abs(Number(current.latitude) - Number(other.latitude));
            const dLng = Math.abs(Number(current.longitude) - Number(other.longitude));
            if (dLat < threshold && dLng < threshold) {
                cluster.push(other);
                visited.add(other.employeeId);
            }
        }

        if (cluster.length === 1) {
            result.push({
                ...cluster[0],
                renderLat: Number(cluster[0].latitude),
                renderLng: Number(cluster[0].longitude),
            });
        } else {
            const radius = 0.00035; // ~35-40m offset
            cluster.forEach((emp, k) => {
                const angle = (2 * Math.PI * k) / cluster.length;
                result.push({
                    ...emp,
                    renderLat: Number(emp.latitude) + radius * Math.cos(angle),
                    renderLng: Number(emp.longitude) + radius * Math.sin(angle),
                });
            });
        }
    }

    return result;
}

export default function LocationTracking({
    isProjectOwner: isProjectOwnerProp,
    superAdmins: superAdminsProp = [],
    currentSuperAdmin: currentSuperAdminProp = null,
}) {
    const { props: pageProps } = usePage();
    const isProjectOwner = Boolean(
        isProjectOwnerProp ??
        pageProps?.isProjectOwner ??
        (pageProps?.auth?.user?.id == 1 || pageProps?.auth?.user?.is_project_owner == 1)
    );
    const [superAdmins, setSuperAdmins] = useState(
        superAdminsProp?.length ? superAdminsProp : (pageProps?.superAdmins || [])
    );
    const currentSuperAdmin = currentSuperAdminProp || pageProps?.currentSuperAdmin || pageProps?.auth?.user || null;

    const apiKey = resolveGoogleMapsApiKey(null, pageProps);
    const { isLoaded, loadError } = useJsApiLoader({
        id: GOOGLE_MAPS_LOADER_ID,
        googleMapsApiKey: apiKey,
        libraries: GOOGLE_MAPS_LIBRARIES,
    });

    const [map, setMap] = useState(null);
    const [employees, setEmployees] = useState([]);
    const [selectedEmployee, setSelectedEmployee] = useState(null);
    const [activeProfile, setActiveProfile] = useState(null);
    const [userCurrentLocation, setUserCurrentLocation] = useState(null);
    const [search, setSearch] = useState("");
    const [selectedSuperAdmin, setSelectedSuperAdmin] = useState("");
    const [department, setDepartment] = useState("");
    const [statusFilter, setStatusFilter] = useState("");
    const [activeTheme, setActiveTheme] = useState("snap"); // snap, carto, mapbox, satellite, roadmap
    const [themeMenuOpen, setThemeMenuOpen] = useState(false);

    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState("");
    const [lastUpdated, setLastUpdated] = useState(null);

    // Location History State
    const [selectedDate, setSelectedDate] = useState(() => toDateInputValue(new Date()));
    const [dateFilterMode, setDateFilterMode] = useState("today"); // today, yesterday, custom
    const [historyPoints, setHistoryPoints] = useState([]);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [historyError, setHistoryError] = useState("");
    const [routePath, setRoutePath] = useState([]);
    const [routeMetrics, setRouteMetrics] = useState(null);
    const [selectedCheckpoint, setSelectedCheckpoint] = useState(null);
    const [isPlaying, setIsPlaying] = useState(false);
    const [playbackIndex, setPlaybackIndex] = useState(0);

    const refreshTimer = useRef(null);
    const playbackTimer = useRef(null);
    const hasFitInitialLocations = useRef(false);

    const basePath = window.location.pathname.includes("/super/")
        ? window.location.pathname.slice(0, window.location.pathname.indexOf("/super/"))
        : "";

    // Load Live Employees
    const loadLocations = useCallback(
        async (silent = false) => {
            if (!silent) setLoading(true);
            else setRefreshing(true);
            setError("");

            try {
                const params = new URLSearchParams();
                if (selectedSuperAdmin) {
                    params.append("super_admin_id", selectedSuperAdmin);
                }
                const queryString = params.toString() ? `?${params.toString()}` : "";

                const response = await fetch(`${basePath}/api/admin/employee-locations${queryString}`, {
                    credentials: "same-origin",
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                });
                if (response.status === 401 || response.status === 403) {
                    throw new Error("Your super-admin session has expired or is not authorized.");
                }
                if (!response.ok) throw new Error("Employee locations could not be loaded.");

                const payload = await response.json();
                if (!payload?.success || !Array.isArray(payload.data)) {
                    throw new Error("The location service returned an unexpected response.");
                }

                setEmployees(payload.data);
                if (Array.isArray(payload.superAdmins) && payload.superAdmins.length > 0) {
                    setSuperAdmins(payload.superAdmins);
                }
                setLastUpdated(new Date());
            } catch (err) {
                setError(err.message || "Employee locations could not be loaded.");
            } finally {
                setLoading(false);
                setRefreshing(false);
            }
        },
        [basePath, selectedSuperAdmin]
    );

    useEffect(() => {
        loadLocations();
    }, [loadLocations]);

    // Periodic auto-refresh every 60s
    useEffect(() => {
        refreshTimer.current = window.setInterval(() => {
            if (!selectedEmployee) {
                loadLocations(true);
            }
        }, 60000);
        return () => window.clearInterval(refreshTimer.current);
    }, [loadLocations, selectedEmployee]);

    // Handle Map Custom Themes (Carto Voyager, Mapbox Streets, Snap Map Pastel, Satellite)
    useEffect(() => {
        if (!map) return;

        const cartoKey = pageProps.carto_api_key || import.meta.env.VITE_CARTO_API_KEY || "";
        const mapboxToken = pageProps.mapbox_token || import.meta.env.VITE_MAPBOX_TOKEN || "";

        if (activeTheme === "carto") {
            if (!map.mapTypes.get("carto")) {
                const cartoType = new window.google.maps.ImageMapType({
                    getTileUrl: (coord, zoom) => {
                        const sub = ["a", "b", "c", "d"];
                        const s = sub[Math.abs(coord.x + coord.y) % sub.length];
                        const param = cartoKey ? `?api_key=${cartoKey}` : "";
                        return `https://${s}.basemaps.cartocdn.com/rastertiles/voyager/${zoom}/${coord.x}/${coord.y}.png${param}`;
                    },
                    tileSize: new window.google.maps.Size(256, 256),
                    maxZoom: 20,
                    minZoom: 1,
                    name: "Carto Voyager",
                });
                map.mapTypes.set("carto", cartoType);
            }
            map.setMapTypeId("carto");
        } else if (activeTheme === "mapbox") {
            if (!map.mapTypes.get("mapbox")) {
                const mapboxType = new window.google.maps.ImageMapType({
                    getTileUrl: (coord, zoom) => {
                        return `https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/256/${zoom}/${coord.x}/${coord.y}@2x?access_token=${mapboxToken}`;
                    },
                    tileSize: new window.google.maps.Size(256, 256),
                    maxZoom: 20,
                    minZoom: 1,
                    name: "Mapbox Streets",
                });
                map.mapTypes.set("mapbox", mapboxType);
            }
            map.setMapTypeId("mapbox");
        } else if (activeTheme === "satellite") {
            map.setMapTypeId("hybrid");
        } else if (activeTheme === "roadmap") {
            map.setMapTypeId("roadmap");
            map.setOptions({ styles: [] });
        } else {
            // "snap" - Snap Map pastel Google styles
            map.setMapTypeId("roadmap");
            map.setOptions({ styles: snapMapStyles });
        }
    }, [map, activeTheme, pageProps]);

    // Filtered visible employees
    const departments = useMemo(
        () => [...new Set(employees.map((e) => e.department).filter(Boolean))].sort(),
        [employees]
    );

    const visibleEmployees = useMemo(() => {
        const q = search.trim().toLowerCase();
        return employees.filter((emp) => {
            const matchesSearch =
                !q ||
                emp.name?.toLowerCase().includes(q) ||
                emp.employeeId?.toLowerCase().includes(q) ||
                emp.superAdminName?.toLowerCase().includes(q) ||
                emp.companyName?.toLowerCase().includes(q);
            const matchesSuperAdmin =
                !selectedSuperAdmin ||
                String(emp.superAdminId) === String(selectedSuperAdmin);
            return (
                matchesSearch &&
                matchesSuperAdmin &&
                (!department || emp.department === department) &&
                (!statusFilter || emp.status === statusFilter)
            );
        });
    }, [employees, search, department, statusFilter, selectedSuperAdmin]);

    const separatedEmployees = useMemo(() => {
        return separateCollidingLocations(visibleEmployees);
    }, [visibleEmployees]);

    const handleLocateMe = useCallback(() => {
        if (!navigator.geolocation) {
            alert("Geolocation is not supported by your browser.");
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const loc = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                setUserCurrentLocation(loc);
                if (map) {
                    map.panTo(loc);
                    map.setZoom(16);
                }
            },
            (err) => {
                console.warn("Locate me error:", err);
                alert("Could not access your location. Please check browser permissions.");
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }, [map]);

    // Fit map bounds to points
    const fitCoordinates = useCallback(
        (coords) => {
            if (!map || coords.length === 0) return;
            if (coords.length === 1) {
                map.panTo({ lat: Number(coords[0].lat || coords[0].latitude), lng: Number(coords[0].lng || coords[0].longitude) });
                map.setZoom(16);
                return;
            }

            const bounds = new window.google.maps.LatLngBounds();
            coords.forEach((c) =>
                bounds.extend({
                    lat: Number(c.lat || c.latitude),
                    lng: Number(c.lng || c.longitude),
                })
            );
            map.fitBounds(bounds, { top: 80, right: 80, bottom: 80, left: 340 });
        },
        [map]
    );

    const fitAllEmployees = useCallback(() => {
        fitCoordinates(employees);
    }, [employees, fitCoordinates]);

    useEffect(() => {
        if (map && employees.length > 0 && !hasFitInitialLocations.current) {
            fitCoordinates(employees);
            hasFitInitialLocations.current = true;
        }
    }, [employees, fitCoordinates, map]);

    // Load History for Selected Employee & Day
    const fetchEmployeeHistory = useCallback(
        async (employee, dayStr) => {
            if (!employee) return;
            setHistoryLoading(true);
            setHistoryError("");
            setSelectedCheckpoint(null);
            setIsPlaying(false);
            setRoutePath([]);
            setRouteMetrics(null);

            const from = `${dayStr} 00:00:00`;
            const to = `${dayStr} 23:59:59`;

            try {
                const query = new URLSearchParams({ from, to });
                const res = await fetch(
                    `${basePath}/api/admin/employees/${encodeURIComponent(employee.employeeId)}/location-history?${query}`,
                    {
                        credentials: "same-origin",
                        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                    }
                );
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data?.message || "Could not retrieve employee location history.");
                }

                const payload = await res.json();
                const points = Array.isArray(payload.data) ? payload.data : [];
                setHistoryPoints(points);

                if (points.length >= 2) {
                    // Call OpenRouteService Directions API via our backend proxy
                    try {
                        const dirRes = await fetch(`${basePath}/api/admin/route-directions`, {
                            method: "POST",
                            credentials: "same-origin",
                            headers: {
                                "Content-Type": "application/json",
                                Accept: "application/json",
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            body: JSON.stringify({
                                coordinates: points.map((p) => [p.latitude, p.longitude]),
                            }),
                        });

                        if (dirRes.ok) {
                            const dirData = await dirRes.json();
                            if (dirData.success && Array.isArray(dirData.coordinates) && dirData.coordinates.length > 0) {
                                setRoutePath(dirData.coordinates);
                                setRouteMetrics({
                                    distanceKm: dirData.distance_km ?? null,
                                    durationMinutes: dirData.duration_minutes ?? null,
                                    source: dirData.source,
                                });
                                fitCoordinates(dirData.coordinates);
                                return;
                            }
                        }
                    } catch {
                        // Fallback below
                    }

                    // Fallback to straight lines between checkpoints
                    const directPath = points.map((p) => ({ lat: Number(p.latitude), lng: Number(p.longitude) }));
                    setRoutePath(directPath);
                    fitCoordinates(directPath);
                } else if (points.length === 1) {
                    setRoutePath([{ lat: Number(points[0].latitude), lng: Number(points[0].longitude) }]);
                    map?.panTo({ lat: Number(points[0].latitude), lng: Number(points[0].longitude) });
                    map?.setZoom(16);
                } else {
                    // Center on employee's current location if no history for that date
                    map?.panTo({ lat: Number(employee.latitude), lng: Number(employee.longitude) });
                    map?.setZoom(15);
                }
            } catch (err) {
                setHistoryError(err.message || "Failed to load location history.");
            } finally {
                setHistoryLoading(false);
            }
        },
        [basePath, fitCoordinates, map]
    );

    // Click on an Employee Avatar
    const handleEmployeeSelect = (employee) => {
        setSelectedEmployee(employee);
        const todayStr = toDateInputValue(new Date());
        setSelectedDate(todayStr);
        setDateFilterMode("today");
        fetchEmployeeHistory(employee, todayStr);
    };

    // Day Filter Buttons: Today, Yesterday, Custom Date
    const handleSetDayFilter = (mode, customDate = null) => {
        if (!selectedEmployee) return;
        setDateFilterMode(mode);

        let targetDay = selectedDate;
        if (mode === "today") {
            targetDay = toDateInputValue(new Date());
        } else if (mode === "yesterday") {
            const y = new Date();
            y.setDate(y.getDate() - 1);
            targetDay = toDateInputValue(y);
        } else if (customDate) {
            targetDay = customDate;
        }

        setSelectedDate(targetDay);
        fetchEmployeeHistory(selectedEmployee, targetDay);
    };

    // Close History view and return to full map
    const handleCloseHistory = () => {
        setSelectedEmployee(null);
        setHistoryPoints([]);
        setRoutePath([]);
        setRouteMetrics(null);
        setSelectedCheckpoint(null);
        setIsPlaying(false);
        fitAllEmployees();
    };

    // Playback Route Animation
    useEffect(() => {
        if (!isPlaying || historyPoints.length === 0) return;

        playbackTimer.current = window.setInterval(() => {
            setPlaybackIndex((prev) => {
                const next = (prev + 1) % historyPoints.length;
                const point = historyPoints[next];
                if (point && map) {
                    map.panTo({ lat: Number(point.latitude), lng: Number(point.longitude) });
                }
                setSelectedCheckpoint(point);
                return next;
            });
        }, 1800);

        return () => window.clearInterval(playbackTimer.current);
    }, [isPlaying, historyPoints, map]);

    return (
        <AuthenticatedLayout>
            <section className="relative h-full min-h-0 w-full overflow-hidden bg-slate-100 font-sans">
                {/* Google Map Viewport */}
                {isLoaded && (
                    <GoogleMap
                        mapContainerStyle={mapContainerStyle}
                        center={defaultCenter}
                        zoom={6}
                        options={{
                            clickableIcons: false,
                            fullscreenControl: false,
                            mapTypeControl: false,
                            streetViewControl: false,
                            zoomControl: false,
                            gestureHandling: "greedy",
                            styles: activeTheme === "snap" ? snapMapStyles : undefined,
                        }}
                        onLoad={setMap}
                        onUnmount={() => setMap(null)}
                    >
                        {/* 1. Snap Map Circular Profile Avatar Markers */}
                        {!selectedEmployee &&
                            separatedEmployees.map((emp) => {
                                const timeBadge = relativeTimeSnap(emp.capturedAt);
                                const isNow = timeBadge === "now";
                                const isOnline = emp.status === "Recently updated" || isNow;
                                const isRecent = timeBadge.includes("m") || timeBadge === "1h";
                                const firstName = (emp.name || "Employee").split(" ")[0];
                                const isSelectedCard = activeProfile?.employeeId === emp.employeeId;

                                return (
                                    <OverlayViewF
                                        key={emp.employeeId}
                                        position={{
                                            lat: Number(emp.renderLat ?? emp.latitude),
                                            lng: Number(emp.renderLng ?? emp.longitude),
                                        }}
                                        mapPaneName={OverlayView.OVERLAY_MOUSE_TARGET}
                                        getPixelPositionOffset={(w, h) => ({ x: -(w / 2), y: -h })}
                                    >
                                        <div
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setActiveProfile(emp);
                                                map?.panTo({ lat: Number(emp.latitude), lng: Number(emp.longitude) });
                                            }}
                                            className={`group relative flex flex-col items-center cursor-pointer select-none transition-all duration-300 hover:scale-115 active:scale-95 ${
                                                isSelectedCard ? "scale-115 z-50" : "hover:z-40"
                                            }`}
                                            style={{ transformOrigin: "bottom center" }}
                                            title={`${emp.name} (${emp.employeeId}) - Tap to view profile`}
                                        >
                                            {/* Circular Profile Avatar with white border and soft shadow */}
                                            <div className="relative h-14 w-14 rounded-full ring-[3px] ring-white shadow-[0_8px_20px_rgba(0,0,0,0.25)] bg-gradient-to-tr from-sky-100 via-indigo-100 to-purple-100 p-0.5 transition-transform duration-200 group-hover:shadow-[0_12px_28px_rgba(79,70,229,0.35)]">
                                                <img
                                                    src={getAvatarSrc(emp)}
                                                    alt={emp.name}
                                                    className="h-full w-full rounded-full object-cover select-none pointer-events-none"
                                                    draggable={false}
                                                    onError={(e) => {
                                                        e.currentTarget.style.display = "none";
                                                        const fallback = e.currentTarget.nextElementSibling;
                                                        if (fallback) fallback.style.display = "flex";
                                                    }}
                                                />
                                                <div
                                                    style={{ display: "none" }}
                                                    className={`h-full w-full rounded-full items-center justify-center font-bold text-white text-sm bg-gradient-to-tr ${getAvatarGradient(
                                                        emp.name
                                                    )}`}
                                                >
                                                    {getAvatarInitials(emp.name)}
                                                </div>

                                                {/* Live Status Pulse Indicator */}
                                                <span className="absolute -top-0.5 -right-0.5 flex h-4 w-4">
                                                    {isOnline && (
                                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                    )}
                                                    <span
                                                        className={`relative inline-flex rounded-full h-4 w-4 border-2 border-white shadow-xs ${
                                                            isOnline
                                                                ? "bg-emerald-500"
                                                                : isRecent
                                                                ? "bg-amber-400"
                                                                : "bg-slate-400"
                                                        }`}
                                                    ></span>
                                                </span>
                                            </div>

                                            {/* Downward Pointer Triangle */}
                                            <div className="w-2.5 h-2.5 bg-white rotate-45 -mt-1.5 shadow-[2px_2px_4px_rgba(0,0,0,0.12)] border-r border-b border-slate-200"></div>

                                            {/* Ground Shadow */}
                                            <div className="w-8 h-1.5 bg-black/25 rounded-full blur-[1px] mt-0.5 transition-all duration-200 group-hover:w-10 group-hover:bg-black/35"></div>

                                            {/* Snapchat Snap Map Pill Badge: Name & relative time */}
                                            <div className="mt-1 flex items-center gap-1.5 px-3 py-1 bg-white/95 backdrop-blur-md rounded-full shadow-lg border border-slate-200/90 text-xs text-slate-800 whitespace-nowrap group-hover:border-indigo-400 group-hover:shadow-indigo-200 transition-all duration-150">
                                                <span className="font-extrabold text-slate-900 tracking-tight">{firstName}</span>
                                                <span
                                                    className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${
                                                        isOnline
                                                            ? "bg-emerald-50 text-emerald-600"
                                                            : isRecent
                                                            ? "bg-amber-50 text-amber-600"
                                                            : "bg-slate-100 text-slate-500"
                                                    }`}
                                                >
                                                    {timeBadge}
                                                </span>
                                            </div>
                                        </div>
                                    </OverlayViewF>
                                );
                            })}

                        {/* Current User Location Beacon */}
                        {userCurrentLocation && (
                            <OverlayViewF
                                position={userCurrentLocation}
                                mapPaneName={OverlayView.OVERLAY_MOUSE_TARGET}
                                getPixelPositionOffset={(w, h) => ({ x: -(w / 2), y: -(h / 2) })}
                            >
                                <div className="relative flex items-center justify-center h-8 w-8 pointer-events-none">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-70"></span>
                                    <span className="relative inline-flex rounded-full h-4 w-4 bg-sky-500 border-2 border-white shadow-lg"></span>
                                </div>
                            </OverlayViewF>
                        )}

                        {/* 2. Route Polyline (Road Path via OpenRouteService) */}
                        {selectedEmployee && routePath.length > 1 && (
                            <PolylineF
                                path={routePath}
                                options={{
                                    geodesic: true,
                                    strokeColor: "#4f46e5",
                                    strokeOpacity: 0.9,
                                    strokeWeight: 5,
                                    icons: [
                                        {
                                            icon: {
                                                path: window.google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                                                scale: 2.5,
                                                fillColor: "#ffffff",
                                                fillOpacity: 1,
                                                strokeColor: "#312e81",
                                                strokeWeight: 1.5,
                                            },
                                            offset: "50%",
                                            repeat: "120px",
                                        },
                                    ],
                                }}
                            />
                        )}

                        {/* 3. Start Point Marker */}
                        {selectedEmployee && historyPoints.length > 0 && (
                            <MarkerF
                                position={{
                                    lat: Number(historyPoints[0].latitude),
                                    lng: Number(historyPoints[0].longitude),
                                }}
                                title={`Start: ${formatTimeString(historyPoints[0].capturedAt)}`}
                                label={{
                                    text: "START",
                                    color: "#ffffff",
                                    fontWeight: "800",
                                    fontSize: "10px",
                                    className: "snap-marker-label",
                                }}
                                icon={{
                                    path: window.google.maps.SymbolPath.CIRCLE,
                                    scale: 14,
                                    fillColor: "#10b981",
                                    fillOpacity: 1,
                                    strokeColor: "#ffffff",
                                    strokeWeight: 3,
                                }}
                                onClick={() => setSelectedCheckpoint(historyPoints[0])}
                            />
                        )}

                        {/* 4. Intermediate Checkpoint Markers */}
                        {selectedEmployee &&
                            historyPoints.slice(1, -1).map((point, idx) => (
                                <MarkerF
                                    key={`${point.capturedAt}-${idx}`}
                                    position={{ lat: Number(point.latitude), lng: Number(point.longitude) }}
                                    label={{
                                        text: String(idx + 2),
                                        color: "#ffffff",
                                        fontWeight: "700",
                                        fontSize: "11px",
                                    }}
                                    icon={{
                                        path: window.google.maps.SymbolPath.CIRCLE,
                                        scale: 10,
                                        fillColor: "#6366f1",
                                        fillOpacity: 0.95,
                                        strokeColor: "#ffffff",
                                        strokeWeight: 2,
                                    }}
                                    onClick={() => setSelectedCheckpoint(point)}
                                />
                            ))}

                        {/* 5. End / Current Position Standing Avatar Marker for Selected Employee */}
                        {selectedEmployee && historyPoints.length > 1 && (
                            <OverlayViewF
                                position={{
                                    lat: Number(historyPoints[historyPoints.length - 1].latitude),
                                    lng: Number(historyPoints[historyPoints.length - 1].longitude),
                                }}
                                mapPaneName={OverlayView.OVERLAY_MOUSE_TARGET}
                                getPixelPositionOffset={(w, h) => ({ x: -(w / 2), y: -h })}
                            >
                                <div
                                    className="group flex flex-col items-center cursor-pointer select-none"
                                    onClick={() => setSelectedCheckpoint(historyPoints[historyPoints.length - 1])}
                                >
                                    {/* Circular Profile Avatar for Latest Stop */}
                                    <div className="relative h-14 w-14 rounded-full ring-[3px] ring-white shadow-[0_8px_20px_rgba(0,0,0,0.25)] bg-gradient-to-tr from-rose-100 to-indigo-100 p-0.5">
                                        <img
                                            src={getAvatarSrc(selectedEmployee)}
                                            alt={selectedEmployee.name}
                                            className="h-full w-full rounded-full object-cover"
                                            onError={(e) => {
                                                e.currentTarget.style.display = "none";
                                                const fallback = e.currentTarget.nextElementSibling;
                                                if (fallback) fallback.style.display = "flex";
                                            }}
                                        />
                                        <div
                                            style={{ display: "none" }}
                                            className={`h-full w-full rounded-full items-center justify-center font-bold text-white text-sm bg-gradient-to-tr ${getAvatarGradient(
                                                selectedEmployee.name
                                            )}`}
                                        >
                                            {getAvatarInitials(selectedEmployee.name)}
                                        </div>
                                    </div>
                                    <div className="w-2.5 h-2.5 bg-rose-600 rotate-45 -mt-1.5"></div>
                                    <div className="w-8 h-1.5 bg-black/25 rounded-full blur-[1px] mt-0.5"></div>
                                    <div className="mt-1 px-3 py-1 bg-rose-600 text-white rounded-full text-[11px] font-bold shadow-lg border border-white/80 whitespace-nowrap">
                                        LATEST STOP · {formatTimeString(historyPoints[historyPoints.length - 1].capturedAt)}
                                    </div>
                                </div>
                            </OverlayViewF>
                        )}

                        {/* Checkpoint Detail InfoWindow */}
                        {selectedCheckpoint && (
                            <InfoWindowF
                                position={{
                                    lat: Number(selectedCheckpoint.latitude),
                                    lng: Number(selectedCheckpoint.longitude),
                                }}
                                onCloseClick={() => setSelectedCheckpoint(null)}
                            >
                                <div className="p-1 min-w-[190px] text-slate-800 font-sans">
                                    <p className="font-bold text-xs text-indigo-700 flex items-center gap-1.5">
                                        <FaMapMarkerAlt /> Checkpoint Pin
                                    </p>
                                    <p className="mt-1 text-xs font-semibold text-slate-900">
                                        Time: {new Date(selectedCheckpoint.capturedAt).toLocaleTimeString()}
                                    </p>
                                    <p className="text-[11px] text-slate-500">
                                        GPS: {Number(selectedCheckpoint.latitude).toFixed(6)},{" "}
                                        {Number(selectedCheckpoint.longitude).toFixed(6)}
                                    </p>
                                    {selectedCheckpoint.accuracy != null && (
                                        <p className="text-[11px] text-slate-500">
                                            Accuracy: ±{Math.round(selectedCheckpoint.accuracy)}m
                                        </p>
                                    )}
                                </div>
                            </InfoWindowF>
                        )}
                    </GoogleMap>
                )}

                {/* Map Loading / Error Fallback */}
                {(!isLoaded || loadError || !apiKey) && (
                    <div className="absolute inset-0 flex items-center justify-center bg-slate-100 px-6 text-center">
                        <div className="max-w-md rounded-3xl border border-slate-200 bg-white/95 p-8 shadow-2xl backdrop-blur">
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 text-xl">
                                <FaLayerGroup />
                            </div>
                            <h2 className="text-base font-bold text-slate-900">Snap Map Initializing</h2>
                            <p className="mt-2 text-sm text-slate-600">
                                {!apiKey
                                    ? "Please ensure VITE_GOOGLE_MAPS_API_KEY is configured in .env."
                                    : loadError
                                    ? "Google Maps connection error. Check key restrictions or internet connection."
                                    : "Loading map engine…"}
                            </p>
                        </div>
                    </div>
                )}

                {/* TOP BAR CONTROLS */}
                <div className="pointer-events-none absolute inset-x-0 top-0 z-20 flex flex-wrap items-start justify-between gap-3 p-3 sm:p-4">
                    {/* Search & Filter Pills */}
                    {!selectedEmployee && (
                        <div className="pointer-events-auto flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200/80 bg-white/90 p-1.5 shadow-xl backdrop-blur-md">
                            {/* Super Admin filter dropdown for Project Owner, or Organization badge for regular Super Admin */}
                            {isProjectOwner ? (
                                <div className="flex items-center gap-1.5 rounded-xl border border-indigo-200/90 bg-indigo-50/70 px-2.5 h-8">
                                    <FaUserTie className="text-indigo-600 text-xs shrink-0" />
                                    <select
                                        value={selectedSuperAdmin}
                                        onChange={(e) => setSelectedSuperAdmin(e.target.value)}
                                        className="h-full border-0 bg-transparent py-0 pl-0 pr-6 text-xs font-semibold text-indigo-900 outline-none focus:ring-0 cursor-pointer"
                                        title="Filter by Super Admin / Project Owner"
                                    >
                                        <option value="">All Super Admins ({superAdmins.length})</option>
                                        {superAdmins.map((sa) => (
                                            <option key={sa.id} value={sa.id}>
                                                {sa.label || `${sa.name}${sa.company_name ? ` (${sa.company_name})` : ""}`}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ) : (
                                currentSuperAdmin && (
                                    <div
                                        className="hidden sm:flex items-center gap-1.5 rounded-xl border border-slate-200/80 bg-slate-50/90 px-2.5 h-8 text-xs font-semibold text-slate-700"
                                        title="Viewing your organization's members"
                                    >
                                        <FaShieldAlt className="text-indigo-600 text-xs shrink-0" />
                                        <span className="truncate max-w-[140px]">
                                            {currentSuperAdmin.company_name ? `${currentSuperAdmin.company_name} Panel` : currentSuperAdmin.name}
                                        </span>
                                    </div>
                                )
                            )}

                            <div className="flex items-center gap-2 px-2.5">
                                <FaSearch className="text-slate-400 text-xs" />
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search employee..."
                                    className="h-8 w-36 sm:w-48 border-0 bg-transparent p-0 text-xs font-medium outline-none placeholder:text-slate-400 focus:ring-0"
                                />
                            </div>

                            {departments.length > 0 && (
                                <select
                                    value={department}
                                    onChange={(e) => setDepartment(e.target.value)}
                                    className="h-8 rounded-xl border-slate-200/80 bg-slate-50/80 px-2.5 py-0 text-xs font-medium text-slate-700 outline-none hover:bg-slate-100"
                                >
                                    <option value="">All Departments</option>
                                    {departments.map((d) => (
                                        <option key={d} value={d}>
                                            {d}
                                        </option>
                                    ))}
                                </select>
                            )}

                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="hidden sm:block h-8 rounded-xl border-slate-200/80 bg-slate-50/80 px-2.5 py-0 text-xs font-medium text-slate-700 outline-none hover:bg-slate-100"
                            >
                                <option value="">All Statuses</option>
                                <option value="Recently updated">Live Now</option>
                                <option value="Stale">Stale</option>
                                <option value="Offline / not reporting">Offline</option>
                            </select>
                        </div>
                    )}

                    {/* Active Employee Selected Indicator (Header in History mode) */}
                    {selectedEmployee && (
                        <div className="pointer-events-auto flex items-center gap-2 rounded-2xl border border-indigo-200 bg-white/95 px-3 py-1.5 shadow-xl backdrop-blur-md">
                            <button
                                type="button"
                                onClick={handleCloseHistory}
                                className="flex h-8 items-center gap-1.5 rounded-xl bg-slate-100 px-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition"
                            >
                                <FaChevronLeft className="text-[10px]" /> Exit History
                            </button>
                            <span className="h-4 border-l border-slate-200"></span>
                            <span className="text-xs font-bold text-indigo-900 truncate max-w-[150px]">
                                {selectedEmployee.name}
                            </span>
                            <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-600">
                                {formatDateDisplay(selectedDate)}
                            </span>
                        </div>
                    )}

                    {/* Right Tools: Fit All, Refresh, Zoom, Basemap Themes */}
                    <div className="pointer-events-auto flex items-center gap-1.5 rounded-2xl border border-slate-200/80 bg-white/90 p-1.5 shadow-xl backdrop-blur-md">
                        <button
                            type="button"
                            title="Fit all on screen"
                            onClick={selectedEmployee ? () => fitCoordinates(routePath) : fitAllEmployees}
                            className="flex h-8 items-center gap-1.5 rounded-xl px-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition"
                        >
                            <FaCrosshairs className="text-xs text-indigo-600" />
                            <span className="hidden sm:inline">Fit All</span>
                        </button>

                        <button
                            type="button"
                            title="Center on my current location"
                            onClick={handleLocateMe}
                            className="flex h-8 items-center gap-1.5 rounded-xl px-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition"
                        >
                            <FaLocationArrow className="text-xs text-sky-600" />
                            <span className="hidden sm:inline">Locate Me</span>
                        </button>

                        <button
                            type="button"
                            title="Refresh employee locations"
                            onClick={() => (selectedEmployee ? fetchEmployeeHistory(selectedEmployee, selectedDate) : loadLocations(true))}
                            disabled={refreshing || historyLoading}
                            className="flex h-8 w-8 items-center justify-center rounded-xl text-slate-700 hover:bg-slate-100 disabled:opacity-50 transition"
                        >
                            <FaRedoAlt className={`text-xs ${refreshing || historyLoading ? "animate-spin text-indigo-600" : ""}`} />
                        </button>

                        <span className="h-4 border-l border-slate-200"></span>

                        <button
                            type="button"
                            title="Zoom In"
                            onClick={() => map?.setZoom((map.getZoom() || 6) + 1)}
                            className="h-8 w-8 rounded-xl text-slate-700 hover:bg-slate-100 flex items-center justify-center transition"
                        >
                            <FaPlus className="text-xs" />
                        </button>
                        <button
                            type="button"
                            title="Zoom Out"
                            onClick={() => map?.setZoom((map.getZoom() || 6) - 1)}
                            className="h-8 w-8 rounded-xl text-slate-700 hover:bg-slate-100 flex items-center justify-center transition"
                        >
                            <FaMinus className="text-xs" />
                        </button>

                        {/* Basemap Switcher Dropdown */}
                        <div className="relative">
                            <button
                                type="button"
                                title="Switch Map Theme (Snap Map, Carto, Mapbox, Satellite)"
                                onClick={() => setThemeMenuOpen(!themeMenuOpen)}
                                className={`h-8 px-2.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition ${
                                    activeTheme === "snap"
                                        ? "bg-amber-100 text-amber-800"
                                        : activeTheme === "carto"
                                        ? "bg-emerald-100 text-emerald-800"
                                        : activeTheme === "mapbox"
                                        ? "bg-blue-100 text-blue-800"
                                        : "bg-slate-100 text-slate-800"
                                }`}
                            >
                                <FaLayerGroup className="text-xs" />
                                <span className="hidden md:inline capitalize">{activeTheme === "snap" ? "Snap Map" : activeTheme}</span>
                            </button>

                            {themeMenuOpen && (
                                <div className="absolute right-0 mt-2 w-48 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl z-50">
                                    <div className="px-3 py-1.5 text-[10px] font-bold uppercase text-slate-400 tracking-wider">
                                        Map Themes & Basemaps
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTheme("snap");
                                            setThemeMenuOpen(false);
                                        }}
                                        className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold ${
                                            activeTheme === "snap" ? "bg-amber-50 text-amber-900" : "text-slate-700 hover:bg-slate-50"
                                        }`}
                                    >
                                        <span>🌟 Snap Map Pastel</span>
                                        {activeTheme === "snap" && <FaCheck className="text-amber-600 text-xs" />}
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTheme("carto");
                                            setThemeMenuOpen(false);
                                        }}
                                        className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold ${
                                            activeTheme === "carto" ? "bg-emerald-50 text-emerald-900" : "text-slate-700 hover:bg-slate-50"
                                        }`}
                                    >
                                        <span>🗺️ Carto Voyager</span>
                                        {activeTheme === "carto" && <FaCheck className="text-emerald-600 text-xs" />}
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTheme("mapbox");
                                            setThemeMenuOpen(false);
                                        }}
                                        className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold ${
                                            activeTheme === "mapbox" ? "bg-blue-50 text-blue-900" : "text-slate-700 hover:bg-slate-50"
                                        }`}
                                    >
                                        <span>🛣️ Mapbox Streets</span>
                                        {activeTheme === "mapbox" && <FaCheck className="text-blue-600 text-xs" />}
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTheme("satellite");
                                            setThemeMenuOpen(false);
                                        }}
                                        className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold ${
                                            activeTheme === "satellite" ? "bg-slate-100 text-slate-900" : "text-slate-700 hover:bg-slate-50"
                                        }`}
                                    >
                                        <span>🛰️ Satellite Hybrid</span>
                                        {activeTheme === "satellite" && <FaCheck className="text-slate-700 text-xs" />}
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTheme("roadmap");
                                            setThemeMenuOpen(false);
                                        }}
                                        className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold ${
                                            activeTheme === "roadmap" ? "bg-slate-100 text-slate-900" : "text-slate-700 hover:bg-slate-50"
                                        }`}
                                    >
                                        <span>📍 Google Roadmap</span>
                                        {activeTheme === "roadmap" && <FaCheck className="text-slate-700 text-xs" />}
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* SNAP MAP LOCATION HISTORY DRAWER / SIDEBAR (When employee avatar is clicked) */}
                {selectedEmployee && (
                    <div className="absolute top-16 left-3 sm:left-4 bottom-4 z-20 w-[calc(100%-1.5rem)] sm:w-[380px] max-w-full flex flex-col rounded-3xl border border-slate-200/90 bg-white/95 shadow-2xl backdrop-blur-xl overflow-hidden animate-in slide-in-from-left-4 duration-200">
                        {/* Header: Employee Snapshot Card */}
                        <div className="relative p-4 bg-gradient-to-br from-indigo-600 via-indigo-700 to-indigo-900 text-white">
                            <button
                                type="button"
                                onClick={handleCloseHistory}
                                className="absolute top-3.5 right-3.5 flex h-7 w-7 items-center justify-center rounded-full bg-white/20 hover:bg-white/30 text-white transition"
                                title="Close & back to live map"
                            >
                                <FaTimes className="text-xs" />
                            </button>

                            <div className="flex items-center justify-between gap-3 relative pr-8">
                                <div className="flex items-center gap-3 min-w-0">
                                    <div className="relative h-14 w-14 rounded-full p-0.5 bg-white shadow-lg shrink-0">
                                        <img
                                            src={getAvatarSrc(selectedEmployee)}
                                            alt={selectedEmployee.name}
                                            className="h-full w-full rounded-full object-cover"
                                            onError={(e) => {
                                                e.currentTarget.style.display = "none";
                                                const fallback = e.currentTarget.nextElementSibling;
                                                if (fallback) fallback.style.display = "flex";
                                            }}
                                        />
                                        <div
                                            style={{ display: "none" }}
                                            className={`h-full w-full rounded-full items-center justify-center font-bold text-white text-base bg-gradient-to-tr ${getAvatarGradient(
                                                selectedEmployee.name
                                            )}`}
                                        >
                                            {getAvatarInitials(selectedEmployee.name)}
                                        </div>
                                    </div>
                                    <div className="min-w-0">
                                        <h3 className="text-base font-extrabold truncate leading-tight">
                                            {selectedEmployee.name}
                                        </h3>
                                        <p className="text-xs text-indigo-200 font-mono mt-0.5">
                                            {selectedEmployee.employeeId}
                                        </p>
                                        <p className="text-[11px] text-indigo-100 truncate mt-0.5">
                                            {selectedEmployee.designation !== "Not specified"
                                                ? selectedEmployee.designation
                                                : selectedEmployee.department}
                                        </p>
                                        {selectedEmployee.superAdminName && (
                                            <p className="text-[10px] text-indigo-200 truncate mt-0.5 flex items-center gap-1">
                                                <FaUserTie className="text-[9px]" /> {selectedEmployee.superAdminName}
                                                {selectedEmployee.companyName ? ` (${selectedEmployee.companyName})` : ""}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 text-[11px] font-bold text-white shrink-0">
                                    <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                    History
                                </span>
                            </div>
                        </div>

                        {/* DAY FILTERS: "today", "yesterday", "date fill like that" */}
                        <div className="p-3 border-b border-slate-100 bg-slate-50/70">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                                    <FaCalendarDay className="text-indigo-600 text-xs" /> Location Day Filter
                                </span>
                                <span className="text-[11px] font-semibold text-indigo-600">
                                    {formatDateDisplay(selectedDate)}
                                </span>
                            </div>

                            {/* Quick Day Filter Buttons */}
                            <div className="grid grid-cols-3 gap-1.5">
                                <button
                                    type="button"
                                    onClick={() => handleSetDayFilter("today")}
                                    className={`py-1.5 px-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 ${
                                        dateFilterMode === "today"
                                            ? "bg-indigo-600 text-white shadow-md shadow-indigo-200"
                                            : "bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
                                    }`}
                                >
                                    <span>☀️</span> Today
                                </button>

                                <button
                                    type="button"
                                    onClick={() => handleSetDayFilter("yesterday")}
                                    className={`py-1.5 px-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 ${
                                        dateFilterMode === "yesterday"
                                            ? "bg-indigo-600 text-white shadow-md shadow-indigo-200"
                                            : "bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
                                    }`}
                                >
                                    <span>⏪</span> Yesterday
                                </button>

                                {/* Date Input: "date fill like that" */}
                                <label
                                    className={`relative py-1.5 px-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer ${
                                        dateFilterMode === "custom"
                                            ? "bg-indigo-600 text-white shadow-md shadow-indigo-200"
                                            : "bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
                                    }`}
                                >
                                    <span>📅</span> Pick Date
                                    <input
                                        type="date"
                                        value={selectedDate}
                                        max={toDateInputValue(new Date())}
                                        onChange={(e) => {
                                            if (e.target.value) {
                                                handleSetDayFilter("custom", e.target.value);
                                            }
                                        }}
                                        className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                    />
                                </label>
                            </div>
                        </div>

                        {/* ROUTE METRICS (Distance, Travel Time, Checkpoints) */}
                        <div className="p-3 bg-white border-b border-slate-100">
                            <div className="grid grid-cols-3 gap-2 text-center">
                                <div className="rounded-2xl bg-indigo-50/70 p-2 border border-indigo-100">
                                    <p className="text-[10px] font-bold uppercase tracking-wider text-indigo-500">
                                        Distance
                                    </p>
                                    <p className="text-sm font-extrabold text-indigo-900 mt-0.5">
                                        {routeMetrics?.distanceKm != null
                                            ? `${routeMetrics.distanceKm} km`
                                            : historyPoints.length > 1
                                            ? "Calculated"
                                            : "0 km"}
                                    </p>
                                </div>

                                <div className="rounded-2xl bg-emerald-50/70 p-2 border border-emerald-100">
                                    <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-600">
                                        Points
                                    </p>
                                    <p className="text-sm font-extrabold text-emerald-900 mt-0.5">
                                        {historyPoints.length}
                                    </p>
                                </div>

                                <div className="rounded-2xl bg-amber-50/70 p-2 border border-amber-100">
                                    <p className="text-[10px] font-bold uppercase tracking-wider text-amber-600">
                                        Duration
                                    </p>
                                    <p className="text-sm font-extrabold text-amber-900 mt-0.5">
                                        {routeMetrics?.durationMinutes != null
                                            ? `${routeMetrics.durationMinutes} min`
                                            : historyPoints.length >= 2
                                            ? `${Math.round(
                                                  (new Date(historyPoints[historyPoints.length - 1].capturedAt) -
                                                      new Date(historyPoints[0].capturedAt)) /
                                                      60000
                                              )} min`
                                            : "0 min"}
                                    </p>
                                </div>
                            </div>

                            {/* Playback & Step Controls */}
                            {historyPoints.length > 1 && (
                                <div className="mt-2.5 flex items-center justify-between gap-2 rounded-2xl bg-slate-50 p-1.5 border border-slate-200">
                                    <button
                                        type="button"
                                        onClick={() => setIsPlaying(!isPlaying)}
                                        className={`flex items-center gap-1.5 rounded-xl px-3 py-1 text-xs font-bold transition ${
                                            isPlaying
                                                ? "bg-amber-500 text-white shadow-sm"
                                                : "bg-indigo-600 text-white shadow-sm hover:bg-indigo-700"
                                        }`}
                                    >
                                        {isPlaying ? <FaPause className="text-[10px]" /> : <FaPlay className="text-[10px]" />}
                                        <span>{isPlaying ? "Pause" : "Play Route"}</span>
                                    </button>

                                    <span className="text-[11px] font-semibold text-slate-500">
                                        Step {playbackIndex + 1} of {historyPoints.length}
                                    </span>

                                    <div className="flex items-center gap-1">
                                        <button
                                            type="button"
                                            disabled={playbackIndex <= 0}
                                            onClick={() => {
                                                const prev = Math.max(0, playbackIndex - 1);
                                                setPlaybackIndex(prev);
                                                setSelectedCheckpoint(historyPoints[prev]);
                                                map?.panTo({
                                                    lat: Number(historyPoints[prev].latitude),
                                                    lng: Number(historyPoints[prev].longitude),
                                                });
                                            }}
                                            className="h-7 w-7 rounded-lg bg-white border border-slate-200 text-slate-700 disabled:opacity-40 flex items-center justify-center hover:bg-slate-100"
                                        >
                                            <FaChevronLeft className="text-[10px]" />
                                        </button>
                                        <button
                                            type="button"
                                            disabled={playbackIndex >= historyPoints.length - 1}
                                            onClick={() => {
                                                const next = Math.min(historyPoints.length - 1, playbackIndex + 1);
                                                setPlaybackIndex(next);
                                                setSelectedCheckpoint(historyPoints[next]);
                                                map?.panTo({
                                                    lat: Number(historyPoints[next].latitude),
                                                    lng: Number(historyPoints[next].longitude),
                                                });
                                            }}
                                            className="h-7 w-7 rounded-lg bg-white border border-slate-200 text-slate-700 disabled:opacity-40 flex items-center justify-center hover:bg-slate-100"
                                        >
                                            <FaChevronRight className="text-[10px]" />
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Checkpoints Timeline List */}
                        <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-2">
                            {historyLoading && (
                                <div className="py-12 text-center">
                                    <FaRedoAlt className="animate-spin mx-auto text-xl text-indigo-600 mb-2" />
                                    <p className="text-xs font-semibold text-slate-600">Loading location history…</p>
                                </div>
                            )}

                            {!historyLoading && historyPoints.length === 0 && (
                                <div className="py-12 text-center px-4">
                                    <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                                        <FaRoute />
                                    </div>
                                    <p className="text-xs font-bold text-slate-800">No recorded movements</p>
                                    <p className="mt-1 text-[11px] text-slate-500">
                                        No GPS location points were recorded for {formatDateDisplay(selectedDate)}. Try selecting another date above.
                                    </p>
                                </div>
                            )}

                            {!historyLoading &&
                                historyPoints.map((point, index) => {
                                    const isStart = index === 0;
                                    const isEnd = index === historyPoints.length - 1 && historyPoints.length > 1;
                                    const isSelected = selectedCheckpoint === point;

                                    return (
                                        <div
                                            key={`${point.capturedAt}-${index}`}
                                            onClick={() => {
                                                setSelectedCheckpoint(point);
                                                setPlaybackIndex(index);
                                                map?.panTo({
                                                    lat: Number(point.latitude),
                                                    lng: Number(point.longitude),
                                                });
                                                map?.setZoom(16);
                                            }}
                                            className={`group flex items-center justify-between p-2.5 rounded-2xl cursor-pointer border transition-all duration-150 ${
                                                isSelected
                                                    ? "bg-indigo-50/90 border-indigo-400 shadow-sm"
                                                    : "bg-white hover:bg-slate-50 border-slate-200/80"
                                            }`}
                                        >
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                <div
                                                    className={`h-7 w-7 rounded-xl flex items-center justify-center text-xs font-extrabold ${
                                                        isStart
                                                            ? "bg-emerald-500 text-white"
                                                            : isEnd
                                                            ? "bg-rose-500 text-white"
                                                            : "bg-indigo-100 text-indigo-700"
                                                    }`}
                                                >
                                                    {isStart ? "S" : isEnd ? "E" : index + 1}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-xs font-bold text-slate-800 truncate">
                                                        {formatTimeString(point.capturedAt)}
                                                        {isStart && (
                                                            <span className="ml-1.5 text-[10px] text-emerald-600 font-semibold">
                                                                · Start Point
                                                            </span>
                                                        )}
                                                        {isEnd && (
                                                            <span className="ml-1.5 text-[10px] text-rose-600 font-semibold">
                                                                · Latest Stop
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="text-[10px] text-slate-400 font-mono truncate">
                                                        {Number(point.latitude).toFixed(4)}, {Number(point.longitude).toFixed(4)}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="text-right shrink-0">
                                                <span className="text-[10px] font-semibold text-slate-400 group-hover:text-indigo-600 transition">
                                                    Focus →
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                        </div>

                        {/* Footer Status */}
                        <div className="p-2.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                            <span>OpenRouteService · Mapbox</span>
                            <button
                                type="button"
                                onClick={handleCloseHistory}
                                className="font-bold text-indigo-600 hover:text-indigo-800"
                            >
                                Back to All Employees
                            </button>
                        </div>
                    </div>
                )}

                {/* SNAPCHAT-STYLE FLOATING PROFILE CARD (When an avatar is clicked) */}
                {activeProfile && !selectedEmployee && (
                    <div className="absolute bottom-6 left-3 sm:left-4 z-30 w-[calc(100%-1.5rem)] sm:w-[360px] max-w-full rounded-3xl border border-slate-200/90 bg-white/95 shadow-2xl backdrop-blur-xl overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-200">
                        {/* Banner with playful Snapchat pastel gradient */}
                        <div className="relative h-20 bg-gradient-to-r from-amber-300 via-yellow-300 to-amber-400 p-3 flex items-start justify-between">
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-black/10 backdrop-blur-sm text-[11px] font-extrabold text-slate-900">
                                Snap Map Profile
                            </span>
                            <button
                                type="button"
                                onClick={() => setActiveProfile(null)}
                                className="flex h-7 w-7 items-center justify-center rounded-full bg-black/15 hover:bg-black/25 text-slate-900 transition"
                                title="Dismiss"
                            >
                                <FaTimes className="text-xs" />
                            </button>
                        </div>

                        {/* Profile Content */}
                        <div className="relative px-5 pb-5 pt-0">
                            {/* Circular Avatar protruding up */}
                            <div className="flex items-end justify-between -mt-10 mb-3">
                                <div className="relative h-20 w-20 rounded-full ring-4 ring-white shadow-xl bg-gradient-to-tr from-sky-100 via-indigo-100 to-purple-100 p-0.5 shrink-0">
                                    <img
                                        src={getAvatarSrc(activeProfile)}
                                        alt={activeProfile.name}
                                        className="h-full w-full rounded-full object-cover"
                                        onError={(e) => {
                                            e.currentTarget.style.display = "none";
                                            const fallback = e.currentTarget.nextElementSibling;
                                            if (fallback) fallback.style.display = "flex";
                                        }}
                                    />
                                    <div
                                        style={{ display: "none" }}
                                        className={`h-full w-full rounded-full items-center justify-center font-bold text-white text-lg bg-gradient-to-tr ${getAvatarGradient(
                                            activeProfile.name
                                        )}`}
                                    >
                                        {getAvatarInitials(activeProfile.name)}
                                    </div>

                                    {/* Status Ring */}
                                    <span className="absolute bottom-0 right-0 flex h-5 w-5">
                                        <span
                                            className={`relative inline-flex rounded-full h-5 w-5 border-2 border-white shadow-xs ${
                                                activeProfile.status === "Recently updated"
                                                    ? "bg-emerald-500"
                                                    : "bg-amber-400"
                                            }`}
                                        ></span>
                                    </span>
                                </div>

                                {/* Status Chip */}
                                <div className="text-right">
                                    <span
                                        className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ${
                                            activeProfile.status === "Recently updated"
                                                ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                                                : "bg-slate-100 text-slate-700 border border-slate-200"
                                        }`}
                                    >
                                        <span
                                            className={`h-2 w-2 rounded-full ${
                                                activeProfile.status === "Recently updated"
                                                    ? "bg-emerald-500 animate-pulse"
                                                    : "bg-slate-400"
                                            }`}
                                        ></span>
                                        {activeProfile.status === "Recently updated" ? "Live on Map" : "Offline / Stale"}
                                    </span>
                                </div>
                            </div>

                            {/* Name & Title */}
                            <div>
                                <h3 className="text-lg font-black text-slate-900 leading-tight">
                                    {activeProfile.name}
                                </h3>
                                <div className="flex items-center gap-2 mt-1 text-xs text-slate-500 font-medium">
                                    <span className="font-mono text-indigo-600 font-semibold">{activeProfile.employeeId}</span>
                                    <span>·</span>
                                    <span>
                                        {activeProfile.designation !== "Not specified"
                                            ? activeProfile.designation
                                            : activeProfile.department}
                                    </span>
                                </div>
                                {activeProfile.superAdminName && (
                                    <div className="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span className="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                            <FaUserTie className="text-[10px]" />
                                            {activeProfile.superAdminName}
                                        </span>
                                        {activeProfile.companyName && (
                                            <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                {activeProfile.companyName}
                                            </span>
                                        )}
                                        {activeProfile.role && activeProfile.role !== "Member" && (
                                            <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700">
                                                {activeProfile.role}
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Location Freshness & Accuracy */}
                            <div className="mt-3.5 rounded-2xl bg-slate-50 p-3 border border-slate-100 text-xs text-slate-600 space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-400 font-medium">Last Location Ping:</span>
                                    <span className="font-bold text-slate-800">
                                        {activeProfile.capturedAt
                                            ? `${new Date(activeProfile.capturedAt).toLocaleTimeString([], {
                                                  hour: "2-digit",
                                                  minute: "2-digit",
                                              })} (${relativeTimeSnap(activeProfile.capturedAt)})`
                                            : "No timestamp"}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-400 font-medium">Coordinates:</span>
                                    <span className="font-mono text-slate-700 text-[11px]">
                                        {Number(activeProfile.latitude).toFixed(5)}, {Number(activeProfile.longitude).toFixed(5)}
                                    </span>
                                </div>
                                {activeProfile.accuracy != null && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-slate-400 font-medium">GPS Accuracy:</span>
                                        <span className="font-semibold text-emerald-700">±{Math.round(activeProfile.accuracy)} meters</span>
                                    </div>
                                )}
                            </div>

                            {/* Action Buttons: Call, Email, Focus, View History */}
                            <div className="mt-3.5 grid grid-cols-2 gap-2">
                                {activeProfile.phone ? (
                                    <a
                                        href={`tel:${activeProfile.phone}`}
                                        className="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition"
                                    >
                                        <FaPhoneAlt className="text-xs text-indigo-600" /> Call
                                    </a>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            map?.panTo({ lat: Number(activeProfile.latitude), lng: Number(activeProfile.longitude) });
                                            map?.setZoom(16);
                                        }}
                                        className="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition"
                                    >
                                        <FaCrosshairs className="text-xs text-indigo-600" /> Focus
                                    </button>
                                )}

                                {activeProfile.email ? (
                                    <a
                                        href={`mailto:${activeProfile.email}`}
                                        className="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition"
                                    >
                                        <FaEnvelope className="text-xs text-indigo-600" /> Email
                                    </a>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            map?.panTo({ lat: Number(activeProfile.latitude), lng: Number(activeProfile.longitude) });
                                            map?.setZoom(17);
                                        }}
                                        className="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition"
                                    >
                                        <FaSearch className="text-xs text-indigo-600" /> Zoom In
                                    </button>
                                )}
                            </div>

                            {/* Main CTA: View Today's Trip History */}
                            <button
                                type="button"
                                onClick={() => {
                                    handleEmployeeSelect(activeProfile);
                                    setActiveProfile(null);
                                }}
                                className="mt-2.5 w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold shadow-lg shadow-indigo-200 transition"
                            >
                                <FaRoute className="text-xs" /> View Movement History
                            </button>
                        </div>
                    </div>
                )}

                {/* Empty state when no live coordinates exist */}
                {!loading && employees.length === 0 && (
                    <div className="absolute inset-0 pointer-events-none flex items-center justify-center p-4">
                        <div className="pointer-events-auto max-w-sm rounded-3xl border border-slate-200/90 bg-white/95 p-6 text-center shadow-2xl backdrop-blur-md">
                            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-500 text-2xl">
                                <FaMapMarkerAlt />
                            </div>
                            <h3 className="text-base font-extrabold text-slate-900">No Active Locations Yet</h3>
                            <p className="mt-1.5 text-xs text-slate-500 leading-relaxed">
                                No employees are currently sharing live GPS coordinates. When members report their location or check in via the mobile app, their circular Snapchat avatars will appear here live.
                            </p>
                            <button
                                type="button"
                                onClick={() => loadLocations(true)}
                                className="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition shadow-md shadow-indigo-200"
                            >
                                <FaRedoAlt className="text-xs" /> Check Again
                            </button>
                        </div>
                    </div>
                )}

                {/* BOTTOM STATUS BAR (When in Live Map mode) */}
                {!selectedEmployee && (
                    <div className="pointer-events-none absolute bottom-4 left-3 z-10 flex max-w-[calc(100%-1.5rem)] flex-wrap items-center gap-2 rounded-full border border-slate-200/90 bg-white/95 px-3.5 py-2 text-xs text-slate-600 shadow-xl backdrop-blur-md sm:left-4">
                        <span className="font-bold text-slate-900">
                            {visibleEmployees.length} of {employees.length} Located
                        </span>
                        {loading && <span className="text-indigo-600 font-semibold">Syncing GPS…</span>}
                        {lastUpdated && (
                            <span className="hidden sm:inline border-l border-slate-200 pl-2">
                                Auto-refreshed at{" "}
                                {lastUpdated.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}
                            </span>
                        )}
                        {employees.length === 0 && !loading && !error && (
                            <span className="text-slate-500">No active employee GPS pings reported yet.</span>
                        )}
                        {error && <span className="text-rose-600 font-semibold">{error}</span>}
                    </div>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
