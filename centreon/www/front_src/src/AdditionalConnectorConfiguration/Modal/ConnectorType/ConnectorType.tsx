import { ReactElement } from 'react';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { SelectField } from '@centreon/ui';

import { labelSelectType, labelType } from '../../translatedLabels';
import { AdditionalConnectorConfiguration } from '../models';

import { useConnectorTypeStyles } from './useConnectorTypeStyles';

const ConnectorType = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useConnectorTypeStyles();

  const { values, setFieldValue, errors, touched, handleBlur } =
    useFormikContext<AdditionalConnectorConfiguration>();

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
        options={[{ id: 1, name: 'VMWare 6/7' }]}
        selectedOptionId={values.type}
        onBlur={handleBlur('parameters.port')}
        onChange={changeTypeValue}
      />
    </div>
  );
};

export default ConnectorType;
