import { useAtomValue, useSetAtom } from 'jotai';
import { all, equals, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconAcknowledge from '@mui/icons-material/Person';

import { SeverityCode, useSnackbar } from '@centreon/ui';

import {
  resourcesToAcknowledgeAtom,
  resourcesToSetDowntimeAtom,
  selectedResourcesAtom
} from '../../atom';
import IconDowntime from '../Columns/Icons/Downtime';
import {
  labelAcknowledge,
  labelCheckCommandSent,
  labelCheckDescription,
  labelForcedCheckCommandSent,
  labelForcedCheckDescription,
  labelSetDowntime
} from '../translatedLabels';

import CheckActionButton from './Check';
import ResourceActionButton from './ResourceActionButton';
import useAclQuery from './aclQuery';
import { Action, CheckActionModel } from './model';

const useStyles = makeStyles()((theme) => ({
  action: {
    marginRight: theme.spacing(1)
  },
  flex: {
    alignItems: 'center',
    display: 'flex'
  }
}));

const ResourceActions = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const resources = useAtomValue(selectedResourcesAtom);
  const setResourcesToAcknowledge = useSetAtom(resourcesToAcknowledgeAtom);
  const setResourcesToSetDowntime = useSetAtom(resourcesToSetDowntimeAtom);

  const { canAcknowledge, canDowntime } = useAclQuery();

  const onSuccessCheckAction = (): void => {
    showSuccessMessage(t(labelCheckCommandSent));
  };

  const onSuccessForcedCheckAction = (): void => {
    showSuccessMessage(t(labelForcedCheckCommandSent));
  };

  const checkAction: CheckActionModel = {
    action: Action.Check,
    data: {
      listOptions: {
        descriptionCheck: labelCheckDescription,
        descriptionForcedCheck: labelForcedCheckDescription
      },

      successCallback: {
        onSuccessCheckAction,
        onSuccessForcedCheckAction
      }
    },
    extraRules: null
  };

  const actions = [
    { action: Action.Acknowledge, extraRules: null },
    checkAction,
    { action: Action.Downtime, extraRules: null }
  ];

  const extractActionsInformation = (
    key
  ): Record<string, boolean | undefined> | Record<string, never> => {
    const item = actions.find(({ action }) => action === key);

    return item
      ? {
          [`extraDisabled${key}`]: item.extraRules?.disabled,
          [`display${key}`]: true,
          [`extraPermitted${key}`]: item.extraRules?.permitted
        }
      : {};
  };

  const {
    displayAcknowledge,
    extraDisabledAcknowledge,
    extraPermittedAcknowledge
  } = extractActionsInformation(Action.Acknowledge);

  const { displayCheck } = extractActionsInformation(Action.Check);

  const extraCheckData = (
    actions.find(({ action }) =>
      equals(action, Action.Check)
    ) as CheckActionModel
  )?.data;

  const { displayDowntime, extraDisabledDowntime, extraPermittedDowntime } =
    extractActionsInformation(Action.Downtime);

  const prepareToAcknowledge = (): void => {
    setResourcesToAcknowledge(resources);
  };

  const prepareToSetDowntime = (): void => {
    setResourcesToSetDowntime(resources);
  };

  const areSelectedResourcesOk = all(
    pathEq(SeverityCode.OK, ['status', 'severity_code']),
    resources
  );

  const defaultDisableAcknowledge =
    !canAcknowledge(resources) || areSelectedResourcesOk;

  const disableAcknowledge =
    extraDisabledAcknowledge || defaultDisableAcknowledge;

  const defaultDisableDowntime = !canDowntime(resources);

  const disableDowntime = extraDisabledDowntime || defaultDisableDowntime;

  const hasSelectedResources = resources.length > 0;

  const defaultIsAcknowledgePermitted =
    canAcknowledge(resources) || !hasSelectedResources;

  const isAcknowledgePermitted = !defaultIsAcknowledgePermitted
    ? defaultIsAcknowledgePermitted
    : extraPermittedAcknowledge;

  const defaultIsDowntimePermitted =
    canDowntime(resources) || !hasSelectedResources;
  const isDowntimePermitted = !defaultIsDowntimePermitted
    ? defaultIsDowntimePermitted
    : extraPermittedDowntime;

  return (
    <div className={classes.flex}>
      <div className={classes.flex}>
        {displayAcknowledge && (
          <div className={classes.action}>
            <ResourceActionButton
              disabled={disableAcknowledge}
              icon={<IconAcknowledge />}
              label={t(labelAcknowledge)}
              permitted={isAcknowledgePermitted}
              testId="mainAcknowledge"
              onClick={prepareToAcknowledge}
            />
          </div>
        )}

        {displayDowntime && (
          <div className={classes.action}>
            <ResourceActionButton
              disabled={disableDowntime}
              icon={<IconDowntime />}
              label={t(labelSetDowntime)}
              permitted={isDowntimePermitted}
              testId="mainSetDowntime"
              onClick={prepareToSetDowntime}
            />
          </div>
        )}
        {displayCheck && (
          <div className={classes.action}>
            <CheckActionButton
              resources={resources}
              testId="mainCheck"
              {...extraCheckData}
            />
          </div>
        )}
      </div>
    </div>
  );
};

export default ResourceActions;
