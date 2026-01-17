import './bootstrap';
import './pwa/registerServiceWorker';

// Libraries
import $ from 'jquery';
import DataTable from 'datatables.net-dt';
import 'datatables.net-buttons-dt';
import 'datatables.net-responsive-dt';
import flatpickr from "flatpickr";
import Chart from 'chart.js/auto';

// Styles
import 'datatables.net-dt/css/dataTables.dataTables.min.css';
import 'datatables.net-buttons-dt/css/buttons.dataTables.min.css';
import 'datatables.net-responsive-dt/css/responsive.dataTables.min.css';
import 'flatpickr/dist/flatpickr.min.css';

// Make globally available
window.$ = window.jQuery = $;
window.DataTable = DataTable;
window.flatpickr = flatpickr;
window.Chart = Chart;

// Initialize DataTables Defaults
$.extend(true, $.fn.dataTable.defaults, {
    responsive: true,
    language: {
        search: "Cari:",
        searchPlaceholder: "Cari data...",
        lengthMenu: "Tampilkan _MENU_ entri",
        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
        infoEmpty: "Tidak ada data",
        infoFiltered: "(disaring dari _MAX_ total data)",
        paginate: {
            first: "Awal",
            last: "Akhir",
            next: "→",
            previous: "←"
        },
        emptyTable: "Belum ada data tersedia"
    },
    dom: '<"dt-toolbar"lBf>rt<"dt-footer"ip>',
    buttons: [
        { extend: 'excel', text: 'Export Excel', className: 'btn-export' },
        { extend: 'print', text: 'Cetak', className: 'btn-export' }
    ]
});

// Auto-initialize components on load
document.addEventListener('DOMContentLoaded', () => {
    // Mobile sidebar toggle (admin/finance layouts)
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');

    if (sidebarToggle && sidebarOverlay) {
        const closeSidebar = () => document.body.classList.remove('sidebar-open');

        sidebarToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-open');
        });

        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // DataTables
    $('.datatable').DataTable();

    // Flatpickr
    flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
        allowInput: true
    });
    
    flatpickr(".daterange", {
        mode: "range",
        dateFormat: "Y-m-d",
        allowInput: true
    });
});
