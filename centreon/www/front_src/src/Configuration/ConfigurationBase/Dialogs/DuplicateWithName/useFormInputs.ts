import { type InputProps, InputType } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { labelName } from '../../translatedLabels';

interface FormInputsState {
  inputs: Array<InputProps>;
}

const useFormInputs = (): FormInputsState => {
  const { t } = useTranslation();

  const inputs = useMemo(
    () => [
      {
        autoFocus: true,
        dataTestId: 'duplicate-name',
        fieldName: 'name',
        group: 'main',
        label: t(labelName),
        required: true,
        type: InputType.Text
      }
    ],
    [t]
  );

  return { inputs };
};

export default useFormInputs;
