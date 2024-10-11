import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import {
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography
} from '@mui/material';

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
          inputProps={{
            'aria-label': t(labelNumberOfValues) as string,
            max: 50,
            min: 3
          }}
          size="compact"
          type="number"
          value={value.numberOfValues}
          onChange={changeNumberOfValues}
        />
        <Typography>{t(labelHosts)}</Typography>
        <ToggleButtonGroup
          exclusive
          className={classes.toggleButtonGroup}
          color="primary"
          disabled={!canEditField}
          size="small"
          value={value.order}
          onChange={changeOrder}
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
