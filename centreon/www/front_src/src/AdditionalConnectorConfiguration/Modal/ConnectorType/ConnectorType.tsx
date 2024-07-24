import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { SelectField } from '@centreon/ui';

import { labelSelectType, labelType } from '../../translatedLabels';
import { ConnectorConfiguration } from '../models';
import { availableConnectorTypes } from '../utils';

import { useConnectorTypeStyles } from './useConnectorTypeStyles';

const ConnectorType = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useConnectorTypeStyles();

  const { values, setFieldValue, errors, touched, handleBlur } =
    useFormikContext<ConnectorConfiguration>();

  const changeTypeValue = (event): void => {
    setFieldValue('type', event.target.value);
  };

  const error = touched?.type ? errors?.type : undefined;

  return (
    <div className={classes.typeContainer}>
      <SelectField
        fullWidth
        required
        dataTestId={labelType}
        error={error as string}
        label={t(labelSelectType)}
        name="type"
        options={availableConnectorTypes}
        selectedOptionId={values.type}
        onBlur={handleBlur('parameters.port')}
        onChange={changeTypeValue}
      />
    </div>
  );
};

export default ConnectorType;
