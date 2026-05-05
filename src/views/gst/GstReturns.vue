<script setup>
import { ref, computed, onMounted } from 'vue'
import { all, item, list } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
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
const currentFy = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
const selFy     = ref(currentFy)
const selMonth  = ref(now.getMonth() + 1)
const selYear   = ref(now.getFullYear())
const selQ      = ref(Math.ceil((now.getMonth() + 1) / 3))
const selQYear  = ref(currentFy)

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

// Available Financial Years
const availableFys = computed(() => {
  const years = []
  for (let y = currentFy; y >= currentFy - 5; y--) years.push(y)
  return years
})

// Months for selected FY
const displayMonths = computed(() => {
  const list = []
  const fy = selFy.value
  for (let m = 4; m <= 12; m++) {
    list.push({ month: m, year: fy, label: MONTH_NAMES[m], shortYear: String(fy).slice(2) })
  }
  for (let m = 1; m <= 3; m++) {
    list.push({ month: m, year: fy + 1, label: MONTH_NAMES[m], shortYear: String(fy + 1).slice(2) })
  }
  return list
})

// Quarters for selected FY
const displayQuarters = computed(() => {
  const fy = selFy.value
  return [
    { q: 1, year: fy, label: 'Apr – Jun', fyLabel: `FY ${fy}–${String(fy+1).slice(2)}` },
    { q: 2, year: fy, label: 'Jul – Sep', fyLabel: `FY ${fy}–${String(fy+1).slice(2)}` },
    { q: 3, year: fy, label: 'Oct – Dec', fyLabel: `FY ${fy}–${String(fy+1).slice(2)}` },
    { q: 4, year: fy, label: 'Jan – Mar', fyLabel: `FY ${fy}–${String(fy+1).slice(2)}` },
  ]
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

const printing = ref(false)

function generatePdf() {
  printing.value = true
  const biz = business.value
  const t    = liveTotals.value
  const r    = periodRange.value
  const selected = invoices.value.filter(inv => selectedIds.value.has(inv.id))

  const rows = selected.map(inv => `
    <tr>
      <td>${inv.number}</td>
      <td>${inv.client_name || 'Walk-in Customer'}</td>
      <td>${inv.client_gstin || '—'}</td>
      <td style="text-align:right">${inr(inv.total)}</td>
    </tr>`).join('')

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GST Report – ${r.label}</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Arial,sans-serif;color:#111;padding:32px;max-width:860px;margin:0 auto}
    .header{text-align:center;border-bottom:2px solid #e5e7eb;padding-bottom:20px;margin-bottom:24px}
    h1{font-size:22px;font-weight:800;margin-bottom:4px}
    .sub{font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;margin-bottom:12px}
    .meta{display:flex;justify-content:center;gap:32px;font-size:12px;color:#555}
    .meta b{color:#111}
    h3{font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin:0 0 10px}
    table{width:100%;border-collapse:collapse;margin-bottom:28px;font-size:13px}
    th{text-align:left;font-size:11px;text-transform:uppercase;color:#9ca3af;border-bottom:2px solid #e5e7eb;padding:6px 0}
    td{padding:9px 0;border-bottom:1px solid #f3f4f6}
    .totals{background:#111;color:#fff;border-radius:12px;padding:20px 24px;margin-top:8px}
    .t-row{display:flex;justify-content:space-between;font-size:13px;color:#9ca3af;padding:4px 0}
    .t-grand{display:flex;justify-content:space-between;font-size:18px;font-weight:800;border-top:1px solid rgba(255,255,255,.15);padding-top:12px;margin-top:8px}
    .footer{text-align:center;font-size:11px;color:#9ca3af;margin-top:28px}
    @media print{body{padding:16px}button{display:none}}
  </style>
</head>
<body>
  <div class="header">
    <h1>${biz?.name || 'Business'}</h1>
    <p class="sub">GST Return Report</p>
    <div class="meta">
      <span><b>GSTIN:</b> ${biz?.gstin || 'Not provided'}</span>
      <span><b>Period:</b> ${r.label}</span>
      <span><b>Generated:</b> ${new Date().toLocaleDateString('en-IN')}</span>
    </div>
  </div>

  <h3>${selected.length} Bills Included</h3>
  <table>
    <thead>
      <tr><th>Invoice No.</th><th>Customer</th><th>GSTIN</th><th style="text-align:right">Amount</th></tr>
    </thead>
    <tbody>${rows}</tbody>
  </table>

  <div class="totals">
    <div class="t-row"><span>CGST</span><span>${inr(t.cgst)}</span></div>
    <div class="t-row"><span>SGST</span><span>${inr(t.sgst)}</span></div>
    <div class="t-row"><span>IGST</span><span>${inr(t.igst)}</span></div>
    <div class="t-grand"><span>Total GST to pay this period</span><span>${inr(t.gst)}</span></div>
  </div>

  <p class="footer">CloudBill &nbsp;·&nbsp; ${new Date().toLocaleString('en-IN')}</p>

  <script>window.onload=function(){window.print()}<\/script>
</body>
</html>`

  const blob = new Blob([html], { type: 'text/html' })
  const url  = URL.createObjectURL(blob)
  const win  = window.open(url, '_blank')
  if (win) win.addEventListener('afterprint', () => URL.revokeObjectURL(url))
  else URL.revokeObjectURL(url)
  printing.value = false
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
  <div class="flex flex-col lg:flex-row gap-6 w-full lg:h-full lg:min-h-0">

    <!-- LEFT PANE: Header & Steps -->
    <div class="shrink-0 w-full lg:w-72 flex flex-col gap-6 lg:h-full lg:min-h-0 print:hidden">
      
      <!-- Header -->
      <div class="pt-2">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary-100 mb-3">
          <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">GST File Generator <HelpIcon section="gst" /></h1>
        <p class="text-sm text-gray-500 mt-1">Create your GST return file in 3 simple steps</p>

        <!-- Step dots (Vertical on Desktop, Horizontal on Mobile) -->
        <div class="flex lg:flex-col justify-between lg:justify-start lg:items-start gap-2 lg:gap-4 mt-6 bg-gray-50 lg:bg-transparent p-3 lg:p-0 rounded-2xl">
          
          <div class="flex items-center lg:items-start gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all shrink-0"
              :class="step===1 ? 'bg-primary-600 text-white shadow-md scale-110' : step>1 ? 'bg-success-500 text-white' : 'bg-gray-200 text-gray-500'">
              <svg v-if="step>1" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
              <span v-else>1</span>
            </div>
            <div class="flex flex-col hidden lg:flex mt-1">
              <span class="text-sm" :class="step===1?'text-primary-600 font-bold':'text-gray-500'">Choose month</span>
            </div>
          </div>
          
          <div class="hidden lg:block w-0.5 h-4 ml-3.5" :class="step>1?'bg-success-400':'bg-gray-200'"></div>
          
          <div class="flex items-center lg:items-start gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all shrink-0"
              :class="step===2 ? 'bg-primary-600 text-white shadow-md scale-110' : step>2 ? 'bg-success-500 text-white' : 'bg-gray-200 text-gray-500'">
              <svg v-if="step>2" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
              <span v-else>2</span>
            </div>
            <div class="flex flex-col hidden lg:flex mt-1">
              <span class="text-sm" :class="step===2?'text-primary-600 font-bold':'text-gray-500'">Check bills</span>
            </div>
          </div>

          <div class="hidden lg:block w-0.5 h-4 ml-3.5" :class="step>2?'bg-success-400':'bg-gray-200'"></div>

          <div class="flex items-center lg:items-start gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all shrink-0"
              :class="step===3 ? 'bg-primary-600 text-white shadow-md scale-110' : 'bg-gray-200 text-gray-500'">
              <span>3</span>
            </div>
            <div class="flex flex-col hidden lg:flex mt-1">
              <span class="text-sm" :class="step===3?'text-primary-600 font-bold':'text-gray-500'">Download</span>
            </div>
          </div>
        </div>
      </div>

      <!-- GSTIN missing notice -->
      <div v-if="!loading && !business?.gstin"
        class="flex items-start gap-3 bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-3 mt-4">
        <svg class="w-6 h-6 mt-0.5 text-yellow-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div class="text-sm text-yellow-800">
          <p class="font-semibold">Your GSTIN is missing</p>
          <p class="mt-0.5">Go to <RouterLink to="/settings" class="underline font-medium">Settings → GST Details</RouterLink> to add it.</p>
        </div>
      </div>
    </div>

    <!-- RIGHT PANE: Main Form / Wizard Area -->
    <div class="flex-1 w-full flex flex-col lg:h-full lg:min-h-0 lg:overflow-y-auto hide-scrollbar pb-20 lg:pb-6 lg:pt-2 print:overflow-visible print:pb-0">
      <div class="w-full max-w-2xl lg:ml-8 space-y-5 print:max-w-none print:w-full print:m-0">
        
        <!-- Print Header (Only visible when printing) -->
        <div class="hidden print:block print:mb-8 print:text-center border-b border-gray-200 print:pb-6">
          <h1 class="text-3xl font-black text-gray-900 mb-1">{{ business?.name || 'GST Return Report' }}</h1>
          <p class="text-sm font-semibold text-gray-500 mb-4 tracking-widest uppercase">GST RETURN REPORT</p>
          <div class="flex justify-center gap-8 text-sm text-gray-700">
            <p><span class="font-semibold text-gray-400">GSTIN:</span> {{ business?.gstin || 'Not provided' }}</p>
            <p><span class="font-semibold text-gray-400">Period:</span> {{ periodRange?.label }}</p>
            <p><span class="font-semibold text-gray-400">Generated:</span> {{ new Date().toLocaleDateString('en-IN') }}</p>
          </div>
        </div>
        
        <div v-if="loading" class="card p-10 text-center text-gray-400">Loading your data…</div>

        <template v-else>

          <!-- ══════════════════════════════════════════
               STEP 1 — Choose month
          ══════════════════════════════════════════ -->
      <div v-if="step===1" class="space-y-4">

        <!-- Choose Financial Year -->
        <div class="card card-body flex items-center justify-between">
          <p class="text-sm font-semibold text-gray-800">Select Financial Year</p>
          <select v-model="selFy" class="form-select w-36 py-2 text-sm font-semibold text-gray-800 bg-gray-50 border-transparent hover:border-gray-300 focus:border-primary-500 transition-colors">
            <option v-for="y in availableFys" :key="y" :value="y">FY {{ y }}–{{ String(y+1).slice(2) }}</option>
          </select>
        </div>

        <!-- How often do you file? -->
        <div class="card card-body">
          <p class="text-sm font-semibold text-gray-800 mb-3">How often do you file GST?</p>
          <div class="grid grid-cols-2 gap-3">
            <button @click="filingFreq='monthly'"
              class="flex flex-col items-center gap-1 py-4 rounded-xl border-2 transition-all"
              :class="filingFreq==='monthly'?'border-primary-500 bg-primary-50':'border-gray-200 hover:border-gray-300'">
              <svg class="w-8 h-8 text-primary-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <span class="text-sm font-semibold text-gray-800">Every month</span>
              <span class="text-xs text-gray-400">Monthly filer</span>
            </button>
            <button @click="filingFreq='quarterly'"
              class="flex flex-col items-center gap-1 py-4 rounded-xl border-2 transition-all"
              :class="filingFreq==='quarterly'?'border-primary-500 bg-primary-50':'border-gray-200 hover:border-gray-300'">
              <svg class="w-8 h-8 text-primary-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
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
            <button v-for="m in displayMonths" :key="`${m.month}-${m.year}`"
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
            <button v-for="q in displayQuarters" :key="`${q.q}-${q.year}`"
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
          <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> {{ fetchError }}
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
        <div class="flex items-center justify-between print:hidden">
          <div class="flex items-center gap-2">
            <span class="bg-primary-100 text-primary-700 text-sm font-semibold px-3 py-1 rounded-full">
              <svg class="w-4 h-4 inline-block text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> {{ periodRange.label }}
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
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
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
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
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
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <div>
                  <p class="font-semibold text-gray-800 text-sm">Cash sales not in this app?</p>
                  <p class="text-xs text-gray-500">Add any sales you didn't enter as a bill</p>
                </div>
              </div>
              <button @click="addCashRow"
                class="text-xs bg-amber-100 text-amber-700 font-medium px-3 py-1.5 rounded-lg hover:bg-amber-200 transition-colors print:hidden">
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
        <div class="card card-body bg-gray-900 print:bg-white print:border print:border-gray-300 text-white print:text-gray-900 rounded-2xl space-y-4 print:break-inside-avoid">
          <div class="flex items-center justify-between">
            <p class="font-semibold">Your GST for {{ periodRange.label }}</p>
            <span class="text-xs text-gray-400 print:text-gray-600">{{ selectedCount }} of {{ invoices.length }} bills included</span>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div class="bg-white/10 print:bg-gray-50 print:border print:border-gray-200 rounded-xl p-3 text-center">
              <p class="text-xs text-gray-300 mb-1">CGST</p>
              <p class="font-bold text-lg">{{ inr(liveTotals.cgst) }}</p>
            </div>
            <div class="bg-white/10 print:bg-gray-50 print:border print:border-gray-200 rounded-xl p-3 text-center">
              <p class="text-xs text-gray-300 print:text-gray-500 mb-1">SGST</p>
              <p class="font-bold text-lg">{{ inr(liveTotals.sgst) }}</p>
            </div>
            <div class="bg-white/10 print:bg-gray-50 print:border print:border-gray-200 rounded-xl p-3 text-center">
              <p class="text-xs text-gray-300 print:text-gray-500 mb-1">IGST</p>
              <p class="font-bold text-lg">{{ inr(liveTotals.igst) }}</p>
            </div>
          </div>
          <div class="border-t border-white/20 print:border-gray-200 pt-3 flex justify-between items-center">
            <p class="text-gray-300 print:text-gray-600 text-sm">Total GST to pay this period</p>
            <p class="text-2xl font-bold text-white print:text-gray-900">{{ inr(liveTotals.gst) }}</p>
          </div>
        </div>

        <div v-if="fetchError" class="flex items-start gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
          <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> {{ fetchError }}
        </div>

        <div class="flex flex-col sm:flex-row gap-3 print:hidden">
          <button @click="step=1" class="btn-outline flex-1" :disabled="building">← Back</button>
          <button @click="generatePdf" class="btn-outline flex-1 flex items-center justify-center gap-2" :disabled="building || printing">
            <svg v-if="printing" class="animate-spin w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            <svg v-else class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            {{ printing ? 'Preparing…' : 'Print Report' }}
          </button>
          <button @click="createFile" :disabled="building || selectedCount===0"
            class="btn-primary flex-1 py-3.5 text-base font-semibold rounded-xl">
            <span v-if="building" class="flex items-center justify-center gap-2">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Creating file…
            </span>
            <span v-else class="flex items-center justify-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Create my GST file</span>
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
            <h2 class="text-lg font-bold text-gray-900">Your GST file is ready!</h2>
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
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> Bills to business customers
              </div>
              <span class="font-semibold text-gray-900">{{ resultData.b2bCount }}</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-gray-100">
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg> Regular customer sales
              </div>
              <span class="font-semibold text-gray-900">{{ resultData.retailCount }}</span>
            </div>
            <div v-if="resultData.cashRows" class="flex items-center justify-between py-2 border-b border-gray-100">
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg> Cash sales added manually
              </div>
              <span class="font-semibold text-gray-900">{{ resultData.cashRows }}</span>
            </div>
            <div v-if="resultData.excluded" class="flex items-center justify-between py-2 border-b border-gray-100">
              <div class="flex items-center gap-2 text-sm text-gray-500">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Bills you excluded
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
              <svg class="w-8 h-8 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <div>
                <p class="text-sm font-semibold text-gray-800">Option 1 — Send to your CA</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  WhatsApp or email the <strong>{{ resultData.filename }}.json</strong> file to your accountant.
                  They will upload it on the GST website for you.
                </p>
              </div>
            </div>
            <div class="flex items-start gap-3 p-3 bg-green-50 rounded-xl">
              <svg class="w-8 h-8 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
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
      </div> <!-- End Main Form wrapper -->
    </div> <!-- End Right Pane -->
  </div>
</template>
