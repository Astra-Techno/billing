<script setup>
import { useHelp } from '../composables/useHelp'

const { helpSection, closeHelp } = useHelp()

const titles = {
  dashboard:      { label: 'Dashboard',              color: 'bg-blue-100 text-blue-600' },
  bills:          { label: 'Bills / Invoices',        color: 'bg-primary-100 text-primary-600' },
  quotes:         { label: 'Quotations',              color: 'bg-indigo-100 text-indigo-600' },
  customers:      { label: 'Customers',               color: 'bg-purple-100 text-purple-600' },
  expenses:       { label: 'Expenses',                color: 'bg-orange-100 text-orange-600' },
  products:       { label: 'Products & Services',     color: 'bg-emerald-100 text-emerald-600' },
  returns:        { label: 'Returns & Adjustments',   color: 'bg-red-100 text-red-500' },
  gst:            { label: 'GST Filing',              color: 'bg-teal-100 text-teal-600' },
  reports:        { label: 'Reports',                 color: 'bg-violet-100 text-violet-600' },
  settings:       { label: 'Settings',                color: 'bg-gray-100 text-gray-600' },
}
</script>

<template>
  <Teleport to="body">
    <div v-if="helpSection" class="fixed inset-0 z-50 flex justify-end">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeHelp" />

      <!-- Drawer -->
      <div class="relative bg-white w-full max-w-md h-full overflow-y-auto shadow-2xl flex flex-col">

        <!-- Header -->
        <div class="sticky top-0 bg-white border-b border-gray-100 px-5 py-4 flex items-center gap-3 z-10">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" :class="titles[helpSection]?.color">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div class="flex-1">
            <p class="font-bold text-gray-900 text-sm">{{ titles[helpSection]?.label }}</p>
            <p class="text-xs text-gray-400">Quick help guide</p>
          </div>
          <button @click="closeHelp" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="p-5 space-y-4 text-sm flex-1">

          <!-- DASHBOARD -->
          <template v-if="helpSection === 'dashboard'">
            <p class="text-gray-600 leading-relaxed">The Dashboard gives you a quick summary of your business health at a glance.</p>
            <div class="space-y-3">
              <div class="bg-blue-50 rounded-xl p-3">
                <p class="font-semibold text-blue-800 text-xs mb-1">Money to Collect</p>
                <p class="text-xs text-blue-700">Total outstanding amount across all pending invoices.</p>
              </div>
              <div class="bg-red-50 rounded-xl p-3">
                <p class="font-semibold text-red-700 text-xs mb-1">Late Payments</p>
                <p class="text-xs text-red-600">Invoices past their due date. Tap the WhatsApp button to send a reminder instantly.</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="font-semibold text-gray-800 text-xs mb-1">Recent Bills</p>
                <p class="text-xs text-gray-600">Your latest invoices. Tap any bill to open it directly.</p>
              </div>
              <div class="bg-amber-50 rounded-xl p-3">
                <p class="font-semibold text-amber-800 text-xs mb-1">Quick Actions</p>
                <p class="text-xs text-amber-700">Shortcuts to New Bill, New Quote, Add Customer, Expenses, Products, and GST Filing.</p>
              </div>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <p class="text-xs text-green-700"><strong>Tip:</strong> Check overdue bills every morning and send WhatsApp reminders to improve cash flow.</p>
            </div>
          </template>

          <!-- BILLS -->
          <template v-else-if="helpSection === 'bills'">
            <p class="text-gray-600 leading-relaxed">Create GST-compliant tax invoices and track payments from your customers.</p>
            <div>
              <p class="font-semibold text-gray-800 mb-2">How to create a bill</p>
              <ol class="space-y-2">
                <li v-for="(s, i) in ['Tap New Bill from the bottom menu or the + button.','Select your customer (or add new on the spot).','Add items — type product name to auto-fill rate & GST.','Set invoice date and due date. Number is auto-generated.','Tap Save Invoice — your GST bill is ready!']"
                  :key="i" class="flex gap-2 text-xs text-gray-600">
                  <span class="w-5 h-5 rounded-full bg-primary-100 text-primary-700 font-bold flex items-center justify-center shrink-0 text-[10px]">{{i+1}}</span>
                  <span>{{s}}</span>
                </li>
              </ol>
            </div>
            <div>
              <p class="font-semibold text-gray-800 mb-2">Actions on a bill</p>
              <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                <div class="bg-gray-50 rounded-lg p-2"><strong>Print/PDF</strong> — Print-ready GST invoice</div>
                <div class="bg-gray-50 rounded-lg p-2"><strong>WhatsApp</strong> — Share with customer</div>
                <div class="bg-gray-50 rounded-lg p-2"><strong>Record Payment</strong> — Mark amount received</div>
                <div class="bg-gray-50 rounded-lg p-2"><strong>Duplicate</strong> — Copy for recurring bills</div>
              </div>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <p class="text-xs text-green-700"><strong>GST Tip:</strong> Same state = CGST + SGST. Different state = IGST. CloudBill handles this automatically.</p>
            </div>
          </template>

          <!-- QUOTES -->
          <template v-else-if="helpSection === 'quotes'">
            <p class="text-gray-600 leading-relaxed">Send price estimates to customers before doing the work. Convert accepted quotes directly to invoices.</p>
            <div>
              <p class="font-semibold text-gray-800 mb-2">Quote lifecycle</p>
              <div class="flex items-center gap-1 flex-wrap text-xs">
                <span class="badge badge-gray">Draft</span><span class="text-gray-400">→</span>
                <span class="badge badge-blue">Sent</span><span class="text-gray-400">→</span>
                <span class="badge badge-green">Accepted</span><span class="text-gray-400">→</span>
                <span class="badge badge-green">Converted</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">Or: <span class="badge badge-red text-[10px]">Declined</span> / <span class="badge badge-yellow text-[10px]">Expired</span></p>
            </div>
            <div class="space-y-2">
              <div class="bg-indigo-50 rounded-xl p-3">
                <p class="font-semibold text-indigo-800 text-xs mb-1">Convert to Invoice</p>
                <p class="text-xs text-indigo-700">Open an accepted quote → tap <strong>Convert to Invoice</strong>. All items copy over automatically.</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="font-semibold text-gray-800 text-xs mb-1">Valid Until Date</p>
                <p class="text-xs text-gray-600">Always set a validity date to protect against price changes after that date.</p>
              </div>
            </div>
          </template>

          <!-- CUSTOMERS -->
          <template v-else-if="helpSection === 'customers'">
            <p class="text-gray-600 leading-relaxed">Save your client details once, then select them in one tap when creating bills or quotes.</p>
            <div>
              <p class="font-semibold text-gray-800 mb-2">Important fields</p>
              <div class="space-y-1.5 text-xs text-gray-600">
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-20 shrink-0">GSTIN</strong><span>For B2B customers — required for proper GST invoicing.</span></div>
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-20 shrink-0">State</strong><span>Determines CGST+SGST vs IGST on invoices.</span></div>
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-20 shrink-0">Phone</strong><span>Used for WhatsApp payment reminders.</span></div>
              </div>
            </div>
            <div class="bg-purple-50 border border-purple-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <p class="text-xs text-purple-700"><strong>Tip:</strong> Tap any customer to see their full billing history and outstanding balance.</p>
            </div>
          </template>

          <!-- EXPENSES -->
          <template v-else-if="helpSection === 'expenses'">
            <p class="text-gray-600 leading-relaxed">Record what your business spends to track true profit and claim GST Input Tax Credit (ITC).</p>
            <div>
              <p class="font-semibold text-gray-800 mb-2">What to record</p>
              <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                <div class="bg-gray-50 rounded-lg p-2">Office rent</div>
                <div class="bg-gray-50 rounded-lg p-2">Electricity</div>
                <div class="bg-gray-50 rounded-lg p-2">Raw materials</div>
                <div class="bg-gray-50 rounded-lg p-2">Travel</div>
                <div class="bg-gray-50 rounded-lg p-2">Software tools</div>
                <div class="bg-gray-50 rounded-lg p-2">Salaries</div>
              </div>
            </div>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <p class="text-xs text-blue-700"><strong>ITC Tip:</strong> Enter the GST amount paid on purchases from GST-registered vendors to claim Input Tax Credit. This is shown in Reports → GST Summary.</p>
            </div>
          </template>

          <!-- PRODUCTS -->
          <template v-else-if="helpSection === 'products'">
            <p class="text-gray-600 leading-relaxed">Add your products and services here so they auto-fill when you create invoices — saving time every time.</p>
            <div>
              <p class="font-semibold text-gray-800 mb-2">Key fields</p>
              <div class="space-y-1.5 text-xs text-gray-600">
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-24 shrink-0">HSN / SAC Code</strong><span>Government classification code for GST. HSN for goods, SAC for services.</span></div>
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-24 shrink-0">GST Rate</strong><span>Common rates: 0%, 5%, 12%, 18%, 28%.</span></div>
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-24 shrink-0">Unit</strong><span>E.g. Nos, Kg, Meter, Hour, Piece.</span></div>
              </div>
            </div>
            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <p class="text-xs text-emerald-700"><strong>Tip:</strong> Add all your common items here first. When billing, just type the name and everything fills automatically.</p>
            </div>
          </template>

          <!-- RETURNS -->
          <template v-else-if="helpSection === 'returns'">
            <p class="text-gray-600 leading-relaxed">Issue credit notes when a customer returns goods or when you need to reduce a previously raised invoice amount.</p>
            <div>
              <p class="font-semibold text-gray-800 mb-2">When to use a Credit Note</p>
              <ul class="space-y-1.5 text-xs text-gray-600">
                <li class="flex gap-2"><span class="w-1.5 h-1.5 rounded-full bg-red-400 mt-1.5 shrink-0"></span>Customer returned goods</li>
                <li class="flex gap-2"><span class="w-1.5 h-1.5 rounded-full bg-red-400 mt-1.5 shrink-0"></span>Price was overcharged</li>
                <li class="flex gap-2"><span class="w-1.5 h-1.5 rounded-full bg-red-400 mt-1.5 shrink-0"></span>Discount given after billing</li>
                <li class="flex gap-2"><span class="w-1.5 h-1.5 rounded-full bg-red-400 mt-1.5 shrink-0"></span>Invoice was raised in error</li>
              </ul>
            </div>
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              <p class="text-xs text-amber-700"><strong>GST Rule:</strong> Under GST law, you cannot modify a sent invoice. Always issue a credit note for corrections instead.</p>
            </div>
          </template>

          <!-- GST -->
          <template v-else-if="helpSection === 'gst'">
            <p class="text-gray-600 leading-relaxed">Prepare your sales data for GST returns. CloudBill organizes your invoices — your CA or you file on the GST portal.</p>
            <div>
              <p class="font-semibold text-gray-800 mb-2">GST Return types</p>
              <div class="space-y-1.5 text-xs text-gray-600">
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-16 shrink-0">GSTR-1</strong><span>Outward sales. Filed monthly or quarterly.</span></div>
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-16 shrink-0">GSTR-3B</strong><span>Summary return for tax payment. Filed monthly.</span></div>
                <div class="flex gap-2 bg-gray-50 rounded-lg p-2"><strong class="w-16 shrink-0">GSTR-2B</strong><span>Auto-generated ITC statement from vendor filings.</span></div>
              </div>
            </div>
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              <p class="text-xs text-amber-700"><strong>Important:</strong> Always consult a Chartered Accountant before filing GST returns. Penalties apply for errors.</p>
            </div>
          </template>

          <!-- REPORTS -->
          <template v-else-if="helpSection === 'reports'">
            <p class="text-gray-600 leading-relaxed">Detailed financial insights for any date range. Use the date filter at the top to select any period.</p>
            <div class="grid grid-cols-1 gap-2">
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="font-semibold text-gray-800 text-xs mb-1">Sales Summary</p>
                <p class="text-xs text-gray-500">Total invoiced, collected, and pending receivables.</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="font-semibold text-gray-800 text-xs mb-1">Expense Summary</p>
                <p class="text-xs text-gray-500">Category-wise breakdown of all expenses.</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="font-semibold text-gray-800 text-xs mb-1">Outstanding Invoices</p>
                <p class="text-xs text-gray-500">All unpaid invoices. Tap the CSV icon to export.</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="font-semibold text-gray-800 text-xs mb-1">GST Summary</p>
                <p class="text-xs text-gray-500">CGST, SGST, IGST collected + ITC estimate.</p>
              </div>
            </div>
          </template>

          <!-- SETTINGS -->
          <template v-else-if="helpSection === 'settings'">
            <p class="text-gray-600 leading-relaxed">Configure your business profile. This information appears on every invoice and quote you send.</p>
            <div class="space-y-2 text-xs text-gray-600">
              <div class="bg-gray-50 rounded-xl p-3"><p class="font-semibold text-gray-800 mb-1">Business Profile</p><p>Name, address, GSTIN, PAN, phone, email.</p></div>
              <div class="bg-gray-50 rounded-xl p-3"><p class="font-semibold text-gray-800 mb-1">Logo & Signature</p><p>Upload company logo and authorized signatory image.</p></div>
              <div class="bg-gray-50 rounded-xl p-3"><p class="font-semibold text-gray-800 mb-1">Bank Details</p><p>Account number, IFSC, bank name — printed on invoices for payments.</p></div>
              <div class="bg-gray-50 rounded-xl p-3"><p class="font-semibold text-gray-800 mb-1">Invoice Preferences</p><p>Default payment terms, invoice prefix, starting number, and footer notes.</p></div>
            </div>
            <div class="bg-red-50 border border-red-100 rounded-xl p-3 flex gap-2">
              <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              <p class="text-xs text-red-600"><strong>Important:</strong> Complete Settings before sending your first invoice — wrong GSTIN or address requires cancelling and re-issuing.</p>
            </div>
          </template>

        </div>

        <!-- Footer -->
        <div class="px-5 pb-5 pt-2 border-t border-gray-100 shrink-0">
          <RouterLink :to="'/help#' + helpSection" @click="closeHelp"
            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-gray-50 hover:bg-gray-100 text-xs font-semibold text-gray-600 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            View full guide
          </RouterLink>
        </div>

      </div>
    </div>
  </Teleport>
</template>
