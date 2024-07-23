import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { TextField } from '@centreon/ui';

import { labelValue, labelName, labelPort } from '../../translatedLabels';
import { ConnectorConfiguration } from '../models';

import { useParameterStyles } from './useParametersStyles';

const Port = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParameterStyles();

  const { values, setFieldValue } = useFormikContext<ConnectorConfiguration>();

  const changePortValue = (event): void => {
    setFieldValue('parameters.port', event.target.value);
  };

  return (
    <div className={classes.parameterItem}>
      <TextField
        disabled
        fullWidth
        required
        dataTestId={labelName}
        label={t(labelPort)}
        value={t(labelPort)}
      />
      <TextField
        fullWidth
        required
        dataTestId={labelValue}
        label={t(labelValue)}
        name="port"
        type="number"
        value={values.parameters.port}
        onChange={changePortValue}
      />
    </div>
  );
};

export default Port;
