import { createRoot } from 'react-dom/client';

// DEMO ONLY, never merged: mocks the host template API the backend does not
// serve yet. Loaded before the app so its first requests are already answered.
import './Configuration/HostTemplates/demo/mockApi';

import Provider from './Main/Provider';
import './app.css';

const container = document.getElementById('root') as HTMLElement;

const createApp = async (): Promise<void> => {
  window.React = await import(/* webpackChunkName: "external" */ 'react');
  createRoot(container).render(<Provider />);
};

createApp();
