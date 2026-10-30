// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';

import { PrimitiveAtom, useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { JSX, RefObject, Suspense } from 'react';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../atoms';
import { labelFilters } from '../../translatedLabels';
import { useFilterStyles } from '../Filters.styles';
import Filters from './Filters';
import useCoutChangedFilters from './useCoutChangedFilters';

interface Props<TFilters> {
  filtersAtom: PrimitiveAtom<TFilters>;
  filtersAtomKey: string;
  areAdvancedFiltersVisible: boolean;
  searchFieldRef: RefObject<HTMLDivElement | null>;
}

const matchReferenceWidth = {
  effect: ({ state }): void => {
    state.elements.popper.style.width = `${state.elements.reference.offsetWidth}px`;
  },
  enabled: true,
  fn: ({ state }): void => {
    state.styles.popper.width = `${state.rects.reference.width}px`;
  },
  name: 'matchReferenceWidth',
  phase: 'beforeWrite',
  requires: ['computeStyles']
};

// The anchor is this icon, 8px inside the search field's right edge, so
// without the offset the panel hangs left of the bar.
const iconAnchoredPopperProps = {
  modifiers: [{ name: 'offset', options: { offset: [8, 0] } }]
};

const PopoverFilter = <TFilters,>({
  filtersAtom,
  filtersAtomKey,
  areAdvancedFiltersVisible,
  searchFieldRef
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles({});

  const { changedFiltersCount } = useCoutChangedFilters({ filtersAtom });

  const isFullWidth = equals(
    useAtomValue(configurationAtom)?.filtersPanelWidth,
    'full'
  );

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
          popperPlacement={isFullWidth ? 'bottom-start' : 'bottom-end'}
          // Full width: hung from the search field itself rather than the
          // icon, so both edges line up with it whatever its width.
          popperProps={
            isFullWidth
              ? {
                  anchorEl: () => searchFieldRef.current,
                  modifiers: [matchReferenceWidth]
                }
              : iconAnchoredPopperProps
          }
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
