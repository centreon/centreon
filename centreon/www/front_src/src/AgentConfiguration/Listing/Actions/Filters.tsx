import { MultiAutocompleteField, PopoverMenu } from '@centreon/ui';
import { Tune } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { labelAgentTypes, labelFilters } from '../../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import { agentTypeOptions, useFilters } from './useFilters';

const Filters = (): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  const { agentTypesFilter, changeAgentTypesFilter, deleteAgentTypesFilter } =
    useFilters();

  return (
    <PopoverMenu title={t(labelFilters)} icon={<Tune />}>
      <div className={classes.filtersContainer}>
        <MultiAutocompleteField
          options={agentTypeOptions}
          value={agentTypesFilter}
          onChange={changeAgentTypesFilter}
          label={t(labelAgentTypes)}
          chipProps={{
            onDelete: deleteAgentTypesFilter
          }}
        />
      </div>
    </PopoverMenu>
  );
};

export default Filters;
