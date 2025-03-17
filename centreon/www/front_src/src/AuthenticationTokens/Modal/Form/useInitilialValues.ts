import { userAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import { find, pick, propEq } from 'ramda';
import { useSearchParams } from 'react-router';
import { dataDuration, tokenTypes } from '../utils';

const useInitilialValues = () => {
  const currentUser = useAtomValue(userAtom);
  const [searchParams] = useSearchParams(window.location.search);

  const type = find(propEq(searchParams.get('type'), 'id'), tokenTypes);

  const user = currentUser.canManageApiTokens
    ? null
    : pick(['id', 'name'], currentUser);

  const duration = find(propEq('neverExpire', 'id'), dataDuration);

  const initialValues = {
    tokenName: '',
    customizeDate: null,
    duration,
    user,
    type
  };

  return {
    initialValues
  };
};

export default useInitilialValues;
