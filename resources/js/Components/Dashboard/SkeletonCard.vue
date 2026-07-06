<script setup>
defineProps({ lines: { type: Number, default: 2 } });
</script>

<template>
    <div class="border border-primary/20 bg-surface p-4 animate-pulse">
        <div class="h-2 w-20 bg-primary/20 rounded mb-3"></div>
        <div v-for="n in lines" :key="n" class="h-4 w-full bg-primary/10 rounded mb-2 last:mb-0"></div>
    </div>
</template>
