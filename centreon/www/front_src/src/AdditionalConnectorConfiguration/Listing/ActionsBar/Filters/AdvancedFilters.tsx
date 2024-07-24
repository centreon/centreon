import { useEffect } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { equals, map, pick, propEq, reject } from 'ramda';

import {
  MultiConnectedAutocompleteField,
  SelectEntry,
  SelectField,
  TextField
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import {
  labelClear,
  labelName,
  labelPollers,
  labelSearch,
  labelType
} from '../../../translatedLabels';
import { useFilterStyles } from '../useActionsStyles';
import { filtersAtom } from '../../atom';
import useLoadData from '../../useLoadData';
import { getPollersEndpoint } from '../../../api/endpoints';
import {
  availableConnectorTypes,
  findConnectorTypeById
} from '../../../Modal/utils';

export const filtersDefaultValue = {
  name: '',
  pollers: [],
  type: { id: '1', name: 'vmware_v6' }
};

const AdvancedFilters = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const [filters, setFilters] = useAtom(filtersAtom);
  const { reload, isLoading } = useLoadData();

  const changeName = (event): void => {
    setFilters({ ...filters, name: event.target.value });
  };

  const changeType = (event): void => {
    const type = findConnectorTypeById(event.target.value);

    setFilters({ ...filters, type });
  };

  const changePollers = (_, pollers: Array<SelectEntry>): void => {
    const selectedpollers = map(pick(['id', 'name']), pollers || []);

    setFilters({
      ...filters,
      pollers: selectedpollers
    });
  };

  const deletePoller = (_, option): void => {
    const pollers = reject(propEq(option.id, 'id'), filters.pollers);

    setFilters({
      ...filters,
      pollers
    });
  };

  const isClearDisabled = equals(filters, filtersDefaultValue);

  const reset = (): void => {
    setFilters(filtersDefaultValue);
  };

  useEffect(() => {
    if (!isClearDisabled) {
      return;
    }

    reload();
  }, [isClearDisabled]);

  return (
    <div className={classes.additionalFilters} data-testid="FilterContainer">
      <TextField
        fullWidth
        dataTestId={labelName}
        label={t(labelName)}
        value={filters?.name}
        onChange={changeName}
      />

      <SelectField
        disabled
        dataTestId={labelType}
        label={t(labelType)}
        options={availableConnectorTypes}
        selectedOptionId={1}
        onChange={changeType}
      />

      <MultiConnectedAutocompleteField
        chipProps={{
          color: 'primary',
          onDelete: deletePoller
        }}
        dataTestId={labelPollers}
        getEndpoint={getPollersEndpoint}
        label={t(labelPollers)}
        value={filters.pollers}
        onChange={changePollers}
      />

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

export default AdvancedFilters;
