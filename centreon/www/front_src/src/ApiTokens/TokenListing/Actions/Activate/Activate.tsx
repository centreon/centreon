import { useEffect, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';

import { Method, useMutationQuery } from '@centreon/ui';
import { Switch } from '@centreon/ui/components';

import { patchTokenEndpoint } from '../../../api/endpoints';
import { labelActiveOrRevoked } from '../../../translatedLabels';
import { Row } from '../../models';

const Activate = ({ row }: Row): React.JSX.Element => {
  const queryClient = useQueryClient();

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
    <Switch
      aria-label={labelActiveOrRevoked}
      checked={!isRevoked}
      size="small"
      onClick={onClick}
    />
  );
};

export default Activate;
