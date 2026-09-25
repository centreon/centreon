import {
  DeleteOutline as DeleteIcon,
  ContentCopyOutlined as DuplicateIcon,
  UndoOutlined as ResetIcon,
  SaveOutlined as SaveIcon
} from '@mui/icons-material';
import { Box, Tooltip, Typography } from '@mui/material';

import { IconButton } from '@centreon/ui';
import { Switch } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { ResourceRow } from '../../models';
import { configurationAtom, formActionsAtom, formStateAtom } from '../atoms';
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

// Own component so that the enable/disable request is only wired up for the
// modules that offer the action.
const EnableAction = ({ row }: { row: ResourceRow }): JSX.Element => {
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

// The panel carries the form's actions in its header, as the mock has them:
// enable · reset · duplicate · save · delete, then the close the panel adds.
const Header = ({ fallbackTitle, loadedResource }: Props): JSX.Element => {
  const { t } = useTranslation();

  const configuration = useAtomValue(configurationAtom);
  const { id, mode, resource: openedResource } = useAtomValue(formStateAtom);
  const formActions = useAtomValue(formActionsAtom);

  const isEditMode = equals(mode, 'edit');

  // The listing row carries name and state before the detail endpoint answers,
  // and a deep link carries neither; what is loaded wins over what was listed.
  const resource = { ...openedResource, ...loadedResource };

  const row = { ...resource, id } as ResourceRow;

  // The mock names the panel after the resource it holds.
  const title = (resource?.name as string) || fallbackTitle;

  const { canDelete, canDuplicate, openDeleteModal, openDuplicateModal } =
    useActions(row);

  // The toggle reflects a state only a loaded resource has.
  const canEnableDisable =
    isEditMode &&
    !!configuration?.actions?.enableDisable?.(row) &&
    !isNil(resource?.isActivated);

  return (
    <Box className="flex min-w-0 items-center gap-2 py-1">
      <Typography
        className="truncate"
        data-testid={panelDataTestIds.header}
        variant="h6"
      >
        {title}
      </Typography>
      <Box className="ml-auto flex items-center gap-1">
        {canEnableDisable && <EnableAction row={row} />}
        {formActions && (
          <IconButton
            ariaLabel={t(labelReset)}
            dataTestid={panelDataTestIds.reset}
            disabled={!formActions.canReset}
            onClick={formActions.reset}
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
