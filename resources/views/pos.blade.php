<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#007A5C">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>SMART POS</title>
    @vite(['resources/js/app.js', 'resources/js/pos/main.js'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --brand: #007A5C;
            --brand-dark: #00624a;
            --brand-soft: #ecfdf5;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --warning: #f59e0b;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --shadow-sm: 0 4px 10px -8px rgba(15, 23, 42, 0.25);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Instrument Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            overflow: hidden;
        }
        .pos-shell {
            height: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .pos-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1.5rem;
            background: #fff;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            flex-shrink: 0;
            z-index: 10;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--brand);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
        }
        .pos-header h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
        }
        .subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 500;
        }
        .header-center {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .header-center select {
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border);
            min-width: 200px;
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text);
            background-color: var(--bg);
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-online { background: #dcfce7; color: #15803d; }
        .badge-offline { background: #fee2e2; color: #b91c1c; }
        
        .btn {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            font-family: inherit;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn:hover:not(:disabled) {
            background: var(--bg);
            border-color: #cbd5e1;
        }
        .btn:active {
            transform: translateY(1px);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn.primary {
            background: var(--brand);
            color: white;
            border: 1px solid var(--brand);
            box-shadow: 0 4px 6px -1px rgba(0, 122, 92, 0.2);
        }
        .btn.primary:hover:not(:disabled) {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
        }
        .btn.secondary {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: var(--text);
        }
        .btn.secondary:hover:not(:disabled) {
            background: #e2e8f0;
        }
        .btn.ghost {
            background: transparent;
            border-color: transparent;
            color: var(--muted);
        }
        .btn.ghost:hover {
            background: var(--bg);
            color: var(--text);
        }

        .pos-body {
            flex: 1;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1.5rem;
            padding: 1rem;
            overflow: hidden;
            min-height: 0;
        }

        /* Left Panel - Catalog */
        .catalog-panel {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            height: 100%;
            overflow: hidden;
        }
        .catalog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .search-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .search-bar {
            position: relative;
            flex: 1;
        }
        .search-bar input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border);
            border-radius: 1rem;
            font-family: inherit;
            font-size: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }
        .scan-action {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.65rem 1rem;
            border-radius: 1rem;
            border: 1px solid var(--brand);
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 10px 18px -12px rgba(0, 122, 92, 0.6);
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .scan-action:hover {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
            transform: translateY(-1px);
        }
        .scan-action .scan-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            font-size: 1rem;
        }
        @media (max-width: 900px) {
            .search-row {
                flex-direction: column;
                align-items: stretch;
            }
            .scan-action {
                justify-content: center;
            }
        }
        
        .category-list {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        .category-pill {
            padding: 0.5rem 1rem;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--border);
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .category-pill.active {
            background: var(--brand);
            color: white;
            border-color: var(--brand);
        }
        
        .product-grid {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
            overflow-y: auto;
            padding-right: 0.5rem;
            align-content: start;
        }
        .product-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            min-height: 190px;
        }
        .product-card:hover {
            border-color: var(--brand);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .product-thumb {
            width: 100%;
            height: 90px;
            border-radius: 0.75rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--muted);
        }
        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-title {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        .product-price {
            font-weight: 700;
            color: var(--brand);
            font-size: 1.1rem;
        }
        .product-stock {
            font-size: 0.75rem;
            color: var(--muted);
        }
        
        /* Right Panel - Cart */
        .cart-panel {
            background: #fff;
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }
        .cart-scroll {
            flex: 1;
            display: grid;
            grid-template-rows: 25% 40% 35%;
            align-content: start;
            overflow: hidden;
            background: #fff;
            min-height: 0;
        }
        .pos-footer {
            text-align: center;
            font-size: 0.75rem;
            color: var(--muted);
            padding: 0.5rem 1rem 0.75rem;
            flex-shrink: 0;
        }
        .cart-scroll > .santri-selector,
        .cart-scroll > .cart-items,
        .cart-scroll > .cart-footer {
            min-height: 0;
        }
        .cart-header {
            padding: 0.9rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }
        .cart-header h2 {
            font-size: 0.95rem;
        }
        .santri-selector {
            padding: 1rem;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            overflow-y: auto;
        }
        .santri-search-row {
            display: flex;
            gap: 0.6rem;
            align-items: center;
        }
        .santri-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-top: 0.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 6px 14px -12px rgba(15, 23, 42, 0.35);
        }
        .santri-search-input {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.9rem;
            font-size: 0.9rem;
            background: #fff;
            box-shadow: 0 4px 10px -8px rgba(15, 23, 42, 0.25);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .santri-search-input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(0, 122, 92, 0.12);
        }
        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 0.9rem;
            border: 1px solid #e2e8f0;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px -8px rgba(15, 23, 42, 0.25);
            transition: all 0.2s ease;
        }
        .icon-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
            transform: translateY(-1px);
        }
        .santri-results {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 16px 28px -20px rgba(15, 23, 42, 0.35);
            padding: 0.6rem;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        .santri-result-item {
            width: 100%;
            min-height: 56px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 0.75rem;
            text-align: left;
            padding: 0.75rem 0.9rem;
            border-radius: 0.9rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .santri-result-item:hover {
            border-color: var(--brand);
            background: #ecfdf5;
            transform: translateY(-1px);
        }
        .santri-result-item:active {
            transform: scale(0.99);
        }
        .santri-result-meta {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
        }
        .santri-avatar {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: rgba(0, 122, 92, 0.12);
            color: var(--brand);
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        .santri-balance-chip {
            background: #e2f7ee;
            color: #0f7a57;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            border: 1px solid rgba(0, 122, 92, 0.2);
            white-space: nowrap;
        }
        .clear-santri-btn {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            border: 1px solid #fee2e2;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ef4444;
        }
        .clear-santri-btn:hover {
            background: #fee2e2;
        }
        
        .cart-items {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 0;
            overflow-y: auto;
            background: #fff;
        }
        .cart-summary-panel {
            margin-top: auto;
        }
        .cart-item {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 0.75rem;
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 0.75rem;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        .cart-item-info strong {
            display: block;
            font-size: 0.95rem;
        }
        .cart-item-info small {
            color: var(--muted);
        }
        .cart-item-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.35rem;
        }
        .cart-item-total {
            font-weight: 800;
            color: var(--text);
        }
        .qty-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #fff;
            border-radius: 0.5rem;
            padding: 2px;
            border: 1px solid var(--border);
        }
        .qty-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #fff;
            border-radius: 0.5rem;
            padding: 2px;
            border: 1px solid var(--border);
        }
        .qty-btn {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--text);
            border-radius: 4px;
        }
        .qty-btn:hover { background: #f1f5f9; }
        .qty-input {
            width: 32px;
            text-align: center;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .cart-remove {
            border: 1px solid #fee2e2;
            background: #fff;
            color: #ef4444;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            cursor: pointer;
        }
        .cart-remove:hover {
            background: #fee2e2;
        }
        .hint {
            color: #94a3b8;
            font-size: 0.85rem;
        }
        
        .cart-footer {
            padding: 0.9rem 1rem;
            background: #fff;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            flex-shrink: 0;
            overflow-y: auto;
        }
        .cart-footer .summary-row {
            font-size: 0.82rem;
        }
        .cart-footer .summary-total {
            font-size: 1.1rem;
        }
        .cart-footer .btn.secondary {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
        }
        .cart-footer .pay-btn {
            padding: 0.85rem;
            font-size: 1rem;
        }
        .receipt-actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        .transaction-history-link {
            border: 0;
            background: transparent;
            color: var(--brand);
            padding: 0.4rem 0.25rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 3px;
            cursor: pointer;
        }
        .transaction-history-link:hover,
        .transaction-history-link:focus-visible {
            color: #047857;
        }
        @media (max-width: 640px) {
            .receipt-actions {
                grid-template-columns: minmax(0, 1fr) auto;
            }
            .transaction-history-link {
                grid-column: 1 / -1;
                grid-row: 2;
                justify-self: start;
            }
        }
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.5rem;
        }
        .payment-method-btn {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--muted);
            padding: 0.55rem 0.75rem;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .payment-method-btn.active {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-soft);
            box-shadow: inset 0 0 0 1px rgba(0, 122, 92, 0.1);
        }
        .payment-section {
            margin-top: 0.5rem;
        }
        .pay-field {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .pay-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            font-weight: 700;
        }
        .pay-input {
            width: 100%;
            padding: 0.5rem 0.7rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .pay-input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(0, 122, 92, 0.12);
            background: #fff;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text);
            padding-top: 0.5rem;
            border-top: 1px dashed var(--border);
        }
        .total-amount {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.1rem;
        }
        .wallet-warning {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            color: #dc2626;
            font-weight: 600;
        }
        
        .pay-btn {
            width: 100%;
            padding: 1rem;
            background: var(--brand);
            color: white;
            border: none;
            border-radius: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 122, 92, 0.3);
            transition: all 0.2s;
        }
        .pay-btn:hover:not(:disabled) {
            background: var(--brand-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 122, 92, 0.4);
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            body {
                height: auto;
                overflow: auto;
            }
            .pos-shell {
                height: auto;
                min-height: 100vh;
            }
            .pos-body {
                grid-template-columns: 1fr;
                overflow: visible;
            }
            .cart-panel {
                height: auto;
                order: -1;
                max-height: none;
            }
            .cart-scroll {
                max-height: none;
            }
            .catalog-panel {
                height: auto;
                overflow: visible;
            }
            .product-grid {
                overflow: visible;
            }
        }
        @media (max-width: 820px) {
            .pos-body {
                padding: 1rem;
                gap: 1rem;
            }
            .cart-panel {
                max-height: none;
            }
            .cart-scroll {
                max-height: none;
            }
        }
        @media (max-width: 640px) {
            .pos-body {
                padding: 0.75rem;
            }
            .cart-panel {
                border-radius: 1.25rem;
            }
            .cart-header {
                padding: 1rem;
            }
            .santri-selector {
                padding: 0.85rem;
            }
        }
        @media (max-width: 640px) {
            .payment-methods {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        
        /* Modals and Overlays */
        .scan-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }
        .scan-modal[hidden] { display: none; }
        .scan-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 1.5rem;
            width: 90%;
            max-width: 400px;
        }
        .scan-video {
            width: 100%;
            border-radius: 1rem;
            background: #000;
            margin: 1rem 0;
            aspect-ratio: 1;
            object-fit: cover;
        }

        /* ── Success Modal ── */
        .success-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 1rem;
        }
        .success-modal[hidden] { display: none; }
        .success-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .success-card {
            position: relative;
            background: #fff;
            border-radius: 1.5rem;
            padding: 2rem 1.75rem 1.5rem;
            width: 100%;
            max-width: 420px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
            animation: successSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes successSlideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Animated Checkmark */
        .success-icon-ring {
            width: 72px;
            height: 72px;
            margin: 0 auto 1rem;
        }
        .success-checkmark {
            width: 72px;
            height: 72px;
        }
        .success-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #007A5C;
            animation: circleAnim 0.6s ease-in-out forwards;
        }
        @keyframes circleAnim {
            to { stroke-dashoffset: 0; }
        }
        .success-check {
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            stroke: #007A5C;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            animation: checkAnim 0.35s 0.35s ease-in-out forwards;
        }
        @keyframes checkAnim {
            to { stroke-dashoffset: 0; }
        }

        .success-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
            margin: 0 0 0.25rem;
        }
        .success-ref {
            font-size: 0.8rem;
            color: var(--muted);
            font-weight: 600;
            margin: 0 0 1rem;
            letter-spacing: 0.04em;
        }

        /* Detail Items Table */
        .success-details {
            text-align: left;
            margin-bottom: 1rem;
        }
        .success-details table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .success-details th {
            padding: 0.45rem 0.5rem;
            background: #f8fafc;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.7rem;
            border-bottom: 1px solid var(--border);
        }
        .success-details td {
            padding: 0.45rem 0.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text);
        }
        .success-details tr:last-child td {
            border-bottom: none;
        }

        /* Summary Section */
        .success-summary {
            background: #f8fafc;
            border-radius: 1rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            text-align: left;
        }
        .success-summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--muted);
            padding: 0.2rem 0;
        }
        .success-summary-row.total {
            border-top: 1px dashed var(--border);
            margin-top: 0.35rem;
            padding-top: 0.5rem;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text);
        }
        .success-summary-row.total span:last-child {
            color: var(--brand);
        }
        .success-summary-row.change span:last-child {
            color: #d97706;
            font-weight: 700;
        }

        /* Action Buttons */
        .success-actions {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .success-print-btn {
            flex: 1;
            padding: 0.75rem 1rem !important;
            font-weight: 700 !important;
            border-radius: 1rem !important;
            gap: 0.5rem;
        }
        .success-new-btn {
            flex: 1.2;
            padding: 0.75rem 1rem !important;
            font-weight: 700 !important;
            border-radius: 1rem !important;
        }
        .success-hint {
            font-size: 0.72rem;
            color: #94a3b8;
            margin: 0;
        }
        .success-hint kbd {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.7rem;
        }

        /* ── Confirm Modal ── */
        .confirm-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 90;
            padding: 1rem;
        }

        /* Read-only transaction history */
        .history-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 95;
            padding: 1rem;
        }
        .history-modal[hidden] { display: none; }
        .history-card {
            position: relative;
            width: min(760px, 100%);
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 1.5rem;
            background: #fff;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.3);
        }
        .history-body {
            overflow-y: auto;
            padding: 1rem 1.5rem 1.5rem;
        }
        .history-state {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.88rem;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }
        .history-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 0.75rem;
            align-items: center;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: #fff;
        }
        .history-row-main,
        .history-row-total {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }
        .history-row-main strong { font-size: 0.84rem; color: var(--text); }
        .history-row-main span,
        .history-row-total span { font-size: 0.72rem; color: var(--muted); }
        .history-row-total { text-align: right; }
        .history-row-total strong { font-size: 0.88rem; color: var(--brand); }
        .history-row-actions { display: flex; gap: 0.3rem; }
        .history-action-link {
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            background: #fff;
            color: #334155;
            padding: 0.42rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }
        .history-action-link:hover,
        .history-action-link:focus-visible { border-color: var(--brand); color: var(--brand); }
        .history-detail {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #a7f3d0;
            border-radius: var(--radius-lg);
            background: #f0fdf4;
        }
        .history-detail-heading,
        .history-detail-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .history-detail-heading > div { display: flex; flex-direction: column; }
        .history-detail-heading span { color: var(--muted); font-size: 0.75rem; }
        .history-detail-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.35rem 1rem;
            margin: 0.8rem 0;
            font-size: 0.78rem;
            color: var(--muted);
        }
        .history-detail-table-wrap { overflow-x: auto; }
        .history-detail-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        .history-detail-table th,
        .history-detail-table td { padding: 0.45rem; border-bottom: 1px solid #d1fae5; text-align: left; }
        .history-detail-table th:last-child,
        .history-detail-table td:last-child { text-align: right; }
        .history-detail-total { margin-top: 0.8rem; font-size: 1rem; }
        .history-detail-total strong { color: var(--brand); font-size: 1.1rem; }
        @media (max-width: 640px) {
            .history-body { padding: 0.85rem; }
            .history-row { grid-template-columns: minmax(0, 1fr) auto; }
            .history-row-actions { grid-column: 1 / -1; }
            .history-detail-meta { grid-template-columns: 1fr; }
        }
        .confirm-modal[hidden] { display: none; }
        .confirm-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .confirm-card {
            position: relative;
            background: #fff;
            border-radius: 1.5rem;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: successSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Header */
        .confirm-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .confirm-header-left h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
        }
        .confirm-header-left p {
            margin: 0.15rem 0 0;
            font-size: 0.78rem;
            color: var(--muted);
            font-weight: 600;
        }
        .confirm-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--muted);
            font-size: 1.1rem;
            transition: all 0.15s;
        }
        .confirm-close-btn:hover {
            background: #fee2e2;
            border-color: #fecaca;
            color: #ef4444;
        }

        /* Body: 2-column layout */
        .confirm-body {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 0;
        }
        @media (max-width: 640px) {
            .confirm-body {
                grid-template-columns: 1fr;
            }
        }

        /* Left — Order Details */
        .confirm-order {
            padding: 1.25rem 1.5rem;
        }
        .confirm-section-title {
            font-size: 0.9rem;
            font-weight: 800;
            margin: 0 0 0.75rem;
            color: var(--text);
        }
        .confirm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .confirm-table th {
            padding: 0.5rem 0.6rem;
            text-align: left;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.7rem;
            border-bottom: 2px solid var(--border);
            background: #fafbfc;
        }
        .confirm-table td {
            padding: 0.6rem 0.6rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text);
        }
        .confirm-table td:last-child {
            font-weight: 700;
            text-align: right;
        }
        .confirm-table th:last-child {
            text-align: right;
        }
        .confirm-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Order summary under table */
        .confirm-order-summary {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px dashed var(--border);
        }
        .confirm-summary-line {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--muted);
            padding: 0.15rem 0;
        }
        .confirm-summary-line.grand-total {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text);
            padding-top: 0.4rem;
            margin-top: 0.25rem;
            border-top: 2px solid var(--text);
        }
        .confirm-summary-line.grand-total span:last-child {
            color: var(--brand);
        }

        /* Right — Customer + Payment Info */
        .confirm-sidebar {
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        @media (max-width: 640px) {
            .confirm-sidebar {
                border-left: none;
                border-top: 1px solid var(--border);
            }
        }
        .confirm-info-grid {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .confirm-student-profile {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 0.9rem;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.85rem;
            border: 1px solid #bfdbfe;
            border-radius: var(--radius-lg);
            background: #eff6ff;
        }
        .confirm-student-photo {
            width: 88px;
            height: 104px;
            border-radius: var(--radius-md);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 1.35rem;
            font-weight: 800;
            border: 2px solid #fff;
            box-shadow: var(--shadow-sm);
        }
        .confirm-student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .confirm-student-check strong {
            display: block;
            color: var(--text);
            font-size: 0.86rem;
            margin-bottom: 0.2rem;
        }
        .confirm-student-check span {
            display: block;
            color: var(--muted);
            font-size: 0.75rem;
            line-height: 1.4;
        }
        .confirm-info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            gap: 0.5rem;
        }
        .confirm-info-row .info-label {
            color: var(--muted);
            font-weight: 600;
            white-space: nowrap;
        }
        .confirm-info-row .info-value {
            font-weight: 700;
            color: var(--text);
            text-align: right;
        }
        .confirm-info-row .info-value.brand {
            color: var(--brand);
        }

        /* Payment method badge */
        .confirm-payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .confirm-payment-badge.cash { background: #dcfce7; color: #15803d; }
        .confirm-payment-badge.wallet { background: #dbeafe; color: #1d4ed8; }
        .confirm-payment-badge.gateway { background: #fef3c7; color: #b45309; }

        /* Footer actions */
        .confirm-footer {
            display: flex;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background: #fff;
            border-radius: 0 0 1.5rem 1.5rem;
        }
        .confirm-cancel-btn {
            flex: 1;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 1rem;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            color: var(--text);
            transition: all 0.15s;
        }
        .confirm-cancel-btn:hover {
            background: #fee2e2;
            border-color: #fecaca;
            color: #dc2626;
        }
        .confirm-buy-btn {
            flex: 1.5;
            padding: 0.85rem 1rem;
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: 1rem;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 4px 12px -2px rgba(0, 122, 92, 0.35);
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .confirm-buy-btn:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -4px rgba(0, 122, 92, 0.45);
        }
        .confirm-buy-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .confirm-buy-btn .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            display: none;
        }
        .confirm-buy-btn.loading .spinner { display: inline-block; }
        .confirm-buy-btn.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="pos-shell" id="pos-app"
     data-location-id="{{ request()->query('location_id', '') }}"
     data-santri-id="{{ request()->query('santri_id', '') }}">
    
    <!-- Navbar -->
    <header class="pos-header">
        <div class="logo-area">
            <div class="logo-icon">S</div>
            <div>
                <h1>SMART POS</h1>
                <p class="subtitle">Kasir: <span id="user-name">Kasir</span></p>
            </div>
        </div>
        
        <div class="header-center hidden md:flex">
             <select id="input-location">
                <option value="">Pilih lokasi...</option>
            </select>
        </div>

        <div class="header-right">
            <div class="status">
                <span class="badge badge-online" id="status-badge">ONLINE</span>
            </div>
            <div class="flex items-center gap-2">
                 <button type="button" class="btn" id="sync-button">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span id="offline-count" class="ml-1">0</span>
                </button>
                <button type="button" class="btn ghost text-red-600" id="logout-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </div>
        </div>
    </header>

    <main class="pos-body">
        <!-- Catalog Section -->
        <div class="catalog-panel">
            <div class="catalog-tools space-y-4">
                <div class="search-row">
                    <div class="search-bar">
                        <span class="search-icon">🔍</span>
                        <input id="product-search" type="text" placeholder="Cari produk (Nama, SKU, Barcode)...">
                    </div>
                    <button type="button" class="scan-action" id="scan-product-btn">
                        <span class="scan-icon">📷</span>
                        <span>Scan Produk</span>
                    </button>
                </div>
            </div>

            <div class="category-list" id="category-list">
                <!-- Javascript will populate this -->
            </div>

            <div class="product-grid custom-scrollbar" id="product-grid">
                <!-- Javascript will populate this -->
            </div>
            
             <div id="product-empty" hidden class="text-center py-10 text-slate-400">
                Produk tidak ditemukan.
            </div>
        </div>

        <!-- Cart Section -->
        <div class="cart-panel">
            <div class="cart-header">
                <h2 class="font-bold text-lg">Keranjang</h2>
                <div class="text-sm font-medium bg-slate-100 text-slate-600 px-3 py-1 rounded-full">
                    <span id="cart-count">0</span> Item
                </div>
            </div>

            <div class="cart-scroll">
                <!-- Santri Selector -->
                <div class="santri-selector">
                    <div class="santri-search-row mb-2">
                        <input id="input-santri" type="text" class="santri-search-input" placeholder="Cari Santri (NIS/Nama)">
                        <button id="scan-santri-btn" class="icon-btn" title="Scan Santri">📷</button>
                    </div>
                    
                    <div class="santri-display" id="santri-card">
                        <div>
                            <p class="font-bold text-sm text-slate-800" id="santri-name">Guest (Umum)</p>
                            <p class="text-xs text-slate-500" id="santri-nis">Tanpa Santri</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Saldo</p>
                            <p class="font-bold text-emerald-600" id="santri-balance">-</p>
                        </div>
                        <button id="clear-santri-btn" class="clear-santri-btn ml-2" title="Reset Santri">✕</button>
                    </div>
                    <div class="santri-results border border-slate-200 rounded-lg mt-2 max-h-52 overflow-y-auto" id="santri-results" hidden></div>
                </div>

                <div class="cart-items custom-scrollbar" id="cart-body">
                    <!-- Cart Items ID -->
                </div>

                <div class="cart-footer">
                    <div class="summary-row"><span>Subtotal</span><span id="subtotal-text">Rp0</span></div>
                    
                    <!-- Payment Method -->
                    <div class="payment-methods">
                        <button type="button" class="payment-method-btn" data-method="cash">Tunai</button>
                        <button type="button" class="payment-method-btn active" data-method="wallet">Saldo Santri</button>
                        <button type="button" class="payment-method-btn" data-method="gateway">Gateway</button>
                    </div>

                    <div class="payment-section" data-payment-section="cash">
                        <label class="pay-field">
                            <span class="pay-label">Jumlah Tunai</span>
                            <input id="pay-cash" type="number" class="pay-input" value="0">
                        </label>
                        <button type="button" class="btn secondary w-full text-sm mt-2" id="pay-exact-btn">💵 Uang Pas</button>
                    </div>

                    <div class="payment-section" data-payment-section="wallet" hidden>
                        <label class="pay-field">
                            <span class="pay-label">Saldo Digunakan</span>
                            <input id="pay-wallet" type="number" class="pay-input" value="0" readonly>
                        </label>
                    </div>

                    <div class="payment-section" data-payment-section="gateway" hidden>
                        <label class="pay-field">
                            <span class="pay-label">Jumlah Gateway</span>
                            <input id="pay-gateway" type="number" class="pay-input" value="0" readonly>
                        </label>
                        <p class="hint">Pembayaran dilanjutkan melalui provider gateway yang aktif.</p>
                    </div>

                    <div class="summary-total">
                        <span>Total</span>
                        <div class="total-amount">
                            <span id="total-text" class="text-emerald-700">Rp0</span>
                            <span id="wallet-warning" class="wallet-warning" hidden>Saldo tidak cukup</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-slate-500">
                        <span>Kembalian</span>
                        <span id="change-text">Rp0</span>
                    </div>

                    <div id="status-message" class="alert alert-success mt-2 text-sm" hidden></div>

                    <button id="submit-btn" type="button" class="pay-btn">
                        Bayar Sekarang
                    </button>

                    <!-- Receipt & Print -->
                    <div class="receipt-actions">
                        <span id="receipt-summary" style="font-size:0.8rem; color:#64748b;">Belum ada transaksi.</span>
                        <button type="button" class="transaction-history-link" id="transaction-history-btn">Lihat riwayat transaksi</button>
                        <button type="button" class="btn secondary" id="print-receipt-btn" style="font-size:0.8rem;" disabled>🖨️ Cetak</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="pos-footer">
        © 2026 Ryand Arifriantoni. All rights reserved.
    </footer>
</div>

<!-- Read-only Transaction History Modal -->
<div class="history-modal" id="transaction-history-modal" hidden>
    <div class="confirm-backdrop" id="transaction-history-backdrop"></div>
    <section class="history-card" role="dialog" aria-modal="true" aria-labelledby="transaction-history-title">
        <div class="confirm-header">
            <div class="confirm-header-left">
                <h2 id="transaction-history-title">Riwayat Transaksi</h2>
                <p>20 transaksi terbaru · hanya lihat dan cetak ulang nota</p>
            </div>
            <button type="button" class="confirm-close-btn" id="transaction-history-close-btn" aria-label="Tutup riwayat transaksi">✕</button>
        </div>
        <div class="history-body">
            <div class="history-state" id="transaction-history-state">Memuat riwayat transaksi…</div>
            <div class="history-list" id="transaction-history-list"></div>
            <div class="history-detail" id="transaction-history-detail" hidden></div>
        </div>
    </section>
</div>

<!-- Confirm Modal -->
<div class="confirm-modal" id="confirm-modal" hidden>
    <div class="confirm-backdrop" id="confirm-backdrop"></div>
    <div class="confirm-card">
        <div class="confirm-header">
            <div class="confirm-header-left">
                <h2>Konfirmasi Pembelian</h2>
                <p id="confirm-ref"></p>
            </div>
            <button type="button" class="confirm-close-btn" id="confirm-close-btn">✕</button>
        </div>

        <div class="confirm-body">
            <!-- Left: Order Details -->
            <div class="confirm-order">
                <h3 class="confirm-section-title">Detail Pesanan</h3>
                <table class="confirm-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="confirm-items"></tbody>
                </table>

                <div class="confirm-order-summary" id="confirm-order-summary"></div>
            </div>

            <!-- Right: Customer & Payment -->
            <div class="confirm-sidebar">
                <div>
                    <h3 class="confirm-section-title">Informasi Pembeli</h3>
                    <div class="confirm-info-grid" id="confirm-customer"></div>
                </div>

                <div>
                    <h3 class="confirm-section-title">Metode Pembayaran</h3>
                    <div class="confirm-info-grid" id="confirm-payment-info"></div>
                </div>
            </div>
        </div>

        <div class="confirm-footer">
            <button type="button" class="confirm-cancel-btn" id="confirm-cancel-btn">
                ✕ Batalkan
            </button>
            <button type="button" class="confirm-buy-btn" id="confirm-buy-btn">
                <span class="spinner"></span>
                <span class="btn-text">✓ Konfirmasi Beli</span>
            </button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="success-modal" id="success-modal" hidden>
    <div class="success-backdrop" id="success-backdrop"></div>
    <div class="success-card">
        <div class="success-icon-ring">
            <svg class="success-checkmark" viewBox="0 0 52 52">
                <circle class="success-circle" cx="26" cy="26" r="25" fill="none"/>
                <path class="success-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>
        <h2 class="success-title">Transaksi Berhasil! 🎉</h2>
        <p class="success-ref" id="success-ref"></p>

        <div class="success-details" id="success-details">
            <!-- JS fills this -->
        </div>

        <div class="success-summary" id="success-summary">
            <!-- JS fills this -->
        </div>

        <div class="success-actions">
            <button type="button" class="btn success-print-btn" id="success-print-btn">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Struk
            </button>
            <button type="button" class="btn primary success-new-btn" id="success-new-btn">
                Transaksi Baru
            </button>
        </div>

        <p class="success-hint">Tekan <kbd>Esc</kbd> atau klik "Transaksi Baru" untuk melanjutkan</p>
    </div>
</div>

<!-- Scan Modal -->
<div class="scan-modal" id="scan-modal" hidden>
    <div class="scan-card">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-lg" id="scan-title">Scan Barcode</h3>
            <button id="scan-close-btn" class="btn ghost p-1">✕</button>
        </div>
        <video id="scan-video" class="scan-video" autoplay playsinline></video>
        <p class="text-center text-sm text-slate-500" id="scan-hint">Arahkan kamera ke kode QR/Barcode</p>
    </div>
</div>

<!-- Offline Queue (Hidden but required by JS) -->
<ul id="offline-queue" hidden></ul>

@php
    $bootstrap = session('smart_bootstrap');
    if (! $bootstrap) {
        $bootstrap = [
            'token' => session('smart_token'),
            'user' => session('smart_user'),
        ];
    }
@endphp
<script>
    window.__SMART_BOOTSTRAP__ = @json($bootstrap ?? []);
</script>
</body>
</html>
