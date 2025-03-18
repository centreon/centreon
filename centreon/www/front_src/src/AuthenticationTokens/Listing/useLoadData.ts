import { useAtomValue } from 'jotai';

import { listTokensDecoder, listTokensEndpoint, useGetAll } from '../api';

import { equals } from 'ramda';
import { filtersAtom } from '../atoms';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';

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
            $rg: user.id
          }
        }))),
    ...(!filters.creators
      ? []
      : filters.creators.map((creator) => ({
          field: 'creator.id',
          values: {
            $rg: creator.id
          }
        }))),
    ...(!filters.types
      ? []
      : filters.types.map((type) => ({
          field: 'type',
          values: {
            $eq: type.id
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
    sortField,
    sortOrder,
    page,
    limit,
    searchConditions,
    queryKey: ['listTokens', sortField, sortOrder, limit, page],
    baseEndpoint: listTokensEndpoint,
    decoder: listTokensDecoder
  });

  return { data, isLoading };
};

export default useLoadData;
