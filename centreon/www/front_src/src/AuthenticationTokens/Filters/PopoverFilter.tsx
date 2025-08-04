import { Suspense } from 'react';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';
import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';
import { useAtomValue } from 'jotai';
import { equals, filter, length, pipe, toPairs } from 'ramda';
import { useTranslation } from 'react-i18next';
import { filtersAtom } from '../atoms';
import { labelFilters } from '../translatedLabels';
import { filtersInitialValues } from '../utils';
import Filters from './Filters';
import { useStyles } from './Filters.styles';

const countDifferences = (defaultValues, values) =>
  pipe(
    toPairs,
    filter(([key, val]) => !equals(val, values[key])),
    length
  )(defaultValues);

const PopoverFilter = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const filters = useAtomValue(filtersAtom);

  const changedFiltersCount = countDifferences(filtersInitialValues, filters);

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
