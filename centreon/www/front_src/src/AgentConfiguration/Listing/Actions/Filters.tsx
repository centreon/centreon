import { capitalize } from '@mui/material';
import type { ChipProps } from '@mui/material/Chip';

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
          onDelete: deleteType as ChipProps['onDelete']
        }}
        label={t(labelAgentType)}
        onChange={
          changeTypes as unknown as Parameters<
            typeof MultiAutocompleteField
          >[0]['onChange']
        }
        options={agentTypeOptions}
        value={filters.type}
      />
      <MultiConnectedAutocompleteField
        chipProps={{
          color: 'primary',
          onDelete: deletePoller as ChipProps['onDelete']
        }}
        dataTestId={labelPoller}
        field="name"
        getEndpoint={
          getPollersEndpoint as unknown as (params: unknown) => string
        }
        label={t(labelPoller)}
        onChange={
          changerPollers as unknown as Parameters<
            typeof MultiConnectedAutocompleteField
          >[0]['onChange']
        }
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
