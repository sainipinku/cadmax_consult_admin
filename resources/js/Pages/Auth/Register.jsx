import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import { FaUser, FaPhone, FaEnvelope, FaBuilding, FaMapMarkedAlt, FaCity, FaLock, FaEye, FaEyeSlash, FaCheckCircle, FaClock, FaTimesCircle, FaArrowLeft, FaChevronDown } from 'react-icons/fa';
import Loading from '@/Components/Loading';

const INDIAN_STATES = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
    'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Andaman and Nicobar', 'Chandigarh', 'Dadra and Nagar Haveli', 'Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
];

const STATE_CITY_MAP = {
    'Maharashtra': ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Aurangabad', 'Thane', 'Solapur', 'Amravati', 'Kolhapur'],
    'Gujarat': ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Gandhinagar', 'Junagadh', 'Bhavnagar'],
    'Karnataka': ['Bengaluru', 'Mysuru', 'Hubballi-Dharwad', 'Mangaluru', 'Belagavi', 'Kalaburagi'],
    'Tamil Nadu': ['Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem', 'Erode'],
    'Delhi': ['New Delhi', 'North Delhi', 'South Delhi', 'East Delhi', 'West Delhi', 'Gurugram', 'Noida'],
    'Uttar Pradesh': ['Lucknow', 'Kanpur', 'Varanasi', 'Agra', 'Prayagraj', 'Ghaziabad', 'Meerut'],
    'Rajasthan': ['Jaipur', 'Jodhpur', 'Udaipur', 'Kota', 'Bikaner', 'Ajmer'],
    'Telangana': ['Hyderabad', 'Warangal', 'Nizamabad', 'Karimnagar', 'Ramagundam'],
    'Andhra Pradesh': ['Visakhapatnam', 'Vijayawada', 'Guntur', 'Tirupati', 'Nellore'],
    'Kerala': ['Thiruvananthapuram', 'Kochi', 'Kozhikode', 'Thrissur', 'Kollam'],
    'Punjab': ['Chandigarh', 'Ludhiana', 'Amritsar', 'Jalandhar', 'Patiala'],
    'Haryana': ['Gurugram', 'Faridabad', 'Panipat', 'Ambala', 'Karnal', 'Hisar'],
    'Madhya Pradesh': ['Bhopal', 'Indore', 'Gwalior', 'Jabalpur', 'Ujjain', 'Sagar'],
    'Chhattisgarh': ['Raipur', 'Bhilai', 'Bilaspur', 'Korba', 'Durg'],
    'Odisha': ['Bhubaneswar', 'Cuttack', 'Rourkela', 'Berhampur', 'Sambalpur'],
    'Bihar': ['Patna', 'Gaya', 'Bhagalpur', 'Muzaffarpur', 'Darbhanga'],
    'Jharkhand': ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro', 'Deoghar'],
    'Assam': ['Guwahati', 'Silchar', 'Dibrugarh', 'Jorhat', 'Nagaon'],
    'West Bengal': ['Kolkata', 'Howrah', 'Durgapur', 'Asansol', 'Siliguri'],
    'Uttarakhand': ['Dehradun', 'Haridwar', 'Roorkee', 'Haldwani', 'Nainital'],
    'Himachal Pradesh': ['Shimla', 'Manali', 'Dharamshala', 'Solan', 'Kullu'],
};

export default function Register() {
    const [step, setStep] = useState('register');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [apiErrors, setApiErrors] = useState({});
    const [successData, setSuccessData] = useState(null);
    const [statusCheckPhone, setStatusCheckPhone] = useState('');
    const [isCheckingStatus, setIsCheckingStatus] = useState(false);
    const [statusResult, setStatusResult] = useState(null);

    const [form, setForm] = useState({
        name: '',
        phone: '',
        email: '',
        company_name: '',
        state: '',
        city: '',
        password: '',
        password_confirmation: '',
        terms_agreed: false,
    });

    const phoneDigits = useMemo(() => form.phone.replace(/\D/g, ''), [form.phone]);
    const cityOptions = useMemo(() => STATE_CITY_MAP[form.state] || [], [form.state]);

    const updateForm = (key, value) => {
        setForm(prev => ({ ...prev, [key]: value }));
        if (apiErrors[key]) {
            setApiErrors(prev => {
                const next = { ...prev };
                delete next[key];
                return next;
            });
        }
    };

    const validateForm = () => {
        const errors = {};
        if (!form.name.trim()) errors.name = 'Full name is required.';
        else if (form.name.trim().length < 2) errors.name = 'Name must be at least 2 characters.';

        const digits = form.phone.replace(/\D/g, '');
        if (!digits) errors.phone = 'Mobile number is required.';
        else if (digits.length !== 10) errors.phone = 'Please enter a valid 10-digit mobile number.';

        if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
            errors.email = 'Please enter a valid email address.';
        }

        if (!form.state) errors.state = 'Please select your state.';

        if (!form.password) errors.password = 'Password is required.';
        else if (form.password.length < 6) errors.password = 'Password must be at least 6 characters.';

        if (form.password !== form.password_confirmation) {
            errors.password_confirmation = 'Passwords do not match.';
        }

        if (!form.terms_agreed) errors.terms_agreed = 'You must agree to the Terms & Conditions and Privacy Policy.';

        return errors;
    };

    const submit = async (e) => {
        e.preventDefault();

        const errors = validateForm();
        if (Object.keys(errors).length > 0) {
            setApiErrors(errors);
            return;
        }

        setIsSubmitting(true);
        setApiErrors({});

        try {
            const { data } = await axios.post('/api/auth/register', {
                ...form,
                phone: phoneDigits,
                terms_agreed: form.terms_agreed ? 1 : 0,
            }, {
                headers: {
                    'X-Mobile-App': 'mobile_registration_flow',
                    'Accept': 'application/json',
                },
            });

            if (data.success) {
                setSuccessData(data);
                setStep('pending');
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                const formatted = {};
                Object.entries(error.response.data.errors).forEach(([k, v]) => {
                    formatted[k] = Array.isArray(v) ? v[0] : v;
                });
                setApiErrors(formatted);
            } else if (error.response?.data?.message) {
                setApiErrors({ submit: error.response.data.message });
            } else {
                setApiErrors({ submit: 'Something went wrong. Please try again.' });
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const checkRegistrationStatus = async () => {
        if (!statusCheckPhone.trim()) {
            setStatusResult({ success: false, message: 'Please enter your phone number.' });
            return;
        }

        setIsCheckingStatus(true);
        setStatusResult(null);

        try {
            const { data } = await axios.get('/api/auth/registration-status', {
                params: { phone: statusCheckPhone.replace(/\D/g, '') },
            });
            setStatusResult(data);

            if (data.approval?.can_login) {
                setTimeout(() => {
                    router.get(route('login'));
                }, 3000);
            }
        } catch (error) {
            setStatusResult(error.response?.data || {
                success: false,
                message: 'Failed to check status. Please try again.',
            });
        } finally {
            setIsCheckingStatus(false);
        }
    };

    if (step === 'pending' && successData) {
        return <PendingStatusScreen
            successData={successData}
            form={form}
            phoneDigits={phoneDigits}
            onCheckAnother={() => {
                setStatusCheckPhone(phoneDigits);
                setStep('check_status');
            }}
            onBackToRegister={() => {
                setStep('register');
                setSuccessData(null);
                setForm({
                    name: '', phone: '', email: '', company_name: '',
                    state: '', city: '', password: '', password_confirmation: '', terms_agreed: false,
                });
            }}
            onGoLogin={() => router.get(route('login'))}
        />;
    }

    if (step === 'check_status') {
        return <StatusCheckScreen
            phone={statusCheckPhone}
            setPhone={setStatusCheckPhone}
            onCheck={checkRegistrationStatus}
            isChecking={isCheckingStatus}
            result={statusResult}
            onBack={() => {
                setStep('register');
                setStatusResult(null);
            }}
            onGoLogin={() => router.get(route('login'))}
        />;
    }

    return (
        <GuestLayout>
            <Head title="Create Your Account | CadMax" />

            <div className="w-full max-w-lg mx-auto">
                <form
                    onSubmit={submit}
                    className="bg-white dark:bg-[#080626] shadow-xl dark:shadow-2xl rounded-2xl p-6 sm:p-8 border border-gray-100 dark:border-gray-700"
                >
                    <div className="text-center mb-8">
                        <h1 className="text-3xl font-extrabold text-[#1e3a8a] dark:text-blue-400 mb-2 tracking-tight">
                            Create Your Account
                        </h1>
                        <p className="text-gray-500 dark:text-gray-400 text-sm sm:text-base">
                            Join CadMax — Construction ERP
                        </p>
                    </div>

                    {apiErrors.submit && (
                        <div className="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm flex items-start gap-2">
                            <FaTimesCircle className="mt-0.5 flex-shrink-0" size={16} />
                            <span>{apiErrors.submit}</span>
                        </div>
                    )}

                    <IconField
                        icon={<FaUser className="text-gray-400" />}
                        label="Full Name"
                        id="name"
                        value={form.name}
                        onChange={(v) => updateForm('name', v)}
                        placeholder="Enter your full name"
                        error={apiErrors.name}
                        autoFocus
                    />

                    <div className="mt-5">
                        <IconField
                            icon={<FaPhone className="text-gray-400" />}
                            label="Mobile Number"
                            id="phone"
                            type="tel"
                            inputMode="numeric"
                            maxLength={10}
                            value={form.phone}
                            onChange={(v) => updateForm('phone', v.replace(/\D/g, '').slice(0, 10))}
                            placeholder="Enter 10-digit mobile number"
                            error={apiErrors.phone}
                            suffix={
                                <span className={`text-xs font-medium ${
                                    phoneDigits.length === 10 ? 'text-green-600 dark:text-green-400' : 'text-gray-400'
                                }`}>
                                    {phoneDigits.length}/10
                                </span>
                            }
                        />
                    </div>

                    <IconField
                        icon={<FaEnvelope className="text-gray-400" />}
                        label="Email Address"
                        id="email"
                        type="email"
                        value={form.email}
                        onChange={(v) => updateForm('email', v)}
                        placeholder="you@company.com (optional)"
                        error={apiErrors.email}
                        optional
                        wrapperClassName="mt-5"
                    />

                    <IconField
                        icon={<FaBuilding className="text-gray-400" />}
                        label="Company Name"
                        id="company_name"
                        value={form.company_name}
                        onChange={(v) => updateForm('company_name', v)}
                        placeholder="Enter company name (optional)"
                        error={apiErrors.company_name}
                        optional
                        wrapperClassName="mt-5"
                    />

                    <div className="mt-5">
                        <SelectField
                            icon={<FaMapMarkedAlt className="text-gray-400" />}
                            label="State"
                            id="state"
                            value={form.state}
                            onChange={(v) => {
                                updateForm('state', v);
                                updateForm('city', '');
                            }}
                            options={INDIAN_STATES}
                            placeholder="Select your state"
                            error={apiErrors.state}
                        />
                    </div>

                    <div className="mt-5">
                        <SelectField
                            icon={<FaCity className="text-gray-400" />}
                            label={form.state ? 'City' : 'Select state first'}
                            id="city"
                            value={form.city}
                            onChange={(v) => updateForm('city', v)}
                            options={cityOptions}
                            placeholder={form.state ? 'Select your city' : 'Please select a state first'}
                            disabled={!form.state}
                            error={apiErrors.city}
                            fallbackText={!form.state ? 'Select state to see cities' : cityOptions.length === 0 ? 'Type city name (free text allowed)' : ''}
                            allowCustom={!cityOptions.length && form.state}
                        />
                    </div>

                    <div className="mt-5">
                        <IconField
                            icon={<FaLock className="text-gray-400" />}
                            label="Password"
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            value={form.password}
                            onChange={(v) => updateForm('password', v)}
                            placeholder="Create a strong password"
                            error={apiErrors.password}
                            suffix={
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(v => !v)}
                                    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors px-2 focus:outline-none"
                                    tabIndex={-1}
                                >
                                    {showPassword ? <FaEyeSlash size={18} /> : <FaEye size={18} />}
                                </button>
                            }
                        />
                    </div>

                    <div className="mt-5">
                        <IconField
                            icon={<FaLock className="text-gray-400" />}
                            label="Confirm Password"
                            id="password_confirmation"
                            type={showConfirmPassword ? 'text' : 'password'}
                            value={form.password_confirmation}
                            onChange={(v) => updateForm('password_confirmation', v)}
                            placeholder="Confirm your password"
                            error={apiErrors.password_confirmation}
                            suffix={
                                <button
                                    type="button"
                                    onClick={() => setShowConfirmPassword(v => !v)}
                                    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors px-2 focus:outline-none"
                                    tabIndex={-1}
                                >
                                    {showConfirmPassword ? <FaEyeSlash size={18} /> : <FaEye size={18} />}
                                </button>
                            }
                        />
                    </div>

                    <div className="mt-6">
                        <label className="flex items-start gap-3 cursor-pointer select-none group">
                            <div className="mt-0.5 flex-shrink-0">
                                <Checkbox
                                    id="terms_agreed"
                                    checked={form.terms_agreed}
                                    onChange={(e) => updateForm('terms_agreed', e.target.checked)}
                                    className="w-5 h-5 rounded text-blue-700 focus:ring-blue-600 border-gray-300 dark:border-gray-600 cursor-pointer group-hover:border-blue-400 transition"
                                />
                            </div>
                            <span className="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                I agree to{' '}
                                <a
                                    href="/terms-and-conditions"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-[#1e40af] dark:text-blue-400 font-semibold hover:underline"
                                >
                                    Terms & Conditions
                                </a>{' '}
                                and{' '}
                                <a
                                    href="/privacy-policy"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-[#1e40af] dark:text-blue-400 font-semibold hover:underline"
                                >
                                    Privacy Policy
                                </a>
                            </span>
                        </label>
                        {apiErrors.terms_agreed && (
                            <p className="mt-1.5 text-xs text-red-600 dark:text-red-400 font-medium">
                                {apiErrors.terms_agreed}
                            </p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="mt-7 w-full py-3.5 sm:py-4 rounded-2xl text-white font-bold text-base sm:text-lg tracking-wide bg-gradient-to-r from-[#1e3a8a] via-[#2563eb] to-[#3b82f6] hover:from-[#1e40af] hover:via-[#1d4ed8] hover:to-[#2563eb] shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center gap-2"
                    >
                        {isSubmitting ? (
                            <>
                                <Loading className="h-5 w-5 !border-2 !border-white !border-t-transparent" />
                                Creating Account...
                            </>
                        ) : (
                            <>
                                <FaCheckCircle className="text-white/90" />
                                Create Account
                            </>
                        )}
                    </button>

                    <div className="mt-6 pt-5 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-3 text-sm">
                        <span className="text-gray-500 dark:text-gray-400">Already have an account?</span>
                        <Link
                            href={route('login')}
                            className="text-[#2563eb] dark:text-blue-400 font-bold hover:underline"
                        >
                            Log In
                        </Link>
                        <span className="hidden sm:inline text-gray-300 dark:text-gray-600">|</span>
                        <button
                            type="button"
                            onClick={() => setStep('check_status')}
                            className="text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 font-medium transition-colors"
                        >
                            Check Approval Status
                        </button>
                    </div>
                </form>
            </div>
        </GuestLayout>
    );
}

function IconField({
    icon, label, id, type = 'text', value, onChange, placeholder,
    error, optional, suffix, autoFocus, wrapperClassName = '', inputMode, maxLength,
}) {
    return (
        <div className={wrapperClassName}>
            <label htmlFor={id} className="flex items-center justify-between mb-1.5">
                <span className="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-1.5">
                    {label}
                    {optional && <span className="text-xs font-normal text-gray-400 dark:text-gray-500">(Optional)</span>}
                </span>
            </label>
            <div className={`relative rounded-2xl border-2 transition-all duration-200 ${
                error
                    ? 'border-red-300 dark:border-red-700 bg-red-50/50 dark:bg-red-900/10'
                    : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 focus-within:border-blue-500 dark:focus-within:border-blue-500 bg-gray-50 dark:bg-gray-800/70 focus-within:bg-white dark:focus-within:bg-gray-800'
            }`}>
                <div className="absolute left-4 top-1/2 -translate-y-1/2 text-lg">
                    {icon}
                </div>
                <TextInput
                    id={id}
                    type={type}
                    name={id}
                    inputMode={inputMode}
                    maxLength={maxLength}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    autoComplete={type === 'password' ? (id.includes('confirm') ? 'new-password' : 'new-password') : 'off'}
                    isFocused={autoFocus}
                    className={`w-full bg-transparent border-0 ring-0 py-3.5 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-0 focus:border-0 ${
                        suffix ? 'pr-12 pl-12' : 'pl-12 pr-4'
                    } rounded-2xl !shadow-none text-base`}
                />
                {suffix && (
                    <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
                        {suffix}
                    </div>
                )}
            </div>
            {error && (
                <InputError message={error} className="mt-1.5 text-xs font-medium" />
            )}
        </div>
    );
}

function SelectField({
    icon, label, id, value, onChange, options, placeholder, error,
    disabled, fallbackText, allowCustom, wrapperClassName = '',
}) {
    const [showCustom, setShowCustom] = useState(false);

    return (
        <div className={wrapperClassName}>
            <label htmlFor={id} className="flex items-center justify-between mb-1.5">
                <span className="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-1.5">
                    {label}
                </span>
                {allowCustom && (
                    <button
                        type="button"
                        onClick={() => setShowCustom(v => !v)}
                        className="text-xs text-blue-600 dark:text-blue-400 font-medium hover:underline"
                    >
                        {showCustom ? 'Select from list' : 'Enter manually'}
                    </button>
                )}
            </label>
            {allowCustom && showCustom ? (
                <div className={`relative rounded-2xl border-2 transition-all duration-200 ${
                    error
                        ? 'border-red-300 dark:border-red-700'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 focus-within:border-blue-500 dark:focus-within:border-blue-500 bg-gray-50 dark:bg-gray-800/70'
                }`}>
                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-lg text-gray-400">
                        {icon}
                    </div>
                    <TextInput
                        id={id}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        placeholder={placeholder}
                        disabled={disabled}
                        className="w-full bg-transparent border-0 ring-0 py-3.5 pl-12 pr-4 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-0 focus:border-0 rounded-2xl !shadow-none text-base"
                    />
                </div>
            ) : (
                <div className={`relative rounded-2xl border-2 transition-all duration-200 ${
                    error
                        ? 'border-red-300 dark:border-red-700'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 focus-within:border-blue-500 dark:focus-within:border-blue-500 bg-gray-50 dark:bg-gray-800/70'
                } ${disabled ? 'opacity-55 cursor-not-allowed' : ''}`}>
                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-lg pointer-events-none z-10">
                        {icon}
                    </div>
                    <select
                        id={id}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        disabled={disabled}
                        className="w-full appearance-none bg-transparent border-0 py-3.5 pl-12 pr-12 text-gray-800 dark:text-gray-100 rounded-2xl text-base cursor-pointer disabled:cursor-not-allowed focus:ring-0 focus:outline-none"
                    >
                        <option value="">{placeholder}</option>
                        {options.map((opt) => (
                            <option key={opt} value={opt} className="bg-white dark:bg-gray-800">
                                {opt}
                            </option>
                        ))}
                    </select>
                    <div className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none z-10">
                        <FaChevronDown size={14} />
                    </div>
                </div>
            )}
            {fallbackText && !error && (
                <p className="mt-1.5 text-xs text-gray-400 dark:text-gray-500 font-medium italic">
                    {fallbackText}
                </p>
            )}
            {error && (
                <InputError message={error} className="mt-1.5 text-xs font-medium" />
            )}
        </div>
    );
}

function PendingStatusScreen({ successData, form, phoneDigits, onCheckAnother, onBackToRegister, onGoLogin }) {
    return (
        <GuestLayout>
            <Head title="Registration Submitted | CadMax" />
            <div className="w-full max-w-lg mx-auto">
                <div className="bg-white dark:bg-[#080626] shadow-xl rounded-2xl p-8 sm:p-10 border border-gray-100 dark:border-gray-700 text-center">
                    <div className="mx-auto w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-gradient-to-br from-amber-100 to-yellow-200 dark:from-amber-900/40 dark:to-yellow-900/30 flex items-center justify-center mb-6 shadow-lg">
                        <FaClock className="text-amber-500" size={42} />
                    </div>

                    <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-800 dark:text-gray-100 mb-2">
                        Registration Submitted!
                    </h2>
                    <p className="text-gray-500 dark:text-gray-400 mb-7 text-base sm:text-lg leading-relaxed">
                        Your account has been created and is{' '}
                        <span className="font-bold text-amber-600 dark:text-amber-400">
                            Pending Admin Approval
                        </span>
                        . You'll be able to log in once a Super Admin reviews and approves your application.
                    </p>

                    <div className="bg-gray-50 dark:bg-gray-800/80 rounded-xl p-5 sm:p-6 text-left border border-gray-100 dark:border-gray-700 mb-7 space-y-3">
                        <InfoRow label="Name" value={form.name} />
                        <InfoRow label="Mobile" value={phoneDigits} />
                        {form.email && <InfoRow label="Email" value={form.email} />}
                        {form.company_name && <InfoRow label="Company" value={form.company_name} />}
                        <InfoRow
                            label="Status"
                            value={
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50">
                                    <FaClock size={10} />
                                    Pending Admin Approval
                                </span>
                            }
                        />
                        <InfoRow label="Est. Time" value="24-48 hours" />
                    </div>

                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <button
                            onClick={onCheckAnother}
                            className="flex-1 py-3 rounded-xl border-2 border-blue-600 text-blue-700 dark:text-blue-400 dark:border-blue-500 font-bold hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all flex items-center justify-center gap-2"
                        >
                            <FaCheckCircle />
                            Check Status
                        </button>
                        <button
                            onClick={onGoLogin}
                            className="flex-1 py-3 rounded-xl bg-gradient-to-r from-[#1e3a8a] to-[#2563eb] text-white font-bold hover:from-[#1e40af] hover:to-[#1d4ed8] shadow-md hover:shadow-lg transition-all"
                        >
                            Go to Login
                        </button>
                    </div>

                    <button
                        onClick={onBackToRegister}
                        className="mt-5 inline-flex items-center gap-1.5 text-sm text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                    >
                        <FaArrowLeft size={12} />
                        Register another account
                    </button>
                </div>
            </div>
        </GuestLayout>
    );
}

function StatusCheckScreen({ phone, setPhone, onCheck, isChecking, result, onBack, onGoLogin }) {
    return (
        <GuestLayout>
            <Head title="Check Approval Status | CadMax" />
            <div className="w-full max-w-lg mx-auto">
                <div className="bg-white dark:bg-[#080626] shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100 dark:border-gray-700">
                    <button
                        onClick={onBack}
                        className="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 mb-5 transition-colors"
                    >
                        <FaArrowLeft size={12} />
                        Back to Register
                    </button>

                    <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-800 dark:text-gray-100 mb-2">
                        Check Approval Status
                    </h2>
                    <p className="text-gray-500 dark:text-gray-400 mb-7">
                        Enter your registered mobile number to check if your account has been approved.
                    </p>

                    <IconField
                        icon={<FaPhone className="text-gray-400" />}
                        label="Registered Mobile Number"
                        id="status_phone"
                        type="tel"
                        inputMode="numeric"
                        maxLength={10}
                        value={phone}
                        onChange={(v) => setPhone(v.replace(/\D/g, '').slice(0, 10))}
                        placeholder="Enter 10-digit mobile number"
                    />

                    <button
                        onClick={onCheck}
                        disabled={isChecking}
                        className="mt-6 w-full py-3.5 rounded-2xl text-white font-bold text-lg bg-gradient-to-r from-[#1e3a8a] to-[#2563eb] hover:from-[#1e40af] hover:to-[#1d4ed8] shadow-md hover:shadow-lg transition-all disabled:opacity-60 flex items-center justify-center gap-2"
                    >
                        {isChecking ? (
                            <>
                                <Loading className="h-5 w-5 !border-2 !border-white !border-t-transparent" />
                                Checking...
                            </>
                        ) : (
                            'Check Status'
                        )}
                    </button>

                    {result && (
                        <div className={`mt-7 p-5 rounded-xl border text-sm ${
                            result.success
                                ? (result.approval?.can_login
                                    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-300'
                                    : result.registered === false
                                        ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300'
                                        : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300')
                                : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300'
                        }`}>
                            <div className="flex items-start gap-3">
                                {result.success ? (
                                    result.approval?.can_login ? (
                                        <FaCheckCircle className="mt-0.5 text-green-600" size={20} />
                                    ) : (
                                        <FaClock className="mt-0.5 text-amber-500" size={20} />
                                    )
                                ) : (
                                    <FaTimesCircle className="mt-0.5 text-red-500" size={20} />
                                )}
                                <div className="flex-1">
                                    <p className="font-bold text-base mb-1">
                                        {result.success
                                            ? (result.approval?.can_login
                                                ? 'Account Approved!'
                                                : result.registered === false
                                                    ? 'No registration found'
                                                    : result.approval?.text)
                                            : 'Status check failed'}
                                    </p>
                                    <p className="opacity-90 leading-relaxed">
                                        {result.success
                                            ? (result.approval?.can_login
                                                ? 'Redirecting to login...'
                                                : result.registered === false
                                                    ? result.message
                                                    : result.approval?.description)
                                            : result.message}
                                    </p>
                                    {result.success && result.member && (
                                        <div className="mt-3 space-y-1 opacity-80 text-xs">
                                            <div>Name: <strong>{result.member.name}</strong></div>
                                            <div>Phone: <strong>{result.member.phone}</strong></div>
                                            {result.member.company_name && (
                                                <div>Company: <strong>{result.member.company_name}</strong></div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="mt-6 pt-5 border-t border-gray-100 dark:border-gray-700 text-center text-sm">
                        <Link
                            href={route('login')}
                            className="text-[#2563eb] dark:text-blue-400 font-bold hover:underline"
                        >
                            Already approved? Log in here →
                        </Link>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}

function InfoRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="text-xs uppercase tracking-wider font-bold text-gray-400 dark:text-gray-500 pt-1 flex-shrink-0">
                {label}
            </span>
            <span className="text-sm font-semibold text-gray-800 dark:text-gray-200 text-right">
                {value}
            </span>
        </div>
    );
}
