import { NumberField } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { path } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { AdditionalConnectorConfiguration } from '../../models';
import { labelPort } from '../../translatedLabels';
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
        dataTestId={`${labelPort}_value`}
        error={error as string}
        fullWidth
        label={t(labelPort)}
        name="port"
        onBlur={handleBlur('parameters.port')}
        onChange={changePortValue}
        required
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              min: 1
            }
          }
        }}
        value={value?.toString()}
      />
    </div>
  );
};

export default Port;
