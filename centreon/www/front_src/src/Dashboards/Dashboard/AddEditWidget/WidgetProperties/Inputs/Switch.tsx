import { useTranslation } from 'react-i18next';

import { FormSwitch, InputType } from '@centreon/ui';

import { WidgetPropertyProps } from '../../models';

const WidgetSwitch = ({
  propertyName,
  label
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  return (
    <FormSwitch
      fieldName={`options.${propertyName}`}
      label={t(label)}
      type={InputType.Switch}
    />
  );
};

export default WidgetSwitch;
