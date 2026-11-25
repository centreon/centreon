import { buildListingDecoder, type ListingModel } from '@centreon/ui';

import { isNil } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

// API Platform leaves null values out of the response, so every nullable
// field is optional too

import {
  Action,
  type ActivityLog,
  type Actor,
  type Change,
  type Target,
  type TargetParent
} from '../models';

const targetParentDecoder = JsonDecoder.object<TargetParent>(
  {
    names: JsonDecoder.array(JsonDecoder.string, 'Parent names'),
    type: JsonDecoder.string
  },
  'Target parent'
);

const targetDecoder = JsonDecoder.object<Target>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    parent: JsonDecoder.optional(JsonDecoder.nullable(targetParentDecoder)),
    type: JsonDecoder.string
  },
  'Target'
);

const actorDecoder = JsonDecoder.object<Actor>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string))
  },
  'Actor'
);

const changeDecoder = JsonDecoder.object<Change>(
  {
    after: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    before: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    field: JsonDecoder.string
  },
  'Change'
);

const activityLogDecoder = JsonDecoder.object<ActivityLog>(
  {
    action: JsonDecoder.enumeration<Action>(Action, 'Action'),
    actor: JsonDecoder.optional(JsonDecoder.nullable(actorDecoder)),
    date: JsonDecoder.string,
    hasChanges: JsonDecoder.boolean,
    id: JsonDecoder.number,
    target: targetDecoder,
    tokenName: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string))
  },
  'Activity log',
  { hasChanges: 'has_changes', tokenName: 'token_name' }
);

const namedActorDecoder = JsonDecoder.object<{ id: number; name: string }>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Named actor'
);

// The logs are not counted: the page only tells whether another one follows,
// through the "next" link of its Hydra view
export const activityLogsDecoder = JsonDecoder.object<{
  member: Array<ActivityLog>;
  view?: { next?: string };
}>(
  {
    member: JsonDecoder.array(activityLogDecoder, 'Activity logs'),
    view: JsonDecoder.optional(
      JsonDecoder.object<{ next?: string }>(
        { next: JsonDecoder.optional(JsonDecoder.string) },
        'View'
      )
    )
  },
  'Activity logs page'
).map<ListingModel<ActivityLog>>(({ member, view }) => ({
  meta: {
    hasNextPage: !isNil(view?.next),
    limit: member.length,
    page: 1,
    total: member.length
  },
  result: member
}));

export const changesDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: changeDecoder,
  entityDecoderName: 'Change',
  listingDecoderName: 'Changes'
});

export const actorsDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: namedActorDecoder,
  entityDecoderName: 'Actor',
  listingDecoderName: 'Actors'
});

const tokenDecoder = JsonDecoder.object<{ name: string }>(
  {
    name: JsonDecoder.string
  },
  'Token'
);

export const tokensDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: tokenDecoder,
  entityDecoderName: 'Token',
  listingDecoderName: 'Tokens'
});
