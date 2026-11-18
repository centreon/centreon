import type {
  CreateForm,
  CreatePayload,
  Enrolment,
  EnrolmentState,
  EnrolmentToken
} from './models';
import {
  labelApproved,
  labelCompleted,
  labelEnrolled,
  labelExpired,
  labelPending,
  labelRejected,
  labelRevoked,
  labelUsedUp,
  labelValid,
  labelWaitsForApproval
} from './translatedLabels';

export const stateLabels: Record<EnrolmentState, string> = {
  approved: labelApproved,
  completed: labelCompleted,
  enrolled: labelEnrolled,
  pending: labelPending,
  rejected: labelRejected,
  revoked: labelRevoked
};

export const getStateLabel = (enrolment: Enrolment): string =>
  enrolment.state === 'pending' && enrolment.approval_required
    ? labelWaitsForApproval
    : stateLabels[enrolment.state];

/** Approval is asked only of the pending enrolments of a token that requires it. */
export const canApprove = (enrolment: Enrolment): boolean =>
  enrolment.state === 'pending' && enrolment.approval_required;

/** A collector that has no poller yet can be refused. */
export const canReject = (enrolment: Enrolment): boolean =>
  enrolment.state === 'pending' || enrolment.state === 'approved';

/** Only a collector bound to its poller has a credential to rotate. */
export const canRotate = (enrolment: Enrolment): boolean =>
  enrolment.poller_id !== null &&
  (enrolment.state === 'enrolled' || enrolment.state === 'completed');

export const getTokenStatus = (token: EnrolmentToken): string => {
  if (token.expired) {
    return labelExpired;
  }

  return token.spent ? labelUsedUp : labelValid;
};

export const formatUses = (token: EnrolmentToken): string =>
  token.max_uses === null
    ? `${token.uses}`
    : `${token.uses} / ${token.max_uses}`;

export const formatDate = (date: string | null): string =>
  date ? new Date(date).toLocaleString() : '-';

export const defaultCreateForm: CreateForm = {
  approvalRequired: false,
  expiresInHours: 24,
  kind: 'named',
  maxUses: '',
  name: ''
};

/** Body of the creation; null when the form is not complete. */
export const buildCreatePayload = (form: CreateForm): CreatePayload | null => {
  const hours = Number(form.expiresInHours);
  if (!Number.isInteger(hours) || hours < 1 || hours > 720) {
    return null;
  }
  const expiresIn = hours * 3600;
  if (form.kind === 'named') {
    const name = form.name.trim();

    return /^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/.test(name)
      ? { expires_in: expiresIn, name }
      : null;
  }
  const payload: CreatePayload = { expires_in: expiresIn, fleet: true };
  if (form.maxUses.trim() !== '') {
    const maxUses = Number(form.maxUses);
    if (!Number.isInteger(maxUses) || maxUses < 1) {
      return null;
    }
    payload.max_uses = maxUses;
  }
  if (form.approvalRequired) {
    payload.approval_required = true;
  }

  return payload;
};
