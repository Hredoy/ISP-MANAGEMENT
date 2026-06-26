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
        icon: Users,
        children: [
            { name: 'CLIENT_LIST', href: '/dashboard/clients', icon: List },
            { name: 'ADD_NEW_CLIENT', href: '/dashboard/clients/create', icon: UserPlus },
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
        icon: Server,
        children: [
            { name: 'LIST_ROUTERS', href: '/dashboard/mikrotik', icon: List, component: 'Mikrotik/Index' },
            { name: 'ADD_NEW', href: '/dashboard/mikrotik/create', icon: Plus, component: 'Mikrotik/Create' },
        ]
    },
    { name: 'SUBSCRIBERS', href: '/dashboard/subscribers', icon: Users, component: 'Subscribers/Index' },
    { name: 'PACKAGES', href: '/dashboard/packages', icon: Zap, component: 'Packages/Index' },
    { name: 'BILLING', href: '/dashboard/billing', icon: CreditCard, component: 'Billing/Index' },
    { name: 'LANDLORD_TENANTS', href: '/landlord/tenants', icon: Settings, component: 'Landlord/Tenants' },
    { name: 'SETTINGS', href: '/dashboard/settings', icon: Settings, component: 'Settings' },
];
