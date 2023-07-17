import { ReactElement } from 'react';

import { useStyles } from './AccessRightsForm.styles';
import {
  AccessRightsFormProvider,
  AccessRightsFormProviderProps
} from './useAccessRightsForm';
import {
  ContactAccessRightInput,
  ContactAccessRightInputProps
} from './Input/ContactAccessRightInput';
import {
  ContactAccessRightsList,
  ContactAccessRightsListProps
} from './List/ContactAccessRightsList';
import {
  AccessRightsFormActions,
  AccessRightsFormActionsProps
} from './AccessRightsFormActions';
import {
  AccessRightsStats,
  AccessRightsStatsProps
} from './Stats/AccessRightsStats';

export type AccessRightsFormProps = {
  labels: AccessRightsFormLabels;
  onCancel?: AccessRightsFormActionsProps['onCancel'];
  resourceLink: string;
} & Pick<
  AccessRightsFormProviderProps,
  'initialValues' | 'onSubmit' | 'options' | 'loadingStatus'
>;

export type AccessRightsFormLabels = {
  actions: AccessRightsFormActionsProps['labels'];
  input: ContactAccessRightInputProps['labels'];
  list: ContactAccessRightsListProps['labels'];
  stats: AccessRightsStatsProps['labels'];
};

const AccessRightsForm = ({
  labels,
  onCancel,
  resourceLink,
  ...providerProps
}: AccessRightsFormProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <AccessRightsFormProvider {...providerProps}>
      <div className={classes.accessRightsForm}>
        <ContactAccessRightInput labels={labels.input} />
        <span className={classes.accessRightsFormList}>
          <ContactAccessRightsList labels={labels.list} />
          <AccessRightsStats labels={labels.stats} />
        </span>
        <AccessRightsFormActions
          labels={labels.actions}
          resourceLink={resourceLink}
          onCancel={onCancel}
        />
      </div>
    </AccessRightsFormProvider>
  );
};

export { AccessRightsForm };
