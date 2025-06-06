import { Button } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import { labelClear, labelSearch } from '../../translatedLabels';
import { useFilterStyles } from '../Filters.styles';

import useFilters from './useFilters';

import { equals } from 'ramda';
import { FieldType } from '../../../models';
import useLoadData from '../../Listing/useLoadData';
import Status from './Fields/Status';
import Text from './Fields/Text';

const Filters = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const { isLoading } = useLoadData();

  const {
    reset,
    isClearDisabled,
    change,
    changeCheckbox,
    reload,
    filtersConfiguration,
    filters
  } = useFilters();

  return (
    <div className={classes.additionalFilters} data-testid="advanced-filters">
      {filtersConfiguration?.map((filter) => {
        if (equals(filter.fieldType, FieldType.Status))
          return (
            <Status
              change={changeCheckbox}
              filters={filters}
              key={filter.name}
            />
          );

        return (
          <Text
            label={filter.name}
            name={filter.fieldName}
            change={change}
            filters={filters}
            key={filter.name}
          />
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
