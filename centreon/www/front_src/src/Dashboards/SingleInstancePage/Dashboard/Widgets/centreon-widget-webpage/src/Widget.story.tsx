import { QueryClient } from '@tanstack/react-query';
import { useMemo } from 'react';

import Widget from '.';

interface Props {
  globalRefreshInterval?: {
    interval: number | null;
    type: 'automatic' | 'global' | 'manual';
  };
  url: string;
  widgetId?: string;
}

export const Default = ({
  globalRefreshInterval = { interval: null, type: 'manual' },
  url,
  widgetId = '1'
}: Props): JSX.Element => {
  const queryClient = useMemo(() => new QueryClient(), []);

  return (
    <div style={{ height: '100vh', position: 'relative', width: '100%' }}>
      <Widget
        dashboardId={1}
        globalRefreshInterval={globalRefreshInterval as never}
        hasDescription={false}
        id={widgetId}
        isInViewport
        panelOptions={{
          refreshInterval: 'custom',
          refreshIntervalCustom: 100,
          url
        }}
        queryClient={queryClient}
        refreshCount={0}
        widgetPrefixQuery="prefix"
      />
    </div>
  );
};
