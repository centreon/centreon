import {
  Alert,
  Checkbox,
  FormControlLabel,
  FormHelperText,
  Grid
} from '@mui/material';

import { Dialog, TextField } from '@centreon/ui';

import { FormikErrors, FormikHandlers, FormikValues } from 'formik';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Resource } from '../../../models';
import {
  labelAcknowledge,
  labelAcknowledgeServices,
  labelCancel,
  labelComment,
  labelNotify,
  labelNotifyHelpCaption,
  labelStickyForAnyNonOkStatus
} from '../../../translatedLabels';
import useAclQuery from '../aclQuery';
import { AcknowledgeFormValues } from '.';

interface Props extends Pick<FormikHandlers, 'handleChange'> {
  canConfirm: boolean;
  errors?: FormikErrors<AcknowledgeFormValues>;
  onCancel: () => void;
  onConfirm: () => Promise<unknown>;
  resources: Array<Resource>;
  submitting: boolean;
  values: FormikValues;
}

const useStyles = makeStyles()((theme) => ({
  notify: {
    marginBottom: theme.spacing(2)
  }
}));

const DialogAcknowledge = ({
  resources,
  canConfirm,
  onCancel,
  onConfirm,
  errors,
  values,
  submitting,
  handleChange
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const { getAcknowledgementDeniedTypeAlert, canAcknowledgeServices } =
    useAclQuery();

  const deniedTypeAlert = getAcknowledgementDeniedTypeAlert(resources);

  const open = resources.length > 0;

  const hasHosts = resources.find((resource) => resource.type === 'host');

  return (
    <Dialog
      confirmDisabled={!canConfirm}
      data-testid="dialogAcknowledge"
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelAcknowledge)}
      labelTitle={t(labelAcknowledge)}
      onCancel={onCancel}
      onClose={onCancel}
      onConfirm={onConfirm}
      open={open}
      submitting={submitting}
    >
      <Grid container direction="column">
        {deniedTypeAlert && (
          <Grid item>
            <Alert severity="warning">{deniedTypeAlert}</Alert>
          </Grid>
        )}
        <Grid item>
          <TextField
            dataTestId={labelComment}
            error={errors?.comment}
            fullWidth
            label={t(labelComment)}
            multiline
            onChange={handleChange('comment')}
            rows={3}
            value={values.comment}
          />
        </Grid>
        <Grid className={classes.notify} container item>
          <Grid item>
            <FormControlLabel
              control={
                <Checkbox
                  checked={values.notify}
                  color="primary"
                  inputProps={{ 'aria-label': t(labelNotify) }}
                  onChange={handleChange('notify')}
                  size="small"
                />
              }
              label={t(labelNotify) as string}
            />
          </Grid>
          <Grid container item rowSpacing={1}>
            <FormHelperText>{t(labelNotifyHelpCaption)}</FormHelperText>
          </Grid>
        </Grid>
        <Grid item>
          <FormControlLabel
            control={
              <Checkbox
                checked={values.isSticky}
                color="primary"
                inputProps={{ 'aria-label': t(labelStickyForAnyNonOkStatus) }}
                onChange={handleChange('isSticky')}
                size="small"
              />
            }
            label={t(labelStickyForAnyNonOkStatus) as string}
          />
        </Grid>
        {hasHosts && (
          <Grid item>
            <FormControlLabel
              control={
                <Checkbox
                  checked={
                    canAcknowledgeServices() &&
                    values.acknowledgeAttachedResources
                  }
                  color="primary"
                  disabled={!canAcknowledgeServices()}
                  inputProps={{ 'aria-label': t(labelAcknowledgeServices) }}
                  onChange={handleChange('acknowledgeAttachedResources')}
                  size="small"
                />
              }
              label={t(labelAcknowledgeServices) as string}
            />
          </Grid>
        )}
      </Grid>
    </Dialog>
  );
};

export default DialogAcknowledge;
