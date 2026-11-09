// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Box } from '@mui/material';

import { Button } from '@centreon/ui/components';

import clsx from 'clsx';
import { PrimitiveAtom, useAtom } from 'jotai';
import { equals } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { FieldType } from '../../../models';
import useLoadData from '../../Listing/useLoadData';
import { labelClear, labelSearch } from '../../translatedLabels';
import { defaultFiltersPanelWidth } from '../Filters.styles';
import {
  Checkbox,
  Checkboxes,
  MultiAutocomplete,
  MultiConnectedAutocomplete,
  SingleConnectedAutocomplete,
  Status,
  Text
} from './Fields';
import useFilters from './useFilters';

interface Props<TFilters> {
  filtersAtom: PrimitiveAtom<TFilters>;
  filtersAtomKey: string;
}

const Filters = <TFilters,>({
  filtersAtom,
  filtersAtomKey
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const { isLoading } = useLoadData({ filtersAtom, filtersAtomKey });

  const {
    reset,
    isClearDisabled,
    reload,
    filtersConfiguration,
    filtersPanelWidth
  } = useFilters({
    filters,
    setFilters
  });

  const isFullWidth = equals(filtersPanelWidth, 'full');

  return (
    <div
      // A `full` panel hangs flush from the field and fills its popper.
      className={clsx(
        'flex flex-col gap-3 bg-background-paper p-4',
        !isFullWidth && 'mt-2 w-(--filters-panel-width)'
      )}
      data-testid="advanced-filters"
      // A width is in MUI spacing units, twice the Tailwind `--spacing`.
      style={
        isFullWidth
          ? undefined
          : {
              '--filters-panel-width': `calc(var(--spacing) * ${2 * (filtersPanelWidth ?? defaultFiltersPanelWidth)})`
            }
      }
    >
      {filtersConfiguration?.map((filter) => {
        if (equals(filter.fieldType, FieldType.Text))
          return (
            <Text<TFilters>
              filters={filters}
              key={filter.name}
              label={filter.name}
              name={filter.fieldName}
              setFilters={setFilters}
            />
          );

        if (equals(filter.fieldType, FieldType.Status))
          return (
            <Status<TFilters>
              filters={filters}
              key={filter.name}
              setFilters={setFilters}
            />
          );

        if (equals(filter.fieldType, FieldType.Checkbox))
          return (
            <Checkbox<TFilters>
              filters={filters}
              key={filter.name}
              label={filter.name}
              name={filter.fieldName}
              setFilters={setFilters}
            />
          );

        if (equals(filter.fieldType, FieldType.Checkboxes))
          return (
            <Checkboxes<TFilters>
              filters={filters}
              key={filter.name}
              label={filter.name}
              name={filter.fieldName}
              options={filter.options}
              setFilters={setFilters}
            />
          );

        if (equals(filter.fieldType, FieldType.MultiAutocomplete))
          return (
            <MultiAutocomplete<TFilters>
              filters={filters}
              key={filter.name}
              label={filter.name}
              name={filter.fieldName}
              options={filter.options}
              setFilters={setFilters}
            />
          );

        if (equals(filter.fieldType, FieldType.MultiConnectedAutocomplete))
          return (
            <MultiConnectedAutocomplete<TFilters>
              filters={filters}
              getEndpoint={filter.getEndpoint}
              key={filter.name}
              label={filter.name}
              name={filter.fieldName}
              setFilters={setFilters}
            />
          );

        if (equals(filter.fieldType, FieldType.SingleConnectedAutocomplete))
          return (
            <SingleConnectedAutocomplete<TFilters>
              baseEndpoint={filter.baseEndpoint}
              decoder={filter.decoder}
              filters={filters}
              getEndpoint={filter.getEndpoint}
              key={filter.name}
              label={filter.name}
              name={filter.fieldName}
              setFilters={setFilters}
            />
          );

        return <Box key={filter.name} />;
      })}

      <div className="mt-4 flex justify-between">
        <Button
          data-testid={labelClear}
          disabled={isClearDisabled}
          onClick={reset}
          size="small"
          variant="ghost"
        >
          {t(labelClear)}
        </Button>
        <Button
          data-testid={labelSearch}
          disabled={isLoading}
          onClick={reload}
          size="small"
        >
          {t(labelSearch)}
        </Button>
      </div>
    </div>
  );
};

export default Filters;
