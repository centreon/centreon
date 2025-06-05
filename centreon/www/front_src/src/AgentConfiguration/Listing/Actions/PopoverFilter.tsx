import { Suspense } from 'react';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';
import TuneIcon from '@mui/icons-material/Tune';
import { useTranslation } from 'react-i18next';
import { labelFilters } from '../../translatedLabels';

import Filters from './Filters';

import { Badge } from '@mui/material';

import useCoutChangedFilters from '../../hooks/useCoutChangedFilters';

import { useActionsStyles } from './Actions.styles';

const PopoverFilter = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { changedFiltersCount } = useCoutChangedFilters();

  return (
    <Suspense
      fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
    >
      <Badge
        color="primary"
        badgeContent={changedFiltersCount}
        className={classes.badge}
      >
        <PopoverMenu
          dataTestId={labelFilters}
          icon={<TuneIcon fontSize="small" />}
          popperPlacement="bottom-end"
          title={t(labelFilters)}
        >
          {(): JSX.Element => <Filters />}
        </PopoverMenu>
      </Badge>
    </Suspense>
  );
};

export default PopoverFilter;
