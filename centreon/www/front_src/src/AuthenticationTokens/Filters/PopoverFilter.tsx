import { Suspense } from 'react';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';
import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { labelFilters } from '../translatedLabels';

import Filters from './Filters';
import { useStyles } from './Filters.styles';
import useCoutChangedFilters from './useCoutChangedFilters';

const PopoverFilter = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

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
          popperProps={{ className: classes.popoverMenu }}
          title={t(labelFilters)}
        >
          {(): JSX.Element => <Filters />}
        </PopoverMenu>
      </Badge>
    </Suspense>
  );
};

export default PopoverFilter;
