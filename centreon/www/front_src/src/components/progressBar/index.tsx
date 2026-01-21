import { Step, StepLabel, Stepper } from '@mui/material';

import { useTranslation } from 'react-i18next';

interface Props {
  activeStep: number;
  steps: Array<string>;
}

const ProgressBar = ({ steps, activeStep }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Stepper activeStep={activeStep} alternativeLabel>
      {steps.map((label) => (
        <Step key={label}>
          <StepLabel>{t(label)}</StepLabel>
        </Step>
      ))}
    </Stepper>
  );
};

export default ProgressBar;
