import { Link, usePage } from '@inertiajs/react';
import { Home, User } from 'lucide-react';

const BottomNavigation = () => {
    const { url: currentPath } = usePage();

    const navItems = [
        { name: 'Dashboard', href: '/dashboard', icon: Home, active: currentPath === '/dashboard' || currentPath === '/' },
        { name: 'Profile', href: '/settings/profile', icon: User, active: currentPath.startsWith('/settings/profile') }
    ];

    return (
        <div className="fixed bottom-0 left-1/2 transform -translate-x-1/2 w-full max-w-[480px] z-50 px-4 pb-4 pt-2">
            <div className="flex items-center justify-center h-14 bg-white/80 backdrop-blur-lg border border-gray-200/80 shadow-2xl shadow-gray-600/10 rounded-2xl px-6">
                <div className="flex items-center justify-around w-full max-w-xs">
                    {navItems.map((item, index) => {
                        const IconComponent = item.icon;
                        return (
                            <Link
                                key={index}
                                href={item.href}
                                className={`flex items-center justify-center w-10 h-10 rounded-xl transition-colors duration-300 ${
                                    item.active ? 'text-green-600 bg-green-50' : 'text-gray-500 hover:text-green-600 hover:bg-gray-50'
                                }`}
                            >
                                <IconComponent size={24} />
                            </Link>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};

export default function MobileLayout({ children }) {
    return (
        <div className="min-h-screen bg-gray-100">
            <div className="max-w-[480px] mx-auto bg-white min-h-screen pb-24 relative overflow-x-hidden">
                {children}
            </div>
            <BottomNavigation />
        </div>
    );
}
