import { TextField } from '@centreon/ui';

import { FormikValues, useFormikContext } from 'formik';
import { path } from 'ramda';
import { useTranslation } from 'react-i18next';

import { labelName, labelNotificationName } from '../../translatedLabels';

const NotificationName = (): JSX.Element => {
  const { t } = useTranslation();

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    const { value } = event.target;
    setFieldValue('name', value);
  };

  const {
    setFieldValue,
    errors,
    handleBlur,
    touched,
    values: { name: notificationName }
  } = useFormikContext<FormikValues>();

  const error = path(['name'], touched) ? path(['name'], errors) : undefined;

  return (
    <TextField
      ariaLabel={labelNotificationName}
      dataTestId="New notification name"
      error={error as string | undefined}
      label={t(labelName) as string}
      name="name"
      onBlur={handleBlur('name')}
      onChange={handleChange}
      required
      value={notificationName}
    />
  );
};

export default NotificationName;
