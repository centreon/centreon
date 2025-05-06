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
  value
}): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useResourceStyles();

  return (
    <TextField
      value={value}
      dataTestId={labelEnterRegex}
      fullWidth
      onChange={changeRegexField}
      label={t(labelEnterRegex)}
      slotProps={{
        input: {
          endAdornment: (
            <Tooltip label={t(labelDeactivateRegex)}>
              <IconButton
                className={classes.selectedRegexIcon}
                onClick={changeRegexFieldOnResourceType}
                size="small"
                icon={<RegexIcon className={classes.selectedRegexIconColor} />}
              />
            </Tooltip>
          )
        }
      }}
    />
  );
};

export default RegexField;
