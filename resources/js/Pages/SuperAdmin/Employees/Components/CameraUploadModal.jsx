import React, { useEffect, useRef, useState, useCallback } from "react";
import {
    MdCamera,
    MdClose,
    MdFileUpload,
    MdQrCodeScanner,
    MdArrowBack,
    MdCheckCircle,
    MdError,
    MdRefresh,
    MdAutoAwesome,
} from "react-icons/md";
import { QRCodeCanvas } from "qrcode.react";
import Cropper from "react-easy-crop";
import Webcam from "react-webcam";

// --- HELPERS ---
const getBrowserName = () => {
    if (typeof navigator === "undefined") return "Unknown";
    const ua = navigator.userAgent;
    if (/Edg/i.test(ua)) return "Microsoft Edge";
    if (/Chrome/i.test(ua) && !/Edg/i.test(ua)) return "Google Chrome";
    if (/Firefox/i.test(ua)) return "Mozilla Firefox";
    if (/Safari/i.test(ua) && !/Chrome|CriOS|Edg/i.test(ua)) return "Safari";
    return "your browser";
};

const getPermissionSteps = (browserName) => {
    if (browserName === "Safari") {
        return [
            "Click Safari in the menu bar and open Settings for This Website.",
            "Set Camera to Allow.",
            "Refresh this page and tap Retry Camera.",
        ];
    }
    if (browserName === "Mozilla Firefox") {
        return [
            "Click the lock icon near the address bar.",
            "Open Connection Securely > More Information > Permissions.",
            "Set Use the Camera to Allow and retry.",
        ];
    }
    if (browserName === "Microsoft Edge") {
        return [
            "Click the lock icon near the URL.",
            "Open Permissions for this site.",
            "Set Camera to Allow, then refresh and retry.",
        ];
    }
    return [
        "Click the lock icon near the URL bar.",
        "Set Camera permission to Allow.",
        "Refresh this page and tap Retry Camera.",
    ];
};

const isLikelyInsecureContextError = (err) => {
    const message = String(err?.message || "").toLowerCase();
    return (
        err?.name === "SecurityError" ||
        message.includes("only secure origins") ||
        message.includes("secure context") ||
        message.includes("insecure") ||
        message.includes("permissions policy")
    );
};

const getCameraErrorMessage = (err) => {
    const message = String(err?.message || "").toLowerCase();

    if (message.includes("permissions policy")) {
        if (window.location.hostname === "127.0.0.1") {
            return "Camera access blocked by browser policy when using 127.0.0.1. Please change URL to localhost and refresh.";
        }
        return "Camera access is blocked by the website's permissions policy. This usually requires the site to be served over HTTPS or localhost.";
    }

    if (isLikelyInsecureContextError(err)) {
        if (
            typeof window !== "undefined" &&
            window.location.protocol === "http:" &&
            window.location.hostname === "127.0.0.1"
        ) {
            return "Camera is blocked on 127.0.0.1. Open the same page with http://localhost and retry.";
        }
        return "Camera requires a secure origin. Use HTTPS or localhost and retry.";
    }
    if (err?.name === "NotAllowedError" || err?.name === "PermissionDeniedError") {
        return "Camera access was denied. Please check browser permissions and OS privacy settings for camera access.";
    }
    if (err?.name === "NotFoundError" || err?.name === "DevicesNotFoundError") {
        return "No camera device was found on this system.";
    }
    if (err?.name === "NotReadableError" || err?.name === "TrackStartError") {
        return "Camera is in use by another application. Close it and retry.";
    }
    if (err?.name === "OverconstrainedError" || err?.name === "ConstraintNotSatisfiedError") {
        return "Requested camera mode is unavailable on this device.";
    }
    return `Could not access camera: ${err?.message || 'Unknown error'}`;
};

const addPermissionsPolicyMetaTag = () => {
    let existingMeta = document.querySelector('meta[http-equiv="Permissions-Policy"]');

    if (!existingMeta) {
        const meta = document.createElement('meta');
        meta.httpEquiv = 'Permissions-Policy';
        meta.content = 'camera=(self), microphone=(self), geolocation=(self)';
        document.head.appendChild(meta);
        console.log('✅ Permissions-Policy meta tag added to document head');
        return true;
    }

    if (!existingMeta.content.includes('camera')) {
        existingMeta.content = 'camera=(self), microphone=(self), geolocation=(self)';
        console.log('✅ Permissions-Policy meta tag updated');
    }

    return false;
};

function BeforeAfterCompare({
    beforeSrc,
    afterSrc,
    beforeLabel = "Before",
    afterLabel = "After",
    imageFilter,
    className = "",
}) {
    const containerRef = useRef(null);
    const [position, setPosition] = useState(50);
    const [isDragging, setIsDragging] = useState(false);

    const updateFromClientX = useCallback((clientX) => {
        const el = containerRef.current;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        if (!rect.width) return;
        const raw = ((clientX - rect.left) / rect.width) * 100;
        const clamped = Math.max(0, Math.min(100, raw));
        setPosition(clamped);
    }, []);

    const onPointerDown = useCallback((e) => {
        if (!e.isPrimary) return;
        e.preventDefault();
        setIsDragging(true);
        updateFromClientX(e.clientX);
        try {
            e.currentTarget.setPointerCapture(e.pointerId);
        } catch {
        }
    }, [updateFromClientX]);

    const onPointerMove = useCallback((e) => {
        if (!isDragging) return;
        if (!e.isPrimary) return;
        updateFromClientX(e.clientX);
    }, [isDragging, updateFromClientX]);

    const onPointerUp = useCallback((e) => {
        if (!e.isPrimary) return;
        setIsDragging(false);
        try {
            e.currentTarget.releasePointerCapture(e.pointerId);
        } catch {
        }
    }, []);

    const onContainerClick = useCallback((e) => {
        if (isDragging) return;
        updateFromClientX(e.clientX);
    }, [isDragging, updateFromClientX]);

    return (
        <div
            ref={containerRef}
            onClick={onContainerClick}
            className={`relative w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white select-none ${className}`}
        >
            <img
                src={afterSrc}
                alt={afterLabel}
                className="absolute inset-0 w-full h-full object-contain"
                style={imageFilter ? { filter: imageFilter } : undefined}
                draggable={false}
            />

            <div
                className="absolute inset-y-0 left-0 overflow-hidden"
                style={{
                    width: `${position}%`,
                    transition: isDragging ? "none" : "width 180ms ease",
                }}
            >
                <img
                    src={beforeSrc}
                    alt={beforeLabel}
                    className="absolute inset-0 w-full h-full object-contain"
                    style={imageFilter ? { filter: imageFilter } : undefined}
                    draggable={false}
                />
            </div>

            <div
                className="absolute inset-y-0"
                style={{
                    left: `${position}%`,
                    transform: "translateX(-50%)",
                }}
            >
                <div
                    onPointerDown={onPointerDown}
                    onPointerMove={onPointerMove}
                    onPointerUp={onPointerUp}
                    onPointerCancel={onPointerUp}
                    className="absolute inset-y-0 left-1/2 -translate-x-1/2 w-14 cursor-ew-resize"
                    style={{ touchAction: "none" }}
                    aria-label="Drag to compare"
                    role="slider"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={Math.round(position)}
                >
                    <div className="absolute inset-y-0 left-1/2 -translate-x-1/2 w-[3px] bg-white/90 shadow-[0_0_0_1px_rgba(0,0,0,0.2)]" />
                    <div className="absolute top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2 w-16 h-2 rounded-full bg-white/95 shadow-lg border border-gray-200" />
                </div>
            </div>
        </div>
    );
}

const getCsrfToken = () => {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }

    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(value);
        }
    }

    return null;
};

const apiRequest = async (url, options = {}) => {
    const csrfToken = getCsrfToken();

    const defaultHeaders = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };

    if (csrfToken) {
        defaultHeaders['X-CSRF-TOKEN'] = csrfToken;
    }

    defaultHeaders['X-Requested-With'] = 'XMLHttpRequest';

    const config = {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers,
        },
        credentials: 'same-origin',
    };

    try {
        const response = await fetch(url, config);

        if (response.status === 419) {
            try {
                const tokenResponse = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'same-origin',
                });

                if (tokenResponse.ok) {
                    const newToken = getCsrfToken();
                    if (newToken) {
                        config.headers['X-CSRF-TOKEN'] = newToken;
                        const retryResponse = await fetch(url, config);
                        return retryResponse;
                    }
                }
            } catch (tokenError) {
                console.warn('Failed to refresh CSRF token');
            }

            return {
                ok: false,
                status: 419,
                statusText: 'CSRF Token Mismatch',
                json: async () => ({ success: false, silent: true }),
                headers: response.headers,
            };
        }

        return response;
    } catch (error) {
        if (error.message?.includes('CSRF') || error.message?.includes('419')) {
            error.silent = true;
        }
        throw error;
    }
};

const apiFormRequest = async (url, options = {}) => {
    const csrfToken = getCsrfToken();

    const defaultHeaders = {
        'Accept': 'application/json',
    };

    if (csrfToken) {
        defaultHeaders['X-CSRF-TOKEN'] = csrfToken;
    }

    defaultHeaders['X-Requested-With'] = 'XMLHttpRequest';

    const config = {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers,
        },
        credentials: 'same-origin',
    };

    try {
        const response = await fetch(url, config);

        if (response.status === 419) {
            try {
                const tokenResponse = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'same-origin',
                });

                if (tokenResponse.ok) {
                    const newToken = getCsrfToken();
                    if (newToken) {
                        config.headers['X-CSRF-TOKEN'] = newToken;
                        const retryResponse = await fetch(url, config);
                        return retryResponse;
                    }
                }
            } catch (tokenError) {
                void tokenError;
            }

            return {
                ok: false,
                status: 419,
                statusText: 'CSRF Token Mismatch',
                json: async () => ({ success: false, silent: true }),
                headers: response.headers,
            };
        }

        return response;
    } catch (error) {
        if (error.message?.includes('CSRF') || error.message?.includes('419')) {
            error.silent = true;
        }
        throw error;
    }
};

const base64ToFile = (base64, filename) => {
    const arr = base64.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, { type: mime });
};

const VALID_WATERMARK_POSITIONS = [
    'top_left',
    'top_center',
    'top_right',
    'bottom_left',
    'bottom_center',
    'bottom_right'
];

const DEFAULT_AI_PARAMS = {
    remove_bg: true,
    height: 1063,
    width: 827,
    background_color: "#FFFFFF",
    water_mark_text: null,
    water_mark_text_color: null,
    water_mark_logo: null,
    watermark_position: null,
    image_shape: "portrait",
    gradient_enabled: false,
    gradient_start_color: null,
    gradient_end_color: null,
    gradient_direction: "to right"
};

const enhanceImageWithAI = async (imageUrl, schoolId, params = {}) => {
    try {
        const mergedParams = { ...DEFAULT_AI_PARAMS, ...params };
        const {
            remove_bg,
            height,
            width,
            background_color,
            water_mark_text,
            water_mark_text_color,
            water_mark_logo,
            watermark_position,
            image_shape,
            gradient_enabled,
            gradient_start_color,
            gradient_end_color,
            gradient_direction,
        } = mergedParams;

        const imageResponse = await fetch(imageUrl);
        const blob = await imageResponse.blob();
        const formData = new FormData();

        formData.append('image', blob, 'image.jpg');
        if (schoolId) {
            formData.append('school_id', String(schoolId));
        }
        formData.append('image_type', 'photo');
        formData.append('remove_bg', remove_bg.toString());

        if (height && !isNaN(height) && height > 0) {
            formData.append('height', Math.round(height).toString());
        }
        if (width && !isNaN(width) && width > 0) {
            formData.append('width', Math.round(width).toString());
        }

        let finalBackgroundColor = background_color;
        if (gradient_enabled && gradient_start_color && gradient_end_color) {
            finalBackgroundColor = gradient_start_color;
            formData.append('gradient_enabled', 'true');
            formData.append('gradient_start_color', gradient_start_color);
            formData.append('gradient_end_color', gradient_end_color);
            if (gradient_direction) {
                formData.append('gradient_direction', gradient_direction);
            }
        }

        if (finalBackgroundColor && finalBackgroundColor.trim() !== '' && finalBackgroundColor !== 'transparent') {
            formData.append('background_color', finalBackgroundColor);
        }

        if (water_mark_text && water_mark_text.trim() !== '') {
            formData.append('water_mark_text', water_mark_text);
        }

        if (water_mark_text_color && water_mark_text_color.trim() !== '') {
            formData.append('water_mark_text_color', water_mark_text_color);
        }

        if (water_mark_logo && water_mark_logo !== 'null' && water_mark_logo !== '' && water_mark_logo !== 'undefined') {
            try {
                if (water_mark_logo.startsWith('data:image')) {
                    const logoFile = base64ToFile(water_mark_logo, 'logo.png');
                    formData.append('water_mark_logo', logoFile);
                } else if (water_mark_logo.startsWith('http')) {
                    const logoResponse = await fetch(water_mark_logo);
                    const logoBlob = await logoResponse.blob();
                    formData.append('water_mark_logo', logoBlob, 'logo.png');
                }
            } catch (error) {
                console.error('Error converting logo:', error);
            }
        }

        const hasWatermark = Boolean(
            (water_mark_text && water_mark_text.trim() !== '') ||
            (water_mark_logo && water_mark_logo !== 'null' && water_mark_logo !== '' && water_mark_logo !== 'undefined')
        );

        if (hasWatermark) {
            if (watermark_position && VALID_WATERMARK_POSITIONS.includes(watermark_position)) {
                formData.append('watermark_position', watermark_position);
            } else if (watermark_position) {
                const positionMap = {
                    'top-left': 'top_left',
                    'top-center': 'top_center',
                    'top-right': 'top_right',
                    'center-left': 'bottom_left',
                    'center': 'bottom_right',
                    'center-right': 'bottom_right',
                    'bottom-left': 'bottom_left',
                    'bottom-center': 'bottom_center',
                    'bottom-right': 'bottom_right'
                };
                const mappedPosition = positionMap[watermark_position] || 'bottom_right';
                formData.append('watermark_position', mappedPosition);
            }
        }

        if (image_shape && image_shape !== '') {
            const shapeMap = {
                'square': 'square',
                'circle': 'round',
                'rounded': 'round',
                'portrait': 'rectangle',
                'landscape': 'rectangle',
                'oval': 'oval',
                'rectangle': 'rectangle'
            };
            const mappedShape = shapeMap[image_shape] || image_shape;
            formData.append('image_shape', mappedShape);
        }

        const apiResponse = await apiFormRequest('/image-enhance', {
            method: 'POST',
            body: formData,
        });

        if (!apiResponse.ok) {
            const errorText = await apiResponse.text();
            console.error('API Error Response:', errorText);
            try {
                const errorJson = JSON.parse(errorText);
                throw new Error(`API error: ${JSON.stringify(errorJson)}`);
            } catch (e) {
                throw new Error(`API error: ${apiResponse.status} - ${errorText.substring(0, 200)}`);
            }
        }

        const data = await apiResponse.json();
        const src = data?.data?.image || null;
        if (!src) {
            console.error('Unexpected API response format:', data);
            throw new Error('Invalid response format from AI service');
        }
        return src;
    } catch (error) {
        console.error('AI Enhancement error:', error);
        throw new Error(`Failed to enhance image: ${error.message}`);
    }
};

async function fetchImageSettings(schoolId) {
    try {
        const response = await apiRequest(`/api/auth/school/image-settings/${schoolId}`, { method: 'GET' });

        if (!response?.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success && result.data) {
            return result.data;
        }

        return null;
    } catch (error) {
        console.error('Failed to fetch image settings:', error);
        return null;
    }
}

async function fetchCooperateImageSettings() {
    try {
        const response = await apiRequest(`/corporate/settings/image-settings/json`, { method: 'GET' });
        if (!response?.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (result.success && result.data) {
            return result.data;
        }
        return null;
    } catch (error) {
        console.error('Failed to fetch corporate image settings:', error);
        return null;
    }
}

export default function CameraUploadModal({
    isOpen,
    onClose,
    onImageCaptured,
    schoolId = null,
    cooperateId = null,
}) {
    const webcamRef = useRef(null);
    const canvasRef = useRef(null);
    const [hasCamera, setHasCamera] = useState(false);
    const [devices, setDevices] = useState([]);
    const [currentCameraIndex, setCurrentCameraIndex] = useState(0);
    const [imageToCrop, setImageToCrop] = useState(null);
    const [preview, setPreview] = useState(null);
    const [originalPreview, setOriginalPreview] = useState(null);
    const [cameraError, setCameraError] = useState(null);
    const [cameraPermissionStatus, setCameraPermissionStatus] = useState("unknown");
    const [isRequestingPermission, setIsRequestingPermission] = useState(false);
    const [webcamKey, setWebcamKey] = useState(0);
    const [isLocalhostIssue, setIsLocalhostIssue] = useState(false);

    // QR Code states
    const [showQR, setShowQR] = useState(false);
    const [sessionId, setSessionId] = useState(null);
    const [connectionStatus, setConnectionStatus] = useState('disconnected');
    const [error, setError] = useState(null);
    const [isCheckingImages, setIsCheckingImages] = useState(false);
    const [isRefreshingToken, setIsRefreshingToken] = useState(false);

    // AI Enhancement states
    const [isEnhancing, setIsEnhancing] = useState(false);
    const [enhancedImage, setEnhancedImage] = useState(null);
    const [aiError, setAiError] = useState(null);
    const [aiParams, setAiParams] = useState(DEFAULT_AI_PARAMS);

    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);
    const [isCropping, setIsCropping] = useState(false);

    const [brightness, setBrightness] = useState(100);
    const [contrast, setContrast] = useState(100);
    const [photoMethod, setPhotoMethod] = useState(null);
    const [aspectRatio, setAspectRatio] = useState(70 / 90);

    const wasOpenRef = useRef(false);
    const metaTagAddedRef = useRef(false);

    useEffect(() => {
        if (typeof window !== 'undefined' && !metaTagAddedRef.current) {
            addPermissionsPolicyMetaTag();
            metaTagAddedRef.current = true;
        }
    }, []);

    useEffect(() => {
        let cancelled = false;
        if (!isOpen) return;
        if (!schoolId && !cooperateId) return;

        (async () => {
            try {
                const settings = schoolId
                    ? await fetchImageSettings(schoolId)
                    : await fetchCooperateImageSettings();
                if (cancelled || !settings) return;

                let width = settings.width_px;
                let height = settings.height_px;

                if (!width && settings.width_mm) {
                    width = Math.round((parseFloat(settings.width_mm) / 25.4) * 300);
                }
                if (!height && settings.height_mm) {
                    height = Math.round((parseFloat(settings.height_mm) / 25.4) * 300);
                }

                if (width > 0 && height > 0) {
                    setAspectRatio(width / height);
                }

                const newAiParams = {
                    remove_bg: settings.remove_bg !== undefined ? settings.remove_bg : true,
                    width: width > 0 ? width : 827,
                    height: height > 0 ? height : 1063,
                    background_color: settings.background_color || "#FFFFFF",
                    water_mark_text: settings.water_mark_text || null,
                    water_mark_text_color: settings.water_mark_text_color || null,
                    water_mark_logo: settings.water_mark_logo || null,
                    watermark_position: settings.watermark_position || null,
                    image_shape: settings.image_shape || "portrait",
                    gradient_enabled: settings.gradient_enabled || false,
                    gradient_start_color: settings.gradient_start_color || null,
                    gradient_end_color: settings.gradient_end_color || null,
                    gradient_direction: settings.gradient_direction || "to right"
                };

                setAiParams(newAiParams);
            } catch (e) {
                console.error('Failed to fetch image settings:', e);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [isOpen, schoolId, cooperateId]);

    const videoConstraints = {
        width: 1280,
        height: 720,
        facingMode: "environment"
    };

    const saveQRImage = useCallback((imageUrl, photoId) => {
        if (imageUrl) {
            onImageCaptured(imageUrl, "qr", photoId);
            resetStates();
            onClose();
        }
    }, [onImageCaptured, onClose]);

    const checkExistingImages = useCallback(async () => {
        if (!sessionId) return;

        setIsCheckingImages(true);
        try {
            setError(null);
            const url = `/remote-camera/session/${sessionId}/latest`;
            const response = await apiRequest(url);

            if (response.status === 419) return;
            if (!response.ok) {
                if (response.status === 404) return;
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success && data.image_url) {
                const imageWithTimestamp = `${data.image_url}?t=${Date.now()}`;
                saveQRImage(imageWithTimestamp, data.photo_id);
            }
        } catch (err) {
            if (!err.silent && err.message !== 'HTTP error! status: 404') {
                console.error('checkExistingImages error:', err);
            }
        } finally {
            setIsCheckingImages(false);
        }
    }, [sessionId, saveQRImage]);

    const refreshCsrfToken = useCallback(async () => {
        if (isRefreshingToken) return;

        setIsRefreshingToken(true);
        try {
            await fetch('/sanctum/csrf-cookie', {
                method: 'GET',
                credentials: 'same-origin',
            });
        } catch (error) {
            console.warn('Failed to refresh CSRF token:', error);
        } finally {
            setIsRefreshingToken(false);
        }
    }, [isRefreshingToken]);

    useEffect(() => {
        if (isOpen && !wasOpenRef.current) {
            refreshCsrfToken();
            wasOpenRef.current = true;
        } else if (!isOpen && wasOpenRef.current) {
            wasOpenRef.current = false;
            resetStates();
        }
    }, [isOpen, refreshCsrfToken]);

    useEffect(() => {
        if (!sessionId || !showQR) return;

        checkExistingImages();
        const intervalId = setInterval(() => {
            checkExistingImages();
        }, 3000);

        return () => {
            clearInterval(intervalId);
        };
    }, [sessionId, showQR, checkExistingImages]);

    const getCameraDevices = useCallback(async () => {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === "videoinput");
            setDevices(videoDevices);

            const backCameraIndex = videoDevices.findIndex(device =>
                device.label.toLowerCase().includes('back') ||
                device.label.toLowerCase().includes('environment')
            );
            if (backCameraIndex !== -1) {
                setCurrentCameraIndex(backCameraIndex);
            }
        } catch (err) {
            console.error('Error getting camera devices:', err);
        }
    }, []);

    const handleUserMedia = useCallback((stream) => {
        setHasCamera(true);
        setCameraPermissionStatus("granted");
        setCameraError(null);
        setIsLocalhostIssue(false);
        setIsRequestingPermission(false);
        getCameraDevices();
    }, [getCameraDevices]);

    const handleUserMediaError = useCallback((err) => {
        const errorMessage = getCameraErrorMessage(err);
        setIsLocalhostIssue(errorMessage.includes("127.0.0.1"));

        if (isLikelyInsecureContextError(err)) {
            setCameraPermissionStatus("blocked");
            setCameraError(errorMessage);
        } else if (err?.name === "NotAllowedError" || err?.name === "PermissionDeniedError") {
            setCameraPermissionStatus("denied");
            setCameraError(errorMessage);
        } else if (err?.name === "NotFoundError" || err?.name === "DevicesNotFoundError") {
            setCameraError("No camera found on this device.");
        } else if (err?.name === "NotReadableError" || err?.name === "TrackStartError") {
            setCameraError("Camera is busy. Please close other apps using the camera.");
        } else {
            setCameraError(errorMessage);
        }

        setHasCamera(false);
        setIsRequestingPermission(false);
    }, []);

    const requestCameraPermission = useCallback(() => {
        setIsRequestingPermission(true);
        setCameraError(null);
        setCameraPermissionStatus("requesting");
        setIsLocalhostIssue(false);
        setWebcamKey(prev => prev + 1);

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                stream.getTracks().forEach(track => track.stop());
                setHasCamera(true);
                setCameraPermissionStatus("granted");
                setCameraError(null);
                setIsRequestingPermission(false);
                getCameraDevices();
            })
            .catch(err => {
                handleUserMediaError(err);
            });
    }, [getCameraDevices, handleUserMediaError]);

    const resetStates = () => {
        setPreview(null);
        setOriginalPreview(null);
        setImageToCrop(null);
        setIsCropping(false);
        setBrightness(100);
        setContrast(100);
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setCroppedAreaPixels(null);
        setPhotoMethod(null);
        setShowQR(false);
        setSessionId(null);
        setError(null);
        setCameraError(null);
        setIsCheckingImages(false);
        setIsRefreshingToken(false);
        setHasCamera(false);
        setCameraPermissionStatus("unknown");
        setIsRequestingPermission(false);
        setIsLocalhostIssue(false);
        setIsEnhancing(false);
        setEnhancedImage(null);
        setAiError(null);
        setAspectRatio(70 / 90);
    };

    const captureImage = useCallback(() => {
        if (webcamRef.current) {
            const imageSrc = webcamRef.current.getScreenshot();
            if (imageSrc) {
                setImageToCrop(imageSrc);
                setPhotoMethod("camera");
                setIsCropping(true);
                setShowQR(false);
            }
        }
    }, []);

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                setImageToCrop(ev.target.result);
                setPhotoMethod("upload");
                setIsCropping(true);
                setShowQR(false);
            };
            reader.readAsDataURL(file);
        }
    };

    const generateQRCode = () => {
        const newSession = "sess_" + Math.random().toString(36).substr(2, 9) + "_" + Date.now();
        setSessionId(newSession);
        setShowQR(true);
        setError(null);
    };

    const getCaptureUrl = () => {
        return `${window.location.origin}/mobile-capture/${sessionId}${schoolId ? `?school_id=${schoolId}` : ""}`;
    };

    const onCropComplete = useCallback((croppedArea, croppedAreaPixels) => {
        setCroppedAreaPixels(croppedAreaPixels);
    }, []);

    const getCroppedImg = useCallback(async () => {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.src = imageToCrop;
            image.onload = () => {
                const canvas = document.createElement("canvas");
                const ctx = canvas.getContext("2d");
                canvas.width = croppedAreaPixels.width;
                canvas.height = croppedAreaPixels.height;
                ctx.drawImage(
                    image,
                    croppedAreaPixels.x,
                    croppedAreaPixels.y,
                    croppedAreaPixels.width,
                    croppedAreaPixels.height,
                    0,
                    0,
                    croppedAreaPixels.width,
                    croppedAreaPixels.height
                );
                canvas.toBlob((blob) => {
                    if (!blob) {
                        reject(new Error("Failed to crop image"));
                        return;
                    }
                    const fileUrl = URL.createObjectURL(blob);
                    resolve(fileUrl);
                }, "image/jpeg", 0.9);
            };
            image.onerror = reject;
        });
    }, [imageToCrop, croppedAreaPixels]);

    const handleCropAndSave = async () => {
        try {
            const croppedImageUrl = await getCroppedImg();
            setPreview(croppedImageUrl);
            setOriginalPreview(croppedImageUrl);
            setIsCropping(false);
            setImageToCrop(null);
            setEnhancedImage(null);
            setAiError(null);
        } catch (err) {
            console.error('Crop error:', err);
            setError('Failed to crop image');
        }
    };

    const handleAIEnhance = async () => {
        if (!preview) return;

        setIsEnhancing(true);
        setAiError(null);

        try {
            const enhancedUrl = await enhanceImageWithAI(preview, schoolId, aiParams);
            setEnhancedImage(enhancedUrl);
            setPreview(enhancedUrl);
        } catch (err) {
            console.error('AI Enhancement failed:', err);
            setAiError(err.message);
        } finally {
            setIsEnhancing(false);
        }
    };

    const applyFiltersToImage = (imageSrc, brightnessVal, contrastVal) => {
        return new Promise((resolve) => {
            const bVal = parseInt(brightnessVal, 10);
            const cVal = parseInt(contrastVal, 10);
            if (bVal === 100 && cVal === 100) {
                resolve(imageSrc);
                return;
            }
            const img = new Image();
            img.crossOrigin = "anonymous";
            img.src = imageSrc;
            img.onload = () => {
                const canvas = document.createElement("canvas");
                canvas.width = img.naturalWidth || img.width || 600;
                canvas.height = img.naturalHeight || img.height || 600;
                const ctx = canvas.getContext("2d");
                ctx.filter = `brightness(${bVal}%) contrast(${cVal}%)`;
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                try {
                    resolve(canvas.toDataURL("image/jpeg", 0.92));
                } catch (e) {
                    resolve(imageSrc);
                }
            };
            img.onerror = () => resolve(imageSrc);
        });
    };

    const handleSave = async () => {
        if (!preview) return;

        try {
            const finalImage = await applyFiltersToImage(preview, brightness, contrast);
            onImageCaptured(finalImage, photoMethod || "upload");
            resetStates();
            onClose();
        } catch (err) {
            console.error("Error saving filtered image:", err);
            onImageCaptured(preview, photoMethod || "upload");
            resetStates();
            onClose();
        }
    };

    const handleClose = () => {
        resetStates();
        onClose();
    };

    const handleRetryCamera = () => {
        setCameraError(null);
        setCameraPermissionStatus("unknown");
        setHasCamera(false);
        requestCameraPermission();
    };

    const displayError = cameraError || error;

    const currentVideoConstraints = devices.length > 0 && currentCameraIndex < devices.length
        ? { deviceId: { exact: devices[currentCameraIndex].deviceId } }
        : videoConstraints;

    const shouldShowCameraTroubleshooting = Boolean(
        isLocalhostIssue ||
        (displayError && !String(displayError).includes('CSRF')) ||
        cameraPermissionStatus === "denied" ||
        cameraPermissionStatus === "blocked"
    );

    const cameraTroubleshootingTitle = isLocalhostIssue ? "URL Issue Detected" : "Camera Access Issue";

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-60 w-screen" style={{ zIndex: 99999 }}>
            <div className="bg-white dark:bg-[#0a0e25] rounded-xl p-2 sm:p-6 w-full max-w-md shadow-xl relative m-1 max-h-[90vh] overflow-y-auto">
                <button
                    onClick={handleClose}
                    className="absolute top-2 right-2 p-2 rounded-full bg-red-500 text-white hover:bg-red-600 transition-colors z-10"
                >
                    <MdClose size={20} />
                </button>

                <h2 className="sm:text-xl text-lg w-full font-bold text-gray-800 dark:text-white mb-4 flex items-center justify-start gap-2">
                    <MdCamera /> Upload Photo
                </h2>

                {shouldShowCameraTroubleshooting && (
                    <div className="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                        <div className="flex items-start gap-2">
                            <MdError size={20} className="flex-shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <div className="font-semibold text-sm mb-1">{cameraTroubleshootingTitle}</div>

                                {isLocalhostIssue ? (
                                    <>
                                        <p className="text-sm mb-2">
                                            You are using <strong>127.0.0.1</strong> which blocks camera access.
                                        </p>
                                        <p className="text-sm mb-2">Please change the URL to:</p>
                                        <code className="block bg-red-200/50 p-2 rounded text-sm mb-2 break-all">
                                            http://localhost{window.location.port ? `:${window.location.port}` : ''}{window.location.pathname}
                                        </code>
                                        <button
                                            onClick={() => {
                                                const newUrl = `http://localhost${window.location.port ? `:${window.location.port}` : ''}${window.location.pathname}${window.location.search}${window.location.hash}`;
                                                window.location.href = newUrl;
                                            }}
                                            className="mt-1 text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition-colors"
                                        >
                                            Go to localhost
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        {!!displayError && !String(displayError).includes('CSRF') && (
                                            <p className="text-sm mb-2">{displayError}</p>
                                        )}

                                        {(cameraPermissionStatus === "denied" ||
                                            cameraPermissionStatus === "blocked" ||
                                            cameraPermissionStatus === "unknown") && (
                                            <button
                                                onClick={handleRetryCamera}
                                                className="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition-colors"
                                            >
                                                Retry Camera
                                            </button>
                                        )}

                                        {cameraPermissionStatus === "denied" && (
                                            <div className="mt-3 border border-amber-300 bg-amber-50 text-amber-900 rounded-lg p-3">
                                                <div className="text-sm font-semibold mb-2">
                                                    How to allow camera access in {getBrowserName()}:
                                                </div>
                                                <ol className="text-xs space-y-1 list-decimal list-inside">
                                                    {getPermissionSteps(getBrowserName()).map((step, idx) => (
                                                        <li key={idx}>{step}</li>
                                                    ))}
                                                </ol>
                                                <button
                                                    onClick={() => window.location.reload()}
                                                    className="mt-3 text-xs bg-amber-600 text-white px-3 py-1 rounded hover:bg-amber-700"
                                                >
                                                    Refresh Page
                                                </button>
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>

                            <button
                                onClick={() => {
                                    setError(null);
                                    setCameraError(null);
                                }}
                                className="text-red-700 hover:text-red-900"
                            >
                                <MdClose size={16} />
                            </button>
                        </div>
                    </div>
                )}

                {showQR ? (
                    <div className="flex flex-col items-center py-4">
                        <button
                            onClick={() => {
                                setShowQR(false);
                                setError(null);
                            }}
                            className="self-start mb-4 flex items-center gap-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                        >
                            <MdArrowBack /> Back
                        </button>

                        <div className="bg-white p-4 rounded-lg mb-4 shadow-lg">
                            <QRCodeCanvas
                                value={getCaptureUrl()}
                                size={200}
                                level="H"
                                includeMargin={true}
                            />
                        </div>

                        <p className="text-sm text-gray-600 dark:text-gray-300 text-center mb-2">
                            Session ID: <span className="font-mono font-bold bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">{sessionId}</span>
                        </p>

                        <p className="text-sm text-gray-500 dark:text-gray-400 text-center mb-4">
                            Scan this QR code with your phone to capture a photo
                        </p>

                        <div className="flex items-center gap-3 mt-4">
                            <div className="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                            <span className="text-sm text-gray-600 dark:text-gray-400">Waiting for photo from phone...</span>
                        </div>

                        <button
                            onClick={checkExistingImages}
                            disabled={isCheckingImages || isRefreshingToken}
                            className="mt-4 text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1 disabled:opacity-50"
                        >
                            {isCheckingImages ? (
                                <>
                                    <div className="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                                    Checking...
                                </>
                            ) : (
                                <>
                                    <MdRefresh size={16} />
                                    Check for existing photos
                                </>
                            )}
                        </button>
                    </div>
                ) : (
                    <>
                        <div className="flex items-center justify-start gap-4 mb-4">
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="photo-aspect"
                                    checked={aspectRatio === 1}
                                    onChange={() => setAspectRatio(1)}
                                    className="w-4 h-4 text-green-600 bg-gray-100 border-gray-600 focus:ring-green-500 dark:focus:ring-green-600 dark:ring-green-800 focus:ring-1 dark:bg-white dark:border-gray-600"
                                />
                                Square (1:1)
                            </label>
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="photo-aspect"
                                    checked={aspectRatio === 5 / 6}
                                    onChange={() => setAspectRatio(5 / 6)}
                                    className="w-4 h-4 text-green-600 bg-gray-100 border-gray-600 focus:ring-green-500 dark:focus:ring-green-600 dark:ring-green-800 focus:ring-1 dark:bg-white dark:border-gray-600"
                                />
                                Portrait (5:6)
                            </label>
                        </div>

                        {isCropping ? (
                            <div className="relative w-full h-80 rounded-lg overflow-hidden mb-4">
                                <Cropper
                                    image={imageToCrop}
                                    crop={crop}
                                    zoom={zoom}
                                    aspect={aspectRatio}
                                    onCropChange={setCrop}
                                    onZoomChange={setZoom}
                                    onCropComplete={onCropComplete}
                                />
                                <button
                                    onClick={handleCropAndSave}
                                    className="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors shadow-lg z-10"
                                >
                                    Crop & Save
                                </button>
                            </div>
                        ) : preview ? (
                            <>
                                {aiError && (
                                    <div className="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-lg text-sm">
                                        {aiError}
                                    </div>
                                )}

                                {enhancedImage && originalPreview ? (
                                    <BeforeAfterCompare
                                        beforeSrc={originalPreview}
                                        afterSrc={enhancedImage}
                                        beforeLabel="Old Photo"
                                        afterLabel="New Photo"
                                        imageFilter={`brightness(${brightness}%) contrast(${contrast}%)`}
                                        className="h-80 mb-4"
                                    />
                                ) : (
                                    <img
                                        src={preview}
                                        alt="Preview"
                                        className="w-full rounded-lg mb-4 object-contain max-h-80 border-2 border-gray-200 dark:border-gray-700 bg-white"
                                        style={{
                                            filter: `brightness(${brightness}%) contrast(${contrast}%)`,
                                        }}
                                    />
                                )}

                                <div className="flex flex-col gap-3 mb-4">
                                    <div className="flex flex-col">
                                        <label className="text-sm text-gray-700 dark:text-gray-300">
                                            Brightness: {brightness}%
                                        </label>
                                        <input
                                            type="range"
                                            min="50"
                                            max="150"
                                            value={brightness}
                                            onChange={(e) =>
                                                setBrightness(e.target.value)
                                            }
                                            className="w-full accent-blue-600"
                                        />
                                    </div>
                                    <div className="flex flex-col">
                                        <label className="text-sm text-gray-700 dark:text-gray-300">
                                            Contrast: {contrast}%
                                        </label>
                                        <input
                                            type="range"
                                            min="50"
                                            max="150"
                                            value={contrast}
                                            onChange={(e) =>
                                                setContrast(e.target.value)
                                            }
                                            className="w-full accent-blue-600"
                                        />
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div className="relative">
                                {cameraPermissionStatus === "unknown" && !hasCamera && !isRequestingPermission && !isLocalhostIssue && (
                                    <div className="w-full h-80 bg-gray-100 rounded-lg mb-4 flex flex-col items-center justify-center">
                                        <MdCamera size={48} className="text-gray-400 mb-4" />
                                        <button
                                            onClick={requestCameraPermission}
                                            className="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors"
                                        >
                                            Allow Camera Access
                                        </button>
                                        <p className="text-xs text-gray-500 mt-3 text-center px-4">
                                            Camera access is required to take photos
                                        </p>
                                    </div>
                                )}

                                {isRequestingPermission && !hasCamera && !cameraError && !isLocalhostIssue && (
                                    <div className="w-full h-80 bg-gray-100 rounded-lg mb-4 flex flex-col items-center justify-center">
                                        <div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                                        <p className="text-sm text-gray-600">Requesting camera permission...</p>
                                        <p className="text-xs text-gray-500 mt-2">Please check browser permission dialog</p>
                                    </div>
                                )}

                                {hasCamera && !isLocalhostIssue && (
                                    <Webcam
                                        key={webcamKey}
                                        ref={webcamRef}
                                        audio={false}
                                        screenshotFormat="image/jpeg"
                                        videoConstraints={currentVideoConstraints}
                                        onUserMedia={handleUserMedia}
                                        onUserMediaError={handleUserMediaError}
                                        className="w-full rounded-lg mb-4 max-h-80 object-contain bg-gray-900"
                                    />
                                )}
                            </div>
                        )}

                        <canvas ref={canvasRef} className="hidden"></canvas>

                        <div className="grid grid-cols-2 gap-3 mt-4">
                            {!preview && !isCropping && (
                                <>
                                    <button
                                        onClick={captureImage}
                                        className="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
                                        disabled={!hasCamera || cameraError !== null || isLocalhostIssue}
                                    >
                                        <MdCamera /> Capture
                                    </button>
                                    <button
                                        onClick={generateQRCode}
                                        className="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition-colors flex items-center justify-center gap-2"
                                    >
                                        <MdQrCodeScanner /> Generate QR
                                    </button>
                                </>
                            )}
                            {!isCropping && (
                                <label className="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors flex items-center justify-center gap-2 cursor-pointer col-span-2">
                                    <MdFileUpload /> Choose File
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={handleFileChange}
                                        className="hidden"
                                    />
                                </label>
                            )}
                            {preview && !isCropping && (
                                <>
                                    <button
                                        onClick={handleSave}
                                        className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-lg font-medium transition-colors flex items-center justify-center gap-2 shadow-md col-span-2"
                                    >
                                        <MdCheckCircle size={18} /> Save
                                    </button>
                                    <button
                                        onClick={() => {
                                            setPreview(null);
                                            setOriginalPreview(null);
                                            setEnhancedImage(null);
                                            setAiError(null);
                                        }}
                                        className="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors col-span-2"
                                    >
                                        Retake
                                    </button>
                                </>
                            )}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
