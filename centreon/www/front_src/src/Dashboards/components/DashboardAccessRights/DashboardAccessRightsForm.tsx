import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import {
  AccessRightsForm,
  AccessRightsFormLabels
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
  labelUpdate
} from '../../translatedLabels';

import { useDashboardAccessRights } from './useDashboardAccessRights';

const DashboardAccessRightsForm = (): ReactElement => {
  const { closeDialog, resourceLink, options, initialAccessRights } =
    useDashboardAccessRights();

  const { t } = useTranslation();

  const labels = useMemo(
    (): AccessRightsFormLabels => ({
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
    }),
    []
  );

  return (
    <AccessRightsForm
      initialValues={initialAccessRights}
      labels={labels}
      options={options}
      resourceLink={resourceLink}
      onCancel={closeDialog}
    />
  );
};

export { DashboardAccessRightsForm };
