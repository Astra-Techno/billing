<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const activeSection = ref('getting-started')

function goToHash(hash) {
  const id = hash?.replace('#', '')
  if (!id) return
  activeSection.value = id
  const el = document.getElementById(id)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(() => { if (route.hash) setTimeout(() => goToHash(route.hash), 100) })
watch(() => route.hash, (h) => goToHash(h))

const sections = [
  { id: 'getting-started', label: 'Getting Started',  icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
  { id: 'dashboard',       label: 'Dashboard',         icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { id: 'bills',           label: 'Bills / Invoices',  icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { id: 'quotes',          label: 'Quotations',        icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { id: 'customers',       label: 'Customers',         icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { id: 'expenses',        label: 'Expenses',          icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { id: 'products',        label: 'Products',          icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { id: 'returns',         label: 'Returns & Adjustments', icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
  { id: 'purchase-orders', label: 'Purchase Orders',   icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
  { id: 'delivery-challans', label: 'Delivery Challans', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { id: 'gst',             label: 'GST Filing',        icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { id: 'reports',         label: 'Reports',           icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { id: 'settings',        label: 'Settings',          icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
]

function scrollTo(id) {
  activeSection.value = id
  const el = document.getElementById(id)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <div class="max-w-6xl mx-auto pb-24 px-4 sm:px-6">

    <!-- Premium Hero Section -->
    <div class="relative bg-gradient-to-br from-primary-600 to-indigo-800 rounded-[2.5rem] p-8 sm:p-12 text-white mb-10 overflow-hidden shadow-soft-blue mt-4">
      <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute bottom-0 left-10 -mb-10 w-48 h-48 bg-primary-400 opacity-20 rounded-full blur-2xl pointer-events-none"></div>
      
      <div class="relative z-10 max-w-2xl">
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-3 text-white">How can we help?</h1>
        <p class="text-primary-100 text-base sm:text-lg">Explore our guides and find answers to all your questions about BillBook India.</p>
        
        <!-- Search bar (Visual for aesthetics) -->
        <div class="mt-8 relative max-w-md animate-fade-in-up">
          <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" placeholder="Search guides, invoices, GST..." class="w-full bg-white text-gray-900 border-0 rounded-2xl py-4 pl-12 pr-4 shadow-xl focus:ring-2 focus:ring-primary-300 placeholder-gray-400 text-sm font-medium transition-all" />
        </div>
      </div>
    </div>

    <div class="lg:flex lg:gap-8">

      <!-- Sidebar TOC (desktop) -->
      <div class="hidden lg:block lg:w-64 shrink-0">
        <div class="card p-4 sticky top-6 bg-white/80 backdrop-blur-xl border border-gray-100 shadow-soft">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-3">Contents</p>
          <nav class="space-y-1">
            <button v-for="s in sections" :key="s.id" @click="scrollTo(s.id)"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-sm transition-all font-medium"
              :class="activeSection === s.id ? 'bg-primary-50 text-primary-700 shadow-sm' : 'text-gray-600 hover:bg-gray-50'">
              <div class="p-1.5 rounded-lg transition-colors" :class="activeSection === s.id ? 'bg-primary-100 text-primary-600' : 'bg-gray-100 text-gray-400'">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="s.icon" />
                </svg>
              </div>
              <span>{{ s.label }}</span>
            </button>
          </nav>
        </div>
      </div>

      <!-- Mobile TOC chips -->
      <div class="lg:hidden flex gap-2 overflow-x-auto no-scrollbar pb-4 mb-4 border-b border-gray-100">
        <button v-for="s in sections" :key="s.id" @click="scrollTo(s.id)"
          class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap shrink-0 transition-all shadow-sm"
          :class="activeSection === s.id ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200 text-gray-600'">
          {{ s.label }}
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 space-y-8">

        <!-- ── Getting Started ── -->
        <section :id="'getting-started'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-amber-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-amber-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Getting Started</h2>
                <p class="text-sm text-gray-500 font-medium">Set up your account in minutes</p>
              </div>
            </div>

            <div class="space-y-6 text-sm text-gray-700">
              <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100/50 rounded-2xl p-5 shadow-sm">
                <p class="font-bold text-blue-900 mb-1.5 text-base">Welcome to BillBook India! 🎉</p>
                <p class="text-blue-800/80 text-sm leading-relaxed">A simple, premium billing and accounting tool designed specifically for Indian small businesses. Create GST-compliant invoices, manage quotations, and track expenses effortlessly.</p>
              </div>

              <div class="grid gap-6 pl-4 border-l-2 border-gray-100">
                <div class="relative">
                  <div class="absolute -left-[1.35rem] top-1 w-3 h-3 rounded-full bg-primary-400 ring-4 ring-white"></div>
                  <p class="font-bold text-gray-900 mb-1">Step 1 — Complete your Business Profile</p>
                  <p class="text-gray-500 leading-relaxed">Go to <strong class="text-gray-700">Settings</strong> and fill in your business details: name, address, GSTIN, PAN, bank account, logo, and signature. This establishes your brand identity on every invoice.</p>
                </div>
                <div class="relative">
                  <div class="absolute -left-[1.35rem] top-1 w-3 h-3 rounded-full bg-primary-400 ring-4 ring-white"></div>
                  <p class="font-bold text-gray-900 mb-1">Step 2 — Add your Catalog</p>
                  <p class="text-gray-500 leading-relaxed">Go to <strong class="text-gray-700">Products</strong> and add your items. Set the rate, HSN/SAC code, and GST rate. When billing, just type the name and it auto-fills everything instantly.</p>
                </div>
                <div class="relative">
                  <div class="absolute -left-[1.35rem] top-1 w-3 h-3 rounded-full bg-primary-400 ring-4 ring-white"></div>
                  <p class="font-bold text-gray-900 mb-1">Step 3 — Create your First Bill</p>
                  <p class="text-gray-500 leading-relaxed">Tap the <strong class="text-gray-700">+ New Bill</strong> button. Select your customer, add items, and hit Save. Your beautiful GST invoice is ready to be shared!</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Dashboard ── -->
        <section :id="'dashboard'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-blue-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Dashboard</h2>
                <p class="text-sm text-gray-500 font-medium">Your business command center</p>
              </div>
            </div>

            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed text-base">The Dashboard provides a real-time, high-level summary of your financial health.</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 hover:shadow-md transition-shadow">
                  <p class="font-bold text-gray-900 mb-1">Financial Metrics</p>
                  <p class="text-gray-500 leading-relaxed">Tracks total revenue, pending receivables, total expenses, and net profit for the month.</p>
                </div>
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 hover:shadow-md transition-shadow">
                  <p class="font-bold text-gray-900 mb-1">Growth Trend</p>
                  <p class="text-gray-500 leading-relaxed">Visual bar chart of your revenue over the last 6 months to spot momentum.</p>
                </div>
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 hover:shadow-md transition-shadow">
                  <p class="font-bold text-gray-900 mb-1">Actionable Reminders</p>
                  <p class="text-gray-500 leading-relaxed">Instantly see who owes you money and send WhatsApp payment reminders with one click.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Bills / Invoices ── -->
        <section :id="'bills'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-primary-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-primary-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Bills & Invoices</h2>
                <p class="text-sm text-gray-500 font-medium">GST & non-GST invoices, share, track payments</p>
              </div>
            </div>

            <div class="space-y-6 text-sm text-gray-700">

              <!-- Invoice Types -->
              <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                <p class="font-bold text-gray-900 mb-3">Invoice Types</p>
                <div class="space-y-2 text-sm text-gray-600">
                  <div class="flex items-start gap-2"><span class="w-28 shrink-0 font-semibold text-gray-800">Tax Invoice</span><span>Standard GST invoice with CGST/SGST or IGST breakdown. Used when you and your customer are both GST-registered.</span></div>
                  <div class="flex items-start gap-2"><span class="w-28 shrink-0 font-semibold text-amber-700">Bill of Supply</span><span>No GST charged — for exempt goods/services or unregistered sellers. All GST rates auto-set to 0% when this type is selected.</span></div>
                  <div class="flex items-start gap-2"><span class="w-28 shrink-0 font-semibold text-gray-800">Retail Invoice</span><span>Simplified invoice for cash/retail sales without GST column breakdown.</span></div>
                  <div class="flex items-start gap-2"><span class="w-28 shrink-0 font-semibold text-gray-800">Export Invoice</span><span>For goods/services exported outside India (zero-rated supply).</span></div>
                </div>
              </div>

              <!-- Lifecycle -->
              <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                <p class="font-bold text-gray-900 mb-3">Invoice Lifecycle</p>
                <div class="flex flex-wrap gap-2 text-xs">
                  <div class="flex items-center gap-2"><span class="badge badge-gray">Draft</span><span class="text-gray-500">Saved, not sent</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-blue">Sent</span><span class="text-gray-500">Shared with customer</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-yellow">Overdue</span><span class="text-gray-500">Past due date</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-green">Paid</span><span class="text-gray-500">Fully settled</span></div>
                </div>
              </div>

              <!-- Actions -->
              <div>
                <p class="font-bold text-gray-900 mb-3">One-Click Actions</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm text-gray-600">
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-primary-200 transition-colors">
                    <span class="text-2xl mb-1">🖨️</span>
                    <span class="font-semibold text-gray-800">Print / PDF</span>
                    <span class="text-xs text-gray-400 mt-0.5">Save or print your invoice</span>
                  </div>
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-green-200 transition-colors">
                    <span class="text-2xl mb-1">📲</span>
                    <span class="font-semibold text-gray-800">WhatsApp</span>
                    <span class="text-xs text-gray-400 mt-0.5">Message with amount + UPI ID</span>
                  </div>
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-sky-200 transition-colors">
                    <span class="text-2xl mb-1">✉️</span>
                    <span class="font-semibold text-gray-800">Email</span>
                    <span class="text-xs text-gray-400 mt-0.5">Pre-filled email with details</span>
                  </div>
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-emerald-200 transition-colors">
                    <span class="text-2xl mb-1">💳</span>
                    <span class="font-semibold text-gray-800">Record Payment</span>
                    <span class="text-xs text-gray-400 mt-0.5">Partial or full payment</span>
                  </div>
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-amber-200 transition-colors">
                    <span class="text-2xl mb-1">✏️</span>
                    <span class="font-semibold text-gray-800">Edit Anytime</span>
                    <span class="text-xs text-gray-400 mt-0.5">Correct any non-cancelled bill</span>
                  </div>
                </div>
              </div>

              <!-- Partial Payments -->
              <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 shadow-sm">
                <p class="font-bold text-emerald-900 mb-1.5">Partial Payments & Balance Tracking</p>
                <p class="text-sm text-emerald-800 leading-relaxed">Tap <strong>Record Payment</strong> on any sent invoice to log cash, UPI, NEFT, or cheque receipts. You can record multiple payments over time — BillBook automatically tracks the running balance due and marks the invoice as Paid when fully settled.</p>
              </div>

              <!-- Auto GST -->
              <div class="bg-green-50 border border-green-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-green-800 leading-relaxed"><strong>Auto GST split:</strong> Same state → CGST + SGST. Different state → IGST. Bill of Supply → no GST. BillBook handles all cases automatically based on your bill type and customer's state.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Purchase Orders ── -->
        <section :id="'purchase-orders'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-violet-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-violet-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Purchase Orders</h2>
                <p class="text-sm text-gray-500 font-medium">Manage orders placed with your suppliers</p>
              </div>
            </div>

            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">A Purchase Order (PO) is a document you send to a supplier confirming what you want to buy, at what price, and when. It is not a payment — it's a formal request.</p>

              <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                <p class="font-bold text-gray-900 mb-3">PO Lifecycle</p>
                <div class="flex flex-wrap gap-2 text-xs">
                  <div class="flex items-center gap-2"><span class="badge badge-gray">Draft</span><span class="text-gray-500">Being prepared</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-blue">Sent</span><span class="text-gray-500">Shared with supplier</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-green">Received</span><span class="text-gray-500">Goods/services received</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-red">Cancelled</span><span class="text-gray-500">PO voided</span></div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Supplier = Customer record</p>
                  <p class="text-gray-500">Suppliers are stored in your Customers list. Add a supplier the same way you add a customer.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Items with GST rates</p>
                  <p class="text-gray-500">Each PO line includes unit price and GST rate for accurate subtotal and tax calculations.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Delivery Challans ── -->
        <section :id="'delivery-challans'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-cyan-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-cyan-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Delivery Challans</h2>
                <p class="text-sm text-gray-500 font-medium">Track goods dispatched to customers</p>
              </div>
            </div>

            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">A Delivery Challan (DC) is a transport document that lists the goods being delivered. It does <strong>not</strong> include prices or GST — it is purely for logistics and proof of dispatch.</p>

              <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                <p class="font-bold text-gray-900 mb-3">DC Lifecycle</p>
                <div class="flex flex-wrap gap-2 text-xs">
                  <div class="flex items-center gap-2"><span class="badge badge-gray">Draft</span><span class="text-gray-500">Being prepared</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-blue">Issued</span><span class="text-gray-500">Goods dispatched</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-green">Delivered</span><span class="text-gray-500">Customer received goods</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-red">Cancelled</span><span class="text-gray-500">Delivery cancelled</span></div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Transport Details</p>
                  <p class="text-gray-500">Record vehicle number, driver name, and destination for each challan.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">No Prices, Just Qty</p>
                  <p class="text-gray-500">DC items show only description, HSN/SAC, quantity, and unit — no financial data.</p>
                </div>
              </div>

              <div class="bg-cyan-50 border border-cyan-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-cyan-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-cyan-900 leading-relaxed"><strong>Tip:</strong> Always create a Tax Invoice alongside or after a Delivery Challan. The DC is a transport slip — it is not a valid tax document for GST purposes.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Expenses ── -->
        <section :id="'expenses'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-orange-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-orange-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Expenses & Purchases</h2>
                <p class="text-sm text-gray-500 font-medium">Track outflows and claim ITC</p>
              </div>
            </div>

            <div class="space-y-4 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed text-base">Tracking expenses is vital for knowing your true net profit and claiming GST Input Tax Credit (ITC) correctly.</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Categorization</p>
                  <p class="text-gray-500">Group expenses (Travel, Rent, Inventory) to generate powerful breakdown reports.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">ITC Tracking</p>
                  <p class="text-gray-500">Enter the GST paid on purchases from registered vendors to automatically calculate your GST liability offsets.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── FAQ ── -->
        <section class="mt-12">
          <h2 class="text-2xl font-extrabold text-gray-900 mb-6 px-2">Frequently Asked Questions</h2>
          <div class="space-y-3">
            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" open>
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                Can I use BillBook India without a GSTIN?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                Yes! If your turnover is below the registration threshold, you can leave the GSTIN field blank in your Business Profile. The system will automatically generate standard (non-tax) invoices for you.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                How do I record a partial payment?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                Open any invoice and tap the green <strong>Record Payment</strong> pill. Enter the amount received. The system will track the payment history and update the "Balance Due" automatically until it is fully paid.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                Can I edit an invoice after sending it?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                Yes — the <strong>Edit</strong> button is available on any invoice that is not cancelled (Draft, Sent, Overdue, or Paid). While you can correct minor errors at any time, standard accounting practice recommends using a <strong>Credit Note</strong> for significant changes to already-shared invoices to maintain a clear audit trail.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                What is the difference between a Tax Invoice and a Bill of Supply?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                A <strong>Tax Invoice</strong> is issued when GST applies — it includes CGST/SGST or IGST breakdown. A <strong>Bill of Supply</strong> is for exempt goods/services or businesses below the GST threshold — no GST is charged. When you select Bill of Supply, all GST rates are automatically set to 0% and the Tax column is hidden on the printed document.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                How do I share an invoice via WhatsApp or Email?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                Open any invoice and tap the green <strong>Share</strong> button to send a WhatsApp message — it automatically includes the invoice number, total, balance due, due date, and your UPI ID (if configured in Settings). Tap the blue <strong>Email</strong> button to open your mail app with a fully pre-filled subject and body. You can then attach the PDF before sending.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                What is a Delivery Challan and when should I use it?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                Use a Delivery Challan when you dispatch goods before raising the invoice — for example, goods sent on approval, goods delivered in batches, or returnable items. A DC contains the vehicle number, driver name, destination, and item quantities (no prices). It must always be accompanied by or followed up with a proper Tax Invoice.
              </div>
            </details>
          </div>
        </section>

        <!-- Footer Note -->
        <div class="mt-12 text-center pb-8 opacity-70">
          <div class="w-16 h-1 bg-gray-200 rounded-full mx-auto mb-6"></div>
          <p class="text-sm font-bold text-gray-800">BillBook India</p>
          <p class="text-xs text-gray-500 mt-1">Proudly built for modern businesses.</p>
        </div>

      </div>
    </div>
  </div>
</template>
