import { useTranslation } from 'react-i18next';

import { Checkbox, FormControlLabel } from '@mui/material';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import useMultiConnectedAutocomplete from '../hooks/useMultiConnectedAutocomplete';
import { useMultiSelectedAutocompleteStyles } from '../styles/MultiSelectedAutocomplete.styles';

interface Props {
  type: string;
}

const MultiConnectedAutocomplete = ({ type }: Props): React.JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useMultiSelectedAutocompleteStyles();

  const {
    checked,
    deleteItem,
    getEndpoint,
    label,
    labelAll,
    labelAllSelected,
    onCheckboxChange,
    onMultiselectChange,
    value
  } = useMultiConnectedAutocomplete(type);

  return (
    <div className={classes.container}>
      <MultiConnectedAutocompleteField
        allowUniqOption
        chipProps={{
          color: 'primary',
          onDelete: (_, option): void => deleteItem({ option, value })
        }}
        className={classes.selector}
        dataTestId={label}
        disabled={checked}
        field="alias"
        getEndpoint={getEndpoint}
        label={checked ? t(labelAllSelected) : t(label)}
        limitTags={5}
        value={value}
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
        label={t(labelAll)}
      />
    </div>
  );
};

export default MultiConnectedAutocomplete;
