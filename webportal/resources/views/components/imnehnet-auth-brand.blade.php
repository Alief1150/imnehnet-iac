{{-- IMNEHNET_AUTH_BRANDING_V1 --}}
<div class="imn-auth-brand"
     aria-label="IMNEHNET - Cybersecurity Lab as a Service">
    <img
        class="imn-auth-brand__image"
        src="{{ asset('imnehnet-login/branding/logo_imnehnet_banner_2.webp') }}"
        alt="IMNEHNET - Cybersecurity Lab as a Service"
        width="360"
        loading="eager"
        decoding="async"
    >
</div>

<style>
/* IMNEHNET_AUTH_BRANDING_V1 */

.imn-auth-brand {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    box-sizing: border-box;

    width: min(100%, 360px);
    max-width: 100%;

    padding: 8px 12px;
    margin: 0 0 18px;

    background: #101e39;
    border-radius: 12px;

    overflow: hidden;
}

.imn-auth-brand__image {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    max-height: 110px;

    object-fit: contain;
    object-position: left center;
}

/* Konsistensi pada card login */
.im-v5-card .imn-auth-brand {
    flex-shrink: 0;
}

/* Logo memiliki teks putih:
   background branding tetap navy pada Light Mode */
.im-v5[data-theme="light"] .imn-auth-brand,
.im-v5[data-theme="dark"] .imn-auth-brand {
    background: #101e39;
}

/* Mobile */
@media (max-width: 640px) {
    .imn-auth-brand {
        width: min(100%, 300px);
        padding: 6px 10px;
        margin-bottom: 14px;
    }

    .imn-auth-brand__image {
        max-height: 90px;
    }
}

/* IMNEHNET_BRANDING_THEME_MOBILE_V2_START */

/* Logo center, desktop and mobile */
.imn-auth-brand {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    width: min(100%, 360px) !important;
    max-width: 100% !important;
    margin: 0 auto 18px auto !important;

    box-sizing: border-box !important;
    overflow: hidden !important;
}

.imn-auth-brand__image {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;

    object-fit: contain !important;
    object-position: center !important;
}

/* Dark Mode: transparent logo wrapper */
.im-v5[data-theme="dark"] .imn-auth-brand {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
}

/* Light Mode: retain navy background */
.im-v5[data-theme="light"] .imn-auth-brand {
    background: #101e39 !important;
    border: 0 !important;
    box-shadow: none !important;
    border-radius: 12px !important;
    padding: 8px 12px !important;
}

/* Responsive logo */
@media (max-width: 640px) {
    .imn-auth-brand {
        width: min(100%, 300px) !important;
        margin: 0 auto 16px auto !important;
    }

    .imn-auth-brand__image {
        max-height: 100px !important;
    }
}

/* Mobile background: fill uncovered area without overlay */
@media (max-width: 768px) {
    html:has([data-im-login]),
    body:has([data-im-login]) {
        min-height: 100% !important;
        background-color: #0b1020 !important;
    }

    body:has([data-im-login]) {
        min-height: 100dvh !important;
    }

    .im-v5[data-im-login] {
        min-height: 100dvh !important;
        box-sizing: border-box !important;
    }
}

/* IMNEHNET_BRANDING_THEME_MOBILE_V2_END */

</style>
