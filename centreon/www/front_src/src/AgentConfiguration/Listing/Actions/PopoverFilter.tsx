import { Suspense } from 'react';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';
import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';

import { useTranslation } from 'react-i18next';

import Filters from './Filters';
import useCountChangedFilters from './useCountChangedFilters';

import { useActionsStyles } from './Actions.styles';

import { labelFilters } from '../../translatedLabels';

const PopoverFilter = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { changedFiltersCount } = useCountChangedFilters();

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
