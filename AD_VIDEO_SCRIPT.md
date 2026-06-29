# CloudBill — Ad Video Script & Feature List

## Tagline Options
- "Bill Smart. Grow Fast."
- "Your Business. One App."
- "GST Billing Made Simple."

---

## VIDEO SCRIPT (60 seconds)

### Scene 1: The Problem (0-8s)
**Visual:** Shopkeeper struggling with paper bills, calculator, messy ledger
**Voice:** "Still doing billing on paper? Losing track of payments? Spending hours on GST?"

### Scene 2: Introduce CloudBill (8-15s)
**Visual:** Phone screen — CloudBill opens with clean dashboard
**Voice:** "Meet CloudBill — the simplest billing app for Indian businesses."

### Scene 3: Create Invoice in Seconds (15-25s)
**Visual:** Quick demo — type product name, auto-fills price & GST, tap Save
**Voice:** "Create GST invoices in seconds. Just type, tap, done. No forms, no confusion."

### Scene 4: Get Paid Faster (25-32s)
**Visual:** WhatsApp share button, UPI QR code on invoice, payment recorded
**Voice:** "Share via WhatsApp. Customers scan QR to pay instantly. Track every rupee."

### Scene 5: Know Your Business (32-40s)
**Visual:** Dashboard with Balance strip, revenue chart, smart insights
**Voice:** "See your collections, expenses, and balance — all in one glance."

### Scene 6: Unique Features Flash (40-52s)
**Visual:** Quick cuts of each feature
**Voice:** "Dark mode. 3 invoice templates. Smart search. Keyboard shortcuts. Payroll. Guided tours. Works on any device."

### Scene 7: Call to Action (52-60s)
**Visual:** Logo + URL + "Free to start"
**Voice:** "CloudBill. Bill smart. Grow fast. Try free at cloudbill.in"

---

## COMPLETE FEATURE LIST

### CORE BILLING
| Feature | Description |
|---|---|
| GST Tax Invoice | Auto CGST+SGST (same state) or IGST (inter-state) |
| Bill of Supply | For non-GST registered or exempt goods |
| Proforma Invoice | Pre-sale estimate, not a tax document |
| Retail Invoice | Walk-in customers, no client details needed |
| Auto Invoice Numbering | Sequential numbers with customizable prefix |
| Multiple Bill Types | Tax Invoice, Bill of Supply, Retail, Export, Proforma |
| Amount in Words | Auto-converts total to Indian English words |
| UPI QR Code on Invoice | Customers scan to pay directly from printed invoice |
| Delivery Challan from Invoice | One-click transport document without prices |

### SMART PRODUCT & CLIENT MANAGEMENT
| Feature | Description |
|---|---|
| Inline Product Autocomplete | Type product name in line item — auto-fills price, unit, GST |
| Inline Client Autocomplete | Type customer name — auto-fills details, or create new inline |
| Save as Product | Unknown item? Create product on-the-fly while billing |
| Save as Client | New customer? Add name + mobile inline, no separate form needed |
| HSN/SAC Code Support | Government classification codes for GST compliance |
| Unit Types | Nos, Kg, Ltr, Hrs, Pcs, Mtr, Box, Set, Pair |
| Smart QTY Increments | Pcs = +1, Kg/Ltr = +0.1, Hrs = +0.5 based on unit type |
| Duplicate Product Prevention | Server-side check prevents adding same product twice |

### KEYBOARD-FIRST EXPERIENCE (Unique)
| Feature | Description |
|---|---|
| Alt+A | Add new line item |
| Alt+C | Jump to customer search |
| Alt+N | Jump to notes |
| Ctrl+Enter | Save form |
| Escape | Close dropdowns |
| Tab Flow | Natural tab order across two-column layout |
| Arrow Keys | Navigate product dropdown with up/down arrows |
| Shortcut Hints Bar | Desktop shows all shortcuts below toolbar |

### SMART SEARCH (Unique)
| Feature | Description |
|---|---|
| Universal Search | Search invoices, clients, AND products from one bar |
| Quick Actions | Type "new invoice" or "expense" — get navigation shortcuts |
| Command-style | Type "settings", "report", "payroll" to jump anywhere |
| Hint Suggestions | Empty search shows example queries to try |
| Mobile + Desktop | Works on both TopBar (mobile) and Ctrl+K (desktop) |

### 3 INVOICE PRINT TEMPLATES (Unique)
| Template | Style |
|---|---|
| Classic | Dark header, traditional formal business layout |
| Modern | Blue gradient header, alternating rows, pill-style dates, blue total bar |
| Minimal | Serif typography, thin lines, generous whitespace, elegant |
| Template Picker | Visual preview cards in Settings — click to select |
| Works with all modes | Tax Invoice, Bill of Supply, Proforma, Delivery Challan |

### GUIDED TOURS (Unique)
| Feature | Description |
|---|---|
| Auto-start | First-time users get step-by-step walkthrough |
| Every Page | Dashboard, Invoices, Clients, Products, Expenses, Payroll, Settings, Reports |
| Tooltip Highlights | Blue glow on target element + dark overlay |
| Replay Anytime | "Tour" button on each page to restart |
| Remembers | Won't repeat after completion (localStorage) |
| Dark Mode Support | Tooltips adapt to dark theme |

### PAYROLL SYSTEM (Unique for billing apps)
| Feature | Description |
|---|---|
| Staff Management | Add staff with name, role, salary, bank/UPI details |
| Monthly Payroll Run | One-click generates salary for all staff |
| Inline Editing | Edit days worked, bonus, deductions — auto-saves |
| Pay All | Bulk payment with method selection (Cash/UPI/NEFT) |
| Auto Expense | Each salary payment auto-creates an Expense record |
| Salary Category | Auto-creates "Salary" expense category |

### DARK MODE (Unique)
| Feature | Description |
|---|---|
| Full Dark Theme | 50+ CSS overrides for every component |
| One-click Toggle | Moon/sun icon in desktop header |
| Settings Toggle | Also available in Settings > Features |
| Persists | Remembers preference in localStorage |
| Print Unaffected | Invoices always print in light mode |

### DASHBOARD & ANALYTICS
| Feature | Description |
|---|---|
| 4 Stat Cards | Collected (this month), Pending, Expenses, Overdue |
| Balance Strip | Total Billed - Total Expenses = Balance (all time) |
| Revenue Chart | Last 6 months collection trend |
| Recent Invoices | Latest bills with status badges |
| Top Clients | Highest billing clients with totals |
| Action Required | Overdue invoice alerts |
| Smart Insights | Contextual business health messages |

### DOCUMENT TYPES
| Type | Purpose |
|---|---|
| Invoice | Bill with GST — "you owe me" |
| Quote | Price estimate — converts to invoice |
| Credit Note | Refund/adjustment against invoice |
| Debit Note | Additional charge on invoice |
| Purchase Order | Order to supplier |
| Delivery Challan | Goods transport document (no prices) |
| Payment | Money received against invoice |
| Expense | Business spending record |

### DESIGN & UX
| Feature | Description |
|---|---|
| GPay-inspired UI | Clean, card-based, avatar-centric design |
| Two-column Invoice Form | Items left, client + details right (desktop) |
| Chip-based Line Items | QTY/Unit and Price/Tax as colored chips |
| Floating Labels | Labels float above inputs when filled |
| Smooth Transitions | Spring-based page animations |
| Skeleton Loading | Shimmer placeholders while data loads |
| Beautiful Empty States | SVG illustrations + CTA when lists are empty |
| Card Hover Animations | Subtle lift + glow on hover |
| Glow Focus Rings | Blue glow on focused inputs |
| Toast Notifications | Slide-up success/error messages |
| Glass Morphism | Frosted glass effect on navigation bars |
| Mobile-first | Responsive design, bottom navigation, safe areas |

### REPORTS & GST
| Feature | Description |
|---|---|
| Outstanding Report | Unpaid invoices with aging |
| GST Summary | CGST, SGST, IGST breakdown |
| Profit & Loss | Revenue vs expenses |
| Expense Summary | Category-wise spending |
| GSTR-1 Ready | B2B and B2C invoice classification |
| HSN Summary | HSN-code-wise tax summary |

### MULTI-USER & SETTINGS
| Feature | Description |
|---|---|
| Role-based Access | Owner, Admin, Accountant, Staff roles |
| Team Invites | Invite via WhatsApp link |
| Business Profile | Logo, GSTIN, PAN, address |
| Bank Details | A/C, IFSC, UPI — printed on invoices |
| Feature Toggles | Enable/disable Quotes, PO, DC, Expenses, GST, Payroll |
| Invoice Preferences | Default terms, notes, numbering |

### INDIAN-SPECIFIC
| Feature | Description |
|---|---|
| Indian Number Format | 1,23,456.00 (lakh/crore system) |
| All Indian States | Dropdown with state codes for GST |
| GSTIN Validation | 15-digit format check |
| HSN/SAC Codes | Government tax classification |
| UPI Integration | QR code on invoices for instant payment |
| WhatsApp Sharing | Share invoice details via WhatsApp |
| Amount in Words | "Fifty Thousand Rupees Only" on invoices |
| Financial Year | April-March Indian fiscal year |

---

## UNIQUE SELLING POINTS (for video highlights)

1. **Fastest Invoice Creation** — Type product name, everything fills. One-line billing.
2. **Keyboard Power** — Alt+A, Alt+C, Ctrl+Enter. Bill without touching the mouse.
3. **Smart Search** — Type "new invoice" and go. Search everything from one bar.
4. **3 Print Templates** — Classic, Modern, Minimal. Pick your style.
5. **Built-in Payroll** — Staff salaries in 3 clicks. No separate HR app needed.
6. **Guided Tours** — First-time users learn by doing, not reading manuals.
7. **Dark Mode** — Easy on the eyes for late-night billing.
8. **Walk-in Billing** — No customer details needed for retail sales.
9. **UPI QR on Invoice** — Customer scans, pays instantly.
10. **Balance Dashboard** — Know your profit at a glance: Billed - Expenses = Balance.

---

## COMPETITOR COMPARISON

| Feature | CloudBill | Zoho Invoice | Vyapar | myBillBook |
|---|---|---|---|---|
| Smart Search with Actions | Yes | No | No | No |
| Keyboard Shortcuts | Full | Limited | No | No |
| 3 Print Templates | Yes | Yes | 1 | 2 |
| Built-in Payroll | Yes | No | No | No |
| Guided Tours | Yes | No | No | No |
| Dark Mode | Yes | No | No | No |
| Walk-in Billing | Yes | No | Yes | Yes |
| UPI QR on Invoice | Yes | No | Yes | Yes |
| Inline Product Create | Yes | No | No | No |
| Free Tier | Yes | Limited | Limited | Limited |

---

## TARGET AUDIENCE
- Small shop owners (retail, wholesale)
- Freelancers & consultants
- Service businesses (IT, design, maintenance)
- Small manufacturers
- Home businesses
- Anyone who needs GST billing without complexity
