import { useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';

import { Method, useMutationQuery } from '@centreon/ui';
import { Switch } from '@centreon/ui/components';

import { tokenEndpoint } from '../../api/endpoints';
import { labelActiveOrRevoked } from '../../translatedLabels';
import { Row } from '../models';
import { useStatusStyles } from './ActionsColumn/Actions.styles';

const Activate = ({ row }: Row): React.JSX.Element => {
  const { classes } = useStatusStyles();

  const queryClient = useQueryClient();

  const [isRevoked, setIsRevoked] = useState<boolean>(row?.isRevoked);

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      tokenEndpoint({ tokenName: row?.name, userId: row?.user.id }),
    method: Method.PATCH,
    onError: () => setIsRevoked(!isRevoked),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['listTokens'] })
  });

  const onClick = (e: React.BaseSyntheticEvent): void => {
    const value = !e.target.checked;
    setIsRevoked(value);

    mutateAsync({
      payload: { is_revoked: value }
    });
  };

  return (
    <div className={classes.container}>
      <Switch
        aria-label={labelActiveOrRevoked}
        checked={!isRevoked}
        disabled={isMutating}
        size="small"
        onClick={onClick}
      />
    </div>
  );
};

export default Activate;
