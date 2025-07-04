import { userAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import { find, pick, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import { dataDuration, tokenTypes } from '../utils';

const useInitilialValues = () => {
  const currentUser = useAtomValue(userAtom);
  const [searchParams] = useSearchParams(window.location.search);
  const { t } = useTranslation();

  const type = find(propEq(searchParams.get('type'), 'id'), tokenTypes);

  const user = currentUser.canManageApiTokens
    ? null
    : pick(['id', 'name'], currentUser);

  const duration = find(propEq('neverExpire', 'id'), dataDuration);

  const translatedDuration = { ...duration, name: t(duration?.name as string) };

  const initialValues = {
    tokenName: '',
    customizeDate: null,
    duration: translatedDuration,
    user,
    type
  };

  return {
    initialValues
  };
};

export default useInitilialValues;
