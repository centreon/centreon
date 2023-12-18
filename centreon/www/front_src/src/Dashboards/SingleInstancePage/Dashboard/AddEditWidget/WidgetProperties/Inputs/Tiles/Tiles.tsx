import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { TextField } from '@centreon/ui';

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

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(
      `options.${propertyName}`,
      equals(event.target.value, '') || Number(event.target.value) < 0
        ? 1
        : Number(event.target.value)
    );
  };

  return (
    <div className={classes.container}>
      <Typography>
        <strong>{t(labelDisplayUpTo)}</strong>
      </Typography>
      <TextField
        autoSize
        autoSizeDefaultWidth={10}
        dataTestId={labelTiles}
        disabled={!canEditField}
        inputProps={{
          'aria-label': t(labelTiles),
          min: 1
        }}
        size="compact"
        type="number"
        value={value}
        onChange={change}
      />
      <Typography>{t(labelTiles)}</Typography>
    </div>
  );
};

export default WidgetTiles;
