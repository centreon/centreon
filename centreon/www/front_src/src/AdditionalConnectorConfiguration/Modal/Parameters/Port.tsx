import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';
import { path } from 'ramda';

import { TextField } from '@centreon/ui';

import { labelValue, labelPort, labelName } from '../../translatedLabels';
import { AdditionalConnectorConfiguration } from '../models';

import { useParameterStyles } from './useParametersStyles';

const Port = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParameterStyles();

  const { values, setFieldValue, errors, touched, handleBlur } =
    useFormikContext<AdditionalConnectorConfiguration>();

  const changePortValue = (event): void => {
    setFieldValue('parameters.port', event.target.value);
  };

  const fieldNamePath = ['parameters', 'port'];

  const value = path(fieldNamePath, values);

  const error = path(fieldNamePath, touched)
    ? path(fieldNamePath, errors)
    : undefined;

  return (
    <div className={classes.parameterItem}>
      <TextField
        disabled
        fullWidth
        dataTestId={labelPort}
        label={t(labelName)}
        value={t(labelPort)}
      />
      <TextField
        fullWidth
        required
        dataTestId={`${labelPort}_value`}
        error={error as string}
        label={t(labelValue)}
        name="port"
        type="number"
        value={value}
        onBlur={handleBlur('parameters.port')}
        onChange={changePortValue}
      />
    </div>
  );
};

export default Port;
