// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import DvrIcon from '@mui/icons-material/Dvr';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';

import { IconButton } from '@centreon/ui';

import { useAtom, useSetAtom } from 'jotai';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { duplicatePanelDerivedAtom, isEditingAtom } from '../../atoms';
import useResetDashboardFromSavedState from '../../hooks/useResetDashboardFromSavedState';
import {
  labelMoreActions,
  labelResourcesStatus,
  labelSeeMore
} from '../../translatedLabels';
import MorePanelActions from './MorePanelActions';
import { ExpandableData } from './models';

interface Props {
  changeViewMode?: () => void;
  expandableData?: ExpandableData;
  id: string;
  linkToResourceStatus?: string;
  pageType?: string | null;
  setRefreshCount?: (id: string) => void;
}

/**
 * Single "more actions" trigger used by every widget (titled or not),
 * rendered as a floating overlay (see Item.tsx's overlayActions) instead of
 * inside the widget's CardHeader, so the interaction is identical
 * regardless of whether the widget has a title.
 */
const PanelMoreActionsButton = ({
  id,
  expandableData,
  linkToResourceStatus,
  changeViewMode,
  pageType,
  setRefreshCount
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const duplicatePanel = useSetAtom(duplicatePanelDerivedAtom);
  const [isEditing, setIsEditing] = useAtom(isEditingAtom);
  const resetDashboardFromSavedState = useResetDashboardFromSavedState();

  const duplicate = (event: MouseEvent): void => {
    event.preventDefault();
    if (!isEditing) {
      resetDashboardFromSavedState();
    }
    setIsEditing(() => true);
    duplicatePanel(id);
  };

  const openMoreActions = (event: React.MouseEvent): void =>
    setMoreActionsOpen(event.target as never);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const page = t(pageType || labelResourcesStatus);

  const openResourceStatus = (): void => {
    changeViewMode?.();
    window.open(linkToResourceStatus, '_blank', 'noopener,noreferrer');
    closeMoreActions();
  };

  const resourceStatusAction = linkToResourceStatus
    ? {
        Icon: DvrIcon,
        label: t(labelSeeMore, { page }),
        onClick: openResourceStatus
      }
    : undefined;

  const refresh = (): void => setRefreshCount?.(id);

  return (
    <>
      <IconButton
        ariaLabel={t(labelMoreActions) as string}
        onClick={openMoreActions}
        title={t(labelMoreActions) as string}
      >
        <MoreHorizIcon fontSize="small" />
      </IconButton>
      <MorePanelActions
        anchor={moreActionsOpen}
        close={closeMoreActions}
        duplicate={duplicate}
        expandableData={expandableData}
        id={id}
        onRefresh={setRefreshCount ? refresh : undefined}
        resourceStatusAction={resourceStatusAction}
      />
    </>
  );
};

export default PanelMoreActionsButton;
