import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import {
  AccessRightsForm,
  AccessRightsFormLabels,
  AccessRightsFormProps,
  Modal
} from '@centreon/ui/components';

import {
  labelCancel,
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
          copyLink: 'copy link',
          copyLinkMessages: {
            error: 'error',
            success: 'link copied'
          },
          submit: t(labelUpdate)
        },
        input: {
          fields: {
            contact: {
              labels: {
                // FIXME
                group: 'group',
                noOptionsText: 'no options',
                placeholder: 'placeholder'
              }
            }
          }
        },
        list: {
          emptyState: 'empty state',
          item: {
            group: 'group',
            state: {
              added: 'added',
              removed: 'removed',
              updated: 'updated'
            }
          }
        },
        stats: {
          added: 'added',
          removed: 'removed',
          updated: 'updated'
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
