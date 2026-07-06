<script setup>
import {ref, onMounted, computed} from 'vue';
import {Link, usePage} from '@inertiajs/vue3';
import { navItems} from "@/Layouts/Nevigations/VerticalNavigation.js";
import {
    LogOut,
    Menu,
    Sun,
    Moon
} from 'lucide-vue-next';
import {createToaster} from "@meforma/vue-toaster";

const isDark = ref(true);
const isSidebarOpen = ref(true);

const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};
const openSubMenu = ref(null);
const toggleSubMenu = (name) => {
    openSubMenu.value = openSubMenu.value === name ? null : name;
};
const toaster = createToaster();
const page = usePage();
const enabledModules = computed(() => page.props.tenant?.enabledModules ?? []);
const userPermissions = computed(() => page.props.auth?.permissions ?? []);
const canShow = (item) => (!item.module || enabledModules.value.includes(item.module))
    && (!item.permission || userPermissions.value.includes(item.permission));
const visibleNavItems = computed(() => navItems
    .filter(canShow)
    .map((item) => ({
        ...item,
        children: item.children?.filter(canShow),
    }))
    .filter((item) => !item.children || item.children.length > 0)
);

onMounted(() => {
    if (localStorage.theme === 'light') {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
    }
});

</script>
<template>
    <div :class="{ 'dark': isDark }" class="min-h-screen font-sans transition-colors duration-300">
        <div class="flex h-screen w-full overflow-hidden bg-terminal text-primary">

            <aside :class="isSidebarOpen ? 'w-64' : 'w-20'" class="flex flex-col transition-all duration-300 bg-surface border-r border-primary/20">
                <div class="p-6 font-bold border-b border-primary/20">
                    <span v-if="isSidebarOpen" class="text-primary">CoreISP</span>
                    <span v-else>#</span>
                </div>

                <nav class="flex-1 mt-6 px-3 space-y-2">
                    <div v-for="item in visibleNavItems" :key="item.name">
                        <Link v-if="!item.children" :href="item.href" prefetch
                              class="flex items-center p-3 rounded-lg border transition-all"
                              :class="$page.component === item.component ? 'bg-primary text-black' : 'text-primary hover:bg-primary/10 border-transparent'">
                            <component :is="item.icon" :size="18" />
                            <span v-if="isSidebarOpen" class="ml-4 text-[11px] font-semibold">{{ item.name }}</span>
                        </Link>

                        <div v-else>
                            <button @click="toggleSubMenu(item.name)"
                                    class="w-full flex items-center p-3 rounded-lg border border-transparent text-primary hover:bg-primary/10 transition-all">
                                <component :is="item.icon" :size="18" />
                                <span v-if="isSidebarOpen" class="ml-4 text-[11px] font-semibold">{{ item.name }}</span>
                                <span v-if="isSidebarOpen" class="ml-auto text-[10px] opacity-60">{{ openSubMenu === item.name ? '−' : '+' }}</span>
                            </button>

                            <div v-if="openSubMenu === item.name && isSidebarOpen" class="ml-6 mt-1 space-y-1 border-l border-primary/20">
                                <Link v-for="child in item.children" :key="child.name" :href="child.href" prefetch
                                      class="flex items-center p-2 pl-6 text-[10px] font-medium hover:text-ink transition-colors"
                                      :class="$page.component === child.component ? 'text-ink' : 'text-primary/60'">
                                    <component :is="child.icon" :size="14" class="mr-2" />
                                    {{ child.name }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </nav>
            </aside>

            <div class="flex-1 flex flex-col min-w-0">
                <header class="h-16 flex items-center justify-between px-8 border-b bg-surface border-primary/20">

                    <button @click="isSidebarOpen = !isSidebarOpen" class="text-primary hover:opacity-70 transition">
                        <Menu :size="22" />
                    </button>

                    <div class="flex items-center gap-6">
                        <button @click="toggleTheme"
                                class="p-2 border rounded-lg flex items-center justify-center transition-all border-primary/40 text-primary hover:bg-primary/10">
                            <Sun v-if="isDark" :size="18" />
                            <Moon v-else :size="18" />
                        </button>

                        <div class="text-right flex items-center gap-3">
                            <div class="hidden sm:block leading-none">
                                <p class="text-[11px] font-semibold text-ink">{{ $page.props.auth.user.name }}</p>
                                <p class="text-[10px] opacity-50">Administrator</p>
                            </div>
                            <Link href="/logout" method="post" as="button" class="text-red-500 hover:text-red-400">
                                <LogOut :size="20" />
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-auto p-8 bg-terminal">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
