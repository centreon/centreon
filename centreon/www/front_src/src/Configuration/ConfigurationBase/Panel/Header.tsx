import {
  DeleteOutline as DeleteIcon,
  ContentCopyOutlined as DuplicateIcon,
  UndoOutlined as ResetIcon,
  SaveOutlined as SaveIcon
} from '@mui/icons-material';
import { Box, Tooltip, Typography } from '@mui/material';

import { IconButton, Image } from '@centreon/ui';
import { Switch } from '@centreon/ui/components';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNil } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { ResourceRow } from '../../models';
import {
  configurationAtom,
  formActionsAtom,
  formStateAtom,
  isResetConfirmationDialogOpenAtom
} from '../atoms';
import useActions from '../Listing/Columns/Actions/useActions';
import useStatus from '../Listing/Columns/Status/useStatus';
import {
  labelDelete,
  labelDisabled,
  labelDuplicate,
  labelEnableDisable,
  labelEnabled,
  labelReset,
  labelSave
} from '../translatedLabels';
import { panelDataTestIds } from './dataTestIds';

const EnableAction = ({ row }: { row: ResourceRow }): ReactElement => {
  const { t } = useTranslation();

  const { isMutating, change, checked } = useStatus({ row });

  return (
    <Tooltip title={checked ? t(labelEnabled) : t(labelDisabled)}>
      <Switch
        aria-label={t(labelEnableDisable)}
        checked={checked}
        color="primary"
        data-testid={panelDataTestIds.enable}
        disabled={isMutating}
        onClick={change}
        size="small"
      />
    </Tooltip>
  );
};

interface Props {
  fallbackTitle: string;
  loadedResource?: Record<string, unknown>;
}

const Header = ({ fallbackTitle, loadedResource }: Props): ReactElement => {
  const { t } = useTranslation();

  const configuration = useAtomValue(configurationAtom);
  const { id, mode, resource: openedResource } = useAtomValue(formStateAtom);
  const formActions = useAtomValue(formActionsAtom);
  const setIsResetConfirmationDialogOpen = useSetAtom(
    isResetConfirmationDialogOpenAtom
  );

  const isEditMode = equals(mode, 'edit');

  // Never merged: `useFetchQuery` keeps the last payload it loaded, so a detail
  // response can still describe the previously opened resource, and an action
  // would carry one resource's id under another's name.
  const resource = (openedResource ?? loadedResource) as
    | Record<string, unknown>
    | undefined;

  const row = { ...resource, id } as ResourceRow;

  const title = (resource?.name as string) || fallbackTitle;

  // `Image` renders nothing without a path: a resource with no icon, or a
  // module whose resources have none, simply shows its name.
  const icon = resource?.icon as { name: string; url: string } | null;

  const { canDelete, canDuplicate, openDeleteModal, openDuplicateModal } =
    useActions(row);

  const canEnableDisable =
    isEditMode &&
    !!configuration?.actions?.enableDisable?.(row) &&
    !isNil(resource?.isActivated);

  return (
    <Box className="flex min-w-0 items-center gap-2 py-1">
      <Image
        alt={icon?.name as string}
        className="size-5 shrink-0"
        fallback={<Box />}
        imagePath={icon?.url as string}
      />
      <Typography
        className="truncate"
        data-testid={panelDataTestIds.header}
        variant="h6"
      >
        {title}
      </Typography>
      <Box className="ml-auto flex items-center gap-1">
        {canEnableDisable && <EnableAction row={row} />}
        {isEditMode && formActions && (
          <IconButton
            ariaLabel={t(labelReset)}
            dataTestid={panelDataTestIds.reset}
            disabled={!formActions.canReset}
            onClick={() => setIsResetConfirmationDialogOpen(true)}
            title={t(labelReset)}
          >
            <ResetIcon />
          </IconButton>
        )}
        {isEditMode && canDuplicate && (
          <IconButton
            ariaLabel={t(labelDuplicate)}
            dataTestid={panelDataTestIds.duplicate}
            onClick={openDuplicateModal}
            title={t(labelDuplicate)}
          >
            <DuplicateIcon />
          </IconButton>
        )}
        {formActions && (
          <IconButton
            ariaLabel={t(labelSave)}
            dataTestid={panelDataTestIds.save}
            disabled={!formActions.canSubmit}
            onClick={formActions.submit}
            title={t(labelSave)}
          >
            <SaveIcon color={formActions.canSubmit ? 'primary' : 'disabled'} />
          </IconButton>
        )}
        {isEditMode && canDelete && (
          <IconButton
            ariaLabel={t(labelDelete)}
            dataTestid={panelDataTestIds.delete}
            onClick={openDeleteModal}
            title={t(labelDelete)}
          >
            <DeleteIcon color="error" />
          </IconButton>
        )}
      </Box>
    </Box>
  );
};

export default Header;
