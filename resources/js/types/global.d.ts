import type { AxiosStatic } from 'axios';
import type { Config, RouteParams, RouteParamsWithQueryOverload } from 'ziggy-js';

declare global {
    interface Window {
        axios: AxiosStatic;
        Ziggy: Config;
    }

    function route(): { current: (name?: string) => boolean };
    function route(name: string, params?: RouteParamsWithQueryOverload | RouteParams, absolute?: boolean): string;
}
