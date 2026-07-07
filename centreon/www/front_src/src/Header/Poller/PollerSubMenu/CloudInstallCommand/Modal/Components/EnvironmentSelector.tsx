import HelpOutlineIcon from '@mui/icons-material/HelpOutlined';
import { Tooltip, Typography } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Section } from '../../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import dockerLogo from '../../../../../../assets/docker.svg';
import linuxLogo from '../../../../../../assets/linux.svg';
import {
  labelDockerCompose,
  labelSelectPollerEnvironment,
  labelVMOrPhysical
} from '../../../../translatedLabels';
import { isGeneratedAtom } from '../../atoms';
import { CloudInstallCommandFormValues, PollerEnvironment } from '../../models';

const environments = [
  {
    env: PollerEnvironment.VM,
    icon: <img alt="Centreon" className="w-12 h-12" src={linuxLogo} />,
    label: labelVMOrPhysical
  },
  {
    env: PollerEnvironment.Docker,
    icon: <img alt="Centreon" className="w-12 h-12" src={dockerLogo} />,
    label: labelDockerCompose
  }
];

const EnvironmentSelector = (): ReactElement => {
  const { t } = useTranslation();
  const isGenerated = useAtomValue(isGeneratedAtom);
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<CloudInstallCommandFormValues>();

  const selectEnvironment = (env: PollerEnvironment): void => {
    setFieldTouched('environment', true, false);
    setFieldValue('environment', env);
  };

  const title = (
    <div className="flex items-center gap-1">
      {t(labelSelectPollerEnvironment)}
      <Tooltip title={t(labelSelectPollerEnvironment)}>
        <HelpOutlineIcon className="text-text-secondary" fontSize="small" />
      </Tooltip>
    </div>
  );

  return (
    <Section order={2} title={title}>
      <div className="flex gap-12 my-2">
        {environments.map(({ env, icon, label }) => (
          <div className="flex flex-col gap-2.5 items-center" key={label}>
            <IconButton
              ariaLabel={t(label)}
              className={`flex justify-center items-center w-16 h-16 rounded-sm ${equals(values.environment, env) ? 'border-2 border-primary-main' : ''}`}
              data-selected={values.environment === env}
              disabled={isGenerated}
              onClick={() => selectEnvironment(env)}
            >
              {icon}
            </IconButton>
            <Typography
              className="font-medium cursor-pointer"
              onClick={() => !isGenerated && selectEnvironment(env)}
              variant="subtitle2"
            >
              {t(label)}
            </Typography>
          </div>
        ))}
      </div>
    </Section>
  );
};

export default EnvironmentSelector;
