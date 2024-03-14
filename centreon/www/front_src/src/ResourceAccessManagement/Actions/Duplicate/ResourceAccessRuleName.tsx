import { useTranslation } from 'react-i18next';
import { FormikValues, useFormikContext } from 'formik';
import { path } from 'ramda';

import { TextField } from '@centreon/ui';

import { labelName, labelResourceAccessRuleName } from '../../translatedLabels';

const ResourceAccessRuleName = (): React.JSX.Element => {
  const { t } = useTranslation();
  const {
    setFieldValue,
    errors,
    handleBlur,
    touched,
    values: { name: ruleName }
  } = useFormikContext<FormikValues>();

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    const { value } = event.target;
    setFieldValue('name', value);
  };

  const error = path(['name'], touched) ? path(['name'], errors) : undefined;

  return (
    <TextField
      required
      ariaLabel={labelResourceAccessRuleName}
      dataTestId="New resource access rule name"
      error={error as string | undefined}
      label={t(labelName) as string}
      name="name"
      value={ruleName}
      onBlur={handleBlur('name')}
      onChange={handleChange}
    />
  );
};

export default ResourceAccessRuleName;
