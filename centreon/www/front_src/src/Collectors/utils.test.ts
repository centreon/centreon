import type { Enrolment, EnrolmentToken } from './models';
import {
  buildCreatePayload,
  canApprove,
  canReject,
  canRotate,
  defaultCreateForm,
  formatUses,
  getStateLabel,
  getTokenStatus
} from './utils';

const enrolment = (changes: Partial<Enrolment>): Enrolment => ({
  address: '10.0.0.1',
  agent_version: '0.1.0',
  approval_required: false,
  created_at: null,
  id: 'e1',
  kind: 'fleet',
  name: 'edge-01',
  poller_id: null,
  poller_name: null,
  state: 'pending',
  token_id: 't1',
  updated_at: null,
  ...changes
});

const token = (changes: Partial<EnrolmentToken>): EnrolmentToken => ({
  approval_required: false,
  created_at: null,
  expired: false,
  expires_at: null,
  id: 't1',
  kind: 'fleet',
  last_used_at: null,
  max_uses: null,
  name: null,
  poller_id: null,
  poller_name: null,
  spent: false,
  uses: 0,
  ...changes
});

describe('collector enrolment actions', () => {
  it('asks for approval only of pending enrolments of a token that requires it', () => {
    const waiting = enrolment({ approval_required: true });

    expect(canApprove(waiting)).toBe(true);
    expect(getStateLabel(waiting)).toBe('Waits for approval');
    expect(canApprove(enrolment({}))).toBe(false);
    expect(
      canApprove(enrolment({ approval_required: true, state: 'approved' }))
    ).toBe(false);
  });

  it('rejects collectors without poller only', () => {
    expect(canReject(enrolment({}))).toBe(true);
    expect(canReject(enrolment({ state: 'approved' }))).toBe(true);
    expect(canReject(enrolment({ poller_id: 3, state: 'enrolled' }))).toBe(
      false
    );
  });

  it('rotates the credential of bound collectors only', () => {
    expect(canRotate(enrolment({ poller_id: 3, state: 'completed' }))).toBe(
      true
    );
    expect(canRotate(enrolment({ poller_id: 3, state: 'revoked' }))).toBe(
      false
    );
    expect(canRotate(enrolment({ state: 'pending' }))).toBe(false);
  });
});

describe('enrolment tokens', () => {
  it('tells expired, used up and valid tokens apart', () => {
    expect(getTokenStatus(token({ expired: true, spent: true }))).toBe(
      'Expired'
    );
    expect(getTokenStatus(token({ spent: true }))).toBe('Used up');
    expect(getTokenStatus(token({}))).toBe('Valid');
    expect(formatUses(token({ max_uses: 3, uses: 1 }))).toBe('1 / 3');
    expect(formatUses(token({ uses: 4 }))).toBe('4');
  });
});

describe('creation payload', () => {
  it('creates a named collector', () => {
    expect(
      buildCreatePayload({ ...defaultCreateForm, name: ' collector-1 ' })
    ).toEqual({ expires_in: 86400, name: 'collector-1' });
    expect(buildCreatePayload({ ...defaultCreateForm, name: 'bad name' })).toBe(
      null
    );
  });

  it('creates a fleet token with uses and approval', () => {
    expect(
      buildCreatePayload({
        ...defaultCreateForm,
        approvalRequired: true,
        expiresInHours: 2,
        kind: 'fleet',
        maxUses: '5'
      })
    ).toEqual({
      approval_required: true,
      expires_in: 7200,
      fleet: true,
      max_uses: 5
    });
    expect(buildCreatePayload({ ...defaultCreateForm, kind: 'fleet' })).toEqual(
      { expires_in: 86400, fleet: true }
    );
    expect(
      buildCreatePayload({ ...defaultCreateForm, kind: 'fleet', maxUses: '0' })
    ).toBe(null);
    expect(
      buildCreatePayload({ ...defaultCreateForm, expiresInHours: 0, name: 'a' })
    ).toBe(null);
  });
});
