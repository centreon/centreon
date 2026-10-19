import { userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import Widget from '.';
import type { PanelOptions } from './models';

interface Props {
  hasDescription?: boolean;
  isSmall?: boolean;
  panelOptions: PanelOptions;
  userLocale?: string | null;
}

export const Default = ({
  hasDescription = false,
  isSmall = false,
  panelOptions,
  userLocale = 'de_DE'
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, {
      locale: userLocale,
      timezone: 'Europe/Helsinki'
    } as never);

    return created;
  }, [userLocale]);

  return (
    <Provider store={store}>
      <div style={{ height: '400px', position: 'relative', width: '600px' }}>
        <Widget
          dashboardId={1}
          forceHeight={isSmall ? 300 : 400}
          forceWidth={isSmall ? 199 : 600}
          globalRefreshInterval={{ interval: null, type: 'manual' }}
          hasDescription={hasDescription}
          id="dashboard"
          isInViewport
          panelOptions={panelOptions}
          refreshCount={0}
          widgetPrefixQuery="prefix"
        />
      </div>
    </Provider>
  );
};
