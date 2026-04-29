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
      { path: 'clients',   name: 'Clients',       component: () => import('../views/clients/ClientList.vue') },
      { path: 'clients/new',       name: 'ClientNew',    component: () => import('../views/clients/ClientForm.vue') },
      { path: 'clients/:id/edit', name: 'ClientEdit',   component: () => import('../views/clients/ClientForm.vue') },
      { path: 'clients/:id',      name: 'ClientView',   component: () => import('../views/clients/ClientDetail.vue') },
      { path: 'invoices',         name: 'Invoices',     component: () => import('../views/invoices/InvoiceList.vue') },
      { path: 'invoices/new',     name: 'InvoiceNew',   component: () => import('../views/invoices/InvoiceForm.vue') },
      { path: 'invoices/:id/edit',name: 'InvoiceEdit',  component: () => import('../views/invoices/InvoiceForm.vue') },
      { path: 'invoices/:id',     name: 'InvoiceView',  component: () => import('../views/invoices/InvoiceDetail.vue') },
      { path: 'quotes',           name: 'Quotes',       component: () => import('../views/quotes/QuoteList.vue') },
      { path: 'quotes/new',       name: 'QuoteNew',     component: () => import('../views/quotes/QuoteForm.vue') },
      { path: 'quotes/:id/edit',  name: 'QuoteEdit',    component: () => import('../views/quotes/QuoteForm.vue') },
      { path: 'quotes/:id',       name: 'QuoteView',    component: () => import('../views/quotes/QuoteDetail.vue') },
      { path: 'expenses',         name: 'Expenses',     component: () => import('../views/expenses/ExpenseList.vue') },
      { path: 'products',         name: 'Products',     component: () => import('../views/products/ProductList.vue') },
      { path: 'credit-notes',     name: 'CreditNotes',  component: () => import('../views/credit-notes/CreditNoteList.vue') },
      { path: 'gst-returns',      name: 'GstReturns',   component: () => import('../views/gst/GstReturns.vue') },
      { path: 'reports',          name: 'Reports',      component: () => import('../views/reports/Reports.vue') },
      { path: 'settings',         name: 'Settings',     component: () => import('../views/settings/Settings.vue') },
      { path: 'help',             name: 'Help',         component: () => import('../views/help/Help.vue') },
    ],
  },

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
