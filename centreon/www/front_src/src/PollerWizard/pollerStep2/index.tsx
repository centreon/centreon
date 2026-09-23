// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import {
  Alert,
  Checkbox,
  FormControlLabel,
  Link,
  Typography
} from '@mui/material';

import type { SelectEntry } from '@centreon/ui';
import {
  centreonBaseURL,
  MultiAutocompleteField,
  postData,
  SelectField,
  useRequest
} from '@centreon/ui';

import { useAtomValue, useSetAtom } from 'jotai';
import { pick } from 'ramda';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import routeMap from '../../reactRoutes/routeMap';
import { useStyles } from '../../styles/partials/form/PollerWizardStyle';
import { remoteServersEndpoint, wizardFormEndpoint } from '../api/endpoints';
import WizardButtons from '../forms/wizardButtons';
import { PollerRemoteList, Props, WizardButtonsTypes } from '../models';
import { PollerData, pollerAtom, setWizardDerivedAtom } from '../pollerAtoms';
import {
  labelAdvancedServerConfiguration,
  labelDocumentation,
  labelGorgonePullWss,
  labelGorgonePullWssPrerequisite,
  labelLinkedadditionalRemote,
  labelLinkedRemoteMaster,
  labelOpenBrokerFlow
} from '../translatedLabels';

const pullWssDocumentationUrl =
  'https://docs.centreon.com/docs/monitoring/monitoring-servers/communications';

interface StepTwoFormData {
  gorgone_pull_wss: boolean;
  linked_remote_master: string;
  linked_remote_slaves: Array<SelectEntry>;
  open_broker_flow: boolean;
}
const PollerWizardStepTwo = ({
  goToNextStep,
  goToPreviousStep
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [remoteServers, setRemoteServers] = useState<Array<PollerRemoteList>>(
    []
  );
  const [stepTwoFormData, setStepTwoFormData] = useState<StepTwoFormData>({
    gorgone_pull_wss: false,
    linked_remote_master: '',
    linked_remote_slaves: [],
    open_broker_flow: false
  });

  const { sendRequest: getRemoteServersRequest } = useRequest<
    Array<PollerRemoteList>
  >({
    request: postData
  });

  const { sendRequest: postWizardFormRequest, sending: loading } = useRequest<{
    success: boolean;
  }>({
    request: postData
  });

  const pollerData = useAtomValue<PollerData | null>(pollerAtom);
  const setWizard = useSetAtom(setWizardDerivedAtom);

  const getRemoteServers = (): void => {
    getRemoteServersRequest({
      data: null,
      endpoint: remoteServersEndpoint
    }).then(setRemoteServers);
  };

  const handleChange = (event): void => {
    const { value, name } = event.target;

    if (name === 'open_broker_flow') {
      setStepTwoFormData({
        ...stepTwoFormData,
        open_broker_flow: !stepTwoFormData.open_broker_flow
      });

      return;
    }
    if (name === 'gorgone_pull_wss') {
      setStepTwoFormData({
        ...stepTwoFormData,
        gorgone_pull_wss: !stepTwoFormData.gorgone_pull_wss
      });

      return;
    }
    setStepTwoFormData({
      ...stepTwoFormData,
      [name]: value
    });
  };

  const changeValue = (_, slaves): void => {
    setStepTwoFormData({
      ...stepTwoFormData,
      linked_remote_slaves: slaves
    });
  };

  const handleSubmit = (event): void => {
    event.preventDefault();
    const data = {
      ...stepTwoFormData,
      linked_remote_slaves: stepTwoFormData.linked_remote_slaves.map(
        ({ id }) => id
      )
    };
    const dataToPost = { ...data, ...pollerData };
    dataToPost.server_type = 'poller';

    postWizardFormRequest({
      data: dataToPost,
      endpoint: wizardFormEndpoint
    })
      .then(({ success }) => {
        setWizard({ submitStatus: success });
        if (pollerData?.linked_remote_master) {
          goToNextStep();
        } else {
          window.location.href = `${centreonBaseURL}${routeMap.pollerList}`;
        }
      })
      .catch(() => undefined);
  };

  const linkedRemoteMasterOption = remoteServers.map(pick(['id', 'name']));

  const linkedRemoteSlavesOption = remoteServers
    .filter(
      (remoteServer) => remoteServer.id !== stepTwoFormData.linked_remote_master
    )
    .map(pick(['id', 'name']));

  useEffect(() => {
    getRemoteServers();
  }, []);

  return (
    <div>
      <div className={classes.formHeading}>
        <Typography variant="h6">
          {t(labelAdvancedServerConfiguration)}
        </Typography>
      </div>
      <form onSubmit={handleSubmit}>
        <div className={classes.form}>
          <SelectField
            fullWidth
            label={t(labelLinkedRemoteMaster)}
            name="linked_remote_master"
            onChange={handleChange}
            options={linkedRemoteMasterOption || []}
            selectedOptionId={stepTwoFormData.linked_remote_master}
          />
          {stepTwoFormData.linked_remote_master && (
            <MultiAutocompleteField
              fullWidth
              label={t(labelLinkedadditionalRemote)}
              onChange={changeValue}
              options={linkedRemoteSlavesOption || []}
              value={stepTwoFormData.linked_remote_slaves}
            />
          )}
          <FormControlLabel
            control={
              <Checkbox
                checked={stepTwoFormData.open_broker_flow}
                name="open_broker_flow"
                onChange={handleChange}
              />
            }
            label={`${t(labelOpenBrokerFlow)}`}
          />
          <FormControlLabel
            control={
              <Checkbox
                checked={stepTwoFormData.gorgone_pull_wss}
                name="gorgone_pull_wss"
                onChange={handleChange}
              />
            }
            label={`${t(labelGorgonePullWss)}`}
          />
          <Alert severity="info">
            {`${t(labelGorgonePullWssPrerequisite)} `}
            <Link
              href={pullWssDocumentationUrl}
              rel="noopener noreferrer"
              target="_blank"
              underline="hover"
            >
              {t(labelDocumentation)}
            </Link>
          </Alert>
          <WizardButtons
            disabled={loading}
            goToPreviousStep={goToPreviousStep}
            type={WizardButtonsTypes.Apply}
          />
        </div>
      </form>
    </div>
  );
};

export default PollerWizardStepTwo;
