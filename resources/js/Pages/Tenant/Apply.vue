<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Building2, Check, ChevronLeft, ChevronRight, Cpu, Rocket, Send } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const steps = [
  { title: 'Company', icon: Building2 },
  { title: 'Plan', icon: Rocket },
  { title: 'Devices', icon: Cpu },
  { title: 'Provision', icon: Check },
];

const plans = [
  { id: 'nano', name: 'Nano', price: '500 BDT', speed: '5 Mbps' },
  { id: 'starter', name: 'Starter', price: '800 BDT', speed: '10 Mbps' },
  { id: 'pro', name: 'Pro', price: '1200 BDT', speed: '20 Mbps' },
  { id: 'enterprise', name: 'Enterprise', price: '1500 BDT', speed: '50 Mbps' },
];

const currentStep = ref(0);
const processing = ref(false);
const result = ref(null);
const errors = ref({});
const form = reactive({
  company_name: '',
  district: '',
  logo: null,
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  plan: 'starter',
  mikrotik_ip: '',
  olt_ip: '',
  olt_brand: '',
});

const progress = computed(() => Math.round(((currentStep.value + 1) / steps.length) * 100));
const selectedPlan = computed(() => plans.find((plan) => plan.id === form.plan));

const setLogo = (event) => {
  form.logo = event.target.files?.[0] ?? null;
};

const next = () => {
  if (currentStep.value < steps.length - 1) currentStep.value++;
};

const back = () => {
  if (currentStep.value > 0 && !processing.value) currentStep.value--;
};

const submit = async () => {
  processing.value = true;
  errors.value = {};
  result.value = null;

  const payload = new FormData();
  Object.entries(form).forEach(([key, value]) => {
    if (value !== null && value !== '') payload.append(key, value);
  });

  try {
    const response = await axios.post('/api/onboard', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    result.value = response.data;
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors ?? {};
      currentStep.value = 0;
    } else {
      errors.value = { provision: ['Provisioning failed. Please try again.'] };
    }
  } finally {
    processing.value = false;
  }
};
</script>

<template>
  <GuestLayout>
    <Head title="ISP Onboarding" />

    <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
      <div class="mb-6">
        <div class="mb-3 flex items-center justify-between gap-4">
          <h1 class="text-2xl font-bold text-gray-950">ISP Onboarding</h1>
          <span class="text-sm font-semibold text-gray-600">{{ progress }}%</span>
        </div>
        <div class="h-2 overflow-hidden rounded bg-gray-200">
          <div class="h-full bg-indigo-600 transition-all" :style="{ width: `${progress}%` }" />
        </div>
      </div>

      <div class="mb-8 grid grid-cols-4 gap-2">
        <button
          v-for="(step, index) in steps"
          :key="step.title"
          type="button"
          class="flex min-h-16 flex-col items-center justify-center gap-1 rounded border text-xs font-semibold transition"
          :class="index === currentStep ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-500'"
          @click="currentStep = index"
          :disabled="processing"
        >
          <component :is="step.icon" class="h-5 w-5" />
          <span>{{ step.title }}</span>
        </button>
      </div>

      <section class="bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-6">
        <div v-if="currentStep === 0" class="grid gap-4 md:grid-cols-2">
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Company name</span>
            <input v-model="form.company_name" class="mt-1 w-full rounded border-gray-300" type="text" required>
            <p class="mt-1 text-xs text-red-600">{{ errors.company_name?.[0] }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">District</span>
            <input v-model="form.district" class="mt-1 w-full rounded border-gray-300" type="text" required>
            <p class="mt-1 text-xs text-red-600">{{ errors.district?.[0] }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Logo</span>
            <input class="mt-1 w-full rounded border border-gray-300 p-2 text-sm" type="file" accept="image/*" @change="setLogo">
            <p class="mt-1 text-xs text-red-600">{{ errors.logo?.[0] }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Contact name</span>
            <input v-model="form.contact_name" class="mt-1 w-full rounded border-gray-300" type="text" required>
            <p class="mt-1 text-xs text-red-600">{{ errors.contact_name?.[0] }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Contact email</span>
            <input v-model="form.contact_email" class="mt-1 w-full rounded border-gray-300" type="email" required>
            <p class="mt-1 text-xs text-red-600">{{ errors.contact_email?.[0] }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Contact phone</span>
            <input v-model="form.contact_phone" class="mt-1 w-full rounded border-gray-300" type="tel" required>
            <p class="mt-1 text-xs text-red-600">{{ errors.contact_phone?.[0] }}</p>
          </label>
        </div>

        <div v-if="currentStep === 1" class="grid gap-3 md:grid-cols-4">
          <button
            v-for="plan in plans"
            :key="plan.id"
            type="button"
            class="min-h-36 rounded border p-4 text-left transition"
            :class="form.plan === plan.id ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-gray-400'"
            @click="form.plan = plan.id"
          >
            <span class="block text-lg font-bold text-gray-950">{{ plan.name }}</span>
            <span class="mt-2 block text-sm text-gray-600">{{ plan.speed }}</span>
            <span class="mt-4 block text-xl font-black text-indigo-700">{{ plan.price }}</span>
          </button>
          <p class="md:col-span-4 text-xs text-red-600">{{ errors.plan?.[0] }}</p>
        </div>

        <div v-if="currentStep === 2" class="grid gap-4 md:grid-cols-3">
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">MikroTik IP</span>
            <input v-model="form.mikrotik_ip" class="mt-1 w-full rounded border-gray-300" type="text" placeholder="192.168.88.1" required>
            <p class="mt-1 text-xs text-red-600">{{ errors.mikrotik_ip?.[0] }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">OLT IP</span>
            <input v-model="form.olt_ip" class="mt-1 w-full rounded border-gray-300" type="text" placeholder="Optional">
            <p class="mt-1 text-xs text-red-600">{{ errors.olt_ip?.[0] }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">OLT brand</span>
            <input v-model="form.olt_brand" class="mt-1 w-full rounded border-gray-300" type="text" placeholder="Optional">
            <p class="mt-1 text-xs text-red-600">{{ errors.olt_brand?.[0] }}</p>
          </label>
        </div>

        <div v-if="currentStep === 3" class="space-y-5">
          <div class="grid gap-3 text-sm md:grid-cols-3">
            <div class="rounded border border-gray-200 p-3">
              <span class="block font-semibold text-gray-500">Company</span>
              <span class="block font-bold text-gray-950">{{ form.company_name || 'Pending' }}</span>
            </div>
            <div class="rounded border border-gray-200 p-3">
              <span class="block font-semibold text-gray-500">Plan</span>
              <span class="block font-bold text-gray-950">{{ selectedPlan.name }} · {{ selectedPlan.price }}</span>
            </div>
            <div class="rounded border border-gray-200 p-3">
              <span class="block font-semibold text-gray-500">Router</span>
              <span class="block font-bold text-gray-950">{{ form.mikrotik_ip || 'Pending' }}</span>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded bg-indigo-600 px-4 py-2 font-semibold text-white disabled:opacity-60"
            :disabled="processing || result"
            @click="submit"
          >
            <Send class="h-4 w-4" />
            {{ processing ? 'Provisioning...' : 'Provision tenant' }}
          </button>

          <p v-if="errors.provision" class="text-sm text-red-600">{{ errors.provision[0] }}</p>

          <div v-if="result" class="space-y-3 rounded border border-emerald-200 bg-emerald-50 p-4">
            <div v-for="step in result.steps" :key="step.key" class="flex items-center gap-2 text-sm font-semibold text-emerald-800">
              <Check class="h-4 w-4" />
              <span>{{ step.label }}</span>
            </div>
            <a class="inline-flex font-bold text-indigo-700" :href="result.tenant.login_url">{{ result.tenant.login_url }}</a>
          </div>
        </div>

        <div class="mt-8 flex justify-between">
          <button type="button" class="inline-flex items-center gap-2 rounded border border-gray-300 px-4 py-2 font-semibold" :disabled="currentStep === 0 || processing" @click="back">
            <ChevronLeft class="h-4 w-4" />
            Back
          </button>
          <button v-if="currentStep < 3" type="button" class="inline-flex items-center gap-2 rounded bg-gray-950 px-4 py-2 font-semibold text-white" @click="next">
            Next
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </section>
    </main>
  </GuestLayout>
</template>
