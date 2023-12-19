import { ReactNode } from 'react';

import { Provider as JotaiProvider, createStore } from 'jotai';

interface Props {
  children: ReactNode;
}

const store = createStore();

const Provider = ({ children }: Props): JSX.Element => (
  <JotaiProvider store={store}>{children}</JotaiProvider>
);

export default Provider;
