import QRCode from 'qrcode';
import JsBarcode from 'jsbarcode';

function renderReceiptQr() {
    const target = document.getElementById('receipt-qr');
    if (! target) {
        return;
    }

    const value = target.dataset.value;
    if (! value) {
        return;
    }

    const canvas = document.createElement('canvas');
    target.innerHTML = '';
    target.appendChild(canvas);

    QRCode.toCanvas(canvas, value, {
        width: 120,
        margin: 1,
        color: {
            dark: '#0f172a',
            light: '#ffffff',
        }
    }).catch((error) => {
        console.warn('QR gagal dibuat', error);
    });
}

function renderReceiptBarcode() {
    const barcodeEl = document.getElementById('receipt-barcode');
    if (! barcodeEl) {
        return;
    }

    const value = barcodeEl.dataset.value;
    if (! value) {
        return;
    }

    JsBarcode(barcodeEl, value, {
        format: 'CODE128',
        height: 50,
        displayValue: false,
        lineColor: '#0f172a',
    });
}

document.addEventListener('DOMContentLoaded', () => {
    renderReceiptQr();
    renderReceiptBarcode();
});
