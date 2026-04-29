<script setup>
import { ref, computed, onMounted } from 'vue'
import { all, item, list } from '../../api'
import { inr } from '../../utils/currency'

// ── Base state ────────────────────────────────────────────────────────────────
const states     = ref([])
const business   = ref(null)
const loading    = ref(true)
const fetching   = ref(false)
const building   = ref(false)
const fetchError = ref('')
const step       = ref(1)   // 1 = pick period, 2 = review, 3 = done

// Filing frequency
const filingFreq = ref('monthly')  // 'monthly' | 'quarterly'

// Period selection
const now       = new Date()
const selMonth  = ref(now.getMonth() + 1)
const selYear   = ref(now.getFullYear())
const selQ      = ref(Math.ceil((now.getMonth() + 1) / 3))
const selQYear  = ref(now.getFullYear())

// Invoices & selection
const invoices    = ref([])
const selectedIds = ref(new Set())
const itemsMap    = ref({})
const cashSales   = ref([])       // manual B2CS rows

// Result
const gstr1Json   = ref(null)
const resultData  = ref(null)

// ── Static data ───────────────────────────────────────────────────────────────
const MONTH_NAMES = ['','January','February','March','April','May','June',
                     'July','August','September','October','November','December']
const QUARTER_LABELS = ['','Apr – Jun','Jul – Sep','Oct – Dec','Jan – Mar']
const GST_RATES = [0, 5, 12, 18, 28]

// Last 12 months as selectable cards
const recentMonths = computed(() => {
  const list = []
  for (let i = 0; i < 12; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    list.push({ month: d.getMonth() + 1, year: d.getFullYear(),
                label: MONTH_NAMES[d.getMonth() + 1], shortYear: String(d.getFullYear()).slice(2) })
  }
  return list
})

// Quarterly options for current and last FY
const recentQuarters = computed(() => {
  const rows = []
  for (let fy = now.getFullYear(); fy >= now.getFullYear() - 1; fy--) {
    for (let q = 4; q >= 1; q--) {
      rows.push({ q, year: fy, label: QUARTER_LABELS[q], fyLabel: `FY ${fy}–${String(fy+1).slice(2)}` })
    }
  }
  return rows.slice(0, 8)
})

// Period date range
const periodRange = computed(() => {
  if (filingFreq.value === 'monthly') {
    const m = String(selMonth.value).padStart(2,'0'), y = selYear.value
    const last = new Date(y, selMonth.value, 0).getDate()
    return {
      fromDate: `${y}-${m}-01`,
      toDate:   `${y}-${m}-${String(last).padStart(2,'0')}`,
      fp:       `${m}${y}`,
      label:    `${MONTH_NAMES[selMonth.value]} ${y}`,
      filename: `GST_File_${MONTH_NAMES[selMonth.value]}_${y}`,
    }
  }
  const qMonths = [[],[4,5,6],[7,8,9],[10,11,12],[1,2,3]]
  const mths  = qMonths[selQ.value]
  const first = mths[0], last = mths[2]
  const fromY = first >= 4 ? selQYear.value : selQYear.value + 1
  const toY   = last  >= 4 ? selQYear.value : selQYear.value + 1
  const lastDay = new Date(toY, last % 12 || 12, 0).getDate()
  const fpM = String(last).padStart(2,'0'), fpY = last >= 4 ? selQYear.value : selQYear.value + 1
  return {
    fromDate: `${fromY}-${String(first).padStart(2,'0')}-01`,
    toDate:   `${toY}-${String(last).padStart(2,'0')}-${String(lastDay).padStart(2,'0')}`,
    fp:       `${fpM}${fpY}`,
    label:    `${QUARTER_LABELS[selQ.value]} ${selQYear.value}–${String(selQYear.value+1).slice(2)}`,
    filename: `GST_File_Q${selQ.value}_${selQYear.value}`,
  }
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const r2 = n => Math.round((parseFloat(n)||0)*100)/100
const fmtGstDate = d => { if(!d)return''; const[y,m,dd]=d.split('-'); return`${dd}-${m}-${y}` }

function stateCode(name) {
  const s = states.value.find(s => s.name === name)
  return s ? String(s.code).padStart(2,'0') : '99'
}
const UQC = {Nos:'NOS',Pcs:'NOS',Set:'NOS',Pair:'NOS',Kg:'KGS',Ltr:'LTR',Mtr:'MTS',Box:'BOX',Hrs:'OTH'}
const uqc = u => UQC[u]||'OTH'

function groupByRate(items) {
  const map = {}
  for (const it of items) {
    const rt = parseFloat(it.gst_rate||0)
    if (!map[rt]) map[rt] = {rt,txval:0,cgst:0,sgst:0,igst:0}
    map[rt].txval += parseFloat(it.taxable_amt||0)
    map[rt].cgst  += parseFloat(it.cgst_amt||0)
    map[rt].sgst  += parseFloat(it.sgst_amt||0)
    map[rt].igst  += parseFloat(it.igst_amt||0)
  }
  return Object.values(map).map(g=>({...g,txval:r2(g.txval),cgst:r2(g.cgst),sgst:r2(g.sgst),igst:r2(g.igst)}))
}

// Categorise invoice for display
const invCategory = inv => inv.client_gstin ? 'business' : 'retail'

// Invoice groups for review screen
const businessInvoices = computed(() => invoices.value.filter(i => invCategory(i)==='business'))
const retailInvoices   = computed(() => invoices.value.filter(i => invCategory(i)==='retail'))

// Totals from selected invoices + cash sales
const liveTotals = computed(() => {
  let taxable=0,cgst=0,sgst=0,igst=0,total=0
  for (const inv of invoices.value) {
    if (!selectedIds.value.has(inv.id)) continue
    for (const it of (itemsMap.value[inv.id]||[])) {
      taxable += parseFloat(it.taxable_amt||0)
      cgst    += parseFloat(it.cgst_amt||0)
      sgst    += parseFloat(it.sgst_amt||0)
      igst    += parseFloat(it.igst_amt||0)
      total   += parseFloat(it.total||0)
    }
  }
  for (const row of cashSales.value) {
    const tv=parseFloat(row.amount||0), rt=parseFloat(row.rt||0)
    const tax=tv*rt/100
    taxable+=tv; total+=tv+tax; cgst+=r2(tax/2); sgst+=r2(tax/2)
  }
  return {taxable:r2(taxable),cgst:r2(cgst),sgst:r2(sgst),igst:r2(igst),total:r2(total),
          gst:r2(cgst+sgst+igst)}
})

const selectedCount = computed(() => selectedIds.value.size)

// ── Step 1 → 2: Fetch invoices ─────────────────────────────────────────────
async function loadSales() {
  fetchError.value = ''
  fetching.value   = true
  invoices.value   = []
  selectedIds.value = new Set()
  itemsMap.value   = {}
  cashSales.value  = []

  try {
    const r = periodRange.value
    const p = {'filter.from_date':r.fromDate,'filter.to_date':r.toDate,limit:2000}
    const [paid,partial,sent,overdue] = await Promise.all([
      list('Invoice',{...p,'filter.status':'paid'}),
      list('Invoice',{...p,'filter.status':'partial'}),
      list('Invoice',{...p,'filter.status':'sent'}),
      list('Invoice',{...p,'filter.status':'overdue'}),
    ])
    const all_inv = [
      ...(paid.data?.data||[]),...(partial.data?.data||[]),
      ...(sent.data?.data||[]),...(overdue.data?.data||[]),
    ].filter(inv=>inv.invoice_type!=='bill_of_supply')
     .sort((a,b)=>new Date(a.issue_date)-new Date(b.issue_date))

    const itemRes = await Promise.all(all_inv.map(inv=>list('Invoice:items',{invoice_id:inv.id})))
    const map={}
    all_inv.forEach((inv,i)=>{ map[inv.id]=itemRes[i].data?.data||[] })

    invoices.value  = all_inv
    itemsMap.value  = map
    all_inv.forEach(inv=>selectedIds.value.add(inv.id))

    if (!all_inv.length) {
      fetchError.value = `No bills found for ${r.label}. If you have sales, make sure they are not in Draft status.`
      return
    }
    step.value = 2
  } catch(e) {
    fetchError.value = 'Something went wrong. Please try again.'
  } finally { fetching.value = false }
}

// Toggle helpers
function toggleInv(id) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
}
function selectAll()   { invoices.value.forEach(inv=>selectedIds.value.add(inv.id)) }
function deselectAll() { invoices.value.forEach(inv=>selectedIds.value.delete(inv.id)) }

// Cash sales rows
function addCashRow() {
  cashSales.value.push({ amount:'', rt:18, state_name: states.value[0]?.name||'' })
}
function removeCashRow(i) { cashSales.value.splice(i,1) }

// ── Step 2 → 3: Build JSON & download ────────────────────────────────────────
async function createFile() {
  building.value = true
  fetchError.value = ''
  try {
    const r = periodRange.value
    const selected = invoices.value.filter(inv=>selectedIds.value.has(inv.id))
    const b2bMap={}, b2csMap={}, b2clMap={}, hsnMap={}

    for (const inv of selected) {
      const items=itemsMap.value[inv.id]||[]
      const posCode=stateCode(inv.place_of_supply_name)
      const hasGstin=!!inv.client_gstin
      const total=parseFloat(inv.total||0)
      const isInter=inv.supply_type==='inter'
      const rg=groupByRate(items)
      const idt=fmtGstDate(inv.issue_date)

      if (hasGstin) {
        if (!b2bMap[inv.client_gstin]) b2bMap[inv.client_gstin]=[]
        b2bMap[inv.client_gstin].push({
          inum:inv.number,idt,val:r2(total),pos:posCode,rchrg:'N',inv_typ:'R',
          itms:rg.map((g,i)=>({num:i+1,itm_det:{txval:g.txval,rt:g.rt,camt:g.cgst,samt:g.sgst,iamt:g.igst,csamt:0}})),
        })
      } else if (isInter && total>250000) {
        if (!b2clMap[posCode]) b2clMap[posCode]=[]
        b2clMap[posCode].push({
          inum:inv.number,idt,val:r2(total),
          itms:rg.map((g,i)=>({num:i+1,itm_det:{txval:g.txval,rt:g.rt,iamt:g.igst,csamt:0}})),
        })
      } else {
        for (const g of rg) {
          const key=`${posCode}_${g.rt}`
          if (!b2csMap[key]) b2csMap[key]={sply_tp:isInter?'INTER':'INTRA',pos:posCode,rt:g.rt,txval:0,camt:0,samt:0,iamt:0,csamt:0}
          b2csMap[key].txval+=g.txval; b2csMap[key].camt+=g.cgst
          b2csMap[key].samt+=g.sgst;   b2csMap[key].iamt+=g.igst
        }
      }
      for (const it of items) {
        const hsn=it.hsn_sac||'',rt=parseFloat(it.gst_rate||0),key=`${hsn||'NONE'}_${rt}`
        if (!hsnMap[key]) hsnMap[key]={hsn_sc:hsn,desc:it.description,uqc:uqc(it.unit),qty:0,val:0,txval:0,rt,camt:0,samt:0,iamt:0,csamt:0}
        hsnMap[key].qty+=parseFloat(it.quantity||0); hsnMap[key].val+=parseFloat(it.total||0)
        hsnMap[key].txval+=parseFloat(it.taxable_amt||0); hsnMap[key].camt+=parseFloat(it.cgst_amt||0)
        hsnMap[key].samt+=parseFloat(it.sgst_amt||0);     hsnMap[key].iamt+=parseFloat(it.igst_amt||0)
      }
    }

    // Merge cash sales
    for (const row of cashSales.value) {
      const tv=parseFloat(row.amount||0); if(!tv) continue
      const pos=stateCode(row.state_name||states.value[0]?.name||'')
      const rt=parseFloat(row.rt||0), tax=r2(tv*rt/100)
      const key=`${pos}_${rt}`
      if (!b2csMap[key]) b2csMap[key]={sply_tp:'INTRA',pos,rt,txval:0,camt:0,samt:0,iamt:0,csamt:0}
      b2csMap[key].txval+=tv; b2csMap[key].camt+=r2(tax/2); b2csMap[key].samt+=r2(tax/2)
    }

    const b2csArr=Object.values(b2csMap).map(x=>({...x,txval:r2(x.txval),camt:r2(x.camt),samt:r2(x.samt),iamt:r2(x.iamt)}))
    const hsnArr=Object.values(hsnMap).map((h,i)=>({num:i+1,...h,qty:r2(h.qty),val:r2(h.val),txval:r2(h.txval),camt:r2(h.camt),samt:r2(h.samt),iamt:r2(h.iamt)}))

    const gstr1={
      gstin:business.value?.gstin||'',fp:r.fp,version:'GST3.0.4',
      b2b:Object.entries(b2bMap).map(([ctin,inv])=>({ctin,inv})),
      b2cs:b2csArr,
      b2cl:Object.entries(b2clMap).map(([pos,inv])=>({pos,inv})),
      hsn:{data:hsnArr},
    }
    gstr1Json.value = gstr1

    const t = liveTotals.value
    resultData.value = {
      period:   r.label,
      filename: r.filename,
      included: selected.length,
      excluded: invoices.value.length - selected.length,
      cashRows: cashSales.value.filter(x=>parseFloat(x.amount||0)>0).length,
      b2bCount: gstr1.b2b.reduce((s,g)=>s+g.inv.length,0),
      retailCount: b2csArr.length + gstr1.b2cl.reduce((s,g)=>s+g.inv.length,0),
      cgst:t.cgst, sgst:t.sgst, igst:t.igst, gst:t.gst,
    }
    step.value = 3
    download()
  } catch(e) {
    fetchError.value = 'Could not create GST file. Please try again.'
  } finally { building.value = false }
}

function download() {
  if (!gstr1Json.value || !resultData.value) return
  const blob=new Blob([JSON.stringify(gstr1Json.value,null,2)],{type:'application/json'})
  const url=URL.createObjectURL(blob)
  const a=Object.assign(document.createElement('a'),{href:url,download:`${resultData.value.filename}.json`})
  a.click(); URL.revokeObjectURL(url)
}

function startOver() { step.value=1; invoices.value=[]; selectedIds.value=new Set(); cashSales.value=[]; fetchError.value=''; gstr1Json.value=null; resultData.value=null }

onMounted(async()=>{
  loading.value=true
  try {
    const [stRes,bizRes]=await Promise.all([all('IndianState'),item('Business')])
    states.value=stRes.data?.data||[]
    business.value=bizRes.data?.data||null
  } catch{}
  loading.value=false
})
</script>

<template>
  <div class="max-w-xl mx-auto space-y-5 pb-10">

    <!-- Header -->
    <div class="text-center pt-2">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary-100 mb-3">
        <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
      </div>
      <h1 class="text-xl font-bold text-gray-900">GST File Generator</h1>
      <p class="text-sm text-gray-500 mt-1">Create your GST return file in 3 simple steps</p>

      <!-- Step dots -->
      <div class="flex items-center justify-center gap-2 mt-4">
        <div v-for="n in 3" :key="n" class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
            :class="step===n ? 'bg-primary-600 text-white shadow-md scale-110'
                  : step>n  ? 'bg-success-500 text-white'
                             : 'bg-gray-200 text-gray-500'">
            <svg v-if="step>n" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
            </svg>
            <span v-else>{{ n }}</span>
          </div>
          <div v-if="n<3" class="w-8 h-0.5" :class="step>n?'bg-success-400':'bg-gray-200'"></div>
        </div>
      </div>
      <div class="flex justify-center gap-10 mt-1">
        <span class="text-xs" :class="step===1?'text-primary-600 font-medium':'text-gray-400'">Choose month</span>
        <span class="text-xs" :class="step===2?'text-primary-600 font-medium':'text-gray-400'">Check bills</span>
        <span class="text-xs" :class="step===3?'text-primary-600 font-medium':'text-gray-400'">Download</span>
      </div>
    </div>

    <div v-if="loading" class="card p-10 text-center text-gray-400">Loading your data…</div>

    <template v-else>

      <!-- GSTIN missing notice -->
      <div v-if="!business?.gstin"
        class="flex items-start gap-3 bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-3">
        <span class="text-xl mt-0.5">⚠️</span>
        <div class="text-sm text-yellow-800">
          <p class="font-semibold">Your GST number (GSTIN) is missing</p>
          <p class="mt-0.5">Go to
            <RouterLink to="/settings" class="underline font-medium">Settings → GST Details</RouterLink>
            and add your GSTIN. It will appear on the file sent to the government.
          </p>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           STEP 1 — Choose month
      ══════════════════════════════════════════ -->
      <div v-if="step===1" class="space-y-4">

        <!-- How often do you file? -->
        <div class="card card-body">
          <p class="text-sm font-semibold text-gray-800 mb-3">How often do you file GST?</p>
          <div class="grid grid-cols-2 gap-3">
            <button @click="filingFreq='monthly'"
              class="flex flex-col items-center gap-1 py-4 rounded-xl border-2 transition-all"
              :class="filingFreq==='monthly'?'border-primary-500 bg-primary-50':'border-gray-200 hover:border-gray-300'">
              <span class="text-2xl">📅</span>
              <span class="text-sm font-semibold text-gray-800">Every month</span>
              <span class="text-xs text-gray-400">Monthly filer</span>
            </button>
            <button @click="filingFreq='quarterly'"
              class="flex flex-col items-center gap-1 py-4 rounded-xl border-2 transition-all"
              :class="filingFreq==='quarterly'?'border-primary-500 bg-primary-50':'border-gray-200 hover:border-gray-300'">
              <span class="text-2xl">🗓️</span>
              <span class="text-sm font-semibold text-gray-800">Every 3 months</span>
              <span class="text-xs text-gray-400">Quarterly filer</span>
            </button>
          </div>
          <p class="text-xs text-gray-400 text-center mt-2">
            Not sure? Ask your CA. Most small shops file every month.
          </p>
        </div>

        <!-- Month picker -->
        <div v-if="filingFreq==='monthly'" class="card card-body">
          <p class="text-sm font-semibold text-gray-800 mb-3">
            Which month's GST file do you need?
          </p>
          <div class="grid grid-cols-3 gap-2">
            <button v-for="m in recentMonths" :key="`${m.month}-${m.year}`"
              @click="selMonth=m.month; selYear=m.year"
              class="py-3 rounded-xl border-2 text-center transition-all"
              :class="selMonth===m.month && selYear===m.year
                ? 'border-primary-500 bg-primary-50'
                : 'border-gray-200 hover:border-gray-300'">
              <p class="text-sm font-semibold text-gray-800">{{ m.label }}</p>
              <p class="text-xs text-gray-400">'{{ m.shortYear }}</p>
            </button>
          </div>
        </div>

        <!-- Quarter picker -->
        <div v-if="filingFreq==='quarterly'" class="card card-body">
          <p class="text-sm font-semibold text-gray-800 mb-3">
            Which quarter's GST file do you need?
          </p>
          <div class="space-y-2">
            <button v-for="q in recentQuarters" :key="`${q.q}-${q.year}`"
              @click="selQ=q.q; selQYear=q.year"
              class="w-full flex items-center justify-between px-4 py-3 rounded-xl border-2 transition-all"
              :class="selQ===q.q && selQYear===q.year
                ? 'border-primary-500 bg-primary-50'
                : 'border-gray-200 hover:border-gray-300'">
              <div class="text-left">
                <p class="text-sm font-semibold text-gray-800">{{ q.label }}</p>
                <p class="text-xs text-gray-400">{{ q.fyLabel }}</p>
              </div>
              <div v-if="selQ===q.q && selQYear===q.year"
                class="w-5 h-5 rounded-full bg-primary-600 flex items-center justify-center">
                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                </svg>
              </div>
            </button>
          </div>
        </div>

        <div v-if="fetchError" class="flex items-start gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
          <span>⚠️</span> {{ fetchError }}
        </div>

        <div v-if="fetching" class="card card-body flex items-center justify-center gap-3 py-6 text-primary-600">
          <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          <span class="text-sm font-medium">Loading your bills for {{ periodRange.label }}…</span>
        </div>

        <button v-if="!fetching" @click="loadSales"
          class="btn-primary w-full py-3.5 text-base font-semibold rounded-xl">
          Show my bills for {{ periodRange?.label }} →
        </button>
      </div>

      <!-- ══════════════════════════════════════════
           STEP 2 — Review bills
      ══════════════════════════════════════════ -->
      <template v-if="step===2">

        <!-- Period badge -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="bg-primary-100 text-primary-700 text-sm font-semibold px-3 py-1 rounded-full">
              📅 {{ periodRange.label }}
            </span>
            <span class="text-xs text-gray-400">{{ invoices.length }} bills found</span>
          </div>
          <button @click="step=1" class="text-xs text-gray-400 hover:text-gray-600 underline">
            Change month
          </button>
        </div>

        <!-- Business customers -->
        <div class="card overflow-hidden">
          <div class="px-5 py-4 bg-blue-50 border-b border-blue-100">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xl">🏢</span>
                <div>
                  <p class="font-semibold text-gray-800 text-sm">Business customers</p>
                  <p class="text-xs text-gray-500">Customers who gave you their GST number</p>
                </div>
              </div>
              <span class="text-xs font-medium text-blue-700 bg-blue-100 px-2 py-1 rounded-full">
                {{ businessInvoices.length }} bill{{ businessInvoices.length!==1?'s':'' }}
              </span>
            </div>
          </div>
          <div v-if="!businessInvoices.length" class="px-5 py-4 text-sm text-gray-400 text-center">
            No bills to business customers this period
          </div>
          <div v-else class="divide-y divide-gray-100">
            <label v-for="inv in businessInvoices" :key="inv.id"
              class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
              :class="{'opacity-40': !selectedIds.has(inv.id)}">
              <input type="checkbox" :checked="selectedIds.has(inv.id)"
                @change="toggleInv(inv.id)" class="w-4 h-4 rounded text-primary-600" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">{{ inv.client_name }}</p>
                <p class="text-xs text-gray-400">{{ inv.number }} · {{ inv.client_gstin }}</p>
              </div>
              <p class="text-sm font-semibold text-gray-900 shrink-0">{{ inr(inv.total) }}</p>
            </label>
          </div>
          <div v-if="businessInvoices.length > 1" class="px-5 py-2 border-t border-gray-100 flex gap-3">
            <button @click="businessInvoices.forEach(inv=>selectedIds.add(inv.id))"
              class="text-xs text-primary-600 hover:underline">Select all</button>
            <span class="text-gray-200">|</span>
            <button @click="businessInvoices.forEach(inv=>selectedIds.delete(inv.id))"
              class="text-xs text-gray-400 hover:underline">Deselect all</button>
          </div>
        </div>

        <!-- Retail customers -->
        <div class="card overflow-hidden">
          <div class="px-5 py-4 bg-purple-50 border-b border-purple-100">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xl">🛍️</span>
                <div>
                  <p class="font-semibold text-gray-800 text-sm">Regular customers</p>
                  <p class="text-xs text-gray-500">Walk-in customers, individuals, no GST number</p>
                </div>
              </div>
              <span class="text-xs font-medium text-purple-700 bg-purple-100 px-2 py-1 rounded-full">
                {{ retailInvoices.length }} bill{{ retailInvoices.length!==1?'s':'' }}
              </span>
            </div>
          </div>
          <div v-if="!retailInvoices.length" class="px-5 py-4 text-sm text-gray-400 text-center">
            No bills to regular customers this period
          </div>
          <div v-else class="divide-y divide-gray-100">
            <label v-for="inv in retailInvoices" :key="inv.id"
              class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
              :class="{'opacity-40': !selectedIds.has(inv.id)}">
              <input type="checkbox" :checked="selectedIds.has(inv.id)"
                @change="toggleInv(inv.id)" class="w-4 h-4 rounded text-primary-600" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">
                  {{ inv.client_name || 'Walk-in Customer' }}
                </p>
                <p class="text-xs text-gray-400">{{ inv.number }}</p>
              </div>
              <p class="text-sm font-semibold text-gray-900 shrink-0">{{ inr(inv.total) }}</p>
            </label>
          </div>
          <div v-if="retailInvoices.length > 1" class="px-5 py-2 border-t border-gray-100 flex gap-3">
            <button @click="retailInvoices.forEach(inv=>selectedIds.add(inv.id))"
              class="text-xs text-primary-600 hover:underline">Select all</button>
            <span class="text-gray-200">|</span>
            <button @click="retailInvoices.forEach(inv=>selectedIds.delete(inv.id))"
              class="text-xs text-gray-400 hover:underline">Deselect all</button>
          </div>
        </div>

        <!-- Cash / unrecorded sales -->
        <div class="card overflow-hidden">
          <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xl">💵</span>
                <div>
                  <p class="font-semibold text-gray-800 text-sm">Cash sales not in this app?</p>
                  <p class="text-xs text-gray-500">Add any sales you didn't enter as a bill</p>
                </div>
              </div>
              <button @click="addCashRow"
                class="text-xs bg-amber-100 text-amber-700 font-medium px-3 py-1.5 rounded-lg hover:bg-amber-200 transition-colors">
                + Add
              </button>
            </div>
          </div>

          <div v-if="!cashSales.length" class="px-5 py-4 text-center">
            <p class="text-sm text-gray-400">All your sales are already in the bills above?</p>
            <p class="text-xs text-gray-400 mt-0.5">Great! Skip this section.</p>
          </div>

          <div v-else class="divide-y divide-gray-100">
            <div v-for="(row, i) in cashSales" :key="i" class="px-5 py-4 space-y-3">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-700">Cash sale entry {{ i+1 }}</p>
                <button @click="removeCashRow(i)" class="text-gray-400 hover:text-red-500 text-lg leading-none">×</button>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="form-label">Total sale amount (₹) — without GST</label>
                  <input v-model="row.amount" type="number" min="0" step="0.01"
                    class="form-input" placeholder="e.g. 50000" />
                </div>
                <div>
                  <label class="form-label">GST rate on this sale</label>
                  <select v-model="row.rt" class="form-select">
                    <option v-for="r in GST_RATES" :key="r" :value="r">{{ r }}% GST</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="form-label">State where you sold</label>
                <select v-model="row.state_name" class="form-select">
                  <option v-for="s in states" :key="s.id" :value="s.name">{{ s.name }}</option>
                </select>
              </div>
              <div class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                GST on this entry: <strong>{{ inr(parseFloat(row.amount||0) * parseFloat(row.rt||0) / 100) }}</strong>
                (CGST {{ inr(parseFloat(row.amount||0)*parseFloat(row.rt||0)/200) }} +
                 SGST {{ inr(parseFloat(row.amount||0)*parseFloat(row.rt||0)/200) }})
              </div>
            </div>
          </div>
        </div>

        <!-- Live totals -->
        <div class="card card-body bg-gray-900 text-white rounded-2xl space-y-4">
          <div class="flex items-center justify-between">
            <p class="font-semibold">Your GST for {{ periodRange.label }}</p>
            <span class="text-xs text-gray-400">{{ selectedCount }} of {{ invoices.length }} bills included</span>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div class="bg-white/10 rounded-xl p-3 text-center">
              <p class="text-xs text-gray-300 mb-1">CGST</p>
              <p class="font-bold text-lg">{{ inr(liveTotals.cgst) }}</p>
            </div>
            <div class="bg-white/10 rounded-xl p-3 text-center">
              <p class="text-xs text-gray-300 mb-1">SGST</p>
              <p class="font-bold text-lg">{{ inr(liveTotals.sgst) }}</p>
            </div>
            <div class="bg-white/10 rounded-xl p-3 text-center">
              <p class="text-xs text-gray-300 mb-1">IGST</p>
              <p class="font-bold text-lg">{{ inr(liveTotals.igst) }}</p>
            </div>
          </div>
          <div class="border-t border-white/20 pt-3 flex justify-between items-center">
            <p class="text-gray-300 text-sm">Total GST to pay this period</p>
            <p class="text-2xl font-bold text-white">{{ inr(liveTotals.gst) }}</p>
          </div>
        </div>

        <div v-if="fetchError" class="flex items-start gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
          <span>⚠️</span> {{ fetchError }}
        </div>

        <div class="flex gap-3">
          <button @click="step=1" class="btn-outline flex-1" :disabled="building">← Back</button>
          <button @click="createFile" :disabled="building || selectedCount===0"
            class="btn-primary flex-1 py-3.5 text-base font-semibold rounded-xl">
            <span v-if="building" class="flex items-center justify-center gap-2">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Creating file…
            </span>
            <span v-else>✅ Create my GST file</span>
          </button>
        </div>
      </template>

      <!-- ══════════════════════════════════════════
           STEP 3 — Done!
      ══════════════════════════════════════════ -->
      <template v-if="step===3 && resultData">

        <!-- Success banner -->
        <div class="card card-body text-center space-y-3 bg-success-50 border border-success-200">
          <div class="flex justify-center">
            <div class="w-16 h-16 rounded-full bg-success-100 flex items-center justify-center">
              <svg class="w-8 h-8 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
          </div>
          <div>
            <h2 class="text-lg font-bold text-gray-900">Your GST file is ready! 🎉</h2>
            <p class="text-sm text-gray-600 mt-1">{{ resultData.period }}</p>
          </div>
          <p class="text-xs text-gray-500">The file was downloaded automatically. Check your Downloads folder.</p>
        </div>

        <!-- What's in the file -->
        <div class="card card-body space-y-4">
          <p class="font-semibold text-gray-800">What's in your file</p>
          <div class="space-y-3">
            <div class="flex items-center justify-between py-2 border-b border-gray-100">
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <span>🏢</span> Bills to business customers
              </div>
              <span class="font-semibold text-gray-900">{{ resultData.b2bCount }}</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-gray-100">
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <span>🛍️</span> Regular customer sales
              </div>
              <span class="font-semibold text-gray-900">{{ resultData.retailCount }}</span>
            </div>
            <div v-if="resultData.cashRows" class="flex items-center justify-between py-2 border-b border-gray-100">
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <span>💵</span> Cash sales added manually
              </div>
              <span class="font-semibold text-gray-900">{{ resultData.cashRows }}</span>
            </div>
            <div v-if="resultData.excluded" class="flex items-center justify-between py-2 border-b border-gray-100">
              <div class="flex items-center gap-2 text-sm text-gray-500">
                <span>❌</span> Bills you excluded
              </div>
              <span class="font-medium text-gray-500">{{ resultData.excluded }}</span>
            </div>
          </div>

          <!-- GST summary -->
          <div class="bg-gray-900 rounded-xl p-4 text-white space-y-2">
            <div class="flex justify-between text-sm"><span class="text-gray-300">CGST</span><span class="font-medium">{{ inr(resultData.cgst) }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-300">SGST</span><span class="font-medium">{{ inr(resultData.sgst) }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-300">IGST</span><span class="font-medium">{{ inr(resultData.igst) }}</span></div>
            <div class="flex justify-between font-bold text-base border-t border-white/20 pt-2">
              <span>Total GST to pay</span><span>{{ inr(resultData.gst) }}</span>
            </div>
          </div>
        </div>

        <!-- Action buttons -->
        <button @click="download"
          class="btn-primary w-full py-4 text-base font-semibold rounded-xl flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Download again
        </button>

        <!-- How to use the file -->
        <div class="card card-body space-y-3">
          <p class="font-semibold text-gray-800">What to do with this file?</p>
          <div class="space-y-3">
            <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-xl">
              <span class="text-2xl shrink-0">👨‍💼</span>
              <div>
                <p class="text-sm font-semibold text-gray-800">Option 1 — Send to your CA</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  WhatsApp or email the <strong>{{ resultData.filename }}.json</strong> file to your accountant.
                  They will upload it on the GST website for you.
                </p>
              </div>
            </div>
            <div class="flex items-start gap-3 p-3 bg-green-50 rounded-xl">
              <span class="text-2xl shrink-0">🌐</span>
              <div>
                <p class="text-sm font-semibold text-gray-800">Option 2 — Upload yourself on gst.gov.in</p>
                <ol class="text-xs text-gray-500 mt-1 space-y-0.5 list-decimal list-inside">
                  <li>Login at gst.gov.in with your username & password</li>
                  <li>Go to Returns → Returns Dashboard</li>
                  <li>Click on GSTR-1 for this month</li>
                  <li>Click "Upload JSON" and select your file</li>
                  <li>Check the numbers and click Submit</li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <button @click="startOver" class="btn-outline w-full">
          Generate file for another month
        </button>
      </template>

    </template>
  </div>
</template>
