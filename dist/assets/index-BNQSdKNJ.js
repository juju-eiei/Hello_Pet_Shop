(function(){let e=document.createElement(`link`).relList;if(e&&e.supports&&e.supports(`modulepreload`))return;for(let e of document.querySelectorAll(`link[rel="modulepreload"]`))n(e);new MutationObserver(e=>{for(let t of e)if(t.type===`childList`)for(let e of t.addedNodes)e.tagName===`LINK`&&e.rel===`modulepreload`&&n(e)}).observe(document,{childList:!0,subtree:!0});function t(e){let t={};return e.integrity&&(t.integrity=e.integrity),e.referrerPolicy&&(t.referrerPolicy=e.referrerPolicy),e.crossOrigin===`use-credentials`?t.credentials=`include`:e.crossOrigin===`anonymous`?t.credentials=`omit`:t.credentials=`same-origin`,t}function n(e){if(e.ep)return;e.ep=!0;let n=t(e);fetch(e.href,n)}})(),window.__i18nInitialized||(window.__i18nInitialized=!0,e());function e(){function e(){let e=localStorage.getItem(`lang`);return e||(e=`th`,localStorage.setItem(`lang`,e)),e}function t(){let e=window.location.hostname;document.cookie=`googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`,document.cookie=`googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=`,document.cookie=`googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=${e}`,document.cookie=`googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=.${e}`,document.cookie=`googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=localhost`,document.cookie=`googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=.localhost`,document.cookie=`googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=127.0.0.1`}function n(e){localStorage.setItem(`lang`,e),t(),e===`en`&&(document.cookie=`googtrans=/th/en; path=/`,window.location.hostname!==`localhost`&&window.location.hostname!==`127.0.0.1`&&(document.cookie=`googtrans=/th/en; path=/; domain=`+window.location.hostname,document.cookie=`googtrans=/th/en; path=/; domain=.`+window.location.hostname))}function r(){let t=e()===`th`?`en`:`th`,r=document.getElementById(`translationLoadingOverlay`);r||(r=document.createElement(`div`),r.id=`translationLoadingOverlay`,r.className=`translation-loading-overlay`,r.innerHTML=`
                <div class="translation-spinner"></div>
                <div class="translation-text">กำลังเปลี่ยนภาษา... / Changing language...</div>
            `,document.body.appendChild(r)),r.classList.add(`show`),setTimeout(()=>{n(t),location.reload()},300)}function i(){setInterval(()=>{document.querySelectorAll(`.goog-te-banner-frame, iframe[class*="goog-te-banner-frame"], iframe[id*="goog-te-banner-frame"]`).forEach(e=>{e.style.setProperty(`display`,`none`,`important`),e.style.setProperty(`visibility`,`hidden`,`important`),e.style.setProperty(`height`,`0px`,`important`),e.style.setProperty(`opacity`,`0`,`important`)}),document.body&&document.body.style.top!==`0px`&&(document.body.style.setProperty(`top`,`0px`,`important`),document.body.style.setProperty(`position`,`static`,`important`)),document.documentElement&&document.documentElement.style.marginTop!==`0px`&&document.documentElement.style.setProperty(`margin-top`,`0px`,`important`),document.querySelectorAll(`#goog-gt-tt, .goog-tooltip, .goog-te-balloon-frame, .goog-te-balloon-wrapper`).forEach(e=>{e.style.setProperty(`display`,`none`,`important`),e.style.setProperty(`visibility`,`hidden`,`important`)})},100)}function a(){if(document.getElementById(`translationLoadingOverlay`))return;if(!document.body){let e=new MutationObserver(()=>{document.body&&(a(),e.disconnect())});e.observe(document.documentElement,{childList:!0});return}let t=document.createElement(`style`);t.innerHTML=`
            .translation-loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(15, 23, 42, 0.85);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 999999;
                color: white;
                font-family: 'Outfit', 'Kanit', sans-serif;
                transition: opacity 0.4s ease, visibility 0.4s ease;
                opacity: 0;
                visibility: hidden;
                pointer-events: none; /* Make it transparent to clicks when hidden */
            }
            .translation-loading-overlay.show {
                opacity: 1;
                visibility: visible;
                pointer-events: auto; /* Catch clicks only when shown */
            }
            .translation-spinner {
                width: 48px;
                height: 48px;
                border: 3.5px solid rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                border-top-color: #94ad5e;
                animation: translate-spin 0.8s linear infinite;
            }
            .translation-text {
                margin-top: 20px;
                font-size: 15px;
                font-weight: 500;
                letter-spacing: 0.5px;
                color: rgba(255, 255, 255, 0.9);
                text-align: center;
            }
            @keyframes translate-spin {
                to { transform: rotate(360deg); }
            }
        `,document.head.appendChild(t);let n=document.createElement(`div`);if(n.id=`translationLoadingOverlay`,n.className=`translation-loading-overlay`,n.innerHTML=`
            <div class="translation-spinner"></div>
            <div class="translation-text">กำลังเปลี่ยนภาษา... / Changing language...</div>
        `,document.body.appendChild(n),e()===`en`&&!document.documentElement.classList.contains(`translated-ltr`)){n.classList.add(`show`);let e=()=>{n.classList.remove(`show`),setTimeout(()=>{n.remove()},450)},t=setTimeout(e,1500),r=new MutationObserver(()=>{document.documentElement.classList.contains(`translated-ltr`)&&(e(),clearTimeout(t),r.disconnect())});r.observe(document.documentElement,{attributes:!0,attributeFilter:[`class`]})}else n.remove()}function o(){let e=document.createElement(`style`);if(e.innerHTML=`
            .goog-te-banner-frame.skiptranslate,
            .goog-te-banner-frame,
            .goog-te-balloon-frame,
            #goog-gt-tt,
            .goog-tooltip,
            .goog-tooltip:hover {
                display: none !important;
                visibility: hidden !important;
            }
            body {
                top: 0px !important;
            }
            html {
                margin-top: 0px !important;
            }
            .goog-text-highlight {
                background-color: transparent !important;
                border: none !important;
                box-shadow: none !important;
            }
            #google_translate_element {
                display: none !important;
            }
        `,document.head.appendChild(e),!document.getElementById(`google_translate_element`)){let e=document.createElement(`div`);e.id=`google_translate_element`,document.body.appendChild(e)}window.googleTranslateElementInit=function(){new google.translate.TranslateElement({pageLanguage:`th`,includedLanguages:`th,en`,layout:google.translate.TranslateElement.InlineLayout.SIMPLE},`google_translate_element`)};let t=document.createElement(`script`);t.type=`text/javascript`,t.src=`https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit`,document.head.appendChild(t),i()}function s(){if(!document.getElementById(`langToggleBtn`)){let t=document.getElementById(`navProfileMenuBtn`);if(t){let n=t.parentElement,i=document.createElement(`button`);i.id=`langToggleBtn`,i.className=`flex items-center space-x-1 hover:text-gray-200 transition-colors focus:outline-none text-sm font-semibold mr-4`,i.style.cursor=`pointer`,i.innerHTML=`
                    <i class="fas fa-globe"></i>
                    <span class="lang-toggle-text">${e().toUpperCase()}</span>
                `,i.addEventListener(`click`,r),n.insertBefore(i,t)}else{let t=document.querySelector(`nav a[href*="register"], nav a[href="/"], nav a[href="/home"]`);if(t){let n=t.parentElement,i=document.createElement(`button`);i.id=`langToggleBtn`,i.className=`flex items-center space-x-1 text-gray-600 hover:text-primary-600 transition-colors focus:outline-none text-sm sm:text-base font-medium mr-4`,i.style.cursor=`pointer`,i.innerHTML=`
                        <i class="fas fa-globe"></i>
                        <span class="lang-toggle-text">${e().toUpperCase()}</span>
                    `,i.addEventListener(`click`,r),n.insertBefore(i,n.firstChild)}}}let t=document.querySelector(`.sidebar-header`);if(t&&!document.getElementById(`langToggleBtnAdmin`)){let n=document.createElement(`button`);n.id=`langToggleBtnAdmin`,n.style.marginLeft=`auto`,n.style.background=`transparent`,n.style.border=`none`,n.style.color=`#fff`,n.style.cursor=`pointer`,n.style.fontSize=`14px`,n.innerHTML=`
                <i class="fas fa-globe"></i>
                <span class="lang-toggle-text">${e().toUpperCase()}</span>
            `,n.addEventListener(`click`,r),t.appendChild(n)}}a(),document.addEventListener(`DOMContentLoaded`,()=>{o(),s()}),document.body&&(o(),s())}function t(){let e=document.getElementById(`navProfileImage`),t=document.getElementById(`navDefaultAvatar`);if(!e||!t)return;let n=localStorage.getItem(`userProfileData`);if(n)try{let r=JSON.parse(n);r.profileImage?(e.src=r.profileImage,e.classList.remove(`hidden`),t.classList.add(`hidden`)):(e.classList.add(`hidden`),t.classList.remove(`hidden`))}catch(e){console.error(`Error parsing profile data for nav`,e)}}document.addEventListener(`DOMContentLoaded`,()=>{console.log(`Hello Pet Shop - Premium UI Loaded`);let e=JSON.parse(localStorage.getItem(`user`));e&&console.log(`Logged in as: ${e.username} (${e.role_name})`);let r=document.getElementById(`navProfileMenuBtn`),i=document.getElementById(`navProfileDropdown`),a=document.getElementById(`logoutBtn`);r&&i&&(r.addEventListener(`click`,e=>{e.stopPropagation(),i.classList.toggle(`hidden`)}),document.addEventListener(`click`,e=>{!r.contains(e.target)&&!i.contains(e.target)&&i.classList.add(`hidden`)})),a&&a.addEventListener(`click`,()=>{localStorage.removeItem(`user`),localStorage.removeItem(`userProfileData`),window.location.href=`/login`}),t(),n()});function n(){let e=JSON.parse(localStorage.getItem(`cart`)||`[]`).reduce((e,t)=>e+t.quantity,0),t=document.getElementById(`cartCount`);t&&(e>0?(t.textContent=e,t.classList.remove(`hidden`)):t.classList.add(`hidden`))}window.location.href=`/login.html`;