import { useEffect, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';

import { Switch, Tooltip } from '@mui/material';

import { Method, useMutationQuery } from '@centreon/ui';

import { patchTokenEndpoint } from '../../../api/endpoints';
import {
  labelActive,
  labelActiveOrRevoked,
  labelRevoked
} from '../../../translatedLabels';
import { Row } from '../../models';

import useActivateStyles from './Activate.styles';

const Activate = ({ row }: Row): React.JSX.Element => {
  const queryClient = useQueryClient();
  const { t } = useTranslation();
  const { classes } = useActivateStyles();

  const [isRevoked, setIsRevoked] = useState<boolean>(row?.isRevoked);

  useEffect(() => {
    if (row?.isRevoked !== isRevoked) {
      setIsRevoked(row?.isRevoked);
    }
  }, [row?.isRevoked]);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      patchTokenEndpoint({ tokenName: row?.name, userId: row?.user.id }),
    method: Method.PATCH,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['listTokens'] })
  });

  const onClick = (e: React.BaseSyntheticEvent): void => {
    const value = e.target.checked;
    setIsRevoked(value);

    mutateAsync({
      payload: { is_revoked: !value }
    });
  };

  return (
    <Tooltip title={isRevoked ? t(labelRevoked) : t(labelActive)}>
      <Switch
        aria-label={t(labelActiveOrRevoked)}
        checked={!isRevoked}
        className={classes.switch}
        color="success"
        size="small"
        onClick={onClick}
      />
    </Tooltip>
  );
};

export default Activate;
