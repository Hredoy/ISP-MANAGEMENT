import {
    LayoutDashboard,
    Users,
    Zap,
    CreditCard,
    Plus,
    List,
    Server,
    Settings,
    MapPin,
    Globe,
    Map,
    UserPlus,
} from 'lucide-vue-next';
export const navItems = [
    { name: 'DASHBOARD', href: '/dashboard', icon: LayoutDashboard, component: 'Dashboard' },
    {
        name: 'CLIENT_MGMT',
        module: 'customers',
        icon: Users,
        children: [
            { name: 'CLIENT_LIST', href: '/dashboard/clients', icon: List, module: 'customers' },
            { name: 'ADD_NEW_CLIENT', href: '/dashboard/clients/create', icon: UserPlus, module: 'customers' },
        ]
    },
    {
        name: 'AREA_MGMT',
        icon: MapPin, // Import MapPin from lucide-vue-next
        children: [
            { name: 'ZONES', href: '/dashboard/zones', icon: Globe, component: 'Area/Zones' },
            { name: 'SUB_ZONES', href: '/dashboard/sub-zones', icon: Map, component: 'Area/SubZones' },
        ]
    },
    {
        name: 'MIKROTIK_NODES',
        module: 'mikrotik',
        icon: Server,
        children: [
            { name: 'LIST_ROUTERS', href: '/dashboard/mikrotik', icon: List, component: 'Mikrotik/Index', module: 'mikrotik' },
            { name: 'ADD_NEW', href: '/dashboard/mikrotik/create', icon: Plus, component: 'Mikrotik/Create', module: 'mikrotik' },
        ]
    },
    { name: 'SUBSCRIBERS', href: '/dashboard/subscribers', icon: Users, component: 'Subscribers/Index' },
    { name: 'PACKAGES', href: '/dashboard/packages', icon: Zap, component: 'Packages/Index', module: 'packages' },
    { name: 'BILLING', href: '/dashboard/billing', icon: CreditCard, component: 'Billing/Index', module: 'billing' },
    { name: 'LANDLORD', href: '/landlord/dashboard', icon: Settings, component: 'Landlord/Dashboard' },
    { name: 'SETTINGS', href: '/dashboard/settings', icon: Settings, component: 'Settings', module: 'settings' },
];
