import { ReactElement, useCallback, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';

import { Form, FormProps } from '../../../Form';
import { InputType } from '../../../Form/Inputs/models';
import { FormVariant } from '../Form.models';
import { FormActions, FormActionsProps } from '../FormActions';

import { DashboardResource } from './Dashboard.resource';
import { useStyles } from './DashboardForm.styles';
import {
  labelMustBeAtLeast,
  labelMustBeMost,
  labelRequired
} from './translatedLabels';

type DashboardFormProps = {
  labels: DashboardFormLabels;
  name: string;
  onSubmit?: FormProps<DashboardResource>['submit'];
  variant?: FormVariant;
} & Pick<FormActionsProps, 'onCancel'>;

type DashboardFormLabels = {
  actions: FormActionsProps['labels'];
  entity: Required<DashboardResource>;
};

const DashboardDuplicationForm = ({
  variant = 'create',
  labels,
  onSubmit,
  onCancel,
  name
}: DashboardFormProps): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const formProps = useMemo<FormProps<DashboardResource>>(
    () => ({
      initialValues: { name },
      inputs: [
        {
          autoFocus: true,
          fieldName: 'name',
          group: 'main',
          label: labels?.entity?.name,
          required: true,
          type: InputType.Text
        }
      ],
      submit: (values, bag) => onSubmit?.(values, bag),
      validationSchema: object({
        name: string()
          .label(labels?.entity?.name)
          .min(3, ({ min, label }) => t(labelMustBeAtLeast, { label, min }))
          .max(50, ({ max, label }) => t(labelMustBeMost, { label, max }))
          .required(t(labelRequired) as string)
      })
    }),
    [labels, onSubmit]
  );

  const Actions = useCallback(
    () => (
      <FormActions<DashboardResource>
        enableSubmitWhenNotDirty
        labels={labels?.actions}
        variant={variant}
        onCancel={onCancel}
      />
    ),
    [labels, onCancel, variant]
  );

  return (
    <div className={classes.dashboardForm}>
      <Form<DashboardResource> {...formProps} Buttons={Actions} />
    </div>
  );
};

export { DashboardDuplicationForm };
