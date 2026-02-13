import { capitalize } from '@mui/material';

import {
  MultiAutocompleteField,
  MultiConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { useTranslation } from 'react-i18next';

import { getPollersEndpoint } from '../../api/endpoints';
import { useGetAgentConfigurations } from '../../hooks/useGetAgentConfigurations';
import { AgentType } from '../../models';
import {
  labelAgentType,
  labelClear,
  labelCMA,
  labelName,
  labelPoller,
  labelSearch
} from '../../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import { useFilters } from './useFilters';

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
        dataTestId={labelName}
        fullWidth
        label={t(labelName)}
        onChange={changeName}
        value={filters.name}
      />
      <MultiAutocompleteField
        chipProps={{
          color: 'primary',
          onDelete: deleteType
        }}
        label={t(labelAgentType)}
        onChange={changeTypes}
        options={agentTypeOptions}
        value={filters.type}
      />
      <MultiConnectedAutocompleteField
        chipProps={{
          color: 'primary',
          onDelete: deletePoller
        }}
        dataTestId={labelPoller}
        field="name"
        getEndpoint={getPollersEndpoint}
        label={t(labelPoller)}
        onChange={changerPollers}
        value={filters['poller.id']}
      />

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
