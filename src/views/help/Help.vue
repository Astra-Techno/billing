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
  { id: 'getting-started',  label: 'Getting Started',     icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
  { id: 'dashboard',        label: 'Dashboard',            icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { id: 'bills',            label: 'Bills / Invoices',     icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { id: 'quotes',           label: 'Quotations',           icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { id: 'customers',        label: 'Customers',            icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { id: 'expenses',         label: 'Expenses',             icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { id: 'products',         label: 'Products',             icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { id: 'returns',          label: 'Credit Notes',         icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
  { id: 'purchase-orders',  label: 'Purchase Orders',      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
  { id: 'delivery-challans', label: 'Delivery Challans',   icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { id: 'gst',              label: 'GST Filing',           icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { id: 'reports',          label: 'Reports',              icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { id: 'settings',         label: 'Settings',             icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
  { id: 'vs-vyapar',        label: 'CloudBill vs Vyapar',  icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
]

function scrollTo(id) {
  activeSection.value = id
  const el = document.getElementById(id)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function exportPdf() {
  const d = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
  const win = window.open('', '_blank')
  win.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CloudBill — Help Guide</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #111827; background: #fff; padding: 32px 40px; font-size: 13px; line-height: 1.6; max-width: 900px; margin: 0 auto; }
  h1 { font-size: 22px; font-weight: 900; color: #1e40af; margin-bottom: 2px; }
  .meta { font-size: 11px; color: #6b7280; margin-bottom: 28px; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; }
  h2 { font-size: 15px; font-weight: 800; color: #111827; margin: 24px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; }
  h3 { font-size: 12px; font-weight: 700; color: #374151; margin: 12px 0 4px; }
  p { color: #4b5563; margin-bottom: 8px; }
  ul { padding-left: 18px; color: #4b5563; }
  ul li { margin-bottom: 3px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0; }
  .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; background: #f9fafb; page-break-inside: avoid; }
  .card h3 { margin-top: 0; }
  .tip { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 12px; margin: 10px 0; }
  .tip strong { color: #1d4ed8; }
  .warn { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 10px 12px; margin: 10px 0; }
  .badges { display: flex; flex-wrap: wrap; gap: 6px; margin: 6px 0; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 700; }
  .badge-gray { background: #f3f4f6; color: #6b7280; }
  .badge-blue { background: #dbeafe; color: #1d4ed8; }
  .badge-green { background: #dcfce7; color: #166534; }
  .badge-yellow { background: #fef9c3; color: #854d0e; }
  .badge-red { background: #fee2e2; color: #dc2626; }
  table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
  th { background: #1f2937; color: #fff; padding: 8px 10px; text-align: left; font-weight: 600; }
  th:not(:first-child) { text-align: center; }
  td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; }
  td:not(:first-child) { text-align: center; }
  tr:nth-child(even) td { background: #f9fafb; }
  .section-header td { background: #f3f4f6; font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
  .check { color: #16a34a; font-weight: 700; }
  .cross { color: #dc2626; }
  .dim { color: #9ca3af; }
  section { page-break-inside: avoid; }
  .footer { margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 11px; color: #9ca3af; text-align: center; }
  @media print {
    body { padding: 16px 20px; }
    h2 { page-break-before: auto; }
  }
</style>
</head>
<body>
<h1>CloudBill — Complete Help Guide</h1>
<p class="meta">Generated on ${d} &nbsp;|&nbsp; cloudbill.in</p>

<section>
<h2>Getting Started</h2>
<div class="grid">
  <div class="card"><h3>Step 1 — Business Profile</h3><p>Go to Settings and fill in your business name, address, GSTIN, PAN, bank account, logo, and signature. This data appears on every document.</p></div>
  <div class="card"><h3>Step 2 — Add Products</h3><p>Go to Products and add your items with rate, HSN/SAC code, and GST rate. They auto-fill when creating invoices.</p></div>
  <div class="card"><h3>Step 3 — Create First Bill</h3><p>Tap + New Bill. Select a customer, add items, and save. Your GST-compliant invoice is ready to share instantly.</p></div>
  <div class="card"><h3>Step 4 — Share & Collect</h3><p>Tap the WhatsApp button to send a payment request. When paid, tap Record Payment to mark the invoice settled.</p></div>
</div>
</section>

<section>
<h2>Dashboard</h2>
<div class="grid">
  <div class="card"><h3>Total Outstanding</h3><p>The large number on the home screen is your total pending receivables — all unpaid invoices combined.</p></div>
  <div class="card"><h3>Overdue Alert Banner</h3><p>A red banner appears when invoices are past their due date, showing the count and total overdue amount.</p></div>
  <div class="card"><h3>Collected This Month</h3><p>Shows the sum of all payments received in the current calendar month, pulled from your payment records.</p></div>
  <div class="card"><h3>Draft Bills</h3><p>Count of bills saved but not yet sent to customers. Tap to view and send them.</p></div>
  <div class="card"><h3>People</h3><p>Recently billed customers shown as avatars. Tap any to jump to their customer profile.</p></div>
  <div class="card"><h3>Recent Activity</h3><p>Your 8 most recent invoices with status and amount. Tap any row to open that invoice.</p></div>
</div>
</section>

<section>
<h2>Bills & Invoices</h2>
<h3>Invoice Types</h3>
<div class="grid">
  <div class="card"><h3>Tax Invoice</h3><p>Standard GST invoice with CGST/SGST or IGST breakdown. For GST-registered transactions.</p></div>
  <div class="card"><h3>Bill of Supply</h3><p>No GST charged. For exempt goods/services or unregistered sellers. GST rates auto-set to 0%.</p></div>
  <div class="card"><h3>Retail Invoice</h3><p>Simplified invoice for cash/retail sales without GST column breakdown.</p></div>
  <div class="card"><h3>Export Invoice</h3><p>For goods/services exported outside India (zero-rated supply).</p></div>
</div>

<h3>Invoice Lifecycle</h3>
<div class="badges">
  <span class="badge badge-gray">Draft</span><span style="font-size:11px;color:#6b7280"> Saved, not sent &nbsp;</span>
  <span class="badge badge-blue">Sent</span><span style="font-size:11px;color:#6b7280"> Shared with customer &nbsp;</span>
  <span class="badge badge-yellow">Overdue</span><span style="font-size:11px;color:#6b7280"> Past due date &nbsp;</span>
  <span class="badge badge-green">Paid</span><span style="font-size:11px;color:#6b7280"> Fully settled</span>
</div>

<h3>Invoice Actions</h3>
<div class="grid">
  <div class="card"><h3>Primary Action (large button)</h3><p>Mark as Sent — for Draft invoices.<br>Record Payment — for Sent/Overdue invoices.</p></div>
  <div class="card"><h3>Secondary Actions (icon row)</h3><p>WhatsApp share, Email (pre-filled), Print (new tab), PDF download, Edit.</p></div>
  <div class="card"><h3>Record Payment</h3><p>Supports cash, UPI, NEFT, cheque. Record partial payments — balance tracked automatically until fully paid.</p></div>
  <div class="card"><h3>Issue Credit Note</h3><p>Open a paid/partial invoice → tap Issue Credit Note. Form opens pre-filled with all original items.</p></div>
</div>
<h3>Bulk Actions & CSV Export</h3>
<p>Tap the checklist icon in the Bills header to enter select mode. Tap rows to select, or use the top checkbox to select all.</p>
<div class="grid">
  <div class="card"><h3>Mark Paid (bulk)</h3><p>Select multiple invoices → Mark Paid → choose date and method. Records a full payment for each in one step. Useful at month-end collection.</p></div>
  <div class="card"><h3>Mark Sent (bulk)</h3><p>Select draft invoices → Mark Sent. Updates status for all selected at once without opening each invoice individually.</p></div>
  <div class="card"><h3>Export CSV</h3><p>Tap the download icon (always visible) to export all filtered invoices. In select mode, exports only selected rows. Open in Excel or share with your CA for reconciliation.</p></div>
  <div class="card"><h3>Accountant Tip</h3><p>Filter by month → select all → Export CSV. Instant data for GSTR workings, ITR preparation, or CA handoffs.</p></div>
</div>
<div class="tip"><strong>Auto GST split:</strong> Same state → CGST + SGST. Different state → IGST. Bill of Supply → no GST. Set automatically based on bill type and customer state.</div>
</section>

<section>
<h2>Quotations</h2>
<p>Send price proposals before the customer confirms. Once accepted, convert to a Tax Invoice in one tap — no re-entry.</p>
<div class="badges">
  <span class="badge badge-gray">Draft</span>&nbsp;
  <span class="badge badge-blue">Sent</span>&nbsp;
  <span class="badge badge-green">Accepted</span>&nbsp;
  <span class="badge badge-red">Declined</span>&nbsp;
  <span class="badge badge-yellow">Expired</span>
</div>
<div class="grid">
  <div class="card"><h3>Convert to Invoice</h3><p>Open an accepted quotation → tap Convert to Invoice. All items and taxes are copied instantly.</p></div>
  <div class="card"><h3>Validity Date</h3><p>Set a validity date. Expired quotes are flagged automatically for follow-up.</p></div>
</div>
</section>

<section>
<h2>Customers</h2>
<div class="grid">
  <div class="card"><h3>Outstanding Balance Badge</h3><p>Each customer row shows a red badge with their current unpaid balance across all invoices.</p></div>
  <div class="card"><h3>Quick Add Form</h3><p>Only Name and Mobile are required. Tap "Add email, GST, address…" to reveal optional fields.</p></div>
  <div class="card"><h3>Account Statement</h3><p>Full billing history — all invoices, payments received, and running outstanding balance.</p></div>
  <div class="card"><h3>State for GST</h3><p>Set the customer's state once. CGST+SGST or IGST is applied automatically on every invoice.</p></div>
</div>
<div class="tip"><strong>Suppliers too:</strong> Store your suppliers in the same Customers list. Pick them when creating Purchase Orders.</div>
</section>

<section>
<h2>Credit Notes</h2>
<p>A Credit Note reduces the amount owed. Use it for returns, overcharges, or post-sale discounts.</p>
<div class="grid">
  <div class="card"><h3>One-Tap from Invoice</h3><p>Open any paid or partially-paid invoice → tap Issue Credit Note. Form is pre-filled with original items.</p></div>
  <div class="card"><h3>Link to Original Invoice</h3><p>Each credit note references the original invoice number — clickable link in the Credit Notes list.</p></div>
  <div class="card"><h3>GST Reversal</h3><p>CGST/SGST/IGST reversal amounts are calculated so your GST returns reflect net liability correctly.</p></div>
  <div class="card"><h3>When to Use</h3><ul><li>Customer returns goods</li><li>You overcharged on a previous bill</li><li>Post-sale discount or price correction</li></ul></div>
</div>
<div class="warn"><strong>Never delete invoices.</strong> Always issue a Credit Note instead — deleting breaks your invoice sequence and GST returns.</div>
</section>

<section>
<h2>Purchase Orders</h2>
<p>A Purchase Order (PO) is a document you send to a supplier confirming what you want to buy, at what price, and when.</p>
<div class="grid">
  <div class="card"><h3>PO Lifecycle</h3><div class="badges"><span class="badge badge-gray">Draft</span>&nbsp;<span class="badge badge-blue">Sent</span>&nbsp;<span class="badge badge-green">Received</span>&nbsp;<span class="badge badge-red">Cancelled</span></div></div>
  <div class="card"><h3>Supplier = Customer Record</h3><p>Suppliers are stored in your Customers list. Add a supplier the same way you add a customer.</p></div>
</div>
</section>

<section>
<h2>Delivery Challans</h2>
<p>A transport document listing goods being delivered. No prices or GST — purely for logistics and proof of dispatch.</p>
<div class="grid">
  <div class="card"><h3>Convert to Invoice</h3><p>Open any challan → tap Convert to Invoice. A new invoice opens pre-filled with the same customer and items — just add prices.</p></div>
  <div class="card"><h3>Typical Workflow</h3><p>Create DC → Issue (dispatch goods) → Mark Delivered → Convert to Invoice (bill the customer).</p></div>
  <div class="card"><h3>Transport Details</h3><p>Record vehicle number, driver name, and destination for each challan.</p></div>
  <div class="card"><h3>DC Lifecycle</h3><div class="badges"><span class="badge badge-gray">Draft</span>&nbsp;<span class="badge badge-blue">Issued</span>&nbsp;<span class="badge badge-green">Delivered</span>&nbsp;<span class="badge badge-red">Cancelled</span></div></div>
</div>
</section>

<section>
<h2>GST Filing</h2>
<div class="grid">
  <div class="card"><h3>GSTR-1 Summary</h3><p>Outward supplies breakdown — B2B invoices with GSTIN, B2C sales, credit notes, nil-rated supplies.</p></div>
  <div class="card"><h3>GSTR-3B Summary</h3><p>Net GST liability: output tax on sales minus input tax credit (ITC) from tracked expenses.</p></div>
  <div class="card"><h3>Tax Rate Breakup</h3><p>Totals split by slab (0%, 5%, 12%, 18%, 28%) with CGST, SGST, IGST shown separately.</p></div>
  <div class="card"><h3>Period Selection</h3><p>Switch between monthly and quarterly views to match your GST return filing frequency.</p></div>
</div>
<div class="tip"><strong>Filing happens on the GST portal.</strong> CloudBill prepares your summary — you log in to gst.gov.in to submit the actual return.</div>
</section>

<section>
<h2>Settings</h2>
<div class="grid">
  <div class="card"><h3>Business Profile</h3><p>Business name, address, GSTIN, PAN, mobile, email, state. Appears on every printed document.</p></div>
  <div class="card"><h3>Logo & Signature</h3><p>Upload your logo and authorised signature — both appear on printed invoices.</p></div>
  <div class="card"><h3>Bank & UPI Details</h3><p>Bank account, IFSC, UPI ID. Appears in payment section of invoices and WhatsApp messages.</p></div>
  <div class="card"><h3>Invoice Numbering</h3><p>Set a custom prefix (e.g. INV- or 2024-) and starting number for your invoice sequence.</p></div>
  <div class="card"><h3>Default Notes & Terms</h3><p>Standard payment terms or thank-you message that pre-fills on every new invoice.</p></div>
  <div class="card"><h3>Settings Layout</h3><p>Desktop: vertical tab sidebar. Mobile: horizontally scrollable tab strip. All settings in one place.</p></div>
</div>
<div class="tip"><strong>Do this first!</strong> Complete your business profile before creating your first invoice — incomplete profiles result in missing GSTIN or bank details on printed documents.</div>
</section>

<section>
<h2>CloudBill vs Vyapar</h2>
<table>
  <thead>
    <tr><th>Feature</th><th>CloudBill</th><th>Vyapar</th></tr>
  </thead>
  <tbody>
    <tr class="section-header"><td colspan="3">Billing & Invoicing</td></tr>
    <tr><td>GST Tax Invoice (CGST/SGST/IGST)</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Bill of Supply (non-GST)</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Quotations / Estimates</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Credit Notes</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Multiple invoice templates</td><td><span class="dim">1 clean template</span></td><td>10+ templates</td></tr>
    <tr class="section-header"><td colspan="3">Payments & Dues</td></tr>
    <tr><td>Partial payment tracking</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Outstanding balance per customer</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Automated payment reminders</td><td><span class="dim">Manual via WhatsApp</span></td><td>Paid</td></tr>
    <tr><td>UPI ID on invoices & WhatsApp</td><td><span class="check">✓ Free</span></td><td><span class="check">✓</span></td></tr>
    <tr class="section-header"><td colspan="3">Sharing & Export</td></tr>
    <tr><td>WhatsApp share with amount + UPI</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Email share (pre-filled)</td><td><span class="check">✓ Free</span></td><td>Paid</td></tr>
    <tr><td>PDF download / Print</td><td><span class="check">✓ Free</span></td><td>Paid</td></tr>
    <tr><td>Invoice branding (no watermark)</td><td><span class="check">✓ Always clean</span></td><td><span class="dim">Free has watermark</span></td></tr>
    <tr class="section-header"><td colspan="3">Documents</td></tr>
    <tr><td>Purchase Orders</td><td><span class="check">✓ Free</span></td><td><span class="check">✓</span></td></tr>
    <tr><td>Delivery Challans</td><td><span class="check">✓ Free</span></td><td><span class="check">✓</span></td></tr>
    <tr><td>Convert Challan to Invoice</td><td><span class="check">✓ Free</span></td><td><span class="check">✓</span></td></tr>
    <tr class="section-header"><td colspan="3">GST & Accounting</td></tr>
    <tr><td>GSTR-1 / GSTR-3B summary</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>GSTR JSON export</td><td><span class="dim">—</span></td><td>Paid</td></tr>
    <tr><td>Expense tracking & ITC</td><td><span class="check">✓ Free</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr class="section-header"><td colspan="3">Inventory & Platform</td></tr>
    <tr><td>Stock quantity tracking</td><td><span class="dim">—</span></td><td><span class="check">✓ Free</span></td></tr>
    <tr><td>Works offline</td><td><span class="dim">—</span></td><td><span class="check">✓ Android</span></td></tr>
    <tr><td>Price</td><td><strong style="color:#16a34a">Free</strong></td><td>₹3,399 / year</td></tr>
  </tbody>
</table>
</section>

<p class="footer">CloudBill &nbsp;·&nbsp; Complete Help Guide &nbsp;·&nbsp; Generated ${d}</p>
<script>window.onload = function() { window.print() }<\/script>
</body>
</html>`)
  win.document.close()
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
        <p class="text-primary-100 text-base sm:text-lg">Explore our guides and find answers to all your questions about CloudBill.</p>

        <div class="mt-8 flex flex-wrap gap-3 animate-fade-in-up">
          <!-- Search bar -->
          <div class="relative flex-1 min-w-[200px] max-w-md">
            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" placeholder="Search guides, invoices, GST..." class="w-full bg-white text-gray-900 border-0 rounded-2xl py-4 pl-12 pr-4 shadow-xl focus:ring-2 focus:ring-primary-300 placeholder-gray-400 text-sm font-medium transition-all" />
          </div>
          <!-- Export PDF button -->
          <button @click="exportPdf"
            class="flex items-center gap-2 bg-white/15 hover:bg-white/25 active:scale-95 backdrop-blur-md text-white font-bold text-sm rounded-2xl px-5 py-4 border border-white/20 transition-all shadow-lg shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export PDF
          </button>
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
                <p class="font-bold text-blue-900 mb-1.5 text-base">Welcome to CloudBill! 🎉</p>
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
                  <p class="font-bold text-gray-900 mb-1">Total Outstanding</p>
                  <p class="text-gray-500 leading-relaxed">The large number at the top is your total pending receivables — money customers owe you right now.</p>
                </div>
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 hover:shadow-md transition-shadow">
                  <p class="font-bold text-gray-900 mb-1">Overdue Alert Banner</p>
                  <p class="text-gray-500 leading-relaxed">A red warning banner appears at the top when invoices are past their due date, showing the count and total overdue amount. Tap <strong>View All</strong> to see the full list.</p>
                </div>
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 hover:shadow-md transition-shadow">
                  <p class="font-bold text-gray-900 mb-1">People (Quick Access)</p>
                  <p class="text-gray-500 leading-relaxed">Horizontally scrollable row of recently billed customers. Tap any avatar to jump to their latest invoice.</p>
                </div>
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 hover:shadow-md transition-shadow">
                  <p class="font-bold text-gray-900 mb-1">Recent Activity</p>
                  <p class="text-gray-500 leading-relaxed">See your 8 most recent invoices with amount due and status. Tap any row to open that invoice.</p>
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
                <p class="font-bold text-gray-900 mb-3">Invoice Actions</p>
                <p class="text-gray-500 mb-3 text-sm">Actions are arranged by priority. The most important action is shown as a large primary button; secondary actions (Share, Email, Print, PDF, Edit) appear as a compact icon row below.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm text-gray-600">
                  <div class="bg-primary-50 border border-primary-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center">
                    <span class="text-2xl mb-1">📤</span>
                    <span class="font-semibold text-gray-800">Mark as Sent</span>
                    <span class="text-xs text-gray-400 mt-0.5">Primary action for Draft invoices</span>
                  </div>
                  <div class="bg-emerald-50 border border-emerald-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center">
                    <span class="text-2xl mb-1">💳</span>
                    <span class="font-semibold text-gray-800">Record Payment</span>
                    <span class="text-xs text-gray-400 mt-0.5">Primary action for Sent/Overdue</span>
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
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-gray-200 transition-colors">
                    <span class="text-2xl mb-1">🖨️</span>
                    <span class="font-semibold text-gray-800">Print</span>
                    <span class="text-xs text-gray-400 mt-0.5">Opens print dialog in new tab (works on mobile)</span>
                  </div>
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-red-200 transition-colors">
                    <span class="text-2xl mb-1">📄</span>
                    <span class="font-semibold text-gray-800">PDF Download</span>
                    <span class="text-xs text-gray-400 mt-0.5">Download invoice as PDF file</span>
                  </div>
                  <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-3 flex flex-col items-center text-center hover:border-amber-200 transition-colors">
                    <span class="text-2xl mb-1">✏️</span>
                    <span class="font-semibold text-gray-800">Edit</span>
                    <span class="text-xs text-gray-400 mt-0.5">Modify any non-cancelled invoice</span>
                  </div>
                </div>
              </div>

              <!-- Bulk Actions & CSV Export -->
              <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                <p class="font-bold text-gray-900 mb-3">Bulk Actions & Export</p>
                <p class="text-gray-500 text-sm mb-4 leading-relaxed">Use bulk select to act on multiple invoices at once — ideal for accountants collecting payments at month-end or exporting data for your CA.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                  <div class="bg-white border border-gray-100 rounded-xl p-3 flex flex-col gap-1">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="w-7 h-7 rounded-lg bg-primary-50 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                      </div>
                      <span class="font-semibold text-gray-800">Select Mode</span>
                    </div>
                    <p class="text-xs text-gray-500">Tap the checklist icon in the Bills header to enter select mode. Tap rows to check/uncheck. Use the top checkbox to select all.</p>
                  </div>
                  <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 flex flex-col gap-1">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      </div>
                      <span class="font-semibold text-emerald-900">Mark Paid</span>
                    </div>
                    <p class="text-xs text-emerald-800">Select multiple invoices → tap Mark Paid → choose payment date and method. Records a full payment for each selected invoice in one step.</p>
                  </div>
                  <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 flex flex-col gap-1">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                      </div>
                      <span class="font-semibold text-blue-900">Export CSV</span>
                    </div>
                    <p class="text-xs text-blue-800">Tap the download icon (always visible) to export all filtered invoices as CSV. In select mode, exports only selected rows. Use with Excel or share with your CA.</p>
                  </div>
                </div>
                <div class="mt-3 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-xs text-amber-800">
                  <strong>Accountant tip:</strong> Filter by month or date range → select all → Export CSV. Use this at month-end for reconciliation or to prepare GSTR workings in Excel.
                </div>
              </div>

              <!-- Issue Credit Note shortcut -->
              <div class="bg-rose-50 border border-rose-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-rose-800 leading-relaxed"><strong>Issue Credit Note from an Invoice:</strong> Open any paid or partially-paid invoice and tap <strong>Issue Credit Note</strong> at the bottom. The credit note form opens pre-filled with all the original items — you only need to adjust quantities or amounts before saving.</p>
              </div>

              <!-- Partial Payments -->
              <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 shadow-sm">
                <p class="font-bold text-emerald-900 mb-1.5">Partial Payments & Balance Tracking</p>
                <p class="text-sm text-emerald-800 leading-relaxed">Tap <strong>Record Payment</strong> on any sent invoice to log cash, UPI, NEFT, or cheque receipts. You can record multiple payments over time — CloudBill automatically tracks the running balance due and marks the invoice as Paid when fully settled.</p>
              </div>

              <!-- Auto GST -->
              <div class="bg-green-50 border border-green-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-green-800 leading-relaxed"><strong>Auto GST split:</strong> Same state → CGST + SGST. Different state → IGST. Bill of Supply → no GST. CloudBill handles all cases automatically based on your bill type and customer's state.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Quotations ── -->
        <section :id="'quotes'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-indigo-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Quotations</h2>
                <p class="text-sm text-gray-500 font-medium">Send price estimates before invoicing</p>
              </div>
            </div>
            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">A Quotation (or Estimate) lets you send a price proposal to a potential customer before they confirm the order. Once accepted, convert it to a Tax Invoice in one tap.</p>

              <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                <p class="font-bold text-gray-900 mb-3">Quotation Lifecycle</p>
                <div class="flex flex-wrap gap-2 text-xs">
                  <div class="flex items-center gap-2"><span class="badge badge-gray">Draft</span><span class="text-gray-500">Being prepared</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-blue">Sent</span><span class="text-gray-500">Shared with customer</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-green">Accepted</span><span class="text-gray-500">Customer approved</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-red">Declined</span><span class="text-gray-500">Customer rejected</span></div>
                  <div class="flex items-center gap-2"><span class="badge badge-yellow">Expired</span><span class="text-gray-500">Past validity date</span></div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Convert to Invoice</p>
                  <p class="text-gray-500">Open an accepted quotation and tap <strong>Convert to Invoice</strong>. All items, customer details, and taxes are copied — no re-entry needed.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Validity Date</p>
                  <p class="text-gray-500">Set a validity date on each quote. Expired quotes are flagged automatically so you know which estimates need follow-up.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Share via WhatsApp</p>
                  <p class="text-gray-500">Send a professional quotation message to your customer instantly via WhatsApp with the total and validity date.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Terms & Notes</p>
                  <p class="text-gray-500">Add custom terms, payment conditions, and a personal note to each quotation before sharing.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Customers ── -->
        <section :id="'customers'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-teal-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-teal-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Customers</h2>
                <p class="text-sm text-gray-500 font-medium">Manage your client directory</p>
              </div>
            </div>
            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">Your customer list is the foundation of billing. Every invoice, quotation, and delivery challan is linked to a customer record. Keeping records complete saves time and reduces errors.</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Outstanding Balance Badge</p>
                  <p class="text-gray-500">Each customer row shows a red badge with their current outstanding balance (unpaid invoices). Empty if the customer is fully paid up.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Account Statement</p>
                  <p class="text-gray-500">View a customer's full billing history — all invoices, payments received, and outstanding balance in one place.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Quick Add Form</p>
                  <p class="text-gray-500">Only <strong>Name</strong> and <strong>Mobile</strong> are required to save a customer. Tap <em>"Add email, GST, address…"</em> to reveal the optional fields — keeps the form fast for new customers.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">State for GST</p>
                  <p class="text-gray-500">Setting the correct state on a customer record ensures CloudBill applies CGST+SGST or IGST automatically on every invoice.</p>
                </div>
              </div>

              <div class="bg-teal-50 border border-teal-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-teal-900 leading-relaxed"><strong>Suppliers too:</strong> Use the same Customers list to store your suppliers. When creating a Purchase Order, just pick the supplier from this list.</p>
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
                  <p class="text-gray-500">DC items show only description, HSN/SAC, quantity, and unit — no financial data needed for a transport slip.</p>
                </div>
                <div class="bg-blue-50 border border-blue-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-blue-900 mb-1">Convert to Invoice</p>
                  <p class="text-blue-700">Open any challan and tap <strong>Convert to Invoice</strong> (or the <em>To Invoice</em> icon). A new invoice form opens pre-filled with the same customer and all items — just add prices and GST rates, then save.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Actions by Status</p>
                  <div class="space-y-1 text-xs text-gray-500 mt-1">
                    <p><strong class="text-gray-700">Draft:</strong> Issue → dispatches goods</p>
                    <p><strong class="text-gray-700">Issued:</strong> Mark Delivered → confirms receipt</p>
                    <p><strong class="text-gray-700">Delivered:</strong> Convert to Invoice → creates a bill</p>
                  </div>
                </div>
              </div>

              <div class="bg-cyan-50 border border-cyan-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-cyan-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-cyan-900 leading-relaxed"><strong>Typical workflow:</strong> Create DC → Issue (dispatch goods) → Mark Delivered → Convert to Invoice (bill the customer). The DC is a transport slip — it is not a valid GST tax document on its own.</p>
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

        <!-- ── Products ── -->
        <section :id="'products'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-lime-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-lime-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-lime-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Products & Services</h2>
                <p class="text-sm text-gray-500 font-medium">Build your catalog for faster billing</p>
              </div>
            </div>
            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">Add your products and services once — then pick them from a list while creating any invoice or quotation. Description, price, HSN/SAC, unit, and GST rate all fill in automatically.</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Product Fields</p>
                  <p class="text-gray-500">Name, type (goods/service), sale price, unit (Nos/Kg/Ltr/Hrs…), HSN/SAC code, and default GST rate.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Quick-Add from Invoice</p>
                  <p class="text-gray-500">While adding items in an invoice, tap the <strong>+</strong> button next to the product picker to create a new product on the spot.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">HSN / SAC Codes</p>
                  <p class="text-gray-500">HSN for goods, SAC for services. These are mandatory for GST invoices above certain turnover thresholds. Enter them once per product.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Units of Measure</p>
                  <p class="text-gray-500">Choose from Nos, Kg, Ltr, Hrs, Pcs, Mtr, Box, Set, Pair — or leave as Nos for general items and services.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Credit Notes ── -->
        <section :id="'returns'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-rose-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-rose-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Credit Notes</h2>
                <p class="text-sm text-gray-500 font-medium">Issue credit notes for returns and corrections</p>
              </div>
            </div>
            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">A Credit Note reduces the amount owed by a customer. Use it when goods are returned, when you've overcharged, or when a discount is given after an invoice has been issued.</p>

              <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                <p class="font-bold text-gray-900 mb-3">When to Use a Credit Note</p>
                <div class="space-y-2">
                  <div class="flex items-start gap-2"><div class="w-1.5 h-1.5 rounded-full bg-rose-400 mt-1.5 shrink-0"></div><span class="text-gray-600">Customer returns goods (full or partial)</span></div>
                  <div class="flex items-start gap-2"><div class="w-1.5 h-1.5 rounded-full bg-rose-400 mt-1.5 shrink-0"></div><span class="text-gray-600">You overcharged on a previous invoice</span></div>
                  <div class="flex items-start gap-2"><div class="w-1.5 h-1.5 rounded-full bg-rose-400 mt-1.5 shrink-0"></div><span class="text-gray-600">Post-sale discount or price correction</span></div>
                  <div class="flex items-start gap-2"><div class="w-1.5 h-1.5 rounded-full bg-rose-400 mt-1.5 shrink-0"></div><span class="text-gray-600">Cancellation of a partially delivered order</span></div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">One-Tap from Invoice</p>
                  <p class="text-gray-500">Open any paid or partially-paid invoice and tap <strong>Issue Credit Note</strong>. The form opens pre-filled with all the original items, so you only adjust quantities or amounts.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Link to Original Invoice</p>
                  <p class="text-gray-500">Each credit note references the original invoice. In the Credit Notes list, the invoice number is a clickable link that takes you directly to that invoice.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">GST Reversal</p>
                  <p class="text-gray-500">Credit notes include the GST reversal amounts (CGST/SGST/IGST) so your GST returns reflect the correct net liability.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Credit Note Lifecycle</p>
                  <div class="flex flex-wrap gap-2 text-xs mt-1">
                    <div class="flex items-center gap-1"><span class="badge badge-gray">Draft</span><span class="text-gray-400">Saved</span></div>
                    <div class="flex items-center gap-1"><span class="badge badge-blue">Issued</span><span class="text-gray-400">Sent to customer</span></div>
                    <div class="flex items-center gap-1"><span class="badge badge-green">Adjusted</span><span class="text-gray-400">Applied to invoice</span></div>
                  </div>
                </div>
              </div>

              <div class="bg-rose-50 border border-rose-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-rose-800 leading-relaxed"><strong>Never delete invoices.</strong> Always issue a Credit Note instead. Deleting a sent invoice breaks your numbering sequence and can cause discrepancies in your GST returns.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ── GST Filing ── -->
        <section :id="'gst'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-yellow-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-yellow-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-yellow-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">GST Filing</h2>
                <p class="text-sm text-gray-500 font-medium">GSTR-1, GSTR-3B summaries and data export</p>
              </div>
            </div>
            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">CloudBill auto-compiles your GST return data from invoices, credit notes, and expenses. Use this section to review totals before filing on the GST portal.</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">GSTR-1 Summary</p>
                  <p class="text-gray-500">Outward supplies breakdown — B2B invoices with customer GSTIN, B2C sales, credit notes, and nil-rated supplies.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">GSTR-3B Summary</p>
                  <p class="text-gray-500">Net GST liability summary: output tax on sales minus input tax credit (ITC) from tracked expenses.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Tax Rate Breakup</p>
                  <p class="text-gray-500">Totals split by GST slab (0%, 5%, 12%, 18%, 28%) with CGST, SGST, and IGST shown separately.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Period Selection</p>
                  <p class="text-gray-500">Switch between monthly and quarterly views to match your GST return filing frequency.</p>
                </div>
              </div>

              <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-yellow-900 leading-relaxed"><strong>Filing happens on the GST portal.</strong> CloudBill prepares and displays your summary — you then log in to <strong>gst.gov.in</strong> to submit the actual return. Always review totals carefully before filing.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Reports ── -->
        <section :id="'reports'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-sky-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-sky-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Reports</h2>
                <p class="text-sm text-gray-500 font-medium">Business insights and financial summaries</p>
              </div>
            </div>
            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">Reports give you a clear picture of your business performance — revenue, expenses, outstanding dues, and profit — all filterable by date range.</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Sales Report</p>
                  <p class="text-gray-500">Total invoiced amount, GST collected, and net taxable value for any date range. Filter by customer or invoice type.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Receivables (Due)</p>
                  <p class="text-gray-500">List of all unpaid and partially paid invoices with days overdue, so you know exactly who owes how much.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Expense Report</p>
                  <p class="text-gray-500">Break down your spending by category (Rent, Travel, Inventory, etc.) for any period.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Profit & Loss</p>
                  <p class="text-gray-500">Revenue minus expenses = net profit. At a glance, see whether your business is in the green this month.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Settings ── -->
        <section :id="'settings'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-gray-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-gray-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">Settings</h2>
                <p class="text-sm text-gray-500 font-medium">Business profile, branding, and preferences</p>
              </div>
            </div>
            <div class="space-y-5 text-sm text-gray-700">
              <p class="text-gray-500 leading-relaxed">Settings is where you configure your business identity. Everything you enter here appears on every invoice, quotation, and document automatically.</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Business Profile</p>
                  <p class="text-gray-500">Business name, address, GSTIN, PAN, mobile, email, and state. These appear on every printed document.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Logo & Signature</p>
                  <p class="text-gray-500">Upload your business logo and authorised signature image. Both appear on printed invoices for a professional look.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Bank & UPI Details</p>
                  <p class="text-gray-500">Enter your bank account, IFSC, and UPI ID. These appear in the payment section of every invoice and in WhatsApp share messages.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Invoice Numbering</p>
                  <p class="text-gray-500">Set a custom prefix (e.g. <em>INV-</em> or <em>2024-</em>) and starting number for your invoice sequence.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">Default Notes & Terms</p>
                  <p class="text-gray-500">Add standard payment terms or a thank-you message that pre-fills on every new invoice.</p>
                </div>
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
                  <p class="font-bold text-gray-900 mb-1">User Account</p>
                  <p class="text-gray-500">Update your login name, email address, and password from the Settings page.</p>
                </div>
              </div>

              <div class="bg-primary-50 border border-primary-100 rounded-xl p-4 flex gap-3 shadow-sm">
                <svg class="w-5 h-5 text-primary-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-primary-900 leading-relaxed"><strong>Do this first!</strong> Complete your business profile in Settings before creating your first invoice. Incomplete profiles result in missing GSTIN or bank details on printed documents.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ── CloudBill vs Vyapar ── -->
        <section :id="'vs-vyapar'" class="card p-8 scroll-mt-6 border-0 shadow-soft relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-32 h-32 bg-primary-50 rounded-bl-full -mr-10 -mt-10 transition-transform group-hover:scale-110"></div>
          <div class="relative z-10">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-12 h-12 rounded-[1rem] bg-primary-100 flex items-center justify-center shrink-0 shadow-inner">
                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-gray-900">CloudBill vs Vyapar</h2>
                <p class="text-sm text-gray-500 font-medium">How we compare to the popular alternative</p>
              </div>
            </div>

            <div class="space-y-7 text-sm text-gray-700">

              <!-- Headline banner -->
              <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-2xl p-5 text-white">
                <p class="font-extrabold text-lg mb-1">Clean, fast, and truly free — built for Indian SMBs</p>
                <p class="text-primary-100 text-sm leading-relaxed">Vyapar is a solid app with many features. CloudBill is for businesses that want a fast, modern billing experience without a yearly subscription or cluttered screens.</p>
              </div>

              <!-- Feature comparison table -->
              <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                  <thead>
                    <tr class="bg-gray-800 text-white">
                      <th class="px-4 py-3 text-left font-semibold rounded-tl-xl">Feature</th>
                      <th class="px-4 py-3 text-center font-semibold text-primary-200">CloudBill</th>
                      <th class="px-4 py-3 text-center font-semibold text-gray-300 rounded-tr-xl">Vyapar</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <!-- Billing -->
                    <tr class="bg-gray-50"><td colspan="3" class="px-4 py-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest">Billing & Invoicing</td></tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">GST Tax Invoice (CGST/SGST/IGST)</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Bill of Supply (non-GST)</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Quotations / Estimates</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Recurring / Auto-repeat invoices</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center text-gray-500">Paid</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Credit Notes (returns & adjustments)</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Edit invoice after sending</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Anytime</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Multiple invoice templates</td>
                      <td class="px-4 py-3 text-center text-gray-400">1 clean template</td>
                      <td class="px-4 py-3 text-center text-gray-600">10+ templates</td>
                    </tr>
                    <!-- Payments -->
                    <tr class="bg-gray-50"><td colspan="3" class="px-4 py-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest">Payments & Dues</td></tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Partial payment tracking</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Balance due on invoice</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Automated payment reminders</td>
                      <td class="px-4 py-3 text-center text-gray-400">Manual via WhatsApp</td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">UPI ID on invoices & WhatsApp</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓</span></td>
                    </tr>
                    <!-- Sharing -->
                    <tr class="bg-gray-50"><td colspan="3" class="px-4 py-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest">Sharing & Export</td></tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">WhatsApp share with amount + UPI</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Email share (pre-filled)</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">PDF download / Print</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Invoice branding (no watermark)</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Always clean</span></td>
                      <td class="px-4 py-3 text-center text-gray-500">Paid (free has "Powered by Vyapar")</td>
                    </tr>
                    <!-- Docs -->
                    <tr class="bg-gray-50"><td colspan="3" class="px-4 py-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest">Documents</td></tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Purchase Orders</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Delivery Challans</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓</span></td>
                    </tr>
                    <!-- GST & Accounting -->
                    <tr class="bg-gray-50"><td colspan="3" class="px-4 py-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest">GST & Accounting</td></tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">GSTR-1 / GSTR-3B summary</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">GSTR JSON export for portal</td>
                      <td class="px-4 py-3 text-center text-gray-400">—</td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Expense tracking & ITC</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Balance Sheet / P&amp;L</td>
                      <td class="px-4 py-3 text-center text-gray-400">Reports only</td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <!-- Inventory -->
                    <tr class="bg-gray-50"><td colspan="3" class="px-4 py-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest">Inventory</td></tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Product / service catalog</td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                      <td class="px-4 py-3 text-center"><span class="text-emerald-600 font-bold">✓ Free</span></td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Stock quantity tracking</td>
                      <td class="px-4 py-3 text-center text-gray-400">—</td>
                      <td class="px-4 py-3 text-center text-gray-600">✓ Free</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Low stock alerts</td>
                      <td class="px-4 py-3 text-center text-gray-400">—</td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Barcode scanning</td>
                      <td class="px-4 py-3 text-center text-gray-400">—</td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <!-- Platform -->
                    <tr class="bg-gray-50"><td colspan="3" class="px-4 py-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest">Platform & Pricing</td></tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Works offline</td>
                      <td class="px-4 py-3 text-center text-gray-400">—</td>
                      <td class="px-4 py-3 text-center text-gray-600">✓ Android app</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Multiple users / devices</td>
                      <td class="px-4 py-3 text-center text-gray-400">—</td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="px-4 py-3 text-gray-700 font-medium">Online store for customers</td>
                      <td class="px-4 py-3 text-center text-gray-400">—</td>
                      <td class="px-4 py-3 text-center text-gray-600">Paid</td>
                    </tr>
                    <tr class="bg-primary-50 hover:bg-primary-100 transition-colors">
                      <td class="px-4 py-3 text-gray-900 font-bold">Price</td>
                      <td class="px-4 py-3 text-center font-extrabold text-emerald-700 text-base">Free</td>
                      <td class="px-4 py-3 text-center font-semibold text-gray-700">₹3,399 / year</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Where Vyapar wins -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-amber-50 border border-amber-100 rounded-2xl p-5">
                  <p class="font-bold text-amber-900 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Choose Vyapar if you need…
                  </p>
                  <ul class="space-y-1.5 text-sm text-amber-800">
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Inventory / stock tracking with low-stock alerts</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Barcode scanning at checkout</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Offline access without internet</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Multi-user access across devices</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>GSTR JSON export directly for the portal</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Full balance sheet & double-entry accounting</li>
                  </ul>
                </div>
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5">
                  <p class="font-bold text-emerald-900 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Choose CloudBill if you want…
                  </p>
                  <ul class="space-y-1.5 text-sm text-emerald-800">
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Zero cost — no yearly subscription ever</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Clean invoices with no "Powered by" watermark</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Fast, modern mobile-first experience</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Email share with pre-filled message (free)</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>PDF download free (no paid plan needed)</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5">•</span>Simple setup — ready in under 5 minutes</li>
                  </ul>
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
                Can I use CloudBill without a GSTIN?
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
                Use a Delivery Challan when you dispatch goods before raising the invoice — for example, goods sent on approval, goods delivered in batches, or returnable items. A DC contains the vehicle number, driver name, destination, and item quantities (no prices). Once goods are delivered, tap <strong>Convert to Invoice</strong> directly from the challan — all items carry over automatically.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                How do I issue a Credit Note for a returned item?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                Open the original invoice and tap <strong>Issue Credit Note</strong> at the bottom of the page. The credit note form opens pre-filled with all the original items — adjust quantities or amounts for the returned goods and save. The credit note will automatically reference the original invoice number for GST compliance.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                Why does the outstanding balance on the customer list differ from what I see inside?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                They should always match. The outstanding balance badge on the customer list and the figure inside the customer detail both come from the same database query — the sum of <em>amount_due</em> across all invoices with status Sent, Partial, or Overdue. If you notice a discrepancy, a page refresh will recalculate.
              </div>
            </details>

            <details class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <summary class="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 select-none hover:bg-gray-50 transition-colors">
                How does the "Collected this month" figure get calculated?
                <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-open:bg-primary-100 group-open:text-primary-600 transition-colors">
                  <svg class="w-4 h-4 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </span>
              </summary>
              <div class="px-5 pb-5 pt-1 text-gray-500 leading-relaxed border-t border-gray-50 mt-1">
                It is the sum of all individual payment entries (cash, UPI, NEFT, cheque) recorded in the current calendar month via the <strong>Record Payment</strong> button — not just fully-paid invoices. Partial payments are included too.
              </div>
            </details>
          </div>
        </section>

        <!-- Footer Note -->
        <div class="mt-12 text-center pb-8 opacity-70">
          <div class="w-16 h-1 bg-gray-200 rounded-full mx-auto mb-6"></div>
          <p class="text-sm font-bold text-gray-800">CloudBill</p>
          <p class="text-xs text-gray-500 mt-1">Proudly built for modern Indian businesses.</p>
          <button @click="exportPdf" class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-primary-600 hover:text-primary-700 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Help Guide as PDF
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
