<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { BriefcaseBusiness, Building2, CalendarCheck, DollarSign, Save, Trash2, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: ISPLayout });

const props = defineProps({
  employees: Object,
  lookups: Object,
  stats: Object,
  attendance: Array,
  leaves: Array,
  payrollRuns: Array,
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions || []);
const can = (permission) => permissions.value.includes(permission);

const employeeForm = useForm({
  employee_no: '',
  name: '',
  email: '',
  phone: '',
  department_id: '',
  designation_id: '',
  team_id: '',
  office_location_id: '',
  shift_id: '',
  joining_date: '',
  basic_salary: 0,
  status: 'active',
  login_enabled: false,
  password: '',
  role: 'Employee',
});

const setupForm = useForm({
  type: 'departments',
  name: '',
  code: '',
  department_id: '',
  phone: '',
  address: '',
  description: '',
  starts_at: '09:00',
  ends_at: '18:00',
  grace_minutes: 10,
  days_per_year: 10,
  is_paid: true,
});

const submitEmployee = () => employeeForm.post(route('dashboard.hrm.employees.store'), {
  preserveScroll: true,
  onSuccess: () => employeeForm.reset(),
});

const submitSetup = () => setupForm.post(route('dashboard.hrm.setup.store', setupForm.type), {
  preserveScroll: true,
  onSuccess: () => setupForm.reset('name', 'code', 'phone', 'address', 'description'),
});
</script>

<template>
  <Head title="Tenant_HRM" />

  <div class="space-y-6 font-mono text-primary">
    <div class="flex flex-col justify-between gap-4 border-b border-primary/20 pb-6 md:flex-row md:items-center">
      <div>
        <h1 class="flex items-center gap-2 text-2xl font-black uppercase italic tracking-tighter">
          <BriefcaseBusiness :size="24" /> Human_Resource_Management
        </h1>
        <p class="mt-2 text-[10px] uppercase tracking-widest text-primary/60">
          Tenant-isolated employees, attendance, leave, payroll, and HR setup.
        </p>
      </div>
    </div>

    <div v-if="page.props.flash?.success" class="border border-primary/30 bg-primary/10 p-4 text-[11px] font-black uppercase">
      {{ page.props.flash.success }}
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="border border-primary/20 bg-black p-4">
        <p class="text-[9px] uppercase text-primary/50">Employees</p>
        <p class="mt-3 text-3xl font-black text-white">{{ stats.employees }}</p>
      </div>
      <div class="border border-primary/20 bg-black p-4">
        <p class="text-[9px] uppercase text-primary/50">Active</p>
        <p class="mt-3 text-3xl font-black text-white">{{ stats.active }}</p>
      </div>
      <div class="border border-primary/20 bg-black p-4">
        <p class="text-[9px] uppercase text-primary/50">On_Leave</p>
        <p class="mt-3 text-3xl font-black text-white">{{ stats.on_leave }}</p>
      </div>
      <div class="border border-primary/20 bg-black p-4">
        <p class="text-[9px] uppercase text-primary/50">Payroll_Runs</p>
        <p class="mt-3 text-3xl font-black text-white">{{ stats.payroll_runs }}</p>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
      <section class="border border-primary/20 bg-black p-5">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-black uppercase text-white">
          <UserPlus :size="18" /> Create_Employee
        </h2>
        <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitEmployee">
          <input v-model="employeeForm.employee_no" class="border-primary/30 bg-black text-primary" placeholder="Employee No" required>
          <input v-model="employeeForm.name" class="border-primary/30 bg-black text-primary" placeholder="Name" required>
          <input v-model="employeeForm.email" type="email" class="border-primary/30 bg-black text-primary" placeholder="Email">
          <input v-model="employeeForm.phone" class="border-primary/30 bg-black text-primary" placeholder="Phone">
          <select v-model="employeeForm.department_id" class="border-primary/30 bg-black text-primary">
            <option value="">Department</option>
            <option v-for="item in lookups.departments" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <select v-model="employeeForm.designation_id" class="border-primary/30 bg-black text-primary">
            <option value="">Designation</option>
            <option v-for="item in lookups.designations" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <select v-model="employeeForm.team_id" class="border-primary/30 bg-black text-primary">
            <option value="">Team</option>
            <option v-for="item in lookups.teams" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <select v-model="employeeForm.office_location_id" class="border-primary/30 bg-black text-primary">
            <option value="">Office Location</option>
            <option v-for="item in lookups.officeLocations" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <select v-model="employeeForm.shift_id" class="border-primary/30 bg-black text-primary">
            <option value="">Shift</option>
            <option v-for="item in lookups.shifts" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <select v-model="employeeForm.role" class="border-primary/30 bg-black text-primary">
            <option v-for="item in lookups.roles" :key="item.id" :value="item.name">{{ item.name }}</option>
          </select>
          <input v-model="employeeForm.joining_date" type="date" class="border-primary/30 bg-black text-primary">
          <input v-model="employeeForm.basic_salary" type="number" min="0" class="border-primary/30 bg-black text-primary" placeholder="Basic Salary">
          <label class="flex items-center gap-2 text-[10px] uppercase text-primary/70">
            <input v-model="employeeForm.login_enabled" type="checkbox" class="border-primary/30 bg-black text-primary"> Login_Access
          </label>
          <input v-if="employeeForm.login_enabled" v-model="employeeForm.password" type="password" class="border-primary/30 bg-black text-primary" placeholder="Password">
          <button v-if="can('employees.create')" class="inline-flex w-fit items-center gap-2 bg-primary px-4 py-2 text-[10px] font-black uppercase text-black">
            <Save :size="14" /> Save
          </button>
        </form>
      </section>

      <section class="border border-primary/20 bg-black p-5">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-black uppercase text-white">
          <Building2 :size="18" /> HRM_Setup
        </h2>
        <form class="grid gap-3" @submit.prevent="submitSetup">
          <select v-model="setupForm.type" class="border-primary/30 bg-black text-primary">
            <option value="departments">Department</option>
            <option value="designations">Designation</option>
            <option value="teams">Team</option>
            <option value="office-locations">Office Location</option>
            <option value="shifts">Shift</option>
            <option value="leave-types">Leave Type</option>
          </select>
          <input v-model="setupForm.name" class="border-primary/30 bg-black text-primary" placeholder="Name" required>
          <input v-if="['departments','designations'].includes(setupForm.type)" v-model="setupForm.code" class="border-primary/30 bg-black text-primary" placeholder="Code">
          <select v-if="['designations','teams'].includes(setupForm.type)" v-model="setupForm.department_id" class="border-primary/30 bg-black text-primary">
            <option value="">Department</option>
            <option v-for="item in lookups.departments" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <input v-if="setupForm.type === 'office-locations'" v-model="setupForm.phone" class="border-primary/30 bg-black text-primary" placeholder="Phone">
          <textarea v-if="['departments','teams'].includes(setupForm.type)" v-model="setupForm.description" class="border-primary/30 bg-black text-primary" placeholder="Description"></textarea>
          <textarea v-if="setupForm.type === 'office-locations'" v-model="setupForm.address" class="border-primary/30 bg-black text-primary" placeholder="Address"></textarea>
          <div v-if="setupForm.type === 'shifts'" class="grid gap-3 md:grid-cols-3">
            <input v-model="setupForm.starts_at" type="time" class="border-primary/30 bg-black text-primary">
            <input v-model="setupForm.ends_at" type="time" class="border-primary/30 bg-black text-primary">
            <input v-model="setupForm.grace_minutes" type="number" class="border-primary/30 bg-black text-primary">
          </div>
          <div v-if="setupForm.type === 'leave-types'" class="grid gap-3 md:grid-cols-2">
            <input v-model="setupForm.days_per_year" type="number" class="border-primary/30 bg-black text-primary">
            <label class="flex items-center gap-2 text-[10px] uppercase text-primary/70">
              <input v-model="setupForm.is_paid" type="checkbox" class="border-primary/30 bg-black text-primary"> Paid
            </label>
          </div>
          <button v-if="can('hrm.create')" class="inline-flex w-fit items-center gap-2 bg-primary px-4 py-2 text-[10px] font-black uppercase text-black">
            <Save :size="14" /> Save_Setup
          </button>
        </form>
      </section>
    </div>

    <section class="border border-primary/20 bg-black p-5">
      <h2 class="mb-4 text-sm font-black uppercase text-white">Employees</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-[10px]">
          <thead class="uppercase text-primary/50">
            <tr>
              <th class="p-2">Employee</th>
              <th class="p-2">Department</th>
              <th class="p-2">Role</th>
              <th class="p-2">Status</th>
              <th class="p-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="employee in employees.data" :key="employee.id" class="border-t border-primary/10">
              <td class="p-2 text-white">{{ employee.employee_no }} / {{ employee.name }}</td>
              <td class="p-2">{{ employee.department?.name || '-' }}</td>
              <td class="p-2">{{ employee.user?.roles?.[0]?.name || '-' }}</td>
              <td class="p-2 uppercase">{{ employee.status }}</td>
              <td class="p-2 text-right">
                <button v-if="can('employees.delete')" class="text-red-400" @click="router.delete(route('dashboard.hrm.employees.archive', employee.id), { preserveScroll: true })">
                  <Trash2 :size="14" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
      <section class="border border-primary/20 bg-black p-5">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-black uppercase text-white"><CalendarCheck :size="18" /> Attendance_And_Leave</h2>
        <p v-for="item in leaves" :key="item.id" class="border-t border-primary/10 py-2 text-[10px]">
          {{ item.employee?.name }} / {{ item.leave_type?.name }} / {{ item.status }}
        </p>
      </section>
      <section class="border border-primary/20 bg-black p-5">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-black uppercase text-white"><DollarSign :size="18" /> Payroll_Base</h2>
        <p v-for="item in payrollRuns" :key="item.id" class="border-t border-primary/10 py-2 text-[10px]">
          {{ item.period }} / {{ item.status }} / {{ item.net_total }}
        </p>
        <p v-if="payrollRuns.length === 0" class="text-[10px] uppercase text-primary/50">No payroll runs yet.</p>
      </section>
    </div>
  </div>
</template>
