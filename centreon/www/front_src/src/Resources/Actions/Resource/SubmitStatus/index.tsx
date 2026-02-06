import { Grid } from '@mui/material';

import {
  Dialog,
  SelectField,
  TextField,
  useRequest,
  useSnackbar
} from '@centreon/ui';

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Resource } from '../../../models';
import {
  labelCancel,
  labelCritical,
  labelDown,
  labelOk,
  labelOutput,
  labelPerformanceData,
  labelStatus,
  labelStatusSubmitted,
  labelSubmit,
  labelSubmitStatus,
  labelUnknown,
  labelUnreachable,
  labelUp,
  labelWarning
} from '../../../translatedLabels';
import { submitResourceStatus } from './api';

interface Props {
  onClose: () => void;
  onSuccess: () => void;
  resource: Resource;
}

const SubmitStatusForm = ({
  resource,
  onClose,
  onSuccess
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [selectedStatusId, setSelectedStatusId] = useState(0);
  const [output, setOutput] = useState('');
  const [performanceData, setPerformanceData] = useState('');

  const serviceStatuses = [
    {
      id: 0,
      name: t(labelOk)
    },
    {
      id: 1,
      name: t(labelWarning)
    },
    {
      id: 2,
      name: t(labelCritical)
    },
    { id: 3, name: t(labelUnknown) }
  ];

  const statuses = {
    host: [
      {
        id: 0,
        name: t(labelUp)
      },
      { id: 1, name: t(labelDown) },
      { id: 2, name: t(labelUnreachable) }
    ],
    metaservice: serviceStatuses,
    service: serviceStatuses
  };

  const { sendRequest, sending } = useRequest({
    request: submitResourceStatus
  });

  const submitStatus = (): void => {
    sendRequest({
      output,
      performanceData,
      resource,
      statusId: selectedStatusId
    }).then(() => {
      showSuccessMessage(t(labelStatusSubmitted));
      onSuccess();
    });
  };

  const changeSelectedStatusId = (event): void => {
    setSelectedStatusId(event.target.value);
  };

  const changeOutput = (event): void => {
    setOutput(event.target.value);
  };

  const changePerformanceData = (event): void => {
    setPerformanceData(event.target.value);
  };

  return (
    <Dialog
      confirmDisabled={sending}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelSubmit)}
      labelTitle={t(labelSubmitStatus)}
      onCancel={onClose}
      onClose={onClose}
      onConfirm={submitStatus}
      open
      submitting={sending}
    >
      <Grid container direction="column" spacing={1} style={{ minWidth: 500 }}>
        <Grid item>
          <SelectField
            fullWidth
            label={t(labelStatus)}
            onChange={changeSelectedStatusId}
            options={statuses[resource.type]}
            selectedOptionId={selectedStatusId}
          />
        </Grid>
        <Grid item>
          <TextField
            ariaLabel={t(labelOutput)}
            fullWidth
            label={t(labelOutput)}
            onChange={changeOutput}
            value={output}
          />
        </Grid>
        <Grid item>
          <TextField
            ariaLabel={t(labelPerformanceData)}
            fullWidth
            label={t(labelPerformanceData)}
            onChange={changePerformanceData}
            value={performanceData}
          />
        </Grid>
      </Grid>
    </Dialog>
  );
};

export default SubmitStatusForm;
