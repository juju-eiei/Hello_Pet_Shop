const fs = require('fs');

let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_customers.html', 'utf8');

const newCSS = `
    <style>
        body { font-family: 'Inter', 'Noto Sans Thai', sans-serif; background-color: #f8fafc; }
        
        .search-container {
            background-color: #ffffff;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .search-container input {
            background-color: transparent;
            color: #1e293b;
            border: none;
            outline: none;
            width: 100%;
            margin-left: 12px;
            font-size: 15px;
        }

        /* Customer Cards */
        .customers-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .customer-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid #f1f5f9;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .customer-card:active {
            transform: scale(0.98);
            background-color: #f8fafc;
        }
        
        .customer-card-top {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;
        }
        
        .customer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #edf2f7;
            color: #2f5d3a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .customer-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 2px;
            min-width: 0; /* allows text truncation */
        }
        .customer-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .customer-id {
            font-size: 12px;
            color: #64748b;
        }
        .customer-email {
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .customer-action {
            flex-shrink: 0;
        }
        .btn-view-profile {
            background-color: #2f5d3a;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            pointer-events: none; /* Clicking the card handles nav */
        }

        .customer-card-bottom {
            display: flex;
            flex-direction: row;
            gap: 16px;
            border-top: 1px dashed #e2e8f0;
            padding-top: 12px;
        }
        .customer-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            background: #f8fafc;
            padding: 6px 12px;
            border-radius: 20px;
        }

        /* Responsive overrides */
        @media (max-width: 768px) {
            .table-header { display: none !important; }
            .stock-table { background: transparent !important; box-shadow: none !important; padding: 0 !important; }
            .page-header { margin-bottom: 20px; }
        }
        
        /* Bottom Nav Highlight Fix */
        .bottom-nav { border-top: 1px solid #e2e8f0; }
    </style>
`;

c = c.replace(/<style>[\s\S]*?<\/style>/, newCSS);

const newRender = `
        function renderCustomers(data) {
            const container = document.getElementById('customersContainer');
            // Remove the hardcoded table-header from DOM on JS render to be safe
            const oldHeader = document.querySelector('.table-header');
            if (oldHeader && window.innerWidth <= 768) oldHeader.style.display = 'none';

            if (data.length === 0) {
                container.innerHTML = \`<div style="padding: 50px; text-align: center; color: #64748b; font-weight: 500;">No customers found.</div>\`;
                return;
            }

            container.innerHTML = '<div class="customers-container">' + data.map(c => {
                const initial = c.name ? c.name.charAt(0).toUpperCase() : '?';
                const idStr = 'CUS-' + String(c.customer_id).padStart(4, '0');
                const petsLabel = c.pet_count > 0 ? \`\${c.pet_count} Pets <span style="font-weight:400; color:#64748b; font-size:11px;">(\${c.pet_types || ''})</span>\` : '0 Pets';
                
                return \`
                    <div class="customer-card" onclick="viewCustomer(\${c.customer_id})">
                        <div class="customer-card-top">
                            <div class="customer-avatar">\${initial}</div>
                            <div class="customer-info">
                                <div class="customer-name">\${c.name}</div>
                                <div class="customer-id">\${idStr}</div>
                                <div class="customer-email">
                                    <i class="far fa-envelope" style="margin-right:2px; font-size:10px;"></i> \${c.email || '-'}
                                    &nbsp;|&nbsp; 
                                    <i class="fas fa-phone-alt" style="margin-right:2px; font-size:10px;"></i> \${c.phone || '-'}
                                </div>
                            </div>
                            <div class="customer-action">
                                <button class="btn-view-profile">View</button>
                            </div>
                        </div>
                        <div class="customer-card-bottom">
                            <div class="customer-stat">
                                <i class="fas fa-star" style="color: #f59e0b;"></i> \${c.points} Pts
                            </div>
                            <div class="customer-stat">
                                <i class="fas fa-paw" style="color: #2f5d3a;"></i> \${petsLabel}
                            </div>
                        </div>
                    </div>
                \`;
            }).join('') + '</div>';
        }
`;

c = c.replace(/function renderCustomers\(data\) {[\s\S]*?}\n\n\s*document\.getElementById\('searchInput'\)/, newRender.trim() + '\\n\\n        document.getElementById(\\'searchInput\\')');

fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_customers.html', c);
console.log('Customer UI updated successfully');
