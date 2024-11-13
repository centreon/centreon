import {
  MultiAutocompleteField,
  MultiConnectedAutocompleteField,
  PopoverMenu
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { Tune } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { getPollersEndpoint } from '../../api/endpoints';
import {
  labelAgentTypes,
  labelClear,
  labelFilters,
  labelPollers
} from '../../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import { agentTypeOptions, useFilters } from './useFilters';

const Filters = (): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  const { agentTypes, pollers, changeEntries, deleteEntry, clearFilters } =
    useFilters();

  return (
    <PopoverMenu title={t(labelFilters)} icon={<Tune />}>
      {(): JSX.Element => (
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
          <Button
            onClick={clearFilters}
            variant="ghost"
            className={classes.clearButton}
            size="small"
          >
            {t(labelClear)}
          </Button>
        </div>
      )}
    </PopoverMenu>
  );
};

export default Filters;
