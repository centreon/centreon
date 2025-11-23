import { Button } from '@centreon/ui/components';
import { PrimitiveAtom, useAtom } from 'jotai';
import { equals } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { FieldType } from '../../../models';
import useLoadData from '../../Listing/useLoadData';
import {
  Checkbox,
  Checkboxes,
  MultiAutocomplete,
  MultiConnectedAutocomplete,
  Status,
  Text
} from './Fields';

import { useFilterStyles } from '../Filters.styles';
import useFilters from './useFilters';

import { labelClear, labelSearch } from '../../translatedLabels';

interface Props<TFilters> {
  filtersAtom: PrimitiveAtom<TFilters>;
  filtersAtomKey: string;
}

const Filters = <TFilters,>({
  filtersAtom,
  filtersAtomKey
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const [filters, setFilters] = useAtom(filtersAtom);

  const { isLoading } = useLoadData({ filtersAtom, filtersAtomKey });

  const { reset, isClearDisabled, reload, filtersConfiguration } = useFilters({
    filters,
    setFilters
  });

  return (
    <div className={classes.additionalFilters} data-testid="advanced-filters">
      {filtersConfiguration?.map((filter) => {
        if (equals(filter.fieldType, FieldType.Text))
          return (
            <Text<TFilters>
              label={filter.name}
              name={filter.fieldName}
              key={filter.name}
              filters={filters}
              setFilters={setFilters}
            />
          );

        if (equals(filter.fieldType, FieldType.Status))
          return (
            <Status<TFilters>
              key={filter.name}
              setFilters={setFilters}
              filters={filters}
            />
          );

        if (equals(filter.fieldType, FieldType.Checkbox))
          return (
            <Checkbox<TFilters>
              label={filter.name}
              key={filter.name}
              setFilters={setFilters}
              filters={filters}
              name={filter.fieldName}
            />
          );

        if (equals(filter.fieldType, FieldType.Checkboxes))
          return (
            <Checkboxes<TFilters>
              label={filter.name}
              key={filter.name}
              setFilters={setFilters}
              filters={filters}
              options={filter.options}
              name={filter.fieldName}
            />
          );

        if (equals(filter.fieldType, FieldType.MultiAutocomplete))
          return (
            <MultiAutocomplete<TFilters>
              label={filter.name}
              name={filter.fieldName}
              options={filter.options}
              key={filter.name}
              setFilters={setFilters}
              filters={filters}
            />
          );

        if (equals(filter.fieldType, FieldType.MultiConnectedAutocomplete))
          return (
            <MultiConnectedAutocomplete<TFilters>
              label={filter.name}
              name={filter.fieldName}
              getEndpoint={filter.getEndpoint}
              key={filter.name}
              setFilters={setFilters}
              filters={filters}
            />
          );

        return;
      })}

      <div className={classes.additionalFiltersButtons}>
        <Button
          data-testid={labelClear}
          disabled={isClearDisabled}
          size="small"
          variant="ghost"
          onClick={reset}
        >
          {t(labelClear)}
        </Button>
        <Button
          data-testid={labelSearch}
          disabled={isLoading}
          size="small"
          onClick={reload}
        >
          {t(labelSearch)}
        </Button>
      </div>
    </div>
  );
};

export default Filters;
