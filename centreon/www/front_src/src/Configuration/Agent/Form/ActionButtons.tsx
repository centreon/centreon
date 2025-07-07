import { useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';
import { equals, isEmpty, isNil, isNotEmpty } from 'ramda';
import { JSX, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { FormActions } from '@centreon/ui/components';

import { CloseModalConfirmation } from '../../ConfigurationBase/Dialogs';
import { isFormDirtyAtom } from '../../ConfigurationBase/atoms';
import { agentTypeFormAtom } from '../atoms';
import { AgentConfigurationForm } from '../models';
import { labelCancel, labelSave } from '../translatedLabels';

const ActionButtons =
  ({ onCancel, mode }) =>
  (): JSX.Element => {
    const { t } = useTranslation();

    const [agentType, setAgentTypeForm] = useAtom(agentTypeFormAtom);

    const setIsDirty = useSetAtom(isFormDirtyAtom);

    const { dirty, isSubmitting, errors, values } =
      useFormikContext<AgentConfigurationForm>();

    const isSubmitDisabled = useMemo(
      () =>
        !dirty ||
        (isNotEmpty(errors) &&
          (isNil(errors.configuration?.hosts) ||
            isEmpty(errors.configuration?.hosts)))
          ? true
          : errors.configuration?.hosts?.some?.(
              (host) => !isNil(host) && !isEmpty(host)
            ) || isSubmitting,
      [dirty, isSubmitting, errors, values]
    );

    useEffect(() => {
      setIsDirty(dirty);
    }, [dirty]);

    useEffect(() => {
      if (isNil(agentType)) {
        setAgentTypeForm(values.type?.id);
      }
    }, []);
    const actionsLabels = {
      cancel: t(labelCancel),
      submit: {
        create: t(labelSave),
        update: t(labelSave)
      }
    };

    const variant = equals(mode, 'add') ? 'create' : 'update';

    return (
      <>
        <FormActions
          labels={actionsLabels}
          variant={variant}
          onCancel={onCancel}
          disableSubmit={isSubmitDisabled}
        />
        <CloseModalConfirmation />
      </>
    );
  };

export default ActionButtons;
