import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { NumberField } from '@centreon/ui';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

import { useTilesStyles } from './Tiles.styles';
import { labelDisplayUpTo, labelTiles } from './translatedLabels';

const WidgetTiles = ({
  propertyName,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useTilesStyles();

  const { values, setFieldValue } = useFormikContext<Widget>();

  const { canEditField } = useCanEditProperties();

  const value = useMemo<number | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const change = (inputValue: number): void => {
    setFieldValue(`options.${propertyName}`, inputValue);
  };

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div className={classes.container}>
      <Label>{t(labelDisplayUpTo)}</Label>
      <NumberField
        autoSize
        autoSizeDefaultWidth={10}
        dataTestId={labelTiles}
        defaultValue={value}
        disabled={!canEditField}
        fallbackValue={100}
        inputProps={{
          'aria-label': t(labelTiles),
          min: 1
        }}
        size="compact"
        type="number"
        onChange={change}
      />
      <Typography>{t(labelTiles)}</Typography>
    </div>
  );
};

export default WidgetTiles;
