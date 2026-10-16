import { TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import StatusChart from './StatusChart';

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
        <MemoryRouter>
          <div style={{ height: '90vh', width: '90%' }}>
            <StatusChart
              dashboardId={1}
              globalRefreshInterval={{ interval: 30, type: 'manual' }}
              hasDescription={false}
              id="1"
              isInViewport
              panelData={data as never}
              panelOptions={options as never}
              playlistHash="hash"
              refreshCount={0}
              widgetPrefixQuery="widget"
            />
          </div>
        </MemoryRouter>
      </Provider>
    </TestQueryProvider>
  );
};
