// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';

import { PrimitiveAtom } from 'jotai';
import { JSX, Suspense } from 'react';
import { useTranslation } from 'react-i18next';

import { labelFilters } from '../../translatedLabels';
import { useFilterStyles } from '../Filters.styles';
import Filters from './Filters';
import useCoutChangedFilters from './useCoutChangedFilters';

interface Props<TFilters> {
  filtersAtom: PrimitiveAtom<TFilters>;
  filtersAtomKey: string;
  areAdvancedFiltersVisible: boolean;
}

const PopoverFilter = <TFilters,>({
  filtersAtom,
  filtersAtomKey,
  areAdvancedFiltersVisible
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles({});

  const { changedFiltersCount } = useCoutChangedFilters({ filtersAtom });

  if (!areAdvancedFiltersVisible) {
    return <div />;
  }

  return (
    <Suspense
      fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
    >
      <Badge
        badgeContent={changedFiltersCount}
        className={classes.badge}
        color="primary"
      >
        <PopoverMenu
          dataTestId={labelFilters}
          icon={<TuneIcon fontSize="small" />}
          popperPlacement="bottom-end"
          // The popper anchors to this icon, which sits 8px inside the search
          // field's right edge, so the panel hangs 8px left of the bar it
          // belongs to. Only visible once a listing widens the panel to the
          // bar's own width, but wrong at every width.
          popperProps={{
            modifiers: [{ name: 'offset', options: { offset: [8, 0] } }]
          }}
          title={t(labelFilters)}
        >
          {(): JSX.Element => (
            <Filters<TFilters>
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
