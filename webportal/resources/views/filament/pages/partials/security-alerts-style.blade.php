

<style>

/* =============================================

   IMNEHNET — Security Alerts Modal

   Responsive Light / Dark Theme

   ============================================= */



/* Modal container */

section[data-im-alert-modal] {

    display: flex !important;

    flex-direction: column !important;

    width: min(1100px, calc(100vw - 32px)) !important;

    max-width: 1100px !important;

    height: min(760px, calc(100dvh - 48px)) !important;

    max-height: calc(100dvh - 48px) !important;

    min-height: 0 !important;

    margin: auto !important;

    overflow: hidden !important;

    border: 1px solid var(--im-alert-border) !important;

    border-radius: 16px !important;

    background: var(--im-alert-bg) !important;

    color: var(--im-alert-text) !important;

    box-shadow: 0 24px 80px rgba(0, 0, 0, .35) !important;

    font-size: 13px !important;

}



/* Light mode — default */

section[data-im-alert-modal] {

    --im-alert-bg: #ffffff;

    --im-alert-text: #1e293b;

    --im-alert-muted: #64748b;

    --im-alert-border: #e2e8f0;

    --im-alert-surface: #f8fafc;

    --im-alert-hover: #f1f5f9;

    --im-alert-input: #ffffff;

}



/* Dark mode — follows Filament */

.dark section[data-im-alert-modal] {

    --im-alert-bg: #111827;

    --im-alert-text: #e5e7eb;

    --im-alert-muted: #94a3b8;

    --im-alert-border: #293449;

    --im-alert-surface: #1b2638;

    --im-alert-hover: #1e2b40;

    --im-alert-input: #1c2638;

}



/* Header */

section[data-im-alert-modal] > header {

    flex-shrink: 0 !important;

    padding: 20px 24px !important;

    border-bottom: 1px solid var(--im-alert-border) !important;

}



section[data-im-alert-modal] h2 {

    color: var(--im-alert-text) !important;

    font-size: 18px !important;

    font-weight: 700 !important;

    line-height: 1.4 !important;

}



section[data-im-alert-modal] header p {

    color: var(--im-alert-muted) !important;

    margin-top: 4px !important;

}



/* Search and severity filter */

section[data-im-alert-modal] > div.flex.flex-wrap {

    display: flex !important;

    flex-direction: row !important;

    flex-wrap: wrap !important;

    align-items: center !important;

    gap: 12px !important;

    padding: 16px 24px !important;

    flex-shrink: 0 !important;

}



section[data-im-alert-modal] input,

section[data-im-alert-modal] select {

    box-sizing: border-box !important;

    height: 40px !important;

    min-height: 40px !important;

    padding: 0 12px !important;

    border: 1px solid var(--im-alert-border) !important;

    border-radius: 8px !important;

    background: var(--im-alert-input) !important;

    color: var(--im-alert-text) !important;

    font-size: 13px !important;

    outline: none !important;

}



section[data-im-alert-modal] input {

    flex: 1 1 280px !important;

    min-width: 180px !important;

    width: auto !important;

}



section[data-im-alert-modal] select {

    flex: 0 0 180px !important;

    width: 180px !important;

}



section[data-im-alert-modal] input:focus,

section[data-im-alert-modal] select:focus {

    border-color: #14b8a6 !important;

    box-shadow: 0 0 0 2px rgba(20, 184, 166, .15) !important;

}



section[data-im-alert-modal] input::placeholder {

    color: var(--im-alert-muted) !important;

}



/* Scrollable table area */

section[data-im-alert-modal] .im-alerts-scroll {

    display: block !important;

    flex: 1 1 auto !important;

    min-height: 0 !important;

    height: auto !important;

    overflow-x: auto !important;

    overflow-y: auto !important;

    overscroll-behavior: contain;

    scrollbar-width: thin;

    scrollbar-color: #64748b transparent;

}



/* Table layout */

section[data-im-alert-modal] table {

    width: 100% !important;

    min-width: 760px !important;

    border-collapse: collapse !important;

    font-size: 12px !important;

}



section[data-im-alert-modal] thead {

    position: sticky;

    top: 0;

    z-index: 2;

}



section[data-im-alert-modal] th {

    padding: 14px 12px !important;

    background: var(--im-alert-surface) !important;

    color: var(--im-alert-muted) !important;

    text-align: left !important;

    text-transform: uppercase !important;

    font-size: 11px !important;

    font-weight: 600 !important;

    letter-spacing: .04em !important;

    border-bottom: 1px solid var(--im-alert-border) !important;

}



section[data-im-alert-modal] td {

    padding: 13px 12px !important;

    color: var(--im-alert-text) !important;

    line-height: 1.6 !important;

    vertical-align: middle !important;

    border-bottom: 1px solid var(--im-alert-border) !important;

}



section[data-im-alert-modal] tbody tr:hover {

    background: var(--im-alert-hover) !important;

}



/* Footer pagination */

section[data-im-alert-modal] > footer {

    flex-shrink: 0 !important;

    padding: 16px 24px !important;

    border-top: 1px solid var(--im-alert-border) !important;

    color: var(--im-alert-muted) !important;

    background: var(--im-alert-bg) !important;

}



section[data-im-alert-modal] button {

    font-size: 12px !important;

    border-radius: 7px !important;

}



section[data-im-alert-modal] button:hover:not(:disabled) {

    color: #14b8a6 !important;

}



/* Mobile responsiveness */

@media (max-width: 640px) {

    section[data-im-alert-modal] {

        width: calc(100vw - 16px) !important;

        height: calc(100dvh - 24px) !important;

        max-height: calc(100dvh - 24px) !important;

        border-radius: 12px !important;

    }



    section[data-im-alert-modal] > header,

    section[data-im-alert-modal] > footer {

        padding: 14px !important;

    }



    section[data-im-alert-modal] > div.flex.flex-wrap {

        flex-direction: column !important;

        align-items: stretch !important;

        padding: 12px 14px !important;

    }



    section[data-im-alert-modal] input,

    section[data-im-alert-modal] select {

        flex: none !important;

        width: 100% !important;

        min-width: 0 !important;

    }



    section[data-im-alert-modal] h2 {

        font-size: 16px !important;

    }

}

</style>
