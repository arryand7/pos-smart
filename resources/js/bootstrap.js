import axios from 'axios';
import { initializeAuthToken } from './services/authToken';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrf = document.head.querySelector('meta[name="csrf-token"]');

if (csrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.content;
}

initializeAuthToken();
