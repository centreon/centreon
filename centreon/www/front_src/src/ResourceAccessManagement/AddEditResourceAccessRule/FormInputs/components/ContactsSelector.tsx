import { useTranslation } from 'react-i18next';

import { Checkbox, FormControlLabel } from '@mui/material';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import {
  labelAllContacts,
  labelAllContactsSelected,
  labelContacts
} from '../../../translatedLabels';
import { useContactsSelectorStyles } from '../styles/ContactsSelector.styles';
import useContactsSelector from '../hooks/useContactsSelector';

const ContactsSelector = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useContactsSelectorStyles();

  const {
    checked,
    contacts,
    deleteContactItem,
    getEndpoint,
    onCheckboxChange,
    onMultiselectChange
  } = useContactsSelector();

  return (
    <div className={classes.container}>
      <MultiConnectedAutocompleteField
        allowUniqOption
        chipProps={{
          color: 'primary',
          onDelete: (_, option): void => deleteContactItem({ contacts, option })
        }}
        className={classes.selector}
        dataTestId={labelContacts}
        disabled={checked}
        field="name"
        getEndpoint={getEndpoint}
        label={checked ? t(labelAllContactsSelected) : t(labelContacts)}
        limitTags={5}
        value={contacts}
        onChange={onMultiselectChange()}
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
        label={t(labelAllContacts)}
      />
    </div>
  );
};

export default ContactsSelector;
