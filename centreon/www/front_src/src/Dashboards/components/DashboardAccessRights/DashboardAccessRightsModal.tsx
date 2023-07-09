import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import {
  AccessRightsForm,
  AccessRightsFormLabels,
  AccessRightsFormProps,
  Modal
} from '@centreon/ui/components';

import {
  labelAccessRightsListEmptyState,
  labelAccessRightsStatsAdded,
  labelAccessRightsStatsRemoved,
  labelAccessRightsStatsUpdated,
  labelAccessRightStateAdded,
  labelAccessRightStateRemoved,
  labelAccessRightStateUpdated,
  labelAdd,
  labelCancel,
  labelContactGroupTag,
  labelContactNoOptionsText,
  labelContactPlaceholder,
  labelCopyLinkToDashboard,
  labelCopyLinkToDashboardError,
  labelCopyLinkToDashboardSuccess,
  labelEditAccessRights,
  labelUpdate
} from '../../translatedLabels';

import { useDashboardAccessRights } from './useDashboardAccessRights';

const DashboardAccessRightsModal = (): ReactElement => {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const { isDialogOpen, closeDialog, resourceLink, dashboard, submit } =
    useDashboardAccessRights();

  const { t } = useTranslation();

  const options: AccessRightsFormProps['options'] = {
    contacts: [
      {
        id: 1,
        name: 'contact1'
      },
      {
        id: 2,
        name: 'contact2'
      }
    ],
    roles: [
      {
        role: 'viewer' // FIXME name ? + allow other roles ?
      },
      {
        role: 'editor'
      }
    ]
  };

  const labels = useMemo(
    (): {
      form: AccessRightsFormLabels;
      modalTitle: string;
    } => ({
      form: {
        actions: {
          cancel: t(labelCancel),
          copyLink: t(labelCopyLinkToDashboard),
          copyLinkMessages: {
            error: t(labelCopyLinkToDashboardError),
            success: t(labelCopyLinkToDashboardSuccess)
          },
          submit: t(labelUpdate)
        },
        input: {
          actions: {
            add: t(labelAdd)
          },
          fields: {
            contact: {
              group: t(labelContactGroupTag),
              noOptionsText: t(labelContactNoOptionsText),
              placeholder: t(labelContactPlaceholder)
            }
          }
        },
        list: {
          emptyState: t(labelAccessRightsListEmptyState),
          item: {
            group: t(labelContactGroupTag),
            state: {
              added: t(labelAccessRightStateAdded),
              removed: t(labelAccessRightStateRemoved),
              updated: t(labelAccessRightStateUpdated)
            }
          }
        },
        stats: {
          added: t(labelAccessRightsStatsAdded),
          removed: t(labelAccessRightsStatsRemoved),
          updated: t(labelAccessRightsStatsUpdated)
        }
      },
      modalTitle: t(labelEditAccessRights)
    }),
    []
  );

  return (
    <Modal open={isDialogOpen} size="medium" onClose={closeDialog}>
      <Modal.Header>{labels.modalTitle}</Modal.Header>
      <Modal.Body>
        <AccessRightsForm
          labels={labels.form}
          options={options}
          resourceLink={resourceLink}
          onCancel={closeDialog}
        />
      </Modal.Body>
    </Modal>
  );
};

export { DashboardAccessRightsModal };
