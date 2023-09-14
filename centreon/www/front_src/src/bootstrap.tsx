import { createRoot } from 'react-dom/client';

import Provider from './Main/Provider';

const container = document.getElementById('root') as HTMLElement;

const createApp = async (): Promise<void> => {
  window.React = await import(/* webpackChunkName: "external" */ 'react');

  createRoot(container).render(<Provider />);
};

createApp();
