import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';

import { PopoverMenu, SearchField } from '@centreon/ui';

import { useAtom, useSetAtom } from 'jotai';
import { isEmpty } from 'ramda';
import { type ChangeEvent, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { filtersAtom, pageAtom } from '../atoms';
import type { Filters as FiltersModel } from '../models';
import { labelFilters, labelSearchObjectName } from '../translatedLabels';
import AdvancedFilters from './AdvancedFilters';

const countAdvancedFilters = (filters: FiltersModel): number =>
  [
    !isEmpty(filters.actors),
    !isEmpty(filters.targetTypes),
    !isEmpty(filters.actions)
  ].filter(Boolean).length;

const Filters = (): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);
  const setPage = useSetAtom(pageAtom);

  const advancedFiltersCount = countAdvancedFilters(filters);

  const changeSearch = (event: ChangeEvent<HTMLInputElement>): void => {
    setPage(1);
    setFilters((current) => ({ ...current, search: event.target.value }));
  };

  const EndAdornment = useMemo(
    () => (): JSX.Element => (
      <Badge badgeContent={advancedFiltersCount} color="primary">
        <PopoverMenu
          dataTestId={labelFilters}
          icon={<TuneIcon fontSize="small" />}
          popperPlacement="bottom-end"
          // The anchor is this icon, 8px inside the search field's right edge,
          // so without the offset the panel hangs left of the bar (as in Hosts)
          popperProps={{
            modifiers: [{ name: 'offset', options: { offset: [8, 0] } }]
          }}
          title={t(labelFilters)}
        >
          {({ close } = {}): JSX.Element => <AdvancedFilters close={close} />}
        </PopoverMenu>
      </Badge>
    ),
    [advancedFiltersCount, t]
  );

  return (
    <div className="w-full">
      <SearchField
        dataTestId="search-bar"
        debounced
        EndAdornment={EndAdornment}
        fullWidth
        onChange={changeSearch}
        placeholder={t(labelSearchObjectName)}
        value={filters.search}
      />
    </div>
  );
};

export default Filters;
