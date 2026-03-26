import IconDisacknowledge from '@mui/icons-material/ConfirmationNumber';
import IconMore from '@mui/icons-material/MoreHoriz';
import IconAcknowledge from '@mui/icons-material/Person';

import { PopoverMenu, SeverityCode, useCancelTokenSource } from '@centreon/ui';

import { useAtom } from 'jotai';
import { all, equals, find, head, pathEq, propEq } from 'ramda';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import AddCommentForm from '../../Graph/Performance/Graph/AddCommentForm';
import Downtime from '../../icons/Downtime';
import { type Resource, ResourceType } from '../../models';
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
import {
  Action,
  type CheckActionModel,
  type ExtraActionsInformation,
  type MoreSecondaryActions,
  type ResourceActions
} from '../model';
import AcknowledgeForm from './Acknowledge';
import ActionMenuItem from './ActionMenuItem';
import useAclQuery from './aclQuery';
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

const ResourceActionsButtons = ({
  resources,
  success,
  mainActions,
  secondaryActions,
  displayCondensed = false,
  renderMoreSecondaryActions
}: ResourceActions & MoreSecondaryActions): JSX.Element => {
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
    arrayActions: mainActions.actions,
    key: Action.Acknowledge
  });

  const { displayCheck } = extractActionsInformation({
    arrayActions: mainActions.actions,
    key: Action.Check
  });

  const extraCheckData = (
    mainActions.actions.find(({ action }) =>
      equals(action, Action.Check)
    ) as CheckActionModel
  )?.data;

  const {
    displayDisacknowledge,
    extraDisabledDisacknowledge,
    extraPermittedDisacknowledge
  } = extractActionsInformation({
    arrayActions: mainActions.actions,
    key: Action.Disacknowledge
  });

  const { displayDowntime, extraDisabledDowntime, extraPermittedDowntime } =
    extractActionsInformation({
      arrayActions: mainActions.actions,
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
    setResourcesToAcknowledge([]);
    setResourcesToSetDowntime([]);
    setResourceToSubmitStatus(null);
    setResourcesToDisacknowledge([]);
    setResourceToComment(null);
    success?.();
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

  const disableAcknowledge =
    extraDisabledAcknowledge || defaultDisableAcknowledge;

  const defaultDisableDowntime = !canDowntime(resources);

  const disableDowntime = extraDisabledDowntime || defaultDisableDowntime;

  const defaultDisableDisacknowledge = !canDisacknowledge(resources);

  const disableDisacknowledge =
    extraDisabledDisacknowledge || defaultDisableDisacknowledge;

  const hasSelectedResources = resources.length > 0;
  const hasOneResourceSelected = resources.length === 1;
  const hasADResource = find(
    propEq(ResourceType.anomalyDetection, 'type'),
    resources
  );

  const disableSubmitStatus =
    !hasOneResourceSelected ||
    !canSubmitStatus(resources) ||
    !head(resources)?.has_passive_checks_enabled ||
    hasADResource;

  const disableAddComment = !hasOneResourceSelected || !canComment(resources);

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

  const defaultIsDisacknowledgePermitted =
    canDisacknowledge(resources) || !hasSelectedResources;

  const isDisacknowledgePermitted = !defaultIsDisacknowledgePermitted
    ? defaultIsDisacknowledgePermitted
    : extraPermittedDisacknowledge;

  const isSubmitStatusPermitted =
    canSubmitStatus(resources) || !hasSelectedResources;
  const isAddCommentPermitted = canComment(resources) || !hasSelectedResources;

  return (
    <div className={classes.flex}>
      <div className={cx(classes.flex, mainActions?.style)}>
        {displayAcknowledge && (
          <div className={classes.action}>
            <ResourceActionButton
              disabled={disableAcknowledge}
              displayCondensed={displayCondensed}
              icon={<IconAcknowledge />}
              label={t(labelAcknowledge)}
              onClick={prepareToAcknowledge}
              permitted={isAcknowledgePermitted}
              testId="mainAcknowledge"
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
              onClick={prepareToDisacknowledge}
              permitted={isDisacknowledgePermitted}
              testId="mainDisacknowledge"
            />
          </div>
        )}

        {displayDowntime && (
          <div className={classes.action}>
            <ResourceActionButton
              disabled={disableDowntime}
              displayCondensed={displayCondensed}
              icon={<Downtime />}
              label={t(labelSetDowntime)}
              onClick={prepareToSetDowntime}
              permitted={isDowntimePermitted}
              testId="mainSetDowntime"
            />
          </div>
        )}
        {displayCheck && (
          <div className={cx({ [classes.action]: !displayCondensed })}>
            <CheckActionButton
              displayCondensed={displayCondensed}
              resources={resources}
              testId="mainCheck"
              {...extraCheckData}
            />
          </div>
        )}
        {resourcesToAcknowledge.length > 0 && (
          <AcknowledgeForm
            onClose={cancelAcknowledge}
            onSuccess={confirmAction}
            resources={resourcesToAcknowledge}
          />
        )}
        {resourcesToSetDowntime.length > 0 && (
          <DowntimeForm
            onClose={cancelSetDowntime}
            onSuccess={confirmAction}
            resources={resourcesToSetDowntime}
          />
        )}
        {resourcesToDisacknowledge.length > 0 && (
          <DisacknowledgeForm
            onClose={cancelDisacknowledge}
            onSuccess={confirmAction}
            resources={resourcesToDisacknowledge}
          />
        )}
        {resourceToSubmitStatus && (
          <SubmitStatusForm
            onClose={cancelSubmitStatus}
            onSuccess={confirmAction}
            resource={resourceToSubmitStatus}
          />
        )}
        {resourceToComment && (
          <AddCommentForm
            date={new Date()}
            onClose={cancelComment}
            onSuccess={confirmAction}
            resource={resourceToComment as Resource}
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
                disabled={defaultDisableDisacknowledge}
                label={labelDisacknowledge}
                onClick={(): void => {
                  close();
                  prepareToDisacknowledge();
                }}
                permitted={defaultIsDisacknowledgePermitted}
                testId="Multiple Disacknowledge"
              />

              <ActionMenuItem
                disabled={disableSubmitStatus}
                label={labelSubmitStatus}
                onClick={(): void => {
                  close();
                  prepareToSubmitStatus();
                }}
                permitted={isSubmitStatusPermitted}
                testId="Submit a status"
              />

              <ActionMenuItem
                disabled={disableAddComment}
                label={labelAddComment}
                onClick={(): void => {
                  close();
                  prepareToAddComment();
                }}
                permitted={isAddCommentPermitted}
                testId="Add a comment"
              />
              {renderMoreSecondaryActions?.({ close })}
            </>
          )}
        </PopoverMenu>
      )}
    </div>
  );
};

export default ResourceActionsButtons;
