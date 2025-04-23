import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Button, CircularProgress } from '@mui/material';

import { useStyles } from '../../styles/partials/form/PollerWizardStyle';
import { WizardButtonsTypes } from '../models';
import { labelApply, labelNext, labelPrevious } from '../translatedLabels';

interface Props {
  disabled: boolean;
  goToPreviousStep: () => void;
  type: WizardButtonsTypes;
}

const WizardButtons = ({
  goToPreviousStep,
  disabled,
  type
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const label = equals(type, WizardButtonsTypes.Next)
    ? t(labelNext)
    : t(labelApply);

  const loadIcon =
    equals(type, WizardButtonsTypes.Apply) && disabled ? (
      <CircularProgress size={15} />
    ) : null;

  return (
    <div className={classes.formButton}>
      <Button size="small" onClick={goToPreviousStep}>
        {t(labelPrevious)}
      </Button>
      <Button
        color="primary"
        disabled={disabled}
        endIcon={loadIcon}
        size="small"
        type="submit"
        variant="contained"
      >
        {label}
      </Button>
    </div>
  );
};

export default WizardButtons;
