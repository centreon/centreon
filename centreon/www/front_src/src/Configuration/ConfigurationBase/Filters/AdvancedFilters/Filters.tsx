import { Button } from '@centreon/ui/components';

import { PrimitiveAtom, useAtom } from 'jotai';
import { equals } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { FieldType } from '../../../models';
import useLoadData from '../../Listing/useLoadData';
import { labelClear, labelSearch } from '../../translatedLabels';
import { useFilterStyles } from '../Filters.styles';
import {
  Checkbox,
  Checkboxes,
  MultiAutocomplete,
  MultiConnectedAutocomplete,
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

        return;
      })}

      <div className={classes.additionalFiltersButtons}>
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
