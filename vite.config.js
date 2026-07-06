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
        target: 'http://localhost/Hello_Pet_Shop',
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
            '/': '/index.html', // Vite typically serves index.html for root, index.html does the redirecting! Wait.
            '/home': '/products.html',
            '/login': '/login.html',
            '/register': '/register.html',
            '/products': '/products.html',
            '/profile': '/profile.html',
            '/cart': '/cart.html',
            '/checkout': '/checkout.html',
            '/order-history': '/order-history.html',
            '/my-pets': '/my-pets.html',
            '/contact': '/contact.html',
            '/admin/stock': '/admin_stock.html',
            '/admin/products': '/admin_product_management.html',
            '/admin/products/edit': '/admin_product_edit.html',
            '/admin/promotions': '/admin_promotions.html',
            '/staff/profile': '/staff_profile.html',
            '/staff/orders': '/staff_orders.html',
            '/staff/customers': '/staff_customers.html',
            '/admin/orders': '/admin_orders.html',
            '/admin/customers': '/admin_customers.html'
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

