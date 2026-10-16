import { TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import Graph from './Graph';

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
  const AnyGraph = Graph as unknown as (
    props: Record<string, unknown>
  ) => JSX.Element;

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <div style={{ height: '400px', width: '100%' }}>
          <AnyGraph
            globalRefreshInterval={{ interval: null, type: 'global' }}
            refreshCount={0}
            {...data}
            {...options}
            dashboardId={1}
            id="1"
            isFromPreview={false}
            playlistHash="hash"
            refreshInterval="default"
            refreshIntervalCustom={15}
          />
        </div>
      </Provider>
    </TestQueryProvider>
  );
};
