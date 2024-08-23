import { useTranslation } from 'react-i18next';

import { Checkbox, FormControlLabel } from '@mui/material';

import { Dataset, ResourceTypeEnum } from '../../../models';
import { useAllOfResourceTypeCheckbox } from '../hooks/useAllOfResourceTypeCheckbox';
import { useAllOfResourceTypeCheckboxStyles } from '../styles/AllOfResourceTypeCheckbox.styles';

interface Props {
  datasetFilter: Array<Dataset>;
  datasetFilterIndex: number;
  datasetIndex: number;
  resourceType: ResourceTypeEnum;
}

const AllOfResourceTypeCheckbox = ({
  datasetFilter,
  datasetFilterIndex,
  datasetIndex,
  resourceType
}: Props): React.JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useAllOfResourceTypeCheckboxStyles();

  const { checkboxLabel, checked, onChange } = useAllOfResourceTypeCheckbox(
    datasetFilter,
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
