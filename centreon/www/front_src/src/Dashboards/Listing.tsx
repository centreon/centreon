import { useMemo } from 'react';

import { dec, equals, gt } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSetAtom } from 'jotai';

import AddIcon from '@mui/icons-material/Add';
import { CircularProgress } from '@mui/material';

import {
  TiledListingActions,
  TiledListingContent,
  TiledListingList
} from '@centreon/ui';
import { Button, DataTable } from '@centreon/ui/components';

import useDashboards from './useDashboards';
import {
  labelCreateADashboard,
  labelNoDashboardsFound
} from './translatedLabels';
import { openDialogAtom } from './atoms';
import { Dashboard } from './models';

const emptyListStateLabels = {
  actions: {
    create: labelCreateADashboard
  },
  title: labelNoDashboardsFound
};

const Listing = (): JSX.Element => {
  const { t } = useTranslation();
  const { dashboards, elementRef, isLoading } = useDashboards();

  const openDialog = useSetAtom(openDialogAtom);

  const hasDashboards = useMemo(
    () => gt(dashboards.length, 0),
    [dashboards.length]
  );

  const createDashboard = (): void => {
    openDialog({
      dashboard: null,
      variant: 'create'
    });
  };

  const editDashboard = (dashboard: Dashboard) => (): void => {
    openDialog({
      dashboard,
      variant: 'update'
    });
  };

  return (
    <TiledListingList>
      <TiledListingActions>
        {hasDashboards && (
          <Button
            aria-label="create"
            data-testid="create-dashboard"
            icon={<AddIcon />}
            iconVariant="start"
            onClick={createDashboard}
          >
            {t(labelCreateADashboard)}
          </Button>
        )}
      </TiledListingActions>
      <TiledListingContent>
        <DataTable isEmpty={hasDashboards}>
          {!hasDashboards ? (
            <DataTable.EmptyState
              aria-label="create"
              data-testid="create-dashboard"
              labels={emptyListStateLabels}
              onCreate={createDashboard}
            />
          ) : (
            dashboards.map((dashboard, index) => {
              const isLastElement = equals(index, dec(dashboards.length));

              return (
                <DataTable.Item
                  hasActions
                  hasCardAction
                  description={dashboard.description ?? undefined}
                  key={dashboard.id}
                  ref={isLastElement ? elementRef : undefined}
                  title={dashboard.name}
                  onDelete={(): void => undefined}
                  onEdit={editDashboard(dashboard)}
                />
              );
            })
          )}
        </DataTable>
        {isLoading && (
          <div>
            <CircularProgress />
          </div>
        )}
      </TiledListingContent>
    </TiledListingList>
  );
};

export default Listing;
