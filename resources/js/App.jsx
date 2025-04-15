import { Provider } from "@shopify/app-bridge-react";
import { AppProvider } from "@shopify/polaris";
import { useState } from "react";
import { Routes, Route } from "react-router-dom";
import enTranslations from "@shopify/polaris/locales/en.json";
import MissingApiKey from "./components/MissingApiKey";
import ProductCreator from "./components/ProductCreator";
import ProductForm from "./components/ProductForm";

const App = () => {
    const [appBridgeConfig] = useState(() => {
        const host = new URLSearchParams(location.search).get("host") || window.__SHOPIFY_HOST;
        window.__SHOPIFY_HOST = host;
        return {
            host,
            apiKey: import.meta.env.VITE_SHOPIFY_API_KEY,
            forceRedirect: true,
        };
    });

    if (!appBridgeConfig.apiKey) {
        return (
            <AppProvider i18n={enTranslations}>
                <MissingApiKey />
            </AppProvider>
        );
    }

    return (
        <AppProvider i18n={enTranslations}>
            <Provider config={appBridgeConfig}>
                <Routes>
                    <Route path="/" element={<ProductForm />} />
                    <Route path="/products/create" element={<ProductForm />} />
                </Routes>
            </Provider>
        </AppProvider>
    );
};

export default App;
