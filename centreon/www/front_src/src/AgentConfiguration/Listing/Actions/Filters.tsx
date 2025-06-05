import { JSX } from 'react';

import {
  MultiAutocompleteField,
  MultiConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import { getPollersEndpoint } from '../../api/endpoints';

import { useActionsStyles } from './Actions.styles';
import { agentTypeOptions, useFilters } from './useFilters';

import { useGetAgentConfigurations } from '../../hooks/useGetAgentConfigurations';

import {
  labelAgentTypes,
  labelClear,
  labelName,
  labelPollers,
  labelSearch
} from '../../translatedLabels';

const Filters = (): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  const { isLoading } = useGetAgentConfigurations();

  const {
    filters,
    changeName,
    changeTypes,
    changePollers,
    deleteItem,
    reset,
    reload,
    isClearDisabled
  } = useFilters();

  return (
    <div className={classes.additionalFilters}>
      <TextField
        fullWidth
        dataTestId={labelName}
        label={t(labelName)}
        value={filters.name}
        onChange={changeName}
      />

      <MultiAutocompleteField
        options={agentTypeOptions}
        value={filters.types}
        onChange={changeTypes}
        label={t(labelAgentTypes)}
        chipProps={{
          onDelete: deleteItem('types'),
          color: 'primary'
        }}
      />
      <MultiConnectedAutocompleteField
        chipProps={{
          onDelete: deleteItem('pollers'),
          color: 'primary'
        }}
        dataTestId={labelPollers}
        getEndpoint={getPollersEndpoint}
        label={t(labelPollers)}
        value={filters.pollers}
        field="name"
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

export default Filters;
