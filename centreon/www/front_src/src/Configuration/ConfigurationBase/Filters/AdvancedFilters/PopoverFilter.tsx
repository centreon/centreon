import { JSX, Suspense } from 'react';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';
import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { labelFilters } from '../../translatedLabels';
import { useFilterStyles } from '../Filters.styles';
import Filters from './Filters';
import useCoutChangedFilters from './useCoutChangedFilters';

interface Props {
  filtersAtom;
  filtersAtomKey: string;
  areAdvancedFiltersVisible: boolean;
}

const PopoverFilter = ({
  filtersAtom,
  filtersAtomKey,
  areAdvancedFiltersVisible
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const { changedFiltersCount } = useCoutChangedFilters({ filtersAtom });

  if (!areAdvancedFiltersVisible) {
    return <div />;
  }

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
          {(): JSX.Element => (
            <Filters
              filtersAtom={filtersAtom}
              filtersAtomKey={filtersAtomKey}
            />
          )}
        </PopoverMenu>
      </Badge>
    </Suspense>
  );
};

export default PopoverFilter;
