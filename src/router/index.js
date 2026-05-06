import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  // Auth
  { path: '/login',    name: 'Login',    component: () => import('../views/auth/Login.vue'),    meta: { guest: true } },
  { path: '/register', name: 'Register', component: () => import('../views/auth/Register.vue'), meta: { guest: true } },

  // App (authenticated)
  {
    path: '/',
    component: () => import('../components/layout/AppLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '',          name: 'Dashboard',     component: () => import('../views/dashboard/Dashboard.vue') },
      {
        path: 'clients',   name: 'Clients',       component: () => import('../views/clients/ClientList.vue'),
        children: [
          { path: 'new',            name: 'ClientNew',       component: () => import('../views/clients/ClientForm.vue') },
          { path: ':id/edit',       name: 'ClientEdit',      component: () => import('../views/clients/ClientForm.vue') },
          { path: ':id/statement',  name: 'ClientStatement', component: () => import('../views/clients/CustomerStatement.vue') },
          { path: ':id',            name: 'ClientView',      component: () => import('../views/clients/ClientDetail.vue') },
        ]
      },
      { 
        path: 'invoices',         name: 'Invoices',     component: () => import('../views/invoices/InvoiceList.vue'),
        children: [
          { path: 'new',     name: 'InvoiceNew',   component: () => import('../views/invoices/InvoiceForm.vue') },
          { path: ':id/edit',name: 'InvoiceEdit',  component: () => import('../views/invoices/InvoiceForm.vue') },
          { path: ':id',     name: 'InvoiceView',  component: () => import('../views/invoices/InvoiceDetail.vue') },
        ]
      },
      { 
        path: 'quotes',           name: 'Quotes',       component: () => import('../views/quotes/QuoteList.vue'),
        children: [
          { path: 'new',       name: 'QuoteNew',     component: () => import('../views/quotes/QuoteForm.vue') },
          { path: ':id/edit',  name: 'QuoteEdit',    component: () => import('../views/quotes/QuoteForm.vue') },
          { path: ':id',       name: 'QuoteView',    component: () => import('../views/quotes/QuoteDetail.vue') },
        ]
      },
      { 
        path: 'expenses',         name: 'Expenses',     component: () => import('../views/expenses/ExpenseList.vue'),
        children: [
          { path: 'new',     name: 'ExpenseNew',   component: () => import('../views/expenses/ExpenseForm.vue') },
          { path: ':id/edit',name: 'ExpenseEdit',  component: () => import('../views/expenses/ExpenseForm.vue') },
        ]
      },
      { 
        path: 'products',         name: 'Products',     component: () => import('../views/products/ProductList.vue'),
        children: [
          { path: 'new',     name: 'ProductNew',   component: () => import('../views/products/ProductForm.vue') },
          { path: ':id/edit',name: 'ProductEdit',  component: () => import('../views/products/ProductForm.vue') },
        ]
      },
      { 
        path: 'credit-notes',     name: 'CreditNotes',  component: () => import('../views/credit-notes/CreditNoteList.vue'),
        children: [
          { path: 'new',     name: 'CreditNoteNew',   component: () => import('../views/credit-notes/CreditNoteForm.vue') },
          { path: ':id/edit',name: 'CreditNoteEdit',  component: () => import('../views/credit-notes/CreditNoteForm.vue') },
        ]
      },
      {
        path: 'purchase-orders',  name: 'PurchaseOrders', component: () => import('../views/purchase-orders/PurchaseOrderList.vue'),
        children: [
          { path: 'new',       name: 'PurchaseOrderNew',    component: () => import('../views/purchase-orders/PurchaseOrderForm.vue') },
          { path: ':id/edit',  name: 'PurchaseOrderEdit',   component: () => import('../views/purchase-orders/PurchaseOrderForm.vue') },
          { path: ':id',       name: 'PurchaseOrderView',   component: () => import('../views/purchase-orders/PurchaseOrderDetail.vue') },
        ]
      },
      {
        path: 'delivery-challans', name: 'DeliveryChallans', component: () => import('../views/delivery-challans/DeliveryChallanList.vue'),
        children: [
          { path: 'new',       name: 'DeliveryChallanNew',  component: () => import('../views/delivery-challans/DeliveryChallanForm.vue') },
          { path: ':id/edit',  name: 'DeliveryChallanEdit', component: () => import('../views/delivery-challans/DeliveryChallanForm.vue') },
          { path: ':id',       name: 'DeliveryChallanView', component: () => import('../views/delivery-challans/DeliveryChallanDetail.vue') },
        ]
      },
      { path: 'gst-returns',      name: 'GstReturns',   component: () => import('../views/gst/GstReturns.vue') },
      { path: 'reports',          name: 'Reports',      component: () => import('../views/reports/Reports.vue') },
      { path: 'settings',         name: 'Settings',     component: () => import('../views/settings/Settings.vue') },
      { path: 'help',             name: 'Help',         component: () => import('../views/help/Help.vue') },
      { path: 'more',             name: 'More',         component: () => import('../views/more/More.vue') },
    ],
  },

  // Print views (standalone, no AppLayout chrome)
  { path: '/print/invoice/:id', name: 'InvoicePrint', component: () => import('../views/invoices/InvoicePrint.vue'), meta: { auth: true } },

  // Public digital business card — no auth required
  { path: '/shop/:slug', name: 'ShopCard', component: () => import('../views/shop/ShopCard.vue') },

  // Staff invite acceptance — accessible logged-in or out
  { path: '/accept-invite/:token', name: 'AcceptInvite', component: () => import('../views/auth/AcceptInvite.vue') },

  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to) => to.hash ? { el: to.hash, behavior: 'smooth', top: 72 } : { top: 0 },
})

// Guards
router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.auth  && !auth.isLoggedIn) return { name: 'Login' }
  if (to.meta.guest && auth.isLoggedIn)  return { name: 'Dashboard' }
})

export default router
