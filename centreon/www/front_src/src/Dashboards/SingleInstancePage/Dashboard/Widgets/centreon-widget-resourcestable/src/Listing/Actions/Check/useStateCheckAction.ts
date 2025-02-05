import { useEffect } from 'react';

import { PrimitiveAtom, SetStateAction, useAtom } from 'jotai';

import { Resource } from '../../models';
import useAclQuery from '../aclQuery';

import { CheckActionAtom } from './checkAtoms';
import { SetAtom } from './models';

interface Props {
  resources: Array<Resource>;
  stateCheckActionAtom: PrimitiveAtom<CheckActionAtom | null>;
}

interface UseStateCheckAction {
  checkAction: CheckActionAtom | null;
  setCheckAction: SetAtom<[SetStateAction<CheckActionAtom | null>], void>;
}

const useStateCheckAction = ({
  resources,
  stateCheckActionAtom
}: Props): UseStateCheckAction => {
  const { canForcedCheck, canCheck } = useAclQuery();

  const [checkAction, setCheckAction] = useAtom(stateCheckActionAtom);

  const canForceCheckResource = canForcedCheck(resources);
  const canCheckResource = canCheck(resources);

  useEffect(() => {
    if (checkAction?.checked || checkAction?.forcedChecked) {
      return;
    }
    if (canForceCheckResource) {
      setCheckAction({ checked: false, forcedChecked: true });

      return;
    }
    if (canCheckResource) {
      setCheckAction({ checked: true, forcedChecked: false });

      return;
    }
    setCheckAction({ checked: false, forcedChecked: false });
  }, [resources.length]);

  return { checkAction, setCheckAction };
};

export default useStateCheckAction;
