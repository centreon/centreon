import { useEffect, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { pick } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Checkbox, FormControlLabel, Typography } from '@mui/material';

import {
  MultiAutocompleteField,
  SelectField,
  centreonBaseURL,
  postData,
  useRequest
} from '@centreon/ui';
import type { SelectEntry } from '@centreon/ui';

import routeMap from '../../reactRoutes/routeMap';
import { useStyles } from '../../styles/partials/form/PollerWizardStyle';
import { remoteServersEndpoint, wizardFormEndpoint } from '../api/endpoints';
import WizardButtons from '../forms/wizardButtons';
import { PollerRemoteList, Props, WizardButtonsTypes } from '../models';
import { PollerData, pollerAtom, setWizardDerivedAtom } from '../pollerAtoms';
import {
  labelAdvancedServerConfiguration,
  labelLinkedRemoteMaster,
  labelLinkedadditionalRemote,
  labelOpenBrokerFlow
} from '../translatedLabels';

interface StepTwoFormData {
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
            options={linkedRemoteMasterOption || []}
            selectedOptionId={stepTwoFormData.linked_remote_master}
            onChange={handleChange}
          />
          {stepTwoFormData.linked_remote_master && (
            <MultiAutocompleteField
              fullWidth
              label={t(labelLinkedadditionalRemote)}
              options={linkedRemoteSlavesOption || []}
              value={stepTwoFormData.linked_remote_slaves}
              onChange={changeValue}
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
