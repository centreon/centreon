import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';
import { all, head, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconMore from '@mui/icons-material/MoreHoriz';
import IconAcknowledge from '@mui/icons-material/Person';
import IconDisacknowledge from '@mui/icons-material/ConfirmationNumber';

import { PopoverMenu, SeverityCode, useCancelTokenSource } from '@centreon/ui';

import AddCommentForm from '../../Graph/Performance/Graph/AddCommentForm';
import IconDowntime from '../../icons/Downtime';
import { Resource } from '../../models';
import {
  labelAcknowledge,
  labelAddComment,
  labelDisacknowledge,
  labelMoreActions,
  labelSetDowntime,
  labelSubmitStatus
} from '../../translatedLabels';
import {
  resourcesToAcknowledgeAtom,
  resourcesToDisacknowledgeAtom,
  resourcesToSetDowntimeAtom
} from '../actionsAtoms';
import { Action, ExtraActionsInformation, ResourceActions } from '../model';

import AcknowledgeForm from './Acknowledge';
import useAclQuery from './aclQuery';
import ActionMenuItem from './ActionMenuItem';
import CheckActionButton from './Check';
import DisacknowledgeForm from './Disacknowledge';
import DowntimeForm from './Downtime';
import ResourceActionButton from './ResourceActionButton';
import SubmitStatusForm from './SubmitStatus';

const useStyles = makeStyles()((theme) => ({
  action: {
    marginRight: theme.spacing(1)
  },
  flex: {
    alignItems: 'center',
    display: 'flex'
  }
}));

const ResourceActions = ({
  resources,
  initialize,
  mainActions,
  secondaryActions,
  displayCondensed = false,
  mainActionsStyle
}: ResourceActions): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const { cancel } = useCancelTokenSource();

  const extractActionsInformation = ({
    key,
    arrayActions
  }: ExtraActionsInformation):
    | Record<string, boolean | undefined>
    | Record<string, never> => {
    const item = arrayActions.find(({ action }) => action === key);

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
  } = extractActionsInformation({
    arrayActions: mainActions,
    key: Action.Acknowledge
  });
  const { displayCheck } = extractActionsInformation({
    arrayActions: mainActions,
    key: Action.Check
  });

  console.log({
    extraPermittedAcknowledge,
    mainActions,
    res: extractActionsInformation({
      arrayActions: mainActions,
      key: Action.Acknowledge
    })
  });
  const {
    displayDisacknowledge,
    extraDisabledDisacknowledge,
    extraPermittedDisacknowledge
  } = extractActionsInformation({
    arrayActions: mainActions,
    key: Action.Disacknowledge
  });

  const { displayDowntime, extraDisabledDownTime, extraPermittedDownTime } =
    extractActionsInformation({
      arrayActions: mainActions,
      key: Action.Downtime
    });

  const moreActions = secondaryActions?.length > 0;

  const [resourceToSubmitStatus, setResourceToSubmitStatus] =
    useState<Resource | null>();
  const [resourceToComment, setResourceToComment] = useState<Resource | null>();

  const [resourcesToAcknowledge, setResourcesToAcknowledge] = useAtom(
    resourcesToAcknowledgeAtom
  );
  const [resourcesToSetDowntime, setResourcesToSetDowntime] = useAtom(
    resourcesToSetDowntimeAtom
  );
  const [resourcesToDisacknowledge, setResourcesToDisacknowledge] = useAtom(
    resourcesToDisacknowledgeAtom
  );

  const {
    canAcknowledge,
    canDowntime,
    canDisacknowledge,
    canSubmitStatus,
    canComment
  } = useAclQuery();

  const confirmAction = (): void => {
    initialize();
    setResourcesToAcknowledge([]);
    setResourcesToSetDowntime([]);
    setResourceToSubmitStatus(null);
    setResourcesToDisacknowledge([]);
    setResourceToComment(null);
  };

  useEffect(() => (): void => cancel(), []);

  const prepareToAcknowledge = (): void => {
    setResourcesToAcknowledge(resources);
  };

  const prepareToSetDowntime = (): void => {
    setResourcesToSetDowntime(resources);
  };

  const cancelAcknowledge = (): void => {
    setResourcesToAcknowledge([]);
  };

  const cancelSetDowntime = (): void => {
    setResourcesToSetDowntime([]);
  };

  const prepareToDisacknowledge = (): void => {
    setResourcesToDisacknowledge(resources);
  };

  const cancelDisacknowledge = (): void => {
    setResourcesToDisacknowledge([]);
  };

  const prepareToSubmitStatus = (): void => {
    const [selectedResource] = resources;

    setResourceToSubmitStatus(selectedResource);
  };

  const cancelSubmitStatus = (): void => {
    setResourceToSubmitStatus(null);
  };

  const prepareToAddComment = (): void => {
    const [selectedResource] = resources;

    setResourceToComment(selectedResource);
  };

  const cancelComment = (): void => {
    setResourceToComment(null);
  };

  const areSelectedResourcesOk = all(
    pathEq(SeverityCode.OK, ['status', 'severity_code']),
    resources
  );

  const defaultDisableAcknowledge =
    !canAcknowledge(resources) || areSelectedResourcesOk;

  const disableAcknowledge = extraDisabledAcknowledge
    ? extraDisabledAcknowledge && defaultDisableAcknowledge
    : defaultDisableAcknowledge;

  const defaultDisableDowntime = !canDowntime(resources);

  const disableDowntime = extraDisabledDownTime
    ? defaultDisableDowntime && extraDisabledDownTime
    : defaultDisableDowntime;

  const defaultDisableDisacknowledge = !canDisacknowledge(resources);

  const disableDisacknowledge = extraDisabledDisacknowledge
    ? defaultDisableDisacknowledge && extraDisabledDisacknowledge
    : defaultDisableDisacknowledge;

  const hasSelectedResources = resources.length > 0;
  const hasOneResourceSelected = resources.length === 1;

  const disableSubmitStatus =
    !hasOneResourceSelected ||
    !canSubmitStatus(resources) ||
    !head(resources)?.has_passive_checks_enabled;

  const disableAddComment = !hasOneResourceSelected || !canComment(resources);

  const defaultIsAcknowledgePermitted =
    canAcknowledge(resources) || !hasSelectedResources;

  const isAcknowledgePermitted = extraPermittedAcknowledge
    ? extraPermittedAcknowledge && defaultIsAcknowledgePermitted
    : defaultIsAcknowledgePermitted;
  const defaultIsDowntimePermitted =
    canDowntime(resources) || !hasSelectedResources;
  const isDowntimePermitted = extraPermittedDownTime
    ? extraPermittedDownTime && defaultIsDowntimePermitted
    : defaultIsDowntimePermitted;

  const defaultIsDisacknowledgePermitted =
    canDisacknowledge(resources) || !hasSelectedResources;

  const isDisacknowledgePermitted = extraPermittedDisacknowledge
    ? extraPermittedDisacknowledge && defaultIsDisacknowledgePermitted
    : defaultIsDisacknowledgePermitted;

  const isSubmitStatusPermitted =
    canSubmitStatus(resources) || !hasSelectedResources;
  const isAddCommentPermitted = canComment(resources) || !hasSelectedResources;

  return (
    <div className={classes.flex}>
      <div className={cx(classes.flex, mainActionsStyle)}>
        {displayAcknowledge && (
          <div className={classes.action}>
            <ResourceActionButton
              disabled={disableAcknowledge}
              displayCondensed={displayCondensed}
              icon={<IconAcknowledge />}
              label={t(labelAcknowledge)}
              permitted={isAcknowledgePermitted}
              testId="Multiple Acknowledge"
              onClick={prepareToAcknowledge}
            />
          </div>
        )}
        {displayDisacknowledge && (
          <div className={classes.action}>
            <ResourceActionButton
              disabled={disableDisacknowledge}
              displayCondensed={displayCondensed}
              icon={<IconDisacknowledge />}
              label={t(labelDisacknowledge)}
              permitted={isDisacknowledgePermitted}
              testId="main disacknowledge"
              onClick={prepareToDisacknowledge}
            />
          </div>
        )}

        {displayDowntime && (
          <div className={classes.action}>
            <ResourceActionButton
              disabled={disableDowntime}
              displayCondensed={displayCondensed}
              icon={<IconDowntime />}
              label={t(labelSetDowntime)}
              permitted={isDowntimePermitted}
              testId="Multiple Set Downtime"
              onClick={prepareToSetDowntime}
            />
          </div>
        )}
        {displayCheck && (
          <div className={classes.action}>
            <CheckActionButton
              displayCondensed={displayCondensed}
              initialize={initialize}
              resources={resources}
              testId="Multiple Check"
            />
          </div>
        )}
        {resourcesToAcknowledge.length > 0 && (
          <AcknowledgeForm
            resources={resourcesToAcknowledge}
            onClose={cancelAcknowledge}
            onSuccess={confirmAction}
          />
        )}
        {resourcesToSetDowntime.length > 0 && (
          <DowntimeForm
            resources={resourcesToSetDowntime}
            onClose={cancelSetDowntime}
            onSuccess={confirmAction}
          />
        )}
        {resourcesToDisacknowledge.length > 0 && (
          <DisacknowledgeForm
            resources={resourcesToDisacknowledge}
            onClose={cancelDisacknowledge}
            onSuccess={confirmAction}
          />
        )}
        {resourceToSubmitStatus && (
          <SubmitStatusForm
            resource={resourceToSubmitStatus}
            onClose={cancelSubmitStatus}
            onSuccess={confirmAction}
          />
        )}
        {resourceToComment && (
          <AddCommentForm
            date={new Date()}
            resource={resourceToComment as Resource}
            onClose={cancelComment}
            onSuccess={confirmAction}
          />
        )}
      </div>

      {moreActions && (
        <PopoverMenu
          icon={<IconMore color="primary" fontSize="small" />}
          title={t(labelMoreActions) as string}
        >
          {({ close }): JSX.Element => (
            <>
              <ActionMenuItem
                disabled={disableDisacknowledge}
                label={labelDisacknowledge}
                permitted={isDisacknowledgePermitted}
                testId="Multiple Disacknowledge"
                onClick={(): void => {
                  close();
                  prepareToDisacknowledge();
                }}
              />

              <ActionMenuItem
                disabled={disableSubmitStatus}
                label={labelSubmitStatus}
                permitted={isSubmitStatusPermitted}
                testId="Submit a status"
                onClick={(): void => {
                  close();
                  prepareToSubmitStatus();
                }}
              />

              <ActionMenuItem
                disabled={disableAddComment}
                label={labelAddComment}
                permitted={isAddCommentPermitted}
                testId="Add a comment"
                onClick={(): void => {
                  close();
                  prepareToAddComment();
                }}
              />
            </>
          )}
        </PopoverMenu>
      )}
    </div>
  );
};

export default ResourceActions;
