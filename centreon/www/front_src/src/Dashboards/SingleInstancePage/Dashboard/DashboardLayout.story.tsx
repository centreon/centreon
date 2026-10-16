import { TestQueryProvider } from '@centreon/ui';
import { federatedWidgetsAtom, isOnPublicPageAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { federatedWidgetsPropertiesAtom } from '../../../federatedModules/atoms';
import DashboardLayout from './DashboardLayout';
import widgetGenericTextProperties from './Widgets/centreon-widget-generictext/properties.json';
import widgetInputProperties from './Widgets/centreon-widget-input/properties.json';
import widgetTextProperties from './Widgets/centreon-widget-text/properties.json';
import { internalWidgetComponents } from './Widgets/widgets';

interface Props {
  isPublic?: boolean;
}

// Dashboard 1 with the internal widgets; specs mock its details endpoint.
export const Default = ({ isPublic = false }: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(federatedWidgetsAtom, internalWidgetComponents);
    created.set(federatedWidgetsPropertiesAtom, [
      widgetTextProperties,
      widgetInputProperties,
      widgetGenericTextProperties
    ] as never);
    created.set(isOnPublicPageAtom, isPublic);

    return created;
  }, [isPublic]);

  return (
    <MemoryRouter>
      <TestQueryProvider>
        <Provider store={store}>
          <DashboardLayout
            displayedDashboardId={1}
            playlistHash={isPublic ? 'hash' : undefined}
          />
        </Provider>
      </TestQueryProvider>
    </MemoryRouter>
  );
};
