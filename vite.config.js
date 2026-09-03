import { defineConfig } from 'vite';
import fullReload from 'vite-plugin-full-reload';

export default defineConfig({
  root: './',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '^.*/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '^/uploads': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  plugins: [
    fullReload(['../src/**/*']),
    {
      name: 'html-rewrite',
      configureServer(server) {
        server.middlewares.use((req, res, next) => {
          const url = req.url.split('?')[0];
          const routes = {
            '/': '/index.html',
            '/home': '/products.html',
            '/products': '/products.html',
            '/login': '/login.html',
            '/register': '/register.html',
            '/forgot-password': '/forgot_password.html',
            '/reset-password': '/reset_password.html',
            '/cart': '/cart.html',
            '/checkout': '/checkout.html',
            '/orders': '/order-history.html',
            '/order-history': '/order-history.html',
            '/my-pets': '/my-pets.html',
            '/profile': '/profile.html',
            '/contact': '/contact.html',
            '/pos': '/pos.html',

            // Admin Clean Routes
            '/admin': '/admin_orders.html',
            '/admin/dashboard': '/admin_dashboard.html',
            '/admin/products': '/admin_product_management.html',
            '/admin/products/edit': '/admin_product_edit.html',
            '/admin/stock': '/admin_stock.html',
            '/admin/categories': '/admin_categories.html',
            '/admin/promotions': '/admin_promotions.html',
            '/admin/orders': '/admin_orders.html',
            '/admin/orders/details': '/admin_order_details.html',
            '/admin/refunds': '/admin_refunds.html',
            '/admin/customers': '/admin_customers.html',
            '/admin/customers/details': '/admin_customer_details.html',
            '/admin/delivery': '/admin_delivery.html',
            '/admin/rewards': '/admin_reward_management.html',
            '/admin/staff': '/admin_staff.html',
            '/admin/schedule': '/admin_schedule.html',
            '/admin/attendance': '/admin_attendance.html',
            '/admin/payroll': '/admin_payroll.html',
            '/admin/payroll/settings': '/admin_pay_settings.html',
            '/admin/transactions': '/admin_transactions.html',
            '/admin/payment-settings': '/admin_payment_settings.html',

            // Staff Clean Routes
            '/staff': '/staff_profile.html',
            '/staff/profile': '/staff_profile.html',
            '/staff/stock': '/staff_stock.html',
            '/staff/orders': '/staff_orders.html',
            '/staff/orders/details': '/staff_order_details.html',
            '/staff/refunds': '/staff_refunds.html',
            '/staff/customers': '/staff_customers.html',
            '/staff/customers/details': '/staff_customer_details.html',
            '/staff/promotions': '/staff_promotions.html',
            '/staff/schedule': '/staff_schedule.html'
          };
          
          if (routes[url]) {
            req.url = routes[url];
          } else if (url.endsWith('.html') && (url.startsWith('/admin/') || url.startsWith('/staff/'))) {
            req.url = '/' + url.split('/').pop();
          }
          next();
        });
      }
    }
  ],
});
