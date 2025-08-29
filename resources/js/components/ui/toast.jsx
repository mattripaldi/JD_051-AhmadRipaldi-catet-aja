import { useEffect, useState } from 'react';
import { CheckCircle, XCircle, AlertCircle, X } from 'lucide-react';

const toastVariants = {
    default: 'bg-gray-900 text-white',
    success: 'bg-green-600 text-white',
    error: 'bg-red-600 text-white',
    warning: 'bg-yellow-600 text-white',
};

const Toast = ({ message, type = 'default', onClose, duration = 3000 }) => {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => {
            setVisible(false);
            setTimeout(onClose, 300); // Wait for animation to complete
        }, duration);

        return () => clearTimeout(timer);
    }, [duration, onClose]);

    const iconMap = {
        success: <CheckCircle className="w-5 h-5" />,
        error: <XCircle className="w-5 h-5" />,
        warning: <AlertCircle className="w-5 h-5" />,
        default: null,
    };

    return (
        <div
            className={`fixed top-4 right-4 z-50 flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${
                visible ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'
            } ${toastVariants[type]} max-w-sm`}
        >
            {iconMap[type]}
            <span className="text-sm font-medium flex-1">{message}</span>
            <button
                onClick={() => {
                    setVisible(false);
                    setTimeout(onClose, 300);
                }}
                className="p-1 hover:bg-black/10 rounded transition-colors"
            >
                <X className="w-4 h-4" />
            </button>
        </div>
    );
};

export const ToastContainer = ({ toasts, removeToast }) => {
    return (
        <div className="fixed top-0 right-0 z-50 pointer-events-none">
            {toasts.map((toast) => (
                <div key={toast.id} className="pointer-events-auto">
                    <Toast
                        message={toast.message}
                        type={toast.type}
                        onClose={() => removeToast(toast.id)}
                        duration={toast.duration}
                    />
                </div>
            ))}
        </div>
    );
};

export const useToast = () => {
    const [toasts, setToasts] = useState([]);

    const addToast = (message, type = 'default', duration = 3000) => {
        const id = Date.now().toString();
        setToasts((prev) => [...prev, { id, message, type, duration }]);
        return id;
    };

    const removeToast = (id) => {
        setToasts((prev) => prev.filter((toast) => toast.id !== id));
    };

    const success = (message, duration) => addToast(message, 'success', duration);
    const error = (message, duration) => addToast(message, 'error', duration);
    const warning = (message, duration) => addToast(message, 'warning', duration);

    return {
        toasts,
        addToast,
        removeToast,
        success,
        error,
        warning,
    };
};

export default Toast;
