import { useState } from 'react';

import Provider from './Provider';

interface Props {
  /** Browser path the application starts on. */
  page?: string;
}

// The whole application entry: the browser path is set before the router
// reads it, as the Cypress spec did with history.pushState.
export const Default = ({ page = '/' }: Props): JSX.Element => {
  useState(() => window.history.pushState({}, '', page));

  return (
    <div style={{ height: '100vh' }}>
      <Provider />
    </div>
  );
};
