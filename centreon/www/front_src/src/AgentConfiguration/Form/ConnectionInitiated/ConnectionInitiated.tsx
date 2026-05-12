import DoneIcon from '@mui/icons-material/Done';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { TabPanel } from '@mui/lab';
import { FormControlLabel } from '@mui/material';

import { Switch, Tooltip } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { AgentConfigurationForm, CMAConfiguration } from '../../models';
import {
  labelByAgent,
  labelByAgentTooltip,
  labelByPoller,
  labelByPollerTooltip,
  labelEnable
} from '../../translatedLabels';
import AgentInitiated from './AgentInitiated';
import { useStyles } from './ConnectionInitiated.styles';
import HostConfigurations from './HostConfigurations/HostConfigurations';
import { Tabs } from './Tabs';

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
      {(values.configuration as CMAConfiguration)[
        name as keyof CMAConfiguration
      ] && (
        <DoneIcon
          className={classes.doneIcon}
          data-testid={`${label} selected`}
        />
      )}
      <div>{t(label)}</div>
      {tooltipLabel && (
        <Tooltip label={t(tooltipLabel)}>
          <InfoOutlinedIcon className={classes.InfoIcon} color="primary" />
        </Tooltip>
      )}
    </div>
  );
};

const tabs = [
  {
    ariaLabel: labelByAgent,
    label: (
      <TabContent
        label={labelByAgent}
        name="agentInitiated"
        tooltipLabel={labelByAgentTooltip}
      />
    ),
    value: 'agent'
  },
  {
    ariaLabel: labelByPoller,
    label: (
      <TabContent
        label={labelByPoller}
        name="pollerInitiated"
        tooltipLabel={labelByPollerTooltip}
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

  const cmaConfiguration = values.configuration as CMAConfiguration;

  const handleChange =
    (name: string) =>
    (event: React.ChangeEvent<HTMLInputElement>): void => {
      const checked = event.target.checked;

      if (equals(name, 'pollerInitiated') && checked) {
        setFieldValue('configuration.hosts', [
          {
            address: '',
            pollerCaCertificate: '',
            pollerCaName: '',
            port: '',
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

  return (
    <Tabs defaultTab="agent" tabs={tabs}>
      <TabPanel className={classes.tabPanel} value="agent">
        <FormControlLabel
          control={
            <Switch
              checked={cmaConfiguration.agentInitiated}
              color="success"
              data-testid="enable_agent"
              onChange={handleChange('agentInitiated')}
              size="small"
            />
          }
          label={t(labelEnable)}
          labelPlacement="start"
          sx={{
            '& .MuiFormControlLabel-label': {
              marginRight: 2
            },
            marginBottom: 2,
            marginLeft: 0
          }}
        />
        {cmaConfiguration.agentInitiated && <AgentInitiated />}
      </TabPanel>
      <TabPanel className={classes.tabPanel} value="poller">
        <FormControlLabel
          control={
            <Switch
              checked={cmaConfiguration.pollerInitiated}
              color="success"
              data-testid="enable_poller"
              onChange={handleChange('pollerInitiated')}
              size="small"
            />
          }
          label={t(labelEnable)}
          labelPlacement="start"
          sx={{
            '& .MuiFormControlLabel-label': {
              marginRight: 2
            },
            marginBottom: 2,
            marginLeft: 0
          }}
        />
        {cmaConfiguration.pollerInitiated && <HostConfigurations />}
      </TabPanel>
    </Tabs>
  );
};

export default ConnectionInitiated;
