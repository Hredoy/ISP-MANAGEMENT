<script setup>
import {ref, onMounted, watch} from 'vue';
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

onMounted(() => {
    if (localStorage.theme === 'light') {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
    }
});

</script>
<style>
.dark main {
    position: relative;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%),
    linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
    background-size: 100% 2px, 3px 100%;
}
</style>
<template>
    <div :class="{ 'dark': isDark }" class="min-h-screen font-mono transition-colors duration-300">
        <div class="flex h-screen w-full overflow-hidden"
             :class="isDark ? 'bg-black text-primary' : 'bg-white text-slate-900'">

            <aside :class="[isSidebarOpen ? 'w-64' : 'w-20', isDark ? 'bg-black border-r border-primary/20' : 'bg-gray-50 border-r border-gray-200']" class="flex flex-col transition-all duration-300">
                <div class="p-6 font-bold border-b" :class="isDark ? 'border-primary/20' : 'border-gray-200'">
                    <span v-if="isSidebarOpen" class="text-primary italic">CORE_v1.0</span>
                    <span v-else>#</span>
                </div>

                <nav class="flex-1 mt-6 px-3 space-y-2">
                    <div v-for="item in navItems" :key="item.name">
                        <Link v-if="!item.children" :href="item.href"
                              class="flex items-center p-3 rounded border transition-all"
                              :class="$page.component === item.component ? 'bg-primary text-black' : 'text-primary hover:bg-primary/10 border-transparent'">
                            <component :is="item.icon" :size="18" />
                            <span v-if="isSidebarOpen" class="ml-4 text-[10px] font-bold tracking-tighter">{{ item.name }}</span>
                        </Link>

                        <div v-else>
                            <button @click="toggleSubMenu(item.name)"
                                    class="w-full flex items-center p-3 rounded border border-transparent text-primary hover:bg-primary/10 transition-all">
                                <component :is="item.icon" :size="18" />
                                <span v-if="isSidebarOpen" class="ml-4 text-[10px] font-bold tracking-tighter">{{ item.name }}</span>
                                <span v-if="isSidebarOpen" class="ml-auto text-[10px]">{{ openSubMenu === item.name ? '[-]' : '[+]' }}</span>
                            </button>

                            <div v-if="openSubMenu === item.name && isSidebarOpen" class="ml-6 mt-1 space-y-1 border-l border-primary/20">
                                <Link v-for="child in item.children" :key="child.name" :href="child.href"
                                      class="flex items-center p-2 pl-6 text-[9px] font-bold hover:text-white transition-colors"
                                      :class="$page.component === child.component ? 'text-white' : 'text-primary/60'">
                                    <component :is="child.icon" :size="14" class="mr-2" />
                                    {{ child.name }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </nav>
            </aside>

            <div class="flex-1 flex flex-col min-w-0">
                <header class="h-16 flex items-center justify-between px-8 border-b"
                        :class="isDark ? 'bg-black border-primary/20' : 'bg-white border-gray-200'">

                    <button @click="isSidebarOpen = !isSidebarOpen" class="text-primary hover:scale-110 transition">
                        <Menu :size="24" />
                    </button>

                    <div class="flex items-center gap-6">
                        <button @click="toggleTheme"
                                class="p-2 border rounded flex items-center justify-center transition-all"
                                :class="isDark ? 'border-primary text-primary hover:bg-primary/20' : 'border-slate-900 text-slate-900 hover:bg-gray-100'">
                            <Sun v-if="isDark" :size="18" />
                            <Moon v-else :size="18" />
                        </button>

                        <div class="text-right flex items-center gap-3">
                            <div class="hidden sm:block leading-none">
                                <p class="text-[10px] font-bold uppercase">{{ $page.props.auth.user.name }}</p>
                                <p class="text-[8px] opacity-50 font-mono">ID: ADMIN_01</p>
                            </div>
                            <Link href="/logout" method="post" as="button" class="text-red-500 hover:text-red-400">
                                <LogOut :size="20" />
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-auto p-8" :class="isDark ? 'bg-black' : 'bg-white'">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
