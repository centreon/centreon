import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { listTokensDecoder } from '../api/decoder';
import { listTokensEndpoint } from '../api/endpoints';
import useGetAll from '../api/useGetAll';

import {
  filtersAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atoms';

interface LoadDataState {
  data?;
  isLoading: boolean;
}

const useLoadData = (): LoadDataState => {
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const filters = useAtomValue(filtersAtom);

  const searchConditions = [
    ...(!filters.users
      ? []
      : filters.users.map((user) => ({
          field: 'user.id',
          values: {
            $eq: user.id
          }
        }))),
    ...(!filters.creators
      ? []
      : filters.creators.map((creator) => ({
          field: 'creator.id',
          values: {
            $eq: creator.id
          }
        }))),
    ...(filters.name
      ? [
          {
            field: 'token_name',
            values: {
              $rg: filters.name
            }
          }
        ]
      : []),
    ...(filters.expirationDate
      ? [
          {
            field: 'expiration_date',
            values: {
              $le: filters.expirationDate
            }
          }
        ]
      : []),
    ...(filters.creationDate
      ? [
          {
            field: 'creation_date',
            values: {
              $ge: filters.creationDate
            }
          }
        ]
      : []),
    equals(filters.enabled, filters.disabled)
      ? {}
      : {
          field: 'is_revoked',
          values: {
            $eq: filters.disabled
          }
        }
  ];

  const { data, isLoading } = useGetAll({
    baseEndpoint: listTokensEndpoint,
    decoder: listTokensDecoder,
    limit,
    page,
    queryKey: ['listTokens', sortField, sortOrder, limit, page],
    searchConditions,
    sortField,
    sortOrder
  });

  return { data, isLoading };
};

export default useLoadData;
