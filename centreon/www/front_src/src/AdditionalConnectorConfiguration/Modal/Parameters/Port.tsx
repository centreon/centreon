import { ReactElement } from 'react';

import { useFormikContext } from 'formik';
import { path } from 'ramda';
import { useTranslation } from 'react-i18next';

import { NumberField } from '@centreon/ui';

import { labelPort } from '../../translatedLabels';
import { AdditionalConnectorConfiguration } from '../models';

import { useParameterStyles } from './useParametersStyles';

const Port = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParameterStyles();

  const { values, setFieldValue, errors, touched, handleBlur } =
    useFormikContext<AdditionalConnectorConfiguration>();

  const changePortValue = (newPort): void => {
    setFieldValue('parameters.port', newPort);
  };

  const fieldNamePath = ['parameters', 'port'];

  const value = path(fieldNamePath, values);

  const error = path(fieldNamePath, touched)
    ? path(fieldNamePath, errors)
    : undefined;

  return (
    <div className={classes.parameterItem}>
      <NumberField
        fullWidth
        required
        dataTestId={`${labelPort}_value`}
        error={error as string}
        label={t(labelPort)}
        inputProps={{
          min: 1
        }}
        name="port"
        value={value?.toString()}
        onBlur={handleBlur('parameters.port')}
        onChange={changePortValue}
      />
    </div>
  );
};

export default Port;
