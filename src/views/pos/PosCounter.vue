<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { all, item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { today, fmtDateShort } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'

const products=ref([]), clients=ref([]), locations=ref([]), stockRows=ref([]), productLocations=ref([]), inventoryMode=ref('none')
const recentSales=ref([]), showRecent=ref(false), recentLoading=ref(false)
const cart=ref([]), category=ref('All'), search=ref(''), clientSearch=ref(''), selectedClient=ref(null)
const customerName=ref(''), customerPhone=ref(''), note=ref(''), paymentMethod=ref('cash'), locationId=ref(null)
const loading=ref(true), paying=ref(false), error=ref(''), success=ref(''), printAfterPay=ref(true)
const searchInput=ref(null), cartExpanded=ref(false)
const scanning=ref(false), scannerReady=ref(false), lastScanCode=ref('')
const printFormat=ref(localStorage.getItem('posPrintFormat')||'thermal58')
let html5QrCode=null

const categories=computed(()=>['All',...new Set(products.value.map(p=>p.pos_category||'Others'))])
const locationProducts=computed(()=>{const lid=locationId.value;if(!lid||!productLocations.value.length)return products.value;const assigned=new Set(productLocations.value.filter(pl=>Number(pl.location_id)===Number(lid)).map(pl=>Number(pl.product_id)));const hasAnyAssignment=new Set(productLocations.value.map(pl=>Number(pl.product_id)));return products.value.filter(p=>assigned.has(Number(p.id))||!hasAnyAssignment.has(Number(p.id)))})
const visibleProducts=computed(()=>{const q=search.value.trim().toLowerCase();return locationProducts.value.filter(p=>(category.value==='All'||(p.pos_category||'Others')===category.value)&&(!q||[p.name,p.sku,p.barcode,p.pos_category].some(v=>String(v||'').toLowerCase().includes(q))))})
const customerMatches=computed(()=>{const q=clientSearch.value.trim().toLowerCase();if(!q)return [];return clients.value.filter(c=>[c.name,c.mobile,c.company].some(v=>String(v||'').toLowerCase().includes(q))).slice(0,6)})
const itemCount=computed(()=>cart.value.reduce((s,l)=>s+Number(l.quantity),0))
const invoiceItems=computed(()=>cart.value.map(l=>({product_id:l.product.id,description:l.product.name,unit:l.product.unit||'Nos',quantity:l.quantity,unit_price:price(l.product),gst_rate:Number(l.product.gst_rate||0),discount_pct:0})))
const totals=computed(()=>calcInvoice(invoiceItems.value,'intra'))

function lineFor(product){return cart.value.find(l=>l.product.id===product.id)}
function stockAt(product){return stockRows.value.find(s=>Number(s.product_id)===Number(product.id)&&Number(s.location_id)===Number(locationId.value))}
function available(product){const row=stockAt(product);return row?Number(row.quantity||0)-Number(row.reserved_quantity||0):Number(product.available_stock||0)}
function price(product){const shopPrice=stockAt(product)?.location_price;return Number(shopPrice===null||shopPrice===undefined?product.price:shopPrice)}
function cannotAdd(product){return inventoryMode.value==='strict'&&+product.track_stock&&available(product)<=Number(lineFor(product)?.quantity||0)}
function add(product){error.value='';if(cannotAdd(product)){error.value=`Only ${available(product)} ${product.unit||'Nos'} available for ${product.name}.`;return}const line=lineFor(product);if(line)line.quantity++;else cart.value.push({product,quantity:1});if(inventoryMode.value==='warn'&&+product.track_stock&&Number(lineFor(product)?.quantity||0)>available(product))error.value=`Stock warning: only ${available(product)} ${product.unit||'Nos'} available for ${product.name}. Sale is still allowed.`}
function addExactSearch(){const q=search.value.trim().toLowerCase();if(!q)return;const product=products.value.find(p=>[p.barcode,p.sku].some(v=>String(v||'').toLowerCase()===q))||visibleProducts.value[0];if(product){add(product);search.value=''}}
function change(line,amount){if(amount>0&&cannotAdd(line.product)){error.value=`Only ${available(line.product)} ${line.product.unit||'Nos'} available.`;return}line.quantity+=amount;if(line.quantity<=0)cart.value=cart.value.filter(x=>x!==line)}
function chooseClient(client){selectedClient.value=client;clientSearch.value=client.name;customerName.value=client.name;customerPhone.value=client.mobile||''}
function onLocationChange(){if(cart.value.length){const invalid=cart.value.filter(l=>!locationProducts.value.some(p=>p.id===l.product.id));if(invalid.length){cart.value=cart.value.filter(l=>locationProducts.value.some(p=>p.id===l.product.id));error.value=`${invalid.length} item(s) removed — not available at this shop.`}}category.value='All'}
function clearCart(){if(cart.value.length&&!confirm('Clear all items from the current sale?'))return;cart.value=[];error.value='';success.value='';nextTick(()=>searchInput.value?.focus())}
function resetSale(){cart.value=[];clientSearch.value='';selectedClient.value=null;customerName.value='';customerPhone.value='';note.value='';paymentMethod.value='cash';search.value='';category.value='All';nextTick(()=>searchInput.value?.focus())}

async function openScanner(){
  scanning.value=true; scannerReady.value=false; error.value=''
  const {Html5Qrcode}=await import('html5-qrcode')
  await nextTick()
  html5QrCode=new Html5Qrcode('pos-scanner-view')
  try{
    await html5QrCode.start({facingMode:'environment'},{fps:10,qrbox:{width:280,height:160},aspectRatio:1.6,disableFlip:false},onScanSuccess,()=>{})
    scannerReady.value=true
  }catch(e){
    scanning.value=false
    error.value=e?.message?.includes('NotAllowed')||e?.name==='NotAllowedError'?'Camera permission denied. Please allow camera access and try again.':'Could not start camera. Check that no other app is using it.'
  }
}
function onScanSuccess(code){
  if(code===lastScanCode.value)return
  lastScanCode.value=code
  const product=products.value.find(p=>[p.barcode,p.sku].some(v=>String(v||'').toLowerCase()===code.toLowerCase()))
  if(product){add(product);success.value=`Scanned: ${product.name}`;setTimeout(()=>{lastScanCode.value=''},1500)}
  else{error.value=`No product found for barcode "${code}".`;setTimeout(()=>{lastScanCode.value=''},2000)}
}
async function closeScanner(){
  if(html5QrCode){try{await html5QrCode.stop()}catch{};try{html5QrCode.clear()}catch{};html5QrCode=null}
  scanning.value=false;scannerReady.value=false;lastScanCode.value=''
}
onUnmounted(()=>{if(html5QrCode){try{html5QrCode.stop()}catch{};html5QrCode=null}})

function setPrintFormat(fmt){printFormat.value=fmt;localStorage.setItem('posPrintFormat',fmt)}

async function loadRecentSales(){
  recentLoading.value=true
  try{
    const res=await list('Invoice',{'filter.invoice_type':'retail',sort_by:'i.created_at',sort_order:'desc',limit:20})
    recentSales.value=res.data?.data||[]
  }catch{}
  recentLoading.value=false
}

function openRecent(){showRecent.value=true;loadRecentSales()}

function reprintSale(inv,format){
  const paper=format||printFormat.value
  window.open(`/print/invoice/${inv.id}?paper=${paper}`,'_blank')
}

async function checkout(){
  error.value='';success.value=''
  if(!cart.value.length){error.value='Add at least one product before payment.';return}
  paying.value=true
  const printWindow=printAfterPay.value?window.open('about:blank','_blank'):null
  try{
    let clientId=selectedClient.value?.id||null
    if(!clientId&&customerName.value.trim()){
      const existing=clients.value.find(c=>(customerPhone.value&&c.mobile===customerPhone.value)||c.name.toLowerCase()===customerName.value.trim().toLowerCase())
      if(existing)clientId=existing.id
      else {const created=await task('Client','create',{name:customerName.value.trim(),mobile:customerPhone.value.trim()||null,type:'individual'});clientId=created.data?.data?.client_id}
    }
    const created=await task('Invoice','create',{client_id:clientId,location_id:locationId.value,invoice_type:'retail',issue_date:today(),due_date:today(),notes:note.value.trim()||null,items:invoiceItems.value})
    const invoiceId=created.data?.data?.invoice_id
    const paid=await task('Invoice','markPaid',{id:invoiceId,payment_date:today(),method:paymentMethod.value})
    const number=paid.data?.data?.number||''
    success.value=`Sale ${number} completed successfully.`
    if(printAfterPay.value){const printUrl=`/print/invoice/${invoiceId}?paper=${printFormat.value}`;if(printWindow)printWindow.location.href=printUrl;else window.location.assign(printUrl)}
    resetSale()
  }catch(e){printWindow?.close();error.value=e.response?.data?.message||'Could not complete this sale. Please try again.'}finally{paying.value=false}
}

onMounted(async()=>{try{const [p,c,s]=await Promise.all([all('Product'),all('Client'),task('Inventory','overview')]);products.value=p.data?.data||[];clients.value=c.data?.data||[];const stock=s.data?.data||{};locations.value=stock.locations||[];stockRows.value=stock.stock||[];productLocations.value=stock.productLocations||[];inventoryMode.value=stock.settings?.inventory_mode||'none';locationId.value=stock.defaultId||locations.value[0]?.id||null}catch(e){error.value=e.response?.data?.message||'Could not load POS.'}finally{loading.value=false;nextTick(()=>searchInput.value?.focus())}})
</script>

<template>
<main class="pos-page">
  <p v-if="error" class="pos-alert error">{{error}}</p><p v-if="success" class="pos-alert success">{{success}}</p>
  <div class="pos-layout">
    <section class="pos-catalog">
      <div class="pos-search"><span>⌕</span><input ref="searchInput" v-model="search" placeholder="Search product, SKU or scan barcode" title="Type a product name, SKU or barcode. Press Enter to add the first match." @keydown.enter.prevent="addExactSearch" /><button class="scan-btn" title="Scan barcode with camera" @click="openScanner"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M5 21H3a2 2 0 01-2-2v-2m5-4h8m-4-4v8"/></svg></button><button class="scan-btn" title="Recent sales" @click="openRecent"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button></div>
      <div class="pos-categories"><button v-for="c in categories" :key="c" :class="{active:category===c}" @click="category=c">{{c==='All'?'All items':c}}</button></div>
      <div v-if="loading" class="pos-empty">Loading products…</div>
      <div v-else class="pos-products">
        <button v-for="p in visibleProducts" :key="p.id" class="pos-product" :class="{selected:lineFor(p),disabled:cannotAdd(p)}" :title="cannotAdd(p)?'No more stock available':'Add this product to the current sale'" @click="add(p)"><span>{{p.name.charAt(0).toUpperCase()}}</span><strong>{{p.name}}</strong><small>{{p.pos_category||'Others'}} · {{p.unit||'Nos'}}<template v-if="+p.track_stock"> · Stock {{available(p)}}</template></small><b>{{inr(price(p))}}</b><em v-if="lineFor(p)">{{lineFor(p).quantity}}</em></button>
        <p v-if="!visibleProducts.length" class="pos-empty">No matching product found.</p>
      </div>
    </section>
    <aside class="pos-cart" :class="{'cart-collapsed':!cartExpanded && cart.length}">
      <div v-if="locations.length>1" class="pos-shop-bar"><select v-model="locationId" @change="onLocationChange"><option v-for="l in locations" :key="l.id" :value="l.id">{{l.name}}</option></select></div>
      <div class="pos-cart-head" @click="cartExpanded=!cartExpanded"><div><p>CURRENT SALE</p><h2>{{itemCount}} {{itemCount===1?'item':'items'}}<span class="cart-toggle lg:hidden">{{cartExpanded?'▾':'▴'}}</span></h2></div><button v-if="cart.length" @click.stop="clearCart">Clear Cart</button></div>
      <div class="pos-lines"><div v-if="!cart.length" class="pos-empty"><strong>Cart is empty</strong><small>Tap products from the left.</small></div><article v-for="line in cart" :key="line.product.id"><div><strong>{{line.product.name}}</strong><small>{{inr(price(line.product))}} × {{line.quantity}}</small></div><div class="pos-step"><button title="Reduce quantity" @click="change(line,-1)">−</button><b>{{line.quantity}}</b><button title="Increase quantity" @click="change(line,1)">+</button></div><strong>{{inr(price(line.product)*line.quantity)}}</strong></article></div>
      <div class="pos-bottom">
        <div class="pos-details-row"><input v-model="customerName" placeholder="Customer name" /><input v-model="customerPhone" inputmode="tel" placeholder="Mobile" /><input v-model="note" placeholder="Note" /></div>
        <div class="pos-pay-row">
          <div class="pos-methods"><button v-for="m in ['cash','upi','card']" :key="m" :class="{active:paymentMethod===m}" @click="paymentMethod=m">{{m.toUpperCase()}}</button></div>
          <div class="pos-print-opts">
            <label class="print"><input v-model="printAfterPay" type="checkbox" /> Print</label>
            <select v-if="printAfterPay" v-model="printFormat" @change="setPrintFormat($event.target.value)" class="print-fmt">
              <option value="thermal58">58mm</option>
              <option value="thermal80">80mm</option>
              <option value="a4">A4</option>
            </select>
          </div>
        </div>
        <footer class="pos-footer"><div><strong>{{inr(totals.total)}}</strong><small>GST incl.</small></div><button :disabled="paying||!cart.length" @click="checkout">{{paying?'Processing…':`Pay ${inr(totals.total)}`}} →</button></footer>
      </div>
    </aside>
  </div>
  <!-- Recent Sales drawer -->
  <div v-if="showRecent" class="scan-overlay" @click.self="showRecent=false">
    <div class="recent-drawer">
      <div class="scan-header">
        <h3>Recent POS Sales</h3>
        <button @click="showRecent=false" class="scan-close">&times;</button>
      </div>
      <div v-if="recentLoading" class="pos-empty">Loading sales…</div>
      <div v-else-if="!recentSales.length" class="pos-empty">No recent sales found.</div>
      <div v-else class="recent-list">
        <article v-for="s in recentSales" :key="s.id" class="recent-item">
          <div class="recent-info">
            <strong>{{ s.client_name || 'Walk-in Customer' }}</strong>
            <small>{{ s.number || 'Pending' }} · {{ fmtDateShort(s.issue_date) }}</small>
          </div>
          <b>{{ inr(s.total) }}</b>
          <div class="recent-actions">
            <button @click="reprintSale(s,'thermal58')" title="Print 58mm receipt">58</button>
            <button @click="reprintSale(s,'a4')" title="Print A4 invoice">A4</button>
            <RouterLink :to="'/invoices/'+s.id" class="recent-view" title="View invoice">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </RouterLink>
          </div>
        </article>
      </div>
    </div>
  </div>
  <!-- Camera scanner modal -->
  <div v-if="scanning" class="scan-overlay" @click.self="closeScanner">
    <div class="scan-modal">
      <div class="scan-header">
        <h3>Scan Barcode</h3>
        <button @click="closeScanner" class="scan-close">&times;</button>
      </div>
      <div id="pos-scanner-view"></div>
      <p v-if="!scannerReady" class="scan-loading">Starting camera…</p>
      <p class="scan-hint">Point your camera at a barcode. Product will be added automatically.</p>
    </div>
  </div>
</main>
</template>

<style scoped>
.pos-page{height:100%;background:#f5f7fb;padding:10px 14px;display:flex;flex-direction:column}
.pos-cart-head p{font-size:11px;font-weight:800;letter-spacing:.16em;color:#6366f1}
.cart-toggle{margin-left:6px;font-size:12px;color:#94a3b8}
.pos-layout{display:grid;grid-template-columns:minmax(0,1fr) 370px;gap:10px;flex:1;min-height:0}
.pos-catalog,.pos-cart{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.04)}
.pos-catalog{padding:10px;display:flex;flex-direction:column;min-height:0;overflow:hidden}
.pos-search{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0 12px;flex-shrink:0}
.pos-search input{width:100%;padding:10px 0;outline:none;background:transparent;font-size:13px}
.pos-categories{display:flex;gap:6px;overflow:auto;padding:8px 0;flex-shrink:0}
.pos-categories button{white-space:nowrap;border:1px solid #e5e7eb;border-radius:999px;padding:6px 12px;font-size:11px;font-weight:700;color:#64748b}
.pos-categories button.active{background:#4f46e5;color:#fff;border-color:#4f46e5}
.pos-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;overflow-y:auto;flex:1;min-height:0;align-content:start}
.pos-product{position:relative;text-align:left;border:1px solid #e5e7eb;border-radius:12px;padding:10px;min-height:125px;transition:.15s}
.pos-product:hover,.pos-product.selected{border-color:#818cf8;background:#f5f3ff;transform:translateY(-1px)}
.pos-product.disabled{opacity:.5}
.pos-product>span{display:flex;width:32px;height:32px;border-radius:9px;align-items:center;justify-content:center;background:#eef2ff;color:#4f46e5;font-weight:800;font-size:13px}
.pos-product strong,.pos-product small,.pos-product b{display:block}
.pos-product strong{margin-top:6px;color:#111827;font-size:12px;line-height:1.3}
.pos-product small{font-size:10px;color:#94a3b8;margin:2px 0 5px}
.pos-product b{color:#4f46e5;font-size:13px}
.pos-product em{position:absolute;right:8px;top:8px;background:#4f46e5;color:white;border-radius:999px;min-width:22px;height:22px;text-align:center;line-height:22px;font-style:normal;font-size:10px;font-weight:800}
.pos-cart{display:flex;flex-direction:column;min-height:0;overflow:hidden}
.pos-shop-bar{padding:8px 12px;background:#f0f0ff;border-bottom:1px solid #e0e0f0;flex-shrink:0}
.pos-shop-bar select{width:100%;border:1px solid #c7d2fe;border-radius:8px;padding:7px 10px;font-size:12px;font-weight:700;color:#4338ca;background:#fff;cursor:pointer}
.pos-cart-head{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid #eee;flex-shrink:0}
.pos-cart-head p{margin-bottom:-2px}
.pos-cart-head h2{font-size:16px;font-weight:800}
.pos-cart-head button{color:#dc2626;font-size:11px;font-weight:700}
.pos-lines{flex:1;overflow-y:auto;min-height:0}
.pos-lines article{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid #f1f5f9}
.pos-lines article strong{font-size:12px}
.pos-lines article small{display:block;font-size:10px;color:#94a3b8}
.pos-step{display:flex;align-items:center;gap:7px}
.pos-step button{width:24px;height:24px;border-radius:7px;background:#eef2ff;color:#4f46e5;font-weight:800;font-size:13px}
.pos-step b{font-size:13px}
.pos-bottom{flex-shrink:0;border-top:1px solid #e5e7eb}
.pos-details-row{display:flex;gap:6px;padding:8px 12px}
.pos-details-row input{flex:1;min-width:0;border:1px solid #e2e8f0;border-radius:8px;padding:7px 10px;font-size:12px}
.pos-pay-row{display:flex;align-items:center;justify-content:space-between;padding:4px 12px 8px}
.pos-methods{display:flex;gap:5px}
.pos-methods button{border:1px solid #e5e7eb;border-radius:999px;padding:5px 12px;font-size:11px;font-weight:700;color:#64748b}
.pos-methods button.active{background:#4f46e5;color:#fff;border-color:#4f46e5}
.print{display:flex;align-items:center;gap:6px;font-size:11px;color:#64748b}
.pos-footer{padding:10px 12px;background:#111827;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px;border-radius:0 0 14px 14px}
.pos-footer small{display:block;color:#94a3b8;font-size:10px}
.pos-footer strong{font-size:20px}
.pos-footer button{background:#6366f1;border-radius:10px;padding:11px 14px;font-weight:800;font-size:13px;min-width:150px}
.pos-footer button:disabled{opacity:.5}
.pos-empty{text-align:center;color:#94a3b8;padding:20px 10px;font-size:13px}
.pos-empty strong,.pos-empty small{display:block}
.pos-alert{padding:8px 12px;border-radius:9px;margin-bottom:8px;font-size:12px;font-weight:600;flex-shrink:0}
.pos-alert.error{background:#fef2f2;color:#b91c1c}
.pos-alert.success{background:#ecfdf5;color:#047857}
.scan-btn{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;color:#4f46e5;background:transparent;border:none;cursor:pointer;flex-shrink:0;transition:.15s}
.scan-btn:hover{background:#eef2ff}
.scan-overlay{position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:20px}
.scan-modal{background:#fff;border-radius:16px;width:100%;max-width:420px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.scan-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb}
.scan-header h3{font-size:15px;font-weight:700;color:#111827}
.scan-close{width:32px;height:32px;border-radius:8px;border:none;background:#f3f4f6;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280}
.scan-close:hover{background:#e5e7eb}
#pos-scanner-view{width:100%;min-height:240px;background:#000}
#pos-scanner-view video{width:100%!important;border-radius:0!important}
.scan-loading{text-align:center;padding:12px;font-size:13px;color:#6b7280}
.scan-hint{text-align:center;padding:12px 16px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9}
.pos-print-opts{display:flex;align-items:center;gap:6px}
.print-fmt{border:1px solid #e2e8f0;border-radius:6px;padding:3px 6px;font-size:11px;font-weight:700;color:#4338ca;background:#f8fafc;cursor:pointer}
.recent-drawer{background:#fff;border-radius:16px;width:100%;max-width:480px;max-height:80vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);display:flex;flex-direction:column}
.recent-list{overflow-y:auto;flex:1;min-height:0}
.recent-item{display:flex;align-items:center;gap:8px;padding:10px 16px;border-bottom:1px solid #f1f5f9}
.recent-info{flex:1;min-width:0}
.recent-info strong{display:block;font-size:13px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.recent-info small{display:block;font-size:11px;color:#94a3b8}
.recent-item>b{font-size:13px;color:#4f46e5;white-space:nowrap}
.recent-actions{display:flex;gap:4px;margin-left:6px}
.recent-actions button,.recent-view{width:30px;height:28px;border-radius:6px;background:#eef2ff;color:#4f46e5;font-weight:800;font-size:10px;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer}
.recent-actions button:hover,.recent-view:hover{background:#c7d2fe}
@media(max-width:1023px){
  .pos-page{padding:8px 8px 0;overflow:hidden}
  .pos-layout{grid-template-columns:1fr;grid-template-rows:1fr auto;height:100%}
  .pos-catalog{overflow:hidden;min-height:0}
  .pos-cart{position:fixed;left:0;right:0;bottom:64px;z-index:30;border-radius:18px 18px 0 0;box-shadow:0 -4px 24px rgba(0,0,0,.12);max-height:50vh;display:flex;flex-direction:column;transition:max-height .25s ease}
  .pos-cart.cart-collapsed .pos-lines,
  .pos-cart.cart-collapsed .pos-details-row,
  .pos-cart.cart-collapsed .pos-pay-row,
  .pos-cart.cart-collapsed .pos-shop-bar{display:none}
  .pos-cart-head{cursor:pointer;-webkit-tap-highlight-color:transparent}
  .pos-products{grid-template-columns:repeat(3,minmax(0,1fr));padding-bottom:140px}
  .pos-lines{flex:1;overflow-y:auto;min-height:0;max-height:28vh}
  .pos-footer{border-radius:0;position:static}
}
@media(max-width:430px){.pos-product{min-height:110px;padding:8px}.pos-products{grid-template-columns:repeat(2,minmax(0,1fr))}.pos-footer button{min-width:120px;padding:10px 8px}}
</style>
