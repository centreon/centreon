import { TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo, useState } from 'react';
import { MemoryRouter } from 'react-router';

import GroupMonitoring from './GroupMonitoring';

interface Props {
  isFromPreview?: boolean;
  isPublic?: boolean;
  // Loose on purpose: specs pass plain JSON panel data and options.
  panelData?: Record<string, unknown>;
  panelOptions: Record<string, unknown>;
}

const hostGroups = {
  resources: [{ resources: [], resourceType: 'host-group' }]
};

// The last setPanelOptions call is recorded for the specs.
export const Default = ({
  isFromPreview,
  isPublic = false,
  panelData = hostGroups,
  panelOptions
}: Props): JSX.Element => {
  const [lastOptions, setLastOptions] = useState('');
  const store = useMemo(() => {
    const created = createStore();
    created.set(isOnPublicPageAtom, isPublic);

    return created;
  }, [isPublic]);

  return (
    <div style={{ height: '100vh' }}>
      <TestQueryProvider>
        <MemoryRouter>
          <Provider store={store}>
            <GroupMonitoring
              dashboardId={1}
              globalRefreshInterval={{ interval: 15, type: 'manual' }}
              hasDescription={false}
              id="1"
              isFromPreview={isFromPreview}
              isInViewport
              panelData={panelData as never}
              panelOptions={
                { ...panelOptions, refreshInterval: 'default' } as never
              }
              playlistHash="hash"
              refreshCount={0}
              setPanelOptions={(options) =>
                setLastOptions(JSON.stringify(options))
              }
              widgetPrefixQuery="widget"
            />
          </Provider>
        </MemoryRouter>
      </TestQueryProvider>
      <form hidden>
        <input data-testid="panel-options" readOnly value={lastOptions} />
      </form>
    </div>
  );
};
