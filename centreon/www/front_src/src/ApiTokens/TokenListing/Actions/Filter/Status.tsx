import { useAtom } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import Typography from '@mui/material/Typography';

import { Checkbox } from '@centreon/ui';

import {
  labelActiveToken,
  labelRevokedToken,
  labelStatus
} from '../../../translatedLabels';

import { isRevokedAtom } from './atoms';
import { useStyles } from './filter.styles';

const Status = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isRevoked, setIsRevoked] = useAtom(isRevokedAtom);

  const handleActiveToken = (event): void => {
    if (event.target.checked) {
      setIsRevoked(false);

      return;
    }
    setIsRevoked(null);
  };

  const handleRevokedToken = (event): void => {
    if (event.target.checked) {
      setIsRevoked(true);

      return;
    }
    setIsRevoked(null);
  };

  return (
    <div className={classes.statusContainer}>
      <Typography className={classes.labelStatus} variant="subtitle2">
        {t(labelStatus)}
      </Typography>
      <div className={classes.checkboxContainer}>
        <Checkbox
          checked={!isNil(isRevoked) ? !isRevoked : false}
          dataTestId={labelActiveToken}
          label={t(labelActiveToken)}
          labelProps={{ classes: { root: classes.checkbox }, variant: 'body2' }}
          onChange={handleActiveToken}
        />
        <Checkbox
          checked={Boolean(isRevoked)}
          dataTestId={labelRevokedToken}
          label={t(labelRevokedToken)}
          labelProps={{ classes: { root: classes.checkbox }, variant: 'body2' }}
          onChange={handleRevokedToken}
        />
      </div>
    </div>
  );
};

export default Status;
