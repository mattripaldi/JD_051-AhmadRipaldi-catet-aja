import {
    BoltIcon,
    BriefcaseIcon,
    CarIcon,
    CircleDollarSignIcon,
    Coffee,
    CreditCardIcon,
    DollarSign,
    GamepadIcon,
    GiftIcon,
    GraduationCapIcon,
    HeartIcon,
    HomeIcon,
    PackageIcon,
    PhoneIcon,
    PiggyBankIcon,
    Plane,
    ShieldIcon,
    ShoppingBagIcon,
    ShoppingCart,
    SparklesIcon,
    TrendingDown,
    TrendingUp,
    UtensilsIcon,
} from 'lucide-react';

export const getCategoryIcon = (iconName) => {
    // Handle undefined or null icon names
    if (!iconName) return CircleDollarSignIcon;

    // Add 'Icon' suffix if it doesn't exist
    const normalizedIconName = iconName.endsWith('Icon') ? iconName : `${iconName}Icon`;

    const iconMap = {
        UtensilsIcon: UtensilsIcon,
        CarIcon: CarIcon,
        GamepadIcon: GamepadIcon,
        BoltIcon: BoltIcon,
        HomeIcon: HomeIcon,
        ShoppingBagIcon: ShoppingBagIcon,
        HeartIcon: HeartIcon,
        GraduationCapIcon: GraduationCapIcon,
        BriefcaseIcon: BriefcaseIcon,
        PackageIcon: PackageIcon,
        TrendingUpIcon: TrendingUp,
        TrendingDownIcon: TrendingDown,
        ShieldIcon: ShieldIcon,
        GiftIcon: GiftIcon,
        SparklesIcon: SparklesIcon,
        CircleDollarSignIcon: CircleDollarSignIcon,
        PhoneIcon: PhoneIcon,
        CreditCardIcon: CreditCardIcon,
        PiggyBankIcon: PiggyBankIcon,
        DollarSignIcon: DollarSign,
    };
    return iconMap[normalizedIconName] ?? CircleDollarSignIcon;
};

export const getCategoryColor = (iconName) => {
    // Handle undefined or null icon names
    if (!iconName) return 'bg-gray-100';

    const colorMap = {
        // Food & Dining
        UtensilsIcon: 'bg-orange-100',

        // Transportation
        CarIcon: 'bg-blue-100',

        // Entertainment
        GamepadIcon: 'bg-purple-100',

        // Utilities
        BoltIcon: 'bg-yellow-100',

        // Housing
        HomeIcon: 'bg-indigo-100',

        // Shopping
        ShoppingBagIcon: 'bg-pink-100',

        // Health
        HeartIcon: 'bg-red-100',

        // Education
        GraduationCapIcon: 'bg-blue-100',

        // Work
        BriefcaseIcon: 'bg-amber-100',

        // Shipping
        PackageIcon: 'bg-emerald-100',

        // Income/Outcome
        TrendingUpIcon: 'bg-green-100',
        TrendingDownIcon: 'bg-red-100',

        // Insurance
        ShieldIcon: 'bg-cyan-100',

        // Gifts
        GiftIcon: 'bg-green-100',

        // Miscellaneous
        SparklesIcon: 'bg-violet-100',

        // Finance
        CircleDollarSignIcon: 'bg-emerald-100',

        // Communication
        PhoneIcon: 'bg-sky-100',

        // Credit Card
        CreditCardIcon: 'bg-slate-100',

        // Savings
        PiggyBankIcon: 'bg-green-100',

        // Money
        DollarSignIcon: 'bg-green-100',
    };

    const normalizedIconName = iconName.endsWith('Icon') ? iconName : `${iconName}Icon`;
    return colorMap[normalizedIconName] ?? 'bg-gray-100';
};

export const getCategoryIconColor = (iconName) => {
    // Handle undefined or null icon names
    if (!iconName) return 'text-gray-600';

    const colorMap = {
        // Food & Dining
        UtensilsIcon: 'text-orange-600',

        // Transportation
        CarIcon: 'text-blue-600',

        // Entertainment
        GamepadIcon: 'text-purple-600',

        // Utilities
        BoltIcon: 'text-yellow-600',

        // Housing
        HomeIcon: 'text-indigo-600',

        // Shopping
        ShoppingBagIcon: 'text-pink-600',

        // Health
        HeartIcon: 'text-red-600',

        // Education
        GraduationCapIcon: 'text-blue-600',

        // Work
        BriefcaseIcon: 'text-amber-600',

        // Shipping
        PackageIcon: 'text-emerald-600',

        // Income/Outcome
        TrendingUpIcon: 'text-green-600',
        TrendingDownIcon: 'text-red-600',

        // Insurance
        ShieldIcon: 'text-cyan-600',

        // Gifts
        GiftIcon: 'text-green-600',

        // Miscellaneous
        SparklesIcon: 'text-violet-600',

        // Finance
        CircleDollarSignIcon: 'text-emerald-600',

        // Communication
        PhoneIcon: 'text-sky-600',

        // Credit Card
        CreditCardIcon: 'text-slate-600',

        // Savings
        PiggyBankIcon: 'text-green-600',

        // Money
        DollarSignIcon: 'text-green-600',
    };

    const normalizedIconName = iconName.endsWith('Icon') ? iconName : `${iconName}Icon`;
    return colorMap[normalizedIconName] ?? 'text-gray-600';
};

export const formatRelativeTime = (date) => {
    const now = new Date();
    const transactionDate = new Date(date);
    const diffInMs = now.getTime() - transactionDate.getTime();
    const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));

    if (diffInDays === 0) {
        return 'Hari ini';
    } else if (diffInDays === 1) {
        return 'Kemarin';
    } else if (diffInDays <= 7) {
        return `${diffInDays} hari lalu`;
    } else if (diffInDays <= 30) {
        const weeks = Math.floor(diffInDays / 7);
        return weeks === 1 ? '1 minggu lalu' : `${weeks} minggu lalu`;
    } else {
        return formatDate(date);
    }
};

export const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

export const getMonthsArray = () => [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

export const getYearsArray = (count = 5) => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: count }, (_, i) => currentYear - i);
};

// Simplified icon mappings for Index pages (without "Icon" suffix)
export const getCategoryIconSimple = (icon) => {
    const icons = {
        'dollar-sign': DollarSign,
        'shopping-cart': ShoppingCart,
        'home': HomeIcon,
        'car': CarIcon,
        'utensils': UtensilsIcon,
        'coffee': Coffee,
        'briefcase': BriefcaseIcon,
        'heart': HeartIcon,
        'graduation-cap': GraduationCapIcon,
        'plane': Plane,
        'gift': GiftIcon,
    };
    return icons[icon] || DollarSign;
};

export const getCategoryColorSimple = (icon) => {
    const colors = {
        'dollar-sign': 'bg-blue-100',
        'shopping-cart': 'bg-purple-100',
        'home': 'bg-green-100',
        'car': 'bg-orange-100',
        'utensils': 'bg-red-100',
        'coffee': 'bg-brown-100',
        'briefcase': 'bg-gray-100',
        'heart': 'bg-pink-100',
        'graduation-cap': 'bg-indigo-100',
        'plane': 'bg-teal-100',
        'gift': 'bg-yellow-100',
    };
    return colors[icon] || 'bg-gray-100';
};

export const getCategoryIconColorSimple = (icon) => {
    const colors = {
        'dollar-sign': 'text-blue-600',
        'shopping-cart': 'text-purple-600',
        'home': 'text-green-600',
        'car': 'text-orange-600',
        'utensils': 'text-red-600',
        'coffee': 'text-amber-600',
        'briefcase': 'text-gray-600',
        'heart': 'text-pink-600',
        'graduation-cap': 'text-indigo-600',
        'plane': 'text-teal-600',
        'gift': 'text-yellow-600',
    };
    return colors[icon] || 'text-gray-600';
};
