import { RegexIcon, TextField } from '@centreon/ui';
import { IconButton, Tooltip } from '@centreon/ui/components';

import { useTranslation } from 'react-i18next';

import {
  labelDeactivateRegex,
  labelEnterRegex
} from '../../../../translatedLabels';
import { useResourceStyles } from '../Inputs.styles';

const RegexField = ({
  changeRegexFieldOnResourceType,
  changeRegexField,
  resourceType,
  value
}): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useResourceStyles();

  return (
    <TextField
      dataTestId={`${labelEnterRegex}-${resourceType}`}
      fullWidth
      label={t(labelEnterRegex)}
      onChange={changeRegexField}
      slotProps={{
        input: {
          endAdornment: (
            <Tooltip label={t(labelDeactivateRegex)}>
              <IconButton
                className={classes.selectedRegexIcon}
                data-testid={`${labelDeactivateRegex}-${resourceType}`}
                icon={<RegexIcon className={classes.selectedRegexIconColor} />}
                onClick={changeRegexFieldOnResourceType}
                size="small"
              />
            </Tooltip>
          )
        }
      }}
      value={value}
    />
  );
};

export default RegexField;
