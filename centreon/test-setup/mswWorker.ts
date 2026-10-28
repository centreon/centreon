import { setupWorker } from 'msw/browser';

// The MSW 2 worker the browser tests share. It intercepts through the Service
// Worker in public/mockServiceWorker.js, which the rstest dev server serves.
// The Cypress tests keep MSW 1 and its own worker in www/front_src/public.
export const worker = setupWorker();
