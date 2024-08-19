import { SelectEntry } from '../../..';

import { useAccessRightsStyles } from './AccessRights.styles';
import Actions from './Actions/Actions';
import List from './List/List';
import ListSkeleton from './List/ListSkeleton';
import Provider from './Provider';
import ShareInput from './ShareInput/ShareInput';
import Stats from './Stats/Stats';
import { AccessRightInitialValues, Endpoints, Labels } from './models';
import { useAccessRightsChange } from './useAccessRightsChange';
import { useAccessRightsInitValues } from './useAccessRightsInitValues';

interface Props {
  cancel?: ({ dirty, values }) => void;
  endpoints: Endpoints;
  initialValues: Array<AccessRightInitialValues>;
  isSubmitting?: boolean;
  labels: Labels;
  loading?: boolean;
  onChange?: (values: Array<AccessRightInitialValues>) => void;
  roles: Array<SelectEntry>;
  submit: (values: Array<AccessRightInitialValues>) => Promise<void>;
}

export const AccessRightsContent = ({
  initialValues,
  roles,
  endpoints,
  submit,
  cancel,
  loading,
  labels,
  isSubmitting,
  onChange
}: Props): JSX.Element => {
  const { classes } = useAccessRightsStyles();
  const clear = useAccessRightsInitValues({ initialValues });
  useAccessRightsChange(onChange);

  return (
    <div className={classes.container}>
      <ShareInput endpoints={endpoints} labels={labels.add} roles={roles} />
      {loading ? <ListSkeleton /> : <List labels={labels.list} roles={roles} />}
      <Stats labels={labels.list} />
      <Actions
        cancel={cancel}
        clear={clear}
        isSubmitting={isSubmitting}
        labels={labels.actions}
        submit={submit}
      />
    </div>
  );
};

export const AccessRights = (props: Props): JSX.Element => (
  <Provider>
    <AccessRightsContent {...props} />
  </Provider>
);
