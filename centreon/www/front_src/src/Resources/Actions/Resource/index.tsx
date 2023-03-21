import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';
import { all, head, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import IconMore from '@mui/icons-material/MoreHoriz';
import IconAcknowledge from '@mui/icons-material/Person';

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
  resourcesToSetDowntimeAtom,
  selectedResourcesAtom
} from '../actionsAtoms';

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

const ResourceActions = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { cancel } = useCancelTokenSource();

  const [resourceToSubmitStatus, setResourceToSubmitStatus] =
    useState<Resource | null>();
  const [resourceToComment, setResourceToComment] = useState<Resource | null>();

  const [selectedResources, setSelectedResources] = useAtom(
    selectedResourcesAtom
  );
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
    setSelectedResources([]);
    setResourcesToAcknowledge([]);
    setResourcesToSetDowntime([]);
    setResourceToSubmitStatus(null);
    setResourcesToDisacknowledge([]);
    setResourceToComment(null);
  };

  useEffect(() => (): void => cancel(), []);

  const prepareToAcknowledge = (): void => {
    setResourcesToAcknowledge(selectedResources);
  };

  const prepareToSetDowntime = (): void => {
    setResourcesToSetDowntime(selectedResources);
  };

  const cancelAcknowledge = (): void => {
    setResourcesToAcknowledge([]);
  };

  const cancelSetDowntime = (): void => {
    setResourcesToSetDowntime([]);
  };

  const prepareToDisacknowledge = (): void => {
    setResourcesToDisacknowledge(selectedResources);
  };

  const cancelDisacknowledge = (): void => {
    setResourcesToDisacknowledge([]);
  };

  const prepareToSubmitStatus = (): void => {
    const [selectedResource] = selectedResources;

    setResourceToSubmitStatus(selectedResource);
  };

  const cancelSubmitStatus = (): void => {
    setResourceToSubmitStatus(null);
  };

  const prepareToAddComment = (): void => {
    const [selectedResource] = selectedResources;

    setResourceToComment(selectedResource);
  };

  const cancelComment = (): void => {
    setResourceToComment(null);
  };

  const areSelectedResourcesOk = all(
    pathEq(['status', 'severity_code'], SeverityCode.Ok),
    selectedResources
  );

  const disableAcknowledge =
    !canAcknowledge(selectedResources) || areSelectedResourcesOk;
  const disableDowntime = !canDowntime(selectedResources);
  const disableDisacknowledge = !canDisacknowledge(selectedResources);

  const hasSelectedResources = selectedResources.length > 0;
  const hasOneResourceSelected = selectedResources.length === 1;

  const disableSubmitStatus =
    !hasOneResourceSelected ||
    !canSubmitStatus(selectedResources) ||
    !head(selectedResources)?.passive_checks;

  const disableAddComment =
    !hasOneResourceSelected || !canComment(selectedResources);

  const isAcknowledgePermitted =
    canAcknowledge(selectedResources) || !hasSelectedResources;
  const isDowntimePermitted =
    canDowntime(selectedResources) || !hasSelectedResources;
  const isDisacknowledgePermitted =
    canDisacknowledge(selectedResources) || !hasSelectedResources;
  const isSubmitStatusPermitted =
    canSubmitStatus(selectedResources) || !hasSelectedResources;
  const isAddCommentPermitted =
    canComment(selectedResources) || !hasSelectedResources;

  return (
    <div className={classes.flex}>
      <div className={classes.flex}>
        <div className={classes.action}>
          <ResourceActionButton
            disabled={disableAcknowledge}
            icon={<IconAcknowledge />}
            label={t(labelAcknowledge)}
            permitted={isAcknowledgePermitted}
            onClick={prepareToAcknowledge}
          />
        </div>
        <div className={classes.action}>
          <ResourceActionButton
            disabled={disableDowntime}
            icon={<IconDowntime />}
            label={t(labelSetDowntime)}
            permitted={isDowntimePermitted}
            onClick={prepareToSetDowntime}
          />
        </div>
        <div className={classes.action}>
          <CheckActionButton
            selectedResources={selectedResources}
            setSelectedResources={setSelectedResources}
          />
        </div>
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
              onClick={(): void => {
                close();
                prepareToDisacknowledge();
              }}
            />
            <ActionMenuItem
              disabled={disableSubmitStatus}
              label={labelSubmitStatus}
              permitted={isSubmitStatusPermitted}
              onClick={(): void => {
                close();
                prepareToSubmitStatus();
              }}
            />

            <ActionMenuItem
              disabled={disableAddComment}
              label={labelAddComment}
              permitted={isAddCommentPermitted}
              onClick={(): void => {
                close();
                prepareToAddComment();
              }}
            />
          </>
        )}
      </PopoverMenu>
    </div>
  );
};

export default ResourceActions;
