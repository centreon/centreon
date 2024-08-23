import { useEffect, useState } from 'react';

import { propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Alert, Checkbox, FormControlLabel, Grid } from '@mui/material';

import { Dialog, useRequest, useSnackbar } from '@centreon/ui';

import { Resource } from '../../../models';
import {
  labelCancel,
  labelDisacknowledge,
  labelDisacknowledgeServices,
  labelDisacknowledgementCommandSent
} from '../../../translatedLabels';
import useAclQuery from '../aclQuery';

import { disacknowledgeResources } from './api';

interface Props {
  onClose;
  onSuccess;
  resources: Array<Resource>;
}

const DisacknowledgeForm = ({
  resources,
  onClose,
  onSuccess
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const [disacknowledgeAttachedResources, setDisacknowledgeAttachedResources] =
    useState(true);

  const {
    sendRequest: sendDisacknowledgeResources,
    sending: sendingDisacknowledgeResources
  } = useRequest({
    request: disacknowledgeResources
  });

  const { getDisacknowledgementDeniedTypeAlert, canDisacknowledgeServices } =
    useAclQuery();

  const deniedTypeAlert = getDisacknowledgementDeniedTypeAlert(resources);

  useEffect(() => {
    if (canDisacknowledgeServices()) {
      return;
    }

    setDisacknowledgeAttachedResources(false);
  }, []);

  const submitDisacknowledge = (): void => {
    sendDisacknowledgeResources({
      disacknowledgeAttachedResources,
      resources
    }).then(() => {
      showSuccessMessage(t(labelDisacknowledgementCommandSent));
      onSuccess();
    });
  };

  const changeDisacknowledgeAttachedRessources = (event): void => {
    setDisacknowledgeAttachedResources(Boolean(event.target.checked));
  };

  const hasHosts = resources.find(propEq('host', 'type'));

  return (
    <Dialog
      open
      confirmDisabled={sendingDisacknowledgeResources}
      data-testid="modalDisacknowledge"
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDisacknowledge)}
      labelTitle={t(labelDisacknowledge)}
      submitting={sendingDisacknowledgeResources}
      onCancel={onClose}
      onClose={onClose}
      onConfirm={submitDisacknowledge}
    >
      <Grid container direction="column" spacing={1}>
        {deniedTypeAlert && (
          <Grid item>
            <Alert severity="warning">{deniedTypeAlert}</Alert>
          </Grid>
        )}
        {hasHosts && (
          <Grid item>
            <FormControlLabel
              control={
                <Checkbox
                  checked={
                    canDisacknowledgeServices() &&
                    disacknowledgeAttachedResources
                  }
                  color="primary"
                  disabled={!canDisacknowledgeServices()}
                  inputProps={{ 'aria-label': t(labelDisacknowledgeServices) }}
                  size="small"
                  onChange={changeDisacknowledgeAttachedRessources}
                />
              }
              label={t(labelDisacknowledgeServices) as string}
            />
          </Grid>
        )}
      </Grid>
    </Dialog>
  );
};

export default DisacknowledgeForm;
