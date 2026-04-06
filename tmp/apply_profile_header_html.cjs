const fs = require('fs');

let c = fs.readFileSync('c:/laragon/www/Hello_Pet_Shop/admin_customer_details.html', 'utf8');

const t2Start = c.indexOf('                <!-- Profile Header -->');
const t2EndStr = '                </div>\\r\\n\\r\\n                <!-- Pets Section -->';
let t2End = c.indexOf('                <!-- Pets Section -->');

if(t2End !== -1) {
    const newHTML = `                <!-- Profile Header -->
                <div class="profile-header-card">
                    <div class="profile-card-top">
                        <div class="profile-avatar" id="cInitials">
                            <i class="far fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h2 id="cName">Customer Name</h2>
                            <div class="profile-contact-line"><i class="far fa-envelope"></i> <span id="cEmail"></span></div>
                            <div class="profile-contact-line"><i class="fas fa-phone-alt"></i> <span id="cPhone"></span></div>
                        </div>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-icon points"><i class="fas fa-star"></i></div>
                            <div class="stat-text"><strong id="cPoints">0</strong> <span>Points</span></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon orders"><i class="fas fa-shopping-bag"></i></div>
                            <div class="stat-text"><strong id="cOrders">0</strong> <span>Orders</span></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon joined"><i class="fas fa-calendar-alt"></i></div>
                            <div class="stat-text"><strong id="cJoined" style="font-size:14px;">-</strong> <span>Joined</span></div>
                        </div>
                    </div>
                </div>

`;
    c = c.substring(0, t2Start) + newHTML + c.substring(t2End);
    fs.writeFileSync('c:/laragon/www/Hello_Pet_Shop/admin_customer_details.html', c);
    console.log('Successfully replaced profile header HTML.');
} else {
    console.log('Failed to find exact substring index bounds.');
}
