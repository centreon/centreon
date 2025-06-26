import { Button } from '@centreon/ui/components';
import { equals } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';
import { useFilterStyles } from '../Filters.styles';

import { FieldType } from '../../../models';
import useLoadData from '../../Listing/useLoadData';
import {
  MultiAutocomplete,
  MultiConnectedAutocomplete,
  Status,
  Text
} from './Fields';
import useFilters from './useFilters';

import { labelClear, labelSearch } from '../../translatedLabels';

const Filters = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const { isLoading } = useLoadData();

  const { reset, isClearDisabled, reload, filtersConfiguration } = useFilters();

  return (
    <div className={classes.additionalFilters} data-testid="advanced-filters">
      {filtersConfiguration?.map((filter) => {
        if (equals(filter.fieldType, FieldType.Status))
          return <Status key={filter.name} />;
        if (equals(filter.fieldType, FieldType.MultiAutocomplete))
          return (
            <MultiAutocomplete
              label={filter.name}
              name={filter.fieldName}
              options={filter.options}
              key={filter.name}
            />
          );

        if (equals(filter.fieldType, FieldType.MultiConnectedAutocomplete))
          return (
            <MultiConnectedAutocomplete
              label={filter.name}
              name={filter.fieldName}
              getEndpoint={filter.getEndpoint}
              key={filter.name}
            />
          );

        return (
          <Text label={filter.name} name={filter.fieldName} key={filter.name} />
        );
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
