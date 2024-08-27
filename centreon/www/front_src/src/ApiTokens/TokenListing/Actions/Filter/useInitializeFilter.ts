import { useCallback } from 'react';

import { useAtom } from 'jotai';

import {
  creationDateAtom,
  creatorsAtom,
  expirationDateAtom,
  isRevokedAtom,
  usersAtom
} from './atoms';

interface Props {
  initialize: () => void;
}

const useInitializeFilter = (): Props => {
  const [users, setUsers] = useAtom(usersAtom);
  const [creators, setCreators] = useAtom(creatorsAtom);
  const [creationDate, setCreationDate] = useAtom(creationDateAtom);
  const [expirationDate, setExpirationDate] = useAtom(expirationDateAtom);
  const [isRevoked, setIsRevoked] = useAtom(isRevokedAtom);

  const initialize = useCallback(() => {
    setUsers([]);
    setCreators([]);
    setCreationDate(null);
    setExpirationDate(null);
    setIsRevoked(null);
  }, [creators.length, users.length, expirationDate, creationDate, isRevoked]);

  return { initialize };
};

export default useInitializeFilter;
