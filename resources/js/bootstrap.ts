import httpClient from '@/lib/http-client';

declare global {
    interface Window {
        axios: typeof httpClient;
    }
}

window.axios = httpClient;
