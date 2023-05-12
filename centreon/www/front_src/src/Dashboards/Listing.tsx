import { useMemo } from 'react';

import { dec, equals, gt } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSetAtom } from 'jotai';

import AddIcon from '@mui/icons-material/Add';
import { CircularProgress } from '@mui/material';

import {
  Button,
  DashboardFormVariant,
  List,
  ListEmptyState,
  ListItem,
  TiledListingActions,
  TiledListingContent,
  TiledListingList
} from '@centreon/ui';

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
      variant: DashboardFormVariant.Create
    });
  };

  const editDashboard = (dashboard: Dashboard) => (): void => {
    openDialog({
      dashboard,
      variant: DashboardFormVariant.Update
    });
  };

  return (
    <TiledListingList>
      <TiledListingActions>
        {hasDashboards && (
          <Button
            dataTestId="create-dashboard"
            icon={<AddIcon />}
            iconVariant="start"
            onClick={createDashboard}
          >
            {t(labelCreateADashboard)}
          </Button>
        )}
      </TiledListingActions>
      <TiledListingContent>
        {!hasDashboards ? (
          <ListEmptyState
            dataTestId="create-dashboard"
            labels={emptyListStateLabels}
            onCreate={createDashboard}
          />
        ) : (
          <List>
            {dashboards.map((dashboard, index) => {
              const isLastElement = equals(index, dec(dashboards.length));

              return (
                <ListItem
                  hasActions
                  hasCardAction
                  description={dashboard.description}
                  key={dashboard.id}
                  ref={isLastElement ? elementRef : undefined}
                  title={dashboard.name}
                  onDelete={(): void => undefined}
                  onEdit={editDashboard(dashboard)}
                />
              );
            })}
            {isLoading && (
              <div>
                <CircularProgress />
              </div>
            )}
          </List>
        )}
      </TiledListingContent>
    </TiledListingList>
  );
};

export default Listing;
