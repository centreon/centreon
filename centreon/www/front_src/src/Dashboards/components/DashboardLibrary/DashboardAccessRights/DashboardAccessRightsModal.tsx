import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { AccessRights, Modal } from '@centreon/ui/components';

import {
  dashboardsContactGroupsEndpoint,
  dashboardsContactsEndpoint
} from '../../../api/endpoints';
import { DashboardRole } from '../../../api/models';
import { useUpdateAccessRights } from '../../../api/useUpdateAccessRights';
import {
  labelAddAContact,
  labelAddAContactGroup,
  labelAdded,
  labelCancel,
  labelContact,
  labelContactGroup,
  labelCopyLink,
  labelEditAccessRights,
  labelEditor,
  labelFailedToCopyLink,
  labelGroup,
  labelLinkCopied,
  labelRemoved,
  labelSave,
  labelShareWith,
  labelTheShareListIsEmpty,
  labelUpdated,
  labelUserRights,
  labelViewer
} from '../../../translatedLabels';

import { useDashboardAccessRights } from './useDashboardAccessRights';

const labels = {
  actions: {
    cancel: labelCancel,
    copyError: labelFailedToCopyLink,
    copyLink: labelCopyLink,
    copySuccess: labelLinkCopied,
    save: labelSave
  },
  add: {
    autocompleteContact: labelAddAContact,
    autocompleteContactGroup: labelAddAContactGroup,
    contact: labelContact,
    contactGroup: labelContactGroup,
    title: labelShareWith
  },
  list: {
    added: labelAdded,
    empty: labelTheShareListIsEmpty,
    group: labelGroup,
    removed: labelRemoved,
    title: labelUserRights,
    updated: labelUpdated
  }
};

const DashboardAccessRightsModal = (): ReactElement => {
  const { t } = useTranslation();

  const { close, modalOpen, shares, link, dashboardId } =
    useDashboardAccessRights();
  const { updateAccessRights, isMutating } = useUpdateAccessRights({
    close,
    dashboardId
  });

  return (
    <Modal open={modalOpen} size="medium" onClose={close}>
      <Modal.Header>{t(labelEditAccessRights)}</Modal.Header>
      <Modal.Body>
        <AccessRights
          cancel={close}
          endpoints={{
            contact: dashboardsContactsEndpoint,
            contactGroup: dashboardsContactGroupsEndpoint
          }}
          initialValues={shares}
          isSubmitting={isMutating}
          labels={labels}
          link={link}
          roles={[
            {
              id: DashboardRole.viewer,
              name: labelViewer
            },
            {
              id: DashboardRole.editor,
              name: labelEditor
            }
          ]}
          submit={updateAccessRights}
        />
      </Modal.Body>
    </Modal>
  );
};

export { DashboardAccessRightsModal };
