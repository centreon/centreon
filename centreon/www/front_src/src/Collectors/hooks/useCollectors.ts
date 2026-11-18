import {
  Method,
  useFetchQuery,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  enrolmentsEndpoint,
  getDecisionEndpoint,
  getRotateCredentialEndpoint,
  getTokenEndpoint,
  tokensEndpoint
} from '../api/endpoints';
import type {
  CreatedEnrolment,
  CreatePayload,
  Enrolment,
  EnrolmentToken,
  Listing
} from '../models';
import {
  labelEnrolmentApproved,
  labelEnrolmentRejected,
  labelRotationRequested,
  labelTokenRevoked
} from '../translatedLabels';

const enrolmentsKey = ['collector-enrolments'];
const tokensKey = ['collector-enrolment-tokens'];

/** The page follows what collectors do (enrol, wait, connect): read again every 10 seconds. */
const refreshInterval = 10_000;

interface UseCollectors {
  enrolments: Array<Enrolment>;
  isLoading: boolean;
  tokens: Array<EnrolmentToken>;
}

export const useCollectors = (): UseCollectors => {
  const enrolments = useFetchQuery<Listing<Enrolment>>({
    getEndpoint: () => enrolmentsEndpoint,
    getQueryKey: () => enrolmentsKey,
    queryOptions: { refetchInterval: refreshInterval, suspense: false }
  });
  const tokens = useFetchQuery<Listing<EnrolmentToken>>({
    getEndpoint: () => tokensEndpoint,
    getQueryKey: () => tokensKey,
    queryOptions: { refetchInterval: refreshInterval, suspense: false }
  });

  return {
    enrolments: enrolments.data?.result ?? [],
    isLoading: enrolments.isLoading || tokens.isLoading,
    tokens: tokens.data?.result ?? []
  };
};

type Action =
  | { kind: 'approve' | 'reject'; id: string }
  | { kind: 'revoke'; id: string }
  | { kind: 'rotate'; pollerId: number };

const getActionEndpoint = (action: Action): string => {
  switch (action.kind) {
    case 'approve':
    case 'reject':
      return getDecisionEndpoint(action.id, action.kind);
    case 'revoke':
      return getTokenEndpoint(action.id);
    default:
      return getRotateCredentialEndpoint(action.pollerId);
  }
};

const successLabels: Record<Action['kind'], string> = {
  approve: labelEnrolmentApproved,
  reject: labelEnrolmentRejected,
  revoke: labelTokenRevoked,
  rotate: labelRotationRequested
};

interface UseCollectorActions {
  create: (payload: CreatePayload) => Promise<CreatedEnrolment | null>;
  isMutating: boolean;
  run: (action: Action) => Promise<void>;
}

export const useCollectorActions = (): UseCollectorActions => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { showSuccessMessage } = useSnackbar();

  const refresh = (): void => {
    queryClient.invalidateQueries({ queryKey: enrolmentsKey });
    queryClient.invalidateQueries({ queryKey: tokensKey });
  };

  const creation = useMutationQuery<CreatedEnrolment, undefined>({
    getEndpoint: () => enrolmentsEndpoint,
    method: Method.POST,
    onSuccess: refresh
  });

  const post = useMutationQuery<object, Action>({
    getEndpoint: (action) => getActionEndpoint(action),
    method: Method.POST,
    onSuccess: (_data, { _meta }) => {
      refresh();
      if (_meta) {
        showSuccessMessage(t(successLabels[_meta.kind]));
      }
    }
  });

  const remove = useMutationQuery<object, Action>({
    getEndpoint: (action) => getActionEndpoint(action),
    method: Method.DELETE,
    onSuccess: (_data, { _meta }) => {
      refresh();
      if (_meta) {
        showSuccessMessage(t(successLabels[_meta.kind]));
      }
    }
  });

  const create = async (
    payload: CreatePayload
  ): Promise<CreatedEnrolment | null> => {
    const answer = await creation.mutateAsync({
      payload: payload as unknown as CreatedEnrolment
    });

    return (answer as { isError?: boolean }).isError
      ? null
      : (answer as CreatedEnrolment);
  };

  const run = async (action: Action): Promise<void> => {
    const mutation = action.kind === 'revoke' ? remove : post;
    await mutation.mutateAsync({ _meta: action });
  };

  return {
    create,
    isMutating: creation.isMutating || post.isMutating || remove.isMutating,
    run
  };
};
