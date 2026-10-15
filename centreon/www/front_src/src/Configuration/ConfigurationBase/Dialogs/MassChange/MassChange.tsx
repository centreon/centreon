import { Typography } from '@mui/material';

import { Button, Modal } from '@centreon/ui/components';

import { useAtom, useAtomValue } from 'jotai';
import pluralize from 'pluralize';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import type { MassChangeForm } from '../../../models';
import {
  configurationAtom,
  formActionsAtom,
  formStateAtom,
  isMassChangeConfirmationDialogOpenAtom
} from '../../atoms';
import {
  labelApply,
  labelApplyChangesTo,
  labelCancel,
  labelMassChange
} from '../../translatedLabels';

interface Props {
  getWarning?: MassChangeForm['getConfirmationWarning'];
}

// States the count and names the rows before anything is written: a check-all
// selects the whole page, deactivated rows included.
const MassChangeDialog = ({ getWarning }: Props): ReactElement => {
  const { t } = useTranslation();

  const [isOpened, setIsOpened] = useAtom(
    isMassChangeConfirmationDialogOpenAtom
  );
  const formActions = useAtomValue(formActionsAtom);
  const { selection = [] } = useAtomValue(formStateAtom);
  const configuration = useAtomValue(configurationAtom);

  const count = selection.length;
  const type = pluralize(configuration?.resourceType ?? '', count);

  const warning =
    isOpened && formActions?.getValues
      ? getWarning?.(formActions.getValues())
      : null;

  const close = (): void => setIsOpened(false);

  const confirm = (): void => {
    formActions?.submit();
    setIsOpened(false);
  };

  return (
    <Modal onClose={close} open={isOpened} size="large">
      <Modal.Header>{t(labelMassChange)}</Modal.Header>
      <Modal.Body>
        <div className="flex flex-col gap-2">
          <Typography data-testid="mass-change-confirmation">
            {t(labelApplyChangesTo(type), { count })}
          </Typography>
          <ul
            className="m-0 max-h-40 overflow-y-auto pl-5"
            data-testid="mass-change-selection"
          >
            {selection.map(({ id, name }) => (
              <li key={id}>
                <Typography>{name}</Typography>
              </li>
            ))}
          </ul>
          {warning && (
            <Typography
              className="rounded-sm bg-warning-light/50 p-2 font-bold text-warning-dark"
              data-testid="mass-change-warning"
            >
              {warning}
            </Typography>
          )}
        </div>
      </Modal.Body>
      {warning ? (
        // A consequence to weigh, not only a write to confirm: Apply takes the
        // warning colour of the message above it.
        <Modal.Actions>
          <Button
            aria-label={t(labelCancel)}
            data-testid="cancel"
            onClick={close}
            size="small"
            variant="secondary"
          >
            {t(labelCancel)}
          </Button>
          <Button
            aria-label={t(labelApply)}
            // One attribute more specific than the button's own primary
            // background, which would otherwise win.
            className="[&:not(:disabled)]:data-[variant=primary]:data-[is-danger=false]:bg-warning-main"
            color="warning"
            data-testid="confirm"
            onClick={confirm}
            size="small"
            type="submit"
            variant="primary"
          >
            {t(labelApply)}
          </Button>
        </Modal.Actions>
      ) : (
        <Modal.Actions
          isDanger
          labels={{
            cancel: t(labelCancel),
            confirm: t(labelApply)
          }}
          onCancel={close}
          onConfirm={confirm}
        />
      )}
    </Modal>
  );
};

export default MassChangeDialog;
