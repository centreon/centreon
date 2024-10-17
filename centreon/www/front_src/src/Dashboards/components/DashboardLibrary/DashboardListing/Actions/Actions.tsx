import { Box } from '@mui/material';

import useIsViewerUser from '../useIsViewerUser';

import { Checkbox } from '@centreon/ui';
import { useAtom } from 'jotai';
import { ChangeEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { onlyFavoriteDashboardAtom } from '../atom';
import { labelFavoriteDashboards } from '../translatedLabels';
import AddDashboard from './AddDashboard';
import Filter from './Filter';
import ViewMode from './ViewMode';
import { useActionsStyles } from './useActionsStyles';

const Actions = ({ openConfig }: { openConfig: () => void }): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  const isViewer = useIsViewerUser();

  const [onlyFavoriteDashboard, setOnlyFavoriteDashboard] = useAtom(
    onlyFavoriteDashboardAtom
  );

  const changeFavoriteFilter = (event: ChangeEvent<HTMLInputElement>): void => {
    setOnlyFavoriteDashboard(event.target.checked);
  };

  return (
    <Box className={classes.container}>
      <Box className={classes.actions}>
        {!isViewer && <AddDashboard openConfig={openConfig} />}
        <Box className={classes.filter}>
          <Filter />
        </Box>
        <ViewMode />
      </Box>
      <Box className={classes.favoriteFilter}>
        <Checkbox
          label={t(labelFavoriteDashboards)}
          checked={onlyFavoriteDashboard}
          onChange={changeFavoriteFilter}
        />
      </Box>
    </Box>
  );
};

export default Actions;
