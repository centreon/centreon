import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { NumberField } from '@centreon/ui';

import { getProperty } from '../utils';
import { Widget, WidgetPropertyProps } from '../../../models';
import { editProperties } from '../../../../hooks/useCanEditDashboard';

import { labelDisplayUpTo, labelTiles } from './translatedLabels';
import { useTilesStyles } from './Tiles.styles';

const WidgetTiles = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useTilesStyles();

  const { values, setFieldValue } = useFormikContext<Widget>();

  const { canEditField } = editProperties.useCanEditProperties();

  const value = useMemo<number | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const change = (inputValue: number): void => {
    setFieldValue(`options.${propertyName}`, inputValue);
  };

  return (
    <div className={classes.container}>
      <Typography>
        <strong>{t(labelDisplayUpTo)}</strong>
      </Typography>
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
