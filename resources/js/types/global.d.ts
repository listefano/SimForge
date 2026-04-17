import type Echo from 'laravel-echo';
import type { AxiosStatic } from 'axios';

declare global {
    interface Window {
        Echo?: Echo<'reverb'>;
        axios: AxiosStatic;
        Pusher: unknown;
    }
}

export {};
