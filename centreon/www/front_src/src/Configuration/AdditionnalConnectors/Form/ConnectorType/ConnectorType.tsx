import { SelectField } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { AdditionalConnectorConfiguration } from '../../models';
import { labelSelectType, labelType } from '../../translatedLabels';
import { useConnectorTypeStyles } from './ConnectorTypeStyles';

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
        dataTestId={labelType}
        error={error as string}
        fullWidth
        label={t(labelSelectType)}
        name="type"
        onBlur={handleBlur('parameters.port')}
        onChange={changeTypeValue}
        options={[{ id: 1, name: 'VMWare 6/7' }]}
        required
        selectedOptionId={values.type}
      />
    </div>
  );
};

export default ConnectorType;
