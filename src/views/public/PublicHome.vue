<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const featureSlides = [
  { image: '/screenshots/pos.jpg', eyebrow: 'Fast counter billing', title: 'Choose a product and finish the sale in seconds.', text: 'Search or scan items, change quantity, take Cash, UPI or Card, then print a 58mm, 80mm or A4 bill.', points: ['Barcode ready', 'Live stock shown', 'One-tap payment'] },
  { image: '/screenshots/inventory.jpg', eyebrow: 'Simple stock control', title: 'Know what is available in every shop and godown.', text: 'See current quantity, low-stock items and stock movement. Use billing only, stock warning or strict stock control.', points: ['Shop-wise stock', 'Low-stock warning', 'Stock transfer'] },
  { image: '/screenshots/invoices.jpg', eyebrow: 'Clear invoice tracking', title: 'Find paid, pending and overdue bills without confusion.', text: 'Filter invoices by status, follow balances and open any bill for payment, sharing, editing or reprinting.', points: ['Payment status', 'Customer balance', 'Quick reprint'] },
  { image: '/screenshots/dashboard.jpg', eyebrow: 'Business at a glance', title: 'See collections, expenses and dues from one screen.', text: 'The dashboard gives the owner a quick daily picture with recent invoices, overdue alerts and useful shortcuts.', points: ['Today’s picture', 'Overdue alerts', 'Quick actions'] },
]
const activeFeature = ref(0)
let featureTimer
let touchStartX = 0
const selectFeature = index => { activeFeature.value = (index + featureSlides.length) % featureSlides.length }
const nextFeature = () => selectFeature(activeFeature.value + 1)
const previousFeature = () => selectFeature(activeFeature.value - 1)
const pauseFeatures = () => clearInterval(featureTimer)
const playFeatures = () => { pauseFeatures(); featureTimer = setInterval(nextFeature, 6500) }
const startFeatureSwipe = event => { touchStartX = event.changedTouches[0].clientX; pauseFeatures() }
const endFeatureSwipe = event => { const distance = event.changedTouches[0].clientX - touchStartX; if (Math.abs(distance) > 45) distance > 0 ? previousFeature() : nextFeature(); playFeatures() }

let cleanUp = () => {}
onMounted(() => {
  const previousTitle = document.title
  document.title = 'AI Billing | GST Billing, POS & Stock for Indian Shops'
  const button = document.querySelector('.public-site .menu-button')
  const nav = document.querySelector('.public-site #site-nav')
  const toggle = () => {
    const open = button.getAttribute('aria-expanded') === 'true'
    button.setAttribute('aria-expanded', String(!open))
    nav.classList.toggle('open', !open)
  }
  const close = () => {
    button.setAttribute('aria-expanded', 'false')
    nav.classList.remove('open')
  }
  button?.addEventListener('click', toggle)
  nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', close))
  cleanUp = () => {
    document.title = previousTitle
    button?.removeEventListener('click', toggle)
    nav?.querySelectorAll('a').forEach(link => link.removeEventListener('click', close))
  }
  playFeatures()
})
onBeforeUnmount(() => { cleanUp(); pauseFeatures() })
</script>

<template>
<div class="public-site">
  <header class="site-header">
    <a class="brand" href="#top" aria-label="AI Billing home">
      <img src="/logo.png" alt="AI Billing">
      <span>AI Billing</span>
    </a>
    <button class="menu-button" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav id="site-nav" aria-label="Main navigation">
      <a href="#how">How it works</a>
      <a href="#features">Features</a>
      <a href="#more">More</a>
      <a href="#editions">Desktop or Web</a>
      <a href="#faq">Questions</a>
    </nav>
    <a class="header-cta" href="/register">Start free trial</a>
  </header>

  <main id="top">
    <section class="hero wrap">
      <div class="hero-copy">
        <p class="eyebrow"><span></span> Built for Indian shop counters</p>
        <h1>Billing that keeps up with <em>your shop.</em></h1>
        <p class="hero-lede">GST invoices, POS counter, stock control, payments, barcodes, reports and 58mm receipts — all from one app. The Windows edition keeps billing even when the internet stops.</p>
        <div class="hero-actions">
          <a class="button primary" href="/register">Start your free trial <span>↗</span></a>
          <a class="text-link" href="#how">See a counter sale <span>↓</span></a>
        </div>
        <ul class="hero-notes" aria-label="Key benefits">
          <li>Cash, UPI &amp; card payments</li>
          <li>58mm &amp; 80mm thermal receipts</li>
          <li>Works offline on Windows</li>
        </ul>
      </div>

      <div class="counter-scene" aria-label="AI Billing point of sale preview">
        <div class="blue-note">Works offline<br><small>on Windows PC</small></div>
        <div class="screen-shell">
          <div class="screen-bar"><i></i><i></i><i></i><b>AI Billing · POS Counter</b></div>
          <div class="pos-ui">
            <aside>
              <strong>AI</strong>
              <span class="active"></span><span></span><span></span><span></span>
            </aside>
            <section class="product-area">
              <div class="search-line">Search product, SKU or scan barcode</div>
              <div class="chips"><b>All items</b><span>Grocery</span><span>Snacks</span></div>
              <div class="products">
                <article><i>R</i><b>Rice Bag</b><small>Stock 24</small><strong>₹620</strong></article>
                <article><i>T</i><b>Tea Packet</b><small>Stock 18</small><strong>₹145</strong></article>
                <article><i>B</i><b>Biscuits</b><small>Stock 42</small><strong>₹30</strong></article>
                <article><i>O</i><b>Cooking Oil</b><small>Stock 15</small><strong>₹178</strong></article>
              </div>
            </section>
            <section class="cart-area">
              <p>CURRENT SALE</p><h3>3 items</h3>
              <div class="line"><span>Rice Bag<small>1 × ₹620</small></span><b>₹620</b></div>
              <div class="line"><span>Tea Packet<small>2 × ₹145</small></span><b>₹290</b></div>
              <div class="pay"><span>Total payable</span><strong>₹910</strong><button>Pay ₹910 →</button></div>
            </section>
          </div>
        </div>
        <div class="receipt">
          <img src="/logo.png" alt="">
          <b>RETAIL INVOICE</b><small>Rice Bag · 1 · ₹620</small><small>Tea Packet · 2 · ₹290</small>
          <hr><strong>TOTAL ₹910</strong><span>Paid via UPI</span>
        </div>
      </div>
    </section>

    <section class="shop-strip" aria-label="Suitable businesses">
      <p>Made for everyday business</p>
      <div><span>Grocery</span><i>◆</i><span>Textiles</span><i>◆</i><span>Hardware</span><i>◆</i><span>Mobile shops</span><i>◆</i><span>Electronics</span><i>◆</i><span>Wholesale</span><i>◆</i><span>Services</span><i>◆</i><span>Stationery</span></div>
    </section>

    <section id="how" class="section wrap sale-flow">
      <div class="section-heading">
        <p class="eyebrow"><span></span> One counter sale</p>
        <h2>From product to printed bill in four clear steps.</h2>
      </div>
      <ol>
        <li><b>01</b><div><h3>Find or scan</h3><p>Search by name, SKU or scan a barcode with your camera. Saved rate, HSN/SAC and GST fill automatically.</p></div></li>
        <li><b>02</b><div><h3>Check the cart</h3><p>Adjust quantities, pick a customer or sell walk-in. See live stock, GST split and the final amount before payment.</p></div></li>
        <li><b>03</b><div><h3>Collect payment</h3><p>Record Cash, UPI or Card in one tap. Partial payments are tracked against the invoice automatically.</p></div></li>
        <li><b>04</b><div><h3>Print &amp; update stock</h3><p>Print a 58mm receipt, 80mm receipt or A4 invoice. Stock deducts from the correct shop automatically.</p></div></li>
      </ol>
    </section>

    <section id="features" class="section feature-section">
      <div class="wrap feature-showcase">
        <div class="feature-intro">
          <div><p class="eyebrow light"><span></span> See how it works</p><h2>Easy to understand.<br>Quick to use.</h2></div>
          <p>Real screens from AI Billing, shown with sample shop data. Use the arrows or swipe to explore the main features.</p>
        </div>
        <div class="feature-slider" tabindex="0" aria-label="AI Billing feature screenshots" @mouseenter="pauseFeatures" @mouseleave="playFeatures" @focusin="pauseFeatures" @focusout="playFeatures" @keydown.left.prevent="previousFeature" @keydown.right.prevent="nextFeature" @touchstart.passive="startFeatureSwipe" @touchend.passive="endFeatureSwipe">
          <div class="feature-visual">
            <div class="screen-caption"><span>AI Billing</span><small>Actual application screen · sample data</small></div>
            <transition name="feature-fade" mode="out-in"><img :key="featureSlides[activeFeature].image" :src="featureSlides[activeFeature].image" :alt="featureSlides[activeFeature].title"></transition>
          </div>
          <div class="feature-detail" aria-live="polite">
            <span class="slide-count">0{{activeFeature + 1}} / 0{{featureSlides.length}}</span>
            <p class="slide-eyebrow">{{featureSlides[activeFeature].eyebrow}}</p>
            <h3>{{featureSlides[activeFeature].title}}</h3>
            <p>{{featureSlides[activeFeature].text}}</p>
            <ul><li v-for="point in featureSlides[activeFeature].points" :key="point">{{point}}</li></ul>
            <div class="slider-controls"><button type="button" aria-label="Previous feature" @click="previousFeature">←</button><div class="slider-dots"><button v-for="(_, index) in featureSlides" :key="index" type="button" :class="{active:index===activeFeature}" :aria-label="`Show feature ${index + 1}`" :aria-current="index===activeFeature ? 'true' : undefined" @click="selectFeature(index)"></button></div><button type="button" aria-label="Next feature" @click="nextFeature">→</button></div>
          </div>
        </div>
      </div>
    </section>

    <section class="section wrap print-story">
      <div class="printer-drawing" aria-hidden="true">
        <div class="printer-top"></div><div class="printer-body"><span></span></div>
        <div class="paper"><b>AI BILLING</b><i></i><i></i><i></i><strong>₹ 910.00</strong></div>
      </div>
      <div class="print-copy">
        <p class="eyebrow"><span></span> Small printer. Proper bill.</p>
        <h2>Made to work with the counter you already have.</h2>
        <p>Print A4 invoices, compact 58mm receipts or 80mm thermal bills. The Windows app sends directly to SC588/PSF588 Bluetooth printers. Every bill shows clear GST, UPI details and your logo.</p>
        <div class="tick-grid"><span>✓ 58mm, 80mm &amp; A4 formats</span><span>✓ SC588 Bluetooth printing</span><span>✓ UPI QR code on the bill</span><span>✓ Reprint any past sale</span><span>✓ Barcode label sheet printing</span><span>✓ GST &amp; totals breakdown</span></div>
      </div>
    </section>

    <section id="more" class="section wrap more-features">
      <div class="section-heading center">
        <p class="eyebrow"><span></span> And there's more</p>
        <h2>Small details that save real time.</h2>
      </div>
      <div class="more-grid">
        <div><b>CSV import</b><p>Import products from CSV with rates, tax, SKU and opening stock per location.</p></div>
        <div><b>Keyboard shortcuts</b><p>Ctrl+S to save, F2 for client search, Alt+A to add a line — fast for power users.</p></div>
        <div><b>Digital shop card</b><p>Share a public business card page with QR code, contact info and logo via WhatsApp or link.</p></div>
        <div><b>Dashboard overview</b><p>Outstanding amount, collections this month, overdue alerts, revenue trend chart and recent invoices at a glance.</p></div>
        <div><b>Offline backups</b><p>Manual and daily local backups with validated restore. Export your data to a USB drive for safety.</p></div>
        <div><b>Indian FY &amp; states</b><p>April–March financial year, all Indian states with codes, GSTIN/PAN/CIN validation and E-way Bill credentials.</p></div>
      </div>
    </section>

    <section id="editions" class="section editions wrap">
      <div class="section-heading center">
        <p class="eyebrow"><span></span> Choose your setup</p>
        <h2>At the counter or wherever you are.</h2>
        <p>Both editions use the same billing workflow. Choose based on where your data and team need to work.</p>
      </div>
      <div class="edition-grid">
        <article class="edition featured">
          <div class="edition-label">Best for a billing counter</div>
          <h3>Windows Offline</h3><p class="edition-lede">For one PC that must keep billing through internet problems.</p>
          <ul><li>All billing, stock &amp; reports work without internet</li><li>Local MySQL database on your PC</li><li>Direct SC588 Bluetooth thermal printing</li><li>Manual &amp; daily local backups with restore</li><li>Single-PC licence activation</li><li>Same features as cloud — nothing removed</li></ul>
          <a href="#trial">Request desktop setup <span>→</span></a>
        </article>
        <article class="edition">
          <div class="edition-label">Best for access anywhere</div>
          <h3>Cloud Web</h3><p class="edition-lede">For owners and teams who need access from phones and multiple computers.</p>
          <ul><li>No installation — runs in any modern browser</li><li>Multiple staff logins with role-based access</li><li>Access from phone, tablet or desktop</li><li>Camera barcode scanning on mobile</li><li>Browser-based A4, 58mm &amp; 80mm printing</li><li>Automatic updates — always the latest version</li></ul>
          <a href="/register">Start web trial <span>→</span></a>
        </article>
      </div>
      <p class="honest-note"><b>Good to know:</b> Separate offline PCs do not share live stock. Use the cloud edition when multiple counters or branches must see the same data at the same time.</p>
    </section>

    <section id="trial" class="section trial-section">
      <div class="wrap trial-inner">
        <div><p class="eyebrow light"><span></span> See it with your own products</p><h2>Take one real sale for a test drive.</h2></div>
        <div><p>Create your business, add a few items and make a sample bill. No payment needed. If you need the Windows app and printer setup, request an assisted installation after the trial.</p><a class="button pale" href="/register">Create free account <span>↗</span></a></div>
      </div>
    </section>

    <section id="faq" class="section wrap faq">
      <div class="section-heading"><p class="eyebrow"><span></span> Straight answers</p><h2>Before you set up your counter.</h2></div>
      <div class="faq-list">
        <details><summary>Will billing work without internet?<i>+</i></summary><p>Yes, in the Windows offline edition. Initial activation needs internet once, but everyday billing, customers, products, payments, stock, reports and thermal printing all continue offline.</p></details>
        <details><summary>Can I use the app without stock maintenance?<i>+</i></summary><p>Yes. Choose "Billing Only" mode and stock is ignored completely. You can switch to "Warn" (show low-stock alerts) or "Strict" (block overselling) at any time from Settings.</p></details>
        <details><summary>Does it print on 58mm thermal paper?<i>+</i></summary><p>Yes. Three print sizes: 58mm receipt, 80mm receipt and A4 full invoice. The Windows app supports direct SC588/PSF588 Bluetooth thermal printing. In the browser, standard print works with any connected printer.</p></details>
        <details><summary>Can I import my existing products?<i>+</i></summary><p>Yes. Download a CSV template, fill in your product name, price, tax rate, SKU, HSN/SAC and opening stock, then upload. Location-specific stock and pricing are also supported during import.</p></details>
        <details><summary>What happens if the PC has a problem?<i>+</i></summary><p>The offline edition includes manual and daily local backups with validated restore. Keep a recent backup copy on a separate USB drive. Your data can be restored to a fresh installation.</p></details>
        <details><summary>Can staff use separate logins?<i>+</i></summary><p>Yes. Create staff accounts with four roles: Owner (full access), Admin (all except team management), Accountant (finance modules) or Staff (only assigned modules). Permissions control who can view, create, edit or delete in each section.</p></details>
        <details><summary>Can I use different GSTINs for different shops?<i>+</i></summary><p>Yes. Each shop/location can have its own GSTIN, state and address. Invoices from that shop automatically use the shop's GSTIN. GST returns can be filed separately per GSTIN.</p></details>
        <details><summary>Does it support barcodes?<i>+</i></summary><p>Yes. Add EAN-13, EAN-8 or CODE128 barcodes to products, or auto-generate them. Scan barcodes at the POS counter using your phone camera or a USB scanner. Print barcode label sheets for your products.</p></details>
        <details><summary>What reports are available?<i>+</i></summary><p>Outstanding receivables, GST summary with input credit, profit &amp; loss, expense breakdown by category, collection trends and aging analysis. All reports support date filtering and CSV export.</p></details>
        <details><summary>Is there a limit on invoices or products?<i>+</i></summary><p>No hard limits in the application. You can create as many invoices, products, clients and transactions as your business needs.</p></details>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap footer-grid">
      <div><a class="brand footer-brand" href="#top"><img src="/logo.png" alt=""><span>AI Billing</span></a><p>GST billing, POS, stock management, barcodes, receipts and reports for Indian shops and small businesses.</p></div>
      <div><b>Product</b><a href="#features">Features</a><a href="#editions">Desktop &amp; Web</a><a href="#more">More features</a><a href="/register">Free trial</a></div>
      <div><b>Useful</b><a href="#how">How it works</a><a href="#faq">Questions</a><a href="/login">Customer login</a></div>
    </div>
    <div class="wrap footer-bottom"><span>© 2026 AI Billing</span><span>GST billing · POS · Stock · Barcodes · Receipts · Reports</span></div>
  </footer>
  
</div>
</template>

<style scoped>
.public-site{--paper:#f7f3ea;--ink:#101a32;--muted:#586174;--blue:#1557e8;--cyan:#4bd5ee;--line:#d9d5cb;--white:#fff;--green:#169c72;--serif:Georgia,'Times New Roman',serif;--sans:Inter,'Segoe UI',Arial,sans-serif}.public-site *{box-sizing:border-box}.public-site{margin:0;background:var(--paper);color:var(--ink);font-family:var(--sans);font-size:16px;line-height:1.6}a{color:inherit;text-decoration:none}.wrap{width:min(1180px,calc(100% - 48px));margin-inline:auto}.site-header{height:82px;display:flex;align-items:center;gap:32px;width:min(1260px,calc(100% - 40px));margin:auto;border-bottom:1px solid var(--line)}.brand{display:flex;align-items:center;gap:10px;font-weight:800;letter-spacing:-.02em}.brand img{width:44px;height:44px;object-fit:cover;border-radius:12px}.site-header nav{display:flex;align-items:center;gap:28px;margin-left:auto}.site-header nav a{font-size:14px;font-weight:650;color:#35405a}.site-header nav a:hover{color:var(--blue)}.header-cta{background:var(--ink);color:white;padding:10px 18px;border-radius:7px;font-size:14px;font-weight:750}.menu-button{display:none;background:none;border:0;font:inherit;font-weight:700}.hero{min-height:690px;display:grid;grid-template-columns:.87fr 1.13fr;align-items:center;gap:54px;padding-block:72px 78px}.eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 18px;text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:850;color:#536079}.eyebrow span{width:28px;height:2px;background:var(--blue)}.hero h1,.section-heading h2,.print-copy h2,.trial-inner h2{font-family:var(--serif);font-weight:500;letter-spacing:-.045em;line-height:.98;margin:0}.hero h1{font-size:clamp(54px,6vw,82px)}.hero h1 em{font-weight:500;color:var(--blue)}.hero-lede{font-size:18px;line-height:1.65;color:var(--muted);max-width:590px;margin:26px 0 30px}.hero-actions{display:flex;align-items:center;gap:26px}.button{display:inline-flex;align-items:center;justify-content:center;gap:18px;padding:14px 19px;border-radius:8px;font-weight:800;font-size:14px}.button.primary{background:var(--blue);color:white;box-shadow:0 7px 0 #0b348c}.button:hover{transform:translateY(-1px)}.text-link{font-weight:800;border-bottom:1px solid #a8a294}.text-link span{margin-left:8px}.hero-notes{display:flex;gap:24px;list-style:none;padding:0;margin:38px 0 0;color:#677083;font-size:12px}.hero-notes li:before{content:'✓';color:var(--green);font-weight:900;margin-right:6px}.counter-scene{position:relative;padding:34px 32px 52px 0}.screen-shell{background:#151d2f;padding:9px;border-radius:16px;box-shadow:0 30px 80px rgba(15,27,50,.22);transform:rotate(1deg)}.screen-bar{height:28px;color:#9ca6bb;display:flex;align-items:center;gap:6px;padding:0 8px;font-size:9px}.screen-bar i{width:6px;height:6px;border-radius:50%;background:#526078}.screen-bar b{margin-left:7px;font-weight:650}.pos-ui{height:390px;background:#f5f7fb;border-radius:9px;display:grid;grid-template-columns:42px 1fr 155px;overflow:hidden}.pos-ui aside{background:#111a2b;display:flex;align-items:center;flex-direction:column;gap:18px;padding-top:16px}.pos-ui aside strong{color:white;background:#3b5cf2;border-radius:8px;padding:4px;font-size:10px}.pos-ui aside span{width:14px;height:14px;border:2px solid #788399;border-radius:4px}.pos-ui aside .active{border-color:#6a7cff;background:#5669ff}.product-area{padding:15px}.search-line{height:31px;background:white;border:1px solid #e2e6ec;border-radius:7px;color:#a1a8b3;font-size:8px;padding:8px 10px}.chips{display:flex;gap:5px;margin:10px 0}.chips>*{font-size:7px;border:1px solid #dce1e8;border-radius:20px;padding:3px 7px;font-style:normal}.chips b{background:var(--blue);color:white;border-color:var(--blue)}.products{display:grid;grid-template-columns:1fr 1fr;gap:8px}.products article{height:130px;background:white;border:1px solid #e0e4ea;border-radius:9px;padding:10px}.products article i{width:28px;height:28px;background:#e9eeff;color:var(--blue);font-style:normal;font-size:9px;font-weight:800;border-radius:7px;display:grid;place-items:center}.products article b,.products article small,.products article strong{display:block}.products article b{font-size:9px;margin-top:12px}.products article small{font-size:7px;color:#939bab}.products article strong{font-size:11px;color:var(--blue);margin-top:8px}.cart-area{background:white;border-left:1px solid #e2e5ea;padding:14px 11px;position:relative}.cart-area>p{font-size:6px;letter-spacing:.1em;color:var(--blue);font-weight:800;margin:0}.cart-area h3{margin:1px 0 14px;font-size:12px}.line{display:flex;justify-content:space-between;font-size:8px;padding:10px 0;border-top:1px solid #eef0f3}.line small{display:block;color:#9299a6}.pay{position:absolute;inset:auto 0 0;background:#121b2d;color:white;padding:12px}.pay span{display:block;font-size:7px;color:#aeb6c6}.pay strong{font-size:18px;display:block}.pay button{width:100%;border:0;border-radius:6px;background:#5468f4;color:white;padding:8px;font-size:8px;font-weight:800}.blue-note{position:absolute;right:2px;top:-2px;z-index:2;background:var(--blue);color:white;padding:13px 16px;border-radius:50%;width:102px;height:102px;display:flex;justify-content:center;flex-direction:column;text-align:center;font-family:var(--serif);font-size:15px;line-height:1.15;transform:rotate(7deg);box-shadow:0 8px 20px #1557e844}.blue-note small{font-family:var(--sans);font-size:7px;text-transform:uppercase;margin-top:4px;letter-spacing:.08em}.receipt{position:absolute;right:8px;bottom:0;background:white;width:130px;padding:14px 12px;text-align:center;box-shadow:0 18px 35px #1b28462c;transform:rotate(-4deg);font-family:monospace}.receipt:after{content:'';position:absolute;bottom:-7px;left:0;right:0;height:8px;background:linear-gradient(135deg,white 5px,transparent 0) 0 0/10px 10px repeat-x}.receipt img{width:25px;height:25px;object-fit:cover}.receipt b,.receipt small,.receipt strong,.receipt span{display:block}.receipt b{font-size:8px}.receipt small{font-size:6px;text-align:left;margin-top:5px}.receipt strong{font-size:10px}.receipt span{font-size:6px;color:var(--green)}.shop-strip{border-block:1px solid var(--line);padding:15px 0;background:#f1ede4;overflow:hidden}.shop-strip p{text-align:center;margin:0 0 6px;font-size:10px;text-transform:uppercase;letter-spacing:.16em;color:#777e8c}.shop-strip div{display:flex;justify-content:center;align-items:center;gap:20px;white-space:nowrap;font-family:var(--serif);font-size:18px}.shop-strip i{font-size:7px;color:var(--blue)}.section{padding-block:105px}.section-heading{max-width:660px}.section-heading h2,.print-copy h2,.trial-inner h2{font-size:clamp(42px,5vw,64px)}.sale-flow{display:grid;grid-template-columns:.82fr 1.18fr;gap:90px}.sale-flow ol{margin:0;padding:0;list-style:none;border-top:1px solid var(--line)}.sale-flow li{display:grid;grid-template-columns:56px 1fr;border-bottom:1px solid var(--line);padding:23px 0}.sale-flow li>b{font-family:var(--serif);font-size:16px;color:var(--blue)}.sale-flow h3{font-size:20px;margin:0 0 4px}.sale-flow p{margin:0;color:var(--muted);font-size:14px}.feature-section{background:var(--ink);color:white}.feature-layout{display:grid;grid-template-columns:.8fr 1.2fr;gap:100px}.sticky-copy{position:sticky;top:110px;align-self:start}.eyebrow.light{color:#aeb9d0}.eyebrow.light span{background:var(--cyan)}.sticky-copy>p:last-child{color:#aeb9d0;max-width:510px;margin-top:24px}.feature-list{border-top:1px solid #34405a}.feature-list article{display:grid;grid-template-columns:55px 1fr;border-bottom:1px solid #34405a;padding:29px 0}.feature-list article>b{color:var(--cyan);font-family:var(--serif)}.feature-list h3{font-size:22px;margin:0 0 5px}.feature-list p{margin:0;color:#aeb9d0;font-size:14px}.print-story{display:grid;grid-template-columns:.85fr 1.15fr;align-items:center;gap:110px}.printer-drawing{height:390px;position:relative;background:#dfe7ff;border-radius:48% 52% 45% 55%;display:flex;align-items:center;justify-content:center}.printer-body{width:245px;height:205px;background:#151a24;border-radius:26px 26px 35px 35px;position:relative;box-shadow:inset 0 -25px 0 #090c12,0 25px 50px #11182a3b}.printer-top{position:absolute;width:213px;height:70px;background:#2b3341;border-radius:28px 28px 8px 8px;top:83px;z-index:2}.printer-body span{position:absolute;width:165px;height:8px;background:#05070a;border-radius:6px;left:40px;top:54px}.paper{position:absolute;z-index:3;width:145px;height:220px;background:#fff;top:138px;padding:24px 17px;box-shadow:0 10px 30px #0d14262c}.paper b,.paper strong{display:block;font-family:monospace;font-size:11px;text-align:center}.paper i{display:block;height:4px;background:#d4d7db;margin-top:13px}.paper i:nth-of-type(2){width:70%}.paper strong{font-size:14px;margin-top:20px}.print-copy>p:not(.eyebrow){font-size:17px;color:var(--muted);max-width:590px}.tick-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:30px;font-size:13px;font-weight:700}.tick-grid span{border-top:1px solid var(--line);padding-top:10px}.more-features{border-top:1px solid var(--line)}.more-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:50px}.more-grid>div{background:#fbf8f1;border:1px solid var(--line);border-radius:12px;padding:26px}.more-grid b{display:block;font-size:15px;margin-bottom:6px}.more-grid p{margin:0;font-size:13px;color:var(--muted);line-height:1.55}.center{text-align:center;margin:auto}.center .eyebrow{justify-content:center}.center>p:last-child{color:var(--muted);max-width:640px;margin:20px auto 0}.edition-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:50px}.edition{border:1px solid var(--line);background:#fbf8f1;padding:38px;border-radius:12px}.edition.featured{background:#e6edff;border-color:#bdcbf3}.edition-label{text-transform:uppercase;letter-spacing:.13em;font-size:9px;font-weight:850;color:var(--blue)}.edition h3{font-family:var(--serif);font-size:40px;margin:10px 0 4px;font-weight:500}.edition-lede{color:var(--muted);min-height:52px}.edition ul{list-style:none;padding:18px 0;margin:18px 0;border-block:1px solid #d4d6dc}.edition li{padding:7px 0;font-size:14px}.edition li:before{content:'✓';color:var(--green);font-weight:900;margin-right:9px}.edition a{font-weight:850;color:var(--blue)}.edition a span{float:right}.honest-note{margin:18px 0 0;background:#eee9dd;padding:14px 18px;border-left:3px solid #9b8c6c;font-size:12px;color:#5f625f}.trial-section{background:var(--blue);color:white}.trial-inner{display:grid;grid-template-columns:1fr .75fr;gap:100px;align-items:end}.trial-inner>div:last-child p{color:#dbe4ff;margin:0 0 24px}.button.pale{background:var(--paper);color:var(--ink);box-shadow:0 6px 0 #0b369a}.faq{display:grid;grid-template-columns:.72fr 1.28fr;gap:90px}.faq-list{border-top:1px solid var(--line)}details{border-bottom:1px solid var(--line)}summary{list-style:none;cursor:pointer;font-weight:800;padding:22px 0;display:flex;justify-content:space-between;gap:20px}summary::-webkit-details-marker{display:none}summary i{font-style:normal;color:var(--blue);font-size:22px;transition:.2s}details[open] summary i{transform:rotate(45deg)}details p{margin:-5px 40px 22px 0;color:var(--muted);font-size:14px}footer{background:#0a1223;color:white;padding:65px 0 22px}.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:60px}.footer-brand img{width:48px;height:48px}.footer-grid>div:first-child p{color:#8f9ab0;font-size:13px;max-width:300px}.footer-grid>div:not(:first-child){display:flex;flex-direction:column;gap:7px}.footer-grid b{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:#8f9ab0;margin-bottom:8px}.footer-grid a{font-size:14px}.footer-bottom{border-top:1px solid #283247;margin-top:50px;padding-top:18px;display:flex;justify-content:space-between;color:#78849b;font-size:11px}
.feature-showcase h2{font-family:var(--serif);font-weight:500;letter-spacing:-.045em;line-height:.98;font-size:clamp(42px,5vw,64px);margin:0}.feature-intro{display:grid;grid-template-columns:1fr .7fr;gap:80px;align-items:end;margin-bottom:48px}.feature-intro>p{color:#aeb9d0;margin:0;max-width:500px}.feature-slider{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(300px,.65fr);background:#fff;color:var(--ink);border-radius:14px;overflow:hidden;box-shadow:0 28px 70px #050a1680;outline:none}.feature-slider:focus-visible{box-shadow:0 0 0 3px var(--cyan),0 28px 70px #050a1680}.feature-visual{min-width:0;background:#e9ecf2;border-right:1px solid #d8dce4}.screen-caption{height:46px;display:flex;align-items:center;justify-content:space-between;padding:0 17px;background:#eef1f6;color:#687287;font-size:11px}.screen-caption span{font-weight:850;color:#17223b}.screen-caption small{font-size:9px;text-transform:uppercase;letter-spacing:.08em}.feature-visual img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;object-position:top left}.feature-detail{padding:38px 34px;display:flex;flex-direction:column}.slide-count{font-family:var(--serif);font-size:15px;color:var(--blue)}.slide-eyebrow{margin:45px 0 10px!important;color:var(--blue)!important;font-size:10px!important;text-transform:uppercase;letter-spacing:.15em;font-weight:850}.feature-detail h3{font-family:var(--serif);font-weight:500;font-size:34px;line-height:1.08;letter-spacing:-.035em;margin:0}.feature-detail>p{color:var(--muted);font-size:14px;line-height:1.7;margin:18px 0}.feature-detail ul{list-style:none;padding:0;margin:3px 0 30px}.feature-detail li{border-top:1px solid #e2e4e9;padding:8px 0;font-size:12px;font-weight:750}.feature-detail li:before{content:'✓';color:var(--green);margin-right:8px}.slider-controls{margin-top:auto;display:flex;align-items:center;justify-content:space-between}.slider-controls>button{width:42px;height:42px;border:1px solid #d7dbe3;border-radius:50%;background:#fff;color:var(--ink);font-size:20px;cursor:pointer}.slider-controls>button:hover{background:var(--ink);color:#fff}.slider-dots{display:flex;gap:7px}.slider-dots button{width:7px;height:7px;padding:0;border:0;border-radius:50%;background:#c7ccd6;cursor:pointer}.slider-dots button.active{width:24px;border-radius:8px;background:var(--blue)}.feature-fade-enter-active,.feature-fade-leave-active{transition:opacity .22s ease}.feature-fade-enter-from,.feature-fade-leave-to{opacity:0}
@media(max-width:900px){.feature-intro{grid-template-columns:1fr;gap:20px}.feature-slider{grid-template-columns:1fr}.feature-visual{border-right:0;border-bottom:1px solid #d8dce4}.feature-detail{min-height:390px}.slide-eyebrow{margin-top:15px!important}.site-header{height:68px}.site-header nav{display:none;position:absolute;z-index:20;top:68px;left:20px;right:20px;background:var(--ink);color:white;padding:22px;flex-direction:column;align-items:flex-start;border-radius:8px;box-shadow:0 18px 50px #101a3255}.site-header nav.open{display:flex}.site-header nav a{color:white}.menu-button{display:block;margin-left:auto}.header-cta{display:none}.hero,.sale-flow,.feature-layout,.print-story,.trial-inner,.faq{grid-template-columns:1fr}.hero{padding-top:55px;gap:42px}.counter-scene{max-width:680px;width:100%;margin:auto}.sale-flow,.feature-layout,.print-story,.faq{gap:45px}.sticky-copy{position:static}.edition-grid{grid-template-columns:1fr}.trial-inner{align-items:start}.section{padding-block:78px}.print-story .printer-drawing{max-width:520px;width:100%;margin:auto}.more-grid{grid-template-columns:1fr 1fr}.footer-grid{grid-template-columns:1.5fr 1fr 1fr}}
@media(max-width:600px){.feature-intro{margin-bottom:28px}.feature-slider{border-radius:9px}.screen-caption{height:38px}.screen-caption small{display:none}.feature-visual img{aspect-ratio:16/11}.feature-detail{padding:25px 22px;min-height:360px}.feature-detail h3{font-size:29px}.slider-controls>button{width:38px;height:38px}.wrap{width:min(100% - 30px,1180px)}.site-header{width:calc(100% - 30px)}.brand img{width:38px;height:38px}.hero{min-height:0;padding-block:45px 58px}.hero h1{font-size:49px}.hero-lede{font-size:16px}.hero-actions{align-items:flex-start;flex-direction:column;gap:20px}.hero-notes{display:grid;gap:5px;margin-top:30px}.counter-scene{padding:20px 0 38px}.screen-shell{padding:6px;border-radius:11px}.pos-ui{height:295px;grid-template-columns:30px 1fr 108px}.product-area{padding:9px}.products{gap:5px}.products article{height:94px;padding:7px}.products article i{width:20px;height:20px}.products article b{margin-top:5px}.cart-area{padding:9px 7px}.line{font-size:6px}.pay{padding:8px}.blue-note{width:78px;height:78px;font-size:11px;right:-4px;top:-18px}.receipt{width:100px;padding:10px;right:3px}.shop-strip div{justify-content:flex-start;animation:marquee 16s linear infinite}.shop-strip p{text-align:left;padding-left:15px}.section{padding-block:65px}.section-heading h2,.print-copy h2,.trial-inner h2{font-size:41px}.sale-flow li{grid-template-columns:45px 1fr}.feature-list article{grid-template-columns:42px 1fr}.printer-drawing{height:320px}.printer-body{transform:scale(.8)}.printer-top{transform:scale(.8);top:52px}.paper{transform:scale(.8);top:112px}.tick-grid{grid-template-columns:1fr}.more-grid{grid-template-columns:1fr}.edition{padding:27px 23px}.edition h3{font-size:34px}.faq{gap:35px}.footer-grid{grid-template-columns:1fr 1fr}.footer-grid>div:first-child{grid-column:1/-1}.footer-bottom{gap:16px;align-items:flex-start;flex-direction:column}@keyframes marquee{to{transform:translateX(-55%)}}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.shop-strip div{animation:none}.button:hover{transform:none}}

</style>
