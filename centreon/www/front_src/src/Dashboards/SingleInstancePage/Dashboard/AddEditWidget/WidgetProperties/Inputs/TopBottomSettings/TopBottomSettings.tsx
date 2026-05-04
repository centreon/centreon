import {
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography
} from '@mui/material';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import {
  labelBottom,
  labelDisplay,
  labelHosts,
  labelNumberOfValues,
  labelShowValueLabels,
  labelTop
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import WidgetSwitch from '../Switch';
import { useTopBottomSettingsStyles } from './TopBottomSettings.styles';
import useTopBottomSettings from './useTopBottomSettings';

const TopBottomSettings = ({
  propertyName,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useTopBottomSettingsStyles();

  const { value, changeOrder, changeNumberOfValues } =
    useTopBottomSettings(propertyName);

  const { canEditField } = useCanEditProperties();

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      <div className={classes.values}>
        <Label>{t(labelDisplay)}</Label>
        <TextField
          className={classes.input}
          disabled={!canEditField}
          onChange={changeNumberOfValues}
          size="compact"
          slotProps={{
            htmlInput: {
              'aria-label': t(labelNumberOfValues) as string,
              max: 50,
              min: 1
            }
          }}
          type="number"
          value={value.numberOfValues}
        />
        <Typography>{t(labelHosts)}</Typography>
        <ToggleButtonGroup
          className={classes.toggleButtonGroup}
          color="primary"
          disabled={!canEditField}
          exclusive
          onChange={changeOrder}
          size="small"
          value={value.order}
        >
          <ToggleButton data-testid={labelTop} value="top">
            {t(labelTop)}
          </ToggleButton>
          <ToggleButton data-testid={labelBottom} value="bottom">
            {t(labelBottom)}
          </ToggleButton>
        </ToggleButtonGroup>
      </div>
      <WidgetSwitch
        label={labelShowValueLabels}
        propertyName={`${propertyName}.showLabels`}
      />
    </div>
  );
};

export default TopBottomSettings;
