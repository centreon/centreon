import { JSX, useMemo } from 'react';

import { Switch, Tooltip } from '@centreon/ui/components';
import DoneIcon from '@mui/icons-material/Done';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { TabPanel } from '@mui/lab';
import { FormControlLabel } from '@mui/material';
import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { AgentConfigurationForm, ConnectionMode } from '../../models';
import AgentInitiated from './AgentInitiated';
import { useStyles } from './ConnectionInitiated.styles';
import HostConfigurations from './HostConfigurations/HostConfigurations';
import { Tabs } from './Tabs';

import {
  labelByAgent,
  labelByAgentTooltip,
  labelByPoller,
  labelByPollerTooltip,
  labelEnable
} from '../../translatedLabels';

interface TabContentProps {
  label: string;
  tooltipLabel?: string;
  name: string;
}

const TabContent = ({ label, tooltipLabel, name }: TabContentProps) => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const { values } = useFormikContext<AgentConfigurationForm>();

  return (
    <div className={classes.tabContent}>
      {values.configuration[name] && (
        <DoneIcon
          className={classes.doneIcon}
          data-testid={`${label} selected`}
        />
      )}
      <div>{t(label)}</div>
      {tooltipLabel && (
        <Tooltip label={t(tooltipLabel)}>
          <InfoOutlinedIcon color="primary" className={classes.InfoIcon} />
        </Tooltip>
      )}
    </div>
  );
};

const tabs = [
  {
    label: (
      <TabContent
        label={labelByAgent}
        tooltipLabel={labelByAgentTooltip}
        name="agentInitiated"
      />
    ),
    value: 'agent'
  },
  {
    label: (
      <TabContent
        label={labelByPoller}
        tooltipLabel={labelByPollerTooltip}
        name="pollerInitiated"
      />
    ),
    value: 'poller'
  }
];

const ConnectionInitiated = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { values, setFieldValue, validateForm } =
    useFormikContext<AgentConfigurationForm>();

  const handleChange =
    (name: string) =>
    (event): void => {
      const checked = event.target.checked;

      if (equals(name, 'pollerInitiated') && checked) {
        setFieldValue('configuration.hosts', [
          {
            address: '',
            port: '',
            pollerCaCertificate: '',
            pollerCaName: '',
            token: null
          }
        ]);
      }

      if (equals(name, 'pollerInitiated') && !checked) {
        setFieldValue('configuration.hosts', []);
      }

      setFieldValue(`configuration.${name}`, checked);

      setTimeout(() => {
        validateForm();
      }, 0);
    };

  const isNoTLSMode = useMemo(
    () =>
      equals(values.connectionMode?.id, ConnectionMode.secure) ||
      equals(values.connectionMode?.id, ConnectionMode.insecure),
    [values.connectionMode?.id]
  );

  return (
    <Tabs tabs={tabs} defaultTab="agent">
      <TabPanel value="agent" className={classes.tabPanel}>
        <FormControlLabel
          control={
            <Switch
              size="small"
              color="success"
              checked={values.configuration.agentInitiated}
              onChange={handleChange('agentInitiated')}
            />
          }
          label={t(labelEnable)}
          labelPlacement="start"
          sx={{
            marginLeft: 0,
            marginBottom: 2,
            '& .MuiFormControlLabel-label': {
              marginRight: 2
            }
          }}
        />
        {values.configuration.agentInitiated && isNoTLSMode && (
          <AgentInitiated />
        )}
      </TabPanel>
      <TabPanel value="poller" className={classes.tabPanel}>
        <FormControlLabel
          control={
            <Switch
              size="small"
              color="success"
              checked={values.configuration.pollerInitiated}
              onChange={handleChange('pollerInitiated')}
            />
          }
          label={t(labelEnable)}
          labelPlacement="start"
          sx={{
            marginLeft: 0,
            marginBottom: 2,
            '& .MuiFormControlLabel-label': {
              marginRight: 2
            }
          }}
        />
        {values.configuration.pollerInitiated && <HostConfigurations />}
      </TabPanel>
    </Tabs>
  );
};

export default ConnectionInitiated;
