import { SelectEntry } from '../../..';

import { useAccessRightsStyles } from './AccessRights.styles';
import Actions from './Actions/Actions';
import List from './List/List';
import Provider from './Provider';
import ShareInput from './ShareInput/ShareInput';
import Stats from './Stats/Stats';
import { AccessRightInitialValues, Endpoints, Labels } from './models';
import { useAccessRightsInitValues } from './useAccessRightsInitValues';

interface Props {
  cancel: () => void;
  endpoints: Endpoints;
  initialValues: Array<AccessRightInitialValues>;
  labels: Labels;
  link?: string;
  roles: Array<SelectEntry>;
  submit: (values: Array<AccessRightInitialValues>) => void;
}

const AccessRights = ({
  initialValues,
  roles,
  endpoints,
  submit,
  cancel,
  link,
  labels
}: Props): JSX.Element => {
  const { classes } = useAccessRightsStyles();
  useAccessRightsInitValues({ initialValues });

  return (
    <div className={classes.container}>
      <ShareInput endpoints={endpoints} labels={labels.add} roles={roles} />
      <List labels={labels.list} roles={roles} />
      <Stats labels={labels.list} />
      <Actions
        cancel={cancel}
        labels={labels.actions}
        link={link}
        submit={submit}
      />
    </div>
  );
};

export default (props: Props): JSX.Element => (
  <Provider>
    <AccessRights {...props} />
  </Provider>
);
