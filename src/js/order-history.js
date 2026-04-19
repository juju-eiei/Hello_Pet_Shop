document.addEventListener('DOMContentLoaded', () => {
    const ordersContainer = document.getElementById('ordersContainer');
    const emptyOrders = document.getElementById('emptyOrders');
    const orderDetailModal = document.getElementById('orderDetailModal');
    const closeModalBtn = document.getElementById('closeModalBtn');

    // Modal fields
    const modalOrderId = document.getElementById('modalOrderId');
    const modalDate = document.getElementById('modalDate');
    const modalStatus = document.getElementById('modalStatus');
    const modalPayment = document.getElementById('modalPayment');
    const modalShipping = document.getElementById('modalShipping');
    const modalItems = document.getElementById('modalItems');
    const modalSubtotal = document.getElementById('modalSubtotal');
    const modalShippingFee = document.getElementById('modalShippingFee');
    const modalTotal = document.getElementById('modalTotal');

    let orders = [];

    function loadOrders() {
        const storedOrders = localStorage.getItem('myOrders');
        if (storedOrders) {
            try {
                orders = JSON.parse(storedOrders);
            } catch (e) {
                console.error("Error parsing orders", e);
                orders = [];
            }
        } else {
            orders = [];
        }
        renderOrders();
    }

    function renderOrders() {
        ordersContainer.innerHTML = '';
        
        if (orders.length === 0) {
            ordersContainer.classList.add('hidden');
            emptyOrders.classList.remove('hidden');
            return;
        }

        ordersContainer.classList.remove('hidden');
        emptyOrders.classList.add('hidden');

        orders.forEach(order => {
            const date = new Date(order.date).toLocaleDateString('th-TH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const card = document.createElement('div');
            card.className = "bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow";
            card.innerHTML = `
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-5 gap-3">
                    <div>
                        <div class="text-sm font-bold text-[#8bb35c] uppercase tracking-wide">Order #${order.id}</div>
                        <div class="text-xs text-gray-400 mt-0.5">${date}</div>
                    </div>
                    <div class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${order.status === 'Preparing' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'}">
                        ${order.status}
                    </div>
                </div>
                
                <div class="border-t border-gray-50 pt-4 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs text-gray-400">Total:</span>
                        <span class="text-lg font-bold text-gray-800">$${order.total.toFixed(2)}</span>
                    </div>
                    <button class="view-details-btn px-4 py-2 bg-gray-50 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-100 transition-colors" data-id="${order.id}">
                        View Details
                    </button>
                </div>
            `;
            ordersContainer.appendChild(card);
        });

        // Add event listeners to buttons
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                showOrderDetails(e.target.dataset.id);
            });
        });
    }

    function showOrderDetails(orderId) {
        const order = orders.find(o => o.id == orderId);
        if (!order) return;

        modalOrderId.textContent = `#${order.id}`;
        modalDate.textContent = new Date(order.date).toLocaleDateString('th-TH', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        
        modalStatus.textContent = order.status;
        modalStatus.className = `text-sm font-bold uppercase ${order.status === 'Preparing' ? 'text-amber-600' : 'text-green-600'}`;
        
        modalPayment.textContent = order.paymentMethod === 'cod' ? 'Cash on Delivery' : 'Bank Transfer';
        modalShipping.textContent = order.deliveryMethod === 'standard' ? 'Standard Delivery' : 'Express Delivery';
        
        // Render items
        modalItems.innerHTML = order.items.map(item => `
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center p-2 shrink-0">
                    <img src="${item.image || 'https://placehold.co/100x100'}" alt="" class="w-full h-full object-contain">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-gray-800 truncate">${item.name}</div>
                    <div class="text-xs text-gray-500">Qty: ${item.quantity} × $${parseFloat(item.price).toFixed(2)}</div>
                </div>
                <div class="text-sm font-bold text-gray-800">
                    $${(parseFloat(item.price) * item.quantity).toFixed(2)}
                </div>
            </div>
        `).join('');

        modalSubtotal.textContent = `$${order.subtotal.toFixed(2)}`;
        modalShippingFee.textContent = `$${order.shipping.toFixed(2)}`;
        modalTotal.textContent = `$${order.total.toFixed(2)}`;

        // Open modal
        orderDetailModal.classList.remove('opacity-0', 'pointer-events-none');
        orderDetailModal.querySelector('div').classList.remove('scale-95');
        orderDetailModal.querySelector('div').classList.add('scale-100');
    }

    function closeModal() {
        orderDetailModal.classList.add('opacity-0', 'pointer-events-none');
        orderDetailModal.querySelector('div').classList.remove('scale-100');
        orderDetailModal.querySelector('div').classList.add('scale-95');
    }

    closeModalBtn.addEventListener('click', closeModal);
    
    // Close on backdrop click
    orderDetailModal.addEventListener('click', (e) => {
        if (e.target === orderDetailModal) closeModal();
    });

    loadOrders();
});
