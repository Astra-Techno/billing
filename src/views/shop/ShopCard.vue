<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'

const route   = useRoute()
const biz     = ref(null)
const loading = ref(true)
const notFound = ref(false)
const qrUrl   = ref('')
const copied  = ref(false)

const cardUrl = computed(() => window.location.href)
const apiBase = import.meta.env.VITE_API_URL || 'http://localhost/billing/api'

onMounted(async () => {
  try {
    const res  = await fetch(`${apiBase}/shop/${route.params.slug}`)
    const json = await res.json()
    if (json.success) {
      biz.value = json.data
      qrUrl.value = await QRCode.toDataURL(cardUrl.value, {
        width: 180, margin: 2,
        color: { dark: '#0f172a', light: '#ffffff' },
      })
    } else {
      notFound.value = true
    }
  } catch {
    notFound.value = true
  }
  loading.value = false
})

// ── Actions ──────────────────────────────────────────────────────────────────

function downloadVCard() {
  const b = biz.value
  const addr = [b.address_line1, b.address_line2, b.city, b.state_name, b.pincode, 'India']
    .filter(Boolean)
  const note = [b.upi_id ? `UPI: ${b.upi_id}` : '', b.gstin ? `GSTIN: ${b.gstin}` : '']
    .filter(Boolean).join(' | ')

  const lines = [
    'BEGIN:VCARD',
    'VERSION:3.0',
    `FN:${b.name}`,
    `ORG:${b.name}`,
    b.mobile  ? `TEL;TYPE=CELL,VOICE:${b.mobile}` : null,
    b.phone   ? `TEL;TYPE=WORK,VOICE:${b.phone}`  : null,
    b.email   ? `EMAIL;TYPE=WORK:${b.email}`       : null,
    b.website ? `URL:${b.website}`                 : null,
    `URL;TYPE=pref:${cardUrl.value}`,
    addr.length ? `ADR;TYPE=WORK:;;${addr.slice(0, 2).join(', ')};${b.city || ''};${b.state_name || ''};${b.pincode || ''};India` : null,
    note ? `NOTE:${note}` : null,
    'END:VCARD',
  ].filter(Boolean).join('\r\n')

  const blob = new Blob([lines], { type: 'text/vcard;charset=utf-8' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href = url; a.download = `${b.slug}.vcf`; a.click()
  URL.revokeObjectURL(url)
}

function shareWhatsApp() {
  const msg = `Hi! I'm ${biz.value.name}. Here's my digital business card:\n${cardUrl.value}`
  window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank')
}

async function copyLink() {
  await navigator.clipboard.writeText(cardUrl.value)
  copied.value = true
  setTimeout(() => copied.value = false, 2000)
}

const mapsUrl = computed(() => {
  if (!biz.value) return ''
  const q = [biz.value.name, biz.value.address_line1, biz.value.city, biz.value.state_name]
    .filter(Boolean).join(', ')
  return `https://maps.google.com/?q=${encodeURIComponent(q)}`
})

const upiUrl = computed(() => {
  if (!biz.value?.upi_id) return ''
  return `upi://pay?pa=${encodeURIComponent(biz.value.upi_id)}&pn=${encodeURIComponent(biz.value.name)}`
})

const avatarLetter = computed(() => biz.value?.name?.charAt(0)?.toUpperCase() || '?')

const fullAddress = computed(() => {
  if (!biz.value) return ''
  return [biz.value.address_line1, biz.value.address_line2, biz.value.city, biz.value.state_name, biz.value.pincode]
    .filter(Boolean).join(', ')
})
</script>

<template>
  <!-- Loading -->
  <div v-if="loading" class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="flex flex-col items-center gap-3">
      <div class="w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      <p class="text-sm text-gray-500 font-medium">Loading business card…</p>
    </div>
  </div>

  <!-- Not found -->
  <div v-else-if="notFound" class="min-h-screen flex items-center justify-center bg-gray-50 px-6">
    <div class="text-center max-w-sm">
      <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">Business not found</h2>
      <p class="text-gray-500 text-sm">This link may be incorrect or the business account is inactive.</p>
    </div>
  </div>

  <!-- Card -->
  <div v-else-if="biz" class="min-h-screen bg-gradient-to-br from-slate-100 to-blue-50 flex items-start justify-center py-6 px-4">
    <div class="w-full max-w-sm">

      <!-- Hero Card -->
      <div class="relative rounded-[2rem] overflow-hidden shadow-2xl mb-4">
        <!-- Background gradient -->
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-slate-800 to-gray-900"></div>
        <div class="absolute top-0 right-0 w-48 h-48 bg-primary-500/20 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-indigo-500/15 rounded-full blur-3xl -ml-16 -mb-16 pointer-events-none"></div>

        <div class="relative z-10 px-7 pt-10 pb-8 flex flex-col items-center text-center">
          <!-- Logo or Avatar -->
          <div class="mb-5">
            <img v-if="biz.logo" :src="biz.logo" :alt="biz.name"
              class="w-24 h-24 rounded-2xl object-cover shadow-lg border-4 border-white/10" />
            <div v-else
              class="w-24 h-24 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg border-4 border-white/10">
              <span class="text-white text-4xl font-black">{{ avatarLetter }}</span>
            </div>
          </div>

          <!-- Name & type -->
          <h1 class="text-2xl font-black text-white tracking-tight leading-tight">{{ biz.name }}</h1>
          <p v-if="biz.business_type" class="text-sm text-slate-400 mt-1 capitalize">{{ biz.business_type.replace('_', ' ') }}</p>

          <!-- GSTIN badge -->
          <div v-if="biz.gstin" class="mt-3 px-3 py-1 bg-white/10 rounded-full text-xs text-slate-300 font-mono tracking-wider">
            GST: {{ biz.gstin }}
          </div>

          <!-- Address -->
          <p v-if="fullAddress" class="mt-4 text-xs text-slate-400 leading-relaxed max-w-[220px]">
            {{ fullAddress }}
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="bg-white rounded-[2rem] shadow-xl p-5 mb-4">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4 text-center">Quick Actions</p>

        <!-- Primary: Save Contact -->
        <button @click="downloadVCard"
          class="w-full flex items-center justify-center gap-3 py-4 bg-primary-600 hover:bg-primary-700 active:scale-95 text-white font-bold rounded-2xl transition-all shadow-lg shadow-primary-200 mb-3 text-sm">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
          Save Contact (.vcf)
        </button>

        <!-- 2x2 grid actions -->
        <div class="grid grid-cols-2 gap-3">
          <!-- Call -->
          <a v-if="biz.mobile || biz.phone" :href="`tel:${biz.mobile || biz.phone}`"
            class="flex flex-col items-center gap-2 py-4 bg-emerald-50 hover:bg-emerald-100 active:scale-95 rounded-2xl transition-all">
            <div class="w-11 h-11 bg-emerald-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-200">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
              </svg>
            </div>
            <span class="text-xs font-bold text-emerald-800">Call</span>
          </a>

          <!-- WhatsApp -->
          <a v-if="biz.mobile" :href="`https://wa.me/${biz.mobile.replace(/\D/g,'')}`" target="_blank"
            class="flex flex-col items-center gap-2 py-4 bg-green-50 hover:bg-green-100 active:scale-95 rounded-2xl transition-all">
            <div class="w-11 h-11 bg-[#25D366] rounded-xl flex items-center justify-center shadow-md shadow-green-200">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
              </svg>
            </div>
            <span class="text-xs font-bold text-green-800">WhatsApp</span>
          </a>

          <!-- Google Maps -->
          <a v-if="fullAddress" :href="mapsUrl" target="_blank"
            class="flex flex-col items-center gap-2 py-4 bg-blue-50 hover:bg-blue-100 active:scale-95 rounded-2xl transition-all">
            <div class="w-11 h-11 bg-blue-500 rounded-xl flex items-center justify-center shadow-md shadow-blue-200">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <span class="text-xs font-bold text-blue-800">Maps</span>
          </a>

          <!-- UPI Pay -->
          <a v-if="biz.upi_id" :href="upiUrl"
            class="flex flex-col items-center gap-2 py-4 bg-violet-50 hover:bg-violet-100 active:scale-95 rounded-2xl transition-all">
            <div class="w-11 h-11 bg-violet-500 rounded-xl flex items-center justify-center shadow-md shadow-violet-200">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
            </div>
            <span class="text-xs font-bold text-violet-800">Pay UPI</span>
          </a>

          <!-- Email -->
          <a v-if="biz.email" :href="`mailto:${biz.email}`"
            class="flex flex-col items-center gap-2 py-4 bg-sky-50 hover:bg-sky-100 active:scale-95 rounded-2xl transition-all">
            <div class="w-11 h-11 bg-sky-500 rounded-xl flex items-center justify-center shadow-md shadow-sky-200">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </div>
            <span class="text-xs font-bold text-sky-800">Email</span>
          </a>

          <!-- Website -->
          <a v-if="biz.website" :href="biz.website" target="_blank"
            class="flex flex-col items-center gap-2 py-4 bg-amber-50 hover:bg-amber-100 active:scale-95 rounded-2xl transition-all">
            <div class="w-11 h-11 bg-amber-500 rounded-xl flex items-center justify-center shadow-md shadow-amber-200">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
              </svg>
            </div>
            <span class="text-xs font-bold text-amber-800">Website</span>
          </a>
        </div>
      </div>

      <!-- QR Code + Share -->
      <div class="bg-white rounded-[2rem] shadow-xl p-6 mb-4">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4 text-center">Share This Card</p>

        <div class="flex flex-col items-center gap-4">
          <!-- QR -->
          <div v-if="qrUrl" class="p-3 bg-white border-2 border-gray-100 rounded-2xl shadow-inner">
            <img :src="qrUrl" alt="QR Code" class="w-44 h-44" />
          </div>
          <p class="text-xs text-gray-500 text-center">Scan to open this card</p>

          <!-- Share buttons -->
          <div class="flex gap-3 w-full">
            <button @click="shareWhatsApp"
              class="flex-1 flex items-center justify-center gap-2 py-3 bg-[#25D366] hover:bg-[#1db954] active:scale-95 text-white font-bold rounded-2xl transition-all text-sm shadow-md shadow-green-200">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
              </svg>
              Share
            </button>
            <button @click="copyLink"
              class="flex-1 flex items-center justify-center gap-2 py-3 bg-gray-100 hover:bg-gray-200 active:scale-95 text-gray-800 font-bold rounded-2xl transition-all text-sm">
              <svg v-if="!copied" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              <svg v-else class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              {{ copied ? 'Copied!' : 'Copy Link' }}
            </button>
          </div>
        </div>
      </div>

      <!-- UPI ID display -->
      <div v-if="biz.upi_id" class="bg-white rounded-[2rem] shadow-xl p-5 mb-4">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 text-center">Payment</p>
        <div class="flex items-center gap-3 bg-violet-50 rounded-2xl px-4 py-3">
          <div class="w-9 h-9 bg-violet-100 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[10px] text-violet-500 font-bold uppercase tracking-wide">UPI ID</p>
            <p class="text-sm font-bold text-violet-900 truncate">{{ biz.upi_id }}</p>
          </div>
          <a :href="upiUrl" class="px-3 py-1.5 bg-violet-600 text-white text-xs font-bold rounded-xl active:scale-95 transition-all">Pay</a>
        </div>
      </div>

      <!-- Footer -->
      <div class="text-center py-4 opacity-60">
        <p class="text-xs text-gray-500">Powered by <strong class="text-gray-700">CloudBill</strong></p>
      </div>

    </div>
  </div>
</template>
