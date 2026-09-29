<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { all, item, task } from '../../api'
import { inr } from '../../utils/currency'
import { today } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'

const products=ref([]), clients=ref([]), locations=ref([]), stockRows=ref([]), productLocations=ref([]), inventoryMode=ref('none')
const cart=ref([]), category=ref('All'), search=ref(''), clientSearch=ref(''), selectedClient=ref(null)
const customerName=ref(''), customerPhone=ref(''), note=ref(''), paymentMethod=ref('cash'), locationId=ref(null)
const loading=ref(true), paying=ref(false), error=ref(''), success=ref(''), printAfterPay=ref(true)
const searchInput=ref(null)

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
    if(printAfterPay.value){const printUrl=`/print/invoice/${invoiceId}?paper=${localStorage.getItem('invoicePaper')||'thermal58'}`;if(printWindow)printWindow.location.href=printUrl;else window.location.assign(printUrl)}
    resetSale()
  }catch(e){printWindow?.close();error.value=e.response?.data?.message||'Could not complete this sale. Please try again.'}finally{paying.value=false}
}

onMounted(async()=>{try{const [p,c,s]=await Promise.all([all('Product'),all('Client'),task('Inventory','overview')]);products.value=p.data?.data||[];clients.value=c.data?.data||[];const stock=s.data?.data||{};locations.value=stock.locations||[];stockRows.value=stock.stock||[];productLocations.value=stock.productLocations||[];inventoryMode.value=stock.settings?.inventory_mode||'none';locationId.value=stock.defaultId||locations.value[0]?.id||null}catch(e){error.value=e.response?.data?.message||'Could not load POS.'}finally{loading.value=false;nextTick(()=>searchInput.value?.focus())}})
</script>

<template>
<main class="pos-page">
  <header class="pos-head"><h1>POS Counter</h1><div class="pos-open"><i></i> Open</div></header>
  <p v-if="error" class="pos-alert error">{{error}}</p><p v-if="success" class="pos-alert success">{{success}}</p>
  <div class="pos-layout">
    <section class="pos-catalog">
      <div class="pos-search"><span>⌕</span><input ref="searchInput" v-model="search" placeholder="Search product, SKU or scan barcode" title="Type a product name, SKU or barcode. Press Enter to add the first match." @keydown.enter.prevent="addExactSearch" /></div>
      <div class="pos-categories"><button v-for="c in categories" :key="c" :class="{active:category===c}" @click="category=c">{{c==='All'?'All items':c}}</button></div>
      <div v-if="loading" class="pos-empty">Loading products…</div>
      <div v-else class="pos-products">
        <button v-for="p in visibleProducts" :key="p.id" class="pos-product" :class="{selected:lineFor(p),disabled:cannotAdd(p)}" :title="cannotAdd(p)?'No more stock available':'Add this product to the current sale'" @click="add(p)"><span>{{p.name.charAt(0).toUpperCase()}}</span><strong>{{p.name}}</strong><small>{{p.pos_category||'Others'}} · {{p.unit||'Nos'}}<template v-if="+p.track_stock"> · Stock {{available(p)}}</template></small><b>{{inr(price(p))}}</b><em v-if="lineFor(p)">{{lineFor(p).quantity}}</em></button>
        <p v-if="!visibleProducts.length" class="pos-empty">No matching product found.</p>
      </div>
    </section>
    <aside class="pos-cart">
      <div v-if="locations.length>1" class="pos-shop-bar"><select v-model="locationId" @change="onLocationChange"><option v-for="l in locations" :key="l.id" :value="l.id">{{l.name}}</option></select></div>
      <div class="pos-cart-head"><div><p>CURRENT SALE</p><h2>{{itemCount}} {{itemCount===1?'item':'items'}}</h2></div><button v-if="cart.length" @click="clearCart">Clear Cart</button></div>
      <div class="pos-lines"><div v-if="!cart.length" class="pos-empty"><strong>Cart is empty</strong><small>Choose products from the left.</small></div><article v-for="line in cart" :key="line.product.id"><div><strong>{{line.product.name}}</strong><small>{{inr(price(line.product))}} × {{line.quantity}}</small></div><div class="pos-step"><button title="Reduce quantity" @click="change(line,-1)">−</button><b>{{line.quantity}}</b><button title="Increase quantity" @click="change(line,1)">+</button></div><strong>{{inr(price(line.product)*line.quantity)}}</strong></article></div>
      <div class="pos-details">
        <label>Customer <small>(optional for walk-in)</small></label>
        <div class="relative"><input v-model="clientSearch" placeholder="Search saved customer" @input="selectedClient=null" /><div v-if="customerMatches.length&&!selectedClient" class="pos-dropdown"><button v-for="c in customerMatches" :key="c.id" @click="chooseClient(c)">{{c.name}} <small>{{c.mobile}}</small></button></div></div>
        <div class="two"><input v-model="customerName" placeholder="Customer name" /><input v-model="customerPhone" inputmode="tel" placeholder="Mobile number" /></div>
        <input v-model="note" placeholder="Sale / kitchen note" />
      </div>
      <div class="pos-payment"><label>Payment method</label><div><button v-for="m in ['cash','upi','card']" :key="m" :class="{active:paymentMethod===m}" @click="paymentMethod=m">{{m.toUpperCase()}}</button></div><label class="print"><input v-model="printAfterPay" type="checkbox" /> Print bill after payment</label></div>
      <footer class="pos-footer"><div><span>Total payable</span><strong>{{inr(totals.total)}}</strong><small>GST included · Paid now</small></div><button :disabled="paying||!cart.length" @click="checkout">{{paying?'Processing…':`Pay ${inr(totals.total)}`}} →</button></footer>
    </aside>
  </div>
</main>
</template>

<style scoped>
.pos-page{min-height:100%;background:#f5f7fb;padding:10px 14px}.pos-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}.pos-cart-head p{font-size:11px;font-weight:800;letter-spacing:.16em;color:#6366f1}.pos-head h1{font-size:16px;font-weight:800;color:#111827}.pos-open{background:#ecfdf5;color:#047857;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700}.pos-open i{display:inline-block;width:7px;height:7px;border-radius:50%;background:#10b981;margin-right:4px}.pos-layout{display:grid;grid-template-columns:minmax(0,1fr) 370px;gap:10px;align-items:start}.pos-catalog,.pos-cart{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.04)}.pos-catalog{padding:10px}.pos-search{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0 12px}.pos-search input{width:100%;padding:10px 0;outline:none;background:transparent;font-size:13px}.pos-categories{display:flex;gap:6px;overflow:auto;padding:8px 0}.pos-categories button,.pos-payment button{white-space:nowrap;border:1px solid #e5e7eb;border-radius:999px;padding:6px 12px;font-size:11px;font-weight:700;color:#64748b}.pos-categories button.active,.pos-payment button.active{background:#4f46e5;color:#fff;border-color:#4f46e5}.pos-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px}.pos-product{position:relative;text-align:left;border:1px solid #e5e7eb;border-radius:12px;padding:10px;min-height:125px;transition:.15s}.pos-product:hover,.pos-product.selected{border-color:#818cf8;background:#f5f3ff;transform:translateY(-1px)}.pos-product.disabled{opacity:.5}.pos-product>span{display:flex;width:32px;height:32px;border-radius:9px;align-items:center;justify-content:center;background:#eef2ff;color:#4f46e5;font-weight:800;font-size:13px}.pos-product strong,.pos-product small,.pos-product b{display:block}.pos-product strong{margin-top:6px;color:#111827;font-size:12px;line-height:1.3}.pos-product small{font-size:10px;color:#94a3b8;margin:2px 0 5px}.pos-product b{color:#4f46e5;font-size:13px}.pos-product em{position:absolute;right:8px;top:8px;background:#4f46e5;color:white;border-radius:999px;min-width:22px;height:22px;text-align:center;line-height:22px;font-style:normal;font-size:10px;font-weight:800}.pos-cart{position:sticky;top:8px;overflow:hidden}.pos-shop-bar{padding:8px 12px;background:#f0f0ff;border-bottom:1px solid #e0e0f0}.pos-shop-bar select{width:100%;border:1px solid #c7d2fe;border-radius:8px;padding:7px 10px;font-size:12px;font-weight:700;color:#4338ca;background:#fff;cursor:pointer}.pos-cart-head{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid #eee}.pos-cart-head p{margin-bottom:-2px}.pos-cart-head h2{font-size:16px;font-weight:800}.pos-cart-head button{color:#dc2626;font-size:11px;font-weight:700}.pos-lines{max-height:240px;overflow:auto}.pos-lines article{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid #f1f5f9}.pos-lines article strong{font-size:12px}.pos-lines article small{display:block;font-size:10px;color:#94a3b8}.pos-step{display:flex;align-items:center;gap:7px}.pos-step button{width:24px;height:24px;border-radius:7px;background:#eef2ff;color:#4f46e5;font-weight:800;font-size:13px}.pos-step b{font-size:13px}.pos-details,.pos-payment{padding:10px 12px;border-top:1px solid #eee;display:grid;gap:7px}.pos-details label,.pos-payment>label:first-child{font-size:11px;font-weight:800;color:#475569}.pos-details input,.pos-details select{width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;font-size:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:6px}.pos-dropdown{position:absolute;z-index:10;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 8px 20px #0002}.pos-dropdown button{display:flex;justify-content:space-between;width:100%;padding:8px 10px;text-align:left;font-size:11px}.pos-payment>div{display:flex;gap:6px}.print{display:flex;align-items:center;gap:6px;font-size:11px;color:#64748b}.pos-footer{padding:10px 12px;background:#111827;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px}.pos-footer span,.pos-footer small{display:block;color:#94a3b8;font-size:10px}.pos-footer strong{font-size:20px}.pos-footer button{background:#6366f1;border-radius:10px;padding:11px 14px;font-weight:800;font-size:13px;min-width:150px}.pos-footer button:disabled{opacity:.5}.pos-empty{text-align:center;color:#94a3b8;padding:20px 10px;font-size:13px}.pos-empty strong,.pos-empty small{display:block}.pos-alert{padding:8px 12px;border-radius:9px;margin-bottom:8px;font-size:12px;font-weight:600}.pos-alert.error{background:#fef2f2;color:#b91c1c}.pos-alert.success{background:#ecfdf5;color:#047857}
@media(max-width:1023px){.pos-page{padding:8px 8px 80px}.pos-open{display:none}.pos-layout{grid-template-columns:1fr}.pos-cart{position:static}.pos-products{grid-template-columns:repeat(3,minmax(0,1fr))}.pos-footer{position:sticky;bottom:72px;z-index:20}.pos-lines{max-height:none}}@media(max-width:430px){.pos-product{min-height:110px;padding:8px}.pos-products{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}.pos-footer button{min-width:120px;padding:10px 8px}}
</style>
