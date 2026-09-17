import { createStore, Provider as JotaiProvider } from 'jotai';
import type { ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

const store = createStore();

const Provider = ({ children }: Props): JSX.Element => (
  <JotaiProvider store={store}>{children}</JotaiProvider>
);

export default Provider;
