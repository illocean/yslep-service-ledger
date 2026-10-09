import axios from 'axios';
import Alpine from 'alpinejs';

window.axios = axios;
window.Alpine = Alpine;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Register Alpine components
import './components/quick-add-bar';
import './components/entry-modal';
import './components/ledger-search';
import './components/snapshot-builder';

Alpine.start();
