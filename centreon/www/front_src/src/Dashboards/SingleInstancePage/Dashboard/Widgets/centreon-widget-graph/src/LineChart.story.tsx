import { TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import WidgetLineChart from './LineChart';

interface Props {
  // Loose on purpose: specs pass plain JSON panel data and options.
  data: Record<string, unknown>;
  isPublic?: boolean;
  options: Record<string, unknown>;
}

export const Default = ({
  data,
  isPublic = false,
  options
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(isOnPublicPageAtom, isPublic);

    return created;
  }, [isPublic]);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <div style={{ height: '400px', width: '100%' }}>
          <WidgetLineChart
            dashboardId={1}
            globalRefreshInterval={{ interval: null, type: 'global' }}
            id="1"
            isInViewport
            panelData={data as never}
            panelOptions={
              {
                globalRefreshInterval: 30,
                refreshInterval: 'manual',
                ...options
              } as never
            }
            playlistHash="hash"
            refreshCount={0}
            widgetPrefixQuery="widget"
          />
        </div>
      </Provider>
    </TestQueryProvider>
  );
};
