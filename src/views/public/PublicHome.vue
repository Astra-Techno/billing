<script setup>
import { onBeforeUnmount, onMounted } from 'vue'

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
})
onBeforeUnmount(() => cleanUp())
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
        <p class="hero-lede">Create GST bills, track stock, collect payments and print compact receipts from one simple counter. The Windows edition keeps billing even when the internet stops.</p>
        <div class="hero-actions">
          <a class="button primary" href="/register">Start your free trial <span>↗</span></a>
          <a class="text-link" href="#how">See a counter sale <span>↓</span></a>
        </div>
        <ul class="hero-notes" aria-label="Key benefits">
          <li>No billing limit mentioned</li>
          <li>Cash, UPI &amp; card</li>
          <li>58mm receipt ready</li>
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
      <div><span>Grocery</span><i>◆</i><span>Textiles</span><i>◆</i><span>Hardware</span><i>◆</i><span>Mobile shops</span><i>◆</i><span>Wholesale</span><i>◆</i><span>Services</span></div>
    </section>

    <section id="how" class="section wrap sale-flow">
      <div class="section-heading">
        <p class="eyebrow"><span></span> One counter sale</p>
        <h2>From product to printed bill in four clear steps.</h2>
      </div>
      <ol>
        <li><b>01</b><div><h3>Find or scan</h3><p>Search by name, SKU or barcode. Your saved rate and GST fill automatically.</p></div></li>
        <li><b>02</b><div><h3>Check the cart</h3><p>Change quantities, choose a customer and see the final amount before payment.</p></div></li>
        <li><b>03</b><div><h3>Collect payment</h3><p>Record Cash, UPI or Card without making the customer wait.</p></div></li>
        <li><b>04</b><div><h3>Print &amp; update stock</h3><p>Print a compact receipt. Stock moves automatically when stock control is enabled.</p></div></li>
      </ol>
    </section>

    <section id="features" class="section feature-section">
      <div class="wrap feature-layout">
        <div class="section-heading sticky-copy">
          <p class="eyebrow light"><span></span> More than a bill</p>
          <h2>Your counter, stock room and collections in one place.</h2>
          <p>Start with simple billing. Switch on stock, GST reports or staff access only when your business needs them.</p>
        </div>
        <div class="feature-list">
          <article><b>01</b><div><h3>GST billing without the clutter</h3><p>Tax invoice, bill of supply, retail invoice, quotations, HSN/SAC and CGST/SGST/IGST calculations.</p></div></article>
          <article><b>02</b><div><h3>Stock control you can choose</h3><p>Use billing only, show a stock warning, or prevent sales beyond available quantity.</p></div></article>
          <article><b>03</b><div><h3>Shop and godown visibility</h3><p>Track location stock, move goods between locations and set a selling price for each shop.</p></div></article>
          <article><b>04</b><div><h3>Customer balance at a glance</h3><p>Record partial payments, view statements, follow overdue bills and share details on WhatsApp.</p></div></article>
          <article><b>05</b><div><h3>Documents your business already uses</h3><p>Quotes, purchase orders, delivery challans, credit notes, expenses and useful owner reports.</p></div></article>
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
        <p>Print A4 invoices or compact 58mm receipts. The Windows app can send bills directly to a paired SC588/PSF588 Bluetooth thermal printer.</p>
        <div class="tick-grid"><span>✓ Bluetooth test inside Settings</span><span>✓ UPI details on the bill</span><span>✓ Clear GST and totals</span><span>✓ Reprint when needed</span></div>
      </div>
    </section>

    <section id="editions" class="section editions wrap">
      <div class="section-heading center">
        <p class="eyebrow"><span></span> Choose your setup</p>
        <h2>At the counter or wherever you are.</h2>
        <p>Both editions use the same familiar billing workflow. Choose based on where your data and team need to work.</p>
      </div>
      <div class="edition-grid">
        <article class="edition featured">
          <div class="edition-label">Best for a billing counter</div>
          <h3>Windows Offline</h3><p class="edition-lede">For one PC that must keep billing through internet problems.</p>
          <ul><li>Billing works without internet</li><li>Local MySQL data</li><li>Direct SC588 Bluetooth printing</li><li>Backup and restore</li><li>Single-PC activation</li></ul>
          <a href="#trial">Request desktop setup <span>→</span></a>
        </article>
        <article class="edition">
          <div class="edition-label">Best for access anywhere</div>
          <h3>Cloud Web</h3><p class="edition-lede">For owners and teams using browsers across phones and computers.</p>
          <ul><li>No installation</li><li>Use on modern browsers</li><li>Multiple team logins</li><li>Online business access</li><li>Browser-based printing</li></ul>
          <a href="/register">Start web trial <span>→</span></a>
        </article>
      </div>
      <p class="honest-note"><b>Good to know:</b> Separate offline PCs do not share live stock. Use the cloud edition when multiple counters or branches must see the same data at the same time.</p>
    </section>

    <section id="trial" class="section trial-section">
      <div class="wrap trial-inner">
        <div><p class="eyebrow light"><span></span> See it with your own products</p><h2>Take one real sale for a test drive.</h2></div>
        <div><p>Create your business, add a few items and make a sample bill. If you need the Windows app and printer setup, arrange an assisted installation after the trial.</p><a class="button pale" href="/register">Create free account <span>↗</span></a></div>
      </div>
    </section>

    <section id="faq" class="section wrap faq">
      <div class="section-heading"><p class="eyebrow"><span></span> Straight answers</p><h2>Before you set up your counter.</h2></div>
      <div class="faq-list">
        <details><summary>Will billing work without internet?<i>+</i></summary><p>Yes, in the Windows offline edition. Initial activation needs internet, but everyday billing, customers, products, payments, reports and local printing continue offline.</p></details>
        <details><summary>Can I use the app without stock maintenance?<i>+</i></summary><p>Yes. Choose Billing Only and stock will not be checked or reduced. You can later switch to warning or strict stock control.</p></details>
        <details><summary>Does it print on 58mm thermal paper?<i>+</i></summary><p>Yes. The app includes a compact 58mm layout and direct Windows Bluetooth support for tested SC588/PSF588-compatible printers.</p></details>
        <details><summary>Can I import my existing products?<i>+</i></summary><p>Yes. Products can be imported from CSV with rates, tax, SKU, opening stock and shop pricing.</p></details>
        <details><summary>What happens if the PC has a problem?<i>+</i></summary><p>The offline edition includes manual and daily local backups plus validated restore. Keep a recent backup copy on a separate USB drive.</p></details>
        <details><summary>Can staff use separate logins?<i>+</i></summary><p>Yes. Owners can create staff accounts and decide which modules and actions each person may use.</p></details>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap footer-grid">
      <div><a class="brand footer-brand" href="#top"><img src="/logo.png" alt=""><span>AI Billing</span></a><p>Practical billing software for Indian shops and small businesses.</p></div>
      <div><b>Product</b><a href="#features">Features</a><a href="#editions">Desktop &amp; Web</a><a href="/register">Free trial</a></div>
      <div><b>Useful</b><a href="#how">How it works</a><a href="#faq">Questions</a><a href="/login">Customer login</a></div>
    </div>
    <div class="wrap footer-bottom"><span>© 2026 AI Billing</span><span>GST billing · POS · Stock · Receipts</span></div>
  </footer>
  
</div>
</template>

<style scoped>
.public-site{--paper:#f7f3ea;--ink:#101a32;--muted:#586174;--blue:#1557e8;--cyan:#4bd5ee;--line:#d9d5cb;--white:#fff;--green:#169c72;--serif:Georgia,'Times New Roman',serif;--sans:Inter,'Segoe UI',Arial,sans-serif}.public-site *{box-sizing:border-box}.public-site{margin:0;background:var(--paper);color:var(--ink);font-family:var(--sans);font-size:16px;line-height:1.6}a{color:inherit;text-decoration:none}.wrap{width:min(1180px,calc(100% - 48px));margin-inline:auto}.site-header{height:82px;display:flex;align-items:center;gap:32px;width:min(1260px,calc(100% - 40px));margin:auto;border-bottom:1px solid var(--line)}.brand{display:flex;align-items:center;gap:10px;font-weight:800;letter-spacing:-.02em}.brand img{width:44px;height:44px;object-fit:cover;border-radius:12px}.site-header nav{display:flex;align-items:center;gap:28px;margin-left:auto}.site-header nav a{font-size:14px;font-weight:650;color:#35405a}.site-header nav a:hover{color:var(--blue)}.header-cta{background:var(--ink);color:white;padding:10px 18px;border-radius:7px;font-size:14px;font-weight:750}.menu-button{display:none;background:none;border:0;font:inherit;font-weight:700}.hero{min-height:690px;display:grid;grid-template-columns:.87fr 1.13fr;align-items:center;gap:54px;padding-block:72px 78px}.eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 18px;text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:850;color:#536079}.eyebrow span{width:28px;height:2px;background:var(--blue)}.hero h1,.section-heading h2,.print-copy h2,.trial-inner h2{font-family:var(--serif);font-weight:500;letter-spacing:-.045em;line-height:.98;margin:0}.hero h1{font-size:clamp(54px,6vw,82px)}.hero h1 em{font-weight:500;color:var(--blue)}.hero-lede{font-size:18px;line-height:1.65;color:var(--muted);max-width:590px;margin:26px 0 30px}.hero-actions{display:flex;align-items:center;gap:26px}.button{display:inline-flex;align-items:center;justify-content:center;gap:18px;padding:14px 19px;border-radius:8px;font-weight:800;font-size:14px}.button.primary{background:var(--blue);color:white;box-shadow:0 7px 0 #0b348c}.button:hover{transform:translateY(-1px)}.text-link{font-weight:800;border-bottom:1px solid #a8a294}.text-link span{margin-left:8px}.hero-notes{display:flex;gap:24px;list-style:none;padding:0;margin:38px 0 0;color:#677083;font-size:12px}.hero-notes li:before{content:'✓';color:var(--green);font-weight:900;margin-right:6px}.counter-scene{position:relative;padding:34px 32px 52px 0}.screen-shell{background:#151d2f;padding:9px;border-radius:16px;box-shadow:0 30px 80px rgba(15,27,50,.22);transform:rotate(1deg)}.screen-bar{height:28px;color:#9ca6bb;display:flex;align-items:center;gap:6px;padding:0 8px;font-size:9px}.screen-bar i{width:6px;height:6px;border-radius:50%;background:#526078}.screen-bar b{margin-left:7px;font-weight:650}.pos-ui{height:390px;background:#f5f7fb;border-radius:9px;display:grid;grid-template-columns:42px 1fr 155px;overflow:hidden}.pos-ui aside{background:#111a2b;display:flex;align-items:center;flex-direction:column;gap:18px;padding-top:16px}.pos-ui aside strong{color:white;background:#3b5cf2;border-radius:8px;padding:4px;font-size:10px}.pos-ui aside span{width:14px;height:14px;border:2px solid #788399;border-radius:4px}.pos-ui aside .active{border-color:#6a7cff;background:#5669ff}.product-area{padding:15px}.search-line{height:31px;background:white;border:1px solid #e2e6ec;border-radius:7px;color:#a1a8b3;font-size:8px;padding:8px 10px}.chips{display:flex;gap:5px;margin:10px 0}.chips>*{font-size:7px;border:1px solid #dce1e8;border-radius:20px;padding:3px 7px;font-style:normal}.chips b{background:var(--blue);color:white;border-color:var(--blue)}.products{display:grid;grid-template-columns:1fr 1fr;gap:8px}.products article{height:130px;background:white;border:1px solid #e0e4ea;border-radius:9px;padding:10px}.products article i{width:28px;height:28px;background:#e9eeff;color:var(--blue);font-style:normal;font-size:9px;font-weight:800;border-radius:7px;display:grid;place-items:center}.products article b,.products article small,.products article strong{display:block}.products article b{font-size:9px;margin-top:12px}.products article small{font-size:7px;color:#939bab}.products article strong{font-size:11px;color:var(--blue);margin-top:8px}.cart-area{background:white;border-left:1px solid #e2e5ea;padding:14px 11px;position:relative}.cart-area>p{font-size:6px;letter-spacing:.1em;color:var(--blue);font-weight:800;margin:0}.cart-area h3{margin:1px 0 14px;font-size:12px}.line{display:flex;justify-content:space-between;font-size:8px;padding:10px 0;border-top:1px solid #eef0f3}.line small{display:block;color:#9299a6}.pay{position:absolute;inset:auto 0 0;background:#121b2d;color:white;padding:12px}.pay span{display:block;font-size:7px;color:#aeb6c6}.pay strong{font-size:18px;display:block}.pay button{width:100%;border:0;border-radius:6px;background:#5468f4;color:white;padding:8px;font-size:8px;font-weight:800}.blue-note{position:absolute;right:2px;top:-2px;z-index:2;background:var(--blue);color:white;padding:13px 16px;border-radius:50%;width:102px;height:102px;display:flex;justify-content:center;flex-direction:column;text-align:center;font-family:var(--serif);font-size:15px;line-height:1.15;transform:rotate(7deg);box-shadow:0 8px 20px #1557e844}.blue-note small{font-family:var(--sans);font-size:7px;text-transform:uppercase;margin-top:4px;letter-spacing:.08em}.receipt{position:absolute;right:8px;bottom:0;background:white;width:130px;padding:14px 12px;text-align:center;box-shadow:0 18px 35px #1b28462c;transform:rotate(-4deg);font-family:monospace}.receipt:after{content:'';position:absolute;bottom:-7px;left:0;right:0;height:8px;background:linear-gradient(135deg,white 5px,transparent 0) 0 0/10px 10px repeat-x}.receipt img{width:25px;height:25px;object-fit:cover}.receipt b,.receipt small,.receipt strong,.receipt span{display:block}.receipt b{font-size:8px}.receipt small{font-size:6px;text-align:left;margin-top:5px}.receipt strong{font-size:10px}.receipt span{font-size:6px;color:var(--green)}.shop-strip{border-block:1px solid var(--line);padding:15px 0;background:#f1ede4;overflow:hidden}.shop-strip p{text-align:center;margin:0 0 6px;font-size:10px;text-transform:uppercase;letter-spacing:.16em;color:#777e8c}.shop-strip div{display:flex;justify-content:center;align-items:center;gap:20px;white-space:nowrap;font-family:var(--serif);font-size:18px}.shop-strip i{font-size:7px;color:var(--blue)}.section{padding-block:105px}.section-heading{max-width:660px}.section-heading h2,.print-copy h2,.trial-inner h2{font-size:clamp(42px,5vw,64px)}.sale-flow{display:grid;grid-template-columns:.82fr 1.18fr;gap:90px}.sale-flow ol{margin:0;padding:0;list-style:none;border-top:1px solid var(--line)}.sale-flow li{display:grid;grid-template-columns:56px 1fr;border-bottom:1px solid var(--line);padding:23px 0}.sale-flow li>b{font-family:var(--serif);font-size:16px;color:var(--blue)}.sale-flow h3{font-size:20px;margin:0 0 4px}.sale-flow p{margin:0;color:var(--muted);font-size:14px}.feature-section{background:var(--ink);color:white}.feature-layout{display:grid;grid-template-columns:.8fr 1.2fr;gap:100px}.sticky-copy{position:sticky;top:110px;align-self:start}.eyebrow.light{color:#aeb9d0}.eyebrow.light span{background:var(--cyan)}.sticky-copy>p:last-child{color:#aeb9d0;max-width:510px;margin-top:24px}.feature-list{border-top:1px solid #34405a}.feature-list article{display:grid;grid-template-columns:55px 1fr;border-bottom:1px solid #34405a;padding:29px 0}.feature-list article>b{color:var(--cyan);font-family:var(--serif)}.feature-list h3{font-size:22px;margin:0 0 5px}.feature-list p{margin:0;color:#aeb9d0;font-size:14px}.print-story{display:grid;grid-template-columns:.85fr 1.15fr;align-items:center;gap:110px}.printer-drawing{height:390px;position:relative;background:#dfe7ff;border-radius:48% 52% 45% 55%;display:flex;align-items:center;justify-content:center}.printer-body{width:245px;height:205px;background:#151a24;border-radius:26px 26px 35px 35px;position:relative;box-shadow:inset 0 -25px 0 #090c12,0 25px 50px #11182a3b}.printer-top{position:absolute;width:213px;height:70px;background:#2b3341;border-radius:28px 28px 8px 8px;top:83px;z-index:2}.printer-body span{position:absolute;width:165px;height:8px;background:#05070a;border-radius:6px;left:40px;top:54px}.paper{position:absolute;z-index:3;width:145px;height:220px;background:#fff;top:138px;padding:24px 17px;box-shadow:0 10px 30px #0d14262c}.paper b,.paper strong{display:block;font-family:monospace;font-size:11px;text-align:center}.paper i{display:block;height:4px;background:#d4d7db;margin-top:13px}.paper i:nth-of-type(2){width:70%}.paper strong{font-size:14px;margin-top:20px}.print-copy>p:not(.eyebrow){font-size:17px;color:var(--muted);max-width:590px}.tick-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:30px;font-size:13px;font-weight:700}.tick-grid span{border-top:1px solid var(--line);padding-top:10px}.center{text-align:center;margin:auto}.center .eyebrow{justify-content:center}.center>p:last-child{color:var(--muted);max-width:640px;margin:20px auto 0}.edition-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:50px}.edition{border:1px solid var(--line);background:#fbf8f1;padding:38px;border-radius:12px}.edition.featured{background:#e6edff;border-color:#bdcbf3}.edition-label{text-transform:uppercase;letter-spacing:.13em;font-size:9px;font-weight:850;color:var(--blue)}.edition h3{font-family:var(--serif);font-size:40px;margin:10px 0 4px;font-weight:500}.edition-lede{color:var(--muted);min-height:52px}.edition ul{list-style:none;padding:18px 0;margin:18px 0;border-block:1px solid #d4d6dc}.edition li{padding:7px 0;font-size:14px}.edition li:before{content:'✓';color:var(--green);font-weight:900;margin-right:9px}.edition a{font-weight:850;color:var(--blue)}.edition a span{float:right}.honest-note{margin:18px 0 0;background:#eee9dd;padding:14px 18px;border-left:3px solid #9b8c6c;font-size:12px;color:#5f625f}.trial-section{background:var(--blue);color:white}.trial-inner{display:grid;grid-template-columns:1fr .75fr;gap:100px;align-items:end}.trial-inner>div:last-child p{color:#dbe4ff;margin:0 0 24px}.button.pale{background:var(--paper);color:var(--ink);box-shadow:0 6px 0 #0b369a}.faq{display:grid;grid-template-columns:.72fr 1.28fr;gap:90px}.faq-list{border-top:1px solid var(--line)}details{border-bottom:1px solid var(--line)}summary{list-style:none;cursor:pointer;font-weight:800;padding:22px 0;display:flex;justify-content:space-between;gap:20px}summary::-webkit-details-marker{display:none}summary i{font-style:normal;color:var(--blue);font-size:22px;transition:.2s}details[open] summary i{transform:rotate(45deg)}details p{margin:-5px 40px 22px 0;color:var(--muted);font-size:14px}footer{background:#0a1223;color:white;padding:65px 0 22px}.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:60px}.footer-brand img{width:48px;height:48px}.footer-grid>div:first-child p{color:#8f9ab0;font-size:13px;max-width:300px}.footer-grid>div:not(:first-child){display:flex;flex-direction:column;gap:7px}.footer-grid b{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:#8f9ab0;margin-bottom:8px}.footer-grid a{font-size:14px}.footer-bottom{border-top:1px solid #283247;margin-top:50px;padding-top:18px;display:flex;justify-content:space-between;color:#78849b;font-size:11px}
@media(max-width:900px){.site-header{height:68px}.site-header nav{display:none;position:absolute;z-index:20;top:68px;left:20px;right:20px;background:var(--ink);color:white;padding:22px;flex-direction:column;align-items:flex-start;border-radius:8px;box-shadow:0 18px 50px #101a3255}.site-header nav.open{display:flex}.site-header nav a{color:white}.menu-button{display:block;margin-left:auto}.header-cta{display:none}.hero,.sale-flow,.feature-layout,.print-story,.trial-inner,.faq{grid-template-columns:1fr}.hero{padding-top:55px;gap:42px}.counter-scene{max-width:680px;width:100%;margin:auto}.sale-flow,.feature-layout,.print-story,.faq{gap:45px}.sticky-copy{position:static}.edition-grid{grid-template-columns:1fr}.trial-inner{align-items:start}.section{padding-block:78px}.print-story .printer-drawing{max-width:520px;width:100%;margin:auto}.footer-grid{grid-template-columns:1.5fr 1fr 1fr}}
@media(max-width:600px){.wrap{width:min(100% - 30px,1180px)}.site-header{width:calc(100% - 30px)}.brand img{width:38px;height:38px}.hero{min-height:0;padding-block:45px 58px}.hero h1{font-size:49px}.hero-lede{font-size:16px}.hero-actions{align-items:flex-start;flex-direction:column;gap:20px}.hero-notes{display:grid;gap:5px;margin-top:30px}.counter-scene{padding:20px 0 38px}.screen-shell{padding:6px;border-radius:11px}.pos-ui{height:295px;grid-template-columns:30px 1fr 108px}.product-area{padding:9px}.products{gap:5px}.products article{height:94px;padding:7px}.products article i{width:20px;height:20px}.products article b{margin-top:5px}.cart-area{padding:9px 7px}.line{font-size:6px}.pay{padding:8px}.blue-note{width:78px;height:78px;font-size:11px;right:-4px;top:-18px}.receipt{width:100px;padding:10px;right:3px}.shop-strip div{justify-content:flex-start;animation:marquee 16s linear infinite}.shop-strip p{text-align:left;padding-left:15px}.section{padding-block:65px}.section-heading h2,.print-copy h2,.trial-inner h2{font-size:41px}.sale-flow li{grid-template-columns:45px 1fr}.feature-list article{grid-template-columns:42px 1fr}.printer-drawing{height:320px}.printer-body{transform:scale(.8)}.printer-top{transform:scale(.8);top:52px}.paper{transform:scale(.8);top:112px}.tick-grid{grid-template-columns:1fr}.edition{padding:27px 23px}.edition h3{font-size:34px}.faq{gap:35px}.footer-grid{grid-template-columns:1fr 1fr}.footer-grid>div:first-child{grid-column:1/-1}.footer-bottom{gap:16px;align-items:flex-start;flex-direction:column}@keyframes marquee{to{transform:translateX(-55%)}}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.shop-strip div{animation:none}.button:hover{transform:none}}

</style>
