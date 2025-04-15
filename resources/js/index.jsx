import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import '@shopify/polaris/build/esm/styles.css';

const root = document.createElement('div');
document.body.appendChild(root);
createRoot(root).render(
    <BrowserRouter>
        <App />
    </BrowserRouter>
);