import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { FormikValues, useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { Box } from '@mui/material';
import SaveIcon from '@mui/icons-material/SaveOutlined';

import {
  ConfirmDialog,
  IconButton,
  useMutationQuery,
  Method
} from '@centreon/ui';

import { EditedNotificationIdAtom, panelModeAtom } from '../../atom';
import { isPanelOpenAtom } from '../../../atom';
import { labelSave } from '../../../translatedLabels';
import { notificationtEndpoint } from '../../api/endpoints';
import { PanelMode } from '../../models';
import { adaptNotifications } from '../../api/adapters';

const useStyle = makeStyles()((theme) => ({
  icon: {
    fontSize: theme.spacing(2.75)
  }
}));

const SaveAction = ({ isValid }: { isValid: boolean }): JSX.Element => {
  const { classes } = useStyle();
  const { t } = useTranslation();

  const [dialogOpen, setDialogOpen] = useState(false);
  const panelMode = useAtomValue(panelModeAtom);
  const editedNotificationId = useAtomValue(EditedNotificationIdAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const { values } = useFormikContext<FormikValues>();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(panelMode, PanelMode.Create)
        ? notificationtEndpoint({})
        : notificationtEndpoint({ id: editedNotificationId }),
    method: equals(panelMode, PanelMode.Create) ? Method.POST : Method.PUT
  });

  const onClick = (): void => {
    setDialogOpen(true);
  };

  const onCancel = (): void => {
    setDialogOpen(false);
  };

  const onConfirm = (): void => {
    mutateAsync(adaptNotifications(values)).then(() => {
      setDialogOpen(false);
      setPanelOpen(false);
    });
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelSave)}
        disabled={!isValid}
        title={t(labelSave)}
        onClick={onClick}
      >
        <SaveIcon
          className={classes.icon}
          color={isValid ? 'primary' : 'disabled'}
        />
      </IconButton>
      <ConfirmDialog
        open={dialogOpen}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default SaveAction;
