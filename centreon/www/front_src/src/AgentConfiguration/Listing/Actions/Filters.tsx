import {
  MultiAutocompleteField,
  MultiConnectedAutocompleteField,
  PopoverMenu
} from '@centreon/ui';
import { Tune } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { getPollersEndpoint } from '../../api/endpoints';
import {
  labelAgentTypes,
  labelFilters,
  labelPollers
} from '../../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import { agentTypeOptions, useFilters } from './useFilters';

const Filters = (): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  const { agentTypes, pollers, changeEntries, deleteEntry } = useFilters();

  return (
    <PopoverMenu title={t(labelFilters)} icon={<Tune />}>
      <div className={classes.filtersContainer}>
        <MultiAutocompleteField
          options={agentTypeOptions}
          value={agentTypes}
          onChange={changeEntries('agentTypes')}
          label={t(labelAgentTypes)}
          chipProps={{
            onDelete: deleteEntry('agentTypes')
          }}
        />
        <MultiConnectedAutocompleteField
          chipProps={{
            onDelete: deleteEntry('pollers')
          }}
          dataTestId={labelPollers}
          getEndpoint={getPollersEndpoint}
          label={t(labelPollers)}
          value={pollers}
          field="name"
          onChange={changeEntries('pollers')}
        />
      </div>
    </PopoverMenu>
  );
};

export default Filters;
