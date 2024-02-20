import { useEffect, useState } from 'react';

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

  const [activeToken, setActiveToken] = useState(false);
  const [revokedToken, setRevokedToken] = useState(false);
  const [isRevoked, setIsRevoked] = useAtom(isRevokedAtom);

  const handleActiveToken = (event): void => {
    if (revokedToken) {
      setRevokedToken(false);
    }
    setActiveToken(event.target.checked);
    if (event.target.checked) {
      setIsRevoked(false);

      return;
    }
    setIsRevoked(null);
  };

  const handleRevokedToken = (event): void => {
    if (activeToken) {
      setActiveToken(false);
    }
    setRevokedToken(event.target.checked);
    if (event.target.checked) {
      setIsRevoked(true);

      return;
    }
    setIsRevoked(null);
  };

  useEffect(() => {
    if (isNil(isRevoked)) {
      setRevokedToken(false);
      setActiveToken(false);

      return;
    }

    if (isRevoked) {
      setRevokedToken(true);

      return;
    }
    setActiveToken(true);
  }, [isRevoked]);

  return (
    <div className={classes.statusContainer}>
      <Typography className={classes.labelStatus} variant="subtitle2">
        {t(labelStatus)}
      </Typography>
      <div className={classes.checkboxContainer}>
        <Checkbox
          checked={activeToken}
          label={t(labelActiveToken)}
          labelProps={{ classes: { root: classes.checkbox }, variant: 'body2' }}
          onChange={handleActiveToken}
        />
        <Checkbox
          checked={revokedToken}
          label={t(labelRevokedToken)}
          labelProps={{ classes: { root: classes.checkbox }, variant: 'body2' }}
          onChange={handleRevokedToken}
        />
      </div>
    </div>
  );
};

export default Status;
