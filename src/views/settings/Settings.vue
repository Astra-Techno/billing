<script setup>
import { ref, onMounted } from 'vue'
import { task, all, item, list } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { useBusinessStore } from '../../stores/business'

const bizStore = useBusinessStore()

const saving   = ref(false)
const loading  = ref(true)
const success  = ref('')
const error    = ref('')
const activeTab = ref('business')
const states   = ref([])

const tabs = [
  { key: 'business',  label: 'My Business' },
  { key: 'gst',       label: 'GST Details' },
  { key: 'bank',      label: 'Payment Info' },
  { key: 'invoice',   label: 'Bill Settings' },
  { key: 'tax_rates', label: 'Tax Rates' },
  { key: 'password',  label: 'Password' },
]

const businessForm = ref({
  name: '', business_type: 'proprietorship', mobile: '', email: '', website: '',
  address_line1: '', address_line2: '', city: '', state_id: '', pincode: '',
})

const gstForm = ref({
  gstin: '', pan: '', cin: '',
})

const bankForm = ref({
  bank_name: '', bank_account_no: '', bank_ifsc: '', bank_account_name: '', upi_id: '',
})

const invoiceForm = ref({
  invoice_prefix: 'INV', quote_prefix: 'QTE', invoice_notes: '', invoice_terms: '',
})

// Tax Rates
const taxRates       = ref([])
const taxModal       = ref(false)
const taxSaving      = ref(false)
const taxError       = ref('')
const taxEditingId   = ref(null)
const taxDeleteTarget = ref(null)
const taxDeleting    = ref(false)
const blankTaxForm   = () => ({ name: '', rate: '', is_default: false })
const taxForm        = ref(blankTaxForm())

// Password
const pwForm = ref({ current_password: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)
const pwSuccess = ref('')
const pwError   = ref('')

// Logo
const currentLogo  = ref('')
const logoPreview  = ref('')
const logoUploading = ref(false)
const logoFileInput = ref(null)

function pickLogo() { logoFileInput.value?.click() }

function onLogoFile(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) { error.value = 'Logo must be under 2 MB.'; return }
  const reader = new FileReader()
  reader.onload = async (ev) => {
    logoPreview.value = ev.target.result
    logoUploading.value = true
    error.value = ''
    try {
      const res = await task('Business', 'uploadLogo', { logo: ev.target.result })
      currentLogo.value = res.data?.data?.logo || ev.target.result
      bizStore.setLogo(currentLogo.value)
      logoPreview.value = ''
      flash('Company logo saved.')
    } catch (err) {
      error.value = err.response?.data?.message || 'Logo upload failed.'
      logoPreview.value = ''
    }
    logoUploading.value = false
  }
  reader.readAsDataURL(file)
  e.target.value = ''
}

async function removeLogo() {
  logoUploading.value = true
  try {
    await task('Business', 'removeLogo', {})
    currentLogo.value = ''
    bizStore.setLogo('')
    flash('Logo removed.')
  } catch {}
  logoUploading.value = false
}

const businessTypes = [
  { value: 'proprietorship', label: 'Proprietorship / Individual' },
  { value: 'partnership',    label: 'Partnership Firm' },
  { value: 'llp',            label: 'LLP' },
  { value: 'private_ltd',    label: 'Private Limited' },
  { value: 'public_ltd',     label: 'Public Limited' },
  { value: 'trust',          label: 'Trust / NGO' },
  { value: 'society',        label: 'Society' },
  { value: 'other',          label: 'Other' },
]

onMounted(async () => {
  loading.value = true
  try {
    const [sRes, bizRes, trRes] = await Promise.all([all('IndianState'), item('Business'), list('TaxRate')])
    taxRates.value = trRes.data?.data || []
    states.value = sRes.data?.data || []

    const biz = bizRes.data?.data || {}
    businessForm.value.name          = biz.name          || ''
    businessForm.value.business_type = biz.business_type || 'proprietorship'
    businessForm.value.mobile        = biz.mobile        || ''
    businessForm.value.email        = biz.email        || ''
    businessForm.value.website      = biz.website      || ''
    businessForm.value.address_line1 = biz.address_line1 || ''
    businessForm.value.address_line2 = biz.address_line2 || ''
    businessForm.value.city         = biz.city         || ''
    businessForm.value.state_id     = biz.state_id     || ''
    businessForm.value.pincode      = biz.pincode      || ''

    gstForm.value.gstin = biz.gstin || ''
    gstForm.value.pan   = biz.pan   || ''
    gstForm.value.cin   = biz.cin   || ''

    bankForm.value.bank_name         = biz.bank_name         || ''
    bankForm.value.bank_account_no   = biz.bank_account_no   || ''
    bankForm.value.bank_ifsc         = biz.bank_ifsc         || ''
    bankForm.value.bank_account_name = biz.bank_account_name || ''
    bankForm.value.upi_id            = biz.upi_id            || ''

    invoiceForm.value.invoice_prefix = biz.invoice_prefix || 'INV'
    invoiceForm.value.quote_prefix   = biz.quote_prefix   || 'QTE'
    invoiceForm.value.invoice_notes  = biz.invoice_notes  || ''
    invoiceForm.value.invoice_terms  = biz.invoice_terms  || ''

    currentLogo.value = biz.logo || ''
    bizStore.setLogo(biz.logo || '')  // sync TopBar avatar
  } catch {}
  loading.value = false
})

function flash(msg) {
  success.value = msg
  error.value = ''
  setTimeout(() => { success.value = '' }, 3000)
}

async function saveBusiness() {
  saving.value = true
  error.value  = ''
  try {
    await task('Business', 'updateProfile', businessForm.value)
    flash('Business profile saved.')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save.'
  } finally { saving.value = false }
}

async function saveGst() {
  saving.value = true
  error.value  = ''
  try {
    await task('Business', 'updateGst', gstForm.value)
    flash('GST details saved.')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save.'
  } finally { saving.value = false }
}

async function saveBank() {
  saving.value = true
  error.value  = ''
  try {
    await task('Business', 'updateBank', bankForm.value)
    flash('Bank details saved.')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save.'
  } finally { saving.value = false }
}

async function loadTaxRates() {
  const res = await list('TaxRate')
  taxRates.value = res.data?.data || []
}

function openAddTax() {
  taxEditingId.value = null
  taxForm.value = blankTaxForm()
  taxError.value = ''
  taxModal.value = true
}

function openEditTax(t) {
  taxEditingId.value = t.id
  taxForm.value = { name: t.name, rate: t.rate, is_default: !!t.is_default }
  taxError.value = ''
  taxModal.value = true
}

async function saveTax() {
  taxError.value = ''
  if (!taxForm.value.name || !taxForm.value.rate) return (taxError.value = 'Name and rate are required.')
  taxSaving.value = true
  try {
    if (taxEditingId.value) {
      await task('TaxRate', 'update', { ...taxForm.value, id: taxEditingId.value })
    } else {
      await task('TaxRate', 'create', taxForm.value)
    }
    taxModal.value = false
    await loadTaxRates()
  } catch (e) {
    taxError.value = e.response?.data?.message || 'Failed to save tax rate.'
  } finally { taxSaving.value = false }
}

async function setDefaultTax(id) {
  try {
    await task('TaxRate', 'setDefault', { id })
    await loadTaxRates()
  } catch {}
}

async function confirmDeleteTax() {
  taxDeleting.value = true
  try {
    await task('TaxRate', 'delete', { id: taxDeleteTarget.value.id })
    taxDeleteTarget.value = null
    await loadTaxRates()
  } catch {
    taxDeleteTarget.value = null
  } finally { taxDeleting.value = false }
}

async function changePassword() {
  pwError.value = ''
  pwSuccess.value = ''
  if (!pwForm.value.current_password || !pwForm.value.password) return (pwError.value = 'All fields are required.')
  if (pwForm.value.password !== pwForm.value.password_confirmation) return (pwError.value = 'New passwords do not match.')
  pwSaving.value = true
  try {
    await task('User', 'changePassword', pwForm.value)
    pwSuccess.value = 'Password changed successfully.'
    pwForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (e) {
    pwError.value = e.response?.data?.message || 'Failed to change password.'
  } finally { pwSaving.value = false }
}

async function saveInvoice() {
  saving.value = true
  error.value  = ''
  try {
    await task('Business', 'updateProfile', {
      name:           businessForm.value.name,
      mobile:         businessForm.value.mobile,
      invoice_prefix: invoiceForm.value.invoice_prefix,
      quote_prefix:   invoiceForm.value.quote_prefix,
      invoice_notes:  invoiceForm.value.invoice_notes,
      invoice_terms:  invoiceForm.value.invoice_terms,
    })
    flash('Invoice settings saved.')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save.'
  } finally { saving.value = false }
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-5">
    <div>
      <h1 class="page-title flex items-center gap-2">Settings <HelpIcon section="settings" /></h1>
      <p class="text-sm text-gray-500 mt-0.5">Set up your business details, payment info, and bill preferences</p>
    </div>

    <!-- Tabs — horizontally scrollable on mobile -->
    <div class="flex gap-1 bg-gray-100 p-1 rounded-xl overflow-x-auto no-scrollbar">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="px-3 py-1.5 rounded-lg text-sm font-medium transition whitespace-nowrap shrink-0"
        :class="activeTab === t.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
        {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-gray-400 text-sm">Loading…</div>

    <!-- Success / Error banners -->
    <div v-if="success" class="text-sm text-success-700 bg-success-50 rounded-lg px-4 py-3 border border-success-200">{{ success }}</div>
    <div v-if="error"   class="text-sm text-danger-600 bg-danger-50 rounded-lg px-4 py-3">{{ error }}</div>

    <!-- Business Profile -->
    <template v-if="!loading && activeTab === 'business'">
      <!-- Company Logo -->
      <div class="card card-body">
        <h2 class="section-title mb-4">Company Logo</h2>
        <div class="flex items-center gap-5">
          <!-- Logo preview -->
          <div class="w-24 h-24 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden shrink-0">
            <img v-if="currentLogo || logoPreview" :src="logoPreview || currentLogo"
              class="w-full h-full object-contain p-1" alt="Company logo" />
            <svg v-else class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
          </div>
          <!-- Upload controls -->
          <div class="space-y-2">
            <p class="text-sm font-semibold text-gray-700">{{ currentLogo ? 'Change Logo' : 'Upload Logo' }}</p>
            <p class="text-xs text-gray-400">PNG, JPG or SVG · Max 2 MB · Shown on printed bills</p>
            <div class="flex gap-2">
              <button type="button" @click="pickLogo" :disabled="logoUploading"
                class="btn-primary btn-sm">
                <svg v-if="logoUploading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                {{ logoUploading ? 'Uploading…' : 'Upload' }}
              </button>
              <button v-if="currentLogo" type="button" @click="removeLogo" :disabled="logoUploading"
                class="btn-outline btn-sm text-red-500 border-red-200 hover:bg-red-50">Remove</button>
            </div>
          </div>
          <input ref="logoFileInput" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
            class="hidden" @change="onLogoFile" />
        </div>
      </div>

      <div class="card card-body space-y-4">
        <h2 class="section-title">Business Profile</h2>
        <div>
          <label class="form-label">Business Name *</label>
          <input v-model="businessForm.name" type="text" class="form-input" placeholder="Your Business Name" />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Business Type</label>
            <select v-model="businessForm.business_type" class="form-select">
              <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Mobile</label>
            <input v-model="businessForm.mobile" type="tel" class="form-input" placeholder="+91 98765 43210" />
          </div>
          <div>
            <label class="form-label">Email</label>
            <input v-model="businessForm.email" type="email" class="form-input" placeholder="you@business.com" />
          </div>
          <div>
            <label class="form-label">Website</label>
            <input v-model="businessForm.website" type="url" class="form-input" placeholder="https://yourbusiness.com" />
          </div>
        </div>
        <h3 class="text-sm font-semibold text-gray-700 pt-2">Business Address <span class="text-gray-400 font-normal">(printed on bills)</span></h3>
        <div>
          <label class="form-label">Street / Shop Number</label>
          <input v-model="businessForm.address_line1" type="text" class="form-input" placeholder="e.g. Shop 12, Main Market" />
        </div>
        <div>
          <label class="form-label">Area / Locality</label>
          <input v-model="businessForm.address_line2" type="text" class="form-input" placeholder="e.g. Gandhi Road, Near Bus Stand" />
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="form-label">City</label>
            <input v-model="businessForm.city" type="text" class="form-input" placeholder="City" />
          </div>
          <div>
            <label class="form-label">State</label>
            <select v-model="businessForm.state_id" class="form-select">
              <option value="">Select State</option>
              <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Pincode</label>
            <input v-model="businessForm.pincode" type="text" class="form-input" placeholder="600001" maxlength="6" />
          </div>
        </div>
        <div class="pt-2">
          <button @click="saveBusiness" :disabled="saving" class="btn-primary w-full sm:w-auto">
            {{ saving ? 'Saving…' : 'Save Business Profile' }}
          </button>
        </div>
      </div>
    </template>

    <!-- GST & Tax -->
    <template v-if="!loading && activeTab === 'gst'">
      <div class="card card-body space-y-4">
        <div>
          <h2 class="section-title mb-0">GST Registration Details</h2>
          <p class="text-xs text-gray-400 mt-0.5">This appears on all your bills for GST compliance</p>
        </div>
        <div>
          <label class="form-label">Your GST Number (GSTIN)</label>
          <input v-model="gstForm.gstin" type="text" class="form-input font-mono uppercase" placeholder="e.g. 27AABCU9603R1Z6" maxlength="15" style="text-transform:uppercase" />
          <p class="text-xs text-gray-400 mt-1">15-character number from your GST registration certificate</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">PAN Number</label>
            <input v-model="gstForm.pan" type="text" class="form-input font-mono uppercase" placeholder="e.g. AABCU9603R" maxlength="10" style="text-transform:uppercase" />
          </div>
          <div>
            <label class="form-label">CIN <span class="text-gray-400 font-normal">(companies only)</span></label>
            <input v-model="gstForm.cin" type="text" class="form-input" placeholder="U74999MH2022PTC000000" />
          </div>
        </div>
        <div class="pt-2">
          <button @click="saveGst" :disabled="saving" class="btn-primary w-full sm:w-auto">
            {{ saving ? 'Saving…' : 'Save GST Details' }}
          </button>
        </div>
      </div>
    </template>

    <!-- Bank & UPI -->
    <template v-if="!loading && activeTab === 'bank'">
      <div class="card card-body space-y-4">
        <h2 class="section-title">Bank Account</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Bank Name</label>
            <input v-model="bankForm.bank_name" type="text" class="form-input" placeholder="State Bank of India" />
          </div>
          <div>
            <label class="form-label">Account Number</label>
            <input v-model="bankForm.bank_account_no" type="text" class="form-input" placeholder="0000000000000" />
          </div>
          <div>
            <label class="form-label">IFSC Code</label>
            <input v-model="bankForm.bank_ifsc" type="text" class="form-input" placeholder="SBIN0001234" maxlength="11" style="text-transform:uppercase" />
          </div>
          <div>
            <label class="form-label">Account Holder Name</label>
            <input v-model="bankForm.bank_account_name" type="text" class="form-input" placeholder="Name as per bank records" />
          </div>
        </div>
        <h2 class="section-title pt-2">UPI</h2>
        <div>
          <label class="form-label">UPI ID</label>
          <input v-model="bankForm.upi_id" type="text" class="form-input" placeholder="yourname@upi" />
          <p class="text-xs text-gray-400 mt-1">Shown on bills so customers can pay you directly via UPI</p>
        </div>
        <div class="pt-2">
          <button @click="saveBank" :disabled="saving" class="btn-primary w-full sm:w-auto">
            {{ saving ? 'Saving…' : 'Save Bank Details' }}
          </button>
        </div>
      </div>
    </template>

    <!-- Invoice Settings -->
    <template v-if="!loading && activeTab === 'invoice'">
      <div class="card card-body space-y-4">
        <div>
          <h2 class="section-title mb-0">Bill Numbering</h2>
          <p class="text-xs text-gray-400 mt-0.5">The short code that appears at the start of every bill or quotation number</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Bill Number Prefix</label>
            <input v-model="invoiceForm.invoice_prefix" type="text" class="form-input" placeholder="INV" />
            <p class="text-xs text-gray-400 mt-1">e.g. INV → INV/2024-25/0001</p>
          </div>
          <div>
            <label class="form-label">Quotation Number Prefix</label>
            <input v-model="invoiceForm.quote_prefix" type="text" class="form-input" placeholder="QTE" />
            <p class="text-xs text-gray-400 mt-1">e.g. QTE → QTE/2024-25/0001</p>
          </div>
        </div>
        <div>
          <h2 class="section-title mb-0 pt-2">Default Message on Bills</h2>
          <p class="text-xs text-gray-400 mt-0.5">These are pre-filled every time you create a new bill</p>
        </div>
        <div>
          <label class="form-label">Message to Customer <span class="text-gray-400 font-normal">(printed at bottom)</span></label>
          <textarea v-model="invoiceForm.invoice_notes" rows="3" class="form-input" placeholder="e.g. Thank you for your business!"></textarea>
        </div>
        <div>
          <label class="form-label">Terms & Conditions</label>
          <textarea v-model="invoiceForm.invoice_terms" rows="3" class="form-input" placeholder="e.g. Payment due within 30 days."></textarea>
        </div>
        <div class="pt-2">
          <button @click="saveInvoice" :disabled="saving" class="btn-primary w-full sm:w-auto">
            {{ saving ? 'Saving…' : 'Save Bill Settings' }}
          </button>
        </div>
      </div>
    </template>

    <!-- Tax Rates -->
    <template v-if="!loading && activeTab === 'tax_rates'">
      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="section-title mb-0">Tax Rates</h2>
          <button @click="openAddTax" class="btn-primary btn-sm">+ Add Rate</button>
        </div>
        <div v-if="!taxRates.length" class="p-8 text-center text-gray-400 text-sm">No tax rates defined</div>
        <div v-else class="divide-y divide-gray-100">
          <div v-for="t in taxRates" :key="t.id" class="flex items-center justify-between px-5 py-3">
            <div>
              <p class="font-medium text-gray-800">{{ t.name }}</p>
              <p class="text-xs text-gray-400">{{ t.rate }}%</p>
            </div>
            <div class="flex items-center gap-3">
              <button v-if="!t.is_default" @click="setDefaultTax(t.id)"
                class="text-xs text-primary-600 hover:underline">Set Default</button>
              <span v-else class="text-xs text-success-600 font-medium">Default</span>
              <button @click="openEditTax(t)" class="text-gray-400 hover:text-primary-600 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <button @click="taxDeleteTarget = t" class="text-gray-400 hover:text-danger-600 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tax Rate modal -->
      <div v-if="taxModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">{{ taxEditingId ? 'Edit Tax Rate' : 'Add Tax Rate' }}</h3>
            <button @click="taxModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <label class="form-label">Name *</label>
              <input v-model="taxForm.name" type="text" class="form-input" placeholder="e.g. GST 18%" />
            </div>
            <div>
              <label class="form-label">Rate (%) *</label>
              <input v-model="taxForm.rate" type="number" min="0" max="100" step="0.01" class="form-input" placeholder="18" />
            </div>
            <div class="flex items-center gap-3">
              <button type="button" @click="taxForm.is_default = !taxForm.is_default"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                :class="taxForm.is_default ? 'bg-primary-600' : 'bg-gray-200'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                  :class="taxForm.is_default ? 'translate-x-6' : 'translate-x-1'"></span>
              </button>
              <span class="text-sm text-gray-600">Set as default</span>
            </div>
            <div v-if="taxError" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-3 py-2">{{ taxError }}</div>
          </div>
          <div class="px-5 pb-5 flex gap-3">
            <button @click="taxModal = false" class="btn-outline flex-1">Cancel</button>
            <button @click="saveTax" :disabled="taxSaving" class="btn-primary flex-1">
              {{ taxSaving ? 'Saving…' : taxEditingId ? 'Update' : 'Add Rate' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Tax delete confirm -->
      <div v-if="taxDeleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl p-6 space-y-4">
          <h3 class="font-semibold text-gray-900">Delete Tax Rate?</h3>
          <p class="text-sm text-gray-600">Delete <strong>{{ taxDeleteTarget.name }}</strong>?</p>
          <div class="flex gap-3">
            <button @click="taxDeleteTarget = null" class="btn-outline flex-1" :disabled="taxDeleting">Cancel</button>
            <button @click="confirmDeleteTax" :disabled="taxDeleting" class="btn-primary flex-1 bg-danger-600 hover:bg-danger-700 border-danger-600">
              {{ taxDeleting ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Password -->
    <template v-if="!loading && activeTab === 'password'">
      <div class="card card-body space-y-4">
        <div>
          <h2 class="section-title mb-0">Change Your Password</h2>
          <p class="text-xs text-gray-400 mt-0.5">Keep your account secure by using a strong password</p>
        </div>
        <div>
          <label class="form-label">Your Current Password</label>
          <input v-model="pwForm.current_password" type="password" class="form-input" placeholder="Enter your current password" />
        </div>
        <div>
          <label class="form-label">New Password</label>
          <input v-model="pwForm.password" type="password" class="form-input" placeholder="At least 8 characters" />
        </div>
        <div>
          <label class="form-label">Confirm New Password</label>
          <input v-model="pwForm.password_confirmation" type="password" class="form-input" placeholder="Type the new password again" />
        </div>
        <div v-if="pwSuccess" class="text-sm text-success-700 bg-success-50 rounded-lg px-4 py-3 border border-success-200">{{ pwSuccess }}</div>
        <div v-if="pwError"   class="text-sm text-danger-600 bg-danger-50 rounded-lg px-4 py-3">{{ pwError }}</div>
        <div class="pt-2">
          <button @click="changePassword" :disabled="pwSaving" class="btn-primary w-full sm:w-auto">
            {{ pwSaving ? 'Changing…' : 'Update Password' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
