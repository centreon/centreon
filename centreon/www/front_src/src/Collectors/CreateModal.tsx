import {
  Checkbox,
  FormControlLabel,
  Radio,
  RadioGroup,
  TextField,
  Typography
} from '@mui/material';

import { Button, CopyCommand, Modal } from '@centreon/ui/components';

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { CreatedEnrolment, CreateForm, CreatePayload } from './models';
import {
  labelAddCollector,
  labelApprovalRequired,
  labelCancel,
  labelClose,
  labelCommand,
  labelCreate,
  labelExpiresInHours,
  labelFleet,
  labelFleetHelp,
  labelInvalidForm,
  labelMaxUses,
  labelName,
  labelNamed,
  labelNamedHelp,
  labelToken,
  labelTokenShownOnce
} from './translatedLabels';
import { buildCreatePayload, defaultCreateForm } from './utils';

interface Props {
  create: (payload: CreatePayload) => Promise<CreatedEnrolment | null>;
  isMutating: boolean;
  onClose: () => void;
  open: boolean;
}

/**
 * Creates an enrolment, then shows its token and the command to run once: nothing keeps the
 * token, so closing the dialog forgets it.
 */
const CreateModal = ({
  create,
  isMutating,
  onClose,
  open
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const [form, setForm] = useState<CreateForm>(defaultCreateForm);
  const [created, setCreated] = useState<CreatedEnrolment | null>(null);

  const payload = buildCreatePayload(form);

  const close = (): void => {
    setCreated(null);
    setForm(defaultCreateForm);
    onClose();
  };

  const submit = (): void => {
    if (payload === null) {
      return;
    }
    create(payload).then((answer) => {
      if (answer) {
        setCreated(answer);
      }
    });
  };

  const update = (changes: Partial<CreateForm>): void =>
    setForm((current) => ({ ...current, ...changes }));

  return (
    <Modal onClose={close} open={open} size="large">
      <Modal.Header>{t(labelAddCollector)}</Modal.Header>
      <Modal.Body>
        {created ? (
          <div className="flex flex-col gap-4" data-testid="created-enrolment">
            <Typography>{t(labelTokenShownOnce)}</Typography>
            <Typography variant="subtitle2">{t(labelCommand)}</Typography>
            <CopyCommand
              commandToCopy={created.command}
              language="bash"
              text={created.command}
            />
            <Typography variant="subtitle2">{t(labelToken)}</Typography>
            <CopyCommand
              commandToCopy={created.token}
              language="bash"
              text={created.token}
            />
          </div>
        ) : (
          <div className="flex flex-col gap-4">
            <RadioGroup
              onChange={(event) =>
                update({ kind: event.target.value as CreateForm['kind'] })
              }
              row
              value={form.kind}
            >
              <FormControlLabel
                control={<Radio />}
                label={t(labelNamed)}
                value="named"
              />
              <FormControlLabel
                control={<Radio />}
                label={t(labelFleet)}
                value="fleet"
              />
            </RadioGroup>
            <Typography variant="body2">
              {t(form.kind === 'named' ? labelNamedHelp : labelFleetHelp)}
            </Typography>
            {form.kind === 'named' ? (
              <TextField
                label={t(labelName)}
                onChange={(event) => update({ name: event.target.value })}
                slotProps={{ htmlInput: { 'aria-label': t(labelName) } }}
                value={form.name}
              />
            ) : (
              <>
                <TextField
                  label={t(labelMaxUses)}
                  onChange={(event) => update({ maxUses: event.target.value })}
                  slotProps={{ htmlInput: { 'aria-label': t(labelMaxUses) } }}
                  value={form.maxUses}
                />
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={form.approvalRequired}
                      onChange={(event) =>
                        update({ approvalRequired: event.target.checked })
                      }
                    />
                  }
                  label={t(labelApprovalRequired)}
                />
              </>
            )}
            <TextField
              label={t(labelExpiresInHours)}
              onChange={(event) =>
                update({ expiresInHours: Number(event.target.value) })
              }
              slotProps={{
                htmlInput: { 'aria-label': t(labelExpiresInHours), min: 1 }
              }}
              type="number"
              value={form.expiresInHours}
            />
            {payload === null && (
              <Typography color="error" variant="body2">
                {t(labelInvalidForm)}
              </Typography>
            )}
          </div>
        )}
      </Modal.Body>
      {created ? (
        <Modal.Actions>
          <Button aria-label={t(labelClose)} onClick={close} size="small">
            {t(labelClose)}
          </Button>
        </Modal.Actions>
      ) : (
        <Modal.Actions
          disabled={payload === null || isMutating}
          labels={{ cancel: t(labelCancel), confirm: t(labelCreate) }}
          onCancel={close}
          onConfirm={submit}
        />
      )}
    </Modal>
  );
};

export default CreateModal;
