import {
  MultiAutocompleteField,
  MultiConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import { getPollersEndpoint } from '../../api/endpoints';

import { useActionsStyles } from './Actions.styles';
import { useFilters } from './useFilters';

import { capitalize } from '@mui/material';
import { useGetAgentConfigurations } from '../../hooks/useGetAgentConfigurations';

import { AgentType } from '../../models';

import {
  labelAgentType,
  labelCMA,
  labelClear,
  labelName,
  labelPoller,
  labelSearch
} from '../../translatedLabels';

export const agentTypeOptions = [
  {
    id: AgentType.Telegraf,
    name: capitalize(AgentType.Telegraf)
  },
  {
    id: AgentType.CMA,
    name: labelCMA
  }
];

const Filters = (): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  const { isLoading } = useGetAgentConfigurations();

  const {
    filters,
    reload,
    reset,
    changeName,
    isClearDisabled,
    changeTypes,
    changerPollers,
    deletePoller,
    deleteType
  } = useFilters();

  return (
    <div className={classes.filtersContainer} data-testid="FilterContainer">
      <TextField
        fullWidth
        dataTestId={labelName}
        label={t(labelName)}
        value={filters.name}
        onChange={changeName}
      />
      <MultiAutocompleteField
        options={agentTypeOptions}
        value={filters.type}
        onChange={changeTypes}
        label={t(labelAgentType)}
        chipProps={{
          onDelete: deleteType,
          color: 'primary'
        }}
      />
      <MultiConnectedAutocompleteField
        chipProps={{
          onDelete: deletePoller,
          color: 'primary'
        }}
        dataTestId={labelPoller}
        getEndpoint={getPollersEndpoint}
        label={t(labelPoller)}
        value={filters['poller.id']}
        field="name"
        onChange={changerPollers}
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
