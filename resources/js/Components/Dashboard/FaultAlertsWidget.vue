<script setup>
import { ShieldAlert } from 'lucide-vue-next';
</script>

<template>
    <div class="border border-primary/20 bg-surface p-4">
        <h3 class="text-[10px] font-bold text-primary underline uppercase tracking-widest mb-3 flex items-center gap-2">
            <ShieldAlert :size="14" /> Active_Fault_Alerts
        </h3>
        <!--
            No fault/alert model exists yet - that lands with the SNMP polling engine + fault
            detection task. Showing an honest empty state instead of fabricating alerts.
        -->
        <p class="text-[11px] text-primary/50">No active fault alerts. Automated fault detection arrives with the SNMP polling engine.</p>
    </div>
</template>
