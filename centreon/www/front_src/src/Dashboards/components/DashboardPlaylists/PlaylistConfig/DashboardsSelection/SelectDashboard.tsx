import { useTranslation } from 'react-i18next';

import AddCircleIcon from '@mui/icons-material/AddCircle';

import { SingleConnectedAutocompleteField } from '@centreon/ui';
import { IconButton } from '@centreon/ui/components';

import { labelAddADashboard } from '../../../../translatedLabels';
import { usePlaylistConfigStyles } from '../PlaylistConfig.styles';

import { useSelectDashboard } from './useSelectDashboard';

interface Props {
  addItem: (item) => void;
}

const SelectDashboard = ({ addItem }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = usePlaylistConfigStyles();

  const {
    getEndpoint,
    selectedDashboard,
    selectDashboard,
    addDashboard,
    addIconDisabled,
    renderOption,
    getOptionDisabled
  } = useSelectDashboard(addItem);

  return (
    <div className={classes.selectDasbhoard}>
      <SingleConnectedAutocompleteField
        clearable
        fullWidth
        disableClearable={false}
        field="name"
        getEndpoint={getEndpoint}
        getOptionDisabled={getOptionDisabled}
        label={t(labelAddADashboard)}
        renderOption={renderOption}
        value={selectedDashboard}
        onChange={selectDashboard}
      />
      <IconButton
        aria-label={t(labelAddADashboard)}
        data-testid={labelAddADashboard}
        disabled={addIconDisabled}
        icon={<AddCircleIcon />}
        onClick={addDashboard}
      />
    </div>
  );
};

export default SelectDashboard;
