import { useTranslation } from 'react-i18next';

import { Checkbox, FormControlLabel } from '@mui/material';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import {
  labelAllContactGroups,
  labelAllContactGroupsSelected,
  labelContactGroups
} from '../../../translatedLabels';
import useContactGroupsSelector from '../hooks/useContactGroupsSelector';
import { useSelectorStyles } from '../styles/Selector.styles';

const ContactGroupsSelector = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useSelectorStyles();

  const {
    contactGroups,
    checked,
    deleteContactGroupsItem,
    getEndpoint,
    onCheckboxChange,
    onMultiSelectChange
  } = useContactGroupsSelector();

  return (
    <div className={classes.container}>
      <MultiConnectedAutocompleteField
        allowUniqOption
        chipProps={{
          color: 'primary',
          onDelete: (_, option): void =>
            deleteContactGroupsItem({ contactGroups, option })
        }}
        className={classes.selector}
        dataTestId={labelContactGroups}
        disabled={checked}
        field="name"
        getEndpoint={getEndpoint()}
        label={
          checked ? t(labelAllContactGroupsSelected) : t(labelContactGroups)
        }
        limitTags={5}
        value={contactGroups}
        onChange={onMultiSelectChange()}
      />
      <FormControlLabel
        className={classes.label}
        control={
          <Checkbox
            checked={checked}
            className={classes.checkbox}
            size="small"
            onChange={onCheckboxChange}
          />
        }
        label={t(labelAllContactGroups)}
      />
    </div>
  );
};

export default ContactGroupsSelector;
