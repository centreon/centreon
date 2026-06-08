// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import {
  Method,
  SeverityCode,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { useAtomValue, useSetAtom } from 'jotai';
import { all, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  resourcesToAcknowledgeAtom,
  resourcesToSetDowntimeAtom,
  selectedResourcesAtom
} from '../../atom';
import {
  labelAcknowledge,
  labelAcknowledgeDescription,
  labelCheck,
  labelCheckCommandSent,
  labelCheckDescription,
  labelForcedCheck,
  labelForcedCheckCommandSent,
  labelForcedCheckDescription,
  labelSetDowntime,
  labelSetDowntimeDescription
} from '../translatedLabels';
import useAclQuery from './aclQuery';
import { checkEndpoint } from './api/endpoint';
import { adjustCheckedResources } from './Check/helpers';
import ResourceActionsMenuButton from './ResourceActionsMenuButton';

const useStyles = makeStyles()({
  flex: {
    alignItems: 'center',
    display: 'flex'
  }
});

const ResourceActions = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [resources, setSelectedResources] = useAtom(selectedResourcesAtom);
  const setResourcesToAcknowledge = useSetAtom(resourcesToAcknowledgeAtom);
  const setResourcesToSetDowntime = useSetAtom(resourcesToSetDowntimeAtom);

  const { canAcknowledge, canCheck, canDowntime, canForcedCheck } =
    useAclQuery();

  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => checkEndpoint,
    method: Method.POST
  });

  const prepareToAcknowledge = (): void => {
    setResourcesToAcknowledge(resources);
  };

  const prepareToSetDowntime = (): void => {
    setResourcesToSetDowntime(resources);
  };

  const checkResources = (isForced: boolean): void => {
    checkResource({
      payload: {
        check: { is_forced: isForced },
        resources: adjustCheckedResources({ resources })
      }
    }).then(() => {
      showSuccessMessage(
        t(isForced ? labelForcedCheckCommandSent : labelCheckCommandSent)
      );
    });
  };

  const areSelectedResourcesOk = all(
    pathEq(SeverityCode.OK, ['status', 'severity_code']),
    resources
  );

  const hasSelectedResources = resources.length > 0;

  const disableAcknowledge =
    !canAcknowledge(resources) || areSelectedResourcesOk;
  const disableDowntime = !canDowntime(resources);
  const disableCheck = !canCheck(resources);
  const disableForcedCheck = !canForcedCheck(resources);

  const isAcknowledgePermitted =
    canAcknowledge(resources) || !hasSelectedResources;
  const isDowntimePermitted = canDowntime(resources) || !hasSelectedResources;
  const isCheckPermitted = canCheck(resources) || !hasSelectedResources;
  const isForcedCheckPermitted =
    canForcedCheck(resources) || !hasSelectedResources;

  const actions = [
    {
      description: labelAcknowledgeDescription,
      disabled: disableAcknowledge,
      label: labelAcknowledge,
      onClick: prepareToAcknowledge,
      permitted: isAcknowledgePermitted,
      testId: 'mainAcknowledge'
    },
    {
      description: labelSetDowntimeDescription,
      disabled: disableDowntime,
      label: labelSetDowntime,
      onClick: prepareToSetDowntime,
      permitted: isDowntimePermitted,
      testId: 'mainSetDowntime'
    },
    {
      description: labelCheckDescription,
      disabled: disableCheck,
      label: labelCheck,
      onClick: () => checkResources(false),
      permitted: isCheckPermitted,
      testId: 'mainCheck'
    },
    {
      description: labelForcedCheckDescription,
      disabled: disableForcedCheck,
      label: labelForcedCheck,
      onClick: () => checkResources(true),
      permitted: isForcedCheckPermitted,
      testId: 'mainForcedCheck'
    }
  ];

  return (
    <div className={classes.flex}>
      <ResourceActionsMenuButton actionGroups={[actions]} />
    </div>
  );
};

export default ResourceActions;
