import { MemoryRouter } from 'react-router';

import PublicPagesManager from './PublicPagesManager';

interface Props {
  pathname: string;
}

// A memory router on the given path replaces the useLocation stub of Cypress.
export const Default = ({ pathname }: Props): JSX.Element => (
  <MemoryRouter initialEntries={[pathname]}>
    <div style={{ height: '100vh' }}>
      <PublicPagesManager />
    </div>
  </MemoryRouter>
);
