import { TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import type { Data } from '../../models';
import Widget from '.';
import type { TopBottomSettings } from './models';

interface Props {
  // Loose on purpose: specs pass plain JSON panel data.
  data?: Record<string, unknown>;
  isPublic?: boolean;
  topBottomSettings?: TopBottomSettings;
}

const defaultSettings = {
  numberOfValues: 10,
  order: 'top',
  showLabels: true
} as TopBottomSettings;

export const Default = ({
  data = {},
  isPublic = false,
  topBottomSettings = defaultSettings
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(isOnPublicPageAtom, isPublic);

    return created;
  }, [isPublic]);

  return (
    <div style={{ height: '400px', width: '100%' }}>
      <TestQueryProvider>
        <Provider store={store}>
          <MemoryRouter>
            <Widget
              dashboardId={1}
              hasDescription={false}
              globalRefreshInterval={{ interval: 30, type: 'global' }}
              id="1"
              isInViewport
              panelData={data as unknown as Data}
              panelOptions={
                {
                  refreshInterval: 'custom',
                  refreshIntervalCustom: 30,
                  threshold: {
                    criticalType: 'default',
                    customCritical: 0,
                    customWarning: 0,
                    enabled: true,
                    warningType: 'default'
                  },
                  topBottomSettings,
                  valueFormat: 'human'
                } as never
              }
              playlistHash="hash"
              refreshCount={0}
              widgetPrefixQuery="widget"
            />
          </MemoryRouter>
        </Provider>
      </TestQueryProvider>
    </div>
  );
};
