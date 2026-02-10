<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
  organization_name: '',
  contact_name: '',
  email: '',
  phone: '',
});

const submit = () => {
  form.post(route('tenant.apply.store'));
};
</script>

<template>
  <GuestLayout>
    <Head title="Apply Organization" />

    <div class="max-w-2xl mx-auto p-6 bg-white rounded shadow">
      <h1 class="text-xl font-bold mb-4">Apply for Your Organization</h1>
      <p class="mb-6 text-sm text-gray-600">Submit your company details. Landlord will review and provision a dedicated tenant database + subdomain after approval.</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <InputLabel for="organization_name" value="Organization Name" />
          <TextInput id="organization_name" v-model="form.organization_name" class="mt-1 block w-full" required />
          <InputError class="mt-2" :message="form.errors.organization_name" />
        </div>

        <div>
          <InputLabel for="contact_name" value="Contact Person" />
          <TextInput id="contact_name" v-model="form.contact_name" class="mt-1 block w-full" required />
          <InputError class="mt-2" :message="form.errors.contact_name" />
        </div>

        <div>
          <InputLabel for="email" value="Email" />
          <TextInput id="email" type="email" v-model="form.email" class="mt-1 block w-full" required />
          <InputError class="mt-2" :message="form.errors.email" />
        </div>

        <div>
          <InputLabel for="phone" value="Phone" />
          <TextInput id="phone" v-model="form.phone" class="mt-1 block w-full" />
          <InputError class="mt-2" :message="form.errors.phone" />
        </div>

        <PrimaryButton :disabled="form.processing">Submit Application</PrimaryButton>
      </form>
    </div>
  </GuestLayout>
</template>
