import { useTranslation } from 'react-i18next';

import { FormControlLabel, Checkbox } from '@mui/material';

import { ResourceTypeEnum } from '../../../models';
import { useAllOfResourceTypeCheckboxStyles } from '../styles/AllOfResourceTypeCheckbox.styles';
import { useAllOfResourceTypeCheckbox } from '../hooks/useAllOfResourceTypeCheckbox';

interface Props {
  datasetFilterIndex: number;
  datasetIndex: number;
  resourceType: ResourceTypeEnum;
}

const AllOfResourceTypeCheckbox = ({
  datasetFilterIndex,
  datasetIndex,
  resourceType
}: Props): React.JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useAllOfResourceTypeCheckboxStyles();

  const { checkboxLabel, checked, onChange } = useAllOfResourceTypeCheckbox(
    datasetFilterIndex,
    datasetIndex,
    resourceType
  );

  return (
    <FormControlLabel
      className={classes.label}
      control={
        <Checkbox
          checked={checked}
          className={classes.checkbox}
          size="small"
          onChange={onChange}
        />
      }
      label={t(checkboxLabel)}
    />
  );
};

export default AllOfResourceTypeCheckbox;
