import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Slider as MuiSlider, Typography } from '@mui/material';

import { NumberField } from '@centreon/ui';

import { WidgetPropertyProps } from '../../../models';
import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import { useSlider } from './useSlider';
import { useSliderStyles } from './Slider.styles';

const Slider = ({
  propertyName,
  slider,
  label,
  isInGroup,
  defaultValue
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useSliderStyles();
  const { value, changeInputValue, changeSliderValue } = useSlider({
    propertyName
  });

  const { canEditField } = useCanEditProperties();

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      <Label>{t(label)}</Label>
      <div className={classes.sliderContainer}>
        <MuiSlider
          className={classes.slider}
          data-testid={`slider-${propertyName}`}
          defaultValue={defaultValue as number}
          disabled={!canEditField}
          max={slider?.max || 100}
          min={slider?.min || 0}
          track={false}
          value={value || 0}
          onChange={changeSliderValue}
        />
        <div className={classes.inputContainer}>
          <NumberField
            className={classes.input}
            containerClassName={classes.field}
            dataTestId="slider-input"
            disabled={!canEditField}
            inputProps={{
              'aria-label': `slider-${propertyName}-input`,
              max: slider?.max || 100,
              min: slider?.min || 0
            }}
            size="compact"
            value={value?.toString()}
            onChange={changeInputValue}
          />
          {slider?.unit && <Typography>{slider.unit}</Typography>}
        </div>
      </div>
    </div>
  );
};

export default Slider;
