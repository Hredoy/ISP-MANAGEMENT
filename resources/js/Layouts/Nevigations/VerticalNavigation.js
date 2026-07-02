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
    MonitorCog,
    Cable,
    Plug,
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
            { name: 'ROUTER_MODE', href: '/dashboard/settings/mikrotik-mode', icon: Settings, component: 'Settings/MikrotikMode', module: 'mikrotik' },
        ]
    },
    {
        name: 'OLT_DEVICES',
        module: 'olt',
        icon: Cable,
        children: [
            { name: 'LIST_OLTS', href: '/dashboard/olts', icon: List, component: 'Olts/Index', module: 'olt' },
            { name: 'ADD_NEW_OLT', href: '/dashboard/olts/create', icon: Plus, component: 'Olts/Create', module: 'olt' },
        ]
    },
    { name: 'SUBSCRIBERS', href: '/dashboard/subscribers', icon: Users, component: 'Subscribers/Index' },
    { name: 'PACKAGES', href: '/dashboard/packages', icon: Zap, component: 'Packages/Index', module: 'packages' },
    { name: 'INTEGRATIONS', href: '/dashboard/integrations', icon: Plug, component: 'Integrations/Index', module: 'sms' },
    { name: 'FRONTEND', href: '/dashboard/frontend', icon: MonitorCog, component: 'Tenant/FrontendAdmin', module: 'settings' },
    { name: 'BILLING', href: '/dashboard/billing', icon: CreditCard, component: 'Billing/Index', module: 'billing' },
    { name: 'SETTINGS', href: '/dashboard/settings', icon: Settings, component: 'Settings', module: 'settings' },
];
