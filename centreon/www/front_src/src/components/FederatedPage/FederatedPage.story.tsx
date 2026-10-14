import { federatedModulesAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import FederatedPage from './FederatedPage';

interface Props {
  hasChildren?: boolean;
  route: string;
}

export const Default = ({ hasChildren = false, route }: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(federatedModulesAtom, []);

    return created;
  }, []);

  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <FederatedPage
          childrenComponent={hasChildren ? 'test' : undefined}
          route={route}
        />
      </Provider>
    </div>
  );
};
