export const enrolmentsEndpoint = '/configuration/collectors/enrolments';

export const tokensEndpoint = '/configuration/collectors/enrolment-tokens';

export const getTokenEndpoint = (id: string): string =>
  `${tokensEndpoint}/${encodeURIComponent(id)}`;

export const getDecisionEndpoint = (
  id: string,
  decision: 'approve' | 'reject'
): string => `${enrolmentsEndpoint}/${encodeURIComponent(id)}/${decision}`;

export const getRotateCredentialEndpoint = (pollerId: number): string =>
  `/configuration/collectors/${pollerId}/rotate-credential`;
