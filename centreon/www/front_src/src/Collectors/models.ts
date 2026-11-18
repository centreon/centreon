export type EnrolmentState =
  | 'pending'
  | 'approved'
  | 'enrolled'
  | 'completed'
  | 'rejected'
  | 'revoked';

export type EnrolmentKind = 'named' | 'fleet';

/** One collector that used a token, as GET /configuration/collectors/enrolments lists it. */
export interface Enrolment {
  address: string;
  agent_version: string;
  approval_required: boolean;
  created_at: string | null;
  id: string;
  kind: EnrolmentKind;
  name: string;
  poller_id: number | null;
  poller_name: string | null;
  state: EnrolmentState;
  token_id: string;
  updated_at: string | null;
}

/** A token, never its value, as GET /configuration/collectors/enrolment-tokens lists it. */
export interface EnrolmentToken {
  approval_required: boolean;
  created_at: string | null;
  expired: boolean;
  expires_at: string | null;
  id: string;
  kind: EnrolmentKind;
  last_used_at: string | null;
  max_uses: number | null;
  name: string | null;
  poller_id: number | null;
  poller_name: string | null;
  spent: boolean;
  uses: number;
}

export interface Listing<T> {
  result: Array<T>;
}

/** Form of the creation dialog. */
export interface CreateForm {
  approvalRequired: boolean;
  expiresInHours: number;
  kind: EnrolmentKind;
  maxUses: string;
  name: string;
}

/** Body of POST /configuration/collectors/enrolments. */
export interface CreatePayload {
  approval_required?: boolean;
  expires_in: number;
  fleet?: boolean;
  max_uses?: number;
  name?: string;
}

/** Answer of the creation: the only place the token appears. */
export interface CreatedEnrolment {
  command: string;
  expires_at: string;
  id: string;
  max_uses: number | null;
  poller_id: number | null;
  poller_name: string | null;
  token: string;
  type: EnrolmentKind;
}
