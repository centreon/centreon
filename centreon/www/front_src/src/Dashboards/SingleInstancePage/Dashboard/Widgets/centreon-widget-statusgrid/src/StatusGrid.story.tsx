import { TestQueryProvider } from '@centreon/ui';
import {
  additionalResourcesAtom,
  isOnPublicPageAtom,
  userAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import Widget from '.';

// What the BAM module registers for the business activities and views.
const bamResources = [
  {
    availableResourceTypeOptions: [],
    baseEndpoint: '/bam/monitoring/business-activities',
    label: 'Business Activity',
    resourceType: 'business-activity'
  },
  {
    availableResourceTypeOptions: [
      { id: 'business-activity', name: 'Business Activity' }
    ],
    baseEndpoint: '/bam/monitoring/business-views',
    label: 'Business View',
    resourceType: 'business-view'
  }
];

interface Props {
  // Loose on purpose: specs pass plain JSON panel data and options.
  data: object;
  isPublic?: boolean;
  options: object;
  withBam?: boolean;
}

export const Default = ({
  data,
  isPublic = false,
  options,
  withBam = false
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, {
      locale: 'en_US',
      timezone: 'Europe/Paris'
    } as never);
    created.set(isOnPublicPageAtom, isPublic);
    if (withBam) {
      created.set(additionalResourcesAtom, bamResources as never);
    }

    return created;
  }, [isPublic, withBam]);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <MemoryRouter>
          <div style={{ height: '400px', width: '1000px' }}>
            <Widget
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
